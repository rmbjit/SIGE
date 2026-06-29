<?php
/**
 * SIGE SoftGenial - MAP Oficial (Mapa de Aproveitamento Pedagógico)
 * Ficheiro: includes/map-pdf-handler.php
 *
 * Sprint 2 · M1 - Imprimível oficial estilo MAPED/A25 (Colégio Malisa).
 *
 * HISTÓRICO DE REVISÕES:
 *   v12.1    - Primeira entrega
 *   v12.1.1  - Fix: LEFT JOIN em falta na query de disciplinas
 *   v12.1.2  - FIX CRÍTICO: adopção da regra null=0 (consistente com a view
 *              "Anual" do boletim). Eliminada dependência de
 *              sige_calcular_situacao_final() que usa "ignorar null" e
 *              produzia Situação TRANSITA quando a view mostrava NÃO TRANSITA.
 *            - Fix: turma_nome vazia (COALESCE+NULLIF em vez de COALESCE só)
 *   v12.1.3  - Fix terminológico: label REPROVA agora é dinâmico.
 *              Classes de fim de ciclo (3ª, 6ª, 9ª, 12ª) → "NÃO TRANSITA"
 *              Classes intermédias (1ª, 2ª, 4ª, 5ª, etc.) → "NÃO PROGRIDE"
 *
 * Chamada:
 *   admin-post.php?action=sige_map_pdf&aluno_id=X&ano=Y&_wpnonce=Z
 *
 * Registo (ver sige-softgenial.php):
 *   add_action('admin_post_sige_map_pdf', 'sige_map_pdf_handler');
 *
 * REGRA NULL=0 (CRÍTICA):
 *   O MAP assume regra null=0 - valores não registados contam como zero em
 *   todos os cálculos (Méd, MT, MFD, NF). Isto é necessário para o MAP
 *   coincidir com o que é visível na vista "Anual (Consolidado)" do boletim.
 *
 *   O motor sige_calcular_situacao_final() usa semântica oposta ("ignorar
 *   null" - retorna NULL para qualquer nota em que um dos AC/ACP/AT esteja
 *   em falta) e NÃO PODE ser usado aqui, sob pena de o MAP contradizer o
 *   que o utilizador vê na interface (ex: aluno com notas incompletas
 *   aparece como TRANSITA no MAP mas NÃO TRANSITA na view Anual).
 *
 *   Esta divergência é um bug arquitectural aberto desde o Sprint 1 T4 e
 *   deve ser resolvido num refactor futuro (unificar numa só semântica).
 *   Até lá, ambos os handlers (boletim-view.php e map-pdf-handler.php)
 *   implementam a regra null=0 localmente.
 *
 * Dependências:
 *   - sige_is_nuclear_by_matriz()     [academic-logic.php, Sprint 1 T3]
 *   - sige_get_categoria_by_matriz()  [academic-logic.php, Sprint 1 T3]
 *   - sige_get_regra_academica()      [academic-logic.php]
 *   - sige_parse_classe_num()         [academic-logic.php]
 *   - sige_is_pauta_final_mode()      [academic-logic.php]
 *   - sige_get_escola_perfil()        [db-handler.php]
 *   - sige_get_escola_id()            [multitenancy.php]
 *
 * @since 12.1
 * @version 12.1.3
 */

if (!defined('ABSPATH')) exit;

// ─────────────────────────────────────────────────────────────────────────────
// HELPERS null=0 (idênticos aos de boletim-view.php; guarded para coexistir)
// ─────────────────────────────────────────────────────────────────────────────

/** Média de AC+ACP com regra null=0 */
if (!function_exists('sige_ap_med_acs')) {
    function sige_ap_med_acs($ac, $acp) {
        $a = ($ac === null || $ac === '') ? 0.0 : (float) $ac;
        $b = ($acp === null || $acp === '') ? 0.0 : (float) $acp;
        return round(($a + $b) / 2, 1);
    }
}

/** MT com regra null=0: MT = ROUND((2 × Méd + AT) / 3, 0) */
if (!function_exists('sige_ap_mt_zerofill')) {
    function sige_ap_mt_zerofill($ac, $acp, $at) {
        $a = ($ac  === null || $ac  === '') ? 0.0 : (float) $ac;
        $b = ($acp === null || $acp === '') ? 0.0 : (float) $acp;
        $c = ($at  === null || $at  === '') ? 0.0 : (float) $at;
        $med = ($a + $b) / 2;
        return (int) round((2 * $med + $c) / 3, 0);
    }
}

