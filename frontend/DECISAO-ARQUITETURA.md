# Decisão de arquitetura — frontend estático na Fase 1

- **Estado:** recomendado para a primeira versão.
- **Escopo:** apresentação institucional, navegação, informações aprovadas e atalhos para e-mail/telefone.
- **Implementação:** HTML5, CSS e módulos JavaScript no navegador. Sem backend, banco, login ou armazenamento de mensagens.

## Por que começar assim

O site é institucional e não há, até o momento, requisito confirmado que precise gravar ou administrar dados. Um site estático atende essas páginas e evita colocar uma API e banco MySQL sem necessidade. Reduz também a quantidade de serviços a atualizar e configurar. HTTPS continua possível; TLS é configurado na hospedagem/domínio.

O JavaScript existente é limitado a interações de interface e informações derivadas publicamente (ano corrente e idade aproximada pela data de fundação); nenhum segredo ou dado sensível deve residir no browser.

## Consequências e limites

- Mudanças de conteúdo exigem alterar e publicar os arquivos.
- Não existe formulário funcional nem armazenamento de solicitações nesta fase.
- `mailto:` depende do aplicativo de e-mail do visitante; `tel:` inicia uma chamada em dispositivos compatíveis.
- O repositório contém material histórico; conteúdo, marca, idade e contatos precisam ser confirmados antes de produção.
- “Preparado para MySQL” é um caminho de evolução: manter frontend separável e documentar os dados necessários. Não significa criar banco agora nem conectar o navegador diretamente ao MySQL.

## Condições para adicionar backend

Reabrir esta decisão se for aprovado um CMS/painel editorial, formulário que armazene e acompanhe pedidos, catálogo administrável ou outro fluxo que persista dados. Desenhar endpoints/API e esquema apenas após especificar finalidade, campos, autorização, retenção e privacidade. Toda consulta deve ser parametrizada; credenciais ficam fora da raiz pública e do Git.

Antes de optar por PHP, C#/.NET ou outra tecnologia, validar o runtime real, suporte vigente, banco/driver, acesso e deploy no plano Terra. A informação pública encontrada aponta PHP 7.1/MySQL 5.5 e ASP.NET 4.0, sem confirmar PHP mantido ou ASP.NET Core atual. Não publicar backend num runtime fora de suporte. Se não houver ambiente adequado no plano, avaliar upgrade ou provedor alternativo.

## HTTPS

Site estático não significa HTTP desprotegido. Produção deve ter certificado válido, suporte aos domínios escolhidos, renovação verificada e redirecionamento de HTTP para HTTPS. Isso depende da configuração Terra/domínio e deve ser homologado antes de apontar o domínio oficial.

## Reavaliar após a validação

Na homologação, revisar tráfego, fluxo de atualização de conteúdo e necessidade de persistência. Na ausência de motivo funcional concreto, continuar estático.
