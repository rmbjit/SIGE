<?php
if (!defined('ABSPATH')) exit;

global $wpdb;

$sige_aluno_portal_active_role_slug = '';
if (function_exists('sige_permissions_get_active_role')) {
    $sige_aluno_portal_active_role = sige_permissions_get_active_role((int)get_current_user_id());
    if ($sige_aluno_portal_active_role && !empty($sige_aluno_portal_active_role->slug)) {
        $sige_aluno_portal_active_role_slug = sanitize_key((string)$sige_aluno_portal_active_role->slug);
    }
}

$sige_aluno_portal_user_can_read = (
    current_user_can('sige_encarregado')
    || current_user_can('sige_aluno')
    || in_array($sige_aluno_portal_active_role_slug, ['encarregado','aluno'], true)
    || (function_exists('sige_can') && sige_can('portal.ver'))
);

$sige_aluno_portal_staff_can_read = (
    (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
    || current_user_can('sige_director')
    || current_user_can('sige_secretario')
    || current_user_can('sige_pedagogico')
    || (function_exists('sige_can') && sige_can('alunos.ver'))
);

if (!$sige_aluno_portal_user_can_read && !$sige_aluno_portal_staff_can_read) {
    if (function_exists('sige_page_guard_render_denied')) {
        sige_page_guard_render_denied('Acesso restrito', 'O seu perfil não tem acesso à Página do Aluno.');
    }
    return;
}

$eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
$ano = function_exists('sige_ano_lectivo_atual') ? (int)sige_ano_lectivo_atual() : (int)wp_date('Y');
$p   = $wpdb->prefix;

if (!function_exists('sige_aluno_portal_table_exists')) {
    function sige_aluno_portal_table_exists(string $table): bool {
        global $wpdb;
        return (bool)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table)));
    }
}
if (!function_exists('sige_aluno_portal_col_exists')) {
    function sige_aluno_portal_col_exists(string $table, string $column): bool {
        global $wpdb;
        if (!sige_aluno_portal_table_exists($table)) return false;
        return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $wpdb->esc_like($column)));
    }
}
if (!function_exists('sige_aluno_portal_money')) {
    function sige_aluno_portal_money($v): string {
        return number_format((float)$v, 2, ',', '.') . ' MZN';
    }
}
if (!function_exists('sige_aluno_portal_saldo_sql')) {
    function sige_aluno_portal_saldo_sql(string $alias = 'l'): string {
        if (function_exists('sige_fin_saldo_sql')) {
            return sige_fin_saldo_sql($alias);
        }
        $a = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'l';
        return "GREATEST(
            COALESCE({$a}.valor_original, 0)
            + COALESCE({$a}.valor_transporte, 0)
            + COALESCE({$a}.valor_extras, 0)
            + COALESCE(NULLIF({$a}.valor_multa_cobrada, 0), {$a}.valor_multa, 0)
            - COALESCE({$a}.valor_desconto, 0)
            - COALESCE({$a}.valor_desconto_especial, 0)
            - COALESCE({$a}.valor_pago, 0)
        , 0)";
    }
}
if (!function_exists('sige_aluno_portal_total_sql')) {
    function sige_aluno_portal_total_sql(string $alias = 'l'): string {
        if (function_exists('sige_fin_total_bruto_sql')) {
            return sige_fin_total_bruto_sql($alias);
        }
        $a = preg_replace('/[^A-Za-z0-9_]/', '', $alias) ?: 'l';
        return "GREATEST(
            COALESCE({$a}.valor_original, 0)
            + COALESCE({$a}.valor_transporte, 0)
            + COALESCE({$a}.valor_extras, 0)
            + COALESCE(NULLIF({$a}.valor_multa_cobrada, 0), {$a}.valor_multa, 0)
            - COALESCE({$a}.valor_desconto, 0)
            - COALESCE({$a}.valor_desconto_especial, 0)
        , 0)";
    }
}
if (!function_exists('sige_aluno_portal_date')) {
    function sige_aluno_portal_date($date): string {
        if (empty($date) || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') return '-';
        return esc_html(wp_date('d/m/Y', strtotime((string)$date)));
    }
}
if (!function_exists('sige_aluno_portal_recibo_url')) {
    function sige_aluno_portal_recibo_url($pagamento_id): string {
        $pagamento_id = absint($pagamento_id);
        if (!$pagamento_id) return '';
        return admin_url('admin.php?page=sige-app&sige_print=recibo&id=' . $pagamento_id);
    }
}

if (!function_exists('sige_aluno_portal_doc_url')) {
    function sige_aluno_portal_doc_url(int $aluno_id, string $field, string $raw_url = ''): string {
        $field = sanitize_key($field);
        if ($aluno_id <= 0 || $field === '' || trim((string)$raw_url) === '') return '';
        if (function_exists('sige_secure_document_url')) {
            return sige_secure_document_url($aluno_id, $field);
        }
        // Fallback legado apenas se o endpoint seguro não estiver carregado.
        return (string)$raw_url;
    }
}

if (!function_exists('sige_aluno_portal_icon')) {
    function sige_aluno_portal_icon(string $name): string {
        if (function_exists('sige_ui_icon')) {
            return sige_ui_icon($name);
        }
        $map = [
            'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
            'book' => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M4 4.5A2.5 2.5 0 0 1 6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5z"/>',
            'wallet' => '<path d="M19 7V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-2"/><path d="M16 12h6v5h-6a2.5 2.5 0 0 1 0-5z"/>',
            'file' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/>',
            'heart' => '<path d="M20.8 4.6a5.5 5.5 0 0 0-7.8 0L12 5.6l-1-1a5.5 5.5 0 0 0-7.8 7.8l1 1L12 21l7.8-7.6 1-1a5.5 5.5 0 0 0 0-7.8Z"/>',
            'activity' => '<path d="M22 12h-4l-3 9L9 3l-3 9H2"/>',
            'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/>',
            'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/>',
            'check' => '<path d="M20 6 9 17l-5-5"/>',
            'alert' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.46 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/>',
            'arrow-left' => '<path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>',
            'phone' => '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.12.9.32 1.78.59 2.63a2 2 0 0 1-.45 2.11L8 9.7a16 16 0 0 0 6.3 6.3l1.24-1.24a2 2 0 0 1 2.11-.45c.85.27 1.73.47 2.63.59A2 2 0 0 1 22 16.92Z"/>',
            'mail' => '<rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 7-10 6L2 7"/>',
            'printer' => '<path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
            'shield' => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
            'chart' => '<path d="M3 3v18h18"/><path d="M7 16V9"/><path d="M12 16V5"/><path d="M17 16v-3"/>',
        ];
        $path = $map[$name] ?? $map['user'];
        return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $path . '</svg>';
    }
}
if (!function_exists('sige_aluno_portal_is_preescolar')) {
    function sige_aluno_portal_is_preescolar($turma): bool {
        if (!$turma) return false;
        if (isset($turma->is_preescolar) && (int)$turma->is_preescolar === 1) return true;
        $txt = strtolower(trim((string)($turma->classe ?? '') . ' ' . (string)($turma->nome ?? '') . ' ' . (string)($turma->nome_turma ?? '') . ' ' . (string)($turma->nivel_ensino ?? '')));
        $from = ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'];
        $to   = ['a','a','a','a','e','e','i','o','o','o','u','c'];
        $txt = str_replace($from, $to, $txt);
        return (bool)preg_match('/pre|jardim|creche|infantil|maternal|casa\s*(dos?\s*)?([2-5]|2\/3)|(^|[^0-9])[2-5]\s*anos([^0-9]|$)/u', $txt);
    }
}

if (!function_exists('sige_aluno_portal_password_policy_v125')) {
    function sige_aluno_portal_password_policy_v125($new_password, $user): array {
        $errors = [];

        if (strlen($new_password) < 8) {
            $errors[] = 'A nova senha deve ter pelo menos 8 caracteres.';
        }
        if (strlen($new_password) > 128) {
            $errors[] = 'A nova senha é demasiado longa.';
        }
        if (!preg_match('/[A-Za-zÀ-ÿ]/u', $new_password) || !preg_match('/\d/', $new_password)) {
            $errors[] = 'A nova senha deve combinar letras e números.';
        }

        $login = is_object($user) && isset($user->user_login) ? strtolower((string)$user->user_login) : '';
        $email_prefix = is_object($user) && isset($user->user_email) ? strtolower((string)strtok((string)$user->user_email, '@')) : '';
        $lower = strtolower($new_password);

        if ($login !== '' && strlen($login) >= 4 && strpos($lower, $login) !== false) {
            $errors[] = 'A nova senha não deve conter o nome de utilizador.';
        }
        if ($email_prefix !== '' && strlen($email_prefix) >= 4 && strpos($lower, $email_prefix) !== false) {
            $errors[] = 'A nova senha não deve conter parte do e-mail.';
        }

        return $errors;
    }
}

$is_portal_user_context = (
    !$sige_aluno_portal_staff_can_read
    && (
        $sige_aluno_portal_user_can_read
        || (function_exists('sige_can') && sige_can('portal.ver') && !(function_exists('sige_can') && sige_can('alunos.ver')))
        || current_user_can('sige_encarregado')
        || current_user_can('sige_aluno')
        || in_array($sige_aluno_portal_active_role_slug, ['encarregado','aluno'], true)
    )
);

$linked_aluno_id = function_exists('sige_aluno_portal_current_student_id_v117')
    ? (int)sige_aluno_portal_current_student_id_v117(get_current_user_id())
    : (int)get_user_meta(get_current_user_id(), 'sige_aluno_id', true);

$aluno_id = isset($_GET['aluno_id']) ? absint($_GET['aluno_id']) : (isset($_GET['id']) ? absint($_GET['id']) : 0);
if ($is_portal_user_context) {
    $aluno_id = $linked_aluno_id;
}
$secao = isset($_GET['secao']) ? sanitize_key((string)$_GET['secao']) : 'resumo';
if (!$aluno_id) {
    $current_user = wp_get_current_user();
    $login_info = ($current_user && $current_user->ID) ? esc_html($current_user->user_login) : '-';
    echo '<div style="max-width:760px;margin:40px auto;padding:30px;border-radius:22px;background:#fff;border:1px solid var(--color-ink-100);box-shadow:0 12px 32px rgba(15,23,42,.12);font-family:Inter,Segoe UI,system-ui,sans-serif;color:var(--color-ink-500);">';
    echo '<div style="display:inline-flex;align-items:center;gap:8px;color:var(--sg-theme-primary,#5a3fd6);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;margin-bottom:12px;">Página do Aluno</div>';
    echo '<h2 style="margin:0 0 10px;font-size:26px;letter-spacing:-.03em;">Conta ainda não associada ao aluno</h2>';
    echo '<p style="margin:0 0 18px;color:var(--color-slate-500);font-size:14px;line-height:1.65;">Esta conta existe, mas ainda não está ligada a um aluno desta escola. Peça à secretaria para abrir a Gestão de Alunos e gerar ou reenviar o acesso do aluno. O sistema irá validar automaticamente a associação.</p>';
    echo '<div style="background:var(--color-slate-50);border:1px solid var(--color-slate-200);border-radius:16px;padding:14px 16px;color:var(--color-slate-700);font-size:13px;font-weight:700;">Utilizador autenticado: ' . $login_info . '</div>';
    echo '</div>';
    return;
}

$tA = $p . 'sige_alunos';
$tM = $p . 'sige_matriculas';
$tT = $p . 'sige_turmas';
$tN = $p . 'sige_notas';
$tD = $p . 'sige_disciplinas';
$tL = $p . 'sige_fin_lancamentos';
$tPg = $p . 'sige_fin_pagamentos';

$aluno = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$tA} WHERE id=%d AND escola_id=%d LIMIT 1", $aluno_id, $eid));
if (!$aluno) {
    echo '<div class="sige-aluno-portal-empty"><h2>Aluno não encontrado</h2><p>Confirme se o aluno pertence à escola actual.</p></div>';
    return;
}

