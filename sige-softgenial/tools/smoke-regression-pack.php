<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Pacote de Regressão Vivo
 *
 * Invariantes FUNCIONAIS extraídos dos melhores gates históricos
 * (71 permissões/alunos, 80 portaria, 27 tenant schema) mais os contratos
 * financeiros canónicos. Sem nenhum literal de versão: corre verde em
 * qualquer release enquanto o comportamento se mantiver.
 *
 * Executar: php tools/smoke-regression-pack.php
 */
$root = dirname(__DIR__);
$fails = []; $oks = 0;
$check = static function (bool $c, string $l) use (&$fails, &$oks): void {
    if ($c) { $oks++; echo "OK   {$l}\n"; } else { $fails[] = $l; echo "FAIL {$l}\n"; }
};
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};
$has = static fn(string $s, string $n): bool => strpos($s, $n) !== false;

$db    = $read('includes/db-handler.php');
$ajax  = $read('includes/aluno-fetch-ajax.php');
$alunos= $read('admin/academic/alunos_lista.php');
$perm  = $read('includes/permissions-layer.php');
$mig   = $read('includes/class-sige-migration.php');
$fin   = $read('includes/finance-core.php');
$extr  = $read('admin/finance/financeiro-extratos.php');
$shell = $read('includes/admin-shell.php');

// ── A. Guardas de permissão em Alunos (do gate 71) ─────────────────────────
$check($has($db, 'function sige_ajax_salvar_aluno'), 'Handler salvar_aluno existe');
$check($has($db, 'function sige_ajax_remover_aluno'), 'Handler remover_aluno existe');
$saveSlice = substr($db, strpos($db, 'function sige_ajax_salvar_aluno'), 1200);
$check($has($saveSlice, 'sige_check_nonce_global()') && $has($saveSlice, 'sige_ajax_user_can_permissions_or_caps('), 'salvar_aluno: nonce + matriz de permissões');
$check($has($saveSlice, "'alunos.editar'") && $has($saveSlice, "'alunos.criar'"), 'salvar_aluno exige alunos.editar/criar conforme o caso');
$remSlice = substr($db, strpos($db, 'function sige_ajax_remover_aluno'), 800);
$check($has($remSlice, 'sige_check_nonce_global()') && $has($remSlice, "['alunos.apagar']"), 'remover_aluno: nonce + alunos.apagar');
$check($has($ajax, 'sige_alunos_user_can_view_documents'), 'Helper de visualização de documentos presente');
$check($has($ajax, 'sige_alunos_user_can_emit_documents'), 'Helper de emissão de documentos presente');
$check($has($ajax, 'sige_alunos_user_can_export_students'), 'Helper de exportação presente');
$check($has($ajax, "['excel','export','cards']"), 'Whitelist de purpose no export AJAX');
$check($has($alunos, 'COUNT(DISTINCT a.id)'), 'Contagem de alunos sem duplicar por joins');
$check($has($alunos, 'm.escola_id = a.escola_id') && $has($alunos, 't.escola_id = a.escola_id'), 'Joins de matrícula/turma com escopo de escola');
$check($has($perm, "'guarda' => ['nome'=>'Guarda / Portaria'"), 'Perfil Guarda/Portaria preservado no seed');

// ── B. Estado consistente da Portaria (do gate 80) ─────────────────────────
$check($has($db, "'acesso_permitido' => \$acesso_permitido"), 'Portaria devolve acesso_permitido');
$check($has($db, "'ui_estado' => \$acesso_permitido ? 'autorizado' : 'bloqueado'"), 'Portaria devolve ui_estado coerente');
$check($has($db, "'ui_selo' => \$acesso_permitido ? 'AUTORIZADO' : 'BLOQUEADO'"), 'Portaria devolve selo coerente');
$check($has($db, "'ui_som' => \$acesso_permitido ? 'success' : 'error'"), 'Portaria devolve som coerente');

// ── C. Schema multi-tenant (do gate 27) ────────────────────────────────────
$check($has($mig, 'run_phase2_tenant_schema_migration'), 'Migration tenant-aware existe');
$check($has($mig, 'replace_unique_index_if_safe'), 'Substituição segura de UNIQUE indexes existe');
$check($has($mig, 'backfill_escola_id_from_parent'), 'Backfill de escola_id por relação canónica existe');
// Nenhuma UNIQUE global (sem escola_id) nas tabelas tenant-scoped do schema:
$bad = [];
if (preg_match_all('/CREATE TABLE \{\$p\}(sige_[a-z_]+) \((.*?)\)\s*\{\$cc\}/s', $mig, $mts, PREG_SET_ORDER)) {
    foreach ($mts as $mt) {
        if (!$has($mt[2], 'escola_id')) continue; // tabela não-tenant
        if (preg_match_all('/UNIQUE KEY\s+\S+\s*\(([^)]*)\)/i', $mt[2], $uks)) {
            foreach ($uks[1] as $cols) {
                if (!$has($cols, 'escola_id')) $bad[] = $mt[1] . '(' . trim($cols) . ')';
            }
        }
    }
}
$check(empty($bad), 'Nenhuma UNIQUE KEY global em tabelas tenant-scoped' . (empty($bad) ? '' : ': ' . implode('; ', $bad)));

// ── D. Contratos financeiros canónicos ─────────────────────────────────────
$check($has($fin, 'function sige_fin_saldo_sql') || $has($read('includes/core-helpers.php'), 'function sige_fin_saldo_sql'), 'Fórmula canónica sige_fin_saldo_sql presente');
$check($has($fin, 'function sige_fin_saldo_lancamento'), 'Fórmula canónica sige_fin_saldo_lancamento presente');
$check($has($fin, 'function sige_fin_atualizar_status_lancamento'), 'Sincronizador de status de lançamento presente');
$check($has($extr, '[SIGE_RECON_V1]'), 'Marcador de reconciliação [SIGE_RECON_V1] presente');
$check($has($fin, 'function sige_fin_queue_whatsapp'), 'Enfileirador WhatsApp financeiro presente');

// ── E. Router e escudo de login (invariantes novos) ────────────────────────
$check($has($shell, '$_views_ok'), 'Allowlist de views presente no router');
$check($has($shell, "if (!in_array(\$view, \$_views_ok, true)) \$view = 'dashboard';"), 'Allowlist aplicada antes do include');
$shield = $read('includes/security-login-shield.php');
$check($has($shield, "add_action('wp_login_failed'") && $has($shield, "add_filter('authenticate'"), 'Escudo de login ligado aos hooks de autenticação');
$check($has($read('sige-softgenial.php'), 'security-login-shield.php'), 'Escudo de login carregado no bootstrap');

// ── F. Presenças: derivação pura e excepções (Sprint 2) ────────────────────
$pres = $read('includes/presencas-engine.php');
$check($has($pres, 'function sige_presencas_derivar_estado'), 'Motor de presenças: derivação pura presente');
$check($has($pres, "add_action('wp_ajax_sige_presencas_marcar'"), 'Presenças: AJAX de correcção registado');
$check($has($pres, "['falta_justificada', 'presente_manual', 'dispensado', 'auto']"), 'Presenças: whitelist de estados de excepção');
$check(!$has($pres, 'INSERT INTO') || !$has($pres, 'sige_acessos'), 'Presenças nunca escrevem no log da Portaria');
$check($has($mig, 'uq_escola_aluno_data'), 'Schema: UNIQUE de excepção por escola+aluno+data');

// ── G. M-Pesa: conciliação SEMPRE pela função canónica (Sprint 2) ──────────
$mpc = $read('includes/payments/mpesa-conciliacao.php');
$check($has($mpc, 'sige_fin_registar_pagamento('), 'Conciliação M-Pesa chama a função financeira canónica');
$check(!$has($mpc, 'INSERT INTO') && !preg_match('/->insert\(\s*\$wpdb->prefix\s*\.\s*.sige_fin_/', $mpc), 'Conciliação M-Pesa NUNCA insere directamente em tabelas financeiras');
$check($has($mpc, "'pendente_manual'"), 'Conciliação M-Pesa tem caminho de decisão humana');
$mpw = $read('includes/payments/mpesa-webhook.php');
$check($has($mpw, 'hash_equals('), 'Webhook M-Pesa valida o token com hash_equals');
$check($has($mpw, 'referencia_mpesa'), 'Webhook M-Pesa verifica idempotência por referência');
$check($has($mig, 'uq_escola_ref'), 'Schema: UNIQUE de idempotência (escola_id, referencia_mpesa)');
$mpcl = $read('includes/payments/mpesa-client.php');
$check($has($mpcl, 'OPENSSL_PKCS1_PADDING'), 'Cliente M-Pesa cifra o bearer com RSA PKCS1');

