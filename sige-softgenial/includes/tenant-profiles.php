<?php
/**
 * SIGE SoftGenial - Tenant Profiles
 * Ficheiro: includes/tenant-profiles.php
 *
 * Sistema de perfis de tenant (cliente). Cada perfil define:
 *   • Que módulos ficam visíveis no menu (mod_financeiro, mod_academico, etc.)
 *   • Se o cliente pode (ou não) alterar esta configuração pela UI
 *   • Seeds iniciais de serviços (carregados via tenant-seeds.php)
 *
 * Perfis disponíveis:
 *   • tesouraria_only → só Tesouraria + Secretaria base + RH (ex: Casa Colorida)
 *   • escola_completa → todos os módulos (ex: Colégio Malisa)
 *   • preescolar      → Jardim + Financeiro + Secretaria base
 *
 * Configuração:
 *   • wp_option 'sige_tenant_profile' (string) → nome do perfil activo
 *   • wp_option 'sige_tenant_profile_locked' (bool) → se true, UI bloqueia edição
 *
 * Helper principal:
 *   sige_modulo_ativo($modulo) → bool (usado em todo o menu e routing)
 *
 * @since 13.0.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// DEFINIÇÕES DOS PERFIS
// ============================================================================

/**
 * Retorna a matriz de perfis disponíveis.
 * Cada perfil define quais módulos estão "on" por defeito.
 *
 * @return array<string, array{label:string, modulos:array<string>, locked:bool, descricao:string}>
 */
function sige_tenant_profiles_disponiveis() {
    return [
        'tesouraria_only' => [
            'label'     => 'Tesouraria (somente)',
            'descricao' => 'Cliente contratou apenas o módulo financeiro. Menus académicos, jardim e logística ocultos.',
            'modulos'   => ['mod_financeiro', 'mod_secretaria_base', 'mod_rh'],
            'locked'    => true,
        ],
        'escola_completa' => [
            'label'     => 'Escola Completa',
            'descricao' => 'Todos os módulos activos. Ideal para escolas com gestão académica e financeira integradas.',
            'modulos'   => [
                'mod_financeiro',
                'mod_academico',
                'mod_secretaria_base',
                'mod_rh',
                'mod_transporte',
                'mod_preescolar',
            ],
            'locked'    => false,
        ],
        'preescolar' => [
            'label'     => 'Pré-Escolar',
            'descricao' => 'Jardim de infância com tesouraria. Sem módulo académico formal.',
            'modulos'   => ['mod_financeiro', 'mod_secretaria_base', 'mod_rh', 'mod_preescolar'],
            'locked'    => false,
        ],
        'academico_only' => [
            'label'     => 'Académico (somente)',
            'descricao' => 'Só gestão de notas/pautas. Sem tesouraria.',
            'modulos'   => ['mod_academico', 'mod_secretaria_base', 'mod_rh'],
            'locked'    => false,
        ],
    ];
}

// ============================================================================
// NORMALIZAÇÃO DE MÓDULOS
// ============================================================================

/**
 * Mapa canónico dos módulos locais.
 *
 * Contexto: ao longo das versões o mesmo módulo apareceu com nomes diferentes
 * em camadas diferentes do sistema/Hub (ex.: mod_logistica vs mod_transporte,
 * mod_jardim vs mod_preescolar). Esta normalização evita que uma área esteja
 * activa na configuração, mas desapareça do menu por comparação literal.
 *
 * @return array<string,string>
 */
function sige_modulo_aliases() {
    return [
        'academico' => 'mod_academico',
        'mod_academico' => 'mod_academico',
        'financeiro' => 'mod_financeiro',
        'tesouraria' => 'mod_financeiro',
        'mod_financeiro' => 'mod_financeiro',
        'secretaria' => 'mod_secretaria_base',
        'cadastro' => 'mod_secretaria_base',
        'cadastro_base' => 'mod_secretaria_base',
        'mod_secretaria_base' => 'mod_secretaria_base',
        'rh' => 'mod_rh',
        'recursos_humanos' => 'mod_rh',
        'mod_rh' => 'mod_rh',
        'preescolar' => 'mod_preescolar',
        'pre_escolar' => 'mod_preescolar',
        'jardim' => 'mod_preescolar',
        'jardim_infancia' => 'mod_preescolar',
        'creche' => 'mod_preescolar',
        'mod_preescolar' => 'mod_preescolar',
        'mod_jardim' => 'mod_preescolar',
        'transporte' => 'mod_transporte',
        'logistica' => 'mod_transporte',
        'logística' => 'mod_transporte',
        'mod_transporte' => 'mod_transporte',
        'mod_logistica' => 'mod_transporte',
        'portal' => 'mod_portal',
        'portal_encarregados' => 'mod_portal',
        'portal_dos_encarregados' => 'mod_portal',
        'mod_portal' => 'mod_portal',
        'portaria' => 'mod_portaria',
        'portaria_digital' => 'mod_portaria',
        'mod_portaria' => 'mod_portaria',
    ];
}

/**
 * Normaliza a lista de módulos activos para chaves canónicas.
 *
 * @param array<int|string,mixed> $modulos
 * @return array<string>
 */
