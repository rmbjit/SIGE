<?php
/**
 * SIGE SoftGenial - Central de Mensagens WhatsApp
 * Ficheiro: admin/whatsapp-central-view.php
 *
 * Mostra TODO o fluxo de mensagens WhatsApp: agendadas, prontas, enviadas,
 * falhadas, canceladas. Permite ver o texto integral, cancelar pendentes,
 * re-enviar falhadas e forçar processamento do cron.
 *
 * Acesso: ?page=sige-app&view=whatsapp_central
 *
 * @since 12.9.56
 */

if (!defined('ABSPATH')) exit;

// ── Permissão ─────────────────────────────────────────────────────────────
if (!function_exists('sige_wppc_user_can') || !sige_wppc_user_can()) {
    echo '<div class="notice notice-error" style="padding:14px;border-radius:8px;background:var(--color-danger-100);color:var(--color-danger-800);border-left:4px solid var(--color-danger-600);">
            <strong>Acesso negado.</strong> Este painel é exclusivo de perfis financeiros, secretariado e direcção.
          </div>';
    return;
}

global $wpdb;
$eid     = (int) sige_wppc_eid();
$tQ      = sige_wppc_table();
$tAlunos = $wpdb->prefix . 'sige_alunos';

$has_scheduled = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('scheduled_at');
$has_priority  = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('priority');
$has_risk      = function_exists('sige_wpp_queue_has_col') && sige_wpp_queue_has_col('risk_flags');

// ── Filtros (GET) ────────────────────────────────────────────────────────
$flt_status  = isset($_GET['st']) ? sanitize_text_field((string)$_GET['st']) : 'all';
$flt_tipo    = isset($_GET['tp']) ? sanitize_text_field((string)$_GET['tp']) : '';
$flt_busca   = isset($_GET['q'])  ? sanitize_text_field((string)$_GET['q'])  : '';
$flt_de      = isset($_GET['de']) ? sanitize_text_field((string)$_GET['de']) : '';
$flt_ate     = isset($_GET['ate'])? sanitize_text_field((string)$_GET['ate']): '';
$pag         = max(1, isset($_GET['p']) ? absint($_GET['p']) : 1);
$por_pag     = 25;
$offset      = ($pag - 1) * $por_pag;

$valid_status = ['all', 'agendada', 'pendente', 'enviado', 'falhou', 'cancelado', 'forcar_envio'];
if (!in_array($flt_status, $valid_status, true)) $flt_status = 'all';

// ── Estatísticas (KPIs) ──────────────────────────────────────────────────
$now_mysql = current_time('mysql');

$kpi_agendadas = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tQ}
     WHERE escola_id = %d
       AND status = 'pendente'
       " . ($has_scheduled ? "AND scheduled_at IS NOT NULL AND scheduled_at > %s" : "AND 1=0"),
    $has_scheduled ? [$eid, $now_mysql] : [$eid]
));

$kpi_prontas = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tQ}
     WHERE escola_id = %d
       AND status IN ('pendente','forcar_envio')
       " . ($has_scheduled ? "AND (scheduled_at IS NULL OR scheduled_at <= %s)" : ""),
    $has_scheduled ? [$eid, $now_mysql] : [$eid]
));

$kpi_enviadas_hoje = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tQ}
     WHERE escola_id = %d AND status = 'enviado' AND DATE(enviado_em) = %s",
    $eid, wp_date('Y-m-d')
));

$kpi_falhas_hoje = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tQ}
     WHERE escola_id = %d AND status = 'falhou' AND DATE(criado_em) = %s",
    $eid, wp_date('Y-m-d')
));

$kpi_total_24h = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tQ}
     WHERE escola_id = %d AND criado_em >= DATE_SUB(NOW(), INTERVAL 24 HOUR)",
    $eid
));

$tz_mz = new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
$quota_wpp = function_exists('sige_notify_daily_profile')
    ? sige_notify_daily_profile($eid)
    : [
        'limit' => 250,
        'sent' => $kpi_enviadas_hoje,
        'remaining' => max(0, 250 - $kpi_enviadas_hoje),
        'percent' => min(100, round(($kpi_enviadas_hoje / 250) * 100, 1)),
        'in_window' => true,
        'resume_at' => '',
        'reason' => 'ok',
    ];
$quota_percent = max(0, min(100, (float)($quota_wpp['percent'] ?? 0)));
$quota_remaining = (int)($quota_wpp['remaining'] ?? 0);
$quota_limit = (int)($quota_wpp['limit'] ?? 250);
$quota_sent = (int)($quota_wpp['sent'] ?? 0);
$quota_reason = (string)($quota_wpp['reason'] ?? 'ok');
$quota_resume = (string)($quota_wpp['resume_at'] ?? '');
$health_wpp = function_exists('sige_wpp_health_profile') ? sige_wpp_health_profile($eid) : null;
$health_mode = is_array($health_wpp) ? (string)($health_wpp['mode'] ?? 'normal') : 'normal';
$health_label = is_array($health_wpp) ? (string)($health_wpp['label'] ?? 'Saudável / Automático') : 'Saudável / Automático';
$health_reason = is_array($health_wpp) ? (string)($health_wpp['reason'] ?? '') : '';
$health_day = is_array($health_wpp) ? (int)($health_wpp['day'] ?? 1) : 1;
$health_failures = is_array($health_wpp) ? (int)($health_wpp['failures_6h'] ?? 0) : 0;
$health_critical = is_array($health_wpp) ? (int)($health_wpp['critical_72h'] ?? 0) : 0;
$health_forced = is_array($health_wpp) ? !empty($health_wpp['forced']) : false;
$health_resume = is_array($health_wpp) ? (string)($health_wpp['resume_at'] ?? '') : '';
$health_updated = isset($_GET['wpp_health_updated']);

$kpi_recibos_atrasados = 0;
if ($has_scheduled) {
    $stuck_cut = wp_date('Y-m-d H:i:s', current_time('timestamp') - (10 * MINUTE_IN_SECONDS), $tz_mz);
    $kpi_recibos_atrasados = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$tQ}
         WHERE escola_id=%d
           AND status='pendente'
           AND (tipo LIKE %s OR tipo LIKE %s OR tipo LIKE %s)
           AND (scheduled_at IS NULL OR scheduled_at <= %s)",
        $eid, '%recibo%', '%pagamento%', '%receipt%', $stuck_cut
    ));
}

// ── Listagem de tipos distintos para o dropdown de filtro ─────────────────
$tipos_dist = $wpdb->get_col($wpdb->prepare(
    "SELECT DISTINCT tipo FROM {$tQ} WHERE escola_id = %d AND tipo IS NOT NULL AND tipo <> '' ORDER BY tipo ASC LIMIT 80",
    $eid
));

// ── Construção da query principal com filtros ─────────────────────────────
$where  = ["q.escola_id = %d"];
$params = [$eid];

if ($flt_status === 'agendada') {
    if ($has_scheduled) {
        $where[]  = "q.status = 'pendente'";
        $where[]  = "q.scheduled_at IS NOT NULL";
        $where[]  = "q.scheduled_at > %s";
        $params[] = $now_mysql;
    } else {
        $where[] = "1 = 0"; // não há coluna → não há agendadas
    }
} elseif ($flt_status !== 'all') {
    $where[]  = "q.status = %s";
    $params[] = $flt_status;
}

if ($flt_tipo !== '') {
    $where[]  = "q.tipo = %s";
    $params[] = $flt_tipo;
}

if ($flt_busca !== '') {
    $like     = '%' . $wpdb->esc_like($flt_busca) . '%';
    $where[]  = "(a.nome_completo LIKE %s OR q.telefone LIKE %s OR q.id = %d)";
    $params[] = $like;
    $params[] = $like;
    $params[] = (int) $flt_busca;
}

if ($flt_de !== '') {
    $where[]  = "q.criado_em >= %s";
    $params[] = $flt_de . ' 00:00:00';
}
if ($flt_ate !== '') {
    $where[]  = "q.criado_em <= %s";
    $params[] = $flt_ate . ' 23:59:59';
}

$whereSql = implode(' AND ', $where);

// Total para paginação
$totalRows = (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tQ} q
     LEFT JOIN {$tAlunos} a ON a.id = q.aluno_id AND a.escola_id = q.escola_id
     WHERE {$whereSql}",
    $params
));
$totalPags = max(1, (int) ceil($totalRows / $por_pag));

// Página actual de dados
$cols_extra = '';
if ($has_scheduled) $cols_extra .= ', q.scheduled_at';
if ($has_priority)  $cols_extra .= ', q.priority';
if ($has_risk)      $cols_extra .= ', q.risk_flags';

$dados = $wpdb->get_results($wpdb->prepare(
    "SELECT q.id, q.aluno_id, q.tipo, q.telefone, q.status, q.tentativas,
            q.criado_em, q.enviado_em, q.erro,
            LEFT(q.mensagem, 140) AS msg_preview,
            CHAR_LENGTH(q.mensagem) AS msg_len,
            a.nome_completo AS aluno_nome
            {$cols_extra}
       FROM {$tQ} q
       LEFT JOIN {$tAlunos} a ON a.id = q.aluno_id AND a.escola_id = q.escola_id
      WHERE {$whereSql}
      ORDER BY q.id DESC
      LIMIT %d OFFSET %d",
    array_merge($params, [$por_pag, $offset])
));

// ── Estado do cron ────────────────────────────────────────────────────────
$next_queue  = wp_next_scheduled('sige_processar_whatsapp_queue');
$next_diario = wp_next_scheduled('sige_evento_diario');
$now_ts      = time();

$ajaxurl = admin_url('admin-ajax.php');
$nonce   = wp_create_nonce('sige_wppc_nonce');

// Helpers de UI
$status_color = [
    'pendente'      => ['var(--color-warning-600)', 'var(--color-warning-50)', 'Pendente'],
    'forcar_envio'  => ['var(--sg-theme-primary,#5a3fd6)', 'var(--sg-theme-soft,#f1edff)', 'Forçar envio'],
    'enviado'       => ['var(--color-success-800)', 'var(--color-success-50)', 'Enviada'],
    'falhou'        => ['var(--color-danger-700)', 'var(--color-danger-50)', 'Falhou'],
    'cancelado'     => ['var(--color-slate-800)', 'var(--color-slate-100)', 'Cancelada'],
];

$tipo_pretty = function ($t) {
    $map = [
        'lembrete'        => 'Lembrete',
        'cobranca_dia'    => 'Cobrança hoje',
        'cobranca_atraso' => 'Cobrança atraso',
        'fatura'          => 'Fatura',
        'recibo'          => 'Recibo',
        'recibo_diferido' => 'Recibo diferido',
        'conta_aluno'     => 'Conta aluno',
        'alerta_falta'    => 'Alerta falta',
        'alerta_nota'     => 'Alerta nota',
    ];
    return $map[$t] ?? esc_html((string)$t);
};


