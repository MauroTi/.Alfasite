<?php
declare(strict_types=1);
require_once __DIR__ . '/bootstrap.php';
try {
    $user = auth_require_user();
    auth_start_session();
} catch (Throwable $error) {
    error_log('Alfatek protected portal initialization failed: ' . get_class($error));
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Portal indisponível</title><main><h1>Portal local indisponível</h1><p>A preparação do banco MySQL local ainda não foi concluída. Volte à <a href="index.html">tela de autenticação</a>.</p></main></html>';
    exit;
}
$csrfToken = htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8');
$email = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
$displayName = htmlspecialchars($user['name'] !== '' ? $user['name'] : $user['email'], ENT_QUOTES, 'UTF-8');
$roleLabel = htmlspecialchars(AUTH_ROLE_LABELS[$user['role']] ?? 'Funcionário', ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR" data-default-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#12384d">
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <meta name="description" content="Aplicativos disponíveis para <?= $email ?> na Alfatek.">
    <title>Aplicativos da equipe | Alfatek Informática</title>
    <link rel="icon" href="../assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../styles/tokens.css">
    <link rel="stylesheet" href="../styles/main.css">
    <link rel="stylesheet" href="intranet.css?v=employee-admin-1">
  </head>
  <body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
      <div class="header-top container">
        <a class="brand-home" href="../" aria-label="Alfatek Informática, início">
          <span class="brand-logo-window"><img src="../assets/logo-mark.png" alt="Alfatek Informática"></span>
          <span class="brand-age" aria-label="33 anos de história"><strong>33</strong><span>anos</span></span>
        </a>
        <button class="back-link" id="logout-button" type="button">Sair (<?= $email ?>)</button>
        <button class="theme-toggle" type="button" aria-label="Ativar modo escuro" aria-pressed="false" title="Ativar modo escuro"><span class="theme-toggle-icon" aria-hidden="true">☾</span><span class="theme-label" aria-hidden="true">Modo escuro</span><span class="sr-only">Ativar modo escuro</span></button>
      </div>
    </header>
    <main id="conteudo" class="portal-main container">
      <div class="portal-heading"><div><span class="login-eyebrow">AMBIENTE CORPORATIVO</span><h1>Aplicativos da equipe</h1><p>Olá, <?= $displayName ?>. Perfil: <?= $roleLabel ?>.</p></div></div>
      <section class="account-link-panel" id="account-link-panel" aria-labelledby="account-link-title">
        <div>
          <span class="login-eyebrow">SEGURANÇA DA CONTA</span>
          <h2 id="account-link-title">Vincular formas de acesso</h2>
          <p>Vincule Google e e-mail/senha à mesma conta para poder escolher qualquer um dos métodos na próxima entrada.</p>
          <p class="account-link-methods" id="account-link-methods" role="status">Conferindo métodos vinculados…</p>
        </div>
        <form class="auth-form account-link-password" id="link-password-form" hidden>
          <label for="link-password">Nova senha do portal</label>
          <input id="link-password" name="new-password" type="password" autocomplete="new-password" minlength="12" placeholder="Use pelo menos 12 caracteres" required>
          <label for="link-password-confirm">Confirme a nova senha</label>
          <input id="link-password-confirm" name="new-password-confirm" type="password" autocomplete="new-password" minlength="12" required>
          <button class="auth-submit" type="submit">Vincular e-mail e senha</button>
        </form>
        <button class="auth-submit account-link-google" id="link-google-button" type="button" hidden>Vincular conta Google</button>
        <p class="account-link-status" id="account-link-status" role="status" aria-live="polite"></p>
      </section>
      <section class="app-grid" aria-label="Aplicativos disponíveis">
        <article class="app-card app-card-placeholder"><span class="app-symbol" aria-hidden="true">＋</span><div><h2>Novo aplicativo</h2><p>Espaço reservado para cadastrar futuros sistemas da equipe.</p></div><span class="app-status">EM PREPARAÇÃO</span></article>
        <a class="app-card app-card-placeholder app-card-link" href="downloads/index.php"><span class="app-symbol" aria-hidden="true">⇩</span><div><h2>Arquivos para download</h2><p>Abrir a pasta de arquivos disponibilizados pela equipe.</p></div><span class="app-status">ABRIR PASTA</span></a>
        <?php if (auth_role_level((string) $user['role']) >= auth_role_level('admin')): ?>
        <a class="app-card app-card-placeholder app-card-link" href="funcionarios.php"><span class="app-symbol" aria-hidden="true">♙</span><div><h2>Gestão de funcionários</h2><p>Cadastrar contas, ajustar níveis de acesso e ativar ou desativar colaboradores.</p></div><span class="app-status">ADMINISTRAÇÃO</span></a>
        <?php endif; ?>
      </section>
    </main>
    <footer class="site-footer"><div class="container intranet-footer"><a class="footer-brand" href="../">alfatek<small> informática</small></a><p>© 2026 Alfatek Informática. Área da equipe.</p></div></footer>
    <script defer src="../scripts/theme-toggle.js"></script>
    <script type="module" src="../scripts/firebase-login.js?v=lan-http-redirect-20261008"></script>
  </body>
</html>
