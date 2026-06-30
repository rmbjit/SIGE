<?php
/**
 * SIGE SoftGenial - Gestão de Alunos
 *
 * v2.1 - Abril 2026
 * - Paleta alinhada com sg-* Design System (Navy/Amber)
 * - ABSPATH guard no topo, removido date_default_timezone_set
 * - Funções helper guardadas com function_exists
 * - Rotas query scoped por escola_id
 *
 * TEMPLATES DE IMPRESSÃO PRESERVADOS:
 * - imprimirBoletim(), imprimirDeclaracao()
 * - printSingleCard(), printBatchCards()
 * - exportarExcelProfissional()
 */

if (!defined('ABSPATH')) exit;

// Guard de acesso - consulta de alunos.
// v12.11.9.65 - Guarda/Portaria e Recepção podem consultar; criação/edição/remoção ficam em permissões próprias.
if (!sige_page_guard_allows(
    ['alunos.ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda']
)) {
    ?>
    <div class="sige-access-denied">
        <div class="sige-access-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <h2>Acesso Restrito</h2>
        <p>O seu perfil SIGE não tem permissão para aceder a este módulo.</p>
    </div>
    <style>
    .sige-access-denied{display:flex;flex-direction:column;align-items:center;justify-content:center;min-height:400px;text-align:center;padding:48px;background:var(--color-slate-50);border-radius:var(--radius-xl);border:1px solid var(--color-ink-100);font-family:'Inter',system-ui,sans-serif;}
    .sige-access-icon{width:80px;height:80px;border-radius:50%;background:var(--color-danger-100);display:flex;align-items:center;justify-content:center;margin-bottom:24px;}
    .sige-access-icon svg{width:40px;height:40px;stroke:var(--color-danger-500);}
    .sige-access-denied h2{font-size:1.5rem;font-weight:700;color:var(--color-black);margin:0 0 var(--space-2);}
    .sige-access-denied p{color:var(--color-slate-500);margin:0;}
    




/* v12.10.97 - Gestão de Alunos: acções em menu compacto para evitar sobreposição do nome */
.sige-alunos-page .sige-aluno-card{
    overflow:visible!important;
    padding-right:20px!important;
}
.sige-alunos-page .sige-aluno-info{
    padding-right:54px!important;
}
.sige-alunos-page .sige-aluno-nome{
    max-width:100%!important;
    overflow-wrap:anywhere!important;
}
.sige-alunos-page .sige-card-actions{
    position:absolute!important;
    top:18px!important;
    right:18px!important;
    display:block!important;
    opacity:1!important;
    z-index:80!important;
    pointer-events:auto!important;
}
.sige-alunos-page .sige-card-actions[open]{
    z-index:120!important;
}
.sige-alunos-page .sige-card-actions > summary{
    list-style:none!important;
}
.sige-alunos-page .sige-card-actions > summary::-webkit-details-marker{
    display:none!important;
}
.sige-alunos-page .sige-actions-trigger{
    width:40px!important;
    height:40px!important;
    border-radius:var(--radius-md)!important;
    border:1px solid rgba(226,232,240,.95)!important;
    background:var(--color-white)!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    cursor:pointer!important;
    box-shadow:var(--shadow-sm);
    transition:transform .18s ease,box-shadow .18s ease,background .18s ease,color .18s ease!important;
}
.sige-alunos-page .sige-actions-trigger:hover,
.sige-alunos-page .sige-card-actions[open] .sige-actions-trigger{
    transform:translateY(-1px)!important;
    background:var(--sg-theme-primary,var(--color-brand-500))!important;
    color:var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
.sige-alunos-page .sige-actions-trigger svg{
    width:20px!important;
    height:20px!important;
    stroke:currentColor!important;
}
.sige-alunos-page .sige-actions-menu{
    position:absolute!important;
    top:48px!important;
    right:0!important;
    min-width:228px!important;
    max-width:min(260px,calc(100vw - 56px))!important;
    padding:10px!important;
    border-radius:var(--radius-lg)!important;
    background:var(--color-white)!important;
    border:1px solid rgba(226,232,240,.95)!important;
    box-shadow:var(--shadow-lg);
    display:grid!important;
    grid-template-columns:repeat(3,1fr)!important;
    gap:var(--space-2)!important;
}
.sige-alunos-page .sige-actions-menu:before{
    content:""!important;
    position:absolute!important;
    top:-7px!important;
    right:14px!important;
    width:14px!important;
    height:14px!important;
    transform:rotate(45deg)!important;
    background:var(--color-white)!important;
    border-left:1px solid rgba(226,232,240,.95)!important;
    border-top:1px solid rgba(226,232,240,.95)!important;
}
.sige-alunos-page .sige-actions-menu .sige-btn-action{
    width:44px!important;
    height:44px!important;
    border-radius:var(--radius-md)!important;
    justify-self:center!important;
    opacity:1!important;
    background:var(--color-white)!important;
    box-shadow:none!important;
}
.sige-alunos-page .sige-actions-menu .sige-btn-action svg{
    width:18px!important;
    height:18px!important;
}
.sige-alunos-page .sige-actions-menu .btn-portal{
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    background:var(--sg-theme-soft,var(--color-brand-50))!important;
    border-color:var(--sg-theme-soft,var(--color-brand-50))!important;
}
@media (min-width:1000px) and (max-width:1500px){
    .sige-alunos-page .sige-aluno-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:22px!important;
    }
    .sige-alunos-page .sige-aluno-card{
        min-width:0!important;
    }
    .sige-alunos-page .sige-aluno-info{
        padding-right:54px!important;
    }
}
@media (max-width:760px){
    .sige-alunos-page .sige-aluno-info{
        padding-right:48px!important;
    }
    .sige-alunos-page .sige-actions-menu{
        grid-template-columns:repeat(3,1fr)!important;
        min-width:206px!important;
    }
}




/* v12.11.9.57 - Hero Fine-Tune 10/10 Mobile PRO
   Micro-refino final: afasta a ilustração do hero do CTA principal e harmoniza a grelha dos cards mobile. */
@media (max-width:760px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{padding:24px 20px 18px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-content{overflow:visible!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{
        padding-right:132px!important;
        min-height:160px!important;
        position:relative!important;
        z-index:2!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{
        max-width:212px!important;
        margin:0 0 10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{
        max-width:198px!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{
        right:12px!important;
        top:32px!important;
        width:128px!important;
        height:100px!important;
        opacity:.78!important;
        z-index:1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-school{
        right:0!important;
        bottom:0!important;
        width:124px!important;
        height:82px!important;
        color:var(--color-brand-300)!important;
        filter:drop-shadow(0 10px 18px rgba(96,78,231,.14))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-roof{left:20px!important;top:8px!important;width:86px!important;height:38px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-body{left:22px!important;bottom:0!important;width:92px!important;height:56px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-body:before{left:38px!important;width:22px!important;height:32px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-window{top:15px!important;left:13px!important;width:14px!important;height:13px!important;box-shadow:var(--shadow-lg);}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-flag{left:70px!important;top:-16px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-flag:after{width:27px!important;height:15px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{margin-top:18px!important;position:relative!important;z-index:3!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{position:relative!important;z-index:3!important;}

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        grid-template-columns:84px minmax(0,1fr)!important;
        gap:14px!important;
        padding:16px 16px 18px!important;
        min-height:154px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-foto{width:78px!important;height:78px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card:before{left:82px!important;top:80px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-info{padding-right:82px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome{margin:0 0 6px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{gap:10px!important;margin-top:12px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{
        min-height:44px!important;
        border-radius:var(--radius-md)!important;
    }
}
@media (max-width:420px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{padding-right:120px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{max-width:184px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{right:8px!important;top:34px!important;width:118px!important;height:94px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-school{width:114px!important;height:76px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-info{padding-right:72px!important;}
}




</style>
    <?php
    return;
}

// v12.11.9.65 - camada read-only para o perfil Guarda/Portaria.
// Consulta de alunos é permitida; criação, edição e remoção dependem de permissões próprias.
$sige_alunos_super = function_exists('sige_page_guard_is_real_admin') ? sige_page_guard_is_real_admin() : ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')));
$sige_alunos_can_create = $sige_alunos_super || (function_exists('sige_can') && sige_can('alunos.criar')) || current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_assistente');
$sige_alunos_can_edit   = $sige_alunos_super || (function_exists('sige_can') && sige_can('alunos.editar')) || current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_assistente');
$sige_alunos_can_delete = $sige_alunos_super || (function_exists('sige_can') && sige_can('alunos.apagar')) || current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_assistente');
$sige_alunos_read_only  = !$sige_alunos_can_create && !$sige_alunos_can_edit && !$sige_alunos_can_delete;

global $wpdb;
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$sige_alunos_active_role = (function_exists('sige_permissions_get_active_role') && get_current_user_id() > 0) ? sige_permissions_get_active_role((int)get_current_user_id(), (int)$escola_id) : null;
$sige_alunos_is_guarda = !$sige_alunos_super && (current_user_can('sige_guarda') || ($sige_alunos_active_role && !empty($sige_alunos_active_role->slug) && (string)$sige_alunos_active_role->slug === 'guarda'));
// v12.11.9.69 - defaults seguros; os escopos finos são calculados após o helper de permissões.
$sige_alunos_can_documents = false;
$sige_alunos_can_documents_view = false;
$sige_alunos_can_documents_emit = false;
$sige_alunos_can_export = false;
$sige_alunos_can_portal_access = false;
$sige_alunos_can_whatsapp = false;


// v12.11.9.68 - UX alinhada a permissões no módulo Alunos.
// Mantém consulta ampla de alunos, mas remove da interface mobile/tablet
// atalhos financeiros, configuração e navegação sem permissão efectiva.
$sige_alunos_can_any = static function(array $permissions, array $legacy_caps = []) use ($sige_alunos_super): bool {
    if ($sige_alunos_super) { return true; }
    if (function_exists('sige_page_guard_allows')) {
        try {
            // A matriz SIGE é soberana quando há perfil activo; não fazemos
            // fallback para WP caps legadas depois de uma negativa real.
            return (bool)sige_page_guard_allows($permissions, $legacy_caps);
        } catch (Throwable $e) {
            // Fallback apenas se o helper central falhar por motivo técnico.
        }
    }
    if (function_exists('sige_can')) {
        foreach ($permissions as $permission) {
            $permission = (string)$permission;
            if ($permission !== '' && sige_can($permission)) { return true; }
        }
    }
    foreach ($legacy_caps as $cap) {
        $cap = (string)$cap;
        if ($cap !== '' && current_user_can($cap)) { return true; }
    }
    return false;
};
$sige_alunos_can_finance_view = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['financeiro.ver','financeiro.dashboard_ver','financeiro.extractos_ver','financeiro.cobrancas_ver','financeiro.pagar'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao']
);
$sige_alunos_can_finance_pay = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['financeiro.pagar'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro']
);
$sige_alunos_can_portaria_nav = $sige_alunos_can_any(
    ['portaria.ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_recepcao','sige_guarda']
);
$sige_alunos_can_dashboard_nav = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['academico.dashboard_ver','financeiro.dashboard_ver','rh.equipe_ver','sistema.estado_ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_financeiro','sige_gestor_rh','sige_pedagogico','sige_admin_ti']
);
$sige_alunos_can_config_nav = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['sistema.estado_ver','configuracoes.ver'],
    ['administrator','sige_admin_ti']
);
$sige_alunos_can_more_nav = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    [
        'rh.equipe_ver',
        'academico.turmas_ver','academico.disciplinas_ver','academico.matriz_ver','academico.boletins_ver','academico.pautas_ver','academico.dec_ver','academico.pauta_final_ver','academico.actas_ver','academico.aprovar_notas','academico.auditoria_notas_ver','academico.estatisticas_ver',
        'financeiro.ver','financeiro.dashboard_ver','financeiro.cobrancas_ver','financeiro.extractos_ver','financeiro.relatorio_mensal_ver','financeiro.auditoria_ver','financeiro.lancamentos_ver','financeiro.despesas_ver','financeiro.centros_custo_ver','financeiro.lancar_mensalidades','financeiro.planos_ver','financeiro.servicos_ver','financeiro.configurar_precos',
        'jardim.ver','jardim.diario_ver','jardim.saude_ver','jardim.boletim_ver','jardim.relatorio_ver','jardim.presencas_ver',
        'transporte.ver','sistema.estado_ver','configuracoes.ver','usuarios.gerir_permissoes'
    ],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_financeiro','sige_gestor_rh','sige_pedagogico','sige_admin_ti','administrator']
);

// v12.11.9.69 - Deep Audit Alunos: acções sensíveis alinhadas à matriz real, não apenas ao facto de não ser Guarda.
// Separámos consulta documental de emissão/exportação. Um perfil read-only com alunos.ver não ganha por acidente
// acesso a BI digital, cartões, declarações ou Excel em lote.
$sige_alunos_can_documents_view = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['documentos.ver','documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.boletins_ver','academico.boletins_emitir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_pedagogico','sige_assistente','sige_recepcao']
);
$sige_alunos_can_documents_emit = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.boletins_emitir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_pedagogico']
);
$sige_alunos_can_documents = $sige_alunos_can_documents_emit;
$sige_alunos_can_export = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['documentos.emitir','documentos.reemitir','documentos.emitir_finais','academico.estatisticas_ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_pedagogico']
);
$sige_alunos_can_portal_access = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['portal.ver','portal.ver_documentos','alunos.contas_ver','alunos.contas_gerir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
);
$sige_alunos_can_whatsapp = !$sige_alunos_is_guarda && $sige_alunos_can_any(
    ['comunicacao.ver','comunicacao.enviar','whatsapp.ver','whatsapp.enviar','financeiro.extractos_ver','financeiro.cobrancas_ver','financeiro.pagar'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_financeiro','sige_assistente','sige_recepcao']
);

wp_enqueue_media();

// ========================================
// HELPERS (SEGURANÇA)
// ========================================
if (!function_exists('sige_table_exists')) {
    function sige_table_exists($table_name) {
        global $wpdb;
        $like = $wpdb->esc_like($table_name);
        return (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $like));
    }
}

if (!function_exists('sige_column_exists')) {
    function sige_column_exists($table_name, $column_name) {
        global $wpdb;
        if (!sige_table_exists($table_name)) return false;
        $col = $wpdb->esc_like($column_name);
        return (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table_name} LIKE %s", $col));
    }
}

// ========================================
// 1. DADOS E CONFIGURAÇÕES
// ========================================
$config = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", sige_get_escola_id()));
$escola_perfil = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;

$logo_final = SIGE_URL . 'assets/img/avatar-default.svg';
if ($config && !empty($config->logo_documentos_url)) { $logo_final = $config->logo_documentos_url; }
elseif ($config && !empty($config->logo_sistema_url)) { $logo_final = $config->logo_sistema_url; }
elseif ($escola_perfil && !empty($escola_perfil->logo_docs_url)) { $logo_final = $escola_perfil->logo_docs_url; }

$nome_escola = ($config && !empty($config->nome_escola)) ? $config->nome_escola : (($escola_perfil && !empty($escola_perfil->nome_escola)) ? $escola_perfil->nome_escola : 'ESCOLA GERAL');
$ano_lectivo = ($config && !empty($config->ano_lectivo)) ? (int)$config->ano_lectivo : 2026;

$sige_global_data = [
    'nome_escola' => $nome_escola,
    'logo_url' => $logo_final,
    'ano_lectivo' => $ano_lectivo
];
// v12.32.0 - Modelo de crachá da escola: config + catálogo para o cliente
// (pré-visualização e impressão partilham o mesmo registo de modelos).
$sige_global_data['cracha'] = function_exists('sige_cracha_config_for_js')
    ? sige_cracha_config_for_js()
    : ['config' => [], 'templates' => [], 'social' => []];
// Nonce de CSP para o <style> da pré-visualização (iframe herda a CSP da página).
$sige_global_data['csp_nonce'] = function_exists('sige_csp_nonce') ? sige_csp_nonce() : '';
// URL do registo de modelos (rede de segurança terciária: se por algum motivo o
// inline não correr, o cliente tenta carregá-lo sob demanda). Aponta para a pasta
// que existe no servidor (assets/views/, fallback assets/cracha/).
$sige_cracha_asset_rel = (defined('SIGE_PATH') && !is_file(SIGE_PATH . 'assets/cracha/sige-cracha-templates.js') && is_file(SIGE_PATH . 'assets/views/sige-cracha-templates.js'))
    ? 'assets/views/sige-cracha-templates.js'
    : 'assets/cracha/sige-cracha-templates.js';
$sige_global_data['cracha_asset'] = defined('SIGE_URL') ? (SIGE_URL . $sige_cracha_asset_rel . '?ver=' . (defined('SIGE_VERSION') ? SIGE_VERSION : '1')) : '';
// Quem pode mudar o modelo de crachá da escola (mostra/oculta o botão).
$sige_can_editar_cracha = (function_exists('sige_can') && sige_can('configuracoes.editar'))
    || (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user())
    || (function_exists('current_user_can') && (
        current_user_can('sige_director') || current_user_can('sige_admin_ti')
        || current_user_can('sige_gestor_rh') || current_user_can('sige_secretaria_geral')
    ));

// ========================================
// 1.1 TRANSPORTES (ROTAS) + CAMPOS NOVOS
// ========================================
$tbl_alunos = $wpdb->prefix . 'sige_alunos';
$tbl_rotas  = $wpdb->prefix . 'sige_transporte_rotas';
$tbl_rotas_legacy = $wpdb->prefix . 'sige_rotas';
$has_rotas_table     = sige_table_exists($tbl_rotas);
if (!$has_rotas_table && sige_table_exists($tbl_rotas_legacy)) {
    $tbl_rotas = $tbl_rotas_legacy;
    $has_rotas_table = true;
}
$has_rota_col        = sige_column_exists($tbl_alunos, 'rota_transporte_id');
$has_rota_escola_col = $has_rotas_table ? sige_column_exists($tbl_rotas, 'escola_id') : false;
$has_rota_nome_rota_col = $has_rotas_table ? sige_column_exists($tbl_rotas, 'nome_rota') : false;
$has_rota_nome_col = $has_rotas_table ? sige_column_exists($tbl_rotas, 'nome') : false;
$has_rota_preco_mensal_col = $has_rotas_table ? sige_column_exists($tbl_rotas, 'preco_mensal') : false;
$has_rota_valor_mensal_col = $has_rotas_table ? sige_column_exists($tbl_rotas, 'valor_mensal') : false;
$has_rota_ativo_col = $has_rotas_table ? sige_column_exists($tbl_rotas, 'ativo') : false;
$has_rota_activo_col = $has_rotas_table ? sige_column_exists($tbl_rotas, 'activo') : false;
$has_whatsapp_col    = sige_column_exists($tbl_alunos, 'whatsapp_notificacoes');
$has_mensalidade_col = sige_column_exists($tbl_alunos, 'mensalidade_base');
$has_regime_col      = sige_column_exists($tbl_alunos, 'regime_creche');

$creche_precos = ['semi_ate4' => 3000, 'semi_5anos' => 3200, 'int_ate4' => 4000, 'int_5anos' => 4200];
$_ccol = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='{$wpdb->prefix}sige_fin_configuracoes' AND COLUMN_NAME='creche_semi_ate4'");
if ((int)$_ccol > 0) {
    $cfg_creche = $wpdb->get_row($wpdb->prepare("SELECT creche_semi_ate4, creche_semi_5anos, creche_integral_ate4, creche_integral_5anos FROM {$wpdb->prefix}sige_fin_configuracoes WHERE escola_id = %d LIMIT 1", sige_get_escola_id()));
    if ($cfg_creche) {
        $creche_precos = [
            'semi_ate4'  => (float)($cfg_creche->creche_semi_ate4    ?: 3000),
            'semi_5anos' => (float)($cfg_creche->creche_semi_5anos   ?: 3200),
            'int_ate4'   => (float)($cfg_creche->creche_integral_ate4 ?: 4000),
            'int_5anos'  => (float)($cfg_creche->creche_integral_5anos ?: 4200),
        ];
    }
}

$has_temirmao_col = sige_column_exists($tbl_alunos, 'tem_irmao');
// [v13] Colunas / tabela novas
$has_regime_mensalidade_col = sige_column_exists($tbl_alunos, 'regime_mensalidade');
$tbl_ativ_extras = $wpdb->prefix . 'sige_aluno_atividades_extras';
$has_ativ_extras_table = sige_table_exists($tbl_ativ_extras);

// [v13] Listar actividades extras disponíveis (tipo = 'atividade_extra' OU 'livros')
$atividades_extras_disponiveis = [];
if (sige_table_exists($wpdb->prefix . 'sige_fin_servicos')) {
    $atividades_extras_disponiveis = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nome, valor, tipo, recorrente
         FROM {$wpdb->prefix}sige_fin_servicos
         WHERE escola_id = %d AND ativo = 1 AND tipo IN ('atividade_extra','livros')
         ORDER BY tipo ASC, nome ASC",
        $escola_id
    ));
}

$rotas = [];
if ($has_rotas_table) {
    // v12.11.9.69 - Deep Audit: compatibilidade segura com instalações que ainda tenham tabela/colunas legadas de rotas.
    $rota_nome_select  = $has_rota_nome_rota_col ? 'nome_rota' : ($has_rota_nome_col ? 'nome AS nome_rota' : "'' AS nome_rota");
    $rota_preco_select = $has_rota_preco_mensal_col ? 'preco_mensal' : ($has_rota_valor_mensal_col ? 'valor_mensal AS preco_mensal' : '0 AS preco_mensal');
    $rota_active_where = $has_rota_ativo_col ? ' AND ativo = 1' : ($has_rota_activo_col ? ' AND activo = 1' : '');
    $rota_school_where = $has_rota_escola_col ? ' AND escola_id = %d' : '';
    $rota_sql = "SELECT id, {$rota_nome_select}, {$rota_preco_select} FROM {$tbl_rotas} WHERE 1=1 {$rota_active_where} {$rota_school_where} ORDER BY nome_rota ASC";
    $rotas = $has_rota_escola_col ? $wpdb->get_results($wpdb->prepare($rota_sql, $escola_id)) : $wpdb->get_results($rota_sql);
}

// ========================================
// 2. BUSCAR TURMAS
// ========================================
$turmas = $wpdb->get_results($wpdb->prepare(
    "SELECT id, nome, classe FROM {$wpdb->prefix}sige_turmas WHERE ano_lectivo = %d AND escola_id = %d ORDER BY classe ASC, nome ASC",
    $ano_lectivo, sige_get_escola_id()
));

// ========================================
// 3. BUSCAR ALUNOS
// ========================================
$join_rota = '';
$select_rota = '';
if ($has_rotas_table && $has_rota_col) {
    $rota_scope_join = $has_rota_escola_col ? ' AND (r.escola_id = a.escola_id OR r.escola_id IS NULL) ' : '';
    $rota_nome_expr  = $has_rota_nome_rota_col ? 'r.nome_rota' : ($has_rota_nome_col ? 'r.nome' : "''");
    $rota_preco_expr = $has_rota_preco_mensal_col ? 'r.preco_mensal' : ($has_rota_valor_mensal_col ? 'r.valor_mensal' : '0');
    $join_rota   = " LEFT JOIN {$tbl_rotas} r ON a.rota_transporte_id = r.id {$rota_scope_join} ";
    $select_rota = ", {$rota_nome_expr} AS transporte_nome_rota, {$rota_preco_expr} AS transporte_preco_mensal ";
}

// ========================================
// 12.9.8: PAGINAÇÃO + FILTROS SERVER-SIDE
// ========================================
// Antes da v12.9.8 esta página fazia SELECT * de TODOS os alunos da escola,
// embutia tudo num JSON inline gigante (`var sigeTodosAlunos`) e renderizava
// um card HTML por aluno num único request. A 600 alunos isto começava a
// demorar; a 1700 a página ficava em branco (memory_limit, parser do browser,
// etc). v12.9.8 reescreve para:
//   - WHERE dinâmico com filtros validados (turma, status, search)
//   - LIMIT/OFFSET por página (50 alunos default)
//   - COUNT total separado para a UI de paginação
//   - JSON do aluno individual obtido via AJAX em editarAluno() - não inline
//   - Funções de exportação (Excel, cartões em lote) fazem fetch on-demand
$paged          = max(1, (int)($_GET['paged'] ?? 1));
$per_page       = 50;
$filtro_turma   = (int)($_GET['filtro_turma'] ?? 0);
$filtro_status  = sanitize_key((string)($_GET['filtro_status'] ?? ''));
$filtro_search  = sanitize_text_field((string)($_GET['filtro_search'] ?? ''));
$offset         = ($paged - 1) * $per_page;

$where_parts = ["a.escola_id = %d"];
$where_params = [$escola_id];
if ($filtro_turma > 0) {
    $where_parts[] = "m.turma_id = %d";
    $where_params[] = $filtro_turma;
}
$allowed_status = ['activo', 'suspenso', 'transferido', 'desistente'];
if (in_array($filtro_status, $allowed_status, true)) {
    $where_parts[] = "a.status = %s";
    $where_params[] = $filtro_status;
}
if ($filtro_search !== '') {
    $like = '%' . $wpdb->esc_like($filtro_search) . '%';
    // v12.11.9.69 - a pesquisa prometia aluno/processo/turma; agora turma e classe entram no WHERE real.
    $where_parts[] = "(a.nome_completo LIKE %s OR a.numero_processo LIKE %s OR a.contacto_encarregado LIKE %s OR a.telemovel_pai LIKE %s OR a.telemovel_mae LIKE %s OR t.nome LIKE %s OR t.classe LIKE %s)";
    $where_params[] = $like;
    $where_params[] = $like;
    $where_params[] = $like;
    $where_params[] = $like;
    $where_params[] = $like;
    $where_params[] = $like;
    $where_params[] = $like;
}
$where_sql = implode(' AND ', $where_parts);

// Total de alunos que correspondem ao filtro (sem paginação) - para a barra de paginação
$count_sql = "SELECT COUNT(DISTINCT a.id)
              FROM {$wpdb->prefix}sige_alunos a
              LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id AND m.ano_lectivo = %d AND m.escola_id = a.escola_id)
              LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = a.escola_id
              WHERE {$where_sql}";
$total_alunos = (int)$wpdb->get_var($wpdb->prepare(
    $count_sql,
    array_merge([$ano_lectivo], $where_params)
));
$total_pages = max(1, (int)ceil($total_alunos / $per_page));
if ($paged > $total_pages) $paged = $total_pages;
$offset = ($paged - 1) * $per_page;

// Pagina actual. v12.17.0: SELECT leve e explicito para reduzir payload/memoria
// sem alterar a edicao, que continua a carregar o aluno completo via AJAX.
$select_alunos_lista = function_exists('sige_alunos_perf_list_select_sql')
    ? sige_alunos_perf_list_select_sql($tbl_alunos, 'a')
    : 'a.*';
$alunos = $wpdb->get_results($wpdb->prepare(
    "SELECT {$select_alunos_lista}, t.nome as turma_nome, t.classe, m.turma_id {$select_rota}
     FROM {$wpdb->prefix}sige_alunos a
     LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id AND m.ano_lectivo = %d AND m.escola_id = a.escola_id)
     LEFT JOIN {$wpdb->prefix}sige_turmas t ON m.turma_id = t.id AND t.escola_id = a.escola_id
     {$join_rota}
     WHERE {$where_sql}
     ORDER BY t.classe ASC, t.nome ASC, a.nome_completo ASC
     LIMIT %d OFFSET %d",
    array_merge([$ano_lectivo], $where_params, [$per_page, $offset])
));

// [v13] Enriquecer cada aluno com a lista de IDs de actividades extras activas.
// Uma query única (IN) para evitar N+1.
if ($has_ativ_extras_table && !empty($alunos)) {
    $ids_alunos = array_map(function($a){ return (int)$a->id; }, $alunos);
    $in_placeholders = implode(',', array_fill(0, count($ids_alunos), '%d'));
    $sql_atv = "SELECT aluno_id, servico_id
                FROM {$tbl_ativ_extras}
                WHERE escola_id = %d AND ativo = 1 AND aluno_id IN ($in_placeholders)";
    $rows_atv = $wpdb->get_results($wpdb->prepare($sql_atv, array_merge([$escola_id], $ids_alunos)));
    $map_atv = [];
    foreach ($rows_atv as $r) {
        $map_atv[(int)$r->aluno_id][] = (int)$r->servico_id;
    }
    foreach ($alunos as &$_a) {
        $_a->atividades_extras_ids = $map_atv[(int)$_a->id] ?? [];
    }
    unset($_a);
}


// ========================================
// 3.0.1 SNAPSHOT FINANCEIRO LEVE POR ALUNO
// ========================================
// Feature operacional: mostrar no card do aluno o estado financeiro resumido
// sem alterar fórmulas, sem recalcular lançamentos e sem escrever na BD.
// Usa a expressão canónica sige_fin_saldo_sql(), quando disponível.
$saldo_financeiro_por_aluno = [];
if ($sige_alunos_can_finance_view && !empty($alunos) && sige_table_exists($wpdb->prefix . 'sige_fin_lancamentos')) {
    $ids_alunos_saldo = array_values(array_unique(array_map(function($a){ return (int)$a->id; }, $alunos)));
    if (!empty($ids_alunos_saldo)) {
        $in_saldo = implode(',', array_fill(0, count($ids_alunos_saldo), '%d'));
        $_saldo_expr = function_exists('sige_fin_saldo_sql')
            ? sige_fin_saldo_sql('l')
            : "GREATEST(COALESCE(l.valor_original,0)+COALESCE(l.valor_transporte,0)+COALESCE(l.valor_extras,0)+COALESCE(NULLIF(l.valor_multa_cobrada,0),l.valor_multa,0)-COALESCE(l.valor_desconto,0)-COALESCE(l.valor_desconto_especial,0)-COALESCE(l.valor_pago,0),0)";

        $rows_saldo = $wpdb->get_results($wpdb->prepare(
            "SELECT
                l.aluno_id,
                SUM(CASE WHEN LOWER(l.status) IN ('pendente','parcial') THEN {$_saldo_expr} ELSE 0 END) AS divida_aberta,
                SUM(CASE WHEN LOWER(l.status) = 'em_plano' THEN {$_saldo_expr} ELSE 0 END) AS divida_em_plano,
                SUM(CASE WHEN LOWER(l.status) = 'parcial' THEN 1 ELSE 0 END) AS qtd_parcial,
                SUM(CASE WHEN LOWER(l.status) = 'pendente' THEN 1 ELSE 0 END) AS qtd_pendente
             FROM {$wpdb->prefix}sige_fin_lancamentos l
             WHERE l.escola_id = %d
               AND l.aluno_id IN ($in_saldo)
               AND LOWER(l.status) IN ('pendente','parcial','em_plano')
             GROUP BY l.aluno_id",
            array_merge([$escola_id], $ids_alunos_saldo)
        ));

        foreach ($rows_saldo as $r) {
            $saldo_financeiro_por_aluno[(int)$r->aluno_id] = [
                'divida'       => max(0, (float)$r->divida_aberta),
                'em_plano'     => max(0, (float)$r->divida_em_plano),
                'qtd_parcial'  => (int)$r->qtd_parcial,
                'qtd_pendente' => (int)$r->qtd_pendente,
                'credito'      => 0.0,
            ];
        }

        // Créditos aprovados/disponíveis, quando a tabela existir.
        $tbl_creditos = $wpdb->prefix . 'sige_fin_creditos';
        if (sige_table_exists($tbl_creditos)) {
            $rows_creditos = $wpdb->get_results($wpdb->prepare(
                "SELECT aluno_id, SUM(GREATEST(COALESCE(valor_disponivel,0),0)) AS credito_disponivel
                 FROM {$tbl_creditos}
                 WHERE escola_id = %d
                   AND aluno_id IN ($in_saldo)
                   AND GREATEST(COALESCE(valor_disponivel,0),0) > 0
                   AND LOWER(COALESCE(status,'')) NOT IN ('cancelado','anulado','usado','esgotado')
                 GROUP BY aluno_id",
                array_merge([$escola_id], $ids_alunos_saldo)
            ));
            foreach ($rows_creditos as $c) {
                $aid = (int)$c->aluno_id;
                if (!isset($saldo_financeiro_por_aluno[$aid])) {
                    $saldo_financeiro_por_aluno[$aid] = [
                        'divida' => 0.0, 'em_plano' => 0.0, 'qtd_parcial' => 0, 'qtd_pendente' => 0, 'credito' => 0.0,
                    ];
                }
                $saldo_financeiro_por_aluno[$aid]['credito'] = max(0, (float)$c->credito_disponivel);
            }
        }
    }
}

// [12.9.8] Removido: $todos_alunos_json = json_encode($alunos);
// Antes embutíamos toda a colecção paginada no JavaScript. As funções que
// usavam essa variável (exportarExcelProfissional, printBatchCards) agora
// fazem fetch via AJAX endpoint sige_get_alunos_export quando o utilizador
// clica nos botões - só puxa a fronteira de alunos que precisa, e sem
// inflacionar o HTML da página.

// ========================================
// 3.1 LÓGICA DE ANIVERSARIANTES
// ========================================
$hoje_dia = wp_date('d');
$hoje_mes = wp_date('m');
$aniversariantes_hoje = [];
$aniversariantes_mes  = [];

foreach ($alunos as $a) {
    if (!empty($a->data_nascimento)) {
        $ts = strtotime($a->data_nascimento);
        if ($ts) {
            $dia_n = wp_date('d', $ts);
            $mes_n = wp_date('m', $ts);
            if ($dia_n == $hoje_dia && $mes_n == $hoje_mes) {
                $aniversariantes_hoje[] = $a->nome_completo;
            }
            if ($mes_n == $hoje_mes) {
                $aniversariantes_mes[] = $a->nome_completo;
            }
        }
    }
}

// ========================================
// 4. ESTATÍSTICAS
// ========================================
$stats = ['total' => 0, 'activos' => 0, 'suspensos' => 0, 'irmaos' => 0, 'm' => 0, 'f' => 0];

foreach($alunos as $a) {
    $stats['total']++;
    if(!empty($a->tem_desconto_irmao) && (int)$a->tem_desconto_irmao === 1) $stats['irmaos']++;
    if(isset($a->genero) && $a->genero === 'M') $stats['m']++;
    if(isset($a->genero) && $a->genero === 'F') $stats['f']++;
    $st = !empty($a->status) ? strtolower($a->status) : 'activo';
    if($st == 'activo') $stats['activos']++;
    if($st == 'suspenso') $stats['suspensos']++;
}
?>

<?php echo function_exists('sige_alunos_perf_defer_script_tag') ? sige_alunos_perf_defer_script_tag('exceljs') : sige_cdn_script("exceljs"); ?>
<?php echo function_exists('sige_alunos_perf_defer_script_tag') ? sige_alunos_perf_defer_script_tag('filesaver') : sige_cdn_script("filesaver"); ?>
<?php echo function_exists('sige_alunos_perf_defer_script_tag') ? sige_alunos_perf_defer_script_tag('qrious') : sige_cdn_script("qrious"); ?>
<script <?php echo sige_csp_script_attr(); ?>>
/* QR local: gera o codigo QR no proprio navegador (sem servico externo). O numero
   de processo do aluno nunca sai do dispositivo. Se a biblioteca nao estiver
   disponivel, devolve string vazia -> o construtor do cartao mostra o numero de
   processo como texto legivel (o porteiro valida pela entrada manual). */
function sigeQrDataUri(text){
    try {
        if (typeof QRious !== 'undefined') {
            var q = new QRious({ value: String(text || ''), size: 100, level: 'M' });
            return q.toDataURL('image/png');
        }
    } catch (e) {}
    return '';
}
</script>

<?php
// v12.32.3 - Registo de modelos do crachá entregue INLINE a partir do filesystem.
// Entrega à prova de falhas: não depende de pedido HTTP/enqueue/CDN/cache.
// LIÇÃO (CloudPanel e afins): pipelines de update podem NÃO criar pastas novas,
// só substituir ficheiros em pastas existentes. Por isso o registo vive agora em
// assets/views/ (pasta já existente e que deploya de forma fiável); mantém-se a
// procura na antiga assets/cracha/ para instalações manuais já feitas.
$sige_cracha_tpl_file = '';
if (defined('SIGE_PATH')) {
    // assets/cracha/ é a localização validada; assets/views/ fica como tolerância.
    foreach (['assets/cracha/sige-cracha-templates.js', 'assets/views/sige-cracha-templates.js'] as $sige_cracha_rel) {
        if (is_file(SIGE_PATH . $sige_cracha_rel)) { $sige_cracha_tpl_file = SIGE_PATH . $sige_cracha_rel; break; }
    }
}
if ($sige_cracha_tpl_file) {
    echo '<script ' . sige_csp_script_attr() . ">\n";
    readfile($sige_cracha_tpl_file);
    echo "\n</script>\n";
}
?>

<style>
/* ========================================
   SIGE ALUNOS - Design System v2.1
   Aligned with sg-* (Navy/Amber)
   ======================================== */

/* Google Fonts already loaded by style.css */

:root {
    --sige-font-display: 'Plus Jakarta Sans', system-ui, sans-serif;
    --sige-font-body: 'Inter', system-ui, sans-serif;
    
    --sige-navy: var(--color-info-900);
    --sige-navy-light: var(--sg-theme-primary-800,var(--color-ink-700));
    --sige-primary: var(--sg-theme-primary,var(--color-brand-500));
    --sige-primary-light: var(--color-ink-600);
    --sige-primary-dark: var(--color-info-700);
    
    --sige-slate-50: var(--color-slate-50);
    --sige-slate-100: var(--color-ink-50);
    --sige-slate-200: var(--color-ink-100);
    --sige-slate-300: var(--color-ink-200);
    --sige-slate-400: var(--color-slate-400);
    --sige-slate-500: var(--color-slate-500);
    --sige-slate-600: var(--color-slate-700);
    --sige-slate-700: var(--color-slate-800);
    --sige-slate-800: var(--color-ink-900);
    --sige-slate-900: var(--color-black);
    
    --sige-success: var(--color-success-700);
    --sige-success-light: var(--color-success-100);
    --sige-warning: var(--color-warning-500);
    --sige-warning-light: var(--color-warning-100);
    --sige-error: var(--color-danger-500);
    --sige-error-light: var(--color-danger-50);
    --sige-info: var(--sg-theme-primary,var(--color-brand-500));
    --sige-info-light: var(--sg-theme-soft,var(--color-brand-50));
    
    --sige-amber: var(--color-warning-500);
    --sige-rose: var(--color-danger-500);
    --sige-purple: var(--color-brand-700);
    
    --sige-shadow-sm: 0 1px 2px rgba(13,18,89,0.04);
    --sige-shadow: 0 4px 12px rgba(13,18,89,0.08);
    --sige-shadow-lg: 0 12px 32px rgba(13,18,89,0.10);
    --sige-shadow-xl: 0 20px 48px rgba(13,18,89,0.14);
    
    --sige-radius: 12px;
    --sige-radius-lg: 16px;
    --sige-radius-xl: 20px;
    --sige-radius-2xl: 24px;
}

/* Reset */
.sige-alunos-page {
    font-family: var(--sige-font-body);
    color: var(--sige-slate-800);
    background: var(--sige-slate-50);
    min-height: 100vh;
    padding:var(--space-6);
}

.sige-alunos-page * {
    box-sizing: border-box;
}

/* Animations */
@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(16px); }
    to { opacity: 1; transform: translateY(0); }
}

@keyframes modalSlideIn {
    from { opacity: 0; transform: translateY(-14px) scale(0.98); }
    to { opacity: 1; transform: translateY(0) scale(1); }
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

@keyframes bounce {
    0%, 100% { transform: translateY(0); }
    50% { transform: translateY(-5px); }
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.6; }
}

/* Toast */
.sige-toast {
    position: fixed;
    top: 32px;
    right: 20px;
    padding:var(--space-4) var(--space-6);
    border-radius: var(--sige-radius);
    font-weight:600;
    font-size:var(--fs-base);
    z-index: 99999;
    display: flex;
    align-items: center;
    gap: 10px;
    box-shadow:var(--shadow-xs);
    animation: fadeInUp 0.3s ease, fadeOut 0.3s ease 3.7s forwards;
}
.sige-toast.success { background: var(--sige-success); color: white; }
.sige-toast.error { background: var(--sige-error); color: white; }
.sige-toast.info { background: var(--sige-info); color: white; }
@keyframes fadeOut { to { opacity: 0; transform: translateY(-20px); } }

/* ========================================
   BIRTHDAY PANEL
   ======================================== */
.sige-birthday-panel {
    background: linear-gradient(135deg, var(--color-brand-500) 0%, var(--sige-primary) 50%, var(--color-info-400) 100%);
    color: var(--color-white);
    padding: 20px 28px;
    border-radius: var(--sige-radius-xl);
    margin-bottom: 24px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap:var(--space-5);
    box-shadow:var(--shadow-xs);
    animation: fadeInUp 0.5s ease-out;
    flex-wrap: wrap;
}

.sige-birthday-info {
    display: flex;
    align-items: center;
    gap:var(--space-4);
}

.sige-birthday-icon {
    width: 56px;
    height: 56px;
    background: rgba(255,255,255,0.2);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    animation: bounce 2s infinite;
}

.sige-birthday-icon svg {
    width: 28px;
    height: 28px;
    stroke: var(--color-white);
}

.sige-birthday-text h3 {
    font-family: var(--sige-font-display);
    font-size: 1.1rem;
    font-weight:600;
    margin:0 0 var(--space-1);
}

.sige-birthday-text p {
    margin: 0;
    font-size: 0.85rem;
    opacity: 0.9;
}

.sige-birthday-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.sige-btn-bday {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding: 10px 18px;
    border-radius: var(--sige-radius);
    background: rgba(255,255,255,0.18);
    border: 1px solid rgba(255,255,255,0.25);
    color: var(--color-white);
    font-weight:600;
    font-size: 0.8rem;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
}

.sige-btn-bday svg {
    width: 16px;
    height: 16px;
}

.sige-btn-bday:hover {
    background: rgba(255,255,255,0.28);
    transform: translateY(-1px);
}

.sige-btn-bday.secondary {
    background: rgba(255,255,255,0.1);
}

/* ========================================
   HERO HEADER
   ======================================== */
.sige-hero {
    position: relative;
    overflow: hidden;
    border-radius: var(--sige-radius-2xl);
    padding:var(--space-8);
    margin-bottom: 24px;
    background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-navy-light) 50%, var(--sige-primary-dark) 100%);
    color: var(--color-white);
    box-shadow:var(--shadow-xs);
    animation: fadeInUp 0.5s ease-out 0.05s both;
}

.sige-hero::before,
.sige-hero::after {
    content: "";
    position: absolute;
    border-radius: 50%;
    pointer-events: none;
}

.sige-hero::before {
    width: 350px;
    height: 350px;
    right: -120px;
    top: -120px;
    background: radial-gradient(circle, rgba(63, 81, 181, 0.25) 0%, transparent 70%);
}

.sige-hero::after {
    width: 250px;
    height: 250px;
    left: -80px;
    bottom: -100px;
    background: radial-gradient(circle, rgba(20, 184, 166, 0.2) 0%, transparent 70%);
}

.sige-hero-content {
    position: relative;
    z-index: 1;
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap:var(--space-5);
    flex-wrap: wrap;
}

.sige-hero-text {
    flex: 1;
    min-width: 280px;
}

.sige-hero-kicker {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding: 8px 14px;
    border-radius:var(--radius-pill);
    background: rgba(255,255,255,0.12);
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 12px;
}

.sige-hero-kicker svg {
    width: 16px;
    height: 16px;
}

.sige-hero h1 {
    font-family: var(--sige-font-display);
    font-size: clamp(1.75rem, 3vw, 2.25rem);
    font-weight:700;
    line-height: 1.1;
    letter-spacing: -0.02em;
    margin:0 0 var(--space-2);
    color: var(--color-white);
}

.sige-hero-subtitle {
    font-size: 0.95rem;
    color: rgba(255,255,255,0.8);
    margin:0 0 var(--space-4);
}

.sige-hero-meta {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding: 8px 14px;
    border-radius:var(--radius-pill);
    background: rgba(255,255,255,0.1);
    border: 1px solid rgba(255,255,255,0.12);
    font-size: 0.75rem;
    font-weight:600;
}

.sige-hero-meta svg {
    width: 14px;
    height: 14px;
    opacity: 0.8;
}

.sige-btn-hero {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    padding: 14px 28px;
    border-radius: var(--sige-radius);
    background: var(--color-white);
    color: var(--sige-navy);
    font-family: var(--sige-font-body);
    font-weight:700;
    font-size: 0.875rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    border: none;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
    box-shadow:var(--shadow-xs);
}

.sige-btn-hero svg {
    width: 18px;
    height: 18px;
}

.sige-btn-hero:hover {
    transform: translateY(-2px);
    box-shadow:var(--shadow-sm);
}

/* ========================================
   STATS GRID
   ======================================== */
.sige-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(170px, 1fr));
    gap:var(--space-4);
    margin-bottom: 24px;
    animation: fadeInUp 0.5s ease-out 0.1s both;
}

.sige-stat-card {
    background: var(--color-white);
    border-radius: var(--sige-radius-lg);
    padding:var(--space-5);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    display: flex;
    align-items: center;
    gap: 14px;
    transition: all var(--duration-normal) ease;
}

.sige-stat-card:hover {
    transform: translateY(-3px);
    box-shadow:var(--shadow-xs);
}

.sige-stat-card.highlight {
    background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-navy-light) 100%);
    color: var(--color-white);
    border: none;
}

.sige-stat-icon {
    width: 44px;
    height: 44px;
    border-radius: var(--sige-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.sige-stat-icon svg {
    width: 22px;
    height: 22px;
}

.sige-stat-icon.icon-success { background: rgba(16, 185, 129, 0.1); border-left: 3px solid var(--sige-success); }
.sige-stat-icon.icon-success svg { stroke: var(--sige-success); }

.sige-stat-icon.icon-error { background: rgba(239, 68, 68, 0.1); border-left: 3px solid var(--sige-error); }
.sige-stat-icon.icon-error svg { stroke: var(--sige-error); }

.sige-stat-icon.icon-primary { background: rgba(63, 81, 181, 0.1); border-left: 3px solid var(--sige-primary); }
.sige-stat-icon.icon-primary svg { stroke: var(--sige-primary); }

.sige-stat-icon.icon-info { background: rgba(59, 130, 246, 0.1); border-left: 3px solid var(--sige-info); }
.sige-stat-icon.icon-info svg { stroke: var(--sige-info); }

.sige-stat-icon.icon-amber { background: rgba(245, 158, 11, 0.1); border-left: 3px solid var(--sige-amber); }
.sige-stat-icon.icon-amber svg { stroke: var(--sige-amber); }

.sige-stat-card.highlight .sige-stat-icon {
    background: rgba(255,255,255,0.15);
    border-left: none;
}
.sige-stat-card.highlight .sige-stat-icon svg {
    stroke: var(--color-white);
}

.sige-stat-info {
    flex: 1;
    min-width: 0;
}

.sige-stat-label {
    font-size: 0.7rem;
    font-weight:600;
    color: var(--sige-slate-500);
    margin-bottom: 2px;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.sige-stat-card.highlight .sige-stat-label {
    color: rgba(255,255,255,0.7);
}

.sige-stat-value {
    font-family: var(--sige-font-display);
    font-size: 1.5rem;
    font-weight:700;
    color: var(--sige-slate-900);
}

.sige-stat-card.highlight .sige-stat-value {
    color: var(--color-white);
}

/* ========================================
   TOOLBAR
   ======================================== */
.sige-toolbar {
    background: var(--color-white);
    border-radius: var(--sige-radius-lg);
    padding:var(--space-4) var(--space-5);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    display: flex;
    gap: 14px;
    align-items: center;
    flex-wrap: wrap;
    margin-bottom: 24px;
    animation: fadeInUp 0.5s ease-out 0.15s both;
}

.sige-search-box {
    flex: 1;
    min-width: 250px;
    position: relative;
}

.sige-search-box input {
    width: 100%;
    height: 44px;
    padding: 0 16px 0 44px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    color: var(--sige-slate-800);
    transition: all var(--duration-normal) ease;
}

.sige-search-box input::placeholder {
    color: var(--sige-slate-400);
}

.sige-search-box input:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}

.sige-search-box svg {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    width: 18px;
    height: 18px;
    stroke: var(--sige-slate-400);
    pointer-events: none;
}

.sige-filter-select {
    height: 44px;
    padding: 0 36px 0 14px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    font-weight:600;
    color: var(--sige-slate-700);
    background: var(--color-white) url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 24 24' fill='none' stroke='%2364748b' stroke-width='2'%3E%3Cpath d='m6 9 6 6 6-6'/%3E%3C/svg%3E") no-repeat right 12px center;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
    appearance: none;
}

.sige-filter-select:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}

.sige-btn-toolbar {
    height: 44px;
    padding: 0 18px;
    border-radius: var(--sige-radius);
    border: 1px solid var(--sige-slate-200);
    background: var(--color-white);
    font-family: var(--sige-font-body);
    font-weight:600;
    font-size: 0.8rem;
    color: var(--sige-slate-600);
    cursor: pointer;
    display: flex;
    align-items: center;
    gap:var(--space-2);
    transition: all var(--duration-normal) ease;
}

.sige-btn-toolbar svg {
    width: 16px;
    height: 16px;
}

.sige-btn-toolbar:hover {
    background: var(--sige-slate-50);
    border-color: var(--sige-primary);
    color: var(--sige-primary);
}

.sige-btn-toolbar.btn-excel {
    color: var(--sige-success);
    border-color: var(--sige-success);
}

.sige-btn-toolbar.btn-excel:hover {
    background: var(--sige-success-light);
}

.sige-btn-toolbar.btn-cards {
    color: var(--sige-purple);
    border-color: var(--sige-purple);
}

.sige-btn-toolbar.btn-cards:hover {
    background: rgba(168, 85, 247, 0.1);
}

/* ========================================
   ALUNO GRID
   ======================================== */
.sige-aluno-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(360px, 1fr));
    gap:var(--space-5);
    animation: fadeInUp 0.5s ease-out 0.2s both;
}

/* ========================================
   ALUNO CARD
   ======================================== */
.sige-aluno-card {
    background: var(--color-white);
    border-radius: var(--sige-radius-lg);
    padding:var(--space-5);
    border: 1px solid var(--sige-slate-100);
    box-shadow:var(--shadow-xs);
    display: flex;
    gap:var(--space-4);
    align-items: flex-start;
    position: relative;
    transition: all 0.25s ease;
}

.sige-aluno-card:hover {
    transform: translateY(-4px);
    box-shadow:var(--shadow-xs);
    border-color: var(--sige-primary-light);
}

.sige-aluno-foto {
    width: 72px;
    height: 72px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--sige-slate-100);
    flex-shrink: 0;
    transition: border-color var(--duration-normal) ease;
}

.sige-aluno-card:hover .sige-aluno-foto {
    border-color: var(--sige-primary-light);
}

.sige-aluno-info {
    flex: 1;
    min-width: 0;
}

.sige-aluno-nome {
    font-family: var(--sige-font-display);
    font-size: 1rem;
    font-weight:600;
    color: var(--sige-navy);
    margin: 0 0 6px;
    display: flex;
    align-items: center;
    gap:var(--space-2);
    flex-wrap: wrap;
}

.sige-badge {
    display: inline-flex;
    align-items: center;
    padding: 3px 8px;
    border-radius:var(--radius-xs);
    font-size: 0.6rem;
    font-weight:700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
}

.sige-badge-irmao {
    background: linear-gradient(135deg, var(--color-warning-100), var(--color-warning-200));
    color: var(--color-danger-500);
}

.sige-badge-funcionario {
    background: linear-gradient(135deg, var(--sg-theme-soft,var(--color-brand-50)), var(--color-info-200));
    color: var(--sg-theme-primary-800,var(--color-ink-700));
}

.sige-badge-hoje {
    background: linear-gradient(135deg, var(--color-warning-200), var(--color-warning-300));
    color: var(--color-warning-800);
    animation: pulse 2s infinite;
}

.sige-badge-saude {
    background: rgba(239, 68, 68, 0.1);
    color: var(--sige-error);
}

.sige-aluno-processo {
    font-size: 0.75rem;
    color: var(--sige-slate-500);
    margin: 0 0 10px;
}

.sige-aluno-processo strong {
    color: var(--sige-slate-700);
}

.sige-aluno-tags {
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}

.sige-tag-turma {
    display: inline-flex;
    align-items: center;
    gap:var(--space-1);
    padding: 4px 10px;
    border-radius: var(--sige-radius);
    background: var(--sige-slate-100);
    font-size: 0.7rem;
    font-weight:600;
    color: var(--sige-slate-600);
}

.sige-tag-turma svg {
    width: 12px;
    height: 12px;
}

.sige-status-badge {
    display: inline-flex;
    padding: 4px 10px;
    border-radius:var(--radius-pill);
    font-size: 0.65rem;
    font-weight:700;
    text-transform: uppercase;
}

.sige-status-activo { background: var(--sige-success-light); color: var(--color-success-800); }
.sige-status-suspenso { background: var(--sige-error-light); color: var(--color-danger-700); }
.sige-status-transferido { background: var(--sige-warning-light); color: var(--color-warning-800); }
.sige-status-desistente { background: var(--sige-slate-100); color: var(--sige-slate-600); }

.sige-finance-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    border-radius:var(--radius-pill);
    font-size: 0.65rem;
    font-weight:700;
    text-transform: uppercase;
    letter-spacing: 0.02em;
    text-decoration: none;
    border: 1px solid transparent;
    line-height: 1.2;
}
.sige-finance-badge svg { width: 13px; height: 13px; flex-shrink: 0; }
.sige-finance-regular { background: var(--color-success-100); color: var(--color-success-800); border-color: var(--color-success-200); }
.sige-finance-divida { background: var(--color-danger-100); color: var(--color-danger-700); border-color: var(--color-danger-200); }
.sige-finance-parcial { background: var(--color-warning-50); color: var(--color-danger-600); border-color: var(--color-warning-200); }
.sige-finance-credito { background: var(--sg-theme-soft,var(--color-brand-50)); color: var(--color-info-600); border-color: var(--sg-theme-soft,var(--color-brand-50)); }
.sige-finance-plano { background: var(--color-warning-200); color: var(--color-warning-800); border-color: var(--color-warning-300); }
.sige-finance-badge:hover { transform: translateY(-1px); box-shadow:var(--shadow-xs); }


.sige-tag-transporte {
    display: inline-flex;
    align-items: center;
    gap:var(--space-1);
    padding: 4px 10px;
    border-radius:var(--radius-pill);
    background: var(--color-warning-100);
    border: 1px solid var(--color-warning-200);
    font-size: 0.65rem;
    font-weight:700;
    color: var(--color-danger-500);
}

.sige-tag-transporte svg {
    width: 12px;
    height: 12px;
}

.sige-wa-btn {
    display: inline-flex;
    align-items: center;
    gap:var(--space-1);
    padding: 4px 10px;
    border-radius: var(--sige-radius);
    background: var(--color-success-50);
    border: 1px solid var(--color-success-100);
    font-size: 0.7rem;
    font-weight:700;
    color: var(--color-success-500);
    text-decoration: none;
    transition: all var(--duration-normal) ease;
}

.sige-wa-btn svg {
    width: 14px;
    height: 14px;
}

.sige-wa-btn:hover {
    background: var(--color-success-500);
    color: var(--color-white);
    border-color: var(--color-success-500);
}

/* Card Actions */
.sige-card-actions {
    position: absolute;
    top: 16px;
    right: 16px;
    display: flex;
    gap: 6px;
    opacity: 0;
    transition: opacity var(--duration-normal) ease;
    z-index: 20;
    pointer-events: auto;
}

.sige-aluno-card:hover .sige-card-actions,
.sige-card-actions:focus-within {
    opacity: 1;
}

.sige-btn-action {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    border: 1px solid var(--sige-slate-200);
    background: var(--color-white);
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--sige-slate-500);
    transition: all var(--duration-normal) ease;
}

.sige-btn-action svg {
    width: 14px;
    height: 14px;
}

.sige-btn-action:hover {
    background: var(--sige-primary);
    color: var(--color-white);
    border-color: var(--sige-primary);
}

.sige-btn-action.btn-card {
    border-color: var(--color-brand-200);
    color: var(--sige-purple);
}

.sige-btn-action.btn-card:hover {
    background: var(--sige-purple);
    border-color: var(--sige-purple);
    color: var(--color-white);
}

.sige-btn-action.btn-delete:hover {
    background: var(--sige-error);
    border-color: var(--sige-error);
}

.sige-btn-action.btn-key {
    border-color: var(--color-warning-400);
    color: var(--sige-amber);
}

.sige-btn-action.btn-key:hover {
    background: var(--sige-amber);
    border-color: var(--sige-amber);
    color: var(--color-white);
}

.sige-btn-action.btn-clip {
    border-color: var(--sige-info);
    color: var(--sige-info);
    text-decoration: none;
}

.sige-btn-action.btn-clip:hover {
    background: var(--sige-info);
    border-color: var(--sige-info);
    color: var(--color-white);
}

/* ========================================
   EMPTY STATE
   ======================================== */
.sige-empty-state {
    grid-column: 1 / -1;
    text-align: center;
    padding: 80px 40px;
    background: var(--color-white);
    border-radius: var(--sige-radius-xl);
    border: 1px solid var(--sige-slate-100);
}

.sige-empty-icon {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    background: var(--sige-slate-100);
    display: flex;
    align-items: center;
    justify-content: center;
    margin:0 auto var(--space-6);
}

.sige-empty-icon svg {
    width: 48px;
    height: 48px;
    stroke: var(--sige-slate-300);
}

.sige-empty-state h3 {
    font-family: var(--sige-font-display);
    font-size: 1.25rem;
    font-weight:600;
    color: var(--sige-slate-600);
    margin:0 0 var(--space-2);
}

.sige-empty-state p {
    color: var(--sige-slate-400);
    font-size: 0.9rem;
    margin: 0;
}

/* ========================================
   MODAL
   ======================================== */
.sige-modal {
    position: fixed;
    inset: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(8px);
    z-index: 10000;
    display: none;
    align-items: center;
    justify-content: center;
    padding:var(--space-5);
}

.sige-modal-content {
    width: 100%;
    max-width: 950px;
    height: 95vh;
    max-height: 95vh;
    background: var(--color-white);
    border-radius: var(--sige-radius-xl);
    box-shadow:var(--shadow-xs);
    animation: modalSlideIn 0.25s ease;
    overflow: hidden;
    display: flex;
    flex-direction: column;
}

/* Form ocupa todo espaço disponível */
.sige-modal-content > form#form-aluno {
    display: flex;
    flex-direction: column;
    flex: 1;
    min-height: 0;
    overflow: hidden;
}

.sige-modal-header {
    padding:var(--space-5) var(--space-6);
    background: linear-gradient(135deg, var(--color-black), var(--color-ink-900));
    color: var(--color-white);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap:var(--space-4);
    flex-shrink: 0;
}

.sige-modal-header h2 {
    font-family: var(--sige-font-display);
    font-size: 1.15rem;
    font-weight:600;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 10px;
    color: var(--color-white);
}

.sige-modal-header h2 svg {
    width: 22px;
    height: 22px;
    stroke: var(--color-white);
}

.sige-modal-close {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    background: rgba(255,255,255,0.15);
    border: none;
    color: var(--color-white);
    font-size: 1.25rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background var(--duration-normal) ease;
    flex-shrink: 0;
}

.sige-modal-close:hover {
    background: rgba(255,255,255,0.25);
}

/* Tabs */
.sige-tabs {
    display: flex;
    gap:var(--space-1);
    padding:0 var(--space-6);
    background: var(--sige-slate-100);
    flex-shrink: 0;
}

.sige-tab {
    padding: 14px 20px;
    font-size: 0.8rem;
    font-weight:600;
    color: var(--sige-slate-500);
    cursor: pointer;
    border-bottom: 3px solid transparent;
    transition: all var(--duration-normal) ease;
}

.sige-tab:hover {
    color: var(--sige-primary);
}

.sige-tab.active {
    color: var(--sige-primary);
    border-bottom-color: var(--sige-primary);
    background: var(--color-white);
}

.sige-tab.tab-health {
    color: var(--sige-error);
}

.sige-tab.tab-health.active {
    border-bottom-color: var(--sige-error);
}

.sige-modal-body {
    padding:var(--space-6);
    overflow-y: auto;
    flex: 1;
    min-height: 0;
    -webkit-overflow-scrolling: touch;
}

/* Scrollbar personalizada */
.sige-modal-body::-webkit-scrollbar {
    width: 8px;
}

.sige-modal-body::-webkit-scrollbar-track {
    background: var(--sige-slate-100);
    border-radius:var(--radius-xs);
}

.sige-modal-body::-webkit-scrollbar-thumb {
    background: var(--sige-slate-300);
    border-radius:var(--radius-xs);
}

.sige-modal-body::-webkit-scrollbar-thumb:hover {
    background: var(--sige-slate-400);
}

.sige-modal-footer {
    display: flex;
    justify-content: flex-end;
    gap:var(--space-3);
    padding:var(--space-4) var(--space-6);
    background: var(--sige-slate-50);
    border-top: 1px solid var(--sige-slate-100);
    flex-shrink: 0;
}

/* Tab Content */
.sige-tab-content {
    display: none;
}

.sige-tab-content.active {
    display: block;
}

/* Form Fields */
.sige-boletim-section {
    border: 2px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    padding:var(--space-5);
    margin-bottom: 20px;
    position: relative;
}

.sige-boletim-section.highlight {
    background: var(--sige-info-light);
    border-color: var(--sige-info);
}

.sige-boletim-title {
    position: absolute;
    top: -10px;
    left: 16px;
    background: var(--color-white);
    padding:0 var(--space-2);
    font-size: 0.7rem;
    font-weight:700;
    text-transform: uppercase;
    color: var(--sige-primary);
}

.sige-boletim-section.highlight .sige-boletim-title {
    background: var(--sige-info-light);
    color: var(--sg-theme-primary,var(--color-brand-500));
}

.sige-field-row {
    display: grid;
    gap:var(--space-4);
    margin-bottom: 16px;
}

.sige-field-row-2 { grid-template-columns: 1fr 1fr; }
.sige-field-row-3 { grid-template-columns: 1fr 1fr 1fr; }
.sige-span-2 { grid-column: span 2; }

.sige-field-group {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sige-field-group label {
    font-size: 0.7rem;
    font-weight:600;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--sige-slate-600);
}

.sige-field-group input,
.sige-field-group select,
.sige-field-group textarea {
    height: 44px;
    padding: 0 14px;
    border: 1px solid var(--sige-slate-200);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.9rem;
    color: var(--sige-slate-800);
    background: var(--color-white);
    transition: all var(--duration-normal) ease;
}

.sige-field-group textarea {
    height: auto;
    padding: 12px 14px;
    min-height: 80px;
    resize: vertical;
}

.sige-field-group input:focus,
.sige-field-group select:focus,
.sige-field-group textarea:focus {
    outline: none;
    border-color: var(--sige-primary);
    box-shadow:var(--shadow-xs);
}

/* Foto Upload */
.sige-foto-upload {
    width: 140px;
    height: 140px;
    background: var(--sige-slate-50);
    border: 3px dashed var(--sige-slate-200);
    border-radius: var(--sige-radius);
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    overflow: hidden;
    position: relative;
    transition: all var(--duration-normal) ease;
}

.sige-foto-upload:hover {
    background: var(--sige-primary-light);
    background: rgba(63, 81, 181, 0.05);
    border-color: var(--sige-primary);
}

.sige-foto-upload img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.sige-foto-upload svg {
    width: 40px;
    height: 40px;
    stroke: var(--sige-slate-300);
}

.sige-foto-upload .overlay {
    position: absolute;
    bottom: 0;
    left: 0;
    width: 100%;
    background: rgba(15, 23, 42, 0.85);
    color: var(--color-white);
    font-size: 0.65rem;
    font-weight:600;
    padding:var(--space-2) 0;
    text-align: center;
    text-transform: uppercase;
    display: none;
}

.sige-foto-upload:hover .overlay {
    display: block;
}

/* Checkbox Group */
.sige-checkbox-row {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    margin-top: 10px;
    padding: 14px;
    background: var(--sige-slate-50);
    border-radius: var(--sige-radius);
}

.sige-checkbox-row label {
    display: flex;
    align-items: center;
    gap:var(--space-2);
    font-size: 0.8rem;
    font-weight:600;
    color: var(--sige-slate-700);
    cursor: pointer;
}

.sige-checkbox-row input[type="checkbox"] {
    width: 18px;
    height: 18px;
    accent-color: var(--sige-primary);
}

/* Extras Box */
.sige-extras-box {
    margin-top: 16px;
    padding:var(--space-4);
    border: 2px dashed var(--color-success-200);
    background: var(--color-success-50);
    border-radius: var(--sige-radius);
}

.sige-extras-box label.title {
    display: block;
    font-size: 0.75rem;
    font-weight:600;
    color: var(--color-success-800);
    margin-bottom: 12px;
}

.sige-extras-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.sige-extras-grid label {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.8rem;
    color: var(--sige-slate-700);
    cursor: pointer;
}

/* Regime Creche */
.sige-creche-box {
    margin-top: 16px;
    padding:var(--space-4);
    border: 2px dashed var(--color-brand-200);
    background: var(--color-brand-50);
    border-radius: var(--sige-radius);
}

.sige-creche-box label.title {
    display: block;
    font-size: 0.75rem;
    font-weight:600;
    color: var(--color-brand-600);
    margin-bottom: 12px;
}

/* Buttons */
.sige-btn-modal {
    display: inline-flex;
    align-items: center;
    gap:var(--space-2);
    padding:var(--space-3) var(--space-5);
    border-radius: var(--sige-radius);
    font-family: var(--sige-font-body);
    font-size: 0.875rem;
    font-weight:600;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
    border: none;
}

.sige-btn-modal svg {
    width: 18px;
    height: 18px;
}

.sige-btn-cancel {
    background: var(--color-white);
    border: 1px solid var(--sige-slate-200);
    color: var(--sige-slate-600);
}

.sige-btn-cancel:hover {
    background: var(--sige-slate-50);
    border-color: var(--sige-slate-300);
}

.sige-btn-submit {
    background: linear-gradient(135deg, var(--sige-navy), var(--sige-navy-light));
    color: var(--color-white);
    box-shadow:var(--shadow-xs);
}

.sige-btn-submit:hover {
    transform: translateY(-1px);
    box-shadow:var(--shadow-sm);
}

.sige-btn-submit:disabled {
    opacity: 0.7;
    cursor: not-allowed;
    transform: none;
}

/* Doc Upload */
.sige-doc-upload-area {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap:var(--space-4);
}

.sige-doc-box {
    border: 2px dashed var(--sige-slate-200);
    border-radius: var(--sige-radius);
    padding:var(--space-5);
    text-align: center;
    cursor: pointer;
    transition: all var(--duration-normal) ease;
}

.sige-doc-box:hover {
    border-color: var(--sige-primary);
    background: rgba(63, 81, 181, 0.03);
}

.sige-doc-box.has-file {
    border-color: var(--sige-success);
    background: var(--sige-success-light);
}

.sige-doc-box svg {
    width: 32px;
    height: 32px;
    stroke: var(--sige-slate-400);
    margin-bottom: 8px;
}

.sige-doc-box.has-file svg {
    stroke: var(--sige-success);
}

.sige-doc-box span {
    display: block;
    font-size: 0.75rem;
    font-weight:600;
    color: var(--sige-slate-600);
}

/* ========================================
   PRINT STYLES
   ======================================== */
@media print {
    body * { visibility: hidden; }
    #print-area, #print-area * { visibility: visible; }
    #print-area { position: absolute; left: 0; top: 0; width: 100%; }
}

/* ========================================
   RESPONSIVE
   ======================================== */
@media (max-width: 900px) {
    .sige-alunos-page { padding:var(--space-4); }
    .sige-hero { padding:var(--space-6); }
    .sige-hero-content { flex-direction: column; }
    .sige-btn-hero { width: 100%; justify-content: center; }
    .sige-aluno-grid { grid-template-columns: 1fr; }
    .sige-field-row-2, .sige-field-row-3 { grid-template-columns: 1fr; }
    .sige-span-2 { grid-column: span 1; }
    .sige-birthday-panel { flex-direction: column; text-align: center; }
    .sige-birthday-info { flex-direction: column; }
}

@media (max-width: 640px) {
    .sige-alunos-page { padding:var(--space-3); }
    .sige-hero { padding:var(--space-5); border-radius: var(--sige-radius-lg); }
    .sige-hero h1 { font-size: 1.5rem; }
    .sige-stats-grid { grid-template-columns: 1fr 1fr; }
    .sige-toolbar { flex-direction: column; }
    .sige-search-box { min-width: 100%; }
    .sige-filter-select { width: 100%; }
    .sige-tabs { overflow-x: auto; }
    .sige-tab { white-space: nowrap; }
}

@media (max-height: 820px) {
    .sige-modal-header { padding: 16px 22px; }
    .sige-tabs { padding: 0 22px; }
    .sige-modal-body { padding:var(--space-5); }
    .sige-modal-footer { padding: 14px 22px; }
}

@media (max-height: 700px) {
    .sige-modal-content { height: 98vh; max-height: 98vh; }
    .sige-modal-header { padding: 12px 18px; }
    .sige-modal-header h2 { font-size: 1rem; }
    .sige-tabs { padding: 0 18px; }
    .sige-tab { padding: 10px 14px; font-size: 0.75rem; }
    .sige-modal-body { padding:var(--space-4); }
    .sige-modal-footer { padding: 12px 18px; }
    .sige-boletim-section { padding:var(--space-4); margin-bottom: 14px; }
    .sige-field-row { margin-bottom: 12px; gap:var(--space-3); }
}

/* ========================================
   12.9.8 - RESULTADOS + PAGINAÇÃO
   ======================================== */
.sige-results-counter {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 16px;
    margin: 8px 0 14px;
    background: var(--color-slate-50);
    border: 1px solid var(--color-ink-100);
    border-radius:var(--radius-sm);
    font-size:var(--fs-sm);
    color: var(--color-slate-700);
    flex-wrap: wrap;
    gap:var(--space-2);
}
.sige-results-counter strong { color: var(--color-info-900); }
.sige-results-counter em { color: var(--color-warning-800); font-style: normal; font-weight:600; }
.sige-results-page { color: var(--color-slate-500); font-size: 12px; }

.sige-pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap:var(--space-1);
    margin:var(--space-6) 0 var(--space-2);
    flex-wrap: wrap;
}
.sige-page-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 40px;
    height: 38px;
    padding:0 var(--space-3);
    border: 1px solid var(--color-ink-100);
    border-radius:var(--radius-sm);
    background: var(--color-white);
    color: var(--color-slate-700);
    font-weight:600;
    font-size:var(--fs-sm);
    text-decoration: none;
    transition: all 0.15s ease;
    cursor: pointer;
}
.sige-page-btn:hover:not(.disabled):not(.current) {
    border-color: var(--color-info-600);
    color: var(--color-info-600);
    background: var(--color-info-50);
}
.sige-page-btn.current {
    background: var(--color-info-600);
    color: var(--color-white);
    border-color: var(--color-info-600);
    cursor: default;
}
.sige-page-btn.disabled {
    background: var(--color-slate-50);
    color: var(--color-ink-200);
    cursor: not-allowed;
    pointer-events: none;
}
.sige-page-ellipsis {
    padding:0 var(--space-2);
    color: var(--color-slate-400);
    font-weight:600;
}

@media (max-width: 768px) {
    .sige-results-counter { font-size: 12px; padding:var(--space-2) var(--space-3); }
    .sige-page-btn { min-width: 36px; height: 34px; padding:0 var(--space-2); font-size: 12px; }
}
/* ========================================
   ALUNOS E MATRÍCULAS - Harmonia Visual v12.10.28
   ======================================== */
body.sige-admin-app.sige-view-alunos_lista .sg-app-page{
    max-width:none;
}
body.sige-admin-app.sige-view-alunos_lista .sg-app-page > .wrap.sige-alunos-page{
    margin:0;
}
.sige-alunos-page{
    background:transparent;
    padding:0;
    max-width:none;
    color:var(--color-black);
}
.sige-birthday-panel{
    background:linear-gradient(135deg,var(--color-brand-500) 0%,var(--color-brand-600) 48%,var(--color-brand-500) 100%);
    border:1px solid rgba(255,255,255,.22);
    box-shadow:var(--shadow-md);
    border-radius:var(--radius-xl);
    margin-bottom:24px;
}
.sige-hero{
    background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 48%,var(--color-brand-50) 100%);
    color:var(--color-brand-900);
    border:1px solid rgba(124,58,237,.13);
    box-shadow:var(--shadow-lg);
    border-radius:var(--radius-xl);
    padding:34px 38px;
    overflow:hidden;
}
.sige-hero::before{
    width:420px;
    height:420px;
    right:-150px;
    top:-170px;
    background:radial-gradient(circle,rgba(124,58,237,.22) 0%,rgba(124,58,237,.08) 40%,transparent 72%);
}
.sige-hero::after{
    width:260px;
    height:260px;
    left:auto;
    right:110px;
    bottom:-130px;
    background:radial-gradient(circle,rgba(20,184,166,.18) 0%,transparent 68%);
}
.sige-hero-kicker{
    background:var(--color-brand-100);
    border:1px solid var(--color-brand-100);
    color:var(--color-brand-600);
    letter-spacing:.08em;
}
.sige-hero-kicker svg{stroke:var(--color-brand-500);}
.sige-hero h1{
    color:var(--color-brand-900);
    letter-spacing:-.045em;
    font-size:clamp(2rem,3.4vw,2.75rem);
}
.sige-hero-subtitle{
    color:var(--color-slate-500);
    max-width:720px;
    font-size:1rem;
    line-height:1.65;
}
.sige-hero-meta{
    background:var(--color-white);
    color:var(--color-slate-700);
    border-color:var(--color-brand-100);
    box-shadow:var(--shadow-sm);
}
.sige-hero-meta svg{stroke:var(--color-brand-500);opacity:1;}
.sige-btn-hero{
    background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700));
    color:var(--color-white);
    border-radius:var(--radius-lg);
    box-shadow:var(--shadow-sm);
    text-transform:none;
    letter-spacing:0;
    min-height:48px;
}
.sige-btn-hero:hover{box-shadow:var(--shadow-md);}
.sige-stats-grid{
    grid-template-columns:repeat(5,minmax(170px,1fr));
    gap:18px;
}
.sige-stat-card{
    border-radius:var(--radius-xl);
    border:1px solid rgba(226,232,240,.86);
    box-shadow:var(--shadow-md);
    padding:22px;
    min-height:102px;
}
.sige-stat-card.highlight{
    background:linear-gradient(135deg,var(--color-brand-500) 0%,var(--color-brand-700) 100%);
    box-shadow:var(--shadow-md);
}
.sige-stat-icon{
    border-radius:var(--radius-lg);
    width:50px;
    height:50px;
    border-left:0!important;
}
.sige-stat-icon svg{width:23px;height:23px;stroke-width:2.15;}
.sige-stat-label{color:var(--color-slate-500);text-transform:none;letter-spacing:0;font-size:.78rem;font-weight:400;}
.sige-stat-value{font-size:1.75rem;color:var(--color-black);letter-spacing:-.03em;}
.sige-toolbar{
    border-radius:var(--radius-xl);
    border:1px solid rgba(226,232,240,.88);
    box-shadow:var(--shadow-md);
    padding:18px;
    gap:var(--space-3);
}
.sige-search-box input,.sige-filter-select{
    height:48px;
    border-radius:var(--radius-lg);
    background:var(--color-slate-50);
    border-color:var(--color-ink-100);
}
.sige-search-box input:focus,.sige-filter-select:focus{
    background:var(--color-white);
    border-color:var(--color-brand-400);
    box-shadow:var(--shadow-xs);
}
.sige-btn-toolbar{
    min-height:48px;
    border-radius:var(--radius-lg)!important;
    font-weight:700;
    box-shadow:none!important;
}
.sige-btn-toolbar.btn-search,
.sige-toolbar .btn-search{
    background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))!important;
    color:var(--color-white)!important;
    border-color:transparent!important;
}
.sige-results-counter{
    border-radius:var(--radius-xl);
    border:1px solid rgba(226,232,240,.86);
    background:var(--color-white);
    box-shadow:var(--shadow-md);
}
.sige-aluno-grid{
    grid-template-columns:repeat(auto-fill,minmax(390px,1fr));
    gap:var(--space-5);
}
.sige-aluno-card{
    border-radius:var(--radius-xl);
    border:1px solid rgba(226,232,240,.9);
    box-shadow:var(--shadow-md);
    padding:22px;
    min-height:154px;
}
.sige-aluno-card:hover{
    border-color:rgba(124,58,237,.26);
    box-shadow:var(--shadow-lg);
}
.sige-aluno-foto{
    width:78px;
    height:78px;
    border-radius:var(--radius-xl);
    border:4px solid var(--color-brand-50);
    background:var(--color-white);
}
.sige-aluno-nome{color:var(--color-brand-900);font-size:1.04rem;line-height:1.32;padding-right:96px;}
.sige-aluno-processo{color:var(--color-slate-500);font-size:.78rem;}
.sige-tag-turma,.sige-status-badge,.sige-finance-badge,.sige-tag-transporte,.sige-wa-btn{
    border-radius:var(--radius-pill);
    min-height:28px;
}
.sige-card-actions{
    opacity:1;
    top:18px;
    right:18px;
    gap:6px;
    background:rgba(255,255,255,.92);
    border:1px solid rgba(226,232,240,.88);
    padding:6px;
    border-radius:var(--radius-lg);
    box-shadow:var(--shadow-sm);
}
.sige-btn-action{
    background:var(--color-white);
    border-color:var(--color-ink-100);
    color:var(--color-slate-700);
    border-radius:var(--radius-md);
}
.sige-btn-action svg{stroke:currentColor!important;fill:none;}
.sige-btn-action.btn-delete{color:var(--color-danger-600);}
.sige-btn-action:hover{
    transform:translateY(-1px);
    color:var(--color-brand-500);
    border-color:var(--color-info-100);
    background:var(--color-brand-50);
}
.sige-empty-state{
    border-radius:var(--radius-xl);
    background:var(--color-white);
    border:1px solid var(--color-ink-100);
    box-shadow:var(--shadow-md);
}
.sige-modal{z-index:100000;background:rgba(15,23,42,.58);backdrop-filter:blur(10px);}
.sige-modal-content{
    max-width:1080px;
    border-radius:var(--radius-xl);
    box-shadow:var(--shadow-lg);
}
.sige-modal-header{
    background:linear-gradient(135deg,var(--color-white),var(--color-slate-50));
    color:var(--color-brand-900);
    border-bottom:1px solid var(--color-brand-100);
}
.sige-modal-header h2{color:var(--color-brand-900);}
.sige-modal-header h2 svg{stroke:var(--color-brand-500);}
.sige-modal-close{background:var(--color-ink-50);color:var(--color-slate-700);}
.sige-modal-close:hover{background:var(--color-brand-100);color:var(--color-brand-600);}
.sige-tabs{background:var(--color-white);border-bottom:1px solid var(--color-brand-100);}
.sige-tab{border-radius:12px 14px 0 0;}
.sige-tab.active{color:var(--color-brand-600);border-bottom-color:var(--color-brand-500);background:var(--color-white);}
.sige-boletim-section{border:1px solid var(--color-ink-100);border-radius:var(--radius-xl);background:var(--color-white);}
.sige-btn-modal{border-radius:var(--radius-lg);min-height:46px;}
.sige-btn-submit{background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700))!important;}
.sige-alunos-popup{position:fixed;inset:0;z-index:140000;display:none;align-items:center;justify-content:center;padding:var(--space-5);}
.sige-alunos-popup.is-open{display:flex;}
.sige-alunos-popup-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(9px);}
.sige-alunos-popup-box{position:relative;width:min(520px,100%);background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);overflow:hidden;animation:modalSlideIn .22s ease;}
.sige-alunos-popup-head{padding:22px 24px;background:linear-gradient(135deg,var(--color-white),var(--color-slate-50));border-bottom:1px solid var(--color-brand-100);display:flex;align-items:center;gap:14px;}
.sige-alunos-popup-icon{width:46px;height:46px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--color-brand-100);color:var(--color-brand-500);flex-shrink:0;}
.sige-alunos-popup-icon svg{width:24px;height:24px;stroke:currentColor;}
.sige-alunos-popup-box.is-danger .sige-alunos-popup-icon{background:var(--color-danger-100);color:var(--color-danger-600);}
.sige-alunos-popup-box.is-warning .sige-alunos-popup-icon{background:var(--color-warning-200);color:var(--color-warning-800);}
.sige-alunos-popup-title{font:800 1.08rem var(--sige-font-display);color:var(--color-brand-900);margin:0;}
.sige-alunos-popup-body{padding:22px 24px;color:var(--color-slate-700);font-size:.94rem;line-height:1.62;white-space:pre-line;}
.sige-alunos-popup-actions{padding:0 var(--space-6) var(--space-6);display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;}
.sige-alunos-popup-btn{border:0;border-radius:var(--radius-md);padding:12px 18px;font-weight:700;cursor:pointer;min-height:44px;}
.sige-alunos-popup-cancel{background:var(--color-ink-50);color:var(--color-slate-700);}
.sige-alunos-popup-confirm{background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-700));color:var(--color-white);box-shadow:var(--shadow-sm);}
.sige-alunos-popup-box.is-danger .sige-alunos-popup-confirm{background:linear-gradient(135deg,var(--color-danger-500),var(--color-danger-700));box-shadow:var(--shadow-sm);}
@media (max-width:1200px){.sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.sige-aluno-grid{grid-template-columns:1fr;}}
@media (max-width:720px){
    .sige-hero{padding:var(--space-6);border-radius:var(--radius-xl);}
    .sige-hero h1{font-size:1.7rem;}
    .sige-stats-grid{grid-template-columns:1fr;}
    .sige-toolbar{flex-direction:column;align-items:stretch;}
    .sige-search-box{min-width:100%;}
    .sige-filter-select,.sige-btn-toolbar{width:100%;}
    .sige-aluno-card{padding:18px;flex-direction:column;}
    .sige-aluno-nome{padding-right:0;}
    .sige-card-actions{position:static;width:100%;margin-top:10px;justify-content:flex-start;overflow:auto;}
    .sige-modal-content{height:96vh;border-radius:var(--radius-xl);}
    .sige-tabs{overflow-x:auto;padding:0 var(--space-3);}
}


/* ========================================
   ALUNOS E MATRÍCULAS - Harmonização V2 v12.10.29
   Referência visual: Painel Principal aprovado
   ======================================== */
body.sige-admin-app.sige-view-alunos_lista .sg-product-page-head{display:none!important;}
body.sige-admin-app.sige-view-alunos_lista .sg-app-page{max-width:none!important;width:100%!important;padding-top:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sg-app-content{padding-left:30px;padding-right:30px;}
.sige-alunos-page{--sgv2-purple:var(--color-brand-500);--sgv2-purple-dark:var(--color-brand-700);--sgv2-purple-soft:var(--color-brand-50);--sgv2-ink:var(--color-ink-500);--sgv2-muted:var(--color-slate-500);--sgv2-line:var(--color-ink-100);--sgv2-bg:var(--color-ink-50);--sgv2-green:var(--color-success-700);--sgv2-red:var(--color-danger-500);--sgv2-amber:var(--color-warning-500);--sgv2-blue:var(--color-info-400);font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;color:var(--sgv2-ink);background:transparent!important;padding:0!important;margin:0!important;display:flex;flex-direction:column;gap:var(--space-5);}
.sige-alunos-page *{box-sizing:border-box;}
.sige-alunos-page .sige-hero{position:relative;overflow:hidden;border-radius:var(--radius-xl);background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-brand-100) 100%)!important;border:1px solid rgba(92,64,187,.12);box-shadow:var(--shadow-md);padding:var(--space-6) var(--space-8)!important;margin:0!important;color:var(--sgv2-ink)!important;display:block;}
.sige-alunos-page .sige-hero:after{display:none!important;}
.sige-alunos-page .sige-hero-content{position:relative;z-index:1;display:block!important;gap:22px;align-items:center;}
.sige-alunos-page .sige-hero-text{min-width:0;}
.sige-alunos-page .sige-hero-kicker{display:block;background:transparent!important;border:0!important;padding:0!important;border-radius:0!important;font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:var(--sgv2-purple)!important;margin:0 0 10px!important;}
.sige-alunos-page .sige-hero h1{margin:0!important;font-size:31px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.04em!important;color:var(--color-black)!important;}
.sige-alunos-page .sige-hero-subtitle{max-width:650px;margin:var(--space-3) 0 0!important;font-size:15px!important;line-height:1.65!important;color:var(--color-slate-700)!important;font-weight:500!important;}
.sige-alunos-page .sige-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:24px;}
.sige-alunos-page .sige-btn-hero{min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:10px;border-radius:var(--radius-md)!important;padding:0 22px!important;font-size:var(--fs-base)!important;font-weight:700!important;text-decoration:none;border:1px solid transparent!important;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);text-transform:none!important;letter-spacing:0!important;}
.sige-alunos-page .sige-btn-hero svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-alunos-page .sige-btn-hero-secondary{background:var(--color-white)!important;color:var(--color-ink-900)!important;border-color:var(--color-ink-100)!important;box-shadow:var(--shadow-sm);}
.sige-alunos-page .sige-btn-hero:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}
/* Herói compacto (v12.22.0): faixa única, sem ilustração. Regras de arte
   (.sige-hero-art/.sige-hero-*/.sige-school-*) removidas com o respectivo HTML. */
.sige-alunos-page .sige-stats-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr))!important;gap:var(--space-4)!important;margin:0!important;}
.sige-alunos-page .sige-stat-card{position:relative;overflow:hidden;display:grid!important;grid-template-columns:auto minmax(0,1fr);gap:var(--space-4);align-items:center;min-height:104px;padding:18px 20px!important;border-radius:var(--radius-xl)!important;background:var(--color-white)!important;border:1px solid rgba(28,32,54,.08)!important;box-shadow:var(--shadow-md);color:var(--sgv2-ink)!important;}
.sige-alunos-page .sige-stat-card:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50));}
.sige-alunos-page .sige-stat-icon{width:52px!important;height:52px!important;border-radius:var(--radius-lg)!important;display:flex;align-items:center;justify-content:center;background:var(--kpi-soft,var(--color-brand-50))!important;color:var(--kpi-color,var(--sgv2-purple))!important;position:relative;z-index:1;border-left:0!important;}
.sige-alunos-page .sige-stat-icon svg{width:24px!important;height:24px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-alunos-page .sige-stat-info{position:relative;z-index:1;min-width:0;}
.sige-alunos-page .sige-stat-label{font-size:var(--fs-sm)!important;font-weight:600!important;color:var(--color-slate-600)!important;margin-bottom:6px!important;text-transform:none!important;letter-spacing:0!important;}
.sige-alunos-page .sige-stat-value{font-size:27px!important;line-height:1!important;font-weight:700!important;letter-spacing:-.03em!important;color:var(--color-black)!important;}
.sige-alunos-page .sige-stat-note{margin-top:7px;font-size:12px;font-weight:600;color:var(--color-ink-400);}
.sige-alunos-page .sige-birthday-panel{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);color:var(--sgv2-ink)!important;padding:18px 22px!important;margin:0!important;}
.sige-alunos-page .sige-birthday-icon{width:40px!important;height:40px!important;border-radius:var(--radius-md)!important;background:var(--color-warning-100)!important;color:var(--color-warning-500)!important;animation:none!important;}
.sige-alunos-page .sige-birthday-icon svg{width:20px!important;height:20px!important;stroke:currentColor!important;}
.sige-alunos-page .sige-birthday-text h3{font-size:17px!important;line-height:1.1!important;font-weight:700!important;letter-spacing:-.03em!important;color:var(--color-ink-500)!important;margin:0 0 5px!important;}
.sige-alunos-page .sige-birthday-text p{font-size:12px!important;font-weight:600!important;color:var(--color-ink-400)!important;margin:0!important;opacity:1!important;}
.sige-alunos-page .sige-btn-bday{background:var(--color-brand-50)!important;border:0!important;color:var(--sgv2-purple)!important;border-radius:var(--radius-pill)!important;min-height:38px;font-size:12px!important;font-weight:700!important;padding:0 13px!important;}
.sige-alunos-page .sige-toolbar{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);padding:20px 22px!important;display:grid!important;grid-template-columns:minmax(280px,1.3fr) minmax(170px,.62fr) minmax(170px,.62fr) auto auto auto!important;gap:var(--space-3)!important;align-items:center!important;margin:0!important;}
.sige-alunos-page .sige-search-box{min-width:0!important;}
.sige-alunos-page .sige-search-box input,.sige-alunos-page .sige-filter-select{height:46px!important;border-radius:var(--radius-md)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-ink-800)!important;font-size:var(--fs-sm)!important;font-weight:600!important;}
.sige-alunos-page .sige-search-box input:focus,.sige-alunos-page .sige-filter-select:focus{border-color:var(--color-info-100)!important;box-shadow:var(--shadow-xs);outline:none!important;}
.sige-alunos-page .sige-search-box svg{stroke:var(--color-ink-400)!important;}
.sige-alunos-page .sige-btn-toolbar{min-height:46px!important;height:46px!important;border-radius:var(--radius-md)!important;padding:0 17px!important;font-size:var(--fs-sm)!important;font-weight:700!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important;color:var(--color-ink-800)!important;box-shadow:none!important;}
.sige-alunos-page .sige-btn-toolbar.btn-search,.sige-alunos-page .sige-toolbar .btn-search{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600))!important;color:var(--color-white)!important;border-color:transparent!important;box-shadow:var(--shadow-sm);}
.sige-alunos-page .sige-btn-toolbar svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-alunos-page .sige-results-counter{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-lg)!important;box-shadow:var(--shadow-sm);padding:14px 18px!important;margin:0!important;color:var(--color-slate-700)!important;font-weight:600!important;}
.sige-alunos-page .sige-results-counter strong{color:var(--sgv2-purple)!important;}
.sige-alunos-page .sige-aluno-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(430px,1fr))!important;gap:18px!important;margin:0!important;}
.sige-alunos-page .sige-aluno-card{background:var(--color-white)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);padding:20px 22px!important;display:flex!important;gap:var(--space-4)!important;min-height:160px!important;}
.sige-alunos-page .sige-aluno-card:hover{transform:translateY(-1px)!important;box-shadow:var(--shadow-md);border-color:var(--color-info-100)!important;}
.sige-alunos-page .sige-aluno-foto{width:70px!important;height:70px!important;border-radius:var(--radius-lg)!important;border:3px solid var(--color-brand-50)!important;background:var(--color-slate-50)!important;}
.sige-alunos-page .sige-aluno-nome{font-size:17px!important;line-height:1.2!important;font-weight:700!important;letter-spacing:-.03em!important;color:var(--color-ink-500)!important;margin:0 0 6px!important;padding-right:112px!important;}
.sige-alunos-page .sige-aluno-processo{font-size:12px!important;color:var(--color-ink-400)!important;font-weight:600!important;margin:0 0 10px!important;}
.sige-alunos-page .sige-tag-turma,.sige-alunos-page .sige-status-badge,.sige-alunos-page .sige-finance-badge,.sige-alunos-page .sige-tag-transporte,.sige-alunos-page .sige-wa-btn{min-height:30px;border-radius:var(--radius-pill)!important;font-size:12px!important;font-weight:700!important;padding:0 10px!important;display:inline-flex;align-items:center;gap:6px;}
.sige-alunos-page .sige-tag-turma svg,.sige-alunos-page .sige-finance-badge svg,.sige-alunos-page .sige-tag-transporte svg,.sige-alunos-page .sige-wa-btn svg{width:14px!important;height:14px!important;color:currentColor!important;stroke:currentColor!important;}
.sige-alunos-page .sige-card-actions{opacity:1!important;top:18px!important;right:18px!important;gap:6px!important;background:rgba(255,255,255,.94)!important;border:1px solid var(--color-slate-100)!important;padding:6px!important;border-radius:var(--radius-lg)!important;box-shadow:var(--shadow-sm);}
.sige-alunos-page .sige-btn-action{width:34px!important;height:34px!important;border-radius:var(--radius-md)!important;border:1px solid var(--color-slate-100)!important;background:var(--color-white)!important;color:var(--color-slate-600)!important;}
.sige-alunos-page .sige-btn-action svg{width:16px!important;height:16px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sige-alunos-page .sige-btn-action:hover{background:var(--color-brand-50)!important;color:var(--sgv2-purple)!important;border-color:var(--color-info-100)!important;}
.sige-alunos-page .sige-empty-state,.sige-alunos-page .sige-pagination{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);}
.sige-alunos-page .sige-pagination{padding:14px;margin:0;}
.sige-alunos-page .sige-page-btn{border-radius:var(--radius-md);border-color:var(--color-slate-100);color:var(--color-ink-800);}
.sige-alunos-page .sige-page-btn.current{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));border-color:transparent;color:var(--color-white);}
@media (max-width:1500px){.sige-alunos-page .sige-toolbar{grid-template-columns:1fr 1fr 1fr auto}.sige-alunos-page .sige-toolbar .btn-cards,.sige-alunos-page .sige-toolbar .btn-excel{grid-column:auto}.sige-alunos-page .sige-hero-content{grid-template-columns:1fr}.sige-alunos-page .sige-hero-art{display:none}}
@media (max-width:1100px){body.sige-admin-app.sige-view-alunos_lista .sg-app-content{padding-left:22px;padding-right:22px}.sige-alunos-page .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important}.sige-alunos-page .sige-toolbar{grid-template-columns:1fr!important}.sige-alunos-page .sige-filter-select,.sige-alunos-page .sige-btn-toolbar{width:100%!important}.sige-alunos-page .sige-aluno-grid{grid-template-columns:1fr!important}}
@media (max-width:720px){body.sige-admin-app.sige-view-alunos_lista .sg-app-content{padding-left:16px;padding-right:16px}.sige-alunos-page .sige-hero{padding:var(--space-6) var(--space-5)!important;border-radius:22px!important}.sige-alunos-page .sige-hero h1{font-size:24px!important}.sige-alunos-page .sige-stats-grid{grid-template-columns:1fr!important}.sige-alunos-page .sige-birthday-panel{align-items:flex-start;flex-direction:column}.sige-alunos-page .sige-aluno-card{flex-direction:column!important}.sige-alunos-page .sige-aluno-nome{padding-right:0!important}.sige-alunos-page .sige-card-actions{position:static!important;width:100%!important;margin-top:10px!important;overflow:auto!important;justify-content:flex-start!important}.sige-alunos-page .sige-modal-content{height:96vh;border-radius:22px!important}.sige-alunos-page .sige-tabs{overflow-x:auto;padding:0 12px}}

/* ========================================
   ALUNOS - Modal de registo no padrão actual v12.10.44
   Mantém regras e gravação intactas; actualiza apenas a experiência visual.
   ======================================== */
body.sige-admin-app.sige-view-alunos_lista.sige-aluno-modal-open{overflow:hidden!important;}
/* Modais/popup acima da barra lateral: .sg-app-content e um contexto de
   empilhamento (position:relative;z-index:1) abaixo da sidebar (z-index alto no
   shell PRO), pelo que um modal filho do conteudo fica tapado pela barra lateral
   por mais alto que seja o seu z-index. Enquanto ha modal/popup aberto, elevamos
   o conteudo acima da sidebar; o fundo do modal passa a cobrir tambem a barra
   lateral (UX correcta). Scoped a esta view; nao afecta outros ecrans. */
body.sige-admin-app.sige-view-alunos_lista.sige-aluno-modal-open .sg-app-content,
body.sige-admin-app.sige-view-alunos_lista.sige-modal-open .sg-app-content{z-index:10090!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro{
    z-index:130000!important;
    padding:22px!important;
    background:rgba(20,24,45,.58)!important;
    backdrop-filter:blur(12px)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-content{
    width:min(1180px,calc(100vw - 44px))!important;
    height:min(92vh,900px)!important;
    max-height:92vh!important;
    border-radius:var(--radius-xl)!important;
    overflow:hidden!important;
    background:var(--color-white)!important;
    border:1px solid rgba(30,34,60,.10)!important;
    box-shadow:var(--shadow-lg);
    display:flex!important;
    flex-direction:column!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-header{
    padding:24px 28px!important;
    min-height:104px!important;
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 48%,var(--sg-theme-soft,var(--color-brand-50)) 100%)!important;
    border-bottom:1px solid rgba(30,34,60,.08)!important;
    color:var(--sg-theme-ink,var(--color-ink-500))!important;
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    gap:18px!important;
    flex-shrink:0!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-title-wrap{
    display:flex!important;
    align-items:center!important;
    gap:var(--space-4)!important;
    min-width:0!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-title-icon{
    width:56px!important;
    height:56px!important;
    border-radius:var(--radius-lg)!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    background:var(--sg-theme-soft,var(--color-brand-50))!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    flex-shrink:0!important;
    box-shadow:inset 0 0 0 1px rgba(var(--sg-theme-primary-rgb,90,63,214),.08)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-title-icon svg{
    width:27px!important;
    height:27px!important;
    stroke:currentColor!important;
    fill:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-kicker{
    display:block!important;
    margin:0 0 5px!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    font-size:var(--fs-xs)!important;
    line-height:1!important;
    font-weight:700!important;
    letter-spacing:.13em!important;
    text-transform:uppercase!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-header h2{
    margin:0!important;
    color:var(--sg-theme-ink,var(--color-ink-500))!important;
    font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;
    font-size:26px!important;
    line-height:1.1!important;
    font-weight:700!important;
    letter-spacing:-.045em!important;
    display:block!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-title-text p{
    margin:var(--space-2) 0 0!important;
    max-width:680px!important;
    color:var(--color-slate-600)!important;
    font-size:var(--fs-sm)!important;
    font-weight:500!important;
    line-height:1.55!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-close{
    width:44px!important;
    height:44px!important;
    border-radius:var(--radius-lg)!important;
    background:var(--color-ink-50)!important;
    color:var(--color-slate-700)!important;
    border:1px solid rgba(30,34,60,.08)!important;
    font-size:25px!important;
    line-height:1!important;
    transition:transform .18s ease,background .18s ease,color .18s ease!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-close:hover{
    transform:translateY(-1px)!important;
    background:var(--sg-theme-soft,var(--color-brand-50))!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tabs{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:10px!important;
    padding:14px 28px!important;
    background:var(--color-white)!important;
    border-bottom:1px solid rgba(30,34,60,.08)!important;
    overflow:visible!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab{
    position:relative!important;
    display:flex!important;
    align-items:center!important;
    gap:10px!important;
    min-height:48px!important;
    padding:0 15px!important;
    border:1px solid rgba(30,34,60,.08)!important;
    border-radius:var(--radius-lg)!important;
    background:var(--color-white)!important;
    color:var(--color-slate-600)!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
    text-transform:none!important;
    letter-spacing:0!important;
    cursor:pointer!important;
    box-shadow:var(--shadow-sm);
    transition:all .18s ease!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab:before{
    content:counter(alunoTab)!important;
    counter-increment:alunoTab!important;
    width:26px!important;
    height:26px!important;
    border-radius:var(--radius-sm)!important;
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    background:var(--color-slate-100)!important;
    color:var(--color-slate-500)!important;
    font-size:12px!important;
    font-weight:700!important;
    flex-shrink:0!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tabs{counter-reset:alunoTab;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab:hover{
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.18)!important;
    transform:translateY(-1px)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab.active{
    background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-brand-700)))!important;
    color:var(--color-white)!important;
    border-color:transparent!important;
    box-shadow:var(--shadow-md);
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab.active:before{
    background:rgba(255,255,255,.20)!important;
    color:var(--color-white)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab.tab-health{color:var(--color-slate-600)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab.tab-health.active{color:var(--color-white)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-content > form#form-aluno{
    flex:1!important;
    min-height:0!important;
    display:flex!important;
    flex-direction:column!important;
    overflow:hidden!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-body{
    flex:1!important;
    min-height:0!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
    padding:24px 28px!important;
    background:linear-gradient(180deg,var(--color-white) 0%,var(--color-white) 100%)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro #tab-dados > div:first-child{
    display:grid!important;
    grid-template-columns:190px minmax(0,1fr)!important;
    gap:22px!important;
    align-items:start!important;
    margin-bottom:20px!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro #tab-dados > div:first-child > div:first-child{
    width:auto!important;
    text-align:center!important;
    background:var(--color-white)!important;
    border:1px solid rgba(30,34,60,.08)!important;
    border-radius:var(--radius-xl)!important;
    padding:18px!important;
    box-shadow:var(--shadow-md);
    position:sticky!important;
    top:0!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-foto-upload{
    width:142px!important;
    height:142px!important;
    margin:0 auto!important;
    border-radius:var(--radius-xl)!important;
    border:2px dashed rgba(var(--sg-theme-primary-rgb,90,63,214),.24)!important;
    background:linear-gradient(135deg,var(--color-white),var(--sg-theme-soft,var(--color-brand-50)))!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-foto-upload svg{
    width:42px!important;
    height:42px!important;
    stroke:currentColor!important;
    opacity:.42!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-foto-upload .overlay{
    display:block!important;
    background:rgba(var(--sg-theme-primary-rgb,90,63,214),.92)!important;
    color:var(--color-white)!important;
    font-size:var(--fs-xs)!important;
    letter-spacing:.04em!important;
    padding:9px 0!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-boletim-section{
    border:1px solid rgba(30,34,60,.08)!important;
    border-radius:var(--radius-xl)!important;
    padding:26px!important;
    margin:0 0 var(--space-5)!important;
    background:var(--color-white)!important;
    box-shadow:var(--shadow-md);
    overflow:visible!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-boletim-section.highlight{
    background:linear-gradient(135deg,var(--color-white) 0%,var(--sg-theme-soft,var(--color-brand-50)) 170%)!important;
    border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.16)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-boletim-title{
    position:static!important;
    display:inline-flex!important;
    align-items:center!important;
    gap:var(--space-2)!important;
    padding:0!important;
    margin:0 0 18px!important;
    background:transparent!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    font-size:12px!important;
    line-height:1.2!important;
    font-weight:700!important;
    letter-spacing:.12em!important;
    text-transform:uppercase!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-boletim-title:before{
    content:""!important;
    width:10px!important;
    height:10px!important;
    border-radius:var(--radius-pill)!important;
    background:var(--sg-theme-primary,var(--color-brand-500))!important;
    box-shadow:var(--shadow-xs);
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row{
    display:grid!important;
    gap:var(--space-4)!important;
    margin-bottom:16px!important;
    min-width:0!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row:last-child{margin-bottom:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-2{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-3{grid-template-columns:repeat(3,minmax(0,1fr))!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-4{grid-template-columns:repeat(4,minmax(0,1fr))!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-guardian-advanced-box{
    border-color:rgba(124,58,237,.20)!important;
    background:linear-gradient(135deg,var(--color-white) 0%,var(--color-slate-50) 100%)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-guardian-note{
    border:1px solid rgba(124,58,237,.14)!important;
    background:rgba(124,58,237,.06)!important;
    color:var(--color-ink-700)!important;
    border-radius:var(--radius-lg)!important;
    padding:12px 14px!important;
    font-size:var(--fs-sm)!important;
    line-height:1.55!important;
    margin-bottom:16px!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-consent-grid{
    display:grid!important;
    grid-template-columns:repeat(4,minmax(0,1fr))!important;
    gap:var(--space-3)!important;
    margin-top:8px!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-consent-grid label{
    display:flex!important;
    align-items:center!important;
    gap:10px!important;
    min-height:52px!important;
    padding:var(--space-3)!important;
    border-radius:var(--radius-lg)!important;
    background:var(--color-white)!important;
    border:1px solid var(--color-ink-100)!important;
    color:var(--color-ink-900)!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
    box-shadow:var(--shadow-sm);
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-consent-grid input{
    width:18px!important;
    height:18px!important;
    min-height:18px!important;
    accent-color:var(--sg-theme-primary,var(--color-brand-500))!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-guardian-other-row.is-required{
    border:1px dashed rgba(124,58,237,.22)!important;
    background:rgba(124,58,237,.035)!important;
    border-radius:var(--radius-lg)!important;
    padding:var(--space-3)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-span-2{grid-column:span 2!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group{gap:var(--space-2)!important;min-width:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group label{
    color:var(--color-slate-700)!important;
    font-size:12px!important;
    font-weight:700!important;
    letter-spacing:.075em!important;
    text-transform:uppercase!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group input,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group select,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group textarea{
    width:100%!important;
    min-width:0!important;
    min-height:50px!important;
    border-radius:var(--radius-lg)!important;
    border:1px solid var(--color-ink-100)!important;
    background:var(--color-white)!important;
    color:var(--color-ink-900)!important;
    font-size:15px!important;
    font-weight:600!important;
    box-shadow:none!important;
    padding:0 var(--space-4)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group textarea{padding:14px 16px!important;line-height:1.55!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group input:focus,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group select:focus,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-group textarea:focus{
    border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.38)!important;
    box-shadow:var(--shadow-xs);
    outline:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-checkbox-row,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-extras-grid{
    display:grid!important;
    grid-template-columns:repeat(2,minmax(0,1fr))!important;
    gap:var(--space-3)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-checkbox-row{
    background:var(--color-slate-50)!important;
    border:1px solid var(--color-slate-100)!important;
    border-radius:var(--radius-lg)!important;
    padding:14px!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-checkbox-row label,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-extras-grid label{
    min-height:38px!important;
    border-radius:var(--radius-md)!important;
    padding:8px 10px!important;
    color:var(--color-ink-800)!important;
    font-weight:600!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro input[type="checkbox"],
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro input[type="radio"]{
    accent-color:var(--sg-theme-primary,var(--color-brand-500))!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-extras-box,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-creche-box{
    border:1px dashed rgba(var(--sg-theme-primary-rgb,90,63,214),.28)!important;
    background:linear-gradient(135deg,var(--color-white),var(--sg-theme-soft,var(--color-brand-50)) 180%)!important;
    border-radius:var(--radius-xl)!important;
    padding:18px!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-upload-area{
    grid-template-columns:repeat(3,minmax(0,1fr))!important;
    gap:var(--space-4)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box{
    min-height:148px!important;
    border-radius:var(--radius-xl)!important;
    border:1.5px dashed rgba(var(--sg-theme-primary-rgb,90,63,214),.24)!important;
    background:var(--color-white)!important;
    box-shadow:var(--shadow-sm);
    display:flex!important;
    flex-direction:column!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
    padding:18px!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box:hover{
    transform:translateY(-1px)!important;
    background:var(--sg-theme-soft,var(--color-brand-50))!important;
    border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.45)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box svg{
    width:34px!important;
    height:34px!important;
    margin:0!important;
    stroke:var(--sg-theme-primary,var(--color-brand-500))!important;
    opacity:.95!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box span{
    color:var(--color-ink-900)!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box span:after{
    content:"Carregar documento";
    display:block;
    margin-top:8px;
    color:var(--sg-theme-primary,var(--color-brand-500));
    font-size:var(--fs-xs);
    font-weight:700;
    letter-spacing:.02em;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box.has-file{
    border-color:rgba(52,168,83,.42)!important;
    background:var(--color-success-50)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box.has-file svg{stroke:var(--color-success-500)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-box.has-file span:after{
    content:"Documento carregado - alterar";
    color:var(--color-success-500);
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-footer{
    background:var(--color-white)!important;
    border-top:1px solid rgba(30,34,60,.08)!important;
    padding:18px 28px!important;
    display:flex!important;
    justify-content:flex-end!important;
    gap:var(--space-3)!important;
    box-shadow:var(--shadow-md);
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-modal{
    min-height:50px!important;
    border-radius:var(--radius-lg)!important;
    padding:0 var(--space-6)!important;
    font-size:var(--fs-base)!important;
    font-weight:700!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    gap:var(--space-2)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-cancel{
    background:var(--color-white)!important;
    color:var(--color-slate-700)!important;
    border:1px solid var(--color-ink-100)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-submit{
    min-width:190px!important;
    background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-brand-700)))!important;
    color:var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-submit svg,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-cancel svg{
    width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;
}
@media (max-width:1100px){
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-3,
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-4{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-consent-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tabs{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
}
@media (max-width:820px){
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro{padding:10px!important;align-items:flex-start!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-content{width:calc(100vw - 20px)!important;height:calc(100vh - 20px)!important;max-height:calc(100vh - 20px)!important;border-radius:var(--radius-xl)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-header{padding:18px 18px!important;min-height:auto!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-title-icon{width:46px!important;height:46px!important;border-radius:var(--radius-lg)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-header h2{font-size:21px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-title-text p{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tabs{display:flex!important;overflow-x:auto!important;padding:var(--space-3) var(--space-4)!important;gap:var(--space-2)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab{min-width:190px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-body{padding:18px 16px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro #tab-dados > div:first-child{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro #tab-dados > div:first-child > div:first-child{position:relative!important;top:auto!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-2,
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-3,
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-field-row-4{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-consent-grid{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-span-2{grid-column:auto!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-checkbox-row,
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-extras-grid,
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-doc-upload-area{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-footer{padding:14px 16px!important;flex-direction:column-reverse!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-modal{width:100%!important;}
}


/* v12.10.96 - Ajuste laptop 17" / 1366-1600px: evita cartões e filtros comprimidos */
@media (max-width:1600px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{padding:18px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-stats-grid{
        grid-template-columns:repeat(4,minmax(0,1fr))!important;
        gap:14px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-stat-card{
        min-width:0!important;
        padding:18px 16px!important;
        gap:var(--space-3)!important;
        align-items:center!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-stat-icon{
        width:50px!important;
        height:50px!important;
        flex:0 0 50px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-stat-info{min-width:0!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-stat-label,
    body.sige-admin-app.sige-view-alunos_lista .sige-stat-note{
        overflow-wrap:anywhere!important;
        word-break:normal!important;
        line-height:1.35!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-stat-value{
        font-size:clamp(22px,2vw,30px)!important;
        line-height:1.05!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-toolbar{
        display:grid!important;
        grid-template-columns:minmax(260px,1.3fr) minmax(170px,.75fr) minmax(170px,.75fr) repeat(3,minmax(132px,auto))!important;
        gap:var(--space-3)!important;
        align-items:center!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-search-box,
    body.sige-admin-app.sige-view-alunos_lista .sige-filter-select,
    body.sige-admin-app.sige-view-alunos_lista .sige-btn-toolbar{
        min-width:0!important;
        width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-btn-toolbar{
        justify-content:center!important;
        text-align:center!important;
        white-space:normal!important;
        line-height:1.2!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-grid{
        grid-template-columns:repeat(auto-fill,minmax(330px,1fr))!important;
        gap:18px!important;
    }
}
@media (max-width:1360px){
    body.sige-admin-app.sige-view-alunos_lista .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-toolbar{
        grid-template-columns:1fr 1fr!important;
    }
}
@media (max-width:820px){
    body.sige-admin-app.sige-view-alunos_lista .sige-toolbar{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-grid{grid-template-columns:1fr!important;}
}


/* v12.11.9.38 - Importação de estudantes em duas etapas: visual Produto PRO */
body.sige-admin-app.sige-view-alunos_lista .sige-btn-hero-import{
    background:linear-gradient(135deg,var(--color-success-600),var(--color-success-500))!important;
    color:var(--color-white)!important;
    border-color:rgba(16,185,129,.25)!important;
    box-shadow:var(--shadow-md);
}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-content{
    max-width:920px!important;
    width:min(920px,calc(100vw - 32px))!important;
    height:auto!important;
    max-height:92vh!important;
    border-radius:var(--radius-xl)!important;
    background:var(--color-white)!important;
    border:1px solid rgba(226,232,240,.95)!important;
    box-shadow:var(--shadow-lg);
}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-header{
    min-height:120px!important;
    padding:24px 28px!important;
    background:linear-gradient(135deg,var(--color-slate-50) 0%,var(--color-white) 62%,var(--color-success-100) 100%)!important;
    color:var(--color-black)!important;
    border-bottom:1px solid rgba(226,232,240,.85)!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-title-wrap{display:flex!important;align-items:center!important;gap:var(--space-4)!important;min-width:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-title-icon{width:58px!important;height:58px!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,var(--color-success-100),var(--color-success-50))!important;color:var(--color-success-500)!important;display:flex!important;align-items:center!important;justify-content:center!important;box-shadow:var(--shadow-md);}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-title-icon svg{width:28px!important;height:28px!important;stroke:currentColor!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-kicker{display:block!important;font-size:12px!important;font-weight:700!important;letter-spacing:.12em!important;text-transform:uppercase!important;color:var(--color-success-500)!important;margin-bottom:4px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-header h2{font-size:26px!important;line-height:1.1!important;color:var(--color-black)!important;margin:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-title-text p{margin:7px 0 0!important;color:var(--color-slate-500)!important;font-size:var(--fs-base)!important;line-height:1.45!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-close{background:var(--color-white)!important;color:var(--color-slate-800)!important;border:1px solid rgba(226,232,240,.95)!important;box-shadow:var(--shadow-sm);}
body.sige-admin-app.sige-view-alunos_lista .sige-import-modal-pro .sige-modal-body{padding:24px 28px!important;overflow-y:auto!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-grid{display:grid!important;grid-template-columns:1.1fr .9fr!important;gap:18px!important;align-items:stretch!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-card{border:1px solid rgba(226,232,240,.95)!important;background:var(--color-white)!important;border-radius:var(--radius-xl)!important;padding:var(--space-5)!important;box-shadow:var(--shadow-md);}
body.sige-admin-app.sige-view-alunos_lista .sige-import-card h3{margin:0 0 var(--space-3)!important;font-size:var(--fs-md)!important;color:var(--color-black)!important;font-weight:700!important;display:flex!important;gap:var(--space-2)!important;align-items:center!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-card p{margin:0 0 var(--space-3)!important;color:var(--color-slate-500)!important;font-size:var(--fs-sm)!important;line-height:1.55!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-field{display:flex!important;flex-direction:column!important;gap:7px!important;margin-bottom:14px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-field label{font-size:12px!important;font-weight:400!important;letter-spacing:.07em!important;text-transform:uppercase!important;color:var(--color-slate-500)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-field input[type="file"],
body.sige-admin-app.sige-view-alunos_lista .sige-import-field input[type="text"],
body.sige-admin-app.sige-view-alunos_lista .sige-import-field select{width:100%!important;min-height:48px!important;border-radius:var(--radius-lg)!important;border:1px solid var(--color-info-100)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;font-weight:600!important;padding:10px 14px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-check{display:flex!important;align-items:flex-start!important;gap:10px!important;padding:14px!important;border-radius:var(--radius-lg)!important;background:var(--color-warning-50)!important;border:1px solid var(--color-warning-300)!important;color:var(--color-warning-900)!important;font-size:var(--fs-sm)!important;line-height:1.45!important;font-weight:600!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-check input{margin-top:2px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-template-list{margin:0!important;padding-left:18px!important;color:var(--color-slate-800)!important;font-size:var(--fs-sm)!important;line-height:1.65!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-template-list strong{color:var(--color-black)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-template-actions{display:grid!important;grid-template-columns:1fr 1fr!important;gap:10px!important;margin-top:16px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-template-actions .sige-btn-modal{width:100%!important;min-height:42px!important;}
@media (max-width:640px){body.sige-admin-app.sige-view-alunos_lista .sige-import-template-actions{grid-template-columns:1fr!important;}}
body.sige-admin-app.sige-view-alunos_lista .sige-import-actions{display:flex!important;gap:var(--space-3)!important;align-items:center!important;justify-content:flex-end!important;padding:18px 28px!important;background:var(--color-slate-50)!important;border-top:1px solid rgba(226,232,240,.9)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-result{display:none;margin-top:18px!important;border-radius:var(--radius-xl)!important;border:1px solid var(--color-info-100)!important;background:var(--color-slate-50)!important;padding:var(--space-4)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-result.active{display:block!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-result-grid{display:grid!important;grid-template-columns:repeat(4,1fr)!important;gap:10px!important;margin-bottom:14px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-result-kpi{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;border-radius:var(--radius-lg)!important;padding:var(--space-3)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-result-kpi span{display:block!important;font-size:var(--fs-xs)!important;text-transform:uppercase!important;letter-spacing:.08em!important;color:var(--color-slate-500)!important;font-weight:400!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-result-kpi strong{display:block!important;font-size:var(--fs-xl)!important;color:var(--color-black)!important;margin-top:3px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-detail-list{max-height:180px!important;overflow:auto!important;border-radius:var(--radius-md)!important;background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;padding:var(--space-2)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-detail-item{display:grid!important;grid-template-columns:76px 110px 1fr!important;gap:var(--space-2)!important;align-items:start!important;padding:var(--space-2)!important;border-bottom:1px solid var(--color-ink-50)!important;font-size:12px!important;color:var(--color-slate-800)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-detail-item:last-child{border-bottom:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status{display:inline-flex!important;align-items:center!important;justify-content:center!important;min-height:24px!important;border-radius:var(--radius-pill)!important;padding:3px 8px!important;font-weight:700!important;font-size:var(--fs-xs)!important;text-transform:uppercase!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.importado{background:var(--color-success-100)!important;color:var(--color-success-900)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.pendente{background:var(--color-warning-200)!important;color:var(--color-warning-800)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.duplicado{background:var(--color-info-50)!important;color:var(--color-info-700)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.erro{background:var(--color-danger-100)!important;color:var(--color-danger-700)!important;}

body.sige-admin-app.sige-view-alunos_lista .sige-import-status.validado{background:var(--color-success-50)!important;color:var(--color-success-800)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.confirmado{background:var(--color-success-100)!important;color:var(--color-success-900)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-stage-note{display:flex!important;gap:10px!important;align-items:flex-start!important;margin:0 0 var(--space-4)!important;padding:14px!important;border-radius:var(--radius-lg)!important;background:var(--color-info-50)!important;border:1px solid var(--color-info-200)!important;color:var(--color-info-800)!important;font-size:var(--fs-sm)!important;line-height:1.5!important;font-weight:700!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-stage-note svg{width:18px!important;height:18px!important;min-width:18px!important;margin-top:1px!important;stroke:currentColor!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-batch{display:inline-flex!important;align-items:center!important;gap:7px!important;margin-top:8px!important;padding:8px 11px!important;border-radius:var(--radius-pill)!important;background:var(--color-ink-50)!important;color:var(--color-slate-800)!important;border:1px solid var(--color-ink-100)!important;font-size:12px!important;font-weight:700!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-panel{margin-top:16px!important;border:1px solid var(--color-danger-200)!important;background:linear-gradient(135deg,var(--color-warning-50),var(--color-white))!important;border-radius:var(--radius-xl)!important;padding:var(--space-4)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-panel h4{display:flex!important;align-items:center!important;gap:var(--space-2)!important;margin:0 0 var(--space-2)!important;color:var(--color-danger-700)!important;font-size:var(--fs-base)!important;font-weight:700!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-panel h4 svg{width:17px!important;height:17px!important;stroke:currentColor!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-panel p{margin:0 0 var(--space-3)!important;color:var(--color-danger-800)!important;font-size:12px!important;line-height:1.5!important;font-weight:600!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-row{display:grid!important;grid-template-columns:minmax(0,1fr) auto!important;gap:10px!important;align-items:end!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-btn-danger-soft{background:var(--color-white)!important;color:var(--color-danger-700)!important;border:1px solid var(--color-danger-200)!important;box-shadow:var(--shadow-sm);}
body.sige-admin-app.sige-view-alunos_lista .sige-btn-danger-soft:hover{background:var(--color-danger-100)!important;color:var(--color-danger-800)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-note{display:flex!important;gap:var(--space-2)!important;align-items:flex-start!important;margin-top:12px!important;padding:var(--space-3)!important;border-radius:var(--radius-lg)!important;background:var(--color-danger-50)!important;border:1px solid var(--color-danger-200)!important;color:var(--color-danger-700)!important;font-size:12px!important;font-weight:700!important;line-height:1.45!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-note svg{width:16px!important;height:16px!important;min-width:16px!important;margin-top:1px!important;stroke:currentColor!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-panel{margin-top:16px!important;border:1px solid rgba(226,232,240,.95)!important;background:linear-gradient(135deg,var(--color-white),var(--color-slate-50))!important;border-radius:var(--radius-xl)!important;padding:var(--space-4)!important;box-shadow:var(--shadow-md);}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-head{display:flex!important;align-items:flex-start!important;justify-content:space-between!important;gap:14px!important;margin-bottom:12px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-head h4{display:flex!important;align-items:center!important;gap:var(--space-2)!important;margin:0 0 5px!important;color:var(--color-black)!important;font-size:15px!important;font-weight:700!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-head h4 svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:var(--color-brand-500)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-head p{margin:0!important;color:var(--color-slate-500)!important;font-size:12px!important;line-height:1.45!important;font-weight:400!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-refresh{min-height:38px!important;border-radius:var(--radius-md)!important;padding:var(--space-2) var(--space-3)!important;white-space:nowrap!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-list{border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;border-radius:var(--radius-lg)!important;overflow:hidden!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-empty,body.sige-admin-app.sige-view-alunos_lista .sige-import-history-loading{padding:18px!important;color:var(--color-slate-500)!important;font-size:var(--fs-sm)!important;font-weight:400!important;text-align:center!important;background:var(--color-white)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-row{display:grid!important;grid-template-columns:minmax(160px,.9fr) minmax(210px,1.2fr) minmax(130px,.8fr) minmax(160px,.9fr) auto!important;gap:var(--space-3)!important;align-items:center!important;padding:13px 14px!important;border-bottom:1px solid var(--color-ink-50)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-row:last-child{border-bottom:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-date{font-size:12px!important;color:var(--color-slate-500)!important;font-weight:400!important;line-height:1.35!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-date small{display:block!important;color:var(--color-slate-400)!important;font-weight:700!important;margin-top:3px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-main{min-width:0!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-lote{display:block!important;color:var(--color-black)!important;font-size:var(--fs-sm)!important;font-weight:700!important;word-break:break-all!important;line-height:1.35!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-turma{display:block!important;color:var(--color-slate-500)!important;font-size:12px!important;font-weight:400!important;margin-top:4px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-kpis{display:flex!important;gap:6px!important;flex-wrap:wrap!important;align-items:center!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-pill{display:inline-flex!important;align-items:center!important;gap:var(--space-1)!important;padding:5px 8px!important;border-radius:var(--radius-pill)!important;background:var(--color-slate-50)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-slate-800)!important;font-size:var(--fs-xs)!important;font-weight:700!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-pill strong{color:var(--color-black)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-meta{display:flex!important;flex-direction:column!important;gap:6px!important;align-items:flex-start!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-user{font-size:12px!important;color:var(--color-slate-700)!important;font-weight:700!important;line-height:1.35!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-user small{display:block!important;color:var(--color-slate-400)!important;font-weight:700!important;margin-top:2px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-actions{display:flex!important;gap:var(--space-2)!important;justify-content:flex-end!important;align-items:center!important;flex-wrap:wrap!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-history-actions .sige-btn-modal{min-height:34px!important;padding:7px 10px!important;border-radius:var(--radius-md)!important;font-size:12px!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.activo{background:var(--color-success-50)!important;color:var(--color-success-800)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.anulado{background:var(--color-ink-50)!important;color:var(--color-slate-700)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.sem_registos{background:var(--color-warning-200)!important;color:var(--color-warning-800)!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-import-status.reconstruido{background:var(--color-brand-100)!important;color:var(--color-brand-700)!important;}
@media (max-width:860px){
    body.sige-admin-app.sige-view-alunos_lista .sige-import-grid{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-result-grid{grid-template-columns:repeat(2,1fr)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-actions{flex-direction:column-reverse!important;align-items:stretch!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-detail-item{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-annul-row{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-history-head{flex-direction:column!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-history-refresh{width:100%!important;justify-content:center!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-history-row{grid-template-columns:1fr!important;align-items:start!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-import-history-actions{justify-content:flex-start!important;}
}

/* v12.11.9.42 - Hotfix definitivo: scroll real do modal de importação de estudantes
   Motivo: o modal tem histórico + anulação + resultado; por isso precisa de layout flex com
   header fixo, corpo rolável e rodapé sempre visível dentro do viewport. */
body.sige-admin-app.sige-view-alunos_lista.sige-aluno-modal-open{
    overflow:hidden!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro{
    position:fixed!important;
    inset:0!important;
    align-items:center!important;
    justify-content:center!important;
    padding:var(--space-3)!important;
    overflow:hidden!important;
    z-index:130000!important;
    background:rgba(15,23,42,.62)!important;
    backdrop-filter:blur(12px)!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-content{
    width:min(1120px,calc(100vw - 24px))!important;
    max-width:1120px!important;
    height:calc(100vh - 24px)!important;
    height:calc(100dvh - 24px)!important;
    max-height:calc(100vh - 24px)!important;
    max-height:calc(100dvh - 24px)!important;
    min-height:0!important;
    display:flex!important;
    flex-direction:column!important;
    overflow:hidden!important;
    margin:0!important;
    border-radius:var(--radius-xl)!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-header{
    flex:0 0 auto!important;
    min-height:0!important;
    padding:18px 28px!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-title-text p{
    max-width:780px!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro #form-import-alunos{
    display:flex!important;
    flex-direction:column!important;
    flex:1 1 auto!important;
    min-height:0!important;
    height:auto!important;
    overflow:hidden!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-body{
    flex:1 1 auto!important;
    min-height:0!important;
    max-height:none!important;
    overflow-y:auto!important;
    overflow-x:hidden!important;
    -webkit-overflow-scrolling:touch!important;
    overscroll-behavior:contain!important;
    padding:22px 28px 26px!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-body::-webkit-scrollbar{
    width:10px!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-body::-webkit-scrollbar-track{
    background:var(--color-ink-50)!important;
    border-radius:var(--radius-pill)!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-body::-webkit-scrollbar-thumb{
    background:var(--color-ink-200)!important;
    border-radius:var(--radius-pill)!important;
    border:2px solid var(--color-ink-50)!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-body::-webkit-scrollbar-thumb:hover{
    background:var(--color-slate-400)!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-import-grid{
    align-items:start!important;
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-import-actions{
    flex:0 0 auto!important;
    position:relative!important;
    z-index:10!important;
    padding:14px 28px!important;
    background:var(--color-white)!important;
    border-top:1px solid rgba(226,232,240,.95)!important;
    box-shadow:var(--shadow-md);
}
body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-import-actions .sige-btn-modal{
    min-height:44px!important;
    white-space:normal!important;
}
@media (max-width:860px){
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro{
        padding:var(--space-2)!important;
        align-items:stretch!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-content{
        width:calc(100vw - 16px)!important;
        height:calc(100vh - 16px)!important;
        height:calc(100dvh - 16px)!important;
        max-height:calc(100vh - 16px)!important;
        max-height:calc(100dvh - 16px)!important;
        border-radius:var(--radius-xl)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-header{
        padding:14px 16px!important;
        gap:10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-title-wrap{
        gap:10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-title-icon{
        width:44px!important;
        height:44px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-title-icon svg{
        width:22px!important;
        height:22px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-header h2{
        font-size:19px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-title-text p{
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-modal-body{
        padding:14px!important;
        padding-bottom:18px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-import-actions{
        padding:10px 14px!important;
        flex-direction:column-reverse!important;
        align-items:stretch!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro .sige-import-actions .sige-btn-modal{
        width:100%!important;
        justify-content:center!important;
    }
}


/* v12.11.9.45 - Hotfix definitivo da confirmação da importação
   Garante que popups de confirmação ficam acima do modal de importação e que a Etapa 2 mostra feedback visível. */
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-popup{
    z-index:170000!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-popup.is-open{
    display:flex!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-import-processing-note{
    display:flex!important;
    align-items:center!important;
    gap:10px!important;
    padding:13px 16px!important;
    border-radius:var(--radius-lg)!important;
    border:1px solid rgba(109,93,252,.20)!important;
    background:linear-gradient(135deg,var(--color-brand-50),var(--color-info-50))!important;
    color:var(--color-brand-800)!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
    line-height:1.45!important;
    margin:0 0 14px!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-import-processing-note svg{
    width:18px!important;
    height:18px!important;
    flex:0 0 auto!important;
    animation:spin 1s linear infinite!important;
}


body.sige-admin-app.sige-view-alunos_lista .sige-360-time strong{display:block;color:var(--color-black);font-size:var(--fs-base);}
body.sige-admin-app.sige-view-alunos_lista .sige-360-mini-changes{display:grid;gap:5px;margin-top:8px;padding-top:8px;border-top:1px dashed rgba(100,116,139,.22);}
body.sige-admin-app.sige-view-alunos_lista .sige-360-mini-changes small{display:block;color:var(--color-slate-500);font-size:12px;line-height:1.4;}
body.sige-admin-app.sige-view-alunos_lista .sige-360-mini-changes b{color:var(--color-slate-800);}
/* v12.11.9.46 - Ficha 360º do Aluno + Índice de Qualidade dos Dados */
.sige-alunos-page .sige-data-quality-badge{
    display:inline-flex!important;align-items:center!important;gap:6px!important;border:0!important;border-radius:var(--radius-pill)!important;padding:6px 10px!important;font-size:var(--fs-xs)!important;line-height:1!important;font-weight:700!important;cursor:pointer!important;background:var(--color-ink-50)!important;color:var(--color-slate-800)!important;box-shadow:none!important;transition:transform .16s ease,box-shadow .16s ease!important;
}
.sige-alunos-page .sige-data-quality-badge:hover{transform:translateY(-1px)!important;box-shadow:var(--shadow-sm);}
.sige-alunos-page .sige-data-quality-badge svg{width:13px!important;height:13px!important;stroke:currentColor!important;fill:none!important;}
.sige-alunos-page .sige-data-quality-completa{background:var(--color-success-100)!important;color:var(--color-success-800)!important;}
.sige-alunos-page .sige-data-quality-pendente{background:var(--color-warning-50)!important;color:var(--color-danger-600)!important;}
.sige-alunos-page .sige-data-quality-critica{background:var(--color-danger-100)!important;color:var(--color-danger-700)!important;}
.sige-alunos-page .sige-actions-menu .btn-360{color:var(--color-brand-500)!important;background:var(--color-brand-50)!important;border-color:var(--color-brand-50)!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360.sige-aluno360-modal-pro{z-index:135000!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360.sige-aluno360-modal-pro .sige-modal-content{width:min(1180px,calc(100vw - 28px))!important;max-width:1180px!important;height:calc(100vh - 28px)!important;max-height:calc(100vh - 28px)!important;border-radius:var(--radius-xl)!important;background:var(--color-slate-50)!important;border:1px solid rgba(226,232,240,.95)!important;box-shadow:var(--shadow-lg);}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-header{min-height:118px!important;background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 52%,var(--color-brand-50) 100%)!important;color:var(--color-black)!important;border-bottom:1px solid var(--color-ink-100)!important;padding:24px 28px!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-title-wrap{display:flex!important;align-items:center!important;gap:var(--space-4)!important;min-width:0!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-title-icon{width:58px!important;height:58px!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,var(--color-brand-100),var(--color-brand-50))!important;color:var(--color-brand-500)!important;display:flex!important;align-items:center!important;justify-content:center!important;box-shadow:var(--shadow-md);}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-title-icon svg{width:28px!important;height:28px!important;stroke:currentColor!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-kicker{display:block!important;font-size:12px!important;font-weight:700!important;letter-spacing:.12em!important;text-transform:uppercase!important;color:var(--color-brand-500)!important;margin-bottom:4px!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-header h2{font-size:26px!important;line-height:1.1!important;color:var(--color-black)!important;margin:0!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-title-text p{margin:7px 0 0!important;color:var(--color-slate-500)!important;font-size:var(--fs-base)!important;line-height:1.45!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-close{background:var(--color-white)!important;color:var(--color-slate-800)!important;border:1px solid rgba(226,232,240,.95)!important;box-shadow:var(--shadow-sm);}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-body{padding:24px 28px!important;overflow-y:auto!important;}
body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-footer{padding:16px 28px!important;background:var(--color-white)!important;border-top:1px solid var(--color-ink-100)!important;}
.sige-360-loading{display:flex;align-items:center;justify-content:center;gap:var(--space-3);min-height:360px;color:var(--color-brand-700);font-weight:700;}
.sige-360-spinner{width:22px;height:22px;border-radius:var(--radius-pill);border:3px solid var(--color-info-100);border-top-color:var(--color-brand-400);animation:spin .8s linear infinite;}
.sige-360-hero-card{display:grid;grid-template-columns:auto minmax(0,1fr) auto;gap:var(--space-5);align-items:center;background:var(--color-white);border:1px solid var(--color-ink-100);border-radius:var(--radius-xl);padding:var(--space-5);box-shadow:var(--shadow-md);margin-bottom:18px;}
.sige-360-photo{width:92px;height:92px;border-radius:var(--radius-xl);object-fit:cover;border:4px solid var(--color-white);box-shadow:var(--shadow-md);background:var(--color-ink-50);}
.sige-360-kicker{display:block;color:var(--color-brand-500);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;}
.sige-360-name{margin:0;color:var(--color-black);font-size:26px;line-height:1.08;font-weight:700;letter-spacing:-.04em;}
.sige-360-meta{display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:10px;}
.sige-360-pill{display:inline-flex;align-items:center;gap:6px;border-radius:var(--radius-pill);background:var(--color-slate-50);border:1px solid var(--color-ink-100);color:var(--color-slate-700);padding:7px 10px;font-size:12px;font-weight:700;}
.sige-360-score{display:flex;align-items:center;gap:var(--space-3);border-radius:var(--radius-xl);padding:12px 14px;background:var(--color-slate-50);border:1px solid var(--color-ink-100);min-width:190px;}
.sige-360-score-circle{width:74px;height:74px;border-radius:var(--radius-pill);display:flex;align-items:center;justify-content:center;background:conic-gradient(var(--score-color,var(--color-brand-400)) calc(var(--score,0)*1%),var(--color-ink-100) 0);position:relative;flex:0 0 auto;}
.sige-360-score-circle:after{content:"";position:absolute;inset:7px;border-radius:var(--radius-pill);background:var(--color-white);}
.sige-360-score-circle strong{position:relative;z-index:1;color:var(--color-black);font-size:18px;font-weight:700;}
.sige-360-score-text strong{display:block;color:var(--color-black);font-size:15px;font-weight:700;}
.sige-360-score-text span{display:block;color:var(--color-slate-500);font-size:12px;font-weight:400;margin-top:3px;}
.sige-360-score.completa{--score-color:var(--color-success-500);}.sige-360-score.pendente{--score-color:var(--color-warning-500);}.sige-360-score.critica{--score-color:var(--color-danger-600);}
.sige-360-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:14px;margin-bottom:18px;}
.sige-360-kpi{background:var(--color-white);border:1px solid var(--color-ink-100);border-radius:var(--radius-xl);padding:var(--space-4);box-shadow:var(--shadow-md);}
.sige-360-kpi span{display:block;color:var(--color-slate-500);font-size:var(--fs-xs);font-weight:400;text-transform:uppercase;letter-spacing:.08em;margin-bottom:7px;}
.sige-360-kpi strong{display:block;color:var(--color-black);font-size:18px;font-weight:700;line-height:1.18;word-break:break-word;}
.sige-360-grid{display:grid;grid-template-columns:1.1fr .9fr;gap:18px;align-items:start;}
.sige-360-card{background:var(--color-white);border:1px solid var(--color-ink-100);border-radius:var(--radius-xl);padding:18px;box-shadow:var(--shadow-md);min-width:0;}
.sige-360-card h3{display:flex;align-items:center;gap:10px;margin:0 0 14px;color:var(--color-black);font-size:var(--fs-md);font-weight:700;letter-spacing:-.02em;}
.sige-360-card h3 svg{width:19px;height:19px;stroke:var(--color-brand-500);}
.sige-360-row{display:grid;grid-template-columns:175px minmax(0,1fr);gap:var(--space-3);padding:10px 0;border-top:1px solid var(--color-ink-50);align-items:start;}
.sige-360-row:first-of-type{border-top:0;}
.sige-360-row span{color:var(--color-slate-500);font-size:12px;font-weight:400;text-transform:uppercase;letter-spacing:.04em;}
.sige-360-row strong{color:var(--color-black);font-size:var(--fs-sm);font-weight:700;line-height:1.45;word-break:break-word;}
.sige-360-empty{padding:14px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px dashed var(--color-ink-200);color:var(--color-slate-500);font-size:var(--fs-sm);font-weight:400;line-height:1.5;}
.sige-360-pendencias{display:flex;flex-direction:column;gap:9px;}
.sige-360-pendencia{display:grid;grid-template-columns:auto minmax(0,1fr);gap:10px;align-items:start;padding:var(--space-3);border-radius:var(--radius-lg);background:var(--color-warning-50);border:1px solid var(--color-warning-200);color:var(--color-warning-900);}
.sige-360-pendencia.alto{background:var(--color-danger-50);border-color:var(--color-danger-200);color:var(--color-danger-700);}.sige-360-pendencia.baixo{background:var(--color-slate-50);border-color:var(--color-ink-100);color:var(--color-slate-800);}
.sige-360-pendencia-dot{width:10px;height:10px;border-radius:var(--radius-pill);background:currentColor;margin-top:5px;opacity:.75;}
.sige-360-pendencia strong{display:block;font-size:var(--fs-sm);font-weight:700;color:inherit;}.sige-360-pendencia small{display:block;margin-top:3px;font-size:12px;line-height:1.45;color:inherit;opacity:.82;}
.sige-360-doc-list,.sige-360-timeline{display:flex;flex-direction:column;gap:var(--space-2);}
.sige-360-doc{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:10px 12px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-ink-100);color:var(--color-slate-800);font-size:var(--fs-sm);font-weight:700;}
.sige-360-doc.ok{background:var(--color-success-50);border-color:var(--color-success-200);color:var(--color-success-900);}
.sige-360-doc a{color:var(--color-brand-500);text-decoration:none;font-weight:700;}
.sige-360-fin-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;margin-top:14px;padding-top:14px;border-top:1px dashed rgba(100,116,139,.24);}
.sige-360-fin-btn{display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);min-height:44px;border-radius:var(--radius-lg);padding:10px 13px;text-decoration:none!important;font-size:12px;font-weight:700;line-height:1.2;background:var(--color-slate-50);color:var(--color-slate-800)!important;border:1px solid var(--color-ink-100);box-shadow:var(--shadow-sm);}
.sige-360-fin-btn.primary{background:linear-gradient(135deg,var(--color-brand-500),var(--color-ink-700))!important;color:var(--color-white)!important;border-color:transparent!important;box-shadow:var(--shadow-sm);}
.sige-360-fin-btn:hover{transform:translateY(-1px);}
.sige-360-fin-note{margin-top:10px;padding:10px 12px;border-radius:var(--radius-md);background:var(--color-slate-50);border:1px dashed var(--color-info-100);color:var(--color-slate-500);font-size:12px;font-weight:400;line-height:1.45;}
.sige-alunos-page .sige-actions-menu .btn-fin-historico{color:var(--color-success-800)!important;background:var(--color-success-50)!important;border-color:var(--color-success-100)!important;}
.sige-mobile-fin-action{background:linear-gradient(135deg,var(--color-success-50),var(--color-success-50))!important;color:var(--color-success-800)!important;border-color:var(--color-success-100)!important;}
.sige-mobile-fin-action span,.btn-fin-historico .sige-action-label{font-weight:700;}
.sige-mobile-fin-action span:after,.btn-fin-historico .sige-action-label:after{content:"360º";display:inline-flex;margin-left:6px;padding:2px 6px;border-radius:var(--radius-pill);background:var(--color-success-100);color:var(--color-success-900);font-size:10px;font-weight:700;vertical-align:middle;}
.sige-btn-finance-history{background:linear-gradient(135deg,var(--color-success-800),var(--color-success-900))!important;color:var(--color-white)!important;border-color:transparent!important;}
@media (max-width:700px){.sige-360-fin-actions{grid-template-columns:1fr}.sige-360-fin-btn{width:100%;}.sige-btn-finance-history{width:100%!important;}}
.sige-360-time{display:grid;grid-template-columns:132px minmax(0,1fr);gap:10px;padding:10px 0;border-top:1px solid var(--color-ink-50);}
.sige-360-time:first-child{border-top:0;}.sige-360-time span{color:var(--color-slate-500);font-size:12px;font-weight:400;}.sige-360-time strong{color:var(--color-black);font-size:var(--fs-sm);font-weight:400;line-height:1.4;}
@media (max-width:1100px){.sige-360-grid{grid-template-columns:1fr}.sige-360-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.sige-360-hero-card{grid-template-columns:auto minmax(0,1fr)}.sige-360-score{grid-column:1/-1;width:100%;}}
@media (max-width:700px){body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-header{padding:18px!important;min-height:auto!important}.sige-360-hero-card{grid-template-columns:1fr;text-align:left}.sige-360-photo{width:84px;height:84px}.sige-360-kpi-grid{grid-template-columns:1fr}.sige-360-row,.sige-360-time{grid-template-columns:1fr;gap:4px}.sige-360-name{font-size:22px}.sige-360-score{min-width:0}.sige-360-card{padding:15px}}


/* v12.11.9.52 - UX Responsivo Alunos Mobile/Tablet PRO
   Camada final não destrutiva: melhora usabilidade em tablet/mobile, mantém regras de negócio intactas. */
body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle,
body.sige-admin-app.sige-view-alunos_lista .sige-mobile-stepbar,
body.sige-admin-app.sige-view-alunos_lista .sige-mobile-step-btn{
    display:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page button:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page a:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page input:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page select:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page textarea:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-modal button:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-modal a:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-modal input:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-modal select:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-modal textarea:focus-visible,
body.sige-admin-app.sige-view-alunos_lista .sige-tab:focus-visible{
    outline:3px solid rgba(var(--sg-theme-primary-rgb,90,63,214),.28)!important;
    outline-offset:3px!important;
    box-shadow:var(--shadow-xs);
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-toolbar,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-action,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-trigger,
body.sige-admin-app.sige-view-alunos_lista .sige-modal .sige-btn-modal,
body.sige-admin-app.sige-view-alunos_lista .sige-modal .sige-modal-close{
    touch-action:manipulation!important;
}
@media (max-width:1180px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{padding-left:22px!important;padding-right:22px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-content{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stats-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-grid{grid-template-columns:repeat(2,minmax(0,1fr))!important;}
}
@media (max-width:900px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions{
        position:static!important;
        width:100%!important;
        margin-top:12px!important;
        overflow:visible!important;
        z-index:50!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{
        width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-trigger{
        width:100%!important;
        min-height:46px!important;
        border-radius:var(--radius-lg)!important;
        justify-content:center!important;
        gap:var(--space-2)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-trigger:after{
        content:"Acções do aluno";
        font-size:var(--fs-sm)!important;
        font-weight:700!important;
        letter-spacing:.01em!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu{
        position:relative!important;
        top:auto!important;
        right:auto!important;
        min-width:0!important;
        max-width:none!important;
        width:100%!important;
        margin-top:10px!important;
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:var(--space-2)!important;
        padding:10px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu:before{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action{
        width:100%!important;
        height:46px!important;
        min-height:46px!important;
        padding:0 13px!important;
        justify-content:flex-start!important;
        gap:10px!important;
        border-radius:var(--radius-md)!important;
        text-decoration:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action:after{
        content:attr(title);
        font-size:var(--fs-sm)!important;
        font-weight:700!important;
        line-height:1.2!important;
        color:currentColor!important;
        white-space:normal!important;
        text-align:left!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action svg{
        flex:0 0 auto!important;
        width:19px!important;
        height:19px!important;
    }
}
@media (max-width:820px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{padding-left:14px!important;padding-right:14px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{gap:var(--space-4)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{padding:22px 18px!important;border-radius:var(--radius-xl)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{font-size:25px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{display:grid!important;grid-template-columns:1fr!important;gap:10px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{width:100%!important;min-height:48px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stats-grid,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-grid{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar{
        display:grid!important;
        grid-template-columns:minmax(0,1fr) auto!important;
        gap:10px!important;
        padding:14px!important;
        border-radius:var(--radius-xl)!important;
        align-items:center!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar .sige-search-box{
        grid-column:1/2!important;
        min-width:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle{
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        gap:var(--space-2)!important;
        min-height:46px!important;
        padding:0 14px!important;
        border-radius:var(--radius-md)!important;
        border:1px solid var(--color-ink-100)!important;
        background:var(--color-white)!important;
        color:var(--sg-theme-primary,var(--color-brand-500))!important;
        font-weight:700!important;
        cursor:pointer!important;
        box-shadow:var(--shadow-sm);
        grid-column:2/3!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle svg{width:18px!important;height:18px!important;stroke:currentColor!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > select,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > .sige-btn-toolbar,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > a.sige-btn-toolbar{
        display:none!important;
        grid-column:1/-1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar.sige-mobile-filters-open > select,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar.sige-mobile-filters-open > .sige-btn-toolbar,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar.sige-mobile-filters-open > a.sige-btn-toolbar{
        display:inline-flex!important;
        width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar.sige-mobile-filters-open > select{display:block!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-results-counter{align-items:flex-start!important;flex-direction:column!important;gap:5px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-pagination{overflow-x:auto!important;justify-content:flex-start!important;padding-bottom:4px!important;}

    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-stepbar{
        display:block!important;
        padding:var(--space-3) var(--space-4)!important;
        background:var(--color-white)!important;
        border-bottom:1px solid rgba(30,34,60,.08)!important;
        flex-shrink:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-stepbar-main{
        display:flex!important;
        align-items:center!important;
        justify-content:space-between!important;
        gap:var(--space-3)!important;
        margin-bottom:9px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-stepbar-label{
        color:var(--sg-theme-primary,var(--color-brand-500))!important;
        font-size:12px!important;
        font-weight:700!important;
        letter-spacing:.06em!important;
        text-transform:uppercase!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-stepbar-title{
        color:var(--color-black)!important;
        font-size:var(--fs-base)!important;
        font-weight:700!important;
        text-align:right!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-stepbar-track{
        height:8px!important;
        border-radius:var(--radius-pill)!important;
        background:var(--color-brand-100)!important;
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-stepbar-fill{
        height:100%!important;
        width:25%;
        border-radius:var(--radius-pill)!important;
        background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-brand-700)))!important;
        transition:width .2s ease!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tabs{
        display:flex!important;
        overflow-x:auto!important;
        padding:10px 16px!important;
        gap:var(--space-2)!important;
        scroll-snap-type:x proximity!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab{
        min-width:165px!important;
        min-height:42px!important;
        padding:0 var(--space-3)!important;
        scroll-snap-align:start!important;
        font-size:12px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-step-btn{
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        min-height:48px!important;
        border-radius:var(--radius-lg)!important;
        border:1px solid var(--color-ink-100)!important;
        background:var(--color-white)!important;
        color:var(--color-slate-700)!important;
        padding:0 18px!important;
        font-size:var(--fs-base)!important;
        font-weight:700!important;
        cursor:pointer!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-step-next{
        background:var(--sg-theme-soft,var(--color-brand-50))!important;
        color:var(--sg-theme-primary,var(--color-brand-500))!important;
        border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.16)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-mobile-step-btn[disabled]{opacity:.42!important;cursor:not-allowed!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-footer{
        display:grid!important;
        grid-template-columns:1fr 1fr!important;
        gap:10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-submit,
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-btn-cancel{
        grid-column:1/-1!important;
    }
}
@media (max-width:700px){
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-content{width:calc(100vw - 16px)!important;height:calc(100vh - 16px)!important;max-height:calc(100vh - 16px)!important;border-radius:var(--radius-xl)!important;}
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-body{padding:var(--space-4)!important;}
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-title-text p{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card{padding:0!important;overflow:hidden!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card > h3{
        margin:0!important;
        padding:var(--space-4)!important;
        cursor:pointer!important;
        min-height:54px!important;
        justify-content:space-between!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card > h3:after{
        content:"−";
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        width:26px!important;
        height:26px!important;
        border-radius:var(--radius-sm)!important;
        background:var(--color-brand-50)!important;
        color:var(--color-brand-500)!important;
        font-size:18px!important;
        line-height:1!important;
        font-weight:700!important;
        margin-left:auto!important;
        flex:0 0 auto!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card.is-collapsed > h3:after{content:"+";}
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card > :not(h3){margin-left:16px!important;margin-right:16px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card > :last-child{margin-bottom:16px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card.is-collapsed > :not(h3){display:none!important;}
}
@media (max-width:540px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{padding-left:10px!important;padding-right:10px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{padding:var(--space-5) var(--space-4)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-card{grid-template-columns:44px minmax(0,1fr)!important;padding:var(--space-4)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-icon{width:44px!important;height:44px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{padding:var(--space-4)!important;border-radius:var(--radius-xl)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-search-box input{font-size:var(--fs-sm)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle span{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-content{width:calc(100vw - 12px)!important;height:calc(100vh - 12px)!important;max-height:calc(100vh - 12px)!important;border-radius:var(--radius-lg)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-title-icon{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-header{padding:14px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-header h2{font-size:19px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tabs{padding:var(--space-2) var(--space-3)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-tab{min-width:150px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-modal-body{padding:14px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-aluno-modal-pro .sige-boletim-section{padding:18px!important;border-radius:var(--radius-xl)!important;}
}


/* v12.11.9.53 - Hotfix UX Mobile Cards de Alunos PRO
   Corrige o painel de acções no mobile/tablet: fechado por defeito, sem área branca gigante,
   sem marcador nativo do <summary>, com botões compactos e texto visível apenas em ecrãs tácteis. */
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-action-label,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-trigger-label{
    display:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page details.sige-card-actions:not([open]) > .sige-actions-menu{
    display:none!important;
    visibility:hidden!important;
    height:0!important;
    min-height:0!important;
    padding:0!important;
    margin:0!important;
    border:0!important;
    overflow:hidden!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{
    list-style:none!important;
    -webkit-appearance:none!important;
    appearance:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary::-webkit-details-marker{
    display:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary::marker{
    content:""!important;
    font-size:0!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-trigger svg{
    width:18px!important;
    height:18px!important;
    min-width:18px!important;
    min-height:18px!important;
    max-width:18px!important;
    max-height:18px!important;
    stroke:currentColor!important;
    fill:none!important;
    flex:0 0 auto!important;
}
@media (max-width:900px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        gap:13px!important;
        min-height:0!important;
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-info{
        padding-right:0!important;
        width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome{
        padding-right:0!important;
        margin-top:2px!important;
        margin-bottom:5px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-tags{
        gap:7px!important;
        align-items:flex-start!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-turma,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-status-badge,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-data-quality-badge,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-finance-badge,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-transporte,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-wa-btn{
        min-height:30px!important;
        padding:6px 10px!important;
        font-size:12px!important;
        line-height:1.1!important;
        max-width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions{
        position:static!important;
        display:block!important;
        width:100%!important;
        min-height:0!important;
        height:auto!important;
        margin-top:12px!important;
        padding:0!important;
        border:0!important;
        background:transparent!important;
        box-shadow:none!important;
        border-radius:0!important;
        overflow:visible!important;
        opacity:1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{
        display:flex!important;
        width:100%!important;
        min-height:44px!important;
        align-items:center!important;
        justify-content:center!important;
        gap:var(--space-2)!important;
        padding:0 14px!important;
        border-radius:var(--radius-lg)!important;
        border:1px solid rgba(226,232,240,.95)!important;
        background:var(--color-white)!important;
        color:var(--color-ink-900)!important;
        cursor:pointer!important;
        box-shadow:var(--shadow-sm);
        user-select:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions[open] > summary{
        color:var(--color-white)!important;
        border-color:transparent!important;
        background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-brand-700)))!important;
        box-shadow:var(--shadow-sm);
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-trigger:after{
        content:none!important;
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-trigger-label{
        display:inline!important;
        font-size:var(--fs-sm)!important;
        font-weight:700!important;
        line-height:1.1!important;
        letter-spacing:.01em!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page details.sige-card-actions[open] > .sige-actions-menu{
        position:static!important;
        display:grid!important;
        visibility:visible!important;
        height:auto!important;
        min-height:0!important;
        width:100%!important;
        min-width:0!important;
        max-width:none!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:var(--space-2)!important;
        margin:10px 0 0!important;
        padding:10px!important;
        border-radius:var(--radius-lg)!important;
        border:1px solid rgba(226,232,240,.95)!important;
        background:var(--color-white)!important;
        box-shadow:var(--shadow-sm);
        overflow:visible!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu:before{
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action{
        display:flex!important;
        width:100%!important;
        min-width:0!important;
        height:42px!important;
        min-height:42px!important;
        max-height:none!important;
        align-items:center!important;
        justify-content:flex-start!important;
        gap:var(--space-2)!important;
        padding:0 10px!important;
        border-radius:var(--radius-md)!important;
        border:1px solid var(--color-ink-100)!important;
        background:var(--color-white)!important;
        box-shadow:none!important;
        text-decoration:none!important;
        font-size:12px!important;
        line-height:1.15!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action:after{
        content:none!important;
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action svg{
        width:17px!important;
        height:17px!important;
        min-width:17px!important;
        min-height:17px!important;
        flex:0 0 auto!important;
        stroke:currentColor!important;
        fill:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-action-label{
        display:block!important;
        min-width:0!important;
        color:currentColor!important;
        font-size:12px!important;
        font-weight:700!important;
        line-height:1.15!important;
        white-space:normal!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .btn-360{
        grid-column:1/-1!important;
        background:var(--sg-theme-soft,var(--color-brand-50))!important;
        border-color:rgba(var(--sg-theme-primary-rgb,90,63,214),.16)!important;
        color:var(--sg-theme-primary,var(--color-brand-500))!important;
        justify-content:center!important;
    }
}
@media (max-width:460px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action{
        padding:0 9px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-action-label{
        font-size:11.5px!important;
    }
}
@media (max-width:370px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page details.sige-card-actions[open] > .sige-actions-menu{
        grid-template-columns:1fr!important;
    }
}


/* v12.11.9.54 - Mobile App Redesign Alunos PRO
   v12.11.9.55 - Smoke visual/funcional + refinamento de aderência mobile.
   Referência inegociável: screenshot mobile aprovado pelo utilizador.
   Camada estritamente visual/responsiva; preserva regras, permissões, AJAX e base de dados. */
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-head,
body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chips,
body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action,
body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav{
    display:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{
    display:contents!important;
}
@media (max-width:760px){
    body.sige-admin-app.sige-view-alunos_lista{
        background:var(--color-slate-50)!important;
        overflow-x:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{
        padding:0 0 calc(112px + env(safe-area-inset-bottom, 0px))!important;
        background:linear-gradient(180deg,var(--color-slate-50) 0%,var(--color-white) 100%)!important;
        min-height:100vh!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-page{
        padding:0!important;
        margin:0!important;
        max-width:none!important;
        width:100%!important;
        background:transparent!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar{
        position:sticky!important;
        top:0!important;
        z-index:10050!important;
        min-height:102px!important;
        height:102px!important;
        margin:0!important;
        padding:18px 18px 30px!important;
        border-radius:0 0 28px 28px!important;
        border:0!important;
        background:linear-gradient(135deg,var(--color-info-700) 0%,var(--color-info-700) 46%,var(--color-brand-500) 100%)!important;
        box-shadow:var(--shadow-md);
        color:var(--color-white)!important;
        display:flex!important;
        align-items:center!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-hamburger{
        width:42px!important;
        height:42px!important;
        min-width:42px!important;
        border:0!important;
        border-radius:var(--radius-md)!important;
        background:transparent!important;
        color:var(--color-white)!important;
        box-shadow:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-hamburger svg{width:28px!important;height:28px!important;stroke:var(--color-white)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-headings{
        display:flex!important;
        align-items:center!important;
        gap:var(--space-2)!important;
        min-width:0!important;
        flex:1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-headings:before{
        content:""!important;
        width:36px!important;
        height:36px!important;
        border-radius:var(--radius-md)!important;
        flex:0 0 auto!important;
        background:rgba(255,255,255,.16)!important;
        border:1px solid rgba(255,255,255,.22)!important;
        box-shadow:inset 0 0 0 1px rgba(255,255,255,.06)!important;
        background-image:linear-gradient(180deg,rgba(255,255,255,.92),rgba(255,255,255,.55)),linear-gradient(135deg,var(--color-info-700),var(--color-brand-400))!important;
        -webkit-mask: none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-kicker{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title{
        display:block!important;
        min-width:0!important;
        max-width:128px!important;
        overflow:hidden!important;
        white-space:nowrap!important;
        text-overflow:ellipsis!important;
        font-size:0!important;
        line-height:1!important;
        color:var(--color-white)!important;
        letter-spacing:-.04em!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title:before{
        content:"SoftGenial"!important;
        font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;
        font-size:18px!important;
        font-weight:700!important;
        color:var(--color-white)!important;
        text-shadow:0 2px 12px rgba(0,0,0,.15)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-meta{
        display:flex!important;
        align-items:center!important;
        gap:10px!important;
        flex:0 0 auto!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{
        height:38px!important;
        padding:0 14px!important;
        border-radius:var(--radius-pill)!important;
        background:rgba(255,255,255,.12)!important;
        border:1px solid rgba(255,255,255,.25)!important;
        color:var(--color-white)!important;
        font-size:12px!important;
        font-weight:700!important;
        box-shadow:none!important;
        white-space:nowrap!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-user{
        padding:0!important;
        background:transparent!important;
        border:0!important;
        box-shadow:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar{
        width:46px!important;
        height:46px!important;
        border-radius:50%!important;
        border:3px solid rgba(255,255,255,.86)!important;
        box-shadow:var(--shadow-sm);
        background:var(--color-white)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-user-meta{display:none!important;}

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{
        display:flex!important;
        flex-direction:column!important;
        gap:14px!important;
        padding:0 var(--space-4) 0!important;
        margin:0!important;
        background:transparent!important;
        width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-head{
        display:flex!important;
        align-items:center!important;
        gap:14px!important;
        margin:-18px 0 0!important;
        padding:28px 18px 12px!important;
        border-radius:22px 24px 0 0!important;
        background:var(--color-white)!important;
        color:var(--color-black)!important;
        box-shadow:var(--shadow-sm);
        position:relative!important;
        z-index:2!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-icon{
        width:46px!important;
        height:46px!important;
        border-radius:var(--radius-lg)!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        flex:0 0 auto!important;
        color:var(--color-info-500)!important;
        background:var(--color-info-50)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-icon svg{width:24px!important;height:24px!important;stroke:currentColor!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-name{
        min-width:0!important;
        font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;
        font-size:var(--fs-lg)!important;
        font-weight:700!important;
        letter-spacing:-.045em!important;
        line-height:1.12!important;
        color:var(--color-black)!important;
    }

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{
        position:relative!important;
        min-height:0!important;
        margin:0!important;
        padding:24px 22px 20px!important;
        border-radius:var(--radius-xl)!important;
        background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 51%,var(--color-brand-50) 100%)!important;
        border:1px solid rgba(54,79,191,.10)!important;
        box-shadow:var(--shadow-md);
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero:before{
        width:190px!important;height:190px!important;right:-72px!important;bottom:-82px!important;background:radial-gradient(circle,rgba(92,66,214,.15),rgba(92,66,214,0) 70%)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-content{
        display:block!important;
        position:relative!important;
        z-index:1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{
        max-width:100%!important;
        padding-right:118px!important;
        min-height:148px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-kicker{
        display:flex!important;
        align-items:center!important;
        gap:var(--space-2)!important;
        margin:0 0 var(--space-3)!important;
        color:var(--color-brand-400)!important;
        font-size:12px!important;
        font-weight:700!important;
        letter-spacing:.10em!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-kicker svg{width:17px!important;height:17px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{
        max-width:240px!important;
        font-size:29px!important;
        line-height:1.03!important;
        letter-spacing:-.055em!important;
        color:var(--color-black)!important;
        margin:0 0 var(--space-3)!important;
        font-weight:700!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{
        max-width:205px!important;
        font-size:var(--fs-base)!important;
        line-height:1.45!important;
        color:var(--color-slate-600)!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{
        display:block!important;
        position:absolute!important;
        right:10px!important;
        top:42px!important;
        width:148px!important;
        height:118px!important;
        min-height:0!important;
        border-radius:0!important;
        background:transparent!important;
        opacity:.92!important;
        overflow:visible!important;
        z-index:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-cloud,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-tree,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-dot{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-school{
        right:0!important;bottom:2px!important;width:142px!important;height:92px!important;color:var(--color-brand-300)!important;filter:drop-shadow(0 12px 22px rgba(96,78,231,.18))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-roof{left:22px!important;top:8px!important;width:94px!important;height:42px!important;border-top-width:8px!important;border-left-width:8px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-body{left:24px!important;bottom:0!important;width:102px!important;height:62px!important;border-radius:12px 14px 8px 8px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-body:before{left:42px!important;width:24px!important;height:36px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-window{top:16px!important;left:14px!important;width:16px!important;height:14px!important;box-shadow:var(--shadow-lg);}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-flag{left:76px!important;top:-18px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-school-flag:after{width:30px!important;height:17px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{
        display:grid!important;
        grid-template-columns:1fr 1fr!important;
        gap:10px!important;
        margin-top:16px!important;
        position:relative!important;
        z-index:2!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{
        min-height:56px!important;
        height:56px!important;
        border-radius:var(--radius-md)!important;
        width:100%!important;
        padding:0 var(--space-3)!important;
        font-size:var(--fs-base)!important;
        box-shadow:var(--shadow-sm);
        background:linear-gradient(135deg,var(--color-info-800),var(--color-info-800))!important;
        color:var(--color-white)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero:first-child{grid-column:1/-1!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero-import{
        background:var(--color-white)!important;
        color:var(--color-success-700)!important;
        border:1.5px solid var(--color-success-600)!important;
        box-shadow:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero-secondary{
        background:var(--color-white)!important;
        color:var(--color-info-600)!important;
        border:1.5px solid var(--color-info-500)!important;
        box-shadow:none!important;
    }

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stats-grid{
        display:grid!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-card{
        min-height:92px!important;
        padding:14px!important;
        gap:var(--space-3)!important;
        border-radius:var(--radius-lg)!important;
        box-shadow:var(--shadow-sm);
        border:1px solid rgba(35,48,96,.08)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-card:after{width:72px!important;height:72px!important;right:-24px!important;top:-24px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-icon{width:44px!important;height:44px!important;border-radius:var(--radius-lg)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-icon svg{width:22px!important;height:22px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-label{font-size:var(--fs-xs)!important;line-height:1.2!important;margin-bottom:5px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-value{font-size:var(--fs-xl)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-note{font-size:var(--fs-xs)!important;margin-top:5px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-birthday-panel{display:none!important;}

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar{
        display:grid!important;
        grid-template-columns:minmax(0,1fr) 54px!important;
        gap:10px!important;
        padding:0!important;
        margin:var(--space-2) 0 0!important;
        background:transparent!important;
        border:0!important;
        border-radius:0!important;
        box-shadow:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-search-box{height:54px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-search-box input{
        height:54px!important;
        border-radius:var(--radius-xl)!important;
        background:var(--color-white)!important;
        border:1px solid rgba(35,48,96,.10)!important;
        box-shadow:var(--shadow-sm);
        font-size:var(--fs-base)!important;
        padding-left:48px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-search-box svg{left:17px!important;width:21px!important;height:21px!important;stroke:var(--color-ink-400)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle{
        display:inline-flex!important;
        width:54px!important;
        height:54px!important;
        min-height:54px!important;
        padding:0!important;
        border-radius:var(--radius-xl)!important;
        border:1px solid rgba(35,48,96,.10)!important;
        background:var(--color-white)!important;
        color:var(--color-info-600)!important;
        box-shadow:var(--shadow-sm);
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle svg{width:23px!important;height:23px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle span{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > select,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > .sige-btn-toolbar,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > a.sige-btn-toolbar{grid-column:1/-1!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chips{
        display:flex!important;
        align-items:center!important;
        gap:10px!important;
        margin:-4px 0 8px!important;
        overflow-x:auto!important;
        -webkit-overflow-scrolling:touch!important;
        scrollbar-width:none!important;
        padding-bottom:2px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chips::-webkit-scrollbar{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chip{
        flex:0 0 auto!important;
        min-height:40px!important;
        padding:0 22px!important;
        border-radius:var(--radius-pill)!important;
        border:1px solid rgba(35,48,96,.10)!important;
        background:var(--color-white)!important;
        color:var(--color-slate-700)!important;
        font-weight:700!important;
        font-size:var(--fs-sm)!important;
        box-shadow:var(--shadow-sm);
        cursor:pointer!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chip.is-active{
        background:linear-gradient(135deg,var(--color-info-800),var(--color-info-800))!important;
        color:var(--color-white)!important;
        border-color:transparent!important;
        box-shadow:var(--shadow-sm);
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-results-counter{
        display:none!important;
    }

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-grid{
        display:grid!important;
        grid-template-columns:1fr!important;
        gap:var(--space-3)!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        position:relative!important;
        display:grid!important;
        grid-template-columns:84px minmax(0,1fr)!important;
        gap:12px 14px!important;
        align-items:start!important;
        padding:18px 16px!important;
        border-radius:var(--radius-xl)!important;
        background:var(--color-white)!important;
        border:1px solid rgba(35,48,96,.08)!important;
        box-shadow:var(--shadow-sm);
        overflow:hidden!important;
        min-height:142px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card.is-mobile-chip-hidden{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-foto{
        width:76px!important;
        height:76px!important;
        border-radius:var(--radius-xl)!important;
        object-fit:cover!important;
        box-shadow:var(--shadow-sm);
        border:1px solid rgba(35,48,96,.08)!important;
        grid-column:1!important;
        grid-row:1 / span 2!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card:before{
        content:""!important;
        position:absolute!important;
        left:80px!important;
        top:78px!important;
        width:17px!important;
        height:17px!important;
        border-radius:50%!important;
        background:var(--color-success-600)!important;
        border:3px solid var(--color-white)!important;
        box-shadow:var(--shadow-xs);
        z-index:2!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-info{
        grid-column:2!important;
        padding:0 76px 0 0!important;
        width:100%!important;
        min-width:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome{
        display:block!important;
        margin:2px 0 4px!important;
        padding:0!important;
        color:var(--color-black)!important;
        font-size:17px!important;
        line-height:1.15!important;
        font-weight:700!important;
        letter-spacing:-.03em!important;
        max-width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome svg,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome .sige-badge{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-processo{
        margin:0 0 2px!important;
        color:var(--color-slate-700)!important;
        font-size:var(--fs-sm)!important;
        line-height:1.35!important;
        font-weight:600!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-processo strong{font-weight:600!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-tags{
        display:block!important;
        margin:0!important;
        padding:0!important;
        min-height:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-turma{
        display:block!important;
        width:auto!important;
        max-width:100%!important;
        min-height:0!important;
        margin:0!important;
        padding:0!important;
        background:transparent!important;
        border:0!important;
        box-shadow:none!important;
        color:var(--color-slate-700)!important;
        font-size:var(--fs-sm)!important;
        font-weight:600!important;
        line-height:1.35!important;
        white-space:nowrap!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-turma:before{content:"Turma: "!important;font-weight:600!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-turma svg{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-status-badge{
        position:absolute!important;
        top:22px!important;
        right:18px!important;
        min-height:34px!important;
        height:34px!important;
        padding:0 14px!important;
        border-radius:var(--radius-pill)!important;
        background:var(--color-success-100)!important;
        color:var(--color-success-500)!important;
        border:0!important;
        box-shadow:none!important;
        font-size:12px!important;
        font-weight:700!important;
        text-transform:capitalize!important;
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        z-index:3!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-status-suspenso,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-status-transferido,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-status-desistente{
        background:var(--color-warning-100)!important;color:var(--color-warning-800)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-data-quality-badge,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-finance-badge,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-transporte,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-wa-btn{display:none!important;}

    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{
        grid-column:2!important;
        grid-row:auto!important;
        display:grid!important;
        grid-template-columns:repeat(3,minmax(0,1fr))!important;
        gap:var(--space-2)!important;
        align-items:stretch!important;
        margin-top:10px!important;
        width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        gap:7px!important;
        min-height:42px!important;
        width:100%!important;
        padding:0 10px!important;
        border-radius:var(--radius-md)!important;
        border:0!important;
        background:var(--color-info-50)!important;
        color:var(--color-info-700)!important;
        font-size:12px!important;
        font-weight:700!important;
        line-height:1!important;
        box-shadow:none!important;
        text-decoration:none!important;
        cursor:pointer!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action svg,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary svg{
        width:17px!important;height:17px!important;stroke:currentColor!important;fill:none!important;flex:0 0 auto!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action span,
    body.sige-admin-app.sige-view-alunos_lista .sige-actions-trigger-label{display:inline!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-card-actions{
        position:relative!important;
        display:block!important;
        width:100%!important;
        margin:0!important;
        padding:0!important;
        grid-column:auto!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-card-actions[open]{grid-column:1/-1!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-card-actions .sige-actions-trigger-label{font-size:0!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-card-actions .sige-actions-trigger-label:after{content:"Mais"!important;font-size:12px!important;font-weight:700!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions[open] > summary{
        background:linear-gradient(135deg,var(--color-info-800),var(--color-info-800))!important;
        color:var(--color-white)!important;
        box-shadow:var(--shadow-sm);
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page details.sige-card-actions[open] > .sige-actions-menu{
        position:static!important;
        grid-column:1/-1!important;
        display:grid!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:var(--space-2)!important;
        width:100%!important;
        margin:10px 0 0!important;
        padding:10px!important;
        border-radius:var(--radius-lg)!important;
        background:var(--color-white)!important;
        border:1px solid rgba(35,48,96,.08)!important;
        box-shadow:var(--shadow-sm);
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu .sige-btn-action{height:40px!important;min-height:40px!important;border-radius:var(--radius-md)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-pagination{display:none!important;}

    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav{
        position:fixed!important;
        left:12px!important;
        right:12px!important;
        bottom:calc(10px + env(safe-area-inset-bottom, 0px))!important;
        height:78px!important; min-height:78px!important;
        z-index:10040!important;
        display:grid!important;
        grid-template-columns:repeat(auto-fit,minmax(64px,1fr))!important;
        align-items:center!important;
        padding:8px 10px 9px!important;
        border-radius:var(--radius-xl)!important;
        background:rgba(255,255,255,.96)!important;
        border:1px solid rgba(35,48,96,.08)!important;
        box-shadow:var(--shadow-md);
        backdrop-filter:blur(12px)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav a,
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav button{
        position:relative!important;
        display:flex!important;
        flex-direction:column!important;
        align-items:center!important;
        justify-content:center!important;
        gap:var(--space-1)!important;
        min-width:0!important;
        height:60px!important;
        color:var(--color-slate-600)!important;
        text-decoration:none!important;
        font-size:var(--fs-xs)!important;
        font-weight:600!important;
        border:0!important;
        background:transparent!important;
        font-family:inherit!important;
        cursor:pointer!important;
        -webkit-appearance:none!important;
        appearance:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav a svg,
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav button svg{width:25px!important;height:25px!important;stroke:currentColor!important;fill:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav button span,
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav a span{
        display:block!important;
        max-width:100%!important;
        overflow:hidden!important;
        text-overflow:ellipsis!important;
        white-space:nowrap!important;
        line-height:1.15!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav a.is-active,
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav button.is-active{color:var(--color-info-600)!important;font-weight:700!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav a.is-active:before,
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav button.is-active:before{
        content:""!important;
        position:absolute!important;
        top:-9px!important;
        width:74px!important;
        height:4px!important;
        border-radius:var(--radius-pill)!important;
        background:var(--color-info-600)!important;
        box-shadow:var(--shadow-xs);
    }
}
@media (max-width:420px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{padding:0 11px!important;font-size:var(--fs-xs)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar{width:42px!important;height:42px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{padding-left:14px!important;padding-right:14px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{padding:22px 20px 18px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{padding-right:105px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{font-size:27px!important;max-width:218px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{font-size:var(--fs-sm)!important;max-width:190px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{right:2px!important;width:132px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{grid-template-columns:78px minmax(0,1fr)!important;padding:16px 14px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-foto{width:70px!important;height:70px!important;border-radius:var(--radius-xl)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card:before{left:73px!important;top:72px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-info{padding-right:68px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome{font-size:var(--fs-md)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{font-size:var(--fs-xs)!important;padding:0 7px!important;}
}
@media (max-width:370px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{padding-right:0!important;min-height:0!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{max-width:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{grid-template-columns:1fr!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-card-actions[open]{grid-column:auto!important;}
}


/* v12.11.9.56 - Header & Card Alignment Mobile PRO
   Correcção cirúrgica do cabeçalho mobile e alinhamento dos cards para aderir ao screenshot de referência. */
@media (max-width:760px){
    html.wp-toolbar,
    body.sige-admin-app.sige-view-alunos_lista{
        margin-top:0!important;
        padding-top:0!important;
        background:var(--color-slate-50)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #wpwrap,
    body.sige-admin-app.sige-view-alunos_lista #wpcontent,
    body.sige-admin-app.sige-view-alunos_lista #wpbody,
    body.sige-admin-app.sige-view-alunos_lista #wpbody-content,
    body.sige-admin-app.sige-view-alunos_lista #sige-layout.sg-product-pro-shell{
        margin-top:0!important;
        padding-top:0!important;
        top:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #sige-layout.sg-product-pro-shell{
        display:block!important;
        min-height:100vh!important;
        overflow-x:hidden!important;
        background:linear-gradient(180deg,var(--color-slate-50) 0%,var(--color-slate-50) 100%)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{
        padding:0 0 calc(112px + env(safe-area-inset-bottom, 0px))!important;
        margin:0!important;
        min-height:100vh!important;
        overflow-x:hidden!important;
        background:linear-gradient(180deg,var(--color-slate-50) 0%,var(--color-slate-50) 100%)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-page{
        margin:0!important;
        padding:0!important;
        width:100%!important;
        max-width:none!important;
        background:transparent!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar{
        position:sticky!important;
        top:0!important;
        z-index:10050!important;
        width:100%!important;
        min-height:88px!important;
        height:88px!important;
        margin:0!important;
        padding:12px 18px 16px!important;
        border-radius:0 0 26px 26px!important;
        border:0!important;
        background:linear-gradient(135deg,var(--color-info-700) 0%,var(--color-info-700) 46%,var(--color-brand-500) 100%)!important;
        box-shadow:var(--shadow-md);
        display:flex!important;
        align-items:center!important;
        justify-content:flex-start!important;
        flex-wrap:nowrap!important;
        gap:11px!important;
        color:var(--color-white)!important;
        overflow:hidden!important;
        clip-path:inset(0 0 0 0 round 0 0 26px 26px)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar .sg-app-hamburger{
        position:relative!important;
        left:auto!important;
        top:auto!important;
        transform:none!important;
        flex:0 0 42px!important;
        width:42px!important;
        height:42px!important;
        min-width:42px!important;
        margin:0!important;
        padding:0!important;
        border:0!important;
        border-radius:var(--radius-md)!important;
        background:transparent!important;
        box-shadow:none!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        color:var(--color-white)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar .sg-app-hamburger svg{
        width:29px!important;
        height:29px!important;
        stroke:var(--color-white)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar > div:first-of-type,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-headings{
        padding-left:0!important;
        flex:1 1 auto!important;
        flex-basis:auto!important;
        min-width:0!important;
        display:flex!important;
        align-items:center!important;
        gap:10px!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-headings:before{
        display:none!important;
        content:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo{
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        flex:0 0 38px!important;
        width:38px!important;
        height:38px!important;
        border-radius:var(--radius-md)!important;
        background:rgba(255,255,255,.15)!important;
        border:1px solid rgba(255,255,255,.24)!important;
        box-shadow:inset 0 0 0 1px rgba(255,255,255,.06),0 10px 20px rgba(0,0,0,.10)!important;
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo img{
        display:block!important;
        width:100%!important;
        height:100%!important;
        object-fit:contain!important;
        padding:5px!important;
        background:rgba(255,255,255,.92)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo.is-fallback:before{
        content:"SG"!important;
        color:var(--color-white)!important;
        font-weight:700!important;
        font-size:12px!important;
        letter-spacing:-.04em!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo.is-fallback{
        background:linear-gradient(135deg,rgba(255,255,255,.25),rgba(255,255,255,.08))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo.is-fallback img{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-kicker{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title{
        display:block!important;
        max-width:118px!important;
        min-width:0!important;
        overflow:hidden!important;
        white-space:nowrap!important;
        text-overflow:ellipsis!important;
        font-size:0!important;
        line-height:1!important;
        margin:0!important;
        padding:0!important;
        color:var(--color-white)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title:before{
        content:"SoftGenial"!important;
        font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;
        font-size:18px!important;
        line-height:1!important;
        font-weight:700!important;
        letter-spacing:-.055em!important;
        color:var(--color-white)!important;
        text-shadow:0 2px 12px rgba(0,0,0,.15)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-meta{
        flex:0 0 auto!important;
        width:auto!important;
        margin-left:auto!important;
        display:flex!important;
        align-items:center!important;
        justify-content:flex-end!important;
        gap:var(--space-2)!important;
        min-width:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{
        display:inline-flex!important;
        align-items:center!important;
        justify-content:center!important;
        height:36px!important;
        min-height:36px!important;
        padding:0 var(--space-3)!important;
        border-radius:var(--radius-pill)!important;
        background:rgba(255,255,255,.12)!important;
        border:1px solid rgba(255,255,255,.25)!important;
        color:var(--color-white)!important;
        font-size:var(--fs-xs)!important;
        line-height:1!important;
        font-weight:700!important;
        white-space:nowrap!important;
        box-shadow:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-user{
        padding:0!important;
        margin:0!important;
        border:0!important;
        background:transparent!important;
        box-shadow:none!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        width:auto!important;
        min-width:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar{
        width:44px!important;
        height:44px!important;
        min-width:44px!important;
        border-radius:50%!important;
        border:3px solid rgba(255,255,255,.88)!important;
        background:var(--color-white)!important;
        box-shadow:var(--shadow-sm);
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar img{
        width:100%!important;
        height:100%!important;
        object-fit:cover!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-user-meta{display:none!important;}

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-head{
        margin:0 -16px 0!important;
        padding:18px 18px 14px!important;
        border-radius:0!important;
        background:rgba(255,255,255,.96)!important;
        box-shadow:var(--shadow-sm);
        gap:var(--space-3)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-icon{
        width:44px!important;
        height:44px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-name{
        font-size:18px!important;
        line-height:1.12!important;
        letter-spacing:-.05em!important;
    }

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{
        padding-left:16px!important;
        padding-right:16px!important;
        gap:14px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{
        padding:22px 20px 18px!important;
        border-radius:var(--radius-xl)!important;
        min-height:0!important;
        box-shadow:var(--shadow-md);
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{
        padding-right:96px!important;
        min-height:142px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{
        max-width:286px!important;
        font-size:25px!important;
        line-height:1.06!important;
        letter-spacing:-.055em!important;
        white-space:normal!important;
        margin-bottom:10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{
        max-width:260px!important;
        font-size:var(--fs-sm)!important;
        line-height:1.42!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{
        right:8px!important;
        bottom:70px!important;
        width:124px!important;
        opacity:.92!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{
        grid-template-columns:1fr 1fr!important;
        gap:10px!important;
        margin-top:16px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{
        min-height:46px!important;
        border-radius:var(--radius-md)!important;
        font-size:var(--fs-sm)!important;
        white-space:nowrap!important;
        line-height:1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero:first-child{
        grid-column:1/-1!important;
        min-height:52px!important;
        font-size:var(--fs-base)!important;
    }

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-card{
        min-height:104px!important;
        padding:var(--space-4)!important;
        border-radius:var(--radius-lg)!important;
        align-items:center!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-icon{
        width:48px!important;
        height:48px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-label{
        font-size:var(--fs-xs)!important;
        line-height:1.22!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-value{
        font-size:22px!important;
        line-height:1.05!important;
    }

    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        grid-template-columns:82px minmax(0,1fr)!important;
        column-gap:13px!important;
        row-gap:0!important;
        align-items:start!important;
        padding:18px 16px!important;
        border-radius:var(--radius-xl)!important;
        min-height:154px!important;
        box-shadow:var(--shadow-md);
        border:1px solid rgba(33,48,96,.07)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-foto{
        width:76px!important;
        height:76px!important;
        border-radius:var(--radius-xl)!important;
        box-shadow:var(--shadow-sm);
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card:before{
        left:78px!important;
        top:80px!important;
        width:16px!important;
        height:16px!important;
        border-width:3px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-info{
        padding-right:70px!important;
        min-height:78px!important;
        display:flex!important;
        flex-direction:column!important;
        justify-content:center!important;
        gap:3px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome{
        font-size:18px!important;
        line-height:1.12!important;
        letter-spacing:-.045em!important;
        margin:0 0 2px!important;
        max-width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-proc,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-turma{
        font-size:12.5px!important;
        line-height:1.25!important;
        color:var(--color-ink-800)!important;
        font-weight:600!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-status-badge{
        top:18px!important;
        right:16px!important;
        height:34px!important;
        min-height:34px!important;
        padding:0 13px!important;
        font-size:12px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{
        grid-column:2!important;
        display:grid!important;
        grid-template-columns:repeat(3,minmax(0,1fr))!important;
        gap:var(--space-2)!important;
        margin-top:12px!important;
        align-items:stretch!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{
        min-height:44px!important;
        height:44px!important;
        border-radius:var(--radius-md)!important;
        background:var(--color-info-50)!important;
        color:var(--color-info-700)!important;
        font-size:12px!important;
        font-weight:700!important;
        gap:6px!important;
        padding:0 var(--space-2)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action svg,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary svg{
        width:16px!important;
        height:16px!important;
    }
}
@media (max-width:420px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar{height:84px!important;min-height:84px!important;padding:10px 14px 14px!important;gap:var(--space-2)!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar .sg-app-hamburger{width:40px!important;height:40px!important;min-width:40px!important;flex-basis:40px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo{width:36px!important;height:36px!important;flex-basis:36px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title{max-width:106px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title:before{font-size:17px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{height:34px!important;min-height:34px!important;padding:0 9px!important;font-size:10px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar{width:40px!important;height:40px!important;min-width:40px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-head{margin-left:-14px!important;margin-right:-14px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{padding-right:88px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{font-size:var(--fs-xl)!important;max-width:268px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{max-width:232px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{width:112px!important;right:4px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{grid-template-columns:78px minmax(0,1fr)!important;padding:17px 14px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-foto{width:72px!important;height:72px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card:before{left:74px!important;top:76px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome{font-size:17px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{font-size:var(--fs-xs)!important;padding:0 6px!important;}
}
@media (max-width:370px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title{max-width:132px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{grid-column:1/-1!important;}
}



/* v12.11.9.60 - Mobile Header Consistency Alunos PRO
   Ajusta a curva inferior esquerda da topbar de Alunos e mantém o topo colado ao ecrã. */
@media (max-width:760px){
    body.sige-admin-app.sige-view-alunos_lista,
    body.sige-admin-app.sige-view-alunos_lista #wpwrap,
    body.sige-admin-app.sige-view-alunos_lista #wpcontent,
    body.sige-admin-app.sige-view-alunos_lista #wpbody,
    body.sige-admin-app.sige-view-alunos_lista #wpbody-content,
    body.sige-admin-app.sige-view-alunos_lista #sige-layout.sg-product-pro-shell,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{
        margin-top:0!important;
        padding-top:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar{
        margin-top:0!important;
        border-radius:0 0 26px 26px!important;
        clip-path:inset(0 0 0 0 round 0 0 26px 26px)!important;
        overflow:hidden!important;
    }
}



/* v12.11.9.61 - Smoke Hardening Alunos Mobile PRO
   Reaplica o fine-tune 10/10 após as camadas globais/v60 para evitar regressão por ordem de CSS. */
@media (max-width:760px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{padding:24px 20px 18px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-content{overflow:visible!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{
        padding-right:124px!important;
        min-height:156px!important;
        position:relative!important;
        z-index:2!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{
        max-width:232px!important;
        margin:0 0 10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{
        max-width:206px!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{
        right:10px!important;
        top:34px!important;
        bottom:auto!important;
        width:122px!important;
        height:96px!important;
        opacity:.78!important;
        z-index:1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-school{
        right:0!important;
        bottom:0!important;
        width:118px!important;
        height:78px!important;
        color:var(--color-brand-300)!important;
        filter:drop-shadow(0 10px 18px rgba(96,78,231,.14))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{
        margin-top:18px!important;
        position:relative!important;
        z-index:3!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{position:relative!important;z-index:3!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        grid-template-columns:84px minmax(0,1fr)!important;
        gap:14px!important;
        min-height:154px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{gap:10px!important;margin-top:12px!important;}
}
@media (max-width:420px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{padding-right:112px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{max-width:190px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{right:7px!important;top:36px!important;width:108px!important;height:88px!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-school{width:106px!important;height:72px!important;}
}
@media (max-width:370px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{padding-right:0!important;min-height:0!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{display:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{max-width:none!important;}
}


/* v12.11.9.68 - Alunos Mobile + Tablet UX PRO
   Camada final de fit-on-screen, fluxo intuitivo e interface alinhada às permissões.
   Não altera cálculos, gravações, importações, impressão nem regras de negócio. */
body.sige-admin-app.sige-view-alunos_lista,
body.sige-admin-app.sige-view-alunos_lista #wpwrap,
body.sige-admin-app.sige-view-alunos_lista #wpcontent,
body.sige-admin-app.sige-view-alunos_lista #wpbody,
body.sige-admin-app.sige-view-alunos_lista #wpbody-content{
    overflow-x:hidden!important;
}
body.sige-admin-app.sige-view-alunos_lista .sg-app-content,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page > *,
body.sige-admin-app.sige-view-alunos_lista .sige-modal-content,
body.sige-admin-app.sige-view-alunos_lista .sige-modal-body{
    max-width:100%!important;
    min-width:0!important;
    box-sizing:border-box!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-card,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-birthday-panel,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-results-counter,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
    overflow-wrap:anywhere!important;
    word-break:normal!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-flow-guide{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:10px;
    margin:0;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-flow-guide span{
    min-height:44px;
    display:flex;
    align-items:center;
    justify-content:center;
    gap:var(--space-2);
    padding:var(--space-2) var(--space-3);
    border-radius:var(--radius-lg);
    background:var(--color-white);
    border:1px solid rgba(226,232,240,.92);
    box-shadow:var(--shadow-sm);
    color:var(--color-slate-800);
    font-size:12px;
    font-weight:700;
    text-align:center;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-flow-guide strong{
    width:24px;
    height:24px;
    border-radius:var(--radius-pill);
    display:inline-flex;
    align-items:center;
    justify-content:center;
    background:var(--color-brand-50);
    color:var(--color-brand-500);
    flex:0 0 24px;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-live{
    display:none;
    padding:0 var(--space-1);
    color:var(--color-slate-500);
    font-size:12px;
    font-weight:600;
}
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-card.is-search-hidden,
body.sige-admin-app.sige-view-alunos_lista .sige-aluno-card.is-mobile-chip-hidden{
    display:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-tags{
    display:flex!important;
    flex-wrap:wrap!important;
    gap:7px!important;
    min-width:0!important;
    max-width:100%!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-turma,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-status-badge,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-data-quality-badge,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-finance-badge,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-tag-transporte,
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-wa-btn{
    max-width:100%!important;
    min-width:0!important;
    white-space:normal!important;
    overflow-wrap:anywhere!important;
    text-decoration:none!important;
}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome,
body.sige-admin-app.sige-view-alunos_lista .sige-360-name,
body.sige-admin-app.sige-view-alunos_lista .sige-360-row strong,
body.sige-admin-app.sige-view-alunos_lista .sige-360-time strong{
    overflow-wrap:anywhere!important;
    word-break:normal!important;
}
@media (min-width:761px) and (max-width:1100px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{
        padding-left:22px!important;
        padding-right:22px!important;
        padding-bottom:calc(112px + env(safe-area-inset-bottom,0px))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{
        gap:18px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{
        padding:28px 26px!important;
        border-radius:var(--radius-xl)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-content{
        grid-template-columns:1fr!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{
        font-size:clamp(28px,4vw,36px)!important;
        max-width:100%!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{
        display:grid!important;
        grid-template-columns:repeat(3,minmax(0,1fr))!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{
        width:100%!important;
        padding-left:14px!important;
        padding-right:14px!important;
        white-space:normal!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stats-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar{
        grid-template-columns:minmax(0,1fr) minmax(180px,.55fr) minmax(180px,.55fr)!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > .sige-btn-toolbar,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > a.sige-btn-toolbar{
        grid-column:auto!important;
        min-height:48px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:var(--space-4)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        display:grid!important;
        grid-template-columns:74px minmax(0,1fr)!important;
        gap:14px!important;
        align-items:start!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{
        grid-column:1/-1!important;
        display:grid!important;
        grid-template-columns:repeat(auto-fit,minmax(132px,1fr))!important;
        gap:10px!important;
    }
}
@media (max-width:760px){
    body.sige-admin-app.sige-view-alunos_lista .sg-app-content{
        padding-left:12px!important;
        padding-right:12px!important;
        padding-bottom:calc(124px + env(safe-area-inset-bottom,0px))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-school-head{
        margin-left:0!important;
        margin-right:0!important;
        border-radius:0 0 22px 22px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{
        padding-left:0!important;
        padding-right:0!important;
        gap:14px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero{
        padding:22px 18px!important;
        border-radius:var(--radius-xl)!important;
        min-height:0!important;
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero:before{
        width:260px!important;
        height:220px!important;
        inset:auto -110px -130px auto!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-content{
        display:block!important;
        overflow:visible!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{
        padding-right:0!important;
        min-height:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-art{
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-kicker{
        font-size:var(--fs-xs)!important;
        letter-spacing:.08em!important;
        margin-bottom:8px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{
        max-width:100%!important;
        font-size:clamp(25px,7vw,32px)!important;
        line-height:1.08!important;
        margin:0 0 10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{
        max-width:100%!important;
        font-size:var(--fs-sm)!important;
        line-height:1.5!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{
        display:grid!important;
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:10px!important;
        margin-top:16px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{
        width:100%!important;
        min-height:48px!important;
        padding:0 var(--space-3)!important;
        white-space:normal!important;
        line-height:1.15!important;
        text-align:center!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero:first-child{
        grid-column:1/-1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-flow-guide{
        grid-template-columns:1fr!important;
        gap:var(--space-2)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-flow-guide span{
        justify-content:flex-start!important;
        min-height:42px!important;
        font-size:12px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stats-grid{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
        gap:10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-card{
        min-height:98px!important;
        padding:14px!important;
        border-radius:var(--radius-lg)!important;
        align-items:flex-start!important;
        gap:10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-icon{
        width:42px!important;
        height:42px!important;
        min-width:42px!important;
        border-radius:var(--radius-md)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-label{
        font-size:10.5px!important;
        line-height:1.2!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-value{
        font-size:clamp(20px,7vw,25px)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stat-note{
        font-size:10.5px!important;
        line-height:1.25!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-birthday-panel{
        padding:15px!important;
        border-radius:var(--radius-xl)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-birthday-actions{
        width:100%!important;
        display:grid!important;
        grid-template-columns:repeat(3,minmax(0,1fr))!important;
        gap:var(--space-2)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-btn-bday{
        width:100%!important;
        justify-content:center!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar{
        position:relative!important;
        display:grid!important;
        grid-template-columns:minmax(0,1fr) 52px!important;
        gap:var(--space-2)!important;
        padding:var(--space-3)!important;
        border-radius:var(--radius-xl)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-search-box{
        grid-column:1/2!important;
        min-width:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-search-box input{
        width:100%!important;
        min-width:0!important;
        padding-right:10px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle{
        width:52px!important;
        min-width:52px!important;
        padding:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-filter-toggle span{
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > select,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > .sige-btn-toolbar,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-toolbar > a.sige-btn-toolbar{
        grid-column:1/-1!important;
        width:100%!important;
        min-height:48px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chips{
        display:grid!important;
        grid-template-columns:repeat(auto-fit,minmax(74px,1fr))!important;
        gap:var(--space-2)!important;
        overflow:visible!important;
        padding:0!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chip{
        width:100%!important;
        min-width:0!important;
        min-height:40px!important;
        padding:0 var(--space-2)!important;
        white-space:normal!important;
        text-align:center!important;
        line-height:1.05!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-mobile-live{
        display:block!important;
        min-height:18px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-results-counter{
        display:flex!important;
        flex-direction:column!important;
        align-items:flex-start!important;
        gap:6px!important;
        padding:13px 14px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-grid{
        grid-template-columns:1fr!important;
        gap:var(--space-3)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        display:grid!important;
        grid-template-columns:72px minmax(0,1fr)!important;
        column-gap:var(--space-3)!important;
        row-gap:10px!important;
        padding:15px!important;
        border-radius:var(--radius-xl)!important;
        min-height:0!important;
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-foto{
        width:68px!important;
        height:68px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-info{
        padding-right:0!important;
        min-width:0!important;
        min-height:0!important;
        justify-content:flex-start!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-nome{
        padding-right:0!important;
        font-size:17px!important;
        line-height:1.18!important;
        margin-bottom:5px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-processo{
        margin-bottom:8px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-card-actions-row{
        grid-column:1/-1!important;
        display:grid!important;
        grid-template-columns:repeat(auto-fit,minmax(112px,1fr))!important;
        gap:var(--space-2)!important;
        margin-top:2px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-primary-action,
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions > summary{
        width:100%!important;
        min-height:44px!important;
        height:auto!important;
        justify-content:center!important;
        white-space:normal!important;
        text-align:center!important;
        line-height:1.1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-card-actions{
        position:relative!important;
        top:auto!important;
        right:auto!important;
        width:100%!important;
        margin:0!important;
        padding:0!important;
        border:0!important;
        background:transparent!important;
        box-shadow:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-actions-menu{
        position:relative!important;
        top:auto!important;
        right:auto!important;
        width:100%!important;
        max-width:100%!important;
        min-width:0!important;
        grid-template-columns:1fr!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-pagination{
        overflow-x:auto!important;
        justify-content:flex-start!important;
        -webkit-overflow-scrolling:touch!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav{
        display:grid!important;
        grid-template-columns:repeat(auto-fit,minmax(64px,1fr))!important;
        grid-auto-flow:row!important;
        gap:0!important;
        width:calc(100vw - 24px)!important;
        max-width:560px!important;
        left:50%!important;
        transform:translateX(-50%)!important;
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav a,
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav button{
        min-width:0!important;
        padding-left:5px!important;
        padding-right:5px!important;
    }
}
@media (max-width:390px){
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-status-chips{
        grid-template-columns:repeat(2,minmax(0,1fr))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{
        grid-template-columns:1fr!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stats-grid{
        grid-template-columns:1fr 1fr!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-card{
        grid-template-columns:64px minmax(0,1fr)!important;
        padding:14px!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-aluno-foto{
        width:60px!important;
        height:60px!important;
    }
}
@media (max-width:340px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-stats-grid{
        grid-template-columns:1fr!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav{
        width:calc(100vw - 14px)!important;
        grid-template-columns:repeat(auto-fit,minmax(56px,1fr))!important;
    }
}

/* v12.11.9.70 - Alunos: botão "Mais" da navegação inferior volta a abrir o menu lateral. */
@media (max-width:760px){
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav button:focus-visible,
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav a:focus-visible{
        outline:3px solid rgba(10,79,215,.28)!important;
        outline-offset:2px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-mobile-bottom-nav .sige-mobile-more-trigger[aria-expanded="true"]{
        color:var(--color-info-600)!important;
    }
}
@media (max-width:820px){
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno.sige-aluno-modal-pro,
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360.sige-aluno360-modal-pro,
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos.sige-import-modal-pro{
        padding:var(--space-2)!important;
        align-items:flex-start!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno .sige-modal-content,
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-content,
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos .sige-modal-content{
        width:calc(100vw - 16px)!important;
        max-width:calc(100vw - 16px)!important;
        height:calc(100dvh - 16px)!important;
        max-height:calc(100dvh - 16px)!important;
        border-radius:var(--radius-xl)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno .sige-modal-header,
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-header,
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos .sige-modal-header{
        padding:var(--space-4)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno .sige-modal-title-icon,
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-title-icon,
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos .sige-modal-title-icon{
        width:44px!important;
        height:44px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno .sige-modal-header h2,
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-header h2,
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos .sige-modal-header h2{
        font-size:var(--fs-lg)!important;
        line-height:1.1!important;
    }
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno .sige-modal-body,
    body.sige-admin-app.sige-view-alunos_lista #modal-aluno-360 .sige-modal-body,
    body.sige-admin-app.sige-view-alunos_lista #modal-import-alunos .sige-modal-body{
        padding:14px!important;
        overflow-x:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-360-grid,
    body.sige-admin-app.sige-view-alunos_lista .sige-360-kpi-grid,
    body.sige-admin-app.sige-view-alunos_lista .sige-360-hero-card{
        grid-template-columns:1fr!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-360-card{
        padding:15px!important;
        border-radius:var(--radius-lg)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sige-360-row,
    body.sige-admin-app.sige-view-alunos_lista .sige-360-time{
        grid-template-columns:1fr!important;
        gap:var(--space-1)!important;
    }
}


/* v12.11.9.69 - Deep Audit Alunos: blindagem extra de fit-on-screen em mobile/tablet. */
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page{max-width:100%;overflow-x:clip;}
.sige-aluno-card.is-birthday-hidden{display:none!important;}
.sige-aluno-card,.sige-aluno-card *,
.sige-360-card,.sige-360-card *,
.sige-alunos-mobile-flow,.sige-alunos-mobile-flow *,
.sige-birthday-panel,.sige-birthday-panel *{min-width:0;}
.sige-aluno-nome,.sige-360-name,.sige-360-pill,.sige-360-row strong,.sige-birthday-text,.sige-results-counter,.sige-results-counter span{overflow-wrap:anywhere;word-break:normal;}
.sige-360-meta{max-width:100%;overflow:hidden;}
.sige-360-pill{max-width:100%;}
@media(max-width:760px){
    .sige-360-kpi-grid,.sige-360-grid,.sige-aluno-card-actions-row{grid-template-columns:1fr !important;}
    .sige-360-meta{display:grid !important;grid-template-columns:1fr !important;gap:var(--space-2);}
    .sige-360-pill{width:100%;justify-content:flex-start;}
    .sige-birthday-actions,#modal-aluno-360 .sige-modal-footer{display:grid !important;grid-template-columns:1fr !important;gap:var(--space-2);}
    #modal-aluno-360 .sige-modal-footer .sige-btn{width:100%;justify-content:center;}
}

.sige-alunos-readonly-banner{display:flex;align-items:center;gap:var(--space-2);margin:0 0 18px;padding:14px 16px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-info-100);color:var(--color-slate-800);font-size:var(--fs-sm);font-weight:600;box-shadow:0 2px 8px rgba(15,23,42,.06)}
.sige-alunos-readonly-banner strong{color:var(--color-info-600);font-weight:700}
@media(max-width:760px){.sige-alunos-readonly-banner{align-items:flex-start;flex-direction:column;gap:var(--space-1);margin-top:-4px}}

/* v12.11.9.69 - Deep Audit UX/Security hardening do módulo Alunos */
body.sige-admin-app.sige-view-alunos_lista .sige-360-doc strong,
body.sige-admin-app.sige-view-alunos_lista .sige-360-row strong,
body.sige-admin-app.sige-view-alunos_lista .sige-360-row span{overflow-wrap:anywhere;word-break:break-word;}
body.sige-admin-app.sige-view-alunos_lista .sige-actions-trigger[aria-expanded="true"]{box-shadow:var(--shadow-xs);}

/* v12.17.1 - Alunos mobile header containment hotfix.
   Escopo: apenas cabecalho mobile no modulo Alunos.
   Causa tratada: regras historicas da pagina de Alunos reexibiam o ano lectivo
   e competiam com o avatar dentro da mesma area direita, cortando a foto.
   Contrato: esconder o chip do ano em mobile, reservar uma coluna fixa para
   o avatar e preservar o header global aprovado. */
@media (max-width:760px){
    body.sige-admin-app.sige-view-alunos_lista .sg-product-pro-shell .sg-app-topbar,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar{
        display:grid!important;
        grid-template-columns:var(--space-10) minmax(0,1fr) var(--space-10)!important;
        align-items:center!important;
        justify-content:normal!important;
        gap:var(--space-2)!important;
        min-height:calc(var(--space-10) + var(--space-8))!important;
        height:auto!important;
        padding:var(--space-3) var(--space-4)!important;
        overflow:hidden!important;
        clip-path:inset(0 0 0 0 round 0 0 var(--radius-xl) var(--radius-xl))!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar .sg-app-hamburger{
        grid-column:1!important;
        width:var(--space-10)!important;
        height:var(--space-10)!important;
        min-width:var(--space-10)!important;
        flex:0 0 var(--space-10)!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar > div:first-of-type,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-headings{
        grid-column:2!important;
        min-width:0!important;
        max-width:100%!important;
        overflow:hidden!important;
        display:flex!important;
        align-items:center!important;
        gap:var(--space-2)!important;
        padding:0!important;
        margin:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo{
        width:calc(var(--space-10) - var(--space-1) / 2)!important;
        height:calc(var(--space-10) - var(--space-1) / 2)!important;
        min-width:calc(var(--space-10) - var(--space-1) / 2)!important;
        flex:0 0 calc(var(--space-10) - var(--space-1) / 2)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-title{
        max-width:100%!important;
        min-width:0!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-chip-year{
        display:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-meta{
        grid-column:3!important;
        justify-self:end!important;
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        gap:0!important;
        width:var(--space-10)!important;
        min-width:var(--space-10)!important;
        max-width:var(--space-10)!important;
        margin:0!important;
        overflow:visible!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-user{
        display:flex!important;
        align-items:center!important;
        justify-content:center!important;
        width:var(--space-10)!important;
        height:var(--space-10)!important;
        min-width:var(--space-10)!important;
        flex:0 0 var(--space-10)!important;
        padding:0!important;
        margin:0!important;
        overflow:visible!important;
        background:transparent!important;
        border:0!important;
        box-shadow:none!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar{
        box-sizing:border-box!important;
        width:var(--space-10)!important;
        height:var(--space-10)!important;
        min-width:var(--space-10)!important;
        flex:0 0 var(--space-10)!important;
        border-radius:50%!important;
        border:var(--space-1) solid rgba(255,255,255,.90)!important;
        background:var(--color-white)!important;
        box-shadow:var(--shadow-sm)!important;
        overflow:hidden!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar img{
        width:100%!important;
        height:100%!important;
        object-fit:cover!important;
        display:block!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-user-meta{
        display:none!important;
    }
}
@media (max-width:390px){
    body.sige-admin-app.sige-view-alunos_lista .sg-product-pro-shell .sg-app-topbar,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar{
        grid-template-columns:var(--space-10) minmax(0,1fr) var(--space-10)!important;
        padding-left:var(--space-4)!important;
        padding-right:var(--space-4)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar .sg-app-hamburger,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-user,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-avatar,
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-meta{
        width:var(--space-10)!important;
        height:var(--space-10)!important;
        min-width:var(--space-10)!important;
        flex-basis:var(--space-10)!important;
    }
    body.sige-admin-app.sige-view-alunos_lista .sg-app-topbar-logo{
        width:calc(var(--space-10) - var(--space-1))!important;
        height:calc(var(--space-10) - var(--space-1))!important;
        min-width:calc(var(--space-10) - var(--space-1))!important;
        flex-basis:calc(var(--space-10) - var(--space-1))!important;
    }
}

/* v12.22.2 - Herói sem ilustração: faixa de uma coluna a toda a largura.
   Anula os padding-right/min-height que reservavam espaço para a arte (já
   removida). Os botões ficam numa LINHA em portáteis (o constrangimento real é
   a largura do conteúdo = janela menos a barra lateral ~286px, não a janela):
   por isso são compactos e NÃO quebram (nada de 2+1 nem dependência de zoom).
   Só empilham em ecrã estreito (telemóvel/tablet pequeno). Colocado no fim para
   vencer as camadas anteriores. */
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-content{display:block!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-text{padding-right:0!important;max-width:none!important;min-height:0!important;width:100%!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero h1{max-width:none!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{display:flex!important;flex-wrap:nowrap!important;gap:var(--space-2)!important;width:100%!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{flex:0 1 auto!important;min-width:0!important;padding:0 var(--space-3)!important;gap:var(--space-2)!important;font-size:var(--fs-sm)!important;white-space:nowrap!important;}
body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero svg{flex:0 0 auto!important;width:16px!important;height:16px!important;}
@media (max-width:720px){
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-subtitle{max-width:none!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-hero-actions{display:grid!important;grid-template-columns:1fr!important;flex-wrap:wrap!important;}
    body.sige-admin-app.sige-view-alunos_lista .sige-alunos-page .sige-btn-hero{width:100%!important;font-size:var(--fs-base)!important;white-space:normal!important;}
}
</style>

<div class="wrap sige-alunos-page">

    <div class="sige-alunos-mobile-school-head" aria-label="Escola activa">
        <span class="sige-alunos-mobile-school-icon" aria-hidden="true">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18"/><path d="M5 21V8l7-5 7 5v13"/><path d="M9 21v-6h6v6"/><path d="M9 10h.01M15 10h.01"/></svg>
        </span>
        <strong class="sige-alunos-mobile-school-name"><?php echo esc_html($nome_escola); ?></strong>
    </div>

    <section class="sige-hero sige-alunos-hero-pro" aria-label="Alunos e matrículas">
        <div class="sige-hero-content">
            <div class="sige-hero-text">
                <div class="sige-hero-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('users') : ''; ?> Académico</div>
                <h1>Alunos e Matrículas</h1>
                <p class="sige-hero-subtitle"><?php echo $sige_alunos_read_only ? 'Consulte estudantes em modo seguro, abra a Ficha 360º e confirme rapidamente a identificação.' : 'Consulte alunos, matrículas, qualidade dos dados e documentos num fluxo simples para mobile e tablet.'; ?></p>
                <div class="sige-hero-actions">
                    <?php if ($sige_alunos_can_create): ?>
                    <button data-sige-act="novoAluno" data-sige-noargs class="sige-btn-hero" type="button">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                        Registar Aluno
                    </button>
                    <button type="button" data-sige-act="abrirImportarAlunos" data-sige-noargs class="sige-btn-hero sige-btn-hero-import">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                        Importar Lista
                    </button>
                    <?php endif; ?>
                    <?php if ($sige_alunos_can_documents_emit): ?>
                    <button type="button" data-sige-act="printBatchCards" data-sige-noargs class="sige-btn-hero sige-btn-hero-secondary">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2M15 12h2"/><path d="M7 16h10"/></svg>
                        Imprimir Cartões
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <div class="sige-alunos-flow-guide" aria-label="Fluxo recomendado no módulo de alunos">
        <span><strong>1</strong> Pesquisar</span>
        <span><strong>2</strong> Abrir Ficha 360º</span>
        <span><strong>3</strong> <?php echo $sige_alunos_read_only ? 'Confirmar identidade' : 'Actualizar ou matricular'; ?></span>
    </div>

    <?php if ($sige_alunos_read_only): ?>
        <div class="sige-alunos-readonly-banner" role="status" aria-live="polite">
            <strong>Modo consulta.</strong>
            O seu perfil permite ver alunos e abrir a Ficha 360º, mas não permite registar, importar, editar ou remover alunos.
        </div>
    <?php endif; ?>

    <div class="sige-stats-grid sige-alunos-kpi-grid">
        <div class="sige-stat-card" style="--kpi-soft:#f3efff;--kpi-color:#6d5dfc;">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Alunos activos</div>
                <div class="sige-stat-value"><?php echo $stats['activos']; ?></div>
                <div class="sige-stat-note">Na página actual</div>
            </div>
        </div>
        <div class="sige-stat-card" style="--kpi-soft:#eaf8ef;--kpi-color:#16a34a;">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Alunos nesta página</div>
                <div class="sige-stat-value"><?php echo $stats['total']; ?></div>
                <div class="sige-stat-note">Na página actual</div>
            </div>
        </div>
        <div class="sige-stat-card" style="--kpi-soft:#eaf1ff;--kpi-color:#2563eb;">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.21 15.89A10 10 0 1 1 8 2.83"/><path d="M22 12A10 10 0 0 0 12 2v10z"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Masculino / Feminino</div>
                <div class="sige-stat-value"><?php echo $stats['m']; ?> / <?php echo $stats['f']; ?></div>
                <div class="sige-stat-note">Distribuição por género</div>
            </div>
        </div>
        <div class="sige-stat-card" style="--kpi-soft:#fff7e8;--kpi-color:#f59e0b;">
            <div class="sige-stat-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div class="sige-stat-info">
                <div class="sige-stat-label">Aniversários da página</div>
                <div class="sige-stat-value"><?php echo count($aniversariantes_hoje); ?></div>
                <div class="sige-stat-note">Hoje</div>
            </div>
        </div>
    </div>

    <div class="sige-birthday-panel">
        <div class="sige-birthday-info">
            <div class="sige-birthday-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 14v7"/><path d="M9 18h6"/></svg>
            </div>
            <div class="sige-birthday-text">
                <?php if(!empty($aniversariantes_hoje)): ?>
                    <h3>Aniversariantes de hoje</h3>
                    <p>Parabéns a: <?php echo esc_html(implode(', ', $aniversariantes_hoje)); ?></p>
                <?php else: ?>
                    <h3>Resumo de aniversários</h3>
                    <p>Nesta página há <strong><?php echo count($aniversariantes_mes); ?></strong> aniversário(s) registado(s) este mês.</p>
                <?php endif; ?>
            </div>
        </div>
        <div class="sige-birthday-actions">
            <button type="button" class="sige-btn-bday" data-sige-act="filtrarAniversariantesHoje" data-sige-noargs>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
                Hoje
            </button>
            <button type="button" class="sige-btn-bday" data-sige-act="filtrarAniversariantesMes" data-sige-noargs>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
                Mês
            </button>
            <button type="button" class="sige-btn-bday secondary" data-sige-act="limparFiltros" data-sige-noargs>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                Limpar
            </button>
        </div>
    </div>

    <?php
    // [12.9.8.1 hotfix] Preservar TODOS os parâmetros de routing GET (page, view,
    // tab, etc) além das filtros que esta página usa. Antes só preservávamos
    // 'page' e perdíamos 'view=alunos_lista', mandando o utilizador ao
    // dashboard quando clicava em paginação ou pesquisa.
    $filter_keys = ['paged', 'filtro_turma', 'filtro_status', 'filtro_search'];
    $preserve_params = [];
    foreach ($_GET as $k => $v) {
        if (in_array($k, $filter_keys, true)) continue;
        // Sanitizar tanto a chave como o valor (defensivo)
        $clean_key = sanitize_key($k);
        if ($clean_key === '') continue;
        $preserve_params[$clean_key] = is_scalar($v) ? sanitize_text_field((string)$v) : '';
    }
    if (!isset($preserve_params['page'])) {
        $preserve_params['page'] = 'sige-app';
    }
    // URL "limpar filtros" - só preserva navegação, sem filtros nem paginação
    $url_clear_filters = esc_url(admin_url('admin.php?' . http_build_query($preserve_params)));
    ?>
    <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="sige-toolbar" id="form-filtros">
        <?php foreach ($preserve_params as $pk => $pv): ?>
            <input type="hidden" name="<?php echo esc_attr($pk); ?>" value="<?php echo esc_attr($pv); ?>">
        <?php endforeach; ?>
        <div class="sige-search-box">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" name="filtro_search" id="filtro-texto" placeholder="Pesquisar aluno, processo ou turma" autocomplete="off" value="<?php echo esc_attr($filtro_search); ?>" data-sige-on-keyup="filtrarAlunosClient()">
        </div>
        
        <select name="filtro_turma" id="filtro-turma" class="sige-filter-select" data-sige-on-change="document.getElementById('form-filtros').submit();">
            <option value="0">Todas as turmas</option>
            <?php foreach($turmas as $t): ?>
                <option value="<?php echo (int)$t->id; ?>" <?php selected($filtro_turma, (int)$t->id); ?>><?php echo esc_html($t->classe . ' - ' . $t->nome); ?></option>
            <?php endforeach; ?>
        </select>
        
        <select name="filtro_status" id="filtro-status" class="sige-filter-select" data-sige-on-change="document.getElementById('form-filtros').submit();">
            <option value="">Todos os estados</option>
            <option value="activo" <?php selected($filtro_status, 'activo'); ?>>Activos</option>
            <option value="suspenso" <?php selected($filtro_status, 'suspenso'); ?>>Suspensos</option>
            <option value="transferido" <?php selected($filtro_status, 'transferido'); ?>>Transferidos</option>
            <option value="desistente" <?php selected($filtro_status, 'desistente'); ?>>Desistentes</option>
        </select>
        
        <button type="submit" class="sige-btn-toolbar btn-search" style="background:#3048c8;color:#fff;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            Pesquisar
        </button>
        
        <?php if ($filtro_search !== '' || $filtro_turma > 0 || $filtro_status !== ''): ?>
        <a href="<?php echo $url_clear_filters; ?>" class="sige-btn-toolbar" style="background:#f1f5f9;color:#475569;text-decoration:none;display:inline-flex;align-items:center;gap:6px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            Limpar
        </a>
        <?php endif; ?>
        
        <?php if ($sige_alunos_can_documents_emit): ?>
        <button type="button" data-sige-act="printBatchCards" data-sige-noargs class="sige-btn-toolbar btn-cards">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><line x1="7" y1="8" x2="7" y2="8.01"/><line x1="7" y1="12" x2="7" y2="12.01"/><line x1="7" y1="16" x2="7" y2="16.01"/><line x1="11" y1="8" x2="17" y2="8"/><line x1="11" y1="12" x2="17" y2="12"/><line x1="11" y1="16" x2="17" y2="16"/></svg>
            Cartões (Lote)
        </button>
        <?php endif; ?>

        <?php if (!empty($sige_can_editar_cracha)): ?>
        <button type="button" data-sige-act="abrirModeloCracha" data-sige-noargs class="sige-btn-toolbar btn-cracha-modelo">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="13.5" cy="6.5" r="2.5"/><circle cx="17.5" cy="10.5" r="2.5"/><circle cx="8.5" cy="7.5" r="2.5"/><circle cx="6.5" cy="12.5" r="2.5"/><path d="M12 2a10 10 0 1 0 0 20 1.5 1.5 0 0 0 1.06-2.56A1.5 1.5 0 0 1 14 17.5a1.5 1.5 0 0 1 1.5-1.5H17a5 5 0 0 0 5-5 9 9 0 0 0-10-9z"/></svg>
            Modelo de Crachá
        </button>
        <?php endif; ?>

        <?php if ($sige_alunos_can_export): ?>
        <button type="button" data-sige-act="exportarExcelProfissional" data-sige-noargs class="sige-btn-toolbar btn-excel">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
            Excel (.xlsx)
        </button>
        <?php endif; ?>
    </form>

    <div class="sige-mobile-status-chips" aria-label="Filtros rápidos de alunos" role="toolbar">
        <button type="button" class="sige-mobile-status-chip is-active" data-sige-mobile-chip="todos" aria-pressed="true">Todos</button>
        <button type="button" class="sige-mobile-status-chip" data-sige-mobile-chip="activos" aria-pressed="false">Activos</button>
        <button type="button" class="sige-mobile-status-chip" data-sige-mobile-chip="inactivos" aria-pressed="false">Inactivos</button>
        <?php if ($sige_alunos_can_finance_view): ?>
        <button type="button" class="sige-mobile-status-chip" data-sige-mobile-chip="devedores" aria-pressed="false">Devedores</button>
        <?php endif; ?>
    </div>
    <div class="sige-alunos-mobile-live" aria-live="polite"></div>

    <?php
    // [12.9.8] Contador de resultados - mostrando X-Y de Z
    $range_from = $total_alunos > 0 ? ($offset + 1) : 0;
    $range_to   = min($offset + $per_page, $total_alunos);
    ?>
    <div class="sige-results-counter">
        <span>Mostrando <strong><?php echo $range_from; ?>-<?php echo $range_to; ?></strong> de <strong><?php echo $total_alunos; ?></strong> aluno<?php echo $total_alunos === 1 ? '' : 's'; ?>
        <?php if ($filtro_search !== ''): ?> · pesquisa: <em><?php echo esc_html($filtro_search); ?></em><?php endif; ?>
        <?php if ($filtro_turma > 0): ?>
            <?php
            $turma_active = null;
            foreach ($turmas as $t) if ((int)$t->id === $filtro_turma) { $turma_active = $t; break; }
            ?>
            <?php if ($turma_active): ?> · turma: <em><?php echo esc_html($turma_active->classe . ' - ' . $turma_active->nome); ?></em><?php endif; ?>
        <?php endif; ?>
        <?php if ($filtro_status !== ''): ?> · estado: <em><?php echo esc_html(ucfirst($filtro_status)); ?></em><?php endif; ?>
        </span>
        <?php if ($total_pages > 1): ?>
        <span class="sige-results-page">página <strong><?php echo $paged; ?></strong> de <strong><?php echo $total_pages; ?></strong></span>
        <?php endif; ?>
    </div>

    <div class="sige-aluno-grid" id="grid-alunos">
        <?php if($alunos): foreach($alunos as $a):
            $foto = !empty($a->foto) ? $a->foto : SIGE_URL . 'assets/img/avatar-default.svg';
            // [12.9.8] Removido $json embutido no botão Editar - agora editarAluno()
            // [12.9.12] Corrige botões mudos no card do aluno.
            $sige_doc_payload = function_exists('sige_alunos_perf_card_document_payload') ? sige_alunos_perf_card_document_payload($a) : $a;
            $json = wp_json_encode($sige_doc_payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
            if (!$json) { $json = '{}'; }
            // recebe apenas o ID e faz AJAX fetch (action sige_get_aluno_full).
            $nome = !empty($a->nome_completo) ? $a->nome_completo : 'Sem Nome';
            $turma_label = (!empty($a->classe) && !empty($a->turma_nome)) ? "{$a->classe} - {$a->turma_nome}" : "Sem Turma";
            $raw_status = !empty($a->status) ? strtolower($a->status) : 'activo';
            $status_label = ucfirst($raw_status);
            
            $tsN = (!empty($a->data_nascimento) && strtotime($a->data_nascimento)) ? strtotime($a->data_nascimento) : 0;
            $mes_nasc = $tsN ? wp_date('m', $tsN) : '00';
            $dia_nasc = $tsN ? wp_date('d', $tsN) : '00';
            $is_hoje = ($tsN && $mes_nasc === $hoje_mes && $dia_nasc === $hoje_dia);
            $has_bi = !empty($a->doc_bi_url);
            
            // WhatsApp
            $tel = '';
            if (!empty($a->whatsapp_notificacoes)) $tel = $a->whatsapp_notificacoes;
            else $tel = !empty($a->telemovel_pai) ? $a->telemovel_pai : (!empty($a->telemovel_mae) ? $a->telemovel_mae : '');
            $wa_link = '';
            if($tel && strlen(preg_replace('/\D/', '', $tel)) >= 9) {
                $tel_clean = sige_telefone_normalizar($tel);
                $msg = urlencode("Olá Sr(a). Encarregado(a) de {$nome}, contactamos do {$nome_escola} para informar que...");
                $wa_link = "https://wa.me/{$tel_clean}?text={$msg}";
            }
            
            // Saúde
            $tem_alergia = (!empty($a->alergias) || !empty($a->condicoes_medicas));
            $info_medica = esc_attr(
                "Grupo: " . (!empty($a->grupo_sanguineo) ? $a->grupo_sanguineo : 'Desconhecido') . "\n" .
                "Alergias: " . (!empty($a->alergias) ? $a->alergias : 'Nenhuma') . "\n" .
                "Condições: " . (!empty($a->condicoes_medicas) ? $a->condicoes_medicas : 'Nenhuma')
            );
            
            // Transporte
            $rota_nome = !empty($a->transporte_nome_rota) ? $a->transporte_nome_rota : '';
            $tem_transporte = (!empty($a->rota_transporte_id) && (int)$a->rota_transporte_id > 0 && $rota_nome);
            // Snapshot financeiro operacional (apenas leitura)
            $fin = $saldo_financeiro_por_aluno[(int)$a->id] ?? ['divida'=>0.0,'em_plano'=>0.0,'qtd_parcial'=>0,'qtd_pendente'=>0,'credito'=>0.0];
            $fin_divida  = round((float)($fin['divida'] ?? 0), 2);
            $fin_plano   = round((float)($fin['em_plano'] ?? 0), 2);
            $fin_credito = round((float)($fin['credito'] ?? 0), 2);
            $fin_cls = 'regular';
            $fin_label = 'Regular';
            $fin_valor = '';
            if ($fin_divida > 0) {
                $fin_cls = ((int)($fin['qtd_parcial'] ?? 0) > 0) ? 'parcial' : 'divida';
                $fin_label = ($fin_cls === 'parcial') ? 'Parcial' : 'Em dívida';
                $fin_valor = number_format($fin_divida, 2, ',', '.') . ' MZN';
            } elseif ($fin_credito > 0) {
                $fin_cls = 'credito';
                $fin_label = 'Crédito';
                $fin_valor = number_format($fin_credito, 2, ',', '.') . ' MZN';
            } elseif ($fin_plano > 0) {
                $fin_cls = 'plano';
                $fin_label = 'Em plano';
                $fin_valor = number_format($fin_plano, 2, ',', '.') . ' MZN';
            }
            $fin_url = admin_url('admin.php?page=sige-app&view=financeiro-pagamentos&aluno_id=' . (int)$a->id);
            $fin_history_url = function_exists('sige_fin_hist_aluno_build_url')
                ? sige_fin_hist_aluno_build_url((int)$a->id, 'html')
                : wp_nonce_url(admin_url('admin.php?sige_print=historico_financeiro_aluno&id=' . (int)$a->id), 'sige_hist_fin_aluno_' . (int)$a->id);
            $fin_extracts_url = admin_url('admin.php?page=sige-app&view=financeiro-extratos&modo=aluno&aluno_id=' . (int)$a->id);
            $sige_quality_card = function_exists('sige_aluno_360_quality_index')
                ? sige_aluno_360_quality_index($a, ['turma_label' => $turma_label, 'turma_id' => (int)$a->turma_id])
                : ['score' => 0, 'class' => 'pendente', 'label' => 'Por avaliar'];
            $sige_quality_score = isset($sige_quality_card['score']) ? (int)$sige_quality_card['score'] : 0;
            $sige_quality_class = isset($sige_quality_card['class']) ? sanitize_html_class($sige_quality_card['class']) : 'pendente';
            $sige_quality_label = isset($sige_quality_card['label']) ? (string)$sige_quality_card['label'] : 'Por avaliar';
            // v12.11.9.69 - evita menu redundante em perfis apenas leitura.
            $sige_aluno_card_has_extra_actions = $sige_alunos_can_finance_view || $sige_alunos_can_portal_access || $sige_alunos_can_documents_emit || ($sige_alunos_can_documents_view && $has_bi) || $sige_alunos_can_edit || $sige_alunos_can_delete;
        ?>
            <div class="sige-aluno-card"
                 data-search="<?php echo esc_attr(strtolower(wp_strip_all_tags($nome . ' ' . (string)$a->numero_processo . ' ' . $turma_label . ' ' . $status_label))); ?>"
                 data-turma-id="<?php echo (int)$a->turma_id; ?>"
                 data-status="<?php echo esc_attr(sanitize_key($raw_status)); ?>"
                 data-has-debt="<?php echo ($sige_alunos_can_finance_view && in_array($fin_cls, ['divida','parcial'], true)) ? '1' : '0'; ?>"
                 data-mes="<?php echo esc_attr($mes_nasc); ?>"
                 data-dia="<?php echo esc_attr($dia_nasc); ?>"
                 data-hoje="<?php echo $is_hoje ? '1' : '0'; ?>">
                
                <img src="<?php echo esc_url($foto); ?>" class="sige-aluno-foto" alt="<?php echo esc_attr('Foto de ' . $nome); ?>">
                
                <div class="sige-aluno-info">
                    <h3 class="sige-aluno-nome">
                        <?php echo esc_html($nome); ?>
                        <?php if($mes_nasc === $hoje_mes): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2" style="width:16px;height:16px;"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M12 14v7"/></svg>
                        <?php endif; ?>
                        <?php if($is_hoje): ?><span class="sige-badge sige-badge-hoje">HOJE</span><?php endif; ?>
                        <?php if(!empty($a->tem_desconto_irmao)): ?><span class="sige-badge sige-badge-irmao">IRMÃO</span><?php endif; ?>
                        <?php if(!empty($a->tem_desconto_funcionario)): ?><span class="sige-badge sige-badge-funcionario">FUNC.</span><?php endif; ?>
                        <?php if($tem_alergia): ?><span class="sige-badge sige-badge-saude" title="Informação de saúde registada. Consulte a Ficha 360º conforme a sua permissão.">SAÚDE</span><?php endif; ?>
                    </h3>
                    
                    <p class="sige-aluno-processo">Processo: <strong><?php echo esc_html($a->numero_processo); ?></strong></p>
                    
                    <div class="sige-aluno-tags">
                        <span class="sige-tag-turma">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
                            <?php echo esc_html($turma_label); ?>
                        </span>
                        <span class="sige-status-badge sige-status-<?php echo esc_attr(sanitize_html_class($raw_status)); ?>"><?php echo esc_html($status_label); ?></span>
                        <button type="button" class="sige-data-quality-badge sige-data-quality-<?php echo esc_attr($sige_quality_class); ?>" data-sige-act="abrirFichaAluno360" data-sige-args="[<?php echo (int)$a->id; ?>]" title="Abrir Ficha 360º · Índice de qualidade dos dados" aria-label="Abrir Ficha 360º de <?php echo esc_attr($nome); ?> · Dados <?php echo esc_attr((string)$sige_quality_score); ?>%">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            Dados <?php echo esc_html($sige_quality_score); ?>%
                        </button>
                        <?php if ($sige_alunos_can_finance_view): ?>
                        <a class="sige-finance-badge sige-finance-<?php echo esc_attr($fin_cls); ?>" href="<?php echo esc_url($fin_url); ?>" title="Abrir pagamentos do aluno">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/></svg>
                            <?php echo esc_html($fin_label); ?><?php if($fin_valor !== ''): ?> · <?php echo esc_html($fin_valor); ?><?php endif; ?>
                        </a>
                        <?php endif; ?>
                        
                        <?php if($tem_transporte): ?>
                            <span class="sige-tag-transporte">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M8 6v6M16 6v6M2 12h20M6 18h12a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2z"/><circle cx="7" cy="18" r="2"/><circle cx="17" cy="18" r="2"/></svg>
                                <?php echo esc_html($rota_nome); ?>
                            </span>
                        <?php endif; ?>
                        
                        <?php if($wa_link && $sige_alunos_can_whatsapp): ?>
                            <a class="sige-wa-btn" href="<?php echo esc_url($wa_link); ?>" target="_blank" rel="noopener" title="WhatsApp">
                                <svg viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
                                WhatsApp
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="sige-mobile-card-actions-row" aria-label="Acções rápidas do aluno">
                    <button class="sige-mobile-primary-action" type="button" data-sige-act="abrirFichaAluno360" data-sige-args="[<?php echo (int)$a->id; ?>]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15h6"/><path d="M9 11h2"/></svg>
                        <span>Ver ficha</span>
                    </button>
                    <?php if ($sige_alunos_can_finance_view): ?>
                    <a class="sige-mobile-primary-action sige-mobile-fin-action" href="<?php echo esc_url($fin_history_url); ?>" target="_blank" rel="noopener" title="Abrir ambiente Histórico Financeiro do aluno">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h4"/><path d="M18 12c-1.5 0-3 .8-3 2.3s1.5 2.2 3 2.2 3 .8 3 2.3S19.5 21 18 21"/></svg>
                        <span>Histórico financeiro</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($sige_alunos_can_edit): ?>
                    <button class="sige-mobile-primary-action" type="button" data-sige-act="editarAluno" data-sige-args="[<?php echo (int)$a->id; ?>]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/></svg>
                        <span>Editar</span>
                    </button>
                    <?php endif; ?>
                <?php if ($sige_aluno_card_has_extra_actions): ?>
                <details class="sige-card-actions">
                    <summary class="sige-actions-trigger" aria-label="Abrir acções de <?php echo esc_attr($nome); ?>" title="Acções" aria-expanded="false">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                            <circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/>
                        </svg>
                        <span class="sige-actions-trigger-label">Ver acções</span>
                    </summary>
                    <div class="sige-actions-menu" role="menu" aria-label="Acções do aluno">
                    <button class="sige-btn-action btn-360" type="button" data-sige-act="abrirFichaAluno360" data-sige-args="[<?php echo (int)$a->id; ?>]" title="Ficha 360º">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                        <span class="sige-action-label">Ficha 360º</span>
                    </button>
                    <?php if ($sige_alunos_can_finance_view): ?>
                    <a class="sige-btn-action btn-fin-historico" href="<?php echo esc_url($fin_history_url); ?>" target="_blank" rel="noopener" title="Abrir ambiente Histórico Financeiro do aluno">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/><path d="M12 21v-4"/><path d="M9 18l3 3 3-3"/></svg>
                        <span class="sige-action-label">Histórico financeiro</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($sige_alunos_can_portal_access): ?>
                    <a class="sige-btn-action btn-portal" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=aluno_portal&aluno_id=' . (int)$a->id)); ?>" title="Página do Aluno">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M19 8v6"/><path d="M22 11h-6"/></svg>
                        <span class="sige-action-label">Página do Aluno</span>
                    </a>
                    <button class="sige-btn-action btn-key" type="button" data-sige-act="verAcesso" data-sige-args="<?php echo esc_attr(wp_json_encode([$a->numero_processo, $nome])); ?>" title="Acesso Portal">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/></svg>
                        <span class="sige-action-label">Acesso Portal</span>
                    </button>
                    <?php endif; ?>
                    <?php if ($sige_alunos_can_documents_emit): ?>
                    <button class="sige-btn-action btn-card" type="button" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="printSingleCard" data-sige-json="<?php echo esc_attr($json); ?>" title="Cartão">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2M15 12h2"/><path d="M7 16h10"/></svg>
                        <span class="sige-action-label">Cartão</span>
                    </button>
                    <?php endif; ?>
                    <?php
                    $sige_bi_secure_url = '';
                    if ($sige_alunos_can_documents_view && $has_bi && function_exists('sige_secure_document_url')) {
                        $sige_bi_secure_url = sige_secure_document_url((int)$a->id, 'doc_bi_url');
                    }
                    ?>
                    <?php if($sige_alunos_can_documents_view && $has_bi && !empty($sige_bi_secure_url)): ?>
                        <a class="sige-btn-action btn-clip" href="<?php echo esc_url($sige_bi_secure_url); ?>" target="_blank" rel="noopener" title="BI Digital protegido">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21.44 11.05l-9.19 9.19a6 6 0 0 1-8.49-8.49l9.19-9.19a4 4 0 0 1 5.66 5.66l-9.2 9.19a2 2 0 0 1-2.83-2.83l8.49-8.48"/></svg>
                            <span class="sige-action-label">BI Digital</span>
                        </a>
                    <?php endif; ?>
                    <?php if ($sige_alunos_can_documents_emit): ?>
                    <button class="sige-btn-action" type="button" title="Declaração" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="imprimirDeclaracao" data-sige-json="<?php echo esc_attr($json); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/></svg>
                        <span class="sige-action-label">Declaração</span>
                    </button>
                    <button class="sige-btn-action" type="button" title="Boletim" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="imprimirBoletim" data-sige-json="<?php echo esc_attr($json); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                        <span class="sige-action-label">Boletim</span>
                    </button>
                    <?php endif; ?>
                    <?php if ($sige_alunos_can_edit): ?>
                    <button class="sige-btn-action" type="button" title="Editar" data-sige-act="editarAluno" data-sige-args="[<?php echo (int)$a->id; ?>]">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        <span class="sige-action-label">Editar</span>
                    </button>
                    <?php endif; ?>
                    <?php if ($sige_alunos_can_delete): ?>
                    <button class="sige-btn-action btn-delete" type="button" title="Remover" data-sige-act="apagarAluno" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$a->id, $nome])); ?>">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        <span class="sige-action-label">Remover</span>
                    </button>
                    <?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>
                </div>
            </div>
        <?php endforeach; else: ?>
            <div class="sige-empty-state">
                <div class="sige-empty-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                </div>
                <h3>Nenhum aluno encontrado</h3>
                <?php if ($filtro_search !== '' || $filtro_turma > 0 || $filtro_status !== ''): ?>
                    <p>A pesquisa actual não devolveu resultados. Tente <a href="<?php echo $url_clear_filters; ?>">limpar os filtros</a>.</p>
                <?php else: ?>
                    <p>Comece por matricular o primeiro aluno do ano lectivo <?php echo esc_html($ano_lectivo); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>

    <?php
    // [12.9.8] BARRA DE PAGINAÇÃO
    if ($total_pages > 1):
        // [12.9.8.1 hotfix] Construir URL base com TODOS os params de routing
        // (page, view, etc) + filtros activos. Antes só preservávamos 'page'.
        $base_args = $preserve_params;
        if ($filtro_turma > 0)        $base_args['filtro_turma']  = $filtro_turma;
        if ($filtro_status !== '')    $base_args['filtro_status'] = $filtro_status;
        if ($filtro_search !== '')    $base_args['filtro_search'] = $filtro_search;
        $page_url = function ($p) use ($base_args) {
            $args = $base_args;
            $args['paged'] = $p;
            return esc_url(admin_url('admin.php?' . http_build_query($args)));
        };
        // Paginação compacta: primeiro, anterior, janela de 5, próximo, último
        $window = 2; // páginas de cada lado da actual
        $window_start = max(1, $paged - $window);
        $window_end   = min($total_pages, $paged + $window);
    ?>
    <nav class="sige-pagination" aria-label="Paginação">
        <?php if ($paged > 1): ?>
            <a href="<?php echo $page_url(1); ?>" class="sige-page-btn" title="Primeira página">«</a>
            <a href="<?php echo $page_url($paged - 1); ?>" class="sige-page-btn" title="Página anterior">‹</a>
        <?php else: ?>
            <span class="sige-page-btn disabled">«</span>
            <span class="sige-page-btn disabled">‹</span>
        <?php endif; ?>

        <?php if ($window_start > 1): ?>
            <a href="<?php echo $page_url(1); ?>" class="sige-page-btn">1</a>
            <?php if ($window_start > 2): ?><span class="sige-page-ellipsis">…</span><?php endif; ?>
        <?php endif; ?>

        <?php for ($i = $window_start; $i <= $window_end; $i++): ?>
            <?php if ($i === $paged): ?>
                <span class="sige-page-btn current"><?php echo $i; ?></span>
            <?php else: ?>
                <a href="<?php echo $page_url($i); ?>" class="sige-page-btn"><?php echo $i; ?></a>
            <?php endif; ?>
        <?php endfor; ?>

        <?php if ($window_end < $total_pages): ?>
            <?php if ($window_end < $total_pages - 1): ?><span class="sige-page-ellipsis">…</span><?php endif; ?>
            <a href="<?php echo $page_url($total_pages); ?>" class="sige-page-btn"><?php echo $total_pages; ?></a>
        <?php endif; ?>

        <?php if ($paged < $total_pages): ?>
            <a href="<?php echo $page_url($paged + 1); ?>" class="sige-page-btn" title="Próxima página">›</a>
            <a href="<?php echo $page_url($total_pages); ?>" class="sige-page-btn" title="Última página">»</a>
        <?php else: ?>
            <span class="sige-page-btn disabled">›</span>
            <span class="sige-page-btn disabled">»</span>
        <?php endif; ?>
    </nav>
    <?php endif; ?>

</div>

<nav class="sige-mobile-bottom-nav" aria-label="Navegação principal mobile">
    <?php if ($sige_alunos_can_dashboard_nav): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=dashboard')); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 11l9-8 9 8"/><path d="M5 10v10h14V10"/><path d="M9 20v-6h6v6"/></svg>
        <span>Início</span>
    </a>
    <?php endif; ?>
    <?php if ($sige_alunos_can_portaria_nav): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=portaria')); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-5"/></svg>
        <span>Portaria</span>
    </a>
    <?php endif; ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=alunos_lista')); ?>" class="is-active" aria-current="page">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        <span>Alunos</span>
    </a>
    <?php if ($sige_alunos_can_finance_pay): ?>
    <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-pagamentos')); ?>">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 10h18"/><path d="M7 15h4"/></svg>
        <span>Pagamentos</span>
    </a>
    <?php endif; ?>
    <?php if ($sige_alunos_can_more_nav): ?>
    <button type="button" class="sige-mobile-more-trigger" data-sige-mobile-more="1" aria-label="Abrir mais opções" aria-controls="sige-sidebar" aria-expanded="false" aria-haspopup="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="5" cy="12" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/></svg>
        <span>Mais</span>
    </button>
    <?php endif; ?>
</nav>

<div id="modal-aluno" class="sige-modal sige-aluno-modal-pro">
    <div class="sige-modal-content">
        <div class="sige-modal-header">
            <div class="sige-modal-title-wrap">
                <div class="sige-modal-title-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="8.5" cy="7" r="4"/><line x1="20" y1="8" x2="20" y2="14"/><line x1="23" y1="11" x2="17" y2="11"/></svg>
                </div>
                <div class="sige-modal-title-text">
                    <span class="sige-modal-kicker">Secretaria</span>
                    <h2 id="modal-title">Registar Aluno</h2>
                    <p>Preencha os dados essenciais, encarregados, saúde e arquivo digital num fluxo simples e seguro.</p>
                </div>
            </div>
            <button type="button" data-sige-act="fecharModal" data-sige-noargs class="sige-modal-close" aria-label="Fechar">&times;</button>
        </div>
        
        <div class="sige-tabs">
            <div class="sige-tab active" data-tab="tab-dados" data-sige-act="switchTab">Dados gerais</div>
            <div class="sige-tab" data-tab="tab-encarregados" data-sige-act="switchTab">Encarregados</div>
            <div class="sige-tab tab-health" data-tab="tab-saude" data-sige-act="switchTab">Saúde e emergência</div>
            <div class="sige-tab" data-tab="tab-docs" data-sige-act="switchTab">Arquivo digital</div>
        </div>
        
        <form id="form-aluno">
            <input type="hidden" name="id_aluno" id="id_aluno">
            <input type="hidden" name="action" value="sige_salvar_aluno">
            <input type="hidden" name="_sige_nonce" id="_sige_nonce" value="">
            <input type="hidden" name="doc_bi_url" id="doc_bi_url">
            <input type="hidden" name="doc_cert_url" id="doc_cert_url">
            <input type="hidden" name="doc_vacina_url" id="doc_vacina_url">
            
            <div class="sige-modal-body">
                <div id="tab-dados" class="sige-tab-content active">
                    <div style="display:flex; gap:24px; margin-bottom:20px; flex-wrap:wrap;">
                        <div style="width:160px; text-align:center;">
                            <input type="hidden" name="foto_url" id="foto_url">
                            <div class="sige-foto-upload" id="btn-upload-foto">
                                <img id="preview-img" src="" style="display:none;">
                                <svg id="placeholder-foto" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>
                                <div class="overlay">Alterar</div>
                            </div>
                            <p style="font-size:0.7rem; color:var(--sige-slate-400); margin-top:8px;">FOTO 3X4</p>
                        </div>
                        
                        <div style="flex:1; min-width:280px;">
                            <div class="sige-boletim-section">
                                <span class="sige-boletim-title">Dados do aluno</span>
                                <div class="sige-field-row sige-field-row-2">
                                    <div class="sige-field-group sige-span-2">
                                        <label>Nome Completo *</label>
                                        <input type="text" name="nome" id="nome" required>
                                    </div>
                                </div>
                                <div class="sige-field-row sige-field-row-3">
                                    <div class="sige-field-group">
                                        <label>Nascimento *</label>
                                        <input type="date" name="data_nascimento" id="data_nascimento" required>
                                    </div>
                                    <div class="sige-field-group">
                                        <label>Género *</label>
                                        <select name="genero" id="genero" required>
                                            <option value="M">Masculino</option>
                                            <option value="F">Feminino</option>
                                        </select>
                                    </div>
                                    <div class="sige-field-group">
                                        <label>Turma <?php echo $ano_lectivo; ?> *</label>
                                        <select name="turma_id" id="turma_id" required>
                                            <option value="">-- Seleccionar --</option>
                                            <?php foreach($turmas as $t): ?>
                                                <option value="<?php echo $t->id; ?>"><?php echo esc_html($t->classe . ' - ' . $t->nome); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="sige-field-row sige-field-row-3">
                                    <div class="sige-field-group">
                                        <label>Tipo Documento</label>
                                        <select name="tipo_documento" id="tipo_documento">
                                            <option value="">-- Seleccionar --</option>
                                            <option value="BI">Bilhete de Identidade</option>
                                            <option value="Boletim de Nascimento">Boletim de Nascimento</option>
                                            <option value="Cedula Pessoal">Cédula Pessoal</option>
                                            <option value="Certidao de Nascimento">Certidão de Nascimento</option>
                                        </select>
                                    </div>
                                    <div class="sige-field-group">
                                        <label>Documento Nº</label>
                                        <input type="text" name="documento_numero" id="documento_numero">
                                    </div>
                                    <div class="sige-field-group">
                                        <label>Bairro</label>
                                        <input type="text" name="bairro" id="bairro">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="sige-boletim-section highlight">
                        <span class="sige-boletim-title">Matrícula e situação</span>
                        <div class="sige-field-row sige-field-row-3">
                            <div class="sige-field-group">
                                <label>Situação do Aluno</label>
                                <select name="status" id="status_aluno" style="border-color:var(--sige-warning);font-weight:700;color:var(--sige-warning);">
                                    <option value="activo">Activo</option>
                                    <option value="suspenso">Suspenso</option>
                                    <option value="transferido">Transferido</option>
                                    <option value="desistente">Desistentes</option>
                                </select>
                            </div>
                            <div class="sige-field-group">
                                <label>Nacionalidade</label>
                                <input type="text" name="nacionalidade" id="nacionalidade" value="Moçambicana">
                            </div>
                            <div class="sige-field-group">
                                <label>NUIT Encarregado</label>
                                <input type="text" name="nuit_encarregado" id="nuit_encarregado" placeholder="9 dígitos" maxlength="9">
                            </div>
                        </div>
                        
                        <div class="sige-checkbox-row">
                            <label>
                                <input type="checkbox" name="tem_desconto_irmao" id="tem_desconto_irmao" value="1">
                                Desconto Irmão
                            </label>
                            <label>
                                <input type="checkbox" name="tem_desconto_funcionario" id="tem_desconto_funcionario" value="1">
                                Filho de Funcionário
                            </label>
                        </div>
                        
                        <div class="sige-extras-box">
                            <label class="title">Actividades & Alimentação (Extras)</label>
                            <div class="sige-extras-grid">
                                <label><input type="checkbox" name="tem_estudos" id="tem_estudos" value="1"> Estudos Orientados</label>
                                <label><input type="checkbox" name="tem_ingles" id="tem_ingles" value="1"> Inglês Intensivo</label>
                                <label><input type="checkbox" name="tem_desporto" id="tem_desporto" value="1"> Desporto Escolar</label>
                                <label><input type="checkbox" name="tem_pequeno_almoco" id="tem_pequeno_almoco" value="1"> Pequeno Almoço</label>
                                <label style="grid-column:span 2;"><input type="checkbox" name="tem_almoco" id="tem_almoco" value="1"> Almoço Completo</label>
                            </div>
                        </div>
                        
                        <div class="sige-creche-box">
                            <label class="title">Regime de Frequência (Creche)</label>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                <label style="display:flex; align-items:center; gap:8px; padding:10px; background:#fff; border-radius:8px; border:2px solid #e1bee7; cursor:pointer;">
                                    <input type="radio" name="regime_creche" id="regime_semi" value="semi_integral">
                                    <span><strong>Semi-integral</strong><br><small style="color:#888;">2-4a: <?php echo number_format($creche_precos['semi_ate4'],0,',','.'); ?> <?php echo esc_html(sige_moeda()); ?></small></span>
                                </label>
                                <label style="display:flex; align-items:center; gap:8px; padding:10px; background:#fff; border-radius:8px; border:2px solid #e1bee7; cursor:pointer;">
                                    <input type="radio" name="regime_creche" id="regime_integral" value="integral">
                                    <span><strong>Integral</strong><br><small style="color:#888;">2-4a: <?php echo number_format($creche_precos['int_ate4'],0,',','.'); ?> <?php echo esc_html(sige_moeda()); ?></small></span>
                                </label>
                                <label style="grid-column:span 2; display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="radio" name="regime_creche" id="regime_nenhum" value="" checked>
                                    <span style="color:#666;">Sem regime de creche (ensino regular)</span>
                                </label>
                            </div>
                        </div>

                        <?php if ($has_regime_mensalidade_col): ?>
                        <!-- [v13] Regime de Mensalidade (Tempo Inteiro / Meio Dia) -->
                        <div class="sige-extras-box" style="border-top:3px solid #10b981;">
                            <label class="title" style="color:#065f46;">💰 Regime de Mensalidade</label>
                            <p style="font-size:12px; color:#64748b; margin:4px 0 10px;">
                                Define qual serviço de mensalidade é aplicado automaticamente ao aluno.
                                Útil para escolas que cobram diferente por tempo de permanência (ex: Casa Colorida).
                            </p>
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:10px;">
                                <label style="display:flex; align-items:center; gap:8px; padding:10px; background:#fff; border-radius:8px; border:2px solid #a7f3d0; cursor:pointer;">
                                    <input type="radio" name="regime_mensalidade" id="regime_mens_tempo_inteiro" value="tempo_inteiro">
                                    <span><strong>Tempo Inteiro</strong><br><small style="color:#888;">Permanência integral</small></span>
                                </label>
                                <label style="display:flex; align-items:center; gap:8px; padding:10px; background:#fff; border-radius:8px; border:2px solid #a7f3d0; cursor:pointer;">
                                    <input type="radio" name="regime_mensalidade" id="regime_mens_meio_dia" value="meio_dia">
                                    <span><strong>Meio Dia</strong><br><small style="color:#888;">Só manhã ou só tarde</small></span>
                                </label>
                                <label style="grid-column:span 2; display:flex; align-items:center; gap:8px; cursor:pointer;">
                                    <input type="radio" name="regime_mensalidade" id="regime_mens_nenhum" value="" checked>
                                    <span style="color:#666;">Sem regime específico (usa mensalidade base)</span>
                                </label>
                            </div>
                        </div>
                        <?php endif; ?>

                        <?php if ($has_ativ_extras_table && !empty($atividades_extras_disponiveis)): ?>
                        <!-- [v13] Actividades Extras vinculáveis (Judo, Piano, Natação, etc.) -->
                        <div class="sige-extras-box" style="border-top:3px solid #f59e0b;">
                            <label class="title" style="color:#92400e;">🎨 Actividades Extras Opcionais</label>
                            <p style="font-size:12px; color:#64748b; margin:4px 0 10px;">
                                Marque as actividades em que o aluno está inscrito.
                                Serão automaticamente cobradas junto à mensalidade mensal.
                            </p>
                            <div class="sige-extras-grid">
                                <?php foreach ($atividades_extras_disponiveis as $atv): ?>
                                    <label style="display:flex; align-items:center; gap:8px; padding:8px; background:#fff; border-radius:6px; border:1px solid #fde68a;">
                                        <input type="checkbox" name="atividades_extras[]"
                                               id="atv_extra_<?php echo (int)$atv->id; ?>"
                                               class="atv-extra-checkbox"
                                               value="<?php echo (int)$atv->id; ?>">
                                        <span>
                                            <strong><?php echo esc_html($atv->nome); ?></strong>
                                            <?php if ((float)$atv->valor > 0): ?>
                                                <br><small style="color:#888;"><?php echo number_format((float)$atv->valor, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?>/mês</small>
                                            <?php else: ?>
                                                <br><small style="color:#ef4444;">(valor por definir)</small>
                                            <?php endif; ?>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                            <p style="font-size:11px; color:#92400e; margin-top:8px;">
                                💡 Para adicionar ou editar actividades disponíveis, vá a <em>Tesouraria → Preços e Serviços</em>.
                            </p>
                        </div>
                        <?php elseif ($has_ativ_extras_table): ?>
                        <div class="sige-extras-box" style="border-top:3px solid #f59e0b; background:#fffbeb;">
                            <label class="title" style="color:#92400e;">🎨 Actividades Extras Opcionais</label>
                            <p style="font-size:12px; color:#92400e; margin:4px 0;">
                                Nenhuma actividade extra configurada no catálogo.
                                Vá a <em>Tesouraria → Preços e Serviços</em> e crie as actividades que a escola pretende cobrar.
                            </p>
                        </div>
                        <?php endif; ?>
                        
                        <div class="sige-field-row sige-field-row-2" style="margin-top:16px;">
                            <div class="sige-field-group">
                                <label>Mensalidade Base (<?php echo esc_html(sige_moeda()); ?>)</label>
                                <input type="number" step="0.01" name="mensalidade_base" id="mensalidade_base" placeholder="Ex: 5000.00">
                            </div>
                            <div class="sige-field-group">
                                <label>Transporte Escolar</label>
                                <select name="rota_transporte_id" id="rota_transporte_id">
                                    <option value="0">Sem transporte</option>
                                    <?php if(!empty($rotas)): foreach($rotas as $r): ?>
                                        <option value="<?php echo (int)$r->id; ?>">
                                            <?php echo esc_html($r->nome_rota); ?> (<?php echo number_format((float)$r->preco_mensal, 2); ?> MT)
                                        </option>
                                    <?php endforeach; endif; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="sige-field-row">
                            <div class="sige-field-group">
                                <label>Processo (visual)</label>
                                <input type="text" id="numero_processo_view" disabled style="background:var(--sige-slate-50);">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="tab-encarregados" class="sige-tab-content">
                    <div class="sige-boletim-section">
                        <span class="sige-boletim-title">3. Dados do Pai</span>
                        <div class="sige-field-row sige-field-row-2">
                            <div class="sige-field-group sige-span-2">
                                <label>Nome</label>
                                <input type="text" name="nome_pai" id="nome_pai">
                            </div>
                        </div>
                        <div class="sige-field-row sige-field-row-3">
                            <div class="sige-field-group">
                                <label>Profissão</label>
                                <input type="text" name="profissao_pai" id="profissao_pai">
                            </div>
                            <div class="sige-field-group">
                                <label>Telemóvel 1</label>
                                <input type="tel" name="telemovel_pai" id="telemovel_pai" placeholder="82/83/84/85/86/87..." maxlength="9">
                            </div>
                            <div class="sige-field-group">
                                <label>Telemóvel 2</label>
                                <input type="tel" name="telemovel_pai_2" id="telemovel_pai_2" placeholder="Opcional" maxlength="9">
                            </div>
                        </div>
                        <div class="sige-field-row">
                            <div class="sige-field-group">
                                <label>E-mail</label>
                                <input type="email" name="email_pai" id="email_pai">
                            </div>
                        </div>
                    </div>
                    
                    <div class="sige-boletim-section">
                        <span class="sige-boletim-title">4. Dados da Mãe</span>
                        <div class="sige-field-row sige-field-row-2">
                            <div class="sige-field-group sige-span-2">
                                <label>Nome</label>
                                <input type="text" name="nome_mae" id="nome_mae">
                            </div>
                        </div>
                        <div class="sige-field-row sige-field-row-3">
                            <div class="sige-field-group">
                                <label>Profissão</label>
                                <input type="text" name="profissao_mae" id="profissao_mae">
                            </div>
                            <div class="sige-field-group">
                                <label>Telemóvel 1</label>
                                <input type="tel" name="telemovel_mae" id="telemovel_mae" placeholder="82/83/84/85/86/87..." maxlength="9">
                            </div>
                            <div class="sige-field-group">
                                <label>Telemóvel 2</label>
                                <input type="tel" name="telemovel_mae_2" id="telemovel_mae_2" placeholder="Opcional" maxlength="9">
                            </div>
                        </div>
                        <div class="sige-field-row">
                            <div class="sige-field-group">
                                <label>E-mail</label>
                                <input type="email" name="email_mae" id="email_mae">
                            </div>
                        </div>
                    </div>
                    
                    <div class="sige-boletim-section" style="border-color:var(--sige-success-light);">
                        <span class="sige-boletim-title" style="color:var(--sige-success);">WhatsApp (Notificações)</span>
                        <div class="sige-field-row">
                            <div class="sige-field-group">
                                <label>WhatsApp para Notificações</label>
                                <input type="text" name="whatsapp_notificacoes" id="whatsapp_notificacoes" placeholder="Ex: 841234567">
                                <small style="display:block; margin-top:6px; color:var(--sige-slate-500); font-size:0.75rem;">
                                    Este número será usado para recibos, cobranças e notificações automáticas.
                                </small>
                            </div>
                        </div>
                    </div>

                    <div class="sige-boletim-section sige-guardian-advanced-box">
                        <span class="sige-boletim-title">Gestão avançada de encarregados</span>
                        <div class="sige-guardian-note">
                            <strong>Boa prática:</strong> defina quem é o encarregado principal, quais canais estão autorizados e quem pode buscar o aluno. Estes dados ajudam a proteger menores e reduzem falhas de comunicação.
                        </div>
                        <div class="sige-field-row sige-field-row-3">
                            <div class="sige-field-group">
                                <label>Encarregado principal</label>
                                <select name="encarregado_principal_tipo" id="encarregado_principal_tipo">
                                    <option value="pai_mae">Pai e mãe / política padrão</option>
                                    <option value="pai">Pai</option>
                                    <option value="mae">Mãe</option>
                                    <option value="outro">Outro encarregado</option>
                                </select>
                            </div>
                            <div class="sige-field-group">
                                <label>Canal preferencial</label>
                                <select name="canal_preferencial_comunicacao" id="canal_preferencial_comunicacao">
                                    <option value="whatsapp">WhatsApp</option>
                                    <option value="email">E-mail</option>
                                    <option value="chamada">Chamada</option>
                                    <option value="sms">SMS</option>
                                </select>
                            </div>
                            <div class="sige-field-group">
                                <label>Parentesco, se for outro</label>
                                <input type="text" name="encarregado_principal_parentesco" id="encarregado_principal_parentesco" placeholder="Ex: Tio, avó, guardião">
                            </div>
                        </div>
                        <div class="sige-field-row sige-field-row-3 sige-guardian-other-row">
                            <div class="sige-field-group">
                                <label>Nome do outro encarregado</label>
                                <input type="text" name="encarregado_principal_nome" id="encarregado_principal_nome" placeholder="Obrigatório se seleccionar Outro">
                            </div>
                            <div class="sige-field-group">
                                <label>Telemóvel do outro encarregado</label>
                                <input type="tel" name="encarregado_principal_telemovel" id="encarregado_principal_telemovel" placeholder="82/83/84/85/86/87..." maxlength="12">
                            </div>
                            <div class="sige-field-group">
                                <label>E-mail do outro encarregado</label>
                                <input type="email" name="encarregado_principal_email" id="encarregado_principal_email" placeholder="Opcional">
                            </div>
                        </div>
                        <div class="sige-consent-grid">
                            <input type="hidden" name="consent_whatsapp" value="0">
                            <label><input type="checkbox" name="consent_whatsapp" id="consent_whatsapp" value="1" checked> Autoriza WhatsApp</label>
                            <input type="hidden" name="consent_email" value="0">
                            <label><input type="checkbox" name="consent_email" id="consent_email" value="1" checked> Autoriza e-mail</label>
                            <input type="hidden" name="consent_chamada" value="0">
                            <label><input type="checkbox" name="consent_chamada" id="consent_chamada" value="1" checked> Autoriza chamada</label>
                            <input type="hidden" name="consent_sms" value="0">
                            <label><input type="checkbox" name="consent_sms" id="consent_sms" value="1"> Autoriza SMS</label>
                        </div>
                    </div>

                    <div class="sige-boletim-section">
                        <span class="sige-boletim-title">Contacto alternativo e autorização de recolha</span>
                        <div class="sige-field-row sige-field-row-3">
                            <div class="sige-field-group">
                                <label>Contacto alternativo</label>
                                <input type="text" name="contacto_alternativo_nome" id="contacto_alternativo_nome" placeholder="Nome completo">
                            </div>
                            <div class="sige-field-group">
                                <label>Parentesco</label>
                                <input type="text" name="contacto_alternativo_parentesco" id="contacto_alternativo_parentesco" placeholder="Ex: Tio, avó">
                            </div>
                            <div class="sige-field-group">
                                <label>Telemóvel</label>
                                <input type="tel" name="contacto_alternativo_telemovel" id="contacto_alternativo_telemovel" placeholder="Opcional" maxlength="12">
                            </div>
                        </div>
                        <div class="sige-field-row sige-field-row-4 sige-guardian-pickup-row">
                            <div class="sige-field-group">
                                <label>Pessoa autorizada a buscar</label>
                                <input type="text" name="autorizado_buscar_nome" id="autorizado_buscar_nome" placeholder="Nome completo">
                            </div>
                            <div class="sige-field-group">
                                <label>Parentesco</label>
                                <input type="text" name="autorizado_buscar_parentesco" id="autorizado_buscar_parentesco" placeholder="Ex: Avó, motorista">
                            </div>
                            <div class="sige-field-group">
                                <label>Telemóvel</label>
                                <input type="tel" name="autorizado_buscar_telemovel" id="autorizado_buscar_telemovel" placeholder="Opcional" maxlength="12">
                            </div>
                            <div class="sige-field-group">
                                <label>Documento</label>
                                <input type="text" name="autorizado_buscar_documento" id="autorizado_buscar_documento" placeholder="BI / outro">
                            </div>
                        </div>
                        <div class="sige-field-row">
                            <div class="sige-field-group">
                                <label>Observações sobre comunicação/autorização</label>
                                <textarea name="encarregado_observacoes" id="encarregado_observacoes" rows="3" placeholder="Ex: contactar primeiro a mãe; pai via chamada; autorização válida apenas em dias úteis..."></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="tab-saude" class="sige-tab-content">
                    <div class="sige-boletim-section" style="border-color:var(--sige-error-light);">
                        <span class="sige-boletim-title" style="color:var(--sige-error);">Saúde e Emergência</span>
                        <div style="background:var(--sige-warning-light); border:1px solid #fde68a; border-radius:var(--sige-radius); padding:12px 16px; margin-bottom:16px; font-size:0.8rem; color:#92400e; display:flex; align-items:center; gap:10px;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px; height:18px; flex-shrink:0;"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            Preencha com cuidado. Estes dados são vitais em caso de emergência escolar.
                        </div>
                        <div class="sige-field-row sige-field-row-2">
                            <div class="sige-field-group">
                                <label>Grupo Sanguíneo</label>
                                <select name="grupo_sanguineo" id="grupo_sanguineo">
                                    <option value="">Desconhecido</option>
                                    <option value="A+">A+</option><option value="A-">A-</option>
                                    <option value="B+">B+</option><option value="B-">B-</option>
                                    <option value="AB+">AB+</option><option value="AB-">AB-</option>
                                    <option value="O+">O+</option><option value="O-">O-</option>
                                </select>
                            </div>
                            <div class="sige-field-group">
                                <label>Hospital de Preferência</label>
                                <input type="text" name="hospital_preferencia" id="hospital_preferencia" placeholder="Ex: Hospital Central">
                            </div>
                        </div>
                        <div class="sige-field-row">
                            <div class="sige-field-group">
                                <label>Alergias (Alimentares, Medicamentos, Picadas)</label>
                                <textarea name="alergias" id="alergias" rows="2" placeholder="Ex: Amendoim, Penicilina..."></textarea>
                            </div>
                        </div>
                        <div class="sige-field-row">
                            <div class="sige-field-group">
                                <label>Condições Médicas / Cuidados Especiais</label>
                                <textarea name="condicoes_medicas" id="condicoes_medicas" rows="2" placeholder="Ex: Asma, Diabetes, Epilepsia..."></textarea>
                            </div>
                        </div>
                        <div class="sige-field-row sige-field-row-2">
                            <div class="sige-field-group">
                                <label>Contacto de Emergência 1</label>
                                <input type="text" name="contacto_emergencia_1" id="contacto_emergencia_1" placeholder="Nome e telemóvel">
                            </div>
                            <div class="sige-field-group">
                                <label>Contacto de Emergência 2</label>
                                <input type="text" name="contacto_emergencia_2" id="contacto_emergencia_2" placeholder="Nome e telemóvel">
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="tab-docs" class="sige-tab-content">
                    <div class="sige-boletim-section">
                        <span class="sige-boletim-title">Arquivo digital</span>
                        <p style="font-size:0.8rem; color:var(--sige-slate-500); margin-bottom:16px;">
                            Carregue cópias digitalizadas dos documentos do aluno. Formatos aceites: PDF, JPG, PNG.
                        </p>
                        <div class="sige-doc-upload-area">
                            <div class="sige-doc-box" id="box-bi" data-sige-act="uploadDoc" data-sige-arg="bi">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="M15 8h2M15 12h2M7 16h10"/></svg>
                                <span>BI / Cédula</span>
                            </div>
                            <div class="sige-doc-box" id="box-cert" data-sige-act="uploadDoc" data-sige-arg="cert">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                                <span>Certidão Nascimento</span>
                            </div>
                            <div class="sige-doc-box" id="box-vacina" data-sige-act="uploadDoc" data-sige-arg="vacina">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 3l-6 6M16 16l5-5-4-4-5 5M10 14L3 21M3 13l6 6"/></svg>
                                <span>Boletim Vacinação</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="sige-modal-footer">
                <button type="button" data-sige-act="fecharModal" data-sige-noargs class="sige-btn-modal sige-btn-cancel">Cancelar</button>
                <button type="submit" id="btn-submit" class="sige-btn-modal sige-btn-submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                    Guardar Aluno
                </button>
            </div>
        </form>
    </div>
</div>


<div id="modal-aluno-360" class="sige-modal sige-aluno360-modal-pro" aria-hidden="true">
    <div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-aluno360-title">
        <div class="sige-modal-header">
            <div class="sige-modal-title-wrap">
                <div class="sige-modal-title-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/></svg>
                </div>
                <div class="sige-modal-title-text">
                    <span class="sige-modal-kicker">Ficha mestra</span>
                    <h2 id="sige-aluno360-title">Ficha 360º do aluno</h2>
                    <p><?php echo $sige_alunos_can_finance_view ? 'Visão integrada do estudante, qualidade dos dados, encarregados, matrícula, documentos, finanças, portaria e comunicações - apenas leitura e com escopo seguro por escola.' : 'Visão integrada do estudante, identificação, encarregados, matrícula, documentos, portaria e dados essenciais - apenas leitura e com escopo seguro por escola.'; ?></p>
                </div>
            </div>
            <button type="button" data-sige-act="fecharFichaAluno360" data-sige-noargs class="sige-modal-close" aria-label="Fechar">&times;</button>
        </div>
        <div class="sige-modal-body" id="sige-aluno360-content">
            <div class="sige-360-loading"><span class="sige-360-spinner"></span> A carregar ficha 360º...</div>
        </div>
        <div class="sige-modal-footer">
            <button type="button" data-sige-act="fecharFichaAluno360" data-sige-noargs class="sige-btn-modal sige-btn-cancel">Fechar</button>
            <?php if ($sige_alunos_can_finance_view): ?>
            <button type="button" id="sige-aluno360-finance" data-sige-act="baixarHistoricoFinanceiroAluno" data-sige-noargs class="sige-btn-modal sige-btn-submit sige-btn-finance-history">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M8 13h8"/><path d="M8 17h5"/><path d="M12 21v-4"/><path d="M9 18l3 3 3-3"/></svg>
                Histórico financeiro
            </button>
            <?php endif; ?>
            <?php if ($sige_alunos_can_edit): ?>
            <button type="button" id="sige-aluno360-editar" data-sige-act="editarAlunoFromFicha360" data-sige-noargs class="sige-btn-modal sige-btn-submit">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                Editar ficha
            </button>
            <?php endif; ?>
        </div>
    </div>
</div>


<div id="modal-import-alunos" class="sige-modal sige-import-modal-pro" aria-hidden="true">
    <div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-import-title">
        <div class="sige-modal-header">
            <div class="sige-modal-title-wrap">
                <div class="sige-modal-title-icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                </div>
                <div class="sige-modal-title-text">
                    <span class="sige-modal-kicker">Importação segura</span>
                    <h2 id="sige-import-title">Importar lista de estudantes</h2>
                    <p>Carregue um modelo Excel (.xlsx) com listas controladas ou um CSV exportado do Excel, escolha a turma e faça primeiro a pré-validação. O SoftGenial só grava depois da confirmação final.</p>
                </div>
            </div>
            <button type="button" data-sige-act="fecharImportarAlunos" data-sige-noargs class="sige-modal-close" aria-label="Fechar">&times;</button>
        </div>

        <form id="form-import-alunos" enctype="multipart/form-data">
            <input type="hidden" name="action" value="sige_importar_alunos_csv">
            <input type="hidden" name="_sige_nonce" value="<?php echo esc_attr(wp_create_nonce('sige_alunos_action')); ?>">
            <input type="hidden" name="sige_import_mode" id="sige_import_mode" value="preview">
            <input type="hidden" name="sige_preview_hash" id="sige_preview_hash" value="">
            <div class="sige-modal-body">
                <div class="sige-import-grid">
                    <div class="sige-import-card">
                        <h3>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            Ficheiro e turma
                        </h3>
                        <p>Use o modelo Excel do SoftGenial para reduzir erros. O ficheiro inclui listas controladas de género, classe, turma, documento e estado; CSV continua disponível como alternativa simples.</p>

                        <div class="sige-import-field">
                            <label for="sige_import_turma_id">Turma de destino <?php echo (int)$ano_lectivo; ?> *</label>
                            <select name="turma_id" id="sige_import_turma_id" required>
                                <option value="">-- Seleccionar turma --</option>
                                <?php foreach($turmas as $t): ?>
                                    <option value="<?php echo (int)$t->id; ?>" <?php selected($filtro_turma, (int)$t->id); ?>><?php echo esc_html($t->classe . ' - ' . $t->nome); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="sige-import-field">
                            <label for="sige_import_ficheiro">Ficheiro Excel/CSV da lista de estudantes *</label>
                            <input type="file" name="ficheiro" id="sige_import_ficheiro" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required>
                        </div>

                        <label class="sige-import-check">
                            <input type="checkbox" name="permitir_pendentes" value="1">
                            <span>Permitir importar linhas com dados dos pais incompletos, marcando a ficha do aluno como pendente de actualização dos contactos antes do envio por WhatsApp.</span>
                        </label>
                    </div>

                    <div class="sige-import-card">
                        <h3>
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                            Regras de validação
                        </h3>
                        <p>Para comunicação por WhatsApp, o padrão normal exige pai e mãe identificados com contactos móveis válidos de Moçambique. A importação pendente só deve ser usada como excepção controlada.</p>
                        <ul class="sige-import-template-list">
                            <li><strong>Etapa 1:</strong> pré-validar a lista, sem gravar nenhum aluno.</li>
                            <li><strong>Etapa 2:</strong> confirmar e gravar apenas os registos importáveis.</li>
                            <li><strong>Obrigatório:</strong> nome_completo, data_nascimento, genero.</li>
                            <li><strong>Modelo Excel:</strong> campos obrigatórios marcados com * e listas controladas para género, classe, turma, documento e estado.</li>
                            <li><strong>Por padrão obrigatórios:</strong> nome_pai, telemovel_pai, nome_mae, telemovel_mae.</li>
                            <li><strong>Telemóveis:</strong> 82, 83, 84, 85, 86 ou 87 + 7 dígitos.</li>
                            <li><strong>Duplicados:</strong> processo repetido ou mesmo nome + nascimento são ignorados.</li>
                            <li><strong>Anulação:</strong> disponível por lote apenas enquanto os alunos ainda não tiverem pagamentos, notas, documentos, portaria, transporte ou mensagens ligadas.</li>
                        </ul>
                        <div class="sige-import-template-actions">
                            <a class="sige-btn-modal sige-btn-submit sige-download-link" href="<?php echo esc_url(admin_url('admin-post.php?action=sige_download_modelo_importacao_alunos_xlsx&_wpnonce=' . wp_create_nonce('sige_download_modelo_importacao_alunos_xlsx'))); ?>" data-sige-no-transition="1" data-sige-act="sigeMarcarDownloadSemTransicao" data-sige-noargs style="justify-content:center;text-decoration:none;">
                                Baixar modelo Excel (.xlsx)
                            </a>
                            <button type="button" class="sige-btn-modal sige-btn-cancel" data-sige-act="baixarModeloImportacaoAlunos" data-sige-noargs style="justify-content:center;">
                                Baixar modelo CSV
                            </button>
                        </div>
                    </div>
                </div>

                <div class="sige-import-annul-panel" aria-label="Anulação segura de lote de importação">
                    <h4>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>
                        Anulação segura de lote
                    </h4>
                    <p>Use esta opção apenas quando uma lista foi importada por engano. O sistema verifica automaticamente se o lote ainda está limpo antes de remover os alunos e as matrículas criadas pela importação.</p>
                    <div class="sige-import-annul-row">
                        <div class="sige-import-field" style="margin-bottom:0!important;">
                            <label for="sige_lote_anular_id">ID do lote de importação</label>
                            <input type="text" id="sige_lote_anular_id" placeholder="Ex: SGIMP-1-20260605-103000-ABC123" autocomplete="off">
                        </div>
                        <button type="button" id="btn-anular-lote-importacao" class="sige-btn-modal sige-btn-danger-soft" data-sige-act="anularLoteImportacaoAlunosFromField" data-sige-noargs>Anular lote</button>
                    </div>
                    <div class="sige-import-annul-note">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
                        <span>Se algum aluno do lote já tiver notas, pagamentos, lançamentos, documentos, registos de portaria, transporte ou mensagens associadas, a anulação automática é bloqueada para proteger a integridade do sistema.</span>
                    </div>
                </div>

                <div class="sige-import-history-panel" aria-label="Histórico de lotes de importação">
                    <div class="sige-import-history-head">
                        <div>
                            <h4>
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 2 5-6"/></svg>
                                Histórico de lotes importados
                            </h4>
                            <p>Consulte as últimas importações, veja a turma, contagens, utilizador, estado do lote e use a anulação segura sem copiar dados manualmente.</p>
                        </div>
                        <button type="button" id="btn-historico-lotes-importacao" class="sige-btn-modal sige-btn-cancel sige-import-history-refresh" data-sige-act="carregarHistoricoLotesImportacao" data-sige-args="[true]">Actualizar histórico</button>
                    </div>
                    <div id="sige-import-history-list" class="sige-import-history-list" aria-live="polite">
                        <div class="sige-import-history-empty">Abra esta janela ou clique em actualizar para consultar os lotes importados.</div>
                    </div>
                </div>

                <div id="sige-import-result" class="sige-import-result" aria-live="polite"></div>
            </div>

            <div class="sige-import-actions">
                <button type="button" data-sige-act="fecharImportarAlunos" data-sige-noargs class="sige-btn-modal sige-btn-cancel">Cancelar</button>
                <button type="submit" id="btn-import-alunos" class="sige-btn-modal sige-btn-submit">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                    Pré-validar lista
                </button>
            </div>
        </form>
    </div>
</div>


<div id="sige-alunos-popup" class="sige-alunos-popup" aria-hidden="true">
    <div class="sige-alunos-popup-backdrop"></div>
    <div class="sige-alunos-popup-box" role="dialog" aria-modal="true" aria-labelledby="sige-alunos-popup-title">
        <div class="sige-alunos-popup-head">
            <div class="sige-alunos-popup-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 8v4"/><path d="M12 16h.01"/></svg>
            </div>
            <h3 id="sige-alunos-popup-title" class="sige-alunos-popup-title">Confirmação</h3>
        </div>
        <div class="sige-alunos-popup-body"></div>
        <div class="sige-alunos-popup-actions">
            <button type="button" class="sige-alunos-popup-btn sige-alunos-popup-cancel">Cancelar</button>
            <button type="button" class="sige-alunos-popup-btn sige-alunos-popup-confirm">Confirmar</button>
        </div>
    </div>
</div>

<?php if (!empty($sige_can_editar_cracha)): ?>
<style>
/* Modelo de Crachá da Escola - estilos só com tokens (sem cores/raios mágicos). */
.sige-cracha-modal{position:fixed;inset:0;z-index:140000;display:none;align-items:center;justify-content:center;padding:var(--space-4);}
.sige-cracha-modal.is-open{display:flex;}
.sige-cracha-backdrop{position:absolute;inset:0;background:rgba(15,23,42,.55);}
.sige-cracha-dialog{position:relative;background:var(--color-white);border-radius:var(--radius-xl);box-shadow:var(--shadow-lg);width:min(940px,96vw);max-height:92vh;display:flex;flex-direction:column;overflow:hidden;}
.sige-cracha-head{display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);padding:var(--space-5) var(--space-6);border-bottom:1px solid var(--color-ink-100);}
.sige-cracha-head h2{margin:0;font-size:var(--fs-lg);font-weight:700;color:var(--color-ink-700);}
.sige-cracha-x{background:none;border:none;font-size:26px;line-height:1;cursor:pointer;color:var(--color-slate-500);padding:0 var(--space-2);}
.sige-cracha-x:hover{color:var(--color-ink-700);}
.sige-cracha-body{padding:var(--space-6);overflow-y:auto;min-height:0;}
.sige-cracha-grid{display:grid;grid-template-columns:1fr 300px;gap:var(--space-6);align-items:start;}
.sige-cracha-label{margin:0 0 var(--space-3);font-size:var(--fs-sm);font-weight:700;color:var(--color-slate-600);text-transform:uppercase;letter-spacing:.4px;}
.sige-cracha-templates{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-bottom:var(--space-5);}
.sige-cracha-tpl{border:1.5px solid var(--color-ink-100);border-radius:var(--radius-md);padding:var(--space-4);cursor:pointer;transition:border-color .15s ease,background .15s ease;}
.sige-cracha-tpl:hover{border-color:var(--color-brand-300);}
.sige-cracha-tpl.is-active{border-color:var(--color-brand-500);background:var(--color-brand-50);}
.sige-cracha-tpl-nome{font-weight:700;color:var(--color-ink-700);font-size:var(--fs-base);}
.sige-cracha-tpl-desc{font-size:var(--fs-sm);color:var(--color-slate-500);margin-top:var(--space-1);line-height:1.35;}
.sige-cracha-accent{display:flex;align-items:center;gap:var(--space-3);margin-bottom:var(--space-5);}
.sige-cracha-accent input[type=color]{width:48px;height:38px;border:1px solid var(--color-ink-200);border-radius:var(--radius-md);background:var(--color-white);cursor:pointer;padding:2px;}
.sige-cracha-accent input[type=text]{flex:1;height:38px;border:1.5px solid var(--color-ink-200);border-radius:var(--radius-md);padding:0 var(--space-3);font-family:'Courier New',monospace;color:var(--color-ink-700);}
.sige-cracha-toggle{display:flex;align-items:center;gap:var(--space-3);font-size:var(--fs-base);color:var(--color-ink-700);cursor:pointer;margin-bottom:var(--space-4);}
.sige-cracha-toggle input{width:18px;height:18px;cursor:pointer;}
.sige-cracha-social{display:grid;gap:var(--space-3);}
.sige-cracha-social.is-hidden{display:none;}
.sige-cracha-social input{height:38px;border:1.5px solid var(--color-ink-200);border-radius:var(--radius-md);padding:0 var(--space-3);color:var(--color-ink-700);font-size:var(--fs-base);}
.sige-cracha-social input:focus,.sige-cracha-accent input:focus{outline:none;border-color:var(--color-brand-400);}
.sige-cracha-preview-wrap{position:sticky;top:0;}
.sige-cracha-frame{width:100%;height:392px;border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);background:var(--color-slate-50);}
.sige-cracha-foot{display:flex;align-items:center;justify-content:flex-end;gap:var(--space-3);padding:var(--space-4) var(--space-6);border-top:1px solid var(--color-ink-100);}
.sige-cracha-msg{margin-right:auto;font-size:var(--fs-sm);color:var(--color-success-700);font-weight:600;}
.sige-cracha-msg.is-error{color:var(--color-danger-500);}
.sige-cracha-btn-cancel,.sige-cracha-btn-save{height:42px;padding:0 var(--space-6);border-radius:var(--radius-md);font-weight:700;font-size:var(--fs-base);cursor:pointer;border:1.5px solid transparent;}
.sige-cracha-btn-cancel{background:var(--color-white);border-color:var(--color-ink-200);color:var(--color-ink-700);}
.sige-cracha-btn-cancel:hover{border-color:var(--color-slate-300);}
.sige-cracha-btn-save{background:var(--color-brand-500);color:var(--color-white);}
.sige-cracha-btn-save:hover{background:var(--color-brand-600);}
.sige-cracha-btn-save[disabled]{opacity:.6;cursor:default;}
@media(max-width:760px){.sige-cracha-grid{grid-template-columns:1fr;}.sige-cracha-templates{grid-template-columns:1fr;}.sige-cracha-preview-wrap{position:static;}.sige-cracha-frame{height:360px;}}
</style>
<div id="sige-cracha-modal" class="sige-cracha-modal" aria-hidden="true">
    <div class="sige-cracha-backdrop" data-sige-act="fecharModeloCracha" data-sige-noargs></div>
    <div class="sige-cracha-dialog" role="dialog" aria-modal="true" aria-labelledby="sige-cracha-title">
        <div class="sige-cracha-head">
            <h2 id="sige-cracha-title">Modelo de Crachá da Escola</h2>
            <button type="button" class="sige-cracha-x" data-sige-act="fecharModeloCracha" data-sige-noargs aria-label="Fechar">&times;</button>
        </div>
        <div class="sige-cracha-body">
            <div class="sige-cracha-grid">
                <div class="sige-cracha-controls">
                    <p class="sige-cracha-label">Modelo</p>
                    <div class="sige-cracha-templates" id="sige-cracha-templates"></div>
                    <p class="sige-cracha-label">Cor de destaque</p>
                    <div class="sige-cracha-accent">
                        <input type="color" id="sige-cracha-accent" value="#7c3aed" aria-label="Cor de destaque">
                        <input type="text" id="sige-cracha-accent-hex" maxlength="7" placeholder="#7c3aed" aria-label="Cor de destaque (hex)">
                    </div>
                    <label class="sige-cracha-toggle">
                        <input type="checkbox" id="sige-cracha-show-social"> Mostrar redes sociais no crachá
                    </label>
                    <div class="sige-cracha-social is-hidden" id="sige-cracha-social">
                        <input type="text" id="sige-cracha-social-instagram" placeholder="Instagram (ex.: @minhaescola)" maxlength="80">
                        <input type="text" id="sige-cracha-social-facebook" placeholder="Facebook (ex.: /minhaescola)" maxlength="80">
                        <input type="text" id="sige-cracha-social-website" placeholder="Website (ex.: minhaescola.co.mz)" maxlength="80">
                    </div>
                </div>
                <div class="sige-cracha-preview-wrap">
                    <p class="sige-cracha-label">Pré-visualização</p>
                    <iframe id="sige-cracha-preview-frame" class="sige-cracha-frame" title="Pré-visualização do crachá"></iframe>
                </div>
            </div>
        </div>
        <div class="sige-cracha-foot">
            <span class="sige-cracha-msg" id="sige-cracha-msg" aria-live="polite"></span>
            <button type="button" class="sige-cracha-btn-cancel" data-sige-act="fecharModeloCracha" data-sige-noargs>Cancelar</button>
            <button type="button" class="sige-cracha-btn-save" id="sige-cracha-save" data-sige-act="guardarModeloCracha" data-sige-noargs>Guardar modelo</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script <?php echo sige_csp_script_attr(); ?>>
// ========================================
// VARIÁVEIS GLOBAIS
// ========================================
var sigeAjax = { nonce_alunos: '<?php echo esc_js(wp_create_nonce('sige_alunos_action')); ?>' };
var sigeGlobal = <?php echo json_encode($sige_global_data); ?>;


function sigeAlunoEscapeHtml(value) {
    return String(value === undefined || value === null ? '' : value).replace(/[&<>'"]/g, function(ch) {
        return ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#039;','"':'&quot;'})[ch];
    });
}


var sigeAluno360CurrentId = 0;
var sigeAluno360FinanceCurrentUrl = "";

function sigeAluno360Loading() {
    return '<div class="sige-360-loading"><span class="sige-360-spinner"></span> A carregar ficha 360º...</div>';
}

function sigeAluno360SafeUrl(value) {
    var url = String(value || '').trim();
    if (!url) return '';
    // Evita javascript:, data:, e URLs protocol-relative (//dominio) dentro da Ficha 360º.
    if ((url.charAt(0) === '/' && url.charAt(1) !== '/') || /^https?:\/\//i.test(url)) return url.replace(/"/g, '%22');
    return '';
}

function sigeAluno360Value(value, fallback) {
    value = (value === undefined || value === null) ? '' : String(value).trim();
    return value ? value : (fallback || 'Não preenchido');
}

function sigeAluno360Money(value) {
    var n = parseFloat(value || 0);
    if (!isFinite(n)) n = 0;
    return n.toLocaleString('pt-MZ', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + ' MZN';
}

function sigeAluno360Row(label, value) {
    return '<div class="sige-360-row"><span>' + sigeAlunoEscapeHtml(label) + '</span><strong>' + sigeAlunoEscapeHtml(sigeAluno360Value(value)) + '</strong></div>';
}

function sigeAluno360Card(title, icon, body) {
    return '<section class="sige-360-card"><h3>' + icon + sigeAlunoEscapeHtml(title) + '</h3>' + body + '</section>';
}

function sigeAluno360Icon(name) {
    var icons = {
        user:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
        users:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg>',
        file:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
        money:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 1v22"/><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7H14a3.5 3.5 0 0 1 0 7H6"/></svg>',
        shield:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        chart:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 3v18h18"/><path d="M7 14l3-3 3 2 5-6"/></svg>',
        heart:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 1 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8z"/></svg>',
        message:'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a4 4 0 0 1-4 4H7l-4 4V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/></svg>'
    };
    return icons[name] || icons.file;
}

function abrirFichaAluno360(alunoId) {
    alunoId = parseInt(alunoId, 10);
    if (!alunoId || alunoId <= 0) {
        sigeAlunoAlert('Não foi possível identificar o aluno seleccionado.', 'Atenção', 'warning');
        return;
    }
    sigeAluno360CurrentId = alunoId;
    sigeAluno360FinanceCurrentUrl = "";
    jQuery('details.sige-card-actions[open]').removeAttr('open');
    jQuery('body').addClass('sige-aluno-modal-open');
    jQuery('#sige-aluno360-content').html(sigeAluno360Loading());
    jQuery('#modal-aluno-360').fadeIn(180).css('display','flex').attr('aria-hidden','false');
    jQuery.post(ajaxurl, {
        action: 'sige_get_aluno_360',
        _sige_nonce: sigeAjax.nonce_alunos,
        aluno_id: alunoId
    }, function(resp){
        if (resp && resp.success && resp.data) {
            renderFichaAluno360(resp.data);
        } else {
            jQuery('#sige-aluno360-content').html('<div class="sige-360-empty">Não foi possível carregar a ficha 360º: ' + sigeAlunoEscapeHtml(resp && resp.data ? resp.data : 'erro desconhecido') + '</div>');
        }
    }).fail(function(){
        jQuery('#sige-aluno360-content').html('<div class="sige-360-empty">O servidor não respondeu ao carregar a ficha 360º. Tente novamente.</div>');
    });
}

function fecharFichaAluno360() {
    jQuery('#modal-aluno-360').fadeOut(160).attr('aria-hidden','true');
    jQuery('body').removeClass('sige-aluno-modal-open');
}

function editarAlunoFromFicha360() {
    var id = parseInt(sigeAluno360CurrentId, 10);
    fecharFichaAluno360();
    if (id > 0) setTimeout(function(){ editarAluno(id); }, 180);
}

function sigeAlunoFinanceHistoryUrl(id) {
    id = parseInt(id, 10) || 0;
    if (!id || window.sigeAlunosCanFinanceView !== true) return '';
    return String(window.sigeAlunosFinanceHistoryBaseUrl || '') + encodeURIComponent(id);
}

function sigeAlunoFinanceHistoryExcelUrl(id) {
    id = parseInt(id, 10) || 0;
    if (!id || window.sigeAlunosCanFinanceView !== true) return '';
    return String(window.sigeAlunosFinanceHistoryExcelBaseUrl || '') + encodeURIComponent(id);
}

function sigeAlunoFinanceExtractsUrl(id) {
    id = parseInt(id, 10) || 0;
    if (!id || window.sigeAlunosCanFinanceView !== true) return '';
    return String(window.sigeAlunosFinanceExtractsBaseUrl || '') + encodeURIComponent(id);
}

function baixarHistoricoFinanceiroAluno() {
    var id = parseInt(sigeAluno360CurrentId, 10) || 0;
    var url = sigeAluno360SafeUrl(sigeAluno360FinanceCurrentUrl || '') || sigeAlunoFinanceHistoryUrl(id);
    if (!url) {
        sigeAlunoAlert('Não foi possível abrir o histórico financeiro deste aluno com o perfil actual.', 'Permissão necessária', 'warning');
        return;
    }
    window.open(url, 'HistoricoFinanceiroAluno', 'width=1100,height=920,scrollbars=yes,resizable=yes');
}

function renderFichaAluno360(data) {
    data = data || {};
    var aluno = data.aluno || {};
    var q = data.qualidade || {score:0,label:'Por avaliar',class:'pendente',message:''};
    var financeiro = data.financeiro || {};
    var scope = data.scope || {};
    var perms = data.permissoes || {};
    var isGuarda360 = scope.guard_readonly === true || perms.guarda === true || window.sigeAlunosIsGuarda === true;
    var canSeeDocs360 = !isGuarda360 && scope.can_view_documents !== false && perms.documentos !== false && window.sigeAlunosCanDocumentsView !== false;
    var canSeeSensitive360 = !isGuarda360 && scope.can_view_sensitive !== false && perms.dados_sensiveis !== false && perms.sensivel !== false;
    var canSeeAcademic360 = !isGuarda360 && scope.can_view_academic !== false && perms.academico !== false;
    var canSeeComms360 = !isGuarda360 && scope.can_view_communications !== false && perms.comunicacoes !== false;
    var canSeeFinance360 = !isGuarda360 && !financeiro.oculto_por_permissao && scope.can_view_finance !== false && perms.financeiro !== false;
    var canSeeQuality360 = !isGuarda360 && canSeeSensitive360 && Array.isArray(q.pendencias);
    var academico = data.academico || {};
    var portaria = data.portaria || {};
    var comunicacoes = data.comunicacoes || {};
    var foto = sigeAluno360SafeUrl(aluno.foto) || '<?php echo esc_js(SIGE_URL . 'assets/img/avatar-default.svg'); ?>';
    var scoreClass = String(q.class || 'pendente').replace(/[^a-z0-9_-]/gi, '').toLowerCase() || 'pendente';
    var score = parseInt(q.score || 0, 10) || 0;
    var html = '';

    html += '<div class="sige-360-hero-card">';
    html += '<img class="sige-360-photo" src="' + sigeAlunoEscapeHtml(foto) + '" alt="Foto do aluno">';
    html += '<div><span class="sige-360-kicker">Ano lectivo ' + sigeAlunoEscapeHtml(data.ano_lectivo || '') + '</span><h3 class="sige-360-name">' + sigeAlunoEscapeHtml(aluno.nome || 'Aluno sem nome') + '</h3>';
    html += '<div class="sige-360-meta"><span class="sige-360-pill">Proc. ' + sigeAlunoEscapeHtml(aluno.processo || '-') + '</span><span class="sige-360-pill">' + sigeAlunoEscapeHtml(aluno.turma || 'Sem turma') + '</span><span class="sige-360-pill">Estado: ' + sigeAlunoEscapeHtml(aluno.status || 'activo') + '</span>';
    if (aluno.idade !== null && aluno.idade !== undefined) html += '<span class="sige-360-pill">' + sigeAlunoEscapeHtml(aluno.idade) + ' anos</span>';
    if (aluno.importacao_lote_id) html += '<span class="sige-360-pill">Lote: ' + sigeAlunoEscapeHtml(aluno.importacao_lote_id) + '</span>';
    html += '</div></div>';
    if (canSeeQuality360) {
        html += '<div class="sige-360-score ' + scoreClass + '" style="--score:' + Math.max(0, Math.min(100, score)) + '"><div class="sige-360-score-circle"><strong>' + score + '%</strong></div><div class="sige-360-score-text"><strong>' + sigeAlunoEscapeHtml(q.label || 'Por avaliar') + '</strong><span>' + sigeAlunoEscapeHtml(q.message || 'Índice operacional de qualidade dos dados.') + '</span></div></div>';
    } else {
        html += '<div class="sige-360-score ok"><div class="sige-360-score-circle"><strong>✓</strong></div><div class="sige-360-score-text"><strong>Consulta autorizada</strong><span>' + sigeAlunoEscapeHtml(isGuarda360 ? 'Modo Portaria: identificação operacional.' : 'Alguns blocos estão ocultos por permissão.') + '</span></div></div>';
    }
    html += '</div>';

    html += '<div class="sige-360-kpi-grid">';
    html += '<div class="sige-360-kpi"><span>Turma</span><strong>' + sigeAlunoEscapeHtml(aluno.turma || 'Sem turma') + '</strong></div>';
    if (canSeeFinance360) html += '<div class="sige-360-kpi"><span>Financeiro</span><strong>' + (financeiro.disponivel ? sigeAluno360Money(financeiro.divida_aberta || 0) + ' em aberto' : 'Sem módulo financeiro') + '</strong></div>';
    if (canSeeAcademic360) html += '<div class="sige-360-kpi"><span>Notas registadas</span><strong>' + sigeAlunoEscapeHtml(academico.total_notas || 0) + '</strong></div>';
    if (canSeeComms360) html += '<div class="sige-360-kpi"><span>Comunicações</span><strong>' + sigeAlunoEscapeHtml(comunicacoes.total || 0) + ' registo(s)</strong></div>';
    html += '</div>';

    var pendencias = canSeeQuality360 && Array.isArray(q.pendencias) ? q.pendencias : [];
    var pendHtml = '';
    if (pendencias.length) {
        pendencias.forEach(function(p){
            var impacto = String(p.impacto || 'medio').replace(/[^a-z0-9_-]/gi, '').toLowerCase();
            pendHtml += '<div class="sige-360-pendencia ' + impacto + '"><span class="sige-360-pendencia-dot"></span><div><strong>' + sigeAlunoEscapeHtml(p.label || 'Pendência') + '</strong><small>' + sigeAlunoEscapeHtml(p.sugestao || 'Actualizar a ficha do aluno.') + '</small></div></div>';
        });
    } else {
        pendHtml = '<div class="sige-360-empty">' + (canSeeQuality360 ? 'Sem pendências críticas neste momento. Continue a manter a ficha actualizada.' : 'Indicadores de qualidade ocultos por permissão.') + '</div>';
    }

    var ident = '';
    ident += sigeAluno360Row('Nome completo', aluno.nome);
    ident += sigeAluno360Row('Número de processo', aluno.processo);
    ident += sigeAluno360Row('Turma', aluno.turma || 'Sem turma');
    ident += sigeAluno360Row('Estado', aluno.status || 'activo');
    if (canSeeSensitive360) {
        ident += sigeAluno360Row('Nascimento', aluno.data_nascimento ? String(aluno.data_nascimento).substring(0,10) : '');
        ident += sigeAluno360Row('Género', aluno.genero);
        ident += sigeAluno360Row('Nacionalidade', aluno.nacionalidade);
        ident += sigeAluno360Row('Bairro', aluno.bairro);
    }
    if (canSeeDocs360) {
        ident += sigeAluno360Row('Documento', ((aluno.tipo_documento || '') + ' ' + (aluno.documento_nr || '')).trim());
        ident += sigeAluno360Row('NUIT encarregado', aluno.nuit_encarregado);
    } else {
        ident += sigeAluno360Row('Modo de acesso', isGuarda360 ? 'Consulta operacional de Portaria' : 'Dados documentais ocultos por permissão');
    }

    var enc = data.encarregados || {};
    var pai = enc.pai || {}, mae = enc.mae || {}, pr = enc.principal || {}, adv = enc.avancado || {};
    var consent = adv.consentimentos || {};
    var alt = adv.contacto_alternativo || {};
    var busca = adv.autorizado_buscar || {};
    var consentLabel = [];
    if (consent.whatsapp) consentLabel.push('WhatsApp');
    if (consent.email) consentLabel.push('E-mail');
    if (consent.chamada) consentLabel.push('Chamada');
    if (consent.sms) consentLabel.push('SMS');
    var encHtml = '';
    encHtml += sigeAluno360Row('Pai', (pai.nome || '') + (pai.telemovel ? ' · ' + pai.telemovel : ''));
    encHtml += sigeAluno360Row('Contacto pai', pai.telemovel ? (pai.telemovel + (pai.telemovel_valido ? ' · válido' : ' · rever')) : '');
    encHtml += sigeAluno360Row('Mãe', (mae.nome || '') + (mae.telemovel ? ' · ' + mae.telemovel : ''));
    encHtml += sigeAluno360Row('Contacto mãe', mae.telemovel ? (mae.telemovel + (mae.telemovel_valido ? ' · válido' : ' · rever')) : '');
    encHtml += sigeAluno360Row('WhatsApp principal', pr.whatsapp || pr.contacto);
    encHtml += sigeAluno360Row('E-mail principal', pr.email || pai.email || mae.email);
    encHtml += sigeAluno360Row('Encarregado principal', adv.principal_label || 'Pai/Mãe');
    encHtml += sigeAluno360Row('Outro encarregado', adv.principal_nome ? (adv.principal_nome + (adv.principal_telemovel ? ' · ' + adv.principal_telemovel : '')) : '');
    encHtml += sigeAluno360Row('Canal preferencial', adv.canal_preferencial || 'whatsapp');
    encHtml += sigeAluno360Row('Canais autorizados', consentLabel.length ? consentLabel.join(', ') : 'Sem consentimento registado');
    encHtml += sigeAluno360Row('Contacto alternativo', alt.nome ? (alt.nome + (alt.telemovel ? ' · ' + alt.telemovel : '')) : '');
    encHtml += sigeAluno360Row('Autorizado a buscar', busca.nome ? (busca.nome + (busca.parentesco ? ' · ' + busca.parentesco : '') + (busca.documento ? ' · Doc. ' + busca.documento : '')) : '');

    var encHistRows = Array.isArray(data.encarregados_historico) ? data.encarregados_historico : [];
    var encHistHtml = '';
    if (encHistRows.length) {
        encHistHtml += '<div class="sige-360-timeline">';
        encHistRows.forEach(function(h){
            var who = h.user_display ? (' · ' + h.user_display) : '';
            var summary = h.resumo || ((h.total_alteracoes || 0) + ' alteração(ões) registada(s)');
            encHistHtml += '<div class="sige-360-time"><span>' + sigeAlunoEscapeHtml((h.criado_em || '') + who) + '</span><strong>' + sigeAlunoEscapeHtml(summary) + '</strong>';
            var changes = Array.isArray(h.alteracoes) ? h.alteracoes : [];
            if (changes.length) {
                encHistHtml += '<div class="sige-360-mini-changes">';
                changes.slice(0, 4).forEach(function(c){
                    encHistHtml += '<small><b>' + sigeAlunoEscapeHtml(c.label || 'Campo') + ':</b> ' + sigeAlunoEscapeHtml(c.antes || '-') + ' → ' + sigeAlunoEscapeHtml(c.depois || '-') + '</small>';
                });
                if (changes.length > 4) encHistHtml += '<small>+' + sigeAlunoEscapeHtml(changes.length - 4) + ' alteração(ões) adicionais.</small>';
                encHistHtml += '</div>';
            }
            encHistHtml += '</div>';
        });
        encHistHtml += '</div>';
    } else {
        encHistHtml = '<div class="sige-360-empty">Ainda não há alterações auditadas nos encarregados deste aluno.</div>';
    }

    var docs = Array.isArray(data.documentos) ? data.documentos : [];
    var docsHtml = '<div class="sige-360-doc-list">';
    if (!canSeeDocs360) {
        docsHtml += '<div class="sige-360-empty">Documentos reservados a perfis autorizados.</div>';
    } else if (docs.length) {
        docs.forEach(function(d){
            var url = sigeAluno360SafeUrl(d.url || '');
            docsHtml += '<div class="sige-360-doc ' + (d.ok ? 'ok' : '') + '"><span>' + sigeAlunoEscapeHtml(d.label || 'Documento') + '</span>' + (d.ok && url ? '<a href="' + sigeAlunoEscapeHtml(url) + '" target="_blank" rel="noopener">Abrir</a>' : '<strong>' + (d.ok ? 'Carregado' : 'Pendente') + '</strong>') + '</div>';
        });
    } else {
        docsHtml += '<div class="sige-360-empty">Sem informação documental.</div>';
    }
    docsHtml += '</div>';

    var saude = data.saude || {};
    var saudeHtml = '';
    saudeHtml += sigeAluno360Row('Grupo sanguíneo', saude.grupo_sanguineo);
    saudeHtml += sigeAluno360Row('Alergias', saude.alergias);
    saudeHtml += sigeAluno360Row('Condições médicas', saude.condicoes_medicas);
    saudeHtml += sigeAluno360Row('Hospital preferência', saude.hospital_preferencia);
    saudeHtml += sigeAluno360Row('Emergência 1', saude.contacto_emergencia_1);
    saudeHtml += sigeAluno360Row('Emergência 2', saude.contacto_emergencia_2);

    var matHtml = '';
    var mats = Array.isArray(data.matriculas) ? data.matriculas : [];
    if (mats.length) {
        matHtml += '<div class="sige-360-timeline">';
        mats.forEach(function(m){
            var turma = ((m.classe || '') + ' - ' + (m.turma_nome || '')).replace(/^\s*-\s*|\s*-\s*$/g,'') || ('Turma #' + (m.turma_id || ''));
            matHtml += '<div class="sige-360-time"><span>' + sigeAlunoEscapeHtml(m.ano_lectivo || 'Ano') + '</span><strong>' + sigeAlunoEscapeHtml(turma) + '</strong></div>';
        });
        matHtml += '</div>';
    } else {
        matHtml = '<div class="sige-360-empty">Sem histórico de matrícula localizado.</div>';
    }

    var finHtml = '';
    if (!canSeeFinance360) {
        finHtml = '<div class="sige-360-empty">Resumo financeiro reservado ao perfil financeiro.</div>';
    } else if (financeiro.disponivel) {
        finHtml += sigeAluno360Row('Estado financeiro', financeiro.status_operacional || 'Por avaliar');
        finHtml += sigeAluno360Row('Dívida aberta', sigeAluno360Money(financeiro.divida_aberta || 0));
        finHtml += sigeAluno360Row('Vencido', sigeAluno360Money(financeiro.saldo_vencido || 0));
        finHtml += sigeAluno360Row('Em plano', sigeAluno360Money(financeiro.em_plano || 0));
        finHtml += sigeAluno360Row('Crédito disponível', sigeAluno360Money(financeiro.credito || 0));
        finHtml += sigeAluno360Row('Total pago', sigeAluno360Money(financeiro.total_pago || 0));
        finHtml += sigeAluno360Row('Estornos', sigeAluno360Money(financeiro.total_estornado || 0));
        finHtml += sigeAluno360Row('Lançamentos abertos', financeiro.lancamentos_abertos || 0);
        finHtml += sigeAluno360Row('Lançamentos pagos', financeiro.lancamentos_pagos || 0);
        if (financeiro.ultimo_pagamento && financeiro.ultimo_pagamento.data) {
            finHtml += sigeAluno360Row('Último pagamento', (financeiro.ultimo_pagamento.data || '') + ' · ' + sigeAluno360Money(financeiro.ultimo_pagamento.valor || 0));
        }
        if (financeiro.proximo_vencimento && financeiro.proximo_vencimento.data) {
            finHtml += sigeAluno360Row('Próximo vencimento', (financeiro.proximo_vencimento.data || '') + ' · ' + sigeAluno360Money(financeiro.proximo_vencimento.saldo || 0));
        }
        var histUrl360 = sigeAluno360SafeUrl(financeiro.historico_url || '') || sigeAlunoFinanceHistoryUrl(aluno.id);
        var histXlsx360 = sigeAluno360SafeUrl(financeiro.historico_xlsx_url || '') || sigeAlunoFinanceHistoryExcelUrl(aluno.id);
        var extractsUrl360 = sigeAluno360SafeUrl(financeiro.extracto_url || '') || sigeAlunoFinanceExtractsUrl(aluno.id);
        sigeAluno360FinanceCurrentUrl = histUrl360 || '';
        if (histUrl360 || histXlsx360 || extractsUrl360) {
            finHtml += '<div class="sige-360-fin-actions">';
            if (histUrl360) finHtml += '<a class="sige-360-fin-btn primary" href="' + sigeAlunoEscapeHtml(histUrl360) + '" target="_blank" rel="noopener">Abrir histórico / PDF</a>';
            if (histXlsx360) finHtml += '<a class="sige-360-fin-btn" href="' + sigeAlunoEscapeHtml(histXlsx360) + '" target="_blank" rel="noopener">Baixar Excel</a>';
            if (extractsUrl360) finHtml += '<a class="sige-360-fin-btn" href="' + sigeAlunoEscapeHtml(extractsUrl360) + '">Abrir extracto/caixa</a>';
            finHtml += '</div><div class="sige-360-fin-note">Ao abrir o histórico, entra no ambiente financeiro oficial do aluno, com PDF administrativo e exportação para Excel.</div>';
        }
    } else {
        finHtml = '<div class="sige-360-empty">Resumo financeiro indisponível neste ambiente.</div>';
    }

    var acadHtml = '';
    if (academico.disponivel) {
        acadHtml += sigeAluno360Row('Total de notas', academico.total_notas || 0);
        acadHtml += sigeAluno360Row('Disciplinas com notas', academico.disciplinas_com_notas || 0);
        var tris = Array.isArray(academico.por_trimestre) ? academico.por_trimestre : [];
        if (tris.length) {
            acadHtml += '<div class="sige-360-timeline">';
            tris.forEach(function(t){ acadHtml += '<div class="sige-360-time"><span>' + sigeAlunoEscapeHtml(t.trimestre || '-') + 'º trimestre</span><strong>' + sigeAlunoEscapeHtml(t.total || 0) + ' registo(s) · ' + sigeAlunoEscapeHtml(t.aprovadas || 0) + ' aprovado(s)</strong></div>'; });
            acadHtml += '</div>';
        }
    } else {
        acadHtml = '<div class="sige-360-empty">Resumo académico indisponível neste ambiente.</div>';
    }

    var portHtml = '';
    if (portaria.disponivel) {
        portHtml += sigeAluno360Row('Total de acessos', portaria.total_acessos || 0);
        var acessos = Array.isArray(portaria.ultimos) ? portaria.ultimos : [];
        if (acessos.length) {
            portHtml += '<div class="sige-360-timeline">';
            acessos.forEach(function(a){ portHtml += '<div class="sige-360-time"><span>' + sigeAlunoEscapeHtml(a.data_hora || '') + '</span><strong>' + sigeAlunoEscapeHtml((a.tipo || 'entrada') + ' · ' + (a.status_no_momento || '')) + '</strong></div>'; });
            portHtml += '</div>';
        }
    } else {
        portHtml = '<div class="sige-360-empty">Sem registos de portaria ou módulo indisponível.</div>';
    }

    var comHtml = '';
    if (comunicacoes.disponivel) {
        comHtml += sigeAluno360Row('Total', comunicacoes.total || 0);
        comHtml += sigeAluno360Row('Pendentes', comunicacoes.pendentes || 0);
        comHtml += sigeAluno360Row('Enviadas', comunicacoes.enviadas || 0);
        comHtml += sigeAluno360Row('Falhadas', comunicacoes.falhadas || 0);
    } else {
        comHtml = '<div class="sige-360-empty">Sem histórico de comunicações WhatsApp neste ambiente.</div>';
    }

    html += '<div class="sige-360-grid">';
    html += '<div>';
    if (canSeeQuality360) html += sigeAluno360Card('Índice de qualidade dos dados', sigeAluno360Icon('chart'), '<div class="sige-360-pendencias">' + pendHtml + '</div>');
    html += sigeAluno360Card('Identificação do aluno', sigeAluno360Icon('user'), ident);
    if (canSeeSensitive360) html += sigeAluno360Card('Encarregados e comunicação', sigeAluno360Icon('users'), encHtml);
    if (canSeeSensitive360) html += sigeAluno360Card('Auditoria dos encarregados', sigeAluno360Icon('shield'), encHistHtml);
    if (canSeeAcademic360 || canSeeSensitive360) html += sigeAluno360Card('Matrícula e percurso', sigeAluno360Icon('shield'), matHtml);
    html += '</div><div>';
    if (canSeeFinance360) html += sigeAluno360Card('Finanças', sigeAluno360Icon('money'), finHtml);
    if (canSeeDocs360) html += sigeAluno360Card('Documentos', sigeAluno360Icon('file'), docsHtml);
    if (canSeeSensitive360) html += sigeAluno360Card('Saúde e emergência', sigeAluno360Icon('heart'), saudeHtml);
    html += sigeAluno360Card('Portaria', sigeAluno360Icon('shield'), portHtml);
    if (canSeeAcademic360) html += sigeAluno360Card('Académico', sigeAluno360Icon('chart'), acadHtml);
    if (canSeeComms360) html += sigeAluno360Card('Comunicações', sigeAluno360Icon('message'), comHtml);
    html += '</div></div>';

    jQuery('#sige-aluno360-content').html(html);
}

var sigeImportPreviewBtnHtml = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg> Pré-validar lista';

function resetImportacaoAlunosPreview(clearResult) {
    jQuery('#sige_import_mode').val('preview');
    jQuery('#sige_preview_hash').val('');
    jQuery('#btn-import-alunos').html(sigeImportPreviewBtnHtml).prop('disabled', false);
    jQuery('#btn-confirm-import-alunos').prop('disabled', false);
    if (clearResult !== false) {
        jQuery('#sige-import-result').removeClass('active').empty();
    }
}

function abrirImportarAlunos() {
    if (!window.sigeAlunosCanCreate && !window.sigeAlunosCanEdit) {
        sigeAlunoAlert('O seu perfil permite apenas consultar alunos. Não pode importar listas.', 'Modo consulta', 'warning');
        return;
    }
    jQuery('#form-import-alunos')[0].reset();
    resetImportacaoAlunosPreview(true);
    var turmaAtual = jQuery('#filtro-turma').val();
    if (turmaAtual && turmaAtual !== '0') {
        jQuery('#sige_import_turma_id').val(turmaAtual);
    }
    jQuery('body').addClass('sige-aluno-modal-open');
    jQuery('#modal-import-alunos').fadeIn(180).css('display','flex').attr('aria-hidden','false');
    carregarHistoricoLotesImportacao(true);
}

function fecharImportarAlunos() {
    jQuery('#modal-import-alunos').fadeOut(160).attr('aria-hidden','true');
    jQuery('body').removeClass('sige-aluno-modal-open');
    resetImportacaoAlunosPreview(true);
}

function baixarModeloImportacaoAlunos() {
    var headers = [
        'numero_processo','nome_completo','data_nascimento','genero','classe','turma','nome_pai','telemovel_pai','nome_mae','telemovel_mae',
        'whatsapp_notificacoes','contacto_encarregado','email_pai','email_mae','email_encarregado','bairro','nacionalidade','tipo_documento','documento_nr','status','observacoes'
    ];
    var exemplo = [
        '', 'Ana Maria Joaquim', '2015-03-12', 'F', '1ª Classe', '1ª Classe - A', 'Joaquim Manuel', '841234567', 'Marta Alberto', '871234567',
        '841234567', '841234567', 'pai@email.co.mz', 'mae@email.co.mz', 'encarregado@email.co.mz', 'Matola', 'Moçambicana', 'Boletim de Nascimento', 'BN12345', 'activo', ''
    ];
    var csv = '\ufeff' + headers.join(';') + '\n' + exemplo.map(function(v){
        var s = String(v).replace(/"/g, '""');
        return '"' + s + '"';
    }).join(';') + '\n';
    var blob = new Blob([csv], {type: 'text/csv;charset=utf-8;'});
    var url = URL.createObjectURL(blob);
    var a = document.createElement('a');
    a.href = url;
    a.download = 'modelo-importacao-alunos-softgenial.csv';
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
}


function sigeAlunoEscapeJs(value) {
    return String(value === undefined || value === null ? '' : value)
        .replace(/\\/g, '\\\\')
        .replace(/'/g, "\\'")
        .replace(/\r?\n/g, ' ');
}

function sigeImportacaoFormatarData(value) {
    var raw = String(value || '').trim();
    if (!raw) return '-';
    return raw.replace('T', ' ').substring(0, 16);
}

function sigeImportacaoEstadoLabel(estado, bloqueadoEm) {
    estado = String(estado || 'activo');
    if (estado === 'anulado') return 'Anulado';
    if (estado === 'sem_registos') return 'Sem registos';
    if (bloqueadoEm) return 'Activo';
    return 'Activo';
}

function seleccionarLoteImportacao(loteId) {
    loteId = String(loteId || '').trim();
    if (!loteId) return;
    jQuery('#sige_lote_anular_id').val(loteId);
    var alvo = jQuery('#sige_lote_anular_id');
    if (alvo.length) {
        alvo.focus();
        var modalBody = jQuery('#modal-import-alunos .sige-modal-body');
        if (modalBody.length) {
            modalBody.animate({scrollTop: Math.max(0, alvo.offset().top - modalBody.offset().top + modalBody.scrollTop() - 120)}, 220);
        }
    }
}

function carregarHistoricoLotesImportacao(showLoading) {
    var box = jQuery('#sige-import-history-list');
    if (!box.length) return;
    if (showLoading !== false) {
        box.html('<div class="sige-import-history-loading">A carregar histórico de lotes...</div>');
    }
    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: {
            action: 'sige_listar_lotes_importacao_alunos',
            _sige_nonce: sigeAjax.nonce_alunos,
            limit: 20
        },
        success: function(res) {
            if (res && res.success) {
                renderHistoricoLotesImportacao((res.data && res.data.lotes) ? res.data.lotes : []);
            } else {
                box.html('<div class="sige-import-history-empty">Não foi possível carregar o histórico: ' + sigeAlunoEscapeHtml(res && res.data ? res.data : 'erro desconhecido') + '</div>');
            }
        },
        error: function() {
            box.html('<div class="sige-import-history-empty">O servidor não respondeu ao carregar o histórico de lotes.</div>');
        }
    });
}

function renderHistoricoLotesImportacao(lotes) {
    var box = jQuery('#sige-import-history-list');
    if (!box.length) return;
    lotes = Array.isArray(lotes) ? lotes : [];
    if (!lotes.length) {
        box.html('<div class="sige-import-history-empty">Ainda não há lotes de importação registados nesta escola.</div>');
        return;
    }

    var html = '';
    lotes.forEach(function(item){
        item = item || {};
        var lote = item.lote_id || '';
        var estado = String(item.estado || 'activo').replace(/[^a-z0-9_-]/gi, '').toLowerCase();
        if (!estado) estado = 'activo';
        var origem = item.origem === 'reconstruido' ? 'reconstruido' : '';
        var estadoClasse = origem || estado;
        var estadoLabel = origem ? 'Reconstruído' : sigeImportacaoEstadoLabel(estado, item.bloqueado_em || '');
        var errosDuplicados = (parseInt(item.erros || 0, 10) || 0) + (parseInt(item.duplicados || 0, 10) || 0);
        var turma = item.turma || 'Turma não identificada';
        var utilizador = item.criado_por || 'Utilizador não identificado';
        var ano = item.ano_lectivo ? 'Ano lectivo ' + item.ano_lectivo : 'Ano não identificado';
        var extraEstado = '';
        if (item.anulado_em) {
            extraEstado = 'Anulado em ' + sigeImportacaoFormatarData(item.anulado_em);
        } else if (item.bloqueado_em) {
            extraEstado = 'Última anulação bloqueada em ' + sigeImportacaoFormatarData(item.bloqueado_em);
        } else if (item.ficheiro) {
            extraEstado = item.ficheiro;
        }

        html += '<div class="sige-import-history-row">';
        html += '<div class="sige-import-history-date">' + sigeImportacaoFormatarData(item.criado_em) + '<small>' + sigeAlunoEscapeHtml(ano) + '</small></div>';
        html += '<div class="sige-import-history-main"><span class="sige-import-history-lote">' + sigeAlunoEscapeHtml(lote) + '</span><span class="sige-import-history-turma">' + sigeAlunoEscapeHtml(turma) + '</span></div>';
        html += '<div class="sige-import-history-kpis">';
        html += '<span class="sige-import-history-pill"><strong>' + sigeAlunoEscapeHtml(item.importados || 0) + '</strong> importados</span>';
        html += '<span class="sige-import-history-pill"><strong>' + sigeAlunoEscapeHtml(item.pendentes || 0) + '</strong> pendentes</span>';
        html += '<span class="sige-import-history-pill"><strong>' + sigeAlunoEscapeHtml(errosDuplicados) + '</strong> erros/dup.</span>';
        html += '</div>';
        html += '<div class="sige-import-history-meta"><span class="sige-import-status ' + sigeAlunoEscapeHtml(estadoClasse) + '">' + sigeAlunoEscapeHtml(estadoLabel) + '</span><span class="sige-import-history-user">' + sigeAlunoEscapeHtml(utilizador) + (extraEstado ? '<small>' + sigeAlunoEscapeHtml(extraEstado) + '</small>' : '') + '</span></div>';
        html += '<div class="sige-import-history-actions">';
        html += '<button type="button" class="sige-btn-modal sige-btn-cancel" data-sige-act="seleccionarLoteImportacao" data-sige-arg="' + sigeAlunoEscapeHtml(lote) + '">Usar ID</button>';
        if (item.pode_anular) {
            html += '<button type="button" class="sige-btn-modal sige-btn-danger-soft" data-sige-act="anularLoteImportacaoAlunos" data-sige-arg="' + sigeAlunoEscapeHtml(lote) + '">Anular</button>';
        }
        html += '</div>';
        html += '</div>';
    });
    box.html(html);
}

function renderImportacaoAlunosResultado(data) {
    var stats = data && data.stats ? data.stats : {};
    var detalhes = data && Array.isArray(data.detalhes) ? data.detalhes : [];
    var turma = data && data.turma ? data.turma : '';
    var modo = data && data.mode ? data.mode : 'confirm';
    var isPreview = modo === 'preview';
    var importaveis = stats.importaveis || stats.importados || 0;
    var errosDuplicados = (stats.erros || 0) + (stats.duplicados || 0);
    var html = '';

    if (isPreview && data.preview_hash) {
        jQuery('#sige_preview_hash').val(data.preview_hash);
        jQuery('#sige_import_mode').val('preview');
    }

    html += '<div class="sige-import-stage-note">';
    html += '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
    html += '<span>';
    if (isPreview) {
        html += '<strong>Etapa 1 concluída:</strong> a lista foi pré-validada e nenhum aluno foi gravado ainda. Revise os erros, duplicados e pendências antes de confirmar.';
    } else {
        html += '<strong>Etapa 2 concluída:</strong> os registos importáveis foram gravados e matriculados na turma seleccionada.';
        if (data.lote_id) {
            html += '<br><span class="sige-import-batch">Lote: ' + sigeAlunoEscapeHtml(data.lote_id) + '</span>';
            jQuery('#sige_lote_anular_id').val(data.lote_id);
        }
    }
    html += '</span></div>';

    html += '<div class="sige-import-result-grid">';
    html += '<div class="sige-import-result-kpi"><span>Linhas</span><strong>' + sigeAlunoEscapeHtml(stats.total_linhas || 0) + '</strong></div>';
    html += '<div class="sige-import-result-kpi"><span>' + (isPreview ? 'Importáveis' : 'Importados') + '</span><strong>' + sigeAlunoEscapeHtml(isPreview ? importaveis : (stats.importados || 0)) + '</strong></div>';
    html += '<div class="sige-import-result-kpi"><span>Pendentes</span><strong>' + sigeAlunoEscapeHtml(stats.pendentes || 0) + '</strong></div>';
    html += '<div class="sige-import-result-kpi"><span>Erros/Duplicados</span><strong>' + sigeAlunoEscapeHtml(errosDuplicados) + '</strong></div>';
    html += '</div>';

    html += '<p style="margin:0 0 12px;color:#475569;font-size:13px;line-height:1.55;">';
    html += (isPreview ? 'Pré-validação feita para ' : 'Importação processada para ') + '<strong>' + sigeAlunoEscapeHtml(turma) + '</strong>. ';
    if ((stats.pendentes || 0) > 0) {
        html += 'Há alunos com dados de pais/contactos pendentes; actualize essas fichas antes de activar comunicações por WhatsApp. ';
    }
    if (data.truncado) {
        html += 'A lista abaixo mostra apenas os primeiros registos do relatório. ';
    }
    html += '</p>';

    if (detalhes.length) {
        html += '<div class="sige-import-detail-list">';
        detalhes.forEach(function(item){
            var estado = item.estado || 'info';
            html += '<div class="sige-import-detail-item">';
            html += '<span>Linha ' + sigeAlunoEscapeHtml(item.linha || '-') + '</span>';
            html += '<span class="sige-import-status ' + sigeAlunoEscapeHtml(estado) + '">' + sigeAlunoEscapeHtml(estado) + '</span>';
            html += '<span><strong>' + sigeAlunoEscapeHtml(item.nome || 'Sem nome') + '</strong><br>' + sigeAlunoEscapeHtml(item.mensagem || '') + '</span>';
            html += '</div>';
        });
        html += '</div>';
    }

    if (isPreview && importaveis > 0) {
        html += '<div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:14px;">';
        html += '<button type="button" class="sige-btn-modal sige-btn-cancel" data-sige-act="resetImportacaoAlunosPreview" data-sige-args="[true]">Rever ficheiro</button>';
        html += '<button type="button" id="btn-confirm-import-alunos" class="sige-btn-modal sige-btn-submit" data-sige-act="confirmarImportacaoAlunos" data-sige-noargs>Confirmar e gravar ' + sigeAlunoEscapeHtml(importaveis) + ' aluno(s)</button>';
        html += '</div>';
    } else if (!isPreview && (stats.importados || 0) > 0) {
        html += '<div style="display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap;margin-top:14px;">';
        if (data.lote_id) {
            html += '<button type="button" class="sige-btn-modal sige-btn-danger-soft" data-sige-act="anularLoteImportacaoAlunos" data-sige-arg="' + sigeAlunoEscapeHtml(data.lote_id) + '">Anular este lote</button>';
        }
        html += '<button type="button" class="sige-btn-modal sige-btn-submit" data-sige-act="sigeAlunosImportacaoReload" data-sige-noargs>Actualizar lista</button>';
        html += '</div>';
    }

    jQuery('#sige-import-result').html(html).addClass('active');
}

function anularLoteImportacaoAlunosFromField() {
    var loteId = jQuery('#sige_lote_anular_id').val();
    anularLoteImportacaoAlunos(loteId);
}

function anularLoteImportacaoAlunos(loteId) {
    loteId = String(loteId || '').trim();
    if (!loteId) {
        sigeAlunoAlert('Informe o ID do lote que deseja anular.', 'ID do lote obrigatório', 'warning');
        return;
    }
    if (!/^SGIMP-[0-9]+-[0-9]{8}-[0-9]{6}-[A-Z0-9]{4,16}$/.test(loteId)) {
        sigeAlunoAlert('O ID do lote não parece válido. Copie o lote exactamente como aparece no relatório da importação.', 'Lote inválido', 'warning');
        return;
    }

    var msg = 'Confirma a anulação automática do lote ' + loteId + '? O sistema só vai remover os alunos se o lote ainda estiver limpo, sem pagamentos, notas, documentos, portaria, transporte ou mensagens associadas.';
    sigeAlunoConfirmAsync(msg, 'Anular lote de importação', 'Anular lote', 'danger').then(function(ok){
        if (!ok) return;
        var btn = jQuery('#btn-anular-lote-importacao');
        var original = btn.html();
        btn.prop('disabled', true).text('A verificar...');
        jQuery.ajax({
            url: ajaxurl,
            type: 'POST',
            data: {
                action: 'sige_anular_lote_importacao_alunos',
                _sige_nonce: sigeAjax.nonce_alunos,
                lote_id: loteId
            },
            success: function(res) {
                btn.prop('disabled', false).html(original);
                if (res && res.success) {
                    var data = res.data || {};
                    var texto = 'Lote anulado com segurança. Alunos removidos: ' + (data.alunos_anulados || 0) + '. Matrículas removidas: ' + (data.matriculas_removidas || 0) + '.';
                    sessionStorage.setItem('sige_toast', JSON.stringify({type:'success', msg:texto}));
                    sigeAlunoAlert(texto, 'Lote anulado', 'success').then(function(){ location.reload(); });
                } else {
                    sigeAlunoAlert((res && res.data ? res.data : 'Não foi possível anular este lote.'), 'Anulação bloqueada', 'warning');
                }
            },
            error: function() {
                btn.prop('disabled', false).html(original);
                sigeAlunoAlert('O servidor não respondeu durante a anulação do lote. Tente novamente.', 'Erro de servidor', 'error');
            }
        });
    });
}

function confirmarImportacaoAlunos() {
    var hash = jQuery('#sige_preview_hash').val();
    if (!hash) {
        sigeAlunoAlert('Faça primeiro a pré-validação da lista antes de gravar.', 'Pré-validação obrigatória', 'warning');
        return;
    }

    // v12.11.9.45 - o botão "Confirmar e gravar" já é a confirmação final da Etapa 2.
    // Antes havia um segundo popup de confirmação que podia ficar oculto por trás do modal
    // de importação, dando a impressão de que nada acontecia.
    jQuery('#sige_import_mode').val('confirm');
    jQuery('#form-import-alunos').trigger('submit');
}

jQuery('#sige_import_turma_id, #sige_import_ficheiro, #form-import-alunos input[name="permitir_pendentes"]').on('change', function(){
    resetImportacaoAlunosPreview(true);
});

jQuery('#sige_lote_anular_id').on('keydown', function(e){
    if (e.key === 'Enter') {
        e.preventDefault();
        anularLoteImportacaoAlunosFromField();
    }
});

jQuery('#form-import-alunos').on('submit', function(e){
    e.preventDefault();
    var turmaId = jQuery('#sige_import_turma_id').val();
    var fileInput = document.getElementById('sige_import_ficheiro');
    var modo = jQuery('#sige_import_mode').val() || 'preview';
    if (!turmaId) {
        sigeAlunoAlert('Seleccione a turma de destino antes de importar.', 'Turma obrigatória', 'warning');
        return;
    }
    if (!fileInput || !fileInput.files || !fileInput.files.length) {
        sigeAlunoAlert('Carregue o ficheiro Excel (.xlsx) ou CSV da lista de estudantes.', 'Ficheiro obrigatório', 'warning');
        return;
    }
    if (modo === 'confirm' && !jQuery('#sige_preview_hash').val()) {
        sigeAlunoAlert('Faça primeiro a pré-validação da lista antes de gravar.', 'Pré-validação obrigatória', 'warning');
        return;
    }

    var btn = jQuery('#btn-import-alunos');
    var confirmBtn = jQuery('#btn-confirm-import-alunos');
    var original = btn.html();
    var confirmOriginal = confirmBtn.length ? confirmBtn.html() : '';
    var busyIcon = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>';
    var busyText = modo === 'confirm' ? 'A gravar...' : 'A pré-validar...';
    btn.html(busyIcon + ' ' + busyText).prop('disabled', true);
    confirmBtn.prop('disabled', true);

    if (modo === 'confirm') {
        if (confirmBtn.length) {
            confirmBtn.html(busyIcon + ' A gravar alunos...');
        }
        jQuery('#sige-import-result .sige-import-processing-note').remove();
        jQuery('#sige-import-result')
            .addClass('active')
            .prepend('<div class="sige-import-processing-note">' + busyIcon + '<span><strong>Etapa 2 em curso:</strong> o SoftGenial está a gravar os alunos importáveis e a criar as respectivas matrículas. Aguarde a confirmação final.</span></div>');
    } else {
        jQuery('#sige-import-result').removeClass('active').empty();
    }

    var fd = new FormData(this);
    fd.set('_sige_nonce', sigeAjax.nonce_alunos);
    fd.set('sige_import_mode', modo);

    jQuery.ajax({
        url: ajaxurl,
        type: 'POST',
        data: fd,
        processData: false,
        contentType: false,
        success: function(res) {
            btn.html(sigeImportPreviewBtnHtml).prop('disabled', false);
            if (confirmBtn.length) {
                confirmBtn.html(confirmOriginal).prop('disabled', false);
            }
            jQuery('#sige-import-result .sige-import-processing-note').remove();
            if (res && res.success) {
                renderImportacaoAlunosResultado(res.data || {});
                jQuery('#sige_import_mode').val('preview');
                if (modo === 'confirm') {
                    jQuery('#sige_preview_hash').val('');
                    carregarHistoricoLotesImportacao(false);
                }
            } else {
                jQuery('#sige_import_mode').val('preview');
                if (confirmBtn.length && confirmOriginal) {
                    confirmBtn.html(confirmOriginal).prop('disabled', false);
                }
                sigeAlunoAlert('Erro: ' + (res && res.data ? res.data : 'Não foi possível processar a importação.'), 'Importação não concluída', 'error');
            }
        },
        error: function() {
            btn.html(sigeImportPreviewBtnHtml).prop('disabled', false);
            if (confirmBtn.length) {
                confirmBtn.html(confirmOriginal).prop('disabled', false);
            }
            jQuery('#sige-import-result .sige-import-processing-note').remove();
            jQuery('#sige_import_mode').val('preview');
            sigeAlunoAlert('O servidor não respondeu durante a importação. Verifique o ficheiro Excel/CSV e tente novamente.', 'Erro de servidor', 'error');
        }
    });
});

function sigeAlunoPopupIcon(type) {
    if (type === 'danger') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 6h18"/><path d="M8 6V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/></svg>';
    if (type === 'warning') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>';
    if (type === 'success') return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6L9 17l-5-5"/></svg>';
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>';
}
function sigeAlunoOpenPopup(opts) {
    opts = opts || {};
    var type = opts.type || 'info';
    var box = jQuery('#sige-alunos-popup .sige-alunos-popup-box');
    box.removeClass('is-danger is-warning is-success').addClass(type === 'danger' ? 'is-danger' : (type === 'warning' ? 'is-warning' : (type === 'success' ? 'is-success' : '')));
    jQuery('#sige-alunos-popup-title').text(opts.title || 'Informação');
    jQuery('#sige-alunos-popup .sige-alunos-popup-body').text(opts.message || '');
    jQuery('#sige-alunos-popup .sige-alunos-popup-icon').html(sigeAlunoPopupIcon(type));
    var cancelBtn = jQuery('#sige-alunos-popup .sige-alunos-popup-cancel');
    var confirmBtn = jQuery('#sige-alunos-popup .sige-alunos-popup-confirm');
    cancelBtn.text(opts.cancelText || 'Cancelar').toggle(opts.mode === 'confirm');
    confirmBtn.text(opts.confirmText || 'OK');
    jQuery('#sige-alunos-popup').addClass('is-open').attr('aria-hidden','false');
    jQuery('body').addClass('sige-modal-open');
    return new Promise(function(resolve){
        function close(result){
            jQuery('#sige-alunos-popup').removeClass('is-open').attr('aria-hidden','true');
            jQuery('body').removeClass('sige-modal-open');
            cancelBtn.off('click', onCancel); confirmBtn.off('click', onConfirm);
            jQuery('#sige-alunos-popup .sige-alunos-popup-backdrop').off('click', onCancel);
            jQuery(document).off('keydown.sigeAlunoPopup', onKey);
            resolve(result);
        }
        function onCancel(){ close(false); }
        function onConfirm(){ close(true); }
        function onKey(ev){ if (ev.key === 'Escape') { ev.preventDefault(); close(false); } }
        cancelBtn.on('click', onCancel);
        confirmBtn.on('click', onConfirm).focus();
        jQuery('#sige-alunos-popup .sige-alunos-popup-backdrop').on('click', onCancel);
        jQuery(document).on('keydown.sigeAlunoPopup', onKey);
    });
}
function sigeAlunoAlert(message, title, type) {
    return sigeAlunoOpenPopup({mode:'alert', title:title || 'Informação', message:message || '', type:type || 'info', confirmText:'Entendi'});
}
function sigeAlunoConfirm(message, title, confirmText, onConfirm, type) {
    sigeAlunoOpenPopup({mode:'confirm', title:title || 'Confirmar acção', message:message || '', type:type || 'warning', confirmText:confirmText || 'Confirmar', cancelText:'Voltar'}).then(function(ok){ if (ok && typeof onConfirm === 'function') onConfirm(); });
}
async function sigeAlunoConfirmAsync(message, title, confirmText, type) {
    return await sigeAlunoOpenPopup({mode:'confirm', title:title || 'Confirmar acção', message:message || '', type:type || 'warning', confirmText:confirmText || 'Confirmar', cancelText:'Voltar'});
}

// [12.9.8/v12.11.9.69] Antes era: var sigeTodosAlunos = <?php /* json_encode gigante */ ?>;
// Agora faz fetch on-demand por finalidade e cacheia separadamente. Isso permite payload mínimo para cartões
// e autorização distinta para Excel/exportação.
var _sigeTodosAlunosCache = {};
function sigeAlunoNormalizeSearch(value) {
    var text = String(value || '').toLowerCase();
    if (typeof text.normalize === 'function') {
        text = text.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }
    return text.trim();
}
function sigeGetCurrentExportFilters() {
    return {
        filtro_turma: String(jQuery('#filtro-turma').val() || '0'),
        filtro_status: String(jQuery('#filtro-status').val() || ''),
        filtro_search: String(jQuery('#filtro-texto').val() || '').trim()
    };
}
async function sigeGetTodosAlunos(purpose) {
    purpose = String(purpose || 'export').replace(/[^a-z0-9_-]/gi, '').toLowerCase() || 'export';
    var filtrosExport = sigeGetCurrentExportFilters();
    var cacheKey = purpose + ':' + JSON.stringify(filtrosExport);
    if (Object.prototype.hasOwnProperty.call(_sigeTodosAlunosCache, cacheKey)) return _sigeTodosAlunosCache[cacheKey];
    return new Promise(function(resolve) {
        jQuery.post(ajaxurl, {
            action: 'sige_get_alunos_export',
            _sige_nonce: sigeAjax.nonce_alunos,
            purpose: purpose,
            filtro_turma: filtrosExport.filtro_turma,
            filtro_status: filtrosExport.filtro_status,
            filtro_search: filtrosExport.filtro_search
        }, function(resp) {
            if (resp && resp.success && Array.isArray(resp.data)) {
                _sigeTodosAlunosCache[cacheKey] = resp.data;
                resolve(resp.data);
            } else {
                var msg = (resp && resp.data) ? resp.data : 'Erro desconhecido';
                console.error('sigeGetTodosAlunos failed:', msg);
                sigeAlunoAlert(msg, 'Falha ao carregar alunos', 'error');
                resolve([]);
            }
        }).fail(function(xhr) {
            console.error('sigeGetTodosAlunos AJAX fail:', xhr.status);
            sigeAlunoAlert('Falha de comunicação ao carregar todos os alunos.', 'Falha de comunicação', 'error');
            resolve([]);
        });
    });
}

// Toast ao carregar página
jQuery(document).ready(function(){
    var toastData = sessionStorage.getItem('sige_toast');
    if(toastData){
        sessionStorage.removeItem('sige_toast');
        try {
            var t = JSON.parse(toastData);
            showToast(t.type, t.msg);
        } catch(e){}
    }
});

function showToast(type, msg) {
    var safeType = String(type || 'info').replace(/[^a-z0-9_-]/gi, '').toLowerCase() || 'info';
    var toast = jQuery('<div></div>').addClass('sige-toast').addClass(safeType).text(String(msg || ''));
    jQuery('body').append(toast);
    setTimeout(function(){ toast.remove(); }, 4000);
}

// ========================================
// TABS
// ========================================
function switchTab(el) {
    jQuery('.sige-tab').removeClass('active');
    jQuery('.sige-tab-content').removeClass('active');
    jQuery(el).addClass('active');
    var tabId = el ? el.getAttribute('data-tab') : null;
    if (tabId) { jQuery('#' + tabId).addClass('active'); }
}

function resetTabsToFirst() {
    jQuery('.sige-tab').removeClass('active');
    jQuery('.sige-tab-content').removeClass('active');
    jQuery('.sige-tab[data-tab="tab-dados"]').addClass('active');
    jQuery('#tab-dados').addClass('active');
}

// v12.11.9.65 - permissões efectivas no front-end; a defesa real permanece no servidor.
window.sigeAlunosCanCreate = <?php echo $sige_alunos_can_create ? 'true' : 'false'; ?>;
window.sigeAlunosCanEdit = <?php echo $sige_alunos_can_edit ? 'true' : 'false'; ?>;
window.sigeAlunosCanDelete = <?php echo $sige_alunos_can_delete ? 'true' : 'false'; ?>;
window.sigeAlunosIsGuarda = <?php echo $sige_alunos_is_guarda ? 'true' : 'false'; ?>;
window.sigeAlunosCanDocuments = <?php echo $sige_alunos_can_documents_emit ? 'true' : 'false'; ?>;
window.sigeAlunosCanDocumentsView = <?php echo $sige_alunos_can_documents_view ? 'true' : 'false'; ?>;
window.sigeAlunosCanExport = <?php echo $sige_alunos_can_export ? 'true' : 'false'; ?>;
window.sigeAlunosCanFinanceView = <?php echo $sige_alunos_can_finance_view ? 'true' : 'false'; ?>;
window.sigeAlunosFinanceHistoryBaseUrl = '<?php echo esc_js(admin_url('admin.php?sige_print=historico_financeiro_aluno&id=')); ?>';
window.sigeAlunosFinanceHistoryExcelBaseUrl = '<?php echo esc_js(admin_url('admin.php?sige_print=historico_financeiro_aluno&formato=xlsx&id=')); ?>';
window.sigeAlunosFinanceExtractsBaseUrl = '<?php echo esc_js(admin_url('admin.php?page=sige-app&view=financeiro-extratos&modo=aluno&aluno_id=')); ?>';

// ========================================
// MODAL
// ========================================
function fecharModal() {
    jQuery('#modal-aluno').fadeOut(200);
    jQuery('body').removeClass('sige-aluno-modal-open');
}

function novoAluno() {
    if (!window.sigeAlunosCanCreate) { sigeAlunoAlert('O seu perfil permite apenas consultar alunos. Não pode registar novos alunos.', 'Modo consulta', 'warning'); return; }
    jQuery('#form-aluno')[0].reset();
    jQuery('#id_aluno').val('');
    jQuery('#preview-img').hide();
    jQuery('#placeholder-foto').show();
    jQuery('#modal-title').text('Registar Aluno');
    jQuery('#numero_processo_view').val('(será gerado automaticamente)');
    jQuery('#encarregado_principal_tipo').val('pai_mae');
    jQuery('#canal_preferencial_comunicacao').val('whatsapp');
    jQuery('#consent_whatsapp,#consent_email,#consent_chamada').prop('checked', true);
    jQuery('#consent_sms').prop('checked', false);
    jQuery('.sige-doc-box').removeClass('has-file');
    sigeToggleOutroEncarregado();
    resetTabsToFirst();
    jQuery('body').addClass('sige-aluno-modal-open');
    jQuery('#modal-aluno').fadeIn(300).css('display','flex');
}

// [12.9.8] editarAluno() - agora aceita um ID e faz fetch via AJAX para puxar
// os dados completos do aluno. Mantém compatibilidade: se for chamado com um
// objecto (chamadas antigas pelo código), passa directamente para
// editarAlunoFromData(). O corpo principal foi movido para essa função.
function editarAluno(idOrData) {
    if (!window.sigeAlunosCanEdit) {
        var rid = (typeof idOrData === 'object' && idOrData !== null) ? parseInt(idOrData.id || 0, 10) : parseInt(idOrData, 10);
        if (rid > 0 && typeof abrirFichaAluno360 === 'function') { abrirFichaAluno360(rid); return; }
        sigeAlunoAlert('O seu perfil permite apenas consultar a ficha do aluno. Não pode editar dados.', 'Modo consulta', 'warning');
        return;
    }
    if (typeof idOrData === 'object' && idOrData !== null) {
        return editarAlunoFromData(idOrData);
    }
    var id = parseInt(idOrData, 10);
    if (!id || id <= 0) { sigeAlunoAlert('Não foi possível identificar o aluno seleccionado.', 'Atenção', 'warning'); return; }
    // Mostrar feedback visual rápido - modal abre logo, dados preenchem ao chegar
    jQuery('body').addClass('sige-aluno-modal-open');
    jQuery('#modal-aluno').fadeIn(150).css('display','flex');
    jQuery('#modal-title').text('A carregar aluno...');
    jQuery.post(ajaxurl, {
        action: 'sige_get_aluno_full',
        _sige_nonce: sigeAjax.nonce_alunos,
        aluno_id: id
    }, function(resp) {
        if (resp && resp.success && resp.data) {
            editarAlunoFromData(resp.data);
        } else {
            jQuery('#modal-aluno').fadeOut(150);
            jQuery('body').removeClass('sige-aluno-modal-open');
            var msg = (resp && resp.data) ? resp.data : 'Erro a carregar dados do aluno.';
            sigeAlunoAlert(msg, 'Não foi possível carregar', 'error');
        }
    }).fail(function(xhr) {
        jQuery('#modal-aluno').fadeOut(150);
        jQuery('body').removeClass('sige-aluno-modal-open');
        sigeAlunoAlert('Não foi possível comunicar com o servidor. Tente novamente.', 'Erro de comunicação', 'error');
    });
}

function editarAlunoFromData(data) {
    jQuery('#form-aluno')[0].reset();
    jQuery('#id_aluno').val(data.id);
    jQuery('#nome').val(data.nome_completo);
    jQuery('#data_nascimento').val(data.data_nascimento ? data.data_nascimento.split(' ')[0] : '');
    jQuery('#genero').val(data.genero);
    jQuery('#turma_id').val(data.turma_id);
    jQuery('#tipo_documento').val(data.tipo_documento || '');
    jQuery('#documento_numero').val(data.documento_nr || data.documento_numero || '');
    jQuery('#bairro').val(data.bairro || '');
    jQuery('#status_aluno').val(data.status || 'activo');
    jQuery('#nacionalidade').val(data.nacionalidade || 'Moçambicana');
    jQuery('#nuit_encarregado').val(data.nuit_encarregado || '');
    
    jQuery('#tem_desconto_irmao').prop('checked', String(data.tem_desconto_irmao) === '1');
    jQuery('#tem_desconto_funcionario').prop('checked', String(data.tem_desconto_funcionario) === '1');
    jQuery('#tem_estudos').prop('checked', String(data.tem_estudos) === '1');
    jQuery('#tem_ingles').prop('checked', String(data.tem_ingles) === '1');
    jQuery('#tem_desporto').prop('checked', String(data.tem_desporto) === '1');
    jQuery('#tem_pequeno_almoco').prop('checked', String(data.tem_pequeno_almoco) === '1');
    jQuery('#tem_almoco').prop('checked', String(data.tem_almoco) === '1');
    
    if(data.regime_creche === 'semi_integral') jQuery('#regime_semi').prop('checked', true);
    else if(data.regime_creche === 'integral') jQuery('#regime_integral').prop('checked', true);
    else jQuery('#regime_nenhum').prop('checked', true);

    // [v13] Regime de Mensalidade (tempo_inteiro / meio_dia)
    if(data.regime_mensalidade === 'tempo_inteiro') jQuery('#regime_mens_tempo_inteiro').prop('checked', true);
    else if(data.regime_mensalidade === 'meio_dia') jQuery('#regime_mens_meio_dia').prop('checked', true);
    else jQuery('#regime_mens_nenhum').prop('checked', true);

    // [v13] Actividades Extras vinculadas ao aluno
    jQuery('.atv-extra-checkbox').prop('checked', false); // reset
    if (data.atividades_extras_ids && Array.isArray(data.atividades_extras_ids)) {
        data.atividades_extras_ids.forEach(function(aid){
            jQuery('#atv_extra_' + parseInt(aid,10)).prop('checked', true);
        });
    }
    
    jQuery('#mensalidade_base').val(data.mensalidade_base || '');
    jQuery('#rota_transporte_id').val(data.rota_transporte_id || '0');
    jQuery('#numero_processo_view').val(data.numero_processo || '');
    
    // Encarregados
    jQuery('#nome_pai').val(data.nome_pai || '');
    jQuery('#profissao_pai').val(data.profissao_pai || '');
    jQuery('#telemovel_pai').val(data.telemovel_pai || '');
    jQuery('#telemovel_pai_2').val(data.telemovel_pai_2 || '');
    jQuery('#email_pai').val(data.email_pai || '');
    jQuery('#nome_mae').val(data.nome_mae || '');
    jQuery('#profissao_mae').val(data.profissao_mae || '');
    jQuery('#telemovel_mae').val(data.telemovel_mae || '');
    jQuery('#telemovel_mae_2').val(data.telemovel_mae_2 || '');
    jQuery('#email_mae').val(data.email_mae || '');
    jQuery('#whatsapp_notificacoes').val(data.whatsapp_notificacoes || '');
    jQuery('#encarregado_principal_tipo').val(data.encarregado_principal_tipo || 'pai_mae');
    jQuery('#encarregado_principal_nome').val(data.encarregado_principal_nome || '');
    jQuery('#encarregado_principal_parentesco').val(data.encarregado_principal_parentesco || '');
    jQuery('#encarregado_principal_telemovel').val(data.encarregado_principal_telemovel || '');
    jQuery('#encarregado_principal_email').val(data.encarregado_principal_email || '');
    jQuery('#canal_preferencial_comunicacao').val(data.canal_preferencial_comunicacao || 'whatsapp');
    jQuery('#consent_whatsapp').prop('checked', String(data.consent_whatsapp !== undefined && data.consent_whatsapp !== null ? data.consent_whatsapp : '1') === '1');
    jQuery('#consent_email').prop('checked', String(data.consent_email !== undefined && data.consent_email !== null ? data.consent_email : '1') === '1');
    jQuery('#consent_sms').prop('checked', String(data.consent_sms !== undefined && data.consent_sms !== null ? data.consent_sms : '0') === '1');
    jQuery('#consent_chamada').prop('checked', String(data.consent_chamada !== undefined && data.consent_chamada !== null ? data.consent_chamada : '1') === '1');
    jQuery('#contacto_alternativo_nome').val(data.contacto_alternativo_nome || '');
    jQuery('#contacto_alternativo_parentesco').val(data.contacto_alternativo_parentesco || '');
    jQuery('#contacto_alternativo_telemovel').val(data.contacto_alternativo_telemovel || '');
    jQuery('#autorizado_buscar_nome').val(data.autorizado_buscar_nome || '');
    jQuery('#autorizado_buscar_parentesco').val(data.autorizado_buscar_parentesco || '');
    jQuery('#autorizado_buscar_telemovel').val(data.autorizado_buscar_telemovel || '');
    jQuery('#autorizado_buscar_documento').val(data.autorizado_buscar_documento || '');
    jQuery('#encarregado_observacoes').val(data.encarregado_observacoes || '');
    sigeToggleOutroEncarregado();
    
    // Saúde
    jQuery('#grupo_sanguineo').val(data.grupo_sanguineo || '');
    jQuery('#hospital_preferencia').val(data.hospital_preferencia || '');
    jQuery('#alergias').val(data.alergias || '');
    jQuery('#condicoes_medicas').val(data.condicoes_medicas || '');
    jQuery('#contacto_emergencia_1').val(data.contacto_emergencia_1 || '');
    jQuery('#contacto_emergencia_2').val(data.contacto_emergencia_2 || '');
    
    // Foto
    if(data.foto) {
        jQuery('#foto_url').val(data.foto);
        jQuery('#preview-img').attr('src', data.foto).show();
        jQuery('#placeholder-foto').hide();
    } else {
        jQuery('#preview-img').hide();
        jQuery('#placeholder-foto').show();
    }
    
    // Docs
    jQuery('#doc_bi_url').val(data.doc_bi_url || '');
    jQuery('#doc_cert_url').val(data.doc_cert_url || '');
    jQuery('#doc_vacina_url').val(data.doc_vacina_url || '');
    applyDocStatusesFromData(data);
    
    jQuery('#modal-title').text('Editar Aluno');
    resetTabsToFirst();
    jQuery('body').addClass('sige-aluno-modal-open');
    jQuery('#modal-aluno').fadeIn(300).css('display','flex');
}

function applyDocStatusesFromData(data) {
    jQuery('#box-bi').toggleClass('has-file', !!(data.doc_bi_url));
    jQuery('#box-cert').toggleClass('has-file', !!(data.doc_cert_url));
    jQuery('#box-vacina').toggleClass('has-file', !!(data.doc_vacina_url));
}

// ========================================
// UPLOAD FOTO & DOCS
// ========================================
jQuery('#btn-upload-foto').on('click', function(){
    var frame = wp.media({ title: 'Seleccionar Foto', multiple: false, library: { type: 'image' } });
    frame.on('select', function(){
        var url = frame.state().get('selection').first().toJSON().url;
        jQuery('#foto_url').val(url);
        jQuery('#preview-img').attr('src', url).show();
        jQuery('#placeholder-foto').hide();
    });
    frame.open();
});

function uploadDoc(tipo) {
    var frame = wp.media({ title: 'Seleccionar Documento', multiple: false });
    frame.on('select', function(){
        var url = frame.state().get('selection').first().toJSON().url;
        if(tipo === 'bi') { jQuery('#doc_bi_url').val(url); jQuery('#box-bi').addClass('has-file'); }
        if(tipo === 'cert') { jQuery('#doc_cert_url').val(url); jQuery('#box-cert').addClass('has-file'); }
        if(tipo === 'vacina') { jQuery('#doc_vacina_url').val(url); jQuery('#box-vacina').addClass('has-file'); }
    });
    frame.open();
}

function sigeToggleOutroEncarregado() {
    var tipo = jQuery('#encarregado_principal_tipo').val() || 'pai_mae';
    var isOutro = tipo === 'outro';
    jQuery('.sige-guardian-other-row').toggleClass('is-required', isOutro);
    jQuery('#encarregado_principal_nome,#encarregado_principal_telemovel').prop('required', isOutro);
}

function sigeMaybeFillWhatsappFromPrincipal() {
    if ((jQuery('#whatsapp_notificacoes').val() || '').trim() !== '') return;
    var tipo = jQuery('#encarregado_principal_tipo').val() || 'pai_mae';
    var tel = '';
    if (tipo === 'pai') tel = jQuery('#telemovel_pai').val() || '';
    else if (tipo === 'mae') tel = jQuery('#telemovel_mae').val() || '';
    else if (tipo === 'outro') tel = jQuery('#encarregado_principal_telemovel').val() || '';
    else tel = jQuery('#telemovel_pai').val() || jQuery('#telemovel_mae').val() || '';
    if (tel) jQuery('#whatsapp_notificacoes').val(tel.replace(/\D/g, '').replace(/^258/, ''));
}

jQuery(document).on('change', '#encarregado_principal_tipo', function(){
    sigeToggleOutroEncarregado();
    sigeMaybeFillWhatsappFromPrincipal();
});
jQuery(document).on('blur change', '#telemovel_pai,#telemovel_mae,#encarregado_principal_telemovel', sigeMaybeFillWhatsappFromPrincipal);

// ========================================
// SUBMIT
// ========================================
jQuery('#form-aluno').on('submit', function(e){
    e.preventDefault();
    var savingExisting = parseInt(jQuery('#id_aluno').val() || '0', 10) > 0;
    if ((savingExisting && !window.sigeAlunosCanEdit) || (!savingExisting && !window.sigeAlunosCanCreate)) {
        sigeAlunoAlert('O seu perfil não tem permissão para guardar alterações em alunos.', 'Permissão negada', 'warning');
        return;
    }
    var cleanPhone = function(sel){
        var v = (jQuery(sel).val() || '').replace(/\D/g, '');
        if (v.length === 12 && v.substring(0,3) === '258') v = v.substring(3);
        return v;
    };
    var telPai = cleanPhone('#telemovel_pai');
    var telPai2 = cleanPhone('#telemovel_pai_2');
    var telMae = cleanPhone('#telemovel_mae');
    var telMae2 = cleanPhone('#telemovel_mae_2');
    var telOutro = cleanPhone('#encarregado_principal_telemovel');
    var telAlt = cleanPhone('#contacto_alternativo_telemovel');
    var telBusca = cleanPhone('#autorizado_buscar_telemovel');
    var nuit = (jQuery('#nuit_encarregado').val() || '').replace(/\D/g, '');
    var regexTel = /^(82|83|84|85|86|87)\d{7}$/;
    var regexNuit = /^\d{9}$/;
    if(telPai && !regexTel.test(telPai)) { sigeAlunoAlert('Tel. do pai (1): deve começar com 82/83/84/85/86/87 e ter 9 dígitos.', 'Corrigir contacto', 'warning'); return; }
    if(telPai2 && !regexTel.test(telPai2)) { sigeAlunoAlert('Tel. do pai (2): deve começar com 82/83/84/85/86/87 e ter 9 dígitos.', 'Corrigir contacto', 'warning'); return; }
    if(telMae && !regexTel.test(telMae)) { sigeAlunoAlert('Tel. da mãe (1): deve começar com 82/83/84/85/86/87 e ter 9 dígitos.', 'Corrigir contacto', 'warning'); return; }
    if(telMae2 && !regexTel.test(telMae2)) { sigeAlunoAlert('Tel. da mãe (2): deve começar com 82/83/84/85/86/87 e ter 9 dígitos.', 'Corrigir contacto', 'warning'); return; }
    if(telOutro && !regexTel.test(telOutro)) { sigeAlunoAlert('Tel. do outro encarregado: deve começar com 82/83/84/85/86/87 e ter 9 dígitos.', 'Corrigir contacto', 'warning'); return; }
    if(telAlt && !regexTel.test(telAlt)) { sigeAlunoAlert('Contacto alternativo: deve começar com 82/83/84/85/86/87 e ter 9 dígitos.', 'Corrigir contacto', 'warning'); return; }
    if(telBusca && !regexTel.test(telBusca)) { sigeAlunoAlert('Contacto da pessoa autorizada a buscar: deve começar com 82/83/84/85/86/87 e ter 9 dígitos.', 'Corrigir contacto', 'warning'); return; }
    if(nuit && !regexNuit.test(nuit)) { sigeAlunoAlert('NUIT: deve ter 9 dígitos.', 'Corrigir NUIT', 'warning'); return; }
    if ((jQuery('#encarregado_principal_tipo').val() || '') === 'outro' && (!jQuery('#encarregado_principal_nome').val() || !telOutro)) {
        sigeAlunoAlert('Para “Outro encarregado principal”, indique nome e telemóvel válido.', 'Completar encarregado', 'warning'); return;
    }
    
    var btn = jQuery('#btn-submit');
    var txt = btn.html();
    btn.html('<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;animation:spin 1s linear infinite;"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> A guardar...').prop('disabled', true);
    
    var fd = new FormData(this);
    if(!fd.has('status')) fd.append('status', jQuery('#status_aluno').val());
    fd.set('_sige_nonce', sigeAjax.nonce_alunos);
    
    jQuery.ajax({
        url: ajaxurl, type: 'POST', data: fd, processData: false, contentType: false,
        success: function(res) {
            if(res.success) {
                sessionStorage.setItem('sige_toast', JSON.stringify({type: 'success', msg: 'Aluno gravado com sucesso!'}));
                location.reload();
            } else {
                sigeAlunoAlert('Erro: ' + res.data, 'Não foi possível guardar', 'error');
                btn.html(txt).prop('disabled', false);
            }
        },
        error: function() {
            sigeAlunoAlert('Não foi possível guardar neste momento. Tente novamente.', 'Erro de servidor', 'error');
            btn.html(txt).prop('disabled', false);
        }
    });
});

// ========================================
// FILTROS
// ========================================
// [12.9.8] filtrarAlunosClient - filtragem em tempo real DENTRO da página actual
// (50 cards no DOM). Para procurar em toda a base, o utilizador faz Enter ou
// clica em "Pesquisar" e o form GET dispara uma nova request server-side.
function filtrarAlunosClient() {
    var txt = (jQuery('#filtro-texto').val() || '').toLowerCase();
    jQuery('.sige-aluno-card').each(function(){
        var search = (jQuery(this).data('search') || '').toString();
        if (search.includes(txt)) jQuery(this).fadeIn(120);
        else jQuery(this).fadeOut(120);
    });
}
// Alias para compatibilidade com chamadas existentes
function filtrarAlunos() { return filtrarAlunosClient(); }

// Submeter form de filtros ao premir Enter no campo de pesquisa
jQuery(document).ready(function(){
    jQuery('#filtro-texto').on('keydown', function(e){
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            document.getElementById('form-filtros').submit();
        }
    });
});

function filtrarAniversariantesHoje() {
    jQuery('.sige-aluno-card').each(function(){
        var isHoje = jQuery(this).data('hoje') == '1';
        if(isHoje) jQuery(this).fadeIn(200); else jQuery(this).fadeOut(200);
    });
}

function filtrarAniversariantesMes() {
    var mesAtual = '<?php echo $hoje_mes; ?>';
    jQuery('.sige-aluno-card').each(function(){
        var mes = jQuery(this).data('mes');
        if(mes === mesAtual) jQuery(this).fadeIn(200); else jQuery(this).fadeOut(200);
    });
}

function limparFiltros() {
    jQuery('#filtro-texto').val('');
    jQuery('#filtro-turma').val('0');
    jQuery('#filtro-status').val('');
    jQuery('.sige-aluno-card').fadeIn(200);
}

function apagarAluno(id, nome) {
    if (!window.sigeAlunosCanDelete) { sigeAlunoAlert('O seu perfil não tem permissão para remover alunos.', 'Permissão negada', 'warning'); return; }
    sigeAlunoConfirm('Está prestes a remover o aluno "' + nome + '".\n\nEsta acção é sensível. Confirme apenas se tem certeza de que pretende continuar.', 'Remover aluno', 'Remover aluno', function(){
        jQuery.post(ajaxurl, {action:'sige_remover_aluno', id:id, _sige_nonce: sigeAjax.nonce_alunos}, function(r){
            if(r.success) {
                sessionStorage.setItem('sige_toast', JSON.stringify({type: 'success', msg: 'Aluno removido com sucesso!'}));
                location.reload();
            } else sigeAlunoAlert('Erro: ' + (r.data || 'Falha ao remover'), 'Não foi possível remover', 'error');
        });
    }, 'danger');
}

function verAcesso(processo, nome) {
    if (window.sigeAlunosIsGuarda) { sigeAlunoAlert('O seu perfil não tem permissão para gerir acesso ao portal.', 'Permissão negada', 'warning'); return; }
    sigeAlunoAlert('Aluno: ' + nome + '\nProcesso: ' + processo + '\n\nEsta área será usada para partilhar o acesso ao portal quando a funcionalidade estiver activa.', 'Acesso ao Portal', 'info');
}

// ========================================
// FILTROS PARTILHADOS PARA EXPORTAÇÃO/CARTÕES - v12.11.9.69
// ========================================
function sigeAlunoTurmaTextoExport(a) {
    a = a || {};
    return ((a.classe && a.turma_nome) ? (a.classe + ' - ' + a.turma_nome) : (a.turma_nome || a.classe || '')).toString();
}
function sigeAlunoMatchesCurrentExportFilters(a) {
    a = a || {};
    var txt = sigeAlunoNormalizeSearch(jQuery('#filtro-texto').val() || '');
    var tr = String(jQuery('#filtro-turma').val() || '0');
    var st = String(jQuery('#filtro-status').val() || '').toLowerCase();
    var turmaTxt = sigeAlunoTurmaTextoExport(a);
    var searchStr = sigeAlunoNormalizeSearch([a.nome_completo, a.numero_processo, a.contacto_encarregado, a.telemovel_pai, a.telemovel_mae, turmaTxt, a.turma_nome, a.classe].join(' '));
    var matchTxt = !txt || searchStr.indexOf(txt) !== -1;
    var matchTr = (tr === '' || tr === '0' || String(a.turma_id || '') === tr);
    var aStatus = String(a.status || 'activo').toLowerCase();
    var matchSt = (!st || aStatus === st);
    return matchTxt && matchTr && matchSt;
}

function sigeAlunosFiltrarPorFiltrosActuais(alunos) {
    return (alunos || []).filter(sigeAlunoMatchesCurrentExportFilters);
}

// ========================================
// EXCEL PROFISSIONAL (PRESERVADO)
// ========================================
async function exportarExcelProfissional() {
    if (!window.sigeAlunosCanExport) { sigeAlunoAlert('O seu perfil não tem permissão para exportar listas de alunos.', 'Permissão negada', 'warning'); return; }
    // [12.9.8] fetch on-demand em vez de variável global inline
    var sigeTodosAlunos = sigeAlunosFiltrarPorFiltrosActuais(await sigeGetTodosAlunos('excel'));
    if (!sigeTodosAlunos || sigeTodosAlunos.length === 0) { sigeAlunoAlert('Não existem alunos para exportar com os filtros actuais.', 'Sem dados para exportar', 'warning'); return; }
    const toStr = (v) => (v === null || v === undefined) ? '' : String(v);
    const safeUpper = (v) => toStr(v).toUpperCase();
    const nomeEscola = toStr(sigeGlobal?.nome_escola || 'ESCOLA GERAL');
    const anoLectivo = toStr(sigeGlobal?.ano_lectivo || '2026');
    const workbook = new ExcelJS.Workbook();
    workbook.creator = nomeEscola;
    workbook.created = new Date();
    const worksheet = workbook.addWorksheet('Lista de Alunos', { pageSetup: { paperSize: 9, orientation: 'landscape', fitToPage: true, fitToWidth: 1, fitToHeight: 0 } });
    worksheet.mergeCells('A1:L1');
    const cA1 = worksheet.getCell('A1');
    cA1.value = safeUpper(nomeEscola);
    cA1.font = { name: 'Calibri', size: 22, bold: true, color: { argb: 'FF1A237E' } };
    cA1.alignment = { vertical: 'middle', horizontal: 'center' };
    worksheet.getRow(1).height = 40;
    worksheet.mergeCells('A2:L2');
    const cA2 = worksheet.getCell('A2');
    cA2.value = `LISTA GERAL DE ALUNOS MATRICULADOS - ANO LECTIVO ${anoLectivo}`;
    cA2.font = { name: 'Calibri', size: 13, bold: true, color: { argb: 'FF546E7A' } };
    cA2.alignment = { vertical: 'middle', horizontal: 'center' };
    cA2.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
    worksheet.getRow(2).height = 25;
    worksheet.mergeCells('A2:L2');
    const cA2_2 = worksheet.getCell('A2');
    cA2_2.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF5F5F5' } };
    worksheet.mergeCells('A3:L3');
    const cA3 = worksheet.getCell('A3');
    const dataProc = new Date().toLocaleString('pt-MZ', { day:'2-digit', month:'long', year:'numeric', hour:'2-digit', minute:'2-digit' });
    cA3.value = `Processado em: ${dataProc}`;
    cA3.font = { name: 'Calibri', size: 10, italic: true, color: { argb: 'FF999999' } };
    cA3.alignment = { vertical: 'middle', horizontal: 'center' };
    worksheet.getRow(3).height = 20;
    worksheet.getRow(4).height = 8;
    worksheet.getColumn(1).width = 6;
    worksheet.getColumn(2).width = 12;
    worksheet.getColumn(3).width = 38;
    worksheet.getColumn(4).width = 10;
    worksheet.getColumn(5).width = 14;
    worksheet.getColumn(6).width = 22;
    worksheet.getColumn(7).width = 28;
    worksheet.getColumn(8).width = 14;
    worksheet.getColumn(9).width = 28;
    worksheet.getColumn(10).width = 14;
    worksheet.getColumn(11).width = 18;
    worksheet.getColumn(12).width = 14;
    const headerRow = worksheet.getRow(5);
    headerRow.values = ['Nº','PROCESSO','NOME COMPLETO DO ALUNO','GÉNERO','DATA NASC.','TURMA','NOME DO PAI','TEL. PAI','NOME DA MÃE','TEL. MÃE','BAIRRO','STATUS'];
    headerRow.font = { name: 'Calibri', bold: true, color: { argb: 'FFFFFFFF' }, size: 11 };
    headerRow.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FF1A237E' } };
    headerRow.alignment = { vertical: 'middle', horizontal: 'center', wrapText: true };
    headerRow.height = 40;
    headerRow.eachCell({ includeEmpty: true }, (cell) => {
        cell.border = { top: { style: 'medium', color: { argb: 'FF1A237E' } }, left: { style: 'thin', color: { argb: 'FFFFFFFF' } }, bottom: { style: 'medium', color: { argb: 'FF1A237E' } }, right: { style: 'thin', color: { argb: 'FFFFFFFF' } } };
    });
    let turmaAtual = '';
    let contador = 0;
    let zebra = true;
    sigeTodosAlunos.forEach((aluno, index) => {
        contador++;
        const turma = (aluno.classe && aluno.turma_nome) ? `${aluno.classe} - ${aluno.turma_nome}` : 'Sem Turma';
        if (turma !== turmaAtual && index > 0) {
            const sep = worksheet.addRow([]);
            sep.height = 6;
            sep.eachCell({ includeEmpty: true }, (cell) => { cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFCFD8DC' } }; });
            zebra = true;
        }
        turmaAtual = turma;
        const st = aluno.status ? safeUpper(aluno.status) : 'ACTIVO';
        const row = worksheet.addRow([
            contador, toStr(aluno.numero_processo), toStr(aluno.nome_completo), toStr(aluno.genero),
            aluno.data_nascimento ? aluno.data_nascimento.split(' ')[0] : '', turma,
            toStr(aluno.nome_pai), toStr(aluno.telemovel_pai), toStr(aluno.nome_mae), toStr(aluno.telemovel_mae),
            toStr(aluno.bairro), st
        ]);
        row.height = 22;
        const bgColor = zebra ? 'FFFFFFFF' : 'FFF8FAFC';
        zebra = !zebra;
        row.eachCell({ includeEmpty: true }, (cell, colNumber) => {
            cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: bgColor } };
            cell.border = { top: { style: 'thin', color: { argb: 'FFE2E8F0' } }, bottom: { style: 'thin', color: { argb: 'FFE2E8F0' } }, left: { style: 'thin', color: { argb: 'FFE2E8F0' } }, right: { style: 'thin', color: { argb: 'FFE2E8F0' } } };
            cell.alignment = { vertical: 'middle', horizontal: colNumber <= 2 ? 'center' : 'left' };
            cell.font = { name: 'Calibri', size: 10, color: { argb: 'FF334155' } };
            if (colNumber === 1) cell.font = { name: 'Calibri', size: 10, bold: true, color: { argb: 'FF64748B' } };
            if (colNumber === 3) cell.font = { name: 'Calibri', size: 10, bold: true, color: { argb: 'FF1A237E' } };
            if (colNumber === 6) { cell.alignment = { vertical: 'middle', horizontal: 'center' }; cell.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFF0F4F8' } }; cell.font = { name: 'Calibri', size: 10, bold: true, color: { argb: 'FF1A237E' } }; }
        });
    });
    const ultimaLinha = worksheet.lastRow.number + 3;
    worksheet.mergeCells(`A${ultimaLinha}:L${ultimaLinha}`);
    const cTotal = worksheet.getCell(`A${ultimaLinha}`);
    cTotal.value = `TOTAL DE ALUNOS MATRICULADOS: ${sigeTodosAlunos.length} alunos`;
    cTotal.font = { name: 'Calibri', size: 12, bold: true, color: { argb: 'FF1A237E' } };
    cTotal.alignment = { vertical: 'middle', horizontal: 'center' };
    cTotal.fill = { type: 'pattern', pattern: 'solid', fgColor: { argb: 'FFE8EAF6' } };
    cTotal.border = { top: { style: 'medium', color: { argb: 'FF1A237E' } }, bottom: { style: 'medium', color: { argb: 'FF1A237E' } } };
    worksheet.getRow(ultimaLinha).height = 30;
    const assinaturaLinha = ultimaLinha + 4;
    worksheet.mergeCells(`A${assinaturaLinha}:F${assinaturaLinha}`);
    const a1 = worksheet.getCell(`A${assinaturaLinha}`);
    a1.value = 'O Chefe da Secretaria';
    a1.font = { name: 'Calibri', size: 10, italic: true, color: { argb: 'FF000000' } };
    a1.alignment = { vertical: 'middle', horizontal: 'center' };
    a1.border = { top: { style: 'thin', color: { argb: 'FF000000' } } };
    worksheet.mergeCells(`H${assinaturaLinha}:L${assinaturaLinha}`);
    const a2 = worksheet.getCell(`H${assinaturaLinha}`);
    a2.value = 'O Director';
    a2.font = { name: 'Calibri', size: 10, italic: true, color: { argb: 'FF000000' } };
    a2.alignment = { vertical: 'middle', horizontal: 'center' };
    a2.border = { top: { style: 'thin', color: { argb: 'FF000000' } } };
    worksheet.views = [{ state: 'frozen', xSplit: 0, ySplit: 5 }];
    try {
        const buffer = await workbook.xlsx.writeBuffer();
        const escolaLimpa = nomeEscola.replace(/\s+/g, '_').replace(/[^a-zA-Z0-9_]/g, '');
        const dataArquivo = new Date().toISOString().slice(0, 10);
        const nomeArquivo = `Lista_Alunos_${escolaLimpa}_${anoLectivo}_${dataArquivo}.xlsx`;
        saveAs(new Blob([buffer]), nomeArquivo);
        showToast('success', 'Excel exportado: ' + nomeArquivo);
    } catch(err) {
        console.error('Erro Excel:', err);
        sigeAlunoAlert('Erro ao gerar Excel: ' + err.message, 'Exportação não concluída', 'error');
    }
}

// ========================================
// IMPRESSÃO BOLETIM (PRESERVADO)
// ========================================
function imprimirBoletim(data) {
    if (!window.sigeAlunosCanDocuments) { sigeAlunoAlert('O seu perfil não tem permissão para emitir documentos.', 'Permissão negada', 'warning'); return; }
    var escolaNome = sigeGlobal.nome_escola || 'ESCOLA GERAL';
    var logoUrl = sigeGlobal.logo_url;
    var anoLectivo = sigeGlobal.ano_lectivo || 2026;
    var img = new Image();
    img.crossOrigin = "anonymous";
    img.src = logoUrl;
    img.onload = function() { runPrint(data, escolaNome, logoUrl, anoLectivo); };
    img.onerror = function() { runPrint(data, escolaNome, '<?php echo esc_url(SIGE_URL . 'assets/img/avatar-default.svg'); ?>', anoLectivo); };
}

function runPrint(data, escolaNome, logoUrl, anoLectivo) {
    var turma = (data.classe && data.turma_nome) ? `${data.classe} - ${data.turma_nome}` : '______________________';
    var val = function(v){ return v ? v : '______________________'; };
    var foto = data.foto
        ? `<img src="${data.foto}" style="width:100px;height:120px;object-fit:cover;border:2px solid #000;display:block;margin:0 auto;">`
        : `<div style="width:100px;height:120px;border:2px solid #000;display:flex;align-items:center;justify-content:center;margin:0 auto;font-size:10px;">FOTO</div>`;
    var html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Ficha_${data.numero_processo}</title>
    <style><?php echo sige_utilities_inline_css(); ?>
        body{font-family:'Times New Roman';padding:20px;color:#000;}
        .header{text-align:center;border-bottom:3px solid #000;margin-bottom:15px;padding-bottom:10px}
        .header img{height:80px;display:block;margin:0 auto 10px}
        .header h3{margin:2px;font-size:10pt;font-weight:bold;text-transform:uppercase}
        .header h2{margin:5px 0;font-size:16pt;font-weight:900;text-transform:uppercase}
        .boletim-box{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px}
        .boletim-title{border:3px solid #000;padding:10px 40px;font-weight:900;font-size:18pt;text-transform:uppercase}
        .section{border:2px solid #000;padding:10px;margin-bottom:15px;position:relative}
        .sec-title{position:absolute;top:-10px;left:10px;background:#fff;padding:0 5px;font-weight:900;font-size:10pt;text-transform:uppercase}
        .row{display:flex;margin-bottom:8px;gap:15px}
        .col{flex:1;display:flex;align-items:baseline}
        .label{font-weight:bold;margin-right:5px;font-size:10pt;white-space:nowrap}
        .value{border-bottom:1px dotted #000;flex:1;font-size:11pt;padding-left:5px}
        @media print{@page{margin:1.5cm}img{max-width:100%!important}}
    </style></head><body>
    <div class="header">
        <img src="${logoUrl}">
        <h3>República de Moçambique</h3>
        <h3>Ministério da Educação e Desenvolvimento Humano</h3>
        <h2>${escolaNome}</h2>
    </div>
    <div class="boletim-box">
        <div class="boletim-title">BOLETIM DE MATRÍCULA</div>
        <div class="sige-u-tac">${foto}<div style="border:1px solid #000;margin-top:5px;font-weight:bold;font-size:9pt">Proc. ${data.numero_processo}</div></div>
    </div>
    <div class="section">
        <span class="sec-title">1. DADOS DO ALUNO E ESCOLARIDADE</span>
        <div class="row"><div class="col" style="flex:2"><span class="label">Nome Completo:</span><span class="value" style="font-weight:bold">${val(data.nome_completo)}</span></div></div>
        <div class="row"><div class="col"><span class="label">Turma/Classe:</span><span class="value" style="font-weight:bold">${turma}</span></div><div class="col"><span class="label">Ano Lectivo:</span><span class="value">${anoLectivo}</span></div></div>
        <div class="row"><div class="col"><span class="label">Data Nasc:</span><span class="value">${val(data.data_nascimento)}</span></div><div class="col"><span class="label">Género:</span><span class="value">${val(data.genero)}</span></div><div class="col"><span class="label">Nacionalidade:</span><span class="value">${val(data.nacionalidade)}</span></div></div>
        <div class="row"><div class="col"><span class="label">Documento:</span><span class="value">${val(data.documento_nr)}</span></div><div class="col"><span class="label">Bairro:</span><span class="value">${val(data.bairro)}</span></div></div>
    </div>
    <div class="section">
        <span class="sec-title">2. DADOS DO PAI</span>
        <div class="row"><div class="col" style="flex:2"><span class="label">Nome:</span><span class="value">${val(data.nome_pai)}</span></div></div>
        <div class="row"><div class="col"><span class="label">Profissão:</span><span class="value">${val(data.profissao_pai)}</span></div><div class="col"><span class="label">Telemóvel 1:</span><span class="value">${val(data.telemovel_pai)}</span></div><div class="col"><span class="label">Telemóvel 2:</span><span class="value">${val(data.telemovel_pai_2)}</span></div><div class="col"><span class="label">E-mail:</span><span class="value">${val(data.email_pai)}</span></div></div>
    </div>
    <div class="section">
        <span class="sec-title">3. DADOS DA MÃE</span>
        <div class="row"><div class="col" style="flex:2"><span class="label">Nome:</span><span class="value">${val(data.nome_mae)}</span></div></div>
        <div class="row"><div class="col"><span class="label">Profissão:</span><span class="value">${val(data.profissao_mae)}</span></div><div class="col"><span class="label">Telemóvel 1:</span><span class="value">${val(data.telemovel_mae)}</span></div><div class="col"><span class="label">Telemóvel 2:</span><span class="value">${val(data.telemovel_mae_2)}</span></div><div class="col"><span class="label">E-mail:</span><span class="value">${val(data.email_mae)}</span></div></div>
    </div>
    <div style="background:#f0f0f0;padding:10px;font-size:10pt;text-align:justify;border-left:5px solid #000;margin-top:20px">
        <strong>DECLARAÇÃO:</strong> Comprometo-me a honrar com todas as minhas obrigações inerentes ao processo de ensino e aprendizagem do meu educando, cumprindo com os prazos estabelecidos e zelando pelo bom aproveitamento escolar.
    </div>
    <div style="margin-top:50px;display:flex;justify-content:space-between;text-align:center;padding:0 30px">
        <div style="width:40%;border-top:1px solid #000;padding-top:5px">O Encarregado(a) de Educação</div>
        <div style="width:40%;border-top:1px solid #000;padding-top:5px">A Secretaria</div>
    </div>
    <div style="text-align:center;font-size:8pt;margin-top:40px;color:#666">Processado em ${new Date().toLocaleString('pt-MZ')}</div>
    </body></html>`;
    var w = window.open('','_blank','height=800,width=900');
    w.document.write(html);
    w.document.close();
    w.onload = function(){ setTimeout(function(){w.focus();w.print();},500); };
}

// ========================================
// IMPRESSÃO DECLARAÇÃO (PRESERVADO)
// ========================================
function imprimirDeclaracao(data) {
    if (!window.sigeAlunosCanDocuments) { sigeAlunoAlert('O seu perfil não tem permissão para emitir documentos.', 'Permissão negada', 'warning'); return; }
    var escolaNome = sigeGlobal.nome_escola || 'ESCOLA GERAL';
    var logoUrl = sigeGlobal.logo_url;
    var anoLectivo = sigeGlobal.ano_lectivo || 2026;
    var turma = (data.classe && data.turma_nome) ? `${data.classe} - ${data.turma_nome}` : 'N/A';
    var hoje = new Date().toLocaleDateString('pt-MZ', { day:'2-digit', month:'long', year:'numeric' });
    var html = `<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Declaração_${data.numero_processo}</title>
    <style>
        body{font-family:'Times New Roman';padding:40px 60px;color:#000;line-height:1.8;}
        .header{text-align:center;border-bottom:3px solid #000;margin-bottom:30px;padding-bottom:15px}
        .header img{height:80px;display:block;margin:0 auto 10px}
        .header h3{margin:2px;font-size:11pt;font-weight:bold;text-transform:uppercase}
        .header h2{margin:5px 0;font-size:16pt;font-weight:900;text-transform:uppercase}
        .titulo{text-align:center;font-size:16pt;font-weight:900;text-transform:uppercase;margin:30px 0;text-decoration:underline}
        .corpo{font-size:12pt;text-align:justify}
        .assinatura{margin-top:60px;text-align:center}
        .assinatura .linha{border-top:1px solid #000;width:250px;margin:40px auto 5px}
        @media print{@page{margin:2cm}}
    </style></head><body>
    <div class="header">
        <img src="${logoUrl}">
        <h3>República de Moçambique</h3>
        <h3>Ministério da Educação e Desenvolvimento Humano</h3>
        <h2>${escolaNome}</h2>
    </div>
    <div class="titulo">Declaração de Matrícula</div>
    <div class="corpo">
        <p>Para os devidos efeitos, declara-se que <strong>${data.nome_completo || 'N/A'}</strong>, portador(a) do documento de identificação nº <strong>${data.documento_nr || 'N/A'}</strong>, nascido(a) em <strong>${data.data_nascimento || 'N/A'}</strong>, é aluno(a) regularmente matriculado(a) neste estabelecimento de ensino.</p>
        <p><strong>Turma:</strong> ${turma}</p>
        <p><strong>Ano Lectivo:</strong> ${anoLectivo}</p>
        <p><strong>Processo nº:</strong> ${data.numero_processo || 'N/A'}</p>
        <p style="margin-top:20px">Por ser verdade e me ter sido solicitado, mandei passar a presente declaração que vai por mim assinada e autenticada com carimbo a óleo em uso nesta instituição.</p>
    </div>
    <p style="text-align:right;margin-top:40px">Maputo, ${hoje}</p>
    <div class="assinatura">
        <div class="linha"></div>
        <p style="font-weight:bold">O Director</p>
    </div>
    <div style="text-align:center;font-size:8pt;margin-top:60px;color:#666">Processado pelo SIGE SoftGenial</div>
    </body></html>`;
    var w = window.open('','_blank','height=800,width=700');
    w.document.write(html);
    w.document.close();
    w.onload = function(){ setTimeout(function(){w.focus();w.print();},500); };
}

// ========================================
// IMPRESSÃO CARTÕES (NOVO DESIGN VERTICAL - CORRIGIDO)
// ========================================
// --- Construtor UNICO do crachá do estudante (partilhado por lote e individual).
// v12.31.0: elimina a duplicacao entre printBatchCards e printSingleCard. Todos os
// campos dinamicos sao escapados; o QR cai para o numero de processo legivel quando
// nao pode ser gerado; a impressao espera o carregamento das imagens.
// Contexto de render do crachá: dados da escola + modelo escolhido pela escola
// (template/cor/redes) vindos de sigeGlobal.cracha. O mesmo contexto alimenta a
// pré-visualização e a impressão, garantindo consistência.
// Nonce de CSP VIVO: lido directamente de uma tag já aceite pela política, o que
// garante correspondência exacta com a CSP em vigor (a propriedade .nonce mantém-se
// acessível por JS mesmo depois de o atributo ser ocultado pelo browser).
function sigeLiveNonce() {
    try {
        var e = document.querySelector('script[nonce]') || document.querySelector('style[nonce]');
        if (e && e.nonce) { return e.nonce; }
    } catch (x) {}
    return (sigeGlobal && sigeGlobal.csp_nonce) ? sigeGlobal.csp_nonce : '';
}

// Rede de segurança: garante que o registo de modelos está disponível. Se o
// enqueue não chegou (timing/cache), carrega o asset sob demanda antes de
// pré-visualizar/imprimir. cb(true|false).
function sigeEnsureCrachaTemplates(cb) {
    if (window.SigeCrachaTemplates) { cb(true); return; }
    var url = (sigeGlobal && sigeGlobal.cracha_asset) ? sigeGlobal.cracha_asset : '';
    if (!url) { cb(false); return; }
    if (window.__sigeCrachaLoading) {
        var iv = setInterval(function () { if (window.SigeCrachaTemplates) { clearInterval(iv); cb(true); } }, 80);
        setTimeout(function () { clearInterval(iv); cb(!!window.SigeCrachaTemplates); }, 4000);
        return;
    }
    window.__sigeCrachaLoading = true;
    var s = document.createElement('script');
    s.src = url;
    var n = sigeLiveNonce(); if (n) { s.setAttribute('nonce', n); }
    s.onload = function () { cb(!!window.SigeCrachaTemplates); };
    s.onerror = function () { window.__sigeCrachaLoading = false; cb(false); };
    document.head.appendChild(s);
}

function sigeCardCtx() {
    var cr = (sigeGlobal && sigeGlobal.cracha && sigeGlobal.cracha.config) ? sigeGlobal.cracha.config : {};
    return {
        escolaNome: (sigeGlobal && sigeGlobal.nome_escola) ? sigeGlobal.nome_escola : 'ESCOLA GERAL',
        logoUrl: (sigeGlobal && sigeGlobal.logo_url) ? sigeGlobal.logo_url : '<?php echo esc_url(SIGE_URL . 'assets/img/avatar-default.svg'); ?>',
        anoLectivo: (sigeGlobal && sigeGlobal.ano_lectivo) ? sigeGlobal.ano_lectivo : '2026',
        template: cr.template || 'aurora',
        accent: cr.accent || '#7c3aed',
        showSocial: !!cr.show_social,
        social: cr.social || {},
        nonce: sigeLiveNonce()
    };
}

// Documento de impressão: delega no registo de modelos (fonte de verdade única,
// partilhada com a pré-visualização). Se o registo não tiver carregado, recorre a
// um cartão simples e funcional (modo degradado), para nunca falhar a impressão.
function sigeCardsDocument(students, ctx, batch) {
    ctx = ctx || sigeCardCtx();
    ctx.batch = !!batch;
    var list = Array.isArray(students) ? students : [students];
    if (window.SigeCrachaTemplates && typeof window.SigeCrachaTemplates.buildDocument === 'function') {
        return window.SigeCrachaTemplates.buildDocument(list, ctx);
    }
    return sigeCardsDocumentFallback(list, ctx);
}

function sigeCardsDocumentFallback(list, ctx) {
    var esc = sigeAlunoEscapeHtml;
    var n = ctx && ctx.nonce ? ' nonce="' + esc(ctx.nonce) + '"' : '';
    var body = '';
    for (var i = 0; i < list.length; i++) {
        var a = list[i] || {};
        var proc = a.numero_processo ? String(a.numero_processo) : '';
        var qr = sigeQrDataUri('Aluno:' + proc);
        var qrCell = qr ? '<img src="' + qr + '" class="qr" alt="">' : '<span class="qrf">' + esc(proc) + '</span>';
        body += '<div class="c"><div class="hd">' + esc(ctx.escolaNome) + '</div>'
            + '<div class="nm">' + esc(a.nome_completo || '') + '</div>'
            + '<div class="mt">Estudante &middot; Proc: ' + esc(proc) + '</div>'
            + '<div class="ft">' + qrCell + '</div></div>';
    }
    return '<!doctype html><html><head><meta charset="utf-8"><title>Cartão</title><style' + n + '>'
        + 'body{font-family:Segoe UI,Arial,sans-serif;margin:0;padding:12px;display:flex;flex-wrap:wrap;gap:12px;color:#475569}'
        + '.c{width:220px;height:350px;border:1px solid #cbd5e1;border-radius:12px;padding:16px;box-sizing:border-box;display:flex;flex-direction:column;align-items:center;text-align:center}'
        + '.hd{font-size:9px;font-weight:700;text-transform:uppercase}.nm{margin-top:80px;font-size:14px;font-weight:800}'
        + '.mt{margin-top:8px;font-size:11px}.ft{margin-top:auto}.qr{width:48px;height:48px}.qrf{font-family:monospace;font-weight:700}'
        + '</style></head><body>' + body + '</body></html>';
}

// Escreve no popup e imprime SO depois de as imagens carregarem (com salvaguarda
// de tempo). Trata o caso de o popup ter sido bloqueado pelo navegador.
function sigePrintCardsWindow(w, html) {
    if (!w) {
        sigeAlunoAlert('O navegador bloqueou a janela de impressão. Permita pop-ups para este site e tente novamente.', 'Pop-up bloqueado', 'warning');
        return;
    }
    try {
        w.document.open();
        w.document.write(html);
        w.document.close();
    } catch (e) {
        sigeAlunoAlert('Não foi possível preparar a impressão. Tente novamente.', 'Erro de impressão', 'error');
        return;
    }
    var printed = false;
    function go() { if (printed) return; printed = true; try { w.focus(); w.print(); } catch (e) {} }
    var imgs = [];
    try { imgs = Array.prototype.slice.call(w.document.images || []); } catch (e) {}
    var pending = imgs.filter(function (im) { return !im.complete; });
    if (pending.length === 0) { setTimeout(go, 200); return; }
    var left = pending.length;
    pending.forEach(function (im) {
        im.addEventListener('load', function () { if (--left <= 0) go(); });
        im.addEventListener('error', function () { if (--left <= 0) go(); });
    });
    setTimeout(go, 6000); // salvaguarda: nunca deixar a impressão pendurada
}

async function printBatchCards() {
    if (!window.sigeAlunosCanDocuments) { sigeAlunoAlert('O seu perfil não tem permissão para imprimir cartões.', 'Permissão negada', 'warning'); return; }
    // [12.9.8/v12.11.9.69] fetch on-demand. Os filtros aplicam-se ao conjunto completo com payload mínimo de cartões.
    var sigeTodosAlunos = await sigeGetTodosAlunos('cards');
    var visibleStudents = sigeAlunosFiltrarPorFiltrosActuais(sigeTodosAlunos);

    if (visibleStudents.length === 0) { sigeAlunoAlert('Nenhum aluno encontrado para impressão com os filtros actuais.', 'Sem alunos para imprimir', 'warning'); return; }
    if (!(await sigeAlunoConfirmAsync('Vai imprimir ' + visibleStudents.length + ' cartões.\n\nRecomenda-se seleccionar uma turma de cada vez para evitar impressão desnecessária.', 'Imprimir cartões de aluno', 'Imprimir cartões', 'warning'))) return;

    var w = window.open('', '', 'width=900,height=800');
    if (!w) { sigeAlunoAlert('O navegador bloqueou a janela de impressão. Permita pop-ups e tente novamente.', 'Pop-up bloqueado', 'warning'); return; }
    try { w.document.write('<!doctype html><meta charset="utf-8"><title>A preparar…</title><body style="font:14px sans-serif;padding:20px">A preparar os crachás…</body>'); } catch (e) {}
    sigeEnsureCrachaTemplates(function () {
        sigePrintCardsWindow(w, sigeCardsDocument(visibleStudents, sigeCardCtx(), true));
    });
}

function printSingleCard(a) {
    if (!window.sigeAlunosCanDocuments) { sigeAlunoAlert('O seu perfil não tem permissão para imprimir cartões.', 'Permissão negada', 'warning'); return; }
    var w = window.open('', '', 'width=350,height=500');
    if (!w) { sigeAlunoAlert('O navegador bloqueou a janela de impressão. Permita pop-ups e tente novamente.', 'Pop-up bloqueado', 'warning'); return; }
    try { w.document.write('<!doctype html><meta charset="utf-8"><title>A preparar…</title><body style="font:14px sans-serif;padding:20px">A preparar o crachá…</body>'); } catch (e) {}
    sigeEnsureCrachaTemplates(function () {
        sigePrintCardsWindow(w, sigeCardsDocument([a || {}], sigeCardCtx(), false));
    });
}

// ========================================
// MODELO DE CRACHÁ DA ESCOLA (seletor + pré-visualização ao vivo + gravação)
// Pré-visualização e impressão usam o MESMO registo de modelos (SigeCrachaTemplates),
// por isso o que a escola escolhe é exactamente o que sai impresso.
// ========================================
(function () {
    var SAMPLE = { nome_completo: 'Maria João Sitoe', numero_processo: '2026-0001', classe: '10ª', turma_nome: 'A', foto: '' };
    function cfg() { return (sigeGlobal && sigeGlobal.cracha) ? sigeGlobal.cracha : { config: {}, templates: {}, social: [] }; }
    function el(id) { return document.getElementById(id); }

    var modal = null, frame = null, estado = null, inited = false;

    function ctxAtual() {
        return {
            escolaNome: (sigeGlobal && sigeGlobal.nome_escola) ? sigeGlobal.nome_escola : 'ESCOLA GERAL',
            logoUrl: (sigeGlobal && sigeGlobal.logo_url) ? sigeGlobal.logo_url : '',
            anoLectivo: (sigeGlobal && sigeGlobal.ano_lectivo) ? sigeGlobal.ano_lectivo : '2026',
            template: estado.template, accent: estado.accent, showSocial: estado.show_social,
            social: estado.social, batch: false,
            nonce: (typeof sigeLiveNonce === 'function') ? sigeLiveNonce() : ((sigeGlobal && sigeGlobal.csp_nonce) || '')
        };
    }
    function escreverNaFrame(doc) {
        if (!frame) { return; }
        // contentDocument.write é mais fiável que srcdoc sob CSP estrita; srcdoc fica de recurso.
        try { var d = frame.contentWindow.document; d.open(); d.write(doc); d.close(); }
        catch (e) { try { frame.srcdoc = doc; } catch (e2) {} }
    }
    function renderPreview() {
        if (!frame) { return; }
        sigeEnsureCrachaTemplates(function (ok) {
            if (!ok || !window.SigeCrachaTemplates) {
                escreverNaFrame('<!doctype html><meta charset="utf-8"><body style="font:13px sans-serif;color:slategray;display:flex;align-items:center;justify-content:center;height:100%;text-align:center;padding:16px">Pré-visualização indisponível. Verifique a ligação e tente reabrir.</body>');
                return;
            }
            var doc;
            try { doc = window.SigeCrachaTemplates.buildDocument([SAMPLE], ctxAtual()); }
            catch (e) { return; }
            escreverNaFrame(doc);
        });
    }
    function marcarTemplate() {
        Array.prototype.forEach.call(document.querySelectorAll('#sige-cracha-templates .sige-cracha-tpl'), function (n) {
            n.classList.toggle('is-active', n.getAttribute('data-tpl') === estado.template);
        });
    }
    function renderTemplateOptions() {
        var wrap = el('sige-cracha-templates'); if (!wrap) { return; }
        var metas = cfg().templates || {}, html = '';
        Object.keys(metas).forEach(function (id) {
            var m = metas[id] || {}, active = (id === estado.template) ? ' is-active' : '';
            html += '<div class="sige-cracha-tpl' + active + '" data-tpl="' + sigeAlunoEscapeHtml(id) + '" role="button" tabindex="0">'
                + '<div class="sige-cracha-tpl-nome">' + sigeAlunoEscapeHtml(m.nome || id) + '</div>'
                + '<div class="sige-cracha-tpl-desc">' + sigeAlunoEscapeHtml(m.descricao || '') + '</div></div>';
        });
        wrap.innerHTML = html;
        Array.prototype.forEach.call(wrap.querySelectorAll('.sige-cracha-tpl'), function (node) {
            function pick() { estado.template = node.getAttribute('data-tpl'); marcarTemplate(); renderPreview(); }
            node.addEventListener('click', pick);
            node.addEventListener('keydown', function (ev) { if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); pick(); } });
        });
    }
    function syncSocialVisibility() {
        var box = el('sige-cracha-social'); if (box) { box.classList.toggle('is-hidden', !estado.show_social); }
    }
    function msg(text, isError) {
        var m = el('sige-cracha-msg'); if (!m) { return; }
        m.textContent = text || ''; m.classList.toggle('is-error', !!isError);
    }
    function lerEstadoDoFormulario() {
        estado.show_social = el('sige-cracha-show-social').checked;
        estado.social = {
            instagram: el('sige-cracha-social-instagram').value || '',
            facebook: el('sige-cracha-social-facebook').value || '',
            website: el('sige-cracha-social-website').value || ''
        };
    }
    function init() {
        if (inited) { return; }
        modal = el('sige-cracha-modal'); frame = el('sige-cracha-preview-frame');
        if (!modal) { return; }
        inited = true;
        var c = cfg().config || {};
        estado = {
            template: c.template || 'aurora',
            accent: c.accent || '#7c3aed',
            show_social: !!c.show_social,
            social: {
                instagram: (c.social && c.social.instagram) || '',
                facebook: (c.social && c.social.facebook) || '',
                website: (c.social && c.social.website) || ''
            }
        };
        el('sige-cracha-accent').value = estado.accent;
        el('sige-cracha-accent-hex').value = estado.accent;
        el('sige-cracha-show-social').checked = estado.show_social;
        el('sige-cracha-social-instagram').value = estado.social.instagram;
        el('sige-cracha-social-facebook').value = estado.social.facebook;
        el('sige-cracha-social-website').value = estado.social.website;
        renderTemplateOptions(); syncSocialVisibility();

        el('sige-cracha-accent').addEventListener('input', function () { estado.accent = this.value; el('sige-cracha-accent-hex').value = this.value; renderPreview(); });
        el('sige-cracha-accent-hex').addEventListener('input', function () {
            var v = String(this.value || '').trim();
            if (/^#?[0-9a-fA-F]{6}$/.test(v)) { if (v[0] !== '#') { v = '#' + v; } estado.accent = v; el('sige-cracha-accent').value = v; renderPreview(); }
        });
        el('sige-cracha-show-social').addEventListener('change', function () { estado.show_social = this.checked; syncSocialVisibility(); renderPreview(); });
        ['instagram', 'facebook', 'website'].forEach(function (f) {
            el('sige-cracha-social-' + f).addEventListener('input', function () { estado.social[f] = this.value; renderPreview(); });
        });
    }
    window.abrirModeloCracha = function () {
        init();
        if (!modal) { sigeAlunoAlert('Seletor de modelo indisponível.', 'Modelo de crachá', 'error'); return; }
        msg('', false);
        modal.classList.add('is-open'); modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sige-modal-open');
        renderPreview();
    };
    window.fecharModeloCracha = function () {
        if (!modal) { return; }
        modal.classList.remove('is-open'); modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sige-modal-open');
    };
    window.guardarModeloCracha = function () {
        init(); if (!modal) { return; }
        lerEstadoDoFormulario();
        var btn = el('sige-cracha-save'); if (btn) { btn.setAttribute('disabled', 'disabled'); }
        msg('A guardar...', false);
        jQuery.post(ajaxurl, {
            action: 'sige_save_cracha_config',
            _sige_nonce: sigeAjax.nonce_alunos,
            template: estado.template,
            accent: estado.accent,
            show_social: estado.show_social ? '1' : '0',
            social_instagram: estado.social.instagram,
            social_facebook: estado.social.facebook,
            social_website: estado.social.website
        }).done(function (r) {
            if (r && r.success && r.data && r.data.config) {
                sigeGlobal.cracha.config = r.data.config; // a impressão passa já a usar o novo modelo
                estado.template = r.data.config.template; estado.accent = r.data.config.accent;
                estado.show_social = !!r.data.config.show_social; estado.social = r.data.config.social || estado.social;
                marcarTemplate(); renderPreview();
                msg('Modelo guardado.', false);
                // Mensagem de sucesso CLARA (toast) + fecha o modal a seguir.
                var nomeModelo = (cfg().templates && cfg().templates[estado.template] && cfg().templates[estado.template].nome) ? cfg().templates[estado.template].nome : estado.template;
                sigeAlunoAlert('O modelo "' + nomeModelo + '" foi guardado e passa a ser usado nos crachás de toda a escola.', 'Modelo de crachá guardado', 'success');
                setTimeout(function () { window.fecharModeloCracha(); }, 900);
            } else {
                var em = (r && r.data && (r.data.msg || r.data)) ? (r.data.msg || r.data) : 'Não foi possível guardar.';
                msg(em, true);
                sigeAlunoAlert(String(em), 'Não foi possível guardar', 'error');
            }
        }).fail(function () {
            msg('Falha de ligação ao guardar. Tente novamente.', true);
            sigeAlunoAlert('Falha de ligação ao guardar o modelo. Verifique a internet e tente novamente.', 'Erro de ligação', 'error');
        })
            .always(function () { if (btn) { btn.removeAttribute('disabled'); } });
    };
    document.addEventListener('keydown', function (ev) {
        if (ev.key === 'Escape' && modal && modal.classList.contains('is-open')) { window.fecharModeloCracha(); }
    });
})();

// ========================================
// REGIME CRECHE: auto-preencher mensalidade
// ========================================
var creche_precos = <?php echo json_encode($creche_precos); ?>;

function sigeCalcularMensalidadeCreche() {
    var regime = jQuery('input[name="regime_creche"]:checked').val();
    if (!regime) return;
    var nasc = jQuery('#data_nascimento').val();
    if (!nasc) return;
    var hoje = new Date();
    var nascDate = new Date(nasc);
    var idade = hoje.getFullYear() - nascDate.getFullYear();
    var m = hoje.getMonth() - nascDate.getMonth();
    if (m < 0 || (m === 0 && hoje.getDate() < nascDate.getDate())) idade--;
    var preco = 0;
    if (regime === 'semi_integral') {
        preco = (idade >= 5) ? creche_precos.semi_5anos : creche_precos.semi_ate4;
    } else if (regime === 'integral') {
        preco = (idade >= 5) ? creche_precos.int_5anos : creche_precos.int_ate4;
    }
    if (preco > 0) jQuery('#mensalidade_base').val(preco.toFixed(2));
}

jQuery(document).on('change', 'input[name="regime_creche"]', sigeCalcularMensalidadeCreche);
jQuery(document).on('change', '#data_nascimento', sigeCalcularMensalidadeCreche);

// ========================================
// UX EXTRAS
// ========================================
// ESC fecha o modal que estiver realmente aberto (antes fechava sempre o
// #modal-aluno, deixando o de importacao/360 visivel mas a cair atras da sidebar
// por perder a classe que eleva o conteudo).
jQuery(document).on('keydown', function(e){
    if (e.key !== 'Escape') return;
    if (jQuery('#modal-import-alunos').is(':visible')) { fecharImportarAlunos(); return; }
    if (jQuery('#modal-aluno-360').is(':visible')) { fecharFichaAluno360(); return; }
    if (jQuery('#modal-aluno').is(':visible')) { fecharModal(); return; }
});
// Clique fora (no fundo) fecha o respectivo modal - coerente nos tres.
jQuery('#modal-aluno').on('click', function(e){ if(e.target===this) fecharModal(); });
jQuery('#modal-aluno-360').on('click', function(e){ if(e.target===this) fecharFichaAluno360(); });
jQuery('#modal-import-alunos').on('click', function(e){ if(e.target===this) fecharImportarAlunos(); });
</script>


<script id="sige-alunos-actions-menu-v121097">
document.addEventListener('DOMContentLoaded', function(){
    var menus = Array.prototype.slice.call(document.querySelectorAll('.sige-alunos-page details.sige-card-actions'));
    if (!menus.length) return;
    menus.forEach(function(menu){
        menu.addEventListener('toggle', function(){
            if (menu.open) {
                menus.forEach(function(other){ if (other !== menu) other.open = false; });
            }
        });
    });
    document.addEventListener('click', function(ev){
        if (!ev.target.closest('.sige-card-actions')) {
            menus.forEach(function(menu){ menu.open = false; });
        }
    });
    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape') {
            menus.forEach(function(menu){ menu.open = false; });
        }
    });
});
</script>



<script id="sige-alunos-ux-responsivo-v1211952">
(function(window, document, $){
    'use strict';
    if (!$) return;

    var tabs = ['tab-dados','tab-encarregados','tab-saude','tab-docs'];
    var tabLabels = {
        'tab-dados': 'Dados gerais',
        'tab-encarregados': 'Encarregados',
        'tab-saude': 'Saúde e emergência',
        'tab-docs': 'Arquivo digital'
    };

    function isMobileUX(){
        return window.matchMedia && window.matchMedia('(max-width: 820px)').matches;
    }

    function tabIndex(tabId){
        var i = tabs.indexOf(tabId);
        return i >= 0 ? i : 0;
    }

    function ensureStepControls(){
        var $modal = $('#modal-aluno');
        if (!$modal.length) return;
        if (!$modal.find('.sige-mobile-stepbar').length) {
            $modal.find('.sige-tabs').first().before(
                '<div class="sige-mobile-stepbar" aria-live="polite">' +
                    '<div class="sige-mobile-stepbar-main">' +
                        '<span class="sige-mobile-stepbar-label">Passo 1 de 4</span>' +
                        '<strong class="sige-mobile-stepbar-title">Dados gerais</strong>' +
                    '</div>' +
                    '<div class="sige-mobile-stepbar-track" aria-hidden="true"><div class="sige-mobile-stepbar-fill"></div></div>' +
                '</div>'
            );
        }
        var $footer = $modal.find('.sige-modal-footer').first();
        if ($footer.length && !$footer.find('.sige-mobile-step-prev').length) {
            $footer.prepend('<button type="button" class="sige-mobile-step-btn sige-mobile-step-prev">Anterior</button><button type="button" class="sige-mobile-step-btn sige-mobile-step-next">Próximo</button>');
        }
    }

    function enhanceTabA11y(){
        var $modal = $('#modal-aluno');
        var $tabs = $modal.find('.sige-tabs');
        $tabs.attr('role','tablist').attr('aria-label','Secções da ficha do aluno');
        $modal.find('.sige-tab').each(function(i){
            var $tab = $(this);
            var tabId = $tab.attr('data-tab') || tabs[i] || '';
            if (!tabId) return;
            var id = 'sige-tab-control-' + tabId;
            $tab.attr({
                'role':'tab',
                'id': id,
                'aria-controls': tabId,
                'aria-selected': $tab.hasClass('active') ? 'true' : 'false',
                'tabindex': $tab.hasClass('active') ? '0' : '-1'
            });
            $('#' + tabId).attr({'role':'tabpanel','aria-labelledby':id});
        });
    }

    function activateTab(tabId, focusTab){
        if (tabs.indexOf(tabId) < 0) tabId = tabs[0];
        var $modal = $('#modal-aluno');
        $modal.find('.sige-tab').removeClass('active').attr({'aria-selected':'false','tabindex':'-1'});
        $modal.find('.sige-tab-content').removeClass('active');
        var $tab = $modal.find('.sige-tab[data-tab="' + tabId + '"]').first();
        var $panel = $('#' + tabId);
        $tab.addClass('active').attr({'aria-selected':'true','tabindex':'0'});
        $panel.addClass('active');
        updateStepUI();
        if (focusTab && $tab.length) $tab.trigger('focus');
        if (isMobileUX() && $panel.length) {
            var $body = $modal.find('.sige-modal-body').first();
            if ($body.length) $body.scrollTop(0);
        }
    }

    function updateStepUI(){
        ensureStepControls();
        enhanceTabA11y();
        var $modal = $('#modal-aluno');
        var active = $modal.find('.sige-tab.active').attr('data-tab') || tabs[0];
        var index = tabIndex(active);
        var total = tabs.length;
        $modal.find('.sige-mobile-stepbar-label').text('Passo ' + (index + 1) + ' de ' + total);
        $modal.find('.sige-mobile-stepbar-title').text(tabLabels[active] || 'Ficha do aluno');
        $modal.find('.sige-mobile-stepbar-fill').css('width', (((index + 1) / total) * 100) + '%');
        $modal.find('.sige-mobile-step-prev').prop('disabled', index === 0).attr('aria-disabled', index === 0 ? 'true' : 'false');
        $modal.find('.sige-mobile-step-next').prop('disabled', index === total - 1).attr('aria-disabled', index === total - 1 ? 'true' : 'false');
        $modal.toggleClass('sige-step-is-last', index === total - 1);
    }

    // Overrides controlados das funções legadas: mantém a API usada pelo módulo.
    // [v12.15.19] Aceita as duas convencoes de chamada sem ambiguidade:
    //  - dispatcher declarativo (data-sige-act="switchTab"): switchTab(elementoDoSeparador)
    //  - handler de evento legado: switchTab(event, 'tab-x')
    //  - chamada directa por id: switchTab('tab-x')
    // O bug anterior lia event.currentTarget/target num elemento (undefined), caindo
    // sempre em tabs[0]: clicar 'Encarregados'/'Saude' voltava a 'Dados gerais'.
    window.switchTab = function(arg, tabId){
        var source = null;
        if (arg && arg.nodeType === 1) {
            source = arg;
        } else if (arg && typeof arg.preventDefault === 'function') {
            arg.preventDefault();
            source = arg.currentTarget || arg.target || null;
        } else if (typeof arg === 'string' && !tabId) {
            tabId = arg;
        }
        var alvo = tabId || (source ? $(source).closest('.sige-tab').attr('data-tab') : null) || tabs[0];
        activateTab(alvo, false);
    };

    window.resetTabsToFirst = function(){
        activateTab(tabs[0], false);
    };

    function ensureMobileFilterToggle(){
        var $form = $('#form-filtros');
        if (!$form.length) return;
        if (!$form.find('.sige-mobile-filter-toggle').length) {
            var buttonHtml = '<button type="button" class="sige-mobile-filter-toggle" aria-expanded="false" aria-controls="form-filtros"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"/></svg><span>Filtros</span></button>';
            $form.find('.sige-search-box').first().after(buttonHtml);
        }
    }

    function toggleMobileFilters(forceOpen){
        var $form = $('#form-filtros');
        var open = (typeof forceOpen === 'boolean') ? forceOpen : !$form.hasClass('sige-mobile-filters-open');
        $form.toggleClass('sige-mobile-filters-open', open);
        $form.find('.sige-mobile-filter-toggle').attr('aria-expanded', open ? 'true' : 'false');
    }

    function initFicha360Accordions(){
        var $cards = $('#sige-aluno360-content .sige-360-card');
        if (!$cards.length) return;
        $cards.each(function(i){
            var $card = $(this);
            var $title = $card.children('h3').first();
            if (!$title.length) return;
            var titleId = 'sige-360-card-title-' + i;
            if (!$title.attr('id')) $title.attr('id', titleId);
            $title.attr({
                'role':'button',
                'tabindex':'0',
                'aria-expanded': (i <= 1 ? 'true' : 'false')
            });
            if (isMobileUX() && window.matchMedia('(max-width: 700px)').matches) {
                $card.toggleClass('is-collapsed', i > 1);
            } else {
                $card.removeClass('is-collapsed');
                $title.attr('aria-expanded','true');
            }
        });
    }

    window.sigeAlunosInitFicha360Accordions = initFicha360Accordions;

    if (typeof window.renderFichaAluno360 === 'function' && !window.renderFichaAluno360.__sigeUX52Wrapped) {
        var originalRenderFichaAluno360 = window.renderFichaAluno360;
        window.renderFichaAluno360 = function(data){
            originalRenderFichaAluno360(data);
            window.setTimeout(initFicha360Accordions, 0);
        };
        window.renderFichaAluno360.__sigeUX52Wrapped = true;
    }

    $(document).on('click', '.sige-mobile-filter-toggle', function(){
        toggleMobileFilters();
    });

    $(document).on('click', '.sige-mobile-step-prev', function(){
        var active = $('#modal-aluno .sige-tab.active').attr('data-tab') || tabs[0];
        var i = Math.max(0, tabIndex(active) - 1);
        activateTab(tabs[i], false);
    });

    $(document).on('click', '.sige-mobile-step-next', function(){
        var active = $('#modal-aluno .sige-tab.active').attr('data-tab') || tabs[0];
        var i = Math.min(tabs.length - 1, tabIndex(active) + 1);
        activateTab(tabs[i], false);
    });

    $(document).on('keydown', '#modal-aluno .sige-tab', function(ev){
        var active = $(this).attr('data-tab') || tabs[0];
        var i = tabIndex(active);
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            activateTab(active, false);
        } else if (ev.key === 'ArrowRight' || ev.key === 'ArrowDown') {
            ev.preventDefault();
            activateTab(tabs[Math.min(tabs.length - 1, i + 1)], true);
        } else if (ev.key === 'ArrowLeft' || ev.key === 'ArrowUp') {
            ev.preventDefault();
            activateTab(tabs[Math.max(0, i - 1)], true);
        } else if (ev.key === 'Home') {
            ev.preventDefault();
            activateTab(tabs[0], true);
        } else if (ev.key === 'End') {
            ev.preventDefault();
            activateTab(tabs[tabs.length - 1], true);
        }
    });

    $(document).on('click', '#sige-aluno360-content .sige-360-card > h3', function(){
        if (!window.matchMedia || !window.matchMedia('(max-width: 700px)').matches) return;
        var $card = $(this).closest('.sige-360-card');
        var collapsed = !$card.hasClass('is-collapsed');
        $card.toggleClass('is-collapsed', collapsed);
        $(this).attr('aria-expanded', collapsed ? 'false' : 'true');
    });

    $(document).on('keydown', '#sige-aluno360-content .sige-360-card > h3', function(ev){
        if (ev.key === 'Enter' || ev.key === ' ') {
            ev.preventDefault();
            $(this).trigger('click');
        }
    });

    $(document).on('click', '.sige-actions-menu .sige-btn-action', function(){
        $(this).closest('details.sige-card-actions').prop('open', false);
    });

    function applyMobileStatusChipFilter(mode) {
        var normalized = mode || 'todos';
        $('.sige-mobile-status-chip').removeClass('is-active').attr('aria-pressed','false');
        $('.sige-mobile-status-chip[data-sige-mobile-chip="' + normalized + '"]').addClass('is-active').attr('aria-pressed','true');
        $('.sige-aluno-card').each(function(){
            var $card = $(this);
            var status = String($card.attr('data-status') || '').toLowerCase();
            var hasDebt = String($card.attr('data-has-debt') || '0') === '1' || $card.find('.sige-finance-divida,.sige-finance-parcial').length > 0;
            var show = true;
            if (normalized === 'activos') show = (status === 'activo');
            if (normalized === 'inactivos') show = (status !== 'activo');
            if (normalized === 'devedores') show = hasDebt;
            $card.toggleClass('is-mobile-chip-hidden', !show);
        });
    }

    $(document).on('click', '.sige-mobile-status-chip', function(){
        if (window.__sigeAlunosUX68Active) return;
        applyMobileStatusChipFilter($(this).data('sige-mobile-chip') || 'todos');
    });

    $(window).on('resize', function(){
        updateStepUI();
        initFicha360Accordions();
    });

    $(document).ready(function(){
        ensureStepControls();
        enhanceTabA11y();
        updateStepUI();
        ensureMobileFilterToggle();
        $('.sige-mobile-status-chip').attr('aria-pressed','false');
        $('.sige-mobile-status-chip.is-active').attr('aria-pressed','true');
    });
})(window, document, window.jQuery);
</script>

<script id="sige-alunos-mobile-tablet-ux-v1211968">
(function(window, document, $){
    'use strict';
    if (!$) return;

    window.__sigeAlunosUX68Active = true;
    var activeQuickFilter = 'todos';

    function cardMatchesSearch($card) {
        var txt = String($('#filtro-texto').val() || '').toLowerCase().trim();
        if (!txt) return true;
        var haystack = String($card.attr('data-search') || $card.data('search') || '').toLowerCase();
        return haystack.indexOf(txt) !== -1;
    }

    function cardMatchesQuickFilter($card) {
        var status = String($card.attr('data-status') || '').toLowerCase();
        var hasDebt = String($card.attr('data-has-debt') || '0') === '1' || $card.find('.sige-finance-divida,.sige-finance-parcial').length > 0;
        if (activeQuickFilter === 'activos') return status === 'activo';
        if (activeQuickFilter === 'inactivos') return status !== 'activo';
        if (activeQuickFilter === 'devedores') return hasDebt;
        return true;
    }

    function updateVisibleCount() {
        var visible = 0;
        var total = 0;
        $('.sige-aluno-card').each(function(){
            total += 1;
            var $card = $(this);
            if (!$card.hasClass('is-search-hidden') && !$card.hasClass('is-mobile-chip-hidden') && $card.css('display') !== 'none') {
                visible += 1;
            }
        });
        var msg = total ? (visible + ' de ' + total + ' aluno(s) visíveis nesta página') : 'Nenhum aluno nesta página';
        $('.sige-alunos-mobile-live').text(msg);
        $('.sige-results-counter').attr('data-visible-local', visible);
    }

    function applyAllClientFilters() {
        $('.sige-aluno-card').each(function(){
            var $card = $(this);
            var searchOk = cardMatchesSearch($card);
            var quickOk = cardMatchesQuickFilter($card);
            $card.toggleClass('is-search-hidden', !searchOk);
            $card.toggleClass('is-mobile-chip-hidden', !quickOk);
            $card.toggle(searchOk && quickOk);
        });
        updateVisibleCount();
        return false;
    }

    window.sigeAlunosApplyAllClientFilters = applyAllClientFilters;
    window.sigeAlunosUpdateVisibleCount = updateVisibleCount;

    var legacyFiltrar = window.filtrarAlunosClient;
    window.filtrarAlunosClient = function(){
        // Substitui o fade isolado por classes determinísticas para combinar pesquisa + chips.
        return applyAllClientFilters();
    };
    window.filtrarAlunosClient.__sigeUX68Wrapped = true;
    window.filtrarAlunos = window.filtrarAlunosClient;

    var legacyLimpar = window.limparFiltros;
    window.limparFiltros = function(){
        $('#filtro-texto').val('');
        activeQuickFilter = 'todos';
        $('.sige-mobile-status-chip').removeClass('is-active').attr('aria-pressed','false');
        $('.sige-mobile-status-chip[data-sige-mobile-chip="todos"]').addClass('is-active').attr('aria-pressed','true');
        $('.sige-aluno-card').removeClass('is-search-hidden is-mobile-chip-hidden').show();
        updateVisibleCount();
        if (typeof legacyLimpar === 'function') {
            try { legacyLimpar.call(window); } catch(e) {}
        }
        return false;
    };

    function clampOpenActionMenu($details) {
        if (!$details || !$details.length || !$details.prop('open')) return;
        var $menu = $details.find('.sige-actions-menu').first();
        if (!$menu.length) return;
        $menu.css({'max-width':'100%'});
        var rect = $menu.get(0).getBoundingClientRect();
        var vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
        if (rect.right > vw - 10) {
            $menu.css({'right':'0','left':'auto'});
        }
    }

    $(document).on('click', '.sige-mobile-status-chip', function(){
        activeQuickFilter = String($(this).data('sige-mobile-chip') || 'todos');
        $('.sige-mobile-status-chip').removeClass('is-active').attr('aria-pressed','false');
        $(this).addClass('is-active').attr('aria-pressed','true');
        window.setTimeout(applyAllClientFilters, 0);
    });

    $(document).on('input', '#filtro-texto', function(){
        applyAllClientFilters();
    });

    $(document).on('toggle', 'details.sige-card-actions', function(){
        var $details = $(this);
        $details.find('> summary').attr('aria-expanded', $details.prop('open') ? 'true' : 'false');
        clampOpenActionMenu($details);
    });

    $(document).on('click', '.sige-mobile-filter-toggle', function(){
        window.setTimeout(function(){
            var $form = $('#form-filtros');
            var open = $form.hasClass('sige-mobile-filters-open');
            $('.sige-mobile-filter-toggle').attr('aria-label', open ? 'Ocultar filtros' : 'Mostrar filtros');
        }, 0);
    });

    $(document).on('keydown', function(ev){
        if (ev.key === 'Escape') {
            $('details.sige-card-actions[open]').prop('open', false);
        }
    });

    $(window).on('resize orientationchange', function(){
        window.setTimeout(function(){
            $('details.sige-card-actions[open]').each(function(){ clampOpenActionMenu($(this)); });
            updateVisibleCount();
        }, 80);
    });

    $(document).ready(function(){
        $('.sige-mobile-status-chip').each(function(){
            $(this).attr('aria-pressed', $(this).hasClass('is-active') ? 'true' : 'false');
        });
        updateVisibleCount();
        if (!document.querySelector('meta[name="viewport"]')) {
            // Não injecta viewport no admin; apenas marca a página para smoke/diagnóstico visual.
            document.body.classList.add('sige-alunos-ux68-ready');
        } else {
            document.body.classList.add('sige-alunos-ux68-ready');
        }
    });
})(window, document, window.jQuery);
</script>


<script id="sige-alunos-deep-audit-fix-v1211969">
(function(window, document, $){
    'use strict';
    if (!$) return;

    var activeBirthdayFilter = 'todos';
    var todayMonth = '<?php echo esc_js($hoje_mes); ?>';

    function birthdayOk($card) {
        if (activeBirthdayFilter === 'hoje') return String($card.data('hoje') || '0') === '1';
        if (activeBirthdayFilter === 'mes') return String($card.data('mes') || '') === String(todayMonth || '');
        return true;
    }

    function updateCount() {
        var visible = 0, total = 0;
        $('.sige-aluno-card').each(function(){
            total += 1;
            var $card = $(this);
            if (!$card.hasClass('is-search-hidden') && !$card.hasClass('is-mobile-chip-hidden') && !$card.hasClass('is-birthday-hidden') && $card.css('display') !== 'none') visible += 1;
        });
        $('.sige-alunos-mobile-live').text(total ? (visible + ' de ' + total + ' aluno(s) visíveis nesta página') : 'Nenhum aluno nesta página');
        $('.sige-results-counter').attr('data-visible-local', visible);
    }

    function applyBirthday(kind) {
        activeBirthdayFilter = kind || 'todos';
        $('.sige-aluno-card').each(function(){
            var $card = $(this), ok = birthdayOk($card);
            $card.toggleClass('is-birthday-hidden', !ok);
            $card.toggle(!$card.hasClass('is-search-hidden') && !$card.hasClass('is-mobile-chip-hidden') && ok);
        });
        updateCount();
        return false;
    }

    window.filtrarAniversariantesHoje = function(){ return applyBirthday('hoje'); };
    window.filtrarAniversariantesMes = function(){ return applyBirthday('mes'); };

    var legacyLimparV69 = window.limparFiltros;
    window.limparFiltros = function(){
        activeBirthdayFilter = 'todos';
        $('.sige-aluno-card').removeClass('is-birthday-hidden');
        if (typeof legacyLimparV69 === 'function') { try { legacyLimparV69.call(window); } catch(e) {} }
        $('#filtro-turma').val('0');
        $('.sige-aluno-card').not('.is-search-hidden,.is-mobile-chip-hidden').show();
        updateCount();
        return false;
    };

    $(document).on('toggle', 'details.sige-card-actions', function(){
        if (this.open) $('details.sige-card-actions').not(this).prop('open', false);
        $(this).find('> summary').attr('aria-expanded', this.open ? 'true' : 'false');
    });

    $(document).on('input click', '#filtro-texto, .sige-mobile-status-chip', function(){
        window.setTimeout(function(){
            if (activeBirthdayFilter !== 'todos') applyBirthday(activeBirthdayFilter);
            updateCount();
        }, 20);
    });

    $(document).ready(function(){
        document.body.classList.add('sige-alunos-v69-deep-audit-ready');
        updateCount();
    });
})(window, document, window.jQuery);
</script>

<script id="sige-alunos-bottom-more-menu-v1211970">
(function(window, document){
    'use strict';

    function each(nodes, cb) {
        Array.prototype.forEach.call(nodes || [], cb);
    }

    function setExpandedState(open) {
        each(document.querySelectorAll('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"], .sg-mobile-global-bottom-nav button[aria-controls="sige-sidebar"], #sige-hamburger'), function(btn){
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }

    function toggleSidebar(forceOpen, trigger) {
        var sidebar = document.getElementById('sige-sidebar');
        var overlay = document.getElementById('sige-overlay');
        if (!sidebar) return false;
        var open = (typeof forceOpen === 'boolean') ? forceOpen : !sidebar.classList.contains('open');
        sidebar.classList.toggle('open', open);
        sidebar.setAttribute('aria-hidden', open ? 'false' : 'true');
        if (overlay) overlay.classList.toggle('show', open);
        document.body.classList.toggle('sg-app-menu-open', open);
        setExpandedState(open);
        if (trigger) trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            window.setTimeout(function(){
                var first = sidebar.querySelector('.sige-menu-item[href], .sg-app-sidebar a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])');
                if (first && typeof first.focus === 'function') {
                    try { first.focus({preventScroll:true}); } catch(e) { first.focus(); }
                }
            }, 70);
        }
        return open;
    }

    window.sigeAlunosToggleMoreMenu = toggleSidebar;

    document.addEventListener('click', function(ev){
        var target = ev.target && ev.target.closest ? ev.target.closest('.sige-mobile-bottom-nav button[aria-controls="sige-sidebar"]') : null;
        if (!target) return;
        ev.preventDefault();
        toggleSidebar(undefined, target);
    }, false);

    document.addEventListener('click', function(ev){
        if (!ev.target || !ev.target.closest) return;
        if (ev.target.closest('#sige-overlay') || ev.target.closest('#sige-sidebar .sige-menu-item[href]')) {
            window.setTimeout(function(){
                var sidebar = document.getElementById('sige-sidebar');
                var open = !!(sidebar && sidebar.classList.contains('open'));
                setExpandedState(open);
            }, 0);
        }
    }, true);

    document.addEventListener('keydown', function(ev){
        if (ev.key === 'Escape') toggleSidebar(false);
    }, true);

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ document.body.classList.add('sige-alunos-more-menu-v70-ready'); });
    } else {
        document.body.classList.add('sige-alunos-more-menu-v70-ready');
    }
})(window, document);

function sigeAlunosImportacaoReload() {
    try { sessionStorage.setItem('sige_toast', JSON.stringify({type:'success', msg:'Lista de alunos importada com sucesso.'})); } catch(e) {}
    location.reload();
}
window.sigeAlunosImportacaoReload = sigeAlunosImportacaoReload;
</script>