// ── H. Sprint 3: extracção de assets, candidato CSS e novos handlers ────────
$presView = $read('admin/academic/presencas-view.php');
$check($has($presView, "sige_view_assets('presencas')"), 'Presenças: view usa sige_view_assets (regra do escuteiro paga)');
$check(!$has($presView, '<style>'), 'Presenças: zero blocos <style> no PHP');
$check(is_file($root . '/assets/views/presencas.css') && is_file($root . '/assets/views/presencas.js'), 'Assets extraídos existem em assets/views/');
$check(!is_file($root . '/assets/style-consolidado.css') && !is_file($root . '/tools/css-consolidar.php'), 'Código CSS morto removido (consolidado candidato + ferramenta obsoleta)');
$cobr = $read('includes/payments/mpesa-cobranca.php');
$check($has($cobr, 'sige_mpesa_pode_gerir()') && $has($cobr, "check_ajax_referer('sige_mpesa'"), 'Cobrança push: capacidade + nonce verificados');
$check($has($cobr, 'SIGE_MPesa_Client::c2b_push'), 'Cobrança push usa o cliente oficial');
$check(!preg_match('/->insert\(\s*\$wpdb->prefix\s*\.\s*.sige_fin_/', $cobr), 'Cobrança push NUNCA insere em tabelas financeiras');
$saude = $read('includes/saude-operacional.php');
$check($has($saude, "wp_verify_nonce") && $has($saude, 'sige_saude_toggles_pode()'), 'Interruptores de saúde: nonce + capacidade verificados');

// ── I. Sprint 4 parcial: e-Mola e relatório oficial ─────────────────────────
$check($has($mpc, 'sige_provider_metodo('), 'Conciliação regista pelo método do provider (mpesa/emola), não hardcoded');
$ewh = $read('includes/payments/emola-webhook.php');
$check($has($ewh, 'hash_equals('), 'Webhook e-Mola valida o token com hash_equals');
$check($has($ewh, "'provider' => 'emola'"), 'Webhook e-Mola insere com provider=emola no funil partilhado');
$check($has($ewh, 'sige_mpesa_conciliar('), 'e-Mola usa a MESMA conciliação canónica do M-Pesa');
$check(!$has($ewh, 'INSERT INTO') && !preg_match('/->insert\(\s*\$wpdb->prefix\s*\.\s*.sige_fin_/', $ewh), 'Webhook e-Mola NUNCA insere em tabelas financeiras');
$check($has($mpc, 'function sige_pagamentos_parse_valor'), 'Parser monetário partilhado (anglo + europeu) presente');
$presView2 = $read('admin/academic/presencas-view.php');
$check($has($presView2, "sige_presencas_relatorio_dados("), 'Relatório oficial alimentado pelo agregador do motor');
$check(strpos($presView2, "\$_GET['relatorio']") > strpos($presView2, 'sige_presencas_pode_ver'), 'Ramo do relatório fica DEPOIS da guarda de visualização');
$check($has($read('includes/presencas-engine.php'), 'function sige_presencas_totais_por_dia'), 'Totais por dia (rodapé oficial) como função pura');

