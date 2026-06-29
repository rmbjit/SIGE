<?php
/**
 * SIGE SoftGenial - ACTA do Conselho de Notas (ACN/A25)
 * Ficheiro: includes/acta-pdf-handler.php
 *
 * Sprint 2 · M2 - Imprimível oficial estilo ACN/A25 (Colégio Malisa).
 * Documento legal ao nível da turma + trimestre.
 *
 * HISTÓRICO DE REVISÕES:
 *   v12.2.0 - Primeira entrega. Cabeçalho + Presentes/Ausentes + Nota Votada +
 *             Estatísticas Demográficas (M/HM) + Estatísticas por Disciplina +
 *             Quadro de Honra + Cumprimento dos Programas + Observações.
 *
 * ROUTES:
 *   admin-post.php?action=sige_acta_pdf&turma_id=X&ano=Y&trimestre=Z&_wpnonce=W
 *   admin-post.php?action=sige_acta_guardar  (POST - guardar dados da ACTA)
 *
 * Registo (ver sige-softgenial.php):
 *   add_action('admin_post_sige_acta_pdf',     'sige_acta_pdf_handler');
 *   add_action('admin_post_sige_acta_guardar', 'sige_acta_guardar_handler');
 *
 * CONVENÇÃO M / HM (MINED-MZ):
 *   No documento oficial MINED as colunas são M e HM:
 *     • M  = Mulheres (count de alunos de género feminino)
 *     • HM = Homens+Mulheres (total real da turma)
 *   Internamente o SIGE usa M=Masculino, F=Feminino e U=desconhecido; o
 *   mapeamento para colunas MINED (sige_acta_map_mined_cols) é:
 *     • col_M_mined  = cnt_genero['F']
 *     • col_HM_mined = cnt_genero['M'] + cnt_genero['F'] + cnt_genero['U']
 *   v12.11.9.16: a normalização de género (sige_acta_norm_genero) passou a
 *   usar a mesma lógica canónica do DEC (remove_accents + maiúsculas + lista
 *   alargada de tokens). Género desconhecido entra apenas no total HM, nunca
 *   é forçado para um sexo, garantindo ACTA == DEC.
 *   Caso a convenção efectiva seja diferente na tua escola, altera
 *   apenas a função sige_acta_map_mined_cols() abaixo.
 *
 * REGRA null=0:
 *   Herdada do MAP - valores não registados contam como zero. Isto
 *   assegura consistência entre MAP, Anual (Consolidado) e ACTA.
 *   Divergência com sige_calcular_situacao_final() é dívida técnica
 *   conhecida, a resolver no refactor de semântica null=0.
 *
 * Dependências:
 *   - sige_num_extenso()              [map-pdf-handler.php]
 *   - sige_map_escala()               [map-pdf-handler.php]
 *   - sige_is_nuclear_by_matriz()     [academic-logic.php]
 *   - sige_parse_classe_num()         [academic-logic.php]
 *   - sige_is_fim_ciclo_by_turma()    [academic-logic.php]
 *   - sige_get_escola_perfil()        [db-handler.php]
 *   - sige_get_escola_id()            [multitenancy.php]
 *
 * @since 12.2.0
 * @version 12.2.0
 */

if (!defined('ABSPATH')) exit;

// ═════════════════════════════════════════════════════════════════════════════
// CONFIGURAÇÃO
// ═════════════════════════════════════════════════════════════════════════════

/** N.° de alunos no Quadro de Honra (top alunos por média anual) */
if (!defined('SIGE_ACTA_QH_TOP_N')) define('SIGE_ACTA_QH_TOP_N', 10);

/**
 * v12.10.77 - Garante colunas PRO para fluxo de aprovação da Nota Votada.
 * Mantém compatibilidade com instalações já existentes sem exigir reinstalação.
 */
if (!function_exists('sige_acta_pro_ensure_schema')) {
    function sige_acta_pro_ensure_schema(): void {
        global $wpdb;
        $p = $wpdb->prefix;
        $table = "{$p}sige_acta_nota_votada";

        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) return;

        $columns = (array) $wpdb->get_col("SHOW COLUMNS FROM {$table}", 0);
        $add = static function(string $col, string $sql) use ($wpdb, $table, &$columns) {
            if (!in_array($col, $columns, true)) {
                $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$sql}");
                $columns[] = $col;
            }
        };

        $add('status', "status VARCHAR(30) NOT NULL DEFAULT 'pendente_dp'");
        $add('aprovado_por', "aprovado_por BIGINT(20) UNSIGNED DEFAULT NULL");
        $add('aprovado_em', "aprovado_em DATETIME DEFAULT NULL");
        $add('aplicado_em', "aplicado_em DATETIME DEFAULT NULL");
        $add('nota_id', "nota_id BIGINT(20) UNSIGNED DEFAULT NULL");
        $add('nota_original_json', "nota_original_json LONGTEXT DEFAULT NULL");
        $add('observacao_dp', "observacao_dp TEXT DEFAULT NULL");

        // Índices seguros: alguns MySQL antigos não suportam IF NOT EXISTS em ADD INDEX.
        $indexes = (array) $wpdb->get_results("SHOW INDEX FROM {$table}", ARRAY_A);
        $idx_names = array_unique(array_map(static function($r){ return $r['Key_name'] ?? ''; }, $indexes));
        if (!in_array('idx_status', $idx_names, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_status (status)");
        }
        if (!in_array('idx_nota_id', $idx_names, true)) {
            $wpdb->query("ALTER TABLE {$table} ADD INDEX idx_nota_id (nota_id)");
        }

        // v12.10.81 - Snapshot oficial do Censo Escolar de 3 de Março.
        // Esta tabela separa o dado estatístico oficial da inferência técnica por data_matricula.
        $charset_collate = $wpdb->get_charset_collate();
        $censo_table = "{$p}sige_acta_censo_3_marco";
        $wpdb->query("CREATE TABLE IF NOT EXISTS {$censo_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL,
            turma_id BIGINT(20) UNSIGNED NOT NULL,
            ano_lectivo INT NOT NULL,
            referencia_data DATE NOT NULL,
            m_mulheres INT NOT NULL DEFAULT 0,
            hm_total INT NOT NULL DEFAULT 0,
            status VARCHAR(30) NOT NULL DEFAULT 'confirmado',
            observacao TEXT DEFAULT NULL,
            snapshot_json LONGTEXT DEFAULT NULL,
            confirmado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            confirmado_em DATETIME DEFAULT NULL,
            criado_em DATETIME DEFAULT NULL,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY uniq_censo_turma_ano (escola_id, turma_id, ano_lectivo, referencia_data),
            KEY idx_censo_lookup (escola_id, turma_id, ano_lectivo)
        ) {$charset_collate}");

        // Fonte oficial de notas: coluna de override aprovado pelo Conselho.
        $notas_table = "{$p}sige_notas";
        $notas_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $notas_table));
        if ($notas_exists === $notas_table) {
            $notas_columns = (array) $wpdb->get_col("SHOW COLUMNS FROM {$notas_table}", 0);
            $add_nota = static function(string $col, string $sql) use ($wpdb, $notas_table, &$notas_columns) {
                if (!in_array($col, $notas_columns, true)) {
                    $wpdb->query("ALTER TABLE {$notas_table} ADD COLUMN {$sql}");
                    $notas_columns[] = $col;
                }
            };
            $add_nota('nota_conselho', "nota_conselho DECIMAL(4,2) DEFAULT NULL");
            $add_nota('nota_conselho_acta_id', "nota_conselho_acta_id BIGINT(20) UNSIGNED DEFAULT NULL");
            $add_nota('nota_conselho_nv_id', "nota_conselho_nv_id BIGINT(20) UNSIGNED DEFAULT NULL");
            $add_nota('nota_conselho_aprovado_por', "nota_conselho_aprovado_por BIGINT(20) UNSIGNED DEFAULT NULL");
            $add_nota('nota_conselho_aprovado_em', "nota_conselho_aprovado_em DATETIME DEFAULT NULL");
            $add_nota('nota_conselho_motivo', "nota_conselho_motivo TEXT DEFAULT NULL");
        }
    }
}

if (!function_exists('sige_acta_can_aprovar_nota_votada')) {
    function sige_acta_can_aprovar_nota_votada(): bool {
        $ok = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
           || current_user_can('sige_director')
           || current_user_can('sige_pedagogico');

        if (function_exists('sige_user_can_any_secure')) {
            $ok = sige_user_can_any_secure(
                ['academico.aprovar_notas','academico.actas_emitir','academico.pautas_emitir'],
                ['sige_director','sige_pedagogico']
            );
        }
        return (bool) $ok;
    }
}

if (!function_exists('sige_acta_mt_from_nota_row')) {
    function sige_acta_mt_from_nota_row($row) {
        if (!$row) return null;
        if (isset($row->nota_conselho) && $row->nota_conselho !== null && $row->nota_conselho !== '' && is_numeric($row->nota_conselho)) {
            return (float) round((float)$row->nota_conselho, 0);
        }
        $ac  = (isset($row->nota_ac) && is_numeric($row->nota_ac)) ? (float)$row->nota_ac : 0.0;
        $acp = (isset($row->nota_acp) && is_numeric($row->nota_acp)) ? (float)$row->nota_acp : 0.0;
        $at  = null;
        if (isset($row->nota_exame) && is_numeric($row->nota_exame)) $at = (float)$row->nota_exame;
        elseif (isset($row->nota_at) && is_numeric($row->nota_at)) $at = (float)$row->nota_at;
        else $at = 0.0;
        $med = ($ac + $acp) / 2;
        return (float) round((2 * $med + $at) / 3, 0);
    }
}

