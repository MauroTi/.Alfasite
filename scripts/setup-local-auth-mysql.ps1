[CmdletBinding()]
param(
    [string] $LoginPath = 'alfatek-auth-bootstrap',
    [string] $RootPassword = $env:ALFATEK_MYSQL_ROOT_PASSWORD
)

$ErrorActionPreference = 'Stop'
$mysqlExe = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe'
$mysqlConfigEditor = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql_config_editor.exe'
$privateRoot = 'C:\ProgramData\AlfatekAuth'
$sessionsPath = Join-Path $privateRoot 'sessions'
$databaseConfig = Join-Path $privateRoot 'database.json'

if (-not (Test-Path -LiteralPath $mysqlExe) -or -not (Test-Path -LiteralPath $mysqlConfigEditor)) {
    throw 'MySQL 8.0 client tools were not found at the expected local installation path.'
}
if (Test-Path -LiteralPath $privateRoot) {
    if (Test-Path -LiteralPath $databaseConfig) {
        throw "Private database configuration '$databaseConfig' already exists. Nothing was overwritten."
    }
    $existingChildren = @(Get-ChildItem -LiteralPath $privateRoot -Force | Where-Object { $_.Name -notin @('sessions', 'trusted-ca.pem', 'firebase-public-keys.json') })
    if ($existingChildren.Count -gt 0) {
        throw "Private local data folder '$privateRoot' already contains other data. Inspect it before running setup; nothing was overwritten."
    }
}
if ((Get-Service -Name MySQL80).Status -ne 'Running') {
    throw 'The local MySQL80 service is not running.'
}
if (Test-Path -LiteralPath $databaseConfig) {
    throw 'The local application database configuration already exists. Nothing was changed.'
}

if ([string]::IsNullOrWhiteSpace($RootPassword)) {
    $probe = & $mysqlConfigEditor print --login-path=$LoginPath 2>&1
    if ($LASTEXITCODE -ne 0) {
        throw "MySQL login path '$LoginPath' is not configured for this Windows account. Configure it interactively with mysql_config_editor first."
    }
}

function Invoke-LocalMySql([string] $Query) {
    if ([string]::IsNullOrWhiteSpace($RootPassword)) {
        & $mysqlExe --login-path=$LoginPath --host=127.0.0.1 --port=3306 --batch --skip-column-names --execute=$Query
    } else {
        $previousPassword = $env:MYSQL_PWD
        try {
            $env:MYSQL_PWD = $RootPassword
            & $mysqlExe --host=127.0.0.1 --port=3306 --user=root --batch --skip-column-names --execute=$Query
        } finally {
            if ($null -eq $previousPassword) { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue } else { $env:MYSQL_PWD = $previousPassword }
        }
    }
}

$databaseExists = Invoke-LocalMySql "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='alfatek_auth';" 2>$null
if ($LASTEXITCODE -ne 0) {
    throw 'MySQL administrative login failed. No existing data was modified.'
}
if ([string]$databaseExists -match '^\s*1\s*$') {
    throw 'Database alfatek_auth already exists. Nothing was overwritten.'
}

$applicationUserExists = Invoke-LocalMySql "SELECT COUNT(*) FROM mysql.user WHERE user='alfatek_auth_app' AND host='127.0.0.1';" 2>$null
if ($LASTEXITCODE -ne 0) {
    throw 'Could not verify whether the application database user already exists. No existing data was modified.'
}
if ([string]$applicationUserExists -match '^\s*1\s*$') {
    throw "MySQL user 'alfatek_auth_app'@'127.0.0.1' already exists. Nothing was overwritten."
}

New-Item -ItemType Directory -Path $sessionsPath -Force | Out-Null
$currentIdentity = [System.Security.Principal.WindowsIdentity]::GetCurrent().Name
& icacls.exe $privateRoot /inheritance:r /grant:r '*S-1-5-18:(OI)(CI)(F)' '*S-1-5-32-544:(OI)(CI)(F)' "${currentIdentity}:(OI)(CI)(F)" | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'Could not restrict permissions on the local authentication data directory.' }