/** MFD com regra null=0: MFD = (MT1 + MT2 + MT3) / 3 */
if (!function_exists('sige_ap_mfd_zerofill')) {
    function sige_ap_mfd_zerofill($mt1, $mt2, $mt3) {
        $a = ($mt1 === null || $mt1 === '') ? 0.0 : (float) $mt1;
        $b = ($mt2 === null || $mt2 === '') ? 0.0 : (float) $mt2;
        $c = ($mt3 === null || $mt3 === '') ? 0.0 : (float) $mt3;
        return (int) round(($a + $b + $c) / 3, 0);
    }
}

/** NF com regra null=0: NF = (MFD × peso_mfd + Exame × peso_exame) / (peso_mfd + peso_exame) */
if (!function_exists('sige_ap_nf_zerofill')) {
    function sige_ap_nf_zerofill($mfd, $exame, $peso_mfd, $peso_exame) {
        $m = ($mfd   === null || $mfd   === '') ? 0.0 : (float) $mfd;
        $e = ($exame === null || $exame === '') ? 0.0 : (float) $exame;
        $pm = max(0, (int) $peso_mfd);
        $pe = max(0, (int) $peso_exame);
        $den = $pm + $pe;
        if ($den <= 0) $den = 100;
        return (int) round(($m * $pm + $e * $pe) / $den, 0);
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// ESCALA QUALITATIVA (SNE / Actualizacao_Sistema.docx Malisa)
// ─────────────────────────────────────────────────────────────────────────────
// 19-20 → E (Excelente)  ·  17-18 → MB  ·  14-16 → B  ·  10-13 → S  ·  0-9 → NS
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('sige_map_escala')) {
    function sige_map_escala($nota) {
        if ($nota === null || $nota === '') return '';
        $n = (int) $nota;
        if ($n <  10) return 'NS';
        if ($n <= 13) return 'S';
        if ($n <= 16) return 'B';
        if ($n <= 18) return 'MB';
        return 'E';
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// NÚMERO POR EXTENSO (0-20) - para "Média Final: 15 (quinze) Valores"
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('sige_num_extenso')) {
    function sige_num_extenso($n) {
        if ($n === null || $n === '') return '';
        $n = (int) round((float) $n);
        if ($n < 0 || $n > 20) return '';
        static $extensos = [
            0  => 'zero',       1  => 'um',          2  => 'dois',
            3  => 'três',       4  => 'quatro',      5  => 'cinco',
            6  => 'seis',       7  => 'sete',        8  => 'oito',
            9  => 'nove',       10 => 'dez',         11 => 'onze',
            12 => 'doze',       13 => 'treze',       14 => 'catorze',
            15 => 'quinze',     16 => 'dezasseis',   17 => 'dezassete',
            18 => 'dezoito',    19 => 'dezanove',    20 => 'vinte',
        ];
        return $extensos[$n] ?? '';
    }
}


// ─────────────────────────────────────────────────────────────────────────────
// PERMISSÕES + MOTOR ÚNICO DO MAP
// ─────────────────────────────────────────────────────────────────────────────
if (!function_exists('sige_map_user_can_emitir')) {
    function sige_map_user_can_emitir() {
        if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
            return true;
        }
        $perms_ok = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
                 || current_user_can('sige_director')
                 || current_user_can('sige_pedagogico')
                 || current_user_can('sige_secretario')
                 || current_user_can('sige_secretaria_geral')
                 || current_user_can('sige_assistente');

        if (function_exists('sige_user_can_any_secure')) {
            $perms_ok = sige_user_can_any_secure(
                ['academico.pautas_emitir', 'academico.boletins_emitir', 'documentos.emitir_finais'],
                ['sige_director', 'sige_pedagogico', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente']
            );
        }
        return (bool) $perms_ok;
    }
}

if (!function_exists('sige_map_guard')) {
    function sige_map_guard($nonce_action) {
        if (!sige_map_user_can_emitir()) {
            wp_die('Acesso negado. Não tem permissões para gerar o MAP.', 'SIGE - MAP', ['response' => 403]);
        }
        if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), $nonce_action)) {
            wp_die('Nonce inválido ou expirado. Recarregue a página e tente de novo.', 'SIGE - MAP', ['response' => 403]);
        }
    }
}

