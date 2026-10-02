# Auditoria inicial e plano de modernização

## O que há no pacote

O `.rar` recebido contém um conjunto de arquivos para hospedagem: diretório `public`, `logs`, um marcador em `private`, `cgi-bin`, `databases` e um `wrapper-bin` dentro do site público. O maior volume é de logs históricos. O diretório público inclui páginas HTML que se identificam como conteúdo gerado por Joomla, CSS, JavaScript, imagens e uma cópia antiga do site em HTML.

## O que a cópia permite fazer

As páginas do site principal podem ser vistas em forma estática. A pasta `site/` organiza os arquivos públicos e normaliza referências absolutas ao domínio para facilitar a navegação local. Menus, páginas institucionais, produtos e serviços preservados estão disponíveis como material de referência.

## O que não foi localizado

No conteúdo extraído não aparecem os arquivos centrais da aplicação Joomla, o arquivo `configuration.php` nem um dump SQL. Sem esses componentes, este material não basta para reerguer o CMS, recuperar o painel administrativo ou reproduzir seu comportamento dinâmico. Algumas URLs amigáveis foram salvas sem extensão como HTML estático. Formulários arquivados dependiam do servidor antigo e não devem ser tratados como funcionais localmente.

## Cuidados com o acervo

Logs de acesso e dados privados foram mantidos fora do repositório versionado porque não são necessários ao trabalho de modernização e podem registrar informações internas. Os arquivos visuais, textos, contatos e informações empresariais da cópia devem ser revisados com o responsável antes de serem publicados novamente.

## Plano de trabalho recomendado

1. **Descoberta:** confirmar proprietário do site, público, metas, serviços atuais, conteúdo a preservar e itens que devem ser removidos.
2. **Recuperação de fontes:** verificar com o provedor se ainda existem banco de dados, configuração, mídia original e backups do Joomla; arquivar cópias em local controlado.
3. **Requisitos e arquitetura:** definir páginas, jornadas, idiomas, acessibilidade, integrações, manutenção e estratégia de hospedagem.
4. **Design e conteúdo:** propor identidade visual atualizada e revisar textos, imagens, endereços, contatos e avisos legais.
5. **Implementação modular:** separar componentes de navegação, conteúdo, páginas de serviços/produtos, contato e configuração para facilitar expansões.
6. **Migração:** levar para a nova estrutura somente conteúdo validado, preservando redirecionamentos das URLs importantes quando possível.
7. **Verificação:** revisar navegação em celular e desktop, teclado e contraste, formulários, links, desempenho, privacidade e segurança.
8. **Publicação e manutenção:** definir domínio, hospedagem, backups, monitoramento, atualização de dependências e responsáveis pela manutenção.

Este é um roteiro inicial. Decisões de tecnologia e escopo devem aguardar a confirmação dos objetivos e a disponibilidade das fontes originais.
