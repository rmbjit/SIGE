<?php
/**
 * SIGE SoftGenial - UI de Permissões v12.8.1
 * Fase segura: perfis + matriz de permissões + atribuição de perfil ao utilizador.
 * Sem overrides por utilizador nesta versão.
 */
if (!defined('ABSPATH')) exit;

// Hotfix 12.8.5: a UI de permissões não pode depender de carregamento indirecto.
// Garante que a Permission Engine está disponível antes de consultar tabelas/roles.
if (!function_exists("sige_permissions_tables")) {
    $perm_layer = defined("SIGE_PATH") ? SIGE_PATH . "includes/permissions-layer.php" : dirname(__DIR__, 2) . "/includes/permissions-layer.php";
    if (file_exists($perm_layer)) {
        require_once $perm_layer;
    }
}


// [12.9.6] Esta página é a *única excepção* à regra "matriz manda".
// Razão: é o painel onde o administrador corrige uma matriz mal configurada.
// Se a matriz estiver quebrada/vazia, ainda assim alguém tem de poder repará-la.
// Por isso, o bypass aqui é deliberadamente mais permissivo do que em qualquer
// outra página do plugin:
//   1) Admin WP real (`manage_options` / `is_super_admin` / role `administrator`).
//   2) WP role `sige_admin_ti` (rede de segurança institucional do tenant).
//   3) Aliases legados: `admin_ti`, `administrador_ti`.
// Só depois - quando nenhum dos itens acima passa - é que se exige a
// permissão `usuarios.gerir_permissoes` da matriz. Esta exigência preserva o
// pedido original: se o admin tirar essa permissão a um perfil SIGE, o
// utilizador desse perfil DEIXA de ver esta página, EXCEPTO se for tecnicamente
// um administrador WP ou tiver o WP role `sige_admin_ti` (rede de segurança).
$__sige_perm_admin_bypass = false;
if (function_exists('sige_permissions_is_super_admin') && sige_permissions_is_super_admin(get_current_user_id())) {
    $__sige_perm_admin_bypass = true;
}
if (!$__sige_perm_admin_bypass && ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_admin_ti'))) {
    $__sige_perm_admin_bypass = true;
}
if (!$__sige_perm_admin_bypass) {
    $__sige_current_user = function_exists('wp_get_current_user') ? wp_get_current_user() : null;
    $__sige_roles = ($__sige_current_user && !empty($__sige_current_user->roles)) ? array_map('strval', (array)$__sige_current_user->roles) : [];
    $__sige_admin_roles = ['administrator', 'super_admin', 'sige_admin_ti', 'admin_ti', 'administrador_ti'];
    if (array_intersect($__sige_roles, $__sige_admin_roles)) {
        $__sige_perm_admin_bypass = true;
    }
}

if (!$__sige_perm_admin_bypass && (!function_exists('sige_can') || !sige_can('usuarios.gerir_permissoes'))) {
    echo '<div class="sige-perm-denied"><strong>Acesso restrito.</strong><br>Sem permissão para gerir perfis e permissões.</div>';
    return;
}

if (function_exists('sige_permissions_maybe_install')) {
    sige_permissions_maybe_install();
}

global $wpdb;
$t = function_exists('sige_permissions_tables') ? sige_permissions_tables() : [];
if (empty($t['roles']) || empty($t['permissions']) || empty($t['role_permissions']) || empty($t['user_roles'])) {
    echo '<div class="sige-perm-denied"><strong>Gestão de permissões indisponível.</strong><br>Não foi possível carregar as tabelas de permissões. Contacte o suporte SoftGenial para regularizar esta área.</div>';
    return;
}
$feedback = '';
$feedback_type = 'success';
$escola_id_contexto = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
if ($escola_id_contexto <= 0 && !(function_exists('sige_permissions_is_super_admin') && sige_permissions_is_super_admin(get_current_user_id()))) {
    echo '<div class="sige-perm-denied"><strong>Escola não identificada.</strong><br>A gestão de permissões é tenant-scoped e foi bloqueada para evitar alterações globais acidentais.</div>';
    return;
}
if ($escola_id_contexto <= 0) $escola_id_contexto = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;

function sige_perm_ui_group_label($modulo) {
    $labels = [
        'financeiro' => 'Financeiro',
        'academico' => 'Académico',
        'secretaria' => 'Secretaria / Alunos',
        'documentos' => 'Documentos',
        'configuracoes' => 'Configurações',
        'usuarios' => 'Utilizadores',
        'portal' => 'Portal',
        'portaria' => 'Portaria',
        'transporte' => 'Transporte / Logística',
        'jardim' => 'Jardim / Creche',
        'rh' => 'Recursos Humanos',
        'comunicacao' => 'Comunicação',
        'sistema' => 'Sistema Técnico',
        'core' => 'Sistema',
    ];
    return $labels[$modulo] ?? ucfirst((string)$modulo);
}

