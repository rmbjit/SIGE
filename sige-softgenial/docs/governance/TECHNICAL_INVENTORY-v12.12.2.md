# TECHNICAL INVENTORY v12.12.2

## Superficie
A versao altera uma superficie documental ja existente: `query_handler:sige_print` com `sige_print=factura`. Nao cria nova rota, AJAX, REST, admin-post, cron ou shortcode.

## Views
View afectada: `admin/finance/financeiro-devedores-view.php`. Os botoes de impressao foram ajustados para `Extracto dívida` e `Histórico`.

## Permissoes
Nao foram criadas permissoes novas. O acesso continua a depender da proteccao documental existente em `sige_processar_impressoes_universal()`.

## Tenant
Todas as consultas novas ou alteradas usam `escola_id` e `aluno_id`. O documento bloqueia emissao se o contexto de escola for invalido.

## Segredos
Sem novos Segredos ou opcoes sensiveis. O registo de opcoes permanece herdado da baseline de governacao.

## Dependencias
Sem novas Dependencias externas. O documento continua a usar o template documental existente.

## query_handler
O fluxo usa `query_handler:sige_print` existente. Nao foi criado `query_handler` novo.

## Ficheiros alterados
- `includes/documents-engine.php`
- `admin/finance/financeiro-devedores-view.php`
- `tools/smoke-devedores-extracto-detalhado-v12-12-2.php`
- `tools/run-gates.php`
- `sige-softgenial.php`
- `BUILD.json`
- `CHANGELOG.md`
- Documentacao de QA, deploy, changelog e governacao.