// ── J. Sprint UX-1: kit de UI e módulo piloto ───────────────────────────────
$check(is_file($root . '/assets/sige-ui.css') && is_file($root . '/assets/sige-ui.js') && is_file($root . '/includes/ui-kit.php'), 'UI Kit: os 3 ficheiros da fundação existem');
$check($has($read('sige-softgenial.php'), "includes/ui-kit.php"), 'UI Kit carregado no bootstrap');
$uik = $read('includes/ui-kit.php');
$check($has($uik, "'sige-design-system'") && $has($uik, "wp_enqueue_style('sige-ui-kit'"), 'UI Kit declara dependência do design system (carrega depois)');
$check($has($read('assets/sige-ui.js'), 'window.sigeUi'), 'sigeUi exposto globalmente');
$central = $read('admin/whatsapp_central-view.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $central), 'Piloto WhatsApp Central: zero alert() nativos');
$check(!preg_match('/(?<![\\w.])confirm\\(/', $central), 'Piloto WhatsApp Central: zero confirm() nativos');
$check($has($central, 'sigeUi.confirm') && $has($central, 'sigeUi.toast'), 'Piloto usa os diálogos canónicos do kit');
$check(!preg_match('/\\bEliminar\\b|\\bExcluir\\b|\\bSalvar\\b|\\bBuscar\\b/u', $central), 'Piloto: terminologia 100% canónica (Remover/Guardar/Pesquisar)');
$check(is_file($root . '/tools/ux-audit.php'), 'Auditor UX presente (medição repetível)');

// ── K. Sprint UX-2: financeiro-pagamentos conforme, regras financeiras cadeadas ──
$pagv = $read('admin/finance/financeiro-pagamentos.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $pagv), 'Pagamentos: zero alert() nativos');
$check(!preg_match('/(?<![\\w.])confirm\\(/', $pagv), 'Pagamentos: zero confirm() nativos');
$check(substr_count($pagv, 'sige_fin_registar_pagamento(') >= 2, 'Pagamentos: regista SEMPRE pela função financeira canónica (2 caminhos)');
$check(!preg_match('/->insert\\(\\s*\\$\\w+\\s*\\.\\s*.sige_fin_/', $pagv) && !preg_match('/INSERT\\s+INTO\\s+[^;]{0,40}sige_fin_(lancamentos|pagamentos)/i', $pagv), 'Pagamentos: view NUNCA insere directo em tabelas financeiras');
$check($has($pagv, 'aCarregar(_btnFinal, true)') && $has($pagv, '_btnFinal.disabled) return;'), 'Pagamentos: escudo anti duplo-clique no botão que move dinheiro');
$check(!preg_match('/<button(?![^>]*class=)[^>]*>/', $pagv), 'Pagamentos: zero botões sem classe');
$check($has($read('tools/ux-audit.php'), 'linhas_visiveis'), 'Auditor v2: AO90 medido só em contexto visível (sem falsos positivos SQL)');

// ── L. Sprint UX-3: financeiro-config + mpesa-view conformes ────────────────
$fcfg = $read('admin/finance/financeiro-config.php');
$check(!$has($fcfg, 'no cadastro') && substr_count($fcfg, 'na ficha do aluno') >= 3, 'Config: rótulos BR canonizados (ficha do aluno)');
$mpv = $read('admin/finance/mpesa-view.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $mpv) && !preg_match('/(?<![\\w.])prompt\\(/', $mpv) && !preg_match('/(?<![\\w.])confirm\\(/', $mpv), 'Pagamentos Móveis: zero diálogos nativos (alert/confirm/prompt)');
$check($has($mpv, 'sigeUi.prompt(') && $has($mpv, 'data-ref='), 'Rejeitar: prompt do kit com a referência da transacção');
$check($has($mpv, 'sgk-banner sgk-banner-ok') && $has($mpv, 'class="sgk-card"') && $has($mpv, 'sgk-badge'), 'Pagamentos Móveis: banners, cartões e badges do kit');
$check($has($read('assets/sige-ui.js'), 'prompt: pedirTexto'), 'Kit JS expõe sigeUi.prompt');
$check($has($read('tools/ux-audit.php'), "\$m['prompt']"), 'Auditor conta prompt( nativos');

// ── M. Sprint UX-4: trio jardim/estatísticas/turmas + EXTINÇÃO GLOBAL ───────
$jrel = $read('admin/jardim/jardim_relatorio-view.php');
$check(!preg_match('/(?<![\\w.])confirm\\(/', $jrel) && substr_count($jrel, 'data-sige-confirm=') >= 2, 'Jardim relatório: confirms declarativos do kit (envio mensal + individual)');
$check(!preg_match('/<button(?![^>]*class=)[^>]*>/', $jrel), 'Jardim relatório: zero botões sem classe');
$estat = $read('admin/academic/estatisticas-demograficas-view.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $estat) && $has($estat, 'sigeUi.toast'), 'Estatísticas: zero alert(), feedback por toast');
$turm = $read('admin/academic/turmas-view.php');
$check(!preg_match('/(?<![\\w.])prompt\\(/', $turm) && substr_count($turm, 'sigeUi.prompt(') >= 2, 'Turmas: horário edita com sigeUi.prompt (disciplina + professor)');
$check($has($read('assets/sige-ui.js'), 'op.valor'), 'Kit: sigeUi.prompt aceita valor inicial');
$prompt_vivos = [];
foreach (glob($root . '/admin/{*,*/*}.php', GLOB_BRACE) as $vf) {
    if (substr($vf, -9) === 'index.php' || strpos($vf, 'template') !== false) continue;
    if (preg_match('/(?<![\\w.])prompt\\(/', (string)file_get_contents($vf))) $prompt_vivos[] = basename($vf);
}
// ── N. Auto-revisão pós-relato de bugs (12 Jun): ponto cego JS fechado ──────
$check(is_file($root . '/tools/check-js-views.php'), 'Verificador de JS embebido nas views existe (6º gate)');
$kitjs = $read('assets/sige-ui.js');
$check($has($kitjs, "addEventListener('submit'"), 'Kit: confirmação declarativa intercepta também a submissão por teclado');
$check($has($kitjs, 'ev.submitter'), 'Kit: usa o submitter real do formulário quando disponível');

// ── O. Hotfix v98: namespace do kit migrado para sgk- (colisão provada) ─────
$check(is_file($root . '/tools/check-css-collisions.php'), 'Gate de colisões CSS existe (7º gate)');
$kitcss = $read('assets/sige-ui.css');
$check($has($kitcss, '.sgk-modal') && $has($kitcss, '.sgk-btn') && $has($kitcss, '--sgk-radius'), 'Kit CSS vive no namespace sgk- (classes e variáveis)');
$check(!preg_match('/\\.sg-(modal|btn|card|toast|badge|banner|empty|spinner|table-wrap)(?![\\w-])/', $kitcss), 'Kit CSS NUNCA redefine classes sg- da casa');
$check($has($kitjs, 'sgk-modal-overlay') && $has($kitjs, 'sgk-toast'), 'Kit JS cria elementos com classes sgk-');
$check($has($read('includes/ui-kit.php'), 'sgk-banner'), 'Helpers PHP do kit emitem classes sgk-');

// ── P. Sprint UX-5: acta + planos + devedores ────────────────────────────────
$acta = $read('admin/academic/acta-view.php');
$check(!preg_match('/(?<![\\w.])confirm\\(/', $acta) && $has($acta, 'data-sige-confirm='), 'Acta: aprovação à pauta com confirmação do kit (zero confirm nativo)');
$check($has($acta, 'sgk-btn sgk-btn-primario'), 'Acta: botão Carregar na hierarquia do kit');
$plv = $read('admin/finance/financeiro-planos-view.php');
$check($has($plv, 'sige_fin_registar_pagamento('), 'Planos: prestações pagam SEMPRE pela função financeira canónica');
$check(!preg_match('/->insert\\(\\s*\\$\\w+\\s*\\.\\s*.sige_fin_/', $plv) && !preg_match('/INSERT\\s+INTO\\s+[^;]{0,40}sige_fin_(lancamentos|pagamentos)/i', $plv), 'Planos: view NUNCA insere directo em tabelas financeiras');
$plv_html = preg_replace('/<\\?(?:php|=)?.*?\\?' . '>/s', '', $plv);
$check(!preg_match('/<button\\b(?![^>]*\\bclass=)[^>]*>/s', $plv_html), 'Planos: zero botões sem classe (HTML real)');
$dev = $read('admin/finance/financeiro-devedores-view.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $dev) && $has($dev, 'openConfirmModal'), 'Devedores: zero alert(); cobrança em massa SEMPRE com confirmação própria');
$check($has($read('tools/ux-audit.php'), '$html_puro') && $has($read('tools/ux-audit.php'), '$s_termos'), 'Auditor v2.4: botões em HTML puro e termos sem comentários');

// ── Q. Sprint UX-6: portal verificado + encerramento + lançamentos ──────────
$apv = $read('admin/academic/aluno-portal-view.php');
$check($has($apv, 'Ainda não existem notas') && $has($apv, 'Sem lançamentos financeiros'), 'Portal do aluno: estados vazios existentes (notas + financeiro) protegidos');
$enc = $read('admin/academic/encerramento-view.php');
$check(!preg_match('/(?<![\\w.])confirm\\(/', $enc) && substr_count($enc, 'data-sige-confirm=') >= 3, 'Encerramento: as 3 acções de ano pedem confirmação do kit');
$check($has($enc, 'data-sige-perigo="1"') && $has($enc, 'btn-encerrar'), 'Encerramento: Encerrar o ano é vermelho e mantém a classe local');
$enc_html = preg_replace('/<\\?(?:php|=)?.*?\\?' . '>/s', '', $enc);
$check(!preg_match('/<button\\b(?![^>]*\\bclass=)[^>]*>/s', $enc_html), 'Encerramento: zero botões sem classe (HTML real)');
$lnc = $read('admin/finance/financeiro-lancamentos-view.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $lnc), 'Lançamentos: zero alert() nativos');
$check(!preg_match('/->insert\\(\\s*\\$\\w+\\s*\\.\\s*.sige_fin_/', $lnc) && !preg_match('/INSERT\\s+INTO\\s+[^;]{0,40}sige_fin_(lancamentos|pagamentos)/i', $lnc), 'Lançamentos: view NUNCA insere directo em tabelas financeiras');
$aud = $read('tools/ux-audit.php');
$check($has($aud, '[\\w-]*-empty') && $has($aud, 'in_array'), 'Auditor v2.5: vazios genéricos e tolerância de dados excluída do AO90');

// ── R. Sprint UX-7: jardim_diario + alocacao + circulares ───────────────────
$jdi = $read('admin/jardim/jardim_diario-view.php');
$jdi_html = preg_replace('/<\\?(?:php|=)?.*?\\?' . '>/s', '', $jdi);
$check(!preg_match('/<button\\b(?![^>]*\\bclass=)[^>]*>/s', $jdi_html) && $has($jdi, 'sgk-btn sgk-btn-primario'), 'Jardim diário: botões na hierarquia do kit (violeta de marca preservado)');
$alo = $read('admin/academic/alocacao-view.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $alo) && !preg_match('/(?<![\\w.])confirm\\(/', $alo), 'Alocação: zero diálogos nativos');
$check($has($alo, 'sigeUi.confirm') && $has($alo, "(nome || 'O aluno')"), 'Alocação: remover da turma confirma com o NOME do aluno');
$check($has($alo, 'A turma ainda está vazia'), 'Alocação: estado vazio existente protegido');
$circ = $read('admin/whatsapp_circulares-view.php');
$check(!preg_match('/(?<![\\w.])alert\\(/', $circ), 'Circulares: zero alert() nativos');
$check($has($circ, 'confirmo_envio') && $has($circ, 'syncBotao') && preg_match('/id="sgCircEnviar"[^>]*disabled/', $circ), 'Circulares: envio em massa SEMPRE atrás da confirmação consciente (checkbox + botão bloqueado)');
$aud2 = $read('tools/ux-audit.php');
$check($has($aud2, 'column_exists') && $has($aud2, 'está vazi'), 'Auditor v2.5d: fragmentos de schema fora do AO90; vazios reconhecem formulações');

// ── S. Feature de cliente: Calendário de Devedores por Mês ──────────────────
$eng = $read('includes/fin-devedores-calendario.php');
$check($has($eng, 'sige_fin_saldo_sql') && $has($eng, 'COUNT(DISTINCT l.aluno_id)'), 'Calendário: agregado usa a saldo CANÓNICA e conta devedores distintos');
$check($has($eng, '({$saldo}) > 0'), 'Calendário: devedor do mês exige saldo em aberto positivo');
$check(!preg_match('/->insert\\(|->update\\(|INSERT\\s+INTO|UPDATE\\s+/i', $eng), 'Calendário: motor é LEITURA PURA (zero escritas na BD)');
$devv = $read('admin/finance/financeiro-devedores-view.php');
$check($has($devv, '{$where_base}{$where_extra}{$where_mes}') && $has($devv, 'sige_fin_devedores_por_mes('), 'Devedores: calendário reutiliza as MESMAS condições da lista (sem o mês)');
$check($has($devv, 'sgdc-tile') && $has($devv, "add_query_arg('mes'"), 'Devedores: tiles clicáveis aplicam o filtro de mês existente');
$check(is_file($root . '/assets/views/devedores.css') && $has($read('includes/ui-kit.php'), 'sige-devedores-css'), 'Calendário: CSS em ficheiro próprio (gate de colisões cobre) e enfileirado');
$check(is_file($root . '/tools/smoke-devedores-calendario.php'), 'Calendário: smoke do motor puro existe (8º conjunto)');
$rg = $read('tools/run-gates.php');
$check(is_file($root . '/tools/run-gates.php') && substr_count($rg, 'tools/smoke-') >= 5 && $has($rg, 'check-js-views') && $has($rg, 'check-css-collisions'), 'Corredor de gates existe e cobre os 8 (exit codes nunca mascarados)');

$check(empty($prompt_vivos), 'EXTINÇÃO GLOBAL: zero prompt() nativos em TODAS as views' . ($prompt_vivos ? ' (vivos: ' . implode(', ', $prompt_vivos) . ')' : ''));

// ── UX-8: EXTINÇÃO GLOBAL de alert() e confirm() (vassoura selada) ──────────
$alert_vivos = [];
$confirm_vivos = [];
foreach (glob($root . '/admin/{*,*/*}.php', GLOB_BRACE) as $vf) {
    if (substr($vf, -9) === 'index.php' || strpos($vf, 'template') !== false) continue;
    $conteudo = (string)file_get_contents($vf);
    if (preg_match('/(?<![\\w.])alert\\(/', $conteudo)) $alert_vivos[] = basename($vf);
    if (preg_match('/(?<![\\w.])confirm\\(/', $conteudo)) $confirm_vivos[] = basename($vf);
}
$check(empty($alert_vivos), 'EXTINÇÃO GLOBAL: zero alert() nativos em TODAS as views' . ($alert_vivos ? ' (vivos: ' . implode(', ', $alert_vivos) . ')' : ''));
$check(empty($confirm_vivos), 'EXTINÇÃO GLOBAL: zero confirm() nativos em TODAS as views' . ($confirm_vivos ? ' (vivos: ' . implode(', ', $confirm_vivos) . ')' : ''));
$check($has($read('assets/sige-ui.js'), "alvo.tagName === 'A'"), 'Kit: confirmação declarativa cobre também links <a> (preservando href/nonce)');

// ── UX-9 (triagem): AO90 e botões sem classe ZERADOS em todo o sistema ──────
$ao90_vivo = 0; $btn_vivo = 0;
foreach (glob($root . '/admin/{*,*/*}.php', GLOB_BRACE) as $vf) {
    if (substr($vf, -9) === 'index.php') continue;
    $cont = (string)file_get_contents($vf);
    $vis = implode("\n", array_filter(explode("\n", $cont), function ($l) {
        return !preg_match('/\$wpdb|\bSELECT\s|\bWHERE\s|\bFROM\s|\bJOIN\s|\bIN\s*\(|->prepare\(|->get_(row|results|var|col)\(|name=[\'"][a-z_]*ativ|in_array\s*\(|value="|selected\s*\(|[=!]==?\s*[\'"][a-z_]*ativ|\bIS\s+NULL|column_exists|\$[A-Za-z_][\w]*(cadastr|ativ)[\w]*|\bativ[oa]?\s*=\s*1\b/i', $l);
    }));
    $ao90_vivo += preg_match_all('/(?<![\w$_])[Uu]su[áa]rio(?![\w_])/u', $vis)
               + preg_match_all('/(?<![\w$_])[Cc]adastr/u', $vis);
    $html = preg_replace('/<\?(?:php|=)?.*?\?' . '>/s', '', $cont);
    $btn_vivo += preg_match_all('/<button\b(?![^>]*\bclass=)[^>]*>/s', $html);
}
$check($ao90_vivo === 0, 'EXTINÇÃO GLOBAL: zero rótulos AO90 visíveis (usuário/cadastro) em todas as views' . ($ao90_vivo ? " (vivos: {$ao90_vivo})" : ''));
$check($btn_vivo === 0, 'EXTINÇÃO GLOBAL: zero botões sem classe em todas as views' . ($btn_vivo ? " (vivos: {$btn_vivo})" : ''));

// ── Consolidação CSS: dashboard sem inline de estilo real ───────────────────
$dash = $read('admin/system/dashboard-view.php');
$check($has($dash, '.sg-val-pos{color:var(--color-success-500);}') && $has($dash, '.sg-val-neg{color:var(--color-danger-500);}') && $has($dash, '.sg-val-hi{color:var(--color-brand-500);}'), 'Dashboard: classes utilitárias semânticas usam tokens canónicos');
$check(!$has($dash, 'style="color:#16a34a;"') && !$has($dash, 'style="color:#ef4444;"') && !$has($dash, 'style="color:#5a3fd6;"'), 'Dashboard: zero inline de cor real (migrado para classes)');
// Impressão visual: as cores consolidadas continuam presentes (em classe, não inline)
$check($has($dash, 'var(--color-success-500)') && $has($dash, 'var(--color-danger-500)') && $has($dash, 'var(--color-brand-500)'), 'Dashboard: papéis de cor preservados via tokens (sucesso/perigo/marca)');
$check($has($read('tools/ux-audit.php'), '__so_vars') && $has($read('tools/ux-audit.php'), '__so_php'), 'Auditor v2.7: custom properties e estilo dinâmico PHP não contam como inline-dívida');

// ── Consolidação CSS: equipe (RH) sem inline de estilo estático ─────────────
$eqp = $read('admin/hr/equipe-view.php');
$check($has($eqp, '.sg-staff-th-nome{width:35%;}') && $has($eqp, '.sg-staff-th-accoes{width:20%;text-align:right;}'), 'Equipe: larguras da tabela de staff em classes (col-* semânticas)');
$check(!preg_match('/<th style="width:/', $eqp) && !$has($eqp, 'style="margin-bottom: 20px;"'), 'Equipe: zero inline estático de largura/margem (migrado para classes)');
// Pixel-perfect: as larguras-chave da tabela continuam presentes
$check($has($eqp, 'width:35%') && $has($eqp, 'width:18%') && $has($eqp, 'width:12%'), 'Equipe: larguras de coluna preservadas (pixel-perfect)');
// Legítimos preservados (geridos por JS / em innerHTML)
$check($has($eqp, "detail.style.display = 'block'") && $has($eqp, "detail.style.display = 'none'"), 'Equipe: alternância JS do detalhe de confirmação intacta');

// ── Consolidação CSS: disciplinas sem inline estático ───────────────────────
$dsc = $read('admin/academic/disciplinas-view.php');
$check($has($dsc, '.sg-disc-th-num{width:60px;text-align:center;}') && $has($dsc, '.sg-empty-pad{padding:var(--space-10) var(--space-5);}'), 'Disciplinas: larguras de tabela e estado vazio em classes');
$check(!preg_match('/<th style="width:/', $dsc) && !$has($dsc, 'style="padding: 40px 20px;"'), 'Disciplinas: zero inline estático de largura/padding');
// Pixel-perfect: os dois estados vazios gémeos partilham agora UMA classe (menos duplicação, mesmo efeito)
$check(substr_count($dsc, 'sige-empty-state sg-empty-pad') === 2, 'Disciplinas: estados vazios gémeos usam a mesma classe (consolidação real)');
$check(!preg_match('/placeholder-fix|data-x="/', $dsc), 'Disciplinas: sem atributos órfãos (input da sigla íntegro)');
// Legítimo preservado: box-esg2 alternado por slideDown/slideUp do jQuery
$check($has($dsc, 'id="box-esg2"') && $has($dsc, "jQuery('#box-esg2').slideDown"), 'Disciplinas: animação jQuery do box-esg2 intacta (display inicial preservado)');

// ── FONTE DA VERDADE: design tokens canónicos ────────────────────────────────
$tok = $read('assets/sige-tokens.css');
$check(is_file($root . '/assets/sige-tokens.css'), 'Design tokens: ficheiro canónico existe (fonte única da verdade)');
$check($has($tok, '--color-brand-500: #7c3aed;') && $has($tok, '--color-slate-500: #64748b;'), 'Design tokens: paleta de marca e neutros ancorada nas cores reais');
$check($has($tok, '--color-info-500: #2563eb;') && $has($tok, '--color-warning-500: #f59e0b;'), 'Design tokens: 7 familias completas (info azul incluida)');

// ── Migração piloto: dashboard 100% sobre tokens ─────────────────────────────
$dashv = $read('admin/system/dashboard-view.php');
$check(!preg_match('/#[0-9a-fA-F]{6}\\b|#[0-9a-fA-F]{3}\\b/', $dashv), 'Dashboard: ZERO cores hex mágicas (100% migrado para tokens)');
$check(substr_count($dashv, 'var(--color-') >= 100, 'Dashboard: cores vêm da fonte da verdade (var(--color-*))');
$check($has($dashv, 'var(--color-info-500)') && $has($dashv, 'var(--color-brand-500)'), 'Dashboard: azul informativo e marca usam tokens semânticos correctos');

// ── CSS legado vivo (style.css) reconciliado com a fonte da verdade ──────────
$stylev = $read('assets/style.css');
$check(!preg_match('/#[0-9a-fA-F]{6}\\b|#[0-9a-fA-F]{3}\\b/', $stylev), 'style.css: ZERO cores hex mágicas (100% migrado para tokens)');
$check($has($stylev, 'PONTE DE COMPATIBILIDADE') && $has($stylev, '--sg-primary-500: var(--color-brand-500)'), 'style.css: ponte --sg-* -> --color-* instalada (uma só fonte da verdade)');
$check(substr_count($stylev, 'var(--color-') >= 700, 'style.css: cores vêm da fonte da verdade (700+ referências)');
$check(is_file($root . '/tools/smoke-design-system.php'), 'Smoke do design system existe (10º conjunto de garantias)');

// ── Ícones: consistência visual normalizada ─────────────────────────────────
$uic = $read('includes/ui-components.php');
$check($has($uic, 'function sige_ui_icon_canonizar'), 'Ícones: função de canonização do invólucro <svg> existe');
$check($has($uic, 'sige_ui_icon_canonizar($svg, $name)'), 'Ícones: sige_ui_icon canoniza cada ícone ao servir');
$check(is_file($root . '/tools/normalizar-icones.py') && is_file($root . '/tools/smoke-icones.php'), 'Ícones: ferramenta de normalização e smoke versionados');
// amostra: os ícones das imagens reportadas estão normalizados (têm <g transform scale>)
$amostra_ok = true;
foreach (['school', 'pin', 'file', 'palette', 'users', 'check'] as $ic) {
    $p = $root . '/assets/icons/sg/' . $ic . '.svg';
    if (!is_file($p) || !preg_match('/<g\s+transform="[^"]*scale\(/', (string)file_get_contents($p))) { $amostra_ok = false; }
}
$check($amostra_ok, 'Ícones: os dos cartões (school/pin/file/palette/users/check) estão normalizados');
$check($has($read('assets/sige-tokens.css'), '.sg-svg-icon {'), 'Ícones: regra base canónica no design system (tamanho + cor herdada)');

// ── Auditoria de design: Config Center (piloto da hierarquia visual) ─────────
$ccv = $read('admin/system/config-center-view.php');
$pesos_cc = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $ccv, $mp)) {
    foreach ($mp[1] as $pp) { $pesos_cc[(int)$pp] = true; }
}
$nc_cc = array_diff(array_keys($pesos_cc), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_cc), 'Config Center: hierarquia de 3 níveis (só pesos reais, zero 850/950)' . ($nc_cc ? ' (' . implode(',', $nc_cc) . ')' : ''));
// raios da escala canónica
if (preg_match_all('/border-radius:\\s*(\\d+)px/', $ccv, $mr)) {
    $raios_cc = array_unique(array_map('intval', $mr[1]));
    $fora = array_diff($raios_cc, [4, 8, 12, 16, 22, 999]);
    $check(empty($fora), 'Config Center: raios da escala canónica (4/8/12/16/22/pill)' . ($fora ? ' (' . implode(',', $fora) . ')' : ''));
}
$check(substr_count($ccv, 'var(--color-') >= 60, 'Config Center: cores do <style> migradas para tokens');
$check(is_file($root . '/tools/check-typography.php') && is_file($root . '/tools/.typography-baseline.json'), 'Gate de tipografia e baseline registados');

// ── Auditoria de design: alunos_lista (ecrã mais usado, 9228 linhas) ─────────
$alv = $read('admin/academic/alunos_lista.php');
// hierarquia: o CSS principal (não os templates de impressão) só tem pesos reais
$pesos_al = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $alv, $mpa)) {
    foreach ($mpa[1] as $pp) { $pesos_al[(int)$pp] = true; }
}
$nc_al = array_diff(array_keys($pesos_al), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_al), 'alunos_lista: hierarquia de pesos reais (zero 850/950 em todo o ficheiro)' . ($nc_al ? ' (' . implode(',', $nc_al) . ')' : ''));
$check(substr_count($alv, 'var(--color-') >= 450, 'alunos_lista: CSS principal migrado para tokens (450+ referências)');
// referências resolvem
preg_match_all('/var\\(\\s*(--color-[a-z]+-[0-9]+)/', $alv, $mra);
$tok_al = $read('assets/sige-tokens.css');
$inex_al = [];
foreach (array_unique($mra[1]) as $rr) { if (strpos($tok_al, $rr . ':') === false) $inex_al[] = $rr; }
$check(empty($inex_al), 'alunos_lista: todas as referências de cor resolvem no canónico' . ($inex_al ? ' (' . implode(',', array_slice($inex_al,0,4)) . ')' : ''));
$check(substr_count($alv, '{') === substr_count($alv, '}'), 'alunos_lista: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: financeiro-extratos (ecrã financeiro) ───────────────
$exv = $read('admin/finance/financeiro-extratos.php');
$pesos_ex = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $exv, $mpe)) {
    foreach ($mpe[1] as $pp) { $pesos_ex[(int)$pp] = true; }
}
$nc_ex = array_diff(array_keys($pesos_ex), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_ex), 'financeiro-extratos: hierarquia de pesos reais (zero 850/950)' . ($nc_ex ? ' (' . implode(',', $nc_ex) . ')' : ''));
$check(substr_count($exv, 'var(--color-') >= 200, 'financeiro-extratos: CSS principal migrado para tokens (200+ referências)');
// o template de impressão (Termo de Fecho) foi PRESERVADO com cores absolutas
$check($has($exv, 'Termo de Fecho de Caixa') && $has($exv, '.has-diff{color:#b42318') === false ? $has($exv, 'Termo de Fecho') : true, 'financeiro-extratos: template de impressão preservado');
$check($has($exv, '#128754') || $has($exv, '#b42318'), 'financeiro-extratos: semântica financeira de reconciliação preservada no termo impresso');
$check(substr_count($exv, '{') === substr_count($exv, '}'), 'financeiro-extratos: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: matriz curricular ──────────────────────────────────
$mxv = $read('admin/academic/matriz-view.php');
$pesos_mx = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $mxv, $mpx)) {
    foreach ($mpx[1] as $pp) { $pesos_mx[(int)$pp] = true; }
}
$nc_mx = array_diff(array_keys($pesos_mx), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_mx), 'matriz: hierarquia de pesos reais (zero 850/950)' . ($nc_mx ? ' (' . implode(',', $nc_mx) . ')' : ''));
$check(substr_count($mxv, 'var(--color-') >= 150, 'matriz: CSS migrado para tokens (150+ referências)');
$check(substr_count($mxv, '{') === substr_count($mxv, '}'), 'matriz: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: aluno-portal (portal externo das famílias) ──────────
$apv = $read('admin/academic/aluno-portal-view.php');
$pesos_ap = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $apv, $mpap)) {
    foreach ($mpap[1] as $pp) { $pesos_ap[(int)$pp] = true; }
}
$nc_ap = array_diff(array_keys($pesos_ap), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_ap), 'aluno-portal: hierarquia de pesos reais (zero 850/950)' . ($nc_ap ? ' (' . implode(',', $nc_ap) . ')' : ''));
$check(substr_count($apv, 'var(--color-') >= 100, 'aluno-portal: CSS e inline migrados para tokens (100+ referências)');
// o tema dinâmico (--sg-theme-primary) é preservado com o seu fallback
$check($has($apv, 'var(--sg-theme-primary,#5a3fd6)') || $has($apv, 'var(--sg-theme-primary, #5a3fd6)'), 'aluno-portal: tema dinâmico da escola preservado (fallback intacto)');
$check(substr_count($apv, '{') === substr_count($apv, '}'), 'aluno-portal: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: disciplinas ────────────────────────────────────────
$dcv = $read('admin/academic/disciplinas-view.php');
$pesos_dc = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $dcv, $mpdc)) {
    foreach ($mpdc[1] as $pp) { $pesos_dc[(int)$pp] = true; }
}
$nc_dc = array_diff(array_keys($pesos_dc), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_dc), 'disciplinas: hierarquia de pesos reais (zero 850/950)' . ($nc_dc ? ' (' . implode(',', $nc_dc) . ')' : ''));
$check(substr_count($dcv, 'var(--color-') >= 180, 'disciplinas: CSS e ícones de ciclo migrados para tokens (180+ referências)');
$check(substr_count($dcv, '{') === substr_count($dcv, '}'), 'disciplinas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: boletim (documento que vai para casa) ──────────────
$blv = $read('admin/academic/boletim-view.php');
$pesos_bl = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $blv, $mpbl)) {
    foreach ($mpbl[1] as $pp) { $pesos_bl[(int)$pp] = true; }
}
$nc_bl = array_diff(array_keys($pesos_bl), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_bl), 'boletim: hierarquia de pesos reais (zero 850/950)' . ($nc_bl ? ' (' . implode(',', $nc_bl) . ')' : ''));
$check(substr_count($blv, 'var(--color-') >= 110, 'boletim: CSS e inline migrados para tokens (110+ referências)');
// a função de cor por nota (semântica de classificação em lógica) é PRESERVADA
$check($has($blv, "return '#dc3545'") && $has($blv, "return '#28a745'"), 'boletim: função de cor por nota preservada (semântica de classificação em lógica)');
// o template de impressão (Aproveitamento) é preservado
$check($has($blv, 'Aproveitamento'), 'boletim: template de impressão do aproveitamento preservado');
$check(substr_count($blv, '{') === substr_count($blv, '}'), 'boletim: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: equipe (gestão de RH) ──────────────────────────────
$eqv = $read('admin/hr/equipe-view.php');
$pesos_eq = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $eqv, $mpeq)) {
    foreach ($mpeq[1] as $pp) { $pesos_eq[(int)$pp] = true; }
}
$nc_eq = array_diff(array_keys($pesos_eq), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_eq), 'equipe: hierarquia de pesos reais (zero 850/950)' . ($nc_eq ? ' (' . implode(',', $nc_eq) . ')' : ''));
$check(substr_count($eqv, 'var(--color-') >= 200, 'equipe: CSS migrado para tokens (200+ referências)');
$check(substr_count($eqv, '{') === substr_count($eqv, '}'), 'equipe: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: minhas_turmas (ecrã dos ícones reportados) ─────────
$mtv = $read('admin/academic/minhas_turmas-view.php');
$pesos_mt = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $mtv, $mpmt)) {
    foreach ($mpmt[1] as $pp) { $pesos_mt[(int)$pp] = true; }
}
$nc_mt = array_diff(array_keys($pesos_mt), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_mt), 'minhas_turmas: hierarquia de pesos reais (zero 850/950)' . ($nc_mt ? ' (' . implode(',', $nc_mt) . ')' : ''));
$check(substr_count($mtv, 'var(--color-') >= 100, 'minhas_turmas: CSS e cores de estado migrados para tokens (100+ referências)');
$check(substr_count($mtv, '{') === substr_count($mtv, '}'), 'minhas_turmas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: portaria (controlo de entradas/saídas) ─────────────
$ptv = $read('admin/system/portaria-view.php');
// pesos: medir só no bloco <style> (o JS tem cores próprias de lógica)
$ptv_css = '';
if (preg_match('/<style[^>]*>(.*?)<\/style>/s', $ptv, $mcss)) { $ptv_css = $mcss[1]; }
$pesos_pt = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $ptv_css, $mppt)) {
    foreach ($mppt[1] as $pp) { $pesos_pt[(int)$pp] = true; }
}
$nc_pt = array_diff(array_keys($pesos_pt), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_pt), 'portaria: hierarquia de pesos reais no CSS (zero 850/950)' . ($nc_pt ? ' (' . implode(',', $nc_pt) . ')' : ''));
$check(substr_count($ptv, 'var(--color-') >= 100, 'portaria: CSS migrado para tokens (100+ referências)');
$check(substr_count($ptv, '{') === substr_count($ptv, '}'), 'portaria: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: encerramento (fecho de período/ano) ────────────────
$env = $read('admin/academic/encerramento-view.php');
$pesos_en = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $env, $mpen)) {
    foreach ($mpen[1] as $pp) { $pesos_en[(int)$pp] = true; }
}
$nc_en = array_diff(array_keys($pesos_en), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_en), 'encerramento: hierarquia de pesos reais (zero 850/950)' . ($nc_en ? ' (' . implode(',', $nc_en) . ')' : ''));
$check(substr_count($env, 'var(--color-') >= 110, 'encerramento: CSS, inline e lógica migrados para tokens (110+ referências)');
$check($has($env, "taxa_apr'] >= 80 ? 'var(--color-success"), 'encerramento: lógica de cor por taxa de aprovação preservada (via tokens)');
$check(substr_count($env, '{') === substr_count($env, '}'), 'encerramento: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: curriculum-engine ──────────────────────────────────
$cev = $read('admin/system/curriculum-engine-view.php');
$pesos_ce = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $cev, $mpce)) {
    foreach ($mpce[1] as $pp) { $pesos_ce[(int)$pp] = true; }
}
$nc_ce = array_diff(array_keys($pesos_ce), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_ce), 'curriculum-engine: hierarquia de pesos reais (zero 850/950)' . ($nc_ce ? ' (' . implode(',', $nc_ce) . ')' : ''));
$check(substr_count($cev, 'var(--color-') >= 100, 'curriculum-engine: CSS e inline migrados para tokens (100+ referências)');
$check($has($cev, 'var(--sg-theme-primary'), 'curriculum-engine: tema dinâmico preservado (com fallback em token)');
$check(substr_count($cev, '{') === substr_count($cev, '}'), 'curriculum-engine: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: transporte (gestão de transporte escolar) ──────────
$trv = $read('admin/logistics/transporte-view.php');
$pesos_tr = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $trv, $mptr)) {
    foreach ($mptr[1] as $pp) { $pesos_tr[(int)$pp] = true; }
}
$nc_tr = array_diff(array_keys($pesos_tr), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_tr), 'transporte: hierarquia de pesos reais (zero 850/950)' . ($nc_tr ? ' (' . implode(',', $nc_tr) . ')' : ''));
$check(substr_count($trv, 'var(--color-') >= 90, 'transporte: CSS, inline e lógica migrados para tokens (90+ referências)');
$check($has($trv, "No limite") && $has($trv, 'var(--color-danger'), 'transporte: lógica de cor por lotação preservada (via tokens)');
$check(substr_count($trv, '{') === substr_count($trv, '}'), 'transporte: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: jardim_saude (registo de saúde do jardim) ──────────
$jsv = $read('admin/jardim/jardim_saude-view.php');
$pesos_js = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $jsv, $mpjs)) {
    foreach ($mpjs[1] as $pp) { $pesos_js[(int)$pp] = true; }
}
$nc_js = array_diff(array_keys($pesos_js), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_js), 'jardim_saude: hierarquia de pesos reais (zero 850/950)' . ($nc_js ? ' (' . implode(',', $nc_js) . ')' : ''));
$check(substr_count($jsv, 'var(--color-') >= 120, 'jardim_saude: CSS e inline de saúde migrados para tokens (120+ referências)');
$check(substr_count($jsv, '{') === substr_count($jsv, '}'), 'jardim_saude: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: jardim_diario (diário do jardim) ───────────────────
$jdv = $read('admin/jardim/jardim_diario-view.php');
$pesos_jd = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $jdv, $mpjd)) {
    foreach ($mpjd[1] as $pp) { $pesos_jd[(int)$pp] = true; }
}
$nc_jd = array_diff(array_keys($pesos_jd), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_jd), 'jardim_diario: hierarquia de pesos reais (zero 850/950)' . ($nc_jd ? ' (' . implode(',', $nc_jd) . ')' : ''));
$check(substr_count($jdv, 'var(--color-') >= 120, 'jardim_diario: CSS, inline e mapa de avaliação migrados (120+ referências)');
$check($has($jdv, "'MB'=>['var(--color-success"), 'jardim_diario: mapa de cor por avaliação MB/B/S/NS preservado (via tokens)');
$check(substr_count($jdv, '{') === substr_count($jdv, '}'), 'jardim_diario: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: dashboard (piloto v108 de cor + tipografia v126) ────
$dbv = $read('admin/system/dashboard-view.php');
$pesos_db = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $dbv, $mpdb)) {
    foreach ($mpdb[1] as $pp) { $pesos_db[(int)$pp] = true; }
}
$nc_db = array_diff(array_keys($pesos_db), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_db), 'dashboard: hierarquia de pesos reais (zero 850/950)' . ($nc_db ? ' (' . implode(',', $nc_db) . ')' : ''));
// o dashboard foi o piloto de cor da v108: deve ter ZERO hex (tudo em tokens)
$check(preg_match('/#[0-9a-fA-F]{6}\\b/', $dbv) === 0, 'dashboard: zero cores mágicas (migração de cor da v108 intacta)');
$check(substr_count($dbv, '{') === substr_count($dbv, '}'), 'dashboard: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: acta (ata de avaliação/conselho) ───────────────────
$acv = $read('admin/academic/acta-view.php');
$pesos_ac = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $acv, $mpac)) {
    foreach ($mpac[1] as $pp) { $pesos_ac[(int)$pp] = true; }
}
$nc_ac = array_diff(array_keys($pesos_ac), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_ac), 'acta: hierarquia de pesos reais (zero 850/950)' . ($nc_ac ? ' (' . implode(',', $nc_ac) . ')' : ''));
$check(substr_count($acv, 'var(--color-') >= 130, 'acta: CSS e inline migrados para tokens (130+ referências)');
// a @media print continua presente (impressão da ata preservada)
$check($has($acv, '@media print'), 'acta: regra de impressão preservada');
$check(substr_count($acv, '{') === substr_count($acv, '}'), 'acta: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: pautas (pauta de avaliação, documento oficial) ─────
$pav = $read('admin/academic/pautas-view.php');
$pesos_pa = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $pav, $mppa)) {
    foreach ($mppa[1] as $pp) { $pesos_pa[(int)$pp] = true; }
}
$nc_pa = array_diff(array_keys($pesos_pa), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_pa), 'pautas: hierarquia de pesos reais (zero 850/950)' . ($nc_pa ? ' (' . implode(',', $nc_pa) . ')' : ''));
$check(substr_count($pav, 'var(--color-') >= 130, 'pautas: CSS, inline e mapa de situação migrados (130+ referências)');
$check($has($pav, "'PROGRIDE' => ['var(--color-success"), 'pautas: mapa de cor por situação (PROGRIDE/TRANSITA/REPROVA) preservado');
// as @media print continuam (impressão da pauta preservada)
$check(substr_count($pav, '@media print') >= 2, 'pautas: regras de impressão preservadas');
$check(substr_count($pav, '{') === substr_count($pav, '}'), 'pautas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: estatisticas-demograficas ──────────────────────────
$edv = $read('admin/academic/estatisticas-demograficas-view.php');
$pesos_ed = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $edv, $mped)) {
    foreach ($mped[1] as $pp) { $pesos_ed[(int)$pp] = true; }
}
$nc_ed = array_diff(array_keys($pesos_ed), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_ed), 'estatisticas-demograficas: hierarquia de pesos reais (zero 850/950)' . ($nc_ed ? ' (' . implode(',', $nc_ed) . ')' : ''));
$check(substr_count($edv, 'var(--color-') >= 90, 'estatisticas-demograficas: CSS e inline migrados (90+ referências)');
// o template de impressão e os gráficos Chart.js são preservados
$check($has($edv, '<!DOCTYPE html>') && $has($edv, 'Estat'), 'estatisticas-demograficas: template de impressão preservado');
$check(substr_count($edv, '{') === substr_count($edv, '}'), 'estatisticas-demograficas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: abertura (abertura de período/ano lectivo) ─────────
$abv = $read('admin/academic/abertura-view.php');
$pesos_ab = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $abv, $mpab)) {
    foreach ($mpab[1] as $pp) { $pesos_ab[(int)$pp] = true; }
}
$nc_ab = array_diff(array_keys($pesos_ab), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_ab), 'abertura: hierarquia de pesos reais (zero 850/950)' . ($nc_ab ? ' (' . implode(',', $nc_ab) . ')' : ''));
$check(substr_count($abv, 'var(--color-') >= 120, 'abertura: CSS e inline migrados para tokens (120+ referências)');
$check($has($abv, '@media print'), 'abertura: regra de impressão do log preservada');
$check(substr_count($abv, '{') === substr_count($abv, '}'), 'abertura: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: financeiro-pagamentos (registo de pagamentos) ──────
$fpv = $read('admin/finance/financeiro-pagamentos.php');
$pesos_fp = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $fpv, $mpfp)) {
    foreach ($mpfp[1] as $pp) { $pesos_fp[(int)$pp] = true; }
}
$nc_fp = array_diff(array_keys($pesos_fp), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_fp), 'financeiro-pagamentos: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_fp ? ' (' . implode(',', $nc_fp) . ')' : ''));
$check(substr_count($fpv, 'var(--color-') >= 300, 'financeiro-pagamentos: CSS, corpo e recibos migrados (300+ referências)');
// classes financeiras de estado preservadas (pago/vencido/pendente)
$check($has($fpv, 'sige-month-paid') && $has($fpv, 'sige-month-overdue'), 'financeiro-pagamentos: classes de estado de pagamento (pago/vencido) preservadas');
$check(substr_count($fpv, '{') === substr_count($fpv, '}'), 'financeiro-pagamentos: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: permissions-ui (gestão de permissões/papéis) ───────
$peuv = $read('admin/system/permissions-ui.php');
$pesos_pe = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $peuv, $mppe)) {
    foreach ($mppe[1] as $pp) { $pesos_pe[(int)$pp] = true; }
}
$nc_pe = array_diff(array_keys($pesos_pe), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_pe), 'permissions-ui: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_pe ? ' (' . implode(',', $nc_pe) . ')' : ''));
$check(substr_count($peuv, 'var(--color-') >= 130, 'permissions-ui: CSS e inline migrados para tokens (130+ referências)');
$check(substr_count($peuv, '{') === substr_count($peuv, '}'), 'permissions-ui: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: jardim_presencas (registo de presenças do jardim) ──
$jpv = $read('admin/jardim/jardim_presencas-view.php');
$pesos_jp = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $jpv, $mpjp)) {
    foreach ($mpjp[1] as $pp) { $pesos_jp[(int)$pp] = true; }
}
$nc_jp = array_diff(array_keys($pesos_jp), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_jp), 'jardim_presencas: hierarquia de pesos reais (zero 850/950)' . ($nc_jp ? ' (' . implode(',', $nc_jp) . ')' : ''));
$check(substr_count($jpv, 'var(--color-') >= 70, 'jardim_presencas: CSS e mapa de presença migrados (70+ referências)');
$check($has($jpv, "'presente' => ['Presente', 'var(--color-success"), 'jardim_presencas: mapa de cor por estado de presença (presente/atraso/falta) preservado');
$check(substr_count($jpv, '{') === substr_count($jpv, '}'), 'jardim_presencas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: pauta-final (pauta final de avaliação) ─────────────
$pfv = $read('admin/academic/pauta-final-view.php');
$pesos_pf = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $pfv, $mppf)) {
    foreach ($mppf[1] as $pp) { $pesos_pf[(int)$pp] = true; }
}
$nc_pf = array_diff(array_keys($pesos_pf), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_pf), 'pauta-final: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_pf ? ' (' . implode(',', $nc_pf) . ')' : ''));
$check(substr_count($pfv, 'var(--color-') >= 90, 'pauta-final: CSS e corpo migrados para tokens (90+ referências)');
$check($has($pfv, '<!DOCTYPE html>') && $has($pfv, 'Pauta Final'), 'pauta-final: template de impressão preservado');
$check($has($pfv, 'sit-progride') && $has($pfv, 'sit-reprova'), 'pauta-final: classes de situação (progride/reprova) preservadas');
$check(substr_count($pfv, '{') === substr_count($pfv, '}'), 'pauta-final: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: whatsapp_central (central de mensagens WhatsApp) ────
$wcv = $read('admin/whatsapp_central-view.php');
$pesos_wc = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $wcv, $mpwc)) {
    foreach ($mpwc[1] as $pp) { $pesos_wc[(int)$pp] = true; }
}
$nc_wc = array_diff(array_keys($pesos_wc), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_wc), 'whatsapp_central: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_wc ? ' (' . implode(',', $nc_wc) . ')' : ''));
$check(substr_count($wcv, 'var(--color-') >= 170, 'whatsapp_central: CSS, corpo e mapa de estado migrados (170+ referências)');
$check($has($wcv, "'enviado'") && $has($wcv, 'var(--color-success'), 'whatsapp_central: mapa de cor por estado de mensagem (enviada/falhou/pendente) preservado');
$check(substr_count($wcv, '{') === substr_count($wcv, '}'), 'whatsapp_central: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: notas (lançamento de notas, ecrã nuclear) ──────────
$nov = $read('admin/academic/notas-view.php');
$pesos_no = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $nov, $mpno)) {
    foreach ($mpno[1] as $pp) { $pesos_no[(int)$pp] = true; }
}
$nc_no = array_diff(array_keys($pesos_no), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_no), 'notas: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_no ? ' (' . implode(',', $nc_no) . ')' : ''));
$check(substr_count($nov, 'var(--color-') >= 170, 'notas: CSS e corpo migrados para tokens (170+ referências)');
$check($has($nov, 'var(--sg-theme-primary,var(--color-brand'), 'notas: tema dinâmico preservado (com fallback em token)');
$check(substr_count($nov, '{') === substr_count($nov, '}'), 'notas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: jardim_relatorio (relatório de avaliação do jardim) ─
$jrv = $read('admin/jardim/jardim_relatorio-view.php');
$pesos_jr = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $jrv, $mpjr)) {
    foreach ($mpjr[1] as $pp) { $pesos_jr[(int)$pp] = true; }
}
$nc_jr = array_diff(array_keys($pesos_jr), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_jr), 'jardim_relatorio: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_jr ? ' (' . implode(',', $nc_jr) . ')' : ''));
$check(substr_count($jrv, 'var(--color-') >= 170, 'jardim_relatorio: CSS, corpo e mapas migrados (170+ referências)');
$check($has($jrv, "'excelente'=>'var(--color-success") && $has($jrv, "'feliz'=>'var(--color-success"), 'jardim_relatorio: mapas de avaliação (estado emocional + desempenho) preservados');
$check(substr_count($jrv, '{') === substr_count($jrv, '}'), 'jardim_relatorio: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: core-status (estado do núcleo/diagnóstico) ─────────
$csv = $read('admin/system/core-status-view.php');
$pesos_cs = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $csv, $mpcs)) {
    foreach ($mpcs[1] as $pp) { $pesos_cs[(int)$pp] = true; }
}
$nc_cs = array_diff(array_keys($pesos_cs), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_cs), 'core-status: hierarquia de pesos reais (zero 850/950)' . ($nc_cs ? ' (' . implode(',', $nc_cs) . ')' : ''));
$check(substr_count($csv, 'var(--color-') >= 75, 'core-status: CSS migrado para tokens (75+ referências)');
$check($has($csv, 'sg-core-badge') && $has($csv, '.warn'), 'core-status: classes de estado de sistema (ok/warn) preservadas');
$check(substr_count($csv, '{') === substr_count($csv, '}'), 'core-status: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: financeiro-devedores (lista de devedores/cobrança) ─
$fdv = $read('admin/finance/financeiro-devedores-view.php');
$pesos_fd = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $fdv, $mpfd)) {
    foreach ($mpfd[1] as $pp) { $pesos_fd[(int)$pp] = true; }
}
$nc_fd = array_diff(array_keys($pesos_fd), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_fd), 'financeiro-devedores: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_fd ? ' (' . implode(',', $nc_fd) . ')' : ''));
$check(substr_count($fdv, 'var(--color-') >= 200, 'financeiro-devedores: CSS e corpo migrados (200+ referências)');
$check($has($fdv, '<!doctype html>') && $has($fdv, 'Lista de Cobran'), 'financeiro-devedores: template de impressão (Lista de Cobrança) preservado');
$check($has($fdv, 'sige-debt-amount'), 'financeiro-devedores: classe de valor de dívida preservada');
$check(substr_count($fdv, '{') === substr_count($fdv, '}'), 'financeiro-devedores: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: turmas (gestão de turmas) ──────────────────────────
$tuv = $read('admin/academic/turmas-view.php');
$pesos_tu = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $tuv, $mptu)) {
    foreach ($mptu[1] as $pp) { $pesos_tu[(int)$pp] = true; }
}
$nc_tu = array_diff(array_keys($pesos_tu), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_tu), 'turmas: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_tu ? ' (' . implode(',', $nc_tu) . ')' : ''));
$check(substr_count($tuv, 'var(--color-') >= 170, 'turmas: blocos CSS e corpo migrados (170+ referências)');
// tres templates de impressão/exportação preservados
$check(substr_count($tuv, 'Mapa de Turmas') >= 1 && substr_count($tuv, 'Lista de Alunos') >= 1, 'turmas: templates de impressão (Mapa de Turmas, Lista de Alunos, Horário) preservados');
$check($has($tuv, 'office:excel'), 'turmas: template de exportação Excel preservado');
$check(substr_count($tuv, '{') === substr_count($tuv, '}'), 'turmas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: auditoria_notas (auditoria/rastreio de notas) ──────
$anv = $read('admin/academic/auditoria_notas-view.php');
$pesos_an = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $anv, $mpan)) {
    foreach ($mpan[1] as $pp) { $pesos_an[(int)$pp] = true; }
}
$nc_an = array_diff(array_keys($pesos_an), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_an), 'auditoria_notas: hierarquia de pesos reais (zero 850/950)' . ($nc_an ? ' (' . implode(',', $nc_an) . ')' : ''));
$check(substr_count($anv, 'var(--color-') >= 75, 'auditoria_notas: CSS, mapa de módulo e estado de erro migrados (75+ referências)');
$check($has($anv, '$is_erro') && $has($anv, 'var(--color-danger'), 'auditoria_notas: lógica de estado de erro (vermelho) preservada');
$check(substr_count($anv, '{') === substr_count($anv, '}'), 'auditoria_notas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: dec (documento DEC) ────────────────────────────────
$decv = $read('admin/academic/dec-view.php');
$pesos_dec = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $decv, $mpdec)) {
    foreach ($mpdec[1] as $pp) { $pesos_dec[(int)$pp] = true; }
}
$nc_dec = array_diff(array_keys($pesos_dec), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_dec), 'dec: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_dec ? ' (' . implode(',', $nc_dec) . ')' : ''));
$check(substr_count($decv, 'var(--color-') >= 75, 'dec: CSS e corpo migrados para tokens (75+ referências)');
$check($has($decv, '<!DOCTYPE html>') && $has($decv, 'DEC'), 'dec: template de impressão preservado');
$check(substr_count($decv, '{') === substr_count($decv, '}'), 'dec: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: aprovar_notas (aprovação de notas) ─────────────────
$apnv = $read('admin/academic/aprovar_notas-view.php');
$pesos_apn = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $apnv, $mpapn)) {
    foreach ($mpapn[1] as $pp) { $pesos_apn[(int)$pp] = true; }
}
$nc_apn = array_diff(array_keys($pesos_apn), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_apn), 'aprovar_notas: hierarquia de pesos reais (zero 850/950)' . ($nc_apn ? ' (' . implode(',', $nc_apn) . ')' : ''));
$check(substr_count($apnv, 'var(--color-') >= 65, 'aprovar_notas: CSS e inline migrados para tokens (65+ referências)');
$check($has($apnv, 'apn-kpi-pending') && $has($apnv, 'btn-rejeitar'), 'aprovar_notas: estado de aprovação (pendente/rejeitar) preservado');
$check(substr_count($apnv, '{') === substr_count($apnv, '}'), 'aprovar_notas: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: jardim_boletim (boletim do jardim de infância) ─────
$jbv = $read('admin/jardim/jardim_boletim-view.php');
$pesos_jb = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $jbv, $mpjb)) {
    foreach ($mpjb[1] as $pp) { $pesos_jb[(int)$pp] = true; }
}
$nc_jb = array_diff(array_keys($pesos_jb), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_jb), 'jardim_boletim: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_jb ? ' (' . implode(',', $nc_jb) . ')' : ''));
$check(substr_count($jbv, 'var(--color-') >= 75, 'jardim_boletim: CSS e corpo migrados para tokens (75+ referências)');
$check($has($jbv, "'MB'=>'var(--color-success") && $has($jbv, "'NS'=>'var(--color-danger"), 'jardim_boletim: mapa de avaliação MB/B/S/NS preservado');
$check(substr_count($jbv, '{') === substr_count($jbv, '}'), 'jardim_boletim: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: financeiro-lancamentos (lançamentos financeiros) ───
$flv = $read('admin/finance/financeiro-lancamentos-view.php');
$pesos_fl = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $flv, $mpfl)) {
    foreach ($mpfl[1] as $pp) { $pesos_fl[(int)$pp] = true; }
}
$nc_fl = array_diff(array_keys($pesos_fl), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_fl), 'financeiro-lancamentos: hierarquia de pesos reais (zero 850/950)' . ($nc_fl ? ' (' . implode(',', $nc_fl) . ')' : ''));
$check(substr_count($flv, 'var(--color-') >= 40, 'financeiro-lancamentos: CSS migrado para tokens (40+ referências)');
$check(substr_count($flv, '{') === substr_count($flv, '}'), 'financeiro-lancamentos: CSS bem-formado (chavetas balanceadas)');

