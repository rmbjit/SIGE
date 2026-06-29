<?php
/**
 * SIGE SoftGenial - Hooks de Auditoria Completos
 * Regista todas as acções do utilizador na tabela sige_logs_auditoria
 *
 * Carregado em: sige-softgenial.php via require_once
 * Depende de: sige_audit_log() definida em finance-core.php
 *
 * Módulos cobertos:
 *   acesso      - login, logout, tentativas falhadas
 *   alunos      - criar, editar, remover, matricular, desmatricular
 *   notas       - lançar/editar notas em bulk, aprovar pauta
 *   turmas      - criar, remover, alocar docente, guardar horário
 *   professores - criar, editar, remover staff/utilizadores
 *   matriz      - vincular/remover disciplina, clonar
 *   config      - guardar configuração global e financeira
 *   financeiro  - já coberto em finance-core.php (não duplicar)
 *   jardim      - diário, saúde, avaliações, critérios
 *   sistema     - exportações, limpeza de dados
 */

if (!defined('ABSPATH')) exit;

// ──────────────────────────────────────────────────────────────────────────────
// HELPER INTERNO: wrapper seguro que evita chamadas antes do finance-core
// ──────────────────────────────────────────────────────────────────────────────
function sige_log(string $acao, string $modulo, $detalhes = null): void {
    if (function_exists('sige_audit_log')) {
        sige_audit_log($acao, $detalhes, $modulo);
    }
}

// Helper: IP real (considera proxies)
function sige_log_ip(): string {
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','REMOTE_ADDR'] as $h) {
        if (!empty($_SERVER[$h])) return sanitize_text_field(explode(',', $_SERVER[$h])[0]);
    }
    return '';
}

// ══════════════════════════════════════════════════════════════════════════════
// 1. ACESSO - Login, Logout, Falhas
// ══════════════════════════════════════════════════════════════════════════════

// Login com sucesso
add_action('wp_login', function (string $user_login, WP_User $user) {
    sige_audit_log('login_sucesso', [
        'username' => $user_login,
        'email'    => $user->user_email,
        'ip'       => sige_log_ip(),
    ], 'acesso', $user->ID);
}, 10, 2);

// Login falhado
add_action('wp_login_failed', function (string $username) {
    // Gravar com user_id=0 (não autenticado)
    global $wpdb;
    $t   = $wpdb->prefix . 'sige_logs_auditoria';
    $ip  = sige_log_ip();
    $det = wp_json_encode(['username' => $username, 'ip' => $ip], JSON_UNESCAPED_UNICODE);
    $wpdb->insert($t, [
        'user_id'      => 0,
        'user_display' => $username . ' (falhou)',
        'modulo'       => 'acesso',
        'acao'         => 'login_falhado',
        'detalhes'     => $det,
        'ip_address'   => $ip,
        'data_hora'    => current_time('mysql'),
    ]);
}, 10, 1);