/**
 * v12.10.77 - Aplica a Nota Votada à fonte oficial da pauta.
 *
 * Regra de segurança:
 * - Apenas Director Pedagógico/Director/Admin.
 * - Só aplica notas de disciplina específica.
 * - Preserva a nota original em JSON na própria linha da acta.
 * - A nota oficial fica aprovada, para a pauta/PDF/Excel/DEC/boletins a reconhecerem.
 */
if (!function_exists('sige_acta_aplicar_nota_votada')) {
    function sige_acta_aplicar_nota_votada(int $nota_votada_id, int $eid): array {
        if (!sige_tenant_write_guard((int) $eid, 'sige_acta_aplicar_nota_votada')) { return ['ok' => false, 'message' => 'Contexto de escola invalido.']; }
        global $wpdb;
        $p = $wpdb->prefix;
        sige_acta_pro_ensure_schema();

        $nv = $wpdb->get_row($wpdb->prepare(
            "SELECT nv.*, ac.turma_id, ac.ano_lectivo, ac.trimestre
             FROM {$p}sige_acta_nota_votada nv
             INNER JOIN {$p}sige_acta_conselho_notas ac ON ac.id = nv.acta_id AND ac.escola_id = nv.escola_id
             WHERE nv.id = %d AND nv.escola_id = %d
             LIMIT 1",
            $nota_votada_id, $eid
        ));

        if (!$nv) {
            return ['ok' => false, 'message' => 'Nota votada não encontrada.'];
        }

        $aluno_id = (int) $nv->aluno_id;
        $disciplina_id = (int) ($nv->disciplina_id ?? 0);
        $turma_id = (int) $nv->turma_id;
        $ano = (int) $nv->ano_lectivo;
        $trimestre = (int) $nv->trimestre;
        $nota_final = ($nv->nota_final !== null && $nv->nota_final !== '') ? (float) $nv->nota_final : null;

        if ($aluno_id <= 0 || $turma_id <= 0 || $ano <= 0 || $trimestre < 1 || $trimestre > 3) {
            return ['ok' => false, 'message' => 'Dados académicos incompletos para aplicar a nota.'];
        }
        if ($disciplina_id <= 0) {
            return ['ok' => false, 'message' => 'A aplicação automática exige uma disciplina específica. Média Global deve ser tratada manualmente.'];
        }
        if ($nota_final === null || $nota_final < 0 || $nota_final > 20) {
            return ['ok' => false, 'message' => 'Nota final inválida. Use valores entre 0 e 20.'];
        }

        // Confirma que o aluno pertence à turma/ano/escola.
        $aluno_ok = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$p}sige_matriculas
             WHERE aluno_id = %d AND turma_id = %d AND ano_lectivo = %d AND escola_id = %d
             LIMIT 1",
            $aluno_id, $turma_id, $ano, $eid
        ));
        if ($aluno_ok <= 0) {
            // Fallback para instalações antigas onde a matrícula pode não ter ano_lectivo coerente.
            $aluno_ok = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$p}sige_alunos WHERE id = %d AND escola_id = %d LIMIT 1",
                $aluno_id, $eid
            ));
        }
        if ($aluno_ok <= 0) {
            return ['ok' => false, 'message' => 'Aluno não pertence à escola/turma desta acta.'];
        }

        $nota_row = $wpdb->get_row($wpdb->prepare(
            "SELECT *
             FROM {$p}sige_notas
             WHERE aluno_id = %d AND disciplina_id = %d AND turma_id = %d
               AND ano_lectivo = %d AND trimestre = %d AND escola_id = %d
             LIMIT 1",
            $aluno_id, $disciplina_id, $turma_id, $ano, $trimestre, $eid
        ));

        $original_json = $nota_row ? wp_json_encode($nota_row, JSON_UNESCAPED_UNICODE) : null;
        $now = current_time('mysql');
        $uid = get_current_user_id();

        // Aplicação rigorosa: a Nota Votada passa a ser a nota oficial do trimestre,
        // sem destruir as componentes originais AC/ACP/AT. As pautas passam a usar
        // `nota_conselho` quando existir, preservando a rastreabilidade pedagógica.
        $nota_data = [
            'escola_id'                   => $eid,
            'aluno_id'                    => $aluno_id,
            'disciplina_id'               => $disciplina_id,
            'trimestre'                   => $trimestre,
            'ano_lectivo'                 => $ano,
            'turma_id'                    => $turma_id,
            'status'                      => 'aprovado',
            'submetido_por'               => $uid,
            'submetido_em'                => $now,
            'aprovado_por'                => $uid,
            'aprovado_em'                 => $now,
            'nota_conselho'               => $nota_final,
            'nota_conselho_acta_id'       => (int) $nv->acta_id,
            'nota_conselho_nv_id'         => $nota_votada_id,
            'nota_conselho_aprovado_por'  => $uid,
            'nota_conselho_aprovado_em'   => $now,
            'nota_conselho_motivo'        => sanitize_textarea_field((string)($nv->recomendacoes ?? 'Nota votada em Conselho de Notas')),
        ];

        $nota_id = 0;
        $wpdb->query('START TRANSACTION');
        try {
            if ($nota_row) {
                $nota_id = (int) $nota_row->id;
                $wpdb->update(
                    "{$p}sige_notas",
                    $nota_data,
                    ['id' => $nota_id, 'escola_id' => $eid]
                );
            } else {
                $wpdb->insert("{$p}sige_notas", $nota_data);
                $nota_id = (int) $wpdb->insert_id;
            }

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            $wpdb->update(
                "{$p}sige_acta_nota_votada",
                [
                    'status'             => 'aprovada_aplicada',
                    'aprovado_por'       => $uid,
                    'aprovado_em'        => $now,
                    'aplicado_em'        => $now,
                    'nota_id'            => $nota_id,
                    'nota_original_json' => $original_json,
                ],
                ['id' => $nota_votada_id, 'escola_id' => $eid]
            );

            if ($wpdb->last_error) {
                throw new Exception($wpdb->last_error);
            }

            $wpdb->query('COMMIT');

            if (function_exists('sige_audit_log')) {
                sige_audit_log('acta_nota_votada_aprovada_aplicada', [
                    'nota_votada_id' => $nota_votada_id,
                    'nota_id'        => $nota_id,
                    'acta_id'        => (int) $nv->acta_id,
                    'aluno_id'       => $aluno_id,
                    'disciplina_id'  => $disciplina_id,
                    'turma_id'       => $turma_id,
                    'ano'            => $ano,
                    'trimestre'      => $trimestre,
                    'nota_final'     => $nota_final,
                    'nota_original'  => $original_json,
                ], 'notas');
            }

            return ['ok' => true, 'message' => 'Nota votada aprovada e aplicada à pauta oficial.'];
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            return ['ok' => false, 'message' => 'Falha ao aplicar nota votada: ' . $e->getMessage()];
        }
    }
}


