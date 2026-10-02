# Planejamento do novo site Alfatek

## Objetivo

Construir um site institucional novo, inspirado no conteúdo e na identidade histórica preservados, com apresentação atualizada, responsiva, acessível e segura. O frontend será HTML semântico, CSS e JavaScript modular. O banco MySQL ficará atrás de uma camada de servidor: navegador algum deve conectar-se diretamente ao banco.

## Direção técnica inicial

- Começar com páginas institucionais estáticas e JavaScript modular (`type="module"`), aplicando progressive enhancement.
- Adicionar backend apenas para necessidades confirmadas, por exemplo, edição de conteúdo ou processamento de formulário.
- Avaliar PHP contra C#/.NET conforme as capacidades efetivas do plano Terra. Não decidir backend supondo suporte que o provedor não documenta.
- Manter o repositório GitHub privado como fonte; não versionar credenciais, logs de produção, dados de clientes ou o RAR bruto.
- Reutilizar o acervo somente como referência; validar textos, contatos, imagens, marca, produtos e serviços com o responsável antes de publicar.

## Descoberta e validação do Terra

As informações públicas consultadas em 02/10/2026 mostram que a hospedagem Terra oferece MySQL e lista PHP 7.1/MySQL 5.5 nas especificações técnicas. A listagem também menciona ASP.NET 4.0 em IIS legado, sem confirmar ASP.NET Core/.NET atual. A página comercial diz que SSL não está incluído e precisa ser contratado externamente, enquanto as condições gerais mencionam um gerenciador SSL. Portanto, antes de escolher backend ou iniciar a implantação, confirmar no plano ativo e com o suporte:

1. Sistema operacional e servidor web do plano.
2. Versões efetivamente mantidas de PHP ou suporte explícito a ASP.NET Core/.NET atual, hospedagem do processo, runtime e mecanismo de deploy.
3. Versão MySQL, InnoDB, PDO ou driver .NET, limites de bancos, conexões e armazenamento.
4. Acesso SSH/SFTP/FTP seguro, regras de rewrite, variáveis/secrets fora da raiz pública, logs e agendamento de tarefas.
5. Certificado TLS: emissão ou compra, suporte ao domínio e `www`, instalação, renovação automática/manual, redirecionamento HTTP→HTTPS e preço.
6. Backup e restauração independentes para arquivos e banco, retenção, limites e teste de recuperação.

**Regra de decisão:** não usar runtime fora de suporte para um novo backend. Se o plano ativo só oferecer versões legadas (como sugere a tabela pública), solicitar confirmação de atualização ou comparar plano/provedor compatível antes da implementação dinâmica. Um frontend HTML/CSS/JS estático pode ser publicado sem backend enquanto essa decisão estiver pendente.

## PHP ou C#/.NET?

### PHP

É a opção com maior compatibilidade provável com hospedagem compartilhada tradicional: a página Terra especifica PHP e MySQL, o modelo de implantação tende a ser envio de arquivos e a integração MySQL é direta. Porém, a especificação pública localizada lista PHP 7.1, versão antiga para um projeto novo; é necessário confirmar uma versão PHP ainda suportada e os recursos PDO/driver requeridos. Se o painel contratado realmente limitar a PHP 7.1, **não recomendamos construir um sistema dinâmico de produção nela**.

### C# com ASP.NET Core

É uma opção robusta e apropriada quando se deseja uma API tipada e a equipe quer investir em .NET, mas requer suporte explícito do host à versão ASP.NET Core/.NET escolhida, runtime instalado ou publicação self-contained, processo persistente, reverse proxy/IIS compatível, deploy e driver MySQL. A referência Terra localizada a ASP.NET 4.0 em IIS não confirma suporte a ASP.NET Core moderno; são plataformas diferentes. Não presumir que publicar um site ASP.NET clássico equivale a hospedar .NET atual.

### Recomendação provisória

Para este site institucional, manter frontend estático inicialmente. Se for confirmado que o Terra oferece PHP atualizado e mantido, PDO MySQL e configuração segura, PHP será provavelmente a alternativa mais simples nesse plano específico. Escolher C#/.NET somente se o Terra confirmar por escrito runtime ASP.NET Core atual e modelo de hospedagem adequado, ou se a organização aceitar usar outra hospedagem para a API. **A evidência pública disponível não permite afirmar que C# seja hospedável no plano Terra nem que o PHP atual esteja disponível; essa decisão fica condicionada à confirmação do plano.**

Em qualquer opção: parâmetros preparados (PDO ou provider ADO.NET), validação no servidor, escape de saída contextual, usuário MySQL com privilégios mínimos, credenciais fora de arquivos públicos/Git, logs sem segredos e migrações versionadas. SQL Injection Prevention Cheat Sheet da OWASP recomenda prepared statements/consultas parametrizadas.

## Arquitetura e módulos