// Logout
add_action('wp_logout', function (int $user_id) {
    $user = get_userdata($user_id);
    sige_audit_log('logout', [
        'username' => $user ? $user->user_login : '?',
        'ip'       => sige_log_ip(),
    ], 'acesso', $user_id);
}, 10, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 2. ALUNOS
// ══════════════════════════════════════════════════════════════════════════════

// Salvar aluno (criar ou editar) - via wp_ajax_sige_salvar_aluno
add_action('wp_ajax_sige_salvar_aluno', function () {
    $id   = (int)($_POST['id'] ?? 0);
    $nome = sanitize_text_field($_POST['nome_completo'] ?? $_POST['nome'] ?? '');
    sige_log($id > 0 ? 'aluno_editado' : 'aluno_criado', 'alunos', [
        'aluno_id' => $id,
        'nome'     => $nome,
    ]);
}, 1); // priority 1 - corre antes do handler principal

// Remover aluno - já tem audit em sige-softgenial.php mas garante cobertura
add_action('wp_ajax_sige_remover_aluno', function () {
    $id = (int)($_POST['aluno_id'] ?? $_POST['id'] ?? 0);
    if ($id) sige_log('aluno_removido', 'alunos', ['aluno_id' => $id]);
}, 1);

// Matricular aluno
add_action('wp_ajax_sige_processar_matricula', function () {
    $aluno_id = (int)($_POST['aluno_id'] ?? 0);
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    sige_log('aluno_matriculado', 'alunos', [
        'aluno_id' => $aluno_id,
        'turma_id' => $turma_id,
    ]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 3. NOTAS ACADÉMICAS
// ══════════════════════════════════════════════════════════════════════════════

add_action('wp_ajax_sige_salvar_notas_bulk', function () {
    $disc_id  = (int)($_POST['disciplina_id'] ?? 0);
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $trim     = (int)($_POST['trimestre'] ?? 0);
    $n_alunos = is_array($_POST['notas'] ?? null) ? count($_POST['notas']) : 0;
    sige_log('nota_lancada', 'notas', [
        'disciplina_id' => $disc_id,
        'turma_id'      => $turma_id,
        'trimestre'     => $trim,
        'n_alunos'      => $n_alunos,
    ]);
}, 1);

// Aprovação de pauta - admin_post
add_action('admin_post_sige_aprovar_pauta', function () {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $trim     = (int)($_POST['trimestre'] ?? 0);
    sige_log('nota_aprovada', 'notas', [
        'turma_id'  => $turma_id,
        'trimestre' => $trim,
    ]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 4. TURMAS
// ══════════════════════════════════════════════════════════════════════════════

// Criar / editar turma
add_action('wp_ajax_sige_salvar_turma', function () {
    $id   = (int)($_POST['id'] ?? 0);
    $nome = sanitize_text_field($_POST['nome'] ?? '');
    $cl   = sanitize_text_field($_POST['classe'] ?? '');
    sige_log($id > 0 ? 'turma_editada' : 'turma_criada', 'turmas', [
        'turma_id' => $id,
        'nome'     => $nome,
        'classe'   => $cl,
    ]);
}, 1);

// Remover turma
add_action('wp_ajax_sige_remover_turma', function () {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) sige_log('turma_removida', 'turmas', ['turma_id' => $id]);
}, 1);

// Alocar docente a disciplina na turma
add_action('wp_ajax_sige_salvar_docente_disciplina', function () {
    $vinculo_id  = (int)($_POST['id_vinculo'] ?? 0);
    $prof_id     = (int)($_POST['professor_id'] ?? 0);
    sige_log('docente_alocado', 'turmas', [
        'vinculo_id'   => $vinculo_id,
        'professor_id' => $prof_id,
    ]);
}, 1);

// Guardar horário da turma
add_action('wp_ajax_sige_salvar_horario_turma', function () {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    sige_log('horario_guardado', 'turmas', ['turma_id' => $turma_id]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 5. PROFESSORES / STAFF / UTILIZADORES
// ══════════════════════════════════════════════════════════════════════════════
// v12.11.9 - Os logs do módulo Equipa passam a ser emitidos pelos handlers
// seguros em includes/ajax-handlers.php apenas após nonce, permissão, validação
// de escola e conclusão efectiva da operação. Isto evita falsos positivos no
// trilho de auditoria e corrige parâmetros divergentes (id vs user_id).

// ══════════════════════════════════════════════════════════════════════════════
// 6. MATRIZ CURRICULAR
// ══════════════════════════════════════════════════════════════════════════════

add_action('wp_ajax_sige_vincular_matriz', function () {
    $classe  = sanitize_text_field($_POST['classe'] ?? '');
    $disc_id = (int)($_POST['disciplina_id'] ?? 0);
    sige_log('matriz_vinculada', 'sistema', [
        'classe'        => $classe,
        'disciplina_id' => $disc_id,
    ]);
}, 1);

add_action('wp_ajax_sige_remover_matriz', function () {
    $id = (int)($_POST['id'] ?? 0);
    sige_log('matriz_removida', 'sistema', ['id' => $id]);
}, 1);

add_action('wp_ajax_sige_clonar_matriz', function () {
    $classe_orig  = sanitize_text_field($_POST['classe_origem'] ?? '');
    $classe_dest  = sanitize_text_field($_POST['classe_destino'] ?? '');
    sige_log('matriz_clonada', 'sistema', [
        'origem'  => $classe_orig,
        'destino' => $classe_dest,
    ]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 7. CONFIGURAÇÃO DO SISTEMA
// ══════════════════════════════════════════════════════════════════════════════

// Config geral (admin-post)
// v12.9.105 - Route morta: nenhum form/handler do plugin envia action=sige_salvar_config.
// O caminho real é o action AJAX sige_salvar_config_avancado (ver linha abaixo) e o novo
// controller (wp_ajax_sige_settings_save_school_profile). Comentado para reduzir ruído
// no admin_post sem apagar o bloco - se algum cliente reportar uso real, reverter.
/*
add_action('admin_post_sige_salvar_config', function () {
    sige_log('config_alterada', 'sistema', [
        'campos' => array_keys(array_filter($_POST, fn($v) => $v !== '', ARRAY_FILTER_USE_BOTH)),
    ]);
}, 1);
*/

// Config avançada (AJAX) - handler real está em includes/db-handler.php.
add_action('wp_ajax_sige_salvar_config_avancado', function () {
    sige_log('config_avancada_alterada', 'sistema', [
        'campos_n' => count($_POST),
    ]);
}, 1);

// Config financeira (admin-post)
// v12.9.105 - Route morta: nenhum form/handler do plugin envia action=sige_salvar_config_financeira.
// A configuração financeira real é gerida pelo módulo financeiro (admin/finance/). Comentado para
// reduzir ruído sem apagar o bloco.
/*
add_action('admin_post_sige_salvar_config_financeira', function () {
    sige_log('config_financeira_alterada', 'financeiro', [
        'desconto_irmaos_tipo'  => sanitize_text_field($_POST['desconto_irmaos_tipo'] ?? 'mt'),
        'desconto_irmaos_mt'    => (float)($_POST['desconto_irmaos_mt'] ?? 0),
        'desconto_irmaos_pct'   => (float)($_POST['desconto_irmaos_pct'] ?? 0),
        'desconto_funcionario_tipo' => sanitize_text_field($_POST['desconto_funcionario_tipo'] ?? 'mt'),
        'desconto_funcionario_mt'   => (float)($_POST['desconto_funcionario_mt'] ?? 0),
        'desconto_funcionario_pct'  => (float)($_POST['desconto_funcionario_percentual'] ?? 0),
    ]);
}, 1);
*/

// WhatsApp
add_action('wp_ajax_sige_salvar_whatsapp_isolado', function () {
    sige_log('whatsapp_config_alterada', 'sistema', ['url_definida' => !empty($_POST['api_url'])]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 8. JARDIM DE INFÂNCIA
// ══════════════════════════════════════════════════════════════════════════════

add_action('admin_post_sige_jardim_diario_salvar', function () {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $data     = sanitize_text_field($_POST['data_registo'] ?? '');
    $n_alunos = is_array($_POST['diario'] ?? null) ? count($_POST['diario']) : 0;
    sige_log('jardim_diario_registado', 'jardim', [
        'turma_id' => $turma_id,
        'data'     => $data,
        'n_alunos' => $n_alunos,
    ]);
}, 1);

add_action('admin_post_sige_jardim_saude_salvar', function () {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $data     = sanitize_text_field($_POST['data_registo'] ?? '');
    $n_alunos = is_array($_POST['saude'] ?? null) ? count($_POST['saude']) : 0;
    sige_log('jardim_saude_registado', 'jardim', [
        'turma_id' => $turma_id,
        'data'     => $data,
        'n_alunos' => $n_alunos,
    ]);
}, 1);

add_action('admin_post_sige_jardim_criterios_salvar', function () {
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $trim     = (int)($_POST['trimestre'] ?? 0);
    $n_alunos = is_array($_POST['coment'] ?? null) ? count($_POST['coment']) : 0;
    sige_log('jardim_avaliacao_guardada', 'jardim', [
        'turma_id'  => $turma_id,
        'trimestre' => $trim,
        'n_alunos'  => $n_alunos,
    ]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 9. ENCERRAMENTO E ABERTURA DE ANO LECTIVO
// ══════════════════════════════════════════════════════════════════════════════

add_action('admin_post_sige_encerrar_ano', function () {
    $ano = (int)($_POST['ano_lectivo'] ?? 0);
    sige_log('ano_encerrado', 'sistema', ['ano_lectivo' => $ano]);
}, 1);

add_action('admin_post_sige_abrir_ano', function () {
    $ano = (int)($_POST['ano_lectivo'] ?? 0);
    sige_log('ano_aberto', 'sistema', ['ano_lectivo' => $ano]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 10. PORTARIA - Registo de acesso físico
// ══════════════════════════════════════════════════════════════════════════════

add_action('wp_ajax_sige_registar_acesso', function () {
    $aluno_id = (int)($_POST['aluno_id'] ?? 0);
    $tipo     = sanitize_text_field($_POST['tipo'] ?? 'entrada'); // entrada | saida
    sige_log('portaria_acesso', 'acesso', [
        'aluno_id' => $aluno_id,
        'tipo'     => $tipo,
    ]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 11. LIMPEZA DE DADOS (acção sensível)
// ══════════════════════════════════════════════════════════════════════════════

add_action('admin_post_sige_limpar_dados_teste', function () {
    $tabelas = sanitize_text_field($_POST['tabelas'] ?? 'todas');
    sige_log('dados_limpos', 'sistema', [
        'tabelas'   => $tabelas,
        'ATENCAO'   => 'Limpeza de dados executada',
    ]);
}, 1);

// ══════════════════════════════════════════════════════════════════════════════
// 12. FASE 1 - logs explícitos para operações críticas do plano de blindagem
// ══════════════════════════════════════════════════════════════════════════════
add_action('admin_post_sige_acta_aprovar_nota_votada', function () {
    sige_log('nota_votada_aprovada_ou_alterada', 'notas', [
        'aluno_id' => (int)($_POST['aluno_id'] ?? 0),
        'turma_id' => (int)($_POST['turma_id'] ?? 0),
        'disciplina_id' => (int)($_POST['disciplina_id'] ?? 0),
        'contexto' => 'acta_conselho_notas',
    ]);
}, 1);

add_action('admin_post_sige_acta_guardar', function () {
    sige_log('acta_guardada', 'notas', [
        'turma_id' => (int)($_POST['turma_id'] ?? 0),
        'ano_lectivo' => (int)($_POST['ano_lectivo'] ?? 0),
    ]);
}, 1);

add_action('admin_post_sige_secure_document_download', function () {
    sige_log('documento_download_solicitado', 'documentos', [
        'aluno_id' => (int)($_GET['aluno_id'] ?? 0),
        'field' => sanitize_key((string)($_GET['field'] ?? '')),
    ]);
}, 1);
