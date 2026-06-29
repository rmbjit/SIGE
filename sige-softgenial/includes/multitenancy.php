<?php
/**
 * SIGE SoftGenial - Módulo Multi-Tenancy
 * 
 * @package SIGE
 * @version 1.0.0
 * @since 2026-03-30
 * 
 * Este módulo permite que múltiplas escolas usem a mesma instalação SIGE.
 * Cada escola tem os seus dados isolados através da coluna escola_id.
 * 
 * RETROCOMPATIBILIDADE: Se não houver escola definida, usa Malisa (ID 1) apenas em modo legado/mono-escola.
 * Fase 1: em modo multi-escola estrito, sem contexto de escola válido o sistema fecha acesso (escola_id=0).
 */

if (!defined('ABSPATH')) exit;

// ============================================================
// CONSTANTES
// ============================================================

define('SIGE_ESCOLA_MALISA', 1); // OBSOLETO (v12.12.9): ja nao e usado como fallback. O resolvedor usa sige_multitenancy_single_active_school_id(). Mantido definido apenas por retrocompatibilidade.
define('SIGE_MULTITENANCY_VERSION', '1.0.0');


if (!function_exists('sige_multitenancy_active_school_count')) {
    function sige_multitenancy_active_school_count(): int {
        static $count = null;
        if ($count !== null) return $count;
        global $wpdb;
        $tabela = $wpdb->prefix . 'sige_escolas';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tabela)));
        if ($exists !== $tabela) {
            $count = 0;
            return $count;
        }
        $count = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$tabela} WHERE activo = 1");
        return $count;
    }
}

if (!function_exists('sige_multitenancy_single_active_school_id')) {
    /**
     * Resolucao determinista da escola unica activa. Substitui o fallback cego
     * SIGE_ESCOLA_MALISA: em vez de assumir id=1, devolve o id REAL da unica
     * escola activa quando (e so quando) existe exactamente uma. Se houver zero
     * ou varias escolas activas, devolve 0 (contexto ambiguo, fail-closed).
     *
     * @return int id da escola unica activa, ou 0 se ambiguo/indeterminado.
     */
    function sige_multitenancy_single_active_school_id(): int {
        static $id = null;
        if ($id !== null) return $id;
        if (sige_multitenancy_active_school_count() !== 1) {
            $id = 0;
            return $id;
        }
        global $wpdb;
        $tabela = $wpdb->prefix . 'sige_escolas';
        $id = (int) $wpdb->get_var("SELECT id FROM {$tabela} WHERE activo = 1 LIMIT 1");
        return $id;
    }
}

if (!function_exists('sige_multitenancy_strict_enabled')) {
    /**
     * Fase 1/P0: fail-closed quando a instalação é realmente multi-escola.
     * Pode ser forçado com define('SIGE_MULTITENANT_STRICT', true).
     * Pode ser temporariamente relaxado com define('SIGE_ALLOW_ESCOLA_FALLBACK', true).
     */
    function sige_multitenancy_strict_enabled(): bool {
        if (defined('SIGE_ALLOW_ESCOLA_FALLBACK') && SIGE_ALLOW_ESCOLA_FALLBACK) return false;
        if (defined('SIGE_MULTITENANT_STRICT')) return (bool)SIGE_MULTITENANT_STRICT;
        $multi_school = sige_multitenancy_active_school_count() > 1;
        if (!$multi_school) return false;
        if (defined('SIGE_ENV') && in_array(strtolower((string)SIGE_ENV), ['prod','production','producao','produção'], true)) return true;
        if (function_exists('wp_get_environment_type') && wp_get_environment_type() === 'production') return true;
        return true; // várias escolas activas: não usar fallback silencioso.
    }
}


// ============================================================
// FUNÇÕES PRINCIPAIS
// ============================================================

/**
 * Obtém o ID da escola actual
 * 
 * Ordem de prioridade:
 * 1. Constante SIGE_CURRENT_ESCOLA (definida no wp-config.php)
 * 2. Meta do utilizador logado (sige_escola_id) - essencial para multi-escola
 * 3. Subdomínio (*.sige.softgenial.edu.mz ou *.softgenial.edu.mz)
 * 4. Subdiretório (softgenial.edu.mz/malisa)
 * 5. Fallback: Malisa (ID 1)
 * 
 * @return int ID da escola
 */
