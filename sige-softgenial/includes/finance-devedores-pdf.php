<?php
/**
 * SIGE SoftGenial - Mapa de Cobranca (Lista de Devedores) em PDF
 * Ficheiro: includes/finance-devedores-pdf.php
 *
 * OBJECTIVO
 * Botao definitivo na Central de Cobrancas (view=financeiro-devedores) que
 * gera a lista completa de devedores da escola num documento pronto a
 * imprimir e a guardar como PDF.
 *
 * ARQUITECTURA (padrao canonico, igual a sige_desp_print)
 *  - Query handler ?sige_dev_print=lista despachado em template_redirect.
 *  - O Security Kernel impoe nonce, permissao, tenant, rate limit e
 *    auditoria pela regra query_handler:sige_dev_print (mode=enforce), com
 *    dispatch antecipado multi-hook (admin_init/parse_request/
 *    template_redirect a prioridade -1000), ANTES deste handler correr.
 *  - Este handler tambem auto-protege (login, nonce, permissao, tenant
 *    fail-closed). Defesa em profundidade: kernel impoe e handler valida.
 *  - Calcula a divida com a MESMA fonte de verdade do ecra e dos pagamentos
 *    (sige_fin_saldo_lancamento), sobre os mesmos lancamentos em aberto e a
 *    mesma populacao (alunos activos com matricula activa). O total do PDF
 *    coincide com o total da Central de Cobrancas por construcao.
 *  - Documento HTML pronto para o browser guardar como PDF, sem dependencia
 *    de biblioteca de PDF no servidor, coerente com o resto do sistema
 *    (boletim, pautas, despesas).
 *
 * ESCALABILIDADE
 *  - Duas queries (populacao e lancamentos em aberto), sem N+1. A soma da
 *    divida e feita em PHP por lancamento, sem JOIN, logo sem fan-out.
 *  - A paginacao do documento e feita pelo motor de impressao do browser; o
 *    cabecalho da tabela repete em cada pagina e as linhas nao se quebram.
 */
if (!defined('ABSPATH')) exit;

/**
 * Dataset canonico da lista de devedores da escola.
 *
 * Replica exactamente a Central de Cobrancas: mesma populacao (alunos activos
 * com matricula activa, o default do ecra) e divida calculada com
 * sige_fin_saldo_lancamento() (a mesma funcao que os pagamentos usam) sobre os
 * lancamentos em aberto ('pendente','parcial'), SEM JOIN a matriculas para nao
 * multiplicar linhas. Garante que o total do PDF e igual ao do ecra.
 *
 * @param int $escola_id Escola corrente (tenant). Valores invalidos devolvem [].
 * @return array<int,object> Linhas com aluno, turma, quantidade e divida_total.
 */
