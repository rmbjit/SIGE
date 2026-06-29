<?php
if (!defined('ABSPATH')) exit;
// [FIX R-02] Guard de acesso - Tesouraria (Config)
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.servicos_ver','financeiro.configurar_precos'],
    ['sige_director','sige_secretario']
)) return;

/*

 * Módulo: Catálogo de Serviços e Preços (CRUD + Auto-Reparação alinhada à BD real)

 * Autor: SIGE SoftGenial

 */

if (!defined('ABSPATH')) exit;

global $wpdb;

// Permissões
// [12.9.6] Verificação fina interna - usa matriz para `configurar_precos`
// (operação crítica). A guarda de entrada acima já garantiu o acesso ao
// módulo; esta linha protege especificamente a edição.
if (!sige_page_guard_allows(
    ['financeiro.configurar_precos'],
    ['sige_fin_config','sige_secretario']
)) {

    echo '<div class="notice notice-error"><p>Sem permissão para configurar preços.</p></div>';

    return;

}

// [v15.2.0 - N2] require_once upgrade.php removido - só era necessário para dbDelta
// que foi eliminado. Schema gerido por class-sige-migration.php.

$tabela     = $wpdb->prefix . 'sige_fin_servicos';
$escola_id  = sige_require_escola_id('financeiro_config');
$ano_letivo = function_exists('sige_fin_get_ano_letivo_master') ? (int)sige_fin_get_ano_letivo_master() : (int)wp_date('Y');

$tbl_config = $wpdb->prefix . 'sige_fin_configuracoes';

// ==============================================================================

// 0) MIGRAÇÃO - garantir colunas de multa escalada e descontos em % na config

// ==============================================================================

// Colunas de sige_fin_configuracoes - geridas por class-sige-migration.php
// Removido ALTER TABLE runtime (redundante desde SCHEMA_VERSION 20260404.2)

// Ler config actual

$cfg = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $tbl_config WHERE escola_id = %d ORDER BY id DESC LIMIT 1",
    sige_get_escola_id()
));

if (!function_exists('sige_fin_cfg_prop')) {
    function sige_fin_cfg_prop($cfg, string $prop, $default = null) {
        if (is_object($cfg) && property_exists($cfg, $prop)) return $cfg->{$prop};
        if (is_array($cfg) && array_key_exists($prop, $cfg)) return $cfg[$prop];
        return $default;
    }
}

// ==============================================================================

// 0b) SALVAR CONFIG FINANCEIRA GLOBAL

// ==============================================================================

$cfg_feedback = '';

if (

    $_SERVER['REQUEST_METHOD'] === 'POST' &&

    isset($_POST['sige_save_cfg_nonce']) &&

    wp_verify_nonce($_POST['sige_save_cfg_nonce'], 'sige_save_cfg_financeiro')

) {

    $dados_cfg = [

        'multa_tier1_dias'               => max(0, sige_fin_post_int('multa_tier1_dias', 10)),

        'multa_tier1_pct'                => max(0.0, sige_fin_post_float('multa_tier1_pct')),

        'multa_tier2_dias'               => max(0, sige_fin_post_int('multa_tier2_dias', 30)),

        'multa_tier2_pct'                => max(0.0, sige_fin_post_float('multa_tier2_pct')),

        'multa_tier3_dias'               => max(0, sige_fin_post_int('multa_tier3_dias', 60)),

        'multa_tier3_pct'                => max(0.0, sige_fin_post_float('multa_tier3_pct')),

        // Descontos automáticos: valor fixo (MT) ou percentagem (%).
        'desconto_irmaos_tipo'           => in_array(sige_fin_post_param('desconto_irmaos_tipo', 'mt'), ['mt','percentual'], true) ? sige_fin_post_param('desconto_irmaos_tipo', 'mt') : 'mt',
        'desconto_irmaos_mt'             => max(0.0, sige_fin_post_float('desconto_irmaos_mt', sige_fin_post_float('desconto_irmaos_pct'))),
        'desconto_irmaos_pct'            => min(100.0, max(0.0, sige_fin_post_float('desconto_irmaos_pct'))),
        'desconto_funcionario_tipo'      => in_array(sige_fin_post_param('desconto_funcionario_tipo', 'mt'), ['mt','percentual'], true) ? sige_fin_post_param('desconto_funcionario_tipo', 'mt') : 'mt',
        'desconto_funcionario_mt'        => max(0.0, sige_fin_post_float('desconto_funcionario_mt', sige_fin_post_float('desconto_funcionario_percentual'))),
        'desconto_funcionario_percentual'=> min(100.0, max(0.0, sige_fin_post_float('desconto_funcionario_percentual'))),
        'desc_adiant_6m_pct'             => max(0.0, sige_fin_post_float('desc_adiant_6m_pct')),
        'desc_adiant_12m_pct'            => max(0.0, sige_fin_post_float('desc_adiant_12m_pct')),

        'creche_semi_ate4'               => max(0.0, sige_fin_post_float('creche_semi_ate4')),

        'creche_semi_5anos'              => max(0.0, sige_fin_post_float('creche_semi_5anos')),

        'creche_integral_ate4'           => max(0.0, sige_fin_post_float('creche_integral_ate4')),

        'creche_integral_5anos'          => max(0.0, sige_fin_post_float('creche_integral_5anos')),

        // Fase 2 - campos dinâmicos
        'prefixo_telefone'               => sige_fin_post_param('prefixo_telefone', '258'),
        'formato_telefone_digitos'       => max(1, sige_fin_post_int('formato_telefone_digitos', 9)),
        'moeda_simbolo'                  => sige_fin_post_param('moeda_simbolo', 'MT'),
        'timezone'                       => sige_fin_post_param('timezone', 'Africa/Maputo'),

    ];

    if ($cfg) {

        $ok = $wpdb->update($tbl_config, $dados_cfg, ['id' => (int)$cfg->id]);

    } else {

        $dados_cfg['escola_id'] = sige_get_escola_id();

        $ok = $wpdb->insert($tbl_config, $dados_cfg);

    }

    $cfg_feedback = ($ok !== false)

        ? '<div class="notice notice-success is-dismissible"><p>Configuração financeira guardada.</p></div>'

        : '<div class="notice notice-error"><p>Erro: ' . 'Contacte o administrador.</p></div>';

    // Recarregar

    $cfg = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $tbl_config WHERE escola_id = %d ORDER BY id DESC LIMIT 1",
        sige_get_escola_id()
    ));

}

// [12.9.15] Guard visual: em instalações novas ou escolas sem linha em
// sige_fin_configuracoes, $cfg pode vir NULL. Mantemos a lógica de gravação
// intacta acima e normalizamos apenas para a camada de leitura/renderização,
// evitando warnings ao abrir a página de configuração de preços.
if (!is_object($cfg)) {
    $cfg = (object) [];
}

// v12.9.81 - garantir que o serviço financeiro de transporte existe e fica
// configurável. O valor mensal continua a vir da rota do aluno; aqui ficam
// apenas as regras financeiras (multa/descontos) aplicáveis ao transporte.
$transport_srv_cfg = function_exists('sige_fin_get_ou_criar_servico_transporte')
    ? sige_fin_get_ou_criar_servico_transporte($escola_id)
    : $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE escola_id=%d AND LOWER(tipo)='transporte' LIMIT 1", $escola_id));

// ==============================================================================

// 1) AUTO-REPARAÇÃO REMOVIDA - schema gerido por class-sige-migration.php
// [v15.2.0 - N2] dbDelta runtime removido. A tabela sige_fin_servicos é
// criada e mantida pelo class-sige-migration.php::create_tables(). Correr
// dbDelta em cada carga da página era overhead desnecessário + risco de
// divergência (dbDelta vs CREATE TABLE no migration).

// ==============================================================================

// 2) HELPERS

// ==============================================================================

function sige_fin_opt($val, $selected) {

    return ((string)$val === (string)$selected) ? 'selected' : '';

}

function sige_fin_norm_tipo($tipo) {

    $tipo = strtolower(trim((string)$tipo));

    $tipo = preg_replace('/\s+/', '_', $tipo);

    $tipo = preg_replace('/[^a-z0-9_]/', '', $tipo);

    return $tipo;

}

// Mapa de classes para label legível (usado no select E na tabela)

function sige_fin_classe_label(string $classe): string {

    $map = [

        'todas'    => 'Geral',

        '2º/3º Ano'   => '2º/3º Ano',

        '4º Ano' => '4º Ano',

        'Pré-primário' => 'Pré-primário',

        '1'  => '1ª',  '2'  => '2ª',  '3'  => '3ª',

        '4'  => '4ª',  '5'  => '5ª',  '6'  => '6ª',

        '7'  => '7ª',  '8'  => '8ª',  '9'  => '9ª',

        '10' => '10ª',

        '11A'=> '11ª A', '11B'=> '11ª B', '11C'=> '11ª C',

        '12A'=> '12ª A', '12B'=> '12ª B', '12C'=> '12ª C',

    ];

    return $map[$classe] ?? ucfirst($classe);

}

function sige_fin_seed_servicos_padrao($tabela, $escola_id = null) {

    global $wpdb;

    $escola_id = ($escola_id !== null)
        ? (int)$escola_id
        : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);

    if ($escola_id <= 0) {
        // Sem escola valida nao se semeia (evita criar servicos na escola 1 por engano).
        return;
    }

    $padrao = [

        ['nome' => 'Uniforme', 'tipo' => 'uniforme', 'valor' => 0, 'categoria' => 'nao_fixo', 'aplica_multa' => 0, 'aplica_desconto' => 0, 'classe' => 'todas'],

        ['nome' => 'Camiseta', 'tipo' => 'camiseta', 'valor' => 0, 'categoria' => 'nao_fixo', 'aplica_multa' => 0, 'aplica_desconto' => 0, 'classe' => 'todas'],

        ['nome' => 'Quinzena da Criança (01/06)', 'tipo' => 'quinzena_crianca', 'valor' => 0, 'categoria' => 'nao_fixo', 'aplica_multa' => 0, 'aplica_desconto' => 0, 'classe' => 'todas'],

        ['nome' => 'Quinzena da Criança (16/06)', 'tipo' => 'quinzena_crianca', 'valor' => 0, 'categoria' => 'nao_fixo', 'aplica_multa' => 0, 'aplica_desconto' => 0, 'classe' => 'todas'],

        ['nome' => 'Encerramento', 'tipo' => 'encerramento', 'valor' => 0, 'categoria' => 'nao_fixo', 'aplica_multa' => 0, 'aplica_desconto' => 0, 'classe' => 'todas'],

    ];

    foreach ($padrao as $p) {

        $existe = $wpdb->get_var($wpdb->prepare(

            "SELECT id FROM $tabela WHERE LOWER(nome)=LOWER(%s) AND LOWER(tipo)=LOWER(%s) AND escola_id=%d LIMIT 1",

            $p['nome'], $p['tipo'], $escola_id

        ));

        if ($existe) continue;

        $wpdb->insert($tabela, [

            'nome' => $p['nome'],

            'tipo' => $p['tipo'],

            'valor' => (float)$p['valor'],

            'dia_vencimento' => 10,

            'aplica_multa' => (int)$p['aplica_multa'],

            'aplica_desconto' => (int)$p['aplica_desconto'],

            'aplica_desc_pronto' => 0,

            'aplica_desc_func' => 0,

            'ativo' => 1,

            'escola_id' => $escola_id,

            'classe' => $p['classe'],

            'categoria' => $p['categoria'],

            'categoria_sne' => 'geral',

            'tipo_multa' => 'percentual',

            'valor_multa' => 0,

            'multa_atraso' => 0,

            'ciclo' => 'todos'

        ]);

    }

}

// ==============================================================================

// 3) AÇÕES: SEED

// ==============================================================================

