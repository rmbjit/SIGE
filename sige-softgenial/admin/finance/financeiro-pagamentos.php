<?php
/**
 * SIGE SoftGenial - Financeiro: Pagamentos
 *
 * v2.1 - Abril 2026
 * - CSS: Indigo→Navy palette finalizada
 * - @import fonts removido, fadeInUp adicionado
 * - date_default_timezone_set removido
 * - 8 funções guardadas, $escola_id normalizado
 */
if (!defined('ABSPATH')) exit;
// Guard de acesso - Tesouraria
// [12.9.6] Matriz SIGE manda; WP caps fallback.
if (!sige_page_guard(
    ['financeiro.pagar','financeiro.ver'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_financeiro']
)) return;
global $wpdb;

// ==============================================================================
// 0. SEGURANÇA E DEPENDÊNCIAS - [v14.1.0] guard unificado com o check inicial (linha 12)
// ==============================================================================

if (!function_exists('sige_fin_get_config')) {
 echo '<div class="notice notice-error"><p>Erro Crítico: Finance Core não carregado.</p></div>';
 return;
}

$ano_letivo = sige_fin_get_ano_letivo_master();
$config_fin = sige_fin_get_config($ano_letivo);
// [v12.5.1] Compatibilidade segura: pagamento parcial é permitido por defeito.
// Se a escola tiver configuração explícita permitir_pagamento_parcial=0, respeita-se a decisão.
// Isto evita regressão em ambientes onde a linha de configuração ainda não foi criada para o ano lectivo actual.
$permite_parcial = (int)($config_fin->permitir_pagamento_parcial ?? 1) === 1;

// Tabelas
$tL = $wpdb->prefix . 'sige_fin_lancamentos';
$tP = $wpdb->prefix . 'sige_fin_pagamentos';
$escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
if ($escola_id <= 0) {
 echo '<div class="notice notice-error"><p>Escola não identificada. Operações financeiras bloqueadas para evitar escrita no tenant errado.</p></div>';
 return;
}
if (!function_exists('sige_fin_user_can_write_12127')) {
 function sige_fin_user_can_write_12127(string $permission): bool {
     if (function_exists('sige_permissions_is_super_admin') && sige_permissions_is_super_admin(get_current_user_id())) return true;
     if (function_exists('sige_can') && sige_can($permission, ['surface' => 'view_action:financeiro-pagamentos'])) return true;
     return false;
 }
}
$tA = $wpdb->prefix . 'sige_alunos';
$tR = $wpdb->prefix . 'sige_transporte_rotas';
$tS = $wpdb->prefix . 'sige_fin_servicos';

// Colunas valor_transporte, valor_extras, valor_desconto, valor_multa, valor_desconto_especial
// Geridas por class-sige-migration.php (CREATE TABLE + M3)
// Removido ALTER TABLE runtime (redundante desde SCHEMA_VERSION 20260404.2)

// ==============================================================================
// HELPERS
// ==============================================================================

if (!function_exists('sige_fin_obter_nome_mes')) {
function sige_fin_obter_nome_mes($num) {
 $meses = [1=>'Jan',2=>'Fev',3=>'Mar',4=>'Abr',5=>'Mai',6=>'Jun',7=>'Jul',8=>'Ago',9=>'Set',10=>'Out',11=>'Nov',12=>'Dez'];
 return $meses[(int)$num] ?? ('Mês '.$num);
}
}

// [DRY-S12] sige_fin_norm_classe() - canónica em finance-core.php

// [DRY-S12] sige_fin_servico_permitido_para_classe() - canónica em finance-core.php

if (!function_exists('sige_fin_get_servico_por_tipo_e_classe')) {
/**
 * Localiza o serviço adequado para um tipo + classe + ciclo opcional.
 *
 * [v15.2.1 - FIX BUG #1] Refactor: o match anterior fazia
 *   LOWER(classe) = LOWER(%s) OR LOWER(classe) = LOWER(%s . 'ª')
 * em SQL, sem normalização. Para turma '2º/3º Ano', o aluno chega como
 * '2/3 Ano' (após strip de ordinais em financeiro-pagamentos.php:256) e
 * a query nunca casava com o serviço cuja classe na BD é '2º/3º Ano'.
 *
 * Solução: carregar TODOS os serviços do tipo e filtrar em PHP via
 * SIGE_FinanceClasseHelper, que sabe lidar com ranges ('2º/3º Ano' → '2/3'),
 * ordinais, casos especiais (Pré-primário) e match canónico bidireccional.
 *
 * [v15.2.1 - FIX BUG #2] Novo parâmetro opcional $ciclo_preferido permite
 * resolver tempo_inteiro/meio_dia já neste passo, em vez de fazer o
 * chamador refazer o lookup com sige_fin_get_servico_regime_mensalidade().
 *
 * @param string $tipo            Tipo do serviço (mensalidade, estudos, etc.)
 * @param string $classe_aluno    Classe do aluno (já com ordinais strippados)
 * @param int    $eid             escola_id (0 = auto-detect)
 * @param string $ciclo_preferido Ciclo preferido (tempo_inteiro|meio_dia|todos|'')
 * @return object|null            Serviço escolhido ou null se nada bate
 */
function sige_fin_get_servico_por_tipo_e_classe($tipo, $classe_aluno, $eid = 0, $ciclo_preferido = '') {
    global $wpdb;
    $tS = $wpdb->prefix . 'sige_fin_servicos';
    if (!$eid) $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $tipo = strtolower(trim((string)$tipo));
    $classe_aluno = trim((string)$classe_aluno);
    $ciclo_preferido = strtolower(trim((string)$ciclo_preferido));

    // Carregar TODOS os serviços do tipo (filtro de classe fica em PHP)
    $todos = (array)$wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$tS} WHERE ativo=1 AND escola_id=%d AND LOWER(tipo)=%s ORDER BY id ASC",
        $eid, $tipo
    ));
    if (!$todos) return null;

    $usar_helper = class_exists('SIGE_FinanceClasseHelper');
    $candidatos_classe = [];
    $candidatos_geral  = [];

    foreach ($todos as $s) {
        if ($usar_helper) {
            $cls_canon = SIGE_FinanceClasseHelper::canonica($s->classe ?? null);
            if ($cls_canon === 'todas') {
                $candidatos_geral[] = $s;
                continue;
            }
            if (SIGE_FinanceClasseHelper::servicoAplicavelAoAluno($s, $classe_aluno)) {
                $candidatos_classe[] = $s;
            }
        } else {
            // Fallback legacy (só corre se o helper não carregou - não devia acontecer)
            $cls_lower = strtolower(trim((string)($s->classe ?? '')));
            if ($cls_lower === '' || $cls_lower === '0' || $cls_lower === 'todas') {
                $candidatos_geral[] = $s;
            } elseif ($cls_lower === strtolower($classe_aluno)
                   || $cls_lower === strtolower($classe_aluno . 'ª')) {
                $candidatos_classe[] = $s;
            }
        }
    }

    // Preferir match específico de classe; cair em "geral" só se não houver
    $pool = !empty($candidatos_classe) ? $candidatos_classe : $candidatos_geral;
    if (empty($pool)) return null;

    // Refinar por ciclo se o aluno tem regime_mensalidade definido
    if ($ciclo_preferido !== '' && $ciclo_preferido !== 'todos') {
        foreach ($pool as $s) {
            $ciclo = strtolower(trim((string)($s->ciclo ?? '')));
            if ($ciclo === $ciclo_preferido) {
                return $s;
            }
        }
        // Sem match exacto de ciclo dentro do pool → cai no primeiro do pool
    }

    return $pool[0];
}
}

// [DRY-S12] sige_transporte_get_preco_mensal() - canónica em finance-core.php

// [DRY-S12] sige_fin_total_restante() - canónica em finance-core.php


