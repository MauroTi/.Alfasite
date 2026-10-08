[CmdletBinding()]
param()

$ErrorActionPreference = 'Stop'
$mysqlConfigEditor = 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql_config_editor.exe'
$loginPath = 'alfatek-auth-bootstrap'
if (-not (Test-Path -LiteralPath $mysqlConfigEditor)) {
    throw 'MySQL 8.0 client tools were not found at the expected local path.'
}
Write-Host 'Enter the MySQL root password. It will be passed to mysql_config_editor through a private redirected input stream and removed after provisioning.'
$rootPassword = Read-Host 'MySQL root password' -AsSecureString
$rootPasswordBstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($rootPassword)
$plainRootPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($rootPasswordBstr)
[Runtime.InteropServices.Marshal]::ZeroFreeBSTR($rootPasswordBstr)

try {
    $startInfo = [System.Diagnostics.ProcessStartInfo]::new()
    $startInfo.FileName = $mysqlConfigEditor
    $startInfo.Arguments = "set --login-path=$loginPath --host=127.0.0.1 --port=3306 --user=root --password"
    $startInfo.UseShellExecute = $false
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true
    $process = [System.Diagnostics.Process]::new()
    $process.StartInfo = $startInfo
    if (-not $process.Start()) { throw 'Could not start MySQL login-path setup.' }
    $process.StandardInput.WriteLine($plainRootPassword)
    $process.StandardInput.Close()
    $stdout = $process.StandardOutput.ReadToEnd()
    $stderr = $process.StandardError.ReadToEnd()
    $process.WaitForExit()
    if ($process.ExitCode -ne 0) {
        throw "MySQL login path setup failed. No Alfatek database was created. $($stderr.Trim())"
    }
    & $PSScriptRoot\setup-local-auth-mysql.ps1 -LoginPath $loginPath
}
finally {
    & $mysqlConfigEditor remove --login-path=$loginPath 2>$null
    $plainRootPassword = $null
    $rootPassword.Dispose()
}
