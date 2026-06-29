<?php
/**
 * SIGE SoftGenial - Alertas Templates & Notificações
 * Ficheiro: includes/alertas-templates.php
 *
 * Rendering de templates (WhatsApp + e-mail) e notificação dual-send (pai + mãe)
 * com debounce 24h por (alerta × canal × destinatário).
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │  TEMPLATES                                                         │
 * │                                                                    │
 * │  Armazenados em wp_options::sige_alertas_templates (JSON).         │
 * │  Estrutura:                                                        │
 * │    {                                                               │
 * │      "pagamento_atraso": {                                         │
 * │        "whatsapp": "Caro(a) {nome_encarregado}, ...",              │
 * │        "email_assunto": "Pagamento em atraso - {escola}",          │
 * │        "email_corpo": "<html>...</html>"                           │
 * │      },                                                            │
 * │      "mensalidade_falta": { ... }                                  │
 * │    }                                                               │
 * │                                                                    │
 * │  Se a option não existe, usa defaults do get_templates_default().  │
 * │  Fátima edita em Tesouraria → Alertas → Configuração.              │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * @since   13.5.0
 * @version 13.5.0-T2 (2026-04-19) - Turno 2: UI + notificação
 * @author  RMBJ Consultoria
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// OPTION KEY dos templates
// ============================================================================
if (!defined('SIGE_ALERTAS_OPT_TEMPLATES')) {
    define('SIGE_ALERTAS_OPT_TEMPLATES', 'sige_alertas_templates');
}

// Debounce em segundos entre notificações do mesmo (alerta × canal × destinatário)
if (!defined('SIGE_ALERTAS_DEBOUNCE_SEG')) {
    define('SIGE_ALERTAS_DEBOUNCE_SEG', DAY_IN_SECONDS); // 86400
}

// ============================================================================
// TEMPLATES DEFAULT
// ============================================================================
// Placeholders suportados em todos os templates:
//   {escola}              Nome da escola
//   {aluno_nome}          Nome completo do aluno
//   {aluno_primeiro_nome} Primeiro nome do aluno
//   {nome_encarregado}    Nome do destinatário (pai ou mãe)
//   {mes_referencia}      Mês formatado "Abril 2026"
//   {valor}               Valor formatado com moeda
//   {dias}                Dias de atraso (só pagamento_atraso)
//   {data_vencimento}     dd/mm/YYYY (só pagamento_atraso)
// ============================================================================
if (!function_exists('sige_alertas_get_templates_default')) {
    function sige_alertas_get_templates_default(): array {
        return [
            'pagamento_atraso' => [
                'whatsapp' =>
                    "Ponto de situação financeira - {escola}\n\n" .
                    "Caro(a) {nome_encarregado},\n\n" .
                    "A mensalidade de {aluno_primeiro_nome} referente a {mes_referencia} " .
                    "no valor de {valor} está em atraso há {dias} dias " .
                    "(vencimento: {data_vencimento}).\n\n" .
                    "Quando for possível, pedimos que contacte a secretaria para alinharmos a melhor forma de regularizar.\n\n" .
                    "Obrigado.",
                'email_assunto' =>
                    "Pagamento em atraso - {aluno_nome} - {escola}",
                'email_corpo' =>
                    "<p>Caro(a) <strong>{nome_encarregado}</strong>,</p>" .
                    "<p>A mensalidade de <strong>{aluno_nome}</strong> referente a " .
                    "<strong>{mes_referencia}</strong> no valor de <strong>{valor}</strong> " .
                    "está em atraso há <strong>{dias} dias</strong> " .
                    "(vencimento: {data_vencimento}).</p>" .
                    "<p>Quando for possível, pedimos que contacte a secretaria para alinharmos a melhor forma de regularizar.</p>" .
                    "<p>Obrigado,<br>{escola}</p>",
            ],

            'mensalidade_falta' => [
                'whatsapp' =>
                    "Mensalidade pendente - {escola}\n\n" .
                    "Caro(a) {nome_encarregado},\n\n" .
                    "A mensalidade de {aluno_primeiro_nome} referente a {mes_referencia} " .
                    "ainda não foi emitida/paga.\n\n" .
                    "Pedimos que contacte a secretaria para alinharmos a melhor forma de regularizar.\n\n" .
                    "Obrigado.",
                'email_assunto' =>
                    "Mensalidade pendente - {aluno_nome} - {mes_referencia}",
                'email_corpo' =>
                    "<p>Caro(a) <strong>{nome_encarregado}</strong>,</p>" .
                    "<p>A mensalidade de <strong>{aluno_nome}</strong> referente a " .
                    "<strong>{mes_referencia}</strong> ainda não foi emitida/paga.</p>" .
                    "<p>Pedimos que contacte a secretaria para alinharmos a melhor forma de regularizar.</p>" .
                    "<p>Obrigado,<br>{escola}</p>",
            ],

            // servico_sem_centro não tem template - é só dashboard/config interna

            // ── [v14.0.0-T2] COBRANÇA PROACTIVA - 4 fases, tom calibrado ───
            //
            // Tom por fase (PT-MZ, deliberadamente Moçambicano):
            //   D-5  → gentil, preventivo. "Recordamos" - não há atraso ainda.
            //   D-0  → curto, neutro. "Hoje é o dia" - forma de cortesia.
            //   D+5  → firme-amigável. "Encontra-se em atraso" - reforço de urgência.
            //   D+15 → firme. "Pedimos urgência" + menção a plano de pagamento.
            //
            // Placeholders já existentes em sige_alertas_build_placeholders() e
            // populados pelo metadata dos detectores: {escola}, {aluno_nome},
            // {aluno_primeiro_nome}, {nome_encarregado}, {mes_referencia},
            // {valor}, {data_vencimento}.
            //
            // Deliberadamente sem emojis pesados em D+15 - tom formal para
            // comunicações que podem acabar referenciadas em conversas difíceis.

            'cobranca_d_menos_5' => [
                'whatsapp' =>
                    "Lembrete da secretaria - {escola}\n\n"
                    . "Caro(a) {nome_encarregado},\n\n"
                    . "Recordamos que a mensalidade de {aluno_primeiro_nome} "
                    . "referente a {mes_referencia}, no valor de {valor}, "
                    . "vence a *{data_vencimento}* (daqui a 5 dias).\n\n"
                    . "Agradecemos a sua atenção.\n\n"
                    . "Obrigado.",
                'email_assunto' =>
                    "Lembrete de vencimento - {aluno_nome} - {mes_referencia}",
                'email_corpo' =>
                    "<p>Caro(a) <strong>{nome_encarregado}</strong>,</p>"
                    . "<p>Recordamos que a mensalidade de <strong>{aluno_nome}</strong> "
                    . "referente a <strong>{mes_referencia}</strong>, no valor de "
                    . "<strong>{valor}</strong>, vence a <strong>{data_vencimento}</strong> "
                    . "(daqui a 5 dias).</p>"
                    . "<p>Agradecemos a sua atenção.</p>"
                    . "<p>Obrigado,<br>{escola}</p>",
            ],

            'cobranca_d_zero' => [
                'whatsapp' =>
                    "Vencimento hoje - {escola}\n\n"
                    . "Caro(a) {nome_encarregado},\n\n"
                    . "Hoje é o dia do vencimento da mensalidade de {aluno_primeiro_nome} "
                    . "referente a {mes_referencia} - {valor}.\n\n"
                    . "Obrigado por manter o pagamento em dia.",
                // Cenário default canal='whatsapp_only' - mas mantemos
                // email por completude caso a Fátima opte por 'ambos' na UI.
                'email_assunto' =>
                    "Vencimento hoje - {aluno_nome} - {mes_referencia}",
                'email_corpo' =>
                    "<p>Caro(a) <strong>{nome_encarregado}</strong>,</p>"
                    . "<p>Hoje é o dia do vencimento da mensalidade de "
                    . "<strong>{aluno_nome}</strong> referente a "
                    . "<strong>{mes_referencia}</strong> - <strong>{valor}</strong>.</p>"
                    . "<p>Obrigado por manter o pagamento em dia.</p>"
                    . "<p>- {escola}</p>",
            ],

            'cobranca_d_mais_5' => [
                'whatsapp' =>
                    "Ponto de situação financeira - {escola}\n\n"
                    . "Caro(a) {nome_encarregado},\n\n"
                    . "A mensalidade de {aluno_primeiro_nome} referente a "
                    . "{mes_referencia} (vencimento {data_vencimento}) encontra-se "
                    . "5 dias em atraso - saldo {valor}.\n\n"
                    . "Agradecemos a sua atenção e ficamos disponíveis para alinhar a melhor forma de regularizar.\n\n"
                    . "Obrigado.",
                'email_assunto' =>
                    "Pagamento em atraso - {aluno_nome} - {mes_referencia}",
                'email_corpo' =>
                    "<p>Caro(a) <strong>{nome_encarregado}</strong>,</p>"
                    . "<p>A mensalidade de <strong>{aluno_nome}</strong> referente a "
                    . "<strong>{mes_referencia}</strong> (vencimento "
                    . "<strong>{data_vencimento}</strong>) encontra-se <strong>5 dias "
                    . "em atraso</strong> - saldo <strong>{valor}</strong>.</p>"
                    . "<p>Agradecemos a sua atenção e ficamos disponíveis para alinhar a melhor forma de regularizar.</p>"
                    . "<p>Obrigado,<br>{escola}</p>",
            ],

            'cobranca_d_mais_15' => [
                'whatsapp' =>
                    "*Pagamento em atraso há 15 dias* - {escola}\n\n"
                    . "Caro(a) {nome_encarregado},\n\n"
                    . "A mensalidade de {aluno_primeiro_nome} referente a "
                    . "{mes_referencia} encontra-se em atraso há *15 dias* - "
                    . "saldo {valor} (vencimento {data_vencimento}).\n\n"
                    . "Pedimos urgência na regularização para evitar suspensão de serviços. "
                    . "Se precisar, contacte a secretaria para combinar um plano de pagamento.\n\n"
                    . "Obrigado.",
                'email_assunto' =>
                    "Pagamento em atraso há 15 dias - {aluno_nome} - {mes_referencia}",
                'email_corpo' =>
                    "<p>Caro(a) <strong>{nome_encarregado}</strong>,</p>"
                    . "<p>A mensalidade de <strong>{aluno_nome}</strong> referente a "
                    . "<strong>{mes_referencia}</strong> encontra-se em atraso há "
                    . "<strong>15 dias</strong> - saldo <strong>{valor}</strong> "
                    . "(vencimento <strong>{data_vencimento}</strong>).</p>"
                    . "<p>Pedimos urgência na regularização para evitar suspensão de serviços. "
                    . "Se precisar, contacte a secretaria para combinar um plano de pagamento.</p>"
                    . "<p>Obrigado,<br>{escola}</p>",
            ],

            // queue_wpp_falhada NÃO tem template aqui - é enviado directamente
            // por sige_alertas_notificar_queue_falhada() porque o destinatário
            // é o Director (email interno), não o encarregado do aluno. A
            // engine dual-send de pai+mãe não se aplica.
        ];
    }
}

// ============================================================================
// LER templates (option + merge com defaults)
// ============================================================================
if (!function_exists('sige_alertas_get_templates')) {
    function sige_alertas_get_templates(): array {
        $defaults = sige_alertas_get_templates_default();
        $custom   = get_option(SIGE_ALERTAS_OPT_TEMPLATES, []);
        if (!is_array($custom)) $custom = [];

        // Merge por tipo (custom sobrepõe default por chave)
        $out = [];
        foreach ($defaults as $tipo => $tpl) {
            $override = $custom[$tipo] ?? [];
            $out[$tipo] = array_merge($tpl, is_array($override) ? $override : []);
        }
        return $out;
    }
}

// ============================================================================
// GUARDAR templates (Fátima edita; só admins podem)
// ============================================================================
if (!function_exists('sige_alertas_save_templates')) {
    function sige_alertas_save_templates(array $templates): bool {
        // Saneamento: aceitar só tipos conhecidos e só chaves conhecidas
        $allowed_tipos = ['pagamento_atraso', 'mensalidade_falta'];
        $allowed_keys  = ['whatsapp', 'email_assunto', 'email_corpo'];

        $clean = [];
        foreach ($allowed_tipos as $t) {
            if (empty($templates[$t]) || !is_array($templates[$t])) continue;
            foreach ($allowed_keys as $k) {
                if (isset($templates[$t][$k])) {
                    // Preservar quebras de linha e tags básicas mas prevenir scripts
                    $val = wp_kses($templates[$t][$k], [
                        'p' => [],
                        'br' => [],
                        'strong' => [],
                        'em' => [],
                        'b' => [],
                        'i' => [],
                        'span' => ['style' => []],
                        'div'  => ['style' => []],
                        'a' => ['href' => [], 'title' => []],
                        'ul' => [],
                        'ol' => [],
                        'li' => [],
                    ]);
                    $clean[$t][$k] = $val;
                }
            }
        }

        return update_option(SIGE_ALERTAS_OPT_TEMPLATES, $clean, false);
    }
}

// ============================================================================
// RENDER - substitui placeholders num template
// ============================================================================
/**
 * @param string $template   String com {placeholders}
 * @param array  $placeholders { chave => valor, sem chaves - ex: 'aluno_nome' => 'João' }
 * @return string
 */