if (!function_exists('sige_fin_obter_classe_actual_aluno')) {
function sige_fin_obter_classe_actual_aluno(int $aluno_id, int $ano_lectivo = 0, int $escola_id = 0): string {
 global $wpdb;
 $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
 $ano_lectivo = $ano_lectivo > 0 ? $ano_lectivo : (function_exists('sige_fin_get_ano_letivo_master') ? (int)sige_fin_get_ano_letivo_master() : (int)date('Y'));
 $tM = $wpdb->prefix . 'sige_matriculas';
 $tT = $wpdb->prefix . 'sige_turmas';
 $queries = [
   $wpdb->prepare("SELECT COALESCE(NULLIF(TRIM(t.classe),''), NULLIF(TRIM(t.nivel_ensino),''), '')
                   FROM $tM m
                   JOIN $tT t ON t.id = m.turma_id
                   WHERE m.aluno_id=%d AND m.escola_id=%d AND m.ano_lectivo=%d
                     AND (m.status_matricula IS NULL OR LOWER(m.status_matricula) IN ('activa','ativa','activo','ativo'))
                   ORDER BY m.id DESC LIMIT 1", $aluno_id, $escola_id, $ano_lectivo),
   $wpdb->prepare("SELECT COALESCE(NULLIF(TRIM(t.classe),''), NULLIF(TRIM(t.nivel_ensino),''), '')
                   FROM $tM m
                   JOIN $tT t ON t.id = m.turma_id
                   WHERE m.aluno_id=%d AND m.escola_id=%d AND m.ano_lectivo=%d
                   ORDER BY m.id DESC LIMIT 1", $aluno_id, $escola_id, $ano_lectivo),
   $wpdb->prepare("SELECT COALESCE(NULLIF(TRIM(t.classe),''), NULLIF(TRIM(t.nivel_ensino),''), '')
                   FROM $tM m
                   JOIN $tT t ON t.id = m.turma_id
                   WHERE m.aluno_id=%d AND m.escola_id=%d
                     AND (m.status_matricula IS NULL OR LOWER(m.status_matricula) IN ('activa','ativa','activo','ativo'))
                   ORDER BY m.ano_lectivo DESC, m.id DESC LIMIT 1", $aluno_id, $escola_id),
   $wpdb->prepare("SELECT COALESCE(NULLIF(TRIM(t.classe),''), NULLIF(TRIM(t.nivel_ensino),''), '')
                   FROM $tM m
                   JOIN $tT t ON t.id = m.turma_id
                   WHERE m.aluno_id=%d AND m.escola_id=%d
                   ORDER BY m.ano_lectivo DESC, m.id DESC LIMIT 1", $aluno_id, $escola_id),
 ];
 foreach ($queries as $sql) {
   $classe = trim((string)$wpdb->get_var($sql));
   if ($classe !== '') return trim((string)preg_replace('/[ªº]/u', '', $classe));
 }
 return '';
}
}

if (!function_exists('sige_fin_get_servicos_selecionados_do_aluno')) {
function sige_fin_get_servicos_selecionados_do_aluno($aluno_row, $classe_aluno) {
 // Fonte única de verdade para serviços escolhidos no registo do aluno.
 // Separa os serviços em dois grupos:
 //   • mensais  → aparecem em "Adiantar Meses Futuros"
 //   • avulsos  → aparecem em "Outros Serviços"
 // Isto evita três inconsistências clássicas:
 //   1) mostrar no pagamento serviços que o aluno NÃO escolheu;
 //   2) empurrar livros/materiais avulsos para cobrança mensal;
 //   3) deixar de fora Judo/Piano/etc. gravados em sige_aluno_atividades_extras.
 global $wpdb;
 $tS = $wpdb->prefix . 'sige_fin_servicos';
 $tC = $wpdb->prefix . 'sige_fin_centros';
 $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

 $tipos_excluidos_sql = "'mensalidade','inscricao_nova','renovacao_inscricao','transporte'";
 $lista = $wpdb->get_results($wpdb->prepare(
 "SELECT s.*,
         COALESCE(c.nome, 'Sem centro') AS centro_nome,
         COALESCE(c.ordem, 999) AS centro_ordem
  FROM $tS s
  LEFT JOIN $tC c ON c.id = s.centro_id AND c.escola_id = s.escola_id
  WHERE s.ativo = 1
    AND s.escola_id = %d
    AND LOWER(s.tipo) NOT IN ($tipos_excluidos_sql)
  ORDER BY centro_ordem ASC, s.nome ASC",
 $eid
 ));

 $flag_to_tipo = [
 'tem_estudos'        => 'estudos',
 'tem_ingles'         => 'ingles',
 'tem_desporto'       => 'desporto',
 'tem_almoco'         => 'almoco',
 'tem_pequeno_almoco' => 'pequeno_almoco',
 ];

 $selecionados_por_id = [];
 if (!empty($aluno_row->id) && function_exists('sige_fin_get_atividades_extras_aluno')) {
     $atvs = sige_fin_get_atividades_extras_aluno((int)$aluno_row->id, $eid);
     foreach ((array)$atvs as $atv) {
         if (!empty($atv->id)) $selecionados_por_id[(int)$atv->id] = true;
     }
 }

 $mensais = [];
 $avulsos = [];
 foreach ((array)$lista as $s) {
     if (!sige_fin_servico_permitido_para_classe($s, $classe_aluno)) continue;

     $pre = false;
     foreach ($flag_to_tipo as $flag => $tipo_match) {
         if (strtolower((string)($s->tipo ?? '')) === $tipo_match && !empty($aluno_row->{$flag})) {
             $pre = true;
             break;
         }
     }
     if (!$pre && isset($selecionados_por_id[(int)$s->id])) {
         $pre = true;
     }
     if (!$pre) continue;

     $s->pre_tickado = false;
     $categoria = strtolower(trim((string)($s->categoria ?? '')));
     $tipo      = strtolower(trim((string)($s->tipo ?? '')));
     $recorr    = (int)($s->recorrente ?? 0) === 1;

     $eh_mensal = $recorr
         || $categoria === 'fixo_mensal'
         || in_array($tipo, ['estudos','ingles','desporto','almoco','pequeno_almoco','atividade_extra'], true);

     if ($eh_mensal) {
         $mensais[(int)$s->id] = $s;
     } else {
         $avulsos[(int)$s->id] = $s;
     }
 }

 return [
     'mensais' => array_values($mensais),
     'avulsos' => array_values($avulsos),
 ];
}
}

if (!function_exists('sige_fin_get_servicos_extras_do_aluno')) {
function sige_fin_get_servicos_extras_do_aluno($aluno_row, $classe_aluno) {
 $packs = sige_fin_get_servicos_selecionados_do_aluno($aluno_row, $classe_aluno);
 return (array)($packs['mensais'] ?? []);
}
}

if (!function_exists('sige_fin_get_servicos_registo_avulsos_do_aluno')) {
function sige_fin_get_servicos_registo_avulsos_do_aluno($aluno_row, $classe_aluno) {
 $packs = sige_fin_get_servicos_selecionados_do_aluno($aluno_row, $classe_aluno);
 return (array)($packs['avulsos'] ?? []);
}
}

if (!function_exists('sige_fin_get_servicos_nao_fixos_para_classe')) {
function sige_fin_get_servicos_nao_fixos_para_classe($classe_aluno, $eid = 0) {
 global $wpdb;
 $tS = $wpdb->prefix . 'sige_fin_servicos';
 if (!$eid) $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
 $lista = $wpdb->get_results($wpdb->prepare("
 SELECT * FROM $tS
 WHERE ativo=1 AND escola_id=%d AND LOWER(COALESCE(categoria,''))='nao_fixo'
 ORDER BY nome ASC
 ", $eid));
 $ok = [];
 foreach ((array)$lista as $s) {
 if (sige_fin_servico_permitido_para_classe($s, $classe_aluno)) $ok[] = $s;
 }
 return $ok;
}
}

if (!function_exists('sige_fin_get_servicos_atividades_disponiveis')) {
function sige_fin_get_servicos_atividades_disponiveis($classe_aluno = '', $eid = 0) {
 global $wpdb;
 $tS = $wpdb->prefix . 'sige_fin_servicos';
 if (!$eid) $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
 $tipos_atividades = ['estudos', 'ingles', 'desporto', 'almoco', 'pequeno_almoco'];
 $tipos_sql = implode("','", $tipos_atividades);
 $lista = $wpdb->get_results($wpdb->prepare("
 SELECT * FROM $tS 
 WHERE ativo=1 AND escola_id=%d AND LOWER(tipo) IN ('$tipos_sql')
 ORDER BY nome ASC
 ", $eid));
 $ok = [];
 foreach ((array)$lista as $s) {
 if ($classe_aluno === '' || sige_fin_servico_permitido_para_classe($s, $classe_aluno)) {
 $ok[] = $s;
 }
 }
 return $ok;
}
}

// [DRY-S12] sige_fin_servico_eh_mensalidade() - canónica em finance-core.php

// [DRY-S12] sige_fin_get_servico_creche() - canónica em finance-core.php

// [DRY-S12] sige_fin_get_ou_criar_servico_transporte() - canónica em finance-core.php

// ==============================================================================
// 1) PROCESSADOR UNIFICADO DE PAGAMENTOS
// ==============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fin_pagar_submit'])) {
 if (!sige_fin_user_can_write_12127('financeiro.pagar')) {
 echo '<div class="notice notice-error"><p>Sem permissão para registar pagamentos.</p></div>';
 } elseif (!isset($_POST['_wpnonce']) || !wp_verify_nonce($_POST['_wpnonce'], 'sige_fin_pagar')) {
 echo '<div class="notice notice-error"><p>A sessão expirou por segurança. Recarregue a página e tente novamente.</p></div>';
 } else {
 $aluno_id = sige_fin_post_int('aluno_id');
 $metodo = sige_fin_post_param('metodo_pagamento', 'numerario');
 $ref = sige_fin_post_param('referencia_externa');
 $multa_isenta = !empty($_POST['multa_isenta']); // checkbox bool - intencional
 $motivo_isencao = sige_fin_post_param('motivo_isencao');
 $parcial_valor = sige_fin_post_float('valor_parcial');
 
 $modo_parcial = ($parcial_valor > 0);
 $saldo_parcial = $parcial_valor; 

 $desconto_especial_total = max(0.0, sige_fin_post_float('desconto_especial'));
 $motivo_desconto_especial = sige_fin_post_param('motivo_desconto_especial');
 
 if ($desconto_especial_total > 0 && empty($motivo_desconto_especial)) {
 echo '<div class="notice notice-error" style="margin:10px 0;padding:12px;"><p>O motivo do desconto especial é obrigatório.</p></div>';
 $desconto_especial_total = 0;
 }

 $ids_reais = isset($_POST['lancamentos']) && is_array($_POST['lancamentos']) ? array_map('intval', $_POST['lancamentos']) : [];
 $itens_virtuais = isset($_POST['virtuais']) && is_array($_POST['virtuais']) ? $_POST['virtuais'] : [];
 $debug_erros = [];

 if (!$aluno_id || (empty($ids_reais) && empty($itens_virtuais))) {
 echo '<div class="notice notice-error"><p>Seleccione o aluno e pelo menos um item para pagar.</p></div>';
 } else {
 $aluno_row_post = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tA WHERE id=%d AND escola_id=%d", $aluno_id, $escola_id));

 $aluno_inactivo_recorrente_post = function_exists('sige_aluno_financeiramente_inactivo') ? sige_aluno_financeiramente_inactivo((int)$aluno_id, (int)$escola_id, (int)$ano_letivo) : false;

 $_tM2 = $wpdb->prefix . 'sige_matriculas';
 $_tT2 = $wpdb->prefix . 'sige_turmas';
 $classe_aluno_post = function_exists('sige_fin_obter_classe_actual_aluno') ? sige_fin_obter_classe_actual_aluno((int)$aluno_id, (int)$ano_letivo, (int)$escola_id) : '';

 // [v15.2.0 - F2] Normalização via ItemDispatcher antes do loop.
 // Trata case-insensitive (bug: regex [a-z_]+ rejeitava serviços com
 // maiúscula), filtra itens inválidos, e prepara terreno para migração
 // futura para JSON tipado (item = ['kind'=>'mens', 'mes'=>4]).
 // O loop abaixo continua a aceitar as strings legacy para zero breakage
 // da UI actual; o dispatcher só valida antecipadamente.
 $_itens_validados = [];
 foreach ($itens_virtuais as $_v_raw) {
 $_parsed = SIGE_FinanceItemDispatcher::parseLegacy((string)$_v_raw);
 if ($_parsed !== null) {
 // Reconstruir versão canónica (case-normalizada) da string
 $_canonical = SIGE_FinanceItemDispatcher::toLegacy($_parsed);
 if ($_canonical !== null) $_itens_validados[] = $_canonical;
 }
 }
 $itens_virtuais = $_itens_validados;

 // FASE 1: MATERIALIZAR ITENS VIRTUAIS
 foreach ($itens_virtuais as $v_str) {
 $v_str = (string)$v_str;
 if (!empty($aluno_inactivo_recorrente_post) && preg_match('/^(PACK|MENS|TRAN|EXT|ATIV)_/i', $v_str)) {
     $debug_erros[] = 'Aluno transferido/desistente/inactivo: novas cobranças recorrentes foram bloqueadas. Apenas dívidas antigas legítimas ou serviços avulsos podem ser tratados.';
     continue;
 }

 // (A) PACOTE MENSAL (PACK_MM)
 if (preg_match('/^PACK_(\d{1,2})$/', $v_str, $m)) {
 $mes_num = (int)$m[1];
 if ($mes_num < 1 || $mes_num > 12) continue;
 $mes_ref = $ano_letivo . '-' . str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT);
 $dia_venc_pac = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);
 $ts_venc = strtotime($mes_ref . '-01');
 $data_venc = sige_mz_date('Y-m', $ts_venc) . '-' . str_pad((string)$dia_venc_pac, 2, '0', STR_PAD_LEFT);
 
 $pack_mens_resolvida = false;
 $lanc_mens_aberto = function_exists('sige_fin_find_lancamento_aberto_por_tipo_mes') ? sige_fin_find_lancamento_aberto_por_tipo_mes((int)$aluno_id, 'mensalidade', $mes_ref, (int)$escola_id) : null;
 if ($lanc_mens_aberto && !empty($lanc_mens_aberto->id)) {
 $ids_reais[] = (int)$lanc_mens_aberto->id;
 $pack_mens_resolvida = true;
 }
 if (!$pack_mens_resolvida) {
 $srv_mens = sige_fin_get_servico_por_tipo_e_classe(
     'mensalidade', $classe_aluno_post, $escola_id,
     (string)($aluno_row_post->regime_mensalidade ?? '')
 );
 if (!$srv_mens) { $debug_erros[] = "Não existe serviço tipo=mensalidade para a classe/turma do aluno."; continue; }
 if (!sige_fin_servico_permitido_para_classe($srv_mens, $classe_aluno_post)) { $debug_erros[] = "Bloqueado: mensalidade incompatível com a classe/turma do aluno."; continue; }
 
 $descM = $srv_mens->nome . ' (' . sige_fin_obter_nome_mes($mes_num) . '/' . $ano_letivo . ')';
 $srv_mens_final = $srv_mens;
 if (!empty($aluno_row_post->regime_creche)) {
 $all_srv_pack = function_exists('sige_fin_get_servicos_ativos_cached') ? sige_fin_get_servicos_ativos_cached((int)$escola_id) : $wpdb->get_results($wpdb->prepare("SELECT * FROM $tS WHERE ativo=1 AND escola_id=%d", $escola_id));
 $srv_c_pack = sige_fin_get_servico_creche($aluno_row_post->regime_creche, $all_srv_pack, $classe_aluno_post);
 if ($srv_c_pack) $srv_mens_final = $srv_c_pack;
 }
 $valor_mens = (float)$srv_mens_final->valor;
 if (!empty($aluno_row_post->mensalidade_base) && (float)$aluno_row_post->mensalidade_base > 0) {
 $valor_mens = (float)$aluno_row_post->mensalidade_base;
 }
 $resM = sige_fin_upsert_lancamento($aluno_id, (int)$srv_mens_final->id, $mes_ref, $descM, $valor_mens, $data_venc, $config_fin, $srv_mens_final);
 if (!empty($resM['lanc_id'])) { $ids_reais[] = (int)$resM['lanc_id']; }
 else { $debug_erros[] = "Mensalidade de " . sige_fin_obter_nome_mes($mes_num) . "/{$ano_letivo} não pôde ser preparada: " . (function_exists('sige_fin_upsert_acao_label') ? sige_fin_upsert_acao_label($resM) : ($resM['acao'] ?? 'sem detalhe')) . "."; }
 }

 $lanc_tran_aberto = function_exists('sige_fin_find_lancamento_aberto_por_tipo_mes') ? sige_fin_find_lancamento_aberto_por_tipo_mes((int)$aluno_id, 'transporte', $mes_ref, (int)$escola_id) : null;
 if ($lanc_tran_aberto && !empty($lanc_tran_aberto->id)) {
 $ids_reais[] = (int)$lanc_tran_aberto->id;
 } else {
 $preco_transp = sige_transporte_get_preco_mensal($aluno_id);
 if ($preco_transp !== null && (float)$preco_transp > 0) {
 $srv_transp = sige_fin_get_ou_criar_servico_transporte();
 if ($srv_transp) {
 $descT = $srv_transp->nome . ' (' . sige_fin_obter_nome_mes($mes_num) . '/' . $ano_letivo . ')';
 $resT = sige_fin_upsert_lancamento($aluno_id, (int)$srv_transp->id, $mes_ref, $descT, (float)$preco_transp, $data_venc, $config_fin, $srv_transp);
 if (!empty($resT['lanc_id'])) { $ids_reais[] = (int)$resT['lanc_id']; }
 else { $debug_erros[] = "Transporte de " . sige_fin_obter_nome_mes($mes_num) . "/{$ano_letivo} não pôde ser preparado: " . (function_exists('sige_fin_upsert_acao_label') ? sige_fin_upsert_acao_label($resT) : ($resT['acao'] ?? 'sem detalhe')) . "."; }
 }
 }
 }
 // [ALTERAÇÃO Abril/2026] Extras são lançados separadamente via EXT_tipo_MM
 // Removido lançamento automático de extras aqui
 continue;
 }

 // (A2) SÓ MENSALIDADE (MENS_MM)
 if (preg_match('/^MENS_(\d{1,2})$/', $v_str, $m)) {
 $mes_num = (int)$m[1];
 if ($mes_num < 1 || $mes_num > 12) continue;
 $mes_ref = $ano_letivo . '-' . str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT);
 $dia_venc_pac = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);
 $ts_venc = strtotime($mes_ref . '-01');
 $data_venc = sige_mz_date('Y-m', $ts_venc) . '-' . str_pad((string)$dia_venc_pac, 2, '0', STR_PAD_LEFT);
 
 $lanc_mens_aberto = function_exists('sige_fin_find_lancamento_aberto_por_tipo_mes') ? sige_fin_find_lancamento_aberto_por_tipo_mes((int)$aluno_id, 'mensalidade', $mes_ref, (int)$escola_id) : null;
 if ($lanc_mens_aberto && !empty($lanc_mens_aberto->id)) {
 $ids_reais[] = (int)$lanc_mens_aberto->id;
 continue;
 }
 $srv_mens = sige_fin_get_servico_por_tipo_e_classe(
     'mensalidade', $classe_aluno_post, $escola_id,
     (string)($aluno_row_post->regime_mensalidade ?? '')
 );
 if (!$srv_mens) { $debug_erros[] = "Sem serviço mensalidade para a classe/turma do aluno."; continue; }
 if (!sige_fin_servico_permitido_para_classe($srv_mens, $classe_aluno_post)) { $debug_erros[] = "Mensalidade incompatível com a classe/turma do aluno."; continue; }
 
 $srv_mens_final = $srv_mens;
 if (!empty($aluno_row_post->regime_creche)) {
 $all_srv_m = function_exists('sige_fin_get_servicos_ativos_cached') ? sige_fin_get_servicos_ativos_cached((int)$escola_id) : $wpdb->get_results($wpdb->prepare("SELECT * FROM $tS WHERE ativo=1 AND escola_id=%d", $escola_id));
 $srv_c_m = sige_fin_get_servico_creche($aluno_row_post->regime_creche, $all_srv_m, $classe_aluno_post);
 if ($srv_c_m) $srv_mens_final = $srv_c_m;
 }
 $valor_mens = (float)$srv_mens_final->valor;
 if (!empty($aluno_row_post->mensalidade_base) && (float)$aluno_row_post->mensalidade_base > 0) {
 $valor_mens = (float)$aluno_row_post->mensalidade_base;
 }
 $descM = $srv_mens_final->nome . ' (' . sige_fin_obter_nome_mes($mes_num) . '/' . $ano_letivo . ')';
 $resM = sige_fin_upsert_lancamento($aluno_id, (int)$srv_mens_final->id, $mes_ref, $descM, $valor_mens, $data_venc, $config_fin, $srv_mens_final);
 if (!empty($resM['lanc_id'])) { $ids_reais[] = (int)$resM['lanc_id']; }
 else { $debug_erros[] = "Mensalidade de " . sige_fin_obter_nome_mes($mes_num) . "/{$ano_letivo} não pôde ser preparada: " . (function_exists('sige_fin_upsert_acao_label') ? sige_fin_upsert_acao_label($resM) : ($resM['acao'] ?? 'sem detalhe')) . "."; }
 
 // [ALTERAÇÃO Abril/2026] Extras são lançados separadamente via EXT_tipo_MM
 // Removido lançamento automático de extras aqui
 continue;
 }

 // (A3) SÓ TRANSPORTE (TRAN_MM)
 if (preg_match('/^TRAN_(\d{1,2})$/', $v_str, $m)) {
 $mes_num = (int)$m[1];
 if ($mes_num < 1 || $mes_num > 12) continue;
 $mes_ref = $ano_letivo . '-' . str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT);
 $dia_venc_pac = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);
 $ts_venc = strtotime($mes_ref . '-01');
 $data_venc = sige_mz_date('Y-m', $ts_venc) . '-' . str_pad((string)$dia_venc_pac, 2, '0', STR_PAD_LEFT);
 
 $lanc_tran_aberto = function_exists('sige_fin_find_lancamento_aberto_por_tipo_mes') ? sige_fin_find_lancamento_aberto_por_tipo_mes((int)$aluno_id, 'transporte', $mes_ref, (int)$escola_id) : null;
 if ($lanc_tran_aberto && !empty($lanc_tran_aberto->id)) {
 $ids_reais[] = (int)$lanc_tran_aberto->id;
 continue;
 }
 $preco_transp = sige_transporte_get_preco_mensal($aluno_id);
 if ($preco_transp !== null && (float)$preco_transp > 0) {
 $srv_transp = sige_fin_get_ou_criar_servico_transporte();
 if ($srv_transp) {
 $descT = $srv_transp->nome . ' (' . sige_fin_obter_nome_mes($mes_num) . '/' . $ano_letivo . ')';
 $resT = sige_fin_upsert_lancamento($aluno_id, (int)$srv_transp->id, $mes_ref, $descT, (float)$preco_transp, $data_venc, $config_fin, $srv_transp);
 if (!empty($resT['lanc_id'])) { $ids_reais[] = (int)$resT['lanc_id']; }
 else { $debug_erros[] = "Transporte de " . sige_fin_obter_nome_mes($mes_num) . "/{$ano_letivo} não pôde ser preparado: " . (function_exists('sige_fin_upsert_acao_label') ? sige_fin_upsert_acao_label($resT) : ($resT['acao'] ?? 'sem detalhe')) . "."; }
 }
 } else {
 $debug_erros[] = "Aluno sem rota de transporte.";
 }
 continue;
 }
 
 // [v14.1.1 EXT-01] SERVIÇOS EXTRAS INDIVIDUAIS - dois formatos aceites:
 //   EXT_<servico_id>_<mes>  (novo, por ID - suporta N serviços do mesmo tipo)
 //   EXT_<tipo>_<mes>        (legacy - mantido para retro-compat)
 // O formato novo é preferido porque catálogos com múltiplos serviços do mesmo
 // tipo (ex: 4× atividade_extra) precisam de distinguir qual serviço lançar.
 $srv_extra = null;
 $mes_num = 0;
 if (preg_match('/^EXT_(\d+)_(\d{1,2})$/', $v_str, $m)) {
 // Formato novo: servico_id explícito
 $srv_id = (int)$m[1];
 $mes_num = (int)$m[2];
 if ($mes_num < 1 || $mes_num > 12) continue;
 if ($srv_id <= 0) continue;
 $srv_extra = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM $tS WHERE id=%d AND ativo=1 AND escola_id=%d LIMIT 1",
 $srv_id, $escola_id
 ));
 if (!$srv_extra) { $debug_erros[] = "Serviço extra #$srv_id não encontrado/inactivo."; continue; }
 if (!sige_fin_servico_permitido_para_classe($srv_extra, $classe_aluno_post)) {
 $debug_erros[] = "Bloqueado: serviço extra incompatível com a classe."; continue;
 }
 } elseif (preg_match('/^EXT_([a-z_]+)_(\d{1,2})$/', $v_str, $m)) {
 // Formato legacy: tipo textual
 $tipo_extra = (string)$m[1];
 $mes_num = (int)$m[2];
 if ($mes_num < 1 || $mes_num > 12) continue;
 // Whitelist expandida para incluir os tipos genéricos de Abril/2026
 $tipos_permitidos = ['estudos', 'ingles', 'desporto', 'almoco', 'pequeno_almoco',
                      'atividade_extra', 'material'];
 if (!in_array($tipo_extra, $tipos_permitidos, true)) continue;
 $srv_extra = sige_fin_get_servico_por_tipo_e_classe($tipo_extra, $classe_aluno_post);
 if (!$srv_extra || empty($srv_extra->id)) {
 $debug_erros[] = "Serviço extra '$tipo_extra' não encontrado."; continue;
 }
 } else {
 // Não bate com EXT_* - deixar outros handlers processar
 $srv_extra = null;
 }

 if ($srv_extra && $mes_num > 0) {
 $mes_ref = $ano_letivo . '-' . str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT);
 $dia_venc_ext = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);
 $ts_venc = strtotime($mes_ref . '-01');
 $data_venc = sige_mz_date('Y-m', $ts_venc) . '-' . str_pad((string)$dia_venc_ext, 2, '0', STR_PAD_LEFT);

 $descX = $srv_extra->nome . ' (' . sige_fin_obter_nome_mes($mes_num) . '/' . $ano_letivo . ')';
 $resX = sige_fin_upsert_lancamento($aluno_id, (int)$srv_extra->id, $mes_ref, $descX, (float)$srv_extra->valor, $data_venc, $config_fin, $srv_extra);
 if (!empty($resX['lanc_id'])) $ids_reais[] = (int)$resX['lanc_id'];
 continue;
 }

 // (B) NÃO FIXOS (NF_ID)
 if (preg_match('/^NF_(\d+)$/', $v_str, $m2)) {
 $svc_id = (int)$m2[1];
 if ($svc_id <= 0) continue;
 $srv_nf = function_exists('sige_fin_get_servico_cached') ? sige_fin_get_servico_cached((int)$svc_id, (int)$escola_id) : $wpdb->get_row($wpdb->prepare("SELECT * FROM $tS WHERE id=%d AND ativo=1 AND escola_id=%d LIMIT 1", $svc_id, $escola_id));
 if (!$srv_nf) { $debug_erros[] = "Serviço avulso (#$svc_id) não encontrado/inactivo."; continue; }
 if (!sige_fin_servico_permitido_para_classe($srv_nf, $classe_aluno_post)) { $debug_erros[] = "Bloqueado: serviço incompatível."; continue; }
 // ── Quantidade (avulsos com múltiplas unidades) ───────────────────
 $qty_nf = max(1, min(99, (int)(($_POST['nf_qty'][$svc_id] ?? 1))));
 // [FIX #7] Avulsos: mes_ref único para contornar UNIQUE KEY
 $mes_ref_base = sige_mz_date('Y-m');
 // Contar TODOS os lançamentos deste serviço para este aluno (qualquer mês)
 // e usar o total como sufixo - garante unicidade sem depender de LIKE+prepare
 $_av_total = (int)$wpdb->get_var($wpdb->prepare(
 "SELECT COUNT(*) FROM $tL WHERE escola_id=%d AND aluno_id=%d AND servico_id=%d",
 $escola_id, $aluno_id, (int)$srv_nf->id
 ));
 $mes_ref_av = $_av_total > 0
 ? $mes_ref_base . '-' . ($_av_total + 1)
 : $mes_ref_base;
 $dia_venc_nf = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);
 $data_venc = sige_mz_date('Y-m', strtotime(sige_mz_date('Y-m').'-01')) . '-' . str_pad((string)$dia_venc_nf, 2, '0', STR_PAD_LEFT);
 $preco_unit_nf = (float)$srv_nf->valor;
 $valor_total_nf = $preco_unit_nf * $qty_nf;
 $descNF = $qty_nf > 1
 ? $srv_nf->nome . ' x' . $qty_nf . ' (Avulso ' . sige_mz_date('d/m/Y') . ')'
 : $srv_nf->nome . ' (Avulso ' . sige_mz_date('d/m/Y') . ')';
 // Usar upsert com mes_ref único - mais seguro que INSERT directo
 $resNF = sige_fin_upsert_lancamento($aluno_id, (int)$srv_nf->id, $mes_ref_av, $descNF, $valor_total_nf, $data_venc, $config_fin, $srv_nf);
 if (!empty($resNF['lanc_id'])) {
 $ids_reais[] = (int)$resNF['lanc_id'];
 if ($qty_nf > 1) {
 $wpdb->update($tL, ['quantidade' => $qty_nf], ['id' => (int)$resNF['lanc_id']]);
 }
 } else {
 $debug_erros[] = "Erro ao criar lançamento avulso para {$srv_nf->nome} ({$resNF['acao']}). mes_ref={$mes_ref_av}" . (!empty($resNF['db_error']) ? " DB: {$resNF['db_error']}" : '');
 }
 continue;
 }

 // (C) ACTIVIDADES PONTUAIS (ATIV_MM_ID)
 if (preg_match('/^ATIV_(\d{2})_(\d+)$/', $v_str, $m3)) {
 $mes_num = (int)$m3[1];
 $svc_id = (int)$m3[2];
 if ($mes_num < 1 || $mes_num > 12) continue;
 if ($svc_id <= 0) continue;
 
 $srv_ativ = function_exists('sige_fin_get_servico_cached') ? sige_fin_get_servico_cached((int)$svc_id, (int)$escola_id) : $wpdb->get_row($wpdb->prepare("SELECT * FROM $tS WHERE id=%d AND ativo=1 AND escola_id=%d LIMIT 1", $svc_id, $escola_id));
 if (!$srv_ativ) { $debug_erros[] = "Serviço actividade (#$svc_id) inactivo."; continue; }
 
 $tipos_ativ = ['estudos', 'ingles', 'desporto', 'almoco', 'pequeno_almoco'];
 if (!in_array(strtolower($srv_ativ->tipo ?? ''), $tipos_ativ, true)) { $debug_erros[] = "Serviço não é actividade."; continue; }
 
 $mes_ref = $ano_letivo . '-' . str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT);
 $dia_venc_ativ = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);
 $data_venc = $mes_ref . '-' . str_pad((string)$dia_venc_ativ, 2, '0', STR_PAD_LEFT);
 $descATIV = $srv_ativ->nome . ' (' . str_pad((string)$mes_num, 2, '0', STR_PAD_LEFT) . '/' . $ano_letivo . ')';
 $resATIV = sige_fin_upsert_lancamento($aluno_id, (int)$srv_ativ->id, $mes_ref, $descATIV, (float)$srv_ativ->valor, $data_venc, $config_fin, $srv_ativ);
 if (!empty($resATIV['lanc_id'])) $ids_reais[] = (int)$resATIV['lanc_id'];
 continue;
 }
 }

 $ids_reais = array_values(array_unique(array_map('intval', $ids_reais)));

 // FASE 1.4: DESCONTO POR ADIANTAMENTO
 $qtd_meses_adiant = 0;
 foreach ($itens_virtuais as $_v) {
 if (preg_match('/^MENS_\d{1,2}$/', (string)$_v)) $qtd_meses_adiant++;
 }
 $desc_adiant_pct = 0.0;
 $desc_adiant_label = '';
 $pct_12 = (float)($config_fin->desc_adiant_12m_pct ?? 0);
 $pct_6 = (float)($config_fin->desc_adiant_6m_pct ?? 0);
 if ($qtd_meses_adiant >= 10 && $pct_12 > 0) {
 $desc_adiant_pct = $pct_12;
 $desc_adiant_label = "Desconto adiantamento {$qtd_meses_adiant} meses ({$pct_12}%)";
 } elseif ($qtd_meses_adiant >= 6 && $pct_6 > 0) {
 $desc_adiant_pct = $pct_6;
 $desc_adiant_label = "Desconto adiantamento {$qtd_meses_adiant} meses ({$pct_6}%)";
 }
 if ($desc_adiant_pct > 0 && !empty($ids_reais)) {
 $total_mens_bruto = 0.0;
 // [v15.2.0 - F3] Batch fetch é obrigatório desde v15.1.0 (fin-core
 // sempre carregado no bootstrap). Fallback N+1 removido.
 $_map_vo = sige_fin_batch_fetch_lancamentos($ids_reais, 'id, valor_original');
 foreach ($ids_reais as $_lid) {
 $_ll = $_map_vo[(int)$_lid] ?? null;
 if ($_ll) $total_mens_bruto += (float)$_ll->valor_original;
 }
 $desc_adiant_valor = round($total_mens_bruto * $desc_adiant_pct / 100, 2);
 if ($desc_adiant_valor > 0) {
 $desconto_especial_total += $desc_adiant_valor;
 if (empty($motivo_desconto_especial)) {
 $motivo_desconto_especial = $desc_adiant_label;
 } else {
 $motivo_desconto_especial .= ' + ' . $desc_adiant_label;
 }
 }
 }

 // FASE 1.5: DISTRIBUIR DESCONTO ESPECIAL
 if ($desconto_especial_total > 0 && !empty($ids_reais)) {
 $saldo_desc = $desconto_especial_total;
 foreach ($ids_reais as $lid) {
 if ($saldo_desc <= 0) break;
 $ll = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tL WHERE id=%d AND escola_id=%d", $lid, $escola_id));
 if (!$ll || $ll->status === 'pago') continue;
 $bruto = ((float)$ll->valor_original
 + (float)($ll->valor_transporte ?? 0)
 + (float)($ll->valor_extras ?? 0)
 + (float)($ll->valor_multa ?? 0))
 - (float)($ll->valor_desconto ?? 0)
 - (float)($ll->valor_pago ?? 0);
 $max_aplicavel = max(0.0, $bruto);
 $aplicar = min($saldo_desc, $max_aplicavel);
 if ($aplicar > 0) {
 $dados_desconto_especial = [
 'valor_desconto_especial' => $aplicar,
 'motivo_desconto_especial' => $motivo_desconto_especial,
];
// [12.6.1] Compatibilidade de schema: algumas instâncias antigas ainda não têm a coluna desconto_especial_por.
// Não criar erro SQL; apenas gravar o aprovador quando a coluna existir.
if (function_exists('sige_db_column_exists')) {
 if (sige_db_column_exists($tL, 'desconto_especial_por')) {
 $dados_desconto_especial['desconto_especial_por'] = get_current_user_id();
 }
} else {
 $col_desc_por = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$tL} LIKE %s", 'desconto_especial_por'));
 if ($col_desc_por) {
 $dados_desconto_especial['desconto_especial_por'] = get_current_user_id();
 }
}
$wpdb->update($tL,
 $dados_desconto_especial,
 ['id' => (int)$lid, 'escola_id' => $escola_id]
 );
 sige_fin_log('desconto_especial', ['lancamento_id' => (int)$lid, 'valor' => $aplicar, 'motivo' => $motivo_desconto_especial, 'aprovado_por' => get_current_user_id()]);
 $saldo_desc -= $aplicar;
 }
 }
 }

 // FASE 2: PAGAMENTO
 // [v12.9.66] Capturar data efectiva (se utilizador indicou data ≠ hoje)
 // Validação cliente já foi feita via AJAX, mas re-validamos aqui no servidor
 // (defesa em profundidade contra request manipulado).
 $__sige_data_efectiva = '';
 if (isset($_POST['data_efectiva'])) {
     $__sige_data_efectiva_raw = sanitize_text_field((string)$_POST['data_efectiva']);
     if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__sige_data_efectiva_raw)) {
         $__sige_data_efectiva = $__sige_data_efectiva_raw;
     }
 }
 // Se foi indicada data diferente de hoje, validar caixa antes de prosseguir
 $__sige_hoje = current_time('Y-m-d');
 if ($__sige_data_efectiva !== '' && $__sige_data_efectiva !== $__sige_hoje) {
     if (function_exists('sige_fin_caixa_status_data')) {
         $__sige_st = sige_fin_caixa_status_data($__sige_data_efectiva, $escola_id);
         if (!$__sige_st['aberta']) {
             echo '<div class="notice notice-error" style="border-left:4px solid var(--color-danger-600);padding:14px;background:var(--color-danger-50);margin:10px 0;">'
                . '<p style="margin:0;font-weight:600;color:var(--color-danger-700);">⚠️ Pagamento bloqueado - caixa fechada</p>'
                . '<p style="margin:6px 0 0 0;color:var(--color-danger-800);">' . esc_html($__sige_st['mensagem']) . '</p>'
                . '<p style="margin:6px 0 0 0;color:var(--color-danger-800);font-size:13px;">Para registar pagamento na data <strong>' . esc_html(wp_date('d/m/Y', strtotime($__sige_data_efectiva))) . '</strong>, peça ao Director para reabrir a caixa desse dia, ou registe na data de hoje.</p>'
                . '</div>';
             return; // aborta o handler - nada é gravado
         }
     }
 }

 $recibo = sige_fin_gerar_recibo_numero('REC');
 $total_pago_msg = 0.0;
 $pagos = 0;
 $erros = [];
 $last_pay_id = 0;

 if (empty($ids_reais)) {
 echo '<div class="notice notice-error"><p>Erro interno: Nenhuma dívida processada.</p>';
 if (!empty($debug_erros)) echo '<p><strong>Detalhes:</strong><br>' . implode('<br>', array_map('esc_html', $debug_erros)) . '</p>';
 if (!empty($itens_virtuais)) {
 echo '<p><small>Itens virtuais recebidos: ' . esc_html(implode(', ', $itens_virtuais)) . '</small></p>';
 }
 echo '</div>';
 } else {
 foreach ($ids_reais as $k => $lanc_id) {
 $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tL WHERE id=%d AND escola_id=%d", $lanc_id, $escola_id));
 if (!$l) continue;

 if (in_array($l->status, ['pago', 'cancelado', 'isento', 'em_plano'], true)) {
 if ($l->status === 'pago') $erros[] = "{$l->descricao} já pago.";
 elseif ($l->status === 'cancelado') $erros[] = "{$l->descricao} está cancelado.";
 elseif ($l->status === 'isento') $erros[] = "{$l->descricao} está isento.";
 elseif ($l->status === 'em_plano') $erros[] = "{$l->descricao} está em plano negociado - use Planos de Pagamento.";
 continue;
 }

 if (function_exists('sige_fin_mes_bloqueado') && sige_fin_mes_bloqueado((int)$l->aluno_id, (string)$l->mes_referencia)) {
 $erros[] = "{$l->descricao} - mês bloqueado, não aceita pagamentos.";
 continue;
 }

 if (function_exists('sige_fin_recalcular_lancamento')) {
 sige_fin_recalcular_e_sync((int)$l->id);
 $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tL WHERE id=%d AND escola_id=%d", $lanc_id, $escola_id));
 }

 $multa = $multa_isenta ? 0.0 : (float)($l->valor_multa ?? 0);
 $transporte = (float)($l->valor_transporte ?? 0);
 $extras = (float)($l->valor_extras ?? 0);
 $desconto = (float)($l->valor_desconto ?? 0);

 $total_divida = ((float)$l->valor_original + $transporte + $extras + $multa) - $desconto - (float)($l->valor_desconto_especial ?? 0);
 $restante = max(0.0, $total_divida - (float)($l->valor_pago ?? 0));

 if ($restante <= 0) { continue; }

 if ($modo_parcial) {
 if ($saldo_parcial <= 0) { continue; }
 $valor_a_pagar = min($saldo_parcial, $restante);
 $saldo_parcial -= $valor_a_pagar;
 } else {
 $valor_a_pagar = $restante;
 }

 $res = sige_fin_registar_pagamento(
 (int)$l->id,
 (float)$valor_a_pagar,
 $metodo,
 $ref ?: null,
 $multa_isenta,
 $motivo_isencao ?: null,
 $recibo
 );

 if (is_wp_error($res)) {
 $erros[] = $res->get_error_message();
 } else {
 $pagos++;
 $last_pay_id = (int)$res;
 $total_pago_msg += (float)$valor_a_pagar;
 }
 }
 }

 // FASE 3: FEEDBACK
 if ($pagos > 0) {
 // [v12.9.66] Aplicar data efectiva ao recibo (se utilizador indicou data passada
 // com caixa aberta). Faz UPDATE de sige_fin_pagamentos.data_efectiva pelos
 // recibos gerados nesta operação. Se aplicação falhar, regista log mas
 // NÃO aborta o pagamento (ele já foi registado com sucesso na função canónica).
 if ($__sige_data_efectiva !== '' && $__sige_data_efectiva !== $__sige_hoje && function_exists('sige_fin_aplicar_data_efectiva')) {
     $__sige_de_res = sige_fin_aplicar_data_efectiva($recibo, $__sige_data_efectiva, $escola_id);
     if (!empty($__sige_de_res['ok'])) {
         echo '<div class="notice notice-info" style="border-left:4px solid var(--color-info-400);padding:10px 14px;background:var(--sg-theme-soft,var(--color-brand-50));margin:10px 0;">'
            . '<p style="margin:0;color:var(--sg-theme-primary,var(--color-brand-500));font-size:13px;">📅 Pagamento registado com data efectiva <strong>' . esc_html(wp_date('d/m/Y', strtotime($__sige_data_efectiva))) . '</strong> - vai aparecer no extracto desse dia.</p>'
            . '</div>';
     } else {
         echo '<div class="notice notice-warning" style="border-left:4px solid var(--color-warning-500);padding:10px 14px;background:var(--color-warning-50);margin:10px 0;">'
            . '<p style="margin:0;color:var(--color-warning-800);font-size:13px;">⚠️ Pagamento registado, mas a data efectiva não pôde ser aplicada: ' . esc_html($__sige_de_res['message'] ?? 'erro desconhecido') . '. O pagamento aparece no extracto de hoje.</p>'
            . '</div>';
     }
 }

 $notificar_whatsapp_pagamento = function_exists('sige_fin_notificacao_canal_ativo') ? sige_fin_notificacao_canal_ativo('whatsapp') : true;
 $notificar_email_pagamento    = function_exists('sige_fin_notificacao_canal_ativo') ? sige_fin_notificacao_canal_ativo('email') : true;
 $detalhes_pagos = [];
 if (!empty($ids_reais)) {
 // [v15.2.0 - F3] Fallback N+1 removido; batch fetch é obrigatório
 $_map_fb = sige_fin_batch_fetch_lancamentos(
 $ids_reais,
 'id, descricao, valor_original, valor_pago, status'
 );
 foreach ($ids_reais as $_lid) {
 $_ll = $_map_fb[(int)$_lid] ?? null;
 if (!$_ll) continue;
 $detalhes_pagos[] = "• " . ($_ll->descricao ?: 'Serviço #' . $_lid) . " - " . number_format((float)$_ll->valor_pago, 2, ',', '.') . " " . sige_moeda() . ($_ll->status === 'parcial' ? ' _(parcial)_' : '');
 }
 }
 $detalhes_str = !empty($detalhes_pagos) ? implode("\n", $detalhes_pagos) : 'Pagamento (' . (int)$pagos . ' item(ns))';

 $link_recibo_publico = '';
 if (function_exists('sige_recibo_url_publica')) {
                    // Passar aluno_id: token fica ligado a este aluno especifico
                    $link_recibo_publico = sige_recibo_url_publica($recibo, (int)$aluno_id);
 }

 if (!empty($notificar_whatsapp_pagamento) && function_exists('sige_fin_wpp_notificar_encarregados')) {
                    // [v13.5.1] Dual-send: WhatsApp para pai E mãe
                    // [v12.9.58] Link NÃO é passado: a mensagem termina com pergunta
                    // "Quer receber o link do recibo digital? Responda sim..." e o link
                    // só é enviado se o encarregado responder afirmativamente.
                    // [v12.9.61] 'link_recibo_pendente' NÃO entra no corpo da mensagem,
                    // mas é capturado pelo dual-send para registar o pending_receipt
                    // (transient 48h). Quando o encarregado responder "sim/quero/ok",
                    // o webhook usa esse pending_receipt para enviar o link.
                    sige_fin_wpp_notificar_encarregados((int)$aluno_id, 'recibo', [
                        'valor'                 => (float)$total_pago_msg,
                        'id'                    => (string)$recibo,
                        'servico'               => $detalhes_str,
                        'detalhes'              => $detalhes_str,
                        'mes'                   => sige_mz_date('m/Y'),
                        'vencimento'            => sige_mz_date('d/m/Y'),
                        'data'                  => sige_mz_date('d/m/Y'),
                        'link_recibo_pendente'  => $link_recibo_publico, // [v12.9.61] usado só para opt-in
                    ]);
                    // [v12.9.57] Confirmação de pagamento → envio IMEDIATO.
                    if (function_exists('sige_notify_force_immediate')) {
                        sige_notify_force_immediate((int)$aluno_id, 'recibo');
                    }
 } elseif (!empty($notificar_whatsapp_pagamento) && function_exists('sige_fin_queue_whatsapp') && $aluno_row_post) {
                    // ── Fallback legacy (single-send) - só usado se notificacoes-encarregados não carregou
 $tel_raw = $aluno_row_post->whatsapp_notificacoes ?: ($aluno_row_post->telemovel_pai ?: ($aluno_row_post->telemovel_mae ?? ''));
 $tel_num = preg_replace('/[^0-9]/', '', (string)$tel_raw);
 $tel_num = sige_telefone_normalizar($tel_num);

 if ($tel_num) {
 if (function_exists('sige_wpp_render_finance_template')) {
 $cfg_wpp = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $escola_id));
 // [v12.9.58] Sem link - pergunta no template trata do envio sob pedido
 $msg_recibo = sige_wpp_render_finance_template('recibo', $cfg_wpp, $aluno_row_post, [
 'valor' => (float)$total_pago_msg,
 'id' => (string)$recibo,
 'servico' => $detalhes_str,
 'detalhes' => $detalhes_str,
 'mes' => sige_mz_date('m/Y'),
 'vencimento' => sige_mz_date('d/m/Y'),
 'data' => sige_mz_date('d/m/Y'),
 ]);
 } else {
 $aluno_nome_msg = explode(' ', trim((string)($aluno_row_post->nome_completo ?? '')))[0];
 $msg_recibo = "Pagamento registado\n\n"
 . "Aluno(a): {$aluno_nome_msg}\n"
 . "Recibo: #{$recibo}\n\n"
 . "Itens pagos:\n" . $detalhes_str . "\n\n"
 . "Total: " . number_format((float)$total_pago_msg, 2, ',', '.') . " " . sige_moeda() . "\n";
 if ($link_recibo_publico) $msg_recibo .= "\nPosso partilhar o comprovativo digital por aqui, se desejar.";
                            $msg_recibo .= "\n\nObrigado.";
 }
 // [v12.9.58] Removido bloco que anexava $link_recibo_publico directo.
 sige_fin_queue_whatsapp((int)$aluno_id, $tel_num, 'recibo', $msg_recibo);
 // [v12.9.57] Confirmação de pagamento → envio IMEDIATO (fallback legacy).
 if (function_exists('sige_notify_force_immediate')) {
 sige_notify_force_immediate((int)$aluno_id, 'recibo');
 }
 }
 } elseif (!empty($notificar_whatsapp_pagamento) && function_exists('sige_enviar_whatsapp')) {
 sige_enviar_whatsapp($aluno_id, 'recibo', [
 'valor' => (float)($total_pago_msg ?? 0),
 'id' => (string)$recibo,
 'servico' => $detalhes_str,
 'detalhes' => $detalhes_str,
 // [v12.9.58] 'link' NÃO é passado - o template tem pergunta convidativa.
 'mes' => sige_mz_date('m/Y'),
 'vencimento' => sige_mz_date('d/m/Y'),
 ]);
 }

 if (!empty($notificar_email_pagamento) && function_exists('sige_enviar_recibo_email')) {
 sige_enviar_recibo_email((int)$aluno_id, (string)$recibo, (float)$total_pago_msg, (int)$pagos);
 }

 $print_pay_id = (int)($last_pay_id ?? 0);
 if ($print_pay_id > 0) {
 $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$tP} WHERE id=%d", $print_pay_id));
 if ($exists <= 0) $print_pay_id = 0;
 }

 if ($print_pay_id <= 0) {
 $cols_rs = $wpdb->get_results("SHOW COLUMNS FROM {$tP}");
 $fields = [];
 if (is_array($cols_rs)) foreach ($cols_rs as $c) if (!empty($c->Field)) $fields[] = $c->Field;

 $recibo_col = null;
 foreach (['recibo_numero', 'recibo_nr', 'recibo', 'numero_recibo'] as $cand) {
 if (in_array($cand, $fields, true)) { $recibo_col = $cand; break; }
 }

 $aluno_col = null;
 foreach (['aluno_id', 'id_aluno'] as $cand) {
 if (in_array($cand, $fields, true)) { $aluno_col = $cand; break; }
 }

 if ($recibo_col) {
 if ($aluno_col) {
 $print_pay_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$tP} WHERE `{$recibo_col}` = %s AND `{$aluno_col}` = %d ORDER BY id DESC LIMIT 1", (string)$recibo, (int)$aluno_id));
 } else {
 $print_pay_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$tP} WHERE `{$recibo_col}` = %s ORDER BY id DESC LIMIT 1", (string)$recibo));
 }
 }

 if ($print_pay_id <= 0 && $recibo_col) {
 if ($aluno_id > 0 && $aluno_col) {
 $print_pay_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$tP} WHERE `{$recibo_col}` = %s AND valor_pago > 0 ORDER BY id DESC LIMIT 1", (string)$recibo));
 }
 if ($print_pay_id <= 0) {
 $print_pay_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$tP} WHERE `{$recibo_col}` = %s ORDER BY id DESC LIMIT 1", (string)$recibo));
 }
 }

 if ($print_pay_id <= 0 && $aluno_id > 0 && $aluno_col) {
 $print_pay_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$tP} WHERE `{$aluno_col}` = %d ORDER BY id DESC LIMIT 1", (int)$aluno_id));
 }
 }

 $link_recibo = admin_url('admin.php?sige_print=recibo&id=' . max(0, (int)$print_pay_id));

 echo '<div id="sige-modal-pagamento-sucesso" class="sige-modal sg-paypro-modal sg-paypro-result-modal" style="display:flex;">
 <div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-pag-sucesso-title">
  <div class="sg-paypro-result-hero">
   <div class="sg-paypro-result-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:28px;height:28px;"><polyline points="20 6 9 17 4 12"/></svg></div>
   <h3 id="sige-pag-sucesso-title">Pagamento realizado</h3>
   <p>Foram regularizados <strong>'.$pagos.'</strong> item(ns). Pode imprimir o recibo agora ou continuar a trabalhar.</p>
  </div>
  <div class="sige-modal-footer">
   <button type="button" class="sige-btn sige-btn-ghost" data-sige-act="sigeCloseModal" data-sige-arg="sige-modal-pagamento-sucesso">Continuar</button>
   <a href="' . esc_url($link_recibo) . '" data-sige-act="sigeAbrirJanela" data-sige-prevent data-sige-window-name="ReciboWin" data-sige-window-features="width=900,height=800,scrollbars=yes" class="sige-btn sige-btn-primary">
    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;"><polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg> Imprimir recibo #'.esc_html($recibo).'
   </a>
  </div>
 </div>
</div>';
 }

 if (!empty($erros) || !empty($debug_erros)) {
 echo '<div class="notice notice-warning" style="border-radius:8px; margin-bottom:20px;"><p><strong>Avisos:</strong><br>' . implode('<br>', array_map('esc_html', array_merge($erros, $debug_erros))) . '</p></div>';
 }
 }
 }
}

// ==============================================================================
// 1B) PROCESSADOR DE CANCELAMENTO DE LANÇAMENTO
// [v15.2.0 - F1] Adapter para SIGE_FinanceActionService::cancelLancamento
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fin_cancelar_submit'])) {
 if (!sige_fin_user_can_write_12127('financeiro.lancamentos_gerir')) {
 echo '<div class="notice notice-error"><p>Sem permissão para cancelar lançamentos.</p></div>';
 } elseif (!isset($_POST['_wpnonce_cancel']) || !wp_verify_nonce($_POST['_wpnonce_cancel'], 'sige_cancelar_lancamento')) {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>A sessão expirou por segurança. Recarregue a página e tente novamente.</p></div>';
 } else {
 try {
 $res = SIGE_FinanceActionService::cancelLancamento(
 sige_fin_post_int('lanc_id_cancel'),
 sige_fin_post_param('motivo_cancelamento'),
 $escola_id
 );
 } catch (Throwable $e) {
 $res = ['ok' => false, 'error' => 'Falha técnica ao cancelar o lançamento: ' . $e->getMessage()];
 if (function_exists('sige_audit_log')) {
 sige_audit_log('cancelar_lancamento_falha_tecnica', ['erro' => $e->getMessage()], 'financeiro');
 }
 }
 if ($res['ok']) {
 echo '<div class="notice notice-success is-dismissible" style="padding:12px; border-radius:8px; margin-bottom:20px;"><strong>Lançamento cancelado com sucesso.</strong><br><small>ID #' . esc_html((string)$res['lancamento_id']) . ' - Motivo: ' . esc_html(sige_fin_post_param('motivo_cancelamento')) . '</small></div>';
 } else {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>' . esc_html($res['error']) . '</p></div>';
        if (function_exists('sige_mfa_render_challenge_form') && !empty($res['mfa_required'])) echo sige_mfa_render_challenge_form();
 }
 }
}


// ==============================================================================
// 1B2) PROCESSADOR DE ISENÇÃO DE DÍVIDA/LANÇAMENTO
// [v12.10.138] Adapter seguro para SIGE_FinanceActionService::isentarLancamento
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fin_isentar_lancamento_submit'])) {
 if (!sige_fin_user_can_write_12127('financeiro.isentar_multas')) {
 echo '<div class="notice notice-error"><p>Sem permissão para isentar dívidas.</p></div>';
 } elseif (!isset($_POST['_wpnonce_isentar']) || !wp_verify_nonce($_POST['_wpnonce_isentar'], 'sige_isentar_lancamento')) {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>A sessão expirou por segurança. Recarregue a página e tente novamente.</p></div>';
 } else {
 try {
 $res = SIGE_FinanceActionService::isentarLancamento(
 sige_fin_post_int('lanc_id_isentar'),
 sige_fin_post_param('motivo_isencao_lancamento'),
 $escola_id
 );
 } catch (Throwable $e) {
 $res = ['ok' => false, 'error' => 'Falha técnica ao isentar o lançamento: ' . $e->getMessage()];
 if (function_exists('sige_audit_log')) {
 sige_audit_log('isentar_lancamento_falha_tecnica', ['erro' => $e->getMessage()], 'financeiro');
 }
 }
 if (!empty($res['ok'])) {
 echo '<div class="notice notice-success is-dismissible" style="padding:12px; border-radius:8px; margin-bottom:20px;"><strong>Dívida isenta com sucesso.</strong><br><small>ID #' . esc_html((string)$res['lancamento_id']) . ' - Motivo: ' . esc_html(sige_fin_post_param('motivo_isencao_lancamento')) . '</small></div>';
 } else {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>' . esc_html($res['error'] ?? 'Não foi possível isentar a dívida.') . '</p></div>';
 if (function_exists('sige_mfa_render_challenge_form') && !empty($res['mfa_required'])) echo sige_mfa_render_challenge_form();
 }
 }
}