$randomBytes = New-Object byte[] 32
$randomGenerator = [Security.Cryptography.RandomNumberGenerator]::Create()
$randomGenerator.GetBytes($randomBytes)
$applicationPassword = [BitConverter]::ToString($randomBytes).Replace('-', '')
$randomGenerator.Dispose()
$sql = @"
CREATE DATABASE alfatek_auth CHARACTER SET utf8mb4 COLLATE utf8mb4_0900_ai_ci;
CREATE USER 'alfatek_auth_app'@'127.0.0.1' IDENTIFIED BY '$applicationPassword';
GRANT SELECT, INSERT, UPDATE, DELETE ON alfatek_auth.* TO 'alfatek_auth_app'@'127.0.0.1';
USE alfatek_auth;
CREATE TABLE authorized_users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  firebase_uid VARCHAR(128) NULL UNIQUE,
  email VARCHAR(254) NOT NULL UNIQUE,
  role ENUM('employee', 'leader', 'supervisor', 'admin', 'superadmin') NOT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  require_linked_methods TINYINT(1) NOT NULL DEFAULT 1,
  created_by VARCHAR(254) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  last_login_at TIMESTAMP NULL DEFAULT NULL,
  CONSTRAINT chk_authorized_email_lower CHECK (email = LOWER(email))
) ENGINE=InnoDB;
CREATE TABLE auth_audit_log (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  firebase_uid VARCHAR(128) NULL,
  email VARCHAR(254) NOT NULL,
  actor_email VARCHAR(254) NULL,
  target_email VARCHAR(254) NULL,
  event_type ENUM('login_succeeded', 'login_denied', 'logout', 'employee_created', 'employee_updated', 'employee_deactivated', 'employee_reactivated', 'employee_password_reset_requested', 'employee_firebase_account_created', 'employee_password_set_by_admin') NOT NULL,
  details_json JSON NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auth_audit_email_created (email, created_at),
  INDEX idx_auth_audit_actor_created (actor_email, created_at)
) ENGINE=InnoDB;
-- The database starts with no authorized accounts. Provision named users deliberately through the authenticated administration flow.
"@

$mysqlArguments = @('--host=127.0.0.1', '--port=3306', '--user=root', '--batch', '--show-warnings')
$startInfo = [System.Diagnostics.ProcessStartInfo]::new()
$startInfo.FileName = $mysqlExe
$startInfo.Arguments = $mysqlArguments -join ' '
$startInfo.UseShellExecute = $false
$startInfo.RedirectStandardInput = $true
$startInfo.RedirectStandardOutput = $true
$startInfo.RedirectStandardError = $true
if (-not [string]::IsNullOrWhiteSpace($RootPassword)) { $startInfo.Environment['MYSQL_PWD'] = $RootPassword }
$mysqlProcess = [System.Diagnostics.Process]::new()
$mysqlProcess.StartInfo = $startInfo
if (-not $mysqlProcess.Start()) { throw 'Could not start the local MySQL client.' }
$mysqlProcess.StandardInput.Write($sql)
$mysqlProcess.StandardInput.Close()
$mysqlOutput = $mysqlProcess.StandardOutput.ReadToEnd()
$mysqlError = $mysqlProcess.StandardError.ReadToEnd()
$mysqlProcess.WaitForExit()
if ($mysqlProcess.ExitCode -ne 0) {
    throw "MySQL schema creation failed. Existing MySQL data was not intentionally modified. $($mysqlError.Trim())"
}

$config = @{
    host = '127.0.0.1'
    port = 3306
    database = 'alfatek_auth'
    username = 'alfatek_auth_app'
    password = $applicationPassword
} | ConvertTo-Json
[System.IO.File]::WriteAllText($databaseConfig, $config, [System.Text.UTF8Encoding]::new($false))
& icacls.exe $databaseConfig /inheritance:r /grant:r '*S-1-5-18:(F)' '*S-1-5-32-544:(F)' "${currentIdentity}:(F)" | Out-Null
if ($LASTEXITCODE -ne 0) { throw 'The MySQL schema exists but private application credentials could not be protected.' }

Write-Output 'Created the local alfatek_auth database, least-privilege MySQL application account, private session directory, and initial allowlist.'
Write-Output 'The existing MySQL databases were not modified.'