$wppc_icon = static function (string $name): string {
    if (function_exists('sige_ui_icon')) {
        return '<span class="wppc-svg">' . sige_ui_icon($name) . '</span>';
    }
    $map = [
        'message' => '<path d="M21 15a4 4 0 0 1-4 4H8l-5 3V7a4 4 0 0 1 4-4h10a4 4 0 0 1 4 4z"/>',
        'clock'   => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'send'    => '<path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/>',
        'check'   => '<path d="M20 6 9 17l-5-5"/>',
        'alert'   => '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
        'shield'  => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>',
        'calendar'=> '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
        'grid'    => '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>',
        'search'  => '<circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/>',
        'settings'=> '<path d="M4 21v-7"/><path d="M4 10V3"/><path d="M12 21v-9"/><path d="M12 8V3"/><path d="M20 21v-5"/><path d="M20 12V3"/><path d="M2 14h4"/><path d="M10 8h4"/><path d="M18 16h4"/>',
    ];
    $path = $map[$name] ?? $map['message'];
    return '<span class="wppc-svg"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg></span>';
};

?>
<style>
/* SIGE SoftGenial - Central WhatsApp: Compliance Visual Integral v12.10.59
   Referência inegociável: Painel Principal / Dashboard V2 MJS-grade.
   Intervenção visual apenas: sem alteração de fila, guardrails, cron, templates ou regras anti-bloqueio. */
.wppc-wrap{
    --wppc-accent:var(--sg-theme-primary,var(--color-brand-500));
    --wppc-accent-dark:var(--sg-theme-primary-800,var(--color-ink-700));
    --wppc-purple:var(--color-brand-500);
    --wppc-purple-soft:var(--color-brand-50);
    --wppc-green:var(--color-success-500);
    --wppc-green-soft:var(--color-success-100);
    --wppc-amber:var(--color-warning-500);
    --wppc-amber-soft:var(--color-warning-50);
    --wppc-red:var(--color-danger-500);
    --wppc-red-soft:var(--color-danger-50);
    --wppc-ink:var(--color-black);
    --wppc-muted:var(--color-slate-600);
    --wppc-line:var(--color-ink-100);
    --wppc-bg:var(--color-ink-50);
    --wppc-shadow:0 18px 45px rgba(34,34,64,.075);
    --wppc-shadow-lg:0 24px 70px rgba(45,36,96,.10);
    max-width:100%;
    margin:0;
    padding:0 0 28px;
    font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif);
    color:var(--wppc-ink);
}
.wppc-wrap *{box-sizing:border-box;}
.wppc-svg{display:inline-flex;align-items:center;justify-content:center;flex:0 0 auto;}
.wppc-svg svg,.wppc-wrap svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;display:block;}

/* HERO - padrão claro do Painel Principal */
.wppc-hero{
    position:relative;
    overflow:hidden;
    min-height:178px;
    border-radius:var(--radius-xl);
    background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-info-50) 100%);
    border:1px solid rgba(92,64,187,.12);
    box-shadow:var(--shadow-xs);
    padding:32px 34px;
    margin:0 0 22px;
    display:grid;
    grid-template-columns:minmax(0,1.05fr) minmax(300px,.95fr);
    gap:22px;
    align-items:center;
}
.wppc-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;border-radius:var(--radius-pill);background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.wppc-hero:after{display:none!important;content:none!important;}
.wppc-hero-main,.wppc-hero-panel{position:relative;z-index:1;}
.wppc-kicker{
    display:inline-flex;
    align-items:center;
    gap:var(--space-2);
    margin:0 0 10px;
    padding:0;
    border:0;
    border-radius:0;
    background:transparent;
    color:var(--wppc-accent);
    font-size:12px;
    line-height:1.2;
    font-weight:700;
    letter-spacing:.11em;
    text-transform:uppercase;
    box-shadow:none;
}
.wppc-kicker:before,.wppc-kicker:after{display:none!important;content:none!important;}
.wppc-title{
    margin:0;
    max-width:650px;
    color:var(--color-black);
    font-size:31px!important;
    line-height:1.08!important;
    font-weight:700!important;
    letter-spacing:-.04em!important;
    font-family:inherit;
}
.wppc-title strong,.wppc-title span{font:inherit;color:inherit;letter-spacing:inherit;}

.wppc-sub{
    max-width:650px;
    margin:var(--space-3) 0 0;
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:500;
}
.wppc-kicker{margin-bottom:10px!important;}

.wppc-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:24px;}
.wppc-hero-btn{min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:10px;border-radius:var(--radius-md);padding:0 22px;font-size:var(--fs-base);font-weight:700;text-decoration:none;border:1px solid transparent;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;background:var(--color-white);color:var(--color-slate-900);}
.wppc-hero-btn svg{width:18px;height:18px;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.wppc-hero-btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--sg-theme-primary-800,var(--color-ink-700)));color:var(--color-white);box-shadow:var(--shadow-md);}
.wppc-hero-btn-secondary{background:var(--color-white);color:var(--color-ink-900);border-color:var(--color-ink-100);box-shadow:var(--shadow-sm);}
.wppc-hero-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}
.wppc-hero-meta{display:flex;flex-wrap:wrap;gap:10px;margin-top:18px;}
.wppc-hero-pill{display:inline-flex;align-items:center;gap:var(--space-2);padding:var(--space-2) var(--space-3);border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-100);color:var(--color-slate-700);font-size:12px;font-weight:700;box-shadow:var(--shadow-sm);}
.wppc-hero-pill .wppc-svg{color:var(--wppc-purple);}
.wppc-hero-panel{
    min-height:148px;
    border-radius:var(--radius-xl);
    background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));
    padding:22px;
    overflow:hidden;
    display:flex;
    flex-direction:column;
    justify-content:center;
    gap:var(--space-3);
    border:1px solid rgba(92,64,187,.08);
}
.wppc-hero-panel:before{content:"";position:absolute;right:22px;bottom:16px;width:112px;height:92px;border-radius:22px 22px 12px 12px;background:rgba(109,93,252,.16);box-shadow:inset 0 0 0 2px rgba(109,93,252,.12);}
.wppc-hero-panel > *{position:relative;z-index:1;}
.wppc-panel-label{display:flex;align-items:center;gap:var(--space-2);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.11em;color:var(--wppc-purple);}
.wppc-panel-number{font-size:40px;line-height:1;font-weight:700;letter-spacing:-.045em;color:var(--color-ink-900);}
.wppc-panel-text{font-size:var(--fs-sm);color:var(--color-slate-600);line-height:1.55;margin:0;max-width:300px;}
.wppc-hero-track{height:10px;border-radius:var(--radius-pill);background:rgba(255,255,255,.7);overflow:hidden;}
.wppc-hero-fill{display:block;height:100%;border-radius:var(--radius-pill);background:linear-gradient(90deg,var(--color-brand-400),var(--color-brand-600));}

/* KPIs - padrão Painel Principal */
.wppc-kpis{display:grid;grid-template-columns:repeat(5,minmax(0,1fr));gap:var(--space-4);margin-bottom:18px;}
.wppc-kpi{
    position:relative;
    overflow:hidden;
    display:grid;
    grid-template-columns:auto minmax(0,1fr);
    align-items:center;
    gap:15px;
    min-height:104px;
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:18px 20px;
    box-shadow:var(--shadow-xs);
    transition:transform .18s ease,box-shadow .18s ease;
}
.wppc-kpi:hover{transform:translateY(-1px);box-shadow:var(--shadow-lg);}
.wppc-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--card-soft,var(--color-brand-50));}
.wppc-kpi-icon{position:relative;z-index:1;width:46px;height:46px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--icon-soft,var(--color-brand-50));color:var(--icon-color,var(--wppc-purple));box-shadow:var(--shadow-sm);}
.wppc-kpi .lbl,.wppc-kpi .num{position:relative;z-index:1;}
.wppc-kpi .lbl{font-size:12px;line-height:1.25;font-weight:700;color:var(--color-slate-600);margin-top:8px;text-transform:none;letter-spacing:0;}
.wppc-kpi .num{font-size:27px;line-height:1;font-weight:700;letter-spacing:-.03em;color:var(--color-ink-900);}
.wppc-kpi.k-agend{--card-soft:var(--color-brand-50);--icon-soft:var(--color-brand-50);--icon-color:var(--color-brand-500);}
.wppc-kpi.k-pron{--card-soft:var(--color-warning-50);--icon-soft:var(--color-warning-50);--icon-color:var(--color-warning-500);}
.wppc-kpi.k-env{--card-soft:var(--color-success-100);--icon-soft:var(--color-success-100);--icon-color:var(--color-success-500);}
.wppc-kpi.k-fail{--card-soft:var(--color-danger-50);--icon-soft:var(--color-danger-50);--icon-color:var(--color-danger-500);}
.wppc-kpi.k-24h{--card-soft:var(--color-info-50);--icon-soft:var(--color-info-50);--icon-color:var(--sg-theme-primary,var(--color-brand-500));}

/* Cards operacionais */
.wppc-card{
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    padding:20px 22px;
    box-shadow:var(--shadow-xs);
    margin-bottom:16px;
}
.wppc-card h2,.wppc-card h3{color:var(--color-ink-500);}
.wppc-quota{display:grid;grid-template-columns:minmax(0,1.5fr) .7fr .7fr .7fr;gap:var(--space-4);align-items:center;}
.wppc-quota-title{display:flex;align-items:center;gap:9px;font-size:15px;color:var(--color-ink-500);font-weight:700;margin-bottom:7px;letter-spacing:-.01em;}
.wppc-quota-sub{font-size:12.5px;color:var(--color-slate-600);line-height:1.55;font-weight:600;}
.wppc-quota-num{font-size:26px;font-weight:700;color:var(--color-ink-900);line-height:1;letter-spacing:-.03em;}
.wppc-quota-lbl{font-size:var(--fs-xs);color:var(--color-slate-600);font-weight:700;text-transform:uppercase;letter-spacing:.05em;margin-top:7px;}
.wppc-quota-track{width:100%;height:12px;border-radius:var(--radius-pill);background:var(--color-slate-100);overflow:hidden;margin-top:14px;}
.wppc-quota-fill{height:100%;border-radius:var(--radius-pill);background:linear-gradient(90deg,var(--color-brand-400),var(--color-brand-600));}
.wppc-alert{margin-top:11px;padding:11px 13px;border-radius:var(--radius-md);font-size:12.5px;font-weight:600;line-height:1.45;border:1px solid transparent;}
.wppc-alert.warn{background:var(--color-warning-50);color:var(--color-warning-800);border-color:var(--color-warning-300);}
.wppc-alert.danger{background:var(--color-danger-50);color:var(--color-danger-700);border-color:var(--color-danger-200);}
.wppc-alert.info{background:var(--sg-theme-soft,var(--color-brand-50));color:var(--color-info-600);border-color:var(--sg-theme-soft,var(--color-brand-50));}
.wppc-health{display:grid;grid-template-columns:minmax(0,1.2fr) minmax(260px,.8fr);gap:18px;align-items:start;}
.wppc-health-badge{display:inline-flex;align-items:center;padding:6px 11px;border-radius:var(--radius-pill);background:var(--color-success-100);color:var(--color-success-900);font-size:12px;font-weight:700;border:1px solid var(--color-success-200);}
.wppc-health select,.wppc-health input{width:100%;min-height:42px;padding:9px 12px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);background:var(--color-white);color:var(--color-ink-500);font-size:var(--fs-sm);font-family:inherit;box-shadow:var(--shadow-sm);}
.wppc-health button{min-height:42px;padding:0 var(--space-4);border:0;border-radius:var(--radius-md);background:linear-gradient(135deg,var(--wppc-accent),var(--wppc-accent-dark));color:var(--color-white);font-weight:700;cursor:pointer;font-family:inherit;box-shadow:var(--shadow-sm);}
.wppc-cron{display:flex;gap:14px;flex-wrap:wrap;align-items:center;}
.wppc-cron .item{font-size:var(--fs-sm);color:var(--color-slate-700);font-weight:600;}
.wppc-cron code{background:var(--color-slate-50);border:1px solid var(--color-slate-100);padding:3px 7px;border-radius:var(--radius-sm);font-size:12px;color:var(--color-ink-500);}
.wppc-cron .ok{color:var(--color-success-900);font-weight:700;}
.wppc-cron .bad{color:var(--color-danger-700);font-weight:700;}
.wppc-cron .late{color:var(--color-warning-800);font-weight:700;}

