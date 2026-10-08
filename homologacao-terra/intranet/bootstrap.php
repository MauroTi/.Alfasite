<?php
declare(strict_types=1);

const AUTH_DATA_DIR = '';
const AUTH_DATABASE_FILE = '';
const AUTH_CA_FILE = '';
const AUTH_SESSION_NAME = 'ALFATEKSESSID';
const AUTH_SESSION_IDLE_SECONDS = 3600;
const AUTH_FIREBASE_CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
function auth_env(string $name, string $default = ''): string
{
    $value = getenv($name);
    return $value === false ? $default : trim($value);
}

function auth_private_config_path(): string
{
    $path = auth_env('ALFATEK_AUTH_CONFIG');
    return $path !== '' && is_file($path) && is_readable($path) ? $path : '';
}

function auth_private_config(): array
{
    static $config = null;
    if (is_array($config)) return $config;
    $path = auth_private_config_path();
    if ($path === '') throw new RuntimeException('Configure ALFATEK_AUTH_CONFIG apontando para um arquivo JSON privado fora da pasta pública.');
    $decoded = json_decode((string) file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Arquivo de configuração privado inválido.');
    return $config = $decoded;
}

function auth_data_dir(): string
{
    return rtrim((string) (auth_private_config()['data_dir'] ?? ''), '/\\');
}

function auth_database_file(): string
{
    $path = auth_private_config_path();
    $candidate = (string) (auth_private_config()['database_file'] ?? '');
    if ($candidate !== '' && is_file($candidate) && is_readable($candidate)) return $candidate;
    return $path;
}

function auth_ca_file(): string
{
    $configured = (string) (auth_private_config()['ca_file'] ?? '');
    return $configured !== '' && is_file($configured) ? $configured : '';
}

function auth_firebase_service_file(): string
{
    $configured = (string) (auth_private_config()['firebase_service_file'] ?? '');
    return $configured !== '' && is_file($configured) && is_readable($configured) ? $configured : '';
}

function auth_firebase_project_id(): string
{
    return auth_env('ALFATEK_FIREBASE_PROJECT_ID', (string) (auth_private_config()['firebase_project_id'] ?? ''));
}

function auth_firebase_api_key(): string
{
    return auth_env('ALFATEK_FIREBASE_API_KEY', (string) (auth_private_config()['firebase_api_key'] ?? ''));
}

function auth_base_path(): string
{
    $base = trim(auth_env('ALFATEK_BASE_PATH', '/'), '/');
    return $base === '' ? '/' : '/' . $base . '/';
}

function auth_path(string $relative): string
{
    return auth_base_path() . ltrim($relative, '/');
}
const AUTH_ROLE_LEVELS = ['employee' => 1, 'leader' => 2, 'supervisor' => 3, 'admin' => 4, 'superadmin' => 5];
const AUTH_ROLE_LABELS = ['employee' => 'Funcionário', 'leader' => 'Líder', 'supervisor' => 'Supervisor', 'admin' => 'Administrador', 'superadmin' => 'Superadministrador'];

function auth_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function auth_is_loopback(string $address): bool
{
    $packed = @inet_pton($address);
    if ($packed === false) {
        return false;
    }
    return $packed === inet_pton('127.0.0.1') || $packed === inet_pton('::1');
}

function auth_is_alfatek_lan_client(string $address): bool
{
    $packed = @inet_pton($address);
    return is_string($packed) && strlen($packed) === 4 && substr($packed, 0, 3) === "\xC0\xA8\xC8";
}

function auth_require_local_request(bool $checkOrigin = false): void
{
    $isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $configuredHost = strtolower(auth_env('ALFATEK_PUBLIC_HOST'));
    if (!$isHttps || $configuredHost === '' || !hash_equals($configuredHost, preg_replace('/:\\d+$/', '', $host))) {
        auth_json(403, ['message' => 'A autenticação de homologação exige o host autorizado e HTTPS válido.']);
    }

    if ($checkOrigin) {
        $origin = strtolower((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $expectedOrigin = 'https://' . $configuredHost;
        if ($origin !== $expectedOrigin) {
            auth_json(403, ['message' => 'Origem de autenticação inválida. Abra o portal pelo HTTPS autorizado.']);
        }
    }
}

function auth_database(): PDO
{
    static $connection = null;
    if ($connection instanceof PDO) {
        return $connection;
    }

    $settings = auth_private_config();
    $host = (string) ($settings['db_host'] ?? '');
    $database = (string) ($settings['db_name'] ?? '');
    $username = (string) ($settings['db_user'] ?? '');
    $password = (string) ($settings['db_password'] ?? '');
    if ($host === '' || !preg_match('/^[A-Za-z0-9_]+$/', $database) || $username === '' || $password === '') throw new RuntimeException('Configure acesso MySQL de homologação no arquivo privado.');

    $connection = new PDO(
        "mysql:host=" . $host . ";port=" . max(1, (int) ($settings['db_port'] ?? 3306)) . ";dbname=" . $database . ";charset=utf8mb4",
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC],
    );
    return $connection;
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $sessionPath = auth_data_dir() . DIRECTORY_SEPARATOR . 'sessions';
    if (auth_data_dir() === '' || !is_dir($sessionPath) || !is_writable($sessionPath)) throw new RuntimeException('Configure uma pasta privada gravável para sessões fora da pasta pública.');

    session_name(AUTH_SESSION_NAME);
    session_save_path($sessionPath);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) AUTH_SESSION_IDLE_SECONDS);
    $secureCookie = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => auth_path('intranet/'),
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    if (!session_start()) {
        throw new RuntimeException('Não foi possível iniciar a sessão local.');
    }
    header('Cache-Control: no-store, private');
    header('X-Content-Type-Options: nosniff');
}

