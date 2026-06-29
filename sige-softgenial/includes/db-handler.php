<?php

if (!defined('ABSPATH')) exit;

/**

 * ==================================================

 * 1. REGISTO DE HOOKS E HELPERS

 * ==================================================

 */

// Helpers de Manutenção de BD

function sige_db_column_exists($table_name, $column_name) {

    global $wpdb;

    if (empty($table_name) || empty($column_name)) return false;

    // Garante que estamos a consultar a BD actual (evita falsos positivos noutros schemas)

    $db_name = $wpdb->dbname ? $wpdb->dbname : DB_NAME;

    $sql = "

        SELECT 1

        FROM INFORMATION_SCHEMA.COLUMNS

        WHERE TABLE_SCHEMA = %s

          AND TABLE_NAME   = %s

          AND COLUMN_NAME  = %s

        LIMIT 1

    ";

    $found = $wpdb->get_var($wpdb->prepare($sql, $db_name, $table_name, $column_name));

    return !empty($found);

}


// ============================================================================
// CACHE - invalidação leve de painéis com contagens de alunos
// ============================================================================
if (!function_exists('sige_clear_student_dashboard_caches')) {
    function sige_clear_student_dashboard_caches(): void {
        global $wpdb;
        if (!function_exists('delete_transient')) return;
        $prefixes = ['_transient_sige_sys_dash_html_', '_transient_timeout_sige_sys_dash_html_'];
        foreach ($prefixes as $prefix) {
            $like = $wpdb->esc_like($prefix) . '%';
            $wpdb->query($wpdb->prepare(
                "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
                $like
            ));
        }
    }
}

// ============================================================================
// TRANSFERÊNCIAS - SINCRONIZAÇÃO REAL ALUNO ↔ MATRÍCULA
// ============================================================================
if (!function_exists('sige_matricula_status_column_accepts')) {
    function sige_matricula_status_column_accepts(string $status): bool {
        global $wpdb;
        $tbl = $wpdb->prefix . 'sige_matriculas';
        $col = $wpdb->get_row($wpdb->prepare("SHOW COLUMNS FROM {$tbl} LIKE %s", 'status_matricula'));
        if (!$col) return false;
        $type = strtolower((string)($col->Type ?? ''));
        if (strpos($type, 'enum(') !== 0) return true;
        preg_match_all("/'([^']*)'/", $type, $m);
        $allowed = array_map('stripslashes', $m[1] ?? []);
        return in_array($status, $allowed, true);
    }
}

if (!function_exists('sige_matricula_status_from_aluno_status')) {
    function sige_matricula_status_from_aluno_status(string $status): string {
        $s = strtolower(trim($status));
        $s = str_replace(['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'], ['a','a','a','a','e','e','i','o','o','o','u','c'], $s);
        $s = str_replace(['_', '-'], ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        if (in_array($s, ['activo','ativo','activa','ativa'], true)) return 'activa';
        if (in_array($s, ['transferido','transferida','transferido saida','transferido saida'], true)) {
            if (sige_matricula_status_column_accepts('transferido_saida')) return 'transferido_saida';
            if (sige_matricula_status_column_accepts('transferido')) return 'transferido';
            return 'cancelada';
        }
        if (in_array($s, ['desistente','desistiu','matricula cancelada','matricula cancelado'], true)) {
            return sige_matricula_status_column_accepts('desistente') ? 'desistente' : 'cancelada';
        }
        if (in_array($s, ['inactivo','inativa','inativo','inativa','cancelado','cancelada'], true)) {
            if (sige_matricula_status_column_accepts('inactiva')) return 'inactiva';
            if (sige_matricula_status_column_accepts('inativa')) return 'inativa';
            return 'cancelada';
        }
        if ($s === 'suspenso') return 'activa';
        return 'activa';
    }
}

if (!function_exists('sige_sync_matricula_status_from_aluno')) {
    function sige_sync_matricula_status_from_aluno(int $aluno_id, int $ano_lectivo, string $status_aluno, int $escola_id = 0): void {
        global $wpdb;
        if ($aluno_id <= 0 || $ano_lectivo <= 0) return;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($escola_id <= 0) { return; }
        $tbl = $wpdb->prefix . 'sige_matriculas';
        if (!function_exists('sige_db_column_exists') || !sige_db_column_exists($tbl, 'status_matricula')) return;
        $novo_status = sige_matricula_status_from_aluno_status($status_aluno);
        $wpdb->update(
            $tbl,
            ['status_matricula' => $novo_status],
            ['aluno_id' => $aluno_id, 'ano_lectivo' => $ano_lectivo, 'escola_id' => $escola_id],
            ['%s'],
            ['%d', '%d', '%d']
        );
        if (function_exists('sige_clear_student_dashboard_caches')) sige_clear_student_dashboard_caches();
    }
}

if (!function_exists('sige_reparar_matriculas_transferidos_existentes')) {
    function sige_reparar_matriculas_transferidos_existentes(bool $force = false): void {
        if (!is_admin()) return;
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $ano = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (int)wp_date('Y');
        if ($eid <= 0 || $ano <= 0) return;

        $lock = 'sige_fix_transferidos_status_matricula_121094_' . $eid . '_' . $ano;
        if (!$force && function_exists('get_transient') && get_transient($lock)) return;

        $tA = $wpdb->prefix . 'sige_alunos';
        $tM = $wpdb->prefix . 'sige_matriculas';
        if (!function_exists('sige_db_column_exists') || !sige_db_column_exists($tM, 'status_matricula')) return;

        $changed = 0;
        $status_transferido = sige_matricula_status_from_aluno_status('transferido');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tM} m
             INNER JOIN {$tA} a ON a.id = m.aluno_id AND a.escola_id = m.escola_id
             SET m.status_matricula = %s
             WHERE m.escola_id = %d
               AND m.ano_lectivo = %d
               AND TRIM(LOWER(a.status)) IN ('transferido','transferida','transferido_saida','transferido saida','transferido saída')
               AND (m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo'))",
            $status_transferido, $eid, $ano
        ));
        $changed += max(0, (int)$wpdb->rows_affected);

        $status_desistente = sige_matricula_status_from_aluno_status('desistente');
        $wpdb->query($wpdb->prepare(
            "UPDATE {$tM} m
             INNER JOIN {$tA} a ON a.id = m.aluno_id AND a.escola_id = m.escola_id
             SET m.status_matricula = %s
             WHERE m.escola_id = %d
               AND m.ano_lectivo = %d
               AND TRIM(LOWER(a.status)) IN ('desistente','desistiu','inactivo','inativa','inativo','inactiva','cancelado','cancelada')
               AND (m.status_matricula IS NULL OR TRIM(LOWER(m.status_matricula)) IN ('activa','ativa','activo','ativo'))",
            $status_desistente, $eid, $ano
        ));
        $changed += max(0, (int)$wpdb->rows_affected);

        if (function_exists('set_transient')) set_transient($lock, current_time('mysql'), 5 * MINUTE_IN_SECONDS);
        if ($changed > 0 && function_exists('sige_clear_student_dashboard_caches')) sige_clear_student_dashboard_caches();
    }
}
add_action('admin_init', 'sige_reparar_matriculas_transferidos_existentes', 20);


/**

 * ==================================================

 * 1.1 UPGRADES DE BASE DE DADOS (ACADÉMICO)

 * ==================================================

 * Objectivo imediato:

 * - Garantir que a tabela sige_notas existe

 * - Adicionar a coluna turma_id (NULL) para isolar notas por Turma/Ano

 * - Criar índices (incluindo UNIQUE) para evitar duplicações e melhorar performance

 *

 * Nota:

 * - Mantém compatibilidade com dados antigos (turma_id pode ficar NULL).

 * - dbDelta só adiciona/ajusta o que falta; não destrói dados.

 */

if (!function_exists('sige_acad_run_db_upgrades')) {

    function sige_acad_run_db_upgrades() {

    // (v11.0) Migrado para class-sige-migration.php
    return;

    global $wpdb;

    $target_ver = 3;

    $current_ver = (int) get_option('sige_acad_db_version', 0);

    if ($current_ver >= $target_ver) return;

    $charset_collate = $wpdb->get_charset_collate();

    // Tabelas

    $tRegras = $wpdb->prefix . 'sige_regras_academicas';

    $tNotas  = $wpdb->prefix . 'sige_notas';

    // SIGE: Controlo de Ano Lectivo (aberto/encerrado)

    $tAnos = $wpdb->prefix . 'sige_anos_lectivos';

    // Criar tabela de anos lectivos (sem dbDelta para evitar ruído)

    $wpdb->query("CREATE TABLE IF NOT EXISTS {$tAnos} (

        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

        ano_lectivo INT(4) NOT NULL,

        status VARCHAR(20) NOT NULL DEFAULT 'aberto',

        encerrado_em DATETIME NULL,

        encerrado_por BIGINT(20) UNSIGNED NULL,

        PRIMARY KEY (id),

        UNIQUE KEY ano_lectivo (ano_lectivo)

    ) {$charset_collate};");

    // 1) Criar/Reparar tabelas (apenas se NÃO existirem)

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tRegras)) !== $tRegras) {

        $sql = "CREATE TABLE $tRegras (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            ano_lectivo INT(4) NOT NULL,

            classe VARCHAR(20) NOT NULL DEFAULT '',

            nota_minima_aprovacao TINYINT(3) UNSIGNED NOT NULL DEFAULT 10,

            media_minima_global TINYINT(3) UNSIGNED NOT NULL DEFAULT 10,

            max_negativas_transita TINYINT(3) UNSIGNED NOT NULL DEFAULT 2,

            max_negativas_progride TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,

            usa_media_global TINYINT(1) NOT NULL DEFAULT 1,

            eh_fim_ciclo TINYINT(1) NOT NULL DEFAULT 0,

            peso_mfd TINYINT(3) UNSIGNED NOT NULL DEFAULT 50,

            peso_exame TINYINT(3) UNSIGNED NOT NULL DEFAULT 50,

            exige_exame TINYINT(1) NOT NULL DEFAULT 0,

            permite_recurso TINYINT(1) NOT NULL DEFAULT 0,

            max_negativas_recurso TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,

            ativo TINYINT(1) NOT NULL DEFAULT 1,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

            classe_num INT(2) NOT NULL DEFAULT 0,

            classe_label VARCHAR(50) NULL DEFAULT NULL,

            PRIMARY KEY  (id),

            UNIQUE KEY uk_regras_ano_classe_num (ano_lectivo, classe_num),

            KEY idx_regras_ano (ano_lectivo),

            KEY idx_regras_classe_num (classe_num)

        ) $charset_collate;";

        dbDelta($sql);

    }

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tNotas)) !== $tNotas) {

        $sql = "CREATE TABLE $tNotas (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            aluno_id BIGINT(20) UNSIGNED NOT NULL,

            turma_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,

            disciplina VARCHAR(190) NOT NULL,

            trimestre TINYINT(1) UNSIGNED NOT NULL,

            ac DECIMAL(5,2) NULL DEFAULT NULL,

            acp DECIMAL(5,2) NULL DEFAULT NULL,

            at DECIMAL(5,2) NULL DEFAULT NULL,

            exame_af DECIMAL(5,2) NULL DEFAULT NULL,

            mfd DECIMAL(5,2) NULL DEFAULT NULL,

            nf DECIMAL(5,2) NULL DEFAULT NULL,

            ano_lectivo INT(4) NOT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id),

            KEY idx_notas_aluno (aluno_id),

            KEY idx_notas_turma (turma_id),

            KEY idx_notas_ano (ano_lectivo)

        ) $charset_collate;";

        dbDelta($sql);

    }

    // 2) Colunas de regras académicas - geridas por class-sige-migration.php (M5)
    //    Removido ALTER TABLE runtime (redundante desde SCHEMA_VERSION 20260404.2)

    // 3) Seed (blindado contra duplicados)

    sige_seed_regras_academicas_default();

    update_option('sige_acad_db_version', $target_ver);

}

}

/**

 * ==================================================

 * 1.3 SEED PADRÃO DAS REGRAS ACADÉMICAS

 * ==================================================

 * - Insere regras padrão para o ano lectivo actual (sige_config.ano_lectivo)

 * - Não duplica entradas existentes

 * - Marca automaticamente as classes de fim de ciclo: 3, 6, 9, 12

 */