// ==============================================================================
// 1C) PROCESSADOR DE BLOQUEIO/DESBLOQUEIO DE MÊS
// [v15.2.0 - F1] Adapters para SIGE_FinanceActionService
// ==============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fin_bloquear_mes_submit'])) {
 if (!sige_fin_user_can_write_12127('financeiro.bloquear_mes')) {
 echo '<div class="notice notice-error"><p>Sem permissão para bloquear mês.</p></div>';
 } elseif (!isset($_POST['_wpnonce_bloquear']) || !wp_verify_nonce($_POST['_wpnonce_bloquear'], 'sige_bloquear_mes')) {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>A sessão expirou por segurança. Recarregue a página e tente novamente.</p></div>';
 } else {
 try {
 $res = SIGE_FinanceActionService::bloquearMes(
 sige_fin_post_int('aluno_id'),
 sige_fin_post_int('mes_bloquear'),
 sige_fin_post_param('motivo_bloqueio', 'Mês sem cobrança'),
 (int)$ano_letivo,
 $escola_id
 );
 } catch (Throwable $e) {
 $res = ['ok' => false, 'error' => 'Falha técnica ao bloquear o mês: ' . $e->getMessage()];
 if (function_exists('sige_audit_log')) {
 sige_audit_log('bloquear_mes_falha_tecnica', ['erro' => $e->getMessage()], 'financeiro');
 }
 }
 if ($res['ok']) {
 $mes_nome = sige_fin_obter_nome_mes(sige_fin_post_int('mes_bloquear'));
 $motivo = sige_fin_post_param('motivo_bloqueio', 'Mês sem cobrança');
 echo '<div class="notice notice-success is-dismissible" style="padding:12px; border-radius:8px; margin-bottom:20px;"><strong>Mês bloqueado com sucesso.</strong><br><small>' . esc_html($mes_nome) . ' ' . esc_html((string)$ano_letivo) . ' - ' . esc_html($motivo) . '</small></div>';
 } else {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>' . esc_html($res['error']) . '</p></div>';
        if (function_exists('sige_mfa_render_challenge_form') && !empty($res['mfa_required'])) echo sige_mfa_render_challenge_form();
 }
 }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fin_desbloquear_mes_submit'])) {
 if (!sige_fin_user_can_write_12127('financeiro.desbloquear_mes')) {
 echo '<div class="notice notice-error"><p>Sem permissão para desbloquear mês.</p></div>';
 } elseif (!isset($_POST['_wpnonce_desbloquear']) || !wp_verify_nonce($_POST['_wpnonce_desbloquear'], 'sige_desbloquear_mes')) {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>A sessão expirou por segurança. Recarregue a página e tente novamente.</p></div>';
 } else {
 try {
 $res = SIGE_FinanceActionService::desbloquearMes(
 sige_fin_post_int('bloqueio_id'),
 $escola_id
 );
 } catch (Throwable $e) {
 $res = ['ok' => false, 'error' => 'Falha técnica ao desbloquear o mês: ' . $e->getMessage()];
 if (function_exists('sige_audit_log')) {
 sige_audit_log('desbloquear_mes_falha_tecnica', ['erro' => $e->getMessage()], 'financeiro');
 }
 }
 if ($res['ok']) {
 echo '<div class="notice notice-success is-dismissible" style="padding:12px; border-radius:8px; margin-bottom:20px;"><strong>Mês desbloqueado com sucesso.</strong></div>';
 } else {
 echo '<div class="notice notice-error" style="border-radius:8px;"><p>' . esc_html($res['error']) . '</p></div>';
        if (function_exists('sige_mfa_render_challenge_form') && !empty($res['mfa_required'])) echo sige_mfa_render_challenge_form();
 }
 }
}

// ==============================================================================
// POST: PAGAMENTO POR FAMÍLIA
// ==============================================================================
// Permite pagar lançamentos de múltiplos irmãos numa única transacção,
// gerando um único recibo consolidado para o encarregado.
// Os irmãos são detectados por correspondência de telemovel_pai/telemovel_mae.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_fin_pagar_familia_submit'])) {
 if (!sige_fin_user_can_write_12127('financeiro.pagar')) {
 echo '<div class="notice notice-error"><p>Sem permissão para registar pagamento familiar.</p></div>';
 } elseif (!isset($_POST['_wpnonce_familia']) || !wp_verify_nonce($_POST['_wpnonce_familia'], 'sige_fin_pagar_familia')) {
 echo '<div class="notice notice-error" style="border-radius:8px;margin:12px 0;"><p>A sessão expirou por segurança. Recarregue a página e tente novamente.</p></div>';
 } elseif (!((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_financeiro') || current_user_can('sige_secretario'))) {
 echo '<div class="notice notice-error" style="border-radius:8px;margin:12px 0;"><p>Sem permissão.</p></div>';
 } else {
 // Ler e validar inputs
 $fam_aluno_ref = sige_fin_post_int('aluno_id_familia_ref');
 $fam_metodo = sige_fin_post_param('fam_metodo_pagamento', 'numerario');
 $fam_ref_ext = sige_fin_post_param('fam_referencia_externa');
 $fam_multa_isenta = !empty($_POST['fam_multa_isenta']); // checkbox bool - intencional
 $fam_motivo_isenc = sige_fin_post_param('fam_motivo_isencao');
 // Ler lançamentos seleccionados: format "aluno_id:lancamento_id"
 $fam_lancs_raw = isset($_POST['familia_lancs']) && is_array($_POST['familia_lancs'])
 ? $_POST['familia_lancs'] : [];
 if (empty($fam_lancs_raw)) {
 echo '<div class="notice notice-warning" style="border-radius:8px;margin:12px 0;"><p>Seleccione pelo menos um lançamento.</p></div>';
 } else {
 // Normalizar: array de [aluno_id, lancamento_id]
 $fam_itens = [];
 foreach ($fam_lancs_raw as $raw) {
 $parts = explode(':', sanitize_text_field((string)$raw));
 if (count($parts) === 2) {
 $aid = (int)$parts[0];
 $lid = (int)$parts[1];
 if ($aid > 0 && $lid > 0) {
 $fam_itens[] = ['aluno_id' => $aid, 'lanc_id' => $lid];
 }
 }
 }
 if (empty($fam_itens)) {
 echo '<div class="notice notice-warning" style="border-radius:8px;margin:12px 0;"><p>Nenhum lançamento válido seleccionado.</p></div>';
 } else {
 // Gerar UM número de recibo para toda a família
 $fam_recibo = sige_fin_gerar_recibo_numero('FAM');

 // [v12.9.66] Capturar e validar data efectiva (família)
 $__sige_fam_data_efectiva = '';
 if (isset($_POST['fam_data_efectiva'])) {
     $__sige_fam_data_raw = sanitize_text_field((string)$_POST['fam_data_efectiva']);
     if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $__sige_fam_data_raw)) {
         $__sige_fam_data_efectiva = $__sige_fam_data_raw;
     }
 }
 $__sige_fam_hoje = current_time('Y-m-d');
 if ($__sige_fam_data_efectiva !== '' && $__sige_fam_data_efectiva !== $__sige_fam_hoje
     && function_exists('sige_fin_caixa_status_data')) {
     $__sige_fam_st = sige_fin_caixa_status_data($__sige_fam_data_efectiva, $escola_id);
     if (!$__sige_fam_st['aberta']) {
         echo '<div class="notice notice-error" style="border-left:4px solid var(--color-danger-600);padding:14px;background:var(--color-danger-50);margin:10px 0;">'
            . '<p style="margin:0;font-weight:600;color:var(--color-danger-700);">⚠️ Pagamento de família bloqueado - caixa fechada</p>'
            . '<p style="margin:6px 0 0 0;color:var(--color-danger-800);">' . esc_html($__sige_fam_st['mensagem']) . '</p>'
            . '</div>';
         return;
     }
 }

 $fam_pagos = 0;
 $fam_total = 0.0;
 $fam_erros = [];
 $fam_nomes = []; // alunos pagos (para a mensagem de sucesso)
 // [v13.6.0 BUG-CRIT-05] Pré-fetch dos nomes dos alunos numa única query
 // antes do loop. Substitui N SELECTs (um por pagamento confirmado).
 $_fam_aluno_ids = array_unique(array_map(fn($it) => (int)$it['aluno_id'], $fam_itens));
 $_fam_nomes_map = function_exists('sige_fin_batch_fetch_alunos_nomes')
 ? sige_fin_batch_fetch_alunos_nomes($_fam_aluno_ids)
 : [];
 foreach ($fam_itens as $item) {
 $l = $wpdb->get_row($wpdb->prepare(
 "SELECT * FROM $tL WHERE id=%d AND aluno_id=%d AND escola_id=%d",
 $item['lanc_id'], $item['aluno_id'], $escola_id
 ));
 if (!$l) {
 $fam_erros[] = "Lançamento #{$item['lanc_id']} não encontrado.";
 continue;
 }
 if (in_array($l->status, ['pago','cancelado','isento'], true)) {
 if ($l->status === 'pago') $fam_erros[] = "{$l->descricao} já pago.";
 continue;
 }
 if (function_exists('sige_fin_recalcular_lancamento')) {
 sige_fin_recalcular_e_sync((int)$l->id);
 $l = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tL WHERE id=%d AND escola_id=%d", $l->id, $escola_id));
 }
 $multa = $fam_multa_isenta ? 0.0 : (float)($l->valor_multa ?? 0);
 $total_div = ((float)$l->valor_original
 + (float)($l->valor_transporte ?? 0)
 + (float)($l->valor_extras ?? 0)
 + $multa)
 - (float)($l->valor_desconto ?? 0)
 - (float)($l->valor_desconto_especial ?? 0);
 $restante = max(0.0, $total_div - (float)($l->valor_pago ?? 0));
 if ($restante <= 0) continue;
 $res = sige_fin_registar_pagamento(
 (int)$l->id,
 $restante,
 $fam_metodo,
 $fam_ref_ext ?: null,
 $fam_multa_isenta,
 $fam_motivo_isenc ?: null,
 $fam_recibo // ← MESMO recibo para todos
 );
 if (is_wp_error($res)) {
 $fam_erros[] = $res->get_error_message();
 } else {
 $fam_pagos++;
 $fam_total += $restante;
 // Registar nome do aluno (sem duplicar) - do mapa pré-fetch
 $aluno_nome = $_fam_nomes_map[(int)$item['aluno_id']] ?? null;
 if (!$aluno_nome) {
 // Fallback legacy apenas se o mapa não tiver a entrada
 $aluno_nome = $wpdb->get_var($wpdb->prepare(
 "SELECT nome_completo FROM $tA WHERE id=%d AND escola_id=%d", $item['aluno_id'], $escola_id
 ));
 }
 if ($aluno_nome && !in_array($aluno_nome, $fam_nomes, true)) {
 $fam_nomes[] = $aluno_nome;
 }
 }
 }
 if ($fam_pagos > 0) {
 // [v12.9.66] Aplicar data efectiva ao recibo família
 if ($__sige_fam_data_efectiva !== '' && $__sige_fam_data_efectiva !== $__sige_fam_hoje
     && function_exists('sige_fin_aplicar_data_efectiva')) {
     $__sige_fam_de_res = sige_fin_aplicar_data_efectiva($fam_recibo, $__sige_fam_data_efectiva, $escola_id);
     if (!empty($__sige_fam_de_res['ok'])) {
         echo '<div class="notice notice-info" style="border-left:4px solid var(--color-info-400);padding:10px 14px;background:var(--sg-theme-soft,var(--color-brand-50));margin:10px 0;">'
            . '<p style="margin:0;color:var(--sg-theme-primary,var(--color-brand-500));font-size:13px;">📅 Data efectiva <strong>' . esc_html(wp_date('d/m/Y', strtotime($__sige_fam_data_efectiva))) . '</strong> aplicada - vai aparecer no extracto desse dia.</p>'
            . '</div>';
     }
 }
 $fam_nomes_str = implode(', ', array_map('esc_html', $fam_nomes));
 echo '<div id="sige-modal-familia-sucesso" class="sige-modal sg-paypro-modal sg-paypro-result-modal" style="display:flex;">';
 echo '<div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-fam-sucesso-title">';
 echo '<div class="sg-paypro-result-hero">';
 echo '<div class="sg-paypro-result-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:28px;height:28px;"><polyline points="20 6 9 17 4 12"/></svg></div>';
 echo '<h3 id="sige-fam-sucesso-title">Pagamento de família registado</h3>';
 echo '<p>Recibo: <strong>' . esc_html($fam_recibo) . '</strong> - ' . $fam_pagos . ' lançamento' . ($fam_pagos > 1 ? 's' : '') . ' - <strong>' . number_format($fam_total, 2, ',', '.') . ' ' . sige_moeda() . '</strong></p>';
 echo '<p style="margin-top:8px;font-size:0.9rem;color:var(--color-slate-700);">Alunos: ' . $fam_nomes_str . '</p>';
 echo '</div>';
 echo '<div class="sige-modal-footer"><button type="button" class="sige-btn sige-btn-primary" data-sige-act="sigeCloseModal" data-sige-arg="sige-modal-familia-sucesso">Continuar</button></div>';
 echo '</div></div>';
 // Log de auditoria
 if (function_exists('sige_audit_log')) {
 sige_audit_log('pagamento_familia', [
 'recibo' => $fam_recibo,
 'aluno_ref' => $fam_aluno_ref,
 'alunos' => $fam_nomes,
 'lancamentos' => count($fam_itens),
 'total' => $fam_total,
 'metodo' => $fam_metodo,
 ], 'financeiro');
 }
 } else {
 echo '<div class="notice notice-warning" style="border-radius:8px;margin:12px 0;"><p>Nenhum pagamento processado.</p></div>';
 }
 if (!empty($fam_erros)) {
 echo '<div class="notice notice-warning" style="border-radius:8px;margin:12px 0;"><p><strong>Avisos:</strong><br>';
 echo implode('<br>', array_map('esc_html', $fam_erros));
 echo '</p></div>';
 }
 }
 }
 }
}
// ==============================================================================
// 2) PREPARAÇÃO DA INTERFACE (GET)
// ==============================================================================

$aluno_id = sige_fin_get_int('aluno_id', (isset($_POST['aluno_id'])) ? (int)$_POST['aluno_id'] : 0);
$busca = sige_fin_get_param('q');

// [v12.9.23] Filtros operacionais na pesquisa de pagamentos.
// Nota técnica: estes filtros NÃO alteram nem recalculam fórmulas financeiras.
// O estado financeiro é apenas lido pela expressão canónica sige_fin_saldo_sql().
$filtro_status_fin = sanitize_key(sige_fin_get_param('status_fin'));
$filtro_turma_id = sige_fin_get_int('turma_id');
$filtro_classe = trim((string)sige_fin_get_param('classe'));

$status_fin_permitidos = ['em_divida', 'regular', 'com_credito'];
if (!in_array($filtro_status_fin, $status_fin_permitidos, true)) {
 $filtro_status_fin = '';
}

$_tM_busca = $wpdb->prefix . 'sige_matriculas';
$_tT_busca = $wpdb->prefix . 'sige_turmas';

$turmas_filtro = $wpdb->get_results($wpdb->prepare("
 SELECT id, nome, nome_turma, classe, nivel_ensino
 FROM {$_tT_busca}
 WHERE escola_id = %d AND (ano_lectivo IS NULL OR ano_lectivo = %d)
 ORDER BY classe ASC, nome ASC, nome_turma ASC
", (int)$escola_id, (int)$ano_letivo));

$classes_filtro = [];
foreach ((array)$turmas_filtro as $_tf) {
 $_classe = trim((string)($_tf->classe ?: $_tf->nivel_ensino));
 if ($_classe !== '') $classes_filtro[$_classe] = $_classe;
}
natcasesort($classes_filtro);

$alunos = [];
$tem_filtro_ativo = ($busca !== '' || $filtro_status_fin !== '' || $filtro_turma_id > 0 || $filtro_classe !== '');

if ($tem_filtro_ativo) {
 $where = ["a.escola_id = %d"];
 $params = [(int)$escola_id];

 if ($busca !== '') {
  $like = '%' . $wpdb->esc_like($busca) . '%';
  $where[] = "(a.nome_completo LIKE %s OR a.numero_processo LIKE %s OR a.contacto_encarregado LIKE %s OR a.whatsapp_notificacoes LIKE %s OR a.telemovel_pai LIKE %s OR a.telemovel_mae LIKE %s)";
  array_push($params, $like, $like, $like, $like, $like, $like);
 }

 if ($filtro_turma_id > 0) {
  $where[] = "m.turma_id = %d";
  $params[] = (int)$filtro_turma_id;
 }

 if ($filtro_classe !== '') {
  $where[] = "(t.classe = %s OR t.nivel_ensino = %s)";
  array_push($params, $filtro_classe, $filtro_classe);
 }

 if (!function_exists('sige_fin_saldo_sql')) {
  $alunos = [];
 } 
 $saldo_sql = function_exists('sige_fin_saldo_sql') ? sige_fin_saldo_sql('l') : "0";
 $credito_join = "";
 $credito_select = "0 AS saldo_credito";
 $credito_table = function_exists('sige_fin_creditos_table') ? sige_fin_creditos_table() : ($wpdb->prefix . 'sige_fin_creditos');
 $credito_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $credito_table));
 if ($credito_exists) {
  $credito_select = "COALESCE(cr.saldo_credito, 0) AS saldo_credito";
  $credito_join = "
  LEFT JOIN (
   SELECT aluno_id, COALESCE(SUM(valor_disponivel),0) AS saldo_credito
   FROM {$credito_table}
   WHERE escola_id = %d
     AND COALESCE(valor_disponivel,0) > 0
     AND COALESCE(status,'') NOT IN ('cancelado','cancelada','consumido','consumida','usado','usada')
   GROUP BY aluno_id
  ) cr ON cr.aluno_id = a.id";
 }

 if ($filtro_status_fin === 'em_divida') {
  $where[] = "COALESCE(fd.saldo_pendente,0) > 0.005";
 } elseif ($filtro_status_fin === 'regular') {
  $where[] = "COALESCE(fd.saldo_pendente,0) <= 0.005";
 } elseif ($filtro_status_fin === 'com_credito') {
  $where[] = $credito_exists ? "COALESCE(cr.saldo_credito,0) > 0.005" : "1=0";
 }

 $where_sql = implode(' AND ', $where);

 $sql = "
 SELECT a.id, a.nome_completo, a.numero_processo, a.foto, a.data_nascimento, a.genero,
        t.nome AS turma_nome, t.nome_turma, t.classe AS turma_classe, t.nivel_ensino,
        COALESCE(fd.saldo_pendente,0) AS saldo_pendente,
        {$credito_select}
 FROM {$tA} a
 LEFT JOIN {$_tM_busca} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id AND m.ano_lectivo = %d
 LEFT JOIN {$_tT_busca} t ON t.id = m.turma_id AND t.escola_id = a.escola_id
 LEFT JOIN (
  SELECT l.aluno_id, COALESCE(SUM({$saldo_sql}),0) AS saldo_pendente
  FROM {$tL} l
  WHERE l.escola_id = %d
    AND l.status NOT IN ('pago','cancelado','isento','em_plano')
  GROUP BY l.aluno_id
 ) fd ON fd.aluno_id = a.id
 {$credito_join}
 WHERE {$where_sql}
 GROUP BY a.id
 ORDER BY 
  CASE WHEN COALESCE(fd.saldo_pendente,0) > 0.005 THEN 0 ELSE 1 END,
  COALESCE(fd.saldo_pendente,0) DESC,
  a.nome_completo ASC
 LIMIT 50
 ";

 $prepare_params = [(int)$ano_letivo, (int)$escola_id];
 if ($credito_exists) $prepare_params[] = (int)$escola_id;
 $prepare_params = array_merge($prepare_params, $params);

 $alunos = $wpdb->get_results($wpdb->prepare($sql, $prepare_params));
}

$dividas = [];
$meses_futuros = [];
$outros_servicos = [];
$transporte_valor = 0.00;
$atividades_disponiveis = [];
$atividades_por_mes = [];
$aluno_row = null;
$classe_aluno = '';
$mensalidade_srv = null;
$transporte_srv = null;
$extras_srv = [];
$total_extras_previstos = 0.0;

