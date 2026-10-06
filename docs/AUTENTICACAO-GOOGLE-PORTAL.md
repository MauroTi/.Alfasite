# Autenticação gerenciada Google para o portal interno

## Objetivo da primeira fase

Substituir a entrada de demonstração por Firebase Authentication (Google Identity Platform), permitindo login com e-mail/senha ou Conta Google, recuperação de senha pelo serviço e acesso apenas a funcionários autorizados. O portal continua separado do site público.

## Decisão de arquitetura

- **Identidade:** Firebase Authentication com provedores Email/Password e Google. O serviço gerencia credenciais e recuperação de senha; não pede escopos para Drive, Gmail ou outros dados Google.
- **Aplicação:** frontend atual em HTML/CSS/JS e backend PHP no mesmo domínio, quando a hospedagem oferecer um runtime PHP mantido.
- **Autorização:** MySQL via PDO (produção) ou SQLite (desenvolvimento) mantém allowlist, papéis e eventos mínimos de auditoria. Firebase autentica; o backend Alfatek decide o que cada usuário pode acessar. O navegador nunca conecta ao banco.
- **Sessão:** o backend PHP valida o Firebase ID token (assinatura RS256, `kid`, emissor, audiência, validade e projeto), confere allowlist e cria sessão local. O cookie será `Secure`, `HttpOnly`, `SameSite=Lax`, com expiração limitada e rotação do identificador. Tokens nunca serão gravados em logs.
- **Acesso inicial:** negar por padrão. Um e-mail explicitamente provisionado pelo administrador do servidor entra uma única vez como administrador inicial. Não haverá promoção pública de usuário a administrador.
- **Papéis iniciais:** `admin` (gerencia contas, papéis e atalhos) e `employee` (usa somente recursos autorizados). As permissões de cada aplicativo serão aplicadas no servidor que protege aquele aplicativo.

## Fluxo de autenticação

1. A tela inicializa o SDK Web Firebase com a configuração pública do aplicativo (sem chave de conta de serviço no frontend).
2. O funcionário autentica com e-mail/senha ou Google. O fluxo de redefinição de senha usa o e-mail de ação do Firebase.
3. O navegador envia o Firebase ID token ao endpoint PHP exclusivamente por HTTPS e com proteção CSRF.
4. Como o Admin SDK oficial não oferece suporte PHP, o backend verifica o JWT com biblioteca PHP mantida e chaves públicas atuais do Firebase, conferindo algoritmo/`kid`, assinatura, `iss`, `aud` (Project ID), `exp`, `sub` e demais claims necessários. Tokens inválidos ou de outro projeto são recusados.
5. O backend normaliza e confere o e-mail e o UID Firebase na allowlist; conta inexistente, inativa ou com vínculo inconsistente é recusada. O Firebase autentica, mas não concede por si só acesso ao portal.
6. Em cada página, operação administrativa, API ou download, o servidor verifica sessão ativa e permissão requerida. Ocultar atalhos no HTML não é controle de acesso.

## Administração e conta inicial

- A primeira conta `admin` será provisionada depois da criação e confirmação do e-mail administrativo. Até lá, a inicialização administrativa fica pendente; nenhum endereço foi presumido.
- O Firebase Authentication permitirá somente os provedores decididos. Não haverá cadastro aberto no portal: contas Firebase e autorizações serão provisionadas pelo administrador; a allowlist do backend continua obrigatória mesmo se uma identidade Firebase existir.
- A área administrativa poderá convidar/provisionar e-mails, ajustar papel e desativar contas. As contas de autenticação e permissões Alfatek devem permanecer consistentes; toda ação administrativa será auditada.
- Novas contas devem ser associadas ao UID Firebase somente depois de autenticação válida e conferência do e-mail autorizado; não basta confiar em um e-mail enviado pelo browser.
- A conta Google da pessoa que administra deve ser individual e protegida com MFA. Não compartilhar uma conta administrativa entre funcionários.
- Registrar quem alterou cada conta e quando, sem gravar tokens, senhas ou dados desnecessários. Não permitir remover/desativar o último administrador ativo sem provisionar outro.

## Requisitos de hospedagem antes de ativar

1. Domínio DNS real e HTTPS válido, com redirecionamento HTTP para HTTPS. O endereço LAN atual usa HTTP e IP privado e serve apenas para protótipo; não deve transportar ID tokens nem sessões reais.
2. Confirmar no plano Terra PHP atualmente mantido (preferencialmente PHP 8.2 ou mais recente compatível com a biblioteca JWT selecionada), extensões cURL/OpenSSL/PDO MySQL e forma de instalar dependências Composer.
3. Confirmar configuração segura do backend, armazenamento de segredos fora da raiz pública, cookies HTTPS, logs, limites de upload e recuperação de backup.
4. Confirmar por escrito se a distribuição dos arquivos e aplicativos planejados é permitida pelo plano contratado. Arquivos privados devem ficar fora da pasta pública e ser entregues por uma rota que confira a sessão/permissão.

As páginas comerciais do Terra consultadas listam PHP 7.1/MySQL 5.5 e dizem que SSL não está incluído. Essa informação pode não representar o contrato atual. PHP 7.1 está fora de suporte para este novo backend; não implantar autenticação nele. Não há ainda confirmação do plano Terra nem mudança nos serviços locais.

### Ambiente local identificado