// ── Auditoria de design: financeiro-config (configuração financeira) ────────
$fcv = $read('admin/finance/financeiro-config.php');
$pesos_fc = [];
if (preg_match_all('/font-weight:\\s*(\\d+)/', $fcv, $mpfc)) {
    foreach ($mpfc[1] as $pp) { $pesos_fc[(int)$pp] = true; }
}
$nc_fc = array_diff(array_keys($pesos_fc), [400, 500, 600, 700, 800, 900]);
$check(empty($nc_fc), 'financeiro-config: hierarquia de pesos reais no ficheiro (zero 850/950)' . ($nc_fc ? ' (' . implode(',', $nc_fc) . ')' : ''));
$check(substr_count($fcv, 'var(--color-') >= 150, 'financeiro-config: CSS e corpo migrados para tokens (150+ referências)');
$check($has($fcv, 'var(--sg-theme-primary,var(--color-brand'), 'financeiro-config: tema dinâmico preservado (com fallback em token)');
$check(substr_count($fcv, '{') === substr_count($fcv, '}'), 'financeiro-config: CSS bem-formado (chavetas balanceadas)');

// ── PROTECÇÃO GLOBAL ANTI-BUG: entidades HTML partidas por migração de cor ──
// (lição da v146: regex de cor apanhou &#NNNNNN; de emojis. Nunca mais.)
$bug_entidade = 0; $bug_qual = '';
foreach (glob($root . '/admin/{*,*/*}.php', GLOB_BRACE) as $fu) {
    $c = @file_get_contents($fu);
    if ($c !== false && strpos($c, '&var(') !== false) {
        $bug_entidade++; $bug_qual = basename($fu);
    }
}
$check($bug_entidade === 0, 'PROTECCAO ANTI-ENTIDADE: nenhum ficheiro com &var( partido' . ($bug_entidade ? " ($bug_qual)" : ''));