if ($aluno_id) {
 $aluno_row = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tA WHERE id=%d AND escola_id=%d", $aluno_id, $escola_id));
 // [v12.11.9.88.1] Auto-cura leve: ao abrir pagamentos de um aluno já reactivado,
 // força a verificação/sincronização do status da matrícula antes de desenhar a UI.
 if ($aluno_row && function_exists('sige_status_operacional_activo') && sige_status_operacional_activo($aluno_row->status ?? '') && function_exists('sige_aluno_financeiramente_inactivo')) {
     sige_aluno_financeiramente_inactivo((int)$aluno_id, (int)$escola_id, (int)$ano_letivo);
 }
 $_tM1 = $wpdb->prefix . 'sige_matriculas';
 $_tT1 = $wpdb->prefix . 'sige_turmas';
 $classe_aluno = function_exists('sige_fin_obter_classe_actual_aluno') ? sige_fin_obter_classe_actual_aluno((int)$aluno_id, (int)$ano_letivo, (int)$escola_id) : '';
 $mensalidade_srv = sige_fin_get_servico_por_tipo_e_classe(
     'mensalidade', $classe_aluno, $escola_id,
     (string)($aluno_row->regime_mensalidade ?? '')
 );
 $transporte_srv = sige_fin_get_ou_criar_servico_transporte();

 if (!empty($aluno_row->regime_creche)) {
 $all_srv = function_exists('sige_fin_get_servicos_ativos_cached') ? sige_fin_get_servicos_ativos_cached((int)$escola_id) : $wpdb->get_results($wpdb->prepare("SELECT * FROM $tS WHERE ativo=1 AND escola_id=%d", $escola_id));
 $srv_c = sige_fin_get_servico_creche($aluno_row->regime_creche, $all_srv, $classe_aluno);
 if ($srv_c) $mensalidade_srv = $srv_c;
 }

 $tv = sige_transporte_get_preco_mensal($aluno_id);
 $transporte_valor = ($tv === null) ? 0.0 : (float)$tv;

 $extras_srv = sige_fin_get_servicos_extras_do_aluno($aluno_row, $classe_aluno);
 foreach ($extras_srv as $sx) $total_extras_previstos += (float)($sx->valor ?? 0);

 // Lançamentos accionáveis (pendentes/parcial) - podem ser pagos agora
 $dividas_raw = $wpdb->get_results($wpdb->prepare("
 SELECT l.*, COALESCE(s.nome, l.descricao, 'Serviço eliminado') as servico_nome
 FROM $tL l
 LEFT JOIN $tS s ON s.id = l.servico_id
 WHERE l.aluno_id = %d AND l.escola_id = %d AND l.status NOT IN ('pago', 'cancelado', 'isento', 'em_plano')
 ORDER BY l.data_vencimento ASC
 ", $aluno_id, $escola_id));
 // Lançamentos em plano negociado - mostrar mas não permitir pagamento directo
 $lancs_em_plano = $wpdb->get_results($wpdb->prepare("
 SELECT l.*, COALESCE(s.nome, l.descricao, 'Serviço eliminado') as servico_nome,
 pl.descricao AS plano_descricao, pl.id AS plano_id_ref
 FROM $tL l
 LEFT JOIN $tS s ON s.id = l.servico_id
 LEFT JOIN {$wpdb->prefix}sige_fin_planos_pagamento pl ON pl.id = l.plano_id
 WHERE l.aluno_id = %d AND l.escola_id = %d AND l.status = 'em_plano'
 ORDER BY l.data_vencimento ASC
 ", $aluno_id, $escola_id));

 // [v15.2.0 - F3] Fallback N+1 removido - batch fetch obrigatório.
 // 2 fases: (1) recalcular tudo, (2) batch refetch único, (3) merge.
 if (!empty($dividas_raw)) {
 // Fase 1: recálculo individual (altera BD, necessariamente 1-a-1)
 foreach ($dividas_raw as $d) {
 if (function_exists('sige_fin_recalcular_lancamento_throttled')) {
 sige_fin_recalcular_lancamento_throttled((int)$d->id, false, 21600);
 } else {
 sige_fin_recalcular_lancamento((int)$d->id);
 }
 }
 // Fase 2: batch refetch único
 $_divida_ids = array_map(fn($d) => (int)$d->id, $dividas_raw);
 $_fresh_map = sige_fin_batch_fetch_lancamentos($_divida_ids);
 // Fase 3: merge em memória
 foreach ($dividas_raw as $d) {
 $d_fresh = $_fresh_map[(int)$d->id] ?? null;
 if ($d_fresh) {
 $d->valor_transporte = (float)($d_fresh->valor_transporte ?? 0);
 $d->valor_extras = (float)($d_fresh->valor_extras ?? 0);
 $d->valor_multa = (float)($d_fresh->valor_multa ?? 0);
 $d->valor_desconto = (float)($d_fresh->valor_desconto ?? 0);
 $d->valor_desconto_especial = (float)($d_fresh->valor_desconto_especial ?? 0);
 $d->valor_pago = (float)($d_fresh->valor_pago ?? 0);
 $d->status = (string)($d_fresh->status ?? 'pendente');
 }
 $dividas[] = $d;
 }
 }

 $mapa_status = []; 
 $mapa_valores = []; 
 // [FIX #1b] Saldo e multa por serviço
 $mapa_saldo_srv = [];
 $mapa_multa_srv = [];
 $mapa_multa_mes = [];
 // [v12.12.64 HIST-CLASSE-01] Índices por tipo canónico.
 // O estado financeiro do mês é aluno+mês+tipo recorrente, não aluno+mês+servico_id actual.
 // Isto preserva mensalidades pagas com a classe anterior quando o aluno muda de classe.
 $mapa_tipo_stats = [];
 $mapa_tipo_saldo = [];
 $mapa_tipo_multa = [];
 $mapa_tipo_status_real = [];
 $meses_bloqueados = []; 

 $existentes = $wpdb->get_results($wpdb->prepare("
 SELECT l.servico_id, l.mes_referencia, l.status,
 l.valor_original, l.valor_pago, l.valor_multa,
 l.valor_desconto, COALESCE(l.valor_desconto_especial,0) AS valor_desconto_especial,
 l.valor_transporte, l.valor_extras,
 COALESCE(LOWER(TRIM(s.tipo)), '') AS servico_tipo,
 COALESCE(s.nome, l.descricao, '') AS servico_nome,
 COALESCE(l.descricao, '') AS lancamento_descricao
 FROM $tL l
 LEFT JOIN $tS s ON s.id = l.servico_id AND s.escola_id = l.escola_id
 WHERE l.escola_id=%d AND l.aluno_id=%d AND l.mes_referencia LIKE %s AND l.status != 'cancelado'
 ", $escola_id, $aluno_id, $ano_letivo . '-%'));

 $bloqueios_raw = $wpdb->get_results($wpdb->prepare("
 SELECT id, mes_referencia, motivo_cancelamento, cancelado_em
 FROM $tL
 WHERE escola_id=%d AND aluno_id=%d AND mes_referencia LIKE %s AND status='cancelado' AND valor_original=0 AND servico_id=0
 ", $escola_id, $aluno_id, $ano_letivo . '-%'));
 
 foreach ((array)$bloqueios_raw as $blq) {
 $parts = explode('-', (string)$blq->mes_referencia);
 if (count($parts) === 2) {
 $mes_num = (int)$parts[1];
 if ($mes_num >= 1 && $mes_num <= 12) {
 $meses_bloqueados[$mes_num] = ['id' => (int)$blq->id, 'motivo' => $blq->motivo_cancelamento, 'data' => $blq->cancelado_em];
 }
 }
 }

 foreach ((array)$existentes as $e) {
 $parts = explode('-', (string)$e->mes_referencia);
 if (count($parts) === 2) {
 $mes_num = (int)$parts[1];
 if ($mes_num >= 1 && $mes_num <= 12) {
 $mapa_status[$mes_num][(int)$e->servico_id] = (string)$e->status;
 $total_lanc = ((float)$e->valor_original + (float)($e->valor_transporte ?? 0)
 + (float)($e->valor_extras ?? 0) + (float)($e->valor_multa ?? 0))
 - (float)($e->valor_desconto ?? 0) - (float)($e->valor_desconto_especial ?? 0);
 $saldo_lanc = max(0.0, $total_lanc - (float)($e->valor_pago ?? 0));
 if (!isset($mapa_valores[$mes_num])) $mapa_valores[$mes_num] = ['total_devido' => 0.0, 'total_pago' => 0.0];
 $mapa_valores[$mes_num]['total_devido'] += max(0.0, $total_lanc);
 $mapa_valores[$mes_num]['total_pago'] += (float)($e->valor_pago ?? 0);
 // [FIX #1b] Saldo e multa por serviço
 $mapa_saldo_srv[$mes_num][(int)$e->servico_id] = $saldo_lanc;
 $mapa_multa_srv[$mes_num][(int)$e->servico_id] = (float)($e->valor_multa ?? 0);
 if (!isset($mapa_multa_mes[$mes_num])) $mapa_multa_mes[$mes_num] = 0.0;
 $mapa_multa_mes[$mes_num] += (float)($e->valor_multa ?? 0);

 // [v12.12.64 HIST-CLASSE-01] Índice por tipo canónico para histórico de mudança de classe.
 $tipo_raw = strtolower(trim((string)($e->servico_tipo ?? '')));
 $nome_desc = strtolower(trim((string)($e->servico_nome ?? '') . ' ' . (string)($e->lancamento_descricao ?? '')));
 if ($tipo_raw === '' && strpos($nome_desc, 'mensal') !== false) $tipo_raw = 'mensalidade';
 if ($tipo_raw === '' && strpos($nome_desc, 'transporte') !== false) $tipo_raw = 'transporte';
 $tipo_canon = function_exists('sige_fin_tipo_servico_canonico') ? sige_fin_tipo_servico_canonico($tipo_raw) : $tipo_raw;
 if (in_array($tipo_canon, ['mensalidade', 'transporte'], true)) {
     if (!isset($mapa_tipo_stats[$mes_num][$tipo_canon])) {
         $mapa_tipo_stats[$mes_num][$tipo_canon] = ['count' => 0, 'pago_isento' => 0, 'isento' => 0, 'pago' => 0, 'parcial' => 0, 'pendente' => 0];
     }
     $st_canon = strtolower(trim((string)$e->status));
     $mapa_tipo_stats[$mes_num][$tipo_canon]['count']++;
     if (in_array($st_canon, ['pago', 'isento'], true)) $mapa_tipo_stats[$mes_num][$tipo_canon]['pago_isento']++;
     if ($st_canon === 'isento') $mapa_tipo_stats[$mes_num][$tipo_canon]['isento']++;
     if ($st_canon === 'pago') $mapa_tipo_stats[$mes_num][$tipo_canon]['pago']++;
     if ($st_canon === 'parcial') $mapa_tipo_stats[$mes_num][$tipo_canon]['parcial']++;
     if ($st_canon === 'pendente') $mapa_tipo_stats[$mes_num][$tipo_canon]['pendente']++;
     if (!isset($mapa_tipo_saldo[$mes_num][$tipo_canon])) $mapa_tipo_saldo[$mes_num][$tipo_canon] = 0.0;
     if (!isset($mapa_tipo_multa[$mes_num][$tipo_canon])) $mapa_tipo_multa[$mes_num][$tipo_canon] = 0.0;
     $mapa_tipo_saldo[$mes_num][$tipo_canon] += $saldo_lanc;
     $mapa_tipo_multa[$mes_num][$tipo_canon] += (float)($e->valor_multa ?? 0);
     if (!isset($mapa_tipo_status_real[$mes_num][$tipo_canon])) $mapa_tipo_status_real[$mes_num][$tipo_canon] = $st_canon;
     if ($st_canon === 'pago') $mapa_tipo_status_real[$mes_num][$tipo_canon] = 'pago';
     elseif ($st_canon === 'isento' && $mapa_tipo_status_real[$mes_num][$tipo_canon] !== 'pago') $mapa_tipo_status_real[$mes_num][$tipo_canon] = 'isento';
 }
 }
 }
 }

 for ($m = 1; $m <= 12; $m++) {
 $mes_ref = $ano_letivo . '-' . str_pad((string)$m, 2, '0', STR_PAD_LEFT);
 if (!$mensalidade_srv) continue;

 if (isset($meses_bloqueados[$m])) {
 $meses_futuros[] = [
 'mes_num' => $m, 'mes_nome' => sige_fin_obter_nome_mes($m), 'total' => 0, 'pago' => false, 'isento' => false, 'parcial' => false, 'bloqueado' => true,
 'bloqueio_id' => $meses_bloqueados[$m]['id'], 'bloqueio_motivo' => $meses_bloqueados[$m]['motivo'], 'real_pago' => 0, 'real_falta' => 0, 'key_input' => '', 'detalhe' => []
 ];
 continue;
 }

 $mes_has_lancamentos = isset($mapa_valores[$m]);
 $mes_paid = false;
 $mes_all_isento = false;
 $mes_parcial = false;
 $real_devido = 0.0;
 $real_pago = 0.0;
 $real_falta = 0.0;
 
 if ($mes_has_lancamentos) {
 $real_devido = (float)$mapa_valores[$m]['total_devido'];
 $real_pago = (float)$mapa_valores[$m]['total_pago'];
 $real_falta = max(0.0, $real_devido - $real_pago);
 
 $todos_status_do_mes = $mapa_status[$m] ?? [];
 $todos_pagos_ou_isentos = true;
 $todos_isentos = !empty($todos_status_do_mes);
 
 foreach ($todos_status_do_mes as $srv_id => $status) {
 if ($status !== 'pago' && $status !== 'isento') $todos_pagos_ou_isentos = false;
 if ($status !== 'isento') $todos_isentos = false;
 }
 if ($real_falta <= 0.01) $todos_pagos_ou_isentos = true;
 
 $mes_paid = $todos_pagos_ou_isentos;
 // [v12.12.64 HIST-CLASSE-01] Mês só exige que exista mensalidade/transporte
 // do tipo canónico no mês, não obrigatoriamente o servico_id da classe actual.
 // Antes, mudar aluno da 4ª para a 2ª fazia Jan-Abr pagos como 4ª reaparecerem
 // como dívida porque o UI procurava apenas o serviço actual da 2ª.
 $tem_mensalidade_no_mes = !empty($mapa_tipo_stats[$m]['mensalidade']['count'])
     || ($mensalidade_srv && isset($mapa_status[$m][(int)$mensalidade_srv->id]));
 $tem_transporte_no_mes = !empty($mapa_tipo_stats[$m]['transporte']['count'])
     || ($transporte_srv && isset($mapa_status[$m][(int)$transporte_srv->id]));

 if ($mes_paid && $mensalidade_srv && !$tem_mensalidade_no_mes) {
 $mes_paid = false;
 }
 // Se transporte existe no perfil mas não foi lançado em nenhum serviço de transporte, não está pago.
 if ($mes_paid && $transporte_srv && $transporte_valor > 0 && !$tem_transporte_no_mes) {
 $mes_paid = false;
 }
 $mes_all_isento = $todos_isentos && $todos_pagos_ou_isentos;
 $mes_parcial = (!$mes_paid && $real_pago > 0 && $real_falta > 0);
 } 

 if ($mes_paid && $mes_all_isento) {
 $meses_futuros[] = ['mes_num' => $m, 'mes_nome' => sige_fin_obter_nome_mes($m), 'total' => 0, 'pago' => false, 'isento' => true, 'parcial' => false, 'real_pago' => 0, 'real_falta' => 0, 'key_input' => 'PACK_' . str_pad((string)$m, 2, '0', STR_PAD_LEFT), 'detalhe' => []];
 continue;
 }
 if ($mes_paid) {
 $meses_futuros[] = ['mes_num' => $m, 'mes_nome' => sige_fin_obter_nome_mes($m), 'total' => 0, 'pago' => true, 'parcial' => false, 'real_pago' => $real_pago, 'real_falta' => 0, 'key_input' => 'PACK_' . str_pad((string)$m, 2, '0', STR_PAD_LEFT), 'detalhe' => []];
 continue;
 }

 // [v12.12.64 HIST-CLASSE-01] Usar valores reais também quando o lançamento
 // pertence a um serviço de mensalidade/transporte da classe anterior.
 $_mid = $mensalidade_srv ? (int)$mensalidade_srv->id : 0;
 $_tid = $transporte_srv ? (int)$transporte_srv->id : 0;
 $tem_saldo_mens_real = isset($mapa_saldo_srv[$m][$_mid]) || isset($mapa_tipo_saldo[$m]['mensalidade']);
 $tem_saldo_trans_real = isset($mapa_saldo_srv[$m][$_tid]) || isset($mapa_tipo_saldo[$m]['transporte']);
 $usar_valores_reais = ($mes_has_lancamentos && $real_falta > 0 && ($tem_saldo_mens_real || $tem_saldo_trans_real));
 if ($usar_valores_reais) {
 $display_total = $real_falta;
 $valor_mens = (float)($mapa_saldo_srv[$m][$_mid] ?? ($mapa_tipo_saldo[$m]['mensalidade'] ?? 0));
 $valor_trans = (float)($mapa_saldo_srv[$m][$_tid] ?? ($mapa_tipo_saldo[$m]['transporte'] ?? 0));
 $valor_extras = 0; $desc_m = 0; $desc_t = 0; $desc_x = 0;
 $multa_mens_preview = (float)($mapa_multa_srv[$m][$_mid] ?? ($mapa_tipo_multa[$m]['mensalidade'] ?? 0));
 $multa_trans_preview = (float)($mapa_multa_srv[$m][$_tid] ?? ($mapa_tipo_multa[$m]['transporte'] ?? 0));
 $multa_preview = (float)($mapa_multa_mes[$m] ?? ($multa_mens_preview + $multa_trans_preview));
 } else {
 $valor_mens = (float)($mensalidade_srv->valor ?? 0);
 if (!empty($aluno_row->mensalidade_base) && (float)$aluno_row->mensalidade_base > 0) $valor_mens = (float)$aluno_row->mensalidade_base;
 $valor_trans = (float)$transporte_valor;
 $valor_extras = (float)$total_extras_previstos;

 $desc_m = 0.0; $desc_t = 0.0; $desc_x = 0.0;
 // [FIX GUARD-01] Verificação explícita em vez de guard silencioso.
 // Se finance-core.php não estiver carregado, o sistema falha de forma
 // audível (log + aviso na UI) em vez de criar lançamentos sem desconto
 // silenciosamente - o que passaria despercebido até um encarregado reclamar.
 if (!function_exists('sige_fin_calcular_desconto')) {
 error_log(
 '[SIGE FIN] CRÍTICO: sige_fin_calcular_desconto() não está disponível. '
 . 'Verificar require de includes/finance-core.php no plugin principal. '
 . 'Lançamentos gerados nesta sessão NÃO terão descontos aplicados.'
 );
 echo '<div class="notice notice-error" style="margin:10px 0;">'
 . '<p><strong>[SIGE]</strong> Módulo de descontos não carregado. '
 . 'Contacte o administrador antes de gerar lançamentos.</p></div>';
 } else {
 $desc_m = (float)sige_fin_calcular_desconto(
 $aluno_id, $config_fin, $mensalidade_srv,
 ['valor_original' => $valor_mens]
 );
 if ($valor_trans > 0 && $transporte_srv) {
 $desc_t = (float)sige_fin_calcular_desconto(
 $aluno_id, $config_fin, $transporte_srv,
 ['valor_original' => $valor_trans]
 );
 }
 foreach ($extras_srv as $sx) {
 $desc_x += (float)sige_fin_calcular_desconto(
 $aluno_id, $config_fin, $sx,
 ['valor_original' => (float)$sx->valor]
 );
 }
 }

 $dia_venc_cfg = (int)($config_fin->dia_vencimento_mensalidade ?? $config_fin->prazo_vencimento ?? 5);
 $ts_prev = strtotime($mes_ref . '-01');
 $data_venc_preview = sige_mz_date('Y-m', $ts_prev) . '-' . str_pad((string)$dia_venc_cfg, 2, '0', STR_PAD_LEFT);
 $multa_mens_preview = 0.0;
 $multa_trans_preview = 0.0;
 $multa_preview = 0.0;
 if (function_exists('sige_fin_calcular_multa')) {
 $multa_mens_preview = (float)sige_fin_calcular_multa(['data_vencimento' => $data_venc_preview, 'valor_original' => $valor_mens], $config_fin, $mensalidade_srv);
 if ($valor_trans > 0 && $transporte_srv) {
 $multa_trans_preview = (float)sige_fin_calcular_multa(['data_vencimento' => $data_venc_preview, 'valor_original' => $valor_trans], $config_fin, $transporte_srv);
 }
 $multa_preview = $multa_mens_preview + $multa_trans_preview;
 }
 $total_preview = max(0, ($valor_mens + $valor_trans + $valor_extras + $multa_preview) - ($desc_m + $desc_t + $desc_x));
 $display_total = $total_preview;
 }

 $mens_status_real = $mapa_status[$m][(int)$mensalidade_srv->id] ?? ($mapa_tipo_status_real[$m]['mensalidade'] ?? null);
 $trans_status_real = ($transporte_valor > 0 && $transporte_srv) ? ($mapa_status[$m][(int)$transporte_srv->id] ?? ($mapa_tipo_status_real[$m]['transporte'] ?? null)) : null;

 $meses_futuros[] = [
 'mes_num' => $m, 'mes_nome' => sige_fin_obter_nome_mes($m), 'total' => $display_total, 'pago' => false, 'parcial' => $mes_parcial, 'real_pago' => $real_pago, 'real_falta' => $real_falta,
 'key_input' => 'PACK_' . str_pad((string)$m, 2, '0', STR_PAD_LEFT),
 'detalhe' => [
 'mensalidade' => $valor_mens, 'transporte' => $valor_trans, 'extras' => $valor_extras, 'desconto' => ($desc_m + $desc_t + $desc_x), 'desconto_mens' => $desc_m, 'desconto_trans'=> $desc_t,
 'multa' => $multa_preview, 'multa_mensalidade' => $multa_mens_preview, 'multa_transporte' => $multa_trans_preview, 'multa_inclusa' => $usar_valores_reais, 'mens_pago' => ($mens_status_real === 'pago'), 'mens_isento' => ($mens_status_real === 'isento'), 'trans_pago' => ($trans_status_real === 'pago'), 'trans_isento'=> ($trans_status_real === 'isento'),
 ]
 ];
 }

 $outros_servicos = sige_fin_get_servicos_nao_fixos_para_classe($classe_aluno);
 $outros_registo = sige_fin_get_servicos_registo_avulsos_do_aluno($aluno_row, $classe_aluno);
 foreach ((array)$outros_registo as $_srv_reg) {
 if (!isset($outros_servicos[(int)$_srv_reg->id])) {
 $outros_servicos[] = $_srv_reg;
 }
 }
 $avulsos_ja_cobrados = [];
 if (!empty($outros_servicos)) {
 $_nf_ids = array_map(function($s){ return (int)$s->id; }, $outros_servicos);
 $_nf_in = implode(",", $_nf_ids);
 $_nf_rows = $wpdb->get_results($wpdb->prepare("SELECT servico_id, status, valor_pago FROM {$tL} WHERE aluno_id=%d AND servico_id IN ({$_nf_in}) AND status IN ('pago','pendente','parcial') AND escola_id=%d", $aluno_id, $escola_id));
 foreach ((array)$_nf_rows as $_nr) $avulsos_ja_cobrados[(int)$_nr->servico_id] = $_nr->status;
 }

 // Normalizar lista de avulsos após merge para evitar duplicados visuais.
 if (!empty($outros_servicos)) {
 $_map_outros = [];
 foreach ((array)$outros_servicos as $_os) $_map_outros[(int)$_os->id] = $_os;
 $outros_servicos = array_values($_map_outros);
 }

 $atividades_disponiveis = sige_fin_get_servicos_atividades_disponiveis($classe_aluno);
 $atividades_por_mes = [];
 if (!empty($atividades_disponiveis)) {
 $_ativ_ids = array_map(function($s){ return (int)$s->id; }, $atividades_disponiveis);
 $_ativ_in = implode(",", $_ativ_ids);
 $_ativ_rows = $wpdb->get_results($wpdb->prepare("SELECT servico_id, mes_referencia, status FROM {$tL} WHERE aluno_id=%d AND servico_id IN ({$_ativ_in}) AND status IN ('pago','pendente','parcial') AND escola_id=%d ORDER BY mes_referencia ASC", $aluno_id, $escola_id));
 foreach ((array)$_ativ_rows as $_ar) {
 $mes_num = (int)substr($_ar->mes_referencia, 5, 2);
 if (!isset($atividades_por_mes[$mes_num])) $atividades_por_mes[$mes_num] = [];
 $atividades_por_mes[$mes_num][(int)$_ar->servico_id] = $_ar->status;
 }
 }
}
// ── Detecção de irmãos (Pagamento por Família) ─────────────────────────────
// [v15.2.0 - F6] Substituído o REGEXP_REPLACE (full scan) por JOIN via
// familia_id (index lookup). O SIGE_FinanceFamiliaService também tem
// self-healing: se o aluno ainda não tem familia_id (pós-backfill M20
// incompleto ou aluno criado depois), faz fallback para a detecção por
// telefone E aproveita para atribuir familia_id - na próxima consulta
// já usa o index.
// [v15.2.0 - F3] Removido o fallback N+1 `else` - sige_fin_batch_fetch_*
// é obrigatório desde v15.1.0 (carregado no bootstrap, fin-core sempre presente).
$irmaos_da_familia = [];
if ($aluno_id && $aluno_row) {
 $irmaos_rows = SIGE_FinanceFamiliaService::buscarIrmaos($aluno_id, $escola_id);

 foreach ($irmaos_rows as $irmao) {
 $lancs_irmao = $wpdb->get_results($wpdb->prepare(
 "SELECT l.*, COALESCE(s.nome, l.descricao, 'Serviço eliminado') as servico_nome
 FROM $tL l
 LEFT JOIN $tS s ON s.id = l.servico_id
 WHERE l.aluno_id = %d
 AND l.escola_id = %d
 AND l.status NOT IN ('pago','cancelado','isento')
 ORDER BY l.data_vencimento ASC",
 $irmao->id, $escola_id
 ));
 if (empty($lancs_irmao)) continue;

 $total_irmao = 0.0;
 // Fase 1: recálculo individual (altera BD, necessariamente 1-a-1)
 foreach ($lancs_irmao as $_li) {
 if (function_exists('sige_fin_recalcular_lancamento_throttled')) {
 sige_fin_recalcular_lancamento_throttled((int)$_li->id, false, 21600);
 if (function_exists('sige_fin_atualizar_status_lancamento')) sige_fin_atualizar_status_lancamento((int)$_li->id);
 } else {
 sige_fin_recalcular_e_sync((int)$_li->id);
 }
 }
 // Fase 2: batch refetch único
 $_li_ids = array_map(fn($x) => (int)$x->id, $lancs_irmao);
 $_li_fresh = sige_fin_batch_fetch_lancamentos($_li_ids);
 // Fase 3: merge em memória + cálculo de restante via fórmula canónica
 foreach ($lancs_irmao as &$li) {
 $fresh = $_li_fresh[(int)$li->id] ?? null;
 if ($fresh) {
 $li->valor_multa = $fresh->valor_multa;
 $li->valor_desconto = $fresh->valor_desconto;
 $li->valor_desconto_especial = $fresh->valor_desconto_especial;
 $li->valor_pago = $fresh->valor_pago;
 $li->valor_transporte = $fresh->valor_transporte ?? 0;
 $li->valor_extras = $fresh->valor_extras ?? 0;
 }
 // [v15.2.0 - F8] Usar fórmula canónica em vez de inline
 $rest_i = sige_fin_saldo_lancamento($li);
 $li->restante = $rest_i;
 $total_irmao += $rest_i;
 }
 unset($li);

 if ($total_irmao > 0) {
 $irmaos_da_familia[] = [
 'aluno' => $irmao,
 'lancamentos' => $lancs_irmao,
 'total' => $total_irmao,
 ];
 }
 }
}

?>

<style>
/* ========================================
 SIGE FINANCEIRO - DESIGN SYSTEM v2.0
 ======================================== */

:root {
 --sige-font-display: 'Plus Jakarta Sans', system-ui, sans-serif;
 --sige-font-body: 'Inter', system-ui, sans-serif;
 --sige-navy: var(--color-info-900); --sige-navy-light: var(--sg-theme-primary-800,var(--color-ink-700));
 --sige-primary: var(--sg-theme-primary,var(--color-brand-500)); --sige-primary-light: var(--color-ink-600); --sige-primary-dark: var(--color-info-700);
 --sige-slate-50: var(--color-slate-50); --sige-slate-100: var(--color-ink-50); --sige-slate-200: var(--color-ink-100);
 --sige-slate-300: var(--color-ink-200); --sige-slate-400: var(--color-slate-400); --sige-slate-500: var(--color-slate-500);
 --sige-slate-600: var(--color-slate-700); --sige-slate-700: var(--color-slate-800); --sige-slate-800: var(--color-ink-900); --sige-slate-900: var(--color-black);
 --sige-success: var(--color-success-700); --sige-success-light: var(--color-success-100);
 --sige-warning: var(--color-warning-500); --sige-warning-light: var(--color-warning-100);
 --sige-error: var(--color-danger-500); --sige-error-light: var(--color-danger-50);
 --sige-info: var(--sg-theme-primary,var(--color-brand-500)); --sige-info-light: var(--sg-theme-soft,var(--color-brand-50));
 --sige-amber: var(--color-warning-500); --sige-orange: var(--color-warning-600);
 
 --sige-shadow-sm: 0 1px 2px rgba(13,18,89,0.04);
 --sige-shadow: 0 4px 12px rgba(13,18,89,0.08);
 --sige-shadow-lg: 0 12px 32px rgba(13,18,89,0.12);
 --sige-shadow-xl: 0 20px 48px rgba(13,18,89,0.16);
 
 --sige-radius: 12px; --sige-radius-lg: 16px; --sige-radius-xl: 20px; --sige-radius-2xl: 24px;
}

.sige-page { font-family: var(--sige-font-body); color: var(--sige-slate-800); background: var(--sige-slate-50); min-height: 100vh; padding:var(--space-6); box-sizing: border-box; }
.sige-page * { box-sizing: border-box; }

@keyframes fadeInUp { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
@keyframes sigeModalSlideIn { from { opacity: 0; transform: translateY(-14px) scale(0.98); } to { opacity: 1; transform: translateY(0) scale(1); } }

/* Hero */
.sige-hero { animation: fadeInUp 0.5s ease-out both; position: relative; overflow: hidden; border-radius: var(--sige-radius-2xl); padding:var(--space-8); margin-bottom: 24px; background: linear-gradient(135deg, var(--sige-navy) 0%, var(--sige-navy-light) 50%, var(--sige-primary-dark) 100%); color: var(--color-white); box-shadow:var(--shadow-xs); }
.sige-hero::before { content: ""; position: absolute; border-radius: 50%; pointer-events: none; width: 350px; height: 350px; right: -120px; top: -120px; background: radial-gradient(circle, rgba(63, 81, 181, 0.15) 0%, transparent 70%); }
.sige-hero h1 { font-family: var(--sige-font-display); font-size: clamp(1.75rem, 3vw, 2.25rem); font-weight:700; line-height: 1.1; margin:0 0 var(--space-2); color: var(--color-white); }
.sige-hero p { font-size: 0.95rem; color: rgba(255,255,255,0.8); margin: 0; }

/* Cards */
.sige-card { background: var(--color-white); border-radius: var(--sige-radius-lg); border: 1px solid var(--sige-slate-100); box-shadow:var(--shadow-xs); margin-bottom: 24px; animation: fadeInUp 0.5s ease-out 0.1s both; }
.sige-card-header { padding:var(--space-5) var(--space-6); border-bottom: 1px solid var(--sige-slate-100); }
.sige-card-body { padding:var(--space-6); }
.sige-card-title { font-family: var(--sige-font-display); font-size: 1.15rem; font-weight:700; color: var(--sige-slate-900); margin:0 0 var(--space-1) 0; display:flex; align-items:center; gap:var(--space-2);}
.sige-card-title svg { width:20px; height:20px; }

/* Forms & Buttons */
.sige-form-group { display: flex; flex-direction: column; gap: 6px; margin-bottom: 16px; }
.sige-label { font-size: 0.75rem; font-weight:600; text-transform: uppercase; letter-spacing: 0.04em; color: var(--sige-slate-600); }
.sige-input, .sige-select { height: 44px; padding: 0 14px; border: 1px solid var(--sige-slate-200); border-radius: var(--sige-radius); font-family: var(--sige-font-body); font-size: 0.9rem; color: var(--sige-slate-800); transition: all var(--duration-normal) ease; background: var(--color-white); }
.sige-input:focus, .sige-select:focus { outline: none; border-color: var(--sige-primary); box-shadow:var(--shadow-xs); }
.sige-btn { display: inline-flex; align-items: center; justify-content: center; gap:var(--space-2); padding: 0 18px; height: 44px; border-radius: var(--sige-radius); font-family: var(--sige-font-body); font-weight:600; font-size: 0.875rem; cursor: pointer; transition: all var(--duration-normal) ease; border: none; text-decoration: none; }
.sige-btn-primary { background: linear-gradient(135deg, var(--sige-primary), var(--sige-primary-dark)); color: var(--color-white); box-shadow:var(--shadow-xs); }
.sige-btn-primary:hover { transform: translateY(-2px); box-shadow:var(--shadow-sm); color:var(--color-white); }
.sige-btn-ghost { background: var(--sige-slate-50); color: var(--sige-slate-700); border: 1px solid var(--sige-slate-200); }
.sige-btn-ghost:hover { background: var(--sige-slate-100); border-color: var(--sige-slate-300); }
.sige-btn-error { background: var(--sige-error-light); color: var(--sige-error); }
.sige-btn-error:hover { background: var(--sige-error); color: var(--color-white); }

/* Student Search Results (High Sensitivity) */
.sige-students-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap:var(--space-4); }
.sige-student-card { background: var(--color-white); border: 1px solid var(--sige-slate-200); border-radius: var(--sige-radius-xl); padding:var(--space-4); display: flex; align-items: center; gap:var(--space-4); text-decoration: none; transition: all var(--duration-normal) cubic-bezier(0.25, 0.8, 0.25, 1); box-shadow:var(--shadow-xs); position: relative; overflow: hidden; cursor: pointer; }
.sige-student-card:hover { transform: translateY(-4px) scale(1.02); box-shadow:var(--shadow-xs); border-color: var(--sige-primary-light); background: linear-gradient(to right, var(--color-white), var(--sige-slate-50)); }
.sige-student-card:active { transform: translateY(0) scale(0.98); box-shadow:var(--shadow-xs); }
.sige-student-card::after { content: ''; position: absolute; inset: 0; background: radial-gradient(circle at center, rgba(63,81,181,0.05) 0%, transparent 100%); opacity: 0; transition: opacity 0.3s ease; pointer-events: none; }
.sige-student-card:hover::after { opacity: 1; }
a.sige-student-card * { pointer-events: none; /* Só nos cartões-link de pesquisa; não bloqueia controlos de formulários. */ }
.sige-student-avatar { width: 52px; height: 52px; border-radius: 50%; background: linear-gradient(135deg, var(--sige-primary-100), var(--sige-primary-200)); display: flex; align-items: center; justify-content: center; flex-shrink: 0; border: 2px solid var(--sige-primary-200); transition: border-color var(--duration-normal); overflow: hidden; }
.sige-student-card:hover .sige-student-avatar { border-color: var(--sige-primary-400); }
.sige-student-avatar img { width: 100%; height: 100%; object-fit: cover; }
.sige-avatar-initials { font-size: 1.1rem; font-weight:700; color: var(--sige-primary-700); }
.sige-student-info { flex: 1; min-width: 0; }
.sige-student-name { font-size: 1rem; font-weight:600; color: var(--sige-slate-900); margin:0 0 var(--space-1); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: color var(--duration-normal); }
.sige-student-card:hover .sige-student-name { color: var(--sige-primary-700); }
.sige-student-meta { display: flex; gap: 6px; align-items: center; margin: 0; font-size: 0.75rem; color: var(--sige-slate-500); }
.sige-badge { padding:var(--space-1) var(--space-2); border-radius:var(--radius-xs); font-weight:600; font-size: 0.7rem; }
.sige-badge-muted { background: var(--sige-slate-100); color: var(--sige-slate-600); }
.sige-badge-primary { background: var(--sige-primary-50); color: var(--sige-primary-700); }

/* Table */
.sige-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.sige-table th { background: var(--sige-slate-900); color: var(--color-white); font-weight:600; text-transform: uppercase; font-size: 0.75rem; letter-spacing: 0.04em; padding: 12px 14px; text-align: left; position: sticky; top: 0; z-index: 2; }
.sige-table td { padding: 12px 14px; border-bottom: 1px solid var(--sige-slate-100); vertical-align: middle; }
.sige-table tr:nth-child(even) { background: var(--sige-slate-50); }
.sige-table tr:hover { background: var(--sige-primary-50); }

/* Month Grid */
.sige-months-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap:var(--space-4); }
.sige-month-card { background: var(--color-white); border-radius: var(--sige-radius-lg); border: 1px solid var(--sige-slate-200); box-shadow:var(--shadow-xs); overflow: hidden; display: flex; flex-direction: column; transition: all var(--duration-normal) ease; }
.sige-month-blocked { border-color: var(--sige-slate-300); opacity: 0.75; }
.sige-month-exempt { border-color: var(--sige-info-300); border-top: 4px solid var(--sige-info); }
.sige-month-paid { border-color: var(--sige-success-300); border-top: 4px solid var(--sige-success); }
.sige-month-partial { border-color: var(--sige-warning-300); border-top: 4px solid var(--sige-warning); }
.sige-month-overdue { border-color: var(--sige-error-300); border-top: 4px solid var(--sige-error); }
.sige-month-header { background: var(--sige-slate-50); padding:var(--space-3) var(--space-4); border-bottom: 1px solid var(--sige-slate-100); font-weight:600; display: flex; justify-content: space-between; align-items: center; font-size: 0.85rem; }
.sige-month-item { padding: 10px 16px; display: flex; align-items: center; gap: 10px; border-bottom: 1px solid var(--sige-slate-50); cursor: pointer; transition: background var(--duration-normal); font-size: 0.78rem; }
.sige-month-item:hover { background: var(--sige-slate-50); }
.sige-month-item-paid { background: var(--sige-success-50); cursor: default; }
.sige-month-item-exempt { background: var(--sige-info-50); cursor: default; }
.sige-month-item-pending { background: var(--sige-warning-50); cursor: not-allowed; opacity: 0.75; border: 1px dashed var(--sige-warning); }

/* Checkout Area */
.sige-payment-summary { background: var(--sige-slate-50); border-top: 4px solid var(--sige-primary-900); }
.sige-payment-grid { display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap:var(--space-6); }
.sige-payment-total { text-align: right; min-width: 250px; }
.sige-total-value { font-size: 2.2rem; font-weight:700; color: var(--sige-primary-900); margin:var(--space-2) 0; font-family: var(--sige-font-display); }

/* Modals */
.sige-modal { position: fixed !important; top: 0 !important; right: 0 !important; bottom: 0 !important; left: 0 !important; width: 100vw !important; height: 100vh !important; min-height: 100vh !important; background: rgba(15, 23, 42, 0.62) !important; backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 999999 !important; display: none; align-items: center !important; justify-content: center !important; padding:var(--space-5)!important; box-sizing: border-box !important; overflow-y: auto !important; }
.sige-modal-content { width: 100%; max-width: 500px; background: var(--color-white); border-radius: var(--sige-radius-xl); box-shadow:var(--shadow-xs); animation: sigeModalSlideIn 0.25s ease; overflow: hidden; display: flex; flex-direction: column; }
.sige-modal-header { padding:var(--space-5) var(--space-6); background: var(--sige-slate-50); border-bottom: 1px solid var(--sige-slate-100); display: flex; justify-content: space-between; align-items: center; }
.sige-modal-header h3 { margin: 0; font-family: var(--sige-font-display); font-size: 1.15rem; font-weight:600; display:flex; align-items:center; gap:var(--space-2);}
.sige-modal-body { padding:var(--space-6); }
.sige-modal-footer { padding:var(--space-4) var(--space-6); background: var(--sige-slate-50); border-top: 1px solid var(--sige-slate-100); display: flex; justify-content: flex-end; gap:var(--space-3); }

/* Utils */
.sige-text-right { text-align: right; }
.sige-font-mono { font-family: monospace; }
.sige-text-success { color: var(--sige-success); }
.sige-checkbox { width: 18px; height: 18px; accent-color: var(--sige-primary); cursor: pointer; }
.sige-flex { display: flex; }
.sige-gap-3 { gap:var(--space-3); }
.sige-mt-3 { margin-top: 12px; }
.sige-w-full { width: 100%; }
/* Extras styling */
.sige-extras-section { background: var(--sige-slate-50); border-top: 1px dashed var(--sige-slate-200); padding: 10px 16px; }
.sige-extras-title { font-size: 0.7rem; font-weight:600; color: var(--sige-slate-500); text-transform: uppercase; letter-spacing: 0.05em; margin-bottom: 8px; }
.sige-extra-item { display: flex; align-items: center; gap:var(--space-2); padding: 6px 0; font-size: 0.75rem; }
.sige-extra-item label { display: flex; align-items: center; gap:var(--space-2); cursor: pointer; flex: 1; }
.sige-extra-name { color: var(--sige-slate-700); font-weight:500; }
.sige-extra-value { color: var(--sige-primary-light); font-weight:600; margin-left: auto; }

/* FIX: Notices com texto invisível (branco sobre branco) */
.sige-page .notice, .wrap .notice { color: var(--sige-slate-800) !important; }
.sige-page .notice strong { color: var(--sige-slate-900); }
.sige-page .notice a { color: var(--sige-primary); }
.sige-page .notice small { color: var(--sige-slate-600); }
.sige-page .notice p { color: var(--sige-slate-800); }

/* =============================================================================
   SoftGenial v12.10.11 - Registar Pagamento: modal centrado e confirmação limpa
   Camada visual/UX. Não altera fórmulas, queries, lançamentos ou regras.
   ============================================================================= */
.sg-paypro-wrap{background:linear-gradient(180deg,var(--color-slate-50) 0%,var(--color-white) 48%,var(--color-slate-50) 100%);padding:0!important;color:var(--color-black);max-width:none!important;width:100%;}
.sg-paypro-wrap .sige-card{border:1px solid rgba(31,32,55,.06)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);background:var(--color-white)!important;overflow:hidden;}
.sg-paypro-wrap .sige-card-header{padding:22px 24px!important;border-bottom:1px solid var(--color-slate-100)!important;background:linear-gradient(180deg,var(--color-white),var(--color-white))!important;}
.sg-paypro-wrap .sige-card-body{padding:var(--space-6)!important;}
.sg-paypro-wrap svg{stroke:currentColor;fill:none;}
.sg-paypro-hero{position:relative;display:grid;grid-template-columns:minmax(0,1.55fr) minmax(340px,.72fr);gap:var(--space-6);align-items:stretch;margin:0 0 22px!important;padding:var(--space-8)!important;border-radius:var(--radius-xl)!important;background:radial-gradient(circle at 86% 15%,rgba(255,255,255,.22),transparent 26%),linear-gradient(135deg,var(--color-brand-800) 0%,var(--color-brand-500) 45%,var(--color-brand-400) 100%)!important;box-shadow:var(--shadow-lg);color:var(--color-white)!important;min-height:214px;}
.sg-paypro-hero:before{content:"";position:absolute;right:-72px;bottom:-86px;width:260px;height:230px;border-radius:var(--radius-pill);background:rgba(255,255,255,.13);}
.sg-paypro-hero:after{content:"";position:absolute;right:31%;top:-80px;width:170px;height:170px;border-radius:var(--radius-pill);background:rgba(255,255,255,.08);}
.sg-paypro-hero-content,.sg-paypro-hero-panel{position:relative;z-index:1;}
.sg-paypro-kicker{display:inline-flex;align-items:center;gap:9px;margin:0 0 14px;padding:var(--space-2) var(--space-3);border-radius:var(--radius-pill);background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.12em;color:var(--color-white);}
.sg-paypro-kicker:before{display:none!important;content:none!important;}
.sg-paypro-hero h1{font-size:clamp(31px,3.5vw,48px)!important;line-height:1.04!important;letter-spacing:-.055em!important;font-weight:700!important;margin:0 0 var(--space-3)!important;color:var(--color-white)!important;max-width:820px;}
.sg-paypro-hero p{font-size:15px!important;line-height:1.6!important;color:rgba(255,255,255,.82)!important;max-width:720px;margin:0!important;}
.sg-paypro-hero-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:22px;}
.sg-paypro-btn{display:inline-flex;align-items:center;justify-content:center;gap:9px;min-height:46px;padding:0 18px;border-radius:var(--radius-lg);text-decoration:none!important;font-size:var(--fs-sm);font-weight:700;transition:transform .18s ease,box-shadow .18s ease;}
.sg-paypro-btn:hover{transform:translateY(-2px);}
.sg-paypro-btn-primary{background:var(--color-white);color:var(--color-brand-500)!important;box-shadow:var(--shadow-md);}
.sg-paypro-btn-soft{background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);color:var(--color-white)!important;}
.sg-paypro-hero-panel{align-self:stretch;display:flex;flex-direction:column;justify-content:space-between;gap:14px;padding:var(--space-5);border-radius:var(--radius-xl);background:rgba(255,255,255,.14);border:1px solid rgba(255,255,255,.22);backdrop-filter:blur(10px);-webkit-backdrop-filter:blur(10px);box-shadow:inset 0 1px 0 rgba(255,255,255,.16);}
.sg-paypro-hero-panel h3{margin:0;color:var(--color-white);font-size:15px;font-weight:700;letter-spacing:-.025em;}
.sg-paypro-step-grid{display:grid;gap:10px;}
.sg-paypro-step{display:flex;align-items:center;gap:11px;min-height:46px;padding:10px 12px;border-radius:var(--radius-lg);background:rgba(255,255,255,.13);border:1px solid rgba(255,255,255,.14);}
.sg-paypro-step span{width:28px;height:28px;flex:0 0 28px;border-radius:var(--radius-sm);display:flex;align-items:center;justify-content:center;background:var(--color-white);color:var(--color-brand-500);font-size:12px;font-weight:700;}
.sg-paypro-step strong{display:block;color:var(--color-white);font-size:var(--fs-sm);font-weight:700;line-height:1.1;}
.sg-paypro-step small{display:block;color:rgba(255,255,255,.72);font-size:var(--fs-xs);font-weight:600;margin-top:2px;}
.sg-paypro-search-card{margin-bottom:22px!important;}
.sg-paypro-search-card .sige-card-body{padding:var(--space-5)!important;}
.sg-paypro-search-card form{display:grid!important;grid-template-columns:minmax(280px,1.35fr) minmax(170px,.65fr) minmax(150px,.55fr) minmax(190px,.72fr) auto auto!important;gap:var(--space-3)!important;align-items:end!important;}
.sg-paypro-search-card .sige-form-group{min-width:0!important;width:100%;}
.sg-paypro-search-card .sige-input,.sg-paypro-search-card select,.sg-paypro-wrap .sige-select,.sg-paypro-wrap .sige-input{border:1px solid var(--color-ink-100)!important;border-radius:var(--radius-md)!important;min-height:44px!important;box-shadow:var(--shadow-xs);}
.sg-paypro-search-card .sige-input:focus,.sg-paypro-wrap .sige-input:focus,.sg-paypro-wrap .sige-select:focus{border-color:var(--color-brand-400)!important;box-shadow:var(--shadow-xs);}
.sg-paypro-search-card .sige-btn,.sg-paypro-wrap .sige-btn{border-radius:var(--radius-md)!important;font-weight:700!important;}
.sg-paypro-hints{margin-top:12px;display:flex;gap:var(--space-2);flex-wrap:wrap;color:var(--color-slate-500);font-size:12px;}
.sg-paypro-hints .sige-badge{padding:7px 10px;border-radius:var(--radius-pill);background:var(--color-brand-50)!important;color:var(--color-brand-500)!important;border:1px solid var(--color-brand-100);font-weight:700;}
.sg-paypro-wrap .sige-students-grid{grid-template-columns:repeat(auto-fill,minmax(340px,1fr))!important;gap:14px!important;}
.sg-paypro-wrap .sige-student-card{border-radius:var(--radius-xl)!important;border:1px solid var(--color-slate-100)!important;box-shadow:var(--shadow-sm);padding:var(--space-4)!important;background:linear-gradient(180deg,var(--color-white),var(--color-white))!important;}
.sg-paypro-wrap .sige-student-avatar{width:56px!important;height:56px!important;border-radius:var(--radius-lg)!important;background:linear-gradient(135deg,var(--color-brand-50),var(--color-brand-100))!important;color:var(--color-brand-500)!important;border:0!important;}
.sg-paypro-wrap .sige-student-name{font-weight:700!important;color:var(--color-black)!important;letter-spacing:-.02em;}
.sg-paypro-student-card{background:linear-gradient(135deg,var(--color-white) 0%,var(--color-white) 100%)!important;}
.sg-paypro-student-card .sige-card-header{background:linear-gradient(135deg,var(--color-white) 0%,var(--color-brand-50) 100%)!important;}
.sg-paypro-student-card .sige-card-title{font-size:22px!important;color:var(--color-black)!important;letter-spacing:-.035em;}
.sg-paypro-notice-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px;margin:0 0 18px;}
.sg-paypro-info-box{border-radius:var(--radius-xl)!important;padding:16px 18px!important;margin:0!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;border-left:5px solid var(--sg-paypro-accent,var(--color-brand-500))!important;box-shadow:var(--shadow-sm);}
.sg-paypro-info-box strong,.sg-paypro-info-box div:first-child{font-weight:700!important;}
.sg-paypro-info-blue{--sg-paypro-accent:var(--color-info-400);}.sg-paypro-info-amber{--sg-paypro-accent:var(--color-warning-600);}
.sg-paypro-operation-card{margin-bottom:18px!important;}
.sg-paypro-operation-card .sige-card-title{font-size:18px!important;color:var(--color-black)!important;}
.sg-paypro-operation-card .sige-card-title svg{width:38px!important;height:38px!important;padding:9px;border-radius:var(--radius-md);background:var(--color-brand-50);color:var(--color-brand-500);box-sizing:border-box;}
.sg-paypro-operation-card .sige-card-header p{font-size:var(--fs-sm)!important;color:var(--color-slate-500)!important;font-weight:600;}
.sg-paypro-actions-row{display:flex!important;align-items:center!important;gap:10px!important;padding:14px 18px!important;flex-wrap:wrap!important;background:var(--color-white)!important;border-bottom:1px solid var(--color-slate-100)!important;}
.sg-paypro-actions-row button{height:36px!important;padding:0 var(--space-3)!important;border-radius:var(--radius-md)!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-brand-500)!important;font-weight:700!important;}
.sg-paypro-scroll-table{overflow:auto;-webkit-overflow-scrolling:touch;}
.sg-paypro-wrap .sige-table{border-collapse:separate!important;border-spacing:0 8px!important;padding:8px 10px 14px;min-width:920px;}
.sg-paypro-wrap .sige-table th{position:static!important;background:transparent!important;color:var(--color-slate-500)!important;font-size:var(--fs-xs)!important;font-weight:700!important;letter-spacing:.06em!important;padding:0 var(--space-3) var(--space-1)!important;border:0!important;}
.sg-paypro-wrap .sige-table td{background:var(--color-white)!important;border-top:1px solid var(--color-slate-100)!important;border-bottom:1px solid var(--color-slate-100)!important;padding:var(--space-3)!important;color:var(--color-ink-800)!important;font-size:var(--fs-sm)!important;font-weight:600!important;}
.sg-paypro-wrap .sige-table td:first-child{border-left:1px solid var(--color-slate-100)!important;border-radius:12px 0 0 14px!important;}.sg-paypro-wrap .sige-table td:last-child{border-right:1px solid var(--color-slate-100)!important;border-radius:0 14px 14px 0!important;}
.sg-paypro-wrap .sige-table tr:hover td{background:var(--color-slate-50)!important;}
.sg-paypro-wrap .sige-months-grid{grid-template-columns:repeat(auto-fill,minmax(250px,1fr))!important;gap:14px!important;}
.sg-paypro-wrap .sige-month-card{border-radius:var(--radius-xl)!important;border:1px solid var(--color-slate-100)!important;box-shadow:var(--shadow-sm);}
.sg-paypro-wrap .sige-avulso-card{border-radius:var(--radius-xl)!important;border:1px solid var(--color-slate-100)!important;box-shadow:var(--shadow-sm);background:linear-gradient(180deg,var(--color-white),var(--color-white))!important;min-width:230px!important;}
.sg-paypro-wrap .sige-payment-summary{position:relative;border:0!important;border-radius:var(--radius-xl)!important;padding:var(--space-6)!important;margin-top:24px!important;background:linear-gradient(135deg,var(--color-black),var(--color-brand-800) 52%,var(--color-brand-500))!important;box-shadow:var(--shadow-lg);color:var(--color-white)!important;overflow:hidden;}
.sg-paypro-wrap .sige-payment-summary:before{content:"";position:absolute;right:-78px;top:-72px;width:240px;height:240px;border-radius:var(--radius-pill);background:rgba(255,255,255,.11);}
.sg-paypro-wrap .sige-payment-summary *{position:relative;z-index:1;}
.sg-paypro-wrap .sige-payment-grid{display:grid!important;grid-template-columns:minmax(0,1fr) minmax(280px,.38fr)!important;gap:22px!important;align-items:stretch!important;}
.sg-paypro-wrap .sige-payment-options{padding:18px;border-radius:var(--radius-xl);background:rgba(255,255,255,.11);border:1px solid rgba(255,255,255,.14);}
.sg-paypro-wrap .sige-payment-summary .sige-label{color:rgba(255,255,255,.72)!important;}
.sg-paypro-wrap .sige-payment-summary .sige-input,.sg-paypro-wrap .sige-payment-summary .sige-select{background:var(--color-white)!important;color:var(--color-black)!important;border:0!important;}
.sg-paypro-wrap .sige-payment-total{min-width:0!important;text-align:left!important;padding:var(--space-5);border-radius:var(--radius-xl);background:var(--color-white);color:var(--color-black);display:flex;flex-direction:column;justify-content:center;}
.sg-paypro-wrap .sige-payment-total p:first-of-type{font-size:12px!important;color:var(--color-slate-500)!important;letter-spacing:.08em!important;}
.sg-paypro-wrap .sige-total-value{font-size:clamp(30px,3vw,42px)!important;line-height:1!important;color:var(--color-brand-500)!important;letter-spacing:-.06em!important;margin:var(--space-2) 0 var(--space-1)!important;}
.sg-paypro-wrap .sige-payment-total .sige-btn{height:58px!important;border-radius:var(--radius-lg)!important;background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-400))!important;box-shadow:var(--shadow-md);}
.sg-paypro-wrap button,.sg-paypro-wrap a,.sg-paypro-wrap input,.sg-paypro-wrap select,.sg-paypro-wrap label{pointer-events:auto!important;}
.sg-paypro-wrap button:not(:disabled),.sg-paypro-wrap [role="button"]{cursor:pointer!important;}
.sg-paypro-row-actions{display:flex!important;align-items:center!important;gap:7px!important;flex-wrap:wrap!important;min-width:172px!important;}
.sg-paypro-action-btn{min-height:32px!important;border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;border-radius:var(--radius-sm)!important;padding:0 9px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:5px!important;color:var(--color-brand-500)!important;font-size:11.5px!important;font-weight:700!important;text-decoration:none!important;line-height:1!important;box-shadow:var(--shadow-xs);}
.sg-paypro-action-btn svg{width:15px!important;height:15px!important;pointer-events:none!important;stroke:currentColor!important;}
.sg-paypro-action-btn span{pointer-events:none!important;}
.sg-paypro-action-btn:hover{transform:translateY(-1px)!important;box-shadow:var(--shadow-sm);background:var(--color-white)!important;}
.btn-cancelar-lancamento.sg-paypro-action-btn{color:var(--color-danger-500)!important;border-color:var(--color-danger-100)!important;background:var(--color-warning-50)!important;}
.btn-isentar-lancamento.sg-paypro-action-btn{color:var(--color-brand-500)!important;border-color:var(--color-info-100)!important;background:var(--color-brand-50)!important;}
.sg-paypro-modal-close{border:none!important;background:transparent!important;font-size:1.5rem!important;line-height:1!important;cursor:pointer!important;padding:0 var(--space-2)!important;}
.sg-paypro-isencao-preset{width:100%!important;}