if (!function_exists('sige_perm_ui_effective_keys')) {
    /**
     * Permissões com aplicação real nesta build.
     *
     * Uma permissão entra aqui quando é usada por page guard, action/service guard
     * ou verificação directa sige_can() no código actual. As restantes permissões
     * continuam registadas para compatibilidade/futuro, mas a UI deixa de as tratar
     * como se já tivessem efeito operacional.
     */
    function sige_perm_ui_effective_keys(): array {
        return [
        'academico.actas_emitir',
        'academico.actas_ver',
        'academico.alocacao_gerir',
        'academico.alocacao_ver',
        'academico.aprovar_notas',
        'academico.auditoria_notas_ver',
        'academico.boletins_emitir',
        'academico.boletins_ver',
        'academico.dashboard_ver',
        'academico.dec_emitir',
        'academico.dec_ver',
        'academico.disciplinas_gerir',
        'academico.disciplinas_ver',
        'academico.editar_notas',
        'academico.estatisticas_ver',
        'academico.fechar_ano',
        'academico.lancar_notas',
        'academico.matriz_gerir',
        'academico.matriz_ver',
        'academico.pauta_final_gerir',
        'academico.pauta_final_ver',
        'academico.pautas_emitir',
        'academico.pautas_ver',
        'academico.reabrir_ano',
        'academico.turmas_gerir',
        'academico.turmas_ver',
        'academico.ver',
        'alunos.contas_gerir',
        'alunos.contas_ver',
        'alunos.criar',
        'alunos.editar',
        'alunos.apagar',
        'alunos.ver',
        'configuracoes.editar',
        'configuracoes.ver',
        'financeiro.auditoria_ver',
        'financeiro.bloquear_mes',
        'financeiro.centros_custo_gerir',
        'financeiro.centros_custo_ver',
        'financeiro.cobrancas_gerir',
        'financeiro.cobrancas_ver',
        'financeiro.configurar_precos',
        'financeiro.dashboard_ver',
        'financeiro.desbloquear_mes',
        'financeiro.despesas_gerir',
        'financeiro.despesas_ver',
        'financeiro.estornar',
        'financeiro.extractos_ver',
        'financeiro.lancamentos_gerir',
        'financeiro.lancamentos_ver',
        'financeiro.lancar_mensalidades',
        'financeiro.pagamentos_turma_ver',
        'financeiro.pagar',
        'financeiro.planos_gerir',
        'financeiro.planos_ver',
        'financeiro.relatorio_mensal_ver',
        'financeiro.servicos_ver',
        'financeiro.ver',
        'jardim.boletim_emitir',
        'jardim.boletim_ver',
        'jardim.diario_gerir',
        'jardim.diario_ver',
        'jardim.presencas_gerir',
        'jardim.presencas_ver',
        'jardim.relatorio_emitir',
        'jardim.relatorio_ver',
        'jardim.saude_gerir',
        'jardim.saude_ver',
        'matriculas.editar',
        'portaria.validar_acesso',
        'portaria.ver',
        'rh.equipe_gerir',
        'rh.equipe_ver',
        'sistema.estado_ver',
        'transporte.alunos_gerir',
        'transporte.rotas_gerir',
        'transporte.ver',
        'usuarios.gerir_permissoes'
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_perm_action'])) {
    check_admin_referer('sige_permissions_ui_action', '_sige_perm_nonce');
    $action = sanitize_key((string)$_POST['sige_perm_action']);

    if ($action === 'break_glass_restore_user_role') {
        $is_owner = function_exists('sige_permissions_principal_protegido')
            && sige_permissions_principal_protegido(get_current_user_id());
        $identifier = isset($_POST['user_identifier']) ? sanitize_text_field(wp_unslash((string)$_POST['user_identifier'])) : '';
        $role_id = isset($_POST['role_id']) ? absint($_POST['role_id']) : 0;
        $role = $role_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['roles']} WHERE id = %d AND ativo = 1 LIMIT 1", $role_id)) : null;
        $target = false;
        if ($identifier !== '') {
            if (ctype_digit($identifier)) $target = get_user_by('id', (int)$identifier);
            if (!$target && is_email($identifier)) $target = get_user_by('email', $identifier);
            if (!$target) $target = get_user_by('login', $identifier);
            if (!$target) {
                $found = get_users(['search' => '*' . esc_attr($identifier) . '*', 'number' => 2, 'fields' => ['ID','display_name','user_login','user_email']]);
                if (is_array($found) && count($found) === 1) $target = get_user_by('id', (int)$found[0]->ID);
            }
        }
        if (!$is_owner) {
            $feedback_type = 'error';
            $feedback = 'Recuperação bloqueada. Apenas administrador WordPress real pode executar recuperação break-glass.';
        } elseif (!$target || !$role) {
            $feedback_type = 'error';
            $feedback = 'Utilizador ou perfil inválido. Confirme o e-mail, username ou ID e seleccione um perfil.';
        } else {
            $sync_ok = function_exists('sige_permissions_sync_user_role')
                ? (bool)sige_permissions_sync_user_role((int)$target->ID, (string)$role->slug, (int)$escola_id_contexto, true)
                : false;
            if ($sync_ok && function_exists('sige_user_integrity_mirror_wp_role')) {
                sige_user_integrity_mirror_wp_role((int)$target->ID, (string)$role->slug, (int)$escola_id_contexto, 'break_glass_restore');
            }
            if ($sync_ok && function_exists('sige_user_integrity_update_snapshot')) {
                sige_user_integrity_update_snapshot((int)$target->ID, (string)$role->slug, (int)$escola_id_contexto, 'break_glass_restore');
            }
            if (function_exists('sige_user_integrity_log')) {
                sige_user_integrity_log($sync_ok ? 'break_glass_restore_ok' : 'break_glass_restore_failed', [
                    'target_id' => (int)$target->ID,
                    'target_login' => (string)$target->user_login,
                    'role_slug' => (string)($role->slug ?? ''),
                    'escola_id' => (int)$escola_id_contexto,
                ], (int)$target->ID);
            }
            if (function_exists('sige_permission_audit')) {
                sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', (bool)$sync_ok, 'ui_break_glass_restore_user_role', ['user_id' => (int)$target->ID, 'role_id' => (int)$role_id]);
            }
            $feedback_type = $sync_ok ? 'success' : 'error';
            $feedback = $sync_ok
                ? 'Perfil recuperado com sucesso. A conta voltou a ter perfil SIGE activo e espelho WordPress quando aplicável.'
                : 'Não foi possível recuperar o perfil. Nenhuma alteração foi aplicada.';
        }
    }

    if ($action === 'save_niveis') {
        // Accao de dono: editar a hierarquia de niveis e exclusivo do administrador WordPress real.
        $is_owner = function_exists('sige_permissions_principal_protegido')
            && sige_permissions_principal_protegido(get_current_user_id());
        if (!$is_owner) {
            $feedback_type = 'error';
            $feedback = 'Apenas um administrador WordPress pode editar a hierarquia de níveis.';
            if (function_exists('sige_permission_audit')) {
                sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', false, 'ui_niveis_blocked', []);
            }
        } else {
            $entradas = (isset($_POST['niveis']) && is_array($_POST['niveis'])) ? wp_unslash($_POST['niveis']) : [];
            $res = function_exists('sige_permissions_guardar_niveis') ? sige_permissions_guardar_niveis((array)$entradas) : ['ok' => false, 'desvios' => 0];
            if (function_exists('sige_permission_audit')) {
                sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', true, 'ui_save_niveis', ['desvios' => (int)($res['desvios'] ?? 0)]);
            }
            $feedback_type = 'success';
            $feedback = 'Hierarquia de níveis actualizada.';
        }
    }

    if ($action === 'save_role_permissions') {
        $role_id = isset($_POST['role_id']) ? absint($_POST['role_id']) : 0;
        $role = $role_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['roles']} WHERE id = %d LIMIT 1", $role_id)) : null;
        if (!$role) {
            $feedback_type = 'error';
            $feedback = 'Perfil inválido. Nenhuma alteração foi aplicada.';
        } else {
            $registry = function_exists('sige_permissions_registry') ? sige_permissions_registry() : [];
            $selected = isset($_POST['permissions']) && is_array($_POST['permissions']) ? array_map('sige_permission_normalize', wp_unslash($_POST['permissions'])) : [];
            $selected = array_values(array_intersect($selected, array_keys($registry)));

            // [12.10.90] A UI só edita permissões com efeito real nesta build.
            // Permissões registadas mas ainda não ligadas a guard/action são preservadas
            // como compatibilidade futura, sem ficarem falsamente editáveis/decorativas.
            $__effective_map_save = array_fill_keys(sige_perm_ui_effective_keys(), true);
            $__school_table = $t['school_role_permissions'] ?? '';
            $__school_rows_count = $__school_table !== '' ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$__school_table} WHERE escola_id = %d AND role_id = %d", $escola_id_contexto, $role_id)) : 0;
            $__existing_all = $__school_rows_count > 0
                ? $wpdb->get_col($wpdb->prepare("SELECT permission_key FROM {$__school_table} WHERE escola_id = %d AND role_id = %d AND allowed = 1", $escola_id_contexto, $role_id))
                : $wpdb->get_col($wpdb->prepare("SELECT permission_key FROM {$t['role_permissions']} WHERE role_id = %d AND allowed = 1", $role_id));
            $__existing_all = array_map('sige_permission_normalize', (array)$__existing_all);
            $__selected_effective = array_values(array_filter($selected, static function($perm) use ($__effective_map_save) {
                return isset($__effective_map_save[$perm]);
            }));
            $__preserve_planned = array_values(array_filter($__existing_all, static function($perm) use ($__effective_map_save) {
                return !isset($__effective_map_save[$perm]);
            }));
            $selected = array_values(array_unique(array_merge($__selected_effective, $__preserve_planned)));
            if ((string)($role->slug ?? '') === 'guarda') {
                // v12.11.9.65 - perfil operacional fechado: Portaria + consulta de alunos somente-leitura.
                $selected = ['portaria.ver','portaria.validar_acesso','alunos.ver'];
            }

            $__target_role_permissions = $t['school_role_permissions'] ?? '';
            if ($__target_role_permissions === '') {
                $feedback_type = 'error';
                $feedback = 'Tabela tenant-scoped de permissões indisponível. Nenhuma alteração foi aplicada.';
            } else {
                $wpdb->query($wpdb->prepare("DELETE FROM {$__target_role_permissions} WHERE escola_id = %d AND role_id = %d", $escola_id_contexto, $role_id));
                foreach ($selected as $perm) {
                    $wpdb->insert($__target_role_permissions, [
                        'escola_id' => $escola_id_contexto,
                        'role_id' => $role_id,
                        'permission_key' => $perm,
                        'allowed' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ], ['%d','%d','%s','%d','%s','%s']);
                }
            }
            if ($feedback_type !== 'error') {
                if (function_exists('sige_permission_audit')) {
                    sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', true, 'ui_save_role_permissions', ['role_id' => $role_id, 'escola_id' => $escola_id_contexto, 'count' => count($selected)]);
                }
                $feedback_type = 'success';
                $feedback = 'Permissões do perfil guardadas para esta escola com sucesso.';
                $_GET['role_id'] = (string)$role_id;
            }
        }
    }

    if ($action === 'assign_user_role') {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $role_id = isset($_POST['role_id']) ? absint($_POST['role_id']) : 0;
        $escola_id = $escola_id_contexto;
        if ($escola_id <= 0) {
            $feedback_type = 'error';
            $feedback = 'Escola não identificada. Nenhuma alteração foi aplicada.';
        }
        $user = $user_id ? get_user_by('id', $user_id) : false;
        $role = $role_id ? $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['roles']} WHERE id = %d AND ativo = 1 LIMIT 1", $role_id)) : null;
        if (!$user || !$role) {
            $feedback_type = 'error';
            $feedback = 'Utilizador ou perfil inválido. Nenhuma alteração foi aplicada.';
        } elseif (function_exists('sige_permissions_avaliar_operacao')
            && !($__aval = sige_permissions_avaliar_operacao(get_current_user_id(), (int)$user_id, 'assign_user_role', (int)$role_id, (int)$escola_id))['permitido']) {
            $feedback_type = 'error';
            $feedback = $__aval['motivo'];
            if (function_exists('sige_permission_audit')) {
                sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', false, 'ui_assign_blocked', ['user_id' => $user_id, 'role_id' => $role_id, 'codigo' => $__aval['codigo']]);
            }
        } else {
            // Fonte de verdade: um perfil SIGE activo por utilizador/escola.
            // A função central também sincroniza o WP role legado para manter menus/rotas antigas coerentes.
            $sync_ok = true;
            if (function_exists('sige_permissions_sync_user_role')) {
                $sync_ok = (bool) sige_permissions_sync_user_role($user_id, (string)$role->slug, $escola_id, true);
            } else {
                $wpdb->update($t['user_roles'], ['ativo' => 0, 'updated_at' => current_time('mysql')], ['user_id' => $user_id, 'escola_id' => $escola_id], ['%d','%s'], ['%d','%d']);
                $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['user_roles']} WHERE user_id = %d AND role_id = %d AND escola_id = %d LIMIT 1", $user_id, $role_id, $escola_id));
                if ($exists > 0) {
                    $wpdb->update($t['user_roles'], ['ativo' => 1, 'updated_at' => current_time('mysql')], ['id' => $exists], ['%d','%s'], ['%d']);
                } else {
                    $wpdb->insert($t['user_roles'], [
                        'user_id' => $user_id,
                        'role_id' => $role_id,
                        'escola_id' => $escola_id,
                        'ativo' => 1,
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql'),
                    ], ['%d','%d','%d','%d','%s','%s']);
                }
            }
            if (!$sync_ok) {
                $feedback_type = 'error';
                $feedback = 'Alteração bloqueada pela guarda de integridade de utilizadores. Apenas administrador WordPress real pode atribuir ou despromover perfis privilegiados.';
                if (function_exists('sige_permission_audit')) {
                    sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', false, 'ui_assign_integrity_blocked', ['user_id' => $user_id, 'role_id' => $role_id]);
                }
            } else {
                if (function_exists('sige_permission_audit')) {
                    sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', true, 'ui_assign_user_role', ['user_id' => $user_id, 'role_id' => $role_id]);
                }
                $feedback_type = 'success';
                $feedback = 'Perfil atribuído ao utilizador com sucesso.';
            }
        }
    }

    // [12.9.7] Remoção do perfil SIGE activo de um utilizador.
    // Volta o utilizador ao comportamento legado (WP roles da Equipa). Não toca
    // no WP role nem nos dados do utilizador - apenas desactiva a linha em
    // {prefix}sige_user_roles para esta escola. Reversível.
    if ($action === 'unassign_user_role') {
        $user_id = isset($_POST['user_id']) ? absint($_POST['user_id']) : 0;
        $escola_id = $escola_id_contexto;
        if ($escola_id <= 0) {
            $feedback_type = 'error';
            $feedback = 'Escola não identificada. Nenhuma alteração foi aplicada.';
        }
        $user = $user_id ? get_user_by('id', $user_id) : false;
        if (!$user) {
            $feedback_type = 'error';
            $feedback = 'Utilizador inválido. Nenhuma alteração foi aplicada.';
        } elseif (function_exists('sige_permissions_avaliar_operacao')
            && !($__aval_u = sige_permissions_avaliar_operacao(get_current_user_id(), (int)$user_id, 'unassign_user_role', 0, (int)$escola_id))['permitido']) {
            $feedback_type = 'error';
            $feedback = $__aval_u['motivo'];
            if (function_exists('sige_permission_audit')) {
                sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', false, 'ui_unassign_blocked', ['user_id' => $user_id, 'codigo' => $__aval_u['codigo']]);
            }
        } else {
            $affected = $wpdb->update(
                $t['user_roles'],
                ['ativo' => 0, 'updated_at' => current_time('mysql')],
                ['user_id' => $user_id, 'escola_id' => $escola_id, 'ativo' => 1],
                ['%d','%s'],
                ['%d','%d','%d']
            );
            // Sobreposicao reversivel: repor o papel WordPress original do utilizador.
            if ($affected > 0 && function_exists('sige_permissions_restore_wp_roles')
                && !(function_exists('sige_permissions_is_super_admin') ? sige_permissions_is_super_admin($user_id) : user_can($user_id, 'manage_options'))) {
                sige_permissions_restore_wp_roles($user_id);
            }
            if ($affected > 0 && function_exists('sige_user_integrity_retire_privileged_snapshot')
                && function_exists('sige_permissions_principal_protegido')
                && sige_permissions_principal_protegido(get_current_user_id())) {
                sige_user_integrity_retire_privileged_snapshot((int)$user_id, (int)$escola_id, 'ui_unassign_user_role');
            }
            if (function_exists('sige_permission_audit')) {
                sige_permission_audit(get_current_user_id(), 'usuarios.gerir_permissoes', true, 'ui_unassign_user_role', ['user_id' => $user_id, 'rows_affected' => (int)$affected]);
            }
            $feedback_type = 'success';
            $feedback = $affected > 0
                ? 'Perfil SIGE removido. O papel WordPress original do utilizador foi reposto.'
                : 'Nenhum perfil activo para remover neste utilizador.';
        }
    }
}
$msg = isset($_GET['perm_msg']) ? sanitize_key((string)$_GET['perm_msg']) : '';
$messages = [
    'role_saved' => ['success', 'Permissões do perfil guardadas com sucesso.'],
    'user_assigned' => ['success', 'Perfil atribuído ao utilizador com sucesso.'],
    'role_invalid' => ['error', 'Perfil inválido. Nenhuma alteração foi aplicada.'],
    'assign_invalid' => ['error', 'Utilizador ou perfil inválido. Nenhuma alteração foi aplicada.'],
];
if (!$feedback && isset($messages[$msg])) {
    [$feedback_type, $feedback] = $messages[$msg];
}


