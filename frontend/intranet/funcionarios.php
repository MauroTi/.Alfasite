<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $user = auth_require_user('admin');
    auth_start_session();
} catch (Throwable $error) {
    error_log('Alfatek employee administration initialization failed: ' . get_class($error));
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Gestão indisponível</title><main><h1>Gestão de funcionários indisponível</h1><p>Volte ao <a href="portal.php">portal</a> e tente novamente.</p></main></html>';
    exit;
}
$csrfToken = htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
$canAssignAdministrativeRoles = in_array($user['role'], ['admin', 'superadmin'], true);
?>
<!doctype html>
<html lang="pt-BR" data-default-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#12384d">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <meta name="description" content="Administração segura dos cadastros e níveis de acesso da equipe Alfatek.">
    <title>Gestão de funcionários | Alfatek Informática</title>
    <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../styles/tokens.css">
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="intranet.css?v=employee-password-admin-1">
  </head>
  <body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
      <div class="header-top container">
        <a class="brand-home" href="../" aria-label="Alfatek Informática, início">
          <span class="brand-logo-window"><img src="../assets/logo-mark.png" alt="Alfatek Informática"></span>
          <span class="brand-age" aria-label="33 anos de história"><strong>33</strong><span>anos</span></span>
        </a>
        <a class="back-link" href="portal.php">Voltar ao portal</a>
        <button class="theme-toggle" type="button" aria-label="Ativar modo escuro" aria-pressed="false" title="Ativar modo escuro"><span class="theme-toggle-icon" aria-hidden="true">☾</span><span class="theme-label" aria-hidden="true">Modo escuro</span><span class="sr-only">Ativar modo escuro</span></button>
      </div>
    </header>
    <main id="conteudo" class="portal-main container employee-admin-main" data-assign-administrative-roles="<?= $canAssignAdministrativeRoles ? 'true' : 'false' ?>">
      <div class="portal-heading"><div><span class="login-eyebrow">ADMINISTRAÇÃO DE ACESSO</span><h1>Gestão de funcionários</h1><p>Cadastre contas, atribua níveis e acompanhe o vínculo de cada colaborador.</p></div></div>
      <section class="role-guide" aria-labelledby="role-guide-title">
        <div><span class="login-eyebrow">HIERARQUIA</span><h2 id="role-guide-title">Cinco níveis de acesso</h2><p>O nível define quais áreas administrativas podem ser acessadas.</p></div>
        <ol class="role-levels">
          <?php foreach (AUTH_ROLE_LABELS as $roleKey => $roleTitle): ?>
          <li><span class="role-number"><?= auth_role_level($roleKey) ?></span><span><strong><?= htmlspecialchars($roleTitle, ENT_QUOTES, 'UTF-8') ?></strong><small><?= $roleKey === 'employee' ? 'Recursos do funcionário' : ($roleKey === 'leader' ? 'Acesso de liderança' : ($roleKey === 'supervisor' ? 'Acesso de supervisão' : ($roleKey === 'admin' ? 'Acessa cadastro e gerencia os cinco níveis' : 'Acesso administrativo completo'))) ?></small></span></li>
          <?php endforeach; ?>
        </ol>
      </section>
      <section class="employee-form-panel" aria-labelledby="employee-form-title">
        <div class="employee-section-heading"><div><span class="login-eyebrow">NOVO ACESSO</span><h2 id="employee-form-title">Cadastrar funcionário</h2><p>Cadastre primeiro o e-mail autorizado. Ao definir a senha inicial na lista, a conta Firebase será criada e a verificação será enviada ao integrante.</p></div></div>
        <form class="employee-form auth-form" id="employee-create-form" autocomplete="off">
          <div><label for="employee-email">E-mail corporativo ou autorizado</label><input id="employee-email" name="email" type="email" maxlength="254" autocomplete="off" placeholder="nome@alfatek.com.br" required></div>
          <div><label for="employee-role">Nível inicial</label><select id="employee-role" name="role" required>
            <option value="employee">Funcionário</option><option value="leader">Líder</option><option value="supervisor">Supervisor</option>
            <?php if ($canAssignAdministrativeRoles): ?><option value="admin">Administrador</option><option value="superadmin">Superadministrador</option><?php endif; ?>
          </select></div>
          <button class="auth-submit" type="submit">Cadastrar funcionário</button>
        </form>
        <p class="employee-form-status" id="employee-form-status" role="status" aria-live="polite"></p>
      </section>
      <section class="employee-list-panel" aria-labelledby="employee-list-title">
        <div class="employee-section-heading"><div><span class="login-eyebrow">ACESSOS REGISTRADOS</span><h2 id="employee-list-title">Contas da equipe</h2></div><button class="employee-refresh" type="button" id="employee-refresh">Atualizar lista</button></div>
        <div class="employee-list-status" id="employee-list-status" role="status" aria-live="polite">Carregando cadastros…</div>
        <div class="employee-table-wrap" id="employee-table-wrap" hidden>
          <table class="employee-table"><thead><tr><th scope="col">E-mail</th><th scope="col">Nível</th><th scope="col">Conta</th><th scope="col">Firebase</th><th scope="col">Senha Firebase</th><th scope="col">Último acesso</th><th scope="col">Ações</th></tr></thead><tbody id="employee-table-body"></tbody></table>
        </div>
      </section>
      <aside class="employee-security-note"><strong>Senhas são gerenciadas pelo Firebase</strong><p>A senha atual nunca pode ser exibida. Administradores e superadministradores podem criar a conta Firebase e definir a senha inicial pela lista, ou atribuir uma nova senha às contas já vinculadas. Contas novas precisam confirmar o e-mail antes do primeiro acesso. O portal não armazena senhas.</p></aside>
    </main>
    <footer class="site-footer"><div class="container intranet-footer"><a class="footer-brand" href="../">alfatek<small> informática</small></a><p>© 2026 Alfatek Informática. Área da equipe.</p></div></footer>
    <script defer src="../scripts/theme-toggle.js"></script>
    <script defer src="../scripts/employee-admin.js?v=employee-password-admin-6"></script>
    <script type="module" src="../scripts/firebase-login.js"></script>
  </body>
</html>