/** Ordem institucional Malisa das disciplinas na secção "Estatísticas por Disciplina" */
if (!function_exists('sige_acta_ordem_disciplinas_malisa')) {
    function sige_acta_ordem_disciplinas_malisa(): array {
        // label_pt => [siglas aceitáveis] - match case-insensitive
        return [
            'Português'        => ['POR', 'PORT', 'PORTUGUES', 'PORTUGUÊS'],
            'Matemática'       => ['MAT', 'MAT(4-6)', 'MATEMATICA', 'MATEMÁTICA'],
            'Inglês'           => ['ING', 'INGLES', 'INGLÊS'],
            'Ed. Visual'       => ['ED.VISUAL', 'ED.V', 'ED. V', 'EDVISUAL', 'EDUCACAO VISUAL'],
            'Ofícios'          => ['OF', 'OFICIOS', 'OFÍCIOS'],
            'Ciências Sociais' => ['CS', 'C.SOCIAIS', 'C. SOCIAIS', 'CIENCIAS SOCIAIS'],
            'Ciências Naturais'=> ['CN', 'C.NATURAIS', 'C. NATURAIS', 'CIENCIAS NATURAIS'],
            'Educação Física'  => ['ED.FISICA', 'ED. FISICA', 'ED.F', 'EDUCACAO FISICA'],
        ];
        // "Comp." (Comportamento) é linha sintética, adicionada no fim.
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// HELPERS null=0 (idênticos aos de map-pdf-handler.php; guarded p/ coexistir)
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('sige_ap_med_acs')) {
    function sige_ap_med_acs($ac, $acp) {
        $a = ($ac === null || $ac === '') ? 0.0 : (float) $ac;
        $b = ($acp === null || $acp === '') ? 0.0 : (float) $acp;
        return round(($a + $b) / 2, 1);
    }
}
if (!function_exists('sige_ap_mt_zerofill')) {
    function sige_ap_mt_zerofill($ac, $acp, $at) {
        $a = ($ac  === null || $ac  === '') ? 0.0 : (float) $ac;
        $b = ($acp === null || $acp === '') ? 0.0 : (float) $acp;
        $c = ($at  === null || $at  === '') ? 0.0 : (float) $at;
        $med = ($a + $b) / 2;
        return (int) round((2 * $med + $c) / 3, 0);
    }
}
if (!function_exists('sige_ap_mfd_zerofill')) {
    function sige_ap_mfd_zerofill($mt1, $mt2, $mt3) {
        $a = ($mt1 === null || $mt1 === '') ? 0.0 : (float) $mt1;
        $b = ($mt2 === null || $mt2 === '') ? 0.0 : (float) $mt2;
        $c = ($mt3 === null || $mt3 === '') ? 0.0 : (float) $mt3;
        return (int) round(($a + $b + $c) / 3, 0);
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// MAPEAMENTO GÉNERO INTERNO → COLUNAS MINED
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Dado um array ['M'=>x, 'F'=>y] (convenção SIGE interna),
 * devolve ['M'=>cnt_F, 'HM'=>cnt_M+cnt_F] (convenção MINED-MZ).
 *
 * Para alterar a convenção: editar só esta função.
 */
if (!function_exists('sige_acta_map_mined_cols')) {
    function sige_acta_map_mined_cols(array $cnt_interno): array {
        $m = (int) ($cnt_interno['M'] ?? 0); // Masculino (Homens)
        $f = (int) ($cnt_interno['F'] ?? 0); // Feminino (Mulheres)
        $u = (int) ($cnt_interno['U'] ?? 0); // Género desconhecido - entra só no total, nunca num sexo
        return [
            'M'  => $f,            // MINED "M"  = Mulheres (F interno)
            'HM' => $m + $f + $u,  // MINED "HM" = total real da turma (inclui desconhecido, como o DEC)
        ];
    }
}

/**
 * Normaliza o valor do campo genero da tabela sige_alunos para a convenção
 * interna do SIGE: 'M' = Masculino, 'F' = Feminino, 'U' = desconhecido.
 *
 * v12.11.9.16 - Alinhado com a normalização canónica do DEC (v12.10.73):
 *   - remove acentos, normaliza maiúsculas e remove caracteres não alfabéticos
 *     antes de comparar, para tolerar dados migrados/importados;
 *   - reconhece variantes completas (Masculino, Feminina, Mulher, Female, etc.);
 *   - um "M" isolado é Masculino (Homem), nunca Mulher;
 *   - género em branco/desconhecido NÃO é forçado para Masculino - devolve 'U'
 *     e entra apenas no total HM (igual ao DEC), preservando a contagem real
 *     da turma sem inflar Homens nem distorcer Mulheres.
 */
if (!function_exists('sige_acta_norm_genero')) {
    function sige_acta_norm_genero($g): string {
        // v12.11.9.17 - passa a delegar na normalização canónica partilhada
        // (academic-logic.php), garantindo regra idêntica ao DEC/Pauta Final.
        if (function_exists('sige_genero_bucket')) {
            return sige_genero_bucket($g);
        }
        // Fallback robusto (caso a logic não esteja carregada).
        $raw = trim((string) $g);
        if ($raw === '') return 'U';
        $u = function_exists('remove_accents') ? remove_accents($raw) : $raw;
        $u = function_exists('mb_strtoupper') ? mb_strtoupper($u, 'UTF-8') : strtoupper($u);
        $u = preg_replace('/[^A-Z]/', '', (string) $u);
        if ($u === '') return 'U';
        if (in_array($u, ['M','MASC','MASCULINO','MASCULINA','MACHO','MALE','HOMEM','HOMENS','H'], true)) return 'M';
        if (in_array($u, ['F','FEM','FEMININO','FEMININA','FEMEA','MULHER','MULHERES','FEMALE'], true)) return 'F';
        return 'U';
    }
}


// ═════════════════════════════════════════════════════════════════════════════
// CENSO ESCOLAR OFICIAL - 3 DE MARÇO
// ═════════════════════════════════════════════════════════════════════════════

if (!function_exists('sige_acta_data_corte_info')) {
    function sige_acta_data_corte_info(int $ano): array {
        $md = (string) get_option('sige_mined_data_corte', '03-03');
        if (!preg_match('/^\d{2}-\d{2}$/', $md)) $md = '03-03';
        [$mmc, $ddc] = explode('-', $md);
        $data = sprintf('%04d-%s-%s', $ano, $mmc, $ddc);
        $nome_mes = [
            '01' => 'Janeiro', '02' => 'Fevereiro', '03' => 'Março', '04' => 'Abril',
            '05' => 'Maio',    '06' => 'Junho',      '07' => 'Julho', '08' => 'Agosto',
            '09' => 'Setembro','10' => 'Outubro',   '11' => 'Novembro','12' => 'Dezembro',
        ];
        return [
            'data'  => $data,
            'label' => (int) $ddc . ' de ' . ($nome_mes[$mmc] ?? $mmc),
        ];
    }
}

if (!function_exists('sige_acta_censo_get_snapshot')) {
    function sige_acta_censo_get_snapshot(int $turma_id, int $ano, int $eid) {
        global $wpdb;
        $p = $wpdb->prefix;
        $table = "{$p}sige_acta_censo_3_marco";
        $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table));
        if ($exists !== $table) return null;
        $info = sige_acta_data_corte_info($ano);
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$table}
             WHERE escola_id = %d AND turma_id = %d AND ano_lectivo = %d AND referencia_data = %s
             LIMIT 1",
            $eid, $turma_id, $ano, $info['data']
        ));
    }
}

if (!function_exists('sige_acta_censo_sugestao_base')) {
    function sige_acta_censo_sugestao_base(int $turma_id, int $ano, int $eid): array {
        global $wpdb;
        $p  = $wpdb->prefix;
        $tA = $p . 'sige_alunos';
        $tM = $p . 'sige_matriculas';
        $rows = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT UPPER(TRIM(COALESCE(a.genero,''))) AS g, COUNT(*) AS c
             FROM {$tA} a
             INNER JOIN {$tM} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
             WHERE a.escola_id = %d
               AND m.turma_id = %d
               AND m.ano_lectivo = %d
               AND (m.status_matricula IS NULL OR m.status_matricula IN ('activa','ativa','activo','ativo'))
             GROUP BY g",
            $eid, $turma_id, $ano
        ));
        $interno = ['M' => 0, 'F' => 0, 'U' => 0];
        foreach ($rows as $r) {
            $g = sige_acta_norm_genero($r->g);
            $interno[$g] += (int) $r->c;
        }
        return sige_acta_map_mined_cols($interno);
    }
}

if (!function_exists('sige_acta_censo_save_snapshot')) {
    function sige_acta_censo_save_snapshot(int $turma_id, int $ano, int $eid, int $m_mulheres, int $hm_total, string $observacao = '', int $acta_id = 0): array {
        if (!sige_tenant_write_guard((int) $eid, 'sige_acta_censo_save_snapshot')) { return ['ok' => false, 'message' => 'Contexto de escola invalido.']; }
        global $wpdb;
        if ($turma_id <= 0 || $ano <= 0) {
            return ['ok' => false, 'msg' => 'Turma ou ano lectivo inválido.'];
        }
        if ($m_mulheres < 0 || $hm_total < 0) {
            return ['ok' => false, 'msg' => 'Os valores do Censo 3 de Março não podem ser negativos.'];
        }
        if ($m_mulheres > $hm_total) {
            return ['ok' => false, 'msg' => 'O total de Mulheres (M) não pode ser maior do que o total HM.'];
        }
        if (function_exists('sige_acta_pro_ensure_schema')) sige_acta_pro_ensure_schema();
        $p = $wpdb->prefix;
        $table = "{$p}sige_acta_censo_3_marco";
        $info = sige_acta_data_corte_info($ano);
        $now = current_time('mysql');
        $uid = get_current_user_id();
        $sug = sige_acta_censo_sugestao_base($turma_id, $ano, $eid);
        $snapshot = [
            'tipo' => 'censo_escolar_oficial_3_marco',
            'regra' => 'Situação real da turma na data oficial do levantamento estatístico escolar de Moçambique.',
            'label' => $info['label'],
            'referencia_data' => $info['data'],
            'valores_confirmados' => ['M' => $m_mulheres, 'HM' => $hm_total],
            'sugestao_sistema_no_momento' => $sug,
            'acta_id_origem' => $acta_id,
            'confirmado_por' => $uid,
            'confirmado_em' => $now,
        ];
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$table} WHERE escola_id=%d AND turma_id=%d AND ano_lectivo=%d AND referencia_data=%s LIMIT 1",
            $eid, $turma_id, $ano, $info['data']
        ));
        $row = [
            'escola_id' => $eid,
            'turma_id' => $turma_id,
            'ano_lectivo' => $ano,
            'referencia_data' => $info['data'],
            'm_mulheres' => $m_mulheres,
            'hm_total' => $hm_total,
            'status' => 'confirmado',
            'observacao' => $observacao,
            'snapshot_json' => wp_json_encode($snapshot),
            'confirmado_por' => $uid ?: null,
            'confirmado_em' => $now,
            'actualizado_em' => $now,
        ];
        if ($existing) {
            $ok = $wpdb->update($table, $row, ['id' => (int)$existing, 'escola_id' => $eid]);
        } else {
            $row['criado_em'] = $now;
            $ok = $wpdb->insert($table, $row);
        }
        if ($ok === false) {
            return ['ok' => false, 'msg' => 'Não foi possível guardar o snapshot oficial do Censo 3 de Março.'];
        }
        if (function_exists('sige_audit_log')) {
            sige_audit_log('acta_censo_3_marco_confirmado', [
                'turma_id' => $turma_id,
                'ano' => $ano,
                'referencia_data' => $info['data'],
                'M' => $m_mulheres,
                'HM' => $hm_total,
                'acta_id' => $acta_id,
            ], 'notas');
        }
        return ['ok' => true, 'msg' => 'Censo 3 de Março confirmado com sucesso.'];
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// ENSURE ACTA (idempotent - cria ou obtém)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Devolve o id da acta para (turma, ano, trimestre). Cria se não existir.
 *
 * @return int acta_id
 */
function sige_acta_get_or_create(int $turma_id, int $ano, int $trimestre): int {
    global $wpdb;
    $p = $wpdb->prefix;
    $t = $p . 'sige_acta_conselho_notas';
    $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
    if ($eid <= 0) { return 0; }

    $id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM {$t}
         WHERE turma_id = %d AND ano_lectivo = %d AND trimestre = %d AND escola_id = %d
         LIMIT 1",
        $turma_id, $ano, $trimestre, $eid
    ));

    if ($id > 0) return $id;

    $wpdb->insert($t, [
        'escola_id'    => $eid,
        'turma_id'     => $turma_id,
        'ano_lectivo'  => $ano,
        'trimestre'    => $trimestre,
        'status'       => 'rascunho',
        'criado_por'   => get_current_user_id(),
        'criado_em'    => current_time('mysql'),
    ], [
        '%d', '%d', '%d', '%d', '%s', '%d', '%s',
    ]);

    return (int) $wpdb->insert_id;
}