if (!function_exists('sige_perm_ui_operational_role_slugs')) {
    /**
     * Catálogo operacional apresentado ao utilizador.
     * Mantém fora da UI perfis legados/duplicados/portal/apoio sem fluxo real de backend.
     * Não apaga dados da base para preservar compatibilidade histórica.
     */
    function sige_perm_ui_operational_role_slugs(): array {
        return [
            'admin_escola',
            'admin_ti',
            'direccao_geral',
            'dir_pedagogico',
            'gestor_rh',
            'professor',
            'educador',
            'secretaria_geral',
            'secretario',
            'tesoureiro',
            'assistente',
            'recepcao',
            'guarda',
        ];
    }
}

$__operational_role_slugs = sige_perm_ui_operational_role_slugs();
$__operational_role_map = array_fill_keys($__operational_role_slugs, true);
$__role_placeholders = implode(',', array_fill(0, count($__operational_role_slugs), '%s'));
$roles = [];
$__hidden_roles_count = 0;
if (!empty($__operational_role_slugs)) {
    $roles = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$t['roles']} WHERE ativo = 1 AND slug IN ($__role_placeholders)",
        ...$__operational_role_slugs
    ));
    $__role_order = array_flip($__operational_role_slugs);
    usort($roles, static function($a, $b) use ($__role_order) {
        return ($__role_order[$a->slug] ?? 999) <=> ($__role_order[$b->slug] ?? 999);
    });
    $__hidden_roles_count = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$t['roles']} WHERE ativo = 1 AND slug NOT IN ($__role_placeholders)",
        ...$__operational_role_slugs
    ));
}

$registry = function_exists('sige_permissions_registry') ? sige_permissions_registry() : [];
$permissions_rows = $wpdb->get_results("SELECT * FROM {$t['permissions']} WHERE ativo = 1 ORDER BY modulo ASC, permission_key ASC", OBJECT_K);

$selected_role_id = isset($_GET['role_id']) ? absint($_GET['role_id']) : 0;
if (!$selected_role_id && !empty($roles)) $selected_role_id = (int)$roles[0]->id;
$selected_role = null;
foreach ($roles as $r) { if ((int)$r->id === $selected_role_id) { $selected_role = $r; break; } }
if (!$selected_role && !empty($roles)) { $selected_role = $roles[0]; $selected_role_id = (int)$selected_role->id; }

$allowed_for_role = [];
if ($selected_role_id) {
    $__school_table = $t['school_role_permissions'] ?? '';
    $__school_rows_count = $__school_table !== '' ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$__school_table} WHERE escola_id = %d AND role_id = %d", $escola_id_contexto, $selected_role_id)) : 0;
    $allowed_for_role = $__school_rows_count > 0
        ? $wpdb->get_col($wpdb->prepare("SELECT permission_key FROM {$__school_table} WHERE escola_id = %d AND role_id = %d AND allowed = 1", $escola_id_contexto, $selected_role_id))
        : $wpdb->get_col($wpdb->prepare("SELECT permission_key FROM {$t['role_permissions']} WHERE role_id = %d AND allowed = 1", $selected_role_id));
    $allowed_for_role = array_fill_keys(array_map('sige_permission_normalize', $allowed_for_role), true);
}

$__perm_effective_keys = sige_perm_ui_effective_keys();
$__perm_effective_map = array_fill_keys($__perm_effective_keys, true);
$__perm_effective_count = 0;
$__perm_planned_count = 0;