if (!function_exists('sige_map_build_data')) {
    /**
     * Constrói os dados de um MAP individual.
     * O mesmo motor é usado para impressão individual e em massa por turma.
     */
    function sige_map_build_data($aluno_id, $ano, $forced_turma_id = 0) {
        global $wpdb;

        $aluno_id = (int) $aluno_id;
        $ano      = (int) $ano;
        $forced_turma_id = (int) $forced_turma_id;
        $p   = $wpdb->prefix;
        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

        if ($aluno_id <= 0 || $ano <= 0) {
            wp_die('Parâmetros em falta: aluno_id e ano são obrigatórios.', 'SIGE - MAP');
        }

        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nome_completo, genero, data_nascimento, numero_processo
             FROM {$p}sige_alunos
             WHERE id = %d AND escola_id = %d
             LIMIT 1",
            $aluno_id, $eid
        ));

        if (!$aluno) {
            wp_die('Aluno não encontrado (id=' . esc_html($aluno_id) . ').', 'SIGE - MAP');
        }

        $where_turma = $forced_turma_id > 0 ? ' AND m.turma_id = %d ' : '';
        $params = [$aluno_id, $ano, $eid];
        if ($forced_turma_id > 0) $params[] = $forced_turma_id;

        $matricula = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*,
                    t.classe, t.nivel_ensino, t.turno,
                    COALESCE(NULLIF(t.nome_turma, ''), t.nome) AS nome_turma
             FROM {$p}sige_matriculas m
             JOIN {$p}sige_turmas t ON t.id = m.turma_id AND t.escola_id = m.escola_id
             WHERE m.aluno_id = %d
               AND m.ano_lectivo = %d
               AND m.escola_id = %d
               {$where_turma}
             ORDER BY m.id DESC
             LIMIT 1",
            $params
        ));

        if (!$matricula) {
            wp_die(sprintf('Sem matrícula para o aluno em %d.', $ano), 'SIGE - MAP');
        }

        $turma_id   = (int) $matricula->turma_id;
        $classe_raw = (string) $matricula->classe;

        // [12.11.9.15] Defesa por URL: professor só gera MAP de aluno/turma atribuídos ao seu perfil.
        if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
            $prof_id_scope = function_exists('sige_get_professor_atual_id') ? (int) sige_get_professor_atual_id() : 0;
            if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, $prof_id_scope, $eid)) {
                wp_die('Acesso negado. Este MAP pertence a uma turma que não está atribuída ao seu perfil de professor.', 'SIGE - MAP', ['response' => 403]);
            }
        }

        $activo_sql = function_exists('sige_aluno_matricula_activa_sql')
            ? sige_aluno_matricula_activa_sql('a', 'm')
            : "(m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo'))";

        // Numero de chamada determinista (ordem canonica nome ASC, id ASC; populacao
        // canonica aluno activo E matricula activa). Substitui o varrimento por @rn,
        // que numerava pela ordem de insercao da tabela e nao pela ordem alfabetica.
        $numero_chamada = function_exists('sige_turma_numero_chamada')
            ? (int) sige_turma_numero_chamada((int) $aluno_id, (int) $turma_id, (int) $ano, (int) $eid)
            : 0;

        $disciplinas = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.nome, d.sigla, d.categoria,
                    COALESCE(
                        mc.ordem_pauta,
                        CASE d.sigla
                            WHEN 'POR'        THEN 1
                            WHEN 'MAT'        THEN 2
                            WHEN 'MAT(4-6)'   THEN 2
                            WHEN 'CS'         THEN 3
                            WHEN 'CN'         THEN 4
                            WHEN 'ING'        THEN 5
                            WHEN 'ED.VISUAL'  THEN 6
                            WHEN 'ED. V'      THEN 6
                            WHEN 'OF'         THEN 7
                            WHEN 'ED. FISICA' THEN 8
                            WHEN 'ED.FISICA'  THEN 8
                            ELSE 99
                        END
                    ) AS ordem
             FROM {$p}sige_disciplinas d
             LEFT JOIN {$p}sige_matriz_curricular mc
                    ON mc.disciplina_id = d.id
                   AND mc.classe = %s
                   AND mc.escola_id = %d
             WHERE d.escola_id = %d
               AND d.id IN (
                    SELECT disciplina_id FROM {$p}sige_turma_disciplinas
                    WHERE turma_id = %d AND escola_id = %d
                    UNION
                    SELECT disciplina_id FROM {$p}sige_matriz_curricular
                    WHERE classe = %s AND escola_id = %d
               )
             ORDER BY ordem, d.nome",
            $classe_raw, $eid, $eid, $turma_id, $eid, $classe_raw, $eid
        ));

        if (function_exists('sige_get_categoria_by_matriz')) {
            foreach ($disciplinas as &$_d) {
                if (isset($_d->id)) {
                    $_d->categoria = sige_get_categoria_by_matriz($classe_raw, (int) $_d->id, $eid);
                }
            }
            unset($_d);
        }
        if (function_exists('sige_apply_categoria_oficial_disciplinas')) {
            $disciplinas = sige_apply_categoria_oficial_disciplinas($disciplinas, $classe_raw);
        }
        if (function_exists('sige_sort_disciplinas_oficial')) {
            $disciplinas = sige_sort_disciplinas_oficial($disciplinas);
        }

        $notas_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT disciplina_id, trimestre, nota_ac, nota_acp, nota_exame, nota_at
             FROM {$p}sige_notas
             WHERE aluno_id = %d
               AND turma_id = %d
               AND ano_lectivo = %d
               AND escola_id = %d
               AND (status IS NULL OR status = 'aprovado')",
            $aluno_id, $turma_id, $ano, $eid
        ));
        $ni = [];
        foreach ($notas_raw as $n) {
            $ni[(int) $n->disciplina_id][(int) $n->trimestre] = $n;
        }

        $classe_num = function_exists('sige_parse_classe_num')
                    ? (int) sige_parse_classe_num($classe_raw)
                    : (int) filter_var($classe_raw, FILTER_SANITIZE_NUMBER_INT);

        $regra = function_exists('sige_get_regra_academica')
               ? sige_get_regra_academica($ano, $classe_num)
               : null;

        $nota_min_aprov   = $regra ? (int) $regra->nota_minima_aprovacao : 10;
        $media_min_global = $regra ? (int) $regra->media_minima_global   : 10;
        $max_progride     = $regra ? (int) $regra->max_negativas_progride : 0;
        $max_transita     = $regra ? (int) $regra->max_negativas_transita : 2;
        $usa_media_global = $regra ? ((int) $regra->usa_media_global === 1) : true;
        $eh_fim_ciclo     = $regra ? ((int) $regra->eh_fim_ciclo === 1) : in_array($classe_num, [3, 6, 9, 12], true);
        $exige_exame      = $regra ? ((int) $regra->exige_exame === 1) : false;
        $peso_mfd         = $regra ? (int) $regra->peso_mfd   : 60;
        $peso_ex          = $regra ? (int) $regra->peso_exame : 40;

        $label_avaliacao_final = function_exists('sige_label_avaliacao_final') ? sige_label_avaliacao_final($classe_num) : ($classe_num === 3 ? 'AF' : 'Exame');
        $label_nota_final      = function_exists('sige_label_nota_final')      ? sige_label_nota_final($classe_num)      : ($classe_num === 3 ? 'MF' : 'NF');
        $label_mapa_final      = ($classe_num === 3) ? 'AF' : (($eh_fim_ciclo && $exige_exame) ? $label_nota_final : 'MFD');

        $pf_mode = function_exists('sige_is_pauta_final_mode')
                 ? (bool) sige_is_pauta_final_mode($ano, $turma_id)
                 : false;

        $linhas = [];
        $negativas = 0;
        $notas_nucleares = [];

        foreach ($disciplinas as $d) {
            $did = (int) $d->id;
            $mts = [];
            for ($t = 1; $t <= 3; $t++) {
                $n = $ni[$did][$t] ?? null;
                $ac  = $n ? $n->nota_ac    : null;
                $acp = $n ? $n->nota_acp   : null;
                $at  = $n ? $n->nota_exame : null;
                $mts[$t] = sige_ap_mt_zerofill($ac, $acp, $at);
            }

            $mfd = sige_ap_mfd_zerofill($mts[1], $mts[2], $mts[3]);
            $categoria = strtolower((string) ($d->categoria ?? 'complementar'));
            $is_nuclear = ($categoria === 'nuclear');
            $mostra_final_aqui = $pf_mode && $eh_fim_ciclo && $exige_exame && $is_nuclear;

            // Avaliação final do T3: AF apenas na 3.ª classe; restantes classes usam Exame.
            // O campo técnico continua a ser nota_at por compatibilidade com a grelha actual.
            $avaliacao_final = $ni[$did][3]->nota_at ?? null;
            $nf = $mostra_final_aqui ? sige_ap_nf_zerofill($mfd, $avaliacao_final, $peso_mfd, $peso_ex) : null;
            $nota_final = $mostra_final_aqui ? $nf : $mfd;

            if ($is_nuclear) {
                $notas_nucleares[] = (int) $nota_final;
                if ((int) $nota_final < $nota_min_aprov) {
                    $negativas++;
                }
            }

            $linhas[] = [
                'id'        => $did,
                'nome'      => (string) $d->nome,
                'sigla'     => (string) $d->sigla,
                'categoria' => $categoria,
                'mt1'       => (int) $mts[1],
                'mt2'       => (int) $mts[2],
                'mt3'       => (int) $mts[3],
                'mfd'       => (int) $mfd,
                'avaliacao_final' => ($avaliacao_final === null || $avaliacao_final === '') ? null : (int) round((float) $avaliacao_final, 0),
                'exame'     => ($avaliacao_final === null || $avaliacao_final === '') ? null : (int) round((float) $avaliacao_final, 0),
                'nf'        => $nf,
                'af'        => (int) $nota_final,
                'nota_final_mapa' => (int) $nota_final,
                'escala'    => sige_map_escala((int) $nota_final),
            ];
        }

        $media_global = count($notas_nucleares) > 0
                      ? (int) round(array_sum($notas_nucleares) / count($notas_nucleares), 0)
                      : 0;

        $sit_raw = 'PROGRIDE';
        if ($usa_media_global && $media_global < $media_min_global) {
            $sit_raw = 'REPROVA';
        } else {
            if ($negativas <= $max_progride)      $sit_raw = 'PROGRIDE';
            elseif ($negativas <= $max_transita)  $sit_raw = 'TRANSITA';
            else                                  $sit_raw = 'REPROVA';
        }
        if ($sit_raw !== 'REPROVA') {
            $sit_raw = $eh_fim_ciclo ? 'TRANSITA' : 'PROGRIDE';
        }

        $situacao_map = [
            'PROGRIDE' => 'PROGRIDE',
            'TRANSITA' => 'TRANSITA',
            'REPROVA'  => $eh_fim_ciclo ? 'NÃO TRANSITA' : 'NÃO PROGRIDE',
        ];
        $situacao_txt = $situacao_map[$sit_raw] ?? $sit_raw;

        $perfil      = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
        $escola_nome = $perfil->nome_escola          ?? get_bloginfo('name');
        $logo_url    = !empty($perfil->logo_documentos_url)
                     ? $perfil->logo_documentos_url
                     : ($perfil->logo_sistema_url ?? '');
        $endereco    = $perfil->endereco_escola      ?? '';
        $email_esc   = $perfil->email_institucional  ?? '';

        return [
            'aluno'            => $aluno,
            'matricula'        => $matricula,
            'ano'              => (int) $ano,
            'classe'           => $classe_raw,
            'turma_nome'       => (string) $matricula->nome_turma,
            'turno'            => (string) ($matricula->turno ?? ''),
            'numero_chamada'   => $numero_chamada > 0 ? $numero_chamada : null,
            'linhas'           => $linhas,
            'situacao'         => $situacao_txt,
            'situacao_raw'     => $sit_raw,
            'media_final'      => (int) $media_global,
            'media_final_ext'  => sige_num_extenso((int) $media_global),
            'negativas'        => (int) $negativas,
            'eh_fim_ciclo'     => (bool) $eh_fim_ciclo,
            'exige_exame'      => (bool) $exige_exame,
            'pauta_final'      => (bool) $pf_mode,
            'label_avaliacao_final' => $label_avaliacao_final,
            'label_nota_final'      => $label_nota_final,
            'label_mapa_final'      => $label_mapa_final,
            'escola_nome'      => $escola_nome,
            'logo_url'         => $logo_url,
            'endereco'         => $endereco,
            'email_escola'     => $email_esc,
        ];
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDLER INDIVIDUAL
// ─────────────────────────────────────────────────────────────────────────────
function sige_map_pdf_handler() {
    sige_map_guard('sige_map_pdf');

    $aluno_id = isset($_GET['aluno_id']) ? intval($_GET['aluno_id']) : 0;
    $ano      = isset($_GET['ano'])      ? intval($_GET['ano'])      : 0;
    $turma_id = isset($_GET['turma_id']) ? intval($_GET['turma_id']) : 0;

    $data = sige_map_build_data($aluno_id, $ano, $turma_id);
    $data_pages = [$data];
    $map_batch = false;

    header('Content-Type: text/html; charset=UTF-8');
    include SIGE_PATH . 'admin/map-oficial-template.php';
    exit;
}

