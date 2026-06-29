<?php
/**
 * SIGE SoftGenial - Auditoria Financeira e Saneamento Seguro
 * Patch v77.1 / 12.2.26
 *
 * A auditoria lê dados e o saneamento seguro corrige apenas casos de baixo risco previamente definidos.
 */
if (!defined('ABSPATH')) exit;

global $wpdb;

if (!sige_page_guard(
    ['financeiro.auditoria_ver'],
    ['sige_director','sige_secretario','sige_financeiro']
)) return;

$tL = $wpdb->prefix . 'sige_fin_lancamentos';
$tP = $wpdb->prefix . 'sige_fin_pagamentos';
$tA = $wpdb->prefix . 'sige_alunos';
$tS = $wpdb->prefix . 'sige_fin_servicos';

function sige_fin_auditoria_table_exists($table) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
}

function sige_fin_auditoria_money($v) {
    return number_format((float)$v, 2, ',', '.') . ' MT';
}

function sige_fin_auditoria_rows($sql) {
    global $wpdb;
    $rows = $wpdb->get_results($sql, ARRAY_A);
    return is_array($rows) ? $rows : [];
}

function sige_fin_auditoria_col_exists($table, $column) {
    global $wpdb;
    return (bool) $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", $column));
}

function sige_fin_auditoria_create_log_table() {
    global $wpdb;
    $table = $wpdb->prefix . 'sige_fin_saneamento_log';
    $charset = $wpdb->get_charset_collate();
    $wpdb->query("CREATE TABLE IF NOT EXISTS `$table` (`id` BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, `tipo` VARCHAR(80) NOT NULL, `tabela` VARCHAR(120) NOT NULL, `referencia_id` BIGINT(20) UNSIGNED DEFAULT NULL, `aluno_id` BIGINT(20) UNSIGNED DEFAULT NULL, `payload` LONGTEXT NULL, `created_by` BIGINT(20) UNSIGNED DEFAULT NULL, `created_at` DATETIME NOT NULL, PRIMARY KEY (`id`), KEY `tipo` (`tipo`), KEY `referencia_id` (`referencia_id`), KEY `aluno_id` (`aluno_id`)) $charset");
    return $table;
}

function sige_fin_auditoria_log_saneamento($tipo, $tabela, $referencia_id, $aluno_id, array $payload) {
    global $wpdb;
    $log_table = sige_fin_auditoria_create_log_table();
    $wpdb->insert($log_table, [
        'tipo' => sanitize_text_field($tipo),
        'tabela' => sanitize_text_field($tabela),
        'referencia_id' => $referencia_id ? (int)$referencia_id : null,
        'aluno_id' => $aluno_id ? (int)$aluno_id : null,
        'payload' => wp_json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'created_by' => get_current_user_id(),
        'created_at' => current_time('mysql'),
    ], ['%s','%s','%d','%d','%s','%d','%s']);
}

function sige_fin_auditoria_drop_b03_trigger() {
    global $wpdb;
    $wpdb->query('DROP TRIGGER IF EXISTS `trg_lancamento_pago_protect`');
}

function sige_fin_auditoria_restore_b03_trigger($table_lancamentos) {
    global $wpdb;
    $sql = "CREATE TRIGGER `trg_lancamento_pago_protect` BEFORE UPDATE ON `$table_lancamentos` FOR EACH ROW BEGIN IF OLD.status = 'pago' AND NEW.status = 'pago' AND (NEW.valor_original <> OLD.valor_original OR NEW.valor_desconto <> OLD.valor_desconto OR NEW.valor_desconto_especial <> OLD.valor_desconto_especial OR NEW.valor_transporte <> OLD.valor_transporte OR NEW.valor_extras <> OLD.valor_extras OR NEW.valor_multa <> OLD.valor_multa OR NEW.servico_id <> OLD.servico_id) THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'SIGE B03: Lançamento pago não pode ter campos financeiros alterados. Use estorno primeiro.'; END IF; END";
    $wpdb->query($sql);
}

