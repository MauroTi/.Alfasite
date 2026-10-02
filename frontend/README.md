# Novo frontend Alfatek — Fase 1

Frontend institucional inicial em HTML semântico, CSS e JavaScript ES Modules. **A recomendação para esta fase é manter o site estático**: não há requisito confirmado de login, painel editorial, transações ou armazenamento de dados de visitantes. Esta fase não contém backend, formulário funcional, login nem conexão MySQL. O menu mobile, alternância entre tema claro/escuro, cálculo informativo da idade da empresa e ano corrente funcionam no navegador.

Site estático continua compatível com HTTPS; certificado TLS e redirecionamento HTTP→HTTPS são tarefas da hospedagem/domínio, não exigem banco nem backend.

## Decisão de arquitetura: começar estático

Para a apresentação institucional aprovada até aqui, HTML/CSS/JS estático é a escolha mais simples: reduz componentes publicados, atualizações, configuração e pontos de ataque; carrega poucas dependências; e o navegador não precisa consultar um banco. O JavaScript desta fase cuida de interações de interface e do cálculo de idade, sem acessar dados privados.

O requisito “preparado para MySQL” significa que a arquitetura futura não deve acoplar o frontend a uma solução fechada. **Não significa criar agora um banco vazio ou expor conexão MySQL no navegador.** Só adicionar backend e banco quando houver uma função que precise persistir ou administrar dados, como painel para atualizar conteúdo, solicitações de contato armazenadas ou catálogo dinâmico.

Se aprovada uma dessas necessidades, integrar o frontend por uma API no servidor. Antes disso, confirmar suporte a versões de runtime seguras no plano Terra. A especificação pública consultada lista PHP 7.1/MySQL 5.5 e não confirma ASP.NET Core moderno; não se deve lançar sistema dinâmico com runtime fora de suporte. Caso o Terra não ofereça ambiente atualizado, comparar upgrade/plano ou hospedar a API em outro serviço. Referências e comparação estão em [`../docs/PLANEJAMENTO-NOVO-SITE.md`](../docs/PLANEJAMENTO-NOVO-SITE.md).

### Migrar para arquitetura dinâmica quando

- for necessário editar páginas/produtos sem publicar arquivos;
- um formulário tiver que gravar e acompanhar pedidos de contato;
- houver informação operacional dinâmica, contas de usuário ou outras funções explicitamente aprovadas.

Telefone e e-mail são ligações externas (`tel:`/`mailto:`), não requerem banco. Se não houver essas necessidades, manter o site estático mesmo depois da publicação.

## Pré-visualização local

Na pasta `frontend`, execute:

```powershell
py -m http.server 8000
```

Abra `http://localhost:8000`. O servidor HTTP serve apenas a visualização local; HTTPS será configurado na fase de publicação e validado conforme o plano Terra contratado.

## Organização

- `index.html`: estrutura semântica das seções e conteúdo inicial.
- `styles/tokens.css`: cores, fontes, medidas e estilo de foco comuns.
- `styles/main.css`: layout responsivo, componentes e regras de acessibilidade visual.
- `scripts/main.js`: composição dos módulos e configuração central do ano de fundação.
- `scripts/modules/company-age.js`: cálculo de anos completos pela data local.
- `scripts/modules/mobile-navigation.js`: comportamento do menu compacto.
- `scripts/theme-toggle.js`: preferência do sistema, alternância manual e persistência local, compatível com hospedagem HTTP/HTTPS e abertura local do frontend.
- `assets/`: favicon novo e imagens recuperadas do acervo para prototipagem.

## Pendências editoriais antes da publicação

- O acervo indica fundação em fevereiro de 1993, mas não fornece o dia. `1993-02-01` é provisório; confirmar a data completa. O dia configurado determina o momento da atualização anual.
- Marca, serviços, área de atendimento, telefone, e-mail e imagens vieram do conteúdo histórico. Validar atualidade e autorização de uso.
- O wordmark em azul é recortado do logo original do acervo. O cabeçalho destaca a marca, telefone clicável e selo de idade calculado, sem reutilizar o antigo selo estático “25 anos”. Substituir o recorte por um arquivo vetorial oficial se a empresa o fornecer.
- O layout reproduz a estrutura visual da página original arquivada (marca, faixa principal, boas-vindas, blocos institucionais, detalhes em colunas e contato), removendo conteúdo de vendas. Os textos de oferta, dados e imagens recuperados continuam pendentes de aprovação.
- A seção Contato usa `mailto:`/`tel:` com dados históricos e sem formulário. Validar os destinos antes de publicar.

## Acessibilidade prevista nesta fase

Idioma `pt-BR`, regiões semânticas, link para pular ao conteúdo, hierarquia de títulos, menu com estado anunciado, operação por teclado, foco visível, contraste planejado, alvos confortáveis, rótulos de navegação, descrição alternativa para fotos e respeito a `prefers-reduced-motion`. Revisão manual com leitor de tela e auditoria formal WCAG ficam para a fase de homologação.