$msg_feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_seed_nonce']) && wp_verify_nonce($_POST['sige_seed_nonce'], 'sige_seed_servicos')) {

    sige_fin_seed_servicos_padrao($tabela, $escola_id);

    $msg_feedback = '<div class="notice notice-success is-dismissible"><p>Serviços padrão criados (se ainda não existiam).</p></div>';

}

// ==============================================================================

// 4) AÇÕES: ADD / UPDATE

// ==============================================================================

$edit_id = sige_fin_get_int('edit');

$editing = null;

if ($edit_id > 0) {

    $editing = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tabela WHERE id=%d AND escola_id=%d LIMIT 1", $edit_id, $escola_id));

}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_save_servico_nonce']) && wp_verify_nonce($_POST['sige_save_servico_nonce'], 'save_servico')) {

    $id = sige_fin_post_int('id');

    $tipo = sige_fin_norm_tipo(sige_fin_post_param('tipo'));

    $categoria = sige_fin_post_param('categoria');

    $dados = [

        'nome' => sige_fin_post_param('nome'),

        'tipo' => $tipo,

        'classe' => sige_fin_post_param('classe', 'todas'),

        'valor' => sige_fin_post_float('valor'),

        'dia_vencimento' => sige_fin_post_int('dia_vencimento', 10),

        'aplica_multa' => isset($_POST['aplica_multa']) ? 1 : 0,

        'aplica_desconto' => isset($_POST['aplica_desconto']) ? 1 : 0,

        'aplica_desc_pronto' => isset($_POST['aplica_desc_pronto']) ? 1 : 0,

        'aplica_desc_func' => isset($_POST['aplica_desc_func']) ? 1 : 0,

        'ativo' => isset($_POST['ativo']) ? 1 : 0,

        'categoria' => ($categoria !== '' ? $categoria : null),

        'categoria_sne' => sige_fin_post_param('categoria_sne', 'geral'),

        'ciclo' => sige_fin_post_param('ciclo', 'todos'),

        'tipo_multa' => sige_fin_post_param('tipo_multa', 'percentual'),

        'valor_multa' => sige_fin_post_float('valor_multa'),

        'multa_atraso' => sige_fin_post_float('multa_atraso'),

        'escola_id' => $escola_id,

        // [v13.4.2] Centro de custo do serviço - fonte da verdade para herança
        // em lançamentos e pagamentos. 0 = "não classificado" (bloqueia gerador).
        'centro_id' => sige_fin_post_int('centro_id', 0),

    ];

    // [v13.4.2] Validação defensiva: se o centro_id enviado não existe nesta
    // escola (ou está inactivo), cai para 0 (exige classificação explícita).
    if ((int)$dados['centro_id'] > 0 && function_exists('sige_fin_get_centros')) {
        $__centros_validos = array_map(fn($c) => (int)$c->id, sige_fin_get_centros(false));
        if (!in_array((int)$dados['centro_id'], $__centros_validos, true)) {
            $dados['centro_id'] = 0;
        }
    }

    if (trim($dados['nome']) === '' || trim($dados['tipo']) === '') {

        $msg_feedback = '<div class="notice notice-error"><p>Nome e Tipo são obrigatórios.</p></div>';

    } else {

        if ($id > 0) {

            $ok = $wpdb->update($tabela, $dados, ['id' => $id]);

            $msg_feedback = ($ok !== false)

                ? '<div class="notice notice-success is-dismissible"><p>Serviço actualizado com sucesso!</p></div>'

                : '<div class="notice notice-error"><p>Erro ao actualizar: ' . 'Contacte o administrador.</p></div>';

        } else {

            $ok = $wpdb->insert($tabela, $dados);

            $msg_feedback = ($ok !== false)

                ? '<div class="notice notice-success is-dismissible"><p>Serviço criado com sucesso!</p></div>'

                : '<div class="notice notice-error"><p>Erro ao criar: ' . 'Contacte o administrador.</p></div>';

        }

    }

    echo '<script ' . sige_csp_script_attr() . '>window.location.href="?page=sige-app&view=financeiro-config";</script>';

    exit;

}

// ==============================================================================
// 4b) REGRAS DO TRANSPORTE - valor vem da rota; regras vêm do serviço transporte
// ==============================================================================
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['sige_save_transporte_regras_nonce']) &&
    wp_verify_nonce($_POST['sige_save_transporte_regras_nonce'], 'sige_save_transporte_regras')
) {
    $transport_srv_cfg = function_exists('sige_fin_get_ou_criar_servico_transporte')
        ? sige_fin_get_ou_criar_servico_transporte($escola_id)
        : $transport_srv_cfg;

    if ($transport_srv_cfg && !empty($transport_srv_cfg->id)) {
        $dados_transporte = [
            'nome'              => 'Transporte Escolar',
            'tipo'              => 'transporte',
            'classe'            => 'todas',
            'categoria'         => 'fixo',
            'aplica_multa'      => isset($_POST['transporte_aplica_multa']) ? 1 : 0,
            'aplica_desconto'   => isset($_POST['transporte_aplica_desconto']) ? 1 : 0,
            'aplica_desc_pronto'=> isset($_POST['transporte_aplica_desc_pronto']) ? 1 : 0,
            'aplica_desc_func'  => isset($_POST['transporte_aplica_desc_func']) ? 1 : 0,
            'tipo_multa'        => in_array(sige_fin_post_param('transporte_tipo_multa', 'percentual'), ['percentual','fixa'], true) ? sige_fin_post_param('transporte_tipo_multa', 'percentual') : 'percentual',
            'valor_multa'       => max(0.0, sige_fin_post_float('transporte_valor_multa')),
            'ativo'             => 1,
        ];

        // O preço do transporte é sempre lido da rota; manter o campo valor do serviço em 0.
        if (function_exists('sige_db_column_exists') ? sige_db_column_exists($tabela, 'valor') : true) {
            $dados_transporte['valor'] = 0.00;
        }
        if (function_exists('sige_db_column_exists') ? sige_db_column_exists($tabela, 'dia_vencimento') : true) {
            $dados_transporte['dia_vencimento'] = max(1, min(31, sige_fin_post_int('transporte_dia_vencimento', 10)));
        }
        if (isset($_POST['transporte_centro_id']) && (function_exists('sige_db_column_exists') ? sige_db_column_exists($tabela, 'centro_id') : true)) {
            $centro_transporte = sige_fin_post_int('transporte_centro_id', 0);
            if ($centro_transporte > 0 && function_exists('sige_fin_get_centros')) {
                $__centros_validos_trans = array_map(fn($c) => (int)$c->id, sige_fin_get_centros(false));
                if (!in_array($centro_transporte, $__centros_validos_trans, true)) $centro_transporte = 0;
            }
            $dados_transporte['centro_id'] = $centro_transporte;
        }

        foreach (array_keys($dados_transporte) as $_col_transporte) {
            if (function_exists('sige_db_column_exists') && !sige_db_column_exists($tabela, $_col_transporte)) {
                unset($dados_transporte[$_col_transporte]);
            }
        }

        $wpdb->update($tabela, $dados_transporte, ['id' => (int)$transport_srv_cfg->id, 'escola_id' => $escola_id]);
    }

    echo '<script ' . sige_csp_script_attr() . '>window.location.href="?page=sige-app&view=financeiro-config";</script>';
    exit;
}

// ==============================================================================

// 5) AÇÕES: DELETE - com protecção de integridade financeira

// ==============================================================================

if (isset($_GET['del']) && isset($_GET['_wpnonce']) && wp_verify_nonce($_GET['_wpnonce'], 'del_servico')) {

    $del_id = sige_fin_get_int('del');
    $tL_del = $wpdb->prefix . 'sige_fin_lancamentos';

    // [FIX SERV-DEL] Verificar lançamentos que referenciam este serviço
    $lanc_stats = $wpdb->get_row($wpdb->prepare(
        "SELECT 
            COUNT(*) AS total,
            SUM(CASE WHEN status IN ('pendente','parcial','em_plano') THEN 1 ELSE 0 END) AS activos,
            SUM(CASE WHEN status = 'pago' THEN 1 ELSE 0 END) AS pagos,
            COALESCE(SUM(CASE WHEN status IN ('pendente','parcial') THEN 
                GREATEST(COALESCE(valor_original,0) - COALESCE(valor_pago,0), 0) ELSE 0 END), 0) AS divida
         FROM $tL_del 
         WHERE servico_id = %d AND escola_id = %d AND status != 'cancelado'",
        $del_id, sige_get_escola_id()
    ));

    $tem_lancs = (int)($lanc_stats->total ?? 0);
    $tem_activos = (int)($lanc_stats->activos ?? 0);
    $tem_pagos = (int)($lanc_stats->pagos ?? 0);
    $divida_aberta = (float)($lanc_stats->divida ?? 0);

    // Confirmar eliminação cascata se há lançamentos
    $confirmar = sige_fin_get_param('confirmar') === '1';

    if ($tem_lancs > 0 && !$confirmar) {
        // Mostrar alerta com detalhes antes de permitir eliminação
        $confirm_url = wp_nonce_url(
            "?page=sige-app&view=financeiro-config&del={$del_id}&confirmar=1",
            'del_servico'
        );
        $servico_del = $wpdb->get_row($wpdb->prepare("SELECT nome FROM $tabela WHERE id=%d", $del_id));
        $nome_srv = $servico_del ? esc_html($servico_del->nome) : "#{$del_id}";

        echo '<div class="sige-alert sige-alert-error" style="margin:20px;padding:20px;">';
        echo '<h3 style="margin:0 0 12px;">Atenção: Serviço com lançamentos existentes</h3>';
        echo '<p>O serviço <strong>' . $nome_srv . '</strong> tem:</p>';
        echo '<ul style="margin:8px 0 8px 20px;">';
        if ($tem_activos > 0) echo '<li><strong>' . $tem_activos . '</strong> lançamentos pendentes/parciais';
        if ($divida_aberta > 0) echo ' (dívida: <strong>' . number_format($divida_aberta, 2, ',', '.') . ' ' . sige_moeda() . '</strong>)';
        if ($tem_activos > 0) echo '</li>';
        if ($tem_pagos > 0) echo '<li><strong>' . $tem_pagos . '</strong> lançamentos já pagos (histórico)</li>';
        echo '</ul>';
        echo '<p style="margin-top:12px;">';
        echo '<strong>Ao confirmar:</strong> os lançamentos pendentes serão <em>cancelados</em> automaticamente. ';
        if ($tem_pagos > 0) {
            echo 'Como existem pagamentos históricos, o serviço será <em>desactivado</em> (não eliminado) para manter a integridade dos relatórios.';
        } else {
            echo 'O serviço será <em>eliminado</em> definitivamente.';
        }
        echo '</p>';
        echo '<div style="margin-top:16px;display:flex;gap:8px;">';
        $btn_label = $tem_pagos > 0 ? 'Sim, desactivar' : 'Sim, eliminar';
        $btn_confirm = $tem_pagos > 0
            ? 'CONFIRMAR: Cancelar ' . $tem_activos . ' lançamentos pendentes e desactivar o serviço?'
            : 'CONFIRMAR: Cancelar ' . $tem_activos . ' lançamentos pendentes e eliminar o serviço?';
        echo '<a class="fc-btn sg-fincfg-danger-link" href="' . esc_url($confirm_url) . '" data-sg-fincfg-confirm="' . esc_attr($btn_confirm) . '" data-sg-fincfg-confirm-detail="Esta operação protege a integridade financeira e deve ser confirmada pela tesouraria.">' . esc_html($btn_label) . '</a>';
        echo '<a class="fc-btn fc-btn-ghost" href="?page=sige-app&view=financeiro-config">Voltar</a>';
        echo '</div></div>';

    } else {
        // Executar eliminação cascata
        if ($tem_lancs > 0) {
            // 1. Cancelar lançamentos pendentes/parciais/em_plano
            $n_cancelados = $wpdb->query($wpdb->prepare(
                "UPDATE $tL_del 
                 SET status = 'cancelado',
                     cancelado_em = %s,
                     cancelado_por = %d,
                     motivo_cancelamento = %s
                 WHERE servico_id = %d 
                   AND escola_id = %d 
                   AND status IN ('pendente','parcial','em_plano')",
                current_time('mysql'),
                get_current_user_id(),
                'Serviço eliminado pela gestão',
                $del_id,
                sige_get_escola_id()
            ));

            // 2. [FIX SERV-DEL-02] Soft delete se há pagamentos históricos
            //    Previne servico_id órfão - relatórios mantêm nome do serviço.
            if ($tem_pagos > 0) {
                $wpdb->update($tabela, [
                    'ativo' => 0,
                    'nome'  => $wpdb->get_var($wpdb->prepare(
                        "SELECT nome FROM $tabela WHERE id=%d", $del_id
                    )) . ' (descontinuado)',
                ], ['id' => $del_id]);
            } else {
                $wpdb->delete($tabela, ['id' => $del_id]);
            }

            // 3. Log de auditoria
            if (function_exists('sige_audit_log')) {
                sige_audit_log($tem_pagos > 0 ? 'servico_desactivado' : 'servico_eliminado_cascata', [
                    'servico_id'            => $del_id,
                    'modo'                  => $tem_pagos > 0 ? 'soft_delete' : 'hard_delete',
                    'lancs_cancelados'      => (int)$n_cancelados,
                    'lancs_pagos_historico' => $tem_pagos,
                    'divida_eliminada'      => $divida_aberta,
                ], 'financeiro');
            }
        } else {
            // Sem lançamentos - pode apagar definitivamente
            $wpdb->delete($tabela, ['id' => $del_id]);
        }

        echo '<script ' . sige_csp_script_attr() . '>window.location.href="?page=sige-app&view=financeiro-config";</script>';
        exit;
    }

}