$matricula = $wpdb->get_row($wpdb->prepare("
    SELECT m.*, t.nome, t.nome_turma, t.classe, t.turno, t.nivel_ensino, t.is_preescolar, t.ano_lectivo AS turma_ano
    FROM {$tM} m
    LEFT JOIN {$tT} t ON t.id=m.turma_id AND t.escola_id=m.escola_id
    WHERE m.aluno_id=%d AND m.escola_id=%d
    ORDER BY CASE WHEN m.ano_lectivo=%d THEN 0 ELSE 1 END, m.ano_lectivo DESC, m.id DESC
    LIMIT 1
", $aluno_id, $eid, $ano));

$turma_id = $matricula ? (int)$matricula->turma_id : 0;
$ano_aluno = $matricula ? (int)$matricula->ano_lectivo : $ano;
$is_preescolar = sige_aluno_portal_is_preescolar($matricula);

$idade = '-';
if (!empty($aluno->data_nascimento) && $aluno->data_nascimento !== '0000-00-00') {
    try {
        $dn = new DateTime((string)$aluno->data_nascimento);
        $hoje = new DateTime(wp_date('Y-m-d'));
        $idade = $dn->diff($hoje)->y . ' anos';
    } catch (Exception $e) {}
}

$foto = !empty($aluno->foto) ? esc_url($aluno->foto) : '';
if (!$foto && !empty($aluno->genero)) {
    $foto = ((string)$aluno->genero === 'F') ? SIGE_URL . 'assets/img/avatar-menina.png' : SIGE_URL . 'assets/img/avatar-menino.png';
}
if (!$foto) $foto = SIGE_URL . 'assets/img/avatar-aluno.png';

$status_aluno = strtolower((string)($aluno->status ?? 'activo'));
$status_label = in_array($status_aluno, ['activo','ativo','activa','ativa'], true) ? 'Activo' : ucfirst((string)($aluno->status ?? '-'));
$status_class = in_array($status_aluno, ['activo','ativo','activa','ativa'], true) ? 'ok' : 'warn';

$classe_label = $matricula ? trim((string)($matricula->classe ?: $matricula->nome ?: $matricula->nome_turma)) : ((string)($aluno->classe_atual ?? ''));
$turma_label = $matricula ? trim((string)($matricula->nome ?: $matricula->nome_turma ?: $matricula->classe)) : 'Sem turma';
$nivel_label = $is_preescolar ? 'Jardim de Infância' : 'Ensino Regular';

$fin_total = $fin_pago = $fin_divida = $fin_vencido = $fin_em_plano = 0.0;
$lanc_recentes = $pag_recentes = [];
$ap_from = isset($_GET['ap_from']) ? sanitize_text_field(wp_unslash((string)$_GET['ap_from'])) : '';
$ap_to   = isset($_GET['ap_to']) ? sanitize_text_field(wp_unslash((string)$_GET['ap_to'])) : '';
$ap_from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ap_from) ? $ap_from : '';
$ap_to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $ap_to) ? $ap_to : '';
if ($ap_from && $ap_to && strtotime($ap_from) > strtotime($ap_to)) {
    $tmp = $ap_from; $ap_from = $ap_to; $ap_to = $tmp;
}
$ap_lanc_limit = 8;
$ap_pag_limit  = 6;
if (sige_aluno_portal_table_exists($tL)) {
    // v12.11.9.14 - A Página do Aluno passa a usar a mesma semântica financeira
    // dos módulos internos: só lançamentos com saldo real e status pendente/parcial
    // geram pendência/bloqueio. Pagos, isentos, cancelados, anulados e planos
    // negociados não devem transformar um aluno regular em devedor no portal.
    $ap_saldo_expr = sige_aluno_portal_saldo_sql('l');
    $ap_total_expr = sige_aluno_portal_total_sql('l');
    $fin = $wpdb->get_row($wpdb->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(l.status,'')) NOT IN ('cancelado','anulado') THEN {$ap_total_expr} ELSE 0 END),0) AS total,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(l.status,'')) NOT IN ('cancelado','anulado') THEN COALESCE(l.valor_pago,0) ELSE 0 END),0) AS pago,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(l.status,'')) IN ('pendente','parcial') THEN {$ap_saldo_expr} ELSE 0 END),0) AS divida,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(l.status,'')) IN ('pendente','parcial') AND {$ap_saldo_expr} > 0.009 AND l.data_vencimento < CURDATE() THEN {$ap_saldo_expr} ELSE 0 END),0) AS vencido,
            COALESCE(SUM(CASE WHEN LOWER(COALESCE(l.status,'')) = 'em_plano' THEN {$ap_saldo_expr} ELSE 0 END),0) AS em_plano
        FROM {$tL} l
        WHERE l.escola_id=%d AND l.aluno_id=%d
    ", $eid, $aluno_id));
    if ($fin) {
        $fin_total = (float)$fin->total;
        $fin_pago = (float)$fin->pago;
        $fin_divida = (float)$fin->divida;
        $fin_vencido = (float)$fin->vencido;
        $fin_em_plano = (float)($fin->em_plano ?? 0);
    }
    $ap_lanc_where = "escola_id=%d AND aluno_id=%d";
    $ap_lanc_args  = [$eid, $aluno_id];
    if ($ap_from !== '') {
        $ap_lanc_where .= " AND data_vencimento >= %s";
        $ap_lanc_args[] = $ap_from;
    }
    if ($ap_to !== '') {
        $ap_lanc_where .= " AND data_vencimento <= %s";
        $ap_lanc_args[] = $ap_to;
    }
    $ap_lanc_args[] = $ap_lanc_limit;
    $lanc_recentes = $wpdb->get_results($wpdb->prepare("
        SELECT
            l.id, l.descricao, l.mes_referencia, l.valor_original, l.valor_pago, l.valor_multa, l.valor_transporte, l.valor_extras, l.valor_desconto, l.valor_desconto_especial, l.data_vencimento, l.status,
            (
                SELECT p2.id FROM {$tPg} p2
                WHERE p2.escola_id = l.escola_id AND p2.aluno_id = l.aluno_id AND p2.lancamento_id = l.id
                ORDER BY p2.data_pagamento DESC, p2.id DESC
                LIMIT 1
            ) AS pagamento_id,
            (
                SELECT p2.recibo_numero FROM {$tPg} p2
                WHERE p2.escola_id = l.escola_id AND p2.aluno_id = l.aluno_id AND p2.lancamento_id = l.id
                ORDER BY p2.data_pagamento DESC, p2.id DESC
                LIMIT 1
            ) AS recibo_numero
        FROM {$tL} l
        WHERE {$ap_lanc_where}
        ORDER BY l.data_vencimento DESC, l.id DESC
        LIMIT %d
    ", $ap_lanc_args));
}
if (sige_aluno_portal_table_exists($tPg)) {
    $ap_pag_where = "escola_id=%d AND aluno_id=%d";
    $ap_pag_args  = [$eid, $aluno_id];
    if ($ap_from !== '') {
        $ap_pag_where .= " AND DATE(data_pagamento) >= %s";
        $ap_pag_args[] = $ap_from;
    }
    if ($ap_to !== '') {
        $ap_pag_where .= " AND DATE(data_pagamento) <= %s";
        $ap_pag_args[] = $ap_to;
    }
    $ap_pag_args[] = $ap_pag_limit;
    $pag_recentes = $wpdb->get_results($wpdb->prepare("
        SELECT id, recibo_numero, valor_pago, metodo_pagamento, data_pagamento
        FROM {$tPg}
        WHERE {$ap_pag_where}
        ORDER BY data_pagamento DESC, id DESC
        LIMIT %d
    ", $ap_pag_args));
}

// v12.10.116 - regras explícitas de portal:
// 1) Página do Aluno é read-only.
// 2) Com dívida, o portal não mostra notas, média, boletim académico/Jardim ou atalhos académicos sensíveis.
// Staff continua a poder trabalhar nos módulos internos próprios, fora desta página.
$portal_readonly = true;
$tem_divida_bloqueante = ((float)$fin_divida > 0.009);
$bloquear_notas_por_divida = $tem_divida_bloqueante;
$is_staff_context = (!$is_portal_user_context) && (
    (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
    || current_user_can('sige_director')
    || current_user_can('sige_secretario')
    || current_user_can('sige_pedagogico')
    || current_user_can('sige_admin')
    || (function_exists('sige_can') && (sige_can('alunos.editar') || sige_can('financeiro.pagar') || sige_can('academico.lancar_notas')))
);

$notas_rows = [];
$media_geral = null;
$disciplinas_count = 0;
if (!$bloquear_notas_por_divida && sige_aluno_portal_table_exists($tN) && sige_aluno_portal_table_exists($tD)) {
    $turma_sql = $turma_id ? $wpdb->prepare(" AND (n.turma_id=%d OR n.turma_id IS NULL OR n.turma_id=0)", $turma_id) : '';
    $notas_rows = $wpdb->get_results($wpdb->prepare("
        SELECT n.*, d.nome AS disciplina_nome, d.sigla
        FROM {$tN} n
        LEFT JOIN {$tD} d ON d.id=n.disciplina_id AND d.escola_id=n.escola_id
        WHERE n.escola_id=%d AND n.aluno_id=%d AND n.ano_lectivo=%d {$turma_sql}
        ORDER BY COALESCE(d.ordem,999), d.nome ASC, n.trimestre ASC
    ", $eid, $aluno_id, $ano_aluno));
    $soma = 0; $cnt = 0; $disc_ids = [];
    foreach ((array)$notas_rows as $nr) {
        $vals = [];
        foreach (['nota_ac','nota_acp','nota_at','nota_exame','nota_conselho'] as $campo) {
            if ($nr->$campo !== null && $nr->$campo !== '') $vals[] = (float)$nr->$campo;
        }
        if ($vals) {
            $soma += array_sum($vals) / count($vals);
            $cnt++;
        }
        if (!empty($nr->disciplina_id)) $disc_ids[(int)$nr->disciplina_id] = true;
    }
    $media_geral = $cnt ? round($soma/$cnt, 1) : null;
    $disciplinas_count = count($disc_ids);
}

$j_diario = $j_saude = $j_presencas = [];
if ($is_preescolar) {
    $tJD = $p . 'sige_jardim_diario';
    $tJS = $p . 'sige_jardim_saude';
    $tJP = $p . 'sige_jardim_presencas';
    if (sige_aluno_portal_table_exists($tJD)) {
        $j_diario = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$tJD}
            WHERE escola_id=%d AND aluno_id=%d
            ORDER BY data_registo DESC, id DESC
            LIMIT 5
        ", $eid, $aluno_id));
    }
    if (sige_aluno_portal_table_exists($tJS)) {
        $j_saude = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$tJS}
            WHERE escola_id=%d AND aluno_id=%d
            ORDER BY data_registo DESC, id DESC
            LIMIT 5
        ", $eid, $aluno_id));
    }
    if (sige_aluno_portal_table_exists($tJP)) {
        $j_presencas = $wpdb->get_results($wpdb->prepare("
            SELECT * FROM {$tJP}
            WHERE escola_id=%d AND aluno_id=%d
            ORDER BY data_registo DESC, id DESC
            LIMIT 8
        ", $eid, $aluno_id));
    }
}

$docs = [
    ['label'=>'BI / Documento', 'field'=>'doc_bi_url', 'url'=> sige_aluno_portal_doc_url((int)$aluno_id, 'doc_bi_url', $aluno->doc_bi_url ?? '')],
    ['label'=>'Certidão', 'field'=>'doc_cert_url', 'url'=> sige_aluno_portal_doc_url((int)$aluno_id, 'doc_cert_url', $aluno->doc_cert_url ?? '')],
    ['label'=>'Boletim de Vacinas', 'field'=>'doc_vacina_url', 'url'=> sige_aluno_portal_doc_url((int)$aluno_id, 'doc_vacina_url', $aluno->doc_vacina_url ?? '')],
];

$base_url = admin_url('admin.php?page=sige-app&view=aluno_portal&aluno_id=' . $aluno_id);

// v12.10.125 - Alteração de senha pelo aluno/encarregado.
// Esta é a única acção POST permitida dentro da Página do Aluno e afecta apenas
// a senha do utilizador autenticado, nunca dados académicos, financeiros ou do aluno.
$ap_password_msg = isset($_GET['pass_msg']) ? sanitize_key((string)$_GET['pass_msg']) : '';
$ap_password_error = isset($_GET['pass_error']) ? sanitize_key((string)$_GET['pass_error']) : '';
$ap_password_messages = [
    'ok' => 'Senha alterada com sucesso.',
    'nonce' => 'Sessão expirada. Actualize a página e tente novamente.',
    'denied' => 'Esta acção está disponível apenas para aluno ou encarregado.',
    'rate' => 'Foram feitas muitas tentativas. Aguarde alguns minutos e tente novamente.',
    'current' => 'A senha actual não está correcta.',
    'confirm' => 'A confirmação não corresponde à nova senha.',
    'same' => 'A nova senha deve ser diferente da senha actual.',
    'empty' => 'Preencha a senha actual, a nova senha e a confirmação.',
    'min_length' => 'A nova senha deve ter pelo menos 8 caracteres.',
    'max_length' => 'A nova senha é demasiado longa. Use no máximo 128 caracteres.',
    'letters_numbers' => 'A nova senha deve combinar letras e números. Exemplo: Escola2026.',
    'contains_login' => 'A nova senha não deve conter o nome de utilizador.',
    'contains_email' => 'A nova senha não deve conter parte do e-mail.',
    'policy' => 'A nova senha não cumpre as condições mínimas de segurança. Use pelo menos 8 caracteres, com letras e números.',
    'unknown' => 'Não foi possível alterar a senha. Tente novamente.',
    'handler' => 'A página foi actualizada para processar a senha com mais segurança. Volte a submeter a alteração.',
];

// v12.10.126 - segurança: a alteração de senha já não é processada dentro da view.
// O processamento real acontece em admin-post.php?action=sige_aluno_portal_change_password,
// antes do App Shell renderizar, para evitar tela branca e garantir feedback claro.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_aluno_portal_action']) && sanitize_key((string)$_POST['sige_aluno_portal_action']) === 'change_password') {
    $ap_password_msg = 'error';
    $ap_password_error = 'handler';
}

$portal_must_change_password = false;
if (!empty($is_portal_user_context)) {
    foreach (['sige_portal_force_password_change','sige_password_must_change','sige_primeiro_acesso_pendente','sige_forcar_troca_senha'] as $force_key) {
        if ((int)get_user_meta(get_current_user_id(), $force_key, true) === 1) {
            $portal_must_change_password = true;
            break;
        }
    }
    if ($portal_must_change_password && $secao !== 'senha') {
        $secao = 'senha';
    }
}

$menu = [
    'resumo' => ['Resumo', 'user'],
    'academico' => [$is_preescolar ? 'Jardim' : 'Académico', $is_preescolar ? 'heart' : 'book'],
    'financeiro' => ['Financeiro', 'wallet'],
    'documentos' => ['Documentos', 'file'],
    'contactos' => ['Contactos', 'phone'],
];
if (!empty($is_portal_user_context)) {
    $menu['senha'] = ['Alterar senha', 'shield'];
}
if (!isset($menu[$secao])) $secao = 'resumo';

$portal_sidebar_nav_html = '';
if (!empty($is_portal_user_context)) {
    ob_start();
    ?>
    <div class="sg-student-sidebar-nav" aria-label="Menu da Página do Aluno">
        <div class="sg-student-sidebar-title">
            <?php echo sige_aluno_portal_icon('user'); ?>
            <span>Página do Aluno</span>
        </div>
        <div class="sg-student-sidebar-links" role="navigation" aria-label="Menu da Página do Aluno">
            <?php foreach ($menu as $key => $item): ?>
                <a class="<?php echo $secao === $key ? 'active' : ''; ?>" href="<?php echo esc_url($base_url . '&secao=' . $key); ?>">
                    <?php echo sige_aluno_portal_icon($item[1]); ?>
                    <span><?php echo esc_html($item[0]); ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    $portal_sidebar_nav_html = trim((string)ob_get_clean());
}

?>
<style id="sige-aluno-portal-v1210119">
body.sige-admin-app.sige-view-aluno_portal .sg-product-page-head{display:none!important}
body.sige-admin-app.sige-view-aluno_portal .sg-app-page{max-width:none!important;width:100%!important;padding-top:0!important;overflow-x:hidden!important}
body.sige-admin-app.sige-view-aluno_portal .sg-app-content{padding-left:30px!important;padding-right:30px!important;overflow-x:hidden!important}
.sige-aluno-portal{
    --ap-blue:var(--sg-theme-primary,var(--color-brand-500));--ap-blue-dark:var(--sg-theme-primary-800,var(--color-ink-700));--ap-purple:var(--color-brand-500);--ap-ink:var(--color-black);--ap-muted:var(--color-slate-600);--ap-line:var(--color-ink-100);--ap-green:var(--color-success-500);--ap-red:var(--color-danger-500);--ap-amber:var(--color-warning-500);
    width:100%;max-width:none;margin:0;padding:0 0 34px;display:flex;flex-direction:column;gap:var(--space-5);color:var(--ap-ink);font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,sans-serif);
}
.sige-aluno-portal *{box-sizing:border-box}
.sige-aluno-portal svg{width:18px;height:18px;display:block;stroke:currentColor;color:currentColor;fill:none}

/* HERO - alinhado ao modelo do Painel Principal */
.ap-hero{
    position:relative;overflow:hidden;min-height:260px;border-radius:var(--radius-xl);background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 48%,var(--color-info-50) 100%);
    border:1px solid rgba(92,64,187,.12);box-shadow:var(--shadow-lg);
    padding:30px;display:grid;grid-template-columns:minmax(0,1fr) minmax(330px,.46fr);gap:28px;align-items:center;
}
.ap-hero:before{
    content:"";position:absolute;right:-120px;bottom:-160px;width:520px;height:360px;border-radius:var(--radius-pill);
    background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 68%);pointer-events:none;
}
.ap-hero:after{
    content:"";position:absolute;right:46px;top:38px;width:120px;height:120px;border-radius:var(--radius-pill);
    background:rgba(255,255,255,.45);filter:blur(.2px);pointer-events:none;
}
.ap-hero-main{position:relative;z-index:1;min-width:0}
.ap-kicker{
    display:inline-flex;align-items:center;gap:9px;color:var(--ap-blue);font-size:12px;font-weight:700;text-transform:uppercase;
    letter-spacing:.14em;margin-bottom:16px;
}
.ap-kicker svg{width:20px;height:20px}
.ap-identity-row{display:grid;grid-template-columns:118px minmax(0,1fr);gap:22px;align-items:center}
.ap-photo{
    width:118px;height:118px;border-radius:var(--radius-xl);object-fit:cover;background:var(--color-slate-50);border:6px solid var(--color-white);
    box-shadow:var(--shadow-md);
}
.ap-identity-copy{min-width:0}
.ap-hero h1{
    margin:0;color:var(--color-black);font-size:40px;line-height:1.03;font-weight:700;letter-spacing:-.055em;
}
.ap-sub{margin:var(--space-3) 0 0;color:var(--color-slate-700);font-size:15px;line-height:1.62;font-weight:600;max-width:760px}
.ap-chips{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}
.ap-chip{
    display:inline-flex;align-items:center;gap:7px;min-height:34px;padding:7px 12px;border-radius:var(--radius-pill);background:var(--color-white);border:1px solid var(--color-slate-100);
    color:var(--color-slate-700);font-size:12px;font-weight:700;box-shadow:var(--shadow-sm);
}
.ap-chip svg{width:16px;height:16px}
.ap-chip.ok{color:var(--color-success-900);background:var(--color-success-50);border-color:var(--color-success-200)}
.ap-chip.warn{color:var(--color-warning-800);background:var(--color-warning-50);border-color:var(--color-warning-200)}
.ap-hero-panel{
    position:relative;z-index:1;min-height:152px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.20));
    padding:22px;border:1px solid rgba(92,64,187,.10);display:flex;flex-direction:column;justify-content:center;gap:10px;
}
.ap-panel-label{display:flex;align-items:center;gap:var(--space-2);color:var(--ap-purple);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.12em}
.ap-panel-value{font-size:34px;font-weight:700;color:var(--color-ink-900);letter-spacing:-.045em;line-height:1}
.ap-panel-text{color:var(--color-slate-600);font-size:var(--fs-sm);font-weight:600;line-height:1.55}
.ap-actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:20px}
.ap-btn{
    min-height:45px;display:inline-flex;align-items:center;justify-content:center;gap:9px;padding:0 var(--space-4);border-radius:var(--radius-lg);border:1px solid var(--color-ink-100);
    background:var(--color-white);color:var(--color-ink-900);text-decoration:none;font-size:var(--fs-sm);font-weight:700;box-shadow:var(--shadow-sm);cursor:pointer;
}
.ap-btn:hover{transform:translateY(-1px);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.ap-btn.primary{background:linear-gradient(135deg,var(--ap-blue),var(--ap-blue-dark));color:var(--color-white);border-color:var(--ap-blue);box-shadow:0 2px 8px rgba(15,23,42,.06)}
.ap-readonly{
    display:inline-flex;align-items:center;gap:var(--space-2);min-height:42px;padding:0 14px;border-radius:var(--radius-md);background:var(--sg-theme-soft,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-500));
    border:1px solid var(--sg-theme-soft,var(--color-brand-50));font-size:var(--fs-sm);font-weight:700;
}