function auth_require_csrf(): void
{
    $expected = (string) ($_SESSION['csrf_token'] ?? '');
    $provided = (string) ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if ($expected === '' || $provided === '' || !hash_equals($expected, $provided)) {
        auth_json(403, ['message' => 'A sessão do formulário expirou. Atualize a página e tente novamente.']);
    }
}

function auth_decode_base64url(string $value): string
{
    $decoded = base64_decode(strtr($value, '-_', '+/') . str_repeat('=', (4 - strlen($value) % 4) % 4), true);
    if ($decoded === false) {
        throw new RuntimeException('Token inválido.');
    }
    return $decoded;
}

function auth_firebase_public_keys(): array
{
    $cacheFile = auth_data_dir() . DIRECTORY_SEPARATOR . 'firebase-public-keys.json';
    $cached = null;
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && (int) ($cached['expires_at'] ?? 0) > time() && is_array($cached['keys'] ?? null)) {
            return $cached['keys'];
        }
    }

    $curl = curl_init(AUTH_FIREBASE_CERTS_URL);
    $caFile = auth_ca_file();
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HEADER => true,
    ]);
    if ($caFile !== '') curl_setopt($curl, CURLOPT_CAINFO, $caFile);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    if (!is_string($response) || $status !== 200) {
        throw new RuntimeException('Falha ao consultar as chaves de assinatura Firebase.');
    }

    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $keys = json_decode($body, true);
    if (!is_array($keys) || $keys === []) {
        throw new RuntimeException('Resposta inválida das chaves de assinatura Firebase.');
    }
    preg_match('/(?:^|\r\n)Cache-Control:\s*[^\r\n]*max-age=(\d+)/i', $headers, $maxAgeMatch);
    $maxAge = min(max((int) ($maxAgeMatch[1] ?? 3600), 60), 86400);
    $payload = json_encode(['expires_at' => time() + $maxAge, 'keys' => $keys], JSON_UNESCAPED_SLASHES);
    if ($payload !== false) {
        file_put_contents($cacheFile, $payload, LOCK_EX);
    }
    return $keys;
}