$grouped = [];
$grouped_planned = [];
foreach ($registry as $key => $meta) {
    $mod = $meta['modulo'] ?? 'core';
    if (isset($__perm_effective_map[$key])) {
        $grouped[$mod][$key] = $meta;
        $__perm_effective_count++;
    } else {
        $grouped_planned[$mod][$key] = $meta;
        $__perm_planned_count++;
    }
}
ksort($grouped);
ksort($grouped_planned);

// [12.9.7] Listagem restringida a STAFF apenas.
// Antes desta versão a página listava todos os utilizadores do site, incluindo
// alunos e encarregados. Esse era o vector mais provável de erro humano -
// atribuir por engano um perfil de staff (e.g., `sige_secretario`) a um aluno
// dar-lhe-ia acesso ao backend. A correcção é filtrar à fonte: SQL-level
// `role__in` com a lista canónica de WP roles de staff. `sige_encarregado`
// e `sige_aluno` ficam de fora; estes operam sempre via portal.
//
// `administrator` é incluído porque o owner do sistema (tu) pode optar por
// atribuir a si próprio um perfil SIGE explícito em vez de operar só via
// bypass de `manage_options`.
$__staff_roles_filter = [
    'administrator',
    'sige_admin_ti',
    'sige_director',
    'sige_pedagogico',
    'sige_secretaria_geral',
    'sige_secretario',
    'sige_assistente',
    'sige_recepcao',
    'sige_guarda',
    'sige_financeiro',
    'sige_professor',
    'sige_educador',
    'sige_gestor_rh',
    'sige_motorista',
    'sige_limpeza',
];
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
if ($escola_id <= 0) $escola_id = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;

$__staff_wp_users = get_users([
    'fields' => ['ID','display_name','user_email'],
    'orderby' => 'display_name',
    'order' => 'ASC',
    'role__in' => $__staff_roles_filter,
    'number' => 500,
]);
$__staff_ids = array_map(static function($u) { return (int)$u->ID; }, (array)$__staff_wp_users);
$__active_sige_ids = [];
if (!empty($t['user_roles']) && !empty($t['roles']) && $escola_id > 0) {
    $__active_sige_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT ur.user_id
           FROM {$t['user_roles']} ur
           INNER JOIN {$t['roles']} r ON r.id = ur.role_id
          WHERE ur.escola_id = %d
            AND ur.ativo = 1
            AND r.ativo = 1",
        $escola_id
    ));
    $__active_sige_ids = array_map('intval', (array)$__active_sige_ids);
}
$__all_staff_ids = array_values(array_unique(array_filter(array_merge($__staff_ids, $__active_sige_ids))));
$users = !empty($__all_staff_ids) ? get_users([
    'include' => $__all_staff_ids,
    'fields' => ['ID','display_name','user_email'],
    'orderby' => 'display_name',
    'order' => 'ASC',
    'number' => 700,
]) : [];
// Hierarquia (Fase 9 Incr 2): estado e nivel do actor, calculados uma vez.
$__actor_id = get_current_user_id();
$__actor_protegido = function_exists('sige_permissions_principal_protegido') && sige_permissions_principal_protegido((int)$__actor_id);
$__actor_nivel = function_exists('sige_permissions_user_nivel') ? sige_permissions_user_nivel((int)$__actor_id, (int)$escola_id) : 0;
$user_role_rows = $wpdb->get_results($wpdb->prepare(
    "SELECT ur.user_id, r.nome AS role_nome, r.slug AS role_slug
       FROM {$t['user_roles']} ur
       INNER JOIN {$t['roles']} r ON r.id = ur.role_id
      WHERE ur.escola_id = %d AND ur.ativo = 1",
    $escola_id
), OBJECT_K);
$__users_total_count = count($users);
$__users_assigned_count = 0;
foreach ($users as $__u) {
    if (isset($user_role_rows[$__u->ID])) $__users_assigned_count++;
}

$perm_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return sige_ui_icon($name);
    }
    $map = [
        'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'key' => '<circle cx="7.5" cy="15.5" r="5.5"/><path d="M12 12l8-8"/><path d="M15 5l4 4"/><path d="M17 3l4 4"/>',
        'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'grid' => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
        'lock' => '<rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/>',
        'save' => '<path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><path d="M17 21v-8H7v8"/><path d="M7 3v5h8"/>',
        'x' => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
        'check' => '<path d="M20 6 9 17l-5-5"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'info' => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
        'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'settings' => '<path d="M12 15.5A3.5 3.5 0 1 0 12 8a3.5 3.5 0 0 0 0 7.5Z"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06A1.65 1.65 0 0 0 15 19.4a1.65 1.65 0 0 0-1 .6 1.65 1.65 0 0 0-.4 1.1V21a2 2 0 1 1-4 0v-.09a1.65 1.65 0 0 0-.4-1.1 1.65 1.65 0 0 0-1-.6 1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-.6-1 1.65 1.65 0 0 0-1.1-.4H3a2 2 0 1 1 0-4h.09a1.65 1.65 0 0 0 1.1-.4 1.65 1.65 0 0 0 .6-1 1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-.6 1.65 1.65 0 0 0 .4-1.1V3a2 2 0 1 1 4 0v.09c0 .41.15.8.4 1.1.25.3.6.52 1 .6.6.1 1.2-.02 1.7-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9c.1.4.31.75.6 1 .3.25.69.4 1.1.4H21a2 2 0 1 1 0 4h-.09c-.41 0-.8.15-1.1.4-.29.25-.5.6-.6 1Z"/>',
        'database' => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14c0 1.7 4 3 9 3s9-1.3 9-3V5"/><path d="M3 12c0 1.7 4 3 9 3s9-1.3 9-3"/>',
    ];
    $path = $map[$name] ?? $map['shield'];
    return '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
};

?>
<style id="sige-permissoes-produto-pro-v121089">
/* SIGE SoftGenial v12.10.89 - Perfis e Permissões: Compliance Visual Integral
   Escopo visual apenas: não altera matriz, perfis, atribuição, remoção,
   auditoria, permissões, segurança, base de dados ou regras funcionais. */
.sige-perm-wrap{
    --perm-blue:var(--sg-theme-primary,var(--color-brand-500));
    --perm-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --perm-purple:var(--color-brand-500);
    --perm-purple-soft:var(--color-brand-50);
    --perm-ink:var(--color-black);
    --perm-muted:var(--color-slate-700);
    --perm-line:var(--color-ink-100);
    --perm-green:var(--color-success-500);
    --perm-red:var(--color-danger-500);
    --perm-amber:var(--color-warning-500);
    width:100%;
    max-width:none;
    margin:0;
    padding:0 0 28px;
    display:flex;
    flex-direction:column;
    gap:18px;
    color:var(--perm-ink);
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
}
.sige-perm-wrap *{box-sizing:border-box}
.sige-perm-wrap svg{width:18px;height:18px;display:block;stroke:currentColor!important;color:currentColor!important;fill:none!important}

/* HERO - padrão Painel Principal / Dashboard V2 MJS-grade */
.sige-perm-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-lg);
    padding:32px 34px;
    margin:0;
    display:grid;
    grid-template-columns:minmax(0,1.04fr) minmax(320px,.96fr);
    gap:22px;
    align-items:center;
    color:var(--perm-ink);
}
.sige-perm-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none}
.sige-perm-hero-main,.sige-perm-hero-panel{position:relative;z-index:1}
.sige-perm-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    color:var(--perm-blue);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
}
.sige-perm-hero h1{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.04em;
    font-family:inherit;
}
.sige-perm-hero p{
    max-width:720px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.sige-perm-chips{display:flex;flex-wrap:wrap;gap:10px;margin-top:22px}
.sige-perm-chips span{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    min-height:38px;
    padding:var(--space-2) var(--space-3);
    border-radius:var(--radius-pill);
    background:var(--color-white);
    border:1px solid var(--color-slate-100);
    color:var(--color-slate-700);
    font-size:12px;
    font-weight:700;
    box-shadow:var(--shadow-sm);
}
.sige-perm-chips svg{color:var(--perm-purple)}
.sige-perm-hero-panel{
    min-height:148px;
    border-radius:var(--radius-xl);
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:10px;
    border:1px solid rgba(92,64,187,.08);
}
.sige-perm-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12)}
.sige-perm-hero-panel>*{position:relative;z-index:1}
.sige-perm-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--perm-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em}
.sige-perm-panel-value{display:block;color:var(--color-ink-900);font-size:36px;line-height:1.05;font-weight:700;letter-spacing:-.045em}
.sige-perm-panel-text{display:block;max-width:330px;color:var(--color-slate-600);font-size:var(--fs-sm);line-height:1.55;font-weight:600}