/* KPIs - mesmo espírito visual dos cartões do Painel Principal */
.ap-kpis{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px}
.ap-kpi{
    min-height:118px;display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-4);align-items:center;background:var(--color-white);border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);padding:19px;box-shadow:var(--shadow-md);position:relative;overflow:hidden;
}
.ap-kpi:after{content:"";position:absolute;right:-38px;top:-46px;width:115px;height:115px;border-radius:var(--radius-pill);background:var(--soft,var(--color-brand-50));opacity:.72}
.ap-kpi-icon{
    position:relative;z-index:1;width:56px;height:56px;border-radius:var(--radius-lg);background:var(--soft,var(--color-brand-50));color:var(--c,var(--color-brand-500));
    display:flex!important;align-items:center!important;justify-content:center!important;place-items:center!important;text-align:center!important;line-height:0!important;
}
.ap-kpi-icon svg{width:24px;height:24px;margin:auto!important;position:static!important;top:auto!important;left:auto!important;display:block!important;transform:none!important}
.ap-kpi div{position:relative;z-index:1}
.ap-kpi strong{display:block;font-size:28px;line-height:1;font-weight:700;color:var(--color-black);letter-spacing:-.04em}
.ap-kpi span{display:block;margin-top:7px;font-size:12px;color:var(--color-slate-600);font-weight:700}

