<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');

require_once __DIR__ . '/bootstrap.php';

try {
    auth_require_local_request(in_array($_SERVER['REQUEST_METHOD'] ?? '', ['POST'], true));
    $action = (string) ($_GET['action'] ?? '');
    if (in_array($action, ['employee-password-reset', 'employee-password-set'], true)
        && auth_env('ALFATEK_ALLOW_FIREBASE_ADMIN') !== '1') {
        auth_json(503, ['message' => 'Operações administrativas Firebase estão bloqueadas até habilitar explicitamente o projeto de teste.']);
    }

    if ($action === 'csrf' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        auth_database()->query('SELECT id FROM authorized_users LIMIT 0');
        auth_start_session();
        if (!is_string($_SESSION['csrf_token'] ?? null)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        auth_json(200, ['csrfToken' => $_SESSION['csrf_token']]);
    }

    if ($action === 'me' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $user = auth_current_user();
        if ($user === null) {
            auth_json(401, ['authenticated' => false]);
        }
        if (!is_string($_SESSION['csrf_token'] ?? null)) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        auth_json(200, ['authenticated' => true, 'user' => ['email' => $user['email'], 'name' => $user['name'], 'role' => $user['role']], 'csrfToken' => $_SESSION['csrf_token']]);
    }

    if ($action === 'employees' && $_SERVER['REQUEST_METHOD'] === 'GET') {
        $actor = auth_require_user('admin');
        $statement = auth_database()->query('SELECT id, firebase_uid, email, role, active, created_by, created_at, updated_at, last_login_at FROM authorized_users ORDER BY active DESC, email ASC');
        $rows = $statement->fetchAll();
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['active'] = (bool) $row['active'];
            $row['canManage'] = auth_user_can_assign_role($actor, (string) $row['role']) && ($actor['email'] !== $row['email'] || $actor['role'] === 'superadmin');
        }
        unset($row);
        auth_json(200, ['employees' => $rows, 'actorRole' => $actor['role']]);
    }

    if ($action === 'employee-password-reset' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $actor = auth_require_user('admin');
        auth_start_session();
        auth_require_csrf();
        $request = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($request)) {
            auth_json(400, ['message' => 'Dados inválidos.']);
        }
        $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            auth_json(400, ['message' => 'Identificador de funcionário inválido.']);
        }

        $pdo = auth_database();
        $find = $pdo->prepare('SELECT id, firebase_uid, email, role FROM authorized_users WHERE id = :id');
        $find->execute(['id' => $id]);
        $target = $find->fetch();
        if (!is_array($target)
            || !auth_user_can_assign_role($actor, (string) $target['role'])
            || ($actor['email'] === $target['email'] && $actor['role'] !== 'superadmin')) {
            auth_json(403, ['message' => 'Seu nível não permite solicitar a redefinição desta conta.']);
        }

        $apiKey = auth_firebase_api_key();
        if ($apiKey === '') {
            auth_json(503, ['message' => 'Configure a chave Firebase do projeto de teste.']);
        }
        $curl = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=' . rawurlencode($apiKey));
        curl_setopt_array($curl, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(['requestType' => 'PASSWORD_RESET', 'email' => $target['email']], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json', 'X-Firebase-Locale: pt-BR'],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 12,
        ]);
        $responseBody = curl_exec($curl);
        $responseStatus = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $curlFailed = $responseBody === false;
        if ($curlFailed) {
            auth_json(502, ['message' => 'Não foi possível conectar ao serviço de autenticação do Firebase.']);
        }
        if ($responseStatus !== 200) {
            $firebaseError = json_decode((string) $responseBody, true);
            $firebaseCode = (string) ($firebaseError['error']['message'] ?? '');
            $message = match ($firebaseCode) {
                'EMAIL_NOT_FOUND', 'USER_NOT_FOUND' => 'O Firebase não encontrou uma conta com senha por e-mail para esse endereço. Contas que usam somente Google não têm senha para redefinir.',
                'OPERATION_NOT_ALLOWED' => 'O provedor de e-mail e senha não está habilitado no Firebase.',
                'TOO_MANY_ATTEMPTS_TRY_LATER' => 'O Firebase limitou temporariamente os pedidos. Aguarde e tente novamente.',
                default => 'O Firebase não aceitou o pedido de redefinição. Confira se a conta tem acesso por e-mail e senha.',
            };
            auth_json(502, ['message' => $message]);
        }

        $audit = $pdo->prepare("INSERT INTO auth_audit_log (firebase_uid, email, actor_email, target_email, event_type, details_json) VALUES (:uid, :email, :actor, :target, 'employee_password_reset_requested', :details)");
        $audit->execute([
            'uid' => $target['firebase_uid'],
            'email' => $target['email'],
            'actor' => $actor['email'],
            'target' => $target['email'],
            'details' => json_encode(['delivery' => 'firebase_email_link'], JSON_THROW_ON_ERROR),
        ]);
        auth_json(200, ['sent' => true, 'message' => 'Link de redefinição enviado ao e-mail do integrante. A senha só muda quando ele abrir o link e escolher uma nova.']);
    }

    if ($action === 'employee-password-set' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $actor = auth_require_user('admin');
        auth_start_session();
        auth_require_csrf();
        $request = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
        $id = is_array($request) ? filter_var($request['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : false;
        $password = is_array($request) ? ($request['password'] ?? null) : null;
        if ($id === false || !is_string($password) || strlen($password) < 12 || strlen($password) > 1024) {
            auth_json(400, ['message' => 'Informe uma senha temporária com pelo menos 12 caracteres.']);
        }
        $pdo = auth_database();
        $find = $pdo->prepare('SELECT id, firebase_uid, email, role FROM authorized_users WHERE id = :id');
        $find->execute(['id' => $id]);
        $target = $find->fetch();
        if (!is_array($target) || !auth_user_can_assign_role($actor, (string) $target['role'])
            || ($actor['email'] === $target['email'] && $actor['role'] !== 'superadmin')) {
            auth_json(403, ['message' => 'Seu nível não permite alterar a senha desta conta.']);
        }
        if (!is_string($target['firebase_uid']) || $target['firebase_uid'] === '') {
            $apiKey = auth_firebase_api_key();
            if ($apiKey === '') {
                auth_json(503, ['message' => 'Configure a chave Firebase do projeto de teste.']);
            }
            $createCurl = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . rawurlencode($apiKey));
            curl_setopt_array($createCurl, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['email' => $target['email'], 'password' => $password, 'returnSecureToken' => true], JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            ]);
            $createBody = curl_exec($createCurl);
            $createStatus = (int) curl_getinfo($createCurl, CURLINFO_RESPONSE_CODE);
            $createCurlErrno = curl_errno($createCurl);
            // No PHP 8.5 curl_close(): CurlHandle is released automatically and curl_close emits a deprecated warning.
            if ($createBody === false) {
                error_log('Alfatek Firebase signup transport failed; cURL errno ' . $createCurlErrno);
                auth_json(502, ['message' => 'O servidor não conseguiu estabelecer uma conexão TLS confiável com o Firebase. A configuração de certificados do PHP foi corrigida; atualize a página e tente novamente.']);
            }
            $createData = is_string($createBody) ? json_decode($createBody, true) : null;
            if (is_array($createData) && is_string($createData['localId'] ?? null) && $createStatus >= 200 && $createStatus < 300) {
                $firebaseUid = $createData['localId'];
                $verificationCurl = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=' . rawurlencode($apiKey));
                curl_setopt_array($verificationCurl, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['requestType' => 'VERIFY_EMAIL', 'idToken' => $createData['idToken'] ?? ''], JSON_THROW_ON_ERROR),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
                ]);
                $verificationBody = curl_exec($verificationCurl);
                $verificationStatus = (int) curl_getinfo($verificationCurl, CURLINFO_RESPONSE_CODE);

                $verificationSent = is_string($verificationBody) && $verificationStatus >= 200 && $verificationStatus < 300;
                $bind = $pdo->prepare('UPDATE authorized_users SET firebase_uid = :uid, updated_at = UTC_TIMESTAMP() WHERE id = :id AND firebase_uid IS NULL');
                $bind->execute(['uid' => $firebaseUid, 'id' => $target['id']]);
                unset($password, $request, $createData, $createBody, $verificationBody);
                if ($bind->rowCount() !== 1) {
                    error_log('Firebase account created but local binding needs reconciliation for employee ID ' . (int) $target['id']);
                    auth_json(409, ['message' => 'A conta Firebase foi criada, mas o vínculo local mudou durante a operação. Atualize a lista e confira o cadastro antes de tentar novamente.']);
                }
                $audit = $pdo->prepare("INSERT INTO auth_audit_log (firebase_uid, email, actor_email, target_email, event_type, details_json) VALUES (:uid, :email, :actor, :target, 'employee_firebase_account_created', :details)");
                $audit->execute(['uid' => $firebaseUid, 'email' => $target['email'], 'actor' => $actor['email'], 'target' => $target['email'], 'details' => json_encode(['method' => 'admin_initial_password'], JSON_THROW_ON_ERROR)]);
                $message = $verificationSent
                    ? 'Conta criada no Firebase, senha inicial definida e e-mail de verificação enviado. Entregue a senha por um canal seguro; o integrante precisará confirmar o e-mail para entrar.'
                    : 'Conta criada no Firebase e senha inicial definida, mas o envio da verificação falhou. Reenvie a verificação antes do primeiro acesso.';
                auth_json(200, ['message' => $message]);
            }
            if (($createData['error']['message'] ?? '') !== 'EMAIL_EXISTS') {
                $firebaseCode = (string) ($createData['error']['message'] ?? '');
                error_log('Alfatek Firebase signup rejected; HTTP ' . $createStatus . '; code ' . preg_replace('/[^A-Z0-9_]/', '', $firebaseCode));
                $message = match ($firebaseCode) {
                    'WEAK_PASSWORD' => 'A senha não atende à política configurada no Firebase.',
                    'OPERATION_NOT_ALLOWED' => 'O provedor E-mail/senha está desativado no Firebase. Habilite-o em Authentication > Provedores de acesso.',
                    'API_KEY_INVALID', 'API_KEY_HTTP_REFERRER_BLOCKED', 'API_KEY_SERVICE_BLOCKED' => 'A chave da API do Firebase recusou a chamada do servidor. Revise as restrições da chave para Identity Toolkit.',
                    'INVALID_EMAIL' => 'O Firebase recusou o formato do e-mail cadastrado.',
                    'TOO_MANY_ATTEMPTS_TRY_LATER' => 'O Firebase limitou temporariamente as tentativas. Aguarde antes de tentar novamente.',
                    default => 'O Firebase recusou a criação da conta. Confira o código técnico no log do Apache e as configurações do projeto.',
                };
                auth_json(502, ['message' => $message]);
            }
            unset($createData, $createBody);
        }
        if (auth_env('ALFATEK_ALLOW_FIREBASE_ADMIN') !== '1') auth_json(503, ['message' => 'Administração Firebase desabilitada até configurar um projeto de teste separado.']);
        $credentialPath = auth_firebase_service_file();
        if ($credentialPath === '') {
            auth_json(503, ['message' => 'Configure uma credencial Admin SDK exclusiva do projeto de teste, fora da pasta pública.']);
        }
        $service = json_decode((string) file_get_contents($credentialPath), true);
        if (!is_array($service) || ($service['project_id'] ?? '') !== auth_firebase_project_id()
            || !is_string($service['client_email'] ?? null) || !is_string($service['private_key'] ?? null)) {
            auth_json(503, ['message' => 'A credencial administrativa do Firebase está inválida.']);
        }
        $now = time();
        $b64url = static fn(string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $unsigned = $b64url(json_encode(['alg' => 'RS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR)) . '.' . $b64url(json_encode([
            'iss' => $service['client_email'], 'scope' => 'https://www.googleapis.com/auth/cloud-platform',
            'aud' => 'https://oauth2.googleapis.com/token', 'iat' => $now, 'exp' => $now + 3600,
        ], JSON_THROW_ON_ERROR));
        $privateKey = openssl_pkey_get_private($service['private_key']);
        if ($privateKey === false || !openssl_sign($unsigned, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            auth_json(503, ['message' => 'Não foi possível assinar a credencial administrativa do Firebase.']);
        }
        $tokenCurl = curl_init('https://oauth2.googleapis.com/token');
        curl_setopt_array($tokenCurl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer', 'assertion' => $unsigned . '.' . $b64url($signature)]),
            CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'], CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        ]);
        $tokenBody = curl_exec($tokenCurl);
        $tokenStatus = (int) curl_getinfo($tokenCurl, CURLINFO_RESPONSE_CODE);
        $tokenData = is_string($tokenBody) ? json_decode($tokenBody, true) : null;
        if ($tokenStatus < 200 || $tokenStatus >= 300 || !is_array($tokenData) || !is_string($tokenData['access_token'] ?? null)) {
            error_log('Alfatek Firebase admin OAuth failed; HTTP ' . $tokenStatus);
            auth_json(503, ['message' => 'O Firebase não autorizou o serviço administrativo.']);
        }
        $firebaseUid = is_string($target['firebase_uid']) ? $target['firebase_uid'] : '';
        $newFirebaseAccount = false;
        if ($firebaseUid === '') {
            $lookupCurl = curl_init('https://identitytoolkit.googleapis.com/v1/projects/' . rawurlencode(auth_firebase_project_id()) . '/accounts:lookup');
            curl_setopt_array($lookupCurl, [
                CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['email' => [$target['email']]], JSON_THROW_ON_ERROR),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $tokenData['access_token']],
                CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
            ]);
            $lookupBody = curl_exec($lookupCurl);
            $lookupStatus = (int) curl_getinfo($lookupCurl, CURLINFO_RESPONSE_CODE);

            $lookupData = is_string($lookupBody) ? json_decode($lookupBody, true) : null;
            if ($lookupStatus < 200 || $lookupStatus >= 300 || !is_array($lookupData)) {
                error_log('Alfatek Firebase account lookup failed for employee ID ' . (int) $target['id'] . '; HTTP ' . $lookupStatus);
                auth_json(502, ['message' => 'Não foi possível consultar a conta no Firebase. Confira as permissões administrativas.']);
            }
            if (is_array($lookupData['users'][0] ?? null) && is_string($lookupData['users'][0]['localId'] ?? null)) {
                $firebaseUid = $lookupData['users'][0]['localId'];
            } else {
                $apiKey = auth_firebase_api_key();
                if ($apiKey === '') {
                    auth_json(503, ['message' => 'Configure a chave Firebase do projeto de teste.']);
                }
                $createCurl = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . rawurlencode($apiKey));
                curl_setopt_array($createCurl, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['email' => $target['email'], 'password' => $password, 'returnSecureToken' => true], JSON_THROW_ON_ERROR),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'],
                    CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
                ]);
                $createBody = curl_exec($createCurl);
                $createStatus = (int) curl_getinfo($createCurl, CURLINFO_RESPONSE_CODE);

                $createData = is_string($createBody) ? json_decode($createBody, true) : null;
                if ($createStatus < 200 || $createStatus >= 300 || !is_array($createData) || !is_string($createData['localId'] ?? null)) {
                    $firebaseCode = (string) ($createData['error']['message'] ?? '');
                    $message = $firebaseCode === 'EMAIL_EXISTS'
                        ? 'Já existe uma conta Firebase com este e-mail, mas ela não pôde ser consultada. Verifique a permissão firebaseauth.users.get da conta administrativa.'
                        : 'O Firebase não conseguiu criar a conta. Confira se o provedor E-mail/senha está habilitado e a política de senha permite esse valor.';
                    auth_json(502, ['message' => $message]);
                }
                $firebaseUid = $createData['localId'];
                $newFirebaseAccount = true;
                $verificationCurl = curl_init('https://identitytoolkit.googleapis.com/v1/accounts:sendOobCode?key=' . rawurlencode($apiKey));
                curl_setopt_array($verificationCurl, [
                    CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['requestType' => 'VERIFY_EMAIL', 'idToken' => $createData['idToken'] ?? ''], JSON_THROW_ON_ERROR),
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Accept: application/json'], CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 12,
                ]);
                $verificationBody = curl_exec($verificationCurl);
                $verificationStatus = (int) curl_getinfo($verificationCurl, CURLINFO_RESPONSE_CODE);

                $verificationSent = is_string($verificationBody) && $verificationStatus >= 200 && $verificationStatus < 300;
                unset($createData, $createBody, $verificationBody);
            }
            unset($lookupData, $lookupBody);
        }
        $updateCurl = curl_init('https://identitytoolkit.googleapis.com/v1/projects/' . rawurlencode(auth_firebase_project_id()) . '/accounts:update');
        curl_setopt_array($updateCurl, [
            CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode(['localId' => $firebaseUid, 'password' => $password], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => ['Content-Type: application/json', 'Authorization: Bearer ' . $tokenData['access_token']],
            CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 15,
        ]);
        $updateBody = curl_exec($updateCurl);
        $updateStatus = (int) curl_getinfo($updateCurl, CURLINFO_RESPONSE_CODE);

        unset($password, $request, $unsigned, $signature, $tokenData, $tokenBody);
        if (!is_string($updateBody) || $updateStatus < 200 || $updateStatus >= 300) {
            error_log('Alfatek Firebase password update failed for employee ID ' . (int) $target['id'] . '; HTTP ' . $updateStatus);
            auth_json(502, ['message' => 'O Firebase recusou a alteração. Confirme a política de senha e as permissões da conta de serviço.']);
        }
        if ($target['firebase_uid'] === null || $target['firebase_uid'] === '') {
            $bind = $pdo->prepare('UPDATE authorized_users SET firebase_uid = :uid, updated_at = UTC_TIMESTAMP() WHERE id = :id AND firebase_uid IS NULL');
            $bind->execute(['uid' => $firebaseUid, 'id' => $target['id']]);
            if ($bind->rowCount() !== 1) {
                error_log('Alfatek Firebase account created but local binding needs reconciliation for employee ID ' . (int) $target['id']);
                auth_json(409, ['message' => 'A conta Firebase foi atualizada, mas o vínculo local mudou durante a operação. Atualize a lista e tente novamente.']);
            }
        }
        $audit = $pdo->prepare("INSERT INTO auth_audit_log (firebase_uid, email, actor_email, target_email, event_type, details_json) VALUES (:uid, :email, :actor, :target, 'employee_password_set_by_admin', :details)");
        $audit->execute(['uid' => $firebaseUid, 'email' => $target['email'], 'actor' => $actor['email'], 'target' => $target['email'], 'details' => json_encode(['method' => $newFirebaseAccount ? 'firebase_account_created_and_password_assigned' : 'admin_assigned'], JSON_THROW_ON_ERROR)]);
        $message = $newFirebaseAccount
            ? (($verificationSent ?? false)
                ? 'Conta Firebase criada, senha inicial atribuída e e-mail de verificação solicitado. Entregue a senha inicial ao integrante por um canal seguro; ele precisará confirmar o e-mail para entrar.'
                : 'Conta Firebase criada e senha inicial atribuída, mas não foi possível enviar a verificação. Confira a configuração do Firebase e envie a verificação antes do primeiro acesso.')
            : 'Nova senha atribuída. Entregue a senha temporária ao integrante por um canal seguro.';
        auth_json(200, ['message' => $message]);
    }

    if (in_array($action, ['employee-create', 'employee-update', 'employee-deactivate', 'employee-reactivate'], true) && $_SERVER['REQUEST_METHOD'] === 'POST') {
        $actor = auth_require_user('admin');
        auth_start_session();
        auth_require_csrf();
        $request = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
        if (!is_array($request)) {
            auth_json(400, ['message' => 'Dados inválidos.']);
        }
        $pdo = auth_database();
        $pdo->beginTransaction();
        try {
            $id = filter_var($request['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            $target = null;
            if ($action !== 'employee-create') {
                if ($id === false) {
                    $pdo->rollBack();
                    auth_json(400, ['message' => 'Identificador de funcionário inválido.']);
                }
                $find = $pdo->prepare('SELECT id, firebase_uid, email, role, active FROM authorized_users WHERE id = :id FOR UPDATE');
                $find->execute(['id' => $id]);
                $target = $find->fetch();
                if (!is_array($target) || !auth_user_can_assign_role($actor, (string) $target['role']) || ($target['email'] === $actor['email'] && $actor['role'] !== 'superadmin')) {
                    $pdo->rollBack();
                    auth_json(403, ['message' => 'Seu nível não permite alterar este cadastro.']);
                }
            }

            if ($action === 'employee-create') {
                $email = strtolower(trim((string) ($request['email'] ?? '')));
                $role = (string) ($request['role'] ?? '');
                if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254 || !auth_user_can_assign_role($actor, $role)) {
                    $pdo->rollBack();
                    auth_json(422, ['message' => 'Informe um e-mail válido e um nível permitido para seu perfil.']);
                }
                $insert = $pdo->prepare("INSERT INTO authorized_users (firebase_uid, email, role, active, created_by) VALUES (NULL, :email, :role, 1, :created_by)");
                $insert->execute(['email' => $email, 'role' => $role, 'created_by' => $actor['email']]);
                $event = 'employee_created';
                $targetEmail = $email;
                $details = ['role' => $role];
            } else {
                $email = (string) $target['email'];
                $role = (string) $target['role'];
                $active = (int) $target['active'];
                if ($action === 'employee-update') {
                    $role = (string) ($request['role'] ?? '');
                    if (!auth_user_can_assign_role($actor, $role)) {
                        $pdo->rollBack();
                        auth_json(403, ['message' => 'Seu nível não pode atribuir o nível solicitado.']);
                    }
                } elseif ($action === 'employee-deactivate') {
                    $active = 0;
                } else {
                    $active = 1;
                }
                if (($role === 'admin' || $role === 'superadmin') && ($active === 0 || $action === 'employee-update' && $role !== $target['role'])) {
                    $remaining = $pdo->query("SELECT COUNT(*) FROM authorized_users WHERE active = 1 AND role IN ('admin','superadmin') AND id <> " . (int) $target['id'])->fetchColumn();
                    if ((int) $remaining < 1) {
                        $pdo->rollBack();
                        auth_json(409, ['message' => 'Não é possível remover ou rebaixar o último administrador ativo.']);
                    }
                }
                $update = $pdo->prepare('UPDATE authorized_users SET role = :role, active = :active, updated_at = UTC_TIMESTAMP() WHERE id = :id');
                $update->execute(['role' => $role, 'active' => $active, 'id' => $target['id']]);
                $event = $action === 'employee-update' ? 'employee_updated' : ($active === 1 ? 'employee_reactivated' : 'employee_deactivated');
                $targetEmail = $email;
                $details = ['role' => $role, 'active' => (bool) $active];
            }
            $audit = $pdo->prepare('INSERT INTO auth_audit_log (firebase_uid, email, actor_email, target_email, event_type, details_json) VALUES (:uid, :email, :actor, :target, :event, :details)');
            $audit->execute(['uid' => $target['firebase_uid'] ?? null, 'email' => $targetEmail, 'actor' => $actor['email'], 'target' => $targetEmail, 'event' => $event, 'details' => json_encode($details, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
            $pdo->commit();
        } catch (PDOException $error) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            if ($error->getCode() === '23000') {
                auth_json(409, ['message' => 'Este e-mail já possui cadastro no portal.']);
            }
            throw $error;
        }
        auth_json(200, ['saved' => true, 'message' => 'Cadastro atualizado.']);
    }

    if ($action === 'session' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        auth_start_session();
        auth_require_csrf();
        $request = json_decode((string) file_get_contents('php://input'), true, 8, JSON_THROW_ON_ERROR);
        $identity = auth_verify_firebase_id_token((string) ($request['idToken'] ?? ''));
        $pdo = auth_database();
        $pdo->beginTransaction();
        $query = $pdo->prepare('SELECT id, firebase_uid, email, role, require_linked_methods FROM authorized_users WHERE email = :email AND active = 1 FOR UPDATE');
        $query->execute(['email' => $identity['email']]);
        $authorized = $query->fetch();
        if ($authorized === false || ($authorized['firebase_uid'] !== null && !hash_equals((string) $authorized['firebase_uid'], $identity['uid']))) {
            $pdo->rollBack();
            auth_json(403, ['message' => 'Sua conta está autenticada, mas ainda não está autorizada pela Alfatek. Procure o administrador.']);
        }
        if ((int) $authorized['require_linked_methods'] === 1
            && (!in_array('google.com', $identity['providers'], true) || !in_array('email', $identity['providers'], true))) {
            $pdo->rollBack();
            auth_json(409, ['linkRequired' => true, 'message' => 'Antes de entrar, vincule Google e e-mail/senha à mesma conta Firebase nesta página.']);
        }
        if ($authorized['firebase_uid'] === null) {
            $bind = $pdo->prepare('UPDATE authorized_users SET firebase_uid = :uid, updated_at = UTC_TIMESTAMP() WHERE email = :email AND firebase_uid IS NULL');
            $bind->execute(['uid' => $identity['uid'], 'email' => $identity['email']]);
            if ($bind->rowCount() !== 1) {
                $pdo->rollBack();
                auth_json(403, ['message' => 'O vínculo desta conta mudou. Procure o administrador.']);
            }
        }
        $markLogin = $pdo->prepare('UPDATE authorized_users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id');
        $markLogin->execute(['id' => $authorized['id']]);
        $audit = $pdo->prepare("INSERT INTO auth_audit_log (firebase_uid, email, event_type) VALUES (:uid, :email, 'login_succeeded')");
        $audit->execute(['uid' => $identity['uid'], 'email' => $identity['email']]);
        $pdo->commit();

        if (!session_regenerate_id(true)) {
            auth_json(500, ['message' => 'Não foi possível criar uma sessão local.']);
        }
        $_SESSION['uid'] = $identity['uid'];
        $_SESSION['email'] = $identity['email'];
        $_SESSION['name'] = $identity['name'];
        $_SESSION['role'] = (string) $authorized['role'];
        $_SESSION['providers'] = $identity['providers'];
        $_SESSION['last_activity'] = time();
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        auth_json(200, ['authenticated' => true, 'user' => ['email' => $identity['email'], 'name' => $identity['name'], 'role' => $authorized['role']]]);
    }

    if ($action === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        auth_start_session();
        auth_require_csrf();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', ['expires' => time() - 42000, 'path' => $params['path'], 'secure' => $params['secure'], 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        auth_json(200, ['authenticated' => false]);
    }

    auth_json(404, ['message' => 'Operação não encontrada.']);
} catch (JsonException|InvalidArgumentException $error) {
    auth_json(400, ['message' => $error->getMessage()]);
} catch (Throwable $error) {
    error_log('Alfatek local authentication error: ' . get_class($error));
    auth_json(503, ['message' => 'A autenticação local não está preparada ou o serviço necessário está indisponível. Confira a configuração local.']);
}
