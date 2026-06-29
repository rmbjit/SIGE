<?php
/**
 * SIGE SoftGenial - View: Contas de Alunos
 * Ficheiro: admin/academic/aluno_contas-view.php
 * Rota: ?page=sige-app&view=aluno_contas
 *
 * v2.1 - Abril 2026
 * - ABSPATH guard movido para o topo
 *
 * Wrapper: delega rendering a sige_render_aluno_contas_view()
 * definida em includes/aluno-accounts.php
 */

if (!defined('ABSPATH')) exit;

// Guard de acesso - Secretaria Académica
// [12.9.6] Matriz SIGE manda; WP caps fallback enquanto não houver perfil SIGE.
if (!sige_page_guard(
    ['alunos.contas_ver','alunos.contas_gerir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;

if (function_exists('sige_render_aluno_contas_view')) {
    sige_render_aluno_contas_view();
} else {
    echo '<div style="padding:30px;color:#c62828;background:#ffebee;border-radius:12px;font-family:Inter,sans-serif;">O ficheiro includes/aluno-accounts.php não está carregado.</div>';
}