/* Layout interno */
.ap-layout{display:grid;grid-template-columns:280px minmax(0,1fr);gap:var(--space-5);align-items:start}
.ap-sidebar{
    background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);
    padding:var(--space-3);position:sticky;top:18px;
}
.ap-menu{display:grid;gap:8px}
.ap-menu a{
    display:flex;align-items:center;gap:11px;min-height:50px;padding:0 15px;border-radius:var(--radius-lg);text-decoration:none;color:var(--color-slate-700);font-size:var(--fs-sm);font-weight:700;
}
.ap-menu a svg{width:18px;height:18px}
.ap-menu a:hover{background:var(--color-slate-50);color:var(--ap-purple)}
.ap-menu a.active{background:linear-gradient(135deg,var(--ap-blue),var(--ap-blue-dark));color:var(--color-white);box-shadow:0 2px 8px rgba(15,23,42,.06)}

.ap-card{
    background:var(--color-white);border:1px solid rgba(28,32,54,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);overflow:hidden;
}
.ap-card-head{
    min-height:68px;padding:18px 20px;border-bottom:1px solid var(--color-slate-100);background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 72%,var(--color-slate-50) 100%);
    display:flex;align-items:center;justify-content:space-between;gap:var(--space-3);flex-wrap:wrap;
}
.ap-card-title{display:flex;align-items:center;gap:11px;font-size:var(--fs-md);font-weight:700;color:var(--color-ink-500)}
.ap-card-title svg{width:20px;height:20px;color:var(--ap-blue)}
.ap-card-body{padding:21px}
.ap-grid-2{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:20px}
.ap-grid-3{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:16px}
.ap-info-list{display:grid;gap:0}
.ap-info-row{display:grid;grid-template-columns:190px minmax(0,1fr);gap:14px;padding:15px 0;border-bottom:1px solid var(--color-ink-50)}
.ap-info-row:last-child{border-bottom:0}
.ap-label{color:var(--color-slate-600);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.07em}
.ap-value{font-size:var(--fs-sm);color:var(--color-ink-500);font-weight:600;word-break:break-word}
.ap-table-wrap{overflow-x:auto}
.ap-table{width:100%;border-collapse:separate;border-spacing:0;min-width:780px}
.ap-table th{padding:12px 14px;background:var(--color-slate-50);color:var(--color-slate-600);font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.06em;text-align:left;border-bottom:1px solid var(--color-slate-100)}
.ap-table td{padding:13px 14px;border-bottom:1px solid var(--color-slate-100);color:var(--color-ink-800);font-size:var(--fs-sm);font-weight:600}
.ap-table tr:last-child td{border-bottom:0}
.ap-badge{display:inline-flex;align-items:center;min-height:28px;padding:0 9px;border-radius:var(--radius-pill);background:var(--color-ink-50);color:var(--color-slate-700);font-size:12px;font-weight:700}
.ap-badge.ok{background:var(--color-success-50);color:var(--color-success-900)}.ap-badge.warn{background:var(--color-warning-50);color:var(--color-warning-800)}.ap-badge.err{background:var(--color-danger-50);color:var(--color-danger-700)}
.ap-empty{padding:28px;text-align:center;color:var(--color-slate-500);font-size:var(--fs-sm);font-weight:600;background:var(--color-slate-50);border:1px dashed var(--color-ink-200);border-radius:16px}
.ap-lock{padding:28px;border-radius:var(--radius-xl);background:var(--color-warning-50);border:1px solid var(--color-warning-200);color:var(--color-warning-800);display:grid;grid-template-columns:auto minmax(0,1fr);gap:14px;align-items:flex-start;box-shadow:0 4px 16px rgba(15,23,42,.08)}
.ap-lock-icon{width:48px;height:48px;border-radius:var(--radius-lg);background:var(--color-warning-100);color:var(--color-warning-500);display:flex;align-items:center;justify-content:center}
.ap-lock-icon svg{width:24px;height:24px}.ap-lock strong{display:block;color:var(--color-warning-900);font-size:var(--fs-md);font-weight:700;margin:0 0 6px}.ap-lock p{margin:0;color:var(--color-warning-800);font-size:var(--fs-sm);line-height:1.55;font-weight:600}
.ap-timeline{display:grid;gap:12px}.ap-event{border:1px solid var(--color-slate-100);background:var(--color-white);border-radius:var(--radius-lg);padding:14px}.ap-event-date{font-size:12px;color:var(--sg-theme-primary,var(--color-brand-500));font-weight:700;margin-bottom:6px}.ap-event-text{font-size:var(--fs-sm);color:var(--color-ink-800);line-height:1.5}
.ap-alert{display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-3);align-items:flex-start;border-radius:var(--radius-lg);padding:15px 16px;font-size:var(--fs-sm);font-weight:700;line-height:1.5;margin-bottom:16px}
.ap-alert svg{width:20px;height:20px;margin-top:1px}
.ap-alert.ok{background:var(--color-success-50);border:1px solid var(--color-success-200);color:var(--color-success-900)}
.ap-alert.error{background:var(--color-danger-50);border:1px solid var(--color-danger-200);color:var(--color-danger-700)}
.ap-password-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:var(--space-5);align-items:start}
.ap-password-form{display:grid;gap:15px}
.ap-field{display:grid;gap:7px}
.ap-field span{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.06em;color:var(--color-slate-600)}
.ap-field input{width:100%;min-height:48px;border:1px solid var(--color-ink-100);border-radius:var(--radius-lg);background:var(--color-white);color:var(--color-black);font-size:var(--fs-base);font-weight:600;padding:0 14px;outline:none}
.ap-field input:focus{border-color:var(--sg-theme-primary,var(--color-brand-500));box-shadow:0 1px 2px rgba(15,23,42,.04)}
.ap-rules{background:var(--color-slate-50);border:1px solid var(--color-slate-100);border-radius:var(--radius-xl);padding:18px}
.ap-rules h3{margin:0 0 var(--space-3);color:var(--color-ink-500);font-size:15px;font-weight:700}
.ap-rules ul{margin:0;padding-left:18px;color:var(--color-slate-600);font-size:var(--fs-sm);font-weight:600;line-height:1.75}
.ap-rules li::marker{color:var(--sg-theme-primary,var(--color-brand-500))}
.ap-password-required > .ap-hero,
.ap-password-required > .ap-kpis,
.ap-password-required > .ap-layout{
    display:none!important;
}
.ap-global-alert{
    margin-bottom:0!important;
}
.ap-first-access-gate{
    min-height:calc(100vh - 170px);
    display:grid;
    place-items:center;
    padding:28px 16px;
}
.ap-first-access-card{
    width:min(100%,680px);
    background:var(--color-white);
    border:1px solid rgba(28,32,54,.08);
    border-radius:var(--radius-xl);
    box-shadow:var(--shadow-lg);
    padding:30px;
    position:relative;
    overflow:hidden;
}
.ap-first-access-card:before{
    content:"";
    position:absolute;
    right:-70px;
    top:-90px;
    width:240px;
    height:240px;
    border-radius:var(--radius-pill);
    background:rgba(90,63,214,.10);
    pointer-events:none;
}
.ap-first-access-icon{
    width:70px;
    height:70px;
    border-radius:var(--radius-xl);
    background:var(--sg-theme-soft,var(--color-brand-50));
    color:var(--sg-theme-primary,var(--color-brand-500));
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    margin-bottom:18px;
    position:relative;
    z-index:1;
}
.ap-first-access-icon svg{
    width:32px!important;
    height:32px!important;
    margin:auto!important;
    position:static!important;
}
.ap-first-access-card h1{
    margin:0;
    color:var(--color-black);
    font-size:34px;
    line-height:1.08;
    font-weight:700;
    letter-spacing:-.045em;
    position:relative;
    z-index:1;
}
.ap-first-access-card p{
    margin:var(--space-3) 0 var(--space-5);
    color:var(--color-slate-700);
    font-size:15px;
    line-height:1.65;
    font-weight:600;
    position:relative;
    z-index:1;
}
.ap-first-access-form{
    position:relative;
    z-index:1;
}
.ap-first-access-submit{
    width:100%;
    margin-top:2px;
}
.ap-first-access-rules{
    margin-top:18px;
    border-radius:var(--radius-lg);
    background:var(--color-slate-50);
    border:1px solid var(--color-slate-100);
    padding:14px 16px;
    display:grid;
    gap:5px;
    color:var(--color-slate-500);
    font-size:var(--fs-sm);
    line-height:1.5;
    font-weight:600;
    position:relative;
    z-index:1;
}
.ap-first-access-rules strong{
    color:var(--color-ink-500);
    font-size:var(--fs-sm);
    font-weight:700;
}
.ap-kpi-icon,
.ap-lock-icon,
.ap-panel-label svg,
.ap-card-title svg,
.ap-chip svg{
    flex-shrink:0!important;
}
.ap-lock-icon,
.ap-first-access-icon{
    display:flex!important;
    align-items:center!important;
    justify-content:center!important;
    line-height:0!important;
}
.ap-lock-icon svg{
    margin:auto!important;
    position:static!important;
}
.sige-aluno-portal-empty{background:var(--color-white);border:1px dashed var(--color-ink-200);border-radius:var(--radius-xl);padding:42px;text-align:center;box-shadow:var(--shadow-md);color:var(--color-slate-500)}