if (!function_exists('sige_alerta_render_template')) {
    function sige_alerta_render_template(string $template, array $placeholders): string {
        $out = $template;
        foreach ($placeholders as $k => $v) {
            $key = '{' . $k . '}';
            $out = str_replace($key, (string)$v, $out);
        }
        // Limpar placeholders não resolvidos (evita "{dias}" visível numa mensagem
        // de tipo que não preenche esse campo)
        $out = preg_replace('/\{[a-z_][a-z0-9_]*\}/i', '', $out);
        return $out;
    }
}

// ============================================================================
// HELPER - formatar mês "Abril 2026" a partir de "2026-04"
// ============================================================================
if (!function_exists('sige_alertas_fmt_mes')) {
    function sige_alertas_fmt_mes(?string $mes_ref): string {
        if (empty($mes_ref)) return '';
        $parts = explode('-', $mes_ref);
        if (count($parts) < 2) return $mes_ref;
        $ts = mktime(0, 0, 0, (int)$parts[1], 1, (int)$parts[0]);
        return $ts ? wp_date('F Y', $ts) : $mes_ref;
    }
}

// ============================================================================
// HELPER - formatar valor com moeda
// ============================================================================
if (!function_exists('sige_alertas_fmt_valor')) {
    function sige_alertas_fmt_valor($valor): string {
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        return number_format((float)$valor, 2, ',', '.') . ' ' . $moeda;
    }
}