function auth_verify_firebase_id_token(string $token): array
{
    if ($token === '' || strlen($token) > 12000) {
        throw new InvalidArgumentException('Entre novamente para atualizar o token de autenticação.');
    }
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new InvalidArgumentException('Token Firebase inválido.');
    }
    [$encodedHeader, $encodedClaims, $encodedSignature] = $parts;
    $header = json_decode(auth_decode_base64url($encodedHeader), true, 16, JSON_THROW_ON_ERROR);
    $claims = json_decode(auth_decode_base64url($encodedClaims), true, 32, JSON_THROW_ON_ERROR);
    if (($header['alg'] ?? null) !== 'RS256' || !is_string($header['kid'] ?? null)) {
        throw new InvalidArgumentException('Cabeçalho do token Firebase inválido.');
    }
    $keys = auth_firebase_public_keys();
    if (!is_string($keys[$header['kid']] ?? null)) {
        throw new InvalidArgumentException('Chave de assinatura Firebase desconhecida.');
    }
    $publicKey = openssl_pkey_get_public($keys[$header['kid']]);
    $signature = auth_decode_base64url($encodedSignature);
    if ($publicKey === false || openssl_verify("$encodedHeader.$encodedClaims", $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
        throw new InvalidArgumentException('Assinatura do token Firebase inválida.');
    }

    $now = time();
    $email = strtolower(trim((string) ($claims['email'] ?? '')));
    $projectId = auth_firebase_project_id();
    if ($projectId === '' || ($claims['aud'] ?? null) !== $projectId
        || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/' . $projectId
        || !is_string($claims['sub'] ?? null) || $claims['sub'] === '' || strlen($claims['sub']) > 128
        || (int) ($claims['exp'] ?? 0) <= $now
        || (int) ($claims['iat'] ?? PHP_INT_MAX) > $now + 60
        || (int) ($claims['auth_time'] ?? PHP_INT_MAX) > $now + 60
        || ($claims['email_verified'] ?? false) !== true
        || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new InvalidArgumentException('O token expirou ou não atende aos requisitos da conta Alfatek.');
    }
    $identities = $claims['firebase']['identities'] ?? [];
    $providers = is_array($identities) ? array_values(array_filter(array_keys($identities), 'is_string')) : [];
    return ['uid' => $claims['sub'], 'email' => $email, 'name' => (string) ($claims['name'] ?? ''), 'providers' => $providers];
}

function auth_current_user(): ?array
{
    auth_start_session();
    if (!is_string($_SESSION['uid'] ?? null) || !is_string($_SESSION['email'] ?? null) || !is_string($_SESSION['role'] ?? null)) {
        return null;
    }
    $lastActivity = (int) ($_SESSION['last_activity'] ?? 0);
    if ($lastActivity === 0 || time() - $lastActivity > AUTH_SESSION_IDLE_SECONDS) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    $authorized = auth_database()->prepare('SELECT role, require_linked_methods FROM authorized_users WHERE firebase_uid = :uid AND email = :email AND active = 1 LIMIT 1');
    $authorized->execute(['uid' => $_SESSION['uid'], 'email' => $_SESSION['email']]);
    $currentUser = $authorized->fetch();
    if (!is_array($currentUser)
        || ((int) $currentUser['require_linked_methods'] === 1 && !auth_session_has_linked_methods())) {
        $_SESSION = [];
        session_destroy();
        return null;
    }
    // Recarrega o papel confiável do banco sem invalidar a sessão quando um administrador o altera.
    $_SESSION['role'] = (string) $currentUser['role'];
    $_SESSION['last_activity'] = time();
    return ['uid' => $_SESSION['uid'], 'email' => $_SESSION['email'], 'name' => (string) ($_SESSION['name'] ?? ''), 'role' => (string) $currentUser['role']];
}

function auth_session_has_linked_methods(): bool
{
    $providers = $_SESSION['providers'] ?? [];
    return is_array($providers) && in_array('google.com', $providers, true) && in_array('email', $providers, true);
}

function auth_role_level(string $role): int
{
    return AUTH_ROLE_LEVELS[$role] ?? 0;
}

function auth_user_can_assign_role(array $actor, string $targetRole): bool
{
    return in_array(($actor['role'] ?? ''), ['admin', 'superadmin'], true)
        && auth_role_level($targetRole) > 0;
}

function auth_require_user(?string $role = null): array
{
    auth_require_local_request();
    $user = auth_current_user();
    if ($user === null) {
        header('Location: ' . auth_path('intranet/index.html'), true, 302);
        exit;
    }
    if ($role !== null && (auth_role_level($role) === 0 || auth_role_level((string) $user['role']) < auth_role_level($role))) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo 'Acesso não autorizado.';
        exit;
    }
    return $user;
}