/* Contexto de aluno/encarregado: não deve parecer área administrativa incompleta */
body.sige-admin-app.sige-view-aluno_portal .sg-app-sidebar .sg-section-title,
body.sige-admin-app.sige-view-aluno_portal .sg-app-sidebar .sg-nav-group,
body.sige-admin-app.sige-view-aluno_portal .sg-app-sidebar nav{
    <?php if (!empty($is_portal_user_context)): ?>display:none!important;<?php endif; ?>
}

/* v12.10.120 - Menu da Página do Aluno na lateral azul */
<?php if (!empty($is_portal_user_context)): ?>
body.sige-admin-app.sige-view-aluno_portal .sg-app-sidebar{
    display:flex!important;
    flex-direction:column!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-app-sidebar:after{
    content:none!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-student-sidebar-nav{
    display:block!important;
    margin:22px 18px 0!important;
    padding-top:18px!important;
    border-top:1px solid rgba(255,255,255,.13)!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-student-sidebar-title{
    display:flex!important;
    align-items:center!important;
    gap:9px!important;
    color:rgba(255,255,255,.62)!important;
    font-size:10px!important;
    font-weight:700!important;
    text-transform:uppercase!important;
    letter-spacing:.14em!important;
    margin:0 0 var(--space-3)!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-student-sidebar-title svg{
    width:16px!important;
    height:16px!important;
    color:rgba(255,255,255,.72)!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-student-sidebar-links{
    display:grid!important;
    gap:var(--space-2)!important;
    visibility:visible!important;
    opacity:1!important;
}
#sige-sidebar .sg-student-sidebar-nav,
#sige-sidebar .sg-student-sidebar-links{
    display:grid!important;
    visibility:visible!important;
    opacity:1!important;
}
#sige-sidebar .sg-student-sidebar-nav{
    display:block!important;
}
#sige-sidebar .sg-student-sidebar-nav a,
#sige-sidebar .sg-student-sidebar-nav a[href*="view=aluno_portal"]{
    min-height:46px!important;
    display:flex!important;
    align-items:center!important;
    gap:11px!important;
    padding:0 14px!important;
    border-radius:var(--radius-lg)!important;
    color:rgba(255,255,255,.86)!important;
    text-decoration:none!important;
    font-size:var(--fs-sm)!important;
    font-weight:700!important;
    border:1px solid transparent!important;
    transition:background .16s ease, color .16s ease, transform .16s ease!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-student-sidebar-nav a svg{
    width:18px!important;
    height:18px!important;
    color:currentColor!important;
    flex:0 0 auto!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-student-sidebar-nav a:hover{
    background:rgba(255,255,255,.10)!important;
    color:var(--color-white)!important;
    transform:translateX(1px)!important;
}
body.sige-admin-app.sige-view-aluno_portal .sg-student-sidebar-nav a.active{
    background:var(--color-white)!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    box-shadow:var(--shadow-md);
}
#sige-sidebar .sg-student-sidebar-nav a.active,
#sige-sidebar .sg-student-sidebar-nav a.active[href*="view=aluno_portal"],
body.sige-admin-app.sige-view-aluno_portal #sige-sidebar .sg-student-sidebar-nav a.active{
    background:var(--color-white)!important;
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    border-color:var(--color-white)!important;
    box-shadow:var(--shadow-md);
}
#sige-sidebar .sg-student-sidebar-nav a.active span,
#sige-sidebar .sg-student-sidebar-nav a.active svg,
body.sige-admin-app.sige-view-aluno_portal #sige-sidebar .sg-student-sidebar-nav a.active span,
body.sige-admin-app.sige-view-aluno_portal #sige-sidebar .sg-student-sidebar-nav a.active svg{
    color:var(--sg-theme-primary,var(--color-brand-500))!important;
    stroke:var(--sg-theme-primary,var(--color-brand-500))!important;
    opacity:1!important;
    visibility:visible!important;
    display:inline-flex!important;
}
.ap-fin-filter{
    display:grid;grid-template-columns:repeat(2,minmax(160px,220px)) auto auto;gap:10px;align-items:end;margin:0;
}
.ap-fin-field{display:grid;gap:6px}.ap-fin-field span{font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--color-slate-600)}
.ap-fin-field input{height:42px;border-radius:var(--radius-md);border:1px solid var(--color-ink-100);background:var(--color-white);padding:0 var(--space-3);font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-500)}
.ap-receipt-link{display:inline-flex;align-items:center;justify-content:center;min-height:30px;padding:0 10px;border-radius:var(--radius-pill);background:var(--sg-theme-soft,var(--color-brand-50));color:var(--sg-theme-primary,var(--color-brand-500))!important;border:1px solid var(--sg-theme-soft,var(--color-brand-50));text-decoration:none;font-size:12px;font-weight:700;white-space:nowrap}
.ap-receipt-link:hover{background:var(--sg-theme-primary,var(--color-brand-500));color:var(--color-white)!important;border-color:var(--sg-theme-primary,var(--color-brand-500))}
.ap-muted-note{color:var(--color-slate-600);font-size:12px;font-weight:600;line-height:1.45}
@media(max-width:760px){.ap-fin-filter{grid-template-columns:1fr}.ap-fin-filter .ap-btn{width:100%}}
body.sige-admin-app.sige-view-aluno_portal .ap-layout-portal{
    grid-template-columns:minmax(0,1fr)!important;
}
body.sige-admin-app.sige-view-aluno_portal .ap-layout-portal .ap-content{
    min-width:0!important;
}
<?php endif; ?>

@media(max-width:1280px){
    .ap-hero{grid-template-columns:minmax(0,1fr)}
    .ap-hero-panel{max-width:none}
    .ap-kpis{grid-template-columns:repeat(2,minmax(0,1fr))}
    .ap-grid-3{grid-template-columns:1fr 1fr}
}
@media(max-width:980px){
    body.sige-admin-app.sige-view-aluno_portal .sg-app-content{padding-left:18px!important;padding-right:18px!important}
    .ap-layout{grid-template-columns:1fr}
    .ap-sidebar{position:static}
    .ap-menu{grid-template-columns:repeat(2,minmax(0,1fr))}
    .ap-grid-2{grid-template-columns:1fr}
}
@media(max-width:900px){
    .ap-password-layout{grid-template-columns:1fr!important}
    .ap-rules{order:2}
}
@media(max-width:760px){
    body.sige-admin-app.sige-view-aluno_portal .sg-app-content{padding-left:14px!important;padding-right:14px!important}
    .ap-first-access-gate{min-height:calc(100vh - 110px);padding:14px 0 28px}
    .ap-first-access-card{border-radius:var(--radius-xl);padding:22px}
    .ap-first-access-card h1{font-size:26px}
    .ap-first-access-card p{font-size:14px}
    .ap-first-access-icon{width:58px;height:58px;border-radius:var(--radius-lg);margin-bottom:14px}
    .ap-first-access-icon svg{width:27px!important;height:27px!important}
    .ap-hero{padding:22px;min-height:0}
    .ap-identity-row{grid-template-columns:1fr}
    .ap-photo{width:96px;height:96px}
    .ap-hero h1{font-size:28px}
    .ap-menu,.ap-kpis,.ap-grid-3{grid-template-columns:1fr}
    .ap-info-row{grid-template-columns:1fr;gap:5px}
    .ap-actions .ap-btn,.ap-readonly{width:100%;justify-content:center}
}
</style>