function sige_get_escola_id() {
    static $escola_id = null;
    
    if ($escola_id !== null) {
        return $escola_id;
    }
    
    // 1. Constante (mais prioritário - definido no wp-config.php)
    if (defined('SIGE_CURRENT_ESCOLA')) {
        $escola_id = (int) SIGE_CURRENT_ESCOLA;
        return $escola_id;
    }
    
    // 2. Meta do utilizador logado (essencial para staff multi-escola)
    if (is_user_logged_in()) {
        $user_escola = get_user_meta(get_current_user_id(), 'sige_escola_id', true);
        if ($user_escola) {
            $escola_id = (int) $user_escola;
            return $escola_id;
        }
    }
    
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    
    // 3. Subdomínio FUTURO (*.sige.softgenial.edu.mz)
    if (preg_match('/^([a-z0-9-]+)\.sige\.softgenial\.edu\.mz$/i', $host, $matches)) {
        $slug = sanitize_key($matches[1]);
        $escola = sige_get_escola_by_slug($slug);
        if ($escola) {
            $escola_id = (int) $escola->id;
            return $escola_id;
        }
    }
    
    // 3b. Subdomínio ACTUAL (demo.softgenial.edu.mz)
    if (preg_match('/^([a-z0-9-]+)\.softgenial\.edu\.mz$/i', $host, $matches)) {
        $slug = sanitize_key($matches[1]);
        if ($slug !== 'www') {
            $escola = sige_get_escola_by_slug($slug);
            if ($escola) {
                $escola_id = (int) $escola->id;
                return $escola_id;
            }
        }
    }
    
    // 4. Subdiretório ACTUAL (softgenial.edu.mz/malisa)
    if (preg_match('/^\/(malisa|demo|[a-z0-9-]+)\//', $uri, $matches)) {
        $slug = sanitize_key($matches[1]);
        if (!in_array($slug, ['wp-admin', 'wp-content', 'wp-includes', 'wp-json'], true)) {
            $escola = sige_get_escola_by_slug($slug);
            if ($escola) {
                $escola_id = (int) $escola->id;
                return $escola_id;
            }
        }
    }
    
    // 5. Resolução final determinista: escola única activa, senão fail-closed (0).
    //    Remove o fallback cego para escola 1: em vez de assumir id=1, usa o id
    //    REAL da única escola activa quando existe exactamente uma; caso ambíguo
    //    (zero ou várias), devolve 0 e audita, em vez de adivinhar.
    $single = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;
    if ($single > 0) {
        $escola_id = $single;
        return $escola_id;
    }

    $escola_id = 0;
    if (function_exists('sige_security_log')) {
        sige_security_log('tenant_context_missing', 'Sem escola resolvável e sem escola única activa; contexto fail-closed (0).');
    }
    return $escola_id;
}

/**
 * Resolve a escola corrente de forma FAIL-CLOSED para CAMINHOS DE ESCRITA
 * autenticados (handlers AJAX, admin-post e codigo de topo de views).
 *
 * Devolve o id da escola resolvida. Se nao houver escola valida (modo
 * multi-escola estrito sem contexto resolvido, em que sige_get_escola_id()
 * devolve 0), BLOQUEIA com 403 em vez de cair silenciosamente para a escola 1.
 *
 * Em mono-escola ou ambiente relaxado, sige_get_escola_id() devolve a escola
 * unica (>= 1), pelo que esta funcao nao altera o comportamento. So fecha o
 * acesso quando o isolamento estrito esta activo e o contexto nao resolve.
 *
 * NAO usar em codigo chamavel por cron/CLI (onde wp_die mataria o processo):
 * nesses contextos, resolver com sige_get_escola_id() e abortar a escrita
 * quando o id vier <= 0.
 *
 * @param string $contexto Etiqueta curta para auditoria (ex.: 'guardar_notas').
 * @return int ID da escola resolvida (sempre > 0 quando a funcao retorna).
 */
if (!function_exists('sige_require_escola_id')) {
    function sige_require_escola_id(string $contexto = ''): int {
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($eid <= 0) {
            if (function_exists('sige_security_log')) {
                sige_security_log('tenant_write_blocked', 'Escrita bloqueada sem escola valida' . ($contexto !== '' ? ' (' . $contexto . ')' : ''));
            }
            if (function_exists('wp_die')) {
                wp_die('Operação bloqueada: contexto de escola inválido.', 'SIGE - Segurança', ['response' => 403]);
            }
            // Sem wp_die (contexto nao-web): devolve 0 para o chamador abortar.
        }
        return $eid;
    }
}