/* KPI */
.sige-perm-kpis{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(190px,1fr));
    gap:var(--space-4);
    margin:0;
}
.sige-perm-kpi{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    gap:14px;
    min-height:104px;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:18px 20px;
    box-shadow:var(--shadow-md);
}
.sige-perm-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50))}
.sige-perm-kpi-icon{
    width:52px;height:52px;border-radius:var(--radius-lg);
    display:flex;align-items:center;justify-content:center;
    background:var(--kpi-soft,var(--color-brand-50));
    color:var(--kpi-color,var(--color-brand-500));
    position:relative;z-index:1;
}
.sige-perm-kpi-icon svg{width:24px;height:24px}
.sige-perm-kpi > div{position:relative;z-index:1}
.sige-perm-kpi .num{font-size:27px;font-weight:700;line-height:1;color:var(--color-black);letter-spacing:-.03em}
.sige-perm-kpi .lbl{font-size:12px;color:var(--color-slate-600);margin-top:7px;font-weight:600}
.sige-perm-kpi.roles{--kpi-color:var(--color-brand-500);--kpi-soft:var(--color-brand-50)}
.sige-perm-kpi.perms{--kpi-color:var(--sg-theme-primary,var(--color-brand-500));--kpi-soft:var(--color-info-50)}
.sige-perm-kpi.users{--kpi-color:var(--color-success-500);--kpi-soft:var(--color-success-50)}
.sige-perm-kpi.assigned{--kpi-color:var(--color-warning-500);--kpi-soft:var(--color-warning-50)}

/* alertas */
.sige-perm-alert{
    display:flex;
    align-items:flex-start;
    gap:10px;
    padding:13px 16px;
    border-radius:var(--radius-lg);
    margin:0;
    font-weight:700;
    font-size:var(--fs-sm);
    line-height:1.5;
    border:1px solid transparent;
    box-shadow:var(--shadow-sm);
}
.sige-perm-alert.success{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200)}
.sige-perm-alert.error{background:var(--color-danger-50);color:var(--color-danger-700);border-color:var(--color-danger-200)}

/* layout */
.sige-perm-grid{
    display:grid;
    grid-template-columns:minmax(250px,320px) minmax(0,1fr);
    gap:22px;
    align-items:start;
}
.sige-perm-card{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    box-shadow:var(--shadow-md);
    overflow:hidden;
}
.sige-perm-card-h{
    padding:20px 22px;
    border-bottom:1px solid var(--color-slate-100);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 64%,var(--color-slate-50) 100%);
}
.sige-perm-card-h h2{
    display:flex;
    align-items:center;
    gap:10px;
    margin:0;
    font-size:17px;
    color:var(--color-ink-500);
    font-weight:700;
    letter-spacing:-.03em;
}
.sige-perm-card-h h2 svg{
    width:38px;height:38px;padding:10px;border-radius:var(--radius-md);background:var(--color-brand-50);color:var(--perm-purple)!important;flex:0 0 auto;
}
.sige-perm-card-h p{margin:var(--space-2) 0 0;font-size:var(--fs-sm);color:var(--color-slate-500);line-height:1.55;font-weight:600}
.sige-perm-body{padding:22px}

/* perfis */
.sige-role-list{padding:var(--space-3);display:grid;gap:var(--space-2);max-height:72vh;overflow:auto}
.sige-role-item{
    display:block;
    text-decoration:none;
    color:var(--color-slate-900);
    border:1px solid var(--color-slate-100);
    border-radius:var(--radius-lg);
    padding:13px 14px;
    background:var(--color-slate-50);
    transition:transform .18s ease,background .18s ease,border-color .18s ease,box-shadow .18s ease;
}
.sige-role-item:hover{transform:translateX(2px);background:var(--color-white);border-color:var(--color-ink-100);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-role-item.active{background:var(--color-brand-50);border-color:var(--color-info-100);box-shadow:inset 4px 0 0 var(--perm-purple)}
.sige-role-item strong{display:block;font-size:var(--fs-base);color:var(--color-ink-500);font-weight:700}
.sige-role-item span{display:block;font-size:12px;color:var(--color-slate-500);margin-top:4px;line-height:1.4;font-weight:600}

/* matriz */
.sige-perm-group{
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-lg);
    overflow:hidden;
    margin-bottom:16px;
    background:var(--color-white);
}
.sige-perm-group-title{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:var(--space-3);
    background:var(--color-slate-50);
    padding:13px 16px;
    border-bottom:1px solid var(--color-ink-100);
    font-weight:700;
    color:var(--color-black);
}
.sige-perm-group-title small{font-weight:700;color:var(--color-slate-500)}
.sige-perm-row{
    display:grid;
    grid-template-columns:32px minmax(0,1fr) 96px;
    gap:var(--space-3);
    align-items:center;
    padding:var(--space-3) var(--space-4);
    border-bottom:1px solid var(--color-ink-50);
    cursor:pointer;
}
.sige-perm-row:last-child{border-bottom:0}
.sige-perm-row:hover{background:var(--color-white)}
.sige-perm-row input{width:18px;height:18px;accent-color:var(--sg-theme-primary,var(--color-brand-500));cursor:pointer}
.sige-perm-name{font-weight:700;color:var(--color-black);font-size:13px}
.sige-perm-desc{font-size:12px;color:var(--color-slate-500);margin-top:3px;line-height:1.4}
.sige-perm-desc code{
    background:var(--color-ink-50);
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-sm);
    padding:2px 6px;
    color:var(--color-slate-700);
    font-size:var(--fs-xs);
}
.sige-risk{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    border-radius:var(--radius-pill);
    padding:6px 10px;
    font-size:10px;
    font-weight:700;
    text-transform:uppercase;
    white-space:nowrap;
}
.sige-risk.baixo{background:var(--sg-theme-soft,var(--color-brand-50));color:var(--color-info-500)}
.sige-risk.medio{background:var(--color-warning-50);color:var(--color-danger-600)}
.sige-risk.alto{background:var(--color-danger-50);color:var(--color-danger-700)}
.sige-risk.critico{background:var(--color-black);color:var(--color-white)}
.sige-perm-actions{
    position:sticky;
    bottom:0;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(10px);
    border-top:1px solid var(--color-ink-100);
    padding:16px 22px;
    display:flex;
    justify-content:flex-end;
    gap:10px;
}

/* botões/forms */
.sige-perm-btn{
    min-height:42px;
    border:0;
    border-radius:var(--radius-md);
    padding:0 var(--space-4);
    font-weight:700;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    font-size:var(--fs-sm);
    font-family:inherit;
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease;
    white-space:nowrap;
}
.sige-perm-btn:hover{transform:translateY(-1px)}
.sige-perm-btn.primary{background:linear-gradient(135deg,var(--perm-blue),var(--perm-blue-dark));color:var(--color-white);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-perm-btn.primary:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-perm-btn.ghost{background:var(--color-white);color:var(--color-ink-900);border:1px solid var(--color-ink-100);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-perm-btn.ghost:hover{box-shadow:0 4px 16px rgba(15,23,42,.08)}
.sige-perm-row-actions form{min-width:0}
.sige-perm-row-actions select,.sige-perm-search{
    width:100%;
    min-width:0;
    min-height:44px;
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-md);
    padding:0 13px;
    font-size:var(--fs-sm);
    color:var(--color-ink-500);
    font-weight:600;
    background:var(--color-white);
    box-shadow:var(--shadow-sm);
    outline:none;
}
.sige-perm-row-actions select:focus,.sige-perm-search:focus{
    border-color:rgba(90,63,214,.55);
    box-shadow:var(--shadow-xs);
}

/* atribuição */
.sige-perm-info{
    display:flex;
    gap:14px;
    align-items:flex-start;
    background:var(--sg-theme-soft,var(--color-brand-50));
    border:1px solid var(--sg-theme-soft,var(--color-brand-50));
    border-radius:var(--radius-lg);
    padding:14px 16px;
    margin-bottom:16px;
    color:var(--sg-theme-primary-800,var(--color-ink-700));
    line-height:1.55;
    font-size:var(--fs-sm);
    font-weight:600;
}
.sige-perm-info-icon{
    flex:0 0 36px;
    width:36px;
    height:36px;
    border-radius:var(--radius-md);
    background:var(--sg-theme-soft,var(--color-brand-50));
    color:var(--color-info-600);
    display:flex;
    align-items:center;
    justify-content:center;
}
.sige-perm-info-icon svg{width:18px;height:18px}
.sige-perm-info em{font-style:italic;color:var(--color-info-600)}
.sige-perm-breakglass{display:flex;gap:var(--space-3);align-items:center;flex-wrap:wrap;}
.sige-perm-breakglass-input{flex:1;min-width:calc(var(--space-10) * 6.5);}
.sige-perm-breakglass-select{min-width:calc(var(--space-10) * 5.5);}
.sige-perm-toolbar{display:flex;justify-content:space-between;align-items:center;gap:var(--space-3);flex-wrap:wrap;margin-bottom:14px}
.sige-perm-search{flex:1;min-width:260px}
.sige-perm-counter{display:flex;gap:var(--space-2);flex-wrap:wrap}
.sige-perm-counter-pill{
    display:inline-flex;
    align-items:center;
    gap:6px;
    background:var(--color-ink-50);
    color:var(--color-slate-800);
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-pill);
    padding:var(--space-2) var(--space-3);
    font-size:12px;
    font-weight:700;
}
.sige-perm-counter-pill strong{color:var(--color-black);font-weight:700}
.sige-perm-counter-assigned{background:var(--color-success-50);color:var(--color-success-800);border-color:var(--color-success-200)}
.sige-perm-counter-assigned strong{color:var(--color-success-900)}
.sige-user-table-wrap{
    width:100%;
    overflow:auto;
    border:1px solid var(--color-slate-100);
    border-radius:var(--radius-lg);
    background:var(--color-white);
}
.sige-user-table{
    width:100%;
    min-width:860px;
    border-collapse:separate;
    border-spacing:0;
}
.sige-user-table th,.sige-user-table td{
    padding:12px 14px;
    border-bottom:1px solid var(--color-slate-100);
    text-align:left;
    font-size:var(--fs-sm);
    color:var(--color-ink-500);
    vertical-align:middle;
}
.sige-user-table tr:last-child td{border-bottom:0}
.sige-user-table th{
    background:var(--color-slate-50);
    color:var(--color-slate-700);
    font-size:var(--fs-xs);
    text-transform:uppercase;
    letter-spacing:.08em;
    font-weight:700;
}
.sige-user-table tbody tr:hover td{background:var(--color-white)}
.sige-perm-current{
    display:inline-flex;
    background:var(--color-brand-50);
    color:var(--color-brand-700);
    font-weight:700;
    padding:6px 10px;
    border-radius:var(--radius-pill);
    font-size:12px;
}
.sige-perm-row-actions{display:flex;gap:var(--space-2);align-items:center;flex-wrap:wrap}
.sige-perm-no-results{
    margin-top:16px;
    text-align:center;
    padding:var(--space-6);
    background:var(--color-slate-50);
    color:var(--color-slate-500);
    border-radius:var(--radius-md);
    font-size:var(--fs-sm);
    border:1px dashed var(--color-ink-200);
    font-weight:600;
}
.sige-perm-denied{
    margin:var(--space-10) auto;
    max-width:760px;
    background:var(--color-white);
    border:1px solid var(--color-danger-200);
    color:var(--color-danger-700);
    border-radius:var(--radius-xl);
    padding:28px;
    box-shadow:var(--shadow-lg);
}