<div class="sige-aluno-portal <?php echo !empty($portal_must_change_password) ? 'ap-password-required' : ''; ?>">
    <?php if ($ap_password_msg === 'ok' && $secao !== 'senha'): ?>
        <div class="ap-alert ok ap-global-alert"><?php echo sige_aluno_portal_icon('check'); ?><div><?php echo esc_html($ap_password_messages['ok']); ?></div></div>
    <?php endif; ?>

    <?php if (!empty($portal_must_change_password)): ?>
        <section class="ap-first-access-gate" aria-labelledby="ap-first-access-title">
            <div class="ap-first-access-card">
                <div class="ap-first-access-icon"><?php echo sige_aluno_portal_icon('shield'); ?></div>
                <div class="ap-kicker"><?php echo sige_aluno_portal_icon('user'); ?><span>Primeiro acesso</span></div>
                <h1 id="ap-first-access-title">Crie a sua senha pessoal</h1>
                <p>Por segurança, a senha recebida da secretaria é provisória. Antes de entrar na Página do Aluno, defina uma nova senha que só você conhece.</p>

                <?php if ($ap_password_msg === 'error'): ?>
                    <div class="ap-alert error"><?php echo sige_aluno_portal_icon('alert'); ?><div><?php echo esc_html($ap_password_messages[$ap_password_error] ?? $ap_password_messages['unknown']); ?></div></div>
                <?php endif; ?>

                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-password-form ap-first-access-form" data-portal-allow-submit="1" autocomplete="off">
                    <input type="hidden" name="action" value="sige_aluno_portal_change_password">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($base_url . '&secao=resumo'); ?>">
                    <?php wp_nonce_field('sige_aluno_portal_change_password', 'sige_aluno_portal_nonce'); ?>

                    <label class="ap-field">
                        <span>Senha actual recebida da secretaria</span>
                        <input type="password" name="current_password" autocomplete="current-password" required>
                    </label>

                    <label class="ap-field">
                        <span>Nova senha</span>
                        <input type="password" name="new_password" autocomplete="new-password" minlength="8" maxlength="128" required>
                    </label>

                    <label class="ap-field">
                        <span>Confirmar nova senha</span>
                        <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="128" required>
                    </label>

                    <button class="ap-btn primary ap-first-access-submit" type="submit"><?php echo sige_aluno_portal_icon('shield'); ?> Guardar e entrar no portal</button>
                </form>

                <div class="ap-first-access-rules">
                    <strong>Condições da nova senha</strong>
                    <span>Mínimo de 8 caracteres, com letras e números. Não use o nome de utilizador nem parte do e-mail.</span>
                </div>
            </div>
        </section>
    <?php endif; ?>

    <section class="ap-hero">
        <div class="ap-hero-main">
            <div class="ap-kicker"><?php echo sige_aluno_portal_icon('user'); ?><span>Página do Aluno</span></div>
            <div class="ap-identity-row">
                <img class="ap-photo" src="<?php echo esc_url($foto); ?>" alt="<?php echo esc_attr($aluno->nome_completo); ?>">
                <div class="ap-identity-copy">
                    <h1><?php echo esc_html($aluno->nome_completo); ?></h1>
                    <p class="ap-sub">Acompanhe aqui os dados essenciais do aluno, a turma, a situação financeira, documentos e contactos da família.</p>
                </div>
            </div>
            <div class="ap-chips">
                <span class="ap-chip <?php echo esc_attr($status_class); ?>"><?php echo sige_aluno_portal_icon($status_class === 'ok' ? 'check' : 'alert'); ?> <?php echo esc_html($status_label); ?></span>
                <span class="ap-chip"><?php echo sige_aluno_portal_icon('calendar'); ?> Ano <?php echo esc_html((string)$ano_aluno); ?></span>
                <span class="ap-chip"><?php echo sige_aluno_portal_icon('users'); ?> <?php echo esc_html($turma_label ?: 'Sem turma'); ?></span>
                <span class="ap-chip"><?php echo sige_aluno_portal_icon($is_preescolar ? 'heart' : 'book'); ?> <?php echo esc_html($nivel_label); ?></span>
                <span class="ap-chip"><?php echo sige_aluno_portal_icon('shield'); ?> Modo leitura</span>
                <?php if (!empty($portal_must_change_password)): ?><span class="ap-chip warn"><?php echo sige_aluno_portal_icon('alert'); ?> Senha provisória</span><?php endif; ?>
            </div>
            <div class="ap-actions">
                <?php if ($is_staff_context): ?>
                    <a class="ap-btn" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=alunos_lista')); ?>"><?php echo sige_aluno_portal_icon('arrow-left'); ?> Voltar aos alunos</a>
                <?php else: ?>
                    <span class="ap-readonly"><?php echo sige_aluno_portal_icon('shield'); ?> Portal em modo leitura</span>
                <?php endif; ?>
                <?php if ($is_staff_context): ?>
                    <a class="ap-btn primary" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=aluno_contas&aluno_id=' . $aluno_id)); ?>"><?php echo sige_aluno_portal_icon('wallet'); ?> Conta do aluno</a>
                    <?php if (!$bloquear_notas_por_divida && $is_preescolar && $turma_id): ?>
                        <a class="ap-btn" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=jardim_diario&turma_id=' . $turma_id)); ?>"><?php echo sige_aluno_portal_icon('heart'); ?> Jardim</a>
                    <?php elseif (!$bloquear_notas_por_divida && $turma_id): ?>
                        <a class="ap-btn" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=notas&turma_id=' . $turma_id)); ?>"><?php echo sige_aluno_portal_icon('book'); ?> Notas</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
        <aside class="ap-hero-panel">
            <div class="ap-panel-label"><?php echo sige_aluno_portal_icon('shield'); ?><span>Estado geral</span></div>
            <div class="ap-panel-value"><?php echo esc_html($fin_divida > 0 ? sige_aluno_portal_money($fin_divida) : 'Regular'); ?></div>
            <div class="ap-panel-text"><?php echo $fin_divida > 0 ? 'Há pendência financeira. As notas e boletins ficam ocultos no portal até regularização.' : 'Sem dívida financeira registada neste momento.'; ?></div>
        </aside>
    </section>

    <section class="ap-kpis">
        <div class="ap-kpi" style="--c:var(--color-brand-500);--soft:var(--color-brand-50)"><span class="ap-kpi-icon"><?php echo sige_aluno_portal_icon('calendar'); ?></span><div><strong><?php echo esc_html($idade); ?></strong><span>Idade</span></div></div>
        <div class="ap-kpi" style="--c:var(--sg-theme-primary,#5a3fd6);--soft:var(--color-info-50)"><span class="ap-kpi-icon"><?php echo sige_aluno_portal_icon($bloquear_notas_por_divida ? 'shield' : 'book'); ?></span><div><strong><?php echo esc_html($bloquear_notas_por_divida ? 'Bloqueado' : ($media_geral !== null ? (string)$media_geral : ($is_preescolar ? 'Qualit.' : '-'))); ?></strong><span><?php echo $bloquear_notas_por_divida ? 'Notas/boletim' : ($is_preescolar ? 'Avaliação' : 'Média estimada'); ?></span></div></div>
        <div class="ap-kpi" style="--c:var(--color-success-500);--soft:var(--color-success-50)"><span class="ap-kpi-icon"><?php echo sige_aluno_portal_icon('check'); ?></span><div><strong><?php echo esc_html(sige_aluno_portal_money($fin_pago)); ?></strong><span>Total pago</span></div></div>
        <div class="ap-kpi" style="--c:var(--color-danger-500);--soft:var(--color-danger-50)"><span class="ap-kpi-icon"><?php echo sige_aluno_portal_icon('wallet'); ?></span><div><strong><?php echo esc_html(sige_aluno_portal_money($fin_vencido)); ?></strong><span>Vencido</span></div></div>
    </section>

    <section class="ap-layout <?php echo !empty($is_portal_user_context) ? 'ap-layout-portal' : ''; ?>">
        <?php if (empty($is_portal_user_context)): ?>
            <aside class="ap-sidebar">
                <nav class="ap-menu" aria-label="Menu do aluno">
                    <?php foreach ($menu as $key => $item): ?>
                        <a class="<?php echo $secao === $key ? 'active' : ''; ?>" href="<?php echo esc_url($base_url . '&secao=' . $key); ?>">
                            <?php echo sige_aluno_portal_icon($item[1]); ?> <?php echo esc_html($item[0]); ?>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </aside>
        <?php endif; ?>

        <main class="ap-content">
            <?php if ($secao === 'resumo'): ?>
                <div class="ap-grid-2">
                    <article class="ap-card">
                        <div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('user'); ?> Identificação</div></div>
                        <div class="ap-card-body">
                            <div class="ap-info-list">
                                <div class="ap-info-row"><div class="ap-label">Processo</div><div class="ap-value"><?php echo esc_html($aluno->numero_processo ?: '-'); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Nascimento</div><div class="ap-value"><?php echo sige_aluno_portal_date($aluno->data_nascimento); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Género</div><div class="ap-value"><?php echo esc_html($aluno->genero ?: '-'); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Naturalidade</div><div class="ap-value"><?php echo esc_html($aluno->naturalidade ?: '-'); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Documento</div><div class="ap-value"><?php echo esc_html(trim(($aluno->tipo_documento ?: '') . ' ' . ($aluno->documento_nr ?: '')) ?: '-'); ?></div></div>
                            </div>
                        </div>
                    </article>
                    <article class="ap-card">
                        <div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('book'); ?> Matrícula actual</div></div>
                        <div class="ap-card-body">
                            <div class="ap-info-list">
                                <div class="ap-info-row"><div class="ap-label">Turma</div><div class="ap-value"><?php echo esc_html($turma_label); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Classe/Nível</div><div class="ap-value"><?php echo esc_html($classe_label ?: '-'); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Turno</div><div class="ap-value"><?php echo esc_html($matricula->turno ?? '-'); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Ano lectivo</div><div class="ap-value"><?php echo esc_html((string)$ano_aluno); ?></div></div>
                                <div class="ap-info-row"><div class="ap-label">Matrícula</div><div class="ap-value"><span class="ap-badge <?php echo (isset($matricula->status_matricula) && in_array(strtolower((string)$matricula->status_matricula), ['activa','ativa','activo','ativo'], true)) ? 'ok' : 'warn'; ?>"><?php echo esc_html($matricula->status_matricula ?? 'Sem matrícula'); ?></span></div></div>
                            </div>
                        </div>
                    </article>
                </div>
            <?php elseif ($secao === 'academico'): ?>
                <?php if ($bloquear_notas_por_divida): ?>
                    <article class="ap-lock">
                        <span class="ap-lock-icon"><?php echo sige_aluno_portal_icon('shield'); ?></span>
                        <div>
                            <strong>Consulta académica bloqueada por dívida</strong>
                            <p>Este portal não apresenta notas, avaliações ou boletins enquanto houver pendência financeira. A escola pode continuar a gerir notas e documentos nos módulos internos autorizados.</p>
                            <div class="ap-actions" style="margin-top:14px">
                                <a class="ap-btn primary" href="<?php echo esc_url($base_url . '&secao=financeiro'); ?>"><?php echo sige_aluno_portal_icon('wallet'); ?> Ver situação financeira</a>
                            </div>
                        </div>
                    </article>
                <?php elseif ($is_preescolar): ?>
                    <div class="ap-grid-3">
                        <a class="ap-card" style="text-decoration:none;color:inherit" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=jardim_diario&turma_id=' . $turma_id)); ?>"><div class="ap-card-body"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('heart'); ?> Diário</div><p class="ap-sub">Actividades, comportamento, sono e recados.</p></div></a>
                        <a class="ap-card" style="text-decoration:none;color:inherit" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=jardim_saude&turma_id=' . $turma_id)); ?>"><div class="ap-card-body"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('activity'); ?> Saúde</div><p class="ap-sub">Nutrição, febre, acidentes e observações.</p></div></a>
                        <a class="ap-card" style="text-decoration:none;color:inherit" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=jardim_boletim&turma_id=' . $turma_id)); ?>"><div class="ap-card-body"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('printer'); ?> Boletim</div><p class="ap-sub">Boletim qualitativo do pré-escolar.</p></div></a>
                    </div>
                    <div style="height:18px"></div>
                    <article class="ap-card">
                        <div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('heart'); ?> Últimos registos do Jardim</div></div>
                        <div class="ap-card-body ap-timeline">
                            <?php if ($j_diario): foreach ($j_diario as $r): ?>
                                <div class="ap-event"><div class="ap-event-date"><?php echo sige_aluno_portal_date($r->data_registo); ?></div><div class="ap-event-text"><?php echo esc_html(wp_trim_words((string)$r->actividades, 24, '…') ?: 'Registo diário sem descrição.'); ?> · Humor: <?php echo esc_html($r->humor ?: '-'); ?> · Sono: <?php echo ((string)$r->dormiu === '1') ? 'Dormiu' : (((string)$r->dormiu === '0') ? 'Não dormiu' : '-'); ?></div></div>
                            <?php endforeach; else: ?>
                                <div class="ap-empty">Ainda não existem registos recentes do Diário para este aluno.</div>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php else: ?>
                    <article class="ap-card">
                        <div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('book'); ?> Notas do aluno</div><span class="ap-badge"><?php echo esc_html((string)$disciplinas_count); ?> disciplina(s)</span></div>
                        <div class="ap-table-wrap">
                            <table class="ap-table">
                                <thead><tr><th>Disciplina</th><th>Trim.</th><th>AC</th><th>ACP</th><th>AT</th><th>Exame</th><th>Conselho</th><th>Estado</th></tr></thead>
                                <tbody>
                                <?php if ($notas_rows): foreach ($notas_rows as $n): ?>
                                    <tr>
                                        <td><?php echo esc_html($n->disciplina_nome ?: ('Disciplina #' . (int)$n->disciplina_id)); ?></td>
                                        <td><?php echo (int)$n->trimestre; ?>º</td>
                                        <td><?php echo esc_html($n->nota_ac !== null ? $n->nota_ac : '-'); ?></td>
                                        <td><?php echo esc_html($n->nota_acp !== null ? $n->nota_acp : '-'); ?></td>
                                        <td><?php echo esc_html($n->nota_at !== null ? $n->nota_at : '-'); ?></td>
                                        <td><?php echo esc_html($n->nota_exame !== null ? $n->nota_exame : '-'); ?></td>
                                        <td><?php echo esc_html($n->nota_conselho !== null ? $n->nota_conselho : '-'); ?></td>
                                        <td><span class="ap-badge <?php echo strtolower((string)$n->status)==='aprovado'?'ok':'warn'; ?>"><?php echo esc_html($n->status ?: '-'); ?></span></td>
                                    </tr>
                                <?php endforeach; else: ?>
                                    <tr><td colspan="8"><div class="ap-empty">Ainda não existem notas registadas para este aluno neste ano lectivo.</div></td></tr>
                                <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </article>
                <?php endif; ?>
            <?php elseif ($secao === 'financeiro'): ?>
                <div class="ap-grid-2">
                    <article class="ap-card"><div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('wallet'); ?> Resumo financeiro</div></div><div class="ap-card-body"><div class="ap-info-list">
                        <div class="ap-info-row"><div class="ap-label">Total lançado</div><div class="ap-value"><?php echo esc_html(sige_aluno_portal_money($fin_total)); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Total pago</div><div class="ap-value"><?php echo esc_html(sige_aluno_portal_money($fin_pago)); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Dívida</div><div class="ap-value"><?php echo esc_html(sige_aluno_portal_money($fin_divida)); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Vencido</div><div class="ap-value"><?php echo esc_html(sige_aluno_portal_money($fin_vencido)); ?></div></div>
                    </div></div></article>
                    <article class="ap-card"><div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('check'); ?> Pagamentos recentes</div><span class="ap-badge"><?php echo (int)$ap_pag_limit; ?> no máximo</span></div><div class="ap-card-body ap-timeline">
                        <?php if ($pag_recentes): foreach ($pag_recentes as $pg): ?>
                            <?php $pg_recibo_url = sige_aluno_portal_recibo_url($pg->id); ?>
                            <div class="ap-event">
                                <div class="ap-event-date"><?php echo sige_aluno_portal_date($pg->data_pagamento); ?></div>
                                <div class="ap-event-text">
                                    <?php echo esc_html(sige_aluno_portal_money($pg->valor_pago)); ?> · <?php echo esc_html(function_exists('sige_fin_metodo_pagamento_label') ? sige_fin_metodo_pagamento_label($pg->metodo_pagamento ?? '') : ($pg->metodo_pagamento ?: '-')); ?> · Recibo <?php echo esc_html($pg->recibo_numero ?: ('#' . (int)$pg->id)); ?>
                                    <?php if ($pg_recibo_url): ?>
                                        <div style="margin-top:9px"><a class="ap-receipt-link" href="<?php echo esc_url($pg_recibo_url); ?>" data-sige-act="sigeAbrirReciboJanela" data-sige-prevent>Baixar / imprimir recibo</a></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; else: ?><div class="ap-empty">Sem pagamentos no período seleccionado.</div><?php endif; ?>
                    </div></article>
                </div>
                <div style="height:18px"></div>
                <article class="ap-card">
                    <div class="ap-card-head">
                        <div>
                            <div class="ap-card-title"><?php echo sige_aluno_portal_icon('file'); ?> Lançamentos recentes</div>
                            <div class="ap-muted-note">Mostra no máximo <?php echo (int)$ap_lanc_limit; ?> lançamento(s). Use o filtro para consultar datas anteriores.</div>
                        </div>
                        <?php if ($is_staff_context): ?><a class="ap-btn" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=aluno_contas&aluno_id=' . $aluno_id)); ?>">Ver conta completa</a><?php endif; ?>
                    </div>
                    <div class="ap-card-body" style="border-bottom:1px solid var(--color-slate-100)">
                        <form class="ap-fin-filter" method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>">
                            <input type="hidden" name="page" value="sige-app">
                            <input type="hidden" name="view" value="aluno_portal">
                            <input type="hidden" name="aluno_id" value="<?php echo (int)$aluno_id; ?>">
                            <input type="hidden" name="secao" value="financeiro">
                            <label class="ap-fin-field"><span>De</span><input type="date" name="ap_from" value="<?php echo esc_attr($ap_from); ?>"></label>
                            <label class="ap-fin-field"><span>Até</span><input type="date" name="ap_to" value="<?php echo esc_attr($ap_to); ?>"></label>
                            <button class="ap-btn primary" type="submit"><?php echo sige_aluno_portal_icon('calendar'); ?> Filtrar</button>
                            <a class="ap-btn" href="<?php echo esc_url($base_url . '&secao=financeiro'); ?>">Limpar</a>
                        </form>
                    </div>
                    <div class="ap-table-wrap"><table class="ap-table"><thead><tr><th>Descrição</th><th>Mês</th><th>Vencimento</th><th>Valor</th><th>Pago</th><th>Status</th><th>Recibo</th></tr></thead><tbody>
                <?php if ($lanc_recentes): foreach ($lanc_recentes as $l): $valor_l=function_exists('sige_fin_total_lancamento') ? sige_fin_total_lancamento($l) : ((float)$l->valor_original+(float)$l->valor_multa+(float)$l->valor_transporte+(float)$l->valor_extras-(float)$l->valor_desconto-(float)$l->valor_desconto_especial); $l_recibo_url = !empty($l->pagamento_id) ? sige_aluno_portal_recibo_url($l->pagamento_id) : ''; ?><tr><td><?php echo esc_html($l->descricao ?: 'Lançamento'); ?></td><td><?php echo esc_html($l->mes_referencia ?: '-'); ?></td><td><?php echo sige_aluno_portal_date($l->data_vencimento); ?></td><td><?php echo esc_html(sige_aluno_portal_money($valor_l)); ?></td><td><?php echo esc_html(sige_aluno_portal_money($l->valor_pago)); ?></td><td><span class="ap-badge <?php echo strtolower((string)$l->status)==='pago'?'ok':($l->data_vencimento < wp_date('Y-m-d') ? 'err':'warn'); ?>"><?php echo esc_html($l->status ?: '-'); ?></span></td><td><?php if ($l_recibo_url): ?><a class="ap-receipt-link" href="<?php echo esc_url($l_recibo_url); ?>" data-sige-act="sigeAbrirReciboJanela" data-sige-prevent>Recibo <?php echo esc_html($l->recibo_numero ?: ('#' . (int)$l->pagamento_id)); ?></a><?php else: ?><span class="ap-badge">-</span><?php endif; ?></td></tr><?php endforeach; else: ?><tr><td colspan="7"><div class="ap-empty">Sem lançamentos financeiros no período seleccionado.</div></td></tr><?php endif; ?>
                </tbody></table></div></article>
            <?php elseif ($secao === 'documentos'): ?>
                <div class="ap-grid-3">
                    <?php foreach ($docs as $doc): ?>
                        <article class="ap-card"><div class="ap-card-body"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('file'); ?> <?php echo esc_html($doc['label']); ?></div><p class="ap-sub"><?php echo !empty($doc['url']) ? 'Documento disponível no arquivo digital.' : 'Documento ainda não anexado.'; ?></p><?php if (!empty($doc['url'])): ?><a class="ap-btn primary" target="_blank" rel="noopener" href="<?php echo esc_url($doc['url']); ?>">Abrir documento</a><?php endif; ?></div></article>
                    <?php endforeach; ?>
                    <article class="ap-card"><div class="ap-card-body"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('printer'); ?> Emissão</div>
                        <?php if ($bloquear_notas_por_divida): ?>
                            <p class="ap-sub">Boletins e documentos académicos ficam ocultos no portal enquanto houver pendência financeira.</p>
                            <span class="ap-badge warn">Bloqueado por dívida</span>
                        <?php elseif ($is_staff_context): ?>
                            <p class="ap-sub">Use os módulos próprios para emitir documentos oficiais com layout validado.</p><div class="ap-actions"><a class="ap-btn" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=boletim&aluno_id=' . $aluno_id)); ?>">Boletim</a><a class="ap-btn" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=dec&aluno_id=' . $aluno_id)); ?>">Declaração/DEC</a></div>
                        <?php else: ?>
                            <p class="ap-sub">Documentos académicos disponíveis apenas quando emitidos pela secretaria.</p>
                        <?php endif; ?>
                    </div></article>
                </div>
            <?php elseif ($secao === 'contactos'): ?>
                <div class="ap-grid-2">
                    <article class="ap-card"><div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('users'); ?> Encarregados e família</div></div><div class="ap-card-body"><div class="ap-info-list">
                        <div class="ap-info-row"><div class="ap-label">Pai</div><div class="ap-value"><?php echo esc_html($aluno->nome_pai ?: '-'); ?> · <?php echo esc_html($aluno->telemovel_pai ?: '-'); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Mãe</div><div class="ap-value"><?php echo esc_html($aluno->nome_mae ?: '-'); ?> · <?php echo esc_html($aluno->telemovel_mae ?: '-'); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Contacto principal</div><div class="ap-value"><?php echo esc_html($aluno->contacto_encarregado ?: '-'); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">WhatsApp</div><div class="ap-value"><?php echo esc_html($aluno->whatsapp_notificacoes ?: '-'); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">E-mail</div><div class="ap-value"><?php echo esc_html($aluno->email_encarregado ?: $aluno->email_pai ?: $aluno->email_mae ?: '-'); ?></div></div>
                    </div></div></article>
                    <article class="ap-card"><div class="ap-card-head"><div class="ap-card-title"><?php echo sige_aluno_portal_icon('heart'); ?> Saúde e emergência</div></div><div class="ap-card-body"><div class="ap-info-list">
                        <div class="ap-info-row"><div class="ap-label">Grupo sanguíneo</div><div class="ap-value"><?php echo esc_html($aluno->grupo_sanguineo ?: '-'); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Alergias</div><div class="ap-value"><?php echo esc_html($aluno->alergias ?: '-'); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Condições médicas</div><div class="ap-value"><?php echo esc_html($aluno->condicoes_medicas ?: '-'); ?></div></div>
                        <div class="ap-info-row"><div class="ap-label">Hospital</div><div class="ap-value"><?php echo esc_html($aluno->hospital_preferencia ?: '-'); ?></div></div>
                    </div></div></article>
                </div>
            <?php elseif ($secao === 'senha'): ?>
                <?php if (empty($is_portal_user_context)): ?>
                    <article class="ap-lock">
                        <span class="ap-lock-icon"><?php echo sige_aluno_portal_icon('shield'); ?></span>
                        <div>
                            <strong>Alteração de senha indisponível neste contexto</strong>
                            <p>Esta área é destinada ao aluno ou encarregado autenticado. A equipa da escola deve gerir utilizadores nos módulos administrativos próprios.</p>
                        </div>
                    </article>
                <?php else: ?>
                    <div class="ap-password-layout">
                        <article class="ap-card">
                            <div class="ap-card-head">
                                <div class="ap-card-title"><?php echo sige_aluno_portal_icon('shield'); ?> Alterar senha</div>
                                <span class="ap-readonly"><?php echo sige_aluno_portal_icon('check'); ?> Só altera a sua senha</span>
                            </div>
                            <div class="ap-card-body">
                                <?php if (!empty($portal_must_change_password)): ?>
                                    <div class="ap-alert error"><?php echo sige_aluno_portal_icon('shield'); ?><div><strong>Primeiro acesso ou senha provisória.</strong><br>Por segurança, altere a senha recebida da secretaria antes de continuar a usar o portal.</div></div>
                                <?php endif; ?>
                                <?php if ($ap_password_msg === 'ok'): ?>
                                    <div class="ap-alert ok"><?php echo sige_aluno_portal_icon('check'); ?><div><?php echo esc_html($ap_password_messages['ok']); ?></div></div>
                                <?php elseif ($ap_password_msg === 'error'): ?>
                                    <div class="ap-alert error"><?php echo sige_aluno_portal_icon('alert'); ?><div><?php echo esc_html($ap_password_messages[$ap_password_error] ?? $ap_password_messages['unknown']); ?></div></div>
                                <?php endif; ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ap-password-form" data-portal-allow-submit="1" autocomplete="off">
                                    <input type="hidden" name="action" value="sige_aluno_portal_change_password">
                                    <input type="hidden" name="redirect_to" value="<?php echo esc_url($base_url . '&secao=senha'); ?>">
                                    <?php wp_nonce_field('sige_aluno_portal_change_password', 'sige_aluno_portal_nonce'); ?>
                                    <label class="ap-field">
                                        <span>Senha actual</span>
                                        <input type="password" name="current_password" autocomplete="current-password" required>
                                    </label>
                                    <label class="ap-field">
                                        <span>Nova senha</span>
                                        <input type="password" name="new_password" autocomplete="new-password" minlength="8" maxlength="128" required>
                                    </label>
                                    <label class="ap-field">
                                        <span>Confirmar nova senha</span>
                                        <input type="password" name="confirm_password" autocomplete="new-password" minlength="8" maxlength="128" required>
                                    </label>
                                    <div class="ap-actions">
                                        <button class="ap-btn primary" type="submit"><?php echo sige_aluno_portal_icon('shield'); ?> Guardar nova senha</button>
                                    </div>
                                </form>
                            </div>
                        </article>
                        <aside class="ap-rules">
                            <h3>Condições de segurança</h3>
                            <ul>
                                <li>Informe primeiro a senha actual.</li>
                                <li>A nova senha deve ter pelo menos 8 caracteres.</li>
                                <li>Use letras e números. Símbolos também são permitidos.</li>
                                <li>Não use o nome de utilizador nem parte do e-mail.</li>
                                <li>A confirmação deve ser igual à nova senha.</li>
                                <li>Exemplo de formato aceite: Escola2026.</li>
                                <li>Por segurança, há limite de tentativas.</li>
                            </ul>
                        </aside>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </section>