/**
 * Guard fail-closed AUDITADO para sumidouros de escrita e funcoes chamaveis por
 * cron/CLI (onde wp_die nao deve ser usado). Devolve true se a escola e valida
 * (> 0); caso contrario regista tenant_write_blocked e devolve false para o
 * chamador abortar com o retorno tipado adequado.
 *
 * Em mono-escola/relaxado o escola_id resolvido e >= 1, pelo que o guard nunca
 * dispara. So fecha quando o isolamento estrito esta activo e nao ha contexto.
 *
 * @param int    $escola_id Escola ja resolvida pelo chamador (param ou inline).
 * @param string $contexto  Etiqueta curta para auditoria.
 * @return bool   true se pode escrever; false se deve abortar.
 */
if (!function_exists('sige_tenant_write_guard')) {
    function sige_tenant_write_guard(int $escola_id, string $contexto = ''): bool {
        if ($escola_id > 0) {
            return true;
        }
        if (function_exists('sige_security_log')) {
            sige_security_log('tenant_write_blocked', 'Escrita bloqueada sem escola valida' . ($contexto !== '' ? ' (' . $contexto . ')' : ''));
        }
        return false;
    }
}

/**
 * Obtém escola pelo slug
 *
 * @param string $slug Slug da escola (ex: 'malisa')
 * @return object|null Objecto escola ou null
 */
function sige_get_escola_by_slug($slug) {
    global $wpdb;
    
    $tabela = $wpdb->prefix . 'sige_escolas';
    
    // Verificar se tabela existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$tabela'") !== $tabela) {
        return null;
    }
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$tabela} WHERE slug = %s AND activo = 1",
        $slug
    ));
}

/**
 * Obtém escola pelo ID
 * 
 * @param int $id ID da escola
 * @return object|null Objecto escola ou null
 */
function sige_get_escola($id = null) {
    global $wpdb;
    
    if ($id === null) {
        $id = sige_get_escola_id();
    }
    
    $tabela = $wpdb->prefix . 'sige_escolas';
    
    // Verificar se tabela existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$tabela'") !== $tabela) {
        // Retornar objecto Malisa fictício para retrocompatibilidade
        return (object) [
            'id' => 1,
            'nome' => 'Colégio Malisa',
            'slug' => 'malisa',
            'plano' => 'completo',
            'activo' => 1
        ];
    }
    
    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$tabela} WHERE id = %d",
        $id
    ));
}

/**
 * Obtém o nome da escola actual
 * 
 * @return string Nome da escola
 */
function sige_get_escola_nome() {
    $escola = sige_get_escola();
    return $escola ? $escola->nome : 'Escola';
}

/**
 * Verifica se a escola actual tem um plano específico
 * 
 * @param string|array $planos Plano(s) a verificar
 * @return bool
 */
function sige_escola_tem_plano($planos) {
    $escola = sige_get_escola();
    if (!$escola) return false;
    
    if (is_array($planos)) {
        return in_array($escola->plano, $planos);
    }
    
    return $escola->plano === $planos;
}

/**
 * Verifica se a escola está activa
 * 
 * @return bool
 */
function sige_escola_activa() {
    $escola = sige_get_escola();
    
    if (!$escola || !$escola->activo) {
        return false;
    }
    
    // Verificar trial
    if (!empty($escola->trial_ate)) {
        $trial_fim = strtotime($escola->trial_ate);
        if ($trial_fim < time()) {
            return false; // Trial expirado
        }
    }
    
    return true;
}

/**
 * Verifica limite de alunos da escola
 * 
 * @return array ['dentro_limite' => bool, 'actual' => int, 'limite' => int]
 */
function sige_verificar_limite_alunos() {
    global $wpdb;
    
    $escola = sige_get_escola();
    $escola_id = sige_get_escola_id();
    
    $limite = $escola ? (int) $escola->limite_alunos : 500;
    
    $actual = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sige_alunos WHERE escola_id = %d",
        $escola_id
    ));
    
    return [
        'dentro_limite' => $actual < $limite,
        'actual' => $actual,
        'limite' => $limite,
        'percentagem' => $limite > 0 ? round(($actual / $limite) * 100) : 0
    ];
}

// ============================================================
// HELPERS PARA QUERIES
// ============================================================

/**
 * Adiciona filtro escola_id a uma query WHERE
 * 
 * Uso: 
 *   $sql = "SELECT * FROM tabela WHERE status = 'activo'" . sige_escola_where();
 *   // Resultado: SELECT * FROM tabela WHERE status = 'activo' AND escola_id = 1
 * 
 * @param string $conector 'AND' ou 'WHERE'
 * @return string Fragmento SQL
 */