if (!function_exists('sige_fin_devedores_dataset')) {
    function sige_fin_devedores_dataset($escola_id) {
        global $wpdb;
        $escola_id = (int) $escola_id;
        if ($escola_id <= 0 || !function_exists('sige_fin_saldo_lancamento')) {
            return [];
        }
        $p = $wpdb->prefix;
        $ano = function_exists('sige_fin_get_ano_letivo_master')
            ? (int) sige_fin_get_ano_letivo_master()
            : (int) wp_date('Y');

        // FONTE DA VERDADE: replica exacta da Central de Cobrancas. O ecra mostra
        // por defeito apenas alunos ACTIVOS com matricula ACTIVA (situacao_aluno =
        // 'activos') e calcula a divida com sige_fin_saldo_lancamento() por
        // lancamento (a mesma funcao que os pagamentos usam), SEM multiplicar por
        // matriculas. Aqui faz-se o mesmo, em dois passos, para garantir que o
        // total do PDF coincide com o do ecra:
        //   1) populacao (alunos activos com matricula activa e lancamentos em aberto);
        //   2) divida real por aluno, somando sige_fin_saldo_lancamento() sobre os
        //      lancamentos em aberto numa query SEM JOIN (sem fan-out).
        $a_activo = function_exists('sige_aluno_activo_sql')
            ? sige_aluno_activo_sql('a')
            : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";
        $m_activa = function_exists('sige_matricula_activa_sql')
            ? sige_matricula_activa_sql('m')
            : "(m.status_matricula IS NULL OR LOWER(m.status_matricula) IN ('activa','ativa','activo','ativo'))";

        // 1) Populacao identica a do ecra. GROUP BY colapsa eventuais multiplas
        //    matriculas (so precisamos de um registo de aluno/turma por aluno; o
        //    total NUNCA sai desta query, para nao ser inflado pelo JOIN).
        $cand = $wpdb->get_results($wpdb->prepare(
            "SELECT l.aluno_id,
                    a.nome_completo,
                    a.numero_processo,
                    a.telemovel_pai,
                    a.whatsapp_notificacoes,
                    t.nome AS turma_nome,
                    t.classe
             FROM {$p}sige_fin_lancamentos l
             JOIN {$p}sige_alunos a
               ON l.aluno_id = a.id AND a.escola_id = %d
             LEFT JOIN {$p}sige_matriculas m
               ON (a.id = m.aluno_id AND m.escola_id = %d AND m.ano_lectivo = %d)
             LEFT JOIN {$p}sige_turmas t
               ON m.turma_id = t.id AND t.escola_id = %d
             WHERE l.escola_id = %d
               AND l.status IN ('pendente', 'parcial')
               AND {$a_activo} AND {$m_activa}
             GROUP BY l.aluno_id",
            $escola_id, $escola_id, $ano, $escola_id, $escola_id
        ));
        if (empty($cand) || !is_array($cand)) {
            return [];
        }

        $info = [];
        $ids = [];
        foreach ($cand as $c) {
            $aid = (int) $c->aluno_id;
            $info[$aid] = $c;
            $ids[] = $aid;
        }

        // 2) Divida real pela fonte de verdade do sistema (a mesma de pagamentos):
        //    sige_fin_saldo_lancamento() por lancamento em aberto, SEM JOIN, logo
        //    sem qualquer multiplicacao por matriculas. Uma so query para todos.
        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $lancs = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$p}sige_fin_lancamentos
             WHERE escola_id = %d
               AND status IN ('pendente', 'parcial')
               AND aluno_id IN ($placeholders)",
            array_merge([$escola_id], $ids)
        ));
        $divida = [];
        $qtd = [];
        if (is_array($lancs)) {
            foreach ($lancs as $l) {
                $saldo = (float) sige_fin_saldo_lancamento($l);
                if ($saldo > 0) {
                    $aid = (int) $l->aluno_id;
                    $divida[$aid] = ($divida[$aid] ?? 0.0) + $saldo;
                    $qtd[$aid] = ($qtd[$aid] ?? 0) + 1;
                }
            }
        }

        // 3) Linhas finais: so quem tem divida real > 0 (mesmo criterio do ecra),
        //    ordenadas por divida desc e depois por nome asc.
        $rows = [];
        foreach ($info as $aid => $c) {
            $d = $divida[$aid] ?? 0.0;
            if ($d <= 0) {
                continue;
            }
            $c->divida_total = $d;
            $c->qtd_mensalidades = $qtd[$aid] ?? 0;
            $rows[] = $c;
        }
        usort($rows, function ($x, $y) {
            if ($x->divida_total == $y->divida_total) {
                return strcmp((string) ($x->nome_completo ?? ''), (string) ($y->nome_completo ?? ''));
            }
            return $y->divida_total <=> $x->divida_total;
        });
        return $rows;
    }
}

/**
 * Resolve o melhor contacto disponivel para a coluna de contacto do mapa.
 *
 * @param object $row Linha do dataset.
 * @return string Contacto humano ou marcador de ausencia.
 */
