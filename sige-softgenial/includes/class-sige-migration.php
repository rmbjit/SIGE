<?php
/**
 * SIGE SoftGenial - Migração Centralizada
 * Ficheiro: includes/class-sige-migration.php
 *
 * Fonte única de verdade para TODA a estrutura de BD do plugin.
 * Cria/actualiza tabelas, índices, colunas e views.
 *
 * ┌────────────────────────────────────────────────────────────────────┐
 * │  REGRA DE OURO: qualquer alteração ao schema da BD passa por     │
 * │  aqui. ZERO dbDelta() ou ALTER TABLE noutros ficheiros.          │
 * └────────────────────────────────────────────────────────────────────┘
 *
 * Invocação:
 *   • register_activation_hook  → SIGE_Migration::activate()
 *   • admin_init                → SIGE_Migration::maybe_upgrade()
 *
 * @since  11.0
 * @author RMBJ Consultoria
 */

if (!defined('ABSPATH')) exit;

class SIGE_Migration {

    /**
     * Versão do schema.
     * Incrementar sempre que se adiciona/altera tabela ou coluna.
     * Formato: YYYYMMDD + sequencial (ex: 20260403.1)
     */
    const SCHEMA_VERSION = '20260621.1';

    /** Option key para controlo de versão */
    const OPTION_KEY = 'sige_schema_version';

    /** @var string Prefixo WP ($wpdb->prefix) */
    private string $p;

    /** @var string Charset collate */
    private string $cc;

    /** @var \wpdb */
    private \wpdb $db;

    // ========================================================================
    // ENTRY POINTS (estáticos)
    // ========================================================================

    /**
     * Hook de activação do plugin.
     * Corre uma única vez ao activar; cria TUDO do zero se necessário.
     */
    public static function activate(): void {
        $m = new self();
        $m->run_full();
    }

