# .Alfasite

Repositório de referência para analisar e modernizar o site histórico da Alfatek.

## Novo site — primeira fase

O frontend inicial está em [`frontend/`](frontend/), implementado com HTML, CSS e JavaScript sem backend nem conexão a banco. Para esta fase, manter estático é a opção indicada; ver [`frontend/DECISAO-ARQUITETURA.md`](frontend/DECISAO-ARQUITETURA.md) e [`frontend/README.md`](frontend/README.md). Conteúdo recuperado do acervo ainda precisa ser confirmado antes da publicação.

## Conteúdo

- `site/`: fotografia estática navegável do site incluído no backup. Os caminhos absolutos do domínio foram adaptados para navegação local.
- `source/public/`: arquivos originais do diretório público, excluindo estatísticas, executável do wrapper e o arquivo RAR duplicado do site antigo.
- `docs/PLANO-MODERNIZACAO.md`: análise inicial do backup.\n- `docs/PLANEJAMENTO-NOVO-SITE.md`: planejamento do novo site, arquitetura, segurança, comparação PHP/C# e atualização automática da idade no logo.
- `source/README.md`: critérios de preservação da fonte.

O `.rar` tinha aproximadamente 28,7 MB descompactado. Foram identificados 150 arquivos públicos (~7 MB), 128 arquivos de logs (~21,7 MB) e um marcador na pasta privada. Logs e marcador não foram versionados porque não são necessários para modernizar as páginas e podem conter informação interna. Nenhum banco SQL, `configuration.php` ou núcleo do Joomla foi encontrado; este repositório não restaura o CMS nem seu painel administrativo.

## Visualização local

Na raiz do repositório, execute:

```powershell
py -m http.server 8000 --directory site
```

Acesse `http://localhost:8000`. Os formulários pertenciam ao backend Joomla hospedado e não enviam mensagens nesta cópia estática.

## Próximo passo

Confirmar com o responsável quais conteúdos, contatos, imagens, identidade visual e serviços continuam atuais antes de definir a nova arquitetura ou publicar o material.