// ═════════════════════════════════════════════════════════════════════════════
// CÁLCULOS - ESTATÍSTICAS DEMOGRÁFICAS (secção "Fim do Trimestre" M/HM)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Calcula as estatísticas demográficas da turma no formato MINED.
 *
 * Retorna:
 *   [
 *     'matriculados'         => ['M'=>x, 'HM'=>y],  // no início do ano
 *     'data_corte'           => ['M'=>x, 'HM'=>y],  // à data-corte MINED (03-03)
 *     'desistencias'         => ['M'=>x, 'HM'=>y],
 *     'obitos'               => ['M'=>x, 'HM'=>y],
 *     'transferidos_recebidos' => ['M'=>x, 'HM'=>y],
 *     'transferidos_enviados'  => ['M'=>x, 'HM'=>y],
 *     'existentes'           => ['M'=>x, 'HM'=>y],   // activos AGORA
 *     'situacao_positiva'    => ['M'=>x, 'HM'=>y],   // aprovados no trimestre
 *     'percentagem'          => ['M'=>string, 'HM'=>string],
 *     'data_corte_label'     => '03 de Março',       // para o cabeçalho da linha
 *   ]
 *
 * Depende da existência de colunas:
 *   sige_matriculas.status_matricula (valores: activa, desistente, obito, transferido_saida, transferido_entrada)
 *   sige_matriculas.data_matricula
 *
 * Se essas colunas não existirem, devolve zeros com flag meta para o template
 * avisar o utilizador que os dados demográficos estão incompletos.
 */
function sige_acta_calc_stats_demograficas(int $turma_id, int $ano, int $trimestre, int $eid): array {
    global $wpdb;
    $p  = $wpdb->prefix;
    $tA = $p . 'sige_alunos';
    $tM = $p . 'sige_matriculas';

    // v12.10.81 - 3 de Março é Censo Escolar oficial, não inferência por data_matricula.
    $censo_info = function_exists('sige_acta_data_corte_info')
        ? sige_acta_data_corte_info($ano)
        : ['data' => sprintf('%04d-03-03', $ano), 'label' => '3 de Março'];
    $data_corte = $censo_info['data'];
    $data_corte_label = $censo_info['label'];

    // Detectar se a coluna status_matricula suporta os valores extendidos.
    // Se ainda não suportar, os buckets extra devolvem zero.
    $col_info = $wpdb->get_results("SHOW COLUMNS FROM `{$tM}` LIKE 'status_matricula'");
    $tem_status_ext = !empty($col_info);

    // Utilitário: contar [M,F] por condição WHERE extra.
    $contar = function($where_extra, $params) use ($wpdb, $tA, $tM, $turma_id, $ano, $eid) {
        $sql = "SELECT UPPER(TRIM(COALESCE(a.genero,''))) AS g, COUNT(*) AS c
                FROM {$tA} a
                INNER JOIN {$tM} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
                WHERE a.escola_id = %d
                  AND m.turma_id = %d
                  AND m.ano_lectivo = %d
                  {$where_extra}
                GROUP BY g";
        $final_params = array_merge([$eid, $turma_id, $ano], $params);
        $rows = $wpdb->get_results($wpdb->prepare($sql, ...$final_params));
        $out = ['M' => 0, 'F' => 0, 'U' => 0];
        foreach ($rows as $r) {
            $g = sige_acta_norm_genero($r->g);
            $out[$g] += (int) $r->c;
        }
        return $out;
    };

    // Matriculados = total de matrículas do ano (todos os status)
    $matriculados = $contar('', []);

    // Censo 3 de Março = snapshot oficial confirmado pela escola.
    // Se ainda não existir confirmação, não apresentamos zero falso: mostramos “-”.
    $censo_snapshot = function_exists('sige_acta_censo_get_snapshot') ? sige_acta_censo_get_snapshot($turma_id, $ano, $eid) : null;
    $data_corte_cnt = $censo_snapshot
        ? ['M' => (int)$censo_snapshot->m_mulheres, 'HM' => (int)$censo_snapshot->hm_total]
        : ['M' => '-', 'HM' => '-'];

    // v12.11.9.17 - Detecção de snapshot de Censo desactualizado.
    // O bug de género (corrigido em v12.11.9.16) só subcontava Mulheres mantendo
    // o total HM igual. Snapshots confirmados ANTES da correcção ficaram com M
    // congelado abaixo do real (caso clássico das turmas 3 A / 4 A / 5 A). Aqui
    // recalculamos a sugestão JÁ corrigida e marcamos divergência quando o total
    // coincide mas o nº de Mulheres difere - sinal inequívoco do bug antigo.
    $data_corte_sug_now = function_exists('sige_acta_censo_sugestao_base')
        ? sige_acta_censo_sugestao_base($turma_id, $ano, $eid)
        : ['M' => 0, 'HM' => 0];
    $data_corte_stale = false;
    if ($censo_snapshot) {
        $snap_m  = (int) $censo_snapshot->m_mulheres;
        $snap_hm = (int) $censo_snapshot->hm_total;
        $now_m   = (int) ($data_corte_sug_now['M'] ?? 0);
        $now_hm  = (int) ($data_corte_sug_now['HM'] ?? 0);
        // Mesmo total, Mulheres diferentes → quase de certeza o bug de género antigo.
        $data_corte_stale = ($snap_hm === $now_hm && $snap_m !== $now_m);
    }

    // Existentes (avaliados) = activos à data actual
    $existentes = $contar(
        " AND (m.status_matricula IS NULL OR m.status_matricula = 'activa')",
        []
    );

    // Desistências, óbitos, transferidos - dependem de status estendido
    if ($tem_status_ext) {
        $desistencias = $contar(" AND m.status_matricula = 'desistente'", []);
        $obitos       = $contar(" AND m.status_matricula = 'obito'",       []);
        $trans_in     = $contar(" AND m.status_matricula = 'transferido_entrada'", []);
        $trans_out    = $contar(" AND m.status_matricula = 'transferido_saida'",   []);
    } else {
        $desistencias = $obitos = $trans_in = $trans_out = ['M' => 0, 'F' => 0, 'U' => 0];
    }

    // Situação Positiva (+) = alunos aprovados no trimestre. Calculado via
    // a matriz de notas: "aprovado no trimestre" = MT >= 10 em TODAS as
    // disciplinas nucleares da classe. Usamos a lista de alunos existentes.
    $aprovados = sige_acta_calc_situacao_positiva($turma_id, $ano, $trimestre, $eid);

    // Percentagem - denominador = existentes (como no documento MINED)
    $pct = function($num, $den) {
        if ($den <= 0) return '0%';
        return round($num / $den * 100, 1) . '%';
    };

    $ex = sige_acta_map_mined_cols($existentes);
    $ap = sige_acta_map_mined_cols($aprovados);

    return [
        'matriculados'           => sige_acta_map_mined_cols($matriculados),
        'data_corte'             => $data_corte_cnt,
        'data_corte_confirmado'  => (bool) $censo_snapshot,
        'data_corte_observacao'  => $censo_snapshot ? (string)($censo_snapshot->observacao ?? '') : '',
        'data_corte_confirmado_em' => $censo_snapshot ? (string)($censo_snapshot->confirmado_em ?? '') : '',
        'data_corte_sug_now'     => $data_corte_sug_now,
        'data_corte_stale'       => $data_corte_stale,
        'desistencias'           => sige_acta_map_mined_cols($desistencias),
        'obitos'                 => sige_acta_map_mined_cols($obitos),
        'transferidos_recebidos' => sige_acta_map_mined_cols($trans_in),
        'transferidos_enviados'  => sige_acta_map_mined_cols($trans_out),
        'existentes'             => $ex,
        'situacao_positiva'      => $ap,
        'percentagem'            => [
            'M'  => $pct($ap['M'],  $ex['M']),
            'HM' => $pct($ap['HM'], $ex['HM']),
        ],
        'data_corte_label'       => $data_corte_label,
        'status_ext_suportado'   => $tem_status_ext,
    ];
}

/**
 * Conta [M,F] de alunos com Situação Positiva no trimestre.
 * Regra: aprovado ⇔ MT >= 10 em TODAS as disciplinas nucleares da classe.
 * Usa regra null=0 (MT calculado com 0 quando falta nota).
 *
 * Para Quadro de Honra e Estatísticas por Disciplina, usamos a mesma fonte.
 */
function sige_acta_calc_situacao_positiva(int $turma_id, int $ano, int $trimestre, int $eid): array {
    global $wpdb;
    $p = $wpdb->prefix;

    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id AS aluno_id, a.genero, t.classe
         FROM {$p}sige_alunos a
         INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
         INNER JOIN {$p}sige_turmas t    ON t.id = m.turma_id   AND t.escola_id = a.escola_id
         WHERE a.escola_id = %d
           AND m.turma_id  = %d
           AND m.ano_lectivo = %d
           AND (m.status_matricula IS NULL OR m.status_matricula = 'activa')",
        $eid, $turma_id, $ano
    ));

    $out = ['M' => 0, 'F' => 0, 'U' => 0];
    if (empty($rows)) return $out;

    foreach ($rows as $r) {
        if (sige_acta_aluno_aprovado_trimestre((int)$r->aluno_id, $turma_id, $ano, $trimestre, (string)$r->classe, $eid)) {
            $g = sige_acta_norm_genero($r->genero);
            $out[$g]++;
        }
    }
    return $out;
}