function sige_normalizar_modulos_ativos(array $modulos) {
    $aliases = sige_modulo_aliases();
    $normalizados = [];

    foreach ($modulos as $modulo) {
        $raw = sanitize_key((string)$modulo);
        if ($raw === '') continue;
        $normalizados[] = $aliases[$raw] ?? $raw;
    }

    // Secretaria base é dependência estrutural mínima do SIGE.
    $normalizados[] = 'mod_secretaria_base';

    $normalizados = array_values(array_unique(array_filter($normalizados)));
    sort($normalizados);
    return $normalizados;
}

/**
 * Normaliza uma chave individual de módulo.
 *
 * @param string $modulo
 * @return string
 */
function sige_normalizar_modulo_key($modulo) {
    $raw = sanitize_key((string)$modulo);
    if ($raw === '') return '';
    $aliases = sige_modulo_aliases();
    return $aliases[$raw] ?? $raw;
}

// ============================================================================
// GETTERS
// ============================================================================

/**
 * Retorna o perfil de tenant activo.
 * Default: 'escola_completa' (retrocompatibilidade com instalações v12.x).
 *
 * @return string
 */
function sige_get_tenant_profile() {
    $profile = get_option('sige_tenant_profile', 'escola_completa');
    $perfis = sige_tenant_profiles_disponiveis();
    if (!isset($perfis[$profile])) {
        $profile = 'escola_completa';
    }
    return $profile;
}

/**
 * Retorna se o perfil actual está bloqueado (não editável pela UI).
 *
 * @return bool
 */
function sige_tenant_profile_is_locked() {
    $profile = sige_get_tenant_profile();
    $perfis = sige_tenant_profiles_disponiveis();
    // Flag no perfil manda; mas admin TI pode forçar override via wp_option
    $lock_override = get_option('sige_tenant_profile_locked', null);
    if ($lock_override !== null) {
        return (bool)$lock_override;
    }
    return !empty($perfis[$profile]['locked']);
}

/**
 * Retorna a lista de módulos activos (respeitando o perfil e overrides).
 *
 * Prioridade:
 *   1) Se o perfil está LOCKED → usa lista do perfil (ignora modulos_ativos do escola)
 *   2) Se NÃO está locked → usa modulos_ativos da tabela sige_escolas (se vazio, usa perfil)
 *
 * @return array<string>
 */
function sige_get_modulos_ativos() {
    static $cache = null;
    if ($cache !== null) return $cache;

    $profile = sige_get_tenant_profile();
    $perfis = sige_tenant_profiles_disponiveis();
    $modulos_perfil = $perfis[$profile]['modulos'] ?? [];

    // Se perfil locked, usa apenas o perfil
    if (sige_tenant_profile_is_locked()) {
        $cache = sige_normalizar_modulos_ativos($modulos_perfil);
        return $cache;
    }

    // Senão, tenta ler da tabela sige_escolas
    $escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
    if ($escola && !empty($escola->modulos_ativos)) {
        $modulos_db = json_decode($escola->modulos_ativos, true);
        if (is_array($modulos_db) && !empty($modulos_db)) {
            $cache = sige_normalizar_modulos_ativos($modulos_db);
            return $cache;
        }
    }

    // Fallback: usa perfil
    $cache = sige_normalizar_modulos_ativos($modulos_perfil);
    return $cache;
}

// ============================================================================
// HELPER CENTRAL - usar em todos os condicionais de menu
// ============================================================================

/**
 * Verifica se um módulo está activo.
 * Esta é a fonte única de verdade para controlo de visibilidade de menus.
 *
 * @param string $modulo ex: 'mod_financeiro', 'mod_academico'
 * @return bool
 */
function sige_modulo_ativo($modulo) {
    $modulo = function_exists('sige_normalizar_modulo_key') ? sige_normalizar_modulo_key((string)$modulo) : sanitize_key((string)$modulo);

    // mod_secretaria_base é sempre obrigatório (alunos, turmas básicas).
    // Sem alunos, não há tesouraria possível.
    if ($modulo === 'mod_secretaria_base') return true;

    $modulos = sige_get_modulos_ativos();
    return in_array($modulo, $modulos, true);
}

// ============================================================================
// REDIRECT PADRÃO DO DASHBOARD
// ============================================================================

/**
 * Retorna a view default quando o utilizador vai para 'dashboard'.
 * Se o académico está off, vai para financeiro-dashboard.
 *
 * @return string
 */
function sige_default_dashboard_view() {
    if (!sige_modulo_ativo('mod_academico') && sige_modulo_ativo('mod_financeiro')) {
        return 'financeiro-dashboard';
    }
    return 'dashboard';
}

// ============================================================================
// SETTER (para admin TI - só via código/CLI/wp_options directamente)
// ============================================================================

/**
 * Define o perfil de tenant. Só deve ser chamado por admin TI ou na activação.
 *
 * @param string $profile nome do perfil
 * @return bool
 */
function sige_set_tenant_profile($profile) {
    $perfis = sige_tenant_profiles_disponiveis();
    if (!isset($perfis[$profile])) {
        return false;
    }
    update_option('sige_tenant_profile', $profile);

    // Limpa cache estático (vai recarregar na próxima chamada)
    // Em WP não há forma elegante de limpar static; o reload da página resolve.

    return true;
}