/* Tabs, filtros e bulk actions */
.wppc-tabs{display:flex;gap:var(--space-2);margin-bottom:16px;flex-wrap:wrap;}
.wppc-tab{display:inline-flex;align-items:center;gap:7px;min-height:40px;padding:0 15px;border-radius:var(--radius-md);background:var(--color-white);color:var(--color-slate-700);text-decoration:none;font-size:var(--fs-sm);font-weight:700;border:1px solid var(--color-slate-100);box-shadow:var(--shadow-sm);}
.wppc-tab:hover{border-color:var(--sg-theme-soft,var(--color-brand-50));color:var(--wppc-accent);}
.wppc-tab.active{background:var(--wppc-accent);color:var(--color-white);border-color:var(--wppc-accent);box-shadow:var(--shadow-sm);}
.wppc-filters{display:grid;grid-template-columns:2fr 1fr 1fr 1fr auto;gap:10px;align-items:end;}
.wppc-filters label{display:block;font-size:var(--fs-xs);color:var(--color-slate-600);text-transform:uppercase;font-weight:700;margin-bottom:6px;letter-spacing:.05em;}
.wppc-filters input,.wppc-filters select{width:100%;min-height:42px;padding:9px 12px;border:1px solid var(--color-ink-100);border-radius:var(--radius-md);background:var(--color-white);color:var(--color-ink-500);font-size:var(--fs-sm);font-family:inherit;box-shadow:var(--shadow-sm);}
.wppc-filters input:focus,.wppc-filters select:focus{outline:none;border-color:rgba(90,63,214,.55);box-shadow:var(--shadow-xs);}
.wppc-filters .btn,.wppc-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);min-height:40px;padding:0 15px;border:1px solid transparent;border-radius:var(--radius-md);background:linear-gradient(135deg,var(--wppc-accent),var(--wppc-accent-dark));color:var(--color-white)!important;font-size:12.5px;font-weight:700;text-decoration:none;cursor:pointer;font-family:inherit;box-shadow:var(--shadow-sm);transition:transform .18s ease,box-shadow .18s ease,filter .18s ease;
}
.wppc-btn:hover,.wppc-filters .btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);filter:none;}
.wppc-filters .btn-clear{background:var(--color-slate-50)!important;color:var(--color-slate-700)!important;border-color:var(--color-slate-100)!important;box-shadow:var(--shadow-sm);}
.wppc-btn-view{background:var(--sg-theme-soft,var(--color-brand-50))!important;color:var(--color-info-600)!important;border-color:var(--sg-theme-soft,var(--color-brand-50))!important;box-shadow:none!important;}
.wppc-btn-cancel{background:var(--color-danger-50)!important;color:var(--color-danger-700)!important;border-color:var(--color-danger-200)!important;box-shadow:none!important;}
.wppc-btn-retry{background:var(--color-success-50)!important;color:var(--color-success-900)!important;border-color:var(--color-success-200)!important;box-shadow:none!important;}
.wppc-btn-del{background:var(--color-slate-50)!important;color:var(--color-slate-700)!important;border-color:var(--color-slate-100)!important;box-shadow:none!important;}
.wppc-btn:disabled{opacity:.55;cursor:not-allowed;transform:none!important;box-shadow:none!important;}
.wppc-check{width:16px;height:16px;cursor:pointer;accent-color:var(--wppc-accent);}
.wppc-bulkbar{position:sticky;top:16px;z-index:50;background:var(--color-ink-900);color:var(--color-white);padding:var(--space-3) var(--space-4);border-radius:var(--radius-lg);box-shadow:var(--shadow-md);margin-bottom:16px;display:none;align-items:center;gap:var(--space-3);flex-wrap:wrap;}
.wppc-bulkbar.show{display:flex;}
.wppc-bulkbar .count{background:var(--color-white);color:var(--color-ink-900);padding:4px 10px;border-radius:var(--radius-pill);font-weight:700;font-size:var(--fs-sm);}
.wppc-bulkbar .lbl{font-size:var(--fs-sm);font-weight:700;}
.wppc-bulkbar .spacer{flex:1;}
.wppc-bulkbar button{min-height:36px;padding:0 var(--space-3);border-radius:var(--radius-md);font-size:12px;font-weight:700;cursor:pointer;border:1px solid rgba(255,255,255,.18);}
.wppc-bulk-cancel{background:var(--color-danger-500);color:var(--color-white);}
.wppc-bulk-retry{background:var(--color-success-600);color:var(--color-white);}
.wppc-bulk-delete{background:var(--color-slate-500);color:var(--color-white);}
.wppc-bulk-clear{background:transparent;color:var(--color-white);border:1px solid rgba(255,255,255,.35)!important;}

/* Pending links panel */
.wppc-pending-panel{
    margin-bottom:16px!important;
    background:var(--color-white)!important;
    border:1px solid rgba(28,32,54,.08)!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-xs);
    overflow:hidden;
}
.wppc-pending-panel summary{
    padding:18px 22px!important;
    color:var(--color-ink-500)!important;
    font-weight:700!important;
    border-bottom:1px solid var(--color-slate-100);
}
#wppc-pending-badge{background:var(--wppc-amber)!important;color:var(--color-white)!important;border-radius:var(--radius-pill)!important;font-weight:700!important;}
.wppc-pending-panel input{border-radius:var(--radius-md)!important;border:1px solid var(--color-ink-100)!important;min-height:42px!important;}

/* Tabela */
.wppc-table{width:100%;border-collapse:separate;border-spacing:0;background:var(--color-white);border-radius:var(--radius-xl);overflow:hidden;box-shadow:var(--shadow-xs);border:1px solid rgba(28,32,54,.08);}
.wppc-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;}
.wppc-table th,.wppc-table td{padding:13px 14px;text-align:left;font-size:var(--fs-sm);border-bottom:1px solid var(--color-info-50);vertical-align:top;color:var(--color-ink-800);}
.wppc-table th{background:var(--color-slate-50);color:var(--color-slate-800);font-weight:700;font-size:12px;text-transform:uppercase;letter-spacing:.04em;}
.wppc-table tr:hover td{background:var(--color-white);}
.wppc-badge{display:inline-flex;align-items:center;padding:6px 9px;border-radius:var(--radius-pill);font-size:var(--fs-xs);font-weight:700;white-space:nowrap;}
.wppc-msg-prev{color:var(--color-slate-700);font-size:12px;line-height:1.45;max-width:360px;}
.wppc-cell-actions{white-space:nowrap;text-align:right;}
.wppc-empty{padding:44px 20px!important;text-align:center;color:var(--color-slate-500);font-weight:600;}
.wppc-pag{margin-top:16px;text-align:center;}
.wppc-pag a,.wppc-pag span{display:inline-flex;align-items:center;justify-content:center;min-width:38px;min-height:38px;padding:9px 12px;margin:0 3px;border:1px solid var(--color-slate-100);border-radius:var(--radius-md);font-size:var(--fs-sm);text-decoration:none;color:var(--color-slate-700);background:var(--color-white);font-weight:700;}
.wppc-pag .current{background:var(--wppc-accent);border-color:var(--wppc-accent);color:var(--color-white);}
.wppc-pag a:hover{border-color:var(--sg-theme-soft,var(--color-brand-50));color:var(--wppc-accent);}

/* Modal */
.wppc-modal-bg{position:fixed;inset:0;background:rgba(15,23,42,.55);display:none;z-index:99999;align-items:center;justify-content:center;}
.wppc-modal-bg.show{display:flex;}
.wppc-modal{background:var(--color-white);border-radius:var(--radius-xl);max-width:720px;width:92%;max-height:90vh;overflow:auto;box-shadow:var(--shadow-lg);border:1px solid rgba(255,255,255,.55);}
.wppc-modal-h{padding:18px 22px;border-bottom:1px solid var(--color-slate-100);display:flex;justify-content:space-between;align-items:center;background:var(--color-white);}
.wppc-modal-h h3{margin:0;color:var(--color-ink-500);font-size:17px;font-weight:700;}
.wppc-modal-x{background:var(--color-slate-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-md);min-height:36px;padding:0 var(--space-3);cursor:pointer;font-weight:700;color:var(--color-slate-700);}
.wppc-modal-body{padding:20px 22px;}
.wppc-modal-meta{display:grid;grid-template-columns:1fr 1fr;gap:10px 18px;font-size:12px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px dashed var(--color-ink-100);}
.wppc-modal-meta .lbl{color:var(--color-slate-500);text-transform:uppercase;font-weight:400;font-size:10px;letter-spacing:.05em;}
.wppc-modal-meta .val{color:var(--color-black);font-weight:600;}
.wppc-modal-msg{background:var(--color-slate-50);border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);padding:var(--space-4);font-family:-apple-system,"Segoe UI",Roboto,sans-serif;font-size:13.5px;line-height:1.55;white-space:pre-wrap;word-wrap:break-word;max-height:50vh;overflow:auto;color:var(--color-black);}
.wppc-modal-err{margin-top:10px;padding:11px 13px;background:var(--color-danger-50);border:1px solid var(--color-danger-200);border-radius:var(--radius-md);color:var(--color-danger-700);font-size:12.5px;font-weight:600;}

@media(max-width:1200px){.wppc-kpis{grid-template-columns:repeat(3,minmax(0,1fr));}.wppc-quota{grid-template-columns:1fr 1fr;}}
@media(max-width:980px){.wppc-hero{grid-template-columns:1fr;padding:26px 24px;}.wppc-kpis{grid-template-columns:1fr 1fr;}.wppc-health{grid-template-columns:1fr;}.wppc-filters{grid-template-columns:1fr 1fr;}.wppc-table{min-width:980px;}.wppc-wrap{overflow-x:auto;}}
@media(max-width:640px){.wppc-title{font-size:var(--fs-xl)!important;}.wppc-kpis{grid-template-columns:1fr;}.wppc-quota{grid-template-columns:1fr;}.wppc-filters{grid-template-columns:1fr;}.wppc-filters .btn{width:100%;}.wppc-hero-actions{flex-direction:column;align-items:stretch;}.wppc-hero-btn{width:100%;}}
</style>