// ==============================================================================

// 6) LISTA

// ==============================================================================

// [v13.7.0 BLOCO 3 RESIDUAL] Filtro universal por Centro de Custo.
// Aplica-se APENAS à listagem - o CRUD (editar/criar) continua a receber
// o centro_id do formulário, não do filtro. Também esconde automaticamente
// quando só há 1 centro activo (hide_if_single no render).
$centro_id_filtro = function_exists('sige_fin_centro_ativo') ? sige_fin_centro_ativo() : 0;
$_centro_sql_cfg  = function_exists('sige_fin_centro_where_clause')
    ? sige_fin_centro_where_clause($centro_id_filtro)
    : '';

$transport_srv_cfg = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM $tabela WHERE escola_id=%d AND LOWER(tipo)='transporte' AND ativo=1 ORDER BY id ASC LIMIT 1",
    $escola_id
));

$servicos = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM $tabela WHERE escola_id=%d {$_centro_sql_cfg} ORDER BY ativo DESC, tipo ASC, classe ASC, nome ASC",
    $escola_id
));

// v12.10.24 - Métricas visuais do catálogo (apenas leitura; não altera regras financeiras)
$fc_servicos_total = is_array($servicos) ? count($servicos) : 0;
$fc_servicos_ativos = 0;
$fc_servicos_sem_centro = 0;
$fc_mensalidades_ativas = 0;
$fc_valor_mensalidades = 0.0;
foreach ((array)$servicos as $__fc_srv) {
    $fc_is_active = ((int)($__fc_srv->ativo ?? 0) === 1);
    if ($fc_is_active) {
        $fc_servicos_ativos++;
        if ((int)($__fc_srv->centro_id ?? 0) <= 0) $fc_servicos_sem_centro++;
        if (strtolower((string)($__fc_srv->tipo ?? '')) === 'mensalidade') {
            $fc_mensalidades_ativas++;
            $fc_valor_mensalidades += (float)($__fc_srv->valor ?? 0);
        }
    }
}
$fc_multa_tiers_ativas = 0;
for ($__fc_n = 1; $__fc_n <= 3; $__fc_n++) {
    if ((float)sige_fin_cfg_prop($cfg, "multa_tier{$__fc_n}_pct", 0) > 0) $fc_multa_tiers_ativas++;
}
$fc_moeda = function_exists('sige_moeda') ? sige_moeda() : (sige_fin_cfg_prop($cfg, 'moeda_simbolo', 'MT') ?: 'MT');
$fc_sem_centro_count = function_exists('sige_fin_count_servicos_sem_centro')
    ? (int)sige_fin_count_servicos_sem_centro()
    : (int)$fc_servicos_sem_centro;

$tipos_recomendados = [

    'mensalidade' => 'Mensalidade (Propina)',

    'transporte' => 'Transporte',

    'estudos' => 'Estudos',

    'ingles' => 'Inglês',

    'desporto' => 'Desporto',

    'almoco' => 'Almoço',

    'pequeno_almoco' => 'Pequeno Almoço',

    'matricula_novo' => 'Matrícula (Novo)',

    'renovacao' => 'Renovação de Matrícula',

    // [v13] Inscrições (Casa Colorida e similares)
    'inscricao_nova' => 'Inscrição (Aluno Novo)',

    'renovacao_inscricao' => 'Renovação de Inscrição',

    // [v13] Actividades extras opcionais (vinculadas ao aluno via tabela N:M)
    'atividade_extra' => 'Actividade Extra (Opcional)',

    'livros' => 'Livros (Material Didáctico)',

    'uniforme' => 'Uniforme (Avulso)',

    'camiseta' => 'Camiseta (Avulso)',

    'quinzena_crianca' => 'Quinzena da Criança (Avulso)',

    'encerramento' => 'Encerramento (Avulso)',

    'exame' => 'Taxa de Exame (Avulso)',

    'material' => 'Material (Avulso)',

];

$categorias_recomendadas = [

    '' => '-- (não definir) --',

    'fixo_mensal' => 'Fixo mensal',

    'nao_fixo' => 'Não fixo (Avulso)',

    'matricula' => 'Matrícula/Renovação',

    'exame' => 'Exame',

    'material' => 'Material/Uniforme',

];

?>

<style>
.fc-page{font-family:'Plus Jakarta Sans','Inter',system-ui,sans-serif;color:var(--color-ink-900);background:var(--color-slate-50);padding:var(--space-6);box-sizing:border-box}
.fc-hero{position:relative;overflow:hidden;border-radius:var(--radius-xl);padding:var(--space-8);margin-bottom:28px;background:linear-gradient(135deg,var(--color-black) 0%,var(--color-ink-900) 50%,var(--color-brand-500) 100%);color:var(--color-white);box-shadow:0 4px 16px rgba(15,23,42,.08)}
.fc-hero h1{font-size:clamp(1.5rem,3vw,2rem);font-weight:700;margin:0 0 6px;line-height:1.2;color:var(--color-white)!important}
.fc-hero p{margin:0;opacity:0.85;font-size:0.9rem;color:var(--color-white)!important}
.fc-card{background:var(--color-white);border-radius:var(--radius-lg);border:1px solid var(--color-ink-50);box-shadow:var(--shadow-xs);margin-bottom:24px;overflow:hidden}
.fc-card-accent{border-top:4px solid}
.fc-card-header{padding:var(--space-5) var(--space-6);border-bottom:1px solid var(--color-ink-50);display:flex;align-items:center;gap:10px}
.fc-card-header h3{font-size:1rem;font-weight:600;margin:0}
.fc-card-body{padding:24px}
.fc-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:24px}
.fc-grid-12{display:grid;grid-template-columns:1fr 2fr;gap:24px}
.fc-label{display:block;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-slate-500);margin-bottom:6px}
.fc-input,.fc-select{height:42px;padding:0 var(--space-3);border:1px solid var(--color-ink-100);border-radius:var(--radius-sm);font-size:0.875rem;color:var(--color-ink-900);background:var(--color-white);width:100%;box-sizing:border-box;transition:border 0.2s}
.fc-input:focus,.fc-select:focus{outline:none;border-color:var(--sg-theme-primary,var(--color-brand-500));box-shadow:0 1px 2px rgba(15,23,42,.04)}
.fc-input-sm{height:38px;width:100px;text-align:center;font-weight:600;font-size:1rem}
.fc-btn{display:inline-flex;align-items:center;justify-content:center;gap:var(--space-2);padding:0 var(--space-5);height:44px;border-radius:var(--radius-md);font-weight:600;font-size:0.875rem;cursor:pointer;border:none;transition:all var(--duration-normal);text-decoration:none}
.fc-btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary,var(--color-brand-500)),var(--color-brand-500));color:var(--color-white);box-shadow:0 1px 2px rgba(15,23,42,.04)}
.fc-btn-primary:hover{transform:translateY(-2px);box-shadow:var(--shadow-sm);color:var(--color-white)}
.fc-btn-ghost{background:var(--color-slate-50);color:var(--color-slate-700);border:1px solid var(--color-ink-100)}
.fc-btn-ghost:hover{background:var(--color-ink-50);border-color:var(--color-ink-200)}
.fc-btn-sm{height:36px;padding:0 14px;font-size:0.8rem;border-radius:8px}
.fc-tier{background:var(--color-slate-50);border:1px solid var(--color-ink-50);border-radius:var(--radius-md);padding:var(--space-4);margin-bottom:12px}
.fc-tier-label{font-weight:600;font-size:0.8rem;margin-bottom:10px}
.fc-hint{font-size:0.7rem;color:var(--color-slate-400);margin-top:6px}
.fc-divider{border:none;border-top:2px solid var(--color-ink-100);margin:32px 0}
.fc-badge{font-size:0.65rem;padding:3px 8px;border-radius:var(--radius-xs);font-weight:600;display:inline-block}
.fc-table{width:100%;border-collapse:separate;border-spacing:0;font-size:0.85rem}
.fc-table-wrap{overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;}
.fc-table th{background:var(--color-slate-50);padding:var(--space-3) var(--space-4);text-align:left;font-size:0.7rem;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:var(--color-slate-500);border-bottom:2px solid var(--color-ink-50)}
.fc-table td{padding:var(--space-3) var(--space-4);border-bottom:1px solid var(--color-slate-50);vertical-align:middle}
.fc-table tr:hover td{background:var(--color-slate-50)}
.fc-check-row{display:flex;align-items:center;gap:10px;padding:var(--space-2) 0;cursor:pointer;font-size:0.875rem}
.fc-check-row input[type=checkbox]{width:18px;height:18px;accent-color:var(--sg-theme-primary,var(--color-brand-500))}
.fc-alert{padding:14px 20px;border-radius:var(--radius-md);font-size:0.875rem;margin-bottom:16px;display:flex;align-items:center;gap:10px}
.fc-alert-success{background:var(--color-success-100);border:1px solid var(--color-success-300);color:var(--color-success-900)}
.fc-alert-error{background:var(--color-danger-100);border:1px solid var(--color-danger-200);color:var(--color-danger-700)}
.fc-rules-box{background:var(--color-success-50);border:1px solid var(--color-success-200);border-radius:var(--radius-md);padding:18px;margin-bottom:16px}
.fc-rules-box.blue{background:var(--sg-theme-soft,var(--color-brand-50));border-color:var(--sg-theme-soft,var(--color-brand-50))}
.fc-rules-box.amber{background:var(--color-warning-50);border-color:var(--color-warning-300)}
@media(max-width:900px){.fc-grid-2,.fc-grid-12{grid-template-columns:1fr}}


/* v12.10.62 - Catálogo de Serviços responsivo com formulário em modal
   Intervenção visual/UX apenas. Não altera CRUD, fórmulas, centros, valores ou regras financeiras. */