    /**
     * admin_init - corre em cada page load do admin.
     * Verifica versão; se desactualizada, faz upgrade.
     */
    public static function maybe_upgrade(): void {
        $current = get_option(self::OPTION_KEY, '0');
        if (version_compare($current, self::SCHEMA_VERSION, '>=')) {
            return;
        }
        // Só admins correm migrações
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) {
            return;
        }
        $m = new self();
        $m->run_full();
    }

    // ========================================================================
    // CONSTRUTOR
    // ========================================================================

    private function __construct() {
        global $wpdb;
        $this->db = $wpdb;
        $this->p  = $wpdb->prefix;
        $this->cc = $wpdb->get_charset_collate();
    }

    // ========================================================================
    // EXECUÇÃO PRINCIPAL
    // ========================================================================

    private function run_full(): void {
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // 1. Criar / actualizar tabelas (dbDelta)
        $this->create_tables();

        // 2. Migrações incrementais (ALTER TABLE, ADD COLUMN, etc.)
        $this->run_migrations();

        // 3. Views
        $this->create_views();

        // 4. Seed data
        $this->seed_defaults();

        // 5. Guardar versão
        update_option(self::OPTION_KEY, self::SCHEMA_VERSION);

        // 6. Limpar options antigas que já não são necessárias
        // (mantêm-se para não quebrar rollback, mas não são consultadas)
    }

    // ========================================================================
    // CRIAÇÃO DE TABELAS (dbDelta)
    // ========================================================================
    //
    // Cada tabela corresponde ao schema REAL de produção (Colégio Malisa,
    // dump de 30 Março 2026) com correcções aplicadas na sessão de 3 Abril.
    //
    // REGRAS dbDelta:
    //   • Uma coluna por linha
    //   • Sem linhas em branco dentro do CREATE TABLE
    //   • PRIMARY KEY com dois espaços antes
    //   • KEY/UNIQUE KEY com dois espaços antes
    //   • Sem backticks (dbDelta não os suporta bem)
    // ========================================================================

    private function create_tables(): void {
        $p  = $this->p;
        $cc = $this->cc;

        $sqls = [];

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO SISTEMA
        // ─────────────────────────────────────────────────────────────────────

        // 1. sige_escolas - Multi-tenancy master
        $sqls[] = "CREATE TABLE {$p}sige_escolas (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            nome VARCHAR(255) NOT NULL,
            slug VARCHAR(100) NOT NULL,
            subdominio VARCHAR(100) DEFAULT NULL,
            nif VARCHAR(20) DEFAULT NULL,
            email VARCHAR(255) DEFAULT NULL,
            telefone VARCHAR(20) DEFAULT NULL,
            endereco TEXT DEFAULT NULL,
            logo_url VARCHAR(500) DEFAULT NULL,
            plano ENUM('tesouraria','completo','institucional') NOT NULL DEFAULT 'completo',
            limite_alunos INT(11) DEFAULT 500,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            trial_ate DATE DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            UNIQUE KEY subdominio (subdominio)
        ) {$cc};";

        // 2. sige_config - Configuração geral da escola
        $sqls[] = "CREATE TABLE {$p}sige_config (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            nome_escola VARCHAR(200) DEFAULT NULL,
            codigo_escola VARCHAR(255) DEFAULT NULL,
            tipo_instituicao VARCHAR(50) DEFAULT NULL,
            ensino_oferecido VARCHAR(255) DEFAULT NULL,
            provincia VARCHAR(50) DEFAULT NULL,
            distrito VARCHAR(50) DEFAULT NULL,
            ano_fundacao VARCHAR(10) DEFAULT NULL,
            endereco_escola TEXT DEFAULT NULL,
            entidade_proprietaria VARCHAR(255) DEFAULT NULL,
            director_nome VARCHAR(255) DEFAULT NULL,
            cargo_direcao VARCHAR(255) DEFAULT NULL,
            sistema_avaliacao VARCHAR(50) DEFAULT NULL,
            ano_lectivo INT(4) DEFAULT NULL,
            nota_minima FLOAT DEFAULT 10,
            nota_maxima FLOAT DEFAULT 20,
            telefone_oficial VARCHAR(30) DEFAULT NULL,
            pais VARCHAR(50) DEFAULT 'Moçambique',
            cidade VARCHAR(100) DEFAULT NULL,
            email_institucional VARCHAR(100) DEFAULT NULL,
            logo_sistema_url VARCHAR(500) DEFAULT NULL,
            logo_documentos_url VARCHAR(500) DEFAULT NULL,
            cor_primaria VARCHAR(20) DEFAULT NULL,
            moeda VARCHAR(10) DEFAULT 'MZN',
            assinatura_director_url VARCHAR(255) DEFAULT NULL,
            rodape_documentos TEXT DEFAULT NULL,
            prazo_pagamento_dias INT(2) DEFAULT 10,
            prefixo_matricula VARCHAR(10) DEFAULT 'MAT',
            nuit VARCHAR(50) DEFAULT NULL,
            prazo_vencimento INT(3) DEFAULT NULL,
            multa_atraso_percentual FLOAT DEFAULT 0,
            dias_para_bloqueio INT(3) DEFAULT 30,
            desconto_irmaos FLOAT DEFAULT 0,
            taxa_inscricao DECIMAL(10,2) DEFAULT 0.00,
            modulos_ativos TEXT DEFAULT NULL,
            sms_api_key VARCHAR(255) DEFAULT NULL,
            sms_sender_id VARCHAR(20) DEFAULT NULL,
            template_boas_vindas TEXT DEFAULT NULL,
            template_atraso_pagamento TEXT DEFAULT NULL,
            template_falta_aluno TEXT DEFAULT NULL,
            template_nota_lancada TEXT DEFAULT NULL,
            tabela_precos LONGTEXT DEFAULT NULL,
            data_inicio_t1 DATE DEFAULT NULL,
            data_fim_t1 DATE DEFAULT NULL,
            data_inicio_t2 DATE DEFAULT NULL,
            data_fim_t2 DATE DEFAULT NULL,
            data_inicio_t3 DATE DEFAULT NULL,
            data_fim_t3 DATE DEFAULT NULL,
            tipo_multa VARCHAR(20) DEFAULT 'percentual',
            desconto_pronto_pagamento FLOAT DEFAULT 0,
            desconto_funcionario FLOAT DEFAULT 0,
            ativar_sms_automatico TINYINT(1) DEFAULT 0,
            escola_tem_areas TINYINT(1) DEFAULT 0,
            turnos_config LONGTEXT DEFAULT NULL,
            feriados_escolares LONGTEXT DEFAULT NULL,
            cabecalho_oficial_url VARCHAR(255) DEFAULT NULL,
            escalas_qualitativas LONGTEXT DEFAULT NULL,
            tipos_avaliacao LONGTEXT DEFAULT NULL,
            formato_recibo VARCHAR(20) DEFAULT 'A4',
            regra_arredondamento VARCHAR(20) DEFAULT 'matematico',
            dados_bancarios TEXT DEFAULT NULL,
            whatsapp_url VARCHAR(500) DEFAULT NULL,
            whatsapp_token VARCHAR(500) DEFAULT NULL,
            whatsapp_destinatarios_padrao VARCHAR(40) NOT NULL DEFAULT 'todos',
            msg_nova_fatura TEXT DEFAULT NULL,
            msg_recibo_pago TEXT DEFAULT NULL,
            msg_cobranca TEXT DEFAULT NULL,
            endereco TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 3. sige_anos_lectivos
        $sqls[] = "CREATE TABLE {$p}sige_anos_lectivos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            ano_lectivo INT(4) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'aberto',
            encerrado_em DATETIME DEFAULT NULL,
            encerrado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_escola_ano_lectivo (escola_id, ano_lectivo),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 4. sige_logs_auditoria - Log principal activo
        $sqls[] = "CREATE TABLE {$p}sige_logs_auditoria (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            user_display VARCHAR(120) DEFAULT NULL,
            modulo VARCHAR(30) NOT NULL DEFAULT 'financeiro',
            acao VARCHAR(100) NOT NULL,
            detalhes LONGTEXT DEFAULT NULL,
            ip_address VARCHAR(45) DEFAULT '',
            data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY modulo (modulo),
            KEY acao (acao),
            KEY user_id (user_id),
            KEY data_hora (data_hora),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 5. sige_audit_log - Tabela legacy (mantida para compatibilidade)
        $sqls[] = "CREATE TABLE {$p}sige_audit_log (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            accao VARCHAR(60) NOT NULL,
            entidade VARCHAR(60) NOT NULL DEFAULT '',
            entidade_id BIGINT(20) NOT NULL DEFAULT 0,
            detalhe TEXT DEFAULT NULL,
            user_id BIGINT(20) NOT NULL DEFAULT 0,
            user_nome VARCHAR(120) NOT NULL DEFAULT '',
            ip VARCHAR(45) NOT NULL DEFAULT '',
            criado_em DATETIME NOT NULL,
            PRIMARY KEY  (id),
            KEY accao (accao),
            KEY user_id (user_id),
            KEY criado_em (criado_em),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO SECRETARIA / ACADÉMICO
        // ─────────────────────────────────────────────────────────────────────

        // 6. sige_alunos
        $sqls[] = "CREATE TABLE {$p}sige_alunos (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            numero_processo VARCHAR(50) DEFAULT NULL,
            nome_completo VARCHAR(255) NOT NULL,
            data_nascimento DATE NOT NULL,
            genero CHAR(1) NOT NULL,
            classe_atual INT(2) DEFAULT 0,
            naturalidade VARCHAR(100) DEFAULT NULL,
            distrito VARCHAR(100) DEFAULT NULL,
            provincia VARCHAR(100) DEFAULT NULL,
            documento_nr VARCHAR(50) DEFAULT NULL,
            tipo_documento VARCHAR(50) DEFAULT NULL,
            nome_pai VARCHAR(255) DEFAULT NULL,
            nome_mae VARCHAR(255) DEFAULT NULL,
            contacto_encarregado VARCHAR(50) DEFAULT NULL,
            profissao_pai VARCHAR(255) DEFAULT NULL,
            profissao_mae VARCHAR(255) DEFAULT NULL,
            whatsapp_notificacoes VARCHAR(50) DEFAULT NULL,
            email_encarregado VARCHAR(100) DEFAULT NULL,
            morada TEXT DEFAULT NULL,
            tem_irmaos TINYINT(1) DEFAULT 0,
            familia_id BIGINT(20) DEFAULT NULL,
            status_exame_6a TINYINT(1) DEFAULT 0,
            data_registo DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) DEFAULT 'activo',
            nuit_encarregado VARCHAR(50) DEFAULT NULL,
            tem_desconto_irmao TINYINT(1) DEFAULT 0,
            observacoes TEXT DEFAULT NULL,
            nacionalidade VARCHAR(100) DEFAULT NULL,
            bairro VARCHAR(255) DEFAULT NULL,
            telemovel_pai VARCHAR(50) DEFAULT NULL,
            telemovel_pai_2 VARCHAR(50) DEFAULT NULL,
            email_pai VARCHAR(100) DEFAULT NULL,
            telemovel_mae VARCHAR(50) DEFAULT NULL,
            telemovel_mae_2 VARCHAR(50) DEFAULT NULL,
            email_mae VARCHAR(100) DEFAULT NULL,
            encarregado_principal_tipo VARCHAR(40) DEFAULT 'pai_mae',
            encarregado_principal_nome VARCHAR(255) DEFAULT NULL,
            encarregado_principal_parentesco VARCHAR(80) DEFAULT NULL,
            encarregado_principal_telemovel VARCHAR(50) DEFAULT NULL,
            encarregado_principal_email VARCHAR(120) DEFAULT NULL,
            canal_preferencial_comunicacao VARCHAR(30) DEFAULT 'whatsapp',
            consent_whatsapp TINYINT(1) DEFAULT 1,
            consent_email TINYINT(1) DEFAULT 1,
            consent_sms TINYINT(1) DEFAULT 0,
            consent_chamada TINYINT(1) DEFAULT 1,
            contacto_alternativo_nome VARCHAR(255) DEFAULT NULL,
            contacto_alternativo_parentesco VARCHAR(80) DEFAULT NULL,
            contacto_alternativo_telemovel VARCHAR(50) DEFAULT NULL,
            autorizado_buscar_nome VARCHAR(255) DEFAULT NULL,
            autorizado_buscar_parentesco VARCHAR(80) DEFAULT NULL,
            autorizado_buscar_telemovel VARCHAR(50) DEFAULT NULL,
            autorizado_buscar_documento VARCHAR(80) DEFAULT NULL,
            contacto_emergencia_1 VARCHAR(255) DEFAULT NULL,
            contacto_emergencia_2 VARCHAR(255) DEFAULT NULL,
            encarregado_observacoes TEXT DEFAULT NULL,
            consentimento_comunicacao_em DATETIME DEFAULT NULL,
            foto VARCHAR(255) DEFAULT NULL,
            doc_bi_url VARCHAR(255) DEFAULT NULL,
            doc_cert_url VARCHAR(255) DEFAULT NULL,
            doc_vacina_url VARCHAR(255) DEFAULT NULL,
            grupo_sanguineo VARCHAR(10) DEFAULT NULL,
            alergias TEXT DEFAULT NULL,
            condicoes_medicas TEXT DEFAULT NULL,
            hospital_preferencia VARCHAR(255) DEFAULT NULL,
            tem_desconto_funcionario TINYINT(1) DEFAULT 0,
            rota_transporte_id INT(11) DEFAULT 0,
            mensalidade_base DECIMAL(10,2) DEFAULT 0.00,
            tem_irmao TINYINT(1) DEFAULT 0,
            tem_estudos TINYINT(1) DEFAULT 0,
            tem_ingles TINYINT(1) DEFAULT 0,
            tem_desporto TINYINT(1) DEFAULT 0,
            tem_almoco TINYINT(1) DEFAULT 0,
            tem_pequeno_almoco TINYINT(1) DEFAULT 0,
            regime_creche VARCHAR(20) DEFAULT NULL,
            importacao_lote_id VARCHAR(100) DEFAULT NULL,
            importado_em DATETIME DEFAULT NULL,
            importado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_processo (escola_id, numero_processo),
            KEY idx_familia (familia_id),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id),
            KEY idx_importacao_lote (escola_id, importacao_lote_id),
            KEY idx_guardian_principal (escola_id, encarregado_principal_tipo)
        ) {$cc};";

        // 6.1. sige_agregados_familiares - agrupamento seguro de irmãos/famílias
        $sqls[] = "CREATE TABLE {$p}sige_agregados_familiares (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            telefone_chave VARCHAR(50) DEFAULT NULL,
            nome_agregado VARCHAR(255) DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_escola_id (escola_id),
            KEY idx_telefone_chave (telefone_chave)
        ) {$cc};";

        // 7. sige_turmas
        $sqls[] = "CREATE TABLE {$p}sige_turmas (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            nome_turma VARCHAR(100) NOT NULL,
            sala_fisica VARCHAR(100) DEFAULT NULL,
            nivel_ensino VARCHAR(50) NOT NULL,
            classe VARCHAR(50) DEFAULT NULL,
            turno VARCHAR(50) DEFAULT NULL,
            sala VARCHAR(50) DEFAULT 'Pendente',
            ano_lectivo INT(4) DEFAULT NULL,
            capacidade_max INT(3) DEFAULT NULL,
            nome VARCHAR(100) NOT NULL,
            capacidade INT(3) DEFAULT NULL,
            status_turma VARCHAR(20) DEFAULT 'activa',
            director_turma_id BIGINT(20) DEFAULT NULL,
            horario_json LONGTEXT DEFAULT NULL,
            is_preescolar TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_escola_id (escola_id),
            KEY idx_ano (ano_lectivo)
        ) {$cc};";

        // 8. sige_turma_alunos
        $sqls[] = "CREATE TABLE {$p}sige_turma_alunos (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            turma_id BIGINT(20) NOT NULL,
            aluno_id BIGINT(20) NOT NULL,
            data_alocacao TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_turma (turma_id),
            KEY idx_aluno (aluno_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 9. sige_matriculas
        $sqls[] = "CREATE TABLE {$p}sige_matriculas (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            turma_id BIGINT(20) NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            data_matricula DATETIME DEFAULT CURRENT_TIMESTAMP,
            status_matricula VARCHAR(20) DEFAULT 'activa',
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            importacao_lote_id VARCHAR(100) DEFAULT NULL,
            importado_em DATETIME DEFAULT NULL,
            importado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (aluno_id),
            KEY idx_turma (turma_id),
            KEY idx_ano (ano_lectivo),
            KEY idx_escola_id (escola_id),
            KEY idx_importacao_lote (escola_id, importacao_lote_id)
        ) {$cc};";

        // 9.1. sige_alunos_importacao_lotes - histórico persistente de importações CSV
        $sqls[] = "CREATE TABLE {$p}sige_alunos_importacao_lotes (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            lote_id VARCHAR(100) NOT NULL,
            ano_lectivo INT(4) DEFAULT NULL,
            turma_id BIGINT(20) DEFAULT NULL,
            turma_nome VARCHAR(255) DEFAULT NULL,
            ficheiro VARCHAR(255) DEFAULT NULL,
            total_linhas INT(11) DEFAULT 0,
            importaveis INT(11) DEFAULT 0,
            importados INT(11) DEFAULT 0,
            pendentes INT(11) DEFAULT 0,
            erros INT(11) DEFAULT 0,
            duplicados INT(11) DEFAULT 0,
            estado VARCHAR(30) NOT NULL DEFAULT 'activo',
            resumo_json LONGTEXT DEFAULT NULL,
            detalhes_json LONGTEXT DEFAULT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            anulado_em DATETIME DEFAULT NULL,
            anulado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            bloqueado_em DATETIME DEFAULT NULL,
            ultimo_bloqueio_json LONGTEXT DEFAULT NULL,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_lote_escola (escola_id, lote_id),
            KEY idx_escola_estado (escola_id, estado),
            KEY idx_escola_criado (escola_id, criado_em),
            KEY idx_turma_ano (turma_id, ano_lectivo)
        ) {$cc};";

        // 9.2. sige_alunos_encarregados_historico - auditoria própria de alterações sensíveis dos encarregados
        $sqls[] = "CREATE TABLE {$p}sige_alunos_encarregados_historico (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            evento VARCHAR(80) NOT NULL DEFAULT 'encarregados_actualizados',
            origem VARCHAR(60) NOT NULL DEFAULT 'cadastro_aluno',
            campos_alterados TEXT DEFAULT NULL,
            total_alteracoes INT(11) NOT NULL DEFAULT 0,
            resumo VARCHAR(255) DEFAULT NULL,
            alteracoes_json LONGTEXT DEFAULT NULL,
            ip_hash VARCHAR(128) DEFAULT NULL,
            user_agent_hash VARCHAR(128) DEFAULT NULL,
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            user_display VARCHAR(160) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_escola_aluno (escola_id, aluno_id),
            KEY idx_escola_criado (escola_id, criado_em),
            KEY idx_aluno_criado (aluno_id, criado_em),
            KEY idx_evento (evento)
        ) {$cc};";

        // 10. sige_disciplinas
        $sqls[] = "CREATE TABLE {$p}sige_disciplinas (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            nome VARCHAR(255) NOT NULL,
            sigla VARCHAR(20) DEFAULT NULL,
            carga_horaria INT(11) DEFAULT 1,
            nome_disciplina VARCHAR(100) NOT NULL,
            e_tronco_comum TINYINT(1) DEFAULT 1,
            ciclo_escolar VARCHAR(50) NOT NULL,
            ciclos TEXT DEFAULT NULL,
            area_esg2 VARCHAR(20) DEFAULT 'comum',
            categoria VARCHAR(20) DEFAULT 'nuclear',
            chefe_grupo_id INT(11) DEFAULT NULL,
            ordem INT(11) DEFAULT 0,
            activo TINYINT(1) DEFAULT 1,
            PRIMARY KEY  (id),
            KEY idx_activo (activo),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 11. sige_disciplinas_indicadores
        $sqls[] = "CREATE TABLE {$p}sige_disciplinas_indicadores (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            disciplina_id BIGINT(20) NOT NULL,
            criterio TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_disc (disciplina_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 12. sige_matriz_curricular
        $sqls[] = "CREATE TABLE {$p}sige_matriz_curricular (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            classe VARCHAR(50) NOT NULL,
            disciplina_id BIGINT(20) NOT NULL,
            carga_horaria INT(3) DEFAULT NULL,
            ordem_pauta INT(3) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_classe (classe),
            KEY idx_disc (disciplina_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 13. sige_turma_disciplinas
        $sqls[] = "CREATE TABLE {$p}sige_turma_disciplinas (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            turma_id BIGINT(20) NOT NULL,
            disciplina_id BIGINT(20) NOT NULL,
            professor_id BIGINT(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_turma (turma_id),
            KEY idx_disc (disciplina_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 14. sige_carga_horaria
        $sqls[] = "CREATE TABLE {$p}sige_carga_horaria (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            professor_id BIGINT(20) NOT NULL,
            turma_id BIGINT(20) NOT NULL,
            disciplina_id BIGINT(20) NOT NULL,
            ano_lectivo INT(4) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY professor_id (professor_id),
            KEY turma_id (turma_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 15. sige_horarios_turma
        $sqls[] = "CREATE TABLE {$p}sige_horarios_turma (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            ano_lectivo INT(4) NOT NULL,
            turma_id BIGINT(20) UNSIGNED NOT NULL,
            dia TINYINT(1) NOT NULL,
            hora_inicio TIME NOT NULL,
            hora_fim TIME NOT NULL,
            disciplina_id BIGINT(20) UNSIGNED DEFAULT NULL,
            professor_id BIGINT(20) UNSIGNED DEFAULT NULL,
            sala VARCHAR(120) DEFAULT NULL,
            observacao VARCHAR(255) DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_turma_dia (turma_id, dia),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 16. sige_notas
        $sqls[] = "CREATE TABLE {$p}sige_notas (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            disciplina_id BIGINT(20) NOT NULL,
            trimestre INT(1) NOT NULL,
            nota_ac DECIMAL(4,2) DEFAULT NULL,
            nota_acp DECIMAL(4,2) DEFAULT NULL,
            nota_at DECIMAL(4,2) DEFAULT NULL,
            nota_exame DECIMAL(4,2) DEFAULT NULL,
            nota_conselho DECIMAL(4,2) DEFAULT NULL,
            nota_conselho_acta_id BIGINT(20) UNSIGNED DEFAULT NULL,
            nota_conselho_nv_id BIGINT(20) UNSIGNED DEFAULT NULL,
            nota_conselho_aprovado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            nota_conselho_aprovado_em DATETIME DEFAULT NULL,
            nota_conselho_motivo TEXT DEFAULT NULL,
            ano_lectivo INT(4) NOT NULL,
            turma_id BIGINT(20) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'aprovado',
            submetido_por BIGINT(20) DEFAULT NULL,
            submetido_em DATETIME DEFAULT NULL,
            aprovado_por BIGINT(20) DEFAULT NULL,
            aprovado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (aluno_id),
            KEY idx_disc (disciplina_id),
            KEY idx_turma (turma_id),
            KEY idx_ano_trim (ano_lectivo, trimestre),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 17. sige_regras_academicas
        $sqls[] = "CREATE TABLE {$p}sige_regras_academicas (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            ano_lectivo INT(4) NOT NULL,
            classe VARCHAR(20) NOT NULL,
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
            updated_at DATETIME DEFAULT NULL,
            classe_num INT(2) NOT NULL,
            classe_label VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_ano (ano_lectivo),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 18. sige_escala_qualitativa
        $sqls[] = "CREATE TABLE {$p}sige_escala_qualitativa (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            ano_lectivo VARCHAR(9) NOT NULL,
            min_val TINYINT(3) UNSIGNED NOT NULL,
            max_val TINYINT(3) UNSIGNED NOT NULL,
            label VARCHAR(10) NOT NULL,
            ordem TINYINT(3) UNSIGNED NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY idx_ano (ano_lectivo),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO RH
        // ─────────────────────────────────────────────────────────────────────

        // 19. sige_professores
        $sqls[] = "CREATE TABLE {$p}sige_professores (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            nome_completo VARCHAR(255) NOT NULL,
            nuit VARCHAR(20) DEFAULT NULL,
            telemovel VARCHAR(20) DEFAULT NULL,
            email VARCHAR(100) DEFAULT NULL,
            formacao_academica VARCHAR(255) DEFAULT NULL,
            nivel_carreira VARCHAR(20) DEFAULT NULL,
            regime_trabalho VARCHAR(50) DEFAULT NULL,
            data_admissao DATE DEFAULT NULL,
            status_ativo TINYINT(1) DEFAULT 1,
            observacoes TEXT DEFAULT NULL,
            tipo_contrato VARCHAR(50) DEFAULT NULL,
            fim_contrato DATE DEFAULT NULL,
            dados_bancarios LONGTEXT DEFAULT NULL,
            documentos_urls LONGTEXT DEFAULT NULL,
            salario_base DECIMAL(10,2) DEFAULT NULL,
            subsidio DECIMAL(10,2) DEFAULT NULL,
            foto_perfil VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_escola_id (escola_id),
            UNIQUE KEY uniq_prof_escola_email (escola_id, email)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO PORTARIA
        // ─────────────────────────────────────────────────────────────────────

        // 20. sige_acessos
        $sqls[] = "CREATE TABLE {$p}sige_acessos (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
            tipo ENUM('entrada','saida') DEFAULT 'entrada',
            porteiro_id BIGINT(20) DEFAULT NULL,
            status_no_momento VARCHAR(20) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (aluno_id),
            KEY idx_data (data_hora),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 20.1 sige_presencas_excecoes (v12.11.9.91)
        // Excepções à derivação de presenças a partir de sige_acessos:
        // justificações e marcações manuais. O log da Portaria é imutável;
        // a correcção humana vive aqui e VENCE a derivação.
        $sqls[] = "CREATE TABLE {$p}sige_presencas_excecoes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            data DATE NOT NULL,
            estado VARCHAR(30) NOT NULL,
            motivo VARCHAR(255) DEFAULT NULL,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            criado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_escola_aluno_data (escola_id, aluno_id, data),
            KEY idx_escola_data (escola_id, data)
        ) {$cc};";

        // 20.2 sige_mpesa_transacoes (v12.11.9.91)
        // Transacções recebidas via webhook M-Pesa/e-Mola e o seu estado de
        // conciliação. A conciliação NUNCA escreve nas tabelas financeiras
        // directamente: chama sige_fin_registar_pagamento (regra canónica).
        $sqls[] = "CREATE TABLE {$p}sige_mpesa_transacoes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            provider VARCHAR(20) NOT NULL DEFAULT 'mpesa',
            referencia_mpesa VARCHAR(80) NOT NULL,
            referencia_cliente VARCHAR(80) DEFAULT NULL,
            msisdn VARCHAR(30) DEFAULT NULL,
            valor DECIMAL(12,2) NOT NULL DEFAULT 0,
            moeda VARCHAR(8) NOT NULL DEFAULT 'MZN',
            estado VARCHAR(30) NOT NULL DEFAULT 'recebida',
            aluno_id BIGINT(20) UNSIGNED DEFAULT NULL,
            lancamento_id BIGINT(20) UNSIGNED DEFAULT NULL,
            pagamento_id BIGINT(20) UNSIGNED DEFAULT NULL,
            erro VARCHAR(255) DEFAULT NULL,
            payload_json LONGTEXT,
            criado_em DATETIME DEFAULT NULL,
            conciliado_em DATETIME DEFAULT NULL,
            conciliado_por VARCHAR(60) DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_escola_ref (escola_id, referencia_mpesa),
            KEY idx_escola_estado (escola_id, estado),
            KEY idx_aluno (aluno_id)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO JARDIM DE INFÂNCIA
        // ─────────────────────────────────────────────────────────────────────

        // 21. sige_jardim_criterios
        $sqls[] = "CREATE TABLE {$p}sige_jardim_criterios (
            id INT(11) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            disciplina_id BIGINT(20) NOT NULL,
            classe VARCHAR(50) DEFAULT NULL,
            ano_lectivo INT(4) DEFAULT NULL,
            trimestre TINYINT(1) DEFAULT NULL,
            descricao TEXT NOT NULL,
            ordem TINYINT(4) NOT NULL DEFAULT 0,
            ativo TINYINT(1) NOT NULL DEFAULT 1,
            versao INT(4) NOT NULL DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_disc (disciplina_id),
            KEY idx_escola_id (escola_id),
            KEY idx_jcrit_scope_v105 (escola_id, disciplina_id, ano_lectivo, trimestre, ativo)
        ) {$cc};";

        // 22. sige_jardim_criterios_respostas
        $sqls[] = "CREATE TABLE {$p}sige_jardim_criterios_respostas (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            turma_id BIGINT(20) DEFAULT NULL,
            criterio_id INT(11) NOT NULL,
            trimestre TINYINT(1) NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'nao_avaliado',
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_jresp_scope_v105 (escola_id, aluno_id, turma_id, criterio_id, trimestre, ano_lectivo),
            KEY idx_aluno (aluno_id),
            KEY idx_criterio (criterio_id),
            KEY idx_escola_id (escola_id),
            KEY idx_jresp_turma_v105 (escola_id, turma_id, ano_lectivo, trimestre)
        ) {$cc};";

        // 23. sige_jardim_avaliacoes
        $sqls[] = "CREATE TABLE {$p}sige_jardim_avaliacoes (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            turma_id BIGINT(20) DEFAULT NULL,
            disciplina_id BIGINT(20) NOT NULL,
            trimestre TINYINT(1) NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            nivel VARCHAR(5) NOT NULL,
            observacao TEXT DEFAULT NULL,
            registado_por BIGINT(20) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            comentario_trimestre TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_javal_scope_v105 (escola_id, aluno_id, turma_id, disciplina_id, trimestre, ano_lectivo),
            KEY idx_aluno (aluno_id),
            KEY idx_escola_id (escola_id),
            KEY idx_javal_turma_v105 (escola_id, turma_id, ano_lectivo, trimestre)
        ) {$cc};";

        // 24. sige_jardim_diario
        $sqls[] = "CREATE TABLE {$p}sige_jardim_diario (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            turma_id BIGINT(20) DEFAULT NULL,
            data_registo DATE NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            actividades TEXT DEFAULT NULL,
            humor VARCHAR(20) DEFAULT NULL,
            comportamento VARCHAR(20) DEFAULT NULL,
            obs_comportamento TEXT DEFAULT NULL,
            dormiu TINYINT(1) DEFAULT NULL,
            minutos_sono SMALLINT(6) DEFAULT NULL,
            obs_sono VARCHAR(255) DEFAULT NULL,
            recado_pais TEXT DEFAULT NULL,
            registado_por BIGINT(20) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_jdiario_scope_v105 (escola_id, aluno_id, turma_id, data_registo, ano_lectivo),
            KEY idx_aluno (aluno_id),
            KEY idx_data (data_registo),
            KEY idx_escola_id (escola_id),
            KEY idx_jdiario_turma_v105 (escola_id, turma_id, data_registo, ano_lectivo)
        ) {$cc};";

        // 25. sige_jardim_saude
        $sqls[] = "CREATE TABLE {$p}sige_jardim_saude (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) NOT NULL,
            turma_id BIGINT(20) DEFAULT NULL,
            data_registo DATE NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            pequeno_almoco VARCHAR(20) DEFAULT NULL,
            almoco VARCHAR(20) DEFAULT NULL,
            lanche VARCHAR(20) DEFAULT NULL,
            obs_alimentacao TEXT DEFAULT NULL,
            febre TINYINT(1) DEFAULT 0,
            temperatura DECIMAL(4,1) DEFAULT NULL,
            queda_acidente TINYINT(1) DEFAULT 0,
            desc_ocorrencia TEXT DEFAULT NULL,
            medicamento_dado VARCHAR(255) DEFAULT NULL,
            registado_por BIGINT(20) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_jsaude_scope_v105 (escola_id, aluno_id, turma_id, data_registo, ano_lectivo),
            KEY idx_aluno (aluno_id),
            KEY idx_data (data_registo),
            KEY idx_escola_id (escola_id),
            KEY idx_jsaude_turma_v105 (escola_id, turma_id, data_registo, ano_lectivo)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO TRANSPORTE
        // ─────────────────────────────────────────────────────────────────────

        // 26. sige_transporte_rotas
        $sqls[] = "CREATE TABLE {$p}sige_transporte_rotas (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            nome_rota VARCHAR(100) DEFAULT NULL,
            area_abrangencia TEXT DEFAULT NULL,
            preco_mensal DECIMAL(10,2) DEFAULT NULL,
            capacidade_maxima INT(11) DEFAULT 15,
            motorista VARCHAR(100) DEFAULT NULL,
            matricula_carro VARCHAR(20) DEFAULT NULL,
            ativo TINYINT(1) DEFAULT 1,
            activo TINYINT(1) DEFAULT 1,
            PRIMARY KEY  (id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 27. sige_transporte_alunos
        $sqls[] = "CREATE TABLE {$p}sige_transporte_alunos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            rota_id BIGINT(20) UNSIGNED NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            data_inicio DATE DEFAULT NULL,
            data_fim DATE DEFAULT NULL,
            data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (aluno_id),
            KEY idx_rota (rota_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO TESOURARIA / FINANCEIRO
        // ─────────────────────────────────────────────────────────────────────

        // 28. sige_fin_configuracoes
        $sqls[] = "CREATE TABLE {$p}sige_fin_configuracoes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            ano_letivo INT(4) NOT NULL,
            desconto_irmao_percentual DECIMAL(5,2) DEFAULT 0.00,
            desconto_irmao_aplica_apartir INT(1) DEFAULT 2,
            desconto_funcionario_percentual DECIMAL(5,2) DEFAULT 0.00,
            desconto_pronto_pag_anual DECIMAL(5,2) DEFAULT 15.00,
            desconto_pronto_pag_trimestral DECIMAL(5,2) DEFAULT 5.00,
            tipo_multa VARCHAR(20) DEFAULT 'percentual',
            valor_multa_fixa DECIMAL(10,2) DEFAULT 500.00,
            percentual_multa_mensal DECIMAL(10,2) DEFAULT 0.00,
            dias_tolerancia_geral INT(4) DEFAULT 0,
            aplicar_multa_apos_dias INT(4) DEFAULT 0,
            trimestre_1_inicio DATE DEFAULT NULL,
            trimestre_1_fim DATE DEFAULT NULL,
            trimestre_2_inicio DATE DEFAULT NULL,
            trimestre_2_fim DATE DEFAULT NULL,
            trimestre_3_inicio DATE DEFAULT NULL,
            trimestre_3_fim DATE DEFAULT NULL,
            permitir_pagamento_parcial TINYINT(1) DEFAULT 1,
            bloquear_aluno_apos_dias INT(3) DEFAULT 30,
            enviar_lembrete_vencimento TINYINT(1) DEFAULT 1,
            dias_antecedencia_lembrete INT(2) DEFAULT 3,
            gerar_lancamentos_automatico TINYINT(1) DEFAULT 1,
            momento_geracao ENUM('matricula','inicio_mes','inicio_trimestre') DEFAULT 'inicio_mes',
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_modificacao DATETIME DEFAULT NULL,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            desconto_irmaos DECIMAL(10,2) DEFAULT 0.00,
            desconto_funcionario DECIMAL(10,2) DEFAULT 0.00,
            desconto_pronto_pagamento DECIMAL(10,2) DEFAULT 0.00,
            prazo_vencimento INT(2) DEFAULT 10,
            taxa_inscricao DECIMAL(10,2) DEFAULT 0.00,
            multa_atraso_percentual DECIMAL(10,2) DEFAULT 0.00,
            preco_estudos DECIMAL(10,2) DEFAULT 1000.00,
            preco_ingles DECIMAL(10,2) DEFAULT 1000.00,
            preco_desporto DECIMAL(10,2) DEFAULT 1000.00,
            preco_almoco DECIMAL(10,2) DEFAULT 2000.00,
            preco_pequeno_almoco DECIMAL(10,2) DEFAULT 500.00,
            dia_vencimento_mensalidade INT(2) DEFAULT 5,
            dias_para_multa INT(3) DEFAULT 10,
            multa_valor_fixo DECIMAL(10,2) DEFAULT 0.00,
            multa_tier1_dias INT(11) NOT NULL DEFAULT 10,
            multa_tier1_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            multa_tier2_dias INT(11) NOT NULL DEFAULT 30,
            multa_tier2_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            multa_tier3_dias INT(11) NOT NULL DEFAULT 60,
            multa_tier3_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            desconto_irmaos_pct DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            desconto_irmaos_mt DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            desconto_irmaos_tipo VARCHAR(20) NOT NULL DEFAULT 'mt',
            desconto_funcionario_mt DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            desconto_funcionario_tipo VARCHAR(20) NOT NULL DEFAULT 'mt',
            creche_semi_ate4 DECIMAL(10,2) DEFAULT 3000.00,
            creche_semi_5anos DECIMAL(10,2) DEFAULT 3200.00,
            creche_integral_ate4 DECIMAL(10,2) DEFAULT 4000.00,
            creche_integral_5anos DECIMAL(10,2) DEFAULT 4200.00,
            desc_adiant_6m_pct DECIMAL(5,2) NOT NULL DEFAULT 2.00,
            desc_adiant_12m_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
            prefixo_telefone VARCHAR(10) NOT NULL DEFAULT '258',
            formato_telefone_digitos INT(2) NOT NULL DEFAULT 9,
            moeda_simbolo VARCHAR(10) NOT NULL DEFAULT 'MT',
            timezone VARCHAR(50) NOT NULL DEFAULT 'Africa/Maputo',
            PRIMARY KEY  (id),
            UNIQUE KEY ano_letivo_escola (ano_letivo, escola_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 29. sige_fin_servicos
        $sqls[] = "CREATE TABLE {$p}sige_fin_servicos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            nome VARCHAR(100) NOT NULL,
            tipo VARCHAR(50) NOT NULL,
            valor DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            multa_atraso DECIMAL(10,2) DEFAULT 0.00,
            dia_vencimento INT(11) DEFAULT 10,
            ativo TINYINT(1) DEFAULT 1,
            aplica_desconto TINYINT(1) DEFAULT 0,
            categoria_sne ENUM('geral','interno','externo') DEFAULT 'geral',
            aplica_desc_pronto TINYINT(1) DEFAULT 0,
            aplica_desc_func TINYINT(1) DEFAULT 0,
            ciclo VARCHAR(50) DEFAULT 'todos',
            tipo_multa VARCHAR(20) DEFAULT 'percentual',
            valor_multa DECIMAL(10,2) DEFAULT 0.00,
            classe VARCHAR(50) DEFAULT 'todas',
            aplica_multa TINYINT(1) DEFAULT 0,
            categoria VARCHAR(60) DEFAULT NULL,
            recorrente TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY  (id),
            KEY idx_escola_id (escola_id),
            KEY idx_tipo (tipo),
            KEY idx_ativo (ativo)
        ) {$cc};";

        // 30. sige_fin_lancamentos - VARCHAR(20) para suportar avulsos sufixados
        $sqls[] = "CREATE TABLE {$p}sige_fin_lancamentos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            servico_id BIGINT(20) UNSIGNED NOT NULL,
            descricao VARCHAR(255) DEFAULT NULL,
            mes_referencia VARCHAR(20) DEFAULT NULL,
            valor_original DECIMAL(10,2) NOT NULL,
            valor_multa DECIMAL(10,2) DEFAULT 0.00,
            valor_desconto DECIMAL(10,2) DEFAULT 0.00,
            valor_pago DECIMAL(10,2) DEFAULT 0.00,
            data_vencimento DATE NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente',
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            valor_transporte DECIMAL(10,2) DEFAULT 0.00,
            valor_extras DECIMAL(10,2) DEFAULT 0.00,
            valor_desconto_especial DECIMAL(10,2) DEFAULT 0.00,
            cancelado_em DATETIME DEFAULT NULL,
            cancelado_por BIGINT(20) DEFAULT NULL,
            motivo_cancelamento VARCHAR(255) DEFAULT NULL,
            motivo_desconto_especial VARCHAR(255) DEFAULT NULL,
            valor_multa_cobrada DECIMAL(10,2) DEFAULT NULL,
            quantidade TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
            plano_id BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (aluno_id),
            KEY idx_servico (servico_id),
            KEY idx_mes (mes_referencia),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id),
            KEY idx_status_venc (status, data_vencimento),
            KEY idx_perf_lanc_escola_mes_status (escola_id, mes_referencia, status),
            KEY idx_perf_lanc_escola_aluno_status_mes (escola_id, aluno_id, status, mes_referencia),
            KEY idx_perf_lanc_escola_status_venc (escola_id, status, data_vencimento),
            KEY idx_perf_lanc_escola_servico_mes_status (escola_id, servico_id, mes_referencia, status)
        ) {$cc};";

        // 30.1 sige_fin_ledger (Fase 6: livro-razao imutavel, append-only, encadeado por HMAC)
        $sqls[] = "CREATE TABLE {$p}sige_fin_ledger (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            seq BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            event_type VARCHAR(60) NOT NULL,
            entidade VARCHAR(60) NOT NULL DEFAULT '',
            entidade_id BIGINT(20) NOT NULL DEFAULT 0,
            montante DECIMAL(15,2) DEFAULT NULL,
            actor_user_id BIGINT(20) NOT NULL DEFAULT 0,
            actor_nome VARCHAR(120) NOT NULL DEFAULT '',
            ocorrido_em DATETIME NOT NULL,
            payload LONGTEXT DEFAULT NULL,
            prev_hash CHAR(64) NOT NULL DEFAULT '',
            hash CHAR(64) NOT NULL DEFAULT '',
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_escola_seq (escola_id, seq),
            KEY idx_event_type (event_type),
            KEY idx_ocorrido_em (ocorrido_em),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 31. sige_fin_pagamentos
        $sqls[] = "CREATE TABLE {$p}sige_fin_pagamentos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            lancamento_id BIGINT(20) UNSIGNED NOT NULL,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            recibo_numero VARCHAR(40) NOT NULL,
            valor_pago DECIMAL(10,2) NOT NULL,
            metodo_pagamento VARCHAR(50) NOT NULL,
            referencia_externa VARCHAR(120) DEFAULT NULL,
            recebido_por BIGINT(20) UNSIGNED DEFAULT NULL,
            data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
            observacoes TEXT DEFAULT NULL,
            multa_isenta TINYINT(1) DEFAULT 0,
            motivo_isencao VARCHAR(190) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_lanc (lancamento_id),
            KEY idx_aluno (aluno_id),
            KEY idx_data (data_pagamento),
            KEY idx_recibo (recibo_numero),
            KEY idx_escola_id (escola_id),
            KEY idx_perf_pag_escola_data (escola_id, data_pagamento),
            KEY idx_perf_pag_escola_aluno_data (escola_id, aluno_id, data_pagamento),
            KEY idx_perf_pag_escola_lanc (escola_id, lancamento_id)
        ) {$cc};";

        // 32. sige_fin_fechos_caixa
        $sqls[] = "CREATE TABLE {$p}sige_fin_fechos_caixa (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            data_caixa DATE NOT NULL,
            total_bruto DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_estornos DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_liquido DECIMAL(12,2) NOT NULL DEFAULT 0.00,
            total_por_metodo LONGTEXT DEFAULT NULL,
            fechado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            data_fecho DATETIME DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'aberto',
            resumo_metodos TEXT DEFAULT NULL,
            fechado_em DATETIME DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY data_escola (data_caixa, escola_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 32b. sige_fin_aprovacoes (Fase 7 incr 2: regra de quatro-olhos)
        $sqls[] = "CREATE TABLE {$p}sige_fin_aprovacoes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            tipo VARCHAR(40) NOT NULL,
            alvo_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            alvo_ref VARCHAR(120) DEFAULT NULL,
            parametros LONGTEXT DEFAULT NULL,
            estado VARCHAR(20) NOT NULL DEFAULT 'pendente',
            solicitante_user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
            solicitante_nome VARCHAR(120) DEFAULT NULL,
            solicitado_em DATETIME DEFAULT NULL,
            aprovador_user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            aprovador_nome VARCHAR(120) DEFAULT NULL,
            decidido_em DATETIME DEFAULT NULL,
            decisao_motivo VARCHAR(255) DEFAULT NULL,
            resultado LONGTEXT DEFAULT NULL,
            executado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_escola_estado (escola_id, estado),
            KEY idx_alvo (escola_id, tipo, alvo_id)
        ) {$cc};";

        // 33. sige_fin_despesas
        $sqls[] = "CREATE TABLE {$p}sige_fin_despesas (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            data_despesa DATE NOT NULL,
            categoria VARCHAR(60) DEFAULT 'geral',
            descricao VARCHAR(255) NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            metodo_pagamento VARCHAR(50) DEFAULT 'numerario',
            referencia VARCHAR(100) DEFAULT NULL,
            fornecedor VARCHAR(150) DEFAULT NULL,
            observacoes TEXT DEFAULT NULL,
            comprovativo_url VARCHAR(500) DEFAULT NULL,
            registado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            aprovado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            status VARCHAR(20) NOT NULL DEFAULT 'aprovado',
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_data (data_despesa),
            KEY idx_categoria (categoria),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id),
            KEY idx_perf_desp_escola_data_status (escola_id, data_despesa, status)
        ) {$cc};";

        // 34. sige_fin_creditos
        $sqls[] = "CREATE TABLE {$p}sige_fin_creditos (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            valor_total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            valor_disponivel DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente',
            meta LONGTEXT DEFAULT NULL,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            criado_em DATETIME NOT NULL,
            aprovado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            aprovado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (aluno_id),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 35. sige_fin_historico_precos
        $sqls[] = "CREATE TABLE {$p}sige_fin_historico_precos (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            servico_id BIGINT(20) DEFAULT NULL,
            pacote_id BIGINT(20) DEFAULT NULL,
            tipo_alteracao ENUM('servico','pacote') NOT NULL,
            campo_alterado VARCHAR(50) NOT NULL,
            valor_anterior VARCHAR(255) DEFAULT NULL,
            valor_novo VARCHAR(255) DEFAULT NULL,
            motivo TEXT DEFAULT NULL,
            data_alteracao DATETIME DEFAULT CURRENT_TIMESTAMP,
            alterado_por INT(11) DEFAULT NULL,
            ip_usuario VARCHAR(45) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_servico (servico_id),
            KEY idx_pacote (pacote_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 36. sige_fin_pacotes
        $sqls[] = "CREATE TABLE {$p}sige_fin_pacotes (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            nome VARCHAR(150) NOT NULL,
            codigo VARCHAR(20) NOT NULL,
            descricao TEXT DEFAULT NULL,
            tipo ENUM('combo_mensal','combo_trimestral','combo_anual','promocional') NOT NULL,
            classe VARCHAR(50) DEFAULT NULL,
            nivel_ensino VARCHAR(50) DEFAULT NULL,
            ano_letivo INT(4) NOT NULL,
            valor_total_servicos DECIMAL(10,2) NOT NULL,
            valor_pacote DECIMAL(10,2) NOT NULL,
            beneficios TEXT DEFAULT NULL,
            condicoes TEXT DEFAULT NULL,
            ativo TINYINT(1) DEFAULT 1,
            destaque TINYINT(1) DEFAULT 0,
            ordem_exibicao INT(3) DEFAULT 0,
            data_inicio_validade DATE DEFAULT NULL,
            data_fim_validade DATE DEFAULT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_pacote_escola_codigo_ano (escola_id, codigo, ano_letivo),
            KEY idx_classe_ano (classe, ano_letivo),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 37. sige_fin_pacote_itens
        $sqls[] = "CREATE TABLE {$p}sige_fin_pacote_itens (
            id BIGINT(20) NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            pacote_id BIGINT(20) NOT NULL,
            servico_id BIGINT(20) UNSIGNED NOT NULL,
            quantidade_meses INT(2) DEFAULT 1,
            observacao VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY fk_pacote (pacote_id),
            KEY fk_servico (servico_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 38. sige_fin_pagamentos_anuais
        $sqls[] = "CREATE TABLE {$p}sige_fin_pagamentos_anuais (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            ano_letivo INT(4) NOT NULL,
            tipo VARCHAR(40) NOT NULL,
            pacote_id BIGINT(20) UNSIGNED DEFAULT NULL,
            valor_total_original DECIMAL(10,2) NOT NULL,
            desconto_pronto_pagamento DECIMAL(10,2) DEFAULT 0.00,
            valor_final_pago DECIMAL(10,2) NOT NULL,
            percentual_desconto DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            data_pagamento DATE NOT NULL,
            metodo_pagamento VARCHAR(50) DEFAULT NULL,
            referencia_pagamento VARCHAR(120) DEFAULT NULL,
            recibo_numero VARCHAR(40) NOT NULL,
            servicos_incluidos LONGTEXT NOT NULL,
            meses_cobertos LONGTEXT NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'ativo',
            observacoes TEXT DEFAULT NULL,
            data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP,
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_pag_anual_escola_aluno_ano (escola_id, aluno_id, ano_letivo),
            KEY idx_aluno (aluno_id),
            KEY idx_ano (ano_letivo),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 39. sige_fin_contactos_cobranca
        $sqls[] = "CREATE TABLE {$p}sige_fin_contactos_cobranca (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            data_contacto DATETIME NOT NULL,
            canal VARCHAR(30) NOT NULL DEFAULT 'whatsapp',
            resultado VARCHAR(50) NOT NULL DEFAULT 'sem_resposta',
            valor_prometido DECIMAL(10,2) DEFAULT NULL,
            data_prometida DATE DEFAULT NULL,
            notas TEXT DEFAULT NULL,
            proximo_contacto DATE DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_aluno (escola_id, aluno_id),
            KEY idx_proximo (escola_id, proximo_contacto)
        ) {$cc};";

        // 40. sige_fin_planos_pagamento
        $sqls[] = "CREATE TABLE {$p}sige_fin_planos_pagamento (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            criado_por BIGINT(20) UNSIGNED NOT NULL,
            valor_total DECIMAL(12,2) NOT NULL,
            n_prestacoes TINYINT(3) UNSIGNED NOT NULL DEFAULT 1,
            descricao VARCHAR(255) DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'activo',
            notas TEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            aprovado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            aprovado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (escola_id, aluno_id),
            KEY idx_status (escola_id, status)
        ) {$cc};";

        // 41. sige_fin_planos_prestacoes
        $sqls[] = "CREATE TABLE {$p}sige_fin_planos_prestacoes (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            plano_id BIGINT(20) UNSIGNED NOT NULL,
            numero TINYINT(3) UNSIGNED NOT NULL,
            valor DECIMAL(10,2) NOT NULL,
            data_vencimento DATE NOT NULL,
            lancamento_id BIGINT(20) UNSIGNED DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pendente',
            pago_em DATETIME DEFAULT NULL,
            notas TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_plano (plano_id),
            KEY idx_venc (escola_id, data_vencimento, status)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO COMUNICAÇÃO
        // ─────────────────────────────────────────────────────────────────────

        // 42. sige_whatsapp_queue
        $sqls[] = "CREATE TABLE {$p}sige_whatsapp_queue (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            telefone VARCHAR(50) DEFAULT NULL,
            mensagem TEXT NOT NULL,
            tipo VARCHAR(40) DEFAULT NULL,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            status VARCHAR(20) DEFAULT 'pendente',
            tentativas INT(2) NOT NULL DEFAULT 0,
            criado_em DATETIME NOT NULL,
            enviado_em DATETIME DEFAULT NULL,
            erro VARCHAR(255) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id),
            KEY idx_perf_wpp_status_tentativas (status, tentativas, criado_em)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // LEGACY (mantida, mas não usada activamente)
        // ─────────────────────────────────────────────────────────────────────

        // 43. sige_financeiro - tabela legacy (0 linhas em produção)
        $sqls[] = "CREATE TABLE {$p}sige_financeiro (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            aluno_id INT(11) NOT NULL,
            servico VARCHAR(100) DEFAULT NULL,
            mes VARCHAR(20) DEFAULT NULL,
            valor DECIMAL(10,2) DEFAULT 0.00,
            valor_transporte DECIMAL(10,2) DEFAULT 0.00,
            multa DECIMAL(10,2) DEFAULT 0.00,
            desconto DECIMAL(10,2) DEFAULT 0.00,
            valor_total DECIMAL(10,2) NOT NULL,
            metodo VARCHAR(50) DEFAULT NULL,
            referencia VARCHAR(100) DEFAULT NULL,
            data_pagamento DATETIME DEFAULT CURRENT_TIMESTAMP,
            registado_por BIGINT(20) DEFAULT NULL,
            tipo_movimento VARCHAR(50) DEFAULT 'receita',
            categoria VARCHAR(100) DEFAULT NULL,
            mes_referencia VARCHAR(20) DEFAULT NULL,
            valor_base DECIMAL(10,2) DEFAULT 0.00,
            metodo_pagamento VARCHAR(50) DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_aluno (aluno_id),
            KEY idx_data (data_pagamento),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO ACTA - Sprint 2 · M2 (ACN/A25 Colégio Malisa)
        // ─────────────────────────────────────────────────────────────────────

        // 44. sige_acta_conselho_notas - Cabeçalho do Conselho de Notas
        $sqls[] = "CREATE TABLE {$p}sige_acta_conselho_notas (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            turma_id BIGINT(20) UNSIGNED NOT NULL,
            ano_lectivo INT(4) NOT NULL,
            trimestre TINYINT(1) NOT NULL,
            data_conselho DATE DEFAULT NULL,
            sala VARCHAR(50) DEFAULT NULL,
            hora_inicio TIME DEFAULT NULL,
            presidido_por VARCHAR(200) DEFAULT NULL,
            como_decorreu TEXT DEFAULT NULL,
            observacao_dp TEXT DEFAULT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'rascunho',
            criado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            criado_em DATETIME DEFAULT NULL,
            finalizado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            finalizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_turma_ano_trim (turma_id, ano_lectivo, trimestre, escola_id),
            KEY idx_ano_trim (ano_lectivo, trimestre),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 45. sige_acta_presencas - Presentes e Ausentes do Conselho
        $sqls[] = "CREATE TABLE {$p}sige_acta_presencas (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            acta_id BIGINT(20) UNSIGNED NOT NULL,
            user_id BIGINT(20) UNSIGNED DEFAULT NULL,
            nome VARCHAR(200) NOT NULL,
            funcao VARCHAR(100) DEFAULT NULL,
            disciplina_id BIGINT(20) UNSIGNED DEFAULT NULL,
            disciplina_nome VARCHAR(100) DEFAULT NULL,
            status VARCHAR(15) NOT NULL DEFAULT 'presente',
            ordem INT(3) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_acta (acta_id),
            KEY idx_status (status),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 46. sige_acta_nota_votada - Notas alteradas pelo Conselho
        $sqls[] = "CREATE TABLE {$p}sige_acta_nota_votada (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            acta_id BIGINT(20) UNSIGNED NOT NULL,
            aluno_id BIGINT(20) UNSIGNED NOT NULL,
            disciplina_id BIGINT(20) UNSIGNED DEFAULT NULL,
            numero_chamada INT(4) DEFAULT NULL,
            nota_inicial DECIMAL(4,2) DEFAULT NULL,
            nota_final DECIMAL(4,2) DEFAULT NULL,
            recomendacoes TEXT DEFAULT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'pendente_dp',
            aprovado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            aprovado_em DATETIME DEFAULT NULL,
            aplicado_em DATETIME DEFAULT NULL,
            nota_id BIGINT(20) UNSIGNED DEFAULT NULL,
            nota_original_json LONGTEXT DEFAULT NULL,
            observacao_dp TEXT DEFAULT NULL,
            ordem INT(3) NOT NULL DEFAULT 0,
            PRIMARY KEY  (id),
            KEY idx_acta (acta_id),
            KEY idx_aluno (aluno_id),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // 47. sige_acta_cumprimento_programas - Último tema + aulas em atraso
        $sqls[] = "CREATE TABLE {$p}sige_acta_cumprimento_programas (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            acta_id BIGINT(20) UNSIGNED NOT NULL,
            disciplina_id BIGINT(20) UNSIGNED DEFAULT NULL,
            disciplina_nome VARCHAR(100) NOT NULL,
            ultimo_tema TEXT DEFAULT NULL,
            aulas_em_atraso INT(4) DEFAULT NULL,
            razoes_atraso TEXT DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uq_acta_disc_tenant (escola_id, acta_id, disciplina_id, disciplina_nome),
            KEY idx_escola_id (escola_id)
        ) {$cc};";

        // ─────────────────────────────────────────────────────────────────────
        // MÓDULO CURRICULUM ENGINE - v12.11.0 Foundation PRO
        // ─────────────────────────────────────────────────────────────────────
        // Fundação multicurrículo em modo seguro. Estas tabelas NÃO substituem
        // a matriz curricular actual nesta fase; apenas preparam Moçambique/SNE,
        // Cambridge, Angola, Brasil e perfis personalizados para integração futura.

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_profiles (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            code VARCHAR(80) NOT NULL,
            nome VARCHAR(180) NOT NULL,
            pais VARCHAR(80) DEFAULT NULL,
            tipo VARCHAR(50) NOT NULL DEFAULT 'national',
            status VARCHAR(30) NOT NULL DEFAULT 'modelo',
            descricao TEXT DEFAULT NULL,
            period_model VARCHAR(60) NOT NULL DEFAULT 'trimestres',
            assessment_scale VARCHAR(80) NOT NULL DEFAULT '0-20',
            progression_model VARCHAR(80) NOT NULL DEFAULT 'custom',
            is_system TINYINT(1) NOT NULL DEFAULT 0,
            is_readonly TINYINT(1) NOT NULL DEFAULT 0,
            meta_json LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_curr_profile_school_code (escola_id, code),
            KEY idx_curr_profile_school (escola_id),
            KEY idx_curr_profile_status (status)
        ) {$cc};";

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_levels (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            profile_id BIGINT(20) UNSIGNED NOT NULL,
            code VARCHAR(80) NOT NULL,
            nome VARCHAR(180) NOT NULL,
            ordem INT(4) NOT NULL DEFAULT 0,
            meta_json LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_curr_level_school_profile_code (escola_id, profile_id, code),
            KEY idx_curr_level_school (escola_id),
            KEY idx_curr_level_profile (profile_id)
        ) {$cc};";

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_grades (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            profile_id BIGINT(20) UNSIGNED NOT NULL,
            level_id BIGINT(20) UNSIGNED DEFAULT NULL,
            code VARCHAR(80) NOT NULL,
            nome VARCHAR(180) NOT NULL,
            legacy_classe VARCHAR(80) DEFAULT NULL,
            ordem INT(4) NOT NULL DEFAULT 0,
            meta_json LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_curr_grade_school_profile_code (escola_id, profile_id, code),
            KEY idx_curr_grade_school (escola_id),
            KEY idx_curr_grade_profile (profile_id),
            KEY idx_curr_grade_legacy (escola_id, legacy_classe)
        ) {$cc};";

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_subjects (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            profile_id BIGINT(20) UNSIGNED NOT NULL,
            grade_id BIGINT(20) UNSIGNED DEFAULT NULL,
            disciplina_id BIGINT(20) UNSIGNED DEFAULT NULL,
            codigo VARCHAR(80) DEFAULT NULL,
            nome VARCHAR(180) NOT NULL,
            categoria VARCHAR(40) NOT NULL DEFAULT 'complementar',
            obrigatoria TINYINT(1) NOT NULL DEFAULT 1,
            conta_progressao TINYINT(1) NOT NULL DEFAULT 0,
            carga_horaria INT(5) DEFAULT NULL,
            ordem INT(4) NOT NULL DEFAULT 999,
            meta_json LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_curr_subject_school_grade_disc (escola_id, profile_id, grade_id, disciplina_id),
            KEY idx_curr_subject_school (escola_id),
            KEY idx_curr_subject_profile (profile_id),
            KEY idx_curr_subject_grade (grade_id),
            KEY idx_curr_subject_disc (disciplina_id)
        ) {$cc};";

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_periods (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            profile_id BIGINT(20) UNSIGNED NOT NULL,
            code VARCHAR(80) NOT NULL,
            nome VARCHAR(180) NOT NULL,
            tipo VARCHAR(60) NOT NULL DEFAULT 'trimestre',
            ordem INT(4) NOT NULL DEFAULT 0,
            peso DECIMAL(6,2) DEFAULT NULL,
            meta_json LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_curr_period_school_profile_code (escola_id, profile_id, code),
            KEY idx_curr_period_school (escola_id),
            KEY idx_curr_period_profile (profile_id)
        ) {$cc};";

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_assessment_rules (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            profile_id BIGINT(20) UNSIGNED NOT NULL,
            grade_id BIGINT(20) UNSIGNED DEFAULT NULL,
            subject_id BIGINT(20) UNSIGNED DEFAULT NULL,
            rule_type VARCHAR(80) NOT NULL,
            rule_key VARCHAR(120) NOT NULL,
            rule_value LONGTEXT DEFAULT NULL,
            prioridade INT(4) NOT NULL DEFAULT 100,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_curr_rule_school (escola_id),
            KEY idx_curr_rule_profile (profile_id),
            KEY idx_curr_rule_lookup (profile_id, grade_id, subject_id, rule_type)
        ) {$cc};";

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_document_templates (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
            profile_id BIGINT(20) UNSIGNED NOT NULL,
            template_type VARCHAR(80) NOT NULL,
            nome VARCHAR(180) NOT NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'rascunho',
            template_json LONGTEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            PRIMARY KEY  (id),
            KEY idx_curr_doc_school (escola_id),
            KEY idx_curr_doc_profile (profile_id),
            KEY idx_curr_doc_type (template_type)
        ) {$cc};";

        $sqls[] = "CREATE TABLE {$p}sige_curriculum_school_settings (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT(20) UNSIGNED NOT NULL,
            active_profile_id BIGINT(20) UNSIGNED DEFAULT NULL,
            modo_execucao VARCHAR(40) NOT NULL DEFAULT 'legacy_safe',
            estado VARCHAR(40) NOT NULL DEFAULT 'foundation',
            observacoes TEXT DEFAULT NULL,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME DEFAULT NULL,
            actualizado_por BIGINT(20) UNSIGNED DEFAULT NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY uniq_curr_settings_school (escola_id),
            KEY idx_curr_settings_profile (active_profile_id)
        ) {$cc};";

        // ── Executar dbDelta para todas as tabelas ──
        foreach ($sqls as $sql) {
            dbDelta($sql);
        }
    }

    // ========================================================================
    // MIGRAÇÕES INCREMENTAIS
    // ========================================================================
    //
    // Estas são operações que dbDelta não faz bem:
    //   • ALTER TABLE … MODIFY COLUMN (mudar tipo/tamanho)
    //   • DROP INDEX
    //   • Condicionais complexas
    //
    // Cada migração verifica se já foi aplicada antes de correr.
    // ========================================================================

    private function run_migrations(): void {
        $p = $this->p;

        // ── M1: mes_referencia VARCHAR(20) ──────────────────────────────────
        // Avulsos com sufixo sequencial (2026-04-2, 2026-04-3) precisam de >7 chars
        $this->ensure_column_size("{$p}sige_fin_lancamentos", 'mes_referencia', 20);

        // ── M2: Colunas extras em sige_logs_auditoria ───────────────────────
        $this->add_column_if_missing("{$p}sige_logs_auditoria", 'modulo',
            "VARCHAR(30) NOT NULL DEFAULT 'financeiro' AFTER acao");
        $this->add_column_if_missing("{$p}sige_logs_auditoria", 'user_display',
            "VARCHAR(120) DEFAULT NULL AFTER user_id");
        $this->add_column_if_missing("{$p}sige_logs_auditoria", 'escola_id',
            "BIGINT(20) UNSIGNED NOT NULL DEFAULT 1 AFTER id");

        // ── M3: Colunas extras em sige_fin_lancamentos ──────────────────────
        $this->add_column_if_missing("{$p}sige_fin_lancamentos", 'valor_transporte',
            "DECIMAL(10,2) DEFAULT 0.00 AFTER data_criacao");
        $this->add_column_if_missing("{$p}sige_fin_lancamentos", 'valor_extras',
            "DECIMAL(10,2) DEFAULT 0.00 AFTER valor_transporte");
        $this->add_column_if_missing("{$p}sige_fin_lancamentos", 'quantidade',
            "TINYINT(3) UNSIGNED NOT NULL DEFAULT 1 AFTER valor_multa_cobrada");
        $this->add_column_if_missing("{$p}sige_fin_lancamentos", 'plano_id',
            "BIGINT(20) UNSIGNED DEFAULT NULL AFTER quantidade");

        // ── M4: Colunas extras em sige_alunos ───────────────────────────────
        $cols_alunos = [
            'mensalidade_base'      => 'DECIMAL(10,2) DEFAULT 0.00',
            'tem_irmao'             => 'TINYINT(1) DEFAULT 0',
            'rota_transporte_id'    => 'INT(11) DEFAULT 0',
            'whatsapp_notificacoes' => 'VARCHAR(50) DEFAULT NULL',
            'email_encarregado'     => 'VARCHAR(100) DEFAULT NULL',
            'email_pai'             => 'VARCHAR(100) DEFAULT NULL',
            'email_mae'             => 'VARCHAR(100) DEFAULT NULL',
            'tem_estudos'           => 'TINYINT(1) DEFAULT 0',
            'tem_ingles'            => 'TINYINT(1) DEFAULT 0',
            'tem_desporto'          => 'TINYINT(1) DEFAULT 0',
            'tem_almoco'            => 'TINYINT(1) DEFAULT 0',
            'tem_pequeno_almoco'    => 'TINYINT(1) DEFAULT 0',
            'regime_creche'         => "VARCHAR(20) DEFAULT NULL",
            'familia_id'           => 'BIGINT(20) DEFAULT NULL',
            'importacao_lote_id'  => 'VARCHAR(100) DEFAULT NULL',
            'importado_em'        => 'DATETIME DEFAULT NULL',
            'importado_por'       => 'BIGINT(20) UNSIGNED DEFAULT NULL',
            'telemovel_pai_2'     => 'VARCHAR(50) DEFAULT NULL',
            'telemovel_mae_2'     => 'VARCHAR(50) DEFAULT NULL',
            'tipo_documento'      => 'VARCHAR(50) DEFAULT NULL',
            'contacto_emergencia_1' => 'VARCHAR(255) DEFAULT NULL',
            'contacto_emergencia_2' => 'VARCHAR(255) DEFAULT NULL',
            'encarregado_principal_tipo' => "VARCHAR(40) DEFAULT 'pai_mae'",
            'encarregado_principal_nome' => 'VARCHAR(255) DEFAULT NULL',
            'encarregado_principal_parentesco' => 'VARCHAR(80) DEFAULT NULL',
            'encarregado_principal_telemovel' => 'VARCHAR(50) DEFAULT NULL',
            'encarregado_principal_email' => 'VARCHAR(120) DEFAULT NULL',
            'canal_preferencial_comunicacao' => "VARCHAR(30) DEFAULT 'whatsapp'",
            'consent_whatsapp'    => 'TINYINT(1) DEFAULT 1',
            'consent_email'       => 'TINYINT(1) DEFAULT 1',
            'consent_sms'         => 'TINYINT(1) DEFAULT 0',
            'consent_chamada'     => 'TINYINT(1) DEFAULT 1',
            'contacto_alternativo_nome' => 'VARCHAR(255) DEFAULT NULL',
            'contacto_alternativo_parentesco' => 'VARCHAR(80) DEFAULT NULL',
            'contacto_alternativo_telemovel' => 'VARCHAR(50) DEFAULT NULL',
            'autorizado_buscar_nome' => 'VARCHAR(255) DEFAULT NULL',
            'autorizado_buscar_parentesco' => 'VARCHAR(80) DEFAULT NULL',
            'autorizado_buscar_telemovel' => 'VARCHAR(50) DEFAULT NULL',
            'autorizado_buscar_documento' => 'VARCHAR(80) DEFAULT NULL',
            'encarregado_observacoes' => 'TEXT DEFAULT NULL',
            'consentimento_comunicacao_em' => 'DATETIME DEFAULT NULL',
        ];
        foreach ($cols_alunos as $col => $def) {
            $this->add_column_if_missing("{$p}sige_alunos", $col, $def);
        }
        $this->add_column_if_missing("{$p}sige_matriculas", 'importacao_lote_id', 'VARCHAR(100) DEFAULT NULL');
        $this->add_column_if_missing("{$p}sige_matriculas", 'importado_em', 'DATETIME DEFAULT NULL');
        $this->add_column_if_missing("{$p}sige_matriculas", 'importado_por', 'BIGINT(20) UNSIGNED DEFAULT NULL');
        $this->add_index_if_missing("{$p}sige_alunos", 'idx_importacao_lote', '(escola_id, importacao_lote_id)');
        $this->add_index_if_missing("{$p}sige_alunos", 'idx_guardian_principal', '(escola_id, encarregado_principal_tipo)');
        $this->add_index_if_missing("{$p}sige_matriculas", 'idx_importacao_lote', '(escola_id, importacao_lote_id)');
        if ($this->table_exists("{$p}sige_alunos_importacao_lotes")) {
            $this->add_index_if_missing("{$p}sige_alunos_importacao_lotes", 'idx_escola_estado', '(escola_id, estado)');
            $this->add_index_if_missing("{$p}sige_alunos_importacao_lotes", 'idx_escola_criado', '(escola_id, criado_em)');
            $this->add_index_if_missing("{$p}sige_alunos_importacao_lotes", 'idx_turma_ano', '(turma_id, ano_lectivo)');
        }
        if ($this->table_exists("{$p}sige_alunos_encarregados_historico")) {
            $this->add_index_if_missing("{$p}sige_alunos_encarregados_historico", 'idx_escola_aluno', '(escola_id, aluno_id)');
            $this->add_index_if_missing("{$p}sige_alunos_encarregados_historico", 'idx_escola_criado', '(escola_id, criado_em)');
            $this->add_index_if_missing("{$p}sige_alunos_encarregados_historico", 'idx_aluno_criado', '(aluno_id, criado_em)');
            $this->add_index_if_missing("{$p}sige_alunos_encarregados_historico", 'idx_evento', '(evento)');
        }

        // v12.10.135 - segurança para Pagamento por Família:
        // garantir índice para familia_id mesmo em bases antigas.
        $this->add_index_if_missing("{$p}sige_alunos", 'idx_familia', '(familia_id)');

        // ── M5: escola_id em tabelas que possam não ter ─────────────────────
        $tables_need_escola = [
            'sige_alunos', 'sige_turmas', 'sige_turma_alunos', 'sige_matriculas',
            'sige_notas', 'sige_disciplinas', 'sige_professores', 'sige_config',
        ];
        foreach ($tables_need_escola as $t) {
            $this->add_column_if_missing("{$p}{$t}", 'escola_id',
                "BIGINT(20) UNSIGNED NOT NULL DEFAULT 1 AFTER id");
        }

        // ── M6: Coluna recorrente em sige_fin_servicos ──────────────────────
        // Flag explícita: 1 = mensalidade/transporte (lançamento mensal),
        //                 0 = avulso (uniforme, material, inscrição).
        // Substitui a lógica hardcoded in_array($tipo, ['mensalidade','transporte']).
        $tSrv = "{$p}sige_fin_servicos";
        $this->add_column_if_missing($tSrv, 'recorrente',
            "TINYINT(1) NOT NULL DEFAULT 1 AFTER categoria");
        // Populate: serviços com categoria 'nao_fixo' ou tipo avulso → recorrente=0
        if (!get_option('sige_m6_recorrente_done')) {
            $this->db->query("UPDATE `{$tSrv}`
                SET recorrente = 0
                WHERE categoria = 'nao_fixo'
                   OR categoria = 'material'
                   OR tipo IN ('uniforme','camiseta','material','matricula_novo','encerramento','quinzena_crianca')");
            update_option('sige_m6_recorrente_done', 1);
        }

        // ── M7: Campos dinâmicos em sige_fin_configuracoes ──────────────────
        $tCfg = "{$p}sige_fin_configuracoes";
        $this->add_column_if_missing($tCfg, 'prefixo_telefone',
            "VARCHAR(10) NOT NULL DEFAULT '258'");
        $this->add_column_if_missing($tCfg, 'formato_telefone_digitos',
            "INT(2) NOT NULL DEFAULT 9");
        $this->add_column_if_missing($tCfg, 'moeda_simbolo',
            "VARCHAR(10) NOT NULL DEFAULT 'MT'");

        // ── M8: UNIQUE KEYs multi-tenant ────────────────────────────────────
        // numero_processo deve ser único POR ESCOLA, não globalmente.
        // uniq_aluno_servico_mes em lancamentos impede avulsos repetidos - remover.
        if (!get_option('sige_m8_unique_keys_done')) {
            $tA = "{$p}sige_alunos";
            $tL = "{$p}sige_fin_lancamentos";

            // numero_processo: (numero_processo) → (escola_id, numero_processo)
            $idx = $this->db->get_results("SHOW INDEX FROM `{$tA}` WHERE Key_name = 'uniq_processo'");
            if (!empty($idx)) {
                $this->db->query("ALTER TABLE `{$tA}` DROP INDEX `uniq_processo`");
                $this->db->query("ALTER TABLE `{$tA}` ADD UNIQUE KEY `uniq_processo` (`escola_id`, `numero_processo`)");
            }

            // uniq_aluno_servico_mes: remover (impedia avulsos repetidos)
            $idx2 = $this->db->get_results("SHOW INDEX FROM `{$tL}` WHERE Key_name = 'uniq_aluno_servico_mes'");
            if (!empty($idx2)) {
                $this->db->query("ALTER TABLE `{$tL}` DROP INDEX `uniq_aluno_servico_mes`");
            }

            update_option('sige_m8_unique_keys_done', 1);
        }

        // ── M8.1: Índices de performance para financeiro/dashboards ─────────
        // Baixo risco: apenas ADD KEY se ainda não existir. Não altera dados nem regras.
        $this->add_index_if_missing("{$p}sige_fin_lancamentos", 'idx_perf_lanc_escola_mes_status', '(escola_id, mes_referencia, status)');
        $this->add_index_if_missing("{$p}sige_fin_lancamentos", 'idx_perf_lanc_escola_aluno_status_mes', '(escola_id, aluno_id, status, mes_referencia)');
        $this->add_index_if_missing("{$p}sige_fin_lancamentos", 'idx_perf_lanc_escola_status_venc', '(escola_id, status, data_vencimento)');
        $this->add_index_if_missing("{$p}sige_fin_lancamentos", 'idx_perf_lanc_escola_servico_mes_status', '(escola_id, servico_id, mes_referencia, status)');
        $this->add_index_if_missing("{$p}sige_fin_pagamentos", 'idx_perf_pag_escola_data', '(escola_id, data_pagamento)');
        $this->add_index_if_missing("{$p}sige_fin_pagamentos", 'idx_perf_pag_escola_aluno_data', '(escola_id, aluno_id, data_pagamento)');
        $this->add_index_if_missing("{$p}sige_fin_pagamentos", 'idx_perf_pag_escola_lanc', '(escola_id, lancamento_id)');
        $this->add_index_if_missing("{$p}sige_fin_despesas", 'idx_perf_desp_escola_data_status', '(escola_id, data_despesa, status)');
        $this->add_index_if_missing("{$p}sige_whatsapp_queue", 'idx_perf_wpp_status_tentativas', '(status, tentativas, criado_em)');
        // ── M8.2: Índices leves para tarefas diárias/cron ─────────────────────────
        // Evita varreduras completas em vencimentos de RH e lembretes financeiros.
        $this->add_index_if_missing("{$p}sige_professores", 'idx_perf_prof_escola_status_fim', '(escola_id, status_ativo, fim_contrato)');
        // v12.11.9 - evita fichas RH duplicadas para o mesmo email na mesma escola.
        // Em bases antigas com duplicados, não força limpeza destrutiva: o código de gravação
        // passa a consolidar novas actualizações no registo existente, e o índice é aplicado
        // automaticamente quando não houver duplicados pendentes.
        $this->add_unique_index_if_no_duplicates("{$p}sige_professores", 'uniq_prof_escola_email', 'escola_id, email', '(escola_id, email)');
        $this->add_index_if_missing("{$p}sige_fin_lancamentos", 'idx_perf_lanc_escola_status_id', '(escola_id, status, id)');
        $this->add_index_if_missing("{$p}sige_fin_lancamentos", 'idx_perf_lanc_escola_status_venc_aluno', '(escola_id, status, data_vencimento, aluno_id)');

        // ── M8.3: Política de destinatários WhatsApp por escola ───────────────
        $tEscolaCfg = "{$p}sige_config";
        $this->add_column_if_missing($tEscolaCfg, 'whatsapp_destinatarios_padrao',
            "VARCHAR(40) NOT NULL DEFAULT 'todos'");

        // ── M9: Timezone por escola ─────────────────────────────────────────
        $this->add_column_if_missing($tCfg, 'timezone',
            "VARCHAR(50) NOT NULL DEFAULT 'Africa/Maputo'");

        // ── M9.1: Descontos Irmão/Funcionário com modo MT ou percentagem ─────
        $this->add_column_if_missing($tCfg, 'desconto_irmaos_mt',
            "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        $this->add_column_if_missing($tCfg, 'desconto_irmaos_tipo',
            "VARCHAR(20) NOT NULL DEFAULT 'mt'");
        $this->add_column_if_missing($tCfg, 'desconto_funcionario_mt',
            "DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        $this->add_column_if_missing($tCfg, 'desconto_funcionario_tipo',
            "VARCHAR(20) NOT NULL DEFAULT 'mt'");

        // ── M10: Categoria em sige_matriz_curricular ────────────────────────
        // A categoria (nuclear/complementar) VARIA POR CLASSE - Ed. Visual é
        // complementar na 1ª-5ª mas nuclear na 6ª (fim do 2º ciclo). Por isso
        // a categoria vive no par (classe × disciplina) da matriz curricular,
        // não na tabela disciplinas.
        //
        // Regras oficiais do SNE (Sistema Nacional de Educação) aplicadas
        // aos dados de produção do Colégio Malisa:
        //   • 1ª-3ª : POR, MAT                            → nuclear
        //   • 4ª-5ª : POR, MAT, CS, CN                    → nuclear
        //   • 6ª    : POR, MAT, CS, CN, Ed.Visual         → nuclear
        //   • Pré-escolar e restantes                     → complementar (default)
        $tMatriz = "{$p}sige_matriz_curricular";
        $tDisc   = "{$p}sige_disciplinas";
        $this->add_column_if_missing($tMatriz, 'categoria',
            "VARCHAR(20) NOT NULL DEFAULT 'complementar' AFTER classe");

        // Populate inicial dos registos existentes (uma única vez).
        // Os INSERTs futuros herdam o DEFAULT 'complementar' e serão
        // corrigidos caso a caso via UI em T3+.
        if (!get_option('sige_m10_matriz_categoria_done')) {

            // Bloco 1 - 1ª, 2ª, 3ª Classe: POR e MAT nucleares
            $this->db->query("
                UPDATE `{$tMatriz}` m
                INNER JOIN `{$tDisc}` d ON d.id = m.disciplina_id
                SET m.categoria = 'nuclear'
                WHERE m.classe IN ('1ª', '2ª', '3ª')
                  AND d.sigla IN ('POR', 'MAT')
            ");

            // Bloco 2 - 4ª, 5ª Classe: POR, MAT, CS, CN nucleares
            // (inclui sigla 'MAT(4-6)' porque na BD do Malisa existe disciplina
            //  duplicada para Matemática do 2º ciclo)
            $this->db->query("
                UPDATE `{$tMatriz}` m
                INNER JOIN `{$tDisc}` d ON d.id = m.disciplina_id
                SET m.categoria = 'nuclear'
                WHERE m.classe IN ('4ª', '5ª')
                  AND d.sigla IN ('POR', 'MAT', 'MAT(4-6)', 'CS', 'CN')
            ");

            // Bloco 3 - 6ª Classe (fim do 2º ciclo): Ed.Visual entra para as
            // nucleares (conta para MG). Sigla oficial no Malisa: 'ED.VISUAL'.
            $this->db->query("
                UPDATE `{$tMatriz}` m
                INNER JOIN `{$tDisc}` d ON d.id = m.disciplina_id
                SET m.categoria = 'nuclear'
                WHERE m.classe = '6ª'
                  AND d.sigla IN ('POR', 'MAT', 'MAT(4-6)', 'CS', 'CN', 'ED.VISUAL')
            ");

            update_option('sige_m10_matriz_categoria_done', 1);
        }


        // ── M12: Diagnóstico/consolidação conservadora MAT(4-6) → MAT ─────────────
        // Matemática é disciplina única (MAT) também para 4ª, 5ª e 6ª.
        // Algumas bases antigas têm disciplina duplicada MAT(4-6).
        // Esta rotina só migra quando não houver conflito de notas.
        if (!get_option('sige_m12_mat46_to_mat_checked')) {
            $tDiscM12 = "{$p}sige_disciplinas";
            $tNotasM12 = "{$p}sige_notas";
            $tMatM12 = "{$p}sige_matriz_curricular";
            $tTDM12 = "{$p}sige_turma_disciplinas";
            $eidM12 = 1;
            $logM12 = [
                'started_at' => current_time('mysql'),
                'status' => 'skipped',
                'message' => '',
            ];

            $mat_id = (int)$this->db->get_var("SELECT id FROM `{$tDiscM12}` WHERE escola_id={$eidM12} AND sigla='MAT' ORDER BY id ASC LIMIT 1");
            $mat46_id = (int)$this->db->get_var("SELECT id FROM `{$tDiscM12}` WHERE escola_id={$eidM12} AND sigla='MAT(4-6)' ORDER BY id ASC LIMIT 1");
            $logM12['mat_id'] = $mat_id;
            $logM12['mat46_id'] = $mat46_id;

            if ($mat_id > 0 && $mat46_id > 0 && $mat_id !== $mat46_id) {
                $conf_notes = (int)$this->db->get_var($this->db->prepare(
                    "SELECT COUNT(*) FROM `{$tNotasM12}` n46
                     INNER JOIN `{$tNotasM12}` nmat
                        ON nmat.escola_id=n46.escola_id
                       AND nmat.aluno_id=n46.aluno_id
                       AND nmat.turma_id=n46.turma_id
                       AND nmat.ano_lectivo=n46.ano_lectivo
                       AND nmat.trimestre=n46.trimestre
                       AND nmat.disciplina_id=%d
                     WHERE n46.escola_id=%d AND n46.disciplina_id=%d",
                    $mat_id, $eidM12, $mat46_id
                ));
                $logM12['note_conflicts'] = $conf_notes;

                if ($conf_notes === 0) {
                    $this->db->query('START TRANSACTION');
                    try {
                        $n1 = (int)$this->db->query($this->db->prepare(
                            "UPDATE `{$tNotasM12}` SET disciplina_id=%d WHERE escola_id=%d AND disciplina_id=%d",
                            $mat_id, $eidM12, $mat46_id
                        ));

                        $dups = $this->db->get_results($this->db->prepare(
                            "SELECT m46.id AS id46
                               FROM `{$tMatM12}` m46
                               INNER JOIN `{$tMatM12}` mmat
                                  ON mmat.escola_id=m46.escola_id
                                 AND mmat.classe=m46.classe
                                 AND mmat.disciplina_id=%d
                              WHERE m46.escola_id=%d AND m46.disciplina_id=%d",
                            $mat_id, $eidM12, $mat46_id
                        ));
                        $del_ids = array_map(function($r){ return (int)$r->id46; }, (array)$dups);
                        $n2del = 0;
                        if (!empty($del_ids)) {
                            $n2del = (int)$this->db->query("DELETE FROM `{$tMatM12}` WHERE id IN (".implode(',', $del_ids).")");
                        }

                        $n2upd = (int)$this->db->query($this->db->prepare(
                            "UPDATE `{$tMatM12}` SET disciplina_id=%d, ordem_pauta=2, categoria='nuclear' WHERE escola_id=%d AND disciplina_id=%d",
                            $mat_id, $eidM12, $mat46_id
                        ));

                        $tddups = $this->db->get_results($this->db->prepare(
                            "SELECT td46.id AS id46
                               FROM `{$tTDM12}` td46
                               INNER JOIN `{$tTDM12}` tdmat
                                  ON tdmat.escola_id=td46.escola_id
                                 AND tdmat.turma_id=td46.turma_id
                                 AND tdmat.disciplina_id=%d
                              WHERE td46.escola_id=%d AND td46.disciplina_id=%d",
                            $mat_id, $eidM12, $mat46_id
                        ));
                        $td_del_ids = array_map(function($r){ return (int)$r->id46; }, (array)$tddups);
                        $n3del = 0;
                        if (!empty($td_del_ids)) {
                            $n3del = (int)$this->db->query("DELETE FROM `{$tTDM12}` WHERE id IN (".implode(',', $td_del_ids).")");
                        }

                        $n3upd = (int)$this->db->query($this->db->prepare(
                            "UPDATE `{$tTDM12}` SET disciplina_id=%d WHERE escola_id=%d AND disciplina_id=%d",
                            $mat_id, $eidM12, $mat46_id
                        ));

                        // Não apagamos a disciplina antiga; apenas desactivamos para não voltar a ser usada.
                        $n4 = (int)$this->db->query($this->db->prepare(
                            "UPDATE `{$tDiscM12}` SET activo=0, sigla='MAT_ANTIGA_4_6', nome='Matemática (antiga 4-6)' WHERE escola_id=%d AND id=%d",
                            $eidM12, $mat46_id
                        ));

                        $this->db->query('COMMIT');
                        $logM12['status'] = 'migrated';
                        $logM12['notes_updated'] = $n1;
                        $logM12['matrix_deleted'] = $n2del;
                        $logM12['matrix_updated'] = $n2upd;
                        $logM12['turma_disc_deleted'] = $n3del;
                        $logM12['turma_disc_updated'] = $n3upd;
                        $logM12['discipline_disabled'] = $n4;
                    } catch (Throwable $e) {
                        $this->db->query('ROLLBACK');
                        $logM12['status'] = 'error';
                        $logM12['message'] = $e->getMessage();
                    }
                } else {
                    $logM12['status'] = 'conflict_detected_no_migration';
                    $logM12['message'] = 'Existem notas em MAT e MAT(4-6) para a mesma combinação aluno/turma/trimestre/ano. Migração automática bloqueada por segurança.';
                }
            } else {
                $logM12['message'] = 'MAT ou MAT(4-6) não encontrados, ou já consolidados.';
            }

            update_option('sige_m12_mat46_to_mat_log', $logM12);
            update_option('sige_m12_mat46_to_mat_checked', 1);
        }

        // ── M11: Fundir disciplina duplicada Ed.V (id=19) → Ed.Visual (id=18) ─
        // Historicamente o Malisa tinha duas disciplinas para Ed.Visual:
        //   • id=18 'Educação Visual'      sigla='ED.VISUAL'     categoria=nuclear
        //   • id=19 'Educação Visual (C)'  sigla='ED. V'          categoria=complementar
        //
        // Esta duplicação era um workaround para representar Ed.V como complementar
        // nas 1ª-3ª e nuclear na 6ª. Com M10, a categoria passa a viver no par
        // (classe × disciplina) da matriz - a duplicação deixa de ter sentido.
        //
        // Esta migração consolida tudo em id=18, preservando todas as notas
        // existentes e actualizando as referências em:
        //   • sige_notas                   (126 rows em produção Malisa)
        //   • sige_matriz_curricular       (3 rows)
        //   • sige_turma_disciplinas       (7 rows, incluindo 1 conflito turma=22)
        //
        // Características de segurança:
        //   1. Pre-flights: confirma que disc 19 e 18 existem exactamente como esperado
        //   2. Detecta conflitos inesperados em notas e matriz e aborta sem efeito
        //   3. Usa transacção para atomicidade (START/COMMIT/ROLLBACK)
        //   4. Filtra por escola_id para não afectar outras escolas
        //   5. Sanity check final - rollback se ficarem referências órfãs
        //   6. Escreve log detalhado em wp_options para debug
        if (!get_option('sige_m11_disciplina_19_done')) {
            $tDisc2 = "{$p}sige_disciplinas";
            $tNotas = "{$p}sige_notas";
            $tMat2  = "{$p}sige_matriz_curricular";
            $tTD    = "{$p}sige_turma_disciplinas";
            $log    = [
                'started_at' => current_time('mysql'),
                'status'     => 'pending',
            ];

            // --- Pre-flight 1: disc 19 existe e é o duplicado esperado?
            $row19 = $this->db->get_row(
                "SELECT id, escola_id, nome, sigla FROM `{$tDisc2}`
                 WHERE id = 19 AND escola_id = 1 AND sigla = 'ED. V'"
            );
            if (!$row19) {
                // Escola limpa / já migrou - marcar done silenciosamente
                $log['status'] = 'noop_already_clean';
                $log['finished_at'] = current_time('mysql');
                update_option('sige_m11_log', $log);
                update_option('sige_m11_disciplina_19_done', 1);
            } else {
                // --- Pre-flight 2: disc 18 (alvo) existe?
                $row18 = $this->db->get_row(
                    "SELECT id FROM `{$tDisc2}` WHERE id = 18 AND escola_id = 1"
                );
                if (!$row18) {
                    $log['status']      = 'abort_no_target';
                    $log['error']       = 'disciplina id=18 (alvo) não encontrada';
                    $log['finished_at'] = current_time('mysql');
                    update_option('sige_m11_log', $log);
                    error_log('SIGE M11 ABORT: ' . $log['error']);
                    // NÃO marcar done - permite retry após correcção manual
                } else {

                    // --- Pre-flight 3: conflitos de chave em notas e matriz
                    $conf_notas = (int) $this->db->get_var(
                        "SELECT COUNT(*) FROM (
                            SELECT aluno_id, trimestre, ano_lectivo, turma_id
                            FROM `{$tNotas}`
                            WHERE escola_id = 1 AND disciplina_id IN (18, 19)
                            GROUP BY aluno_id, trimestre, ano_lectivo, turma_id
                            HAVING COUNT(DISTINCT disciplina_id) = 2
                        ) x"
                    );
                    $conf_mat = (int) $this->db->get_var(
                        "SELECT COUNT(*) FROM (
                            SELECT classe
                            FROM `{$tMat2}`
                            WHERE escola_id = 1 AND disciplina_id IN (18, 19)
                            GROUP BY classe
                            HAVING COUNT(DISTINCT disciplina_id) = 2
                        ) x"
                    );
                    $log['preflight'] = [
                        'conflitos_notas'  => $conf_notas,
                        'conflitos_matriz' => $conf_mat,
                    ];

                    if ($conf_notas > 0 || $conf_mat > 0) {
                        $log['status']      = 'abort_unexpected_conflicts';
                        $log['error']       = sprintf(
                            'conflitos inesperados (notas=%d, matriz=%d)',
                            $conf_notas, $conf_mat
                        );
                        $log['finished_at'] = current_time('mysql');
                        update_option('sige_m11_log', $log);
                        error_log('SIGE M11 ABORT: ' . $log['error']);
                        // NÃO marcar done
                    } else {
                        // --- Execução atómica em transacção
                        $this->db->query('START TRANSACTION');

                        // [A] Resolver conflito em turma_disciplinas (turma já tem 18 E 19)
                        //     Mantém o row com disc=18; elimina o de disc=19.
                        $dedupe_td = (int) $this->db->query(
                            "DELETE td19 FROM `{$tTD}` td19
                             INNER JOIN `{$tTD}` td18
                                ON td18.turma_id = td19.turma_id
                               AND td18.escola_id = td19.escola_id
                               AND td18.disciplina_id = 18
                             WHERE td19.escola_id = 1
                               AND td19.disciplina_id = 19"
                        );

                        // [B] Remap 19 → 18 em todas as tabelas referenciadoras
                        //     (outras 5 tabelas verificadas no dump: 0 refs - ainda assim
                        //      corremos o UPDATE para proteger deploys futuros onde possam ter surgido)
                        $upd = [
                            'turma_disciplinas'       => (int) $this->db->query(
                                "UPDATE `{$tTD}` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                            'notas'                   => (int) $this->db->query(
                                "UPDATE `{$tNotas}` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                            'matriz_curricular'       => (int) $this->db->query(
                                "UPDATE `{$tMat2}` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                            'disciplinas_indicadores' => (int) $this->db->query(
                                "UPDATE `{$p}sige_disciplinas_indicadores` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                            'carga_horaria'           => (int) $this->db->query(
                                "UPDATE `{$p}sige_carga_horaria` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                            'horarios_turma'          => (int) $this->db->query(
                                "UPDATE `{$p}sige_horarios_turma` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                            'jardim_avaliacoes'       => (int) $this->db->query(
                                "UPDATE `{$p}sige_jardim_avaliacoes` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                            'jardim_criterios'        => (int) $this->db->query(
                                "UPDATE `{$p}sige_jardim_criterios` SET disciplina_id = 18
                                 WHERE escola_id = 1 AND disciplina_id = 19"),
                        ];

                        // [C] Eliminar a própria disciplina 19
                        $del_disc = (int) $this->db->query(
                            "DELETE FROM `{$tDisc2}`
                             WHERE id = 19 AND escola_id = 1 AND sigla = 'ED. V'"
                        );

                        // [D] Sanity check - confirmar que não ficaram órfãs
                        $orfas = (int) $this->db->get_var(
                            "SELECT
                                (SELECT COUNT(*) FROM `{$tNotas}`  WHERE disciplina_id = 19) +
                                (SELECT COUNT(*) FROM `{$tMat2}`   WHERE disciplina_id = 19) +
                                (SELECT COUNT(*) FROM `{$tTD}`     WHERE disciplina_id = 19) +
                                (SELECT COUNT(*) FROM `{$tDisc2}`  WHERE id = 19)
                             AS total"
                        );

                        $log['dedupe_td']         = $dedupe_td;
                        $log['updated']           = $upd;
                        $log['deleted_disciplina']= $del_disc;
                        $log['orfas_residuais']   = $orfas;

                        if ($orfas > 0) {
                            $this->db->query('ROLLBACK');
                            $log['status']      = 'rollback';
                            $log['error']       = "{$orfas} referências órfãs após merge";
                            $log['finished_at'] = current_time('mysql');
                            update_option('sige_m11_log', $log);
                            error_log('SIGE M11 ROLLBACK: ' . $log['error']);
                            // NÃO marcar done - investigar
                        } else {
                            $this->db->query('COMMIT');
                            $log['status']      = 'success';
                            $log['finished_at'] = current_time('mysql');
                            update_option('sige_m11_log', $log);
                            update_option('sige_m11_disciplina_19_done', 1);
                        }
                    }
                }
            }
        }
        // ── M13: Correcção preventiva da Educação Visual auxiliar (4ª/5ª) ────
        // Idempotente: não remove notas nem disciplinas; apenas corrige categoria/ordem.
        if (!get_option('sige_m13_edvisual_auxiliar_checked')) {
            $tDiscV13 = "{$p}sige_disciplinas";
            $tMatV13  = "{$p}sige_matriz_curricular";
            $tTDV13   = "{$p}sige_turma_disciplinas";
            $ed_visual_ids = $this->db->get_col("SELECT id FROM `{$tDiscV13}` WHERE escola_id=1 AND (sigla IN ('ED.VISUAL','ED. VISUAL','ED. V') OR nome LIKE '%Visual%')");
            if (!empty($ed_visual_ids)) {
                $ids_sql = implode(',', array_map('intval', $ed_visual_ids));
                $this->db->query("UPDATE `{$tMatV13}` SET categoria='complementar', ordem_pauta=6 WHERE escola_id=1 AND classe IN ('4ª','5ª','4a','5a') AND disciplina_id IN ({$ids_sql})");
                $this->db->query("UPDATE `{$tMatV13}` SET categoria='nuclear', ordem_pauta=6 WHERE escola_id=1 AND classe IN ('6ª','6a') AND disciplina_id IN ({$ids_sql})");
                $this->db->query("UPDATE `{$tTDV13}` td INNER JOIN `{$p}sige_turmas` t ON t.id=td.turma_id AND t.escola_id=td.escola_id SET td.categoria='complementar', td.ordem_pauta=6 WHERE td.escola_id=1 AND t.classe IN ('4ª','5ª','4a','5a') AND td.disciplina_id IN ({$ids_sql})");
                $this->db->query("UPDATE `{$tTDV13}` td INNER JOIN `{$p}sige_turmas` t ON t.id=td.turma_id AND t.escola_id=td.escola_id SET td.categoria='nuclear', td.ordem_pauta=6 WHERE td.escola_id=1 AND t.classe IN ('6ª','6a') AND td.disciplina_id IN ({$ids_sql})");
            }
            update_option('sige_m13_edvisual_auxiliar_checked', 1);
        }

        // ── M14: Fase 2 - Arquitectura multi-escola / tenant-aware schema ───
        // Objectivo: remover índices únicos globais em tabelas escolares e
        // reforçar escola_id por backfill a partir de relações canónicas.
        // Não altera fórmulas, valores, notas, pagamentos ou regras funcionais.
        $this->run_phase2_tenant_schema_migration();

    }

    /**
     * v12.11.9.27 - Fase 2/P2.1: consolidação multi-escola.
     */
    private function run_phase2_tenant_schema_migration(): void {
        $p = $this->p;
        $log = [
            'version'    => defined('SIGE_VERSION') ? SIGE_VERSION : '12.11.9.27',
            'schema'     => self::SCHEMA_VERSION,
            'started_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'unique_indexes' => [],
            'backfill' => [],
            'tenant_indexes' => [],
        ];

        // 1) Índices únicos tenant-aware. O índice novo é criado antes do antigo
        // ser removido; se houver duplicados, o índice antigo fica intacto.
        $log['unique_indexes']['anos_lectivos'] = $this->replace_unique_index_if_safe(
            "{$p}sige_anos_lectivos",
            'ano_lectivo',
            'uniq_escola_ano_lectivo',
            ['escola_id', 'ano_lectivo']
        );
        $log['unique_indexes']['fin_pacotes'] = $this->replace_unique_index_if_safe(
            "{$p}sige_fin_pacotes",
            'codigo_unico',
            'uniq_pacote_escola_codigo_ano',
            ['escola_id', 'codigo', 'ano_letivo']
        );
        $log['unique_indexes']['fin_pagamentos_anuais'] = $this->replace_unique_index_if_safe(
            "{$p}sige_fin_pagamentos_anuais",
            'aluno_ano_unico',
            'uniq_pag_anual_escola_aluno_ano',
            ['escola_id', 'aluno_id', 'ano_letivo']
        );
        $log['unique_indexes']['acta_cumprimento_programas'] = $this->replace_unique_index_if_safe(
            "{$p}sige_acta_cumprimento_programas",
            'uq_acta_disc',
            'uq_acta_disc_tenant',
            ['escola_id', 'acta_id', 'disciplina_id', 'disciplina_nome']
        );
        $log['unique_indexes']['curriculum_levels'] = $this->replace_unique_index_if_safe(
            "{$p}sige_curriculum_levels",
            'uniq_curr_level_profile_code',
            'uniq_curr_level_school_profile_code',
            ['escola_id', 'profile_id', 'code']
        );
        $log['unique_indexes']['curriculum_grades'] = $this->replace_unique_index_if_safe(
            "{$p}sige_curriculum_grades",
            'uniq_curr_grade_profile_code',
            'uniq_curr_grade_school_profile_code',
            ['escola_id', 'profile_id', 'code']
        );
        $log['unique_indexes']['curriculum_subjects'] = $this->replace_unique_index_if_safe(
            "{$p}sige_curriculum_subjects",
            'uniq_curr_subject_grade_disc',
            'uniq_curr_subject_school_grade_disc',
            ['escola_id', 'profile_id', 'grade_id', 'disciplina_id']
        );
        $log['unique_indexes']['curriculum_periods'] = $this->replace_unique_index_if_safe(
            "{$p}sige_curriculum_periods",
            'uniq_curr_period_profile_code',
            'uniq_curr_period_school_profile_code',
            ['escola_id', 'profile_id', 'code']
        );

        // 2) Backfill de escola_id a partir das relações canónicas.
        // Corrige dados legados que ficaram em escola_id=1 por default, quando o
        // objecto pai indica outra escola. Cada operação é idempotente.
        $pairs = [
            ['sige_turma_alunos', 'aluno_id', 'sige_alunos'],
            ['sige_matriculas', 'aluno_id', 'sige_alunos'],
            ['sige_notas', 'aluno_id', 'sige_alunos'],
            ['sige_disciplinas_indicadores', 'disciplina_id', 'sige_disciplinas'],
            ['sige_matriz_curricular', 'disciplina_id', 'sige_disciplinas'],
            ['sige_turma_disciplinas', 'turma_id', 'sige_turmas'],
            ['sige_carga_horaria', 'turma_id', 'sige_turmas'],
            ['sige_horarios_turma', 'turma_id', 'sige_turmas'],
            ['sige_acessos', 'aluno_id', 'sige_alunos'],
            ['sige_jardim_criterios', 'disciplina_id', 'sige_disciplinas'],
            ['sige_jardim_criterios_respostas', 'aluno_id', 'sige_alunos'],
            ['sige_jardim_avaliacoes', 'aluno_id', 'sige_alunos'],
            ['sige_jardim_diario', 'aluno_id', 'sige_alunos'],
            ['sige_jardim_saude', 'aluno_id', 'sige_alunos'],
            ['sige_transporte_alunos', 'aluno_id', 'sige_alunos'],
            ['sige_fin_lancamentos', 'aluno_id', 'sige_alunos'],
            ['sige_fin_pagamentos', 'lancamento_id', 'sige_fin_lancamentos'],
            ['sige_fin_creditos', 'aluno_id', 'sige_alunos'],
            ['sige_fin_historico_precos', 'servico_id', 'sige_fin_servicos'],
            ['sige_fin_pacote_itens', 'pacote_id', 'sige_fin_pacotes'],
            ['sige_fin_contactos_cobranca', 'aluno_id', 'sige_alunos'],
            ['sige_fin_planos_pagamento', 'aluno_id', 'sige_alunos'],
            ['sige_fin_planos_prestacoes', 'plano_id', 'sige_fin_planos_pagamento'],
            ['sige_whatsapp_queue', 'aluno_id', 'sige_alunos'],
            ['sige_presencas_excecoes', 'aluno_id', 'sige_alunos'],
            ['sige_financeiro', 'aluno_id', 'sige_alunos'],
            ['sige_acta_conselho_notas', 'turma_id', 'sige_turmas'],
            ['sige_acta_presencas', 'acta_id', 'sige_acta_conselho_notas'],
            ['sige_acta_nota_votada', 'acta_id', 'sige_acta_conselho_notas'],
            ['sige_acta_cumprimento_programas', 'acta_id', 'sige_acta_conselho_notas'],
            ['sige_curriculum_levels', 'profile_id', 'sige_curriculum_profiles'],
            ['sige_curriculum_grades', 'profile_id', 'sige_curriculum_profiles'],
            ['sige_curriculum_subjects', 'profile_id', 'sige_curriculum_profiles'],
            ['sige_curriculum_periods', 'profile_id', 'sige_curriculum_profiles'],
            ['sige_curriculum_assessment_rules', 'profile_id', 'sige_curriculum_profiles'],
            ['sige_curriculum_document_templates', 'profile_id', 'sige_curriculum_profiles'],
            ['sige_curriculum_school_settings', 'active_profile_id', 'sige_curriculum_profiles'],
        ];
        foreach ($pairs as $pair) {
            [$child, $fk, $parent] = $pair;
            $key = $child . '.' . $fk;
            $log['backfill'][$key] = $this->backfill_escola_id_from_parent(
                "{$p}{$child}",
                $fk,
                "{$p}{$parent}"
            );
        }
        // Pagamentos também podem existir sem lançamento associado; nesse caso
        // inferir pelo aluno é mais seguro que manter default legado.
        $log['backfill']['sige_fin_pagamentos.aluno_id_fallback'] = $this->backfill_escola_id_from_parent(
            "{$p}sige_fin_pagamentos",
            'aluno_id',
            "{$p}sige_alunos"
        );
        $log['backfill']['sige_transporte_alunos.rota_id_fallback'] = $this->backfill_escola_id_from_parent(
            "{$p}sige_transporte_alunos",
            'rota_id',
            "{$p}sige_transporte_rotas"
        );

        // 3) Índices compostos tenant-aware para consultas e relatórios.
        $tenant_indexes = [
            ["{$p}sige_anos_lectivos", 'idx_phase2_ano_escola_status', '(escola_id, ano_lectivo, status)'],
            ["{$p}sige_turmas", 'idx_phase2_turmas_escola_ano_classe', '(escola_id, ano_lectivo, classe)'],
            ["{$p}sige_turma_alunos", 'idx_phase2_turma_aluno_school', '(escola_id, turma_id, aluno_id)'],
            ["{$p}sige_matriculas", 'idx_phase2_mat_aluno_ano_school', '(escola_id, aluno_id, ano_lectivo)'],
            ["{$p}sige_notas", 'idx_phase2_notas_turma_periodo', '(escola_id, turma_id, ano_lectivo, trimestre)'],
            ["{$p}sige_notas", 'idx_phase2_notas_aluno_ano', '(escola_id, aluno_id, ano_lectivo)'],
            ["{$p}sige_matriz_curricular", 'idx_phase2_matriz_school_classe_disc', '(escola_id, classe, disciplina_id)'],
            ["{$p}sige_turma_disciplinas", 'idx_phase2_td_school_turma_disc', '(escola_id, turma_id, disciplina_id)'],
            ["{$p}sige_fin_pacotes", 'idx_phase2_pacotes_school_classe_ano', '(escola_id, classe, ano_letivo, ativo)'],
            ["{$p}sige_fin_pagamentos_anuais", 'idx_phase2_pag_anual_school_aluno', '(escola_id, aluno_id, ano_letivo, status)'],
            ["{$p}sige_whatsapp_queue", 'idx_phase2_wpp_school_status', '(escola_id, status, tentativas, criado_em)'],
            ["{$p}sige_acta_cumprimento_programas", 'idx_phase2_acta_cump_school_acta', '(escola_id, acta_id)'],
            ["{$p}sige_curriculum_profiles", 'idx_phase2_curr_profile_school_status', '(escola_id, status)'],
        ];
        foreach ($tenant_indexes as $idx) {
            [$table, $name, $definition] = $idx;
            $before = $this->index_exists($table, $name);
            $this->add_index_if_missing($table, $name, $definition);
            $log['tenant_indexes'][$name] = $before ? 'already_exists' : ($this->index_exists($table, $name) ? 'created' : 'not_created');
        }

        // 4) Auditoria resumida gravada em option para diagnóstico futuro.
        $log['schema_audit'] = $this->phase2_collect_tenant_schema_audit();
        $log['finished_at'] = function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s');
        update_option('sige_phase2_tenant_schema_audit_v12_11_9_27', $log, false);
    }

    // ========================================================================
    // VIEWS
    // ========================================================================

    private function create_views(): void {
        $p = $this->p;

        // Limpar views antigas sem prefixo (legado)
        $this->db->query("DROP VIEW IF EXISTS sige_v_reconciliacao");
        $this->db->query("DROP VIEW IF EXISTS v_pacotes_disponiveis");
        $this->db->query("DROP VIEW IF EXISTS v_servicos_por_classe");

        // sige_v_reconciliacao - conciliação financeira
        $this->db->query("CREATE OR REPLACE VIEW {$p}sige_v_reconciliacao AS
            SELECT
                l.id AS lancamento_id,
                l.escola_id,
                l.aluno_id,
                l.descricao,
                l.valor_pago AS vp_lancamento,
                COALESCE(SUM(pg.valor_pago), 0) AS vp_pagamentos,
                ROUND(ABS(l.valor_pago - COALESCE(SUM(pg.valor_pago), 0)), 2) AS divergencia,
                l.status,
                l.mes_referencia
            FROM {$p}sige_fin_lancamentos l
            LEFT JOIN {$p}sige_fin_pagamentos pg ON pg.lancamento_id = l.id
            WHERE l.status <> 'cancelado'
            GROUP BY l.id
            HAVING divergencia > 0.01
            ORDER BY divergencia DESC
        ");

        // v_pacotes_disponiveis
        $this->db->query("CREATE OR REPLACE VIEW {$p}v_pacotes_disponiveis AS
            SELECT
                p.id, p.escola_id, p.nome, p.codigo, p.classe, p.tipo,
                p.valor_total_servicos, p.valor_pacote,
                ROUND((p.valor_total_servicos - p.valor_pacote) / p.valor_total_servicos * 100, 2)
                    AS desconto_percentual,
                (SELECT COUNT(*) FROM {$p}sige_fin_pacote_itens pi WHERE pi.pacote_id = p.id)
                    AS total_servicos,
                p.ano_letivo
            FROM {$p}sige_fin_pacotes p
            WHERE p.ativo = 1
        ");

        // v_servicos_por_classe
        $this->db->query("CREATE OR REPLACE VIEW {$p}v_servicos_por_classe AS
            SELECT
                s.id AS servico_id,
                s.escola_id,
                s.nome,
                s.tipo,
                s.valor,
                s.classe,
                s.categoria,
                s.ativo,
                s.recorrente
            FROM {$p}sige_fin_servicos s
            WHERE s.ativo = 1
        ");
    }

    // ========================================================================
    // SEED DEFAULTS
    // ========================================================================

    private function seed_defaults(): void {
        $p = $this->p;

        // Escola default (id=1) se não existir
        $exists = $this->db->get_var("SELECT COUNT(*) FROM {$p}sige_escolas");
        if ((int)$exists === 0) {
            $this->db->insert("{$p}sige_escolas", [
                'nome'  => 'Minha Escola',
                'slug'  => 'escola',
                'plano' => 'completo',
            ]);
        }

        // [Sprint 2 · M2] Data-corte MINED para estatística demográfica da ACTA.
        // Formato MM-DD (mês-dia); default 03-03 (oficial MINED-MZ para censo
        // anual de matriculados). Editável via Configurações.
        if (get_option('sige_mined_data_corte', null) === null) {
            add_option('sige_mined_data_corte', '03-03');
        }
    }

    // ========================================================================
    // HELPERS
    // ========================================================================

    /**
     * Adiciona coluna se não existir.
     */
    private function add_column_if_missing(string $table, string $column, string $definition): void {
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND COLUMN_NAME = %s",
            $table, $column
        ));
        if ((int)$exists === 0) {
            $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$column}` {$definition}");
        }
    }

    /**
     * Garante que uma coluna VARCHAR tem pelo menos $min_size chars.
     */
    private function ensure_column_size(string $table, string $column, int $min_size): void {
        $col = $this->db->get_row("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        if (!$col) return;
        // Extrair tamanho actual: "varchar(7)" → 7
        if (preg_match('/varchar\((\d+)\)/i', $col->Type, $m)) {
            if ((int)$m[1] < $min_size) {
                $this->db->query(
                    "ALTER TABLE `{$table}` MODIFY COLUMN `{$column}` VARCHAR({$min_size}) DEFAULT NULL"
                );
            }
        }
    }

    /**
     * Adiciona índice se não existir. Usado para optimizações de performance
     * sem alterar regras de negócio nem dados existentes.
     */
    private function add_index_if_missing(string $table, string $index, string $definition): void {
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND INDEX_NAME = %s",
            $table, $index
        ));
        if ((int)$exists === 0) {
            $this->db->query("ALTER TABLE `{$table}` ADD KEY `{$index}` {$definition}");
        }
    }

    /**
     * v12.11.9.1 - Adiciona UNIQUE KEY apenas quando for seguro.
     *
     * Esta migração é deliberadamente conservadora: em bases antigas com dados
     * duplicados, não força limpeza nem altera fichas de colaboradores. Apenas
     * regista o estado e deixa a base funcional, evitando fatal errors e falhas
     * de ALTER TABLE durante o carregamento do WordPress.
     */
    private function add_unique_index_if_no_duplicates(string $table, string $index, string $columns_csv, string $definition): void {
        $table_exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s",
            $table
        ));

        if ((int) $table_exists === 0) {
            return;
        }

        $exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND INDEX_NAME = %s",
            $table,
            $index
        ));

        if ((int) $exists > 0) {
            return;
        }

        // Actualmente usado para sige_professores(escola_id, email). Não tenta
        // criar índice único se houver emails duplicados na mesma escola.
        if (trim(strtolower($columns_csv)) === 'escola_id, email') {
            $duplicates = $this->db->get_var(
                "SELECT COUNT(*) FROM (
                    SELECT escola_id, email
                    FROM `{$table}`
                    WHERE email IS NOT NULL
                    GROUP BY escola_id, email
                    HAVING COUNT(*) > 1
                ) AS dup"
            );

            if ((int) $duplicates > 0) {
                update_option('sige_professores_unique_email_pending_cleanup', 1, false);
                return;
            }
        }

        $result = $this->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$index}` {$definition}");

        if ($result === false) {
            update_option('sige_last_unique_index_error_' . sanitize_key($index), (string) $this->db->last_error, false);
        } else {
            delete_option('sige_last_unique_index_error_' . sanitize_key($index));
            delete_option('sige_professores_unique_email_pending_cleanup');
        }
    }

    /**
     * Verifica se uma tabela existe na base actual.
     */
    private function table_exists(string $table): bool {
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s",
            $table
        ));
        return (int)$exists > 0;
    }

    /**
     * Verifica se uma coluna existe numa tabela.
     */
    private function column_exists(string $table, string $column): bool {
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND COLUMN_NAME = %s",
            $table,
            $column
        ));
        return (int)$exists > 0;
    }

    /**
     * Verifica se um índice existe numa tabela.
     */
    private function index_exists(string $table, string $index): bool {
        $exists = $this->db->get_var($this->db->prepare(
            "SELECT COUNT(*) FROM information_schema.STATISTICS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = %s
               AND INDEX_NAME = %s",
            $table,
            $index
        ));
        return (int)$exists > 0;
    }

    /**
     * Normaliza nomes de colunas usados internamente nas migrations.
     */
    private function sanitize_identifier_list(array $columns): array {
        $safe = [];
        foreach ($columns as $column) {
            $column = preg_replace('/[^A-Za-z0-9_]/', '', (string)$column);
            if ($column !== '') {
                $safe[] = $column;
            }
        }
        return $safe;
    }

    /**
     * Conta grupos duplicados para uma combinação de colunas.
     * Linhas com NULL em qualquer coluna são ignoradas para respeitar a semântica
     * de UNIQUE KEY do MySQL, que permite múltiplos NULL.
     */
    private function count_duplicate_groups_for_columns(string $table, array $columns): int {
        if (!$this->table_exists($table)) return 0;
        $columns = $this->sanitize_identifier_list($columns);
        if (empty($columns)) return 0;
        foreach ($columns as $column) {
            if (!$this->column_exists($table, $column)) return 0;
        }
        $cols = implode(', ', array_map(static fn($c) => "`{$c}`", $columns));
        $not_null = implode(' AND ', array_map(static fn($c) => "`{$c}` IS NOT NULL", $columns));
        $sql = "SELECT COUNT(*) FROM (
            SELECT {$cols}
            FROM `{$table}`
            WHERE {$not_null}
            GROUP BY {$cols}
            HAVING COUNT(*) > 1
        ) AS dup";
        return (int)$this->db->get_var($sql);
    }

    /**
     * Adiciona UNIQUE KEY genérica apenas quando não há duplicados.
     */
    private function add_unique_index_for_columns_if_safe(string $table, string $index, array $columns): string {
        if (!$this->table_exists($table)) return 'table_missing';
        if ($this->index_exists($table, $index)) return 'already_exists';
        $columns = $this->sanitize_identifier_list($columns);
        if (empty($columns)) return 'invalid_columns';
        foreach ($columns as $column) {
            if (!$this->column_exists($table, $column)) return 'column_missing:' . $column;
        }
        $duplicates = $this->count_duplicate_groups_for_columns($table, $columns);
        if ($duplicates > 0) {
            update_option('sige_phase2_unique_pending_' . sanitize_key($index), [
                'table' => $table,
                'index' => $index,
                'columns' => $columns,
                'duplicate_groups' => $duplicates,
                'checked_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            ], false);
            return 'blocked_duplicates:' . $duplicates;
        }
        $cols = implode(', ', array_map(static fn($c) => "`{$c}`", $columns));
        $result = $this->db->query("ALTER TABLE `{$table}` ADD UNIQUE KEY `{$index}` ({$cols})");
        if ($result === false) {
            update_option('sige_phase2_unique_error_' . sanitize_key($index), (string)$this->db->last_error, false);
            return 'add_failed:' . (string)$this->db->last_error;
        }
        delete_option('sige_phase2_unique_pending_' . sanitize_key($index));
        delete_option('sige_phase2_unique_error_' . sanitize_key($index));
        return 'created';
    }

    /**
     * Substitui um UNIQUE KEY global por outro scoped por escola_id, de forma segura.
     */
    private function replace_unique_index_if_safe(string $table, string $old_index, string $new_index, array $new_columns): array {
        $status = [
            'table' => $table,
            'old_index' => $old_index,
            'new_index' => $new_index,
            'new_columns' => $new_columns,
            'old_exists_before' => false,
            'new_status' => 'not_started',
            'old_drop_status' => 'not_attempted',
        ];
        if (!$this->table_exists($table)) {
            $status['new_status'] = 'table_missing';
            return $status;
        }
        $status['old_exists_before'] = $this->index_exists($table, $old_index);
        $status['new_status'] = $this->add_unique_index_for_columns_if_safe($table, $new_index, $new_columns);
        if ($this->index_exists($table, $new_index) && $this->index_exists($table, $old_index)) {
            $drop = $this->db->query("ALTER TABLE `{$table}` DROP INDEX `{$old_index}`");
            $status['old_drop_status'] = ($drop === false) ? ('drop_failed:' . (string)$this->db->last_error) : 'dropped';
        } elseif (!$this->index_exists($table, $old_index)) {
            $status['old_drop_status'] = 'old_missing';
        } else {
            $status['old_drop_status'] = 'kept_because_new_missing';
        }
        $status['old_exists_after'] = $this->index_exists($table, $old_index);
        $status['new_exists_after'] = $this->index_exists($table, $new_index);
        return $status;
    }

    /**
     * Backfill de escola_id de uma tabela filha a partir de uma tabela pai.
     */
    private function backfill_escola_id_from_parent(string $child_table, string $fk_column, string $parent_table, string $parent_pk = 'id'): int {
        if (!$this->table_exists($child_table) || !$this->table_exists($parent_table)) return 0;
        $fk_column = preg_replace('/[^A-Za-z0-9_]/', '', $fk_column);
        $parent_pk = preg_replace('/[^A-Za-z0-9_]/', '', $parent_pk);
        if ($fk_column === '' || $parent_pk === '') return 0;
        if (!$this->column_exists($child_table, 'escola_id') || !$this->column_exists($parent_table, 'escola_id')) return 0;
        if (!$this->column_exists($child_table, $fk_column) || !$this->column_exists($parent_table, $parent_pk)) return 0;

        $sql = "UPDATE `{$child_table}` c
                INNER JOIN `{$parent_table}` p ON p.`{$parent_pk}` = c.`{$fk_column}`
                SET c.`escola_id` = p.`escola_id`
                WHERE c.`{$fk_column}` IS NOT NULL
                  AND c.`{$fk_column}` > 0
                  AND p.`escola_id` IS NOT NULL
                  AND p.`escola_id` > 0
                  AND (c.`escola_id` IS NULL OR c.`escola_id` <= 0 OR c.`escola_id` <> p.`escola_id`)";
        $result = $this->db->query($sql);
        if ($result === false) {
            update_option('sige_phase2_backfill_error_' . sanitize_key($child_table . '_' . $fk_column), (string)$this->db->last_error, false);
            return 0;
        }
        return (int)$result;
    }

    /**
     * Auditoria pós-migração: tabelas sem escola_id e UNIQUE KEYs ainda globais.
     */
    private function phase2_collect_tenant_schema_audit(): array {
        $prefix = $this->p . 'sige\_%';
        $tables = $this->db->get_col($this->db->prepare(
            "SELECT TABLE_NAME FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME LIKE %s
             ORDER BY TABLE_NAME",
            $prefix
        ));
        $missing_school = [];
        $unique_without_school = [];
        $allow_without_school = [
            $this->p . 'sige_escolas.slug',
            $this->p . 'sige_escolas.subdominio',
        ];
        foreach ((array)$tables as $table) {
            $has_school = $this->column_exists($table, 'escola_id');
            if (!$has_school && $table !== $this->p . 'sige_escolas') {
                $missing_school[] = $table;
            }
            if ($has_school) {
                $indexes = $this->db->get_results($this->db->prepare(
                    "SELECT INDEX_NAME, GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX SEPARATOR ',') AS cols
                     FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = DATABASE()
                       AND TABLE_NAME = %s
                       AND NON_UNIQUE = 0
                       AND INDEX_NAME <> 'PRIMARY'
                     GROUP BY INDEX_NAME",
                    $table
                ), ARRAY_A);
                foreach ((array)$indexes as $idx) {
                    $cols = (string)($idx['cols'] ?? '');
                    $key = $table . '.' . (string)($idx['INDEX_NAME'] ?? '');
                    if (strpos(',' . $cols . ',', ',escola_id,') === false && !in_array($key, $allow_without_school, true)) {
                        $unique_without_school[] = [
                            'table' => $table,
                            'index' => (string)$idx['INDEX_NAME'],
                            'columns' => $cols,
                        ];
                    }
                }
            }
        }
        return [
            'checked_at' => function_exists('current_time') ? current_time('mysql') : gmdate('Y-m-d H:i:s'),
            'tables_checked' => count((array)$tables),
            'tables_missing_escola_id' => $missing_school,
            'unique_indexes_without_escola_id' => $unique_without_school,
        ];
    }


} // end class SIGE_Migration