if (!function_exists('sige_fin_devedores_contacto')) {
    function sige_fin_devedores_contacto($row) {
        $candidatos = [
            isset($row->whatsapp_notificacoes) ? (string) $row->whatsapp_notificacoes : '',
            isset($row->telemovel_pai) ? (string) $row->telemovel_pai : '',
        ];
        foreach ($candidatos as $c) {
            $c = trim($c);
            if ($c !== '') {
                return $c;
            }
        }
        return 'Sem contacto';
    }
}

/**
 * Dispatcher do documento de cobranca.
 *
 * Espelha sige_desp_print: hook template_redirect, validacao de tipo,
 * login, nonce, permissao SIGE com fallback de papeis e tenant fail-closed.
 * O Security Kernel ja tera imposto a regra enforce antes deste ponto.
 */
add_action('template_redirect', function () {
    if (!isset($_GET['sige_dev_print'])) {
        return;
    }
    if (!is_user_logged_in()) {
        wp_die('Acesso negado.', 'SIGE - Seguranca', ['response' => 403]);
    }

    $tipo = sanitize_key(wp_unslash($_GET['sige_dev_print']));
    if (!in_array($tipo, ['lista'], true)) {
        wp_die('Tipo de impressao invalido.', 'SIGE - Seguranca', ['response' => 400]);
    }

    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
    if (!wp_verify_nonce($nonce, 'sige_dev_print')) {
        wp_die(
            'Pedido invalido. Reabra o documento a partir da Central de Cobrancas.',
            'SIGE - Seguranca',
            ['response' => 403]
        );
    }

    // Permissao: exactamente a mesma da pagina (Central de Cobrancas):
    // cobrancas_ver OU cobrancas_gerir; papeis WP servem de fallback compativel.
    // Nao se inclui financeiro.ver para nao alargar o acesso para alem da pagina.
    $pode = false;
    if (function_exists('sige_can')) {
        $pode = sige_can('financeiro.cobrancas_ver', ['surface' => 'sige_dev_print'])
            || sige_can('financeiro.cobrancas_gerir', ['surface' => 'sige_dev_print']);
    }
    if (!$pode) {
        $pode = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
            || current_user_can('sige_director')
            || current_user_can('sige_secretario')
            || current_user_can('sige_financeiro');
    }
    if (!$pode) {
        wp_die('Sem permissao.', 'SIGE - Seguranca', ['response' => 403]);
    }

    // Tenant fail-closed: sem escola resolvida, nao ha documento.
    $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    if ($escola_id <= 0) {
        if (function_exists('sige_audit_log')) {
            sige_audit_log('devedores_lista_tenant_invalido', ['tipo' => $tipo], 'financeiro');
        }
        wp_die('Escola nao resolvida. Reabra a partir da Central de Cobrancas.', 'SIGE - Seguranca', ['response' => 403]);
    }

    $rows = sige_fin_devedores_dataset($escola_id);

    if (function_exists('sige_audit_log')) {
        sige_audit_log('devedores_lista_impressa', [
            'escola_id' => $escola_id,
            'devedores' => is_array($rows) ? count($rows) : 0,
        ], 'financeiro');
    }

    // Identidade visual da escola: MESMA fonte canonica das demais impressoes
    // (sige_desp_print usa sige_get_escola_perfil()->nome_escola/->logotipo).
    // O perfil ja vem tenant-scoped (sige_config WHERE escola_id = escola corrente).
    $escola = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
    $escola_nome = ($escola && !empty($escola->nome_escola))
        ? (string) $escola->nome_escola
        : (function_exists('get_bloginfo') ? (string) get_bloginfo('name') : 'Escola');
    $logo = ($escola && !empty($escola->logotipo)) ? (string) $escola->logotipo : '';

    header('Content-Type: text/html; charset=UTF-8');
    sige_dev_print_page($escola_nome, $logo, $rows);
    exit;
}, 5);