/**
 * Obtém a linha de nota CORRECTA para o aluno/disciplina/trimestre.
 * v12.11.9.19 - Antes usava LIMIT 1 sem turma_id, o que em alunos
 * reinscritos/transferidos (merge Malisa) podia apanhar uma linha de outro
 * contexto (vazia) e reprovar o aluno por engano. Agora:
 *   1) prefere a linha da turma correcta;
 *   2) na ausência, escolhe a linha mais completa (maior MT calculado);
 * usando exactamente a mesma fonte de MT do DEC e das Estatísticas por
 * Disciplina (sige_acta_mt_from_nota_row, que respeita nota_conselho).
 */
if (!function_exists('sige_acta_fetch_nota_row')) {
    function sige_acta_fetch_nota_row(int $aluno_id, int $did, int $turma_id, int $ano, int $trimestre, int $eid) {
        global $wpdb;
        $p = $wpdb->prefix;
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT turma_id, nota_ac, nota_acp, nota_at, nota_exame, nota_conselho
             FROM {$p}sige_notas
             WHERE aluno_id = %d AND disciplina_id = %d
               AND ano_lectivo = %d AND trimestre = %d AND escola_id = %d",
            $aluno_id, $did, $ano, $trimestre, $eid
        ));
        if (empty($rows)) return null;
        foreach ($rows as $r) {
            if ((int)($r->turma_id ?? 0) === (int)$turma_id) return $r;
        }
        $best = null; $bestmt = -1.0;
        foreach ($rows as $r) {
            $mt = sige_acta_mt_from_nota_row($r);
            if ($mt !== null && (float)$mt > $bestmt) { $bestmt = (float)$mt; $best = $r; }
        }
        return $best ?: $rows[0];
    }
}

/**
 * Retorna true se o aluno tem MT >= 10 em TODAS as disciplinas nucleares
 * da classe, no trimestre dado.
 *
 * v12.11.9.19 - Passa a usar a MESMA fonte de MT do DEC e das Estatísticas
 * por Disciplina (sige_acta_mt_from_nota_row: respeita nota_conselho e usa a
 * linha da turma correcta). Corrige a divergência em que a Situação Positiva
 * reprovava alunos que o DEC e a estatística por disciplina davam como
 * positivos. Mantém null=0 apenas quando NÃO existe nenhuma linha de nota.
 */
function sige_acta_aluno_aprovado_trimestre(int $aluno_id, int $turma_id, int $ano, int $trimestre, string $classe, int $eid): bool {
    global $wpdb;
    $p = $wpdb->prefix;

    // Disciplinas da turma
    $disc_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT td.disciplina_id
         FROM {$p}sige_turma_disciplinas td
         WHERE td.turma_id = %d AND td.escola_id = %d",
        $turma_id, $eid
    ));

    if (empty($disc_ids)) return false;

    $all_mt_ok = true;
    $viu_alguma_nuclear = false;
    foreach ($disc_ids as $did) {
        $did = (int) $did;
        if (function_exists('sige_is_nuclear_by_matriz')) {
            $is_nuc = sige_is_nuclear_by_matriz($classe, $did);
            if (!$is_nuc) continue;
        }
        $viu_alguma_nuclear = true;

        $nota = sige_acta_fetch_nota_row($aluno_id, $did, $turma_id, $ano, $trimestre, $eid);
        $mt = $nota ? (float) sige_acta_mt_from_nota_row($nota) : 0.0;
        if ($mt < 10) { $all_mt_ok = false; break; }
    }

    // Fallback - se não encontrou disciplinas nucleares, usa TODAS.
    if (!$viu_alguma_nuclear) {
        foreach ($disc_ids as $did) {
            $did = (int) $did;
            $nota = sige_acta_fetch_nota_row($aluno_id, $did, $turma_id, $ano, $trimestre, $eid);
            $mt = $nota ? (float) sige_acta_mt_from_nota_row($nota) : 0.0;
            if ($mt < 10) return false;
        }
    }

    return $all_mt_ok;
}

// ═════════════════════════════════════════════════════════════════════════════
// CÁLCULOS - ESTATÍSTICAS POR DISCIPLINA (bins 0-4, 5-9, 10-13, 14-17, 18-20)
// ═════════════════════════════════════════════════════════════════════════════

function sige_acta_calc_stats_por_disciplina(int $turma_id, int $ano, int $trimestre, int $eid): array {
    global $wpdb;
    $p = $wpdb->prefix;

    // Classe da turma - usada apenas para buscar a matriz curricular correcta.
    $turma = $wpdb->get_row($wpdb->prepare(
        "SELECT classe FROM {$p}sige_turmas WHERE id = %d AND escola_id = %d LIMIT 1",
        $turma_id, $eid
    ));
    $classe = $turma ? (string)($turma->classe ?? '') : '';

    // v12.10.79 - disciplinas reais da turma/classe, sem lista fixa.
    $discs = function_exists('sige_acta_get_disciplinas_turma')
        ? sige_acta_get_disciplinas_turma($turma_id, $eid, $classe)
        : [];

    $zeros = ['avaliados' => 0, 'positiva' => 0, 'b04' => 0, 'b59' => 0, 'b1013' => 0, 'b1417' => 0, 'b1820' => 0];
    $cols = [];
    $map_did_to_label = [];

    foreach ($discs as $d) {
        $did = (int)($d->id ?? 0);
        $label = trim((string)($d->nome ?? ''));
        if ($did <= 0 || $label === '') continue;
        $map_did_to_label[$did] = $label;
        if (!isset($cols[$label])) $cols[$label] = $zeros;
    }

    if (empty($map_did_to_label)) {
        return [];
    }

    // Alunos activos na turma
    $alunos_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT a.id
         FROM {$p}sige_alunos a
         INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
         WHERE a.escola_id = %d AND m.turma_id = %d AND m.ano_lectivo = %d
           AND (m.status_matricula IS NULL OR m.status_matricula = 'activa')",
        $eid, $turma_id, $ano
    ));
    $alunos_ids = array_map('intval', (array)$alunos_ids);

    if (!empty($alunos_ids)) {
        $did_list = array_keys($map_did_to_label);
        $placeholders_a = implode(',', array_fill(0, count($alunos_ids), '%d'));
        $placeholders_d = implode(',', array_fill(0, count($did_list), '%d'));
        $params = array_merge([$eid, $ano, $trimestre, $turma_id], $did_list, $alunos_ids);

        $notas = $wpdb->get_results($wpdb->prepare(
            "SELECT aluno_id, disciplina_id, nota_ac, nota_acp, nota_at, nota_exame, nota_conselho
             FROM {$p}sige_notas
             WHERE escola_id = %d
               AND ano_lectivo = %d
               AND trimestre = %d
               AND turma_id = %d
               AND disciplina_id IN ($placeholders_d)
               AND aluno_id IN ($placeholders_a)",
            ...$params
        ));

        // Indexar por (aluno, disciplina) para garantir contagem única.
        $seen = [];
        foreach ($notas as $n) {
            $key = (int)$n->aluno_id . ':' . (int)$n->disciplina_id;
            if (isset($seen[$key])) continue;
            $seen[$key] = true;

            $label = $map_did_to_label[(int)$n->disciplina_id] ?? null;
            if ($label === null) continue;

            $mt = function_exists('sige_acta_mt_from_nota_row')
                ? sige_acta_mt_from_nota_row($n)
                : sige_ap_mt_zerofill($n->nota_ac ?? null, $n->nota_acp ?? null, $n->nota_at ?? ($n->nota_exame ?? null));

            $cols[$label]['avaliados']++;
            if ($mt >= 10) $cols[$label]['positiva']++;
            if ($mt >= 0  && $mt <=  4) $cols[$label]['b04']++;
            elseif ($mt <=  9)          $cols[$label]['b59']++;
            elseif ($mt <= 13)          $cols[$label]['b1013']++;
            elseif ($mt <= 17)          $cols[$label]['b1417']++;
            else                        $cols[$label]['b1820']++;
        }

        // Regra histórica null=0 da ACTA: aluno sem nota naquela disciplina entra como avaliado com MT=0.
        foreach ($map_did_to_label as $did => $label) {
            foreach ($alunos_ids as $a_id) {
                $k = $a_id . ':' . $did;
                if (!isset($seen[$k])) {
                    $cols[$label]['avaliados']++;
                    $cols[$label]['b04']++;
                }
            }
        }
    }

    return $cols;
}

// ═════════════════════════════════════════════════════════════════════════════
// CÁLCULOS - QUADRO DE HONRA (top N alunos por média anual)
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Top N alunos da turma por média acumulada até ao trimestre dado.
 * A "média" é a média aritmética das MT das disciplinas nucleares nos
 * trimestres 1..$trimestre.
 *
 * @return array lista de ['posicao'=>n, 'nome'=>..., 'media'=>float]
 */
