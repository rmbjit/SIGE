<?php
/**
 * Execucao do apagamento por anonimizacao (direito ao apagamento) - Fase 8 incr 3.
 *
 * Endpoint admin_post governado pelo Security Kernel em modo enforce, risco
 * critico. Operacao destrutiva e irreversivel. Salvaguardas:
 *  - permissao privacidade.apagamento_executar (so administracao e direccao);
 *  - confirmacao em dois passos: o operador escreve o numero de processo exacto,
 *    e o servidor so executa se coincidir com o do aluno;
 *  - revalidacao do aluno na escola activa (fail-closed);
 *  - auditoria antes e depois (quem, que aluno, que campos, quando);
 *  - nonce e rate limit aplicados tambem pelo Kernel.
 *
 * Registado no hook admin_post_sige_privacidade_apagar.
 */

if (!defined('ABSPATH')) exit;

add_action('admin_post_sige_privacidade_apagar', 'sige_privacidade_handle_apagar');

if (!function_exists('sige_privacidade_handle_apagar')) {
    function sige_privacidade_handle_apagar(): void {
        if (!function_exists('sige_pii_apagamento_pode_executar') || !sige_pii_apagamento_pode_executar()) {
            wp_die('Acesso negado. Apenas a Direccao e a administracao podem apagar dados pessoais.', 'Acesso restrito', ['response' => 403]);
        }
        check_admin_referer('sige_privacidade_apagar');

        $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $aluno_id = isset($_POST['aluno_id']) ? (int) $_POST['aluno_id'] : 0;
        $confirmacao = isset($_POST['confirmar_processo']) ? sanitize_text_field(wp_unslash($_POST['confirmar_processo'])) : '';

        if ($escola_id <= 0) {
            wp_die('Sem escola activa no contexto. Nao e possivel apagar.', 'Sem escola', ['response' => 400]);
        }
        if ($aluno_id <= 0) {
            wp_die('Aluno nao especificado.', 'Pedido invalido', ['response' => 400]);
        }
        if (!sige_pii_dossier_aluno_pertence($aluno_id, $escola_id)) {
            wp_die('Aluno nao encontrado nesta escola.', 'Indisponivel', ['response' => 403]);
        }

        // Confirmacao em dois passos: o numero de processo escrito tem de coincidir.
        $ident = sige_pii_dossier_identificacao($aluno_id, $escola_id);
        $processo_real = trim((string) ($ident['numero_processo'] ?? ''));
        if ($processo_real === '' || strcasecmp(trim($confirmacao), $processo_real) !== 0) {
            $url = add_query_arg([
                'page'     => 'sige-app',
                'view'     => 'privacidade-apagamento',
                'aluno_id' => $aluno_id,
                'erro'     => 'confirmacao',
            ], admin_url('admin.php'));
            wp_safe_redirect($url);
            exit;
        }

        // Auditoria ANTES: registar a intencao e o alvo.
        $uid = function_exists('get_current_user_id') ? (int) get_current_user_id() : 0;
        $plano = sige_pii_apagamento_plano($aluno_id, $escola_id);
        if (function_exists('sige_permission_audit')) {
            sige_permission_audit($uid, 'privacidade.apagamento_executar', true, 'apagar_inicio', [
                'aluno_id'      => $aluno_id,
                'escola_id'     => $escola_id,
                'total_colunas' => (int) ($plano['total_colunas'] ?? 0),
            ]);
        }

        $res = sige_pii_apagamento_executar($aluno_id, $escola_id);

        // Auditoria DEPOIS: registar o resultado.
        if (function_exists('sige_permission_audit')) {
            sige_permission_audit($uid, 'privacidade.apagamento_executar', !empty($res['ok']), 'apagar_fim', [
                'aluno_id'      => $aluno_id,
                'escola_id'     => $escola_id,
                'total_tabelas' => (int) ($res['total_tabelas'] ?? 0),
                'total_colunas' => (int) ($res['total_colunas'] ?? 0),
                'total_linhas'  => (int) ($res['total_linhas'] ?? 0),
            ]);
        }

        $url = add_query_arg([
            'page'     => 'sige-app',
            'view'     => 'privacidade-apagamento',
            'aluno_id' => $aluno_id,
            'feito'    => !empty($res['ok']) ? '1' : '0',
            'colunas'  => (int) ($res['total_colunas'] ?? 0),
        ], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }
}