// ============================================================================
// HELPER - construir placeholders a partir de um alerta + encarregado
// ============================================================================
/**
 * Constrói o array de placeholders completo a partir do row do alerta e do
 * aluno, para um destinatário específico (pai ou mãe).
 *
 * @param object $alerta    Row de sige_alertas
 * @param object $aluno     Row de sige_alunos
 * @param string $nome_enc  Nome do destinatário (ex: nome_pai ou nome_mae)
 * @return array
 */
if (!function_exists('sige_alertas_build_placeholders')) {
    function sige_alertas_build_placeholders(object $alerta, object $aluno, string $nome_enc): array {
        $meta = [];
        if (!empty($alerta->metadata)) {
            $decoded = json_decode((string)$alerta->metadata, true);
            if (is_array($decoded)) $meta = $decoded;
        }

        // Nome da escola
        $escola_nome = get_bloginfo('name');
        if (function_exists('sige_get_escola_perfil')) {
            $perfil = sige_get_escola_perfil();
            if (!empty($perfil->nome_escola)) {
                $escola_nome = $perfil->nome_escola;
            }
        }

        $primeiro_nome = '';
        if (!empty($aluno->nome_completo)) {
            $primeiro_nome = explode(' ', trim((string)$aluno->nome_completo))[0];
        }

        $venc_fmt = '';
        if (!empty($meta['data_vencimento'])) {
            $ts = strtotime((string)$meta['data_vencimento']);
            $venc_fmt = $ts ? wp_date('d/m/Y', $ts) : (string)$meta['data_vencimento'];
        }

        return [
            'escola'              => $escola_nome,
            'aluno_nome'          => (string)($aluno->nome_completo ?? ''),
            'aluno_primeiro_nome' => $primeiro_nome,
            'nome_encarregado'    => $nome_enc ?: 'Encarregado(a)',
            'mes_referencia'      => sige_alertas_fmt_mes($meta['mes_referencia'] ?? null),
            'valor'               => !empty($meta['valor'])
                ? sige_alertas_fmt_valor($meta['valor'])
                : '',
            'dias'                => (string)($meta['dias_atraso'] ?? ''),
            'data_vencimento'     => $venc_fmt,
        ];
    }
}

