<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test v12.11.9.69 - Alunos Deep Audit + UX/Security Hardening
 * Verifica apenas invariantes estáticos/estruturais. Não toca na BD.
 */
$root = dirname(__DIR__);
$read = static function(string $rel) use ($root): string {
    $path = $root . '/' . ltrim($rel, '/');
    return is_file($path) ? (string)file_get_contents($path) : '';
};
$main = $read('sige-softgenial.php');
$build = $read('BUILD.json');
$changelog = $read('CHANGELOG-v12-11-9-69-alunos-deep-audit-ux-security-hardening.txt');
$alunos = $read('admin/academic/alunos_lista.php');
$ajax = $read('includes/aluno-fetch-ajax.php');
$secure = $read('includes/secure-document-download.php');
$roles = $read('includes/security-roles.php');
$perms = $read('includes/permissions-layer.php');
$asset = $read('assets/mobile-tablet-ux.js');

$checks = [
    'version_header_12_11_9_69' => strpos($main, 'Version: 12.11.9.69') !== false,
    'version_constant_12_11_9_69' => strpos($main, "define('SIGE_VERSION', '12.11.9.69');") !== false,
    'build_version_12_11_9_69' => strpos($build, '12.11.9.69') !== false,
    'build_slug_deep_audit' => strpos($build, 'alunos-deep-audit') !== false,
    'changelog_69_present' => strpos($changelog, 'Alunos Deep Audit') !== false,

    'server_search_includes_turma_nome' => strpos($alunos, 't.nome LIKE %s') !== false,
    'server_search_includes_classe' => strpos($alunos, 't.classe LIKE %s') !== false,
    'count_uses_distinct_student' => strpos($alunos, 'COUNT(DISTINCT a.id)') !== false,
    'matricula_join_scoped_school' => strpos($alunos, 'm.escola_id = a.escola_id') !== false,
    'turma_join_scoped_school' => strpos($alunos, 't.escola_id = a.escola_id') !== false,

    'admin_documents_view_scope' => strpos($alunos, '$sige_alunos_can_documents_view') !== false,
    'admin_documents_emit_scope' => strpos($alunos, '$sige_alunos_can_documents_emit') !== false,
    'admin_export_scope' => strpos($alunos, '$sige_alunos_can_export') !== false,
    'admin_no_overbroad_documents_non_guard' => strpos($alunos, '$sige_alunos_can_documents = !$sige_alunos_is_guarda;') === false,
    'admin_no_overbroad_export_non_guard' => strpos($alunos, '$sige_alunos_can_export = !$sige_alunos_is_guarda;') === false,
    'admin_card_extra_actions_guard' => strpos($alunos, '$sige_aluno_card_has_extra_actions') !== false,
    'admin_bi_uses_documents_view' => strpos($alunos, '$sige_alunos_can_documents_view && $has_bi') !== false,
    'admin_emit_buttons_use_emit_scope' => substr_count($alunos, '$sige_alunos_can_documents_emit') >= 4,

    'frontend_documents_view_flag' => strpos($alunos, 'window.sigeAlunosCanDocumentsView') !== false,
    'frontend_export_flag' => strpos($alunos, 'window.sigeAlunosCanExport') !== false,
    'fetch_helper_accepts_purpose' => strpos($alunos, 'async function sigeGetTodosAlunos(purpose)') !== false,
    'fetch_helper_posts_purpose' => strpos($alunos, 'purpose: purpose') !== false,
    'fetch_cache_by_purpose' => strpos($alunos, '_sigeTodosAlunosCache = {}') !== false,
    'excel_uses_excel_purpose' => strpos($alunos, "sigeGetTodosAlunos('excel')") !== false,
    'cards_uses_cards_purpose' => strpos($alunos, "sigeGetTodosAlunos('cards')") !== false,
    'export_filters_shared' => strpos($alunos, 'sigeAlunosFiltrarPorFiltrosActuais') !== false,
    'export_filters_server_payload' => strpos($alunos, 'filtro_turma: filtrosExport.filtro_turma') !== false && strpos($alunos, 'filtro_search: filtrosExport.filtro_search') !== false,
    'export_filter_accent_normalize' => strpos($alunos, 'function sigeAlunoNormalizeSearch') !== false && strpos($alunos, "normalize('NFD')") !== false,
    'toast_no_html_concat' => strpos($alunos, "var toast = jQuery('<div class=\"sige-toast ") === false,
    'toast_uses_text_node' => strpos($alunos, ".addClass('sige-toast').addClass(safeType).text(String(msg || ''))") !== false,
    'ficha360_perms_payload_frontend' => strpos($alunos, 'var perms = data.permissoes || {};') !== false,
    'ficha360_sensitive_scope_frontend' => strpos($alunos, 'var canSeeSensitive360') !== false && strpos($alunos, 'perms.dados_sensiveis !== false') !== false && strpos($alunos, 'perms.sensivel !== false') !== false,
    'ficha360_quality_gated' => strpos($alunos, 'if (canSeeQuality360) html += sigeAluno360Card') !== false,
    'ficha360_docs_card_gated' => strpos($alunos, "if (canSeeDocs360) html += sigeAluno360Card('Documentos'") !== false,
    'all_turmas_zero_match' => strpos($alunos, "tr === '0'") !== false,
    'clear_filters_uses_zero_turma' => strpos($alunos, "#filtro-turma').val('0')") !== false,

    'mobile_fit_marker_69' => strpos($alunos, 'Deep Audit Alunos: blindagem extra de fit-on-screen') !== false,
    'mobile_page_overflow_clipped' => strpos($alunos, 'overflow-x:clip') !== false,
    'mobile_min_width_zero' => strpos($alunos, 'min-width:0') !== false,
    'mobile_overflow_wrap_anywhere' => strpos($alunos, 'overflow-wrap:anywhere') !== false,
    'birthday_hidden_css' => strpos($alunos, 'is-birthday-hidden') !== false,
    'birthday_v69_script' => strpos($alunos, 'sige-alunos-deep-audit-fix-v1211969') !== false,
    'birthday_v69_ready_class' => strpos($alunos, 'sige-alunos-v69-deep-audit-ready') !== false,

    'photo_alt_contextual' => strpos($alunos, "'Foto de ' . \$nome") !== false,
    'data_quality_aria_label' => strpos($alunos, 'aria-label="Abrir Ficha 360º de') !== false,
    'actions_summary_aria_expanded' => strpos($alunos, 'aria-expanded="false"') !== false && strpos($alunos, "attr('aria-expanded'") !== false,
    'mobile_edit_label_clear' => strpos($alunos, '<span>Editar</span>') !== false,

    'ajax_view_documents_helper' => strpos($ajax, 'sige_alunos_user_can_view_documents') !== false,
    'ajax_emit_documents_helper' => strpos($ajax, 'sige_alunos_user_can_emit_documents') !== false,
    'ajax_export_students_helper' => strpos($ajax, 'sige_alunos_user_can_export_students') !== false,
    'ajax_export_scope_no_alunos_editar' => strpos($ajax, "['documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.estatisticas_ver','alunos.editar']") === false,
    'ajax_purpose_whitelist' => strpos($ajax, "['excel','export','cards']") !== false,
    'ajax_cards_permission_error' => strpos($ajax, 'Sem permissão para imprimir cartões em lote') !== false,
    'ajax_export_permission_error' => strpos($ajax, 'Sem permissão para exportar listas de alunos') !== false,
    'ajax_cards_minimization' => strpos($ajax, 'minimização de dados para cartões') !== false,
    'ajax_cards_success_minimal' => strpos($ajax, 'wp_send_json_success($cards)') !== false,
    'ajax_ficha360_docs_scope' => strpos($ajax, '$can_view_docs_360') !== false,
    'ajax_ficha360_scope_payload' => strpos($ajax, "'can_view_documents' => \$can_view_docs_360") !== false,
    'ajax_ficha360_sensitive_payload_alias' => strpos($ajax, "'dados_sensiveis' => \$can_view_sensitive_360") !== false && strpos($ajax, "'dados_sensiveis' => false") !== false,
    'ajax_sensitive_scope_helper' => strpos($ajax, 'sige_alunos_user_can_view_sensitive_student_details') !== false,
    'ajax_sensitive_payload_minimized' => strpos($ajax, "foreach (['genero','data_nascimento','idade'") !== false,
    'ajax_guard_payload_portaria_only' => strpos($ajax, 'Modo portaria') !== false && strpos($ajax, "'guard_readonly' => true") !== false,
    'ajax_server_filters_payload' => strpos($ajax, '$filtro_turma') !== false && strpos($ajax, '$filtro_search') !== false && strpos($ajax, 'WHERE {$where_sql}') !== false,
    'ajax_route_helper_modern_legacy' => strpos($ajax, 'sige_alunos_ajax_resolve_rotas_table') !== false && strpos($ajax, 'sige_transporte_rotas') !== false && strpos($ajax, 'sige_rotas') !== false,

    'secure_document_not_alunos_ver' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais','alunos.ver']") === false,
    'secure_document_uses_document_permissions' => strpos($secure, "['documentos.ver','documentos.emitir','documentos.emitir_finais']") !== false,
    'secure_document_allowed_fields_preserved' => strpos($secure, "'doc_bi_url'") !== false && strpos($secure, "'doc_cert_url'") !== false && strpos($secure, "'doc_vacina_url'") !== false,

    'guard_role_preserved' => strpos($roles, 'sige_guarda') !== false,
    'guard_permissions_preserved' => strpos($perms, 'portaria.validar_acesso') !== false && strpos($perms, "'guarda'") !== false,
    'global_mobile_asset_preserved' => strpos($asset, 'sige-ux-mobile') !== false && strpos($asset, 'sige-ux-tablet') !== false,
];

$failed = [];
foreach ($checks as $name => $ok) {
    if (!$ok) $failed[] = $name;
}
if ($failed) {
    fwrite(STDERR, 'Smoke Alunos Deep Audit v12.11.9.69 falhou: ' . implode(', ', $failed) . PHP_EOL);
    exit(1);
}
echo 'Smoke Alunos Deep Audit v12.11.9.69 OK: ' . count($checks) . '/' . count($checks) . ' checks.' . PHP_EOL;
