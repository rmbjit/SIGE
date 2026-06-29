<?php
/**
 * SIGE SoftGenial v12.15.13 - Portal enxuto (peso de entrega por papel).
 *
 * Decide quando a Página do Aluno deve ser servida com o bundle mínimo, em vez
 * de carregar a shell administrativa completa. O encarregado/aluno entra no
 * portal poucas vezes por trimestre, tipicamente no telemovel e em rede fraca,
 * apenas para LER (saldo, propinas, boletim) e mudar a palavra-passe. Nao tem
 * uploader nem chamadas AJAX, por isso a maquinaria de media do WordPress e os
 * CSS de views financeiras de staff sao puro peso morto para este papel.
 *
 * REGRAS DE OURO desta camada:
 *   - NAO altera PHP financeiro, formulas, saldos, Finance Score nem queries.
 *     So decide O QUE se enfileira, nunca O QUE se calcula.
 *   - So actua na view do portal (`aluno_portal`).
 *   - So actua para utilizadores cujo acesso e EXCLUSIVAMENTE o portal. Qualquer
 *     membro do staff que inspeccione o portal mantem a shell completa, porque
 *     pode navegar dali para modulos pesados.
 *   - Reversivel de imediato: option `sige_portal_lean_assets_v121513_enabled = 0`.
 *
 * Ficheiro auto-contido de proposito: depende apenas de funcoes do WordPress
 * (todas com guarda function_exists nos pontos de uso), para poder ser exercitado
 * isoladamente pelo gate tools/smoke-portal-lean-assets-v12-15-13.php.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_portal_lean_flag_enabled')) {
    /** A camada esta ligada? (option = '0' desliga e repoe a shell completa). */
    function sige_portal_lean_flag_enabled(): bool {
        if (!function_exists('get_option')) return true;
        return get_option('sige_portal_lean_assets_v121513_enabled', '1') !== '0';
    }
}

if (!function_exists('sige_portal_lean_current_view')) {
    /** View pedida, normalizada. Vazia se ausente. */
    function sige_portal_lean_current_view(): string {
        if (!isset($_GET['view'])) return '';
        $view = (string) $_GET['view'];
        return function_exists('sanitize_key') ? sanitize_key($view) : strtolower(preg_replace('/[^a-z0-9_\-]/', '', $view));
    }
}

if (!function_exists('sige_portal_lean_user_is_staff')) {
    /**
     * O utilizador actual tem algum papel de staff? Se sim, NUNCA se aplica o
     * enxugamento, mesmo que esteja a ver o portal: ele pode navegar para
     * modulos que precisam da shell completa.
     */
    function sige_portal_lean_user_is_staff(): bool {
        if (!function_exists('current_user_can')) return true; // na duvida, shell completa
        $staff_caps = [
            'manage_options',
            'sige_admin_ti',
            'sige_director',
            'sige_secretario',
            'sige_secretaria_geral',
            'sige_assistente',
            'sige_pedagogico',
            'sige_financeiro',
            'sige_professor',
            'sige_gestor_rh',
            'sige_educador',
            'sige_guarda',
        ];
        foreach ($staff_caps as $cap) {
            if (current_user_can($cap)) return true;
        }
        return false;
    }
}

if (!function_exists('sige_portal_lean_user_is_portal_only')) {
    /** O utilizador e exclusivamente do portal (encarregado ou aluno)? */
    function sige_portal_lean_user_is_portal_only(): bool {
        if (!function_exists('current_user_can')) return false;
        return current_user_can('sige_encarregado') || current_user_can('sige_aluno');
    }
}

if (!function_exists('sige_portal_lean_is_active')) {
    /**
     * Decisao unica: deve esta resposta usar o bundle minimo do portal?
     * Verdadeiro apenas quando: flag ligada E view = aluno_portal E utilizador
     * exclusivamente do portal (nao staff). Em qualquer outro caso, falso, e a
     * shell completa carrega como sempre (degradacao segura).
     */
    function sige_portal_lean_is_active(): bool {
        if (!sige_portal_lean_flag_enabled()) return false;
        if (sige_portal_lean_current_view() !== 'aluno_portal') return false;
        if (sige_portal_lean_user_is_staff()) return false;
        return sige_portal_lean_user_is_portal_only();
    }
}