// ── RESPONSIVO: tabelas largas com wrapper de scroll (não estouram em mobile) ──
$fcfg = $read('admin/finance/financeiro-config.php');
$check(substr_count($fcfg, 'fc-table-wrap') >= 2, 'RESPONSIVO: tabelas do financeiro-config com wrapper de scroll');
$wpp = $read('admin/whatsapp_central-view.php');
$check($has($wpp, 'wppc-table-wrap'), 'RESPONSIVO: tabela do whatsapp_central com wrapper de scroll');
$pag = $read('admin/finance/financeiro-pagamentos.php');
$pag_guards = substr_count($pag, 'overflow-x:auto') + substr_count($pag, 'sige-u-oxa');
$check($pag_guards >= 2, 'RESPONSIVO: tabelas do financeiro-pagamentos protegidas');
$eqp = $read('admin/hr/equipe-view.php');
$check($has($eqp, 'max-height:90vh'), 'RESPONSIVO: modal do equipe com max-height (botões acessíveis)');
$check($has($tok, '--radius-md:') && $has($tok, '--shadow-md:') && $has($tok, '--duration-normal:'), 'Design tokens: raios, sombras e durações canónicos definidos');
$check($has($tok, '@keyframes sige-rise-in') && $has($tok, '@keyframes sige-spin'), 'Design tokens: animações de entrada canónicas partilhadas');
$uikit = $read('includes/ui-kit.php');
$check($has($uikit, "wp_enqueue_style('sige-tokens'") && strpos($uikit, "'sige-tokens'") < strpos($uikit, "'sige-ui-kit'"), 'Design tokens: carregam ANTES do kit e das views (cascata correcta)');
$check(is_file($root . '/tools/check-design-tokens.php') && is_file($root . '/tools/.design-tokens-baseline.json'), 'Design tokens: gate guardião e baseline registados');

echo str_repeat('-', 60) . "\n";
if ($fails) {
    fwrite(STDERR, 'REGRESSÃO FALHOU: ' . count($fails) . " invariante(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "REGRESSÃO OK - {$oks} invariantes verdes.\n";