function sige_escola_where($conector = 'AND') {
    $escola_id = sige_get_escola_id();
    if ($escola_id <= 0) return " {$conector} 1 = 0";
    return " {$conector} escola_id = {$escola_id}";
}

/**
 * Adiciona escola_id aos dados de INSERT
 * 
 * Uso:
 *   $dados = ['nome' => 'João', 'turma_id' => 5];
 *   $dados = sige_add_escola_id($dados);
 *   // Resultado: ['nome' => 'João', 'turma_id' => 5, 'escola_id' => 1]
 * 
 * @param array $dados Dados originais
 * @return array Dados com escola_id
 */
function sige_add_escola_id($dados) {
    $escola_id = sige_get_escola_id();
    if ($escola_id <= 0) {
        if (function_exists('sige_security_log')) sige_security_log('tenant_insert_blocked', 'Insert SIGE sem escola válida.');
        $dados['escola_id'] = 0;
        return $dados;
    }
    $dados['escola_id'] = $escola_id;
    return $dados;
}

// ============================================================
// ADMIN: LISTAR ESCOLAS (para super-admin)
// ============================================================

/**
 * Obtém lista de todas as escolas
 * 
 * @param bool $apenas_activas Se true, retorna apenas activas
 * @return array Lista de escolas
 */
function sige_listar_escolas($apenas_activas = true) {
    global $wpdb;
    
    $tabela = $wpdb->prefix . 'sige_escolas';
    
    // Verificar se tabela existe
    if ($wpdb->get_var("SHOW TABLES LIKE '$tabela'") !== $tabela) {
        return [];
    }
    
    $where = $apenas_activas ? "WHERE activo = 1" : "";
    
    return $wpdb->get_results("SELECT * FROM {$tabela} {$where} ORDER BY nome ASC");
}

/**
 * Cria uma nova escola
 * 
 * @param array $dados Dados da escola
 * @return int|false ID da escola criada ou false
 */
function sige_criar_escola($dados) {
    global $wpdb;
    
    $defaults = [
        'plano' => 'completo',
        'limite_alunos' => 500,
        'activo' => 1,
        'criado_em' => current_time('mysql')
    ];
    
    $dados = wp_parse_args($dados, $defaults);
    
    // Gerar slug se não fornecido
    if (empty($dados['slug'])) {
        $dados['slug'] = sanitize_title($dados['nome']);
    }
    
    $result = $wpdb->insert(
        $wpdb->prefix . 'sige_escolas',
        $dados
    );
    
    if ($result) {
        return $wpdb->insert_id;
    }
    
    return false;
}

// ============================================================
// HOOKS DE INICIALIZAÇÃO
// ============================================================

/**
 * Adiciona escola_id automaticamente em todos os inserts SIGE
 */
add_filter('sige_before_insert', 'sige_add_escola_id', 10, 1);

/**
 * Regista as meta boxes no admin
 */
add_action('admin_init', function() {
    // Só mostrar selector de escola para super-admins
    if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && sige_is_multitenancy_active()) {
        add_action('admin_notices', 'sige_mostrar_selector_escola');
    }
});

/**
 * Verifica se multitenancy está activo
 * 
 * @return bool
 */
function sige_is_multitenancy_active() {
    global $wpdb;
    $tabela = $wpdb->prefix . 'sige_escolas';
    return $wpdb->get_var("SHOW TABLES LIKE '$tabela'") === $tabela;
}

/**
 * Mostra selector de escola no admin (para debug/super-admin)
 */
function sige_mostrar_selector_escola() {
    // Só mostrar se houver mais de 1 escola
    $escolas = sige_listar_escolas();
    if (count($escolas) <= 1) return;
    
    $escola_actual = sige_get_escola();
    
    echo '<div class="notice notice-info" style="padding:10px;">';
    if (!$escola_actual) {
        echo '<strong>🏫 Escola Actual:</strong> <span style="color:#b91c1c">sem contexto válido</span>';
    } else {
        echo '<strong>🏫 Escola Actual:</strong> ' . esc_html($escola_actual->nome);
        echo ' <small style="color:#666;">(' . esc_html($escola_actual->plano) . ')</small>';
    }
    echo '</div>';
}

// ============================================================
// FIM DO MÓDULO
// ============================================================