<div class="wrap wppc-wrap">
    <section class="wppc-hero" aria-label="Central de Mensagens WhatsApp">
        <div class="wppc-hero-main">
            <div class="wppc-kicker"><?php echo $wppc_icon('message'); ?><span>Comunicação</span></div>
            <h1 class="wppc-title">Operação WhatsApp</h1>
            <p class="wppc-sub">Acompanhe mensagens agendadas, pendentes, enviadas e falhadas com leitura clara, janela segura, limite diário e controlo operacional do número.</p>
            <div class="wppc-hero-actions" aria-label="Atalhos da central">
                <a href="#wppc-estado-fila" class="wppc-hero-btn wppc-hero-btn-primary"><?php echo $wppc_icon('send'); ?><span>Ver operação da fila</span></a>
                <a href="#wppc-estado-numero" class="wppc-hero-btn wppc-hero-btn-secondary"><?php echo $wppc_icon('shield'); ?><span>Ver saúde do número</span></a>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=config_center&sgcc_tab=comunicacao#comunicacao')); ?>" class="wppc-hero-btn wppc-hero-btn-secondary"><?php echo $wppc_icon('settings'); ?><span>Configurar WhatsApp</span></a>
            </div>
            <div class="wppc-hero-meta" aria-label="Regras activas">
                <span class="wppc-hero-pill"><?php echo $wppc_icon('shield'); ?><span>Guardrails activos</span></span>
                <span class="wppc-hero-pill"><?php echo $wppc_icon('clock'); ?><span>Janela Maputo 07:30-19:30</span></span>
                <span class="wppc-hero-pill"><?php echo $wppc_icon('grid'); ?><span>Fila controlada</span></span>
            </div>
        </div>
        <aside class="wppc-hero-panel" aria-label="Resumo da quota WhatsApp">
            <div class="wppc-panel-label"><?php echo $wppc_icon('shield'); ?><span>Quota diária</span></div>
            <div class="wppc-panel-number"><?php echo esc_html($quota_percent); ?>%</div>
            <p class="wppc-panel-text"><?php echo number_format($quota_sent, 0, ',', '.'); ?> de <?php echo number_format($quota_limit, 0, ',', '.'); ?> mensagem(ns) usadas hoje. Estado: <?php echo esc_html($health_label); ?>.</p>
            <div class="wppc-hero-track" aria-hidden="true"><span class="wppc-hero-fill" style="width:<?php echo esc_attr($quota_percent); ?>%;"></span></div>
        </aside>
    </section>

    <!-- KPIs -->
    <div class="wppc-kpis">
        <div class="wppc-kpi k-agend">
            <span class="wppc-kpi-icon"><?php echo $wppc_icon('calendar'); ?></span><div><div class="num"><?php echo number_format($kpi_agendadas, 0, ',', '.'); ?></div><div class="lbl">Agendadas (futuro)</div></div>
        </div>
        <div class="wppc-kpi k-pron">
            <span class="wppc-kpi-icon"><?php echo $wppc_icon('send'); ?></span><div><div class="num"><?php echo number_format($kpi_prontas, 0, ',', '.'); ?></div><div class="lbl">Prontas a sair</div></div>
        </div>
        <div class="wppc-kpi k-env">
            <span class="wppc-kpi-icon"><?php echo $wppc_icon('check'); ?></span><div><div class="num"><?php echo number_format($kpi_enviadas_hoje, 0, ',', '.'); ?></div><div class="lbl">Enviadas hoje</div></div>
        </div>
        <div class="wppc-kpi k-fail">
            <span class="wppc-kpi-icon"><?php echo $wppc_icon('alert'); ?></span><div><div class="num"><?php echo number_format($kpi_falhas_hoje, 0, ',', '.'); ?></div><div class="lbl">Falhas hoje</div></div>
        </div>
        <div class="wppc-kpi k-24h">
            <span class="wppc-kpi-icon"><?php echo $wppc_icon('clock'); ?></span><div><div class="num"><?php echo number_format($kpi_total_24h, 0, ',', '.'); ?></div><div class="lbl">Criadas (24h)</div></div>
        </div>
    </div>

    <!-- Limite diário e janela operacional -->
    <div id="wppc-estado-fila" class="wppc-card wppc-quota">
        <div>
            <div class="wppc-quota-title"><?php echo $wppc_icon('clock'); ?><span>Quota diária WhatsApp - janela segura 07:30 às 19:30 (Maputo)</span></div>
            <div class="wppc-quota-sub">
                O sistema envia recibos/pagamentos em cadência segura e só retoma mensagens fora da janela ou após limite no próximo período permitido.
            </div>
            <div class="wppc-quota-track" aria-label="Percentagem usada no dia">
                <div class="wppc-quota-fill" style="width:<?php echo esc_attr($quota_percent); ?>%;"></div>
            </div>
            <?php if ($quota_reason === 'daily_limit_reached'): ?>
                <div class="wppc-alert danger">Limite diário atingido. A fila retoma automaticamente em <?php echo esc_html($quota_resume ?: 'amanhã às 07:30'); ?>.</div>
            <?php elseif ($quota_reason === 'outside_window'): ?>
                <div class="wppc-alert warn">Fora do horário seguro. A fila retoma automaticamente em <?php echo esc_html($quota_resume ?: '07:30'); ?>.</div>
            <?php else: ?>
                <div class="wppc-alert info">Dentro das regras operacionais. Recibos sem link saem primeiro; links ficam sob envio manual.</div>
            <?php endif; ?>
            <?php if ($kpi_recibos_atrasados > 0): ?>
                <div class="wppc-alert warn">Há <?php echo number_format($kpi_recibos_atrasados, 0, ',', '.'); ?> recibo(s)/pagamento(s) pendente(s) atrasado(s). O botão <strong>Processar fila agora</strong> reprograma estes itens em cadência segura, respeitando os intervalos mínimos.</div>
            <?php endif; ?>
        </div>
        <div>
            <div class="wppc-quota-num"><?php echo number_format($quota_sent, 0, ',', '.'); ?></div>
            <div class="wppc-quota-lbl">Usadas hoje</div>
        </div>
        <div>
            <div class="wppc-quota-num"><?php echo number_format($quota_remaining, 0, ',', '.'); ?></div>
            <div class="wppc-quota-lbl">Restantes hoje</div>
        </div>
        <div>
            <div class="wppc-quota-num"><?php echo number_format($quota_limit, 0, ',', '.'); ?></div>
            <div class="wppc-quota-lbl">Limite actual</div>
        </div>
    </div>

    <!-- v12.9.76 - Health Mode PRO / DB Index Hotfix -->
    <div id="wppc-estado-numero" class="wppc-card wppc-health">
        <div>
            <div class="wppc-quota-title"><?php echo $wppc_icon('shield'); ?><span>Saúde do número WhatsApp</span></div>
            <div style="margin:6px 0 10px 0;"><span class="wppc-health-badge"><?php echo esc_html($health_label); ?></span></div>
            <div class="wppc-quota-sub">
                Dia do modo: <strong><?php echo (int)$health_day; ?></strong> · Falhas 6h: <strong><?php echo (int)$health_failures; ?></strong> · Sinais críticos 72h: <strong><?php echo (int)$health_critical; ?></strong><br>
                <?php echo esc_html($health_reason ?: 'Sem sinal crítico recente.'); ?>
            </div>
            <?php if ($health_mode === 'review'): ?>
                <div class="wppc-alert danger">Número em revisão/sinal crítico. O sistema mantém envios a 0 até validação humana.</div>
            <?php elseif ($health_mode === 'paused'): ?>
                <div class="wppc-alert danger">Envios pausados por protecção<?php echo $health_resume ? ' até ' . esc_html($health_resume) : ''; ?>. Se escolher Aquecimento ou Recuperação, o sistema limpa a pausa antiga e só volta a pausar perante novas falhas reais.</div>
            <?php elseif ($health_mode === 'recovery' || $health_mode === 'warmup'): ?>
                <div class="wppc-alert warn">Limite diário reduzido automaticamente para proteger a reputação do número.</div>
            <?php else: ?>
                <div class="wppc-alert info">O número está em modo saudável. Mesmo assim, continuam activos: janela Maputo, limite diário, intervalo global, bloqueio de links automáticos e opt-out.</div>
            <?php endif; ?>
            <?php if ($health_updated): ?>
                <div class="wppc-alert info">Estado WhatsApp actualizado com sucesso.</div>
            <?php endif; ?>
        </div>
        <div>
            <?php if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_admin')): ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" style="display:grid; gap:8px;">
                    <?php wp_nonce_field('sige_wpp_health_set'); ?>
                    <input type="hidden" name="action" value="sige_wpp_health_set">
                    <label style="font-size:11px;color:var(--color-slate-500);font-weight:800;text-transform:uppercase;">Modo de protecção</label>
                    <select name="mode">
                        <option value="normal" <?php selected($health_mode, 'normal'); ?>>Saudável / Automático</option>
                        <option value="warmup" <?php selected($health_mode, 'warmup'); ?>>Aquecimento</option>
                        <option value="recovery" <?php selected($health_mode, 'recovery'); ?>>Recuperação</option>
                        <option value="review" <?php selected($health_mode, 'review'); ?>>Em revisão</option>
                        <option value="paused" <?php selected($health_mode, 'paused'); ?>>Pausado por 2h</option>
                    </select>
                    <input type="text" name="reason" value="" placeholder="Motivo interno desta alteração">
                    <button type="submit" class="sgk-btn sgk-btn-primario sgk-btn-sm">Guardar modo seguro</button>
                    <div class="wppc-quota-sub">Aquecimento/Recuperação limpam a pausa e os contadores antigos. Falhas críticas novas continuam a forçar revisão/pausa.</div>
                </form>
            <?php else: ?>
                <div class="wppc-quota-sub">Este modo é gerido automaticamente pelo sistema e por administradores autorizados.</div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Cron status + Forçar processamento -->
    <div class="wppc-card">
        <div class="wppc-cron">
            <div class="item">
                <strong>Cron da fila</strong> <code>sige_processar_whatsapp_queue</code>:
                <?php if ($next_queue): $delta = $next_queue - $now_ts; ?>
                    <?php if ($delta < 0): ?>
                        <span class="late">atrasado <?php echo abs($delta); ?>s</span>
                    <?php else: ?>
                        <span class="ok">próx. em <?php echo $delta; ?>s</span>
                    <?php endif; ?>
                    (<?php echo esc_html(wp_date('d/m/Y H:i:s', $next_queue)); ?>)
                <?php else: ?>
                    <span class="bad">não agendado</span>
                <?php endif; ?>
            </div>
            <div class="item">
                <strong>Cron diário</strong> <code>sige_evento_diario</code>:
                <?php if ($next_diario): ?>
                    <span class="ok"><?php echo esc_html(wp_date('d/m/Y H:i', $next_diario)); ?></span>
                <?php else: ?>
                    <span class="bad">não agendado</span>
                <?php endif; ?>
            </div>
            <button type="button" id="wppc-force-cron" class="wppc-btn wppc-btn-primary">Processar fila agora</button>
            <span id="wppc-force-result" style="font-size:12px;color:var(--color-slate-500);"></span>

            <?php
            // [v12.9.62] Botão pull-forward - só aparece se houver mensagens agendadas para o futuro
            $pf_tbl = sige_wppc_table();
            $pf_count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$pf_tbl}
                  WHERE escola_id=%d
                    AND status IN ('pendente','forcar_envio')
                    AND scheduled_at > %s",
                sige_wppc_eid(), wp_date('Y-m-d 23:59:59')
            ));
            if ($pf_count > 0):
            ?>
            <div style="flex-basis:100%; margin-top:10px; padding-top:10px; border-top:1px dashed var(--color-ink-100); display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span style="font-size:12px; color:var(--sg-theme-primary-800,var(--color-ink-700)); background:var(--sg-theme-soft,var(--color-brand-50)); padding:4px 10px; border-radius:6px; font-weight:600;">
                    Spread-load
                </span>
                <span style="font-size:12px; color:var(--color-slate-700);">
                    <strong><?php echo (int)$pf_count; ?></strong> mensagem(ns) agendada(s) para amanhã ou depois - pode trazê-las para hoje, distribuídas pelas janelas humanas (manhã/tarde/fim).
                </span>
                <button type="button" id="wppc-pullforward-btn" class="wppc-btn" >Trazer <?php echo (int)$pf_count; ?> para hoje</button>
                <span id="wppc-pullforward-result" style="font-size:12px;color:var(--color-slate-500);"></span>
            </div>
            <?php endif; ?>

            <?php
            // [v12.9.61] Botão de backfill - só aparece se houver recibos pendentes sem pending_receipt registado
            $bf_count = function_exists('sige_wppc_count_recibos_sem_pending') ? sige_wppc_count_recibos_sem_pending() : 0;
            if ($bf_count > 0):
            ?>
            <div style="flex-basis:100%; margin-top:10px; padding-top:10px; border-top:1px dashed var(--color-ink-100); display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                <span style="font-size:12px; color:var(--color-warning-800); background:var(--color-warning-200); padding:4px 10px; border-radius:6px; font-weight:600;">
                    Auto-responder
                </span>
                <span style="font-size:12px; color:var(--color-slate-700);">
                    <strong><?php echo (int)$bf_count; ?></strong> recibo(s) antigos na fila ainda sem resposta automática preparada. Ficam disponíveis para envio manual do link quando o encarregado pedir.
                </span>
                <button type="button" id="wppc-backfill-btn" class="wppc-btn" >Preparar resposta automática para <?php echo (int)$bf_count; ?> recibo(s)</button>
                <span id="wppc-backfill-result" style="font-size:12px;color:var(--color-slate-500);"></span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="wppc-card" style="display:flex;gap:var(--space-3);align-items:center;justify-content:space-between;flex-wrap:wrap;">
      <span>Esta tabela mostra só o WhatsApp. Para ver e-mail e WhatsApp juntos, o histórico por encarregado e os modelos de texto, use a Central de Comunicações.</span>
      <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=comunicacoes_central')); ?>" class="wppc-hero-btn wppc-hero-btn-secondary">Abrir Central de Comunicações</a>
    </div>

    <!-- Tabs por status -->
    <?php
    $tab_url = function ($st) use ($flt_tipo, $flt_busca, $flt_de, $flt_ate) {
        return esc_url(add_query_arg([
            'page' => 'sige-app', 'view' => 'whatsapp_central',
            'st' => $st, 'tp' => $flt_tipo, 'q' => $flt_busca, 'de' => $flt_de, 'ate' => $flt_ate,
        ], admin_url('admin.php')));
    };
    ?>

    <!-- [v12.9.64] Painel: Pedidos de link de recibo pendentes (controlo manual) -->
    <details class="wppc-pending-panel" style="margin-bottom:14px; background:var(--color-warning-50); border:1px solid var(--color-warning-400); border-radius:10px;">
        <summary style="cursor:pointer; padding:14px 18px; font-weight:700; color:var(--color-warning-800); display:flex; align-items:center; gap:10px;">
            <span style="font-size:18px;"></span>
            <span>Pedidos de Link de Recibo</span>
            <span id="wppc-pending-badge" style="background:var(--color-warning-500); color:var(--color-white); padding:2px 10px; border-radius:999px; font-size:12px;">…</span>
            <span style="font-size:12px; font-weight:400; color:var(--color-warning-900); margin-left:8px;">- envio manual após pedido do encarregado</span>
        </summary>
        <div id="wppc-pending-body" style="padding:0 18px 18px 18px;">
            <div style="font-size:13px; color:var(--color-warning-900); margin-bottom:12px;">
                Lista de encarregados que receberam a confirmação de pagamento sem link e ainda têm o link do recibo <em>pendente</em>.
                Clique em <strong>Enviar link agora</strong> apenas quando o pai/encarregado pedir - o link sai com o mesmo formato conversacional ("Claro 😊 segue o link...") em 0-30 segundos.
            </div>
            <div style="display:flex; gap:10px; margin-bottom:12px; align-items:center; flex-wrap:wrap;">
                <input type="text" id="wppc-pending-search" placeholder="Pesquisar por aluno, encarregado, telefone ou recibo…" autocomplete="off" style="flex:1; min-width:280px; padding:8px 12px; border:1px solid var(--color-warning-400); border-radius:6px; font-size:13px; background:var(--color-white);">
                <span id="wppc-pending-counter" style="font-size:12px; color:var(--color-warning-900); font-weight:600;"></span>
                <button type="button" id="wppc-pending-clear-search" class="sgk-btn sgk-btn-ghost sgk-btn-sm" style="display:none;">✕ Limpar</button>
            </div>
            <div id="wppc-pending-list">
                <div style="color:var(--color-warning-900); font-style:italic;">A carregar pendentes...</div>
            </div>
        </div>
    </details>

    <div class="wppc-tabs">
        <a href="<?php echo $tab_url('all'); ?>"      class="wppc-tab <?php echo $flt_status==='all'?'active':''; ?>">Todas</a>
        <a href="<?php echo $tab_url('agendada'); ?>" class="wppc-tab <?php echo $flt_status==='agendada'?'active':''; ?>">Agendadas (<?php echo $kpi_agendadas; ?>)</a>
        <a href="<?php echo $tab_url('pendente'); ?>" class="wppc-tab <?php echo $flt_status==='pendente'?'active':''; ?>">Pendentes</a>
        <a href="<?php echo $tab_url('enviado'); ?>"  class="wppc-tab <?php echo $flt_status==='enviado'?'active':''; ?>">Enviadas</a>
        <a href="<?php echo $tab_url('falhou'); ?>"   class="wppc-tab <?php echo $flt_status==='falhou'?'active':''; ?>">Falhadas</a>
        <a href="<?php echo $tab_url('cancelado'); ?>" class="wppc-tab <?php echo $flt_status==='cancelado'?'active':''; ?>">Canceladas</a>
    </div>

    <!-- [v12.9.59] Barra de bulk actions - aparece quando há linhas seleccionadas -->
    <div class="wppc-bulkbar" id="wppc-bulkbar">
        <span class="count"><span id="wppc-bulk-count">0</span></span>
        <span class="lbl">mensagens seleccionadas</span>
        <div class="spacer"></div>
        <button type="button" class="wppc-bulk-cancel" id="wppc-bulk-cancel" data-op="cancel">Cancelar seleccionadas</button>
        <button type="button" class="wppc-bulk-retry" id="wppc-bulk-retry" data-op="retry">Re-enviar seleccionadas</button>
        <button type="button" class="wppc-bulk-delete" id="wppc-bulk-delete" data-op="delete">Remover seleccionadas</button>
        <button type="button" class="wppc-bulk-clear" id="wppc-bulk-clear">✕ Limpar selecção</button>
    </div>

    <!-- Filtros -->
    <form method="get" class="wppc-card">
        <input type="hidden" name="page" value="sige-app">
        <input type="hidden" name="view" value="whatsapp_central">
        <input type="hidden" name="st"   value="<?php echo esc_attr($flt_status); ?>">
        <div class="wppc-filters">
            <div>
                <label>Pesquisar (aluno, telefone, ID)</label>
                <input type="text" name="q" value="<?php echo esc_attr($flt_busca); ?>" placeholder="Ex: João Manuel, 84..., 12345">
            </div>
            <div>
                <label>Tipo</label>
                <select name="tp">
                    <option value="">- Todos -</option>
                    <?php foreach ($tipos_dist as $t): ?>
                        <option value="<?php echo esc_attr($t); ?>" <?php selected($flt_tipo, $t); ?>><?php echo esc_html($t); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>De (criado_em)</label>
                <input type="date" name="de" value="<?php echo esc_attr($flt_de); ?>">
            </div>
            <div>
                <label>Até</label>
                <input type="date" name="ate" value="<?php echo esc_attr($flt_ate); ?>">
            </div>
            <div>
                <button type="submit" class="btn">Filtrar</button>
                <?php if ($flt_busca || $flt_tipo || $flt_de || $flt_ate): ?>
                    <a href="<?php echo $tab_url($flt_status); ?>" class="btn btn-clear" style="text-decoration:none;display:inline-block;">Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <!-- Tabela -->
    <div class="wppc-table-wrap"><table class="wppc-table">
        <thead>
            <tr>
                <th style="width:36px;text-align:center;"><input type="checkbox" id="wppc-check-all" class="wppc-check" title="Seleccionar todas nesta página"></th>
                <th style="width:60px;">ID</th>
                <th style="width:90px;">Status</th>
                <th>Aluno</th>
                <th style="width:130px;">Tipo</th>
                <th style="width:130px;">Telefone</th>
                <th>Pré-visualização da mensagem</th>
                <th style="width:130px;">Quando</th>
                <th style="width:60px;">Tent.</th>
                <th style="width:240px;">Acções</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($dados)): ?>
                <tr><td colspan="10" class="wppc-empty">Sem mensagens com estes filtros.</td></tr>
            <?php else: foreach ($dados as $m):
                $st       = (string)$m->status;
                $is_agend = ($st === 'pendente' && $has_scheduled && !empty($m->scheduled_at) && $m->scheduled_at > $now_mysql);
                $st_key   = $is_agend ? 'agendada' : $st;
                $st_meta  = $status_color[$st] ?? ['var(--color-slate-700)','var(--color-ink-50)', $st];
                if ($is_agend) $st_meta = ['var(--color-brand-700)','var(--color-brand-100)','Agendada'];

                // Quando: scheduled_at futuro > enviado_em > criado_em
                if ($is_agend) {
                    $when     = $m->scheduled_at;
                    $when_lbl = 'Sairá em';
                } elseif ($st === 'enviado' && !empty($m->enviado_em)) {
                    $when     = $m->enviado_em;
                    $when_lbl = 'Enviada em';
                } else {
                    $when     = $m->criado_em;
                    $when_lbl = 'Criada em';
                }
            ?>
                <tr data-row-id="<?php echo (int)$m->id; ?>" data-status="<?php echo esc_attr($st); ?>">
                    <td style="text-align:center;"><input type="checkbox" class="wppc-check wppc-row-check" value="<?php echo (int)$m->id; ?>" data-status="<?php echo esc_attr($st); ?>"></td>
                    <td><strong>#<?php echo (int)$m->id; ?></strong></td>
                    <td>
                        <span class="wppc-badge" style="color:<?php echo esc_attr($st_meta[0]); ?>;background:<?php echo esc_attr($st_meta[1]); ?>;">
                            <?php echo esc_html($st_meta[2]); ?>
                        </span>
                    </td>
                    <td>
                        <?php if (!empty($m->aluno_nome)): ?>
                            <strong><?php echo esc_html($m->aluno_nome); ?></strong><br>
                            <span style="color:var(--color-slate-400);font-size:11px;">ID #<?php echo (int)$m->aluno_id; ?></span>
                        <?php else: ?>
                            <span style="color:var(--color-slate-400);">- sem aluno -</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $tipo_pretty($m->tipo); ?></td>
                    <td style="font-family:monospace;font-size:12px;"><?php echo esc_html($m->telefone); ?></td>
                    <td class="wppc-msg-prev">
                        <?php
                        $prev = (string)$m->msg_preview;
                        echo esc_html($prev);
                        if ((int)$m->msg_len > 140) echo '… <span style="color:var(--color-slate-400);">(' . (int)$m->msg_len . ' car.)</span>';
                        ?>
                    </td>
                    <td style="font-size:12px;color:var(--color-slate-700);">
                        <span style="color:var(--color-slate-400);display:block;font-size:10px;text-transform:uppercase;font-weight:700;"><?php echo esc_html($when_lbl); ?></span>
                        <?php echo $when ? esc_html(wp_date('d/m/Y H:i', strtotime($when))) : '-'; ?>
                    </td>
                    <td style="text-align:center;font-weight:700;color:<?php echo (int)$m->tentativas > 0 ? 'var(--color-danger-700)' : 'var(--color-slate-400)'; ?>;"><?php echo (int)$m->tentativas; ?></td>
                    <td class="wppc-cell-actions">
                        <button type="button" class="wppc-btn wppc-btn-view" data-act="view" data-id="<?php echo (int)$m->id; ?>">Ver</button>
                        <?php if (in_array($st, ['pendente','forcar_envio'], true)): ?>
                            <button type="button" class="wppc-btn wppc-btn-cancel" data-act="cancel" data-id="<?php echo (int)$m->id; ?>">Cancelar</button>
                        <?php endif; ?>
                        <?php if (in_array($st, ['falhou','cancelado'], true)): ?>
                            <button type="button" class="wppc-btn wppc-btn-retry" data-act="retry" data-id="<?php echo (int)$m->id; ?>">Re-enviar</button>
                        <?php endif; ?>
                        <?php if (in_array($st, ['enviado','falhou','cancelado'], true)): ?>
                            <button type="button" class="wppc-btn wppc-btn-del" data-act="delete" data-id="<?php echo (int)$m->id; ?>" title="Remover do histórico">Remover</button>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table></div>

    <!-- Paginação -->
    <?php if ($totalPags > 1): ?>
    <div class="wppc-pag">
        <?php
        $build_url = function($p) use ($flt_status, $flt_tipo, $flt_busca, $flt_de, $flt_ate) {
            return esc_url(add_query_arg([
                'page'=>'sige-app','view'=>'whatsapp_central',
                'st'=>$flt_status,'tp'=>$flt_tipo,'q'=>$flt_busca,'de'=>$flt_de,'ate'=>$flt_ate,'p'=>$p,
            ], admin_url('admin.php')));
        };
        if ($pag > 1) echo '<a href="'.$build_url($pag-1).'">‹ Anterior</a>';
        $start = max(1, $pag-3); $end = min($totalPags, $pag+3);
        if ($start > 1) echo '<a href="'.$build_url(1).'">1</a> <span>…</span>';
        for ($i = $start; $i <= $end; $i++) {
            if ($i === $pag) echo '<span class="current">'.$i.'</span>';
            else echo '<a href="'.$build_url($i).'">'.$i.'</a>';
        }
        if ($end < $totalPags) echo '<span>…</span> <a href="'.$build_url($totalPags).'">'.$totalPags.'</a>';
        if ($pag < $totalPags) echo '<a href="'.$build_url($pag+1).'">Próxima ›</a>';
        ?>
        <div style="margin-top:8px;font-size:12px;color:var(--color-slate-500);">
            Total: <strong><?php echo number_format($totalRows, 0, ',', '.'); ?></strong> mensagens • Página <?php echo $pag; ?> de <?php echo $totalPags; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de detalhe -->
