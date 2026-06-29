# Inventário Técnico - v12.14.4

## Ficheiros afectados
- `includes/financeiro-historico-aluno-pro.php`
- `assets/documents/financeiro-historico-aluno.css`
- `assets/sige-document-actions.js`
- `assets/sige-ui.js`
- `sige-softgenial.php`
- `BUILD.json`
- `CHANGELOG.md`
- `docs/changelog/CHANGELOG-v12-14-4-csp-regression-documents-buttons.txt`

## Superfícies funcionais
- Documento financeiro oficial do aluno.
- Janela/aba de impressão e guardar PDF.
- Shell admin SIGE com eventos legados convertidos para `data-sige-on-*`.

## Endpoints/rotas
- Admin/post ou URL interna que chama o histórico financeiro do aluno.
- `page=sige-app` no shell admin para módulos afectados.

## Permissões e tenant isolation
- Não foram alteradas regras de permissão.
- Não foram alteradas consultas financeiras nem escopo de escola.
- O documento financeiro mantém os controlos existentes de acesso.

## Dependências
- Nenhuma dependência externa nova.
- Novo asset JS/CSS local, self-hosted.