/* responsividade */
@media(max-width:1180px){
    .sige-perm-hero{grid-template-columns:1fr;padding:26px 24px}
    .sige-perm-grid{grid-template-columns:1fr}
    .sige-role-list{max-height:none}
}
@media(max-width:780px){
    .sige-perm-hero h1{font-size:24px}
    .sige-perm-kpis{grid-template-columns:1fr}
    .sige-perm-row{grid-template-columns:32px minmax(0,1fr)}
    .sige-risk{grid-column:2;justify-self:flex-start}
    .sige-perm-actions{display:grid;grid-template-columns:1fr}
    .sige-perm-btn{width:100%}
    .sige-perm-body{padding:18px 16px}
    .sige-perm-card-h{padding:18px 16px}
    .sige-perm-info{align-items:flex-start}
    .sige-perm-toolbar{align-items:stretch}
    .sige-perm-search,.sige-perm-counter{width:100%}
}

/* v12.10.90 - UX PRO e efeito real */
.sige-perm-card-h-actions{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:var(--space-4);
}
.sige-perm-card-h-actions > div{min-width:0}
.sige-perm-card-h-actions .sige-perm-btn{flex:0 0 auto}
.sige-perm-matrix-toolbar{
    position:sticky;
    top:0;
    z-index:5;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:var(--space-3);
    flex-wrap:wrap;
    margin:-22px -22px 18px;
    padding:14px 22px;
    background:rgba(255,255,255,.94);
    backdrop-filter:blur(10px);
    border-bottom:1px solid var(--color-slate-100);
}
.sige-perm-search-wrap{
    flex:1;
    min-width:280px;
    position:relative;
    display:flex;
    align-items:center;
}
.sige-perm-search-wrap svg{
    position:absolute;
    left:13px;
    width:16px!important;
    height:16px!important;
    color:var(--color-slate-500)!important;
    pointer-events:none;
}
.sige-perm-search-wrap .sige-perm-search{
    padding-left:40px;
}
.sige-perm-effect-summary{
    display:flex;
    align-items:center;
    justify-content:flex-end;
    flex-wrap:wrap;
    gap:var(--space-2);
}
.sige-perm-effect-summary span{
    display:inline-flex;
    align-items:center;
    gap:7px;
    min-height:34px;
    padding:7px 10px;
    border-radius:var(--radius-pill);
    font-size:11.5px;
    font-weight:700;
    border:1px solid transparent;
}
.sige-perm-effect-ok{background:var(--color-success-50);color:var(--color-success-900);border-color:var(--color-success-200)!important}
.sige-perm-effect-plan{background:var(--color-slate-50);color:var(--color-slate-700);border-color:var(--color-ink-100)!important}
.sige-perm-group{
    border:1px solid var(--color-ink-100);
    border-radius:var(--radius-lg);
    overflow:hidden;
    margin-bottom:12px;
    background:var(--color-white);
}
.sige-perm-group[open]{
    box-shadow:var(--shadow-sm);
}
.sige-perm-group-title{
    cursor:pointer;
    list-style:none;
}
.sige-perm-group-title::-webkit-details-marker{display:none}
.sige-perm-group-title:after{
    content:"+";
    width:28px;
    height:28px;
    border-radius:var(--radius-sm);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:var(--color-white);
    border:1px solid var(--color-ink-100);
    color:var(--color-brand-500);
    font-weight:700;
    margin-left:auto;
}
.sige-perm-group[open] > .sige-perm-group-title:after{content:"−"}
.sige-perm-row.is-hidden{display:none}
.sige-perm-group.no-match{display:none}
.sige-perm-planned{
    margin-top:18px;
    border:1px dashed var(--color-ink-200);
    border-radius:var(--radius-lg);
    background:var(--color-slate-50);
    padding:0;
    overflow:hidden;
}
.sige-perm-planned summary{
    cursor:pointer;
    display:flex;
    align-items:center;
    gap:9px;
    padding:14px 16px;
    color:var(--color-slate-800);
    font-weight:700;
}
.sige-perm-planned p{
    margin:0;
    padding:0 16px 14px;
    color:var(--color-slate-500);
    font-size:var(--fs-sm);
    line-height:1.55;
    font-weight:600;
}
.sige-perm-planned-list{
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
    gap:var(--space-2);
    padding:0 var(--space-4) var(--space-4);
}
.sige-perm-planned-list div{
    display:flex;
    justify-content:space-between;
    gap:10px;
    background:var(--color-white);
    border:1px solid var(--color-slate-100);
    border-radius:var(--radius-md);
    padding:10px 12px;
    font-size:12px;
}
.sige-perm-planned-list strong{color:var(--color-ink-500)}
.sige-perm-planned-list span{color:var(--color-slate-500);font-weight:700}
@media(max-width:780px){
    .sige-perm-card-h-actions{display:grid;grid-template-columns:1fr}
    .sige-perm-matrix-toolbar{margin:-18px -16px 16px;padding:14px 16px}
    .sige-perm-search-wrap{min-width:100%}
    .sige-perm-effect-summary{justify-content:flex-start;width:100%}
}


/* v12.10.91 - catálogo operacional de perfis */
.sige-perm-current.deprecated{
    background:var(--color-warning-50);
    color:var(--color-warning-800);
    border:1px solid var(--color-warning-200);
}
.sige-perm-current.protegido{
    background:var(--color-slate-100);
    color:var(--color-slate-700);
    border:1px solid var(--color-slate-300);
}