.sg-paypro-payment-check{display:flex!important;align-items:center!important;gap:10px!important;padding:10px 12px!important;border-radius:var(--radius-lg)!important;background:rgba(255,255,255,.14)!important;border:1px solid rgba(255,255,255,.18)!important;color:var(--color-white)!important;font-size:var(--fs-base)!important;font-weight:700!important;line-height:1.25!important;}
.sg-paypro-payment-check input{flex:0 0 auto!important;background:var(--color-white)!important;border:1px solid rgba(255,255,255,.65)!important;}
.sg-paypro-payment-check span{display:inline-block!important;color:var(--color-white)!important;opacity:1!important;text-shadow:0 1px 2px rgba(0,0,0,.18)!important;}
.sg-paypro-payment-note{color:rgba(255,255,255,.76)!important;font-size:var(--fs-sm)!important;line-height:1.45!important;margin:6px 0 0!important;}
.sg-paypro-modal .sige-modal-content{max-width:620px!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-lg);}
.sg-paypro-modal .sige-modal-header{padding:22px 24px!important;background:linear-gradient(135deg,var(--color-white) 0%,var(--color-brand-50) 100%)!important;}
.sg-paypro-modal .sige-modal-header h3{color:var(--color-black)!important;font-size:var(--fs-lg)!important;font-weight:700!important;letter-spacing:-.035em!important;}
.sg-paypro-modal .sige-modal-body{padding:var(--space-6)!important;color:var(--color-slate-900)!important;}
.sg-paypro-confirm-summary{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-top:18px;}
.sg-paypro-confirm-item{padding:14px;border-radius:var(--radius-lg);background:var(--color-slate-50);border:1px solid var(--color-brand-100);}
.sg-paypro-confirm-item strong{display:block;font-size:var(--fs-xs);text-transform:uppercase;letter-spacing:.08em;color:var(--color-slate-500);margin-bottom:5px;}
.sg-paypro-confirm-item span{display:block;font-size:17px;font-weight:700;color:var(--color-black);}
.sg-paypro-confirm-warning{display:flex;gap:10px;align-items:flex-start;margin-top:18px;padding:14px 15px;border-radius:var(--radius-lg);background:var(--color-warning-50);border:1px solid var(--color-warning-200);color:var(--color-warning-900);font-size:var(--fs-sm);font-weight:600;line-height:1.45;}
.sg-paypro-modal .sige-modal-footer{padding:18px 24px!important;background:var(--color-white)!important;}
.sg-paypro-modal .sige-modal-footer .sige-btn{border-radius:var(--radius-lg)!important;height:46px!important;font-weight:700!important;}
.sg-paypro-modal{z-index:999999!important;align-items:center!important;justify-content:center!important;position:fixed!important;inset:0!important;width:100vw!important;height:100vh!important;}
.sg-paypro-modal .sige-modal-content{position:relative!important;margin:0 auto!important;}
body.sgk-modal-open{overflow:hidden!important;}
html.sgk-modal-open{overflow:hidden!important;}
.sg-paypro-wrap .sige-payment-summary .sg-paypro-payment-check{color:var(--color-white)!important;}
.sg-paypro-wrap .sige-payment-summary .sg-paypro-payment-check span{color:var(--color-white)!important;opacity:1!important;visibility:visible!important;}
.sg-paypro-wrap .sige-payment-summary .sg-paypro-payment-check input[type="checkbox"]{accent-color:var(--color-white)!important;box-shadow:var(--shadow-xs);}
.sg-paypro-result-modal .sige-modal-content{max-width:680px!important;border-radius:var(--radius-xl)!important;}
.sg-paypro-result-hero{padding:26px 26px 22px;background:linear-gradient(135deg,var(--color-success-50),var(--color-success-50));border-bottom:1px solid var(--color-success-100);}
.sg-paypro-result-icon{width:54px;height:54px;border-radius:var(--radius-xl);background:var(--color-success-600);color:var(--color-white);display:flex;align-items:center;justify-content:center;box-shadow:var(--shadow-md);margin-bottom:14px;}
.sg-paypro-result-hero h3{margin:0 0 6px!important;color:var(--color-black)!important;font-size:22px!important;font-weight:700!important;letter-spacing:-.035em!important;}
.sg-paypro-result-hero p{margin:0!important;color:var(--color-slate-700)!important;font-weight:600!important;}
@media (max-width:760px){.sg-paypro-confirm-summary{grid-template-columns:1fr;}.sg-paypro-modal .sige-modal-content{max-width:calc(100vw - 24px)!important;border-radius:var(--radius-xl)!important;}.sg-paypro-modal .sige-modal-footer{display:grid!important;grid-template-columns:1fr!important;}.sg-paypro-modal .sige-modal-footer .sige-btn{width:100%;}}
.sg-paypro-wrap .sige-checkbox{accent-color:var(--color-brand-500)!important;}
@media (max-width:1280px){.sg-paypro-hero{grid-template-columns:1fr;}.sg-paypro-search-card form{grid-template-columns:repeat(2,minmax(0,1fr))!important;}.sg-paypro-wrap .sige-payment-grid{grid-template-columns:1fr!important;}.sg-paypro-notice-grid{grid-template-columns:1fr;}}
@media (max-width:760px){.sg-paypro-wrap{padding:0!important;}.sg-paypro-hero{padding:24px 18px!important;border-radius:var(--radius-xl)!important;min-height:0;}.sg-paypro-hero h1{font-size:30px!important;}.sg-paypro-hero-actions{display:grid;grid-template-columns:1fr;}.sg-paypro-btn{width:100%;}.sg-paypro-hero-panel{padding:var(--space-4);border-radius:var(--radius-xl);}.sg-paypro-search-card form{grid-template-columns:1fr!important;}.sg-paypro-search-card .sige-btn,.sg-paypro-search-card a{width:100%;}.sg-paypro-wrap .sige-card-body{padding:var(--space-4)!important;}.sg-paypro-wrap .sige-card-header{padding:18px!important;}.sg-paypro-wrap .sige-students-grid{grid-template-columns:1fr!important;}.sg-paypro-wrap .sige-table{min-width:860px;}.sg-paypro-scroll-table:after{content:'Deslize para ver mais';display:block;margin:0 var(--space-3) var(--space-3);color:var(--color-ink-400);font-size:var(--fs-xs);font-weight:700;text-align:right;}.sg-paypro-wrap .sige-months-grid{grid-template-columns:1fr!important;}.sg-paypro-wrap .sige-payment-summary{padding:var(--space-4)!important;border-radius:var(--radius-xl)!important;}.sg-paypro-wrap .sige-payment-options{padding:14px;border-radius:var(--radius-lg);}.sg-paypro-wrap .sige-payment-total{padding:var(--space-4);border-radius:var(--radius-lg);}.sg-paypro-wrap .sige-total-value{font-size:32px!important;}}


/* =============================================================================
   SoftGenial v12.10.12 - Registar Pagamento alinhado ao Painel Principal
   Camada visual apenas: mesma linguagem, proporções e leitura do Dashboard.
   ============================================================================= */
.sg-paypro-wrap{--sgv2-purple:var(--color-brand-500);--sgv2-purple-dark:var(--color-brand-700);--sgv2-purple-soft:var(--color-brand-50);--sgv2-ink:var(--color-ink-500);--sgv2-muted:var(--color-slate-500);--sgv2-line:var(--color-ink-100);--sgv2-bg:var(--color-ink-50);--sgv2-green:var(--color-success-700);--sgv2-red:var(--color-danger-500);--sgv2-amber:var(--color-warning-500);--sgv2-blue:var(--color-info-400);background:transparent!important;font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif!important;color:var(--sgv2-ink)!important;display:flex;flex-direction:column;gap:var(--space-5)!important;}
.sg-paypro-hero{position:relative!important;overflow:hidden!important;min-height:178px!important;margin:0!important;padding:32px 34px!important;border-radius:var(--radius-xl)!important;background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-brand-100) 100%)!important;border:1px solid rgba(92,64,187,.12)!important;box-shadow:var(--shadow-lg);color:var(--sgv2-ink)!important;display:grid!important;grid-template-columns:minmax(0,1.04fr) minmax(340px,.96fr)!important;gap:22px!important;align-items:center!important;}
.sg-paypro-hero:before{content:""!important;position:absolute!important;inset:auto -80px -130px auto!important;width:420px!important;height:300px!important;border-radius:var(--radius-pill)!important;background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%)!important;pointer-events:none!important;}
.sg-paypro-hero:after{content:""!important;position:absolute!important;right:210px!important;bottom:28px!important;width:90px!important;height:90px!important;border-radius:var(--radius-pill)!important;background:rgba(109,93,252,.13)!important;pointer-events:none!important;}
.sg-paypro-hero-content,.sg-paypro-hero-panel{position:relative!important;z-index:1!important;}
.sg-paypro-kicker{display:inline-flex!important;align-items:center!important;gap:var(--space-2)!important;margin:0 0 10px!important;font-size:12px!important;font-weight:700!important;letter-spacing:.11em!important;text-transform:uppercase!important;color:var(--sgv2-purple)!important;}
.sg-paypro-kicker:before{display:none!important;content:none!important;}
.sg-paypro-kicker svg{width:18px!important;height:18px!important;stroke:currentColor!important;fill:none!important;color:currentColor!important;opacity:1!important;}
.sg-paypro-hero h1{margin:0!important;font-size:31px!important;line-height:1.08!important;font-weight:700!important;letter-spacing:-.04em!important;color:var(--color-black)!important;}
.sg-paypro-hero p{max-width:650px!important;margin:var(--space-3) 0 0!important;font-size:15px!important;line-height:1.65!important;color:var(--color-slate-700)!important;font-weight:500!important;}
.sg-paypro-hero-actions{display:flex!important;flex-wrap:wrap!important;gap:var(--space-3)!important;margin-top:24px!important;}
.sg-paypro-btn{min-height:46px!important;display:inline-flex!important;align-items:center!important;justify-content:center!important;gap:10px!important;border-radius:var(--radius-md)!important;padding:0 22px!important;font-size:var(--fs-base)!important;font-weight:700!important;text-decoration:none!important;border:1px solid transparent!important;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease!important;}
.sg-paypro-btn-primary{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600))!important;color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sg-paypro-btn-soft{background:var(--color-white)!important;color:var(--color-ink-900)!important;border-color:var(--color-ink-100)!important;box-shadow:var(--shadow-sm);}
.sg-paypro-hero-panel{align-self:stretch!important;display:flex!important;flex-direction:column!important;justify-content:center!important;padding:var(--space-5)!important;border-radius:var(--radius-xl)!important;background:linear-gradient(135deg,rgba(255,255,255,.78),rgba(248,246,255,.94))!important;border:1px solid rgba(109,93,252,.14)!important;box-shadow:var(--shadow-md);color:var(--color-ink-500)!important;backdrop-filter:blur(10px)!important;}
.sg-paypro-hero-panel h3{margin:0 0 var(--space-3)!important;color:var(--color-ink-500)!important;font-size:15px!important;font-weight:700!important;letter-spacing:-.02em!important;}
.sg-paypro-step-grid{gap:9px!important;}
.sg-paypro-step{min-height:48px!important;border-radius:var(--radius-md)!important;background:var(--color-white)!important;border:1px solid var(--color-slate-100)!important;color:var(--color-ink-500)!important;box-shadow:var(--shadow-sm);}
.sg-paypro-step span{background:var(--color-brand-50)!important;color:var(--color-brand-500)!important;box-shadow:none!important;}
.sg-paypro-step strong{color:var(--color-ink-500)!important;}
.sg-paypro-step small{color:var(--color-ink-400)!important;}
.sg-paypro-search-card{margin:0!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);border:1px solid rgba(30,34,60,.08)!important;}
.sg-paypro-search-card .sige-card-body{padding:18px 20px!important;}
.sg-paypro-wrap .sige-card{border-radius:var(--radius-xl)!important;border:1px solid rgba(30,34,60,.08)!important;box-shadow:var(--shadow-md);background:var(--color-white)!important;}
.sg-paypro-wrap .sige-card-header{padding:20px 22px 14px!important;background:var(--color-white)!important;border-bottom:0!important;}
.sg-paypro-wrap .sige-card-title{font-size:17px!important;line-height:1.1!important;font-weight:700!important;letter-spacing:-.03em!important;color:var(--color-ink-500)!important;}
.sg-paypro-wrap .sige-label{font-size:var(--fs-xs)!important;font-weight:700!important;text-transform:uppercase!important;letter-spacing:.06em!important;color:var(--color-slate-600)!important;}
.sg-paypro-wrap .sige-input,.sg-paypro-wrap .sige-select{height:46px!important;border-radius:var(--radius-md)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-ink-500)!important;background:var(--color-white)!important;box-shadow:var(--shadow-sm);}
.sg-paypro-info-box{border-radius:var(--radius-lg)!important;background:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sg-paypro-wrap .sige-payment-summary{background:var(--color-white)!important;color:var(--color-ink-500)!important;border:1px solid rgba(30,34,60,.08)!important;border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-md);padding:20px 22px!important;}
.sg-paypro-wrap .sige-payment-summary:before{background:radial-gradient(circle,rgba(109,93,252,.12),rgba(109,93,252,0) 65%)!important;}
.sg-paypro-wrap .sige-payment-options{background:var(--color-slate-50)!important;border:1px solid var(--color-brand-100)!important;border-radius:var(--radius-lg)!important;}
.sg-paypro-wrap .sige-payment-summary .sige-label{color:var(--color-slate-600)!important;}
.sg-paypro-wrap .sige-payment-summary .sige-input,.sg-paypro-wrap .sige-payment-summary .sige-select{border:1px solid var(--color-ink-100)!important;background:var(--color-white)!important;color:var(--color-ink-500)!important;}
.sg-paypro-payment-check{background:var(--color-white)!important;border:1px solid var(--color-ink-100)!important;color:var(--color-ink-500)!important;box-shadow:var(--shadow-sm);}
.sg-paypro-payment-check span,.sg-paypro-wrap .sige-payment-summary .sg-paypro-payment-check span{color:var(--color-ink-500)!important;opacity:1!important;text-shadow:none!important;visibility:visible!important;}
.sg-paypro-payment-check input[type="checkbox"],.sg-paypro-wrap .sige-payment-summary .sg-paypro-payment-check input[type="checkbox"]{accent-color:var(--color-brand-500)!important;box-shadow:none!important;}
/* v12.10.138 - Blindagem de clique/seleção no módulo de pagamentos.
   Resolve casos em que pseudo-elementos, wrappers ou CSS do admin bloqueiam checkboxes/botões. */
.sg-paypro-wrap .sige-payment-summary:before,
.sg-paypro-wrap .sige-payment-summary:after,
.sg-paypro-wrap .sige-card:before,
.sg-paypro-wrap .sige-card:after,
.sg-paypro-wrap .sg-paypro-hero:before,
.sg-paypro-wrap .sg-paypro-hero:after{pointer-events:none!important;}
.sg-paypro-wrap input[type="checkbox"],
.sg-paypro-wrap input[type="radio"]{
  -webkit-appearance:checkbox!important;appearance:auto!important;opacity:1!important;visibility:visible!important;
  pointer-events:auto!important;cursor:pointer!important;position:relative!important;z-index:30!important;
  min-width:16px!important;min-height:16px!important;display:inline-block!important;
}
.sg-paypro-wrap input[type="radio"]{-webkit-appearance:radio!important;}
.sg-paypro-wrap button,
.sg-paypro-wrap [role="button"],
.sg-paypro-wrap label,
.sg-paypro-wrap select,
.sg-paypro-wrap input,
.sg-paypro-wrap textarea{pointer-events:auto!important;}
.sg-paypro-wrap button:not(:disabled),
.sg-paypro-wrap a[href],
.sg-paypro-wrap label[for],
.sg-paypro-wrap .sg-paypro-payment-check{cursor:pointer!important;}
.sg-paypro-payment-check{user-select:none!important;position:relative!important;z-index:35!important;transition:border-color .18s ease,box-shadow .18s ease,background .18s ease,transform .18s ease;}
.sg-paypro-payment-check:focus-visible{outline:3px solid rgba(90,63,214,.22)!important;outline-offset:3px!important;}
.sg-paypro-payment-check.is-checked{border-color:var(--color-brand-500)!important;background:var(--color-brand-50)!important;box-shadow:var(--shadow-sm);color:var(--color-ink-500)!important;}
.sg-paypro-payment-check.is-checked span{color:var(--color-ink-500)!important;}
#sige-motivo-isencao[aria-disabled="true"]{opacity:.72!important;}

.sg-paypro-payment-note{color:var(--color-slate-500)!important;}
.sg-paypro-wrap .sige-payment-total{background:var(--color-slate-50)!important;border:1px solid var(--color-brand-100)!important;color:var(--color-ink-500)!important;border-radius:var(--radius-lg)!important;}
.sg-paypro-wrap .sige-payment-total .sige-btn{height:54px!important;border-radius:var(--radius-lg)!important;background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600))!important;box-shadow:var(--shadow-md);}
.sg-paypro-wrap .sige-total-value{color:var(--color-brand-500)!important;font-size:clamp(30px,3vw,42px)!important;}
.sg-paypro-modal .sige-modal-content,.sg-paypro-result-modal .sige-modal-content{border-radius:var(--radius-xl)!important;box-shadow:var(--shadow-lg);}
.sg-paypro-modal .sige-modal-header,.sg-paypro-result-hero{background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-brand-100) 100%)!important;border-bottom:1px solid rgba(92,64,187,.12)!important;}
.sg-paypro-result-icon{background:linear-gradient(135deg,var(--color-success-600),var(--color-success-500))!important;}
@media (max-width:1280px){.sg-paypro-hero{grid-template-columns:1fr!important;}.sg-paypro-hero-panel{max-width:620px!important;width:100%!important;}}
@media (max-width:760px){.sg-paypro-wrap{gap:14px!important;}.sg-paypro-hero{padding:var(--space-6) var(--space-5)!important;border-radius:var(--radius-xl)!important;min-height:0!important;}.sg-paypro-hero h1{font-size:26px!important;}.sg-paypro-hero-actions{display:grid!important;grid-template-columns:1fr!important;}.sg-paypro-btn{width:100%!important;}.sg-paypro-search-card .sige-card-body{padding:var(--space-4)!important;}.sg-paypro-wrap .sige-card{border-radius:var(--radius-xl)!important;}.sg-paypro-wrap .sige-payment-summary{padding:var(--space-4)!important;}.sg-paypro-wrap .sige-payment-grid{grid-template-columns:1fr!important;}}

</style>

<div class="wrap sige-page sg-paypro-wrap">

 <section class="sige-hero sg-paypro-hero">
 <div class="sg-paypro-hero-content">
  <div class="sg-paypro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('money') : ''; ?> Tesouraria</div>
  <h1>Registar Pagamento</h1>
  <p>Pesquise o aluno, confirme os valores em aberto, seleccione o que será pago e finalize o recibo.</p>
  <div class="sg-paypro-hero-actions">
   <a class="sg-paypro-btn sg-paypro-btn-primary" href="?page=sige-app&view=financeiro-dashboard">Resumo financeiro</a>
   <a class="sg-paypro-btn sg-paypro-btn-soft" href="?page=sige-app&view=financeiro-devedores">Ver devedores</a>
  </div>
 </div>
 <aside class="sg-paypro-hero-panel" aria-label="Fluxo de pagamento">
  <h3>Fluxo de atendimento</h3>
  <div class="sg-paypro-step-grid">
   <div class="sg-paypro-step"><span>1</span><div><strong>Pesquisar aluno</strong><small>Nome, processo ou contacto</small></div></div>
   <div class="sg-paypro-step"><span>2</span><div><strong>Seleccionar cobranças</strong><small>Dívidas, meses futuros ou serviços</small></div></div>
   <div class="sg-paypro-step"><span>3</span><div><strong>Confirmar recibo</strong><small>Ano Lectivo <?php echo esc_html($ano_letivo); ?></small></div></div>
  </div>
 </aside>
</section>

 <div class="sige-card sg-paypro-search-card">
 <div class="sige-card-body">
 <form method="get" class="sige-flex sige-gap-3 sg-paypro-search-form" style="align-items: flex-end; flex-wrap: wrap;">
 <input type="hidden" name="page" value="sige-app">
 <input type="hidden" name="view" value="financeiro-pagamentos">
 <div class="sige-form-group" style="flex:1.2; min-width:250px; margin-bottom:0;">
 <label class="sige-label">Pesquisar aluno</label>
 <div style="position:relative;">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="position:absolute; left:12px; top:50%; transform:translateY(-50%); width:18px; height:18px; color:var(--sige-slate-400);"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
 <input type="text" name="q" value="<?php echo esc_attr($busca); ?>" class="sige-input sige-w-full" style="padding-left:40px;" placeholder="Nome, nº processo ou contacto..." autocomplete="off">
</div>
</div>
 <div class="sige-form-group" style="min-width:170px; margin-bottom:0;">
 <label class="sige-label">Estado financeiro</label>
 <select name="status_fin" class="sige-input sige-w-full">
  <option value="">Todos</option>
  <option value="em_divida" <?php selected($filtro_status_fin, 'em_divida'); ?>>Com dívida</option>
  <option value="regular" <?php selected($filtro_status_fin, 'regular'); ?>>Regular</option>
  <option value="com_credito" <?php selected($filtro_status_fin, 'com_credito'); ?>>Com crédito</option>
 </select>
</div>
 <div class="sige-form-group" style="min-width:170px; margin-bottom:0;">
 <label class="sige-label">Classe</label>
 <select name="classe" class="sige-input sige-w-full">
  <option value="">Todas</option>
  <?php foreach ((array)$classes_filtro as $_classe_op): ?>
   <option value="<?php echo esc_attr($_classe_op); ?>" <?php selected($filtro_classe, $_classe_op); ?>><?php echo esc_html($_classe_op); ?></option>
  <?php endforeach; ?>
 </select>
</div>
 <div class="sige-form-group" style="min-width:190px; margin-bottom:0;">
 <label class="sige-label">Turma</label>
 <select name="turma_id" class="sige-input sige-w-full">
  <option value="0">Todas</option>
  <?php foreach ((array)$turmas_filtro as $_turma_op): 
   $_turma_nome = trim((string)($_turma_op->nome ?: $_turma_op->nome_turma ?: ('Turma #' . $_turma_op->id)));
   $_turma_classe = trim((string)($_turma_op->classe ?: $_turma_op->nivel_ensino));
  ?>
   <option value="<?php echo (int)$_turma_op->id; ?>" <?php selected((int)$filtro_turma_id, (int)$_turma_op->id); ?>>
    <?php echo esc_html($_turma_nome . ($_turma_classe !== '' ? ' - ' . $_turma_classe : '')); ?>
   </option>
  <?php endforeach; ?>
 </select>
</div>
 <button type="submit" class="sige-btn sige-btn-primary">Filtrar</button>
 <?php if ($tem_filtro_ativo): ?>
  <a href="?page=sige-app&view=financeiro-pagamentos" class="sige-btn sige-btn-light">Limpar</a>
 <?php endif; ?>
</form>
 <div class="sg-paypro-hints">
  <span class="sige-badge sige-badge-muted">Pesquisa rápida</span>
  <span class="sige-badge sige-badge-muted">Até 50 resultados</span>
  <span class="sige-badge sige-badge-muted">Inclui contactos</span>
 </div>

 <?php if ($tem_filtro_ativo && !$aluno_id): ?>
 <div style="margin-top: 24px;">
 <p style="font-size:0.85rem; color:var(--sige-slate-500); margin-bottom:16px;">
 <?php echo count($alunos); ?> resultado(s) encontrado(s) com os critérios aplicados:
</p>
 <?php if (empty($alunos)): ?>
 <div style="text-align:center; padding:40px; color:var(--sige-slate-400);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:48px;height:48px;margin-bottom:12px;"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
 <p style="font-weight:600; color:var(--sige-slate-700); font-size:1.1rem; margin:0 0 4px;">Nenhum aluno encontrado</p>
 <p class="sige-u-m0">Tente pesquisar com outro nome ou número de processo.</p>
</div>
 <?php else: ?>
 <div class="sige-students-grid">
 <?php foreach ($alunos as $a): 
 $foto_url = !empty($a->foto) ? esc_url($a->foto) : '';
 $nomes = explode(' ', trim($a->nome_completo));
 $iniciais = (count($nomes) >= 2) ? strtoupper(substr($nomes[0], 0, 1) . substr(end($nomes), 0, 1)) : strtoupper(substr($a->nome_completo, 0, 2));
 $classe_display = $a->turma_classe ?: $a->nivel_ensino ?: '';
 $saldo_pendente_card = isset($a->saldo_pendente) ? (float)$a->saldo_pendente : 0.0;
 $saldo_credito_card = isset($a->saldo_credito) ? (float)$a->saldo_credito : 0.0;
 $url_aluno = add_query_arg([
  'page' => 'sige-app',
  'view' => 'financeiro-pagamentos',
  'aluno_id' => (int)$a->id,
 ], admin_url('admin.php'));
 ?>
 <a href="<?php echo esc_url($url_aluno); ?>" class="sige-student-card">
 <div class="sige-student-avatar">
 <?php if ($foto_url): ?><img src="<?php echo $foto_url; ?>" alt=""><?php else: ?><span class="sige-avatar-initials"><?php echo esc_html($iniciais); ?></span><?php endif; ?>
</div>
 <div class="sige-student-info">
 <p class="sige-student-name"><?php echo esc_html($a->nome_completo); ?></p>
 <p class="sige-student-meta">
 <span class="sige-badge sige-badge-muted">#<?php echo esc_html($a->numero_processo); ?></span>
 <?php if ($classe_display): ?><span class="sige-badge sige-badge-primary"><?php echo esc_html($classe_display); ?></span><?php endif; ?>
 <?php if ($saldo_pendente_card > 0.005): ?>
  <span class="sige-badge" style="background:var(--color-danger-100);color:var(--color-danger-700);">Dívida: <?php echo number_format($saldo_pendente_card, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></span>
 <?php else: ?>
  <span class="sige-badge" style="background:var(--color-success-100);color:var(--color-success-900);">Regular</span>
 <?php endif; ?>
 <?php if ($saldo_credito_card > 0.005): ?>
  <span class="sige-badge" style="background:var(--sg-theme-soft,var(--color-brand-50));color:var(--color-info-600);">Crédito: <?php echo number_format($saldo_credito_card, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></span>
 <?php endif; ?>
</p>
</div>
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:20px;height:20px;color:var(--sige-slate-300);"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
</a>
 <?php endforeach; ?>
</div>
 <?php endif; ?>
</div>
 <?php endif; ?>
</div>
</div>

 <?php if ($aluno_id):
 $al = $wpdb->get_row($wpdb->prepare("SELECT nome_completo, numero_processo FROM $tA WHERE id=%d AND escola_id=%d", $aluno_id, $escola_id));
 ?>

 <div class="sige-card sg-paypro-student-card">
 <div class="sige-card-header" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:16px;">
 <div>
 <h2 class="sige-card-title">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
 <?php echo esc_html($al->nome_completo ?? 'Aluno'); ?>
</h2>
 <p style="margin:4px 0 0; font-size:0.85rem; color:var(--sige-slate-500);">
 Classe: <strong style="color:var(--sige-slate-800);"><?php echo esc_html($classe_aluno ?: '-'); ?></strong>
 &nbsp;• Transp. Mensal: <strong style="color:var(--sige-slate-800);"><?php echo number_format((float)$transporte_valor,2,',','.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
 <?php if ($total_extras_previstos > 0): ?>
 &nbsp;• Extras Fixos: <strong style="color:var(--sige-slate-800);"><?php echo number_format((float)$total_extras_previstos,2,',','.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
 <?php endif; ?>
</p>
</div>
 <a class="sige-btn sige-btn-ghost" href="?page=sige-app&view=financeiro-pagamentos">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px;"><path d="M21 2v6h-6"/><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/></svg> Trocar Aluno
</a>
</div>
</div>

 <form method="post" id="sige-form-pagamento-principal">
 <?php wp_nonce_field('sige_fin_pagar'); ?>
 <input type="hidden" name="sige_fin_pagar_submit" value="1">
 <input type="hidden" name="aluno_id" value="<?php echo (int)$aluno_id; ?>">

 <div class="sg-paypro-info-box sg-paypro-info-blue" style="background:var(--color-slate-50);border:1px solid var(--sg-theme-soft,var(--color-brand-50));border-left:4px solid var(--color-info-500);border-radius:12px;padding:14px 16px;margin:0 0 18px;">
   <div style="font-weight:700;color:var(--sg-theme-primary-800,var(--color-ink-700));margin-bottom:6px;">Notificações do pagamento</div>
   <p style="margin:0 0 10px;color:var(--color-slate-700);font-size:13px;">Escolha se este pagamento deve enviar recibo/confirmação aos encarregados.</p>
   <input type="hidden" name="notificar_whatsapp" value="0">
   <input type="hidden" name="notificar_email" value="0">
   <label style="display:inline-flex;align-items:center;gap:8px;margin-right:18px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_whatsapp" value="1" checked> Enviar WhatsApp</label>
   <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_email" value="1" checked> Enviar E-mail</label>
 </div>

 <!-- [v12.9.66] Data efectiva do pagamento -->
 <div id="sige-data-efectiva-card" class="sg-paypro-info-box sg-paypro-info-amber" style="background:var(--color-slate-50);border:1px solid var(--color-warning-300);border-left:4px solid var(--color-warning-500);border-radius:12px;padding:14px 16px;margin:0 0 18px;">
   <div style="font-weight:700;color:var(--color-warning-800);margin-bottom:6px;display:flex;align-items:center;gap:8px;">
     📅 Data efectiva do pagamento
   </div>
   <p style="margin:0 0 10px;color:var(--color-warning-900);font-size:13px;">
     Por defeito é <strong>hoje</strong>. Indique outra data se o pagamento foi recebido em dia anterior - desde que a caixa desse dia esteja <strong>aberta</strong>.
     O pagamento aparece no extracto da data indicada.
   </p>
   <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
     <input type="date"
            id="sige-data-efectiva-input"
            name="data_efectiva"
            value="<?php echo esc_attr(current_time('Y-m-d')); ?>"
            max="<?php echo esc_attr(current_time('Y-m-d')); ?>"
            min="<?php echo esc_attr(wp_date('Y-m-d', strtotime('-90 days'))); ?>"
            style="padding:8px 12px;border:1px solid var(--color-slate-200);border-radius:6px;font-size:14px;font-family:inherit;">
     <span id="sige-data-efectiva-status" style="font-size:13px;font-weight:600;"></span>
   </div>
   <input type="hidden" id="sige-data-efectiva-nonce" value="<?php echo esc_attr(wp_create_nonce('sige_check_caixa_data')); ?>">
 </div>

 <div class="sige-card sg-paypro-operation-card">
 <div class="sige-card-header">
 <h3 class="sige-card-title" style="color:var(--sige-error);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg> 1. Dívidas Actuais
</h3>
 <p style="margin:4px 0 0; font-size:0.8rem; color:var(--sige-slate-500);">Seleccione os lançamentos que serão incluídos no pagamento.</p>
</div>
 <div class="sige-card-body" style="padding:0;">
 <?php if (empty($dividas)): ?>
 <div style="text-align:center; padding:40px; color:var(--sige-slate-400);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:40px;height:40px;margin-bottom:12px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
 <p style="font-weight:600; color:var(--sige-slate-700); margin:0 0 4px;">Nada lançado ainda</p>
 <p style="margin:0; font-size:0.9rem;">O aluno não tem dívidas pendentes.</p>
</div>
 <?php else: ?>
 <!-- Barra de acções para multi-lançamento -->
 <div class="sg-paypro-actions-row" style="display:flex; align-items:center; gap:10px; padding:10px 0 14px; flex-wrap:wrap;">
 <button type="button" data-sige-act="sigeSelectAllDividas" data-sige-args='[true]'
 class="sgk-btn sgk-btn-sec sgk-btn-sm">
 &#9745; Seleccionar Tudo
</button>
 <button type="button" data-sige-act="sigeSelectAllDividas" data-sige-args='[false]'
 class="sgk-btn sgk-btn-sec sgk-btn-sm">
 &#9744; Limpar
</button>
 <span id="sige-dividas-sel-info" style="font-size:0.8rem; color:var(--sige-slate-500); margin-left:4px;"></span>
 <span style="margin-left:auto; font-size:0.75rem; color:var(--sige-slate-400);">
 &#128161; M&#250;ltiplos lan&#231;amentos geram um &#250;nico recibo consolidado
</span>
</div>
 <div class="sg-paypro-scroll-table" style="max-height:350px; overflow-y:auto; border-bottom:1px solid var(--sige-slate-100);">
 <table class="sige-table">
 <thead>
 <tr>
 <th style="width:40px;">
 <input type="checkbox" id="sige-check-all-dividas"
 title="Seleccionar/Limpar todos"
 onchange="sigeSelectAllDividas(this.checked)"
 style="width:16px;height:16px;cursor:pointer;">
</th>
 <th>Servi&#231;o</th>
 <th>Mês</th>
 <th>Vencimento</th>
 <th class="sige-text-right">Original</th>
 <th class="sige-text-right">Transp.</th>
 <th class="sige-text-right">Extras</th>
 <th class="sige-text-right">Desc.</th>
 <th class="sige-text-right">Multa</th>
 <th class="sige-text-right">Pago</th>
 <th class="sige-text-right">A Pagar</th>
 

<?php
 $sige_pay_can_cancel_debt = ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_financeiro') || current_user_can('sige_secretario'));
 $sige_pay_can_exempt_debt = ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_secretario'));
 ?>
 <?php if ($sige_pay_can_cancel_debt || $sige_pay_can_exempt_debt): ?>
 <th style="width:118px;">Acções</th>
 <?php endif; ?>
</tr>
</thead>
 <tbody>
 <?php foreach ($dividas as $d): $rest = sige_fin_total_restante($d); ?>
 <tr>
 <td>
 <input type="checkbox" name="lancamentos[]"
 value="<?php echo (int)$d->id; ?>"
 class="item-checkbox sige-checkbox sige-divida-check"
 data-valor="<?php echo esc_attr($rest); ?>"
 data-multa-efectiva="<?php echo esc_attr(max(0, (float)($d->valor_multa ?? 0))); ?>"
 onchange="recalcularTotal(); sigeUpdateDividaSel();">
</td>
 <td class="sige-u-fw5">
 <?php echo esc_html($d->servico_nome); ?>
 <?php if (!empty($d->quantidade) && (int)$d->quantidade > 1): ?>
 <span style="font-size:0.7rem; background:var(--color-info-50); color:var(--color-info-700); padding:1px 6px; border-radius:4px; margin-left:4px; font-weight:600;">
 &times;<?php echo (int)$d->quantidade; ?>
</span>
 <?php endif; ?>
</td>
 <td class="sige-font-mono"><?php echo esc_html($d->mes_referencia ?: '-'); ?></td>
 <td class="sige-font-mono"><?php echo esc_html(sige_mz_date('d/m/Y', strtotime($d->data_vencimento))); ?></td>
 <td class="sige-text-right sige-font-mono"><?php echo number_format((float)$d->valor_original, 2, ',', '.'); ?></td>
 <td class="sige-text-right sige-font-mono" style="color:var(--sige-orange);"><?php echo number_format((float)($d->valor_transporte ?? 0), 2, ',', '.'); ?></td>
 <td class="sige-text-right sige-font-mono" style="color:var(--sige-primary-light);"><?php echo number_format((float)($d->valor_extras ?? 0), 2, ',', '.'); ?></td>
 <td class="sige-text-right sige-font-mono sige-text-success"><?php $desc_total_display = (float)($d->valor_desconto ?? 0) + (float)($d->valor_desconto_especial ?? 0); echo number_format($desc_total_display, 2, ',', '.'); if ((float)($d->valor_desconto_especial ?? 0) > 0) echo ' ⭐'; ?></td>
 <td class="sige-text-right sige-font-mono" style="color:var(--sige-error);"><?php echo number_format((float)($d->valor_multa ?? 0), 2, ',', '.'); ?></td>
 <td class="sige-text-right sige-font-mono"><?php echo number_format((float)($d->valor_pago ?? 0), 2, ',', '.'); ?></td>
 <td class="sige-text-right sige-font-mono"><strong style="color:var(--sige-error); font-size:1rem;"><?php echo number_format((float)$rest, 2, ',', '.'); ?></strong></td>
 <?php if ($sige_pay_can_cancel_debt || $sige_pay_can_exempt_debt): ?>
 <td>
 <div class="sg-paypro-row-actions" aria-label="Acções da dívida">
 <?php if ($sige_pay_can_exempt_debt && (float)($d->valor_pago ?? 0) <= 0.00001): ?>
 <button type="button" class="sige-btn-ghost sg-paypro-action-btn btn-isentar-lancamento" data-id="<?php echo (int)$d->id; ?>" data-servico="<?php echo esc_attr($d->servico_nome); ?>" data-mes="<?php echo esc_attr($d->mes_referencia ?: '-'); ?>" data-valor="<?php echo esc_attr(number_format((float)$rest, 2, '.', '')); ?>" title="Isentar dívida" aria-label="Isentar dívida">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg>
 <span>Isentar</span>
 </button>
 <?php endif; ?>
 <?php if ($sige_pay_can_cancel_debt): ?>
 <button type="button" class="sige-btn-ghost sg-paypro-action-btn btn-cancelar-lancamento" data-id="<?php echo (int)$d->id; ?>" data-servico="<?php echo esc_attr($d->servico_nome); ?>" data-mes="<?php echo esc_attr($d->mes_referencia ?: '-'); ?>" data-valor="<?php echo esc_attr(number_format((float)$rest, 2, '.', '')); ?>" title="Cancelar dívida" aria-label="Cancelar dívida">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
 <span>Cancelar</span>
 </button>
 <?php endif; ?>
 </div>
 </td>
 <?php endif; ?>
</tr>
 <?php endforeach; ?>
</tbody>
</table>
</div>
 <?php if (!empty($lancs_em_plano)): ?>
 <!-- Lançamentos em Plano Negociado -->
 <div style="margin-top:12px; padding:12px 16px; background:var(--color-brand-50); border:1px solid var(--color-info-100); border-radius:10px;">
 <div style="display:flex; align-items:center; gap:8px; margin-bottom:10px;">
 <span style="font-size:0.8rem; font-weight:600; color:var(--color-brand-500); text-transform:uppercase; letter-spacing:.05em;"> Em Plano de Pagamento</span>
 <span style="font-size:0.75rem; color:var(--color-brand-500); background:var(--color-brand-100); padding:2px 8px; border-radius:10px; font-weight:600;">
 <?php echo count($lancs_em_plano); ?> lançamento<?php echo count($lancs_em_plano)>1?'s':''; ?>
</span>
</div>
 <div style="overflow-x:auto;-webkit-overflow-scrolling:touch;max-width:100%;"><table class="sige-table" style="margin:0;">
 <thead>
 <tr>
 <th style="width:32px;"></th>
 <th>Serviço</th>
 <th>Mês</th>
 <th class="sige-text-right">Valor</th>
 <th>Plano</th>
</tr>
</thead>
 <tbody>
 <?php foreach ($lancs_em_plano as $lep):
 $lep_total = max(0,(float)$lep->valor_original
 +(float)($lep->valor_multa??0)
 -(float)($lep->valor_desconto??0)
 -(float)($lep->valor_desconto_especial??0)
 -(float)($lep->valor_pago??0));
 ?>
 <tr style="opacity:.75;">
 <td>
 <span title="Gerido via Plano de Pagamento - não pode ser pago directamente"
 style="display:inline-flex;align-items:center;justify-content:center;width:22px;height:22px;background:var(--color-brand-100);border-radius:4px;font-size:0.75rem;"></span>
</td>
 <td style="font-size:0.85rem; color:var(--color-slate-500);"><?php echo esc_html($lep->servico_nome); ?></td>
 <td class="sige-font-mono" style="font-size:0.82rem; color:var(--color-slate-500);"><?php echo esc_html($lep->mes_referencia??'-'); ?></td>
 <td class="sige-text-right sige-font-mono" style="font-size:0.85rem; color:var(--color-brand-500); font-weight:600;"><?php echo number_format($lep_total,2,',','.'); ?></td>
 <td style="font-size:0.78rem; color:var(--color-brand-500);">
 <a href="?page=sige-app&view=financeiro-planos" style="color:var(--color-brand-500); text-decoration:none; font-weight:600;">
 <?php echo esc_html(mb_strimwidth($lep->plano_descricao??'Ver Plano',0,35,'…')); ?>
</a>
</td>
</tr>
 <?php endforeach; ?>
</tbody>
</table></div>
 <p style="margin:8px 0 0; font-size:0.78rem; color:var(--color-brand-500);">
 ↗ Processe os pagamentos em
 <a href="?page=sige-app&view=financeiro-planos" style="color:var(--color-brand-500); font-weight:600;">Planos de Pagamento</a>
</p>
</div>
 <?php endif; ?>
 <?php endif; ?>
</div>
</div>

 <div class="sige-card sg-paypro-operation-card">
 <div class="sige-card-header">
 <h3 class="sige-card-title" style="color:var(--sige-info);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg> 2. Adiantar Meses Futuros
</h3>
 <p style="margin:4px 0 0; font-size:0.8rem; color:var(--sige-slate-500);">Seleccione mensalidade, transporte e/ou serviços extras para cada mês.</p>
</div>
 <div class="sige-card-body">
 <?php if (!$mensalidade_srv): ?>
 <div style="text-align:center; padding:20px; color:var(--sige-slate-400);">Sem serviço de mensalidade no catálogo.</div>
 <?php elseif (empty($meses_futuros)): ?>
 <div style="text-align:center; padding:20px; color:var(--sige-slate-400);">Sem meses disponíveis.</div>
 <?php else: ?>
 <div class="sige-months-grid">
 <?php foreach($meses_futuros as $mf):
 $is_pago = !empty($mf['pago']); $is_parcial = !empty($mf['parcial']); $is_isento = !empty($mf['isento']); $is_bloqueado = !empty($mf['bloqueado']);
 $tem_multa = !$is_pago && !$is_bloqueado && !empty($mf['detalhe']['multa']) && (float)$mf['detalhe']['multa'] > 0;
 $val_m_bruto = (float)($mf['detalhe']['mensalidade'] ?? 0);
 $desc_m_preview = (float)($mf['detalhe']['desconto_mens'] ?? 0);
 $val_m = max(0, $val_m_bruto - $desc_m_preview);
 $val_t_bruto = (float)($mf['detalhe']['transporte'] ?? 0);
 $desc_t_preview = (float)($mf['detalhe']['desconto_trans'] ?? 0);
 $val_t = max(0, $val_t_bruto - $desc_t_preview);
 $mm = str_pad((string)$mf['mes_num'], 2, '0', STR_PAD_LEFT);
 $status_class = '';
 if ($is_bloqueado) $status_class = 'sige-month-blocked'; elseif ($is_isento) $status_class = 'sige-month-exempt'; elseif ($is_pago) $status_class = 'sige-month-paid'; elseif ($is_parcial) $status_class = 'sige-month-partial'; elseif ($tem_multa) $status_class = 'sige-month-overdue';
 ?>
 <div class="sige-month-card <?php echo $status_class; ?>">
 <div class="sige-month-header">
 <span><?php echo esc_html($mf['mes_nome']); ?></span>
 <?php if ($is_bloqueado): ?><span class="sige-badge sige-badge-muted">Bloqueado</span>
 <?php elseif ($is_isento): ?><svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-info)" stroke-width="2" style="width:16px;height:16px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
 <?php elseif ($is_pago): ?><svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-success)" stroke-width="2" style="width:18px;height:18px;"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
 <?php elseif ($is_parcial): ?><span class="sige-badge" style="background:var(--sige-warning-100); color:var(--sige-warning-700);">Parcial</span>
 <?php elseif ($tem_multa): ?><span class="sige-badge" style="background:var(--sige-error-100); color:var(--sige-error-700);">Multa</span>
 <?php endif; ?>
</div>

 <?php if (!$is_bloqueado && !$is_isento && !$is_pago && (float)($mf['real_falta'] ?? 0) > 0.005): ?>
 <div style="margin:0 12px 10px;padding:10px 12px;border-radius:12px;background:var(--color-warning-50);border:1px solid var(--color-warning-200);color:var(--color-warning-800);font-size:0.76rem;line-height:1.45;">
  <div style="display:flex;justify-content:space-between;gap:8px;align-items:center;">
   <strong>Dívida mensal em aberto</strong>
   <strong style="font-size:0.9rem;"><?php echo number_format((float)$mf['real_falta'], 0, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
  </div>
  <div style="margin-top:4px;color:var(--color-warning-900);">
   <?php if ((float)($mf['real_pago'] ?? 0) > 0): ?>Pago: <?php echo number_format((float)$mf['real_pago'], 0, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?> · <?php endif; ?>
   <?php if ((float)($mf['detalhe']['mensalidade'] ?? 0) > 0): ?>Mens.: <?php echo number_format((float)$mf['detalhe']['mensalidade'], 0, ',', '.'); ?><?php endif; ?>
   <?php if ((float)($mf['detalhe']['transporte'] ?? 0) > 0): ?> · Transp.: <?php echo number_format((float)$mf['detalhe']['transporte'], 0, ',', '.'); ?><?php endif; ?>
   <?php if ((float)($mf['detalhe']['multa'] ?? 0) > 0): ?> · Multa: <?php echo number_format((float)$mf['detalhe']['multa'], 0, ',', '.'); ?><?php endif; ?>
  </div>
 </div>
 <?php endif; ?>

 <?php if ($is_bloqueado): ?>
 <div style="padding:20px; text-align:center;">
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-slate-400)" stroke-width="2" style="width:32px;height:32px;margin:0 auto 8px;"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg>
 <div style="font-size:0.8rem; color:var(--sige-slate-500);">Sem cobrança</div>
 <?php if (!empty($mf['bloqueio_motivo'])): ?><div style="font-size:0.75rem; color:var(--sige-slate-400); margin-top:4px;"><?php echo esc_html($mf['bloqueio_motivo']); ?></div><?php endif; ?>
 <?php if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_financeiro')): ?>
 <button type="button" class="sige-btn-ghost btn-desbloquear-mes" style="font-size:0.75rem; padding:4px 8px; margin-top:12px; border-radius:4px; cursor:pointer;" data-id="<?php echo (int)$mf['bloqueio_id']; ?>" data-mes="<?php echo esc_attr($mf['mes_nome']); ?>"> Desbloquear</button>
 <?php endif; ?>
</div>
 <?php elseif ($is_isento): ?>
 <div style="padding:20px; text-align:center; color:var(--sige-info); font-weight:600;">Isento</div>
 <?php elseif ($is_pago): ?>
 <div style="padding:12px 20px; text-align:center; color:var(--sige-success); font-weight:600;">
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-success)" stroke-width="2" style="width:20px;height:20px;vertical-align:middle;margin-right:4px;"><polyline points="20 6 9 17 4 12"/></svg>
 Mensalidade Paga
</div>
 <?php if (!empty($extras_srv)): ?>
 <div class="sige-extras-section" style="border-top:1px solid var(--sige-slate-100);">
 <div class="sige-extras-title">&#128218; Servi&#231;os Extras</div>
 <?php
 // [v14.1.1 EXT-01] Agrupamento visual por centro: renderiza cabeçalho
 // quando o centro muda (só aparece se houver mais de um centro distinto nos extras).
 $_centros_distintos = array_unique(array_map(function($s){ return $s->centro_nome ?? ''; }, $extras_srv));
 $_agrupar_por_centro = count($_centros_distintos) > 1;
 $_last_centro = null;
 foreach ($extras_srv as $sx):
 $_this_centro = $sx->centro_nome ?? '';
 if ($_agrupar_por_centro && $_this_centro !== $_last_centro):
 ?>
 <div style="font-size:0.72rem; font-weight:600; color:var(--sige-slate-500); text-transform:uppercase; letter-spacing:.04em; padding:6px 0 2px; margin-top:4px;"><?php echo esc_html($_this_centro); ?></div>
 <?php
 $_last_centro = $_this_centro;
 endif;
 $ext_tipo = strtolower((string)($sx->tipo ?? ''));
 $ext_id = (int)$sx->id;
 $ext_val = (float)$sx->valor;
 $ext_nome = $sx->nome;
 $ext_status = $mapa_status[$mf['mes_num']][$ext_id] ?? null;
 $ext_pago = ($ext_status === 'pago');
 $ext_isento = ($ext_status === 'isento');
 $ext_pendente = ($ext_status === 'pendente' || $ext_status === 'parcial');
 ?>
 <div class="sige-extra-item">
 <label>
 <?php if ($ext_isento): ?>
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-info)" stroke-width="2" style="width:16px;height:16px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
 <?php elseif ($ext_pago): ?>
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-success)" stroke-width="2" style="width:16px;height:16px;"><polyline points="20 6 9 17 4 12"/></svg>
 <?php elseif ($ext_pendente): ?>
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-warning)" stroke-width="2" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
 <?php else: ?>
 <input type="checkbox" name="virtuais[]" value="EXT_<?php echo (int)$ext_id; ?>_<?php echo $mm; ?>" class="item-checkbox sige-checkbox" data-valor="<?php echo esc_attr($ext_val); ?>" onchange="recalcularTotal()" style="width:16px;height:16px;"<?php if (!empty($sx->pre_tickado)) echo " checked"; ?>>
 <?php endif; ?>
 <span class="sige-extra-name"><?php echo esc_html($ext_nome); ?></span>
</label>
 <span class="sige-extra-value"><?php echo number_format($ext_val, 0, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></span>
</div>
 <?php endforeach; ?>
</div>
 <?php endif; ?>
 <?php else: ?>
 <?php $mens_ja_pago = !empty($mf['detalhe']['mens_pago']); $mens_isento = !empty($mf['detalhe']['mens_isento']);
 // [v15.2.0 - F4] Detectar se já existe lançamento PENDENTE/PARCIAL
 // em "Dívidas Actuais" para mensalidade/transporte deste mês. Se sim,
 // desabilitar o cartão virtual para não gerar duplicata - o utilizador
 // deve pagar via tabela de dívidas.
 $_mid = $mensalidade_srv ? (int)$mensalidade_srv->id : 0;
 $_tid = $transporte_srv ? (int)$transporte_srv->id : 0;
 $_mens_status_mapa = $mapa_status[$mf['mes_num']][$_mid] ?? null;
 $_trans_status_mapa = $mapa_status[$mf['mes_num']][$_tid] ?? null;
 $mens_ja_lancado_pendente = in_array($_mens_status_mapa, ['pendente', 'parcial'], true);
 $trans_ja_lancado_pendente = in_array($_trans_status_mapa, ['pendente', 'parcial'], true);
 ?>
 <?php if ($val_m > 0): ?>
 <label class="sige-month-item <?php echo $mens_ja_pago ? 'sige-month-item-paid' : ($mens_isento ? 'sige-month-item-exempt' : ($mens_ja_lancado_pendente ? 'sige-month-item-pending' : '')); ?>" <?php if ($mens_ja_lancado_pendente): ?>title="Já existe lançamento pendente em 'Dívidas Actuais'. Pague por essa tabela para não duplicar."<?php endif; ?>>
 <?php if ($mens_isento): ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;color:var(--sige-info);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
 <?php elseif ($mens_ja_pago): ?> <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-success)" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg>
 <?php elseif ($mens_ja_lancado_pendente): ?> <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-warning)" stroke-width="2" style="width:18px;height:18px;" title="Pendente em Dívidas Actuais"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
 <?php else: ?> <input type="checkbox" name="virtuais[]" value="MENS_<?php echo $mm; ?>" class="item-checkbox sige-checkbox" data-valor="<?php echo esc_attr($val_m); ?>" data-multa="<?php echo esc_attr((!empty($mf['detalhe']['multa_inclusa']) ? 0 : ($tem_multa ? (float)($mf['detalhe']['multa_mensalidade'] ?? $mf['detalhe']['multa'] ?? 0) : 0))); ?>" onchange="recalcularTotal()">
 <?php endif; ?>
 <div>
 <span style="color:var(--sige-primary-700); font-weight:600;">Mensalidade</span><?php if ($mens_ja_lancado_pendente): ?> <small style="color:var(--sige-warning); font-size:0.65rem; font-weight:600;">&#9888; paga em Dívidas</small><?php endif; ?><br>
 <strong style="color:var(--sige-slate-800);"><?php echo number_format($val_m, 0, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
 <?php if ($desc_m_preview > 0 && !$mens_ja_pago && !$mens_isento && !$mens_ja_lancado_pendente): ?><br><span style="color:var(--sige-success); font-size:0.7rem;">-<?php echo number_format($desc_m_preview, 0, ',', '.'); ?> desc.</span><?php endif; ?>
 <?php if (!empty($mf['detalhe']['multa_mensalidade']) && (float)$mf['detalhe']['multa_mensalidade'] > 0 && empty($mf['detalhe']['multa_inclusa']) && !$mens_ja_pago && !$mens_isento && !$mens_ja_lancado_pendente): ?><br><span style="color:var(--sige-error); font-size:0.7rem;">+<?php echo number_format((float)$mf['detalhe']['multa_mensalidade'], 0, ',', '.'); ?> multa</span><?php endif; ?>
</div>
</label>
 <?php endif; ?>

 <?php $trans_ja_pago = !empty($mf['detalhe']['trans_pago']); $trans_isento = !empty($mf['detalhe']['trans_isento']); ?>
 <?php if ($val_t > 0): ?>
 <label class="sige-month-item <?php echo $trans_ja_pago ? 'sige-month-item-paid' : ($trans_isento ? 'sige-month-item-exempt' : ($trans_ja_lancado_pendente ? 'sige-month-item-pending' : '')); ?>" <?php if ($trans_ja_lancado_pendente): ?>title="Já existe lançamento pendente em 'Dívidas Actuais'."<?php endif; ?>>
 <?php if ($trans_isento): ?> <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:18px;height:18px;color:var(--sige-info);"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
 <?php elseif ($trans_ja_pago): ?> <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-success)" stroke-width="2" style="width:18px;height:18px;"><polyline points="20 6 9 17 4 12"/></svg>
 <?php elseif ($trans_ja_lancado_pendente): ?> <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-warning)" stroke-width="2" style="width:18px;height:18px;" title="Pendente em Dívidas Actuais"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
 <?php else: ?> <input type="checkbox" name="virtuais[]" value="TRAN_<?php echo $mm; ?>" class="item-checkbox sige-checkbox" data-valor="<?php echo esc_attr($val_t); ?>" data-multa="<?php echo esc_attr((!empty($mf['detalhe']['multa_inclusa']) ? 0 : ($tem_multa ? (float)($mf['detalhe']['multa_transporte'] ?? 0) : 0))); ?>" onchange="recalcularTotal()">
 <?php endif; ?>
 <div>
 <span style="color:var(--sige-primary-light); font-weight:600;">Transporte</span><?php if ($trans_ja_lancado_pendente): ?> <small style="color:var(--sige-warning); font-size:0.65rem; font-weight:600;">&#9888; paga em Dívidas</small><?php endif; ?><br>
 <strong style="color:var(--sige-slate-800);"><?php echo number_format($val_t, 0, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
 <?php if ($desc_t_preview > 0 && !$trans_ja_pago && !$trans_isento && !$trans_ja_lancado_pendente): ?><br><span style="color:var(--sige-success); font-size:0.7rem;">-<?php echo number_format($desc_t_preview, 0, ',', '.'); ?> desc.</span><?php endif; ?>
 <?php if (!empty($mf['detalhe']['multa_transporte']) && (float)$mf['detalhe']['multa_transporte'] > 0 && empty($mf['detalhe']['multa_inclusa']) && !$trans_ja_pago && !$trans_isento && !$trans_ja_lancado_pendente): ?><br><span style="color:var(--sige-error); font-size:0.7rem;">+<?php echo number_format((float)$mf['detalhe']['multa_transporte'], 0, ',', '.'); ?> multa</span><?php endif; ?>
</div>
</label>
 <?php endif; ?>
 
 <?php // [ALTERAÇÃO Abril/2026] Checkboxes individuais para serviços extras ?>
 <?php if (!empty($extras_srv) && !$is_pago && !$is_isento): ?>
 <div class="sige-extras-section">
 <div class="sige-extras-title">&#128218; Serviços Extras</div>
 <?php
 // [v14.1.1 EXT-01] Agrupamento visual por centro: renderiza cabeçalho
 // quando o centro muda (só aparece se houver mais de um centro distinto nos extras).
 $_centros_distintos = array_unique(array_map(function($s){ return $s->centro_nome ?? ''; }, $extras_srv));
 $_agrupar_por_centro = count($_centros_distintos) > 1;
 $_last_centro = null;
 foreach ($extras_srv as $sx):
 $_this_centro = $sx->centro_nome ?? '';
 if ($_agrupar_por_centro && $_this_centro !== $_last_centro):
 ?>
 <div style="font-size:0.72rem; font-weight:600; color:var(--sige-slate-500); text-transform:uppercase; letter-spacing:.04em; padding:6px 0 2px; margin-top:4px;"><?php echo esc_html($_this_centro); ?></div>
 <?php
 $_last_centro = $_this_centro;
 endif; 
 $ext_tipo = strtolower((string)($sx->tipo ?? ''));
 $ext_id = (int)$sx->id;
 $ext_val = (float)$sx->valor;
 $ext_nome = $sx->nome;
 // Verificar se já existe lançamento para este extra neste mês
 $ext_status = $mapa_status[$mf['mes_num']][$ext_id] ?? null;
 $ext_pago = ($ext_status === 'pago');
 $ext_isento = ($ext_status === 'isento');
 $ext_pendente = ($ext_status === 'pendente' || $ext_status === 'parcial');
 ?>
 <div class="sige-extra-item">
 <label>
 <?php if ($ext_isento): ?>
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-info)" stroke-width="2" style="width:16px;height:16px;"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
 <?php elseif ($ext_pago): ?>
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-success)" stroke-width="2" style="width:16px;height:16px;"><polyline points="20 6 9 17 4 12"/></svg>
 <?php elseif ($ext_pendente): ?>
 <svg viewBox="0 0 24 24" fill="none" stroke="var(--sige-warning)" stroke-width="2" style="width:16px;height:16px;"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
 <?php else: ?>
 <input type="checkbox" name="virtuais[]" value="EXT_<?php echo (int)$ext_id; ?>_<?php echo $mm; ?>" class="item-checkbox sige-checkbox" data-valor="<?php echo esc_attr($ext_val); ?>" onchange="recalcularTotal()" style="width:16px;height:16px;"<?php if (!empty($sx->pre_tickado)) echo " checked"; ?>>
 <?php endif; ?>
 <span class="sige-extra-name"><?php echo esc_html($ext_nome); ?></span>