// ============================================================================
// DEBOUNCE - já enviei para este destinatário neste alerta nas últimas 24h?
// ============================================================================
if (!function_exists('sige_alertas_debounce_ok')) {
    function sige_alertas_debounce_ok(int $alerta_id, string $canal, string $destinatario): bool {
        global $wpdb;
        $t = $wpdb->prefix . 'sige_alertas_envios';

        // Só conta envios com sucesso OU pendente recente - não queremos contar
        // falhas definitivas (se falhou, faz sentido tentar de novo).
        $desde = wp_date('Y-m-d H:i:s', time() - SIGE_ALERTAS_DEBOUNCE_SEG);

        $count = (int)$wpdb->get_var($wpdb->prepare("
            SELECT COUNT(*) FROM {$t}
            WHERE alerta_id = %d
              AND canal = %s
              AND destinatario = %s
              AND status IN ('sucesso','pendente')
              AND enviado_em >= %s
        ", $alerta_id, $canal, $destinatario, $desde));

        return ($count === 0);
    }
}

// ============================================================================
// REGISTAR envio (sucesso ou falha) em sige_alertas_envios
// ============================================================================
if (!function_exists('sige_alertas_registar_envio')) {
    function sige_alertas_registar_envio(
        int $alerta_id,
        int $escola_id,
        string $canal,
        string $destinatario,
        string $destinatario_tipo,
        string $status,
        ?string $erro = null,
        int $user_id = 0
    ): int {
        global $wpdb;
        $t = $wpdb->prefix . 'sige_alertas_envios';

        $wpdb->insert($t, [
            'alerta_id'         => $alerta_id,
            'escola_id'         => $escola_id,
            'canal'             => $canal,
            'destinatario'      => $destinatario,
            'destinatario_tipo' => $destinatario_tipo,
            'status'            => $status,
            'erro'              => $erro ? substr((string)$erro, 0, 500) : null,
            'enviado_em'        => current_time('mysql'),
            'user_id'           => $user_id ?: null,
        ], ['%d','%d','%s','%s','%s','%s','%s','%s','%d']);

        return (int)$wpdb->insert_id;
    }
}

// ============================================================================
// ENVIAR WhatsApp (dual - pai + mãe) com templates e debounce
// ============================================================================
if (!function_exists('sige_alertas_enviar_whatsapp_dual')) {
    function sige_alertas_enviar_whatsapp_dual(object $alerta, object $aluno, int $user_id = 0): array {
        $templates = sige_alertas_get_templates();
        $tpl = $templates[$alerta->tipo] ?? null;
        if (empty($tpl['whatsapp'])) {
            return ['tentativas' => 0, 'sucessos' => 0, 'falhas' => 0, 'detalhes' => []];
        }

        $destinatarios = [
            ['tipo' => 'pai', 'nome' => (string)($aluno->nome_pai ?? ''), 'tel' => (string)($aluno->telemovel_pai ?? '')],
            ['tipo' => 'mae', 'nome' => (string)($aluno->nome_mae ?? ''), 'tel' => (string)($aluno->telemovel_mae ?? '')],
        ];

        $tentativas = 0;
        $sucessos   = 0;
        $falhas     = 0;
        $detalhes   = [];

        foreach ($destinatarios as $d) {
            $tel_raw = trim($d['tel']);
            if ($tel_raw === '') {
                // Sem número - regista como falha com motivo claro
                sige_alertas_registar_envio(
                    (int)$alerta->id,
                    (int)$alerta->escola_id,
                    'whatsapp',
                    '',
                    $d['tipo'],
                    'falha',
                    'Sem número de telefone registado',
                    $user_id
                );
                $falhas++;
                $detalhes[] = ['canal' => 'whatsapp', 'tipo' => $d['tipo'], 'status' => 'falha', 'motivo' => 'sem número'];
                continue;
            }

            $tel_norm = function_exists('sige_telefone_normalizar')
                ? sige_telefone_normalizar($tel_raw)
                : preg_replace('/[^0-9]/', '', $tel_raw);

            if (strlen($tel_norm) < 9) {
                sige_alertas_registar_envio(
                    (int)$alerta->id,
                    (int)$alerta->escola_id,
                    'whatsapp',
                    $tel_norm,
                    $d['tipo'],
                    'falha',
                    'Número inválido após normalização',
                    $user_id
                );
                $falhas++;
                $detalhes[] = ['canal' => 'whatsapp', 'tipo' => $d['tipo'], 'status' => 'falha', 'motivo' => 'número inválido'];
                continue;
            }

            // Debounce 24h
            if (!sige_alertas_debounce_ok((int)$alerta->id, 'whatsapp', $tel_norm)) {
                $detalhes[] = ['canal' => 'whatsapp', 'tipo' => $d['tipo'], 'status' => 'debounce', 'motivo' => 'enviado há menos de 24h'];
                continue;
            }

            // Render com placeholders específicos do destinatário
            $placeholders = sige_alertas_build_placeholders($alerta, $aluno, $d['nome']);

            // v12.9.54 - Hotfix: quando o renderer v2 conversacional está
            // disponível, usamos sempre o tom v2 (cobrança) para qualquer
            // alerta financeiro de pagamento/mensalidade/cobrança. Os
            // templates antigos em alertas-templates.php (com asteriscos,
            // emojis pesados e tom imperativo) deixam de chegar ao
            // encarregado pelo caminho dos alertas.
            $usar_v2 = function_exists('sige_wpp_tpl_v2_render');
            if ($usar_v2) {
                global $wpdb;
                $cfg_row = $wpdb->get_row($wpdb->prepare(
                    "SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
                    (int)$alerta->escola_id
                ));

                $valor_num = 0.0;
                if (isset($placeholders['valor'])) {
                    $bruto = preg_replace('/[^0-9,\.]/', '', (string)$placeholders['valor']);
                    $bruto = str_replace('.', '', $bruto);
                    $bruto = str_replace(',', '.', $bruto);
                    $valor_num = (float)$bruto;
                }

                $vars_v2 = [
                    'encarregado' => (string)$d['nome'],
                    'valor'       => $valor_num,
                    'vencimento'  => (string)($placeholders['data_vencimento'] ?? ''),
                    'detalhes'    => sprintf('• %s', (string)($placeholders['aluno_primeiro_nome'] ?? '') ?: 'Mensalidade'),
                    'mes'         => (string)($placeholders['mes_referencia'] ?? ''),
                    'id'          => (string)$alerta->id,
                ];
                $mensagem = sige_wpp_tpl_v2_render('cobranca', $cfg_row, $aluno, $vars_v2);
            } else {
                $mensagem = sige_alerta_render_template((string)$tpl['whatsapp'], $placeholders);
            }

            if (function_exists('sige_notify_humanize_outbound_whatsapp')) {
                $mensagem = sige_notify_humanize_outbound_whatsapp($mensagem, 'alerta_' . (string)$alerta->tipo, [
                    'aluno_id' => (int)$alerta->aluno_id,
                    'telefone' => $tel_norm,
                ]);
            }

            $tentativas++;

            // Empurrar via sige_fin_queue_whatsapp - a fila decide o ritmo seguro.
            if (function_exists('sige_fin_queue_whatsapp')) {
                $ok = sige_fin_queue_whatsapp((int)$alerta->aluno_id, $tel_norm, 'alerta_' . $alerta->tipo, $mensagem);
                $status = $ok ? 'sucesso' : 'falha';
                $erro   = $ok ? null : 'queue_whatsapp devolveu false';
            } else {
                $status = 'falha';
                $erro   = 'sige_fin_queue_whatsapp indisponível';
            }

            sige_alertas_registar_envio(
                (int)$alerta->id,
                (int)$alerta->escola_id,
                'whatsapp',
                $tel_norm,
                $d['tipo'],
                $status,
                $erro,
                $user_id
            );

            if ($status === 'sucesso') { $sucessos++; $detalhes[] = ['canal'=>'whatsapp','tipo'=>$d['tipo'],'status'=>'sucesso','numero'=>$tel_norm]; }
            else                       { $falhas++;   $detalhes[] = ['canal'=>'whatsapp','tipo'=>$d['tipo'],'status'=>'falha',  'motivo'=>$erro]; }
        }

        return ['tentativas' => $tentativas, 'sucessos' => $sucessos, 'falhas' => $falhas, 'detalhes' => $detalhes];
    }
}

// ============================================================================
// ENVIAR E-mail (dual - pai + mãe) com templates e debounce
// ============================================================================
if (!function_exists('sige_alertas_enviar_email_dual')) {
    function sige_alertas_enviar_email_dual(object $alerta, object $aluno, int $user_id = 0): array {
        $templates = sige_alertas_get_templates();
        $tpl = $templates[$alerta->tipo] ?? null;
        if (empty($tpl['email_assunto']) || empty($tpl['email_corpo'])) {
            return ['tentativas' => 0, 'sucessos' => 0, 'falhas' => 0, 'detalhes' => []];
        }

        $destinatarios = [
            ['tipo' => 'pai', 'nome' => (string)($aluno->nome_pai ?? ''), 'email' => (string)($aluno->email_pai ?? '')],
            ['tipo' => 'mae', 'nome' => (string)($aluno->nome_mae ?? ''), 'email' => (string)($aluno->email_mae ?? '')],
        ];

        $tentativas = 0;
        $sucessos   = 0;
        $falhas     = 0;
        $detalhes   = [];

        foreach ($destinatarios as $d) {
            $email = trim($d['email']);
            if ($email === '' || !is_email($email)) {
                sige_alertas_registar_envio(
                    (int)$alerta->id,
                    (int)$alerta->escola_id,
                    'email',
                    $email,
                    $d['tipo'],
                    'falha',
                    $email === '' ? 'Sem e-mail registado' : 'E-mail inválido',
                    $user_id
                );
                $falhas++;
                $detalhes[] = ['canal' => 'email', 'tipo' => $d['tipo'], 'status' => 'falha', 'motivo' => $email === '' ? 'sem e-mail' : 'inválido'];
                continue;
            }

            if (!sige_alertas_debounce_ok((int)$alerta->id, 'email', $email)) {
                $detalhes[] = ['canal' => 'email', 'tipo' => $d['tipo'], 'status' => 'debounce', 'motivo' => 'enviado há menos de 24h'];
                continue;
            }

            $placeholders = sige_alertas_build_placeholders($alerta, $aluno, $d['nome']);
            $assunto = sige_alerta_render_template((string)$tpl['email_assunto'], $placeholders);
            $corpo   = sige_alerta_render_template((string)$tpl['email_corpo'], $placeholders);
            if (function_exists('sige_notify_humanize_outbound_email_html')) {
                $assunto = sige_notify_humanize_outbound_email_html($assunto);
                $corpo   = sige_notify_humanize_outbound_email_html($corpo);
            }

            $tentativas++;

            $headers = ['Content-Type: text/html; charset=UTF-8'];
            $ok = wp_mail($email, $assunto, $corpo, $headers);

            sige_alertas_registar_envio(
                (int)$alerta->id,
                (int)$alerta->escola_id,
                'email',
                $email,
                $d['tipo'],
                $ok ? 'sucesso' : 'falha',
                $ok ? null : 'wp_mail devolveu false (ver SMTP logs)',
                $user_id
            );

            if ($ok) { $sucessos++; $detalhes[] = ['canal'=>'email','tipo'=>$d['tipo'],'status'=>'sucesso','email'=>$email]; }
            else     { $falhas++;   $detalhes[] = ['canal'=>'email','tipo'=>$d['tipo'],'status'=>'falha','motivo'=>'wp_mail falhou']; }
        }

        return ['tentativas' => $tentativas, 'sucessos' => $sucessos, 'falhas' => $falhas, 'detalhes' => $detalhes];
    }
}

// ============================================================================
// NOTIFICAR - fachada pública. Chama WA + email. Usado pelo AJAX.
// ============================================================================
if (!function_exists('sige_alerta_notificar')) {
    function sige_alerta_notificar(int $alerta_id, int $user_id = 0): array {
        global $wpdb;

        $alerta = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}sige_alertas WHERE id = %d LIMIT 1",
            $alerta_id
        ));
        if (!$alerta) {
            return ['erro' => 'Alerta não encontrado', 'alerta_id' => $alerta_id];
        }
        if ((int)$alerta->aluno_id <= 0) {
            return ['erro' => 'Alerta sem aluno associado (tipo ' . $alerta->tipo . ')', 'alerta_id' => $alerta_id];
        }
        // Este tipo não gera notificação
        if ($alerta->tipo === SIGE_ALERTA_SERVICO_SEM_CENTRO) {
            return ['erro' => 'Este tipo de alerta não gera notificação', 'alerta_id' => $alerta_id];
        }

        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nome_completo, nome_pai, nome_mae,
                    telemovel_pai, telemovel_mae, email_pai, email_mae
             FROM {$wpdb->prefix}sige_alunos
             WHERE id = %d AND escola_id = %d
             LIMIT 1",
            (int)$alerta->aluno_id, (int)$alerta->escola_id
        ));
        if (!$aluno) {
            return ['erro' => 'Aluno do alerta não encontrado', 'alerta_id' => $alerta_id];
        }

        $wa = sige_alertas_enviar_whatsapp_dual($alerta, $aluno, $user_id);
        $em = sige_alertas_enviar_email_dual($alerta, $aluno, $user_id);

        if (function_exists('sige_fin_log')) {
            sige_fin_log('alerta_notificado', [
                'alerta_id'  => $alerta_id,
                'aluno_id'   => (int)$alerta->aluno_id,
                'whatsapp'   => ['tentativas'=>$wa['tentativas'],'sucessos'=>$wa['sucessos'],'falhas'=>$wa['falhas']],
                'email'      => ['tentativas'=>$em['tentativas'],'sucessos'=>$em['sucessos'],'falhas'=>$em['falhas']],
                'user_id'    => $user_id ?: get_current_user_id(),
            ]);
        }

        // Actualizar timestamp do alerta
        $wpdb->update(
            $wpdb->prefix . 'sige_alertas',
            ['actualizado_em' => current_time('mysql')],
            ['id' => $alerta_id],
            ['%s'],
            ['%d']
        );

        return [
            'alerta_id' => $alerta_id,
            'whatsapp'  => $wa,
            'email'     => $em,
            'total_sucessos' => $wa['sucessos'] + $em['sucessos'],
            'total_falhas'   => $wa['falhas']   + $em['falhas'],
            'total_debounce' => count(array_filter(array_merge($wa['detalhes'], $em['detalhes']), function($d){ return ($d['status'] ?? '') === 'debounce'; })),
        ];
    }
}