</style>
<div class="sige-perm-wrap">
    <section class="sige-perm-hero" aria-label="Gestão de Permissões">
        <div class="sige-perm-hero-main">
            <div class="sige-perm-kicker"><?php echo $perm_icon('shield'); ?><span>Configurações</span></div>
            <h1>Gestão de Permissões</h1>
            <p>Configure perfis operacionais, controle a matriz de permissões e atribua utilizadores a funções SIGE com segurança e governação institucional.</p>
            <div class="sige-perm-chips">
                <span><?php echo $perm_icon('key'); ?> Matriz de permissões activa</span>
                <span><?php echo $perm_icon('users'); ?> Staff protegido</span>
                <span><?php echo $perm_icon('lock'); ?> Matriz centralizada</span>
                <span><?php echo $perm_icon('check'); ?> <?php echo esc_html((string)$__perm_effective_count); ?> permissões com efeito real</span>
            </div>
        </div>
        <aside class="sige-perm-hero-panel" aria-label="Estado das permissões">
            <div class="sige-perm-panel-label"><?php echo $perm_icon('grid'); ?><span>Matriz</span></div>
            <strong class="sige-perm-panel-value"><?php echo esc_html((string)count($roles)); ?></strong>
            <span class="sige-perm-panel-text">Perfil(is) operacionais disponíveis para configuração e atribuição a utilizadores staff.</span>
        </aside>
    </section>

    <?php if ($feedback): ?><div class="sige-perm-alert <?php echo esc_attr($feedback_type); ?>"><?php echo esc_html($feedback); ?></div><?php endif; ?>

    <div class="sige-perm-grid">
        <aside class="sige-perm-card">
            <div class="sige-perm-card-h"><h2><?php echo $perm_icon('grid'); ?> Perfis</h2><p>Seleccione um perfil para editar a matriz de permissões.</p></div>
            <div class="sige-role-list">
                <?php foreach ($roles as $role): ?>
                    <a class="sige-role-item <?php echo ((int)$role->id === $selected_role_id) ? 'active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=sige_permissoes&role_id=' . (int)$role->id)); ?>">
                        <strong><?php echo esc_html($role->nome); ?></strong>
                        <span><?php echo esc_html($role->descricao ?: $role->slug); ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        </aside>

        <section class="sige-perm-card">
            <div class="sige-perm-card-h sige-perm-card-h-actions">
                <div>
                    <h2><?php echo $perm_icon('key'); ?> Matriz de permissões<?php echo $selected_role ? ' · ' . esc_html($selected_role->nome) : ''; ?></h2>
                    <p>Alterações aqui afectam apenas esta escola; o template global do produto permanece preservado. A edição principal mostra apenas permissões já ligadas a guardas reais do sistema.</p>
                </div>
                <button class="sige-perm-btn primary" type="submit" form="sige-perm-matrix-form"><?php echo $perm_icon('save'); ?> Guardar</button>
            </div>
            <form id="sige-perm-matrix-form" method="post">
                <?php wp_nonce_field('sige_permissions_ui_action', '_sige_perm_nonce'); ?>
                <input type="hidden" name="sige_perm_action" value="save_role_permissions">
                <input type="hidden" name="role_id" value="<?php echo (int)$selected_role_id; ?>">
                <div class="sige-perm-body">
                    <div class="sige-perm-matrix-toolbar">
                        <div class="sige-perm-search-wrap"><?php echo $perm_icon('search'); ?><input type="search" id="sige-perm-matrix-search" class="sige-perm-search" placeholder="Pesquisar permissão, módulo ou código..." autocomplete="off"></div>
                        <div class="sige-perm-effect-summary">
                            <span class="sige-perm-effect-ok"><?php echo $perm_icon('check'); ?> <?php echo esc_html((string)$__perm_effective_count); ?> com efeito real</span>
                            <?php if ($__perm_planned_count > 0): ?><span class="sige-perm-effect-plan"><?php echo $perm_icon('info'); ?> <?php echo esc_html((string)$__perm_planned_count); ?> em preparação</span><?php endif; ?>
                        </div>
                    </div>

                    <?php $__perm_group_i = 0; foreach ($grouped as $modulo => $items): ?>
                        <details class="sige-perm-group" <?php echo $__perm_group_i === 0 ? 'open' : ''; ?>>
                            <summary class="sige-perm-group-title">
                                <span><?php echo esc_html(sige_perm_ui_group_label($modulo)); ?></span>
                                <small><?php echo count($items); ?> permissões · efeito real</small>
                            </summary>
                            <?php foreach ($items as $perm_key => $meta): $risk = $meta['risco'] ?? 'baixo'; ?>
                                <label class="sige-perm-row" data-perm-search="<?php echo esc_attr(mb_strtolower(sige_perm_ui_group_label($modulo) . ' ' . ($meta['nome'] ?? '') . ' ' . $perm_key . ' ' . ($meta['descricao'] ?? ''))); ?>">
                                    <input type="checkbox" name="permissions[]" value="<?php echo esc_attr($perm_key); ?>" <?php checked(isset($allowed_for_role[$perm_key])); ?>>
                                    <span><span class="sige-perm-name"><?php echo esc_html($meta['nome'] ?? $perm_key); ?></span><span class="sige-perm-desc"><code><?php echo esc_html($perm_key); ?></code> · <?php echo esc_html($meta['descricao'] ?? ''); ?></span></span>
                                    <span class="sige-risk <?php echo esc_attr($risk); ?>"><?php echo esc_html($risk); ?></span>
                                </label>
                            <?php endforeach; ?>
                        </details>
                    <?php $__perm_group_i++; endforeach; ?>

                    <?php if (!empty($grouped_planned)): ?>
                        <details class="sige-perm-planned">
                            <summary><?php echo $perm_icon('info'); ?> Permissões em preparação - não editáveis nesta build</summary>
                            <p>Estas permissões existem no registry para compatibilidade e evolução futura, mas ainda não foram ligadas a guards/acções reais no código actual. Por isso, não aparecem como permissões editáveis para evitar efeito decorativo.</p>
                            <div class="sige-perm-planned-list">
                                <?php foreach ($grouped_planned as $modulo => $items): ?>
                                    <div><strong><?php echo esc_html(sige_perm_ui_group_label($modulo)); ?></strong><span><?php echo count($items); ?> item(ns)</span></div>
                                <?php endforeach; ?>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
                <div class="sige-perm-actions">
                    <a class="sige-perm-btn ghost" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=sige_permissoes')); ?>"><?php echo $perm_icon('x'); ?> Cancelar</a>
                    <button class="sige-perm-btn primary" type="submit"><?php echo $perm_icon('save'); ?> Guardar permissões</button>
                </div>
            </form>
        </section>
    </div>


    <?php if ($__actor_protegido): ?>
    <section class="sige-perm-card">
        <div class="sige-perm-card-h">
            <h2><?php echo $perm_icon('shield'); ?> Recuperação segura de utilizador</h2>
            <p>Use apenas em incidente: quando uma conta de staff desaparece da lista ou fica como subscriber apesar de dever manter perfil SIGE.</p>
        </div>
        <div class="sige-perm-body">
            <div class="sige-perm-info">
                <div class="sige-perm-info-icon" aria-hidden="true"><?php echo $perm_icon('alert'); ?></div>
                <div>
                    <strong>Acção break-glass auditada.</strong>
                    Procura por ID, username ou e-mail; repõe o perfil SIGE activo e cria o espelho WordPress para perfis privilegiados. Só administrador WordPress protegido pode executar.
                </div>
            </div>
            <form method="post" class="sige-perm-breakglass">
                <?php wp_nonce_field('sige_permissions_ui_action', '_sige_perm_nonce'); ?>
                <input type="hidden" name="sige_perm_action" value="break_glass_restore_user_role">
                <input type="text" name="user_identifier" class="sige-perm-search sige-perm-breakglass-input" placeholder="E-mail, username ou ID do utilizador" required>
                <select name="role_id" class="sige-perm-breakglass-select" required>
                    <option value="">Perfil a recuperar...</option>
                    <?php foreach ($roles as $role):
                        $__rslug = sanitize_key((string)$role->slug);
                        $__is_priv = function_exists('sige_user_integrity_sige_role_is_privileged') ? sige_user_integrity_sige_role_is_privileged($__rslug) : in_array($__rslug, ['admin_ti','admin_escola','direccao_geral','director'], true);
                        if (!$__is_priv) continue;
                    ?>
                        <option value="<?php echo (int)$role->id; ?>"><?php echo esc_html($role->nome); ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="sige-perm-btn primary" type="submit" data-sige-titulo="Recuperar perfil" data-sige-confirm="Esta acção vai repor um perfil privilegiado e ficará auditada. Confirma que está a responder a um incidente real?" data-sige-confirmar="Recuperar"><?php echo $perm_icon('key'); ?> Recuperar</button>
            </form>
        </div>
    </section>
    <?php endif; ?>

    <section class="sige-perm-card">
        <div class="sige-perm-card-h">
            <h2><?php echo $perm_icon('users'); ?> Atribuição de perfis a utilizadores</h2>
            <p>Um perfil SIGE activo por utilizador nesta escola. Overrides individuais ficam preparados para fase posterior.</p>
        </div>
        <div class="sige-perm-body">
            <div class="sige-perm-info">
                <div class="sige-perm-info-icon" aria-hidden="true"><?php echo $perm_icon('info'); ?></div>
                <div>
                    <strong>Lista restrita a staff.</strong>
                    Esta área mostra utilizadores com WP role de staff ou com perfil SIGE activo nesta escola. Assim, uma conta que apareça como subscriber no WordPress, mas ainda tenha perfil SIGE activo, não desaparece da gestão de permissões.
                    Perfis legados, duplicados, portal ou apoio sem fluxo administrativo real ficam fora do catálogo para simplificar a experiência e evitar atribuições erradas.
                </div>
            </div>
            <div class="sige-perm-toolbar">
                <input type="search" id="sige-perm-user-search" class="sige-perm-search" placeholder="Pesquisar por nome ou e-mail..." autocomplete="off">
                <div class="sige-perm-counter">
                    <span class="sige-perm-counter-pill"><strong><?php echo (int)$__users_total_count; ?></strong> utilizadores staff</span>
                    <span class="sige-perm-counter-pill sige-perm-counter-assigned"><strong><?php echo (int)$__users_assigned_count; ?></strong> com perfil SIGE</span>
                </div>
            </div>
            <div class="sige-user-table-wrap">
            <table class="sige-user-table" id="sige-perm-user-table">
                <thead><tr><th>Utilizador</th><th>E-mail</th><th>Perfil SIGE actual</th><th style="min-width:280px;">Atribuir / Remover</th></tr></thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr class="sige-perm-empty-row">
                        <td colspan="4" style="text-align:center;padding:36px 18px;color:var(--color-slate-500);">
                            <div style="font-weight:700;color:var(--color-black);margin-bottom:6px;">Nenhum utilizador staff encontrado.</div>
                            Crie funcionários através do menu <em>Equipa &amp; Professores</em>; aparecerão aqui automaticamente.
                        </td>
                    </tr>
                <?php endif; ?>
                <?php foreach ($users as $user): $ur = $user_role_rows[$user->ID] ?? null; $ur_hidden = $ur && !isset($__operational_role_map[$ur->role_slug]); $__protegido = function_exists('sige_permissions_principal_protegido') && sige_permissions_principal_protegido((int)$user->ID); $__target_nivel = function_exists('sige_permissions_user_nivel') ? sige_permissions_user_nivel((int)$user->ID, (int)$escola_id) : 0; $__gerivel = $__actor_protegido || (!$__protegido && $__actor_nivel > $__target_nivel); ?>
                    <tr class="sige-perm-user-row" data-search="<?php echo esc_attr(mb_strtolower($user->display_name . ' ' . $user->user_email)); ?>">
                        <td><strong><?php echo esc_html($user->display_name); ?></strong></td>
                        <td><?php echo esc_html($user->user_email); ?></td>
                        <td><?php
                            if ($ur && $ur_hidden) {
                                echo '<span class="sige-perm-current deprecated">' . esc_html($ur->role_nome) . ' · fora do catálogo</span>';
                            } elseif ($ur) {
                                echo '<span class="sige-perm-current">' . esc_html($ur->role_nome) . '</span>';
                            } else {
                                echo '<span style="color:var(--color-slate-400);font-weight:700;">Sem perfil SIGE</span>';
                            }
                        ?></td>
                        <td>
                            <?php if ($__protegido): ?>
                            <div class="sige-perm-row-actions">
                                <span class="sige-perm-current protegido" title="Administrador WordPress protegido">🔒 Protegido</span>
                                <small>Conta de administração WordPress, não gerível aqui por desenho.</small>
                            </div>
                            <?php elseif (!$__gerivel): ?>
                            <div class="sige-perm-row-actions">
                                <span class="sige-perm-current protegido" title="Nível igual ou superior ao seu">Nível superior ou igual</span>
                                <small>Só pode gerir utilizadores de nível inferior ao seu.</small>
                            </div>
                            <?php else: ?>
                            <div class="sige-perm-row-actions">
                                <form method="post" style="display:flex;gap:8px;align-items:center;flex:1;">
                                    <?php wp_nonce_field('sige_permissions_ui_action', '_sige_perm_nonce'); ?>
                                    <input type="hidden" name="sige_perm_action" value="assign_user_role">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user->ID; ?>">
                                    <select name="role_id" required>
                                        <option value="">Seleccionar...</option>
                                        <?php foreach ($roles as $role):
                                            if (!$__actor_protegido) {
                                                $__rnivel = function_exists('sige_permissions_role_nivel') ? sige_permissions_role_nivel($role->slug) : 0;
                                                $__rgestao = function_exists('sige_permissions_role_concede_gestao') && sige_permissions_role_concede_gestao($role->slug);
                                                if ($__rgestao || $__rnivel >= $__actor_nivel) continue;
                                            }
                                        ?>
                                            <option value="<?php echo (int)$role->id; ?>" <?php selected($ur && $ur->role_slug === $role->slug); ?>><?php echo esc_html($role->nome); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="sige-perm-btn primary" type="submit" style="padding:9px 12px;"><?php echo $perm_icon('check'); ?> Aplicar</button>
                                </form>
                                <?php if ($ur): ?>
                                <form method="post">
                                    <?php wp_nonce_field('sige_permissions_ui_action', '_sige_perm_nonce'); ?>
                                    <input type="hidden" name="sige_perm_action" value="unassign_user_role">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$user->ID; ?>">
                                    <button class="sige-perm-btn ghost" type="submit" style="padding:9px 12px;" title="Remover perfil SIGE - utilizador volta ao comportamento legado" data-sige-titulo="Remover perfil SIGE" data-sige-confirm="O perfil SIGE deste utilizador será removido. Ele continua a funcionar pelo papel WordPress legado." data-sige-confirmar="Remover perfil"><?php echo $perm_icon('x'); ?> Remover</button>
                                </form>
                                <?php endif; ?>
                            </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
            <div id="sige-perm-no-results" class="sige-perm-no-results" hidden>
                Nenhum utilizador corresponde à pesquisa.
            </div>
        </div>
    </section>
    <?php if ($__actor_protegido): ?>
    <section class="sige-perm-card">
        <div class="sige-perm-card-h">
            <h2><?php echo $perm_icon('shield'); ?> Hierarquia de níveis</h2>
            <p>Defina o nível de cada perfil (0 a 100). Um gestor só atribui e gere perfis abaixo do seu nível. Apenas o administrador WordPress edita esta hierarquia.</p>
        </div>
        <form method="post" class="sige-perm-niveis-form">
            <?php wp_nonce_field('sige_permissions_ui_action', '_sige_perm_nonce'); ?>
            <input type="hidden" name="sige_perm_action" value="save_niveis">
            <?php foreach ($roles as $role): $__nv = function_exists('sige_permissions_role_nivel') ? sige_permissions_role_nivel($role->slug) : 0; ?>
            <div class="sige-perm-row-actions">
                <label for="nivel-<?php echo esc_attr($role->slug); ?>"><strong><?php echo esc_html($role->nome); ?></strong></label>
                <input type="number" id="nivel-<?php echo esc_attr($role->slug); ?>" name="niveis[<?php echo esc_attr($role->slug); ?>]" value="<?php echo (int)$__nv; ?>" min="0" max="100" step="1" inputmode="numeric">
            </div>
            <?php endforeach; ?>
            <div class="sige-perm-row-actions">
                <button class="sige-perm-btn primary" type="submit"><?php echo $perm_icon('check'); ?> Guardar níveis</button>
            </div>
        </form>
    </section>
    <?php endif; ?>