if (!function_exists('sige_seed_regras_academicas_default')) {

    function sige_seed_regras_academicas_default() {

    if (!is_admin()) return;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return;

    global $wpdb;

    $tRegras = $wpdb->prefix . 'sige_regras_academicas';

    // Descobrir ano lectivo actual (fallback 0)

    $ano = 0;

    $tConfig = $wpdb->prefix . 'sige_config';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tConfig)) === $tConfig) {

        $ano = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ano_lectivo FROM $tConfig WHERE escola_id = %d LIMIT 1",
            sige_get_escola_id()
        ));

    }

    if ($ano < 2000) $ano = (int) wp_date('Y');

    // Se já houver regras para este ano, não volta a semear

    $has = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $tRegras WHERE ano_lectivo=%d", $ano));

    if ($has > 0) return;

    // Classes padrão 1-12

    $classes = range(1, 12);

    foreach ($classes as $c) {

        $is_fim_ciclo = in_array((int)$c, [3, 6, 9, 12], true) ? 1 : 0;

        $exige_exame  = $is_fim_ciclo ? 1 : 0;

        // Importante: preencher 'classe' (varchar) e 'classe_num' (int) para compatibilidade com índices antigos

        $data = [

            'ano_lectivo'            => $ano,

            'classe'                 => (string) $c,

            'classe_num'             => (int) $c,

            'classe_label'           => $c . 'ª',

            'nota_minima_aprovacao'  => 10,

            'media_minima_global'    => 10,

            'max_negativas_progride' => 0,

            'max_negativas_transita' => 2,

            'usa_media_global'       => 1,

            'eh_fim_ciclo'           => $is_fim_ciclo,

            'exige_exame'            => $exige_exame,

            'peso_mfd'               => 50,

            'peso_exame'             => 50,

            'permite_recurso'        => 0,

            'max_negativas_recurso'  => 0,

            'ativo'                  => 1,

        ];

        // REPLACE evita duplicados caso exista UNIQUE (ano_lectivo, classe_num) ou (ano_lectivo, classe)

        $wpdb->replace($tRegras, $data);

    }

}

}

// Configurações

add_action('wp_ajax_sige_salvar_config_avancado', 'sige_ajax_salvar_config_avancado');

add_action('wp_ajax_sige_ler_logs', 'sige_ajax_ler_logs');

add_action('wp_ajax_sige_restaurar_backup', 'sige_ajax_restaurar_backup');

add_action('wp_ajax_sige_testar_sms_config', 'sige_ajax_testar_sms_config');

// Professores

add_action('wp_ajax_sige_salvar_professor', 'sige_ajax_salvar_professor');

add_action('wp_ajax_sige_atualizar_professor', 'sige_ajax_atualizar_professor');

add_action('wp_ajax_sige_remover_professor', 'sige_ajax_remover_professor');

add_action('wp_ajax_sige_get_professor_detalhes', 'sige_ajax_get_professor_detalhes');

add_action('wp_ajax_sige_vincular_carga', 'sige_ajax_vincular_carga');

// Alunos e Turmas

add_action('wp_ajax_sige_processar_matricula', 'sige_ajax_salvar_aluno');

add_action('wp_ajax_sige_criar_turma', 'sige_ajax_criar_turma');

add_action('wp_ajax_sige_excluir_turma', 'sige_ajax_excluir_turma');

add_action('wp_ajax_sige_alocar_aluno', 'sige_ajax_alocar_aluno');

add_action('wp_ajax_sige_remover_alocacao', 'sige_ajax_remover_alocacao');

// Instalação

// (REMOVIDO v11.0) - Migração centralizada em class-sige-migration.php
// register_activation_hook e admin_init DB já não são geridos aqui.

/**
 * ==================================================
 * 1.2 UPGRADES DE BASE DE DADOS (FINANCEIRO)
 * ==================================================
 *
 * Versioning independente: sige_fin_db_version
 * Regra: só adiciona. Nunca destrói dados.
 * Usa sige_db_column_exists() para ADDs seguros.
 *
 * target_ver actual: 1
 *   v1 - Novas colunas em sige_fin_lancamentos
 *        Novas tabelas: contactos_cobranca, planos_pagamento, planos_prestacoes
 */