</div>



<script <?php echo sige_csp_script_attr(); ?>>
(function(){
  var html = <?php echo wp_json_encode($portal_sidebar_nav_html); ?>;
  if (!html) return;

  function mountStudentSidebarNav(){
    var sidebar = document.querySelector('.sg-app-sidebar, .sige-app-sidebar, aside[class*="sidebar"]');
    if (!sidebar) return false;

    var existing = sidebar.querySelector('.sg-student-sidebar-nav');
    if (existing) {
      var existingLinks = existing.querySelector('.sg-student-sidebar-links');
      if (existingLinks) {
        existingLinks.style.display = 'grid';
        existingLinks.style.visibility = 'visible';
        existingLinks.style.opacity = '1';
        Array.prototype.forEach.call(existingLinks.querySelectorAll('a'), function(a){
          a.style.display = 'flex';
          a.style.visibility = 'visible';
          a.style.opacity = '1';
        });
      }
      return true;
    }

    var holder = document.createElement('div');
    holder.innerHTML = html;
    var node = holder.firstElementChild;
    if (!node) return false;

    var logout = sidebar.querySelector('a[href*="logout"], .sg-logout, .sige-logout, [class*="logout"], button[class*="logout"], [class*="terminar"]');
    if (logout && logout.parentNode && logout.parentNode !== sidebar) {
      logout.parentNode.insertAdjacentElement('beforebegin', node);
    } else if (logout) {
      logout.insertAdjacentElement('beforebegin', node);
    } else {
      sidebar.appendChild(node);
    }

    var links = node.querySelector('.sg-student-sidebar-links');
    if (links) {
      links.style.display = 'grid';
      links.style.visibility = 'visible';
      links.style.opacity = '1';
      Array.prototype.forEach.call(links.querySelectorAll('a'), function(a){
        a.style.display = 'flex';
        a.style.visibility = 'visible';
        a.style.opacity = '1';
      });
    }

    return true;
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){
      if (!mountStudentSidebarNav()) setTimeout(mountStudentSidebarNav, 250);
    });
  } else {
    if (!mountStudentSidebarNav()) setTimeout(mountStudentSidebarNav, 250);
  }
})();
</script>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
  // v12.10.126 - defesa adicional: a página é de consulta,
  // excepto a alteração de senha do próprio utilizador e filtros GET de leitura.
  document.addEventListener('submit', function(e){
    var form = e.target;
    if (!form || !form.closest || !form.closest('.sige-aluno-portal')) return true;

    var method = (form.getAttribute('method') || 'get').toLowerCase();
    var allowed = form.getAttribute('data-portal-allow-submit') === '1';

    if (method === 'post' && !allowed) {
      e.preventDefault();
      return false;
    }

    if (method === 'post' && allowed) {
      var btn = form.querySelector('button[type="submit"]');
      if (btn) {
        btn.disabled = true;
        btn.style.opacity = '.75';
        btn.style.pointerEvents = 'none';
        btn.innerHTML = 'A guardar senha...';
      }
    }

    return true;
  }, true);
})();
</script>