</div>
<script <?php echo sige_csp_script_attr(); ?>>
(function(){
    var input = document.getElementById('sige-perm-user-search');
    var table = document.getElementById('sige-perm-user-table');
    var emptyMsg = document.getElementById('sige-perm-no-results');
    if (!input || !table) return;
    var rows = table.querySelectorAll('tbody tr.sige-perm-user-row');
    function applyFilter() {
        var q = (input.value || '').toLowerCase().trim();
        var visibleCount = 0;
        rows.forEach(function(r){
            var hay = r.getAttribute('data-search') || '';
            var match = q === '' || hay.indexOf(q) !== -1;
            r.style.display = match ? '' : 'none';
            if (match) visibleCount++;
        });
        if (emptyMsg) emptyMsg.hidden = (visibleCount > 0 || q === '');
    }
    input.addEventListener('input', applyFilter);


    var matrixInput = document.getElementById('sige-perm-matrix-search');
    if (matrixInput) {
        var permRows = document.querySelectorAll('.sige-perm-row[data-perm-search]');
        var groups = document.querySelectorAll('details.sige-perm-group');
        matrixInput.addEventListener('input', function(){
            var q = (matrixInput.value || '').toLowerCase().trim();
            groups.forEach(function(g){ g.classList.remove('no-match'); });
            permRows.forEach(function(r){
                var hay = r.getAttribute('data-perm-search') || '';
                var match = q === '' || hay.indexOf(q) !== -1;
                r.classList.toggle('is-hidden', !match);
            });
            groups.forEach(function(g){
                var visible = g.querySelectorAll('.sige-perm-row[data-perm-search]:not(.is-hidden)').length;
                g.classList.toggle('no-match', visible === 0 && q !== '');
                if (q !== '' && visible > 0) g.open = true;
            });
        });
    }

})();
</script>