if (!function_exists('sige_fin_run_db_upgrades')) {

    function sige_fin_run_db_upgrades(): void {

        // (v11.0) Migrado para class-sige-migration.php
        return;

        global $wpdb;

        $target_ver  = 1;
        $current_ver = (int) get_option('sige_fin_db_version', 0);

        if ($current_ver >= $target_ver) return;

        $charset = $wpdb->get_charset_collate();
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $tL = $wpdb->prefix . 'sige_fin_lancamentos';

        // ── v1: Colunas quantidade e plano_id em sige_fin_lancamentos ─────
        //    Geridas por class-sige-migration.php (M3)
        //    Removido ALTER TABLE runtime (redundante desde SCHEMA_VERSION 20260404.2)

        // ── v1: sige_fin_contactos_cobranca ───────────────────────────────
        //
        // Log de contactos de cobrança - cada linha é uma tentativa de contacto
        // com um devedor. Permite ao director ver quem foi contactado, quando,
        // o resultado, e quando está agendado o próximo contacto.
        //
        $tC = $wpdb->prefix . 'sige_fin_contactos_cobranca';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tC)) !== $tC) {
            $sql = "CREATE TABLE $tC (
                `id`              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `escola_id`       BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
                `aluno_id`        BIGINT(20) UNSIGNED NOT NULL,
                `user_id`         BIGINT(20) UNSIGNED NOT NULL COMMENT 'Quem fez o contacto',
                `data_contacto`   DATETIME NOT NULL,
                `canal`           VARCHAR(30) NOT NULL DEFAULT 'whatsapp'
                                  COMMENT 'whatsapp | telefone | presencial | email | sms',
                `resultado`       VARCHAR(50) NOT NULL DEFAULT 'sem_resposta'
                                  COMMENT 'sem_resposta | prometeu_pagar | pagou | recusou | numero_errado | outro',
                `valor_prometido` DECIMAL(10,2) DEFAULT NULL
                                  COMMENT 'Valor que o encarregado prometeu pagar (se aplicável)',
                `data_prometida`  DATE DEFAULT NULL
                                  COMMENT 'Data que o encarregado prometeu pagar',
                `notas`           TEXT DEFAULT NULL,
                `proximo_contacto` DATE DEFAULT NULL
                                  COMMENT 'Agendamento do próximo contacto - usado para alertas',
                `criado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                KEY `idx_aluno`   (`escola_id`, `aluno_id`),
                KEY `idx_proximo` (`escola_id`, `proximo_contacto`)
            ) $charset;";
            dbDelta($sql);
        }

        // ── v1: sige_fin_planos_pagamento ─────────────────────────────────
        //
        // Plano de pagamento negociado com o encarregado.
        // O director/tesoureiro define o total, o nº de prestações e as datas.
        // Cada prestação é uma linha em sige_fin_planos_prestacoes.
        //
        $tPl = $wpdb->prefix . 'sige_fin_planos_pagamento';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tPl)) !== $tPl) {
            $sql = "CREATE TABLE $tPl (
                `id`              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `escola_id`       BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
                `aluno_id`        BIGINT(20) UNSIGNED NOT NULL,
                `criado_por`      BIGINT(20) UNSIGNED NOT NULL,
                `valor_total`     DECIMAL(12,2) NOT NULL COMMENT 'Total da dívida acordada',
                `n_prestacoes`    TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
                `descricao`       VARCHAR(255) DEFAULT NULL
                                  COMMENT 'Ex: Acordo Maio 2026 - dívida de Jan-Abr',
                `status`          VARCHAR(20) NOT NULL DEFAULT 'activo'
                                  COMMENT 'activo | cumprido | quebrado | cancelado',
                `notas`           TEXT DEFAULT NULL,
                `criado_em`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `aprovado_por`    BIGINT(20) UNSIGNED DEFAULT NULL,
                `aprovado_em`     DATETIME DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_aluno`   (`escola_id`, `aluno_id`),
                KEY `idx_status`  (`escola_id`, `status`)
            ) $charset;";
            dbDelta($sql);
        }

        // ── v1: sige_fin_planos_prestacoes ────────────────────────────────
        //
        // Cada linha = uma prestação de um plano.
        // Quando a prestação é paga, o lancamento_id é preenchido
        // e o status muda para 'pago'.
        //
        $tPr = $wpdb->prefix . 'sige_fin_planos_prestacoes';
        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tPr)) !== $tPr) {
            $sql = "CREATE TABLE $tPr (
                `id`              BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
                `escola_id`       BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
                `plano_id`        BIGINT(20) UNSIGNED NOT NULL,
                `numero`          TINYINT(3) UNSIGNED NOT NULL COMMENT 'Nº da prestação (1, 2, 3...)',
                `valor`           DECIMAL(10,2) NOT NULL,
                `data_vencimento` DATE NOT NULL,
                `lancamento_id`   BIGINT(20) UNSIGNED DEFAULT NULL
                                  COMMENT 'Preenchido ao criar o lançamento desta prestação',
                `status`          VARCHAR(20) NOT NULL DEFAULT 'pendente'
                                  COMMENT 'pendente | pago | atrasado | cancelado',
                `pago_em`         DATETIME DEFAULT NULL,
                `notas`           TEXT DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_plano`   (`plano_id`),
                KEY `idx_venc`    (`escola_id`, `data_vencimento`, `status`)
            ) $charset;";
            dbDelta($sql);
        }

        update_option('sige_fin_db_version', $target_ver);

    } // end sige_fin_run_db_upgrades

} // end function_exists guard

/**

 * ==================================================

 * 2. ESTRUTURA DA BASE DE DADOS

 * ==================================================

 */

function sige_criar_tabelas_sistema() {

    // (v11.0) Migrado para class-sige-migration.php
    return;

    global $wpdb;

    $target_ver  = 2;

    $current_ver = (int) get_option('sige_core_db_version', 0);

    if ($current_ver >= $target_ver) return;

    $charset_collate = $wpdb->get_charset_collate();

    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    // Lista mínima de tabelas essenciais (cria apenas se não existirem)

    $tables_sql = [];

    $tConfig = $wpdb->prefix . 'sige_config';

    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tConfig)) !== $tConfig) {

        $tables_sql[] = "CREATE TABLE $tConfig (

            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,

            ano_lectivo INT(4) NOT NULL DEFAULT 0,

            data_inicio_t1 DATE NULL DEFAULT NULL,

            data_fim_t1 DATE NULL DEFAULT NULL,

            data_inicio_t2 DATE NULL DEFAULT NULL,

            data_fim_t2 DATE NULL DEFAULT NULL,

            data_inicio_t3 DATE NULL DEFAULT NULL,

            data_fim_t3 DATE NULL DEFAULT NULL,

            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

            updated_at DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

            PRIMARY KEY (id)

        ) $charset_collate;";

    }

    // (Se precisares adicionar mais tabelas essenciais aqui, segue a mesma regra: uma coluna por linha)

    foreach ($tables_sql as $sql) {

        dbDelta($sql);

    }

    update_option('sige_core_db_version', $target_ver);

}

function sige_registar_log($acao, $detalhes) {

    global $wpdb;

    $user_id = get_current_user_id();

    $ip = $_SERVER['REMOTE_ADDR'];

    $wpdb->insert($wpdb->prefix . 'sige_logs_auditoria', array(

        'escola_id' => sige_get_escola_id(),

        'user_id' => $user_id, 

        'acao' => $acao, 

        'detalhes' => $detalhes, 

        'ip_address' => $ip

    ));

}

/**

 * ==================================================

 * 3. MOTOR DE NOTIFICAÇÕES

 * ==================================================

 */

function sige_enviar_notificacao($tipo_evento, $aluno_id, $dados_extras = []) {

    global $wpdb;

    $config = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
        sige_get_escola_id()
    ));

    $aluno  = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_alunos WHERE id = %d AND escola_id = %d", $aluno_id, sige_get_escola_id()));

    if (!$config || !$aluno) return false;

    $mensagem = ""; 

    $assunto = "";

    

    switch ($tipo_evento) {

        case 'boas_vindas': 

            $mensagem = $config->template_boas_vindas; 

            $assunto = "Bem-vindo"; 

            break;

        case 'atraso_pagamento': 

            $mensagem = $config->template_atraso_pagamento; 

            $assunto = "Aviso Financeiro"; 

            break;

        case 'falta': 

            $mensagem = $config->template_falta_aluno; 

            $assunto = "Falta"; 

            break;

        case 'nota': 

            $mensagem = $config->template_nota_lancada; 

            $assunto = "Notas"; 

            break;

    }

    

    if (empty($mensagem)) return false;

    $variaveis = [

        '{ALUNO}' => $aluno->nome_completo, 

        '{ENCARREGADO}' => $aluno->nome_pai, 

        '{DATA}' => wp_date('d/m/Y'),

        '{VALOR}' => isset($dados_extras['valor']) ? $dados_extras['valor'] . ' ' . $config->moeda : '',

        '{PASSWORD}' => isset($dados_extras['password']) ? $dados_extras['password'] : '******'

    ];

    

    $corpo_final = str_replace(array_keys($variaveis), array_values($variaveis), $mensagem);

    if (is_email($aluno->email_encarregado)) {

        $footer = $config->rodape_documentos ? "\n\n--\n" . $config->rodape_documentos : "";

        wp_mail($aluno->email_encarregado, $assunto, $corpo_final . $footer);

        sige_registar_log('Notificação Email', "Enviado para {$aluno->email_encarregado}");

    }

    

    return true;

}

/**

 * ==================================================

 * 4. FUNÇÕES AJAX

 * ==================================================

 */

// 4.1 SALVAR CONFIGURAÇÕES (ATUALIZADO SNE 4.0)

// [F4] Rate limiting via transients
// [F5] Token encryption
// [v12.10.140] CRYPTO-02: encriptação autenticada para segredos operacionais.
// Novo formato: sige2:{base64(nonce+ciphertext+mac)} com sodium secretbox.
// Mantém leitura retrocompatível de CBC antigo, sem devolver plaintext em falha.
function sige_encrypt_token($p) {
    if (empty($p)) return '';
    $plain = (string)$p;
    $key = hash('sha256', wp_salt('auth') . '|' . wp_salt('secure_auth') . '|sige-token-v2', true);
    if (function_exists('sodium_crypto_secretbox')) {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plain, $nonce, $key);
        return 'sige2:' . base64_encode($nonce . $cipher);
    }
    $iv = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($cipher === false) return '';
    return 'gcm1:' . base64_encode($iv . $tag . $cipher);
}
function sige_decrypt_token($e) {
    if (empty($e)) return '';
    $enc = (string)$e;
    $key = hash('sha256', wp_salt('auth') . '|' . wp_salt('secure_auth') . '|sige-token-v2', true);

    if (strpos($enc, 'sige2:') === 0 && function_exists('sodium_crypto_secretbox_open')) {
        $raw = base64_decode(substr($enc, 6), true);
        if ($raw === false || strlen($raw) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) return '';
        $nonce = substr($raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        return $plain === false ? '' : $plain;
    }

    if (strpos($enc, 'gcm1:') === 0) {
        $raw = base64_decode(substr($enc, 5), true);
        if ($raw === false || strlen($raw) <= 28) return '';
        $iv = substr($raw, 0, 12);
        $tag = substr($raw, 12, 16);
        $cipher = substr($raw, 28);
        $plain = openssl_decrypt($cipher, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? '' : $plain;
    }

    // Retrocompatibilidade: formato CBC antigo com IV aleatório no início.
    $k_old = substr(hash('sha256', wp_salt('auth')), 0, 32);
    $raw = base64_decode($enc, true);
    if ($raw !== false && strlen($raw) >= 17) {
        $iv = substr($raw, 0, 16);
        $ciphertext = substr($raw, 16);
        $d = openssl_decrypt($ciphertext, 'aes-256-cbc', $k_old, OPENSSL_RAW_DATA, $iv);
        if ($d !== false) return $d;
    }

    // Retrocompatibilidade: formato CBC mais antigo com IV determinístico.
    $decoded = base64_decode($enc, true);
    if ($decoded !== false) {
        $v_old = substr(hash('sha256', wp_salt('secure_auth')), 0, 16);
        $d2 = openssl_decrypt($decoded, 'aes-256-cbc', $k_old, 0, $v_old);
        if ($d2 !== false) return $d2;
    }

    // Segurança: não devolver o valor original como se fosse segredo válido.
    return '';
}

function sige_rate_limit($action, $max = 5, $window = 300) {
    $key = 'sige_rl_' . $action . '_' . md5($_SERVER['REMOTE_ADDR'] ?? 'x');
    $count = (int) get_transient($key);
    if ($count >= $max) {
        wp_send_json_error('Demasiadas tentativas. Aguarde ' . intval($window/60) . ' min.');
        exit;
    }
    set_transient($key, $count + 1, $window);
}

function sige_check_nonce_global() {
    $ng = $_POST['_sige_nonce_g'] ?? $_GET['_sige_nonce_g'] ?? '';
    if ($ng && wp_verify_nonce($ng, 'sige_global_action')) return true;
    $ns = $_POST['_sige_nonce'] ?? $_GET['_sige_nonce'] ?? '';
    if ($ns) {
        foreach (['sige_turmas_action','sige_alunos_action','sige_equipe_action','sige_cfg_global','sige_portaria_acesso','sige_testar_wpp_nonce'] as $a) {
            if (wp_verify_nonce($ns, $a)) return true;
        }
    }
    $wp = $_POST['_wpnonce'] ?? $_GET['_wpnonce'] ?? '';
    if ($wp && wp_verify_nonce($wp, 'sige_global_action')) return true;
    wp_send_json_error('Sessao expirada. Recarregue a pagina.');
    exit;
}

if (!function_exists('sige_ajax_user_can_permissions_or_caps')) {
    /**
     * v12.11.9.65 - defesa servidor para acções AJAX por matriz SIGE + fallback WP caps.
     */
    function sige_ajax_user_can_permissions_or_caps(array $permissions, array $legacy_caps = []): bool {
        if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) return true;
        if (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user()) return true;
        if (!function_exists('sige_is_real_wp_admin_user') && current_user_can('manage_options')) return true;
        foreach ($permissions as $permission) {
            $permission = is_string($permission) ? trim($permission) : '';
            if ($permission !== '' && function_exists('sige_can') && sige_can($permission)) return true;
        }
        // Mantém a matriz SIGE como fonte primária: se o utilizador já tem
        // perfil SIGE activo, não reabrimos a acção por capabilities antigas.
        $has_active_sige_role = function_exists('sige_page_guard_has_active_sige_role') ? sige_page_guard_has_active_sige_role() : false;
        if (!$has_active_sige_role) {
            foreach ($legacy_caps as $cap) {
                $cap = is_string($cap) ? trim($cap) : '';
                if ($cap !== '' && current_user_can($cap)) return true;
            }
        }
        return false;
    }
}

function sige_ajax_salvar_config_avancado() {
    // v12.9.105 - Saneamento legacy: handler hardenizado.
    //
    // Antes (v12.9.104): aceitava 50+ campos do form antigo, fazia UPDATE com
    // WHERE id=1 ignorando escola_id (vazamento multi-tenant), e gravava colunas
    // sem consumo operacional comprovado (figuras de estilo).
    //
    // Agora (v12.9.105):
    //   1. WHERE usa escola_id correcto (corrige Achado 1).
    //   2. Apenas campos activos comprovados são aceites (corrige Achado 2).
    //      Campos retirados do modo escola na v12.9.104 (sistema_avaliacao,
    //      nota_minima, nota_maxima, regra_arredondamento, escola_tem_areas,
    //      formato_recibo, prefixo_matricula, dados_bancarios,
    //      assinatura_director_url, turnos_config, feriados_escolares,
    //      escalas_qualitativas, tipos_avaliacao, datas de trimestre, tabela_precos,
    //      SMS legacy e templates SMS legacy, e regras financeiras duplicadas)
    //      são ignorados silenciosamente. Os dados antigos permanecem na BD,
    //      apenas a escrita é bloqueada.
    //   3. Templates WhatsApp legacy (msg_nova_fatura, msg_recibo_pago, msg_cobranca)
    //      continuam graváveis porque a notification-policy ainda os lê com
    //      fallback para o motor v2_render.
    //   4. SHOW TABLES LIKE passa a usar prepare().
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

    $tabela    = $wpdb->prefix . 'sige_config';
    $escola_id = sige_require_escola_id('config_avancado');

    if (function_exists('sige_wpp_ensure_destinatarios_column')) { sige_wpp_ensure_destinatarios_column(); }

    // Garantir que a tabela existe (com prepare).
    $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabela));
    if ((string) $found !== $tabela) {
        if (function_exists('sige_criar_tabelas_sistema')) {
            sige_criar_tabelas_sistema();
        }
    }

    // ── Whitelist v12.9.105 ──
    // Apenas estes campos são gravados a partir do form legacy. Tudo o resto é
    // descartado silenciosamente para evitar regressão. Os campos retirados
    // continuam acessíveis para leitura/auditoria.
    $dados = [];

    // Identificação institucional (lida em ~35 ficheiros do plugin).
    if (isset($_POST['nome_escola']))           $dados['nome_escola']           = sanitize_text_field(wp_unslash($_POST['nome_escola']));
    if (isset($_POST['codigo_escola']))         $dados['codigo_escola']         = sanitize_text_field(wp_unslash($_POST['codigo_escola']));
    if (isset($_POST['tipo_instituicao']))      $dados['tipo_instituicao']      = sanitize_text_field(wp_unslash($_POST['tipo_instituicao']));
    if (isset($_POST['ano_fundacao']))          $dados['ano_fundacao']          = sanitize_text_field(wp_unslash($_POST['ano_fundacao']));
    if (isset($_POST['entidade_proprietaria'])) $dados['entidade_proprietaria'] = sanitize_text_field(wp_unslash($_POST['entidade_proprietaria']));
    if (isset($_POST['nuit']))                  $dados['nuit']                  = sanitize_text_field(wp_unslash($_POST['nuit']));

    if (isset($_POST['ensino'])) {
        $ensino_arr = is_array($_POST['ensino']) ? $_POST['ensino'] : [];
        $dados['ensino_oferecido'] = implode(', ', array_map('sanitize_text_field', wp_unslash($ensino_arr)));
    } elseif (isset($_POST['ensino_oferecido'])) {
        $dados['ensino_oferecido'] = sanitize_text_field(wp_unslash($_POST['ensino_oferecido']));
    }

    // Localização e contactos.
    if (isset($_POST['pais']))                $dados['pais']                = sanitize_text_field(wp_unslash($_POST['pais']));
    if (isset($_POST['provincia']))           $dados['provincia']           = sanitize_text_field(wp_unslash($_POST['provincia']));
    if (isset($_POST['distrito']))            $dados['distrito']            = sanitize_text_field(wp_unslash($_POST['distrito']));
    if (isset($_POST['cidade']))              $dados['cidade']              = sanitize_text_field(wp_unslash($_POST['cidade']));
    if (isset($_POST['endereco_escola']))     $dados['endereco_escola']     = sanitize_textarea_field(wp_unslash($_POST['endereco_escola']));
    if (isset($_POST['telefone_oficial']))    $dados['telefone_oficial']    = sanitize_text_field(wp_unslash($_POST['telefone_oficial']));
    if (isset($_POST['email_institucional'])) $dados['email_institucional'] = sanitize_email(wp_unslash($_POST['email_institucional']));

    // Direcção.
    if (isset($_POST['director_nome'])) $dados['director_nome'] = sanitize_text_field(wp_unslash($_POST['director_nome']));
    if (isset($_POST['cargo_direcao'])) $dados['cargo_direcao'] = sanitize_text_field(wp_unslash($_POST['cargo_direcao']));

    // Operacional transversal.
    if (isset($_POST['ano_lectivo'])) $dados['ano_lectivo'] = intval($_POST['ano_lectivo']);
    if (isset($_POST['moeda']))       $dados['moeda']       = sanitize_text_field(wp_unslash($_POST['moeda']));

    // Marca e documentos.
    if (isset($_POST['logo_sistema_url']))     $dados['logo_sistema_url']     = esc_url_raw(wp_unslash($_POST['logo_sistema_url']));
    if (isset($_POST['logo_documentos_url']))  $dados['logo_documentos_url']  = esc_url_raw(wp_unslash($_POST['logo_documentos_url']));
    if (isset($_POST['cabecalho_oficial_url'])) $dados['cabecalho_oficial_url'] = esc_url_raw(wp_unslash($_POST['cabecalho_oficial_url']));
    if (isset($_POST['cor_primaria']))         $dados['cor_primaria']         = sanitize_hex_color(wp_unslash($_POST['cor_primaria']));
    if (isset($_POST['rodape_documentos']))    $dados['rodape_documentos']    = sanitize_textarea_field(wp_unslash($_POST['rodape_documentos']));

    // Módulos locais (continua válido por compatibilidade - Hub é fonte de verdade).
    if (isset($_POST['modulos'])) {
        $mods = is_array($_POST['modulos']) ? $_POST['modulos'] : [];
        $dados['modulos_ativos'] = wp_json_encode(array_values(array_map('sanitize_text_field', wp_unslash($mods))), JSON_UNESCAPED_UNICODE);
    }

    // WhatsApp/Z-API (canal operacional activo).
    if (isset($_POST['whatsapp_url'])) {
        $dados['whatsapp_url'] = esc_url_raw(wp_unslash($_POST['whatsapp_url']));
    }
    if (isset($_POST['whatsapp_token'])) {
        $plain = sanitize_text_field(wp_unslash($_POST['whatsapp_token']));
        // Preservar valor existente quando o campo chega vazio (mesma regra do controller novo).
        if ($plain !== '') {
            $dados['whatsapp_token'] = function_exists('sige_encrypt_token') ? sige_encrypt_token($plain) : $plain;
        }
    }
    if (isset($_POST['whatsapp_destinatarios_padrao'])) {
        $dest = wp_unslash($_POST['whatsapp_destinatarios_padrao']);
        $dados['whatsapp_destinatarios_padrao'] = function_exists('sige_wpp_destinatarios_normalize')
            ? sige_wpp_destinatarios_normalize($dest)
            : sanitize_key($dest);
    }

    // Templates WhatsApp legacy - preservados porque a notification-policy
    // ainda lê estas colunas com fallback para o motor conversacional v2_render.
    if (isset($_POST['msg_nova_fatura']))  $dados['msg_nova_fatura']  = wp_kses_post(wp_unslash($_POST['msg_nova_fatura']));
    if (isset($_POST['msg_recibo_pago']))  $dados['msg_recibo_pago']  = wp_kses_post(wp_unslash($_POST['msg_recibo_pago']));
    if (isset($_POST['msg_cobranca']))     $dados['msg_cobranca']     = wp_kses_post(wp_unslash($_POST['msg_cobranca']));

    if (empty($dados)) {
        wp_send_json_success('Nenhuma alteração detectada.');
    }

    // Garantir que existe uma linha para esta escola antes de actualizar.
    $row_id = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT id FROM `$tabela` WHERE escola_id = %d LIMIT 1",
        $escola_id
    ));

    if ($row_id > 0) {
        // Actualização multi-tenant correcta - corrige Achado 1.
        $res = $wpdb->update($tabela, $dados, ['id' => $row_id]);
    } else {
        // Primeira gravação para esta escola.
        $insert = array_merge($dados, ['escola_id' => $escola_id]);
        $res = $wpdb->insert($tabela, $insert);
    }

    if ($res !== false) {
        wp_send_json_success('Configurações Gravadas com Sucesso!');
    } else {
        wp_send_json_error('Erro SQL: ' . $wpdb->last_error);
    }

}

// 4.2 CRIAR TURMA

function sige_ajax_criar_turma() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_criar_turma')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario')) wp_send_json_error('Negado.');

    

    $wpdb->insert($wpdb->prefix . 'sige_turmas', array(

        'escola_id' => sige_get_escola_id(),

        'nome_turma' => sanitize_text_field($_POST['nome_turma']),

        'nivel_ensino' => sanitize_text_field($_POST['nivel_ensino']),

        'turno' => sanitize_text_field($_POST['turno']),

        'capacidade_max' => intval($_POST['capacidade_max']),

        'ano_lectivo' => wp_date('Y')

    ));

    

    wp_send_json_success('Turma criada!');

}

// 4.3 EXCLUIR TURMA

function sige_ajax_excluir_turma() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_excluir_turma')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario')) wp_send_json_error('Negado.');

    

    $wpdb->delete($wpdb->prefix . 'sige_turmas', array('id' => intval($_POST['id']), 'escola_id' => sige_get_escola_id()));

    wp_send_json_success('Turma removida.');

}

// 4.4 LER LOGS

function sige_ajax_ler_logs() {
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director') && !current_user_can('sige_secretario')) wp_send_json_error('Negado.');

    

    $escola_id = sige_get_escola_id();

    $logs = $wpdb->get_results($wpdb->prepare("

        SELECT l.*, u.display_name 

        FROM {$wpdb->prefix}sige_logs_auditoria l 

        LEFT JOIN {$wpdb->prefix}users u ON l.user_id = u.ID 

        WHERE l.escola_id = %d

        ORDER BY l.data_hora DESC 

        LIMIT 50

    ", $escola_id));

    

    foreach ($logs as $log) { 

        $log->data_formatada = date('d/m/Y H:i', strtotime($log->data_hora)); 

    }

    

    wp_send_json_success($logs);

}

// 4.5 BACKUP E RESTAURO

function sige_ajax_restaurar_backup() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_restaurar_backup')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

    

    if (!empty($_FILES['ficheiro_backup']['tmp_name'])) {

        $dados = function_exists('sige_sec_validate_backup_upload')
            ? sige_sec_validate_backup_upload($_FILES['ficheiro_backup'])
            : json_decode((string)file_get_contents($_FILES['ficheiro_backup']['tmp_name']), true);

        if (is_wp_error($dados)) {
            wp_send_json_error($dados->get_error_message());
        }

        if (is_array($dados)) {

            if (function_exists('sige_backup_config_snapshot')) {
                $snapshot = sige_backup_config_snapshot('before_config_restore');
                if ($snapshot === '' && function_exists('sige_security_log')) {
                    sige_security_log('backup_snapshot_failed', 'Não foi possível criar snapshot antes do restauro de configuração.');
                }
            }
            if (function_exists('sige_backup_school_light_snapshot')) {
                $snapshot_light = sige_backup_school_light_snapshot('before_config_restore');
                if ($snapshot_light === '' && function_exists('sige_security_log')) {
                    sige_security_log('backup_light_snapshot_failed', 'Não foi possível criar snapshot leve antes do restauro de configuração.');
                }
            }

            unset($dados['id']);
                $dados_limpos = array_intersect_key($dados, array_flip(['nome_escola','codigo_escola','tipo_instituicao','ensino_oferecido','provincia','distrito','ano_fundacao','endereco_escola','entidade_proprietaria','director_nome','cargo_direcao','sistema_avaliacao','ano_lectivo','nota_minima','nota_maxima','telefone_oficial','pais','cidade','email_institucional','logo_sistema_url','logo_documentos_url','cor_primaria','moeda','assinatura_director_url','rodape_documentos','prazo_pagamento_dias','prefixo_matricula','nuit','prazo_vencimento','multa_atraso_percentual','dias_para_bloqueio','desconto_irmaos','taxa_inscricao','modulos_ativos','tabela_precos','data_inicio_t1','data_fim_t1','data_inicio_t2','data_fim_t2','data_inicio_t3','data_fim_t3','tipo_multa','desconto_pronto_pagamento','desconto_funcionario','escola_tem_areas','turnos_config','feriados_escolares','cabecalho_oficial_url','escalas_qualitativas','tipos_avaliacao','formato_recibo','regra_arredondamento','dados_bancarios','msg_nova_fatura','msg_recibo_pago','msg_cobranca','whatsapp_destinatarios_padrao']));
                $dados = $dados_limpos;

            $res = $wpdb->update("{$wpdb->prefix}sige_config", $dados, array('escola_id' => sige_get_escola_id()));

            

            if ($res !== false) {

                wp_send_json_success('Configurações restauradas!');

            } else {

                wp_send_json_error('Erro BD.');

            }

        } else { 

            wp_send_json_error('JSON inválido.'); 

        }

    } else { 

        wp_send_json_error('Sem ficheiro.'); 

    }

}

// 4.6 TESTE SMS

function sige_ajax_testar_sms_config() {
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

    

    $numero = preg_replace('/[^0-9]/', '', $_POST['numero_teste']);

    $api_key = sanitize_text_field($_POST['api_key']);

    

    if(empty($api_key) || strlen($numero) < 9) {

        wp_send_json_error('Dados inválidos.');

    }

    

    wp_send_json_success("Teste enviado para $numero (Simulado)!");

}

// 4.7 PROFESSORES (Placeholders)

function sige_ajax_salvar_professor() {
    sige_check_nonce_global(); wp_send_json_success('Em breve'); }

function sige_ajax_atualizar_professor() {
    sige_check_nonce_global(); wp_send_json_success('Em breve'); }

function sige_ajax_remover_professor() {
    sige_check_nonce_global(); wp_send_json_success('Em breve'); }

function sige_ajax_get_professor_detalhes() {
    sige_check_nonce_global(); wp_send_json_success('Em breve'); }

function sige_ajax_alocar_aluno() {
    sige_check_nonce_global(); wp_send_json_success('Em breve'); }

function sige_ajax_remover_alocacao() {
    sige_check_nonce_global(); wp_send_json_success('Em breve'); }

function sige_ajax_vincular_carga() {
    sige_check_nonce_global(); wp_send_json_success('Em breve'); }

// 4.8 GET PERFIL ESCOLA

function sige_get_escola_perfil() { 

    global $wpdb; 

    return $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
        sige_get_escola_id()
    )); 

}

// 4.9 CRIAR UTILIZADOR STAFF (EQUIPA)

add_action('wp_ajax_sige_criar_usuario_staff', 'sige_ajax_criar_usuario_staff');

function sige_ajax_criar_usuario_staff() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_criar_usuario_staff')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_rate_limit('criar_staff', 5, 300);
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

    $nome = sanitize_text_field($_POST['nome_completo']);

    $email = sanitize_email($_POST['email_staff']);

    $role_staff = sanitize_key($_POST['role_staff'] ?? '');
    $allowed_staff_roles = ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda','sige_financeiro','sige_professor','sige_educador','sige_gestor_rh','sige_pedagogico','sige_motorista','sige_limpeza'];
    if (!in_array($role_staff, $allowed_staff_roles, true)) {
        wp_send_json_error('Perfil de utilizador inválido.');
    }

    if(email_exists($email)) wp_send_json_error('Este e-mail já existe.');

    // 1. Criar WP User

    $senha = wp_generate_password(12, true);

    $uid = wp_create_user($email, $senha, $email);

    if (is_wp_error($uid)) wp_send_json_error($uid->get_error_message());

    // 2. Atualizar Perfil

    $user = new WP_User($uid);

    $sige_role_slug = function_exists('sige_permissions_wp_role_to_sige_role') ? sige_permissions_wp_role_to_sige_role($role_staff) : '';
    if ($sige_role_slug !== '' && function_exists('sige_user_integrity_can_change_sige_role') && !sige_user_integrity_can_change_sige_role((int)$uid, $sige_role_slug, (int)sige_get_escola_id())) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user((int)$uid);
        wp_send_json_error('Alteração bloqueada pela guarda de integridade de utilizadores.');
    }
    $user->set_role($role_staff);
    if ($sige_role_slug !== '' && function_exists('sige_permissions_sync_user_role') && !sige_permissions_sync_user_role((int)$uid, $sige_role_slug, (int)sige_get_escola_id(), false)) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user((int)$uid);
        wp_send_json_error('Não foi possível sincronizar o perfil SIGE do utilizador.');
    }

    wp_update_user(['ID' => $uid, 'display_name' => $nome]);

    update_user_meta($uid, 'billing_phone', sanitize_text_field($_POST['telemovel']));

    // 3. Preparar Dados Extra (JSON)

    $banco = [

        'banco_nome' => sanitize_text_field($_POST['banco_nome']),

        'nib' => sanitize_text_field($_POST['nib']),

        'mpesa' => sanitize_text_field($_POST['mpesa'])

    ];

    

    $docs = [

        'doc_bi' => esc_url_raw($_POST['doc_bi']),

        'doc_cv' => esc_url_raw($_POST['doc_cv']),

        'doc_cert' => esc_url_raw($_POST['doc_cert'])

    ];

    // 4. Inserir na Tabela Professores

    $wpdb->insert($wpdb->prefix . 'sige_professores', [

        'escola_id' => sige_get_escola_id(),

        'nome_completo' => $nome,

        'email' => $email,

        'telemovel' => sanitize_text_field($_POST['telemovel']),

        'nuit' => sanitize_text_field($_POST['nuit']),

        'formacao_academica' => sanitize_text_field($_POST['formacao']),

        'tipo_contrato' => sanitize_text_field($_POST['tipo_contrato']),

        'fim_contrato' => sanitize_text_field($_POST['fim_contrato']),

        'salario_base' => floatval($_POST['salario_base'] ?? 0),

        'subsidio' => floatval($_POST['subsidio'] ?? 0),

        'dados_bancarios' => json_encode($banco),

        'documentos_urls' => json_encode($docs),

        'status_ativo' => 1,

        'data_admissao' => date('Y-m-d')

    ]);

    

    update_user_meta($uid, 'sige_professor_id', $wpdb->insert_id);

    // Email de Boas-vindas

    $reset_key = get_password_reset_key(get_user_by('email', $email));
            $reset_url = wp_login_url() . '?action=rp&key=' . $reset_key . '&login=' . rawurlencode($email);
            wp_mail($email, 'Acesso SIGE', "Bem-vindo ao SIGE!\n\nUtilizador: $email\n\nDefina a sua senha:\n$reset_url\n\nLink expira em 24h.");

    wp_send_json_success(['msg' => 'Funcionário criado!', 'pass' => $senha]);

}

// 4.10 REMOVER UTILIZADOR STAFF

add_action('wp_ajax_sige_remover_usuario_staff', 'sige_ajax_remover_usuario_staff');

function sige_ajax_remover_usuario_staff() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_remover_usuario_staff')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) wp_send_json_error('Negado.');

    

    require_once(ABSPATH.'wp-admin/includes/user.php');

    $user_id = intval($_POST['id']);

    

    if ($user_id == get_current_user_id()) {

        wp_send_json_error('Não pode remover a sua própria conta.');

    }

    global $wpdb;

    $email = get_userdata($user_id)->user_email;

    $wpdb->update($wpdb->prefix . 'sige_professores', ['status_ativo' => 0], ['email' => $email, 'escola_id' => sige_get_escola_id()]);

    if (wp_delete_user($user_id)) {

        wp_send_json_success('Utilizador removido.');

    } else {

        wp_send_json_error('Erro ao remover.');

    }

}

// 4.11 EDITAR UTILIZADOR STAFF

add_action('wp_ajax_sige_editar_usuario_staff', 'sige_ajax_editar_usuario_staff');

function sige_ajax_editar_usuario_staff() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_editar_usuario_staff')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')) {

        wp_send_json_error('Negado.');

    }

    $uid = intval($_POST['user_id']);

    $nome = sanitize_text_field($_POST['nome_completo']);

    $email = sanitize_email($_POST['email_staff']);

    $role_staff = sanitize_key($_POST['role_staff'] ?? '');
    $allowed_staff_roles = ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda','sige_financeiro','sige_professor','sige_educador','sige_gestor_rh','sige_pedagogico','sige_motorista','sige_limpeza'];
    if (!in_array($role_staff, $allowed_staff_roles, true)) {
        wp_send_json_error('Perfil de utilizador inválido.');
    }

    // Atualizar WP User

    wp_update_user(['ID' => $uid, 'display_name' => $nome, 'user_email' => $email]);

    $u = new WP_User($uid);
    $old_roles = array_values((array)$u->roles);
    $old_role = reset($old_roles) ?: '';
    $sige_role_slug = function_exists('sige_permissions_wp_role_to_sige_role') ? sige_permissions_wp_role_to_sige_role($role_staff) : '';
    if ($sige_role_slug !== '' && function_exists('sige_user_integrity_can_change_sige_role') && !sige_user_integrity_can_change_sige_role((int)$uid, $sige_role_slug, (int)sige_get_escola_id())) {
        wp_send_json_error('Alteração bloqueada pela guarda de integridade de utilizadores.');
    }
    $u->set_role($role_staff);
    if ($sige_role_slug !== '' && function_exists('sige_permissions_sync_user_role') && !sige_permissions_sync_user_role((int)$uid, $sige_role_slug, (int)sige_get_escola_id(), false)) {
        if ($old_role !== '') {
            $rollback = new WP_User($uid);
            $rollback->set_role((string)$old_role);
        }
        wp_send_json_error('Não foi possível sincronizar o perfil SIGE do utilizador.');
    }

    update_user_meta($uid, 'billing_phone', sanitize_text_field($_POST['telemovel']));

    // Dados JSON

    // [XSS-07] Sanitizar campos POST antes de gravar
    $banco = [

        'banco_nome' => sanitize_text_field($_POST['banco_nome'] ?? ''), 

        'nib' => sanitize_text_field($_POST['nib'] ?? ''), 

        'mpesa' => sanitize_text_field($_POST['mpesa'] ?? '')

    ];

    

    $docs = [

        'doc_bi' => esc_url_raw($_POST['doc_bi'] ?? ''), 

        'doc_cv' => esc_url_raw($_POST['doc_cv'] ?? ''), 

        'doc_cert' => esc_url_raw($_POST['doc_cert'] ?? '')

    ];

    // Atualizar/Criar Ficha RH

    $prof_id = get_user_meta($uid, 'sige_professor_id', true);

    

    $dados_rh = [

        'nome_completo' => $nome,

        'email' => $email,

        'telemovel' => sanitize_text_field($_POST['telemovel']),

        'nuit' => sanitize_text_field($_POST['nuit']),

        'formacao_academica' => sanitize_text_field($_POST['formacao']),

        'tipo_contrato' => sanitize_text_field($_POST['tipo_contrato']),

        'fim_contrato' => sanitize_text_field($_POST['fim_contrato']),

        'salario_base' => floatval($_POST['salario_base']),

        'subsidio' => floatval($_POST['subsidio']),

        'foto_perfil' => esc_url_raw($_POST['foto_perfil']),

        'dados_bancarios' => json_encode($banco),

        'documentos_urls' => json_encode($docs),

        'status_ativo' => 1

    ];

    if ($prof_id) {

        $wpdb->update($wpdb->prefix . 'sige_professores', $dados_rh, ['id' => $prof_id, 'escola_id' => sige_get_escola_id()]);

    } else {

        $dados_rh['data_admissao'] = date('Y-m-d');

        $dados_rh['escola_id'] = sige_get_escola_id();

        $wpdb->insert($wpdb->prefix . 'sige_professores', $dados_rh);

        update_user_meta($uid, 'sige_professor_id', $wpdb->insert_id);

    }

    wp_send_json_success('Ficha actualizada com sucesso!');

}

// 4.12 RESETAR SENHA

add_action('wp_ajax_sige_resetar_senha', 'sige_ajax_resetar_senha');

function sige_ajax_resetar_senha() {
    sige_rate_limit('resetar_senha', 3, 600);
    sige_check_nonce_global();

    if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')) {
        wp_send_json_error('Negado.');
    }

    $user_id = intval($_POST['id'] ?? 0);
    $user = get_userdata($user_id);
    if (!$user) wp_send_json_error('Utilizador não encontrado.');

    global $wpdb;
    $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
    $cfg = $wpdb->get_row($wpdb->prepare("SELECT nome_escola, email_institucional FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1", $eid));
    $nome_escola = $cfg->nome_escola ?? 'SIGE SoftGenial';
    $email_escola = $cfg->email_institucional ?? get_option('admin_email');

    $ok = function_exists('sige_sec_send_password_reset_email')
        ? sige_sec_send_password_reset_email($user, (string)$nome_escola, (string)$email_escola)
        : retrieve_password($user->user_login);

    if (function_exists('sige_audit_log')) {
        sige_audit_log('reset_senha_link_seguro', [
            'staff_user_id' => $user_id,
            'email' => $user->user_email,
            'resultado' => 'Link único de redefinição enviado.'
        ], 'professores');
    }

    if ($ok) {
        wp_send_json_success('Link seguro de redefinição enviado para ' . $user->user_email . '. A senha não é mostrada nem enviada em texto claro.');
    }
    wp_send_json_error('Não foi possível enviar o link de redefinição. Verifique SMTP.');
}

// 4.13 GERIR CRITÉRIOS PRÉ-ESCOLAR

add_action('wp_ajax_sige_salvar_criterio', 'sige_ajax_salvar_criterio');

function sige_ajax_salvar_criterio() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_salvar_criterio')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $did = intval($_POST['disciplina_id']);

    $texto = sanitize_text_field($_POST['criterio']);

    

    $wpdb->insert($wpdb->prefix.'sige_disciplinas_indicadores', [

        'escola_id' => sige_get_escola_id(),

        'disciplina_id' => $did, 

        'criterio' => $texto

    ]);

    

    wp_send_json_success($wpdb->insert_id);

}

add_action('wp_ajax_sige_remover_criterio', 'sige_ajax_remover_criterio');

function sige_ajax_remover_criterio() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_remover_criterio')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $id = intval($_POST['id']);

    $wpdb->delete($wpdb->prefix.'sige_disciplinas_indicadores', ['id' => $id, 'escola_id' => sige_get_escola_id()]);

    wp_send_json_success();

}

add_action('wp_ajax_sige_listar_criterios', 'sige_ajax_listar_criterios');

function sige_ajax_listar_criterios() {
    sige_check_nonce_global();

    global $wpdb;

    $did = intval($_POST['disciplina_id']);

    $res = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_disciplinas_indicadores WHERE disciplina_id = %d AND escola_id = %d", $did, sige_get_escola_id()));

    wp_send_json_success($res);

}

// 4.14 GESTÃO DA MATRIZ CURRICULAR

add_action('wp_ajax_sige_vincular_matriz', 'sige_ajax_vincular_matriz');

function sige_ajax_vincular_matriz() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_vincular_matriz')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $classe = sanitize_text_field($_POST['classe']);

    $did = intval($_POST['disciplina_id']);

    $carga = intval($_POST['carga']);

    $existe = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$wpdb->prefix}sige_matriz_curricular WHERE classe = %s AND disciplina_id = %d AND escola_id = %d", $classe, $did, sige_get_escola_id()));

    

    if (!$existe) {

        $wpdb->insert($wpdb->prefix.'sige_matriz_curricular', [

            'escola_id' => sige_get_escola_id(),

            'classe' => $classe,

            'disciplina_id' => $did,

            'carga_horaria' => $carga

        ]);

        wp_send_json_success();

    } else {

        wp_send_json_error('Esta disciplina já existe nesta classe.');

    }

}

add_action('wp_ajax_sige_remover_matriz', 'sige_ajax_remover_matriz');

function sige_ajax_remover_matriz() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_remover_matriz')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $id = intval($_POST['id']);

    $wpdb->delete($wpdb->prefix.'sige_matriz_curricular', ['id' => $id, 'escola_id' => sige_get_escola_id()]);

    wp_send_json_success();

}

add_action('wp_ajax_sige_get_matriz', 'sige_ajax_get_matriz');

function sige_ajax_get_matriz() {
    sige_check_nonce_global();

    global $wpdb;

    $classe = sanitize_text_field($_POST['classe']);

    $res = $wpdb->get_results($wpdb->prepare("
        SELECT m.id, m.carga_horaria, m.ordem_pauta, d.nome, d.sigla 
        FROM {$wpdb->prefix}sige_matriz_curricular m
        JOIN {$wpdb->prefix}sige_disciplinas d ON m.disciplina_id = d.id
        WHERE m.classe = %s AND m.escola_id = %d
        ORDER BY m.ordem_pauta ASC, d.nome ASC
    ", $classe, sige_get_escola_id()));

    wp_send_json_success($res);
}

// 4.15 CLONAR MATRIZ ENTRE CLASSES

add_action('wp_ajax_sige_clonar_matriz', 'sige_ajax_clonar_matriz');

function sige_ajax_clonar_matriz() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_clonar_matriz')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $origem = sanitize_text_field($_POST['origem']);

    $destino = sanitize_text_field($_POST['destino']);

    if ($origem === $destino) wp_send_json_error('Classe de origem e destino são iguais.');

    $wpdb->delete($wpdb->prefix.'sige_matriz_curricular', ['classe' => $destino, 'escola_id' => sige_get_escola_id()]);

    $itens = $wpdb->get_results($wpdb->prepare("SELECT disciplina_id, carga_horaria, ordem_pauta FROM {$wpdb->prefix}sige_matriz_curricular WHERE classe = %s AND escola_id = %d", $origem, sige_get_escola_id()));

    foreach ($itens as $i) {

        $wpdb->insert($wpdb->prefix.'sige_matriz_curricular', [

            'escola_id' => sige_get_escola_id(),

            'classe' => $destino,

            'disciplina_id' => $i->disciplina_id,

            'carga_horaria' => $i->carga_horaria,

            'ordem_pauta' => $i->ordem_pauta

        ]);

    }

    

    wp_send_json_success();

}

// 4.16 ATUALIZAR ORDEM DA DISCIPLINA NA MATRIZ

add_action('wp_ajax_sige_update_ordem_matriz', 'sige_ajax_update_ordem_matriz');

function sige_ajax_update_ordem_matriz() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_update_ordem_matriz')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $id = intval($_POST['id']);

    $ordem = intval($_POST['ordem']);

    $wpdb->update($wpdb->prefix.'sige_matriz_curricular', ['ordem_pauta' => $ordem], ['id' => $id, 'escola_id' => sige_get_escola_id()]);

    wp_send_json_success();

}

// Handler em lote para drag-and-drop de ordem na matriz curricular

add_action('wp_ajax_sige_update_ordem_matriz_lote', 'sige_ajax_update_ordem_matriz_lote');

function sige_ajax_update_ordem_matriz_lote() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_update_ordem_matriz_lote')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $ordens = json_decode(stripslashes($_POST['ordens'] ?? '[]'), true);

    if (!is_array($ordens) || empty($ordens)) { wp_send_json_error('Dados invalidos'); }

    foreach ($ordens as $item) {

        $id    = (int)($item['id'] ?? 0);

        $ordem = (int)($item['ordem'] ?? 0);

        if ($id > 0) {

            $wpdb->update($wpdb->prefix . 'sige_matriz_curricular', ['ordem_pauta' => $ordem], ['id' => $id, 'escola_id' => sige_get_escola_id()]);

        }

    }

    wp_send_json_success();

}

// 4.17 GESTÃO DE TURMAS

add_action('wp_ajax_sige_salvar_turma', 'sige_ajax_salvar_turma');

function sige_ajax_salvar_turma() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_salvar_turma')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $id = isset($_POST['id_turma']) ? intval($_POST['id_turma']) : 0;

    

    $dados = [

        'nome'              => sanitize_text_field($_POST['nome']),

        'classe'            => sanitize_text_field($_POST['classe']),

        'turno'             => sanitize_text_field($_POST['turno']),

        'sala'              => sanitize_text_field($_POST['sala']),

        'ano_lectivo'       => 2026,

        'capacidade'        => intval($_POST['capacidade']),

        'director_turma_id' => !empty($_POST['director_turma_id']) ? intval($_POST['director_turma_id']) : NULL

    ];

    if ($id > 0) {

        $wpdb->update($wpdb->prefix.'sige_turmas', $dados, ['id' => $id, 'escola_id' => sige_get_escola_id()]);

        wp_send_json_success();

    } else {

        $dados['escola_id'] = sige_get_escola_id();

        $wpdb->insert($wpdb->prefix.'sige_turmas', $dados);

        $turma_id = $wpdb->insert_id;

        

        // Herdar disciplinas da Matriz

        $classe = $dados['classe'];

        $matriz = $wpdb->get_results($wpdb->prepare("SELECT disciplina_id FROM {$wpdb->prefix}sige_matriz_curricular WHERE classe = %s AND escola_id = %d", $classe, sige_get_escola_id()));

        

        foreach ($matriz as $m) {

            $wpdb->insert($wpdb->prefix.'sige_turma_disciplinas', [

                'escola_id' => sige_get_escola_id(),

                'turma_id' => $turma_id, 

                'disciplina_id' => $m->disciplina_id

            ]);

        }

        

        wp_send_json_success();

    }

    

    wp_die();

}

// 4.18 REMOVER TURMA

add_action('wp_ajax_sige_remover_turma', 'sige_ajax_remover_turma');

function sige_ajax_remover_turma() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_remover_turma')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $id = intval($_POST['id']);

    

    $wpdb->delete($wpdb->prefix . 'sige_turmas', ['id' => $id, 'escola_id' => sige_get_escola_id()]);

    $wpdb->delete($wpdb->prefix . 'sige_turma_disciplinas', ['turma_id' => $id, 'escola_id' => sige_get_escola_id()]);

    

    wp_send_json_success();

    wp_die();

}

// 4.19 LISTAR DOCENTES DA TURMA

add_action('wp_ajax_sige_listar_docentes_turma', 'sige_ajax_listar_docentes_turma');

function sige_ajax_listar_docentes_turma() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_listar_docentes_turma')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $turma_id = intval($_POST['turma_id']);

    // 1. Obter a classe da turma

    $turma = $wpdb->get_row($wpdb->prepare(

        "SELECT classe FROM {$wpdb->prefix}sige_turmas WHERE id = %d AND escola_id = %d LIMIT 1", $turma_id, sige_get_escola_id()));

    if (!$turma) { wp_send_json_error('Turma nao encontrada.'); wp_die(); }

    $classe = $turma->classe;

    // 2. Disciplinas da matriz curricular para esta classe

    $matriz = $wpdb->get_results($wpdb->prepare(

        "SELECT mc.disciplina_id, mc.carga_horaria, d.nome AS disc_nome, d.sigla

         FROM {$wpdb->prefix}sige_matriz_curricular mc

         JOIN {$wpdb->prefix}sige_disciplinas d ON d.id = mc.disciplina_id

         WHERE mc.classe = %s AND mc.escola_id = %d AND d.activo = 1

         ORDER BY mc.ordem_pauta ASC, d.nome ASC", $classe, sige_get_escola_id()));

    if (empty($matriz)) {

        // Fallback: usar disciplinas ja vinculadas mesmo sem matriz

        $res = $wpdb->get_results($wpdb->prepare(

            "SELECT td.id, d.nome AS disciplina_nome, d.sigla, td.professor_id, 0 AS carga_horaria

             FROM {$wpdb->prefix}sige_turma_disciplinas td

             JOIN {$wpdb->prefix}sige_disciplinas d ON td.disciplina_id = d.id

             WHERE td.turma_id = %d AND td.escola_id = %d ORDER BY d.nome ASC", $turma_id, sige_get_escola_id()));

        wp_send_json_success(['disciplinas' => $res, 'classe' => $classe, 'fonte' => 'vinculo_existente']);

        wp_die();

    }

    // 3. Auto-criar vinculos em falta (disciplinas da matriz sem vinculo na turma)

    foreach ($matriz as $m) {

        $existe = $wpdb->get_var($wpdb->prepare(

            "SELECT id FROM {$wpdb->prefix}sige_turma_disciplinas

             WHERE turma_id = %d AND disciplina_id = %d AND escola_id = %d LIMIT 1",

            $turma_id, $m->disciplina_id, sige_get_escola_id()));

        if (!$existe) {

            $wpdb->insert($wpdb->prefix.'sige_turma_disciplinas', [

                'escola_id'    => sige_get_escola_id(),

                'turma_id'     => $turma_id,

                'disciplina_id'=> $m->disciplina_id,

                'professor_id' => null,

            ]);

        }

    }

    // 4. Devolver lista completa com professor_id actual

    $res = $wpdb->get_results($wpdb->prepare(

        "SELECT td.id, d.nome AS disciplina_nome, d.sigla, mc.carga_horaria, td.professor_id

         FROM {$wpdb->prefix}sige_turma_disciplinas td

         JOIN {$wpdb->prefix}sige_disciplinas d ON td.disciplina_id = d.id

         LEFT JOIN {$wpdb->prefix}sige_matriz_curricular mc

               ON mc.disciplina_id = td.disciplina_id AND mc.classe = %s

         WHERE td.turma_id = %d AND td.escola_id = %d AND d.activo = 1

         ORDER BY COALESCE(mc.ordem_pauta, 99) ASC, d.nome ASC",

        $classe, $turma_id, sige_get_escola_id()));

    wp_send_json_success(['disciplinas' => $res, 'classe' => $classe, 'fonte' => 'matriz']);

    wp_die();

}

// 4.20 SALVAR PROFESSOR NA DISCIPLINA

add_action('wp_ajax_sige_salvar_docente_disciplina', 'sige_ajax_salvar_docente_disciplina');

function sige_ajax_salvar_docente_disciplina() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_salvar_docente_disciplina')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    

    $id_vinculo = intval($_POST['id_vinculo']);

    $professor_id = !empty($_POST['professor_id']) ? intval($_POST['professor_id']) : NULL;

    

    $tabela = $wpdb->prefix . 'sige_turma_disciplinas';

    $resultado = $wpdb->update(

        $tabela, 

        ['professor_id' => $professor_id], 

        ['id' => $id_vinculo, 'escola_id' => sige_get_escola_id()]

    );

    if ($resultado !== false) {

        wp_send_json_success("Docente alocado!");

    } else {

        wp_send_json_error("Erro ao guardar docente: " . $wpdb->last_error);

    }

    

    wp_die();

}

/**

 * ==================================================

 * 5. MÓDULO DE ALUNOS E MATRÍCULAS

 * ==================================================

 */

// 5.1 SALVAR ALUNO (COM UPLOAD DE FOTO, DOCUMENTOS, DADOS MÉDICOS E FINANCEIROS)

add_action('wp_ajax_sige_salvar_aluno', 'sige_ajax_salvar_aluno');

// [SEGURANÇA] Removido: add_action('wp_ajax_nopriv_sige_salvar_aluno', 'sige_ajax_salvar_aluno');

function sige_ajax_salvar_aluno() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_salvar_aluno')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    global $wpdb;

    $id_aluno = isset($_POST['id_aluno']) ? (int)$_POST['id_aluno'] : 0;
    $sige_required_aluno_permission = $id_aluno > 0 ? 'alunos.editar' : 'alunos.criar';

    // v12.11.9.65 - Guarda/Portaria pode consultar alunos, mas nunca gravar cadastro.
    if (!sige_ajax_user_can_permissions_or_caps(
        [$sige_required_aluno_permission],
        ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
    )) {
        wp_send_json_error($id_aluno > 0 ? 'Sem permissão para editar alunos.' : 'Sem permissão para criar alunos.');
    }

    $tbl_alunos     = $wpdb->prefix . 'sige_alunos';

    $tbl_matriculas = $wpdb->prefix . 'sige_matriculas';

    $tbl_turmas     = $wpdb->prefix . 'sige_turmas';

    // [v12.11.9.50] Snapshot anterior dos campos de encarregados para auditoria própria.
    // A leitura é escopada por escola e não bloqueia a gravação se a camada ainda não estiver carregada.
    $sige_guardian_audit_before = null;
    if ($id_aluno > 0 && function_exists('sige_guardian_adv_audit_log')) {
        $sige_guardian_audit_before = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tbl_alunos} WHERE id = %d AND escola_id = %d LIMIT 1",
            $id_aluno,
            sige_get_escola_id()
        ));
    }

    // Ano lectivo actual (usa config, com fallback)

    $config = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM {$wpdb->prefix}sige_config WHERE escola_id = %d LIMIT 1",
        sige_get_escola_id()
    ));

    $ano_lectivo = ($config && !empty($config->ano_lectivo)) ? (int)$config->ano_lectivo : (int)wp_date('Y');

    // Turma

    $turma_id = isset($_POST['turma_id']) ? (int)$_POST['turma_id'] : 0;

    if ($turma_id <= 0) {

        wp_send_json_error('Turma inválida.');

    }

    // Helper para checkbox

    $cb = function($k) {

        return (isset($_POST[$k]) && (string)$_POST[$k] === '1') ? 1 : 0;

    };

    // [v12.11.9.49] Checkbox com valor padrão para campos novos.
    // Se a chamada vier de formulário antigo sem estes campos, preserva o comportamento legado.
    // Se o formulário novo enviar hidden=0 + checkbox=1, respeita a escolha explícita do utilizador.
    $cb_default = function($k, $default) {
        if (!array_key_exists($k, $_POST)) {
            return (int)$default;
        }
        return ((string)$_POST[$k] === '1') ? 1 : 0;
    };

    // [v12.11.9.49] Helpers defensivos para Gestão Avançada de Encarregados.
    // Normaliza números móveis moçambicanos sem forçar erro em campos vazios.
    $phone = function($k) {
        $digits = preg_replace('/\D+/', '', (string)($_POST[$k] ?? ''));
        if (strlen($digits) === 12 && substr($digits, 0, 3) === '258') {
            $digits = substr($digits, 3);
        }
        return $digits;
    };
    $valid_phone = function($value) {
        $value = (string)$value;
        return $value === '' || (bool)preg_match('/^(82|83|84|85|86|87)\d{7}$/', $value);
    };
    foreach (['telemovel_pai','telemovel_pai_2','telemovel_mae','telemovel_mae_2','whatsapp_notificacoes','encarregado_principal_telemovel','contacto_alternativo_telemovel','autorizado_buscar_telemovel'] as $campo_tel) {
        $tel_norm = $phone($campo_tel);
        if (!$valid_phone($tel_norm)) {
            wp_send_json_error('Contacto inválido em ' . sanitize_text_field($campo_tel) . '. Use número móvel moçambicano com 9 dígitos iniciado por 82, 83, 84, 85, 86 ou 87.');
        }
    }
    $principal_tipo = sanitize_key($_POST['encarregado_principal_tipo'] ?? 'pai_mae');
    if (!in_array($principal_tipo, ['pai_mae','pai','mae','outro'], true)) {
        $principal_tipo = 'pai_mae';
    }
    if ($principal_tipo === 'outro') {
        $principal_nome = sanitize_text_field($_POST['encarregado_principal_nome'] ?? '');
        $principal_tel  = $phone('encarregado_principal_telemovel');
        if ($principal_nome === '' || $principal_tel === '') {
            wp_send_json_error('Quando selecciona “Outro encarregado principal”, indique pelo menos o nome e o telemóvel válido.');
        }
    }
    $canal_preferencial = sanitize_key($_POST['canal_preferencial_comunicacao'] ?? 'whatsapp');
    if (!in_array($canal_preferencial, ['whatsapp','email','chamada','sms'], true)) {
        $canal_preferencial = 'whatsapp';
    }

    // [v12.11.9.51] Smoke hardening - resolução server-side do contacto/e-mail principal.
    // Não depende apenas do JavaScript para preencher whatsapp_notificacoes/contacto_encarregado.
    // Evita também que email_encarregado seja limpo quando o campo "outro encarregado" existe no formulário mas está vazio.
    $guardian_first = function(array $values) {
        foreach ($values as $value) {
            $value = is_string($value) ? trim($value) : $value;
            if ($value !== '' && $value !== null) return $value;
        }
        return '';
    };
    $tel_pai_1 = $phone('telemovel_pai');
    $tel_pai_2 = $phone('telemovel_pai_2');
    $tel_mae_1 = $phone('telemovel_mae');
    $tel_mae_2 = $phone('telemovel_mae_2');
    $tel_whatsapp = $phone('whatsapp_notificacoes');
    $tel_outro = $phone('encarregado_principal_telemovel');
    $email_pai = sanitize_email($_POST['email_pai'] ?? '');
    $email_mae = sanitize_email($_POST['email_mae'] ?? '');
    $email_outro = sanitize_email($_POST['encarregado_principal_email'] ?? '');

    if ($principal_tipo === 'pai') {
        $guardian_primary_phone = $guardian_first([$tel_whatsapp, $tel_pai_1, $tel_pai_2, $tel_mae_1, $tel_mae_2, $tel_outro]);
        $guardian_primary_email = $guardian_first([$email_pai, $email_mae, $email_outro]);
    } elseif ($principal_tipo === 'mae') {
        $guardian_primary_phone = $guardian_first([$tel_whatsapp, $tel_mae_1, $tel_mae_2, $tel_pai_1, $tel_pai_2, $tel_outro]);
        $guardian_primary_email = $guardian_first([$email_mae, $email_pai, $email_outro]);
    } elseif ($principal_tipo === 'outro') {
        $guardian_primary_phone = $guardian_first([$tel_whatsapp, $tel_outro, $tel_pai_1, $tel_mae_1, $tel_pai_2, $tel_mae_2]);
        $guardian_primary_email = $guardian_first([$email_outro, $email_pai, $email_mae]);
    } else {
        $guardian_primary_phone = $guardian_first([$tel_whatsapp, $tel_pai_1, $tel_mae_1, $tel_pai_2, $tel_mae_2, $tel_outro]);
        $guardian_primary_email = $guardian_first([$email_pai, $email_mae, $email_outro]);
    }

    $consent_whatsapp = $cb_default('consent_whatsapp', 1);
    $consent_email    = $cb_default('consent_email', 1);
    $consent_sms      = $cb_default('consent_sms', 0);
    $consent_chamada  = $cb_default('consent_chamada', 1);
    $consentimento_em = current_time('mysql');
    if ($id_aluno > 0 && is_object($sige_guardian_audit_before)) {
        $consent_changed = false;
        foreach ([
            'consent_whatsapp' => $consent_whatsapp,
            'consent_email'    => $consent_email,
            'consent_sms'      => $consent_sms,
            'consent_chamada'  => $consent_chamada,
        ] as $__cg_col => $__cg_new) {
            $__cg_old = property_exists($sige_guardian_audit_before, $__cg_col) ? (int)$sige_guardian_audit_before->{$__cg_col} : (int)$__cg_new;
            if ($__cg_old !== (int)$__cg_new) { $consent_changed = true; break; }
        }
        if (!$consent_changed && property_exists($sige_guardian_audit_before, 'consentimento_comunicacao_em')) {
            $consentimento_em = (string)$sige_guardian_audit_before->consentimento_comunicacao_em;
        }
    }

    // ===== DADOS BASE (serão incluídos apenas se as colunas existirem) =====

    $dados_brutos = [

        'nome_completo'          => sanitize_text_field($_POST['nome'] ?? ''),

        'genero'                 => sanitize_text_field($_POST['genero'] ?? ''),

        'data_nascimento'        => sanitize_text_field($_POST['data_nascimento'] ?? ''),

        'nacionalidade'          => sanitize_text_field($_POST['nacionalidade'] ?? ''),

        'bairro'                 => sanitize_text_field($_POST['bairro'] ?? ''),

        'documento_numero'       => sanitize_text_field($_POST['documento_numero'] ?? ''),

        'documento_nr'           => sanitize_text_field($_POST['documento_numero'] ?? ''), // compatibilidade

        'tipo_documento'         => sanitize_text_field($_POST['tipo_documento'] ?? ''),

        'status'                 => (function_exists('sige_aluno_status_canonico') ? sige_aluno_status_canonico(sanitize_text_field($_POST['status'] ?? 'activo')) : sanitize_text_field($_POST['status'] ?? 'activo')),

        // Encarregados

        'nome_pai'               => sanitize_text_field($_POST['nome_pai'] ?? ''),

        'profissao_pai'          => sanitize_text_field($_POST['profissao_pai'] ?? ''),

        'telemovel_pai'          => $tel_pai_1,

        'telemovel_pai_2'        => $tel_pai_2,

        'email_pai'              => $email_pai,

        'nome_mae'               => sanitize_text_field($_POST['nome_mae'] ?? ''),

        'profissao_mae'          => sanitize_text_field($_POST['profissao_mae'] ?? ''),

        'telemovel_mae'          => $tel_mae_1,

        'telemovel_mae_2'        => $tel_mae_2,

        'email_mae'              => $email_mae,

        'nuit_encarregado'       => sanitize_text_field($_POST['nuit_encarregado'] ?? ''),

        // Gestão avançada de encarregados e consentimentos

        'encarregado_principal_tipo'       => $principal_tipo,

        'encarregado_principal_nome'       => sanitize_text_field($_POST['encarregado_principal_nome'] ?? ''),

        'encarregado_principal_parentesco' => sanitize_text_field($_POST['encarregado_principal_parentesco'] ?? ''),

        'encarregado_principal_telemovel'  => $tel_outro,

        'encarregado_principal_email'      => $email_outro,

        'canal_preferencial_comunicacao'   => $canal_preferencial,

        'consent_whatsapp'                 => $consent_whatsapp,

        'consent_email'                    => $consent_email,

        'consent_sms'                      => $consent_sms,

        'consent_chamada'                  => $consent_chamada,

        'contacto_alternativo_nome'        => sanitize_text_field($_POST['contacto_alternativo_nome'] ?? ''),

        'contacto_alternativo_parentesco'  => sanitize_text_field($_POST['contacto_alternativo_parentesco'] ?? ''),

        'contacto_alternativo_telemovel'   => $phone('contacto_alternativo_telemovel'),

        'autorizado_buscar_nome'           => sanitize_text_field($_POST['autorizado_buscar_nome'] ?? ''),

        'autorizado_buscar_parentesco'     => sanitize_text_field($_POST['autorizado_buscar_parentesco'] ?? ''),

        'autorizado_buscar_telemovel'      => $phone('autorizado_buscar_telemovel'),

        'autorizado_buscar_documento'      => sanitize_text_field($_POST['autorizado_buscar_documento'] ?? ''),

        'encarregado_observacoes'          => sanitize_textarea_field($_POST['encarregado_observacoes'] ?? ''),

        'consentimento_comunicacao_em'     => $consentimento_em,

        // WhatsApp (prioritário)

        'whatsapp_notificacoes'  => $guardian_primary_phone,

        'contacto_encarregado'   => $guardian_primary_phone,

        'email_encarregado'      => $guardian_primary_email,

        // Saúde

        'grupo_sanguineo'        => sanitize_text_field($_POST['grupo_sanguineo'] ?? ''),

        'hospital_preferencia'   => sanitize_text_field($_POST['hospital_preferencia'] ?? ''),

        'alergias'               => sanitize_textarea_field($_POST['alergias'] ?? ''),

        'condicoes_medicas'      => sanitize_textarea_field($_POST['condicoes_medicas'] ?? ''),

        'contacto_emergencia_1'  => sanitize_text_field($_POST['contacto_emergencia_1'] ?? ''),

        'contacto_emergencia_2'  => sanitize_text_field($_POST['contacto_emergencia_2'] ?? ''),

        // Arquivo Digital

        'doc_bi_url'             => function_exists('sige_uploads_privatize_doc_value') ? sige_uploads_privatize_doc_value(esc_url_raw($_POST['doc_bi_url'] ?? '')) : esc_url_raw($_POST['doc_bi_url'] ?? ''),

        'doc_cert_url'           => function_exists('sige_uploads_privatize_doc_value') ? sige_uploads_privatize_doc_value(esc_url_raw($_POST['doc_cert_url'] ?? '')) : esc_url_raw($_POST['doc_cert_url'] ?? ''),

        'doc_vacina_url'         => function_exists('sige_uploads_privatize_doc_value') ? sige_uploads_privatize_doc_value(esc_url_raw($_POST['doc_vacina_url'] ?? '')) : esc_url_raw($_POST['doc_vacina_url'] ?? ''),

        // Foto

        'foto'                   => esc_url_raw($_POST['foto_url'] ?? ''),

        // Descontos

        'tem_desconto_irmao'     => $cb('tem_desconto_irmao'),

        'tem_desconto_funcionario'=> $cb('tem_desconto_funcionario'),

        // Extras (Actividades & Alimentação)

        'tem_estudos'            => $cb('tem_estudos'),

        'tem_ingles'             => $cb('tem_ingles'),

        'tem_desporto'           => $cb('tem_desporto'),

        'tem_pequeno_almoco'     => $cb('tem_pequeno_almoco'),

        'tem_almoco'             => $cb('tem_almoco'),

        // Financeiro + Transporte

        'mensalidade_base'       => isset($_POST['mensalidade_base']) ? (float)$_POST['mensalidade_base'] : 0,

        'tem_irmao'              => $cb('tem_irmao'),

        'rota_transporte_id'     => isset($_POST['rota_transporte_id']) ? (int)$_POST['rota_transporte_id'] : 0,

        'regime_creche'          => sanitize_text_field($_POST['regime_creche'] ?? ''),

        // [v96] Casa Colorida: cobrança por ciclo de permanência
        'regime_mensalidade'     => in_array(($_POST['regime_mensalidade'] ?? ''), ['tempo_inteiro','meio_dia'], true) ? sanitize_text_field($_POST['regime_mensalidade']) : '',

    ];

    // Monta $dados_db só com colunas existentes (não quebra em BD antigas)

    $dados_db = [];

    foreach ($dados_brutos as $col => $val) {

        if (sige_db_column_exists($tbl_alunos, $col)) {

            $dados_db[$col] = $val;

        }

    }

    // ===== NUMERO DE PROCESSO (apenas se for NOVO e a coluna existir) =====

    if ($id_aluno === 0 && sige_db_column_exists($tbl_alunos, 'numero_processo')) {

        $max_proc = (int)$wpdb->get_var($wpdb->prepare("SELECT MAX(CAST(numero_processo AS UNSIGNED)) FROM {$tbl_alunos} WHERE escola_id = %d", sige_get_escola_id()));

        $novo_proc = str_pad((string)($max_proc + 1), 5, '0', STR_PAD_LEFT);

        $dados_db['numero_processo'] = $novo_proc;

    }

    // ===== INSERIR / ATUALIZAR =====

    if ($id_aluno > 0) {

        $ok = $wpdb->update($tbl_alunos, $dados_db, ['id' => $id_aluno, 'escola_id' => sige_get_escola_id()]);

        if ($ok === false) {

            wp_send_json_error('Erro ao actualizar aluno: ' . $wpdb->last_error);

        }

        $aluno_id = $id_aluno;

    } else {

        $dados_db['escola_id'] = sige_get_escola_id();

        $ok = $wpdb->insert($tbl_alunos, $dados_db);

        if ($ok === false) {

            wp_send_json_error('Erro ao criar aluno: ' . $wpdb->last_error);

        }

        $aluno_id = (int)$wpdb->insert_id;

    }

    // [v12.11.9.50] Auditoria própria da Gestão Avançada de Encarregados.
    // Regista apenas diferenças reais, com valores de contacto mascarados e hashes de integridade.
    if (function_exists('sige_guardian_adv_audit_log')) {
        $sige_guardian_audit_after = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$tbl_alunos} WHERE id = %d AND escola_id = %d LIMIT 1",
            $aluno_id,
            sige_get_escola_id()
        ));
        sige_guardian_adv_audit_log($aluno_id, $sige_guardian_audit_before, $sige_guardian_audit_after, [
            'evento' => ($id_aluno > 0 ? 'encarregados_actualizados' : 'encarregados_registados'),
            'origem' => 'cadastro_aluno',
        ]);
    }

    // ===== [v99] SYNC ACTIVIDADES EXTRAS (Casa Colorida) =====
    // Mantém a regra de negócio original: serviços extras escolhidos no registo do aluno
    // ficam gravados em sige_aluno_atividades_extras e depois alimentam pagamentos/gerador.
    $tbl_ativ_extras = $wpdb->prefix . 'sige_aluno_atividades_extras';
    $ativ_existe = ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tbl_ativ_extras)) === $tbl_ativ_extras);
    if ($ativ_existe) {
        $wpdb->update($tbl_ativ_extras,
            ['ativo' => 0],
            ['aluno_id' => $aluno_id, 'escola_id' => sige_get_escola_id()],
            ['%d'],
            ['%d', '%d']
        );

        $atividades_posted = isset($_POST['atividades_extras']) && is_array($_POST['atividades_extras'])
            ? array_values(array_unique(array_map('intval', $_POST['atividades_extras'])))
            : [];

        $user_id = get_current_user_id();
        foreach ($atividades_posted as $servico_id) {
            if ($servico_id <= 0) continue;
            $srv_ok = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}sige_fin_servicos
                 WHERE id=%d AND escola_id=%d AND tipo IN ('atividade_extra','livros') LIMIT 1",
                $servico_id, sige_get_escola_id()
            ));
            if (!$srv_ok) continue;

            $ja = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tbl_ativ_extras}
                 WHERE escola_id=%d AND aluno_id=%d AND servico_id=%d LIMIT 1",
                sige_get_escola_id(), $aluno_id, $servico_id
            ));
            if ($ja) {
                $wpdb->update($tbl_ativ_extras, ['ativo' => 1], ['id' => $ja], ['%d'], ['%d']);
            } else {
                $wpdb->insert($tbl_ativ_extras, [
                    'escola_id' => sige_get_escola_id(),
                    'aluno_id' => $aluno_id,
                    'servico_id' => $servico_id,
                    'ativo' => 1,
                    'data_inicio' => current_time('Y-m-d'),
                    'criado_em' => current_time('mysql'),
                    'criado_por' => $user_id,
                ]);
            }
        }
    }

    // ===== MATRÍCULA (UPSERT) =====

    // Confirma que a turma existe para o ano lectivo

    $turma_ok = (int)$wpdb->get_var($wpdb->prepare(

        "SELECT COUNT(*) FROM {$tbl_turmas} WHERE id=%d AND ano_lectivo=%d AND escola_id=%d",

        $turma_id, $ano_lectivo, sige_get_escola_id()

    ));

    if ($turma_ok <= 0) {

        wp_send_json_error('Turma não encontrada no ano lectivo actual.');

    }

    // Atualiza matrícula se já existir (aluno+ano), senão cria

    $matricula_id = (int)$wpdb->get_var($wpdb->prepare(

        "SELECT id FROM {$tbl_matriculas} WHERE aluno_id=%d AND ano_lectivo=%d AND escola_id=%d LIMIT 1",

        $aluno_id, $ano_lectivo, sige_get_escola_id()

    ));

    $dados_matricula = [

        'aluno_id'    => $aluno_id,

        'turma_id'    => $turma_id,

        'ano_lectivo' => $ano_lectivo

    ];

    // [v12.11.9.88.1] Sincronização aluno ↔ matrícula.
    // Quando um aluno desistente/transferido é reactivado no cadastro,
    // a matrícula do ano lectivo também precisa voltar a activa; caso
    // contrário, pagamentos e lançamentos continuam a ler o estado antigo.
    if (function_exists('sige_db_column_exists') && sige_db_column_exists($tbl_matriculas, 'status_matricula')) {
        $status_aluno_para_matricula = sanitize_text_field($_POST['status'] ?? ($dados_db['status'] ?? 'activo'));
        $dados_matricula['status_matricula'] = function_exists('sige_matricula_status_from_aluno_status')
            ? sige_matricula_status_from_aluno_status($status_aluno_para_matricula)
            : (function_exists('sige_status_operacional_activo') && sige_status_operacional_activo($status_aluno_para_matricula) ? 'activa' : sanitize_key($status_aluno_para_matricula));
    }

    if ($matricula_id > 0) {

        $okm = $wpdb->update($tbl_matriculas, $dados_matricula, ['id' => $matricula_id, 'escola_id' => sige_get_escola_id()]);

        if ($okm === false) {

            wp_send_json_error('Erro ao actualizar matrícula: ' . $wpdb->last_error);

        }

    } else {

        $dados_matricula['escola_id'] = sige_get_escola_id();

        $okm = $wpdb->insert($tbl_matriculas, $dados_matricula);

        if ($okm === false) {

            wp_send_json_error('Erro ao criar matrícula: ' . $wpdb->last_error);

        }

    }

    wp_send_json_success(['aluno_id' => $aluno_id]);

}

// 5.2 REMOVER ALUNO

add_action('wp_ajax_sige_remover_aluno', 'sige_ajax_remover_aluno');

function sige_ajax_remover_aluno() {
    if (!sige_tenant_write_guard((int) sige_get_escola_id(), 'sige_ajax_remover_aluno')) { wp_send_json_error('Contexto de escola invalido.'); }
    sige_check_nonce_global();

    if (!sige_ajax_user_can_permissions_or_caps(
        ['alunos.apagar'],
        ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
    )) {
        wp_send_json_error('Sem permissão para remover alunos.');
    }

    global $wpdb;

    $id = intval($_POST['id']);

    

    $wpdb->delete($wpdb->prefix.'sige_matriculas', ['aluno_id' => $id, 'escola_id' => sige_get_escola_id()]);

    $wpdb->delete($wpdb->prefix.'sige_alunos', ['id' => $id, 'escola_id' => sige_get_escola_id()]);

    

    wp_send_json_success();

    wp_die();

}

/**

 * ==================================================

 * 6. VALIDAÇÃO DE ACESSO (PORTARIA)

 * ==================================================

 */

if (!function_exists('sige_portaria_normalizar_situacao')) {
    /**
     * Normaliza a situação do aluno/matrícula para decisão operacional da Portaria.
     * Não altera dados na base de dados; apenas torna a validação robusta para
     * variações como activo/ativo, transferido/transferida e desistiu/desistente.
     */
    function sige_portaria_normalizar_situacao($situacao): string {
        $valor = trim(strtolower((string)$situacao));
        if ($valor === '') return '';
        if (function_exists('remove_accents')) {
            $valor = remove_accents($valor);
        } else {
            $valor = strtr($valor, [
                'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
                'é'=>'e','ê'=>'e','è'=>'e','ë'=>'e',
                'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
                'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
                'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u','ç'=>'c'
            ]);
        }
        $valor = str_replace(['_', '-'], ' ', $valor);
        $valor = preg_replace('/\s+/', ' ', $valor);
        return trim((string)$valor);
    }
}

if (!function_exists('sige_portaria_decisao_situacao')) {
    /**
     * Regra de portaria: só permite acesso para aluno/matrícula em situação activa.
     * Situações suspenso, transferido ou desistente bloqueiam sempre a entrada.
     */
    function sige_portaria_decisao_situacao($situacao, string $fonte = 'aluno'): array {
        $normalizada = sige_portaria_normalizar_situacao($situacao);

        if ($normalizada === '') {
            if ($fonte === 'matricula') {
                return [
                    'activo' => true,
                    'label' => 'Activo',
                    'codigo' => 'activo',
                    'fonte' => $fonte,
                    'nota' => '',
                    'acao' => 'Permitir entrada',
                ];
            }
            return [
                'activo' => false,
                'label' => 'Situação não definida',
                'codigo' => 'indefinido',
                'fonte' => $fonte,
                'nota' => 'Situação do aluno não definida. Não permitir entrada. Encaminhar à Secretaria.',
                'acao' => 'Bloquear entrada',
            ];
        }

        if (in_array($normalizada, ['activo','ativo','activa','ativa'], true)) {
            return [
                'activo' => true,
                'label' => 'Activo',
                'codigo' => 'activo',
                'fonte' => $fonte,
                'nota' => '',
                'acao' => 'Permitir entrada',
            ];
        }

        if (in_array($normalizada, ['suspenso','suspensa'], true)) {
            $label = 'Suspenso';
            $codigo = 'suspenso';
        } elseif (in_array($normalizada, ['transferido','transferida','transferido saida','transferida saida','transferido saída','transferida saída'], true)) {
            $label = 'Transferido';
            $codigo = 'transferido';
        } elseif (in_array($normalizada, ['desistente','desistiu','matricula cancelada','matricula cancelado'], true)) {
            $label = 'Desistente';
            $codigo = 'desistente';
        } elseif (in_array($normalizada, ['cancelado','cancelada'], true)) {
            $label = 'Cancelado';
            $codigo = 'cancelado';
        } elseif (in_array($normalizada, ['inactivo','inactiva','inativo','inativa'], true)) {
            $label = 'Inactivo';
            $codigo = 'inactivo';
        } else {
            $label = 'Não activo';
            $codigo = 'nao_activo';
        }

        $prefixo = ($fonte === 'matricula') ? 'Situação da matrícula' : 'Situação do aluno';
        return [
            'activo' => false,
            'label' => $label,
            'codigo' => $codigo,
            'fonte' => $fonte,
            'nota' => $prefixo . ': ' . $label . '. Não permitir entrada. Encaminhar à Secretaria.',
            'acao' => 'Bloquear entrada',
        ];
    }
}

add_action('wp_ajax_sige_validar_acesso', 'sige_ajax_validar_acesso');

function sige_ajax_validar_acesso() {
    sige_check_nonce_global();

    if (!sige_ajax_user_can_permissions_or_caps(
        ['portaria.validar_acesso'],
        ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda']
    )) {
        wp_send_json_error(['msg' => 'Sem permissão para usar a Portaria Digital.']);
    }

    global $wpdb;

    // v12.11.9.80 - Portaria só autoriza estudantes activos e expõe estado visual único para o frontend.
    // O crachá pode trazer URL/texto, mas a validação operacional usa apenas
    // o número de processo numérico do aluno.
    $codigo_lido = isset($_POST['qr_code']) ? sanitize_text_field(wp_unslash($_POST['qr_code'])) : '';
    $numero_processo = preg_replace('/[^0-9]/', '', $codigo_lido);
    $numero_processo = substr($numero_processo, 0, 20);

    if (empty($numero_processo)) {
        wp_send_json_error(['msg' => 'Código inválido ou vazio.']);
    }

    $escola_id = sige_require_escola_id('validar_acesso');
    $ano_lectivo = function_exists('sige_get_ano_lectivo_atual')
        ? (int)sige_get_ano_lectivo_atual()
        : (function_exists('sige_ano_lectivo_atual') ? (int)sige_ano_lectivo_atual() : (int)wp_date('Y'));

    $aluno = $wpdb->get_row($wpdb->prepare(
        "SELECT id, nome_completo, foto, status, numero_processo
           FROM {$wpdb->prefix}sige_alunos
          WHERE numero_processo = %s AND escola_id = %d
          LIMIT 1",
        $numero_processo,
        $escola_id
    ));

    if (!$aluno) {
        wp_send_json_error(['msg' => 'Aluno não encontrado.']);
    }

    $matricula = $wpdb->get_row($wpdb->prepare(
        "SELECT m.id,
                m.ano_lectivo,
                COALESCE(m.status_matricula, '') AS status_matricula,
                CONCAT(COALESCE(t.classe, ''), CASE WHEN t.nome IS NULL OR t.nome = '' THEN '' ELSE CONCAT(' - ', t.nome) END) AS turma
           FROM {$wpdb->prefix}sige_matriculas m
      LEFT JOIN {$wpdb->prefix}sige_turmas t
             ON t.id = m.turma_id
            AND t.escola_id = m.escola_id
          WHERE m.aluno_id = %d
            AND m.escola_id = %d
       ORDER BY CASE WHEN m.ano_lectivo = %d THEN 0 ELSE 1 END,
                m.ano_lectivo DESC,
                m.id DESC
          LIMIT 1",
        (int)$aluno->id,
        $escola_id,
        $ano_lectivo
    ));

    $turma = $matricula && trim((string)$matricula->turma) !== '' ? trim((string)$matricula->turma) : 'Sem turma';

    $decisao_aluno = sige_portaria_decisao_situacao($aluno->status ?? '', 'aluno');
    $decisao_matricula = sige_portaria_decisao_situacao($matricula->status_matricula ?? '', 'matricula');

    // Fonte da decisão de bloqueio: prioridade ao estado principal do aluno;
    // se ele estiver activo, a matrícula actual ainda pode bloquear a entrada.
    $decisao_final = $decisao_aluno['activo'] ? $decisao_matricula : $decisao_aluno;
    $acesso_permitido = !empty($decisao_aluno['activo']) && !empty($decisao_matricula['activo']);

    if ($acesso_permitido) {
        $mensagem = 'ENTRADA AUTORIZADA';
        $obs = '';
        $acao = 'Permitir entrada';
        $cor = '#4caf50';
        $som = 'success';
    } else {
        $mensagem = 'ACESSO BLOQUEADO';
        $obs = $decisao_final['nota'] ?: 'Situação não activa. Não permitir entrada. Encaminhar à Secretaria.';
        $acao = 'Bloquear entrada';
        $cor = '#ef4444';
        $som = 'error';
    }

    $resposta = [
        'permitido' => $acesso_permitido,
        'acesso_permitido' => $acesso_permitido,
        'bloqueado' => !$acesso_permitido,
        'nome' => wp_strip_all_tags($aluno->nome_completo),
        'foto' => esc_url_raw($aluno->foto ?: SIGE_URL . 'assets/img/avatar-default.svg'),
        'turma' => wp_strip_all_tags($turma ?: 'Sem turma'),
        'processo' => wp_strip_all_tags($aluno->numero_processo),
        'status' => $decisao_final['label'],
        'situacao' => $decisao_final['codigo'],
        'situacao_label' => $decisao_final['label'],
        'situacao_fonte' => $decisao_final['fonte'],
        'cor' => $cor,
        'som' => $som,
        'mensagem' => $mensagem,
        'obs' => $obs,
        'motivo_bloqueio' => $acesso_permitido ? '' : $obs,
        'acao' => $acao,
        'resultado' => $acesso_permitido ? 'autorizado' : 'bloqueado',
        'ui_estado' => $acesso_permitido ? 'autorizado' : 'bloqueado',
        'ui_selo' => $acesso_permitido ? 'AUTORIZADO' : 'BLOQUEADO',
        'ui_classe' => $acesso_permitido ? 'success' : 'error',
        'ui_cor' => $acesso_permitido ? '#16a34a' : '#ef4444',
        'ui_som' => $acesso_permitido ? 'success' : 'error',
    ];

    // Regista a tentativa para histórico da Portaria, inclusive bloqueios.
    $wpdb->insert($wpdb->prefix.'sige_acessos', [
        'escola_id' => $escola_id,
        'aluno_id' => (int)$aluno->id,
        'data_hora' => current_time('mysql'),
        'porteiro_id' => get_current_user_id(),
        'status_no_momento' => $decisao_final['codigo'] ?: ($acesso_permitido ? 'activo' : 'nao_activo'),
    ]);

    wp_send_json_success($resposta);
}

// ==================================================

// SIGE: Helpers de Encerramento de Ano Lectivo

// ==================================================

if (!function_exists('sige_ano_status_get')) {

    function sige_ano_status_get($ano) {

        global $wpdb;

        $t = $wpdb->prefix . 'sige_anos_lectivos';

        $status = $wpdb->get_var($wpdb->prepare(

            "SELECT status FROM {$t} WHERE ano_lectivo=%d AND escola_id=%d LIMIT 1",

            (int) $ano, sige_get_escola_id()

        ));

        return $status ? $status : 'aberto';

    }

}

// [DRY-S12] sige_is_ano_encerrado() - canónica em academic-logic.php

if (!function_exists('sige_ano_ensure_row')) {

    function sige_ano_ensure_row($ano) {

        global $wpdb;

        $t = $wpdb->prefix . 'sige_anos_lectivos';

        $wpdb->query($wpdb->prepare(

            "INSERT IGNORE INTO {$t} (ano_lectivo, escola_id, status) VALUES (%d, %d, 'aberto')",

            (int) $ano, sige_get_escola_id()

        ));

    }

}