.fc-grid-12.sg-fincfg-services-grid{
    display:block!important;
    grid-template-columns:1fr!important;
    gap:0!important;
}
.sg-fincfg-actionbar{
    display:flex!important;
    align-items:center!important;
    gap:10px!important;
    flex-wrap:wrap!important;
}
.sg-fincfg-actionbar .fc-btn svg,
.sg-fincfg-services-tools .fc-btn svg,
.sg-fincfg-service-modal-head h3 svg{
    width:18px!important;
    height:18px!important;
    stroke:currentColor!important;
    fill:none!important;
    opacity:1!important;
}
.sg-fincfg-service-modal{
    position:fixed!important;
    inset:0!important;
    z-index:100000!important;
    display:none!important;
    align-items:flex-start!important;
    justify-content:center!important;
    padding:28px 18px!important;
    background:rgba(15,23,42,.58)!important;
    backdrop-filter:blur(10px)!important;
    overflow:auto!important;
}
.sg-fincfg-service-modal.is-open{
    display:flex!important;
}
.sg-fincfg-service-dialog{
    width:min(980px,96vw)!important;
    margin:auto 0!important;
}
.sg-fincfg-service-form-card{
    width:100%!important;
    max-height:calc(100vh - 56px)!important;
    overflow:auto!important;
    border-radius:var(--radius-xl)!important;
    box-shadow:var(--shadow-lg);
    border:1px solid rgba(255,255,255,.55)!important;
}
.sg-fincfg-service-modal-head{
    position:sticky!important;
    top:0!important;
    z-index:2!important;
    background:var(--color-white)!important;
    justify-content:space-between!important;
    gap:var(--space-4)!important;
}
.sg-fincfg-service-modal-head h3{
    display:flex!important;
    align-items:center!important;
    gap:10px!important;
    margin:0!important;
}
.sg-fincfg-modal-x{
    width:38px!important;
    height:38px!important;
    border:1px solid var(--color-ink-100)!important;
    border-radius:var(--radius-md)!important;
    background:var(--color-slate-50)!important;
    color:var(--color-slate-800)!important;
    font-size:var(--fs-xl)!important;
    line-height:1!important;
    cursor:pointer!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
}
body.sg-fincfg-service-modal-open{
    overflow:hidden!important;
}
.sg-fincfg-services-list{
    width:100%!important;
    margin-top:0!important;
}
.sg-fincfg-services-head{
    display:flex!important;
    align-items:center!important;
    justify-content:space-between!important;
    gap:14px!important;
    flex-wrap:wrap!important;
    padding:var(--space-5) var(--space-6)!important;
}
.sg-fincfg-services-title{
    display:flex!important;
    align-items:center!important;
    gap:var(--space-3)!important;
    min-width:260px!important;
}
.sg-fincfg-services-title-icon{
    width:42px!important;
    height:42px!important;
    border-radius:var(--radius-md)!important;
    background:var(--color-brand-50)!important;
    color:var(--color-brand-500)!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    flex:0 0 auto!important;
}
.sg-fincfg-services-title-icon svg{
    width:20px!important;
    height:20px!important;
}
.sg-fincfg-services-title h3{
    margin:0!important;
    color:var(--color-ink-500)!important;
    font-size:17px!important;
    font-weight:700!important;
    letter-spacing:-.03em!important;
}
.sg-fincfg-services-title p{
    margin:5px 0 0!important;
    color:var(--color-ink-400)!important;
    font-size:12px!important;
    font-weight:600!important;
}
.sg-fincfg-services-tools{
    display:flex!important;
    align-items:center!important;
    justify-content:flex-end!important;
    gap:10px!important;
    flex-wrap:wrap!important;
    margin-left:auto!important;
}
.sg-fincfg-services-tools form{
    margin:0!important;
    display:flex!important;
    align-items:center!important;
    gap:var(--space-2)!important;
    flex-wrap:wrap!important;
}
.sg-fincfg-services-body{
    overflow-x:auto!important;
    -webkit-overflow-scrolling:touch!important;
}
.sg-fincfg-services-body .fc-table{
    min-width:1120px!important;
}
.sg-fincfg-services-body .fc-table th,
.sg-fincfg-services-body .fc-table td{
    white-space:nowrap!important;
}
.sg-fincfg-services-body .fc-table th:nth-child(3),
.sg-fincfg-services-body .fc-table td:nth-child(3){
    white-space:normal!important;
    min-width:190px!important;
}
.sg-fincfg-services-body .fc-table th:last-child,
.sg-fincfg-services-body .fc-table td:last-child{
    position:sticky!important;
    right:0!important;
    z-index:3!important;
    background:var(--color-white)!important;
    min-width:172px!important;
    box-shadow:var(--shadow-sm);
}
.sg-fincfg-services-body .fc-table th:last-child{
    z-index:4!important;
    background:var(--color-slate-50)!important;
}
.sg-fincfg-services-body .fc-table tr:hover td:last-child{
    background:var(--color-slate-50)!important;
}
.sg-fincfg-warning-icon{
    width:34px!important;
    height:34px!important;
    display:inline-flex!important;
    align-items:center!important;
    justify-content:center!important;
    border-radius:var(--radius-md)!important;
    background:var(--color-warning-50)!important;
    color:var(--color-warning-700)!important;
    flex:0 0 auto!important;
}
.sg-fincfg-warning-icon svg{
    width:18px!important;
    height:18px!important;
}
@media(max-width:980px){
    .sg-fincfg-services-head{align-items:stretch!important;}
    .sg-fincfg-services-title{width:100%!important;}
    .sg-fincfg-services-tools{width:100%!important;justify-content:flex-start!important;}
    .sg-fincfg-services-tools .fc-btn,
    .sg-fincfg-services-tools form,
    .sg-fincfg-services-tools select{width:100%!important;}
    .sg-fincfg-service-dialog{width:min(720px,96vw)!important;}
}
@media(max-width:640px){
    .sg-fincfg-service-modal{padding:var(--space-3)!important;}
    .sg-fincfg-service-form-card{max-height:calc(100vh - 24px)!important;border-radius:var(--radius-xl)!important;}
    .sg-fincfg-service-form-card .fc-card-body{padding:18px!important;}
    .sg-fincfg-service-form-card [style*="display:flex"]{flex-wrap:wrap!important;}
    .sg-fincfg-service-form-card [style*="flex:1"]{min-width:100%!important;}
    .sg-fincfg-services-body .fc-table{min-width:1040px!important;}
}

</style>

<div class="fc-page sg-fincfg-wrap">

<?php if (!empty($cfg_feedback)) echo $cfg_feedback; ?>
<?php if (!empty($msg_feedback)) echo $msg_feedback; ?>

<!-- ═══════════ SECÇÃO A - CONFIGURAÇÕES FINANCEIRAS ═══════════ -->
<div class="fc-hero sg-fincfg-hero">
    <div class="sg-fincfg-hero-copy">
        <span class="sg-fincfg-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('settings') : ''; ?> Financeiro</span>
        <h1>Preços e Serviços</h1>
        <p>Configure multas, descontos, períodos, transporte e serviços cobrados pela escola numa área limpa e segura.</p>
        <div class="sg-fincfg-hero-actions">
            <a class="fc-btn fc-btn-primary" href="#fc-catalogo">Gerir serviços</a>
            <a class="fc-btn fc-btn-ghost" href="#fc-periodos">Períodos do ano</a>
        </div>
    </div>
    <div class="sg-fincfg-hero-panel">
        <span>Estado do catálogo</span>
        <strong><?php echo (int)$fc_servicos_ativos; ?> activos</strong>
        <small><?php echo (int)$fc_servicos_total; ?> serviços registados · <?php echo (int)$fc_sem_centro_count; ?> por classificar</small>
    </div>
</div>

<div class="sg-finpro-kpi-grid sg-fincfg-kpis">
    <div class="sg-finpro-kpi sg-finpro-tone-purple">
        <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('settings') : ''; ?></div>
        <div><span>Serviços activos</span><strong><?php echo (int)$fc_servicos_ativos; ?></strong><small><?php echo (int)$fc_servicos_total; ?> registados no catálogo</small></div>
    </div>
    <div class="sg-finpro-kpi sg-finpro-tone-green">
        <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?></div>
        <div><span>Mensalidades activas</span><strong><?php echo (int)$fc_mensalidades_ativas; ?></strong><small><?php echo number_format((float)$fc_valor_mensalidades, 2, ',', '.'); ?> <?php echo esc_html($fc_moeda); ?> no catálogo</small></div>
    </div>
    <div class="sg-finpro-kpi sg-finpro-tone-amber">
        <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('activity') : ''; ?></div>
        <div><span>Multas configuradas</span><strong><?php echo (int)$fc_multa_tiers_ativas; ?>/3</strong><small>Níveis automáticos activos</small></div>
    </div>
    <div class="sg-finpro-kpi sg-finpro-tone-red">
        <div class="sg-finpro-kpi-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('building') : ''; ?></div>
        <div><span>Por classificar</span><strong><?php echo (int)$fc_sem_centro_count; ?></strong><small>Serviços sem centro de custo</small></div>
    </div>
</div>

<form method="post" class="sg-fincfg-form">
<?php wp_nonce_field('sige_save_cfg_financeiro', 'sige_save_cfg_nonce'); ?>

