<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$checks = [];
$checks['PHP 8.2 ou superior'] = PHP_VERSION_ID >= 80200;
$checks['Extensão PDO MySQL'] = extension_loaded('pdo_mysql');
$checks['Extensão cURL'] = extension_loaded('curl');
$checks['Extensão OpenSSL'] = extension_loaded('openssl');
$checks['Acesso HTTPS válido'] = !empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off';
$checks['Arquivo privado configurado'] = getenv('ALFATEK_AUTH_CONFIG') !== false && is_file((string) getenv('ALFATEK_AUTH_CONFIG'));
$checks['Caminho base configurado'] = getenv('ALFATEK_BASE_PATH') !== false;
$checks['Host público configurado'] = getenv('ALFATEK_PUBLIC_HOST') !== false;
$checks['Firebase de teste configurado'] = getenv('ALFATEK_FIREBASE_PROJECT_ID') !== false && getenv('ALFATEK_FIREBASE_API_KEY') !== false;
$allReady = !in_array(false, $checks, true);
?>
<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Diagnóstico de homologação Alfatek</title><style>body{font:16px system-ui;background:#101a20;color:#e7f1f5;max-width:800px;margin:4rem auto;padding:0 1rem}main{background:#1b2a32;border:1px solid #40535d;border-radius:16px;padding:2rem}li{margin:.8rem 0}.ok{color:#6ee7a8}.fail{color:#ffc66d}code{overflow-wrap:anywhere}</style><main><h1>Homologação Alfatek no Terra</h1><p>Diagnóstico técnico. Nenhuma credencial é exibida nem gravada.</p><ul><?php foreach ($checks as $label => $ok): ?><li class="<?= $ok ? 'ok' : 'fail' ?>"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>: <?= $ok ? 'OK' : 'PENDENTE' ?></li><?php endforeach; ?></ul><?php if (!$allReady): ?><p>Autenticação permanece bloqueada até todos os itens necessários estarem prontos. O plano compartilhado Terra pode não permitir esses requisitos; confirme PHP, SSL, variáveis de ambiente e MySQL com o suporte.</p><?php else: ?><p>Pré-requisitos básicos presentes. Ainda é necessário executar o instalador SQL de homologação e testar todas as rotas com contas sintéticas.</p><?php endif; ?><p>Após o diagnóstico, remova este arquivo do servidor ou proteja-o por senha.</p></main></html>