<div class="wppc-modal-bg" id="wppc-modal-bg">
    <div class="wppc-modal">
        <div class="wppc-modal-h">
            <h3 id="wppc-modal-title">Mensagem #-</h3>
            <button type="button" class="wppc-modal-x" id="wppc-modal-close">✕</button>
        </div>
        <div class="wppc-modal-body" id="wppc-modal-body">
            <div style="text-align:center;color:var(--color-slate-400);padding:30px;">A carregar…</div>
        </div>
    </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
// Reabre o painel de pendentes (substitui a IIFE do onclick por vanilla fiel):
// fecha e reabre o <details> apos um instante, para repetir a tentativa.
window.sigeReabrirPainelPendentes = function () {
    var p = document.querySelector('.wppc-pending-panel');
    if (p) {
        p.removeAttribute('open');
        setTimeout(function () { p.setAttribute('open', ''); }, 100);
    }
};
(function(){
    var ajaxurl = <?php echo wp_json_encode($ajaxurl); ?>;
    var nonce   = <?php echo wp_json_encode($nonce); ?>;

    // -- Helpers ----------------------------------------------------------
    function esc(s){ var d=document.createElement('div'); d.textContent=s==null?'':String(s); return d.innerHTML; }
    function fmtDate(s){ if(!s) return '-'; var d=new Date(String(s).replace(' ','T')); if (isNaN(d.getTime())) return esc(s); return d.toLocaleString('pt-PT',{year:'numeric',month:'2-digit',day:'2-digit',hour:'2-digit',minute:'2-digit'}); }
    function post(action, id){
        var fd = new FormData();
        fd.append('action', action);
        fd.append('_wpnonce', nonce);
        fd.append('id', id);
        return fetch(ajaxurl, {method:'POST', credentials:'same-origin', body:fd}).then(function(r){ return r.json(); });
    }

    // -- Modal ------------------------------------------------------------
    var modalBg    = document.getElementById('wppc-modal-bg');
    var modalTitle = document.getElementById('wppc-modal-title');
    var modalBody  = document.getElementById('wppc-modal-body');
    document.getElementById('wppc-modal-close').addEventListener('click', closeModal);
    modalBg.addEventListener('click', function(e){ if (e.target === modalBg) closeModal(); });
    function openModal(){ modalBg.classList.add('show'); }
    function closeModal(){ modalBg.classList.remove('show'); }

    function renderModal(d){
        modalTitle.textContent = 'Mensagem #' + d.id;
        var html = '';
        html += '<div class="wppc-modal-meta">';
        html += '<div><div class="lbl">Status</div><div class="val">'+esc(d.status)+'</div></div>';
        html += '<div><div class="lbl">Tipo</div><div class="val">'+esc(d.tipo)+'</div></div>';
        html += '<div><div class="lbl">Aluno</div><div class="val">'+esc(d.aluno_nome || ('-  ID '+(d.aluno_id||'?')))+'</div></div>';
        html += '<div><div class="lbl">Telefone</div><div class="val" style="font-family:monospace;">'+esc(d.telefone)+'</div></div>';
        html += '<div><div class="lbl">Criada em</div><div class="val">'+fmtDate(d.criado_em)+'</div></div>';
        if (d.scheduled_at) html += '<div><div class="lbl">Agendada para</div><div class="val">'+fmtDate(d.scheduled_at)+'</div></div>';
        if (d.enviado_em)   html += '<div><div class="lbl">Enviada em</div><div class="val">'+fmtDate(d.enviado_em)+'</div></div>';
        html += '<div><div class="lbl">Tentativas</div><div class="val">'+esc(d.tentativas)+'</div></div>';
        if (d.priority !== null && d.priority !== undefined) html += '<div><div class="lbl">Prioridade</div><div class="val">'+esc(d.priority)+'</div></div>';
        if (d.risk_flags) html += '<div><div class="lbl">Risk flags</div><div class="val">'+esc(d.risk_flags)+'</div></div>';
        html += '</div>';
        html += '<div style="font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;margin-bottom:6px;">Texto integral da mensagem</div>';
        html += '<div class="wppc-modal-msg">'+esc(d.mensagem)+'</div>';
        if (d.erro) html += '<div class="wppc-modal-err"><strong>Último erro:</strong> '+esc(d.erro)+'</div>';
        modalBody.innerHTML = html;
    }

    // -- Click handlers nas linhas ---------------------------------------
    document.querySelectorAll('.wppc-btn[data-act]').forEach(function(btn){
        btn.addEventListener('click', async function(){
            var act = btn.getAttribute('data-act');
            var id  = btn.getAttribute('data-id');
            if (!id) return;

            if (act === 'view') {
                modalBody.innerHTML = '<div style="text-align:center;color:#94a3b8;padding:30px;">A carregar…</div>';
                openModal();
                post('sige_wppc_get_full', id).then(function(r){
                    if (r && r.success) renderModal(r.data);
                    else modalBody.innerHTML = '<div class="wppc-modal-err">Erro: '+esc(r && r.data && r.data.message || 'desconhecido')+'</div>';
                }).catch(function(){
                    modalBody.innerHTML = '<div class="wppc-modal-err">Falha de rede.</div>';
                });
                return;
            }

            var dialogos = {
                cancel: { titulo: 'Cancelar mensagem', texto: 'A mensagem #'+id+' será cancelada e não será enviada.', confirmar: 'Cancelar mensagem' },
                retry:  { titulo: 'Re-enviar mensagem', texto: 'A mensagem #'+id+' volta à fila como pendente e sai na próxima janela de envio.', confirmar: 'Re-enviar' },
                'delete': { titulo: 'Remover do histórico', texto: 'A mensagem #'+id+' será removida definitivamente do histórico. Esta acção não pode ser revertida.', confirmar: 'Remover', perigo: true }
            };
            if (!(await sigeUi.confirm(dialogos[act] || { texto: 'Confirmar esta acção?' }))) return;

            btn.disabled = true; btn.style.opacity = '.5';
            post('sige_wppc_'+act, id).then(function(r){
                if (r && r.success) {
                    var row = document.querySelector('tr[data-row-id="'+id+'"]');
                    if (row) row.style.opacity = '.4';
                    setTimeout(function(){ window.location.reload(); }, 600);
                } else {
                    sigeUi.toast('Não foi possível concluir: ' + (r && r.data && r.data.message || 'erro desconhecido'), 'erro');
                    btn.disabled = false; btn.style.opacity = '1';
                }
            }).catch(function(){
                sigeUi.toast('Falha de ligação. Verifique a internet e tente novamente.', 'erro');
                btn.disabled = false; btn.style.opacity = '1';
            });
        });
    });

    // -- Forçar cron ------------------------------------------------------
    var fcBtn = document.getElementById('wppc-force-cron');
    var fcRes = document.getElementById('wppc-force-result');
    fcBtn.addEventListener('click', function(){
        fcBtn.disabled = true; fcBtn.style.opacity='.5';
        fcRes.textContent = 'A processar…';
        post('sige_wppc_force_cron', 0).then(function(r){
            if (r && r.success) {
                fcRes.textContent = '' + (r.data.message || 'OK') + ' (' + (r.data.duration_ms||0) + 'ms). A recarregar…';
                setTimeout(function(){ window.location.reload(); }, 1200);
            } else {
                fcRes.textContent = '' + (r && r.data && r.data.message || 'erro');
                fcBtn.disabled = false; fcBtn.style.opacity='1';
            }
        }).catch(function(){
            fcRes.textContent = 'Falha de rede.';
            fcBtn.disabled = false; fcBtn.style.opacity='1';
        });
    });

    // ── [v12.9.59] BULK ACTIONS ──────────────────────────────────────────
    var bulkBar   = document.getElementById('wppc-bulkbar');
    var bulkCount = document.getElementById('wppc-bulk-count');
    var btnCancel = document.getElementById('wppc-bulk-cancel');
    var btnRetry  = document.getElementById('wppc-bulk-retry');
    var btnDelete = document.getElementById('wppc-bulk-delete');
    var btnClear  = document.getElementById('wppc-bulk-clear');
    var checkAll  = document.getElementById('wppc-check-all');

    function getCheckedRows() {
        return Array.prototype.slice.call(document.querySelectorAll('.wppc-row-check:checked'));
    }

    function updateBulkBar() {
        var rows = getCheckedRows();
        var n = rows.length;
        bulkCount.textContent = n;

        if (n === 0) {
            bulkBar.classList.remove('show');
            if (checkAll) checkAll.checked = false;
            return;
        }
        bulkBar.classList.add('show');

        // Activa botões consoante haja pelo menos 1 linha elegível
        // cancel: pendente | forcar_envio
        // retry:  falhou | cancelado
        // delete: enviado | falhou | cancelado
        var elig = { cancel: 0, retry: 0, 'delete': 0 };
        rows.forEach(function(cb) {
            var st = cb.getAttribute('data-status');
            if (st === 'pendente' || st === 'forcar_envio') elig.cancel++;
            if (st === 'falhou' || st === 'cancelado') elig.retry++;
            if (st === 'enviado' || st === 'falhou' || st === 'cancelado') elig['delete']++;
        });
        btnCancel.disabled = elig.cancel === 0;
        btnRetry.disabled  = elig.retry === 0;
        btnDelete.disabled = elig['delete'] === 0;
        btnCancel.title = elig.cancel + ' elegível(eis) • outras serão ignoradas';
        btnRetry.title  = elig.retry  + ' elegível(eis) • outras serão ignoradas';
        btnDelete.title = elig['delete'] + ' elegível(eis) • outras serão ignoradas';

        // Estado do master checkbox
        var allBoxes = document.querySelectorAll('.wppc-row-check');
        if (checkAll) checkAll.checked = (allBoxes.length > 0 && n === allBoxes.length);
    }

    // Toggle individual
    document.querySelectorAll('.wppc-row-check').forEach(function(cb) {
        cb.addEventListener('change', updateBulkBar);
    });

    // Master checkbox: marca todas as linhas DA PÁGINA actual
    if (checkAll) {
        checkAll.addEventListener('change', function() {
            var on = checkAll.checked;
            document.querySelectorAll('.wppc-row-check').forEach(function(cb) { cb.checked = on; });
            updateBulkBar();
        });
    }

    // Botão limpar
    btnClear.addEventListener('click', function() {
        document.querySelectorAll('.wppc-row-check').forEach(function(cb) { cb.checked = false; });
        if (checkAll) checkAll.checked = false;
        updateBulkBar();
    });

    // Bulk action - confirma + envia para sige_wppc_bulk
    async function runBulk(op) {
        var rows = getCheckedRows();
        var ids  = rows.map(function(cb) { return cb.value; });
        if (ids.length === 0) return;

        var labels = {
            cancel: 'Cancelar',
            retry:  'Re-enviar',
            'delete': 'Remover definitivamente'
        };
        var dialogos = {
            cancel: { titulo: 'Cancelar seleccionadas', texto: ids.length + ' mensagem(ns) seleccionada(s). Apenas as pendentes/agendadas serão canceladas; as restantes ficam como estão.', confirmar: 'Cancelar mensagens' },
            retry:  { titulo: 'Re-enviar seleccionadas', texto: ids.length + ' mensagem(ns) seleccionada(s). Apenas as falhadas/canceladas voltam à fila como pendentes; as restantes ficam como estão.', confirmar: 'Re-enviar' },
            'delete': { titulo: 'Remover seleccionadas', texto: ids.length + ' mensagem(ns) seleccionada(s) serão removidas definitivamente do histórico (apenas enviadas, falhadas ou canceladas; as pendentes ficam). Esta acção não pode ser revertida.', confirmar: 'Remover', perigo: true }
        };
        if (!(await sigeUi.confirm(dialogos[op] || { texto: 'Confirmar esta acção?' }))) return;

        // Desactiva todos os botões durante o request
        [btnCancel, btnRetry, btnDelete, btnClear].forEach(function(b) { b.disabled = true; b.style.opacity = '.5'; });
        bulkCount.textContent = '⏳';

        var fd = new FormData();
        fd.append('action', 'sige_wppc_bulk');
        fd.append('_wpnonce', nonce);
        fd.append('op', op);
        ids.forEach(function(id) { fd.append('ids[]', id); });

        fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (r && r.success) {
                    sigeUi.toast((r.data.message || 'Operação concluída') + ' (' + r.data.ok + '/' + r.data.total + ')', 'ok');
                    setTimeout(function(){ window.location.reload(); }, 900);
                } else {
                    sigeUi.toast('Não foi possível concluir: ' + (r && r.data && r.data.message || 'erro desconhecido'), 'erro');
                    [btnCancel, btnRetry, btnDelete, btnClear].forEach(function(b) { b.disabled = false; b.style.opacity = '1'; });
                    updateBulkBar();
                }
            })
            .catch(function() {
                sigeUi.toast('Falha de ligação. Verifique a internet e tente novamente.', 'erro');
                [btnCancel, btnRetry, btnDelete, btnClear].forEach(function(b) { b.disabled = false; b.style.opacity = '1'; });
                updateBulkBar();
            });
    }

    btnCancel.addEventListener('click', function() { runBulk('cancel'); });
    btnRetry .addEventListener('click', function() { runBulk('retry');  });
    btnDelete.addEventListener('click', function() { runBulk('delete'); });

    // ── [v12.9.64] PAINEL: Pedidos de Link de Recibo Pendentes ──────────
    var pendingPanel = document.querySelector('.wppc-pending-panel');
    var pendingBadge = document.getElementById('wppc-pending-badge');
    var pendingList  = document.getElementById('wppc-pending-list');
    var pendingSearch = document.getElementById('wppc-pending-search');
    var pendingCounter = document.getElementById('wppc-pending-counter');
    var pendingClearSearch = document.getElementById('wppc-pending-clear-search');
    var pendingLoaded = false;
    var pendingDataset = []; // [v12.9.65] cache local para filtro client-side

    function loadPendingReceipts() {
        if (pendingLoaded) return; // 1× só por carregamento de página
        pendingLoaded = true;

        var fd = new FormData();
        fd.append('action', 'sige_wppc_pending_receipts_list');
        fd.append('_wpnonce', nonce);

        fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r) {
                // [v12.9.64.2] Lê o body como TEXT 1× só, depois tenta JSON.
                // Evita "body stream already read" se o servidor devolver HTML.
                return r.text().then(function(txt) {
                    var data;
                    try { data = JSON.parse(txt); }
                    catch (e) {
                        throw new Error('HTTP ' + r.status + ' - resposta não-JSON: ' + txt.substring(0, 400));
                    }
                    if (!r.ok) {
                        throw new Error('HTTP ' + r.status + ' - ' + (data && data.data && data.data.message || JSON.stringify(data).substring(0, 200)));
                    }
                    return data;
                });
            })
            .then(function(r) {
                if (r && r.success) {
                    pendingBadge.textContent = r.data.count;
                    pendingDataset = r.data.pendentes || [];
                    renderPendingList(pendingDataset);
                    updatePendingCounter(pendingDataset.length, pendingDataset.length);
                } else {
                    pendingBadge.textContent = '?';
                    pendingList.innerHTML = '<div style="color:#b91c1c;">Erro: ' + escapeHtml(r && r.data && r.data.message || 'desconhecido') + '</div>';
                }
            })
            .catch(function(err) {
                pendingBadge.textContent = '!';
                pendingLoaded = false; // permite retry
                pendingList.innerHTML = '<div style="color:#b91c1c; padding:12px; background:#fef2f2; border-radius:6px;">'
                    + '<strong>Erro ao carregar:</strong><br>'
                    + '<code style="font-size:11px; word-break:break-all; white-space:pre-wrap;">' + escapeHtml(err.message || String(err)) + '</code><br><br>'
                    + '<button type="button" data-sige-act="sigeReabrirPainelPendentes" data-sige-noargs class="sgk-btn sgk-btn-sec sgk-btn-sm">🔁 Tentar novamente</button>'
                    + '</div>';
            });
    }

    function fmtDate(s) {
        if (!s) return '-';
        var d = new Date(s.replace(' ', 'T'));
        if (isNaN(d.getTime())) return s;
        return d.toLocaleString('pt-PT', { day:'2-digit', month:'2-digit', hour:'2-digit', minute:'2-digit' });
    }

    function renderPendingList(items) {
        if (!items.length) {
            // [v12.9.65] Distingue lista vazia de filtro sem resultados
            var hasFilter = pendingSearch && pendingSearch.value.trim() !== '';
            if (hasFilter) {
                pendingList.innerHTML = '<div style="color:#78350f; padding:16px; background:#fffbeb; border-radius:6px; font-style:italic;">🔍 Sem resultados para "<strong>' + escapeHtml(pendingSearch.value) + '</strong>". Tente outro termo ou <a href="#" id="wppc-pending-clear-search-inline" style="color:#3b82f6;">limpar pesquisa</a>.</div>';
                var inlineClear = document.getElementById('wppc-pending-clear-search-inline');
                if (inlineClear) {
                    inlineClear.addEventListener('click', function(e) {
                        e.preventDefault();
                        pendingSearch.value = '';
                        applyPendingSearch();
                        pendingSearch.focus();
                    });
                }
            } else {
                pendingList.innerHTML = '<div class="sgk-empty"><div class="sgk-empty-icone">🎉</div><p class="sgk-empty-titulo">Tudo tratado</p><p class="sgk-empty-texto">Todos os encarregados já receberam o link do recibo ou indicaram que não o querem.</p></div>';
            }
            return;
        }

        var html = '<table style="width:100%; border-collapse:collapse; background:#fff; border-radius:8px; overflow:hidden;">';
        html += '<thead><tr style="background:#fef3c7; text-align:left;">';
        html += '<th style="padding:8px 10px; font-size:12px; color:#78350f;">Aluno</th>';
        html += '<th style="padding:8px 10px; font-size:12px; color:#78350f;">Encarregado / Telefone</th>';
        html += '<th style="padding:8px 10px; font-size:12px; color:#78350f;">Recibo</th>';
        html += '<th style="padding:8px 10px; font-size:12px; color:#78350f;">Pergunta enviada</th>';
        html += '<th style="padding:8px 10px; font-size:12px; color:#78350f;">Última resposta</th>';
        html += '<th style="padding:8px 10px; font-size:12px; color:#78350f; text-align:right;">Acções</th>';
        html += '</tr></thead><tbody>';

        items.forEach(function(it) {
            var lastRep = it.last_reply_at ? '✓ ' + fmtDate(it.last_reply_at) : '-';
            html += '<tr data-tel="' + escapeHtml(it.telefone) + '" style="border-top:1px solid #fde68a;">';
            html += '<td style="padding:10px;"><strong>' + escapeHtml(it.aluno_nome || '-') + '</strong></td>';
            html += '<td style="padding:10px;"><strong>' + escapeHtml(it.encarregado || 'Encarregado') + '</strong><br><span style="color:#64748b; font-size:12px;">' + escapeHtml(it.telefone) + '</span></td>';
            html += '<td style="padding:10px; font-family:monospace; font-size:12px;">' + escapeHtml(it.recibo || '-') + '</td>';
            html += '<td style="padding:10px; font-size:12px;">' + fmtDate(it.enviado_em || it.criado_em) + '</td>';
            html += '<td style="padding:10px; font-size:12px;' + (it.last_reply_at ? 'color:#166534; font-weight:600;' : 'color:#94a3b8;') + '">' + lastRep + '</td>';
            html += '<td style="padding:10px; text-align:right;">';
            html += '<button type="button" class="wppc-send-link-btn" data-tel="' + escapeHtml(it.telefone) + '" style="background:#10b981; color:#fff; padding:6px 10px; border:0; border-radius:6px; font-size:11px; font-weight:700; cursor:pointer; margin-right:4px;">📎 Enviar link</button>';
            html += '<button type="button" class="wppc-clear-pending-btn" data-tel="' + escapeHtml(it.telefone) + '" style="background:#94a3b8; color:#fff; padding:6px 10px; border:0; border-radius:6px; font-size:11px; cursor:pointer;">✕ Tratado</button>';
            html += '</td>';
            html += '</tr>';
        });
        html += '</tbody></table>';
        pendingList.innerHTML = html;

        // Bind dos botões
        pendingList.querySelectorAll('.wppc-send-link-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { sendLinkNow(btn.getAttribute('data-tel'), btn); });
        });
        pendingList.querySelectorAll('.wppc-clear-pending-btn').forEach(function(btn) {
            btn.addEventListener('click', function() { clearPendingNow(btn.getAttribute('data-tel'), btn); });
        });
    }

    function escapeHtml(s) {
        return String(s || '').replace(/[<>&"']/g, function(c) {
            return { '<':'&lt;','>':'&gt;','&':'&amp;','"':'&quot;',"'":'&#39;' }[c];
        });
    }

    // ── [v12.9.65] Pesquisa rápida client-side no painel de pendentes ────
    function normalizeSearch(s) {
        // lowercase + remove acentos + remove pontuação para busca robusta
        return String(s || '')
            .toLowerCase()
            .normalize('NFD').replace(/[\u0300-\u036f]/g, '')  // remove acentos
            .replace(/[^a-z0-9\s]/g, ' ')                       // pontuação → espaço
            .replace(/\s+/g, ' ').trim();
    }

    function filterPendingDataset(query) {
        var q = normalizeSearch(query);
        if (!q) return pendingDataset.slice();
        // Suporta múltiplos termos separados por espaço (todos têm de bater)
        var terms = q.split(' ').filter(Boolean);
        return pendingDataset.filter(function(it) {
            var haystack = normalizeSearch([
                it.aluno_nome,
                it.encarregado,
                it.telefone,
                it.recibo,
                it.aluno_id
            ].join(' '));
            return terms.every(function(t) { return haystack.indexOf(t) !== -1; });
        });
    }

    function updatePendingCounter(shown, total) {
        if (!pendingCounter) return;
        if (shown === total) {
            pendingCounter.textContent = total === 0 ? '' : (total + ' pendente' + (total !== 1 ? 's' : ''));
        } else {
            pendingCounter.textContent = shown + ' de ' + total + ' (filtro activo)';
        }
    }

    function applyPendingSearch() {
        if (!pendingSearch) return;
        var q = pendingSearch.value;
        var filtered = filterPendingDataset(q);
        renderPendingList(filtered);
        updatePendingCounter(filtered.length, pendingDataset.length);
        if (pendingClearSearch) {
            pendingClearSearch.style.display = q.trim() ? 'inline-block' : 'none';
        }
    }

    if (pendingSearch) {
        // Debounce 200ms para não re-render a cada tecla
        var pendingSearchTimer = null;
        pendingSearch.addEventListener('input', function() {
            clearTimeout(pendingSearchTimer);
            pendingSearchTimer = setTimeout(applyPendingSearch, 200);
        });
        pendingSearch.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                pendingSearch.value = '';
                applyPendingSearch();
            }
        });
    }
    if (pendingClearSearch) {
        pendingClearSearch.addEventListener('click', function() {
            pendingSearch.value = '';
            applyPendingSearch();
            pendingSearch.focus();
        });
    }

    async function sendLinkNow(tel, btn) {
        if (!(await sigeUi.confirm({ titulo: 'Enviar link do recibo', texto: 'O link do recibo segue para ' + tel + ' nos próximos 0 a 30 segundos, em formato conversacional.', confirmar: 'Enviar link' }))) return;
        btn.disabled = true; btn.style.opacity = '.5'; btn.textContent = '⏳ A enviar...';

        var fd = new FormData();
        fd.append('action', 'sige_wppc_send_link_now');
        fd.append('_wpnonce', nonce);
        fd.append('telefone', tel);

        fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (r && r.success) {
                    btn.textContent = 'Enviado';
                    btn.style.background = '#16a34a';
                    // [v12.9.65] Remove do dataset cache
                    pendingDataset = pendingDataset.filter(function(x) { return x.telefone !== tel; });
                    // Remove a linha após 1.5s
                    setTimeout(function() {
                        var row = btn.closest('tr');
                        if (row) row.remove();
                        var n = pendingList.querySelectorAll('tr[data-tel]').length;
                        pendingBadge.textContent = pendingDataset.length;
                        updatePendingCounter(n, pendingDataset.length);
                        if (n === 0) renderPendingList([]);
                    }, 1500);
                } else {
                    sigeUi.toast((r && r.data && r.data.message) || 'Não foi possível enviar o link. Tente novamente.', 'erro');
                    btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '📎 Enviar link';
                }
            })
            .catch(function() {
                sigeUi.toast('Falha de ligação. Verifique a internet e tente novamente.', 'erro');
                btn.disabled = false; btn.style.opacity = '1'; btn.textContent = '📎 Enviar link';
            });
    }

    async function clearPendingNow(tel, btn) {
        if (!(await sigeUi.confirm({ titulo: 'Marcar como tratado', texto: 'O número ' + tel + ' será marcado como tratado SEM envio do link. Use se já tratou manualmente fora do sistema ou se o encarregado mudou de ideia.', confirmar: 'Marcar como tratado' }))) return;
        btn.disabled = true; btn.style.opacity = '.5';

        var fd = new FormData();
        fd.append('action', 'sige_wppc_clear_pending');
        fd.append('_wpnonce', nonce);
        fd.append('telefone', tel);

        fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function(r) { return r.json(); })
            .then(function(r) {
                if (r && r.success) {
                    // [v12.9.65] Remove do dataset cache
                    pendingDataset = pendingDataset.filter(function(x) { return x.telefone !== tel; });
                    var row = btn.closest('tr');
                    if (row) row.remove();
                    var n = pendingList.querySelectorAll('tr[data-tel]').length;
                    pendingBadge.textContent = pendingDataset.length;
                    updatePendingCounter(n, pendingDataset.length);
                    if (n === 0) renderPendingList([]);
                } else {
                    sigeUi.toast((r && r.data && r.data.message) || 'Não foi possível concluir. Tente novamente.', 'erro');
                    btn.disabled = false; btn.style.opacity = '1';
                }
            })
            .catch(function() {
                sigeUi.toast('Falha de ligação. Verifique a internet e tente novamente.', 'erro');
                btn.disabled = false; btn.style.opacity = '1';
            });
    }

    // Carrega quando o painel é aberto (lazy)
    if (pendingPanel) {
        pendingPanel.addEventListener('toggle', function() {
            if (pendingPanel.open) loadPendingReceipts();
        });
    }

    // ── [v12.9.62] PULL-FORWARD: redistribuir mensagens futuras para hoje ──
    var pfBtn = document.getElementById('wppc-pullforward-btn');
    var pfRes = document.getElementById('wppc-pullforward-result');
    if (pfBtn) {
        pfBtn.addEventListener('click', async function() {
            if (!(await sigeUi.confirm({ titulo: 'Trazer agendadas para hoje', texto: 'As mensagens agendadas para amanhã ou depois serão redistribuídas: as que couberem nas janelas de hoje (manhã, tarde, fim do dia) saem hoje. O limite diário mantém-se; as restantes ficam para amanhã, distribuídas.', confirmar: 'Redistribuir' }))) return;

            pfBtn.disabled = true; pfBtn.style.opacity = '.5';
            pfRes.textContent = '⏳ A redistribuir...';

            var fd = new FormData();
            fd.append('action', 'sige_wppc_pull_forward');
            fd.append('_wpnonce', nonce);

            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(r) {
                    if (r && r.success) {
                        sigeUi.toast('' + r.data.message, 'ok');
                        window.location.reload();
                    } else {
                        pfRes.textContent = '' + (r && r.data && r.data.message || 'Erro desconhecido');
                        pfRes.style.color = '#b91c1c';
                        pfBtn.disabled = false; pfBtn.style.opacity = '1';
                    }
                })
                .catch(function() {
                    pfRes.textContent = 'Falha de rede.';
                    pfRes.style.color = '#b91c1c';
                    pfBtn.disabled = false; pfBtn.style.opacity = '1';
                });
        });
    }

    // ── [v12.9.61] BACKFILL pending_receipt para recibos antigos ─────────
    var bfBtn = document.getElementById('wppc-backfill-btn');
    var bfRes = document.getElementById('wppc-backfill-result');
    if (bfBtn) {
        bfBtn.addEventListener('click', async function() {
            if (!(await sigeUi.confirm({ titulo: 'Preparar respostas automáticas', texto: 'Os recibos antigos que estão na fila ganham resposta automática preparada. Acção segura: pode repetir-se sem duplicar nada e demora poucos segundos.', confirmar: 'Preparar' }))) return;

            bfBtn.disabled = true; bfBtn.style.opacity = '.5';
            bfRes.textContent = '⏳ A processar...';

            var fd = new FormData();
            fd.append('action', 'sige_wppc_backfill_pending');
            fd.append('_wpnonce', nonce);

            fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
                .then(function(r) { return r.json(); })
                .then(function(r) {
                    if (r && r.success) {
                        bfRes.textContent = '' + r.data.message;
                        bfRes.style.color = '#166534';
                        // Esconder o botão se tudo foi processado (set + already_set === scanned)
                        var s = r.data.stats || {};
                        if ((s.set + s.already_set) === s.scanned && s.no_recibo_match === 0) {
                            setTimeout(function() {
                                bfBtn.style.display = 'none';
                                bfRes.textContent += ' • Recarrega a página para refrescar contadores.';
                            }, 1500);
                        } else {
                            bfBtn.disabled = false; bfBtn.style.opacity = '1';
                        }
                    } else {
                        bfRes.textContent = '' + (r && r.data && r.data.message || 'Erro desconhecido');
                        bfRes.style.color = '#b91c1c';
                        bfBtn.disabled = false; bfBtn.style.opacity = '1';
                    }
                })
                .catch(function() {
                    bfRes.textContent = 'Falha de rede.';
                    bfRes.style.color = '#b91c1c';
                    bfBtn.disabled = false; bfBtn.style.opacity = '1';
                });
        });
    }
})();
</script>
