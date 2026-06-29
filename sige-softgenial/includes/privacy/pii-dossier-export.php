<?php
/**
 * Exportacao do dossie de dados pessoais do aluno (portabilidade) - Fase 8 incr 2.
 *
 * Endpoint admin_post governado pelo Security Kernel em modo enforce. Valida o
 * aluno na escola activa, monta o dossie (so leitura) e transmite um ficheiro
 * JSON estruturado. Protegido por nonce e isolamento por escola; cada exportacao
 * fica registada na auditoria de permissoes. O Kernel aplica ainda permissao,
 * nonce e rate limit antes deste handler correr (defesa em profundidade).
 *
 * Registado no hook admin_post_sige_privacidade_exportar.
 */

if (!defined('ABSPATH')) exit;

add_action('admin_post_sige_privacidade_exportar', 'sige_privacidade_handle_exportar');

if (!function_exists('sige_privacidade_handle_exportar')) {
    function sige_privacidade_handle_exportar(): void {
        if (!function_exists('sige_pii_dossier_pode_exportar') || !sige_pii_dossier_pode_exportar()) {
            wp_die('Acesso negado. Apenas a Direccao e a administracao podem exportar dados pessoais.', 'Acesso restrito', ['response' => 403]);
        }
        check_admin_referer('sige_privacidade_exportar');

        $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $aluno_id = isset($_POST['aluno_id']) ? (int) $_POST['aluno_id'] : 0;

        if ($escola_id <= 0) {
            wp_die('Sem escola activa no contexto. Nao e possivel exportar.', 'Sem escola', ['response' => 400]);
        }
        if ($aluno_id <= 0) {
            wp_die('Aluno nao especificado.', 'Pedido invalido', ['response' => 400]);
        }

        $dossie = sige_pii_dossier($aluno_id, $escola_id);
        if (empty($dossie['ok'])) {
            $motivo = isset($dossie['motivo']) ? (string) $dossie['motivo'] : 'nao disponivel';
            wp_die('Nao foi possivel montar o dossie: ' . esc_html($motivo) . '.', 'Indisponivel', ['response' => 403]);
        }

        // Registo de acesso: quem exportou que aluno e quando.
        if (function_exists('sige_permission_audit')) {
            $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
            sige_permission_audit($uid, 'privacidade.acesso_exportar', true, 'exportar_dossie', ['aluno_id' => $aluno_id, 'escola_id' => $escola_id]);
        }

        $json = sige_pii_dossier_json($dossie);
        $proc = $dossie['identificacao']['numero_processo'] ?? (string) $aluno_id;
        $proc_safe = preg_replace('/[^A-Za-z0-9_\-]/', '', (string) $proc);
        if ($proc_safe === '') { $proc_safe = (string) $aluno_id; }
        $filename = 'dossie-dados-pessoais-' . $proc_safe . '-' . gmdate('Ymd') . '.json';

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));
        header('X-Content-Type-Options: nosniff');
        echo $json;
        exit;
    }
}