function sige_acta_calc_quadro_honra(int $turma_id, int $ano, int $trimestre, int $eid, int $top_n = 10): array {
    global $wpdb;
    $p = $wpdb->prefix;

    // Alunos + classe
    $alunos = $wpdb->get_results($wpdb->prepare(
        "SELECT a.id, a.nome_completo, t.classe
         FROM {$p}sige_alunos a
         INNER JOIN {$p}sige_matriculas m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
         INNER JOIN {$p}sige_turmas t    ON t.id = m.turma_id  AND t.escola_id = a.escola_id
         WHERE a.escola_id = %d AND m.turma_id = %d AND m.ano_lectivo = %d
           AND (m.status_matricula IS NULL OR m.status_matricula = 'activa')",
        $eid, $turma_id, $ano
    ));

    if (empty($alunos)) return [];

    $medias = [];
    foreach ($alunos as $al) {
        $cl = (string) $al->classe;

        // Disciplinas nucleares da classe
        $disc_ids = $wpdb->get_col($wpdb->prepare(
            "SELECT td.disciplina_id FROM {$p}sige_turma_disciplinas td
             WHERE td.turma_id = %d AND td.escola_id = %d",
            $turma_id, $eid
        ));
        $nucleares = [];
        foreach ($disc_ids as $did) {
            $did = (int) $did;
            if (function_exists('sige_is_nuclear_by_matriz')) {
                if (!sige_is_nuclear_by_matriz($cl, $did)) continue;
            }
            $nucleares[] = $did;
        }
        if (empty($nucleares)) $nucleares = array_map('intval', $disc_ids);
        if (empty($nucleares)) continue;

        // MT por disciplina e trimestre
        $soma = 0.0; $cnt = 0;
        foreach ($nucleares as $did) {
            for ($t = 1; $t <= $trimestre; $t++) {
                $n = $wpdb->get_row($wpdb->prepare(
                    "SELECT nota_ac, nota_acp, nota_at
                     FROM {$p}sige_notas
                     WHERE aluno_id = %d AND disciplina_id = %d
                       AND ano_lectivo = %d AND trimestre = %d AND escola_id = %d
                     LIMIT 1",
                    (int) $al->id, $did, $ano, $t, $eid
                ));
                $mt = $n ? sige_ap_mt_zerofill($n->nota_ac, $n->nota_acp, $n->nota_at)
                         : sige_ap_mt_zerofill(null, null, null);
                $soma += $mt;
                $cnt++;
            }
        }
        $med = $cnt > 0 ? round($soma / $cnt, 2) : 0.0;

        // Só entra no QH com média >= 14 (critério institucional típico)
        if ($med < 14) continue;
        $medias[] = ['nome' => (string) $al->nome_completo, 'media' => $med];
    }

    usort($medias, function($a, $b) { return $b['media'] <=> $a['media']; });
    $top = array_slice($medias, 0, max(1, $top_n));

    $out = [];
    foreach ($top as $i => $row) {
        $out[] = [
            'posicao' => $i + 1,
            'nome'    => $row['nome'],
            'media'   => $row['media'],
        ];
    }
    return $out;
}


// v12.10.79 - Fonte canónica das disciplinas da ACTA.
// A ACTA impressa/descarregada passa a usar a mesma fonte turma/classe da pauta:
// 1) disciplinas vinculadas à turma;
// 2) fallback controlado para a matriz curricular da classe, quando o vínculo da turma estiver incompleto;
// 3) nunca usa listas fixas/hardcoded de disciplinas para compor o documento.
if (!function_exists('sige_acta_get_disciplinas_turma')) {
function sige_acta_get_disciplinas_turma(int $turma_id, int $eid, string $classe = ''): array {
    global $wpdb;
    $p = $wpdb->prefix;

    $classe = trim((string) $classe);

    $disciplinas = (array) $wpdb->get_results($wpdb->prepare(
        "SELECT d.id, d.nome, d.sigla, d.categoria,
                COALESCE(
                    mc.ordem_pauta,
                    CASE UPPER(TRIM(d.sigla))
                        WHEN 'POR'        THEN 1
                        WHEN 'PORT'       THEN 1
                        WHEN 'MAT'        THEN 2
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
                ) AS ordem_pauta
         FROM {$p}sige_disciplinas d
         LEFT JOIN {$p}sige_matriz_curricular mc
                ON mc.disciplina_id = d.id
               AND mc.escola_id = d.escola_id
               AND mc.classe = %s
         WHERE d.escola_id = %d
           AND d.id IN (
                SELECT disciplina_id
                FROM {$p}sige_turma_disciplinas
                WHERE turma_id = %d AND escola_id = %d
                UNION
                SELECT disciplina_id
                FROM {$p}sige_matriz_curricular
                WHERE classe = %s AND escola_id = %d
           )
         ORDER BY ordem_pauta ASC, d.nome ASC",
        $classe, $eid, $turma_id, $eid, $classe, $eid
    ));

    // Se existir a função oficial de ordenação/categoria já usada nas Pautas,
    // reaplica-a para manter coerência visual e académica entre Pauta, DEC e ACTA.
    if (!empty($classe) && function_exists('sige_apply_categoria_oficial_disciplinas')) {
        $disciplinas = sige_apply_categoria_oficial_disciplinas($disciplinas, $classe);
    }
    if (!empty($classe) && function_exists('sige_get_categoria_by_matriz')) {
        foreach ($disciplinas as $d) {
            if (isset($d->id)) {
                $d->categoria = sige_get_categoria_by_matriz($classe, (int)$d->id, $eid);
            }
        }
    }

    return $disciplinas;
}
}

// ═════════════════════════════════════════════════════════════════════════════
// LOAD - PRESENÇAS, NOTA VOTADA, CUMPRIMENTO PROGRAMAS
// ═════════════════════════════════════════════════════════════════════════════

function sige_acta_load_presencas(int $acta_id, int $eid): array {
    global $wpdb;
    $p = $wpdb->prefix;
    return (array) $wpdb->get_results($wpdb->prepare(
        "SELECT id, user_id, nome, funcao, disciplina_id, disciplina_nome, status, ordem
         FROM {$p}sige_acta_presencas
         WHERE acta_id = %d AND escola_id = %d
         ORDER BY status ASC, ordem ASC, id ASC",
        $acta_id, $eid
    ));
}

function sige_acta_load_nota_votada(int $acta_id, int $eid): array {
    global $wpdb;
    $p = $wpdb->prefix;
    return (array) $wpdb->get_results($wpdb->prepare(
        "SELECT nv.id, nv.aluno_id, a.nome_completo, nv.numero_chamada,
                nv.disciplina_id, d.nome AS disciplina_nome,
                nv.nota_inicial, nv.nota_final, nv.recomendacoes, nv.ordem,
                COALESCE(nv.status, 'pendente_dp') AS status,
                nv.aprovado_por, nv.aprovado_em, nv.aplicado_em, nv.nota_id, nv.observacao_dp
         FROM {$p}sige_acta_nota_votada nv
         LEFT JOIN {$p}sige_alunos a ON a.id = nv.aluno_id AND a.escola_id = nv.escola_id
         LEFT JOIN {$p}sige_disciplinas d ON d.id = nv.disciplina_id AND d.escola_id = nv.escola_id
         WHERE nv.acta_id = %d AND nv.escola_id = %d
         ORDER BY nv.ordem ASC, nv.id ASC",
        $acta_id, $eid
    ));
}

function sige_acta_load_cumprimento_programas(int $acta_id, int $eid): array {
    global $wpdb;
    $p = $wpdb->prefix;
    return (array) $wpdb->get_results($wpdb->prepare(
        "SELECT id, disciplina_id, disciplina_nome, ultimo_tema, aulas_em_atraso, razoes_atraso
         FROM {$p}sige_acta_cumprimento_programas
         WHERE acta_id = %d AND escola_id = %d
         ORDER BY disciplina_nome ASC",
        $acta_id, $eid
    ));
}

// ═════════════════════════════════════════════════════════════════════════════
// HANDLER PRINCIPAL - GERAR ACTA (GET)
// ═════════════════════════════════════════════════════════════════════════════