<div class="fc-grid-2">

    <!-- MULTA ESCALADA -->
    <div class="fc-card fc-card-accent" style="border-top-color:var(--color-danger-500);">
        <div class="fc-card-header">
            <span style="font-size:1.2rem;">&#128680;</span>
            <h3 style="color:var(--color-danger-500);">Multa por Atraso (Escalada)</h3>
        </div>
        <div class="fc-card-body">
            <p style="font-size:0.75rem;color:var(--color-slate-400);margin:0 0 16px;">O sistema aplica automaticamente o nível mais alto que o atraso justificar. Deixe 0% para desactivar.</p>

            <?php
            $tiers = [
                1 => ['label' => 'Nível 1 - Atraso inicial',   'color' => 'var(--color-warning-500)'],
                2 => ['label' => 'Nível 2 - Atraso moderado',  'color' => 'var(--color-danger-500)'],
                3 => ['label' => 'Nível 3 - Atraso grave',     'color' => 'var(--color-danger-700)'],
            ];
            foreach ($tiers as $n => $t):
                $dias_val = (int)($cfg->{"multa_tier{$n}_dias"} ?? [10,30,60][$n-1]);
                $pct_val  = (float)($cfg->{"multa_tier{$n}_pct"}  ?? 0);
            ?>
            <div class="fc-tier">
                <div class="fc-tier-label" style="color:<?php echo $t['color']; ?>;"><?php echo $t['label']; ?></div>
                <div style="display:flex;gap:12px;align-items:flex-end;">
                    <div class="sige-u-flex-1">
                        <label class="fc-label">Após (dias)</label>
                        <input type="number" name="multa_tier<?php echo $n; ?>_dias" min="1" max="365" value="<?php echo esc_attr($dias_val); ?>" class="fc-input fc-input-sm">
                    </div>
                    <div class="sige-u-flex-1">
                        <label class="fc-label">% do valor</label>
                        <div style="display:flex;align-items:center;gap:4px;">
                            <input type="number" name="multa_tier<?php echo $n; ?>_pct" min="0" max="100" step="0.5" value="<?php echo number_format($pct_val, 1, '.', ''); ?>" class="fc-input fc-input-sm">
                            <span style="font-weight:600;color:<?php echo $t['color']; ?>;">%</span>
                        </div>
                    </div>
                </div>
                <div class="fc-hint">
                    <?php if ($pct_val > 0): ?>
                        Ex: dívida 1.800 <?php echo esc_html(sige_moeda()); ?> após <?php echo $dias_val; ?>d &rarr; multa <strong><?php echo number_format(1800 * $pct_val / 100, 2); ?> <?php echo esc_html(sige_moeda()); ?></strong>
                    <?php else: ?>
                        Nível desactivado (0%).
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <div class="fc-hint" style="margin-top:4px;">&#9888;&#65039; Um serviço pode ter multa própria (catálogo) que substitui estes valores globais.</div>
        </div>
    </div>

    <!-- DESCONTOS -->
    <div class="fc-card fc-card-accent" style="border-top-color:var(--color-success-600);">
        <div class="fc-card-header">
            <span style="font-size:1.2rem;">&#127873;</span>
            <h3 style="color:var(--color-success-500);">Descontos Automáticos</h3>
        </div>
        <div class="fc-card-body">
            <p style="font-size:0.75rem;color:var(--color-slate-400);margin:0 0 16px;">Aplicados se o tick correspondente estiver activo na ficha do aluno e o serviço tiver a regra activada.</p>

            <?php
            $desc_irmao_tipo_raw = sige_fin_cfg_prop($cfg, 'desconto_irmaos_tipo', 'mt');
            $desc_func_tipo_raw  = sige_fin_cfg_prop($cfg, 'desconto_funcionario_tipo', 'mt');
            $desc_irmao_tipo = in_array($desc_irmao_tipo_raw, ['mt','percentual'], true) ? $desc_irmao_tipo_raw : 'mt';
            $desc_func_tipo  = in_array($desc_func_tipo_raw, ['mt','percentual'], true) ? $desc_func_tipo_raw : 'mt';
            $desc_irmao_mt   = (float)sige_fin_cfg_prop($cfg, 'desconto_irmaos_mt', sige_fin_cfg_prop($cfg, 'desconto_irmaos_pct', 0));
            $desc_irmao_pct  = (float)sige_fin_cfg_prop($cfg, 'desconto_irmaos_pct', 0);
            $desc_func_mt    = (float)sige_fin_cfg_prop($cfg, 'desconto_funcionario_mt', sige_fin_cfg_prop($cfg, 'desconto_funcionario_percentual', 0));
            $desc_func_pct   = (float)sige_fin_cfg_prop($cfg, 'desconto_funcionario_percentual', 0);
            ?>

            <!-- Irmãos -->
            <div class="fc-rules-box">
                <div style="font-weight:600;font-size:0.8rem;color:var(--color-success-900);margin-bottom:10px;">&#128104;&#8205;&#128105;&#8205;&#128103; Desconto - 2.º Filho e seguintes</div>
                <p style="font-size:0.75rem;color:var(--color-slate-500);margin:0 0 10px;">Tick <em>"Desconto Irmão"</em> na ficha do aluno + <em>"Desc. Irmãos"</em> no catálogo. Pode ser valor fixo em MT ou percentagem sobre o valor do serviço.</p>
                <div style="display:grid;grid-template-columns:140px 1fr 1fr;gap:10px;align-items:end;">
                    <div>
                        <label class="fc-label">Tipo</label>
                        <select name="desconto_irmaos_tipo" class="fc-select" style="border-color:var(--color-success-300);">
                            <option value="mt" <?php selected($desc_irmao_tipo, 'mt'); ?>>Valor fixo</option>
                            <option value="percentual" <?php selected($desc_irmao_tipo, 'percentual'); ?>>Percentagem</option>
                        </select>
                    </div>
                    <div>
                        <label class="fc-label">Valor fixo (<?php echo esc_html(sige_moeda()); ?>)</label>
                        <input type="number" name="desconto_irmaos_mt" min="0" step="0.01" value="<?php echo number_format($desc_irmao_mt, 2, '.', ''); ?>" class="fc-input" style="border-color:var(--color-success-300);">
                    </div>
                    <div>
                        <label class="fc-label">Percentagem (%)</label>
                        <input type="number" name="desconto_irmaos_pct" min="0" max="100" step="0.01" value="<?php echo number_format($desc_irmao_pct, 2, '.', ''); ?>" class="fc-input" style="border-color:var(--color-success-300);">
                    </div>
                </div>
                <div class="fc-hint" style="color:var(--color-success-900);">O sistema usa apenas o tipo seleccionado; o outro valor fica guardado para uso futuro.</div>
            </div>

            <!-- Funcionário -->
            <div class="fc-rules-box blue">
                <div style="font-weight:600;font-size:0.8rem;color:var(--sg-theme-primary,var(--color-brand-500));margin-bottom:10px;">&#127979; Desconto - Filho de Funcionário</div>
                <p style="font-size:0.75rem;color:var(--color-slate-500);margin:0 0 10px;">Tick <em>"Filho de Funcionário"</em> na ficha do aluno + <em>"Desc. Funcionário"</em> no catálogo. Pode ser valor fixo em MT ou percentagem sobre o valor do serviço.</p>
                <div style="display:grid;grid-template-columns:140px 1fr 1fr;gap:10px;align-items:end;">
                    <div>
                        <label class="fc-label">Tipo</label>
                        <select name="desconto_funcionario_tipo" class="fc-select" style="border-color:var(--color-info-200);">
                            <option value="mt" <?php selected($desc_func_tipo, 'mt'); ?>>Valor fixo</option>
                            <option value="percentual" <?php selected($desc_func_tipo, 'percentual'); ?>>Percentagem</option>
                        </select>
                    </div>
                    <div>
                        <label class="fc-label">Valor fixo (<?php echo esc_html(sige_moeda()); ?>)</label>
                        <input type="number" name="desconto_funcionario_mt" min="0" step="0.01" value="<?php echo number_format($desc_func_mt, 2, '.', ''); ?>" class="fc-input" style="border-color:var(--color-info-200);">
                    </div>
                    <div>
                        <label class="fc-label">Percentagem (%)</label>
                        <input type="number" name="desconto_funcionario_percentual" min="0" max="100" step="0.01" value="<?php echo number_format($desc_func_pct, 2, '.', ''); ?>" class="fc-input" style="border-color:var(--color-info-200);">
                    </div>
                </div>
                <div class="fc-hint" style="color:var(--sg-theme-primary,var(--color-brand-500));">O sistema usa apenas o tipo seleccionado; o outro valor fica guardado para uso futuro.</div>
            </div>

            <!-- Adiantamento -->
            <div class="fc-rules-box blue">
                <div style="font-weight:600;font-size:0.8rem;color:var(--sg-theme-primary,var(--color-brand-500));margin-bottom:10px;">&#9203; Desconto por Pagamento Adiantado</div>
                <p style="font-size:0.75rem;color:var(--color-slate-500);margin:0 0 12px;">Quando o encarregado paga <strong>6+</strong> ou <strong>10+ meses</strong> de uma vez.</p>
                <div style="display:flex;flex-wrap:wrap;gap:20px;">
                    <div>
                        <label class="fc-label" style="color:var(--sg-theme-primary,var(--color-brand-500));">6 meses</label>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input type="number" name="desc_adiant_6m_pct" min="0" max="50" step="0.01" value="<?php echo number_format((float)($cfg->desc_adiant_6m_pct ?? 2), 2, '.', ''); ?>" class="fc-input fc-input-sm" style="border-color:var(--color-info-200);">
                            <span style="font-weight:600;color:var(--sg-theme-primary,var(--color-brand-500));">%</span>
                        </div>
                    </div>
                    <div>
                        <label class="fc-label" style="color:var(--sg-theme-primary,var(--color-brand-500));">10+ meses</label>
                        <div style="display:flex;align-items:center;gap:6px;">
                            <input type="number" name="desc_adiant_12m_pct" min="0" max="50" step="0.01" value="<?php echo number_format((float)($cfg->desc_adiant_12m_pct ?? 5), 2, '.', ''); ?>" class="fc-input fc-input-sm" style="border-color:var(--color-info-200);">
                            <span style="font-weight:600;color:var(--sg-theme-primary,var(--color-brand-500));">%</span>
                        </div>
                    </div>
                </div>
                <?php $pct6=(float)($cfg->desc_adiant_6m_pct??2);$pct12=(float)($cfg->desc_adiant_12m_pct??5);$ex=3000; ?>
                <div class="fc-hint" style="color:var(--sg-theme-primary,var(--color-brand-500));margin-top:10px;">
                    Ex: mens. <?php echo number_format($ex,0,',','.'); ?> <?php echo esc_html(sige_moeda()); ?> &mdash;
                    6m = -<?php echo number_format($pct6,1); ?>% (<strong><?php echo number_format($ex*6*$pct6/100,0,',','.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>)
                    | 12m = -<?php echo number_format($pct12,1); ?>% (<strong><?php echo number_format($ex*12*$pct12/100,0,',','.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>)
                </div>
            </div>

            <!-- Nota -->
            <div class="fc-rules-box amber" style="font-size:0.75rem;color:var(--color-warning-800);">
                <strong>&#8505;&#65039; Como activar por serviço:</strong> No catálogo abaixo, edita as <em>"Regras Aplicáveis"</em> e marca <strong>Desc. Irmãos</strong> ou <strong>Desc. Funcionário</strong>. Serviços sem estas regras não recebem desconto.
            </div>
        </div>
    </div>

</div><!-- /grid-2 -->

<!-- CRECHE -->
<div class="fc-card fc-card-accent" style="border-top-color:var(--color-brand-400);margin-bottom:24px;">
    <div class="fc-card-header">
        <span style="font-size:1.2rem;">&#127979;</span>
        <h3 style="color:var(--color-brand-500);">Preços da Creche por Regime</h3>
    </div>
    <div class="fc-card-body">
        <p style="font-size:0.75rem;color:var(--color-slate-400);margin:0 0 16px;">Aplicados automaticamente ao registar/editar aluno com regime de creche.</p>
        <div class="fc-table-wrap"><table class="fc-table">
            <thead><tr><th></th><th>2 a 4 anos (<?php echo esc_html(sige_moeda()); ?>)</th><th>5 anos (<?php echo esc_html(sige_moeda()); ?>)</th></tr></thead>
            <tbody>
            <tr>
                <td><strong>Semi-integral</strong></td>
                <td><input type="number" name="creche_semi_ate4" step="0.01" min="0" class="fc-input fc-input-sm" value="<?php echo number_format((float)($cfg->creche_semi_ate4 ?? 3000), 2, '.', ''); ?>"></td>
                <td><input type="number" name="creche_semi_5anos" step="0.01" min="0" class="fc-input fc-input-sm" value="<?php echo number_format((float)($cfg->creche_semi_5anos ?? 3200), 2, '.', ''); ?>"></td>
            </tr>
            <tr>
                <td><strong>Integral</strong></td>
                <td><input type="number" name="creche_integral_ate4" step="0.01" min="0" class="fc-input fc-input-sm" value="<?php echo number_format((float)($cfg->creche_integral_ate4 ?? 4000), 2, '.', ''); ?>"></td>
                <td><input type="number" name="creche_integral_5anos" step="0.01" min="0" class="fc-input fc-input-sm" value="<?php echo number_format((float)($cfg->creche_integral_5anos ?? 4200), 2, '.', ''); ?>"></td>
            </tr>
            </tbody>
        </table></div>
    </div>
</div>

<!-- ▸ LOCALIZAÇÃO -->
<div class="fc-card fc-card-accent" style="border-top-color:var(--color-slate-500);">
    <div class="fc-card-header">
        <h3>&#127760; Localização</h3>
    </div>
    <div class="fc-card-body">
        <p style="font-size:0.8rem;color:var(--color-slate-500);margin:0 0 16px;">
            Configurações regionais - moeda e formato de telefone.
            Estas definições são usadas em toda a aplicação.
        </p>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:16px;">
            <div>
                <label class="fc-label">Símbolo da Moeda</label>
                <input type="text" name="moeda_simbolo" class="fc-input"
                       value="<?php echo esc_attr($cfg->moeda_simbolo ?? 'MT'); ?>"
                       placeholder="MT" style="max-width:100px;">
                <div class="fc-hint">Ex: MT, USD, EUR, R$</div>
            </div>
            <div>
                <label class="fc-label">Prefixo Telefone (país)</label>
                <input type="text" name="prefixo_telefone" class="fc-input"
                       value="<?php echo esc_attr($cfg->prefixo_telefone ?? '258'); ?>"
                       placeholder="258" style="max-width:100px;">
                <div class="fc-hint">Ex: 258 (Moçambique), 55 (Brasil)</div>
            </div>
            <div>
                <label class="fc-label">Dígitos do Telefone (sem prefixo)</label>
                <input type="number" name="formato_telefone_digitos" class="fc-input"
                       value="<?php echo (int)($cfg->formato_telefone_digitos ?? 9); ?>"
                       min="7" max="15" style="max-width:100px;">
                <div class="fc-hint">Nº local sem código país (MZ = 9)</div>
            </div>
            <div>
                <label class="fc-label">Fuso Horário</label>
                <select name="timezone" class="fc-input" style="max-width:220px;">
                    <?php
                    $tz_sel = $cfg->timezone ?? 'Africa/Maputo';
                    $tzones = [
                        'Africa/Maputo'       => 'Moçambique (CAT, UTC+2)',
                        'Africa/Johannesburg' => 'África do Sul (SAST, UTC+2)',
                        'Africa/Luanda'       => 'Angola (WAT, UTC+1)',
                        'Africa/Sao_Tome'     => 'São Tomé (GMT, UTC+0)',
                        'Atlantic/Cape_Verde' => 'Cabo Verde (CVT, UTC-1)',
                        'Asia/Dili'           => 'Timor-Leste (TLT, UTC+9)',
                        'America/Sao_Paulo'   => 'Brasil (BRT, UTC-3)',
                        'Europe/Lisbon'       => 'Portugal (WET, UTC+0)',
                    ];
                    foreach ($tzones as $tz_val => $tz_label):
                    ?>
                        <option value="<?php echo esc_attr($tz_val); ?>" <?php selected($tz_sel, $tz_val); ?>><?php echo esc_html($tz_label); ?></option>
                    <?php endforeach; ?>
                </select>
                <div class="fc-hint">Usado para datas e vencimentos</div>
            </div>
        </div>
    </div>
</div>

<div style="text-align:right;margin-bottom:32px;">
    <button type="submit" class="fc-btn fc-btn-primary" style="padding:0 32px;">Guardar Configurações</button>
</div>

</form>

<!-- ═══════════ SECÇÃO B09 - GESTÃO DE PERÍODOS ═══════════ -->
<?php
// B09 POST handler
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_periodos_nonce'])
    && wp_verify_nonce($_POST['sige_periodos_nonce'], 'sige_save_periodos')) {
    $ano_per = sige_fin_post_int('periodos_ano', $ano_letivo);
    $meses_fechados = isset($_POST['mes_fechado']) && is_array($_POST['mes_fechado']) ? $_POST['mes_fechado'] : [];
    $periodos = [];
    for ($m = 1; $m <= 12; $m++) {
        $mr = $ano_per . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
        $periodos[$mr] = in_array((string)$m, $meses_fechados, true) ? 'fechado' : 'aberto';
    }
    sige_fin_periodos_save($periodos, $ano_per);
    sige_fin_log('periodos_actualizados', ['ano' => $ano_per, 'periodos' => $periodos]);
    echo '<div class="notice notice-success is-dismissible"><p>Períodos do ano ' . esc_html($ano_per) . ' actualizados.</p></div>';
}

$periodos_ano = $ano_letivo;
$periodos_actual = function_exists('sige_fin_periodos_get') ? sige_fin_periodos_get($periodos_ano) : [];
$nomes_meses = ['','Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
?>
<div id="fc-periodos" class="fc-card sg-fincfg-periodos" style="margin-bottom:32px;">
    <div class="fc-card-body">
        <h3 style="color:var(--color-info-900);">Gestão de Períodos - Ano <?php echo esc_html($periodos_ano); ?></h3>
        <p style="font-size:0.85rem;color:var(--color-slate-500);margin-bottom:16px;">
            Feche períodos para bloquear a geração de lançamentos e pagamentos em meses específicos.
            Meses fechados impedem cobrança a nível da escola inteira.
        </p>
        <form method="post">
            <?php wp_nonce_field('sige_save_periodos', 'sige_periodos_nonce'); ?>
            <input type="hidden" name="periodos_ano" value="<?php echo esc_attr($periodos_ano); ?>">
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(140px,1fr));gap:10px;margin-bottom:16px;">
            <?php for ($m = 1; $m <= 12; $m++):
                $mr = $periodos_ano . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
                $estado = $periodos_actual[$mr] ?? 'aberto';
                $is_fechado = ($estado === 'fechado');
                $bg = $is_fechado ? 'var(--color-danger-50)' : 'var(--color-success-50)';
                $border = $is_fechado ? 'var(--color-danger-200)' : 'var(--color-success-200)';
                $color = $is_fechado ? 'var(--color-danger-700)' : 'var(--color-success-900)';
            ?>
                <label style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-radius:10px;
                    background:<?php echo $bg; ?>;border:1px solid <?php echo $border; ?>;cursor:pointer;
                    font-size:0.85rem;font-weight:600;color:<?php echo $color; ?>;">
                    <input type="checkbox" name="mes_fechado[]" value="<?php echo $m; ?>"
                        <?php checked($is_fechado); ?>
                        style="accent-color:var(--color-danger-600);width:16px;height:16px;"
                        onchange="this.closest('label').style.background=this.checked?'var(--color-danger-50)':'var(--color-success-50)';
                                  this.closest('label').style.borderColor=this.checked?'var(--color-danger-200)':'var(--color-success-200)';
                                  this.closest('label').style.color=this.checked?'var(--color-danger-700)':'var(--color-success-900)';">
                    <?php echo esc_html($nomes_meses[$m]); ?>
                    <span style="margin-left:auto;font-size:0.7rem;opacity:0.7;">
                        <?php echo $is_fechado ? 'FECHADO' : 'aberto'; ?>
                    </span>
                </label>
            <?php endfor; ?>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;">
                <span style="font-size:0.75rem;color:var(--color-slate-400);">Marque os meses que deseja FECHAR. Desmarcados ficam abertos.</span>
                <button type="submit" class="fc-btn fc-btn-primary" style="padding:6px 24px;">Guardar Períodos</button>
            </div>
        </form>
    </div>
</div>

<hr class="fc-divider">

<!-- ═══════════ SECÇÃO B - CATÁLOGO DE SERVIÇOS ═══════════ -->
<div id="fc-catalogo" class="fc-hero sg-fincfg-hero sg-fincfg-hero-compact">
    <div class="sg-fincfg-hero-copy">
        <span class="sg-fincfg-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?> Catálogo</span>
        <h1>Catálogo de Serviços e Preços</h1>
        <p>Organize mensalidades, transporte e serviços avulsos com centro de custo, regras de multa e descontos.</p>
    </div>
    <div class="sg-fincfg-rule-card">
        <strong>Regras activas</strong>
        <span>Multa:
            <?php
            $tl = [];
            for ($n = 1; $n <= 3; $n++) {
                $d = (int)($cfg->{"multa_tier{$n}_dias"} ?? 0);
                $p = (float)($cfg->{"multa_tier{$n}_pct"} ?? 0);
                if ($p > 0) $tl[] = "após {$d}d → {$p}%";
            }
            echo $tl ? esc_html(implode(' | ', $tl)) : '-';
            ?>
        </span>
        <span>Irmão: <?php echo ($desc_irmao_tipo === 'percentual') ? number_format($desc_irmao_pct, 2) . '%' : number_format($desc_irmao_mt, 2) . ' ' . esc_html($fc_moeda); ?> · Funcionário: <?php echo ($desc_func_tipo === 'percentual') ? number_format($desc_func_pct, 2) . '%' : number_format($desc_func_mt, 2) . ' ' . esc_html($fc_moeda); ?></span>
    </div>
</div>

<div class="sg-fincfg-actionbar">
    <button type="button" class="fc-btn fc-btn-primary fc-btn-sm" id="sgFincfgOpenServicoModal"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('plus') : ''; ?> Adicionar Serviço</button>
    <form method="post" style="display:inline;">
        <?php wp_nonce_field('sige_seed_servicos', 'sige_seed_nonce'); ?>
        <button class="fc-btn fc-btn-ghost fc-btn-sm">Criar serviços padrão</button>
    </form>
    <span>O formulário abre em janela própria para dar largura total à lista de serviços.</span>
</div>

<?php if ($transport_srv_cfg): ?>
<div class="fc-card" style="margin-bottom:20px;border-left:4px solid var(--color-warning-600);">
    <div class="fc-card-header" style="justify-content:space-between;gap:12px;align-items:flex-start;">
        <div>
            <h3 style="margin:0;color:var(--color-warning-800);">🚌 Regras do Transporte Escolar</h3>
            <p style="margin:6px 0 0;color:var(--color-slate-500);font-size:0.85rem;">
                O valor mensal continua a vir das rotas de transporte de cada aluno. Aqui a escola decide se esse valor recebe multa e descontos, tal como acontece com os restantes serviços.
            </p>
        </div>
        <a class="fc-btn fc-btn-ghost fc-btn-sm" href="?page=sige-app&view=financeiro-config&edit=<?php echo (int)$transport_srv_cfg->id; ?>">Editar serviço completo</a>
    </div>
    <div class="fc-card-body">
        <form method="post" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px;align-items:end;">
            <?php wp_nonce_field('sige_save_transporte_regras', 'sige_save_transporte_regras_nonce'); ?>

            <?php
            $__tm_trans = (string)($transport_srv_cfg->tipo_multa ?? 'percentual');
            $__cid_trans = (int)($transport_srv_cfg->centro_id ?? 0);
            ?>

            <label class="fc-check-row" style="margin:0;">
                <input type="checkbox" name="transporte_aplica_multa" <?php checked(1, (int)($transport_srv_cfg->aplica_multa ?? 0)); ?>>
                Cobrar multa no transporte
            </label>
            <label class="fc-check-row" style="margin:0;">
                <input type="checkbox" name="transporte_aplica_desconto" <?php checked(1, (int)($transport_srv_cfg->aplica_desconto ?? 0)); ?>>
                Aplicar desconto de irmãos
            </label>
            <label class="fc-check-row" style="margin:0;">
                <input type="checkbox" name="transporte_aplica_desc_func" <?php checked(1, (int)($transport_srv_cfg->aplica_desc_func ?? 0)); ?>>
                Aplicar desconto de funcionário
            </label>
            <label class="fc-check-row" style="margin:0;">
                <input type="checkbox" name="transporte_aplica_desc_pronto" <?php checked(1, (int)($transport_srv_cfg->aplica_desc_pronto ?? 0)); ?>>
                Aplicar desconto de pronto pagamento
            </label>

            <div>
                <label class="fc-label">Multa específica do transporte</label>
                <div style="display:flex;gap:8px;">
                    <select name="transporte_tipo_multa" class="fc-select" style="flex:1;">
                        <option value="percentual" <?php echo sige_fin_opt('percentual', $__tm_trans); ?>>% sobre transporte</option>
                        <option value="fixa" <?php echo sige_fin_opt('fixa', $__tm_trans); ?>>Fixa</option>
                    </select>
                    <input type="number" step="0.01" min="0" name="transporte_valor_multa" value="<?php echo esc_attr($transport_srv_cfg->valor_multa ?? 0); ?>" class="fc-input" style="width:100px;">
                </div>
                <div class="fc-hint">0 = usa a multa global configurada acima.</div>
            </div>

            <div>
                <label class="fc-label">Vencimento</label>
                <input type="number" min="1" max="31" name="transporte_dia_vencimento" value="<?php echo esc_attr($transport_srv_cfg->dia_vencimento ?? 10); ?>" class="fc-input">
            </div>

            <?php if (function_exists('sige_fin_get_centros') && !empty(sige_fin_get_centros(false))): ?>
            <div>
                <label class="fc-label">Centro de custo</label>
                <select name="transporte_centro_id" class="fc-select">
                    <option value="0" <?php echo sige_fin_opt(0, $__cid_trans); ?>>- Por definir -</option>
                    <?php foreach (sige_fin_get_centros(false) as $__ct): ?>
                        <option value="<?php echo (int)$__ct->id; ?>" <?php echo sige_fin_opt((int)$__ct->id, $__cid_trans); ?>><?php echo esc_html($__ct->nome); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <button type="submit" class="fc-btn fc-btn-primary">Guardar regras do transporte</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="fc-grid-12 sg-fincfg-services-grid">

    <!-- FORM ADD/EDIT - agora em modal para libertar largura da lista -->
    <div id="sgFincfgServicoModal" class="sg-fincfg-service-modal<?php echo $editing ? ' is-open' : ''; ?>" aria-hidden="<?php echo $editing ? 'false' : 'true'; ?>">
      <div class="sg-fincfg-service-dialog" role="dialog" aria-modal="true" aria-labelledby="sgFincfgServicoTitle">
        <div class="fc-card fc-card-accent sg-fincfg-service-form-card" style="border-top-color:var(--sg-theme-primary,var(--color-brand-500));height:fit-content;">
        <div class="fc-card-header sg-fincfg-service-modal-head">
            <h3 id="sgFincfgServicoTitle"><?php echo $editing ? (function_exists('sige_ui_icon') ? sige_ui_icon('edit') : '') . ' Editar Serviço' : (function_exists('sige_ui_icon') ? sige_ui_icon('plus') : '') . ' Adicionar Serviço'; ?></h3>
            <button type="button" class="sg-fincfg-modal-x" data-sg-fincfg-servico-close aria-label="Fechar">×</button>
        </div>
        <div class="fc-card-body">
            <form method="post">
                <?php wp_nonce_field('save_servico', 'sige_save_servico_nonce'); ?>
                <input type="hidden" name="id" value="<?php echo (int)($editing->id ?? 0); ?>">

                <div style="margin-bottom:14px;">
                    <label class="fc-label">Nome/Descrição</label>
                    <input type="text" name="nome" required value="<?php echo esc_attr($editing->nome ?? ''); ?>" placeholder="Ex: Mensalidade 2ª Classe" class="fc-input">
                </div>

                <div style="display:flex;gap:14px;margin-bottom:14px;">
                    <div class="sige-u-flex-1">
                        <label class="fc-label">Tipo (regra)</label>
                        <select name="tipo" class="fc-select">
                            <?php
                            $tipo_sel = $editing ? (string)$editing->tipo : 'mensalidade';
                            foreach ($tipos_recomendados as $k => $lab) {
                                echo '<option value="'.esc_attr($k).'" '.sige_fin_opt($k, $tipo_sel).'>'.esc_html($lab).'</option>';
                            }
                            ?>
                        </select>
                        <div class="fc-hint">Para "Outros serviços": tipo=uniforme, categoria=nao_fixo.</div>
                    </div>
                    <div class="sige-u-flex-1">
                        <label class="fc-label">Categoria</label>
                        <select name="categoria" class="fc-select">
                            <?php
                            $cat_sel = $editing ? (string)($editing->categoria ?? '') : '';
                            foreach ($categorias_recomendadas as $k => $lab) {
                                echo '<option value="'.esc_attr($k).'" '.sige_fin_opt($k, $cat_sel).'>'.esc_html($lab).'</option>';
                            }
                            ?>
                        </select>
                    </div>
                </div>

                <div style="display:flex;gap:14px;margin-bottom:14px;">
                    <div class="sige-u-flex-1">
                        <label class="fc-label">Classe</label>
                        <?php $classe_sel = $editing ? (string)($editing->classe ?? 'todas') : 'todas'; ?>
                        <select name="classe" class="fc-select">
                            <option value="todas" <?php echo sige_fin_opt('todas', $classe_sel); ?>>-- Todas --</option>
                            <optgroup label="Pré-Escolar">
                                <option value="2º/3º Ano" <?php echo sige_fin_opt('2º/3º Ano', $classe_sel); ?>>2º/3º Ano</option>
                                <option value="4º Ano" <?php echo sige_fin_opt('4º Ano', $classe_sel); ?>>4º Ano</option>
                                <option value="Pré-primário" <?php echo sige_fin_opt('Pré-primário', $classe_sel); ?>>Pré-primário</option>
                            </optgroup>
                            <optgroup label="Primário">
                                <?php for ($c=1;$c<=6;$c++): ?><option value="<?php echo $c; ?>" <?php echo sige_fin_opt((string)$c, $classe_sel); ?>><?php echo $c; ?>ª Classe</option><?php endfor; ?>
                            </optgroup>
                            <optgroup label="ESG1">
                                <?php for ($c=7;$c<=9;$c++): ?><option value="<?php echo $c; ?>" <?php echo sige_fin_opt((string)$c, $classe_sel); ?>><?php echo $c; ?>ª Classe</option><?php endfor; ?>
                            </optgroup>
                            <optgroup label="ESG2">
                                <option value="10" <?php echo sige_fin_opt('10', $classe_sel); ?>>10ª Classe</option>
                                <?php foreach(['11A','11B','11C','12A','12B','12C'] as $cl): ?><option value="<?php echo $cl; ?>" <?php echo sige_fin_opt($cl, $classe_sel); ?>><?php echo $cl; ?></option><?php endforeach; ?>
                            </optgroup>
                        </select>
                    </div>
                    <div class="sige-u-flex-1">
                        <label class="fc-label">Ciclo</label>
                        <?php $ciclo_sel = $editing ? (string)($editing->ciclo ?? 'todos') : 'todos'; ?>
                        <select name="ciclo" class="fc-select">
                            <option value="todos" <?php echo sige_fin_opt('todos', $ciclo_sel); ?>>Todos</option>
                            <option value="diurno" <?php echo sige_fin_opt('diurno', $ciclo_sel); ?>>Diurno</option>
                            <option value="pos_laboral" <?php echo sige_fin_opt('pos_laboral', $ciclo_sel); ?>>Pós-laboral</option>
                            <option value="tempo_inteiro" <?php echo sige_fin_opt('tempo_inteiro', $ciclo_sel); ?>>Tempo Inteiro (v13)</option>
                            <option value="meio_dia" <?php echo sige_fin_opt('meio_dia', $ciclo_sel); ?>>Meio Dia (v13)</option>
                        </select>
                    </div>
                </div>

                <?php
                  // [v13.4.2] Dropdown Centro de Custo - aparece se houver >=1 centro activo
                  $__centros_lista = function_exists('sige_fin_get_centros') ? sige_fin_get_centros(false) : [];
                  $__centro_sel = $editing ? (int)($editing->centro_id ?? 0) : 0;
                  if (!empty($__centros_lista)):
                ?>
                <div style="display:flex;gap:14px;margin-bottom:14px;padding:14px;background:var(--color-warning-50);border:1px solid var(--color-warning-300);border-radius:10px;">
                    <div class="sige-u-flex-1">
                        <label class="fc-label" style="color:var(--color-warning-800);">
                            Centro de Custo <span style="color:var(--color-danger-600);">*</span>
                            <?php if ($__centro_sel === 0): ?>
                            <span style="background:var(--color-danger-600);color:var(--color-white);padding:2px 8px;border-radius:10px;font-size:10px;font-weight:600;margin-left:6px;">⚠ POR DEFINIR</span>
                            <?php endif; ?>
                        </label>
                        <select name="centro_id" required class="fc-select" style="border-color:var(--color-warning-400);">
                            <option value="0" <?php echo sige_fin_opt(0, $__centro_sel); ?>>- Seleccionar centro -</option>
                            <?php foreach ($__centros_lista as $__c): ?>
                            <option value="<?php echo (int)$__c->id; ?>" <?php echo sige_fin_opt((int)$__c->id, $__centro_sel); ?>>
                                <?php echo esc_html($__c->nome); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="fc-hint" style="color:var(--color-warning-800);">
                            Cada serviço pertence a um centro. Lançamentos criados a partir deste serviço
                            herdam automaticamente o centro, permitindo separar receitas no dashboard.
                            Ex: Mensalidade → Casa Colorida; Judo → Centro de Actividades.
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <div style="display:flex;gap:14px;margin-bottom:14px;background:var(--color-slate-50);padding:14px;border-radius:10px;">
                    <div class="sige-u-flex-1">
                        <label class="fc-label" style="color:var(--color-success-500);">Valor (MT)</label>
                        <input type="number" step="0.01" name="valor" required value="<?php echo esc_attr($editing->valor ?? 0); ?>" class="fc-input" style="font-weight:600;border-color:var(--color-success-300);">
                    </div>
                    <div style="width:100px;">
                        <label class="fc-label">Vence Dia</label>
                        <input type="number" name="dia_vencimento" value="<?php echo esc_attr($editing->dia_vencimento ?? 10); ?>" class="fc-input fc-input-sm">
                    </div>
                </div>

                <div style="display:flex;gap:14px;margin-bottom:14px;">
                    <div class="sige-u-flex-1">
                        <label class="fc-label">Categoria SNE</label>
                        <?php $sne_sel = $editing ? (string)($editing->categoria_sne ?? 'geral') : 'geral'; ?>
                        <select name="categoria_sne" class="fc-select">
                            <option value="geral" <?php echo sige_fin_opt('geral', $sne_sel); ?>>Geral</option>
                            <option value="interno" <?php echo sige_fin_opt('interno', $sne_sel); ?>>Interno</option>
                            <option value="externo" <?php echo sige_fin_opt('externo', $sne_sel); ?>>Externo</option>
                        </select>
                    </div>
                    <div class="sige-u-flex-1">
                        <label class="fc-label">Multa específica</label>
                        <?php $tm_sel = $editing ? (string)($editing->tipo_multa ?? 'percentual') : 'percentual'; ?>
                        <div style="display:flex;gap:8px;">
                            <select name="tipo_multa" class="fc-select" style="flex:1;">
                                <option value="percentual" <?php echo sige_fin_opt('percentual', $tm_sel); ?>>%</option>
                                <option value="fixa" <?php echo sige_fin_opt('fixa', $tm_sel); ?>>Fixa (<?php echo esc_html(sige_moeda()); ?>)</option>
                            </select>
                            <input type="number" step="0.01" name="valor_multa" value="<?php echo esc_attr($editing->valor_multa ?? 0); ?>" class="fc-input" style="width:90px;" placeholder="0">
                        </div>
                        <div class="fc-hint">0 = usa multa global.</div>
                    </div>
                </div>

                <div style="margin-bottom:16px;border:1px solid var(--color-ink-100);padding:16px;border-radius:12px;background:var(--color-white);">
                    <label class="fc-label" style="margin-bottom:12px;">&#9881;&#65039; Regras Aplicáveis</label>
                    <?php
                    $am = $editing ? (int)$editing->aplica_multa : 0;
                    $ad = $editing ? (int)$editing->aplica_desconto : 0;
                    $ap = $editing ? (int)$editing->aplica_desc_pronto : 0;
                    $af = $editing ? (int)$editing->aplica_desc_func : 0;
                    $at = $editing ? (int)$editing->ativo : 1;
                    ?>
                    <label class="fc-check-row"><input type="checkbox" name="aplica_multa" <?php checked(1, $am); ?>> Cobrar Multa</label>
                    <label class="fc-check-row"><input type="checkbox" name="aplica_desconto" <?php checked(1, $ad); ?>> Desc. Irmãos</label>
                    <label class="fc-check-row"><input type="checkbox" name="aplica_desc_pronto" <?php checked(1, $ap); ?>> Desc. Pronto Pag.</label>
                    <label class="fc-check-row"><input type="checkbox" name="aplica_desc_func" <?php checked(1, $af); ?>> Desc. Funcionário</label>
                    <label class="fc-check-row" style="border-top:1px solid var(--color-ink-50);padding-top:12px;margin-top:4px;"><input type="checkbox" name="ativo" <?php checked(1, $at); ?>> <strong>Activo</strong></label>
                </div>

                <div style="display:flex;gap:10px;">
                    <button type="submit" class="fc-btn fc-btn-primary" style="flex:1;">&#128190; <?php echo $editing ? 'Actualizar' : 'Gravar'; ?></button>
                    <?php if ($editing): ?>
                    <a class="fc-btn fc-btn-ghost" style="flex:1;text-align:center;" href="?page=sige-app&view=financeiro-config">Cancelar</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        </div>
      </div>
    </div>

    <!-- LISTA DE SERVIÇOS -->
    <div class="fc-card sg-fincfg-services-list">
        <div class="fc-card-header sg-fincfg-services-head" style="justify-content:space-between;">
            <div class="sg-fincfg-services-title">
                <span class="sg-fincfg-services-title-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('clipboard') : ''; ?></span>
                <div>
                    <h3>Lista de Serviços<?php
                        if ($centro_id_filtro > 0 && function_exists('sige_fin_get_centro_nome')) {
                            echo ' <span style="font-weight:400;color:var(--color-slate-500);font-size:0.85rem;">· filtrado por '
                                . esc_html(sige_fin_get_centro_nome($centro_id_filtro))
                                . '</span>';
                        }
                    ?></h3>
                    <p>Consulte valores, centros de custo e acções sem perder largura de trabalho.</p>
                </div>
            </div>
            <div class="sg-fincfg-services-tools">
                <button type="button" class="fc-btn fc-btn-primary fc-btn-sm" data-sg-fincfg-servico-open><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('plus') : ''; ?> Adicionar Serviço</button>
            <?php
            // [v13.7.0 BLOCO 3 RESIDUAL] Filtro Centro no header da listagem.
            // Submete via GET; a query $servicos aplica o WHERE. Só renderiza
            // se a escola tem mais de 1 centro activo (hide_if_single).
            if (function_exists('sige_fin_render_filtro_centro')) {
                $__cfg_centro_select = sige_fin_render_filtro_centro([
                    'selected'      => $centro_id_filtro,
                    'include_label' => false,
                    'auto_submit'   => true,
                ]);
                if ($__cfg_centro_select !== '') {
                    echo '<form method="get" style="margin:0;display:flex;align-items:center;gap:8px;">';
                    echo '<input type="hidden" name="page" value="sige-app">';
                    echo '<input type="hidden" name="view" value="financeiro-config">';
                    echo '<label style="font-size:0.8rem;font-weight:600;color:var(--color-slate-700);margin:0;">Centro:</label>';
                    echo $__cfg_centro_select;
                    echo '<noscript><button type="submit" class="fc-btn fc-btn-sm">Aplicar</button></noscript>';
                    echo '</form>';
                }
            }
            ?>
            </div>
        </div>
        <div class="fc-card-body sg-fincfg-services-body" style="padding:0;">
            <?php
              // [v13.4.2] Banner de auditoria: quantos serviços sem centro atribuído
              $__sem_centro_count = function_exists('sige_fin_count_servicos_sem_centro')
                  ? sige_fin_count_servicos_sem_centro() : 0;
              if ($__sem_centro_count > 0):
            ?>
            <div style="margin:0;padding:12px 24px;background:var(--color-danger-50);border-bottom:2px solid var(--color-danger-200);color:var(--color-danger-700);font-size:13px;display:flex;align-items:center;gap:10px;">
                <span class="sg-fincfg-warning-icon"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('alert') : ''; ?></span>
                <div>
                    <strong><?php echo (int)$__sem_centro_count; ?> serviço(s) activo(s) sem centro atribuído.</strong>
                    Lançamentos futuros deste(s) serviço(s) estarão bloqueados até atribuires um centro.
                    Edita cada um na tabela abaixo e selecciona o centro correcto.
                </div>
            </div>
            <?php endif; ?>
            <div class="fc-table-wrap"><table class="fc-table">
                <thead>
                    <tr>
                        <th style="width:8%;">Estado</th>
                        <th style="width:10%;">Classe</th>
                        <th>Serviço</th>
                        <th style="width:10%;">Tipo</th>
                        <th style="width:10%;">Categoria</th>
                        <th style="width:11%;">Centro</th>
                        <th style="width:10%;">Valor</th>
                        <th style="width:7%;">Vence</th>
                        <th style="width:80px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($servicos)): ?>
                        <tr><td colspan="9" style="text-align:center;padding:40px;color:var(--color-slate-400);">Nenhum serviço definido.</td></tr>
                    <?php else: ?>
                        <?php
                        // [v13.4.2] Pre-indexar centros para rendering da coluna
                        $__centros_idx = [];
                        if (function_exists('sige_fin_get_centros')) {
                            foreach (sige_fin_get_centros(true) as $__c) {
                                $__centros_idx[(int)$__c->id] = $__c;
                            }
                        }
                        foreach ($servicos as $s):
                            $classe_label = sige_fin_classe_label((string)($s->classe ?? 'todas'));
                            $is_active = ((int)$s->ativo === 1);
                            $del_url = wp_nonce_url("?page=sige-app&view=financeiro-config&del=".(int)$s->id, 'del_servico');
                            $__cid = (int)($s->centro_id ?? 0);
                            $__centro_obj = $__cid > 0 && isset($__centros_idx[$__cid]) ? $__centros_idx[$__cid] : null;
                        ?>
                        <tr style="<?php echo $is_active ? '' : 'opacity:0.5;'; ?><?php echo ($__cid === 0 && $is_active) ? 'background:var(--color-danger-50);' : ''; ?>">
                            <td class="sige-u-tac"><?php echo $is_active ? '&#9989;' : '&#9940;'; ?></td>
                            <td>
                                <?php if ($s->classe === 'todas'): ?>
                                    <span class="fc-badge" style="background:var(--color-ink-50);color:var(--color-slate-500);">Todas</span>
                                <?php else: ?>
                                    <span class="fc-badge" style="background:var(--color-success-100);color:var(--color-success-900);"><?php echo esc_html($classe_label); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($s->nome); ?></strong></td>
                            <td><code style="font-size:0.7rem;background:var(--color-ink-50);padding:2px 6px;border-radius:4px;"><?php echo esc_html($s->tipo); ?></code></td>
                            <td style="font-size:0.8rem;color:var(--color-slate-500);"><?php echo esc_html($s->categoria ?: '-'); ?></td>
                            <td>
                                <?php if ($__centro_obj): ?>
                                    <span style="display:inline-block;padding:3px 9px;background:<?php echo esc_attr($__centro_obj->cor ?? 'var(--color-slate-500)'); ?>;color:var(--color-white);border-radius:10px;font-size:11px;font-weight:600;">
                                        <?php echo esc_html($__centro_obj->nome); ?>
                                    </span>
                                <?php else: ?>
                                    <span style="display:inline-block;padding:3px 9px;background:var(--color-danger-600);color:var(--color-white);border-radius:10px;font-size:11px;font-weight:600;">
                                        POR DEFINIR
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td style="color:var(--color-success-500);font-weight:600;"><?php echo number_format((float)$s->valor, 2); ?> <?php echo esc_html(sige_moeda()); ?></td>
                            <td style="font-size:0.8rem;">Dia <?php echo (int)($s->dia_vencimento ?? 10); ?></td>
                            <td class="sige-u-nowrap">
                                <a class="fc-btn fc-btn-ghost fc-btn-sm" href="?page=sige-app&view=financeiro-config&edit=<?php echo (int)$s->id; ?>">Editar</a>
                                <a class="fc-btn fc-btn-sm sg-fincfg-danger-link" href="<?php echo esc_url($del_url); ?>" data-sg-fincfg-confirm="Remover este serviço?" data-sg-fincfg-confirm-detail="Confirme apenas se pretende remover ou desactivar este serviço de forma segura.">Eliminar</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table></div>
            <div style="padding:16px 24px;font-size:0.7rem;color:var(--color-slate-400);border-top:1px solid var(--color-ink-50);">
                Para "Outros serviços" na Tesouraria: <strong>categoria=nao_fixo</strong> + tipos <code>uniforme</code>, <code>camiseta</code>, etc.
            </div>
        </div>
    </div>

</div><!-- /grid-12 -->

<div id="sgFincfgConfirmModal" class="sg-fincfg-modal" aria-hidden="true">
    <div class="sg-fincfg-modal-card" role="dialog" aria-modal="true" aria-labelledby="sgFincfgConfirmTitle">
        <div class="sg-fincfg-modal-head">
            <div>
                <span class="sg-fincfg-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('shield') : ''; ?> Confirmação</span>
                <h3 id="sgFincfgConfirmTitle">Confirmar acção</h3>
            </div>
            <button type="button" class="sg-fincfg-modal-x" data-sg-fincfg-close aria-label="Fechar">×</button>
        </div>
        <div class="sg-fincfg-modal-body">
            <p id="sgFincfgConfirmText">Confirma esta acção?</p>
            <small id="sgFincfgConfirmDetail">Esta operação deve ser feita com atenção.</small>
        </div>
        <div class="sg-fincfg-modal-actions">
            <button type="button" class="fc-btn fc-btn-ghost" data-sg-fincfg-close>Voltar</button>
            <button type="button" class="fc-btn fc-btn-primary" id="sgFincfgConfirmAction">Confirmar</button>
        </div>
    </div>
</div>


<script <?php echo sige_csp_script_attr(); ?>>
document.addEventListener('DOMContentLoaded', function(){
    var serviceModal = document.getElementById('sgFincfgServicoModal');
    if (!serviceModal) return;
    var isEditing = serviceModal.classList.contains('is-open');
    function openServiceModal(){
        serviceModal.classList.add('is-open');
        serviceModal.setAttribute('aria-hidden','false');
        document.body.classList.add('sg-fincfg-service-modal-open');
        var first = serviceModal.querySelector('input[name="nome"]');
        if (first) setTimeout(function(){ first.focus(); }, 80);
    }
    function closeServiceModal(){
        if (isEditing) {
            window.location.href = '?page=sige-app&view=financeiro-config#fc-catalogo';
            return;
        }
        serviceModal.classList.remove('is-open');
        serviceModal.setAttribute('aria-hidden','true');
        document.body.classList.remove('sg-fincfg-service-modal-open');
    }
    document.querySelectorAll('#sgFincfgOpenServicoModal,[data-sg-fincfg-servico-open]').forEach(function(btn){
        btn.addEventListener('click', function(e){ e.preventDefault(); openServiceModal(); });
    });
    document.querySelectorAll('[data-sg-fincfg-servico-close]').forEach(function(btn){
        btn.addEventListener('click', function(e){ e.preventDefault(); closeServiceModal(); });
    });
    serviceModal.addEventListener('click', function(e){ if (e.target === serviceModal) closeServiceModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && serviceModal.classList.contains('is-open')) closeServiceModal(); });
    if (isEditing) document.body.classList.add('sg-fincfg-service-modal-open');
});
</script>

<script <?php echo sige_csp_script_attr(); ?>>
document.addEventListener('DOMContentLoaded', function(){
    var modal = document.getElementById('sgFincfgConfirmModal');
    if (!modal) return;
    var text = document.getElementById('sgFincfgConfirmText');
    var detail = document.getElementById('sgFincfgConfirmDetail');
    var action = document.getElementById('sgFincfgConfirmAction');
    var targetHref = '';
    function closeModal(){
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden','true');
        document.body.classList.remove('sg-fincfg-modal-open');
        targetHref = '';
    }
    document.querySelectorAll('[data-sg-fincfg-close]').forEach(function(btn){ btn.addEventListener('click', closeModal); });
    modal.addEventListener('click', function(e){ if (e.target === modal) closeModal(); });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && modal.classList.contains('is-open')) closeModal(); });
    document.querySelectorAll('[data-sg-fincfg-confirm]').forEach(function(el){
        el.addEventListener('click', function(e){
            e.preventDefault();
            targetHref = el.getAttribute('href') || '';
            text.textContent = el.getAttribute('data-sg-fincfg-confirm') || 'Confirma esta acção?';
            detail.textContent = el.getAttribute('data-sg-fincfg-confirm-detail') || 'Esta operação deve ser feita com atenção.';
            modal.classList.add('is-open');
            modal.setAttribute('aria-hidden','false');
            document.body.classList.add('sg-fincfg-modal-open');
        });
    });
    action.addEventListener('click', function(){ if (targetHref) window.location.href = targetHref; });
});
</script>

</div><!-- /fc-page -->