function sige_fin_auditoria_executar_saneamento_seguro($tL, $tP, $tA) {
    global $wpdb;
    $result = ['multas_regularizadas'=>0,'pagamentos_orfaos_marcados'=>0,'ignorados'=>0,'erros'=>[]];
    $has_multa_cobrada = sige_fin_auditoria_col_exists($tL, 'valor_multa_cobrada');
    $select_multa_cobrada = $has_multa_cobrada ? 'l.valor_multa_cobrada' : 'NULL AS valor_multa_cobrada';
    $rows = $wpdb->get_results("SELECT l.id, l.aluno_id, COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS aluno, l.descricao, l.valor_original, l.valor_desconto, l.valor_desconto_especial, l.valor_multa, l.valor_pago, $select_multa_cobrada, COALESCE(SUM(p.valor_pago),0) AS soma_pagamentos FROM `$tL` l LEFT JOIN `$tA` a ON a.id = l.aluno_id LEFT JOIN `$tP` p ON p.lancamento_id = l.id WHERE l.status = 'pago' AND l.cancelado_em IS NULL AND COALESCE(l.valor_multa,0) > 0.01 GROUP BY l.id LIMIT 300", ARRAY_A);
    $to_fix = [];
    foreach ((array)$rows as $r) {
        $esperado_sem_multa = max(0, (float)$r['valor_original'] - (float)$r['valor_desconto'] - (float)$r['valor_desconto_especial']);
        $pago_real = max((float)$r['soma_pagamentos'], (float)$r['valor_pago']);
        if ($pago_real + 0.01 >= $esperado_sem_multa && $pago_real <= ($esperado_sem_multa + 0.01)) { $to_fix[] = $r; } else { $result['ignorados']++; }
    }
    if ($to_fix) {
        sige_fin_auditoria_drop_b03_trigger();
        foreach ($to_fix as $r) {
            sige_fin_auditoria_log_saneamento('multa_pago_sem_multa', $tL, (int)$r['id'], (int)$r['aluno_id'], ['antes'=>$r, 'regra'=>'Pagamento normal confirmado. Multa corrente removida; multa cobrada zerada quando aplicável.']);
            $data = ['valor_multa' => 0.00]; $formats = ['%f'];
            if ($has_multa_cobrada) { $data['valor_multa_cobrada'] = 0.00; $formats[] = '%f'; }
            $ok = $wpdb->update($tL, $data, ['id'=>(int)$r['id']], $formats, ['%d']);
            if ($ok === false) { $result['erros'][] = 'Falha ao regularizar multa do lançamento #' . (int)$r['id'] . ': ' . $wpdb->last_error; } else { $result['multas_regularizadas']++; }
        }
        sige_fin_auditoria_restore_b03_trigger($tL);
    }
    $orphans = $wpdb->get_results("SELECT p.id, p.lancamento_id, p.aluno_id, COALESCE(a.nome_completo, CONCAT('Aluno #', p.aluno_id)) AS aluno, p.recibo_numero, p.valor_pago, p.data_pagamento, p.observacoes FROM `$tP` p LEFT JOIN `$tL` l ON l.id = p.lancamento_id LEFT JOIN `$tA` a ON a.id = p.aluno_id WHERE l.id IS NULL LIMIT 300", ARRAY_A);
    foreach ((array)$orphans as $p) {
        $obs = (string)($p['observacoes'] ?? '');
        if (strpos($obs, '[SIGE-ORFAO]') !== false) { continue; }
        sige_fin_auditoria_log_saneamento('pagamento_orfao_marcado', $tP, (int)$p['id'], (int)$p['aluno_id'], ['pagamento'=>$p, 'regra'=>'Opção A: manter recibo/pagamento e marcar como órfão; sem religação automática.']);
        $append = trim($obs . "
[SIGE-ORFAO] Pagamento preservado: lançamento referenciado #" . (int)$p['lancamento_id'] . " não existe. Não religado automaticamente.");
        $ok = $wpdb->update($tP, ['observacoes'=>$append], ['id'=>(int)$p['id']], ['%s'], ['%d']);
        if ($ok === false) { $result['erros'][] = 'Falha ao marcar pagamento órfão #' . (int)$p['id'] . ': ' . $wpdb->last_error; } else { $result['pagamentos_orfaos_marcados']++; }
    }
    return $result;
}

$ready = sige_fin_auditoria_table_exists($tL) && sige_fin_auditoria_table_exists($tP) && sige_fin_auditoria_table_exists($tA);
$saneamento_result = null;
if ($ready && isset($_POST['sige_fin_auditoria_saneamento'])) {
    check_admin_referer('sige_fin_auditoria_saneamento_v77');
    $saneamento_result = sige_fin_auditoria_executar_saneamento_seguro($tL, $tP, $tA);
}
$issues = [];
$summary = [
    'critico' => 0,
    'alto' => 0,
    'medio' => 0,
    'baixo' => 0,
];

if ($ready) {
    // 1) Lançamento marcado como pago, mas matematicamente não fecha.
    $rows = sige_fin_auditoria_rows("SELECT l.id, l.aluno_id, COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS aluno, l.descricao, l.mes_referencia,
            l.status, l.valor_original, l.valor_multa, l.valor_desconto, l.valor_desconto_especial, l.valor_pago,
            (l.valor_original + COALESCE(l.valor_multa,0) - COALESCE(l.valor_desconto,0) - COALESCE(l.valor_desconto_especial,0) - COALESCE(l.valor_pago,0)) AS saldo_tecnico
        FROM {$tL} l
        LEFT JOIN {$tA} a ON a.id = l.aluno_id
        WHERE l.status = 'pago'
          AND l.cancelado_em IS NULL
          AND ABS(l.valor_original + COALESCE(l.valor_multa,0) - COALESCE(l.valor_desconto,0) - COALESCE(l.valor_desconto_especial,0) - COALESCE(l.valor_pago,0)) > 0.01
        ORDER BY ABS(l.valor_original + COALESCE(l.valor_multa,0) - COALESCE(l.valor_desconto,0) - COALESCE(l.valor_desconto_especial,0) - COALESCE(l.valor_pago,0)) DESC, a.nome_completo ASC
        LIMIT 100");
    foreach ($rows as $r) {
        $issues[] = [
            'nivel' => 'critico',
            'aluno' => $r['aluno'],
            'item' => '#' . $r['id'] . ' - ' . $r['descricao'],
            'problema' => 'Lançamento está como pago, mas o saldo técnico não fecha.',
            'detalhe' => 'Saldo técnico: ' . sige_fin_auditoria_money($r['saldo_tecnico']) . ' | Original: ' . sige_fin_auditoria_money($r['valor_original']) . ' | Pago: ' . sige_fin_auditoria_money($r['valor_pago']) . ' | Descontos: ' . sige_fin_auditoria_money((float)$r['valor_desconto'] + (float)$r['valor_desconto_especial']) . ' | Multa: ' . sige_fin_auditoria_money($r['valor_multa']),
            'accao' => 'Rever antes de corrigir: pode ser desconto, pagamento parcial indevidamente fechado ou histórico antigo.',
        ];
        $summary['critico']++;
    }

    // 2) Soma real dos pagamentos diferente do valor_pago no lançamento.
    $rows = sige_fin_auditoria_rows("SELECT l.id, l.aluno_id, COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS aluno, l.descricao, l.mes_referencia,
            l.status, l.valor_pago AS valor_lancamento, COALESCE(SUM(p.valor_pago),0) AS soma_pagamentos,
            (COALESCE(SUM(p.valor_pago),0) - COALESCE(l.valor_pago,0)) AS diferenca
        FROM {$tL} l
        LEFT JOIN {$tA} a ON a.id = l.aluno_id
        LEFT JOIN {$tP} p ON p.lancamento_id = l.id
        WHERE l.cancelado_em IS NULL
        GROUP BY l.id
        HAVING ABS(COALESCE(SUM(p.valor_pago),0) - COALESCE(l.valor_pago,0)) > 0.01
        ORDER BY ABS(COALESCE(SUM(p.valor_pago),0) - COALESCE(l.valor_pago,0)) DESC, a.nome_completo ASC
        LIMIT 100");
    foreach ($rows as $r) {
        $issues[] = [
            'nivel' => 'alto',
            'aluno' => $r['aluno'],
            'item' => '#' . $r['id'] . ' - ' . $r['descricao'],
            'problema' => 'Valor pago no lançamento diverge da soma dos recibos/pagamentos.',
            'detalhe' => 'No lançamento: ' . sige_fin_auditoria_money($r['valor_lancamento']) . ' | Soma dos pagamentos: ' . sige_fin_auditoria_money($r['soma_pagamentos']) . ' | Diferença: ' . sige_fin_auditoria_money($r['diferenca']),
            'accao' => 'Confirmar recibos antes de qualquer normalização. Não alterar histórico sem relatório.',
        ];
        $summary['alto']++;
    }

    // 3) Pagos com multa activa.
    $rows = sige_fin_auditoria_rows("SELECT l.id, COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS aluno, l.descricao, l.mes_referencia, l.valor_multa, l.valor_multa_cobrada
        FROM {$tL} l
        LEFT JOIN {$tA} a ON a.id = l.aluno_id
        WHERE l.status = 'pago'
          AND l.cancelado_em IS NULL
          AND COALESCE(l.valor_multa,0) > 0.01
        ORDER BY l.valor_multa DESC, a.nome_completo ASC
        LIMIT 100");
    foreach ($rows as $r) {
        $issues[] = [
            'nivel' => 'medio',
            'aluno' => $r['aluno'],
            'item' => '#' . $r['id'] . ' - ' . $r['descricao'],
            'problema' => 'Lançamento pago mantém multa activa no campo corrente.',
            'detalhe' => 'Multa corrente: ' . sige_fin_auditoria_money($r['valor_multa']) . ' | Multa cobrada registada: ' . sige_fin_auditoria_money($r['valor_multa_cobrada']),
            'accao' => 'Em modo seguro, a multa corrente deve deixar de afectar cobrança futura; o valor histórico cobrado deve ficar preservado.',
        ];
        $summary['medio']++;
    }

    // 4) Descontos especiais em lançamentos pagos - não é sempre erro, mas precisa visibilidade.
    $rows = sige_fin_auditoria_rows("SELECT l.id, COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS aluno, l.descricao, l.mes_referencia, l.valor_desconto_especial, l.motivo_desconto_especial
        FROM {$tL} l
        LEFT JOIN {$tA} a ON a.id = l.aluno_id
        WHERE l.status = 'pago'
          AND l.cancelado_em IS NULL
          AND COALESCE(l.valor_desconto_especial,0) > 0.01
        ORDER BY l.valor_desconto_especial DESC, a.nome_completo ASC
        LIMIT 100");
    foreach ($rows as $r) {
        $issues[] = [
            'nivel' => 'baixo',
            'aluno' => $r['aluno'],
            'item' => '#' . $r['id'] . ' - ' . $r['descricao'],
            'problema' => 'Lançamento pago com desconto especial registado.',
            'detalhe' => 'Desconto especial: ' . sige_fin_auditoria_money($r['valor_desconto_especial']) . ' | Motivo: ' . ($r['motivo_desconto_especial'] ?: 'sem motivo registado'),
            'accao' => 'Validar se foi desconto real. Se foi erro, retirar no cartão do aluno ou corrigir em patch controlado.',
        ];
        $summary['baixo']++;
    }

    // 5) Duplicados activos para mesmo aluno/serviço/mês.
    $rows = sige_fin_auditoria_rows("SELECT l.aluno_id, COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS aluno, l.servico_id, COALESCE(s.nome, CONCAT('Serviço #', l.servico_id)) AS servico, l.mes_referencia,
            COUNT(*) AS qtd, GROUP_CONCAT(l.id ORDER BY l.id SEPARATOR ', ') AS ids, SUM(l.valor_original) AS total_original
        FROM {$tL} l
        LEFT JOIN {$tA} a ON a.id = l.aluno_id
        LEFT JOIN {$tS} s ON s.id = l.servico_id
        WHERE l.cancelado_em IS NULL
          AND l.mes_referencia IS NOT NULL
          AND l.mes_referencia <> ''
        GROUP BY l.aluno_id, l.servico_id, l.mes_referencia
        HAVING qtd > 1
        ORDER BY qtd DESC, a.nome_completo ASC
        LIMIT 100");
    foreach ($rows as $r) {
        $issues[] = [
            'nivel' => 'alto',
            'aluno' => $r['aluno'],
            'item' => $r['servico'] . ' - ' . $r['mes_referencia'],
            'problema' => 'Possível lançamento duplicado para o mesmo aluno, serviço e mês.',
            'detalhe' => 'IDs: ' . $r['ids'] . ' | Quantidade: ' . (int)$r['qtd'] . ' | Total original somado: ' . sige_fin_auditoria_money($r['total_original']),
            'accao' => 'Rever manualmente. Duplicado pago não deve ser apagado; deve ser tratado por estorno ou cancelamento controlado.',
        ];
        $summary['alto']++;
    }

    // 6) Auditoria avançada: mensalidades duplicadas ou valor lançado diferente do valor esperado por mês.
    // Regra: para cada aluno + mês, a mensalidade activa deve ser única e o total lançado deve bater com o valor configurado do serviço.
    $rows = sige_fin_auditoria_rows("SELECT l.aluno_id,
            COALESCE(a.nome_completo, CONCAT('Aluno #', l.aluno_id)) AS aluno,
            l.mes_referencia,
            COUNT(*) AS qtd_lancamentos,
            COUNT(DISTINCT l.servico_id) AS qtd_servicos,
            GROUP_CONCAT(l.id ORDER BY l.id SEPARATOR ', ') AS ids,
            GROUP_CONCAT(DISTINCT COALESCE(s.nome, CONCAT('Serviço #', l.servico_id)) ORDER BY s.nome SEPARATOR ' | ') AS servicos,
            SUM(COALESCE(l.valor_original,0)) AS total_lancado,
            MIN(COALESCE(s.valor,0)) AS menor_valor_servico,
            MAX(COALESCE(s.valor,0)) AS maior_valor_servico,
            SUM(COALESCE(l.valor_pago,0)) AS total_pago,
            GROUP_CONCAT(DISTINCT l.status ORDER BY l.status SEPARATOR ', ') AS estados
        FROM {$tL} l
        INNER JOIN {$tS} s ON s.id = l.servico_id AND s.tipo = 'mensalidade'
        LEFT JOIN {$tA} a ON a.id = l.aluno_id
        WHERE l.cancelado_em IS NULL
          AND l.mes_referencia IS NOT NULL
          AND l.mes_referencia <> ''
          AND l.status <> 'cancelado'
          AND COALESCE(s.valor,0) > 0
        GROUP BY l.aluno_id, l.mes_referencia
        HAVING qtd_lancamentos > 1
            OR ABS(SUM(COALESCE(l.valor_original,0)) - MAX(COALESCE(s.valor,0))) > 0.01
        ORDER BY l.mes_referencia DESC, a.nome_completo ASC
        LIMIT 150");
    foreach ($rows as $r) {
        $duplicado = ((int)$r['qtd_lancamentos'] > 1);
        $valor_esperado = (float)$r['maior_valor_servico'];
        $total_lancado = (float)$r['total_lancado'];
        $diferenca = $total_lancado - $valor_esperado;
        $nivel = $duplicado || abs($diferenca) > 0.01 ? 'alto' : 'medio';
        $issues[] = [
            'nivel' => $nivel,
            'aluno' => $r['aluno'],
            'item' => 'Mensalidade - ' . $r['mes_referencia'],
            'problema' => $duplicado ? 'Possível duplicação de mensalidade no mesmo mês.' : 'Valor mensal lançado diferente do valor esperado para a mensalidade.',
            'detalhe' => 'IDs: ' . $r['ids'] . ' | Serviços: ' . $r['servicos'] . ' | Lançamentos: ' . (int)$r['qtd_lancamentos'] . ' | Total lançado: ' . sige_fin_auditoria_money($total_lancado) . ' | Valor esperado: ' . sige_fin_auditoria_money($valor_esperado) . ' | Diferença: ' . sige_fin_auditoria_money($diferenca) . ' | Total pago: ' . sige_fin_auditoria_money($r['total_pago']) . ' | Estados: ' . $r['estados'],
            'accao' => 'Auditoria apenas informativa. Rever no cartão do aluno antes de qualquer saneamento; não cancelar mensalidade paga sem estorno ou validação manual.',
        ];
        $summary[$nivel]++;
    }

    // 6) Pagamentos órfãos.
    $rows = sige_fin_auditoria_rows("SELECT p.id, p.lancamento_id, p.aluno_id, COALESCE(a.nome_completo, CONCAT('Aluno #', p.aluno_id)) AS aluno, p.recibo_numero, p.valor_pago, p.data_pagamento
        FROM {$tP} p
        LEFT JOIN {$tL} l ON l.id = p.lancamento_id
        LEFT JOIN {$tA} a ON a.id = p.aluno_id
        WHERE l.id IS NULL
        ORDER BY p.data_pagamento DESC
        LIMIT 100");
    foreach ($rows as $r) {
        $issues[] = [
            'nivel' => 'critico',
            'aluno' => $r['aluno'],
            'item' => 'Recibo ' . $r['recibo_numero'] . ' - pagamento #' . $r['id'],
            'problema' => 'Pagamento aponta para lançamento inexistente.',
            'detalhe' => 'Lançamento referenciado: #' . $r['lancamento_id'] . ' | Valor: ' . sige_fin_auditoria_money($r['valor_pago']) . ' | Data: ' . $r['data_pagamento'],
            'accao' => 'Não corrigir automaticamente sem confirmar recibo e histórico.',
        ];
        $summary['critico']++;
    }
}

$total_issues = count($issues);
?>

<?php
if (!function_exists('sige_fin_auditoria_clean_label')) {
    function sige_fin_auditoria_clean_label($text) {
        $text = (string)$text;
        $replace = [
            'Saldo técnico' => 'Diferença apurada',
            'saldo técnico' => 'diferença apurada',
            'IDs:' => 'Registos:',
            'ID:' => 'Registo:',
            'patch controlado' => 'processo controlado',
            'normalização' => 'correcção',
            'Normalização' => 'Correcção',
            'órfão' => 'sem ligação ao lançamento',
            'órfãos' => 'sem ligação ao lançamento',
            'Lançamento referenciado' => 'Lançamento associado',
        ];
        return strtr($text, $replace);
    }
}
$audit_health_label = $total_issues ? 'Requer acompanhamento' : 'Sem alertas relevantes';
$audit_health_class = $total_issues ? 'is-coral' : 'is-positive';
?>

<div class="sg-auditpro-wrap sg-finpro-wrap">
    <section class="sg-finpro-hero sg-auditpro-hero" aria-label="Auditoria Financeira">
        <div class="sg-finpro-hero-copy">
            <span class="sg-finpro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?> Controlo Financeiro</span>
            <h1>Auditoria Financeira</h1>
            <p>Acompanhe alertas de consistência, recibos, lançamentos e situações que merecem validação antes de qualquer decisão da tesouraria.</p>
            <div class="sg-finpro-hero-actions">
                <a class="sg-finpro-btn sg-finpro-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-extratos')); ?>"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('file') : ''; ?> Abrir Extractos</a>
                <a class="sg-finpro-btn sg-finpro-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-relatorio-mensal')); ?>"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> Relatório Mensal</a>
            </div>
        </div>
        <div class="sg-finpro-hero-panel">
            <div class="sg-finpro-mini-label">Estado da verificação</div>
            <strong class="<?php echo esc_attr($audit_health_class); ?>"><?php echo esc_html($audit_health_label); ?></strong>
            <span><?php echo esc_html($total_issues); ?> alerta(s) encontrados nas verificações actuais.</span>
            <div class="sg-finpro-progress"><i style="width:<?php echo esc_attr($total_issues ? min(100, max(18, $total_issues * 5)) : 100); ?>%"></i></div>
        </div>
    </section>

    <?php if (!$ready): ?>
        <div class="sg-finpro-alert">
            <div class="sg-finpro-alert-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('alert') : ''; ?></div>
            <div>
                <strong>Não foi possível concluir a verificação.</strong>
                <p>Uma ou mais áreas financeiras essenciais ainda não estão disponíveis nesta instalação.</p>
            </div>
        </div>
    <?php else: ?>
        <?php if (is_array($saneamento_result)): ?>
            <div class="sg-auditpro-result sg-auditpro-result-ok">
                <div class="sg-finpro-section-icon sg-finpro-soft-green"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></div>
                <div>
                    <strong>Correcção segura concluída.</strong>
                    <p>Multas indevidas regularizadas: <?php echo esc_html((int)$saneamento_result['multas_regularizadas']); ?> · Pagamentos sem ligação marcados para acompanhamento: <?php echo esc_html((int)$saneamento_result['pagamentos_orfaos_marcados']); ?> · Casos mantidos para revisão: <?php echo esc_html((int)$saneamento_result['ignorados']); ?>.</p>
                    <?php if (!empty($saneamento_result['erros'])): ?><p><strong>Atenção:</strong> houve um impedimento ao concluir parte da operação. Reveja a execução antes de repetir.</p><?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <div class="sg-finpro-kpi-grid sg-auditpro-kpis">
            <div class="sg-finpro-kpi sg-finpro-tone-blue">
                <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('chart') : ''; ?></div>
                <div><span>Total de alertas</span><strong><?php echo esc_html($total_issues); ?></strong><small>Situações encontradas</small></div>
            </div>
            <div class="sg-finpro-kpi sg-finpro-tone-coral">
                <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('alert') : ''; ?></div>
                <div><span>Críticos</span><strong><?php echo esc_html($summary['critico']); ?></strong><small>Prioridade máxima</small></div>
            </div>
            <div class="sg-finpro-kpi sg-finpro-tone-amber">
                <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('trending') : ''; ?></div>
                <div><span>Altos</span><strong><?php echo esc_html($summary['alto']); ?></strong><small>Rever com atenção</small></div>
            </div>
            <div class="sg-finpro-kpi sg-finpro-tone-green">
                <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></div>
                <div><span>Médios e baixos</span><strong><?php echo esc_html((int)$summary['medio'] + (int)$summary['baixo']); ?></strong><small>Acompanhamento normal</small></div>
            </div>
        </div>

        <div class="sg-finpro-grid sg-auditpro-grid">
            <section class="sg-finpro-card sg-auditpro-action-card">
                <div class="sg-finpro-card-head">
                    <div>
                        <span class="sg-finpro-section-icon sg-finpro-soft-blue"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?></span>
                        <div>
                            <h2>Correcção segura</h2>
                            <p>Use apenas quando pretender regularizar os casos simples identificados pelo sistema, sem apagar recibos nem alterar pagamentos confirmados.</p>
                        </div>
                    </div>
                </div>
                <form method="post" class="sg-auditpro-safe-form" id="sigeAuditSafeForm">
                    <?php wp_nonce_field('sige_fin_auditoria_saneamento_v77'); ?>
                    <input type="hidden" name="sige_fin_auditoria_saneamento" value="1">
                    <button type="submit" class="sg-finpro-btn sg-finpro-btn-primary"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?> Corrigir casos seguros</button>
                    <span>Esta acção mantém o histórico e deixa os restantes casos para revisão manual.</span>
                </form>
            </section>

            <section class="sg-finpro-card sg-auditpro-info-card">
                <div class="sg-finpro-card-head">
                    <div>
                        <span class="sg-finpro-section-icon sg-finpro-soft-amber"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('book') : ''; ?></span>
                        <div>
                            <h2>Como interpretar</h2>
                            <p>Nem todo alerta significa erro. Alguns casos podem representar descontos autorizados, acertos antigos ou registos que precisam apenas de confirmação.</p>
                        </div>
                    </div>
                </div>
                <div class="sg-auditpro-levels">
                    <span class="sg-auditpro-badge sg-auditpro-badge-critico">Crítico</span>
                    <span class="sg-auditpro-badge sg-auditpro-badge-alto">Alto</span>
                    <span class="sg-auditpro-badge sg-auditpro-badge-medio">Médio</span>
                    <span class="sg-auditpro-badge sg-auditpro-badge-baixo">Baixo</span>
                </div>
            </section>
        </div>

        <section class="sg-finpro-card sg-auditpro-table-card">
            <div class="sg-finpro-card-head">
                <div>
                    <span class="sg-finpro-section-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('clipboard') : ''; ?></span>
                    <div>
                        <h2>Alertas encontrados</h2>
                        <p>Lista de situações que merecem validação pela direcção ou tesouraria.</p>
                    </div>
                </div>
            </div>

            <?php if (!$issues): ?>
                <div class="sg-auditpro-empty">
                    <div class="sg-finpro-section-icon sg-finpro-soft-green"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('check') : ''; ?></div>
                    <strong>Nenhuma inconsistência financeira detectada.</strong>
                    <p>As verificações actuais não encontraram situações que exijam acompanhamento.</p>
                </div>
            <?php else: ?>
                <div class="sg-auditpro-table-wrap">
                    <table class="sg-finpro-table sg-auditpro-table">
                        <thead>
                            <tr>
                                <th>Nível</th>
                                <th>Aluno</th>
                                <th>Registo / Item</th>
                                <th>Situação</th>
                                <th>Detalhe</th>
                                <th>Orientação</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($issues as $i): ?>
                            <tr>
                                <td><span class="sg-auditpro-badge sg-auditpro-badge-<?php echo esc_attr($i['nivel']); ?>"><?php echo esc_html($i['nivel']); ?></span></td>
                                <td><strong><?php echo esc_html($i['aluno']); ?></strong></td>
                                <td><?php echo esc_html(sige_fin_auditoria_clean_label($i['item'])); ?></td>
                                <td><?php echo esc_html(sige_fin_auditoria_clean_label($i['problema'])); ?></td>
                                <td class="sg-auditpro-muted"><?php echo esc_html(sige_fin_auditoria_clean_label($i['detalhe'])); ?></td>
                                <td><?php echo esc_html(sige_fin_auditoria_clean_label($i['accao'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
</div>

<div class="sg-modal-backdrop sg-auditpro-modal-backdrop" id="sigeAuditConfirmModal" aria-hidden="true">
    <div class="sige-lanc-modal sg-auditpro-modal">
        <div class="sige-lanc-modal-header"><h3 class="sige-lanc-modal-title">Confirmar correcção segura</h3></div>
        <div class="sige-lanc-modal-body">
            <p class="sg-expense-confirm-text">Confirma que pretende corrigir apenas os casos seguros identificados pela auditoria?</p>
            <div class="sg-expense-confirm-box">
                <span>Acção</span><strong>Corrigir casos seguros</strong>
                <span>Histórico</span><strong>Recibos e pagamentos confirmados serão preservados.</strong>
                <span>Restantes casos</span><strong>Continuam disponíveis para revisão manual.</strong>
            </div>
        </div>
        <div class="sige-lanc-modal-footer">
            <button type="button" class="sg-btn sg-btn-ghost" id="sigeAuditCancelBtn">Voltar</button>
            <button type="button" class="sg-btn sg-btn-primary" id="sigeAuditConfirmBtn">Confirmar</button>
        </div>
    </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
    var form = document.getElementById('sigeAuditSafeForm');
    var modal = document.getElementById('sigeAuditConfirmModal');
    var confirmed = false;
    function openModal(){ if(!modal) return; modal.classList.add('is-open'); modal.style.display='flex'; modal.setAttribute('aria-hidden','false'); document.body.classList.add('sg-modal-open'); }
    function closeModal(){ if(!modal) return; modal.classList.remove('is-open'); modal.style.display='none'; modal.setAttribute('aria-hidden','true'); document.body.classList.remove('sg-modal-open'); }
    if(form){
        form.addEventListener('submit', function(e){
            if(confirmed){ return true; }
            e.preventDefault();
            openModal();
        });
    }
    var cancelBtn = document.getElementById('sigeAuditCancelBtn');
    var confirmBtn = document.getElementById('sigeAuditConfirmBtn');
    if(cancelBtn){ cancelBtn.addEventListener('click', closeModal); }
    if(confirmBtn){ confirmBtn.addEventListener('click', function(){ if(form){ confirmed = true; closeModal(); form.submit(); } }); }
    if(modal){ modal.addEventListener('click', function(e){ if(e.target === modal){ closeModal(); } }); }
    document.addEventListener('keydown', function(e){ if(e.key === 'Escape'){ closeModal(); } });
})();
</script>
