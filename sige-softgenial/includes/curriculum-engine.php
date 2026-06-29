<?php
/**
 * SIGE SoftGenial - Curriculum Engine Foundation PRO
 *
 * Primeira fundação para suporte multicurrículo (Moçambique/SNE, Cambridge,
 * Angola, Brasil e perfis personalizados) sem alterar fórmulas académicas,
 * pautas, DEC, boletins ou regras de progressão existentes.
 *
 * Fase actual: modo fundação/observação. O sistema continua a usar o fluxo
 * académico validado. Este motor cria estrutura, inventário e sincronização
 * segura para futura activação por fases no ambiente de testes.
 *
 * @since 12.11.0
 */

if (!defined('ABSPATH')) exit;

if (!defined('SIGE_CURRICULUM_ENGINE_VERSION')) {
    define('SIGE_CURRICULUM_ENGINE_VERSION', '12.11.0');
}

if (!function_exists('sige_curriculum_can_manage')) {
    function sige_curriculum_can_manage(): bool {
        if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return true;
        if (function_exists('sige_can')) {
            return sige_can('configuracoes.ver') || sige_can('academico.matriz_ver');
        }
        return current_user_can('sige_admin') || current_user_can('sige_director') || current_user_can('sige_pedagogico');
    }
}

if (!function_exists('sige_curriculum_table_exists')) {
    function sige_curriculum_table_exists(string $table): bool {
        global $wpdb;
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s",
            $table
        ));
        return (int)$exists > 0;
    }
}

if (!function_exists('sige_curriculum_tables_ready')) {
    function sige_curriculum_tables_ready(): bool {
        global $wpdb;
        $tables = [
            $wpdb->prefix . 'sige_curriculum_profiles',
            $wpdb->prefix . 'sige_curriculum_school_settings',
            $wpdb->prefix . 'sige_curriculum_subjects',
        ];
        foreach ($tables as $table) {
            if (!sige_curriculum_table_exists($table)) return false;
        }
        return true;
    }
}

if (!function_exists('sige_curriculum_get_school_ids')) {
    function sige_curriculum_get_school_ids(): array {
        global $wpdb;
        $t = $wpdb->prefix . 'sige_escolas';
        if (!sige_curriculum_table_exists($t)) return [1];
        $ids = $wpdb->get_col("SELECT id FROM `{$t}` WHERE activo = 1 ORDER BY id ASC");
        $ids = array_values(array_filter(array_map('intval', (array)$ids)));
        return !empty($ids) ? $ids : [1];
    }
}

if (!function_exists('sige_curriculum_profile_templates')) {
    function sige_curriculum_profile_templates(): array {
        return [
            'mozambique_sne' => [
                'nome' => 'Moçambique / SNE',
                'pais' => 'Moçambique',
                'tipo' => 'sne',
                'status' => 'activo',
                'descricao' => 'Perfil curricular actual do Sistema Nacional de Educação de Moçambique. Mantém compatibilidade total com as regras já validadas.',
                'period_model' => 'trimestres',
                'assessment_scale' => '0-20',
                'progression_model' => 'sne_legacy',
            ],
            'cambridge' => [
                'nome' => 'Cambridge',
                'pais' => 'Internacional',
                'tipo' => 'international',
                'status' => 'modelo',
                'descricao' => 'Modelo reservado para estrutura Cambridge/International Curriculum. Ainda não altera cálculos.',
                'period_model' => 'terms',
                'assessment_scale' => 'percentage_letters',
                'progression_model' => 'custom',
            ],
            'angola' => [
                'nome' => 'Angola',
                'pais' => 'Angola',
                'tipo' => 'national',
                'status' => 'modelo',
                'descricao' => 'Modelo reservado para escolas angolanas. Ainda não altera cálculos.',
                'period_model' => 'trimestres',
                'assessment_scale' => '0-20',
                'progression_model' => 'custom',
            ],
            'brasil' => [
                'nome' => 'Brasil',
                'pais' => 'Brasil',
                'tipo' => 'national',
                'status' => 'modelo',
                'descricao' => 'Modelo reservado para Ensino Fundamental/Médio. Ainda não altera cálculos.',
                'period_model' => 'bimestres_semestres',
                'assessment_scale' => '0-10',
                'progression_model' => 'custom',
            ],
            'custom' => [
                'nome' => 'Currículo Personalizado',
                'pais' => 'Personalizado',
                'tipo' => 'custom',
                'status' => 'modelo',
                'descricao' => 'Base para centros de formação, institutos e escolas com regras próprias. Ainda não altera cálculos.',
                'period_model' => 'custom',
                'assessment_scale' => 'custom',
                'progression_model' => 'custom',
            ],
        ];
    }
}