</label>
 <span class="sige-extra-value"><?php echo number_format($ext_val, 0, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></span>
</div>
 <?php endforeach; ?>
</div>
 <?php endif; ?>

 <?php if ($is_parcial): ?>
 <div style="padding:6px 16px; font-size:0.75rem; background:var(--sige-warning-50); border-top:1px solid var(--sige-warning-100); color:var(--sige-slate-700);">Pago: <?php echo number_format((float)$mf['real_pago'], 0, ',', '.'); ?> | Falta: <strong style="color:var(--sige-error);"><?php echo number_format((float)$mf['real_falta'], 0, ',', '.'); ?></strong></div>
 <?php endif; ?>
 <?php if ($tem_multa): ?>
 <div style="padding:6px 16px; font-size:0.75rem; background:var(--sige-error-50); border-top:1px solid var(--sige-error-100); color:var(--sige-error); font-weight:600;">Multa: <?php echo number_format((float)$mf['detalhe']['multa'], 0, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></div>
 <?php endif; ?>

 <?php $mes_sem_lancamentos = !$mens_ja_pago && !$mens_isento && !$trans_ja_pago && !$trans_isento && !$is_parcial;
 if ($mes_sem_lancamentos && ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_financeiro'))): ?>
 <div style="padding:8px; text-align:center; border-top:1px dashed var(--sige-slate-200);">
 <button type="button" class="sige-btn-ghost btn-bloquear-mes" style="font-size:0.75rem; padding:4px 8px; border-radius:4px; cursor:pointer;" data-mes="<?php echo (int)$mf['mes_num']; ?>" data-nome="<?php echo esc_attr($mf['mes_nome']); ?>">Bloquear Mês</button>
</div>
 <?php endif; ?>
 <?php endif; ?>
</div>
 <?php endforeach; ?>
</div>
 <?php endif; ?>
</div>
</div>

 <?php /* SECÇÃO "3. Actividades Pontuais" REMOVIDA - Abril/2026 */ ?>

 <div class="sige-card sg-paypro-operation-card">
 <div class="sige-card-header">
 <h3 class="sige-card-title" style="color:var(--sige-orange);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/></svg> 3. Outros Serviços (Avulsos)
</h3>
</div>
 <div class="sige-card-body">
 <?php if (empty($outros_servicos)): ?>
 <div style="text-align:center; padding:20px; color:var(--sige-slate-400);">Nenhum serviço avulso disponível.</div>
 <?php else: ?>
 <div style="display:flex; flex-wrap:wrap; gap:16px;">
 <?php foreach($outros_servicos as $os): $nf_id = (int)$os->id; $nf_ja = isset($avulsos_ja_cobrados[$nf_id]); ?>
 <div class="sige-avulso-card" style="background:var(--color-white); border:1px solid var(--sige-slate-200); border-radius:12px; padding:16px; box-shadow:var(--sige-shadow-sm); min-width:200px; max-width:260px;">
 <label style="display:flex; align-items:flex-start; gap:10px; cursor:pointer;">
 <input type="checkbox"
 name="virtuais[]"
 value="<?php echo esc_attr('NF_' . $nf_id); ?>"
 class="item-checkbox sige-checkbox sige-avulso-check"
 data-valor="<?php echo esc_attr((float)$os->valor); ?>"
 data-svc-id="<?php echo $nf_id; ?>"
 data-preco-unit="<?php echo esc_attr((float)$os->valor); ?>"
 style="margin-top:3px;"
 onchange="sigeAvulsoChange(this)">
 <div class="sige-u-flex-1">
 <strong style="color:var(--sige-slate-800); font-size:0.9rem; display:block; margin-bottom:2px;"><?php echo esc_html($os->nome); ?></strong>
 <?php if ($nf_ja): ?>
 <span style="font-size:0.65rem; background:var(--sg-theme-soft,var(--color-brand-50)); color:var(--color-info-600); padding:1px 6px; border-radius:4px; margin-bottom:4px; display:inline-block;">J&#225; comprado antes</span>
 <?php endif; ?>
 <div style="font-size:0.78rem; color:var(--sige-slate-500); margin-bottom:2px;">
 Pre&#231;o unit.: <strong style="color:var(--sige-orange);"><?php echo number_format((float)$os->valor, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
</div>
</div>
</label>
 <div class="sige-avulso-qty" id="qty-wrap-<?php echo $nf_id; ?>"
 style="display:none; margin-top:10px; padding-top:10px; border-top:1px solid var(--sige-slate-100);">
 <div style="display:flex; align-items:center; gap:8px; flex-wrap:wrap;">
 <label style="font-size:0.72rem; font-weight:600; color:var(--sige-slate-600); text-transform:uppercase; letter-spacing:.04em; white-space:nowrap;">Quantidade</label>
 <input type="number"
 name="nf_qty[<?php echo $nf_id; ?>]"
 id="nf-qty-<?php echo $nf_id; ?>"
 value="1" min="1" max="99"
 class="sige-input sige-avulso-qty-input"
 data-svc-id="<?php echo $nf_id; ?>"
 data-preco-unit="<?php echo esc_attr((float)$os->valor); ?>"
 style="width:70px; height:36px; text-align:center; font-weight:600; padding:0 8px;"
 oninput="sigeAvulsoQtyChange(<?php echo $nf_id; ?>)">
 <span id="nf-total-<?php echo $nf_id; ?>" style="font-weight:700; color:var(--sige-orange); font-size:0.95rem; white-space:nowrap;">
 = <?php echo number_format((float)$os->valor, 2, ',', '.'); ?> MT
</span>
</div>
</div>
</div>
 <?php endforeach; ?>
</div>
 <?php endif; ?>
</div>
</div>

 <div class="sige-payment-summary" style="border-radius:16px; padding:24px; box-shadow:var(--sige-shadow-lg); margin-top:32px;">
 <div class="sige-payment-grid">
 <div class="sige-payment-options">
 <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end;">
 <div class="sige-form-group" style="flex:1; min-width:200px; margin:0;">
 <label class="sige-label">Método de Pagamento</label>
 <select name="metodo_pagamento" class="sige-select">
 <?php echo function_exists('sige_fin_metodos_pagamento_options_html')
     ? sige_fin_metodos_pagamento_options_html('', ['context' => 'pagamento'])
     : '<option value="numerario">Numerário</option><option value="mpesa">M-Pesa</option><option value="emola">E-Mola</option><option value="emola_comerciante">E-Mola Comerciante</option><option value="bim">Millennium BIM</option><option value="bci">BCI</option><option value="pagafacil">Paga Fácil</option><option value="pos_bci">POS BCI</option><option value="pos_bim">POS BIM</option><option value="pos_stbank">POS STBANK</option><option value="pos_moza">POS MOZA</option><option value="pos_nedbank">POS NEDBANK</option><option value="pos_fnb">POS FNB</option><option value="nib">Transferência (NIB)</option><option value="transferencia">Transferência Bancária</option>'; ?>
</select>
</div>
 <div class="sige-form-group" style="flex:2; min-width:200px; margin:0;">
 <label class="sige-label">Referência / Nº de talão</label>
 <input name="referencia_externa" type="text" class="sige-input" placeholder="Ex.: talão POS BCI, M-Pesa, NIB...">
</div>
</div>

 <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:center; margin-top:20px;">
 <label class="sg-paypro-payment-check" id="sige-multa-isenta-label" for="sige-multa-isenta" role="checkbox" aria-checked="false" tabindex="0">
 <input type="checkbox" id="sige-multa-isenta" name="multa_isenta" value="1" class="sige-checkbox" onchange="recalcularTotal();sigeToggleMultaIsentaUI();"> <span>Isentar multas</span>
</label>
 <input name="motivo_isencao" id="sige-motivo-isencao" placeholder="Motivo da isenção..." class="sige-input" style="height:42px; font-size:0.9rem; flex:1; min-width:220px;">
</div>

 <div style="display:flex; gap:16px; flex-wrap:wrap; align-items:flex-end; margin-top:20px; padding-top:20px; border-top:1px solid var(--sige-slate-200);">
 <div class="sige-form-group" style="margin:0;">
 <label class="sige-label" style="color:var(--sige-success);">⭐ Desconto Especial (MT)</label>
 <input name="desconto_especial" id="desconto_especial" type="number" step="0.01" min="0" class="sige-input" style="width:160px; border-color:var(--sige-success-300);" placeholder="Ex: 500" oninput="recalcularTotal();toggleMotivoDesc();">
</div>
 <div class="sige-form-group" id="motivo_desc_wrap" style="display:none; flex:1; min-width:200px; margin:0;">
 <label class="sige-label">Motivo do Desconto *</label>
 <input name="motivo_desconto_especial" id="motivo_desconto_especial" type="text" maxlength="255" class="sige-input" placeholder="Ex: Acordo com a direcção...">
</div>
</div>

 <?php if ($permite_parcial): ?>
 <div class="sige-form-group" style="margin-top:20px; padding-top:20px; border-top:1px solid var(--sige-slate-200);">
 <label class="sige-label" style="color:var(--sige-warning-700);">Valor Disponível (Pagamento Parcial)</label>
 <input name="valor_parcial" type="number" step="0.01" min="0" class="sige-input" style="width:200px; border-color:var(--sige-warning-300);" placeholder="Ex: 5000" oninput="recalcularTotal()">
 <p style="font-size:0.75rem; color:var(--sige-slate-500); margin:4px 0 0;">Preencha apenas se não pagar o total. O sistema distribui pelos itens por ordem.</p>
</div>
 <?php endif; ?>
</div>

 <div class="sige-payment-total">
 <div id="sige-adiant-banner" style="display:none; background:var(--sige-info-50); border:1px solid var(--sige-info-200); border-radius:8px; padding:10px; margin-bottom:16px; font-size:0.85rem; color:var(--sige-info-700); text-align:left;"></div>
 <p style="font-size:1rem; font-weight:600; color:var(--sige-slate-500); margin:0; text-transform:uppercase; letter-spacing:1px;">Total a Pagar</p>
 <p class="sige-total-value" id="display_total">0,00 <?php echo esc_html(sige_moeda()); ?></p>
 <button type="submit" name="sige_fin_pagar_submit" class="sige-btn sige-btn-primary" style="width:100%; height:56px; font-size:1.1rem; border-radius:12px; margin-top:16px;">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:22px;height:22px;"><polyline points="20 6 9 17 4 12"/></svg> Confirmar pagamento
</button>
</div>
</div>
</div>

</form>

<div id="sige-modal-confirmar-pagamento" class="sige-modal sg-paypro-modal" aria-hidden="true">
 <div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-confirmar-pagamento-title">
  <div class="sige-modal-header">
   <h3 id="sige-confirmar-pagamento-title">Confirmar pagamento</h3>
   <button type="button" class="sige-btn-ghost" style="border:none;background:transparent;font-size:1.5rem;line-height:1;cursor:pointer;" data-sige-act="sigeFecharConfirmacaoPagamento" data-sige-noargs>&times;</button>
  </div>
  <div class="sige-modal-body">
   <p style="margin:0;color:var(--color-slate-700);font-size:14px;line-height:1.55;">Antes de finalizar, confirme se o método de pagamento seleccionado está correcto. Esta verificação ajuda a evitar registos com método errado no caixa e nos relatórios.</p>
   <div class="sg-paypro-confirm-summary">
    <div class="sg-paypro-confirm-item"><strong>Método</strong><span id="sige-confirm-metodo">-</span></div>
    <div class="sg-paypro-confirm-item"><strong>Total</strong><span id="sige-confirm-total">-</span></div>
    <div class="sg-paypro-confirm-item"><strong>Referência</strong><span id="sige-confirm-referencia">-</span></div>
    <div class="sg-paypro-confirm-item"><strong>Aluno</strong><span id="sige-confirm-aluno"><?php echo esc_html($al->nome_completo ?? 'Aluno'); ?></span></div>
   </div>
   <div class="sg-paypro-confirm-warning">
    <span style="font-size:18px;line-height:1;">⚠️</span>
    <span>Se o método não estiver correcto, volte e altere antes de confirmar. Depois de registado, o pagamento passa a afectar o recibo, o caixa e os relatórios financeiros.</span>
   </div>
  </div>
  <div class="sige-modal-footer">
   <button type="button" class="sige-btn sige-btn-ghost" data-sige-act="sigeFecharConfirmacaoPagamento" data-sige-noargs>Voltar e corrigir</button>
   <button type="button" class="sige-btn sige-btn-primary" id="sige-btn-confirmar-pagamento-final">Sim, confirmar pagamento</button>
  </div>
 </div>
</div>

 <?php endif; ?>

</div>

<?php if ($aluno_id && !empty($irmaos_da_familia)): ?>
<!-- ══════════════════════════════════════════════════════════
 PAGAMENTO POR FAMÍLIA - aparece só quando há irmãos com pendentes
 ══════════════════════════════════════════════════════════ -->
<div style="margin-top:28px;" id="sige-secao-familia">
 <div class="sige-card" style="border:2px solid var(--color-brand-500); border-radius:16px; overflow:hidden;">
 <!-- Cabeçalho clicável -->
 <div class="sige-card-header" style="background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-600)); cursor:pointer;"
 data-sige-act="sigeFamiliaToggle" data-sige-noargs>
 <div style="display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:12px;">
 <h3 class="sige-card-title" style="color:var(--color-white); margin:0;">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
 <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
 <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
</svg>
 Pagamento por Fam&#237;lia
</h3>
 <div style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
 <span style="background:rgba(255,255,255,0.2); color:var(--color-white); padding:4px 12px; border-radius:20px; font-size:0.8rem; font-weight:600;">
 <?php echo count($irmaos_da_familia); ?> irm&#227;o<?php echo count($irmaos_da_familia) > 1 ? 's' : ''; ?> com pendentes
</span>
 <span style="background:rgba(255,255,255,0.25); color:var(--color-white); padding:4px 12px; border-radius:20px; font-size:0.85rem; font-weight:700;">
 <?php echo number_format(array_sum(array_column($irmaos_da_familia, 'total')), 2, ',', '.'); ?> MT
</span>
 <svg id="sige-familia-chevron" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2.5"
 style="width:20px;height:20px;transition:transform 0.3s;flex-shrink:0;">
 <polyline points="6 9 12 15 18 9"/>
</svg>
</div>
</div>
 <p style="color:rgba(255,255,255,0.75); font-size:0.8rem; margin:6px 0 0;">
 Um &#250;nico recibo consolidado para todos os irm&#227;os seleccionados.
 Clique para expandir.
</p>
</div>
 <!-- Corpo (expandível) -->
 <div id="sige-familia-body" style="display:none;">
 <form method="post" id="sige-form-familia">
 <?php wp_nonce_field('sige_fin_pagar_familia', '_wpnonce_familia'); ?>
 <input type="hidden" name="sige_fin_pagar_familia_submit" value="1">
 <input type="hidden" name="aluno_id_familia_ref" value="<?php echo (int)$aluno_id; ?>">
 <div class="sige-card-body">
 <!-- Acções rápidas -->
 <div style="display:flex; align-items:center; gap:10px; margin-bottom:16px; flex-wrap:wrap;">
 <button type="button" data-sige-act="sigeFamSelectAll" data-sige-args='[true]'
 class="sgk-btn sgk-btn-sec sgk-btn-sm" style="border-color:var(--color-brand-500); color:var(--color-brand-500);">
 &#9745; Seleccionar Tudo
</button>
 <button type="button" data-sige-act="sigeFamSelectAll" data-sige-args='[false]'
 class="sgk-btn sgk-btn-sec sgk-btn-sm">
 &#9744; Limpar
</button>
 <span id="sige-fam-sel-info" style="font-size:0.82rem; color:var(--color-brand-500); font-weight:600;"></span>
</div>
 <?php foreach ($irmaos_da_familia as $fam_item):
 $fi_aluno = $fam_item['aluno'];
 $fi_lancs = $fam_item['lancamentos'];
 $fi_total = $fam_item['total'];
 ?>
 <div style="margin-bottom:20px; border:1px solid var(--sige-slate-200); border-radius:12px; overflow:hidden;">
 <!-- Cabeçalho do irmão -->
 <div style="background:var(--sige-slate-50); padding:12px 16px; display:flex; align-items:center; justify-content:space-between; border-bottom:1px solid var(--sige-slate-100);">
 <div style="display:flex; align-items:center; gap:10px;">
 <div style="width:36px; height:36px; border-radius:50%; background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-400)); display:flex; align-items:center; justify-content:center; color:var(--color-white); font-weight:700; font-size:0.9rem; flex-shrink:0;">
 <?php echo esc_html(mb_strtoupper(mb_substr($fi_aluno->nome_completo, 0, 1))); ?>
</div>
 <div>
 <strong style="color:var(--sige-slate-900);"><?php echo esc_html($fi_aluno->nome_completo); ?></strong>
 <span style="font-size:0.75rem; color:var(--sige-slate-400); margin-left:6px;">N&#186; <?php echo esc_html($fi_aluno->numero_processo ?? '-'); ?></span>
</div>
</div>
 <strong style="color:var(--color-brand-500);"><?php echo number_format($fi_total, 2, ',', '.'); ?> <?php echo esc_html(sige_moeda()); ?></strong>
</div>
 <!-- Lançamentos do irmão -->
 <div class="sige-u-oxa">
 <table class="sige-table" style="margin:0; border-radius:0;">
 <thead>
 <tr>
 <th style="width:36px;"></th>
 <th>Servi&#231;o</th>
 <th>M&#234;s</th>
 <th>Vencimento</th>
 <th class="sige-text-right">A Pagar</th>
 <th class="sige-text-right">Multa</th>
</tr>
</thead>
 <tbody>
 <?php foreach ($fi_lancs as $fi_l):
 $fi_rest = (float)($fi_l->restante ?? 0);
 if ($fi_rest <= 0) continue;
 $fi_key = $fi_aluno->id . ':' . $fi_l->id;
 $fi_venc = !empty($fi_l->data_vencimento) ? sige_mz_date('d/m/Y', strtotime($fi_l->data_vencimento)) : '-';
 $fi_late = !empty($fi_l->data_vencimento) && strtotime($fi_l->data_vencimento) < time();
 ?>
 <tr>
 <td>
 <input type="checkbox"
 name="familia_lancs[]"
 value="<?php echo esc_attr($fi_key); ?>"
 class="sige-checkbox sige-fam-check"
 data-valor="<?php echo esc_attr($fi_rest); ?>"
 style="width:16px;height:16px;cursor:pointer;"
 onchange="sigeFamRecalc()">
</td>
 <td style="font-size:0.88rem; font-weight:500;"><?php echo esc_html($fi_l->servico_nome); ?></td>
 <td class="sige-font-mono" style="font-size:0.85rem;"><?php echo esc_html($fi_l->mes_referencia ?? '-'); ?></td>
 <td class="sige-font-mono" style="font-size:0.85rem;<?php echo $fi_late ? 'color:var(--sige-error);font-weight:600;' : ''; ?>">
 <?php echo $fi_venc; ?><?php if ($fi_late) echo ' <span style="font-size:0.7rem;">&#9888;</span>'; ?>
</td>
 <td class="sige-text-right" style="font-weight:700; color:var(--sige-error);"><?php echo number_format($fi_rest, 2, ',', '.'); ?></td>
 <td class="sige-text-right sige-font-mono" style="font-size:0.85rem; color:var(--sige-error);">
 <?php echo (float)($fi_l->valor_multa ?? 0) > 0 ? number_format((float)$fi_l->valor_multa, 2, ',', '.') : '-'; ?>
</td>
</tr>
 <?php endforeach; ?>
</tbody>
</table>
</div>
</div>
 <?php endforeach; ?>
 <!-- Opções de pagamento -->
 <div style="background:var(--sige-slate-50); border:1px solid var(--sige-slate-200); border-radius:12px; padding:20px; margin-top:8px;">
 <div style="display:flex; flex-wrap:wrap; gap:16px; align-items:flex-end;">
 <div class="sige-form-group" style="flex:1; min-width:180px; margin:0;">
 <label class="sige-label">M&#233;todo de Pagamento</label>
 <select name="fam_metodo_pagamento" class="sige-select">
 <?php echo function_exists('sige_fin_metodos_pagamento_options_html')
     ? sige_fin_metodos_pagamento_options_html('', ['context' => 'familia'])
     : '<option value="numerario">Numerário</option><option value="mpesa">M-Pesa</option><option value="emola">E-Mola</option><option value="emola_comerciante">E-Mola Comerciante</option><option value="bim">Millennium BIM</option><option value="bci">BCI</option><option value="pagafacil">Paga Fácil</option><option value="pos_bci">POS BCI</option><option value="pos_bim">POS BIM</option><option value="pos_stbank">POS STBANK</option><option value="pos_moza">POS MOZA</option><option value="pos_nedbank">POS NEDBANK</option><option value="pos_fnb">POS FNB</option><option value="nib">Transferência (NIB)</option><option value="transferencia">Transferência Bancária</option>'; ?>
</select>
</div>
 <div class="sige-form-group" style="flex:1; min-width:160px; margin:0;">
 <label class="sige-label">Refer&#234;ncia / Confirma&#231;&#227;o</label>
 <input type="text" name="fam_referencia_externa" class="sige-input" placeholder="N&#186; do tal&#227;o POS, M-Pesa, NIB, etc.">
</div>
 <!-- [v12.9.66] Data efectiva (família) -->
 <div class="sige-form-group" style="flex:0 0 200px; margin:0;">
 <label class="sige-label" style="display:flex; align-items:center; gap:6px;">📅 Data efectiva</label>
 <input
 type="date"
 name="fam_data_efectiva"
 class="sige-input sige-data-efectiva-fam"
 value="<?php echo esc_attr(current_time('Y-m-d')); ?>"
 max="<?php echo esc_attr(current_time('Y-m-d')); ?>"
 min="<?php echo esc_attr(wp_date('Y-m-d', strtotime('-90 days'))); ?>"
 >
 </div>
 <div class="sige-form-group" style="margin:0;">
 <label class="sige-label" style="display:flex; align-items:center; gap:6px; margin-bottom:0;">
 <input type="checkbox" name="fam_multa_isenta" value="1" style="width:16px;height:16px;"
 onchange="document.getElementById('fam_motivo_wrap').style.display=this.checked?'flex':'none'">
 Isentar Multas
</label>
 <div id="fam_motivo_wrap" style="display:none; margin-top:8px;">
 <input type="text" name="fam_motivo_isencao" class="sige-input" placeholder="Motivo" style="height:38px;">
</div>
</div>
</div>
 <!-- Total + Botão -->
 <div style="display:flex; align-items:center; justify-content:space-between; margin-top:16px; padding-top:16px; border-top:1px solid var(--sige-slate-200); flex-wrap:wrap; gap:12px;">
 <div>
 <div style="font-size:0.72rem; font-weight:600; text-transform:uppercase; letter-spacing:.05em; color:var(--sige-slate-500);">Total Seleccionado</div>
 <div id="sige-fam-total" style="font-size:1.8rem; font-weight:700; color:var(--color-brand-500); line-height:1.1;">0,00 <?php echo esc_html(sige_moeda()); ?></div>
 <div id="sige-fam-count" style="font-size:0.8rem; color:var(--sige-slate-400); margin-top:2px;">Seleccione lan&#231;amentos acima</div>
</div>
 <button type="submit" class="sige-btn"
 style="background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-600)); color:var(--color-white); height:52px; padding:0 28px; font-size:0.95rem; font-weight:700; border-radius:12px; box-shadow:0 4px 14px rgba(124,58,237,0.35);">
 <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="width:18px;height:18px;">
 <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/>
 <path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>
</svg>
 Pagar Fam&#237;lia - 1 Recibo
</button>
</div>
</div>
</div>
</form>
</div>
</div>
</div>
<?php endif; // irmaos_da_familia ?>

<?php if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || current_user_can('sige_director') || current_user_can('sige_financeiro')): ?>

<div id="sige-modal-cancelar" class="sige-modal">
 <div class="sige-modal-content">
 <div class="sige-modal-header">
 <h3 style="color:var(--sige-error);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg> Cancelar Lançamento</h3>
 <button type="button" class="sige-btn-ghost" style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" data-sige-act="fecharModalCancelar" data-sige-noargs>&times;</button>
</div>
 <div class="sige-modal-body">
 <p style="color:var(--sige-slate-600); margin-bottom:16px;">Tem a certeza que deseja cancelar este lançamento?</p>
 <div style="background:var(--sige-error-50); border:1px solid var(--sige-error-200); border-radius:8px; padding:16px; margin-bottom:20px; font-size:0.9rem; color:var(--sige-slate-800);">
 <div style="margin-bottom:4px;"><strong>Serviço:</strong> <span id="sige-cancel-servico">-</span></div>
 <div style="margin-bottom:4px;"><strong>Mês:</strong> <span id="sige-cancel-mes">-</span></div>
 <div><strong>Valor:</strong> <span id="sige-cancel-valor" style="color:var(--sige-error); font-weight:600;">-</span> <?php echo esc_html(sige_moeda()); ?></div>
</div>
 <form method="post" id="sige-form-cancelar">
 <?php wp_nonce_field('sige_cancelar_lancamento', '_wpnonce_cancel'); ?>
 <input type="hidden" name="sige_fin_cancelar_submit" value="1">
 <input type="hidden" name="aluno_id" value="<?php echo (int)$aluno_id; ?>">

 <div style="background:var(--color-slate-50);border:1px solid var(--sg-theme-soft,var(--color-brand-50));border-left:4px solid var(--color-info-500);border-radius:12px;padding:14px 16px;margin:0 0 18px;">
   <div style="font-weight:700;color:var(--sg-theme-primary-800,var(--color-ink-700));margin-bottom:6px;">Notificações do pagamento</div>
   <p style="margin:0 0 10px;color:var(--color-slate-700);font-size:13px;">Escolha se este pagamento deve enviar recibo/confirmação aos encarregados.</p>
   <input type="hidden" name="notificar_whatsapp" value="0">
   <input type="hidden" name="notificar_email" value="0">
   <label style="display:inline-flex;align-items:center;gap:8px;margin-right:18px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_whatsapp" value="1" checked> Enviar WhatsApp</label>
   <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_email" value="1" checked> Enviar E-mail</label>
 </div>
 <input type="hidden" name="lanc_id_cancel" id="sige-cancel-lanc-id" value="">
 <div class="sige-form-group">
 <label class="sige-label">Motivo do cancelamento <span style="color:var(--sige-error);">*</span></label>
 <textarea name="motivo_cancelamento" id="sige-cancel-motivo" rows="3" required class="sige-input" style="height:auto; padding:12px;" placeholder="Ex: Mês sem cobrança, lançamento duplicado..."></textarea>
</div>
</form>
</div>
 <div class="sige-modal-footer">
 <button type="button" class="sige-btn sige-btn-ghost" data-sige-act="fecharModalCancelar" data-sige-noargs>Voltar</button>
 <button type="submit" form="sige-form-cancelar" class="sige-btn sige-btn-error">Confirmar Cancelamento</button>
</div>
</div>
</div>

<div id="sige-modal-isentar" class="sige-modal" aria-hidden="true">
 <div class="sige-modal-content" role="dialog" aria-modal="true" aria-labelledby="sige-isentar-title">
 <div class="sige-modal-header">
 <h3 id="sige-isentar-title" style="color:var(--sige-primary);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M9 12l2 2 4-4"/></svg> Isentar dívida</h3>
 <button type="button" class="sige-btn-ghost sg-paypro-modal-close" data-sige-act="fecharModalIsentar" data-sige-noargs aria-label="Fechar">&times;</button>
</div>
 <div class="sige-modal-body">
 <p style="color:var(--sige-slate-700); margin:0 0 16px; line-height:1.55;">Esta acção marca a dívida como <strong>isenta</strong>. Ela deixa de contar como valor em aberto, mas mantém o histórico auditável.</p>
 <div style="background:var(--color-brand-50); border:1px solid var(--color-info-100); border-radius:12px; padding:14px 16px; margin-bottom:18px; font-size:0.9rem; color:var(--sige-slate-800);">
 <div style="margin-bottom:4px;"><strong>Serviço:</strong> <span id="sige-isentar-servico">-</span></div>
 <div style="margin-bottom:4px;"><strong>Mês:</strong> <span id="sige-isentar-mes">-</span></div>
 <div><strong>Valor em aberto:</strong> <span id="sige-isentar-valor">-</span></div>
 </div>
 <form method="post" id="sige-form-isentar">
 <?php wp_nonce_field('sige_isentar_lancamento', '_wpnonce_isentar'); ?>
 <input type="hidden" name="sige_fin_isentar_lancamento_submit" value="1">
 <input type="hidden" name="aluno_id" value="<?php echo (int)$aluno_id; ?>">
 <input type="hidden" name="lanc_id_isentar" id="sige-isentar-lanc-id" value="">
 <div class="sige-form-group">
 <label class="sige-label">Motivo da isenção (obrigatório)</label>
 <select class="sige-select sg-paypro-isencao-preset" id="sige-isentar-preset" aria-label="Motivo rápido de isenção">
 <option value="">- Seleccionar motivo rápido -</option>
 <option value="Aluno(a) transferido(a) / desistiu neste período">Aluno transferido/desistente</option>
 <option value="Decisão administrativa da direcção">Decisão administrativa</option>
 <option value="Bolsa ou isenção autorizada pela direcção">Bolsa/isenção autorizada</option>
 <option value="Cobrança indevida para este período">Cobrança indevida</option>
 <option value="Regularização de lançamento anterior">Regularização administrativa</option>
 </select>
 </div>
 <div class="sige-form-group">
 <input type="text" name="motivo_isencao_lancamento" id="sige-isentar-motivo" class="sige-input" placeholder="Descreva o motivo da isenção" required>
 </div>
</form>
</div>
 <div class="sige-modal-footer">
 <button type="button" class="sige-btn sige-btn-ghost" data-sige-act="fecharModalIsentar" data-sige-noargs>Voltar</button>
 <button type="button" class="sige-btn sige-btn-primary" data-sige-submit-form="sige-form-isentar" style="background:linear-gradient(135deg,var(--color-brand-500),var(--color-brand-400));color:var(--color-white);">Confirmar Isenção</button>
</div>
</div>
</div>

<div id="sige-modal-bloquear" class="sige-modal">
 <div class="sige-modal-content">
 <div class="sige-modal-header">
 <h3><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="4.93" y1="4.93" x2="19.07" y2="19.07"/></svg> Bloquear Mês</h3>
 <button type="button" class="sige-btn-ghost" style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" data-sige-act="fecharModalBloquear" data-sige-noargs>&times;</button>
</div>
 <div class="sige-modal-body">
 <p style="color:var(--sige-slate-800); margin-bottom:12px;">Marcar o mês de <strong id="sige-bloquear-mes-nome">-</strong> como <em>sem cobrança</em>?</p>
 <p style="font-size:0.85rem; color:var(--sige-slate-500); margin-bottom:20px;">Isto impede lançamentos neste mês. Pode desbloquear depois.</p>
 <form method="post" id="sige-form-bloquear">
 <?php wp_nonce_field('sige_bloquear_mes', '_wpnonce_bloquear'); ?>
 <input type="hidden" name="sige_fin_bloquear_mes_submit" value="1">
 <input type="hidden" name="aluno_id" value="<?php echo (int)$aluno_id; ?>">

 <div style="background:var(--color-slate-50);border:1px solid var(--sg-theme-soft,var(--color-brand-50));border-left:4px solid var(--color-info-500);border-radius:12px;padding:14px 16px;margin:0 0 18px;">
   <div style="font-weight:700;color:var(--sg-theme-primary-800,var(--color-ink-700));margin-bottom:6px;">Notificações do pagamento</div>
   <p style="margin:0 0 10px;color:var(--color-slate-700);font-size:13px;">Escolha se este pagamento deve enviar recibo/confirmação aos encarregados.</p>
   <input type="hidden" name="notificar_whatsapp" value="0">
   <input type="hidden" name="notificar_email" value="0">
   <label style="display:inline-flex;align-items:center;gap:8px;margin-right:18px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_whatsapp" value="1" checked> Enviar WhatsApp</label>
   <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_email" value="1" checked> Enviar E-mail</label>
 </div>
 <input type="hidden" name="mes_bloquear" id="sige-bloquear-mes-num" value="">
 <div class="sige-form-group">
 <label class="sige-label">Motivo (opcional)</label>
 <input type="text" name="motivo_bloqueio" id="sige-bloquear-motivo" value="Mês sem cobrança" class="sige-input">
</div>
</form>
</div>
 <div class="sige-modal-footer">
 <button type="button" class="sige-btn sige-btn-ghost" data-sige-act="fecharModalBloquear" data-sige-noargs>Cancelar</button>
 <button type="submit" form="sige-form-bloquear" class="sige-btn" style="background:var(--sige-slate-800); color:var(--color-white);">Confirmar Bloqueio</button>
</div>
</div>
</div>

<div id="sige-modal-desbloquear" class="sige-modal">
 <div class="sige-modal-content">
 <div class="sige-modal-header">
 <h3 style="color:var(--sige-success);"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg> Desbloquear Mês</h3>
 <button type="button" class="sige-btn-ghost" style="border:none; background:transparent; font-size:1.5rem; cursor:pointer;" data-sige-act="fecharModalDesbloquear" data-sige-noargs>&times;</button>
</div>
 <div class="sige-modal-body">
 <p style="color:var(--sige-slate-800); margin-bottom:12px;">Remover o bloqueio do mês de <strong id="sige-desbloquear-mes-nome">-</strong>?</p>
 <p style="font-size:0.85rem; color:var(--sige-slate-500);">O mês ficará disponível para lançamentos.</p>
 <form method="post" id="sige-form-desbloquear">
 <?php wp_nonce_field('sige_desbloquear_mes', '_wpnonce_desbloquear'); ?>
 <input type="hidden" name="sige_fin_desbloquear_mes_submit" value="1">
 <input type="hidden" name="aluno_id" value="<?php echo (int)$aluno_id; ?>">

 <div style="background:var(--color-slate-50);border:1px solid var(--sg-theme-soft,var(--color-brand-50));border-left:4px solid var(--color-info-500);border-radius:12px;padding:14px 16px;margin:0 0 18px;">
   <div style="font-weight:700;color:var(--sg-theme-primary-800,var(--color-ink-700));margin-bottom:6px;">Notificações do pagamento</div>
   <p style="margin:0 0 10px;color:var(--color-slate-700);font-size:13px;">Escolha se este pagamento deve enviar recibo/confirmação aos encarregados.</p>
   <input type="hidden" name="notificar_whatsapp" value="0">
   <input type="hidden" name="notificar_email" value="0">
   <label style="display:inline-flex;align-items:center;gap:8px;margin-right:18px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_whatsapp" value="1" checked> Enviar WhatsApp</label>
   <label style="display:inline-flex;align-items:center;gap:8px;font-weight:600;color:var(--color-black);"><input type="checkbox" name="notificar_email" value="1" checked> Enviar E-mail</label>
 </div>
 <input type="hidden" name="bloqueio_id" id="sige-desbloquear-id" value="">
</form>
</div>
 <div class="sige-modal-footer">
 <button type="button" class="sige-btn sige-btn-ghost" data-sige-act="fecharModalDesbloquear" data-sige-noargs>Cancelar</button>
 <button type="submit" form="sige-form-desbloquear" class="sige-btn sige-btn-primary" style="background:var(--sige-success);">Desbloquear</button>
</div>
</div>
</div>

<?php endif; ?>

<script <?php echo sige_csp_script_attr(); ?>>
var SIGE_DESC_6M = <?php echo (float)($config_fin->desc_adiant_6m_pct ?? 0); ?>;
var SIGE_DESC_12M = <?php echo (float)($config_fin->desc_adiant_12m_pct ?? 0); ?>;

// ── Avulso: mostrar/esconder quantidade ao marcar/desmarcar ─────────────
function sigeAvulsoChange(checkbox) {
 var svcId = checkbox.getAttribute('data-svc-id');
 var wrap = document.getElementById('qty-wrap-' + svcId);
 var qtyIn = document.getElementById('nf-qty-' + svcId);
 if (wrap) {
 wrap.style.display = checkbox.checked ? 'block' : 'none';
 if (!checkbox.checked && qtyIn) { qtyIn.value = 1; }
 }
 sigeAvulsoQtyChange(svcId);
 recalcularTotal();
}
// ── Avulso: recalcular total da linha ao mudar quantidade ─────────────────
function sigeAvulsoQtyChange(svcId) {
 var checkbox = document.querySelector('.sige-avulso-check[data-svc-id="' + svcId + '"]');
 var qtyInput = document.getElementById('nf-qty-' + svcId);
 var totalSpan = document.getElementById('nf-total-' + svcId);
 if (!checkbox || !qtyInput || !totalSpan) return;
 var precoUnit = parseFloat(checkbox.getAttribute('data-preco-unit')) || 0;
 var qty = Math.max(1, Math.min(99, parseInt(qtyInput.value) || 1));
 var total = precoUnit * qty;
 checkbox.setAttribute('data-valor', total.toFixed(2));
 totalSpan.textContent = '= ' + total.toLocaleString('pt-MZ', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' MT';
 recalcularTotal();
}
// ── Multi-lançamento: seleccionar/limpar todos os checkboxes de dívidas ──
function sigeSelectAllDividas(checked) {
 document.querySelectorAll('.sige-divida-check').forEach(function(cb) {
 if (cb.checked !== checked) {
 cb.checked = checked;
 cb.dispatchEvent(new Event('change'));
 }
 });
 var master = document.getElementById('sige-check-all-dividas');
 if (master) master.checked = checked;
 recalcularTotal();
 sigeUpdateDividaSel();
}
// ── Multi-lançamento: actualizar indicador "X lançamentos seleccionados" ─
function sigeUpdateDividaSel() {
 var checked = document.querySelectorAll('.sige-divida-check:checked');
 var all = document.querySelectorAll('.sige-divida-check');
 var info = document.getElementById('sige-dividas-sel-info');
 var master = document.getElementById('sige-check-all-dividas');
 if (!info) return;
 if (checked.length === 0) {
 info.textContent = '';
 } else {
 var total = 0;
 var multaIsentaEl = document.getElementById('sige-multa-isenta') || document.querySelector('input[name="multa_isenta"]');
 var multaIsenta = !!(multaIsentaEl && multaIsentaEl.checked);
 checked.forEach(function(cb) {
  var valor = parseFloat(cb.getAttribute('data-valor')) || 0;
  var multaEfectiva = parseFloat(cb.getAttribute('data-multa-efectiva')) || 0;
  total += multaIsenta ? Math.max(0, valor - multaEfectiva) : valor;
 });
 info.textContent = checked.length + ' lançamento' + (checked.length > 1 ? 's' : '')
 + ' - Total: ' + total.toLocaleString('pt-MZ', {minimumFractionDigits:2}) + ' MT';
 info.style.color = 'var(--sige-primary)';
 info.style.fontWeight = '700';
 }
 if (master) master.checked = (checked.length === all.length && all.length > 0);
}
function recalcularTotal() {
 let total = 0.0;
 var multaIsentaEl = document.getElementById('sige-multa-isenta') || document.querySelector('input[name="multa_isenta"]');
 var multaIsenta = !!(multaIsentaEl && multaIsentaEl.checked);
 document.querySelectorAll('.item-checkbox:checked').forEach(function(el) {
 var valor = parseFloat(el.getAttribute('data-valor')) || 0.0;
 var multaPreview = parseFloat(el.getAttribute('data-multa')) || 0.0;
 var multaEfectiva = parseFloat(el.getAttribute('data-multa-efectiva')) || 0.0;
 // Para dívidas reais, data-valor já inclui a multa; quando isenta, subtrai apenas a multa efectiva.
 // Para itens virtuais, data-multa é apenas pré-visualização adicional; quando isenta, não soma essa multa.
 if (multaIsenta) {
  total += Math.max(0, valor - multaEfectiva);
 } else {
  total += valor + multaPreview;
 }
 });

 var mesesCount = 0;
 document.querySelectorAll('.item-checkbox:checked').forEach(function(el) {
 if (el.value && el.value.match(/^MENS_\d{1,2}$/)) mesesCount++;
 });
 var descAdiantPct = 0, descAdiantLabel = '';
 if (mesesCount >= 10 && SIGE_DESC_12M > 0) {
 descAdiantPct = SIGE_DESC_12M;
 descAdiantLabel = mesesCount + ' meses → ' + SIGE_DESC_12M + '% desconto';
 } else if (mesesCount >= 6 && SIGE_DESC_6M > 0) {
 descAdiantPct = SIGE_DESC_6M;
 descAdiantLabel = mesesCount + ' meses → ' + SIGE_DESC_6M + '% desconto';
 }
 
 var descAdiantBanner = document.getElementById('sige-adiant-banner');
 if (descAdiantBanner) {
 if (descAdiantPct > 0) {
 var descAdiantValor = Math.round(total * descAdiantPct) / 100;
 descAdiantBanner.innerHTML = '⏩ <strong>' + descAdiantLabel + '</strong> = -' + descAdiantValor.toLocaleString('pt-MZ', {minimumFractionDigits:2}) + ' MT';
 descAdiantBanner.style.display = 'block';
 total = Math.max(0, total - descAdiantValor);
 } else {
 descAdiantBanner.style.display = 'none';
 }
 }

 var descEspInput = document.querySelector('input[name="desconto_especial"]');
 var descEspVal = descEspInput ? (parseFloat(descEspInput.value) || 0) : 0;
 if (descEspVal > 0) total = Math.max(0, total - descEspVal);

 var parcialInput = document.querySelector('input[name="valor_parcial"]');
 var parcialVal = parcialInput ? parseFloat(parcialInput.value) : 0;
 var efectivo = total;
 var sufixo = '';
 if (descEspVal > 0) sufixo = ' <span style="font-size:1rem;font-weight:600;color:var(--sige-success);">(c/ desc.)</span>';
 if (parcialVal > 0 && total > 0) {
 efectivo = Math.min(parcialVal, total);
 if (parcialVal < total) sufixo = ' <span style="font-size:1rem;font-weight:600;color:var(--sige-warning);">(parcial)</span>';
 }

 var el = document.getElementById('display_total');
 el.innerHTML = efectivo.toLocaleString('pt-MZ', {minimumFractionDigits: 2, maximumFractionDigits: 2}) + " <?php echo esc_js(sige_moeda()); ?>" + sufixo;
 el.style.color = (parcialVal > 0 && parcialVal < total) ? 'var(--sige-warning-700)' : (descEspVal > 0 ? 'var(--sige-success-700)' : 'var(--sige-primary-900)');
}

function toggleMotivoDesc() {
 var val = parseFloat(document.getElementById('desconto_especial').value) || 0;
 var wrap = document.getElementById('motivo_desc_wrap');
 var campo = document.getElementById('motivo_desconto_especial');
 if (val > 0) {
 wrap.style.display = 'block';
 campo.required = true;
 } else {
 wrap.style.display = 'none';
 campo.required = false;
 campo.value = '';
 }
}

var _formPag = document.getElementById('sige-form-pagamento-principal');
var _sigeConfirmandoPagamento = false;
function sigeMountModalToBody(id) {
 var modal = document.getElementById(id);
 if (!modal) return null;
 if (modal.parentNode !== document.body) {
  document.body.appendChild(modal);
 }
 return modal;
}
function sigeAnyModalVisible() {
 return !!document.querySelector('.sige-modal[style*="display: flex"], .sige-modal[style*="display:flex"]');
}
function sigeOpenModal(id) {
 var modal = sigeMountModalToBody(id);
 if (!modal) return null;
 modal.style.display = 'flex';
 modal.setAttribute('aria-hidden', 'false');
 document.documentElement.classList.add('sgk-modal-open');
 document.body.classList.add('sgk-modal-open');
 return modal;
}
function sigeCloseModal(id) {
 var modal = document.getElementById(id);
 if (!modal) return;
 modal.style.display = 'none';
 modal.setAttribute('aria-hidden', 'true');
 if (!sigeAnyModalVisible()) {
  document.documentElement.classList.remove('sgk-modal-open');
  document.body.classList.remove('sgk-modal-open');
 }
}
function sigePagamentoAviso(msg, tipo) {
 if (window.sigeUi && typeof sigeUi.toast === 'function') {
  sigeUi.toast(msg, tipo || 'aviso');
 } else {
  window.alert(msg);
 }
}
function sigePagamentoFocus(el) {
 if (!el) return;
 var box = el.closest ? (el.closest('.sige-card,.sg-paypro-scroll-table,.sige-payment-summary,.sige-payment-options') || el) : el;
 box.classList.add('sg-paypro-payment-focus');
 try { box.scrollIntoView({ behavior: 'smooth', block: 'center' }); } catch (e) { box.scrollIntoView(); }
 window.setTimeout(function(){ box.classList.remove('sg-paypro-payment-focus'); }, 1800);
 if (el.focus) window.setTimeout(function(){ try { el.focus({ preventScroll: true }); } catch(e) { el.focus(); } }, 250);
}
function sigePagamentoSeleccaoValida() {
 if (!_formPag) return false;
 return !!_formPag.querySelector('.item-checkbox:checked');
}
function sigePagamentoTotalNumerico() {
 var total = 0.0;
 if (!_formPag) return total;
 _formPag.querySelectorAll('.item-checkbox:checked').forEach(function(el) {
  var valor = parseFloat(el.getAttribute('data-valor')) || 0;
  var multaPreview = parseFloat(el.getAttribute('data-multa')) || 0;
  total += Math.max(0, valor + multaPreview);
 });
 var parcialInput = _formPag.querySelector('input[name="valor_parcial"]');
 var parcialVal = parcialInput ? parseFloat(parcialInput.value) : 0;
 if (parcialVal > 0 && total > 0) total = Math.min(parcialVal, total);
 return total;
}
function sigePagamentoPodeAbrirConfirmacao() {
 if (!_formPag) return false;
 if (!sigePagamentoSeleccaoValida()) {
  var primeiroItem = _formPag.querySelector('.item-checkbox') || document.querySelector('.sg-paypro-scroll-table,.sige-months-grid');
  sigePagamentoAviso('Seleccione pelo menos uma dívida, mensalidade ou serviço antes de confirmar o pagamento.', 'aviso');
  sigePagamentoFocus(primeiroItem);
  return false;
 }
 var metodoSelect = _formPag.querySelector('select[name="metodo_pagamento"]');
 if (!metodoSelect || !metodoSelect.value) {
  sigePagamentoAviso('Seleccione o método de pagamento antes de confirmar.', 'aviso');
  sigePagamentoFocus(metodoSelect || _formPag);
  return false;
 }
 recalcularTotal();
 if (sigePagamentoTotalNumerico() <= 0.005) {
  sigePagamentoAviso('O total a pagar está a zero. Confirme a selecção antes de avançar.', 'aviso');
  sigePagamentoFocus(document.getElementById('display_total') || _formPag);
  return false;
 }
 return true;
}
function sigeAbrirConfirmacaoPagamento() {
 if (!sigePagamentoPodeAbrirConfirmacao()) return;
 var modal = sigeOpenModal('sige-modal-confirmar-pagamento');
 if (!modal || !_formPag) return;
 var metodoSelect = _formPag.querySelector('select[name="metodo_pagamento"]');
 var refInput = _formPag.querySelector('input[name="referencia_externa"]');
 var totalEl = document.getElementById('display_total');
 var metodoTexto = metodoSelect && metodoSelect.options[metodoSelect.selectedIndex] ? metodoSelect.options[metodoSelect.selectedIndex].text.trim() : '-';
 document.getElementById('sige-confirm-metodo').textContent = metodoTexto || '-';
 document.getElementById('sige-confirm-referencia').textContent = refInput && refInput.value.trim() ? refInput.value.trim() : 'Sem referência';
 document.getElementById('sige-confirm-total').textContent = totalEl ? totalEl.textContent.trim() : '-';
 window.setTimeout(function(){
  var btn = document.getElementById('sige-btn-confirmar-pagamento-final');
  if (btn) btn.focus();
 }, 30);
}
function sigeFecharConfirmacaoPagamento() {
 sigeCloseModal('sige-modal-confirmar-pagamento');
}
window.sigeOpenModal = sigeOpenModal;
window.sigeCloseModal = sigeCloseModal;
if (_formPag) {
 _formPag.addEventListener('submit', function(e) {
 var desc = parseFloat(document.getElementById('desconto_especial')?.value) || 0;
 var mot = (document.getElementById('motivo_desconto_especial')?.value || '').trim();
 if (desc > 0 && !mot) {
 e.preventDefault();
 sigeUi.toast('Preencha o motivo do desconto especial para continuar.', 'aviso');
 document.getElementById('motivo_desconto_especial').focus();
 _sigeConfirmandoPagamento = false;
 var _bf = document.getElementById('sige-btn-confirmar-pagamento-final');
 if (_bf && window.sigeUi) sigeUi.aCarregar(_bf, false);
 return false;
 }
 if (!_sigeConfirmandoPagamento) {
  e.preventDefault();
  if (!sigePagamentoPodeAbrirConfirmacao()) return false;
  sigeAbrirConfirmacaoPagamento();
  return false;
 }
 });
 var _btnFinal = document.getElementById('sige-btn-confirmar-pagamento-final');
 if (_btnFinal) {
  _btnFinal.addEventListener('click', function() {
   if (_btnFinal.disabled) return;
   if (window.sigeUi) sigeUi.aCarregar(_btnFinal, true);
   _sigeConfirmandoPagamento = true;
   sigeFecharConfirmacaoPagamento();
   if (typeof _formPag.requestSubmit === 'function') {
    _formPag.requestSubmit();
   } else {
    _formPag.submit();
   }
  });
 }
}

function abrirModalCancelar(id, servico, mes, valor) {
 document.getElementById('sige-cancel-lanc-id').value = id;
 document.getElementById('sige-cancel-servico').textContent = servico || '-';
 document.getElementById('sige-cancel-mes').textContent = mes || '-';
 document.getElementById('sige-cancel-valor').textContent = valor || '-';
 document.getElementById('sige-cancel-motivo').value = '';
 sigeOpenModal('sige-modal-cancelar');
 window.setTimeout(function(){ var el = document.getElementById('sige-cancel-motivo'); if (el) el.focus(); }, 50);
}
function fecharModalCancelar() { sigeCloseModal('sige-modal-cancelar'); }

function abrirModalIsentar(id, servico, mes, valor) {
 document.getElementById('sige-isentar-lanc-id').value = id;
 document.getElementById('sige-isentar-servico').textContent = servico || '-';
 document.getElementById('sige-isentar-mes').textContent = mes || '-';
 document.getElementById('sige-isentar-valor').textContent = valor || '-';
 var motivo = document.getElementById('sige-isentar-motivo');
 var preset = document.getElementById('sige-isentar-preset');
 if (motivo) motivo.value = '';
 if (preset) preset.selectedIndex = 0;
 sigeOpenModal('sige-modal-isentar');
 window.setTimeout(function(){ if (motivo) motivo.focus(); }, 50);
}
function fecharModalIsentar() { sigeCloseModal('sige-modal-isentar'); }

function sigeSubmitFormCompat(formId) {
 var form = document.getElementById(formId);
 if (!form) return false;
 if (typeof form.requestSubmit === 'function') form.requestSubmit();
 else {
  var tmp = document.createElement('button');
  tmp.type = 'submit'; tmp.style.display = 'none';
  form.appendChild(tmp); tmp.click(); form.removeChild(tmp);
 }
 return true;
}

function sigeToggleMultaIsentaUI() {
 var cb = document.getElementById('sige-multa-isenta') || document.querySelector('input[name="multa_isenta"]');
 var label = document.getElementById('sige-multa-isenta-label') || (cb && cb.closest ? cb.closest('.sg-paypro-payment-check') : null);
 var motivo = document.getElementById('sige-motivo-isencao') || document.querySelector('input[name="motivo_isencao"]');
 var checked = !!(cb && cb.checked);
 if (label) {
  label.classList.toggle('is-checked', checked);
  label.setAttribute('aria-checked', checked ? 'true' : 'false');
 }
 if (motivo) {
  motivo.required = checked;
  motivo.setAttribute('aria-disabled', checked ? 'false' : 'true');
  motivo.placeholder = checked ? 'Informe o motivo da isenção da multa...' : 'Motivo da isenção...';
 }
}

function sigeBindPagamentoInteracoes() {
 var multaLabel = document.getElementById('sige-multa-isenta-label');
 var multaCb = document.getElementById('sige-multa-isenta') || document.querySelector('input[name="multa_isenta"]');
 if (multaLabel && multaCb && !multaLabel.dataset.sigeBound) {
  multaLabel.dataset.sigeBound = '1';
  multaLabel.addEventListener('click', function(e) {
   // Se o clique foi directamente no input, deixa o browser marcar/desmarcar naturalmente.
   if (e.target === multaCb) {
    window.setTimeout(function(){ sigeToggleMultaIsentaUI(); recalcularTotal(); sigeUpdateDividaSel(); }, 0);
    return;
   }
   e.preventDefault();
   multaCb.checked = !multaCb.checked;
   multaCb.dispatchEvent(new Event('change', { bubbles: true }));
  });
  multaLabel.addEventListener('keydown', function(e) {
   if (e.key === ' ' || e.key === 'Enter') {
    e.preventDefault();
    multaCb.checked = !multaCb.checked;
    multaCb.dispatchEvent(new Event('change', { bubbles: true }));
   }
  });
 }
 if (multaCb && !multaCb.dataset.sigeBound) {
  multaCb.dataset.sigeBound = '1';
  multaCb.addEventListener('change', function(){ sigeToggleMultaIsentaUI(); recalcularTotal(); sigeUpdateDividaSel(); });
 }
 sigeToggleMultaIsentaUI();
}

function abrirModalBloquear(mes, nome) {
 document.getElementById('sige-bloquear-mes-num').value = mes;
 document.getElementById('sige-bloquear-mes-nome').textContent = nome;
 document.getElementById('sige-bloquear-motivo').value = 'Mês sem cobrança';
 sigeOpenModal('sige-modal-bloquear');
}
function fecharModalBloquear() { sigeCloseModal('sige-modal-bloquear'); }

function abrirModalDesbloquear(id, mes) {
 document.getElementById('sige-desbloquear-id').value = id;
 document.getElementById('sige-desbloquear-mes-nome').textContent = mes;
 sigeOpenModal('sige-modal-desbloquear');
}
function fecharModalDesbloquear() { sigeCloseModal('sige-modal-desbloquear'); }

document.addEventListener('DOMContentLoaded', function() {
 // Iniciar estado dos avulsos, indicador de dívidas e controlos clicáveis
 sigeBindPagamentoInteracoes();
 sigeUpdateDividaSel();
 document.querySelectorAll('.sige-avulso-check').forEach(function(cb) {
 var wrap = document.getElementById('qty-wrap-' + cb.getAttribute('data-svc-id'));
 if (wrap) wrap.style.display = cb.checked ? 'block' : 'none';
 });
 document.addEventListener('click', function(e) {
 var btnCancel = e.target.closest ? e.target.closest('.btn-cancelar-lancamento') : null;
 if (btnCancel) {
  e.preventDefault();
  abrirModalCancelar(btnCancel.getAttribute('data-id'), btnCancel.getAttribute('data-servico'), btnCancel.getAttribute('data-mes'), btnCancel.getAttribute('data-valor'));
  return;
 }
 var btnIsentar = e.target.closest ? e.target.closest('.btn-isentar-lancamento') : null;
 if (btnIsentar) {
  e.preventDefault();
  abrirModalIsentar(btnIsentar.getAttribute('data-id'), btnIsentar.getAttribute('data-servico'), btnIsentar.getAttribute('data-mes'), btnIsentar.getAttribute('data-valor'));
  return;
 }
 var btnBloq = e.target.closest ? e.target.closest('.btn-bloquear-mes') : null;
 if (btnBloq) {
  e.preventDefault();
  abrirModalBloquear(btnBloq.getAttribute('data-mes'), btnBloq.getAttribute('data-nome'));
  return;
 }
 var btnDesbloq = e.target.closest ? e.target.closest('.btn-desbloquear-mes') : null;
 if (btnDesbloq) {
  e.preventDefault();
  abrirModalDesbloquear(btnDesbloq.getAttribute('data-id'), btnDesbloq.getAttribute('data-mes'));
  return;
 }
 var submitCompat = e.target.closest ? e.target.closest('[data-sige-submit-form]') : null;
 if (submitCompat) {
  e.preventDefault();
  sigeSubmitFormCompat(submitCompat.getAttribute('data-sige-submit-form'));
 }
 });
 var isencaoPreset = document.getElementById('sige-isentar-preset');
 if (isencaoPreset) {
  isencaoPreset.addEventListener('change', function(){
   var motivo = document.getElementById('sige-isentar-motivo');
   if (motivo && this.value) motivo.value = this.value;
  });
 }

 ['sige-modal-confirmar-pagamento', 'sige-modal-cancelar', 'sige-modal-isentar', 'sige-modal-bloquear', 'sige-modal-desbloquear', 'sige-modal-pagamento-sucesso', 'sige-modal-familia-sucesso'].forEach(function(id) {
 var modal = sigeMountModalToBody(id);
 if (modal) modal.addEventListener('click', function(e) { if (e.target === this) sigeCloseModal(id); });
 });

 document.addEventListener('keydown', function(e) {
 if (e.key === 'Escape') {
  sigeFecharConfirmacaoPagamento();
  fecharModalCancelar();
  fecharModalIsentar();
  fecharModalBloquear();
  fecharModalDesbloquear();
  sigeCloseModal('sige-modal-pagamento-sucesso');
  sigeCloseModal('sige-modal-familia-sucesso');
 }
 });
});
// ── Pagamento por Família ────────────────────────────────────────────────
function sigeFamiliaToggle() {
 var body = document.getElementById('sige-familia-body');
 var chevron = document.getElementById('sige-familia-chevron');
 if (!body) return;
 var isOpen = body.style.display !== 'none';
 body.style.display = isOpen ? 'none' : 'block';
 if (chevron) chevron.style.transform = isOpen ? '' : 'rotate(180deg)';
}
function sigeFamSelectAll(checked) {
 document.querySelectorAll('.sige-fam-check').forEach(function(cb) { cb.checked = checked; });
 sigeFamRecalc();
}
function sigeFamRecalc() {
 var checks = document.querySelectorAll('.sige-fam-check:checked');
 var total = 0;
 var count = checks.length;
 checks.forEach(function(cb) { total += parseFloat(cb.getAttribute('data-valor')) || 0; });
 var elTotal = document.getElementById('sige-fam-total');
 var elCount = document.getElementById('sige-fam-count');
 var elInfo = document.getElementById('sige-fam-sel-info');
 if (elTotal) elTotal.textContent = total.toLocaleString('pt-MZ', {minimumFractionDigits:2, maximumFractionDigits:2}) + ' MT';
 if (elCount) elCount.textContent = count > 0
 ? count + ' lançamento' + (count > 1 ? 's' : '') + ' seleccionado' + (count > 1 ? 's' : '')
 : 'Seleccione lançamentos acima';
 if (elInfo) elInfo.textContent = count > 0
 ? count + ' - ' + total.toLocaleString('pt-MZ', {minimumFractionDigits:2}) + ' MT'
 : '';
}
// Auto-expandir quando URL tem #familia
(function() {
 if (window.location.hash === '#familia') {
 var body = document.getElementById('sige-familia-body');
 var chev = document.getElementById('sige-familia-chevron');
 if (body) { body.style.display = 'block'; }
 if (chev) { chev.style.transform = 'rotate(180deg)'; }
 var sec = document.getElementById('sige-secao-familia');
 if (sec) setTimeout(function() { sec.scrollIntoView({behavior:'smooth'}); }, 400);
 }
 sigeFamRecalc();

 // ── [v12.9.66] Validação de Data Efectiva do Pagamento ──────────────
 (function() {
     var dataInput = document.getElementById('sige-data-efectiva-input');
     var statusEl  = document.getElementById('sige-data-efectiva-status');
     var nonceEl   = document.getElementById('sige-data-efectiva-nonce');
     if (!dataInput || !statusEl || !nonceEl) return;

     var ajaxurl = (typeof window.ajaxurl !== 'undefined') ? window.ajaxurl : '/wp-admin/admin-ajax.php';
     var hoje = dataInput.getAttribute('max'); // hoje, definido server-side
     var ultimoEstado = { aberta: true }; // por defeito permite
     var timer = null;

     function setStatus(html, color) {
         statusEl.innerHTML = html;
         statusEl.style.color = color || 'var(--color-slate-700)';
     }

     function disableSubmit(reason) {
         document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function(b) {
             b.disabled = true;
             b.style.opacity = '0.4';
             b.style.cursor = 'not-allowed';
             b.setAttribute('data-disabled-reason', reason);
         });
     }
     function enableSubmit() {
         document.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function(b) {
             if (b.getAttribute('data-disabled-reason')) {
                 b.disabled = false;
                 b.style.opacity = '';
                 b.style.cursor = '';
                 b.removeAttribute('data-disabled-reason');
             }
         });
     }

     function checkCaixa() {
         var data = dataInput.value;
         if (!data) {
             setStatus('', '');
             enableSubmit();
             return;
         }
         if (data === hoje) {
             setStatus('✓ Hoje (caixa do dia)', 'var(--color-success-500)');
             enableSubmit();
             ultimoEstado = { aberta: true };
             return;
         }

         setStatus('⏳ A verificar caixa...', 'var(--color-slate-500)');
         var fd = new FormData();
         fd.append('action', 'sige_check_caixa_data');
         fd.append('_wpnonce', nonceEl.value);
         fd.append('data', data);

         fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
             .then(function(r) { return r.text(); })
             .then(function(txt) {
                 var d;
                 try { d = JSON.parse(txt); }
                 catch (e) {
                     throw new Error('Resposta não-JSON: ' + txt.substring(0, 200));
                 }
                 if (!d || !d.success) {
                     throw new Error((d && d.data && d.data.message) || 'Erro ao verificar caixa');
                 }
                 ultimoEstado = d.data;
                 if (d.data.aberta) {
                     setStatus('✓ Caixa aberta nesta data - pode registar', 'var(--color-success-500)');
                     enableSubmit();
                 } else {
                     setStatus('🔒 ' + (d.data.mensagem || 'Caixa fechada'), 'var(--color-danger-700)');
                     disableSubmit('Caixa fechada na data indicada');
                 }
             })
             .catch(function(err) {
                 setStatus('⚠️ ' + (err.message || 'Erro de rede'), 'var(--color-danger-700)');
                 disableSubmit('Erro a verificar caixa');
             });
     }

     dataInput.addEventListener('change', function() {
         clearTimeout(timer);
         timer = setTimeout(checkCaixa, 200);
     });

     // Validação inicial caso a página seja carregada com data já preenchida
     if (dataInput.value && dataInput.value !== hoje) {
         checkCaixa();
     } else {
         setStatus('✓ Hoje (caixa do dia)', 'var(--color-success-500)');
     }
 })();
})();
</script>
