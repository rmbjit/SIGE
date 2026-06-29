<?php
/** SIGE SoftGenial - Jardim de Infância: handlers v78. */
if (!defined('ABSPATH')) exit;

function sige_jardim_can_manage_v78(?string $permissao = null): bool {
    $legacy = sige_jardim_manage_legacy_caps_v104();
    if ($permissao !== null && $permissao !== '') {
        return sige_jardim_permission_allows_v104([$permissao], $legacy);
    }
    return sige_jardim_permission_allows_v104([
        'jardim.diario_gerir','jardim.saude_gerir','jardim.presencas_gerir',
        'jardim.boletim_emitir','jardim.relatorio_emitir'
    ], $legacy);
}
function sige_jardim_redirect_v78(string $view, array $args = []): void {
    wp_safe_redirect(add_query_arg(array_merge(['page'=>'sige-app','view'=>$view], $args), admin_url('admin.php'))); exit;
}

// ============================================================================
// v12.10.104 - Jardim de Infância: helpers centrais de fluxo e fonte da verdade
// ============================================================================
// Objectivo: todas as views e handlers do Jardim usam a mesma fonte de verdade:
// turma pré-escolar válida + aluno activo + matrícula activa + escopo do utilizador.

if (!function_exists('sige_jardim_sql_alias_safe_v104')) {
    function sige_jardim_sql_alias_safe_v104(string $alias, string $fallback = 't'): string {
        $alias = trim($alias);
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) ? $alias : $fallback;
    }
}

if (!function_exists('sige_jardim_table_exists_v104')) {
    function sige_jardim_table_exists_v104(string $table): bool {
        global $wpdb;
        if ($table === '') return false;
        return $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) === $table;
    }
}

if (!function_exists('sige_jardim_column_exists_v104')) {
    function sige_jardim_column_exists_v104(string $table, string $column): bool {
        global $wpdb;
        if ($table === '' || $column === '') return false;
        if (function_exists('sige_db_column_exists')) return (bool)sige_db_column_exists($table, $column);
        return (bool)$wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", $column));
    }
}

if (!function_exists('sige_jardim_turma_activa_sql_v104')) {
    function sige_jardim_turma_activa_sql_v104(string $alias = 't'): string {
        $alias = sige_jardim_sql_alias_safe_v104($alias, 't');
        return "({$alias}.status_turma IS NULL OR TRIM(LOWER({$alias}.status_turma)) IN ('activa','ativa','activo','ativo'))";
    }
}

if (!function_exists('sige_jardim_preescolar_sql_v104')) {
    function sige_jardim_preescolar_sql_v104(string $alias = 't'): string {
        global $wpdb;
        $alias = sige_jardim_sql_alias_safe_v104($alias, 't');
        $turmas = $wpdb->prefix . 'sige_turmas';
        $fallback = "(
            {$alias}.classe LIKE '%Pré%' OR {$alias}.classe LIKE '%Pre%' OR {$alias}.classe LIKE '%Creche%' OR {$alias}.classe LIKE '%Jardim%'
            OR {$alias}.nome LIKE '%Pré%' OR {$alias}.nome LIKE '%Pre%' OR {$alias}.nome LIKE '%Creche%' OR {$alias}.nome LIKE '%Jardim%'
            OR {$alias}.classe IN ('2º/3º Ano','2º/3º ano','2/3 Ano','2/3 ano','2/3 anos','3º Ano','3º ano','4º Ano','4º ano','4 anos','5 anos','5 Anos','Pré','Pre','Pré-primário','Pre-primario','Pré-escolar','Pre-escolar')
            OR {$alias}.nome IN ('Casa 2/3 anos','Casa dos 2/3 anos','Casa 2 e 3 anos','Casa dos 2 e 3 anos','Casa 4 anos','Casa dos 4 anos','Casa 5 anos','Casa dos 5 anos','Pré','Pre','Pré-escolar','Pre-escolar')
            OR ({$alias}.nome LIKE '%Casa%' AND ({$alias}.nome LIKE '%2/3%' OR {$alias}.nome LIKE '%2 e 3%' OR {$alias}.nome LIKE '%4 anos%' OR {$alias}.nome LIKE '%5 anos%'))
            OR ({$alias}.classe LIKE '%Casa%' AND ({$alias}.classe LIKE '%2/3%' OR {$alias}.classe LIKE '%2 e 3%' OR {$alias}.classe LIKE '%4 anos%' OR {$alias}.classe LIKE '%5 anos%'))
            OR ({$alias}.nome REGEXP '(^|[^0-9])5[[:space:]]*anos([^0-9]|$)')
            OR ({$alias}.classe REGEXP '(^|[^0-9])5[[:space:]]*anos([^0-9]|$)')
        )";
        if (sige_jardim_column_exists_v104($turmas, 'is_preescolar')) {
            return "(({$alias}.is_preescolar = 1) OR {$fallback})";
        }
        return $fallback;
    }
}

if (!function_exists('sige_jardim_aluno_matricula_activa_sql_v104')) {
    function sige_jardim_aluno_matricula_activa_sql_v104(string $aluno_alias = 'a', string $matricula_alias = 'm'): string {
        if (function_exists('sige_aluno_matricula_activa_sql')) {
            return sige_aluno_matricula_activa_sql($aluno_alias, $matricula_alias);
        }
        $a = sige_jardim_sql_alias_safe_v104($aluno_alias, 'a');
        $m = sige_jardim_sql_alias_safe_v104($matricula_alias, 'm');
        return "({$a}.status IS NULL OR TRIM(LOWER({$a}.status)) IN ('activo','ativo','activa','ativa')) AND ({$m}.status_matricula IS NULL OR TRIM(LOWER({$m}.status_matricula)) IN ('activa','ativa','activo','ativo'))";
    }
}

if (!function_exists('sige_jardim_permission_allows_v104')) {
    function sige_jardim_permission_allows_v104(array $permissoes, array $legacy_caps = []): bool {
        if (function_exists('sige_page_guard_allows')) return sige_page_guard_allows($permissoes, $legacy_caps);
        if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return true;
        if (function_exists('sige_can')) {
            foreach ($permissoes as $perm) if ($perm && sige_can((string)$perm)) return true;
        }
        foreach ($legacy_caps as $cap) if ($cap && current_user_can((string)$cap)) return true;
        return false;
    }
}

if (!function_exists('sige_jardim_manage_legacy_caps_v104')) {
    function sige_jardim_manage_legacy_caps_v104(): array {
        return ['sige_assistente','sige_director','sige_educador','sige_professor','sige_secretario','sige_secretaria_geral'];
    }
}

if (!function_exists('sige_jardim_supervisor_v104')) {
    function sige_jardim_supervisor_v104(): bool {
        if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return true;
        if (function_exists('sige_permissions_get_active_role')) {
            $role = sige_permissions_get_active_role(get_current_user_id());
            $slug = $role && !empty($role->slug) ? sanitize_key((string)$role->slug) : '';
            if ($slug && in_array($slug, ['admin_escola','admin_ti','direccao_geral','dir_pedagogico','secretaria_geral','secretario','assistente','recepcao','tesoureiro'], true)) {
                return true;
            }
            if ($slug && in_array($slug, ['educador','professor'], true)) return false;
        }
        return current_user_can('sige_director') || current_user_can('sige_secretario') || current_user_can('sige_secretaria_geral') || current_user_can('sige_assistente');
    }
}

if (!function_exists('sige_jardim_current_professor_id_v104')) {
    function sige_jardim_current_professor_id_v104(int $escola_id = 0): int {
        global $wpdb;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        $user = wp_get_current_user();
        if (!$user || empty($user->user_email)) return 0;
        $tbl = $wpdb->prefix . 'sige_professores';
        if (!sige_jardim_table_exists_v104($tbl)) return 0;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$tbl} WHERE escola_id=%d AND email=%s LIMIT 1",
            $escola_id,
            (string)$user->user_email
        ));
    }
}

if (!function_exists('sige_jardim_get_preescolar_turmas_v104')) {
    function sige_jardim_get_preescolar_turmas_v104(int $escola_id, int $ano_lectivo, bool $respeitar_escopo = true): array {
        global $wpdb;
        $tT = $wpdb->prefix . 'sige_turmas';
        $pre = sige_jardim_preescolar_sql_v104('t');
        $act = sige_jardim_turma_activa_sql_v104('t');
        $cols = 't.id, t.nome, t.classe, t.director_turma_id';
        if (sige_jardim_column_exists_v104($tT, 'turno')) $cols .= ', t.turno';

        $turmas = $wpdb->get_results($wpdb->prepare(
            "SELECT {$cols}
             FROM {$tT} t
             WHERE t.escola_id=%d
               AND t.ano_lectivo=%d
               AND {$act}
               AND {$pre}
             ORDER BY t.classe ASC, t.nome ASC",
            $escola_id, $ano_lectivo
        ));

        if (!$respeitar_escopo || sige_jardim_supervisor_v104()) return (array)$turmas;

        $prof_id = sige_jardim_current_professor_id_v104($escola_id);
        if ($prof_id <= 0) return [];
        return array_values(array_filter((array)$turmas, static function($t) use ($prof_id) {
            return isset($t->director_turma_id) && (int)$t->director_turma_id === (int)$prof_id;
        }));
    }
}