- `site`/frontend: cabeçalho, navegação, rodapé, início, serviços, produtos aprovados, sobre e contato.
- `assets`: tokens de cor/tipografia, estilos base/layout/componentes e imagens otimizadas.
- `scripts`: módulos JS pequenos para menu móvel, interações e validação de UX; validação de servidor continua obrigatória.
- `backend` (opcional, condicionado ao host): endpoints mínimos; regras de negócio separadas do acesso MySQL.
- `database` (opcional): esquema, migrações e dados sintéticos locais; nunca inserir dados pessoais reais em seeds.
- `docs`: configuração, decisões arquiteturais, implantação, backup, restauração e operação.

Não incluir inicialmente comércio eletrônico, pagamentos, área de clientes, uploads públicos ou autenticação sem requisito aprovado. Um formulário de contato depende de aprovação dos campos, destinatário, retenção de dados, antispam e aviso de privacidade.

## Mapa inicial

- Início.
- Serviços: contratos de manutenção, laboratório e serviços avulsos, se ainda ofertados.
- Produtos, apenas se o catálogo continuar atual.
- Sobre.
- Contato.
- Privacidade, conforme os mecanismos de coleta efetivamente adotados.

## Atualização automática da idade da empresa no logo

O acervo histórico afirma que a empresa foi fundada em fevereiro de 1993. Confirmar a data oficial completa (dia, mês e ano) com o responsável antes de usá-la. Registrar a data de fundação como um único dado de marca documentado; não digitar manualmente “33 anos” no logo ou em várias páginas.

Preferência de implementação: manter o arquivo principal do logo independente da idade e compor um selo/texto HTML acessível junto à marca. Um pequeno módulo JavaScript calcula anos completos a partir da data de fundação e da data local atual, verificando se o aniversário deste ano já ocorreu. Exibir, por exemplo, “Desde 1993” no conteúdo base e complementar com “33 anos de história” quando JavaScript estiver ativo; usar `aria-label`/texto apropriado sem duplicar anúncios do leitor de tela. Não recalcular continuamente nem usar o horário UTC de modo que gere mudança de idade na data errada por fuso horário.

Para layouts que exijam o número dentro de uma imagem, manter o logo-mestre sem idade e gerar o selo/variante de forma automatizada no processo de build, usando a mesma fonte única da data. Nunca editar manualmente o número ano a ano. Definir o comportamento de fallback, direitos da marca, contraste e leitura em tamanho reduzido. Testar a virada do aniversário com datas simuladas. A idade calculada é informativa; o ano de fundação confirmado continua visível se JavaScript estiver desativado.

## Acessibilidade e experiência

Meta WCAG 2.2 AA, com avaliação automatizada e revisão manual:

- HTML semântico, idioma `pt-BR`, landmarks, ordem de títulos e link “Pular para o conteúdo”.
- Teclado integral, foco evidente, ordem lógica, menu móvel acessível e movimento reduzido quando solicitado.
- Contraste, ampliação/reflow, texto legível e alvos de toque adequados.
- Campos com rótulos, instruções e erros associados; mensagens de estado anunciadas a tecnologias assistivas.
- Texto alternativo útil; imagens decorativas com `alt=""`; não deixar informação essencial apenas em imagens, cor, hover ou animação.
- Conteúdo e chamadas principais claros em celular e desktop.

## Segurança e HTTPS

- HTTPS obrigatório no domínio e `www` conforme DNS/certificado; forçar redirecionamento HTTP→HTTPS, eliminar conteúdo misto e validar renovação. Ativar HSTS apenas depois de comprovar a cobertura de hostnames e a renovação.
- Não guardar credenciais ou chaves em JavaScript, HTML, repositório ou raiz pública. Não conectar MySQL diretamente do navegador.
- Consultas parametrizadas, validação allowlist, codificação/escape de saída contextual; proteção CSRF em ações autenticadas; limitação de tentativas e abuso.
- Cookies de sessão `Secure`, `HttpOnly`, `SameSite`; expiração e rotação adequadas. Se houver login, hash de senha com algoritmo contemporâneo oferecido pelo runtime aprovado, MFA quando viável e recuperação segura.
- CSP planejada conforme recursos reais, `X-Content-Type-Options`, `Referrer-Policy`, política de framing e permissões mínimas.
- Erros públicos genéricos; logs protegidos e minimizados, sem senhas/tokens; dependências e runtime corrigidos.
- Privilégios mínimos de DB e publicação; retirar arquivos de teste, instaladores, dumps e backups da raiz pública.
- Backups próprios criptografados conforme capacidade e teste periódico de restauração; não depender apenas da cópia do provedor.
- Aplicar privacidade desde o desenho: finalidade, minimização, retenção, acesso, exclusão e transparência para dados coletados.

## Conteúdo, SEO e desempenho

- Confirmar dados empresariais antes de SEO local/dados estruturados.
- Títulos e descrições únicos, URLs claras, sitemap e metadados sociais.
- Imagens responsivas/comprimidas, dimensões reservadas, lazy loading abaixo da dobra e JavaScript mínimo.
- Evitar trackers, mapas incorporados ou fontes de terceiros sem necessidade e avaliação de privacidade.

