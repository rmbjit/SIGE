# TENANT ISOLATION REGISTER - v12.12.0

Registo de fallback relacionado a `escola_id`.

TENANT_FALLBACK_BASELINE: 194

## Leitura

Cada fallback `escola_id = 1` ou equivalente e uma divida de isolamento. Esta versao nao declara correcao definitiva; a eliminacao fail-closed pertence a Fase 3.

## Amostra

- admin/academic/abertura-view.php:25 - `$_eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/acta-view.php:37 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/academic/aluno-portal-view.php:36 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/alunos_lista.php:256 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/aprovar_notas-view.php:32 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/auditoria_notas-view.php:20 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/boletim-view.php:35 - `$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/academic/dec-view.php:18 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/academic/disciplinas-view.php:45 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/encerramento-view.php:38 - `$_eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/estatisticas-demograficas-view.php:28 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/academic/matriz-view.php:298 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/minhas_turmas-view.php:23 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/notas-view.php:31 - `$eid = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/academic/pauta-final-view.php:24 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/pautas-view.php:175 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/presencas-view.php:10 - `$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/academic/turmas-view.php:42 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/academic/vincular-view.php:75 - `$discs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_disciplinas WHERE escola_id = %d ORDER BY nome ASC", function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1));`
- admin/finance/financeiro-centros-view.php:25 - `function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1`
- admin/finance/financeiro-config.php:41 - `$escola_id  = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-config.php:240 - `: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
- admin/finance/financeiro-config.php:243 - `$escola_id = 1;`
- admin/finance/financeiro-dashboard.php:37 - `$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/finance/financeiro-despesas-view.php:24 - `$escola_id = (int) ($wpdb->get_var("SELECT escola_id FROM {$p}sige_config LIMIT 1") ?: 1);`
- admin/finance/financeiro-devedores-view.php:24 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-extratos.php:310 - `$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/finance/financeiro-gerador.php:63 - `$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/finance/financeiro-gerador.php:137 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-inscricoes-view.php:21 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-lancamentos-view.php:117 - `$escola_id = (int) ($wpdb->get_var("SELECT escola_id FROM {$wpdb->prefix}sige_config LIMIT 1") ?: 1);`
- admin/finance/financeiro-lancamentos-view.php:210 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-pagamentos.php:39 - `$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/finance/financeiro-pagamentos.php:90 - `if (!$eid) $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-pagamentos.php:155 - `$escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
- admin/finance/financeiro-pagamentos.php:204 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-pagamentos.php:294 - `if (!$eid) $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-pagamentos.php:312 - `if (!$eid) $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/finance/financeiro-planos-view.php:23 - `$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/finance/financeiro-relatorio-mensal-view.php:47 - `$escola_id = (int) ($wpdb->get_var("SELECT escola_id FROM {$wpdb->prefix}sige_config LIMIT 1") ?: 1);`
- admin/finance/mpesa-view.php:10 - `$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/finance/pagamentos-turma-view.php:13 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/hr/equipe-view.php:37 - `$escola_id = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/jardim/jardim_boletim-view.php:19 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/jardim/jardim_diario-view.php:19 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/jardim/jardim_presencas-view.php:10 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/jardim/jardim_relatorio-view.php:18 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/jardim/jardim_saude-view.php:17 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/logistics/transporte-view.php:23 - `$sige_transport_escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/system/curriculum-engine-view.php:22 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/system/dashboard-view.php:39 - `$eid = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1;`
- admin/system/permissions-ui.php:234 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/system/permissions-ui.php:235 - `if ($escola_id <= 0) $escola_id = 1;`
- admin/system/permissions-ui.php:276 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/system/permissions-ui.php:277 - `if ($escola_id <= 0) $escola_id = 1;`
- admin/system/permissions-ui.php:427 - `$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- admin/system/permissions-ui.php:428 - `if ($escola_id <= 0) $escola_id = 1;`
- admin/whatsapp_circulares-view.php:10 - `$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- admin/whatsapp_diag-view.php:33 - `$eid     = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- includes/academic-logic.php:32 - `return function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- includes/academic-logic.php:62 - `$eid = ($escola_id !== null && (int)$escola_id > 0) ? (int)$escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
- includes/academic-logic.php:490 - `'escola_id'     => function_exists('sige_get_escola_id') ? sige_get_escola_id() : 1,`
- includes/academic-logic.php:571 - `$turma = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tTurmas} WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id, function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1));`
- includes/academic-logic.php:655 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- includes/academic-logic.php:677 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- includes/academic-logic.php:743 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- includes/academic-logic.php:831 - `function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1,`
- includes/academic-logic.php:1322 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- includes/academic-logic.php:1769 - `: (function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1);`
- includes/academic-logic.php:2083 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- includes/academic-logic.php:2188 - `$escola_id = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
- includes/academic-logic.php:2227 - `$escola_id = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
- includes/acta-pdf-handler.php:596 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- includes/acta-pdf-handler.php:1236 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- includes/acta-pdf-handler.php:1355 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- includes/acta-pdf-handler.php:1609 - `$eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 1;`
- includes/ajax-handlers.php:45 - `$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1;`
- includes/alertas-core.php:303 - `: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
- includes/alertas-core.php:357 - `$eid = $escola_id ?? (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
- includes/alertas-core.php:372 - `$eid = $escola_id ?? (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 1);`