if (!function_exists('sige_jardim_turma_valida_v104')) {
    function sige_jardim_turma_valida_v104(int $turma_id, int $escola_id, int $ano_lectivo, bool $respeitar_escopo = true): bool {
        if ($turma_id <= 0 || $escola_id <= 0 || $ano_lectivo <= 0) return false;
        $turmas = sige_jardim_get_preescolar_turmas_v104($escola_id, $ano_lectivo, $respeitar_escopo);
        foreach ((array)$turmas as $t) {
            if ((int)$t->id === $turma_id) return true;
        }
        return false;
    }
}

if (!function_exists('sige_jardim_get_alunos_activos_turma_v104')) {
    function sige_jardim_get_alunos_activos_turma_v104(int $turma_id, int $escola_id, int $ano_lectivo, string $select = ''): array {
        global $wpdb;
        if ($turma_id <= 0 || $escola_id <= 0 || $ano_lectivo <= 0) return [];
        $select = trim($select) ?: 'a.id, a.nome_completo, a.foto, a.genero';
        $tA = $wpdb->prefix . 'sige_alunos';
        $tM = $wpdb->prefix . 'sige_matriculas';
        $am = sige_jardim_aluno_matricula_activa_sql_v104('a', 'm');
        return (array)$wpdb->get_results($wpdb->prepare(
            "SELECT DISTINCT {$select}
             FROM {$tA} a
             INNER JOIN {$tM} m
                ON m.aluno_id = a.id
               AND m.escola_id = a.escola_id
             WHERE a.escola_id = %d
               AND m.escola_id = %d
               AND m.turma_id = %d
               AND m.ano_lectivo = %d
               AND {$am}
             ORDER BY a.nome_completo ASC",
            $escola_id, $escola_id, $turma_id, $ano_lectivo
        ));
    }
}

if (!function_exists('sige_jardim_get_aluno_ids_activos_turma_v104')) {
    function sige_jardim_get_aluno_ids_activos_turma_v104(int $turma_id, int $escola_id, int $ano_lectivo): array {
        $rows = sige_jardim_get_alunos_activos_turma_v104($turma_id, $escola_id, $ano_lectivo, 'a.id');
        $ids = [];
        foreach ($rows as $r) $ids[(int)$r->id] = true;
        return $ids;
    }
}

if (!function_exists('sige_jardim_aluno_activo_na_turma_v104')) {
    function sige_jardim_aluno_activo_na_turma_v104(int $aluno_id, int $turma_id, int $escola_id, int $ano_lectivo): bool {
        if ($aluno_id <= 0) return false;
        $ids = sige_jardim_get_aluno_ids_activos_turma_v104($turma_id, $escola_id, $ano_lectivo);
        return isset($ids[$aluno_id]);
    }
}

if (!function_exists('sige_jardim_get_disciplinas_turma_v104')) {
    function sige_jardim_get_disciplinas_turma_v104(int $turma_id, int $escola_id): array {
        global $wpdb;
        $tT  = $wpdb->prefix . 'sige_turmas';
        $tD  = $wpdb->prefix . 'sige_disciplinas';
        $tTD = $wpdb->prefix . 'sige_turma_disciplinas';
        $tMC = $wpdb->prefix . 'sige_matriz_curricular';

        $turma = $wpdb->get_row($wpdb->prepare("SELECT classe, nome FROM {$tT} WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id, $escola_id));
        $classe = $turma ? (string)($turma->classe ?? '') : '';
        $turma_nome = $turma ? (string)($turma->nome ?? '') : '';
        $classe_aliases = function_exists('sige_jardim_classe_aliases_v106') ? sige_jardim_classe_aliases_v106($classe, $turma_nome) : array_filter([$classe]);
        $classe_aliases = array_values(array_unique(array_filter($classe_aliases)));

        $hasTD = sige_jardim_table_exists_v104($tTD);
        $hasMC = sige_jardim_table_exists_v104($tMC);
        if ($turma_id > 0 && ($hasTD || ($hasMC && $classe !== ''))) {
            $parts = [];
            if ($hasTD) {
                $parts[] = $wpdb->prepare("SELECT disciplina_id FROM {$tTD} WHERE turma_id=%d AND escola_id=%d", $turma_id, $escola_id);
            }
            if ($hasMC && !empty($classe_aliases)) {
                $classe_ph = implode(',', array_fill(0, count($classe_aliases), '%s'));
                $parts[] = $wpdb->prepare(
                    "SELECT disciplina_id FROM {$tMC} WHERE escola_id=%d AND classe IN ({$classe_ph})",
                    array_merge([$escola_id], $classe_aliases)
                );
            }
            $union = implode(' UNION ', $parts);
            if ($union !== '') {
                if ($hasMC) {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT DISTINCT d.id, d.nome, d.sigla, COALESCE(d.ordem, 999) AS ordem
                         FROM {$tD} d
                         WHERE d.escola_id=%d
                           AND (d.activo IS NULL OR d.activo=1)
                           AND d.id IN ({$union})
                         ORDER BY ordem ASC, d.nome ASC",
                        $escola_id
                    ));
                } else {
                    $rows = $wpdb->get_results($wpdb->prepare(
                        "SELECT DISTINCT d.id, d.nome, d.sigla, COALESCE(d.ordem, 999) AS ordem
                         FROM {$tD} d
                         WHERE d.escola_id=%d
                           AND (d.activo IS NULL OR d.activo=1)
                           AND d.id IN ({$union})
                         ORDER BY ordem ASC, d.nome ASC",
                        $escola_id
                    ));
                }
                if (!empty($rows)) {
                    if (function_exists('sige_jardim_sync_indicadores_para_criterios_v106')) {
                        sige_jardim_sync_indicadores_para_criterios_v106(array_map('intval', array_column((array)$rows, 'id')), (int)$escola_id);
                    }
                    return (array)$rows;
                }
            }
        }

        // Fallback histórico: disciplinas marcadas como Pré-Escolar.
        $fallback_rows = (array)$wpdb->get_results($wpdb->prepare(
            "SELECT id, nome, sigla, COALESCE(ordem,999) AS ordem
             FROM {$tD}
             WHERE escola_id=%d
               AND (activo IS NULL OR activo=1)
               AND (ciclos LIKE %s OR ciclos LIKE %s OR nome LIKE %s)
             ORDER BY ordem ASC, nome ASC",
            $escola_id, '%PreEscolar%', '%Pré%', '%Pré%'
        ));
        if (!empty($fallback_rows) && function_exists('sige_jardim_sync_indicadores_para_criterios_v106')) {
            sige_jardim_sync_indicadores_para_criterios_v106(array_map('intval', array_column((array)$fallback_rows, 'id')), (int)$escola_id);
        }
        return $fallback_rows;
    }
}


// ============================================================================
// v12.10.106 - Jardim: reactivação segura e critérios a partir dos indicadores
// ============================================================================
// Regras:
// - Não inventa critérios. Apenas sincroniza indicadores já criados pela escola
//   em sige_disciplinas_indicadores para a fonte do Jardim, sige_jardim_criterios.
// - Não torna transferidos/desistentes activos. Só corrige matrícula quando o
//   aluno já está marcado como activo e não possui matrícula activa no ano.

if (!function_exists('sige_jardim_norm_text_v106')) {
    function sige_jardim_norm_text_v106(string $s): string {
        $s = strtolower(trim($s));
        $s = str_replace(
            ['á','à','ã','â','ä','é','è','ê','ë','í','ì','î','ï','ó','ò','õ','ô','ö','ú','ù','û','ü','ç','º','ª','-','_'],
            ['a','a','a','a','a','e','e','e','e','i','i','i','i','o','o','o','o','o','u','u','u','u','c','','',' ',' '],
            $s
        );
        $s = preg_replace('/\s+/', ' ', $s);
        return trim((string)$s);
    }
}

