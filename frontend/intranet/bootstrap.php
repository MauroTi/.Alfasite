<?php
declare(strict_types=1);

const AUTH_DATA_DIR = 'C:/ProgramData/AlfatekAuth';
const AUTH_DATABASE_FILE = AUTH_DATA_DIR . '/database.json';
const AUTH_CA_FILE = AUTH_DATA_DIR . '/trusted-ca.pem';
const AUTH_SESSION_NAME = 'ALFATEKSESSID';
const AUTH_SESSION_PATH = AUTH_DATA_DIR . '/sessions';
const AUTH_SESSION_IDLE_SECONDS = 3600;
const AUTH_FIREBASE_PROJECT_ID = 'alfatek-portal';
const AUTH_FIREBASE_CERTS_URL = 'https://www.googleapis.com/robot/v1/metadata/x509/securetoken@system.gserviceaccount.com';
const AUTH_ALLOWED_HOSTS = ['localhost', 'localhost:80', 'localhost:443', '192.168.200.61', '192.168.200.61:443'];
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
    $host = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    $remoteAddress = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $isHttps = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    $isLoopbackRequest = in_array($host, ['localhost', 'localhost:80', 'localhost:443'], true) && auth_is_loopback($remoteAddress);
    $isLanRequest = in_array($host, ['192.168.200.61', '192.168.200.61:443'], true) && auth_is_alfatek_lan_client($remoteAddress) && $isHttps;
    if (!$isLoopbackRequest && !$isLanRequest) {
        auth_json(403, ['message' => 'Use localhost neste computador ou HTTPS pela intranet Alfatek autorizada.']);
    }

    if ($checkOrigin) {
        $origin = strtolower((string) ($_SERVER['HTTP_ORIGIN'] ?? ''));
        $allowedOrigins = ['http://localhost', 'http://localhost:80', 'https://localhost', 'https://192.168.200.61'];
        if (!$isLanRequest && !in_array($origin, $allowedOrigins, true) || $isLanRequest && $origin !== 'https://192.168.200.61') {
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

    if (!is_file(AUTH_DATABASE_FILE)) {
        throw new RuntimeException('Banco local ainda não provisionado. Execute scripts/setup-local-auth-mysql.ps1.');
    }

    $settings = json_decode((string) file_get_contents(AUTH_DATABASE_FILE), true, 16, JSON_THROW_ON_ERROR);
    $host = (string) ($settings['host'] ?? '');
    $database = (string) ($settings['database'] ?? '');
    $username = (string) ($settings['username'] ?? '');
    $password = (string) ($settings['password'] ?? '');
    if ($host !== '127.0.0.1' || $database !== 'alfatek_auth' || $username !== 'alfatek_auth_app' || $password === '') {
        throw new RuntimeException('Configuração local do banco inválida.');
    }

    $connection = new PDO(
        "mysql:host=127.0.0.1;port=3306;dbname=alfatek_auth;charset=utf8mb4",
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
    if (!is_dir(AUTH_SESSION_PATH) || !is_writable(AUTH_SESSION_PATH)) {
        throw new RuntimeException('Pasta privada de sessões não está preparada. Execute scripts/setup-local-auth-mysql.ps1.');
    }

    session_name(AUTH_SESSION_NAME);
    session_save_path(AUTH_SESSION_PATH);
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', (string) AUTH_SESSION_IDLE_SECONDS);
    $secureCookie = (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/Alfatek/intranet/',
        'secure' => $secureCookie,
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
    $cacheFile = AUTH_DATA_DIR . '/firebase-public-keys.json';
    $cached = null;
    if (is_file($cacheFile)) {
        $cached = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cached) && (int) ($cached['expires_at'] ?? 0) > time() && is_array($cached['keys'] ?? null)) {
            return $cached['keys'];
        }
    }

    $curl = curl_init(AUTH_FIREBASE_CERTS_URL);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_CAINFO => AUTH_CA_FILE,
        CURLOPT_HEADER => true,
    ]);
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
    if (($claims['aud'] ?? null) !== AUTH_FIREBASE_PROJECT_ID
        || ($claims['iss'] ?? null) !== 'https://securetoken.google.com/' . AUTH_FIREBASE_PROJECT_ID
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
        header('Location: index.html', true, 302);
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