// ─────────────────────────────────────────────────────────────────────────────
// HANDLER EM MASSA / POR TURMA
// ─────────────────────────────────────────────────────────────────────────────
function sige_map_turma_pdf_handler() {
    sige_map_guard('sige_map_turma_pdf');

    global $wpdb;
    $p   = $wpdb->prefix;
    $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

    $turma_id = isset($_GET['turma_id']) ? intval($_GET['turma_id']) : 0;
    $ano      = isset($_GET['ano'])      ? intval($_GET['ano'])      : 0;

    if ($turma_id <= 0 || $ano <= 0) {
        wp_die('Parâmetros em falta: turma_id e ano são obrigatórios para impressão em massa.', 'SIGE - MAP');
    }

    $turma = $wpdb->get_row($wpdb->prepare(
        "SELECT id, classe, COALESCE(NULLIF(nome_turma,''), nome) AS nome_turma
         FROM {$p}sige_turmas
         WHERE id = %d AND escola_id = %d
         LIMIT 1",
        $turma_id, $eid
    ));
    if (!$turma) {
        wp_die('Turma não encontrada para gerar o MAP em massa.', 'SIGE - MAP');
    }

    // [12.11.9.15] Defesa por URL: professor só gera MAP em massa das suas turmas.
    if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
        $prof_id_scope = function_exists('sige_get_professor_atual_id') ? (int) sige_get_professor_atual_id() : 0;
        if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, $prof_id_scope, $eid)) {
            wp_die('Acesso negado. Esta turma não está atribuída ao seu perfil de professor.', 'SIGE - MAP', ['response' => 403]);
        }
    }

    $activo_sql = function_exists('sige_aluno_matricula_activa_sql')
        ? sige_aluno_matricula_activa_sql('a', 'm')
        : "(m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo'))";

    $alunos = $wpdb->get_col($wpdb->prepare(
        "SELECT a.id
         FROM {$p}sige_matriculas m
         JOIN {$p}sige_alunos a ON a.id = m.aluno_id AND a.escola_id = m.escola_id
         WHERE m.turma_id = %d
           AND m.ano_lectivo = %d
           AND m.escola_id = %d
           AND {$activo_sql}
         ORDER BY a.nome_completo ASC, a.id ASC",
        $turma_id, $ano, $eid
    ));

    if (empty($alunos)) {
        wp_die('Não existem alunos activos nesta turma/ano para gerar o MAP em massa.', 'SIGE - MAP');
    }

    $data_pages = [];
    foreach ($alunos as $aid) {
        $data_pages[] = sige_map_build_data((int) $aid, $ano, $turma_id);
    }

    $data = $data_pages[0];
    $map_batch = true;
    $map_batch_title = 'MAP - Turma ' . (string) $turma->nome_turma . ' - ' . (int) $ano;

    header('Content-Type: text/html; charset=UTF-8');
    include SIGE_PATH . 'admin/map-oficial-template.php';
    exit;
}