if (!function_exists('sige_jardim_classe_aliases_v106')) {
    function sige_jardim_classe_aliases_v106(string $classe, string $nome = ''): array {
        $base = array_values(array_unique(array_filter([$classe, $nome])));
        $n = sige_jardim_norm_text_v106($classe . ' ' . $nome);
        $aliases = $base;

        if (preg_match('/\b2\b/', $n) && preg_match('/\b3\b/', $n)) {
            $aliases = array_merge($aliases, [
                '2º/3º Ano', '2º/3º ano', '2/3 Ano', '2/3 ano', '2/3 Anos', '2/3 anos',
                'Casa 2/3 anos', 'Casa dos 2/3 anos', 'Casa 2 e 3 anos', 'Casa dos 2 e 3 anos',
                '2-3 anos', '2 a 3 anos'
            ]);
        }
        if (preg_match('/\b4\b/', $n)) {
            $aliases = array_merge($aliases, [
                '4º Ano', '4º ano', '4 Ano', '4 ano', '4 Anos', '4 anos',
                'Casa 4 anos', 'Casa dos 4 anos'
            ]);
        }
        // 5 anos é Jardim/Pré, mas não deve ser confundido com 5ª classe.
        // Por isso, só entra quando aparece como idade ("5 anos") ou como "Casa 5 anos".
        if (preg_match('/(^|[^0-9])5\s*anos([^0-9]|$)/', $n) || strpos($n, 'casa 5') !== false || strpos($n, 'pre') !== false) {
            $aliases = array_merge($aliases, [
                '5 anos', '5 Anos',
                'Casa 5 anos', 'Casa dos 5 anos',
                'Pré', 'Pre', 'Pré-escolar', 'Pre-escolar',
                'Pré-primário', 'Pre-primario'
            ]);
        }
        if (strpos($n, 'pre') !== false || strpos($n, 'jardim') !== false || strpos($n, 'creche') !== false) {
            $aliases = array_merge($aliases, ['Pré','Pre','Pré-primário','Pre-primario','Pré-escolar','Pre-escolar','Jardim','Creche']);
        }

        return array_values(array_unique(array_filter(array_map('trim', $aliases))));
    }
}

if (!function_exists('sige_jardim_reparar_matriculas_alunos_reativados_v106')) {
    function sige_jardim_reparar_matriculas_alunos_reativados_v106(int $escola_id, int $ano_lectivo): int {
        global $wpdb;
        if ($escola_id <= 0 || $ano_lectivo <= 0) return 0;

        $tA = $wpdb->prefix . 'sige_alunos';
        $tM = $wpdb->prefix . 'sige_matriculas';

        if (!sige_jardim_table_exists_v104($tA) || !sige_jardim_table_exists_v104($tM)) return 0;
        if (!sige_jardim_column_exists_v104($tM, 'status_matricula')) return 0;

        // v12.10.108:
        // Reparação sem UPDATE com subquery sobre a mesma tabela, evitando
        // incompatibilidades em algumas versões MariaDB/MySQL.
        //
        // Regra de segurança:
        // - só actua se o status principal do aluno já voltou a activo/ativo/activa/ativa;
        // - não reactiva alunos que continuam transferidos, desistentes ou inactivos;
        // - só corrige se não existir outra matrícula activa no mesmo ano;
        // - apenas a matrícula mais recente do ano é reactivada.

        $candidatos = $wpdb->get_results($wpdb->prepare("
            SELECT m.id
            FROM {$tM} m
            INNER JOIN {$tA} a
                    ON a.id = m.aluno_id
                   AND a.escola_id = m.escola_id
            LEFT JOIN {$tM} ma
                   ON ma.aluno_id = m.aluno_id
                  AND ma.escola_id = m.escola_id
                  AND ma.ano_lectivo = m.ano_lectivo
                  AND (ma.status_matricula IS NULL OR TRIM(LOWER(ma.status_matricula)) IN ('activa','ativa','activo','ativo'))
            INNER JOIN (
                SELECT aluno_id, escola_id, ano_lectivo, MAX(id) AS max_id
                FROM {$tM}
                WHERE escola_id = %d
                  AND ano_lectivo = %d
                GROUP BY aluno_id, escola_id, ano_lectivo
            ) ult
                    ON ult.max_id = m.id
            WHERE m.escola_id = %d
              AND m.ano_lectivo = %d
              AND TRIM(LOWER(COALESCE(a.status,''))) IN ('activo','ativo','activa','ativa')
              AND ma.id IS NULL
              AND TRIM(LOWER(COALESCE(m.status_matricula,''))) IN (
                    'inactiva','inativa','inactivo','inativo',
                    'cancelada','cancelado',
                    'desistente','desistiu',
                    'transferido','transferida','transferido_saida','transferido saida','transferido saída'
              )
        ", $escola_id, $ano_lectivo, $escola_id, $ano_lectivo));

        if (!$candidatos) return 0;

        $ids = array_map('intval', array_column((array)$candidatos, 'id'));
        $ids = array_values(array_filter(array_unique($ids)));
        if (!$ids) return 0;

        $ph = implode(',', array_fill(0, count($ids), '%d'));
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tM} SET status_matricula='activa' WHERE escola_id=%d AND ano_lectivo=%d AND id IN ({$ph})",
            array_merge([$escola_id, $ano_lectivo], $ids)
        ));

        return max(0, (int)$wpdb->rows_affected);
    }
}

if (!function_exists('sige_jardim_sync_indicadores_para_criterios_v106')) {
    function sige_jardim_sync_indicadores_para_criterios_v106(array $disciplina_ids, int $escola_id, int $ano_lectivo = 0): int {
        global $wpdb;
        $disciplina_ids = array_values(array_unique(array_filter(array_map('intval', $disciplina_ids))));
        if (!$disciplina_ids || $escola_id <= 0) return 0;

        $tI = $wpdb->prefix . 'sige_disciplinas_indicadores';
        $tC = $wpdb->prefix . 'sige_jardim_criterios';
        if (!sige_jardim_table_exists_v104($tI) || !sige_jardim_table_exists_v104($tC)) return 0;

        $ph = implode(',', array_fill(0, count($disciplina_ids), '%d'));
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, disciplina_id, criterio
             FROM {$tI}
             WHERE escola_id=%d
               AND disciplina_id IN ({$ph})
               AND TRIM(COALESCE(criterio,'')) <> ''
             ORDER BY disciplina_id ASC, id ASC",
            array_merge([$escola_id], $disciplina_ids)
        ));
        if (!$rows) return 0;

        $hasClasse = sige_jardim_column_exists_v104($tC, 'classe');
        $hasAno    = sige_jardim_column_exists_v104($tC, 'ano_lectivo');
        $hasTri    = sige_jardim_column_exists_v104($tC, 'trimestre');
        $hasAtivo  = sige_jardim_column_exists_v104($tC, 'ativo');
        $hasVersao = sige_jardim_column_exists_v104($tC, 'versao');
        $hasCA     = sige_jardim_column_exists_v104($tC, 'created_at');
        $hasUA     = sige_jardim_column_exists_v104($tC, 'updated_at');

        $inserted = 0;
        foreach ($rows as $r) {
            $disc_id = (int)$r->disciplina_id;
            $desc = trim(wp_strip_all_tags((string)$r->criterio));
            if ($disc_id <= 0 || $desc === '') continue;

            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tC}
                 WHERE escola_id=%d
                   AND disciplina_id=%d
                   AND TRIM(LOWER(descricao)) = TRIM(LOWER(%s))
                 LIMIT 1",
                $escola_id, $disc_id, $desc
            ));
            if ($exists) continue;

            $data = [
                'escola_id' => $escola_id,
                'disciplina_id' => $disc_id,
                'descricao' => $desc,
                'ordem' => (int)$r->id,
            ];
            if ($hasClasse) $data['classe'] = null;
            if ($hasAno)    $data['ano_lectivo'] = null; // reutilizável em qualquer ano
            if ($hasTri)    $data['trimestre'] = null;   // reutilizável em qualquer trimestre
            if ($hasAtivo)  $data['ativo'] = 1;
            if ($hasVersao) $data['versao'] = 1;
            if ($hasCA)     $data['created_at'] = current_time('mysql');
            if ($hasUA)     $data['updated_at'] = current_time('mysql');

            if ($wpdb->insert($tC, $data) !== false) $inserted++;
        }
        return $inserted;
    }
}

if (!function_exists('sige_jardim_criterios_extra_sql_v106')) {
    function sige_jardim_criterios_extra_sql_v106(string $table, int $ano_lectivo, int $trimestre): string {
        $extra = '';
        if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($table, 'ativo')) {
            $extra .= " AND (ativo IS NULL OR ativo=1)";
        }
        if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($table, 'ano_lectivo')) {
            $extra .= " AND (ano_lectivo IS NULL OR ano_lectivo=0 OR ano_lectivo=" . (int)$ano_lectivo . ")";
        }
        if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($table, 'trimestre')) {
            $extra .= " AND (trimestre IS NULL OR trimestre=0 OR trimestre=" . (int)$trimestre . ")";
        }
        return $extra;
    }
}


function sige_jardim_upsert_v78(string $table, array $where, array $data): bool {
    global $wpdb; $clauses=[]; $vals=[];
    foreach ($where as $k=>$v) { $clauses[] = "$k = ".(is_int($v) ? '%d' : '%s'); $vals[]=$v; }
    $id = $wpdb->get_var($wpdb->prepare("SELECT id FROM $table WHERE ".implode(' AND ', $clauses)." LIMIT 1", $vals));
    if ($id) return $wpdb->update($table, $data, ['id'=>(int)$id]) !== false;
    return (bool)$wpdb->insert($table, array_merge($where, $data));
}
function sige_jardim_nivel_v78(array $statuses): string {
    if (!$statuses) return '';
    $total = 0; foreach ($statuses as $st) { if ($st==='atingido') $total += 1; elseif ($st==='progresso') $total += .5; }
    $pct = round(($total / count($statuses)) * 100);
    return $pct >= 85 ? 'MB' : ($pct >= 70 ? 'B' : ($pct >= 50 ? 'S' : 'NS'));
}
function sige_jardim_periodo_mes_v78(string $mes): array {
    if (!preg_match('/^\d{4}-\d{2}$/', $mes)) $mes = wp_date('Y-m');
    $ini = $mes.'-01'; $fim = wp_date('Y-m-t', strtotime($ini));
    return [$ini, min($fim, wp_date('Y-m-d'))];
}