- O web server ativo é o serviço Windows `Apache`, Apache 2.4.55, executável em `%APPDATA%\\Apache24\\bin\\httpd.exe`; ele inicia automaticamente como serviço.
- A raiz pública configurada é `C:\\xampp\\htdocs`. `C:\\xampp\\htdocs\\Alfatek` é uma junction para a pasta `frontend` deste repositório. O projeto usa, portanto, Apache como serviço; não depende do painel XAMPP.
- O Apache carrega `C:\\php\\php8apache2_4.dll` e `C:\\php\\php.ini`. O runtime efetivamente ligado ao Apache é PHP 8.5.1 (módulo e CLI), não `C:\\xampp\\php\\php.exe`.
- No `C:\\php\\php.ini` ativo, cURL, OpenSSL, PDO MySQL, PDO SQLite, mbstring e fileinfo estavam inicialmente desativados. Foram habilitados para preparar o runtime local; os módulos foram confirmados no PHP 8.5.1 e o serviço Apache foi reiniciado depois de `httpd -t` retornar `Syntax OK`. A configuração original foi preservada em `C:\\Users\\Tecnico\\AppData\\Local\\AlfatekAuthPrep\\php.ini.before-auth-prep.bak`. Composer ainda não está instalado.
- MySQL 8.0 existe instalado localmente, mas o serviço `MySQL80` está parado e desativado; PostgreSQL 18 está ativo. Não foi criado banco nem alterada configuração de serviços.
- O Apache escuta HTTP nas portas 80 e HTTPS 443; o HTTPS aponta para certificado de desenvolvimento `server.crt` instalado em janeiro de 2023. Não presumir que esse certificado é confiável pelo navegador nem usar a origem LAN HTTP para enviar tokens Google.
- O Firebase Auth requer domínio autorizado; para desenvolvimento, usar um host local aceito pelo Firebase e incluí-lo na lista de domínios autorizados. O endereço LAN privado `http://192.168.200.61/Alfatek/` não é uma origem de produção adequada para autenticação. Em produção, usar o domínio público por HTTPS.

## Etapas de implementação

### 1. Preparação (decisão tomada; configuração pendente)

- Aguardar a criação da conta Google administrativa e, então, confirmar seu endereço antes de provisioná-la. **Nenhum e-mail ou usuário administrador foi presumido ou criado.**
- Confirmar domínio de produção, HTTPS e recursos atuais do plano Terra.
- Criar projeto Firebase/Google Cloud, registrar aplicativo Web, habilitar Email/Password e Google e configurar e-mails de ação e domínios autorizados.
- Preparar biblioteca JWT mantida e configuração privada local; nunca versionar credencial de conta de serviço, segredos, usuários de produção ou dados pessoais.

### 2. Login e sessão

- Implementar endpoint de sessão, validação do Firebase ID token em PHP, allowlist e criação/encerramento de sessão.
- Conectar formulário de e-mail/senha, redefinição por e-mail e login Google ao Firebase somente quando a configuração Web, endpoint e HTTPS estiverem definidos. Preservar o acesso de demonstração apenas para desenvolvimento e removê-lo antes de produção.
- Proteger `portal.html` e downloads no servidor; páginas estáticas diretas não podem conter dados internos.

### 3. Administração de e-mails

- Criar tabela de contas com e-mail normalizado, `firebase_uid` anulável até o primeiro vínculo verificado, papel, estado, criador e datas.
- Implementar operações administrativas via `POST`, token CSRF, validação no servidor e autorização `admin` em toda requisição.
- Criar tela em cards para listar, adicionar, alterar papel e desativar contas, com confirmação de alterações, mensagens acessíveis e histórico mínimo de auditoria.
- Impedir auto-revogação acidental do último administrador ativo.

### 4. Homologação e publicação

- Testar sucesso, conta não cadastrada, conta desativada, token inválido/expirado, audiência errada, sessão expirada, CSRF, acesso direto a URLs protegidas e alteração de conta por funcionário comum.
- Implantar primeiro em staging HTTPS com dados sintéticos, configurar backups e validar restauração.
- Ativar em produção somente após confirmar runtime, domínio, certificado e política de armazenamento do plano.

## Pendências para avançar

- A conta Google administrativa ainda será criada. O endereço será confirmado depois; até lá, o provisionamento inicial permanece pendente.
- Confirmar se há Google Workspace e qual domínio é gerenciado; mesmo assim, a autorização do portal será por allowlist explícita.
- Criar projeto Firebase/Identity Platform e obter a configuração pública do aplicativo Web. Não colocar chave de conta de serviço no frontend nem no repositório.
- Domínio público que hospedará o portal e confirmação do certificado HTTPS.
- Plano Terra contratado: versão real do PHP/MySQL, extensões disponíveis, Composer/deploy e configuração de segredos fora da raiz pública.

## Referências técnicas

- Firebase Authentication: https://firebase.google.com/docs/auth/
- Autenticação Web com senha: https://firebase.google.com/docs/auth/web/password-auth
- Provedor Google para Web: https://firebase.google.com/docs/auth/web/google-signin
- Redefinição de senha: https://firebase.google.com/docs/auth/web/manage-users
- Validar Firebase ID tokens em backend: https://firebase.google.com/docs/auth/admin/verify-id-tokens
- Cookies de sessão Firebase: https://firebase.google.com/docs/auth/admin/manage-cookies
- Preços Identity Platform: https://cloud.google.com/identity-platform/pricing
- OWASP Authorization Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/Authorization_Cheat_Sheet.html
- Hospedagem Terra: https://servicos.terra.com.br/para-seu-negocio/hospedagem-site
