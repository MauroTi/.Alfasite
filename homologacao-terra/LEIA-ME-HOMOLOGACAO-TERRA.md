# Homologação Alfatek para hospedagem Terra

Esta pasta é uma cópia isolada para avaliar a compatibilidade da aplicação com o servidor contratado. Não substitui o site atual e não contém as credenciais privadas locais.

## Antes de publicar

1. Confirme com o Terra o PHP efetivamente atribuído ao seu plano. Esta aplicação requer PHP 8.2+ e extensões PDO MySQL, cURL e OpenSSL. A especificação pública consultada lista PHP 7.1 e MySQL 5.5, configuração antiga que pode não refletir seu plano atual.
2. Confirme HTTPS válido no host de homologação, subdomínio ou pasta, MySQL compatível e possibilidade de guardar um arquivo de configuração fora do diretório público.
3. Use um **projeto Firebase separado para testes**, com contas sintéticas. Não reutilize contas, banco ou chave de serviço de produção.
4. Preencha `scripts/firebase-config.js` com os dados do app Web de um Firebase de teste separado e autorize nele o host de homologação. Configure no servidor estas variáveis, fora do conteúdo público: `ALFATEK_AUTH_CONFIG` (caminho absoluto ao JSON privado), `ALFATEK_BASE_PATH` (por exemplo `/provisorio`), `ALFATEK_PUBLIC_HOST` (host sem protocolo), `ALFATEK_FIREBASE_PROJECT_ID` e `ALFATEK_FIREBASE_API_KEY`.
5. O JSON apontado por `ALFATEK_AUTH_CONFIG` deve conter `data_dir`, `db_host`, `db_port`, `db_name`, `db_user`, `db_password`, `firebase_project_id`, `firebase_api_key`, `firebase_service_file` e opcionalmente `ca_file`. O diretório `data_dir` e sua subpasta `sessions` precisam existir e permitir gravação pelo PHP, fora da raiz pública. A credencial Admin SDK, se usada, fica fora da raiz pública e pertence exclusivamente ao projeto Firebase de testes. Atribuição e redefinição administrativa de senha exigem `ALFATEK_ALLOW_FIREBASE_ADMIN=1`; mantenha desligado até confirmar que a credencial pertence ao projeto de teste.
6. Importe `database/schema.sql` no banco de teste e, após criar uma conta sintética no Firebase, adapte `database/primeiro-admin.exemplo.sql` com UID/e-mail dessa conta de teste. Não use dados reais de produção.
7. Rode `intranet/diagnostico.php` temporariamente; retire-o depois. Ele não revela valores de segredo.


## Teste visual sem backend

O site público, estilos, imagens e telas podem ser enviados a `/provisorio/` para avaliação visual. Login, sessão, gestão e MySQL só podem ser testados quando o diagnóstico e as configurações acima estiverem completos. A aplicação não envia credenciais por HTTP.

## Compatibilidade importante

A implementação original usa recursos de PHP 8 (`match`, tipos `never` e parâmetros nomeados). Não é compatível com PHP 7.1 sem reescrita substancial. Não tente contornar removendo verificações de versão, HTTPS ou sessão.