function sige_jardim_presencas_table_v81(): string {
    global $wpdb;
    return $wpdb->prefix . 'sige_jardim_presencas';
}
function sige_jardim_ensure_presencas_table_v81(): void {
    global $wpdb;
    $t = sige_jardim_presencas_table_v81();
    $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s", $t));
    if ($exists) return;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset = $wpdb->get_charset_collate();
    dbDelta("CREATE TABLE $t (
        id BIGINT(20) NOT NULL AUTO_INCREMENT,
        escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
        aluno_id BIGINT(20) NOT NULL,
        turma_id BIGINT(20) DEFAULT NULL,
        data_registo DATE NOT NULL,
        ano_lectivo INT(4) NOT NULL,
        status VARCHAR(30) NOT NULL DEFAULT 'presente',
        observacao TEXT DEFAULT NULL,
        registado_por BIGINT(20) DEFAULT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uniq_presenca (escola_id, aluno_id, turma_id, data_registo, ano_lectivo),
        KEY idx_aluno (aluno_id),
        KEY idx_turma_data (turma_id, data_registo),
        KEY idx_escola_id (escola_id)
    ) $charset;");
}
function sige_jardim_presenca_resumo_v81(int $aluno_id, int $turma_id, int $ano, string $ini, string $fim): array {
    global $wpdb;
    sige_jardim_ensure_presencas_table_v81();
    $t = sige_jardim_presencas_table_v81();
    $rows = $wpdb->get_results($wpdb->prepare("SELECT status, COUNT(*) total FROM $t WHERE aluno_id=%d AND turma_id=%d AND ano_lectivo=%d AND data_registo BETWEEN %s AND %s GROUP BY status", $aluno_id, $turma_id, $ano, $ini, $fim));
    $out = ['presente'=>0, 'falta'=>0, 'falta_justificada'=>0, 'atraso'=>0, 'total'=>0];
    foreach ((array)$rows as $r) { $st = (string)$r->status; if (isset($out[$st])) $out[$st] = (int)$r->total; $out['total'] += (int)$r->total; }
    return $out;
}
function sige_jardim_presenca_ultima_v81(int $aluno_id, int $turma_id, int $ano) {
    global $wpdb;
    sige_jardim_ensure_presencas_table_v81();
    $t = sige_jardim_presencas_table_v81();
    return $wpdb->get_row($wpdb->prepare("SELECT * FROM $t WHERE aluno_id=%d AND turma_id=%d AND ano_lectivo=%d ORDER BY data_registo DESC, id DESC LIMIT 1", $aluno_id, $turma_id, $ano));
}
function sige_jardim_presenca_label_v81(string $status): string {
    $map = ['presente'=>'Presente', 'falta'=>'Falta', 'falta_justificada'=>'Falta justificada', 'atraso'=>'Atraso'];
    return $map[$status] ?? $status;
}
function sige_jardim_dias_uteis_v81(string $ini, string $fim): int {
    try { $d = new DateTime($ini); $f = new DateTime($fim); } catch (Exception $e) { return 0; }
    $n = 0; while ($d <= $f) { if ((int)$d->format('N') <= 5) $n++; $d->modify('+1 day'); }
    return $n;
}


// ============================================================================
// v12.10.105 - Jardim: Critérios PRO e Migração Segura
// ============================================================================
// Migração idempotente: prepara estrutura, limpa duplicados previsíveis e cria
// índices úteis/únicos sem inventar critérios pedagógicos.

if (!function_exists('sige_jardim_index_exists_v105')) {
    function sige_jardim_index_exists_v105(string $table, string $index): bool {
        global $wpdb;
        if ($table === '' || $index === '') return false;
        return (bool)$wpdb->get_var($wpdb->prepare("SHOW INDEX FROM {$table} WHERE Key_name=%s", $index));
    }
}