## Fases do projeto e entregáveis

### Fase 0 — viabilidade e escopo

Confirmar plano Terra, domínio, runtime, MySQL, certificado, acesso de deploy/backups; identificar responsáveis e objetivos; decidir se há função dinâmica real.

**Entregável:** requisitos aprovados, matriz de compatibilidade do host e decisão documentada entre site estático e backend.

### Fase 1 — auditoria e conteúdo

Revisar página a página o material arquivado; classificar conteúdo como manter, reescrever, substituir ou remover; validar marca, contatos, endereço, catálogo e data de fundação completa.

**Entregável:** sitemap e inventário de conteúdo aprovados.

### Fase 2 — protótipo acessível

Definir identidade, componentes, fluxos de navegação/contato e variações para celular/desktop. Validar leitura, contraste, foco e teclado antes de implementar tudo.

**Entregável:** protótipo e critérios visuais/acessíveis aprovados.

### Fase 3 — frontend institucional

Implementar páginas em HTML/CSS/JS modular, responsivo, com navegação acessível, meta tags, selo de idade automático e graceful fallback sem JavaScript.

**Entregável:** site institucional navegável sem dependência de banco.

### Fase 4 — backend e MySQL (se requisito confirmado)

Somente após validar runtime do Terra: modelar entidades mínimas, criar migrações, endpoints limitados, consultas parametrizadas, validação de servidor, controles de abuso e formulário aprovado. Usar dados sintéticos em desenvolvimento.

**Entregável:** funções dinâmicas mínimas com configurações seguras fora da raiz pública.

### Fase 5 — qualidade e homologação

Revisar acessibilidade por teclado/leitor de tela, contraste, zoom e responsividade; links, rotas, formulário, erros, persistência, cabeçalhos, HTTPS, conteúdo misto, dependências e restauração de backup.

**Entregável:** checklist de homologação aprovado, defeitos críticos corrigidos e plano de rollback.

### Fase 6 — publicação e manutenção

Fazer backup do site vigente, publicar em ambiente de homologação e depois janela aprovada; validar domínio, HTTPS, navegação e formulário; monitorar falhas. Agendar atualização de conteúdo/runtime/dependências, backups e teste de recuperação.

**Entregável:** publicação confirmada, documentação operacional e responsáveis definidos.

## Critérios de aceite

- Conteúdo, contatos, serviços, direitos das imagens e data da fundação aprovados.
- Layout responsivo e operação somente por teclado; landmarks, foco, contraste e fluxo validados com tecnologias assistivas.
- Idade correta para datas antes/depois do aniversário, ano de fundação correto e fallback compreensível sem JavaScript.
- Links funcionais; formulário somente se aprovado, com erros acessíveis e confirmação clara.
- HTTPS válido e renovável, redirecionamento ativo e ausência de requisições inseguras.
- Nenhum segredo no Git/pasta pública; navegador sem acesso direto ao banco; SQL parametrizado; backups restauráveis.
- Runtime de produção suportado e documentado pelo provedor.
- Processo de build/deploy, configuração, migrações e recuperação documentados.

## Riscos em aberto

1. As especificações públicas do Terra encontradas listam PHP 7.1 e MySQL 5.5; não comprovam que esse seja o plano atual do usuário nem que um runtime atual esteja disponível.
2. A página comercial diz que SSL não está incluído, mas as condições gerais mencionam gerenciador SSL. Confirmar custo, emissão, instalação e renovação do plano contratado.
3. A referência a ASP.NET 4.0 não confirma ASP.NET Core/.NET atual.
4. O backup não inclui núcleo Joomla, configuração nem dump SQL; não há restauração direta do CMS.
5. A empresa, o conteúdo atual, a data de fundação completa e a eventual necessidade de formulário/painel ainda precisam de confirmação.

## Referências verificadas em 02/10/2026

- Terra, hospedagem e especificações: https://servicos.terra.com.br/para-seu-negocio/hospedagem-site (MySQL; PHP 7.1/MySQL 5.5 indicados; declaração sobre SSL externo).
- Condições gerais Terra: https://s1.trrsf.com/fe/sva-contracts/contratos/HHS.html (gerenciador SSL, MySQL 4.1.22/5.5, PHP 7.1 e ASP.NET 4.0 indicados).
- Microsoft Learn, hospedagem ASP.NET Core: https://learn.microsoft.com/aspnet/core/host-and-deploy/ (opções de hospedagem e dependências de ambiente).
- OWASP, SQL Injection Prevention Cheat Sheet: https://cheatsheetseries.owasp.org/cheatsheets/SQL_Injection_Prevention_Cheat_Sheet.html (consultas parametrizadas/prepared statements).

As ofertas e versões podem mudar. Revalidar no painel e junto ao suporte Terra antes de definir a arquitetura ou publicar.
