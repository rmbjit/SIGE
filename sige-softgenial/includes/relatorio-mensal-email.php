<?php
/**
 * SIGE SoftGenial - Relatório Mensal Automático à Direcção
 *
 * No dia 1 de cada mês envia por email um resumo do MÊS FECHADO:
 * receita recebida, valores pendentes, taxa de cobrança e top devedores.
 * Não cria números novos: lê os mesmos KPIs canónicos do Dashboard
 * Financeiro (fin-kpi-engine), por isso bate sempre certo com o ecrã.
 *
 * OFF por defeito. Ligar:
 *   update_option('sige_relatorio_mensal_email', 'on');
 * Destinatários (separados por vírgula; vazio = email do administrador):
 *   update_option('sige_relatorio_mensal_destinatarios', 'director@escola.mz');
 *
 * Idempotente: marca o mês enviado em sige_relatorio_mensal_ultimo e
 * nunca duplica, mesmo que o cron diário corra mais do que uma vez.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_relatorio_mensal_mz')) {
    function sige_relatorio_mensal_mz(float $v): string {
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        return number_format($v, 2, ',', '.') . ' ' . $moeda;
    }
}

if (!function_exists('sige_relatorio_mensal_compor')) {
    /** Devolve ['assunto'=>..., 'html'=>...] para o mês/ano indicados. */
    function sige_relatorio_mensal_compor(int $escola_id, int $ano, int $mes, string $escola_nome): array {
        $meses = ['', 'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        $nome_mes = $meses[$mes] ?? (string)$mes;

        $receita_mes = 0.0;
        if (function_exists('sige_kpi_por_mes')) {
            $por_mes = sige_kpi_por_mes($escola_id, $ano);
            foreach ((array)$por_mes as $linha) {
                $lm = is_object($linha) ? (array)$linha : (array)$linha;
                $m = (int)($lm['mes'] ?? $lm['m'] ?? 0);
                if ($m === $mes) { $receita_mes = (float)($lm['total'] ?? $lm['receita'] ?? $lm['valor'] ?? 0); break; }
            }
        }
        $pendente = function_exists('sige_kpi_pendente') ? (float)sige_kpi_pendente($escola_id, $ano) : 0.0;
        $atraso   = function_exists('sige_kpi_atraso') ? (float)sige_kpi_atraso($escola_id, $ano) : 0.0;
        $taxa     = function_exists('sige_kpi_taxa_cobranca') ? (float)sige_kpi_taxa_cobranca($escola_id, $ano) : 0.0;
        $top      = function_exists('sige_kpi_top_devedores') ? (array)sige_kpi_top_devedores($escola_id, 5, 0, $ano) : [];

        $linhas_top = '';
        foreach ($top as $d) {
            $d = (array)$d;
            $nome = esc_html((string)($d['nome'] ?? $d['nome_completo'] ?? $d['aluno'] ?? '')); 
            $val = sige_relatorio_mensal_mz((float)($d['divida'] ?? $d['total'] ?? $d['saldo'] ?? 0));
            if ($nome === '') continue;
            $linhas_top .= "<tr><td style='padding:6px 10px;border-bottom:1px solid #eef2f7;'>{$nome}</td><td style='padding:6px 10px;border-bottom:1px solid #eef2f7;text-align:right;'>{$val}</td></tr>";
        }
        if ($linhas_top === '') {
            $linhas_top = "<tr><td colspan='2' style='padding:8px 10px;color:#64748b;'>Sem devedores relevantes. Excelente sinal.</td></tr>";
        }

        $assunto = sprintf('%s - Resumo financeiro de %s %d', $escola_nome !== '' ? $escola_nome : 'SIGE', $nome_mes, $ano);
        $kpi = static function (string $rotulo, string $valor): string {
            return "<td style='padding:14px;background:#f8fafc;border-radius:10px;'><div style='font-size:12px;color:#64748b;'>{$rotulo}</div><div style='font-size:18px;font-weight:700;color:#0d1259;margin-top:2px;'>{$valor}</div></td>";
        };
        $html = "<div style='font-family:Segoe UI,Arial,sans-serif;max-width:640px;margin:auto;color:#0f172a;'>"
              . "<h2 style='color:#0d1259;margin-bottom:4px;'>" . esc_html($escola_nome) . "</h2>"
              . "<p style='margin-top:0;color:#475569;'>Resumo financeiro automático de <strong>{$nome_mes} de {$ano}</strong>.</p>"
              . "<table role='presentation' style='width:100%;border-collapse:separate;border-spacing:8px;'><tr>"
              . $kpi('Receita recebida no mês', sige_relatorio_mensal_mz($receita_mes))
              . $kpi('Taxa de cobrança do ano', number_format($taxa, 1, ',', '.') . '%')
              . "</tr><tr>"
              . $kpi('Pendente do ano', sige_relatorio_mensal_mz($pendente))
              . $kpi('Em atraso', sige_relatorio_mensal_mz($atraso))
              . "</tr></table>"
              . "<h3 style='color:#0d1259;margin:18px 0 8px;'>Top 5 devedores</h3>"
              . "<table role='presentation' style='width:100%;border-collapse:collapse;background:#fff;border:1px solid #eef2f7;border-radius:10px;'>{$linhas_top}</table>"
              . "<p style='color:#64748b;font-size:12px;margin-top:16px;'>Gerado automaticamente pelo SIGE SoftGenial no dia 1. Os valores são os mesmos do Painel Financeiro. Para deixar de receber, peça ao administrador do sistema.</p>"
              . "</div>";

        return ['assunto' => $assunto, 'html' => $html];
    }
}

// Engancha no evento diário já agendado: corre só no dia 1 e só uma vez por mês.
add_action('sige_evento_diario', function () {
    if (get_option('sige_relatorio_mensal_email', 'off') !== 'on') return;
    if ((int) current_time('j') !== 1) return;

    $ref = (string) current_time('Y-m'); // mês corrente; o relatório é do anterior
    if (get_option('sige_relatorio_mensal_ultimo', '') === $ref) return; // idempotência

    $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    $ts_mes_passado = strtotime('first day of last month', current_time('timestamp'));
    $ano = (int) date('Y', $ts_mes_passado);
    $mes = (int) date('n', $ts_mes_passado);

    $escola_nome = '';
    if (function_exists('sige_get_escola_perfil')) {
        $perfil = sige_get_escola_perfil();
        $escola_nome = $perfil && !empty($perfil->nome) ? (string)$perfil->nome : '';
    }

    $rel = sige_relatorio_mensal_compor($escola_id, $ano, $mes, $escola_nome);

    $dest_raw = (string) get_option('sige_relatorio_mensal_destinatarios', '');
    $dest = array_filter(array_map('sanitize_email', array_map('trim', explode(',', $dest_raw))));
    if (empty($dest)) $dest = [get_bloginfo('admin_email')];

    $headers = ['Content-Type: text/html; charset=UTF-8'];
    $enviado = wp_mail($dest, $rel['assunto'], $rel['html'], $headers);

    if ($enviado) {
        update_option('sige_relatorio_mensal_ultimo', $ref, false);
        if (function_exists('sige_security_log')) {
            sige_security_log('relatorio_mensal_enviado', 'mes=' . $mes . '/' . $ano . ' dest=' . count($dest));
        }
    } elseif (function_exists('sige_security_log')) {
        sige_security_log('relatorio_mensal_falhou', 'mes=' . $mes . '/' . $ano);
    }
}, 20);