function sige_acta_pdf_handler() {

    if (function_exists('sige_acta_pro_ensure_schema')) sige_acta_pro_ensure_schema();

    // ── 1. Segurança ──────────────────────────────────────────────────────
    $perms_ok = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
             || current_user_can('sige_professor')
             || current_user_can('sige_director')
             || current_user_can('sige_pedagogico')
             || current_user_can('sige_secretario')
             || current_user_can('sige_secretaria_geral')
             || current_user_can('sige_assistente');

    if (function_exists('sige_user_can_any_secure')) {
        $perms_ok = sige_user_can_any_secure(['academico.actas_emitir','academico.pautas_emitir','academico.pautas_ver'], ['sige_professor','sige_director','sige_pedagogico','sige_secretario','sige_secretaria_geral','sige_assistente']);
    }

    if (!$perms_ok) {
        wp_die('Acesso negado. Não tem permissões para gerar a Acta do Conselho de Notas.', 'SIGE - ACTA', ['response' => 403]);
    }

    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'sige_acta_pdf')) {
        wp_die('Nonce inválido ou expirado. Recarregue a página e tente de novo.', 'SIGE - ACTA', ['response' => 403]);
    }

    // ── 2. Parâmetros ─────────────────────────────────────────────────────
    $turma_id  = isset($_GET['turma_id'])  ? (int) $_GET['turma_id']  : 0;
    $ano       = isset($_GET['ano'])       ? (int) $_GET['ano']       : 0;
    $trimestre = isset($_GET['trimestre']) ? (int) $_GET['trimestre'] : 0;

    if ($turma_id <= 0 || $ano <= 0 || $trimestre < 1 || $trimestre > 3) {
        wp_die('Parâmetros obrigatórios: turma_id, ano (4 dígitos) e trimestre (1-3).', 'SIGE - ACTA');
    }

    global $wpdb;
    $p = $wpdb->prefix;
    $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;

    // ── 3. Turma ──────────────────────────────────────────────────────────
    $turma = $wpdb->get_row($wpdb->prepare(
        "SELECT id, classe, turno,
                COALESCE(NULLIF(nome_turma,''), nome) AS nome_turma
         FROM {$p}sige_turmas
         WHERE id = %d AND escola_id = %d
         LIMIT 1",
        $turma_id, $eid
    ));
    if (!$turma) wp_die('Turma não encontrada (id=' . (int) $turma_id . ').', 'SIGE - ACTA');

    // [12.11.9.15] Defesa por URL: professor só pode imprimir ACTA de turma atribuída.
    if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
        $prof_id_scope = function_exists('sige_get_professor_atual_id') ? (int) sige_get_professor_atual_id() : 0;
        if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, $prof_id_scope, $eid)) {
            wp_die('Acesso negado. Esta Acta pertence a uma turma que não está atribuída ao seu perfil de professor.', 'SIGE - ACTA', ['response' => 403]);
        }
    }

    // ── 4. Acta (ensure) ──────────────────────────────────────────────────
    $acta_id = sige_acta_get_or_create($turma_id, $ano, $trimestre);
    $acta = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$p}sige_acta_conselho_notas WHERE id = %d AND escola_id = %d LIMIT 1",
        $acta_id, $eid
    ));

    // ── 5. Presenças ──────────────────────────────────────────────────────
    $pres_raw = sige_acta_load_presencas($acta_id, $eid);
    $presentes = []; $ausentes = [];
    foreach ($pres_raw as $pp) {
        if ($pp->status === 'ausente') $ausentes[] = $pp; else $presentes[] = $pp;
    }

    // ── 6. Nota Votada ────────────────────────────────────────────────────
    $nota_votada = sige_acta_load_nota_votada($acta_id, $eid);

    // ── 7. Cumprimento dos Programas ──────────────────────────────────────
    // v12.10.79 - o impresso usa a lista canónica turma/classe; dados antigos
    // de cumprimento são apenas preenchimento dos campos, nunca fonte da lista.
    $cumprimento = sige_acta_load_cumprimento_programas($acta_id, $eid);
    $disciplinas_turma = function_exists('sige_acta_get_disciplinas_turma')
        ? sige_acta_get_disciplinas_turma($turma_id, $eid, (string)($turma->classe ?? ''))
        : [];

    // ── 8. Estatísticas demográficas + por disciplina + QH ────────────────
    $stats_demo       = sige_acta_calc_stats_demograficas($turma_id, $ano, $trimestre, $eid);
    $stats_disciplina = sige_acta_calc_stats_por_disciplina($turma_id, $ano, $trimestre, $eid);
    $quadro_honra     = sige_acta_calc_quadro_honra($turma_id, $ano, $trimestre, $eid, SIGE_ACTA_QH_TOP_N);

    // ── 9. Perfil da escola ───────────────────────────────────────────────
    $perfil      = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
    $escola_nome = $perfil->nome_escola          ?? get_bloginfo('name');
    $logo_url    = !empty($perfil->logo_documentos_url)
                 ? $perfil->logo_documentos_url
                 : ($perfil->logo_sistema_url ?? '');
    $endereco    = $perfil->endereco_escola      ?? '';
    $email_esc   = $perfil->email_institucional  ?? '';

    // ── 10. Trimestre em ordinal PT ───────────────────────────────────────
    $trim_ord = ['', 'I', 'II', 'III'][$trimestre] ?? (string) $trimestre;

    // ── 11. Render ────────────────────────────────────────────────────────
    $data = [
        'acta'              => $acta,
        'turma'             => $turma,
        'ano'               => $ano,
        'trimestre'         => $trimestre,
        'trimestre_ordinal' => $trim_ord,
        'classe_num'        => function_exists('sige_parse_classe_num') ? sige_parse_classe_num((string) $turma->classe) : (preg_match('/\d+/', (string) $turma->classe, $mm) ? (int) $mm[0] : 0),
        'turma_nome'        => (string) $turma->nome_turma,
        'presentes'         => $presentes,
        'ausentes'          => $ausentes,
        'nota_votada'       => $nota_votada,
        'cumprimento'       => $cumprimento,
        'disciplinas_turma' => $disciplinas_turma,
        'stats_demo'        => $stats_demo,
        'stats_disciplina'  => $stats_disciplina,
        'quadro_honra'      => $quadro_honra,
        'escola_nome'       => $escola_nome,
        'logo_url'          => $logo_url,
        'endereco'          => $endereco,
        'email_escola'      => $email_esc,
    ];

    header('Content-Type: text/html; charset=UTF-8');
    include SIGE_PATH . 'admin/acta-oficial-template.php';
    exit;
}

// ═════════════════════════════════════════════════════════════════════════════
// HANDLER - GUARDAR DADOS DA ACTA (POST)
// ═════════════════════════════════════════════════════════════════════════════