if (!function_exists('sige_jardim_add_column_v105')) {
    function sige_jardim_add_column_v105(string $table, string $column, string $definition): void {
        global $wpdb;
        if (!sige_jardim_table_exists_v104($table) || sige_jardim_column_exists_v104($table, $column)) return;
        $wpdb->query("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    }
}

if (!function_exists('sige_jardim_add_index_v105')) {
    function sige_jardim_add_index_v105(string $table, string $index_name, string $definition): void {
        global $wpdb;
        if (!sige_jardim_table_exists_v104($table) || sige_jardim_index_exists_v105($table, $index_name)) return;
        $wpdb->query("ALTER TABLE {$table} ADD {$definition}");
    }
}

if (!function_exists('sige_jardim_migracao_criterios_pro_v105')) {
    function sige_jardim_migracao_criterios_pro_v105(): void {
        if (!is_admin()) return;
        $done = get_option('sige_jardim_criterios_pro_v105_done');
        if ($done === '1') return;

        global $wpdb;
        $p  = $wpdb->prefix;
        $tC = $p.'sige_jardim_criterios';
        $tR = $p.'sige_jardim_criterios_respostas';
        $tA = $p.'sige_jardim_avaliacoes';
        $tD = $p.'sige_jardim_diario';
        $tS = $p.'sige_jardim_saude';
        $tM = $p.'sige_matriculas';

        // 1) Estrutura PRO de critérios. Não cria critérios novos; apenas prepara campos.
        if (sige_jardim_table_exists_v104($tC)) {
            sige_jardim_add_column_v105($tC, 'classe', "VARCHAR(50) DEFAULT NULL AFTER disciplina_id");
            sige_jardim_add_column_v105($tC, 'ano_lectivo', "INT(4) DEFAULT NULL AFTER classe");
            sige_jardim_add_column_v105($tC, 'trimestre', "TINYINT(1) DEFAULT NULL AFTER ano_lectivo");
            sige_jardim_add_column_v105($tC, 'ativo', "TINYINT(1) NOT NULL DEFAULT 1 AFTER ordem");
            sige_jardim_add_column_v105($tC, 'versao', "INT(4) NOT NULL DEFAULT 1 AFTER ativo");
            sige_jardim_add_column_v105($tC, 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP AFTER versao");
            sige_jardim_add_column_v105($tC, 'updated_at', "DATETIME DEFAULT CURRENT_TIMESTAMP AFTER created_at");
            sige_jardim_add_index_v105($tC, 'idx_jcrit_scope_v105', "KEY idx_jcrit_scope_v105 (escola_id, disciplina_id, ano_lectivo, trimestre, ativo)");
        }

        // 2) Respostas passam a poder guardar turma_id para preservar histórico por turma.
        if (sige_jardim_table_exists_v104($tR)) {
            sige_jardim_add_column_v105($tR, 'turma_id', "BIGINT(20) DEFAULT NULL AFTER aluno_id");

            // Preencher turma_id histórico a partir da matrícula do mesmo ano quando possível.
            if (sige_jardim_column_exists_v104($tR, 'turma_id') && sige_jardim_table_exists_v104($tM)) {
                $wpdb->query("
                    UPDATE {$tR} r
                    INNER JOIN {$tM} m
                            ON m.aluno_id = r.aluno_id
                           AND m.escola_id = r.escola_id
                           AND m.ano_lectivo = r.ano_lectivo
                    SET r.turma_id = m.turma_id
                    WHERE (r.turma_id IS NULL OR r.turma_id = 0)
                      AND m.turma_id IS NOT NULL
                ");
            }

            // Limpeza segura: mantém o ID mais recente para a mesma resposta lógica.
            if (sige_jardim_column_exists_v104($tR, 'turma_id')) {
                $wpdb->query("
                    DELETE r1 FROM {$tR} r1
                    INNER JOIN {$tR} r2
                       ON r1.escola_id = r2.escola_id
                      AND r1.aluno_id = r2.aluno_id
                      AND COALESCE(r1.turma_id,0) = COALESCE(r2.turma_id,0)
                      AND r1.criterio_id = r2.criterio_id
                      AND r1.trimestre = r2.trimestre
                      AND r1.ano_lectivo = r2.ano_lectivo
                      AND r1.id < r2.id
                ");
                sige_jardim_add_index_v105($tR, 'uniq_jresp_scope_v105', "UNIQUE KEY uniq_jresp_scope_v105 (escola_id, aluno_id, turma_id, criterio_id, trimestre, ano_lectivo)");
                sige_jardim_add_index_v105($tR, 'idx_jresp_turma_v105', "KEY idx_jresp_turma_v105 (escola_id, turma_id, ano_lectivo, trimestre)");
            }
        }

        // 3) Avaliações qualitativas: uma avaliação lógica por aluno/turma/disciplina/trimestre/ano.
        if (sige_jardim_table_exists_v104($tA)) {
            $wpdb->query("
                DELETE a1 FROM {$tA} a1
                INNER JOIN {$tA} a2
                   ON a1.escola_id = a2.escola_id
                  AND a1.aluno_id = a2.aluno_id
                  AND COALESCE(a1.turma_id,0) = COALESCE(a2.turma_id,0)
                  AND a1.disciplina_id = a2.disciplina_id
                  AND a1.trimestre = a2.trimestre
                  AND a1.ano_lectivo = a2.ano_lectivo
                  AND a1.id < a2.id
            ");
            sige_jardim_add_index_v105($tA, 'uniq_javal_scope_v105', "UNIQUE KEY uniq_javal_scope_v105 (escola_id, aluno_id, turma_id, disciplina_id, trimestre, ano_lectivo)");
            sige_jardim_add_index_v105($tA, 'idx_javal_turma_v105', "KEY idx_javal_turma_v105 (escola_id, turma_id, ano_lectivo, trimestre)");
        }

        // 4) Diário e Saúde: evitar duplicado por aluno/turma/data/ano.
        if (sige_jardim_table_exists_v104($tD)) {
            $wpdb->query("
                DELETE d1 FROM {$tD} d1
                INNER JOIN {$tD} d2
                   ON d1.escola_id = d2.escola_id
                  AND d1.aluno_id = d2.aluno_id
                  AND COALESCE(d1.turma_id,0) = COALESCE(d2.turma_id,0)
                  AND d1.data_registo = d2.data_registo
                  AND d1.ano_lectivo = d2.ano_lectivo
                  AND d1.id < d2.id
            ");
            sige_jardim_add_index_v105($tD, 'uniq_jdiario_scope_v105', "UNIQUE KEY uniq_jdiario_scope_v105 (escola_id, aluno_id, turma_id, data_registo, ano_lectivo)");
            sige_jardim_add_index_v105($tD, 'idx_jdiario_turma_v105', "KEY idx_jdiario_turma_v105 (escola_id, turma_id, data_registo, ano_lectivo)");
        }

        if (sige_jardim_table_exists_v104($tS)) {
            $wpdb->query("
                DELETE s1 FROM {$tS} s1
                INNER JOIN {$tS} s2
                   ON s1.escola_id = s2.escola_id
                  AND s1.aluno_id = s2.aluno_id
                  AND COALESCE(s1.turma_id,0) = COALESCE(s2.turma_id,0)
                  AND s1.data_registo = s2.data_registo
                  AND s1.ano_lectivo = s2.ano_lectivo
                  AND s1.id < s2.id
            ");
            sige_jardim_add_index_v105($tS, 'uniq_jsaude_scope_v105', "UNIQUE KEY uniq_jsaude_scope_v105 (escola_id, aluno_id, turma_id, data_registo, ano_lectivo)");
            sige_jardim_add_index_v105($tS, 'idx_jsaude_turma_v105', "KEY idx_jsaude_turma_v105 (escola_id, turma_id, data_registo, ano_lectivo)");
        }

        update_option('sige_jardim_criterios_pro_v105_done', '1', false);
        if (function_exists('sige_audit_log')) {
            sige_audit_log('jardim_criterios_pro_migracao_v105', ['status'=>'concluido'], 'jardim');
        }
    }
}
add_action('admin_init', 'sige_jardim_migracao_criterios_pro_v105', 25);


add_action('admin_init', function () {
    if (is_admin() && isset($_GET['page']) && $_GET['page'] === 'sige-app') {
        $view = isset($_GET['view']) ? sanitize_text_field((string)$_GET['view']) : '';
        if (strpos($view, 'jardim') === 0) sige_jardim_ensure_presencas_table_v81();
    }
});

add_action('admin_post_sige_jardim_presencas_salvar', function () {
    if (!sige_jardim_can_manage_v78('jardim.presencas_gerir')) wp_die('Sem permissão.');
    check_admin_referer('sige_jardim_presencas_nonce', '_jpres_nonce');
    global $wpdb;
    sige_jardim_ensure_presencas_table_v81();
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $data = sanitize_text_field($_POST['data_registo'] ?? wp_date('Y-m-d'));
    $ano = (int)($_POST['ano_lectivo'] ?? (function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : wp_date('Y')));
    $rows = (isset($_POST['presenca']) && is_array($_POST['presenca'])) ? $_POST['presenca'] : [];
    if (!sige_jardim_turma_valida_v104($turma_id, $eid, $ano, true)) {
        sige_jardim_redirect_v78('jardim_presencas', ['turma_id'=>$turma_id,'data_reg'=>$data,'msg'=>'erro']);
    }
    $alunos_validos = sige_jardim_get_aluno_ids_activos_turma_v104($turma_id, $eid, $ano);
    $valid = ['presente','falta','falta_justificada','atraso'];
    $ok = 0; $t = sige_jardim_presencas_table_v81();
    foreach ($rows as $aluno_id => $r) {
        $aluno_id = (int)$aluno_id; if (!$aluno_id || !is_array($r) || !isset($alunos_validos[$aluno_id])) continue;
        $status = sanitize_text_field($r['status'] ?? 'presente');
        if (!in_array($status, $valid, true)) $status = 'presente';
        $dados = ['status'=>$status, 'observacao'=>sanitize_textarea_field($r['observacao'] ?? ''), 'registado_por'=>get_current_user_id(), 'updated_at'=>current_time('mysql')];
        if (sige_jardim_upsert_v78($t, ['escola_id'=>$eid,'aluno_id'=>$aluno_id,'turma_id'=>$turma_id,'data_registo'=>$data,'ano_lectivo'=>$ano], $dados)) $ok++;
    }
    if (function_exists('sige_audit_log')) sige_audit_log('jardim_presencas_salvas', ['turma_id'=>(int)$turma_id, 'data'=>$data, 'registos'=>(int)$ok], 'jardim');
    sige_jardim_redirect_v78('jardim_presencas', ['turma_id'=>$turma_id,'data_reg'=>$data,'msg'=>$ok?'ok':'erro']);
});


add_action('admin_post_sige_jardim_diario_salvar', function () {
    if (!sige_jardim_can_manage_v78('jardim.diario_gerir')) wp_die('Sem permissão.');
    check_admin_referer('sige_jardim_diario_nonce', '_jardim_nonce');
    global $wpdb; $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $turma_id = (int)($_POST['turma_id'] ?? 0); $data = sanitize_text_field($_POST['data_registo'] ?? wp_date('Y-m-d'));
    $ano = (int)($_POST['ano_lectivo'] ?? (function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : wp_date('Y')));
    $rows = (isset($_POST['diario']) && is_array($_POST['diario'])) ? $_POST['diario'] : [];
    if (!sige_jardim_turma_valida_v104($turma_id, $eid, $ano, true)) {
        sige_jardim_redirect_v78('jardim_diario', ['tab'=>'diario','turma_id'=>$turma_id,'data_reg'=>$data,'msg'=>'erro']);
    }
    $alunos_validos = sige_jardim_get_aluno_ids_activos_turma_v104($turma_id, $eid, $ano);
    $ok = 0; $t = $wpdb->prefix.'sige_jardim_diario';
    foreach ($rows as $aluno_id=>$r) {
        $aluno_id=(int)$aluno_id; if (!$aluno_id || !is_array($r) || !isset($alunos_validos[$aluno_id])) continue;
        $dados = [
            'actividades'=>sanitize_textarea_field($r['actividades'] ?? ''), 'humor'=>sanitize_text_field($r['humor'] ?? ''),
            'comportamento'=>sanitize_text_field($r['comportamento'] ?? ''), 'obs_comportamento'=>sanitize_textarea_field($r['obs_comportamento'] ?? ''),
            'dormiu'=>(isset($r['dormiu']) && $r['dormiu']!=='') ? (int)$r['dormiu'] : null,
            'minutos_sono'=>(isset($r['minutos_sono']) && $r['minutos_sono']!=='') ? max(0, min(180, (int)$r['minutos_sono'])) : null,
            'obs_sono'=>sanitize_text_field($r['obs_sono'] ?? ''), 'recado_pais'=>sanitize_textarea_field($r['recado_pais'] ?? ''), 'registado_por'=>get_current_user_id()
        ];
        if (sige_jardim_upsert_v78($t, ['escola_id'=>$eid,'aluno_id'=>$aluno_id,'turma_id'=>$turma_id,'data_registo'=>$data,'ano_lectivo'=>$ano], $dados)) $ok++;
    }
    if (function_exists('sige_audit_log')) sige_audit_log('jardim_diario_salvo', ['turma_id'=>(int)$turma_id, 'data'=>$data, 'registos'=>(int)$ok], 'jardim');
    sige_jardim_redirect_v78('jardim_diario', ['tab'=>'diario','turma_id'=>$turma_id,'data_reg'=>$data,'msg'=>$ok?'ok':'erro']);
});

add_action('admin_post_sige_jardim_saude_salvar', function () {
    if (!sige_jardim_can_manage_v78('jardim.saude_gerir')) wp_die('Sem permissão.');
    check_admin_referer('sige_jardim_saude_nonce', '_jsaude_nonce');
    global $wpdb; $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $turma_id = (int)($_POST['turma_id'] ?? 0); $data = sanitize_text_field($_POST['data_registo'] ?? wp_date('Y-m-d'));
    $ano = (int)($_POST['ano_lectivo'] ?? (function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : wp_date('Y')));
    $rows = (isset($_POST['saude']) && is_array($_POST['saude'])) ? $_POST['saude'] : [];
    if (!sige_jardim_turma_valida_v104($turma_id, $eid, $ano, true)) {
        sige_jardim_redirect_v78('jardim_saude', ['turma_id'=>$turma_id,'data_reg'=>$data,'msg'=>'erro']);
    }
    $alunos_validos = sige_jardim_get_aluno_ids_activos_turma_v104($turma_id, $eid, $ano);
    $ok = 0; $t = $wpdb->prefix.'sige_jardim_saude';
    foreach ($rows as $aluno_id=>$r) {
        $aluno_id=(int)$aluno_id; if (!$aluno_id || !is_array($r) || !isset($alunos_validos[$aluno_id])) continue;
        $dados = [
            'pequeno_almoco'=>sanitize_text_field($r['pequeno_almoco'] ?? ''), 'almoco'=>sanitize_text_field($r['almoco'] ?? ''), 'lanche'=>sanitize_text_field($r['lanche'] ?? ''),
            'obs_alimentacao'=>sanitize_textarea_field($r['obs_alimentacao'] ?? ''), 'febre'=>!empty($r['febre'])?1:0,
            'temperatura'=>(isset($r['temperatura']) && $r['temperatura']!=='') ? (float)$r['temperatura'] : null, 'queda_acidente'=>!empty($r['queda_acidente'])?1:0,
            'desc_ocorrencia'=>sanitize_textarea_field($r['desc_ocorrencia'] ?? ''), 'medicamento_dado'=>sanitize_text_field($r['medicamento_dado'] ?? ''), 'registado_por'=>get_current_user_id()
        ];
        if (sige_jardim_upsert_v78($t, ['escola_id'=>$eid,'aluno_id'=>$aluno_id,'turma_id'=>$turma_id,'data_registo'=>$data,'ano_lectivo'=>$ano], $dados)) $ok++;
    }
    if (function_exists('sige_audit_log')) sige_audit_log('jardim_saude_salva', ['turma_id'=>(int)$turma_id, 'data'=>$data, 'registos'=>(int)$ok], 'jardim');
    sige_jardim_redirect_v78('jardim_saude', ['turma_id'=>$turma_id,'data_reg'=>$data,'msg'=>$ok?'ok':'erro']);
});

add_action('admin_post_sige_jardim_criterios_salvar', function () {
    if (!sige_jardim_can_manage_v78('jardim.diario_gerir')) wp_die('Sem permissão.');
    check_admin_referer('sige_jardim_criterios_nonce', '_jcrit_nonce');
    global $wpdb; $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $turma_id=(int)($_POST['turma_id']??0); $trimestre=(int)($_POST['trimestre']??1);
    $ano=(int)($_POST['ano_lectivo'] ?? (function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : wp_date('Y')));
    $resp=(isset($_POST['resp']) && is_array($_POST['resp'])) ? $_POST['resp'] : []; $coment=(isset($_POST['coment']) && is_array($_POST['coment'])) ? $_POST['coment'] : [];
    if (!sige_jardim_turma_valida_v104($turma_id, $eid, $ano, true)) {
        sige_jardim_redirect_v78('jardim_diario', ['tab'=>'avaliacao','turma_id'=>$turma_id,'trimestre'=>$trimestre,'msg'=>'erro']);
    }
    $alunos_validos = sige_jardim_get_aluno_ids_activos_turma_v104($turma_id, $eid, $ano);
    $disc_validos = [];
    foreach (sige_jardim_get_disciplinas_turma_v104($turma_id, $eid) as $__d) $disc_validos[(int)$__d->id] = true;
    $tCR=$wpdb->prefix.'sige_jardim_criterios_respostas'; $tA=$wpdb->prefix.'sige_jardim_avaliacoes'; $tC=$wpdb->prefix.'sige_jardim_criterios';

    $map=[];
    if (!empty($disc_validos)) {
        $disc_ids_validos = array_keys($disc_validos);
        $disc_ph = implode(',', array_fill(0, count($disc_ids_validos), '%d'));
        $crit_extra_handler = function_exists('sige_jardim_criterios_extra_sql_v106')
            ? sige_jardim_criterios_extra_sql_v106($tC, (int)$ano, (int)$trimestre)
            : '';
        $criterios_validos_rows = $wpdb->get_results($wpdb->prepare(
            "SELECT id, disciplina_id
             FROM {$tC}
             WHERE escola_id=%d
               AND disciplina_id IN ({$disc_ph})
               {$crit_extra_handler}",
            array_merge([$eid], $disc_ids_validos)
        ));
        foreach ((array)$criterios_validos_rows as $c) $map[(int)$c->id]=(int)$c->disciplina_id;
    }
    $by=[]; $ok=0;
    foreach ($resp as $aluno_id=>$criterios) { $aluno_id=(int)$aluno_id; if (!$aluno_id || !is_array($criterios) || !isset($alunos_validos[$aluno_id])) continue;
        foreach ($criterios as $cid=>$status) { $cid=(int)$cid; $status=sanitize_text_field($status); if (!$cid || !in_array($status, ['atingido','progresso','nao_atingido'], true)) continue;
            $disc=$map[$cid]??0;
            if (!$disc || !isset($disc_validos[(int)$disc])) continue;
            $by[$aluno_id][$disc][]=$status;
            $where_resp = ['escola_id'=>$eid,'aluno_id'=>$aluno_id,'criterio_id'=>$cid,'trimestre'=>$trimestre,'ano_lectivo'=>$ano];
            if (function_exists('sige_jardim_column_exists_v104') && sige_jardim_column_exists_v104($tCR, 'turma_id')) $where_resp['turma_id'] = $turma_id;
            if (sige_jardim_upsert_v78($tCR, $where_resp, ['status'=>$status, 'updated_at'=>current_time('mysql')])) $ok++;
        }
    }
    foreach ($by as $aluno_id=>$disc_statuses) foreach ($disc_statuses as $disc=>$statuses) {
        $comentario=sanitize_textarea_field($coment[$aluno_id][$disc] ?? '');
        sige_jardim_upsert_v78($tA, ['escola_id'=>$eid,'aluno_id'=>(int)$aluno_id,'turma_id'=>$turma_id,'disciplina_id'=>(int)$disc,'trimestre'=>$trimestre,'ano_lectivo'=>$ano], ['nivel'=>sige_jardim_nivel_v78($statuses),'observacao'=>$comentario,'comentario_trimestre'=>$comentario,'registado_por'=>get_current_user_id(),'updated_at'=>current_time('mysql')]);
    }
    if (function_exists('sige_audit_log')) sige_audit_log('jardim_avaliacao_salva', ['turma_id'=>(int)$turma_id, 'trimestre'=>(int)$trimestre, 'ano'=>(int)$ano, 'respostas'=>(int)$ok], 'jardim');
    sige_jardim_redirect_v78('jardim_diario', ['tab'=>'avaliacao','turma_id'=>$turma_id,'trimestre'=>$trimestre,'msg'=>$ok?'ok':'erro']);
});

add_action('admin_post_sige_jardim_avaliacao_salvar', function () { do_action('admin_post_sige_jardim_criterios_salvar'); });

function sige_jardim_contactos_aluno_v78($aluno): array {
    $telefones=[]; foreach (['whatsapp_notificacoes','contacto_encarregado','telemovel_pai','telemovel_mae','telemovel_pai_2','telemovel_mae_2'] as $c) if (!empty($aluno->$c)) { $n=preg_replace('/[^0-9]/','',(string)$aluno->$c); if($n) $telefones[]=$n; }
    $emails=[]; foreach (['email_encarregado','email_pai','email_mae'] as $c) if (!empty($aluno->$c) && is_email($aluno->$c)) $emails[]=strtolower($aluno->$c);
    return [array_values(array_unique($telefones)), array_values(array_unique($emails))];
}
function sige_jardim_resumo_mensal_aluno_v78(int $aluno_id, int $turma_id, int $ano, string $mes): array {
    global $wpdb; [$ini,$fim]=sige_jardim_periodo_mes_v78($mes); $t=$wpdb->prefix.'sige_jardim_diario';
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $rows=$wpdb->get_results($wpdb->prepare("SELECT * FROM $t WHERE escola_id=%d AND aluno_id=%d AND turma_id=%d AND ano_lectivo=%d AND data_registo BETWEEN %s AND %s ORDER BY data_registo ASC", $eid,$aluno_id,$turma_id,$ano,$ini,$fim));
    $humor=[]; $comp=[]; $rec=[]; foreach($rows as $r){ if($r->humor)$humor[$r->humor]=($humor[$r->humor]??0)+1; if($r->comportamento)$comp[$r->comportamento]=($comp[$r->comportamento]??0)+1; if(!empty($r->recado_pais))$rec[]=wp_date('d/m',strtotime($r->data_registo)).': '.trim(wp_strip_all_tags($r->recado_pais)); }
    arsort($humor); arsort($comp);
    $pres = sige_jardim_presenca_resumo_v81($aluno_id, $turma_id, $ano, $ini, $fim);
    $dias_uteis = sige_jardim_dias_uteis_v81($ini, $fim);
    return ['total'=>count($rows),'humor'=>array_key_first($humor)?:'','comportamento'=>array_key_first($comp)?:'','recados'=>array_slice(array_reverse($rec),0,3),'presencas'=>$pres,'dias_uteis'=>$dias_uteis];
}
function sige_jardim_msg_mensal_v78($aluno, string $turma_nome, string $mes, array $r): string {
    $hl=['feliz'=>'Feliz','calmo'=>'Calmo/a','triste'=>'Triste','agitado'=>'Agitado/a','cansado'=>'Cansado/a']; $cl=['excelente'=>'Excelente','bom'=>'Bom','satisfatorio'=>'Satisfatório','necessita_atencao'=>'Necessita atenção'];
    $lin=[]; $lin[]='Olá, estimado(a) encarregado(a).'; $lin[]='Segue o resumo mensal do Jardim de Infância referente a '.wp_date('F Y', strtotime($mes.'-01')).':'; $lin[]='';
    $lin[]='Aluno(a): '.trim((string)$aluno->nome_completo); $lin[]='Turma: '.$turma_nome; $lin[]='Registos feitos: '.(int)$r['total']; $lin[]='Humor predominante: '.($hl[$r['humor']] ?? 'Sem dados suficientes'); $lin[]='Comportamento predominante: '.($cl[$r['comportamento']] ?? 'Sem dados suficientes'); if (!empty($r['presencas'])) { $p=$r['presencas']; $lin[]='Presenças no mês: '.(int)$p['presente'].' presente(s), '.(int)$p['atraso'].' atraso(s), '.(int)$p['falta'].' falta(s), '.(int)$p['falta_justificada'].' falta(s) justificada(s)'.(!empty($r['dias_uteis'])?' em '.(int)$r['dias_uteis'].' dias úteis':'').'.'; }
    if (!empty($r['recados'])) { $lin[]=''; $lin[]='Recados/observações:'; foreach ($r['recados'] as $x) $lin[]='• '.$x; }
    $lin[]=''; $lin[]='Com carinho,'; $lin[]='Coordenação Pedagógica'; return implode("\n", $lin);
}


function sige_jardim_ultimo_resumo_aluno_v80(int $aluno_id, int $turma_id, int $ano): array {
    global $wpdb;
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $tD = $wpdb->prefix.'sige_jardim_diario';
    $tS = $wpdb->prefix.'sige_jardim_saude';
    $tA = $wpdb->prefix.'sige_jardim_avaliacoes';
    $presenca = sige_jardim_presenca_ultima_v81($aluno_id, $turma_id, $ano);
    $diario = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tD WHERE escola_id=%d AND aluno_id=%d AND turma_id=%d AND ano_lectivo=%d ORDER BY data_registo DESC, id DESC LIMIT 1", $eid, $aluno_id, $turma_id, $ano));
    $saude = $wpdb->get_row($wpdb->prepare("SELECT * FROM $tS WHERE escola_id=%d AND aluno_id=%d AND turma_id=%d AND ano_lectivo=%d ORDER BY data_registo DESC, id DESC LIMIT 1", $eid, $aluno_id, $turma_id, $ano));
    $avaliacoes = $wpdb->get_results($wpdb->prepare("SELECT nivel, observacao, comentario_trimestre, trimestre, updated_at FROM $tA WHERE escola_id=%d AND aluno_id=%d AND turma_id=%d AND ano_lectivo=%d ORDER BY trimestre DESC, updated_at DESC LIMIT 5", $eid, $aluno_id, $turma_id, $ano));
    return ['diario'=>$diario, 'saude'=>$saude, 'avaliacoes'=>$avaliacoes, 'presenca'=>$presenca];
}

function sige_jardim_msg_individual_v80($aluno, string $turma_nome, array $resumo): string {
    $humor = ['feliz'=>'Feliz','calmo'=>'Calmo/a','triste'=>'Triste','agitado'=>'Agitado/a','cansado'=>'Cansado/a'];
    $comp  = ['excelente'=>'Excelente','bom'=>'Bom','satisfatorio'=>'Satisfatório','necessita_atencao'=>'Necessita atenção'];
    $alim  = ['tudo'=>'Comeu tudo','metade'=>'Comeu metade','pouco'=>'Comeu pouco','nao_comeu'=>'Não comeu'];
    $lin = [];
    $lin[] = 'Olá, estimado(a) encarregado(a).';
    $lin[] = 'Segue o resumo individual mais recente do Jardim de Infância:';
    $lin[] = '';
    $lin[] = 'Aluno(a): '.trim((string)$aluno->nome_completo);
    $lin[] = 'Turma: '.$turma_nome;
    $p = $resumo['presenca'] ?? null;
    if ($p) {
        $lin[] = '';
        $lin[] = '📌 Presença - '.wp_date('d/m/Y', strtotime($p->data_registo));
        $lin[] = 'Estado: '.sige_jardim_presenca_label_v81((string)$p->status);
        if (!empty($p->observacao)) $lin[] = 'Observação da presença: '.trim(wp_strip_all_tags($p->observacao));
    }
    $d = $resumo['diario'] ?? null;
    if ($d) {
        $lin[] = '';
        $lin[] = '📝 Diário - '.wp_date('d/m/Y', strtotime($d->data_registo));
        if (!empty($d->actividades))       $lin[] = 'Actividades: '.trim(wp_strip_all_tags($d->actividades));
        if (!empty($d->humor))             $lin[] = 'Humor: '.($humor[$d->humor] ?? $d->humor);
        if (!empty($d->comportamento))     $lin[] = 'Comportamento: '.($comp[$d->comportamento] ?? $d->comportamento);
        if (!empty($d->obs_comportamento)) $lin[] = 'Observação: '.trim(wp_strip_all_tags($d->obs_comportamento));
        if ((string)$d->dormiu === '1')    $lin[] = 'Sono: dormiu'.(!empty($d->minutos_sono) ? ' cerca de '.(int)$d->minutos_sono.' min' : '');
        elseif ((string)$d->dormiu === '0')$lin[] = 'Sono: não dormiu';
        if (!empty($d->recado_pais))       $lin[] = 'Recado: '.trim(wp_strip_all_tags($d->recado_pais));
    }
    $s = $resumo['saude'] ?? null;
    if ($s) {
        $lin[] = '';
        $lin[] = '❤️ Saúde e alimentação - '.wp_date('d/m/Y', strtotime($s->data_registo));
        if (!empty($s->pequeno_almoco)) $lin[] = 'Pequeno-almoço: '.($alim[$s->pequeno_almoco] ?? $s->pequeno_almoco);
        if (!empty($s->almoco))         $lin[] = 'Almoço: '.($alim[$s->almoco] ?? $s->almoco);
        if (!empty($s->lanche))         $lin[] = 'Lanche: '.($alim[$s->lanche] ?? $s->lanche);
        if (!empty($s->obs_alimentacao))$lin[] = 'Observação alimentar: '.trim(wp_strip_all_tags($s->obs_alimentacao));
        if (!empty($s->febre))          $lin[] = 'Febre: sim'.(!empty($s->temperatura) ? ' ('.number_format((float)$s->temperatura,1,',','.').' ºC)' : '');
        if (!empty($s->queda_acidente)) $lin[] = 'Ocorrência/acidente: '.(!empty($s->desc_ocorrencia) ? trim(wp_strip_all_tags($s->desc_ocorrencia)) : 'registado');
        if (!empty($s->medicamento_dado)) $lin[] = 'Medicamento: '.trim(wp_strip_all_tags($s->medicamento_dado));
    }
    if (!empty($resumo['avaliacoes'])) {
        $lin[] = '';
        $lin[] = '📊 Avaliação qualitativa mais recente:';
        $shown = 0;
        foreach ($resumo['avaliacoes'] as $av) {
            if ($shown >= 3) break;
            $txt = trim(wp_strip_all_tags($av->comentario_trimestre ?: $av->observacao));
            $linha = '• Nível '.$av->nivel.' - '.$av->trimestre.'º trimestre';
            if ($txt !== '') $linha .= ': '.$txt;
            $lin[] = $linha;
            $shown++;
        }
    }
    if (!$d && !$s && empty($resumo['avaliacoes'])) {
        $lin[] = '';
        $lin[] = 'Ainda não existem registos recentes suficientes para este aluno.';
    }
    $lin[] = '';
    $lin[] = 'Com carinho,';
    $lin[] = 'Coordenação Pedagógica';
    return implode("\n", $lin);
}

add_action('admin_post_sige_jardim_relatorio_enviar_individual', function () {
    if (!sige_jardim_can_manage_v78('jardim.relatorio_emitir')) wp_die('Sem permissão.');
    check_admin_referer('sige_jardim_relatorio_envio_individual_nonce', '_jrel_envio_ind_nonce');
    global $wpdb;
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $aluno_id = (int)($_POST['aluno_id'] ?? 0);
    $turma_id = (int)($_POST['turma_id'] ?? 0);
    $ano = (int)($_POST['ano_lectivo'] ?? (function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : wp_date('Y')));
    $mes = sanitize_text_field($_POST['mes_sel'] ?? wp_date('Y-m'));
    $canal = sanitize_text_field($_POST['canal'] ?? 'ambos');
    if (!$aluno_id || !$turma_id || !in_array($canal, ['ambos','whatsapp','email'], true) || !sige_jardim_turma_valida_v104($turma_id, $eid, $ano, true) || !sige_jardim_aluno_activo_na_turma_v104($aluno_id, $turma_id, $eid, $ano)) sige_jardim_redirect_v78('jardim_relatorio', ['turma_id'=>$turma_id,'periodo'=>'mes','mes_sel'=>$mes,'msg_envio'=>'erro']);
    $aluno = $wpdb->get_row($wpdb->prepare("SELECT id,nome_completo,whatsapp_notificacoes,contacto_encarregado,telemovel_pai,telemovel_mae,telemovel_pai_2,telemovel_mae_2,email_encarregado,email_pai,email_mae FROM {$wpdb->prefix}sige_alunos WHERE id=%d AND escola_id=%d LIMIT 1", $aluno_id, $eid));
    if (!$aluno) sige_jardim_redirect_v78('jardim_relatorio', ['turma_id'=>$turma_id,'periodo'=>'mes','mes_sel'=>$mes,'msg_envio'=>'erro']);

    $lock_ind = 'sige_jrel_ind_v108_' . $eid . '_' . $turma_id . '_' . $aluno_id . '_' . sanitize_key($canal);
    if (function_exists('get_transient') && get_transient($lock_ind)) {
        sige_jardim_redirect_v78('jardim_relatorio', ['turma_id'=>$turma_id,'periodo'=>'mes','mes_sel'=>$mes,'msg_envio'=>'duplicado']);
    }
    if (function_exists('set_transient')) set_transient($lock_ind, 1, 90);

    $turma = $wpdb->get_row($wpdb->prepare("SELECT nome, classe FROM {$wpdb->prefix}sige_turmas WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id, $eid));
    $turma_nome = $turma ? ($turma->nome ?: $turma->classe) : 'Jardim de Infância';
    $msg = sige_jardim_msg_individual_v80($aluno, $turma_nome, sige_jardim_ultimo_resumo_aluno_v80($aluno_id, $turma_id, $ano));
    [$tels, $emails] = sige_jardim_contactos_aluno_v78($aluno);
    $wpp = 0; $email = 0; $sem_contacto = 0;
    if (($canal === 'ambos' || $canal === 'whatsapp') && $tels && function_exists('sige_fin_queue_whatsapp')) if (sige_fin_queue_whatsapp($aluno_id, $tels[0], 'jardim_resumo_individual', $msg)) $wpp++;
    if (($canal === 'ambos' || $canal === 'email') && $emails) { $assunto = 'Resumo individual do Jardim de Infância - '.trim((string)$aluno->nome_completo); foreach ($emails as $em) if (wp_mail($em, $assunto, $msg, ['Content-Type: text/plain; charset=UTF-8'])) $email++; }
    if (!$tels && !$emails) $sem_contacto = 1;
    if (function_exists('sige_audit_log')) sige_audit_log('jardim_resumo_individual_enviado', ['aluno_id'=>$aluno_id, 'turma_id'=>$turma_id, 'canal'=>$canal, 'whatsapp'=>$wpp, 'email'=>$email, 'sem_contacto'=>$sem_contacto], 'jardim');
    sige_jardim_redirect_v78('jardim_relatorio', ['turma_id'=>$turma_id,'sub'=>'turma','periodo'=>'mes','mes_sel'=>$mes,'msg_envio'=>'ok_ind','wpp'=>$wpp,'email'=>$email,'sem_contacto'=>$sem_contacto,'aluno_enviado'=>$aluno_id]);
});
add_action('admin_post_sige_jardim_relatorio_enviar_mensal', function () {
    if (!sige_jardim_can_manage_v78('jardim.relatorio_emitir')) wp_die('Sem permissão.');
    check_admin_referer('sige_jardim_relatorio_envio_nonce', '_jrel_envio_nonce');
    global $wpdb; $eid=function_exists('sige_get_escola_id')?(int)sige_get_escola_id():0;
    $turma_id=(int)($_POST['turma_id']??0); $ano=(int)($_POST['ano_lectivo'] ?? (function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : wp_date('Y'))); $mes=sanitize_text_field($_POST['mes_sel']??wp_date('Y-m')); $canal=sanitize_text_field($_POST['canal']??'ambos');
    if (!$turma_id || !sige_jardim_turma_valida_v104($turma_id, $eid, $ano, true)) sige_jardim_redirect_v78('jardim_relatorio', ['msg_envio'=>'erro']);

    $lock_mensal = 'sige_jrel_mensal_v108_' . $eid . '_' . $turma_id . '_' . $ano . '_' . sanitize_key($mes) . '_' . sanitize_key($canal);
    if (function_exists('get_transient') && get_transient($lock_mensal)) {
        sige_jardim_redirect_v78('jardim_relatorio', ['turma_id'=>$turma_id,'periodo'=>'mes','mes_sel'=>$mes,'msg_envio'=>'duplicado']);
    }
    if (function_exists('set_transient')) set_transient($lock_mensal, 1, 90);

    $turma=$wpdb->get_row($wpdb->prepare("SELECT nome, classe FROM {$wpdb->prefix}sige_turmas WHERE id=%d AND escola_id=%d LIMIT 1", $turma_id,$eid)); $turma_nome=$turma?($turma->nome?:$turma->classe):'Jardim de Infância';
    $alunos=sige_jardim_get_alunos_activos_turma_v104($turma_id,$eid,$ano,"a.id,a.nome_completo,a.whatsapp_notificacoes,a.contacto_encarregado,a.telemovel_pai,a.telemovel_mae,a.telemovel_pai_2,a.telemovel_mae_2,a.email_encarregado,a.email_pai,a.email_mae");
    $wpp=0; $email=0; $sem_contacto=0; $sem_dados=0;
    foreach($alunos as $aluno){ $res=sige_jardim_resumo_mensal_aluno_v78((int)$aluno->id,$turma_id,$ano,$mes); if((int)$res['total']<=0 && empty($res['presencas']['total'])){$sem_dados++; continue;} $msg=sige_jardim_msg_mensal_v78($aluno,$turma_nome,$mes,$res); [$tels,$emails]=sige_jardim_contactos_aluno_v78($aluno);
        if (($canal==='ambos'||$canal==='whatsapp') && $tels && function_exists('sige_fin_queue_whatsapp')) if (sige_fin_queue_whatsapp((int)$aluno->id,$tels[0],'jardim_resumo_mensal',$msg)) $wpp++;
        if (($canal==='ambos'||$canal==='email') && $emails) { $assunto='Resumo mensal do Jardim de Infância - '.wp_date('F Y', strtotime($mes.'-01')); foreach($emails as $em) if(wp_mail($em,$assunto,$msg,['Content-Type: text/plain; charset=UTF-8'])) $email++; }
        if (!$tels && !$emails) $sem_contacto++;
    }
    if (function_exists('sige_audit_log')) sige_audit_log('jardim_resumo_mensal_enviado', ['turma_id'=>(int)$turma_id, 'mes'=>$mes, 'whatsapp'=>(int)$wpp, 'email'=>(int)$email, 'sem_contacto'=>(int)$sem_contacto, 'sem_dados'=>(int)$sem_dados], 'jardim');
    sige_jardim_redirect_v78('jardim_relatorio', ['turma_id'=>$turma_id,'periodo'=>'mes','mes_sel'=>$mes,'msg_envio'=>'ok','wpp'=>$wpp,'email'=>$email,'sem_contacto'=>$sem_contacto,'sem_dados'=>$sem_dados]);
});


add_action('admin_init', function () {
    if (!is_admin()) return;
    if (($_GET['page'] ?? '') !== 'sige-app') return;
    $view = isset($_GET['view']) ? sanitize_key((string)$_GET['view']) : '';
    if (strpos($view, 'jardim_') !== 0) return;
    if (!function_exists('sige_jardim_reparar_matriculas_alunos_reativados_v106')) return;
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $ano = function_exists('sige_ano_lectivo_atual') ? (int)sige_ano_lectivo_atual() : (int)wp_date('Y');
    sige_jardim_reparar_matriculas_alunos_reativados_v106($eid, $ano);
}, 18);