if (!function_exists('sige_curriculum_seed_school')) {
    function sige_curriculum_seed_school(int $escola_id): void {
        global $wpdb;
        if ($escola_id <= 0 || !sige_curriculum_tables_ready()) return;

        $tProfiles = $wpdb->prefix . 'sige_curriculum_profiles';
        $tSettings = $wpdb->prefix . 'sige_curriculum_school_settings';
        $now = current_time('mysql');

        $templates = sige_curriculum_profile_templates();
        foreach ($templates as $code => $tpl) {
            $exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$tProfiles}` WHERE escola_id = %d AND code = %s LIMIT 1",
                $escola_id, $code
            ));
            if ($exists > 0) continue;

            $wpdb->insert($tProfiles, [
                'escola_id' => $escola_id,
                'code' => $code,
                'nome' => $tpl['nome'],
                'pais' => $tpl['pais'],
                'tipo' => $tpl['tipo'],
                'status' => $tpl['status'],
                'descricao' => $tpl['descricao'],
                'period_model' => $tpl['period_model'],
                'assessment_scale' => $tpl['assessment_scale'],
                'progression_model' => $tpl['progression_model'],
                'is_system' => 1,
                'is_readonly' => ($code === 'mozambique_sne') ? 1 : 0,
                'meta_json' => wp_json_encode(['foundation' => true, 'applies_to_core' => false]),
                'criado_em' => $now,
                'actualizado_em' => $now,
            ]);
        }

        $active_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$tProfiles}` WHERE escola_id = %d AND code = 'mozambique_sne' LIMIT 1",
            $escola_id
        ));
        if ($active_id > 0) {
            $settings_exists = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$tSettings}` WHERE escola_id = %d LIMIT 1",
                $escola_id
            ));
            if ($settings_exists <= 0) {
                $wpdb->insert($tSettings, [
                    'escola_id' => $escola_id,
                    'active_profile_id' => $active_id,
                    'modo_execucao' => 'legacy_safe',
                    'estado' => 'foundation',
                    'observacoes' => 'Fundação multicurrículo instalada. O núcleo académico continua a usar o fluxo Moçambique/SNE validado.',
                    'criado_em' => $now,
                    'actualizado_em' => $now,
                ]);
            }
        }
    }
}

if (!function_exists('sige_curriculum_seed_defaults')) {
    function sige_curriculum_seed_defaults(): void {
        if (!sige_curriculum_tables_ready()) return;
        foreach (sige_curriculum_get_school_ids() as $eid) {
            sige_curriculum_seed_school((int)$eid);
        }
    }
}

if (!function_exists('sige_curriculum_get_active_profile')) {
    function sige_curriculum_get_active_profile(?int $escola_id = null) {
        global $wpdb;
        if (!sige_curriculum_tables_ready()) return null;
        $eid = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        $tProfiles = $wpdb->prefix . 'sige_curriculum_profiles';
        $tSettings = $wpdb->prefix . 'sige_curriculum_school_settings';
        $profile = $wpdb->get_row($wpdb->prepare(
            "SELECT p.* FROM `{$tSettings}` s INNER JOIN `{$tProfiles}` p ON p.id = s.active_profile_id WHERE s.escola_id = %d LIMIT 1",
            $eid
        ));
        if (!$profile) {
            sige_curriculum_seed_school($eid);
            $profile = $wpdb->get_row($wpdb->prepare(
                "SELECT p.* FROM `{$tSettings}` s INNER JOIN `{$tProfiles}` p ON p.id = s.active_profile_id WHERE s.escola_id = %d LIMIT 1",
                $eid
            ));
        }
        return $profile ?: null;
    }
}

if (!function_exists('sige_curriculum_get_profile_options')) {
    function sige_curriculum_get_profile_options(?int $escola_id = null): array {
        global $wpdb;
        if (!sige_curriculum_tables_ready()) return [];
        $eid = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        sige_curriculum_seed_school($eid);
        $t = $wpdb->prefix . 'sige_curriculum_profiles';
        return (array)$wpdb->get_results($wpdb->prepare(
            "SELECT * FROM `{$t}` WHERE escola_id = %d ORDER BY FIELD(code,'mozambique_sne','cambridge','angola','brasil','custom'), nome ASC",
            $eid
        ));
    }
}

if (!function_exists('sige_curriculum_sync_legacy_matrix')) {
    function sige_curriculum_sync_legacy_matrix(?int $escola_id = null, bool $force = false): array {
        global $wpdb;
        if (!sige_curriculum_tables_ready()) {
            return ['ok' => false, 'message' => 'Tabelas do Curriculum Engine ainda não existem.', 'inserted' => 0, 'updated' => 0];
        }
        $eid = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($eid <= 0) { return []; }
        sige_curriculum_seed_school($eid);

        $tProfiles = $wpdb->prefix . 'sige_curriculum_profiles';
        $tGrades   = $wpdb->prefix . 'sige_curriculum_grades';
        $tSubjects = $wpdb->prefix . 'sige_curriculum_subjects';
        $tMatriz   = $wpdb->prefix . 'sige_matriz_curricular';
        $tDisc     = $wpdb->prefix . 'sige_disciplinas';

        $profile_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM `{$tProfiles}` WHERE escola_id = %d AND code = 'mozambique_sne' LIMIT 1",
            $eid
        ));
        if ($profile_id <= 0) {
            return ['ok' => false, 'message' => 'Perfil Moçambique/SNE não encontrado.', 'inserted' => 0, 'updated' => 0];
        }

        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT m.classe, m.disciplina_id, m.carga_horaria, m.ordem_pauta, COALESCE(m.categoria,'complementar') AS categoria, d.nome, d.sigla
               FROM `{$tMatriz}` m
               INNER JOIN `{$tDisc}` d ON d.id = m.disciplina_id
              WHERE m.escola_id = %d
              ORDER BY m.classe ASC, COALESCE(m.ordem_pauta, 999), d.nome ASC",
            $eid
        ));

        $inserted = 0; $updated = 0;
        $now = current_time('mysql');
        foreach ((array)$rows as $r) {
            $classe = trim((string)$r->classe);
            if ($classe === '') continue;
            $grade = $wpdb->get_row($wpdb->prepare(
                "SELECT id FROM `{$tGrades}` WHERE escola_id = %d AND profile_id = %d AND legacy_classe = %s LIMIT 1",
                $eid, $profile_id, $classe
            ));
            if (!$grade) {
                $wpdb->insert($tGrades, [
                    'escola_id' => $eid,
                    'profile_id' => $profile_id,
                    'level_id' => null,
                    'code' => sanitize_key(str_replace(['ª','º',' '], ['a','o','_'], $classe)),
                    'nome' => $classe,
                    'legacy_classe' => $classe,
                    'ordem' => function_exists('sige_parse_classe_num') ? (int)sige_parse_classe_num($classe) : (int)preg_replace('/\D+/', '', $classe),
                    'meta_json' => wp_json_encode(['source' => 'sige_matriz_curricular']),
                    'criado_em' => $now,
                    'actualizado_em' => $now,
                ]);
                $grade_id = (int)$wpdb->insert_id;
            } else {
                $grade_id = (int)$grade->id;
            }
            if ($grade_id <= 0) continue;

            $did = (int)$r->disciplina_id;
            $exists_id = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM `{$tSubjects}` WHERE escola_id = %d AND profile_id = %d AND grade_id = %d AND disciplina_id = %d LIMIT 1",
                $eid, $profile_id, $grade_id, $did
            ));
            $data = [
                'codigo' => sanitize_text_field((string)($r->sigla ?: $did)),
                'nome' => sanitize_text_field((string)$r->nome),
                'categoria' => sanitize_key((string)($r->categoria ?: 'complementar')),
                'obrigatoria' => 1,
                'conta_progressao' => strtolower((string)$r->categoria) === 'nuclear' ? 1 : 0,
                'carga_horaria' => $r->carga_horaria !== null ? (int)$r->carga_horaria : null,
                'ordem' => $r->ordem_pauta !== null ? (int)$r->ordem_pauta : 999,
                'meta_json' => wp_json_encode(['legacy_disciplina_id' => $did, 'source' => 'sige_matriz_curricular']),
                'actualizado_em' => $now,
            ];
            if ($exists_id > 0) {
                if ($force) {
                    $wpdb->update($tSubjects, $data, ['id' => $exists_id, 'escola_id' => $eid]);
                    $updated++;
                }
            } else {
                $data['escola_id'] = $eid;
                $data['profile_id'] = $profile_id;
                $data['grade_id'] = $grade_id;
                $data['disciplina_id'] = $did;
                $data['criado_em'] = $now;
                $wpdb->insert($tSubjects, $data);
                $inserted++;
            }
        }

        update_option('sige_curriculum_last_sync_' . $eid, [
            'synced_at' => $now,
            'rows_found' => count((array)$rows),
            'inserted' => $inserted,
            'updated' => $updated,
            'force' => $force ? 1 : 0,
        ], false);

        return ['ok' => true, 'message' => 'Matriz actual sincronizada para o perfil Moçambique/SNE em modo fundação.', 'inserted' => $inserted, 'updated' => $updated, 'rows_found' => count((array)$rows)];
    }
}

if (!function_exists('sige_curriculum_get_stats')) {
    function sige_curriculum_get_stats(?int $escola_id = null): array {
        global $wpdb;
        $eid = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if (!sige_curriculum_tables_ready()) return ['ready' => false];
        $tables = [
            'profiles' => $wpdb->prefix . 'sige_curriculum_profiles',
            'grades' => $wpdb->prefix . 'sige_curriculum_grades',
            'subjects' => $wpdb->prefix . 'sige_curriculum_subjects',
            'rules' => $wpdb->prefix . 'sige_curriculum_assessment_rules',
            'matrix' => $wpdb->prefix . 'sige_matriz_curricular',
        ];
        $out = ['ready' => true];
        foreach ($tables as $key => $table) {
            $out[$key] = sige_curriculum_table_exists($table)
                ? (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM `{$table}` WHERE escola_id = %d", $eid))
                : 0;
        }
        $out['active_profile'] = sige_curriculum_get_active_profile($eid);
        $out['last_sync'] = get_option('sige_curriculum_last_sync_' . $eid, []);
        return $out;
    }
}

if (!function_exists('sige_curriculum_install')) {
    function sige_curriculum_install(): void {
        if (class_exists('SIGE_Migration')) {
            SIGE_Migration::activate();
        }
        sige_curriculum_seed_defaults();
        foreach (sige_curriculum_get_school_ids() as $eid) {
            // Sincronização inicial apenas copia dados para as novas tabelas; não muda o núcleo académico.
            sige_curriculum_sync_legacy_matrix((int)$eid, false);
        }
    }
}

add_action('admin_init', function () {
    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return;
    if (!sige_curriculum_tables_ready()) return;
    sige_curriculum_seed_defaults();
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    if (!get_option('sige_curriculum_initial_sync_done_' . $eid)) {
        sige_curriculum_sync_legacy_matrix($eid, false);
        update_option('sige_curriculum_initial_sync_done_' . $eid, 1, false);
    }
});

add_action('admin_post_sige_curriculum_sync_legacy', function () {
    if (!sige_curriculum_can_manage()) wp_die('Sem permissão.');
    check_admin_referer('sige_curriculum_sync_legacy');
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $res = sige_curriculum_sync_legacy_matrix($eid, true);
    $url = add_query_arg([
        'page' => 'sige-app',
        'view' => 'curriculos',
        'curriculum_sync' => !empty($res['ok']) ? 'ok' : 'error',
        'inserted' => (int)($res['inserted'] ?? 0),
        'updated' => (int)($res['updated'] ?? 0),
    ], admin_url('admin.php'));
    wp_safe_redirect($url);
    exit;
});

add_action('admin_post_sige_curriculum_select_profile', function () {
    if (!sige_curriculum_can_manage()) wp_die('Sem permissão.');
    check_admin_referer('sige_curriculum_select_profile');
    global $wpdb;
    if (!sige_curriculum_tables_ready()) wp_die('Tabelas do motor curricular ainda não existem.');
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $profile_id = isset($_POST['profile_id']) ? (int)$_POST['profile_id'] : 0;
    $tProfiles = $wpdb->prefix . 'sige_curriculum_profiles';
    $tSettings = $wpdb->prefix . 'sige_curriculum_school_settings';
    $valid = (int)$wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `{$tProfiles}` WHERE id = %d AND escola_id = %d LIMIT 1",
        $profile_id, $eid
    ));
    if ($valid > 0) {
        $now = current_time('mysql');
        $exists = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$tSettings}` WHERE escola_id = %d LIMIT 1", $eid));
        if ($exists > 0) {
            $wpdb->update($tSettings, [
                'active_profile_id' => $valid,
                'modo_execucao' => 'legacy_safe',
                'estado' => 'foundation',
                'actualizado_em' => $now,
                'actualizado_por' => get_current_user_id(),
            ], ['escola_id' => $eid]);
        } else {
            $wpdb->insert($tSettings, [
                'escola_id' => $eid,
                'active_profile_id' => $valid,
                'modo_execucao' => 'legacy_safe',
                'estado' => 'foundation',
                'criado_em' => $now,
                'actualizado_em' => $now,
                'actualizado_por' => get_current_user_id(),
            ]);
        }
    }
    wp_safe_redirect(add_query_arg(['page' => 'sige-app', 'view' => 'curriculos', 'profile_saved' => 1], admin_url('admin.php')));
    exit;
});