function sige_acta_guardar_handler() {

    if (function_exists('sige_acta_pro_ensure_schema')) sige_acta_pro_ensure_schema();

    // ── Segurança ─────────────────────────────────────────────────────────
    $perms_ok = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
             || current_user_can('sige_director')
             || current_user_can('sige_pedagogico')
             || current_user_can('sige_secretaria_geral');

    if (function_exists('sige_user_can_any_secure')) {
        $perms_ok = sige_user_can_any_secure(['academico.actas_emitir','academico.aprovar_notas','academico.pautas_emitir'], ['sige_director','sige_pedagogico','sige_secretaria_geral']);
    }

    if (!$perms_ok) {
        wp_die('Acesso negado. Não tem permissões para editar a Acta.', 'SIGE - ACTA', ['response' => 403]);
    }

    if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'sige_acta_guardar')) {
        wp_die('Nonce inválido ou expirado. Recarregue a página e tente de novo.', 'SIGE - ACTA', ['response' => 403]);
    }

    global $wpdb;
    $p = $wpdb->prefix;
    $eid = sige_require_escola_id('acta_guardar');

    $acta_id   = isset($_POST['acta_id'])   ? (int) $_POST['acta_id']   : 0;
    $turma_id  = isset($_POST['turma_id'])  ? (int) $_POST['turma_id']  : 0;
    $ano       = isset($_POST['ano'])       ? (int) $_POST['ano']       : 0;
    $trimestre = isset($_POST['trimestre']) ? (int) $_POST['trimestre'] : 0;

    if ($acta_id <= 0) {
        if ($turma_id <= 0 || $ano <= 0 || $trimestre < 1 || $trimestre > 3) {
            wp_die('Parâmetros inválidos (acta_id ou turma_id+ano+trimestre).', 'SIGE - ACTA');
        }
        $acta_id = sige_acta_get_or_create($turma_id, $ano, $trimestre);
    }

    if ($turma_id <= 0) {
        $turma_id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT turma_id FROM {$p}sige_acta_conselho_notas WHERE id = %d AND escola_id = %d LIMIT 1",
            $acta_id, $eid
        ));
    }

    // [12.11.9.15] Defesa por POST: se um professor ganhar permissão de edição, continua limitado às suas turmas.
    if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
        $prof_id_scope = function_exists('sige_get_professor_atual_id') ? (int) sige_get_professor_atual_id() : 0;
        if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, $prof_id_scope, $eid)) {
            wp_die('Acesso negado. Esta Acta pertence a uma turma que não está atribuída ao seu perfil de professor.', 'SIGE - ACTA', ['response' => 403]);
        }
    }

    // Verificar ownership multi-tenant (não deixar editar acta de outra escola)
    $own = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT escola_id FROM {$p}sige_acta_conselho_notas WHERE id = %d LIMIT 1",
        $acta_id
    ));
    if ($own !== $eid && !(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
        wp_die('Acta não pertence à sua escola.', 'SIGE - ACTA', ['response' => 403]);
    }

    // ── Cabeçalho ─────────────────────────────────────────────────────────
    $data_conselho = isset($_POST['data_conselho']) ? sanitize_text_field(wp_unslash($_POST['data_conselho'])) : '';
    $sala          = isset($_POST['sala'])          ? sanitize_text_field(wp_unslash($_POST['sala']))          : '';
    $hora_inicio   = isset($_POST['hora_inicio'])   ? sanitize_text_field(wp_unslash($_POST['hora_inicio']))   : '';
    $presidido_por = isset($_POST['presidido_por']) ? sanitize_text_field(wp_unslash($_POST['presidido_por'])) : '';
    $como_decorreu = isset($_POST['como_decorreu']) ? sanitize_textarea_field(wp_unslash($_POST['como_decorreu'])) : '';
    $observacao_dp = isset($_POST['observacao_dp']) ? sanitize_textarea_field(wp_unslash($_POST['observacao_dp'])) : '';

    // observacao_dp só pode ser editada por DP ou superior.
    // v12.11.4: quando há matriz SIGE activa, a decisão passa por permissões reais.
    $pode_obs_dp = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))
                || current_user_can('sige_director')
                || current_user_can('sige_pedagogico');
    if (function_exists('sige_user_can_any_secure')) {
        $pode_obs_dp = sige_user_can_any_secure(['academico.aprovar_notas','academico.actas_emitir'], ['sige_director','sige_pedagogico']);
    }

    $upd = [
        'data_conselho' => $data_conselho ?: null,
        'sala'          => $sala,
        'hora_inicio'   => $hora_inicio ?: null,
        'presidido_por' => $presidido_por,
        'como_decorreu' => $como_decorreu,
    ];
    if ($pode_obs_dp) $upd['observacao_dp'] = $observacao_dp;

    // v12.10.81 - confirmação controlada do Censo Escolar oficial de 3 de Março.
    // Só grava quando o utilizador assinala explicitamente a confirmação.
    if (!empty($_POST['censo3_confirmar'])) {
        $censo_m_raw  = isset($_POST['censo3_m'])  ? sanitize_text_field(wp_unslash($_POST['censo3_m']))  : '';
        $censo_hm_raw = isset($_POST['censo3_hm']) ? sanitize_text_field(wp_unslash($_POST['censo3_hm'])) : '';
        $censo_obs    = isset($_POST['censo3_observacao']) ? sanitize_textarea_field(wp_unslash($_POST['censo3_observacao'])) : '';
        if ($censo_m_raw === '' || $censo_hm_raw === '' || !is_numeric($censo_m_raw) || !is_numeric($censo_hm_raw)) {
            wp_die('Para confirmar o Censo 3 de Março, preencha os valores M e HM com números válidos.', 'SIGE - ACTA', ['response' => 422]);
        }
        $censo_result = function_exists('sige_acta_censo_save_snapshot')
            ? sige_acta_censo_save_snapshot($turma_id, $ano, $eid, max(0, (int)$censo_m_raw), max(0, (int)$censo_hm_raw), $censo_obs, $acta_id)
            : ['ok' => false, 'msg' => 'Função de Censo 3 de Março indisponível.'];
        if (empty($censo_result['ok'])) {
            wp_die(esc_html($censo_result['msg'] ?? 'Erro ao guardar o Censo 3 de Março.'), 'SIGE - ACTA', ['response' => 422]);
        }
    }

    $wpdb->update(
        "{$p}sige_acta_conselho_notas",
        $upd,
        ['id' => $acta_id, 'escola_id' => $eid]
    );

    // ── Presenças (replace all) ──────────────────────────────────────────
    if (isset($_POST['presencas']) && is_array($_POST['presencas'])) {
        $wpdb->delete("{$p}sige_acta_presencas", ['acta_id' => $acta_id, 'escola_id' => $eid]);
        $ordem = 0;
        foreach ((array) $_POST['presencas'] as $pr) {
            $nome = sanitize_text_field(wp_unslash($pr['nome'] ?? ''));
            if ($nome === '') continue;
            $wpdb->insert("{$p}sige_acta_presencas", [
                'escola_id'       => $eid,
                'acta_id'         => $acta_id,
                'user_id'         => isset($pr['user_id']) ? (int) $pr['user_id'] : null,
                'nome'            => $nome,
                'funcao'          => sanitize_text_field(wp_unslash($pr['funcao'] ?? '')),
                'disciplina_id'   => isset($pr['disciplina_id']) && $pr['disciplina_id'] !== '' ? (int) $pr['disciplina_id'] : null,
                'disciplina_nome' => sanitize_text_field(wp_unslash($pr['disciplina_nome'] ?? '')),
                'status'          => in_array(($pr['status'] ?? 'presente'), ['presente', 'ausente'], true) ? $pr['status'] : 'presente',
                'ordem'           => $ordem++,
            ]);
        }
    }

    // ── Nota Votada (upsert seguro; preserva aprovação e rastreabilidade) ─────────────────
    if (isset($_POST['nota_votada']) && is_array($_POST['nota_votada'])) {
        $existing_nv = [];
        $old_rows_nv = (array) $wpdb->get_results($wpdb->prepare(
            "SELECT *
             FROM {$p}sige_acta_nota_votada
             WHERE acta_id = %d AND escola_id = %d",
            $acta_id, $eid
        ));
        foreach ($old_rows_nv as $old_nv) {
            $existing_nv[(int) $old_nv->id] = $old_nv;
        }

        $submitted_ids = [];
        $ordem = 0;

        foreach ((array) $_POST['nota_votada'] as $nv) {
            $aluno_id = isset($nv['aluno_id']) ? (int) $nv['aluno_id'] : 0;
            if ($aluno_id <= 0) continue;

            $old_id         = isset($nv['id']) ? (int) $nv['id'] : 0;
            $old_nv         = $old_id > 0 && isset($existing_nv[$old_id]) ? $existing_nv[$old_id] : null;
            $disciplina_id  = isset($nv['disciplina_id']) && $nv['disciplina_id'] !== '' ? (int) $nv['disciplina_id'] : null;
            $numero_chamada = isset($nv['numero_chamada']) && $nv['numero_chamada'] !== '' ? (int) $nv['numero_chamada'] : null;
            $nota_inicial   = isset($nv['nota_inicial']) && $nv['nota_inicial'] !== '' ? (float) $nv['nota_inicial'] : null;
            $nota_final     = isset($nv['nota_final'])   && $nv['nota_final']   !== '' ? (float) $nv['nota_final']   : null;
            $recomendacoes  = sanitize_textarea_field(wp_unslash($nv['recomendacoes'] ?? ''));

            $row_core = [
                'aluno_id'       => $aluno_id,
                'disciplina_id'  => $disciplina_id,
                'numero_chamada' => $numero_chamada,
                'nota_inicial'   => $nota_inicial,
                'nota_final'     => $nota_final,
                'recomendacoes'  => $recomendacoes,
                'ordem'          => $ordem++,
            ];

            if ($old_nv) {
                $submitted_ids[] = $old_id;

                $was_applied = (string)($old_nv->status ?? '') === 'aprovada_aplicada';
                $changed_after_apply =
                    $was_applied && (
                        (int)$old_nv->aluno_id !== $aluno_id ||
                        (int)($old_nv->disciplina_id ?? 0) !== (int)($disciplina_id ?? 0) ||
                        (float)($old_nv->nota_final ?? -999) !== (float)($nota_final ?? -998)
                    );

                if ($changed_after_apply) {
                    wp_die(
                        'Esta Nota Votada já foi aprovada e aplicada à pauta. Para alterar, deve reverter tecnicamente a aplicação ou criar uma nova decisão do Conselho.',
                        'SIGE - ACTA',
                        ['response' => 409]
                    );
                }

                if ($was_applied) {
                    // Linha aplicada: preserva dados críticos e apenas mantém a ordem.
                    $wpdb->update(
                        "{$p}sige_acta_nota_votada",
                        ['ordem' => $row_core['ordem']],
                        ['id' => $old_id, 'acta_id' => $acta_id, 'escola_id' => $eid]
                    );
                } else {
                    $row_core['status'] = 'pendente_dp';
                    $wpdb->update(
                        "{$p}sige_acta_nota_votada",
                        $row_core,
                        ['id' => $old_id, 'acta_id' => $acta_id, 'escola_id' => $eid]
                    );
                }
            } else {
                $row_core['escola_id'] = $eid;
                $row_core['acta_id'] = $acta_id;
                $row_core['status'] = 'pendente_dp';
                $wpdb->insert("{$p}sige_acta_nota_votada", $row_core);
                if ($wpdb->insert_id) $submitted_ids[] = (int) $wpdb->insert_id;
            }
        }

        // Remove apenas rascunhos/pendentes que deixaram de vir do formulário.
        // Linhas já aplicadas à pauta nunca são apagadas por remoção acidental na UI.
        foreach ($existing_nv as $old_id => $old_nv) {
            if (in_array((int)$old_id, $submitted_ids, true)) continue;
            if ((string)($old_nv->status ?? '') === 'aprovada_aplicada') continue;
            $wpdb->delete("{$p}sige_acta_nota_votada", ['id' => (int)$old_id, 'acta_id' => $acta_id, 'escola_id' => $eid]);
        }
    }

    // ── Cumprimento de Programas (upsert por disciplina_nome) ────────────
    if (isset($_POST['cumprimento']) && is_array($_POST['cumprimento'])) {
        $wpdb->delete("{$p}sige_acta_cumprimento_programas", ['acta_id' => $acta_id, 'escola_id' => $eid]);
        foreach ((array) $_POST['cumprimento'] as $cp) {
            $disc_nome = sanitize_text_field(wp_unslash($cp['disciplina_nome'] ?? ''));
            if ($disc_nome === '') continue;
            $wpdb->insert("{$p}sige_acta_cumprimento_programas", [
                'escola_id'       => $eid,
                'acta_id'         => $acta_id,
                'disciplina_id'   => isset($cp['disciplina_id']) && $cp['disciplina_id'] !== '' ? (int) $cp['disciplina_id'] : null,
                'disciplina_nome' => $disc_nome,
                'ultimo_tema'     => sanitize_textarea_field(wp_unslash($cp['ultimo_tema'] ?? '')),
                'aulas_em_atraso' => isset($cp['aulas_em_atraso']) && $cp['aulas_em_atraso'] !== '' ? (int) $cp['aulas_em_atraso'] : null,
                'razoes_atraso'   => sanitize_textarea_field(wp_unslash($cp['razoes_atraso'] ?? '')),
            ]);
        }
    }

    // ── Audit log ────────────────────────────────────────────────────────
    if (function_exists('sige_audit_log')) {
        sige_audit_log('acta_guardada', [
            'acta_id'   => $acta_id,
            'turma_id'  => $turma_id,
            'ano'       => $ano,
            'trimestre' => $trimestre,
        ], 'notas');
    }

    // ── Redirect back ────────────────────────────────────────────────────
    $redirect = isset($_POST['_redirect']) ? esc_url_raw(wp_unslash($_POST['_redirect'])) : admin_url('admin.php?page=sige-app&view=acta');
    $redirect = add_query_arg(['saved' => 1, 'acta_id' => $acta_id], $redirect);
    wp_safe_redirect($redirect);
    exit;
}


// ═════════════════════════════════════════════════════════════════════════════
// HANDLER - APROVAR E APLICAR NOTA VOTADA À PAUTA OFICIAL
// ═════════════════════════════════════════════════════════════════════════════

function sige_acta_aprovar_nota_votada_handler() {
    if (!sige_acta_can_aprovar_nota_votada()) {
        wp_die('Acesso negado. Apenas Director Pedagógico, Director ou Administração podem aprovar e aplicar nota votada.', 'SIGE - ACTA', ['response' => 403]);
    }

    $nota_votada_id = isset($_REQUEST['nota_votada_id']) ? (int) $_REQUEST['nota_votada_id'] : 0;
    if ($nota_votada_id <= 0) {
        wp_die('Nota votada inválida.', 'SIGE - ACTA');
    }

    $nonce_action = 'sige_acta_aprovar_nota_votada_' . $nota_votada_id;
    if (!isset($_REQUEST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_REQUEST['_wpnonce'])), $nonce_action)) {
        wp_die('Nonce inválido ou expirado. Recarregue a página e tente novamente.', 'SIGE - ACTA', ['response' => 403]);
    }

    global $wpdb;
    $eid = sige_require_escola_id('acta_aprovar_nota_votada');

    $result = sige_acta_aplicar_nota_votada($nota_votada_id, $eid);

    $redirect = isset($_REQUEST['_redirect'])
        ? esc_url_raw(wp_unslash($_REQUEST['_redirect']))
        : admin_url('admin.php?page=sige-app&view=acta');

    $redirect = add_query_arg([
        'acta_apply' => $result['ok'] ? 'ok' : 'erro',
        'acta_msg'   => rawurlencode((string) ($result['message'] ?? '')),
    ], $redirect);

    wp_safe_redirect($redirect);
    exit;
}

