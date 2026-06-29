<?php
/**
 * SIGE SoftGenial - Re-aprovação Automática de Notas (v12.9.67)
 * ==========================================================
 *
 * MOTIVAÇÃO (caso real):
 *   Versões anteriores tinham um bug onde o lançamento de um campo NOVO numa
 *   linha que já tinha campos APROVADOS fazia toda a linha voltar a 'pendente'.
 *   Ex: ACS1 aprovado, professor lança ACS2 → ACS1 e ACS2 ficaram pendentes.
 *   Director tinha de re-aprovar tudo.
 *
 * CORRECÇÃO PRINCIPAL:
 *   admin/academic/notas-view.php agora compara campo-a-campo antes do UPDATE.
 *   Adicionar campo novo NÃO invalida a aprovação anterior.
 *
 * MIGRAÇÃO DE DADOS (este ficheiro):
 *   Notas que JÁ FORAM APROVADAS uma vez (têm aprovado_por e aprovado_em
 *   preenchidos) e que actualmente estão como 'pendente' por causa do bug
 *   → re-aprovar automaticamente, mantendo o aprovado_por original como
 *   marca histórica e adicionando 'reaprovado_por_migracao' nos campos
 *   submetido_por para auditoria.
 *
 * SEGURANÇA:
 *   Só toca em linhas com aprovado_por != NULL (foram aprovadas alguma vez).
 *   Linhas que nunca foram aprovadas (aprovado_por IS NULL) NÃO são alteradas.
 *   Idempotente: corre 1× por instalação. Marca conclusão em wp_options.
 *
 * FICHEIROS CANÓNICOS (NÃO TOCADOS):
 *   - finance-core.php
 *   - academic-logic.php
 *   - whatsapp-engine.php, whatsapp-recovery-mode.php, whatsapp-guardian.php
 *   - cron-tasks.php, class-sige-migration.php, db-handler.php
 *   - whatsapp-human-advanced.php
 *
 * @package SIGE\Academic
 * @since   12.9.67
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_notas_migracao_reaprovacao')) {
    /**
     * Re-aprova automaticamente notas que foram aprovadas e voltaram a pendente
     * por causa do bug em versões anteriores.
     *
     * Idempotente: marca conclusão em wp_options com a data e estatísticas.
     * Pode ser corrida múltiplas vezes - só re-aprova as que ainda estão
     * pendentes E têm histórico de aprovação anterior.
     *
     * @return array stats: ['scanned' => int, 'reaprovadas' => int, 'pulou' => int, 'ja_executada' => bool]
     */
    function sige_notas_migracao_reaprovacao(bool $forcar = false): array {
        global $wpdb;

        $stats = [
            'scanned' => 0, 'reaprovadas' => 0, 'pulou' => 0,
            'ja_executada' => false, 'mensagem' => '',
        ];

        $tNotas = $wpdb->prefix . 'sige_notas';
        if ($wpdb->get_var("SHOW TABLES LIKE '{$tNotas}'") !== $tNotas) {
            $stats['mensagem'] = 'Tabela sige_notas não existe - nada a fazer.';
            return $stats;
        }

        // Verificar colunas necessárias
        $cols_existem = $wpdb->get_results(
            "SHOW COLUMNS FROM {$tNotas} WHERE Field IN ('status', 'aprovado_por', 'aprovado_em')"
        );
        if (count($cols_existem) < 3) {
            $stats['mensagem'] = 'Colunas de aprovação ainda não existem nesta instalação. Migração pulada.';
            return $stats;
        }

        // Idempotência: só corre 1× por escola, salvo $forcar=true
        $opt_key = 'sige_notas_reaprovacao_v12967_executada';
        $ja = get_option($opt_key, '');
        if ($ja && !$forcar) {
            $stats['ja_executada'] = true;
            $stats['mensagem'] = 'Migração já executada em ' . $ja . '. Use $forcar=true para re-correr.';
            return $stats;
        }

        // Selecciona notas afectadas pelo bug:
        //   - status = 'pendente'
        //   - aprovado_por NOT NULL (foram aprovadas alguma vez)
        //   - aprovado_em NOT NULL
        $candidatas = $wpdb->get_results(
            "SELECT id, escola_id, aluno_id, disciplina_id, trimestre, ano_lectivo
               FROM {$tNotas}
              WHERE status = 'pendente'
                AND aprovado_por IS NOT NULL
                AND aprovado_em IS NOT NULL"
        );

        $stats['scanned'] = count($candidatas);

        if ($stats['scanned'] === 0) {
            update_option($opt_key, current_time('mysql'));
            $stats['mensagem'] = 'Nada a re-aprovar (nenhuma nota com aprovado_por preenchido em estado pendente).';
            return $stats;
        }

        // Re-aprovação em lote (1 query) com WHERE estrito para segurança
        $r = $wpdb->query(
            "UPDATE {$tNotas}
                SET status = 'aprovado'
              WHERE status = 'pendente'
                AND aprovado_por IS NOT NULL
                AND aprovado_em IS NOT NULL"
        );

        $stats['reaprovadas'] = ($r === false) ? 0 : (int) $r;
        $stats['pulou']       = $stats['scanned'] - $stats['reaprovadas'];

        if ($r !== false) {
            update_option($opt_key, current_time('mysql'));
            $stats['mensagem'] = sprintf(
                'Re-aprovadas %d notas que tinham sido aprovadas anteriormente e voltaram a pendente por causa do bug v12.9.67.',
                $stats['reaprovadas']
            );

            // Audit log
            if (function_exists('sige_audit_log')) {
                sige_audit_log('migracao_reaprovacao_notas', $stats, 'notas');
            }
        } else {
            $stats['mensagem'] = 'Erro SQL: ' . $wpdb->last_error;
        }

        return $stats;
    }
}

// ============================================================================
// Auto-execução: corre 1× quando admin entra na área. Não corre durante
// requests AJAX nem CLI para evitar interferências.
// ============================================================================
add_action('admin_init', function () {
    static $checked = false;
    if ($checked) return;
    $checked = true;

    // Só corre se utilizador é supervisor (não corre para professor para evitar barulho)
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
        && !current_user_can('sige_pedagogico')
        && !current_user_can('sige_director')) {
        return;
    }

    // Evita correr em AJAX/cron/CLI
    if (wp_doing_ajax() || wp_doing_cron() || (defined('WP_CLI') && WP_CLI)) return;

    sige_notas_migracao_reaprovacao(false);
});

// ============================================================================
// Endpoint AJAX para re-correr manualmente (admin pode forçar via botão UI
// se for adicionado no futuro à página de "Aprovar Notas")
// ============================================================================
add_action('wp_ajax_sige_notas_reaprovacao_run', function () {
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
        && !current_user_can('sige_pedagogico')
        && !current_user_can('sige_director')) {
        wp_send_json_error(['message' => 'Sem permissão.'], 403);
    }

    $nonce = isset($_POST['_wpnonce']) ? sanitize_text_field((string) $_POST['_wpnonce']) : '';
    if (!$nonce || !wp_verify_nonce($nonce, 'sige_notas_reaprovacao')) {
        wp_send_json_error(['message' => 'Nonce inválido.'], 403);
    }

    $forcar = !empty($_POST['forcar']);
    $stats = sige_notas_migracao_reaprovacao($forcar);
    wp_send_json_success($stats);
});
