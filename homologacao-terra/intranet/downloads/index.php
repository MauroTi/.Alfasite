<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/bootstrap.php';
try {
    $user = auth_require_user();
} catch (Throwable $error) {
    error_log('Alfatek protected downloads initialization failed: ' . get_class($error));
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><title>Downloads indisponíveis</title><main><h1>Downloads indisponíveis</h1><p>A preparação da autenticação local ainda não foi concluída.</p></main></html>';
    exit;
}
$email = htmlspecialchars($user['email'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="pt-BR" data-default-theme="dark">
  <head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#12384d">
    <meta name="description" content="Pasta de downloads da equipe Alfatek.">
    <title>Downloads | Alfatek Informática</title>
    <link rel="icon" href="../../assets/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="../../styles/tokens.css">
    <link rel="stylesheet" href="../../styles/main.css">
    <link rel="stylesheet" href="../intranet.css?v=local-auth-1">
  </head>
  <body>
    <a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
    <header class="site-header">
      <div class="header-top container">
        <a class="brand-home" href="../../" aria-label="Alfatek Informática, início">
          <span class="brand-logo-window"><img src="../../assets/logo-mark.png" alt="Alfatek Informática"></span>
          <span class="brand-age" aria-label="33 anos de história"><strong>33</strong><span>anos</span></span>
        </a>
        <a class="back-link" href="../portal.php">← Voltar aos aplicativos</a>
        <button class="theme-toggle" type="button" aria-label="Ativar modo escuro" aria-pressed="false" title="Ativar modo escuro"><span class="theme-toggle-icon" aria-hidden="true">☾</span><span class="theme-label" aria-hidden="true">Modo escuro</span><span class="sr-only">Ativar modo escuro</span></button>
      </div>
    </header>
    <main id="conteudo" class="downloads-main container">
      <section class="downloads-card">
        <span class="app-symbol" aria-hidden="true">⇩</span>
        <span class="login-eyebrow">PASTA DA EQUIPE</span>
        <h1>Arquivos para download</h1>
        <p class="downloads-empty">A pasta está vazia. Nenhum arquivo interno está publicado.</p>
        <p class="login-security">Sessão autenticada como <?= $email ?>.</p>
        <p class="downloads-warning"><strong>Atenção:</strong> publique arquivos somente depois de adicionarmos uma rota de download que confira a permissão no servidor. O endereço direto de um arquivo público não é protegido pelo login.</p>
      </section>
    </main>
    <footer class="site-footer"><div class="container intranet-footer"><a class="footer-brand" href="../../">alfatek<small> informática</small></a><p>© 2026 Alfatek Informática. Área da equipe.</p></div></footer>
    <script defer src="../../scripts/theme-toggle.js"></script>
  </body>
</html>