/**
 * Renderiza o documento de cobranca pronto para imprimir/guardar como PDF.
 *
 * Sem biblioteca de PDF: CSS de impressao com @page A4, cabecalho de tabela
 * que repete em cada pagina e linhas que nao se quebram. Coerente com o
 * wrapper canonico sige_desp_print_page, mas dedicado e refinado para a
 * lista de devedores.
 *
 * @param string $escola_nome Nome da escola.
 * @param string $logo URL do logotipo (opcional).
 * @param array<int,object> $rows Dataset de devedores.
 */
if (!function_exists('sige_dev_print_page')) {
    function sige_dev_print_page($escola_nome, $logo, $rows) {
        $rows = is_array($rows) ? $rows : [];
        $total_devedores = count($rows);
        $total_divida = 0.0;
        $maior_divida = 0.0;
        foreach ($rows as $r) {
            $v = (float) ($r->divida_total ?? 0);
            $total_divida += $v;
            if ($v > $maior_divida) {
                $maior_divida = $v;
            }
        }
        $moeda = function_exists('sige_moeda') ? (string) sige_moeda() : 'MT';
        $ano = function_exists('sige_fin_get_ano_letivo_master')
            ? (int) sige_fin_get_ano_letivo_master()
            : (int) wp_date('Y');
        $fmt = static function ($v) {
            return number_format((float) $v, 2, ',', '.');
        };
        ?><!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html('Mapa de Cobranca - ' . $escola_nome); ?></title>
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{font-family:'Segoe UI',system-ui,Arial,sans-serif;color:#1e293b;background:#fff;padding:28px;max-width:900px;margin:0 auto}
        .toolbar{text-align:center;margin-bottom:22px}
        .toolbar button{font:inherit;font-weight:700;cursor:pointer;border-radius:8px;padding:10px 26px}
        .btn-print{background:#1e40af;color:#fff;border:none}
        .btn-close{background:#f1f5f9;color:#334155;border:1px solid #cbd5e1;margin-left:8px}
        .doc-head{display:flex;align-items:center;gap:16px;padding-bottom:16px;border-bottom:3px solid #1e293b;margin-bottom:18px}
        .doc-head img{width:62px;height:62px;object-fit:contain}
        .doc-head .info{flex:1}
        .doc-head .escola{font-size:19px;font-weight:800;color:#0f172a;line-height:1.2}
        .doc-head .tipo{font-size:12px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:1.5px;margin-top:3px}
        .doc-head .meta{font-size:11px;color:#94a3b8;text-align:right;line-height:1.5}
        .cards{display:flex;gap:12px;margin-bottom:18px}
        .card{flex:1;border:1px solid #e2e8f0;border-radius:10px;padding:12px 14px;background:#f8fafc}
        .card .label{font-size:10px;font-weight:700;color:#64748b;text-transform:uppercase;letter-spacing:.8px}
        .card .value{font-size:19px;font-weight:800;color:#0f172a;margin-top:4px}
        .card .value small{font-size:11px;font-weight:600;color:#94a3b8}
        table{width:100%;border-collapse:collapse}
        thead th{background:#0f172a;color:#fff;font-size:10.5px;text-transform:uppercase;letter-spacing:.5px;padding:9px 8px;text-align:left}
        thead th.num{text-align:right}
        tbody td{font-size:12px;padding:7px 8px;border-bottom:1px solid #eef2f7;vertical-align:top}
        tbody tr:nth-child(even){background:#fafbfc}
        td.idx{color:#94a3b8;font-size:11px;width:34px}
        td.aluno{font-weight:600}
        td.aluno small{display:block;font-weight:400;color:#94a3b8;font-size:10.5px;margin-top:1px}
        td.num{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap}
        td.divida{text-align:right;font-weight:800;color:#b91c1c;white-space:nowrap}
        tfoot td{font-size:13px;font-weight:800;padding:10px 8px;border-top:2px solid #0f172a;background:#f1f5f9}
        tfoot td.num{text-align:right;color:#b91c1c}
        .empty{padding:40px;text-align:center;color:#64748b;border:1px dashed #cbd5e1;border-radius:10px}
        .footer{margin-top:26px;padding-top:12px;border-top:1px solid #e2e8f0;text-align:center;font-size:10px;color:#94a3b8}
        @media print{
            body{padding:0;max-width:none}
            .no-print{display:none!important}
            thead{display:table-header-group}
            tfoot{display:table-footer-group}
            tr{page-break-inside:avoid}
            @page{size:A4;margin:12mm 14mm}
        }
    </style>
</head>
<body>
    <div class="toolbar no-print">
        <button class="btn-print" onclick="window.print()" type="button">Imprimir / Guardar PDF</button>
        <button class="btn-close" onclick="window.close()" type="button">Fechar</button>
    </div>

    <div class="doc-head">
        <?php if ($logo !== ''): ?><img src="<?php echo esc_url($logo); ?>" alt="Logotipo"><?php endif; ?>
        <div class="info">
            <div class="escola"><?php echo esc_html($escola_nome); ?></div>
            <div class="tipo">Mapa de Cobranca - Lista de Devedores</div>
        </div>
        <div class="meta">
            Ano lectivo: <strong><?php echo esc_html((string) $ano); ?></strong><br>
            Emitido em:<br>
            <strong><?php echo esc_html(wp_date('d/m/Y H:i')); ?></strong>
        </div>
    </div>

    <div class="cards">
        <div class="card">
            <div class="label">Alunos com divida</div>
            <div class="value"><?php echo esc_html((string) $total_devedores); ?></div>
        </div>
        <div class="card">
            <div class="label">Divida total acumulada</div>
            <div class="value"><?php echo esc_html($fmt($total_divida)); ?> <small><?php echo esc_html($moeda); ?></small></div>
        </div>
        <div class="card">
            <div class="label">Maior divida individual</div>
            <div class="value"><?php echo esc_html($fmt($maior_divida)); ?> <small><?php echo esc_html($moeda); ?></small></div>
        </div>
    </div>

    <?php if ($total_devedores === 0): ?>
        <div class="empty">Sem alunos com divida em aberto nesta escola.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th class="idx">#</th>
                    <th>Aluno</th>
                    <th>Turma / Classe</th>
                    <th>Contacto</th>
                    <th class="num">Mensalidades</th>
                    <th class="num">Divida (<?php echo esc_html($moeda); ?>)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $i => $r):
                    $turma = trim((string) ($r->turma_nome ?? ''));
                    $classe = trim((string) ($r->classe ?? ''));
                    $turma_classe = trim($turma . ($classe !== '' ? ' / ' . $classe : ''));
                    if ($turma_classe === '') {
                        $turma_classe = 'Sem turma';
                    }
                    $proc = trim((string) ($r->numero_processo ?? ''));
                ?>
                <tr>
                    <td class="idx"><?php echo esc_html((string) ($i + 1)); ?></td>
                    <td class="aluno">
                        <?php echo esc_html((string) ($r->nome_completo ?? 'Aluno')); ?>
                        <?php if ($proc !== ''): ?><small>Proc: <?php echo esc_html($proc); ?></small><?php endif; ?>
                    </td>
                    <td><?php echo esc_html($turma_classe); ?></td>
                    <td><?php echo esc_html(sige_fin_devedores_contacto($r)); ?></td>
                    <td class="num"><?php echo esc_html((string) (int) ($r->qtd_mensalidades ?? 0)); ?></td>
                    <td class="divida"><?php echo esc_html($fmt($r->divida_total ?? 0)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="5">Total em divida (<?php echo esc_html((string) $total_devedores); ?> alunos)</td>
                    <td class="num"><?php echo esc_html($fmt($total_divida) . ' ' . $moeda); ?></td>
                </tr>
            </tfoot>
        </table>
    <?php endif; ?>

    <div class="footer">
        Documento emitido pela secretaria - <?php echo esc_html($escola_nome); ?>. Valores calculados pela formula financeira canonica do SIGE.
    </div>
</body>
</html><?php
    }
}
