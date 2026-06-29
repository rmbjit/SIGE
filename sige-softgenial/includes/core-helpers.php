<?php
/**
 * SIGE SoftGenial - Core Helpers
 * Ficheiro: includes/core-helpers.php
 * 
 * Funções auxiliares essenciais usadas em todo o plugin.
 * Carregado primeiro, antes de todos os outros módulos.
 * 
 * @since 10.0
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// ANO LECTIVO ACTUAL (fonte única de verdade)
// Lê da tabela sige_config → campo ano_lectivo
// Fallback: ano civil actual (date('Y'))
// ============================================================================
if (!function_exists('sige_ano_lectivo_atual')) {
    function sige_ano_lectivo_atual(): int {
        static $_ano = null;
        if ($_ano !== null) return $_ano;
        
        global $wpdb;
        $tC = $wpdb->prefix . 'sige_config';
        
        // Verificar se a tabela e coluna existem antes de consultar
        $col_exists = $wpdb->get_var("SELECT COUNT(*) FROM information_schema.COLUMNS 
            WHERE TABLE_SCHEMA = DATABASE() 
              AND TABLE_NAME = '{$tC}' 
              AND COLUMN_NAME = 'ano_lectivo'");
        
        if ((int)$col_exists > 0) {
            $eid = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;
            $val = $wpdb->get_var($wpdb->prepare(
                "SELECT ano_lectivo FROM {$tC} WHERE ano_lectivo > 0 AND escola_id = %d ORDER BY id DESC LIMIT 1", $eid
            ));
            if ($val && (int)$val > 2000) {
                $_ano = (int)$val;
                return $_ano;
            }
        }
        
        $_ano = (int)wp_date('Y');
        return $_ano;
    }
}

// ============================================================================
// VERIFICAÇÃO DE ROLES
// ============================================================================
if (!function_exists('sige_is_portal_role')) {
    /**
     * Verifica se o utilizador tem role de portal (aluno/encarregado)
     */
    function sige_is_portal_role(WP_User $user): bool {
        $portal_roles = ['sige_aluno', 'sige_encarregado', 'subscriber'];
        foreach ($portal_roles as $r) {
            if (in_array($r, (array)$user->roles, true)) return true;
        }
        return false;
    }
}

if (!function_exists('sige_is_staff_role')) {
    /**
     * Verifica se o utilizador tem role de staff (funcionário da escola)
     */
    function sige_is_staff_role(WP_User $user): bool {
        // Nota: 'administrator' (WP puro) NÃO é staff - é o provider do sistema
        $staff_roles = [
            'sige_admin_ti', 'sige_admin', 'sige_director', 'sige_pedagogico',
            'sige_secretaria_geral', 'sige_secretario', 'sige_assistente',
            'sige_financeiro', 'sige_professor', 'sige_educador',
            'sige_gestor_rh', 'sige_motorista', 'sige_limpeza', 'sige_recepcao', 'sige_guarda'
        ];
        foreach ($staff_roles as $r) {
            if (in_array($r, (array)$user->roles, true)) return true;
        }
        return false;
    }
}

if (!function_exists('sige_utilizador_e_staff')) {
    /**
     * Verifica se o utilizador actual é staff
     */
    function sige_utilizador_e_staff(): bool {
        if (!is_user_logged_in()) return false;
        $u = wp_get_current_user();
        return sige_is_staff_role($u);
    }
}

// ============================================================================
// IDENTIFICAÇÃO DE ALUNO
// ============================================================================
if (!function_exists('sige_get_aluno_id_do_utilizador')) {
    /**
     * Obtém o ID do aluno associado ao utilizador actual
     */
    function sige_get_aluno_id_do_utilizador(): int {
        if (!is_user_logged_in()) return 0;
        $u = wp_get_current_user();
        $meta = get_user_meta($u->ID, 'sige_aluno_id', true);
        $aluno_id = $meta ? (int)$meta : 0;
        if ($aluno_id <= 0) return 0;

        // Fase 1: validar que o aluno associado pertence à escola corrente.
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($eid <= 0) return 0;
        $t = $wpdb->prefix . 'sige_alunos';
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($t)));
        if ($exists === $t) {
            $ok = (int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(1) FROM {$t} WHERE id=%d AND escola_id=%d LIMIT 1", $aluno_id, $eid));
            if ($ok <= 0) return 0;
        }
        return $aluno_id;
    }
}

if (!function_exists('sige_get_aluno_id_do_pagamento')) {
    /**
     * Obtém o ID do aluno a partir de um pagamento
     */
    function sige_get_aluno_id_do_pagamento(int $pagamento_id): int {
        global $wpdb;
        $t = $wpdb->prefix . 'sige_fin_pagamentos';
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($pagamento_id <= 0 || $eid <= 0) return 0;
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT aluno_id FROM {$t} WHERE id = %d AND escola_id = %d", $pagamento_id, $eid
        ));
    }
}

// ============================================================================
// CONFIGURAÇÃO FINANCEIRA (cache por request)
// ============================================================================
if (!function_exists('sige_fin_cfg')) {
    /**
     * Devolve a linha de sige_fin_configuracoes para o ano lectivo actual.
     * Resultado cacheado em static para evitar queries repetidas.
     */
    function sige_fin_cfg(): ?object {
        static $_cfg = null;
        if ($_cfg !== null) return $_cfg;

        global $wpdb;
        $t   = $wpdb->prefix . 'sige_fin_configuracoes';
        $ano = function_exists('sige_ano_lectivo_atual') ? sige_ano_lectivo_atual() : (int)wp_date('Y');
        $eid = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;

        // Verificar se a tabela existe antes de consultar
        if ($wpdb->get_var("SHOW TABLES LIKE '{$t}'") !== $t) return null;

        $_cfg = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$t} WHERE ano_letivo = %d AND escola_id = %d LIMIT 1", $ano, $eid
        ));
        return $_cfg;
    }
}

// ============================================================================
// MOEDA (símbolo dinâmico)
// ============================================================================
if (!function_exists('sige_moeda')) {
    /**
     * Devolve o símbolo da moeda configurada.
     * Default: 'MT' (Metical moçambicano).
     */
    function sige_moeda(): string {
        $cfg = sige_fin_cfg();
        return ($cfg && !empty($cfg->moeda_simbolo)) ? $cfg->moeda_simbolo : 'MT';
    }
}

// ============================================================================
// TELEFONE (normalização dinâmica)
// ============================================================================
if (!function_exists('sige_telefone_normalizar')) {
    /**
     * Normaliza um número de telefone:
     *   - Remove tudo que não seja dígito
     *   - Se tiver exactamente N dígitos (default 9), prepõe prefixo (default 258)
     *
     * @param string|int $raw  Número em qualquer formato
     * @return string          Número normalizado (apenas dígitos) ou vazio
     */
    function sige_telefone_normalizar($raw): string {
        $numero = preg_replace('/[^0-9]/', '', (string)$raw);
        if (empty($numero)) return '';

        $cfg     = sige_fin_cfg();
        $prefixo = ($cfg && !empty($cfg->prefixo_telefone)) ? $cfg->prefixo_telefone : '258';
        $digitos = ($cfg && !empty($cfg->formato_telefone_digitos)) ? (int)$cfg->formato_telefone_digitos : 9;

        if (strlen($numero) === $digitos) {
            $numero = $prefixo . $numero;
        }
        return $numero;
    }
}

// ============================================================================
// SERVIÇO RECORRENTE (usa coluna BD em vez de hardcode)
// ============================================================================
if (!function_exists('sige_servico_eh_recorrente')) {
    /**
     * Verifica se um serviço é recorrente (mensal) ou avulso.
     * Usa a coluna `recorrente` da tabela sige_fin_servicos.
     * Fallback: mensalidade/transporte = recorrente, resto = avulso.
     *
     * @param object|array $servico  Objecto/array do serviço (precisa de ->recorrente ou ->tipo)
     * @return bool
     */
    function sige_servico_eh_recorrente($servico): bool {
        $s = (object)$servico;
        // Se a coluna recorrente existe no objecto, usar directamente
        if (isset($s->recorrente)) {
            return (bool)(int)$s->recorrente;
        }
        // Fallback: lógica original por tipo
        $tipo = strtolower(trim((string)($s->tipo ?? '')));
        return in_array($tipo, ['mensalidade', 'transporte', 'ingles', 'almoco', 'pequeno_almoco', 'estudos'], true);
    }
}


// ============================================================================
// ESTADO OPERACIONAL DO ALUNO / MATRÍCULA - v12.10.94
// ============================================================================
// Merge das correcções Malisa v12.9.91-v12.9.93 para a linha Produto PRO.
// Regra: transferido/desistente/inactivo deixa de ser operacionalmente activo,
// mas histórico académico/financeiro é preservado.
if (!function_exists('sige_sql_alias_safe')) {
    function sige_sql_alias_safe(string $alias): string {
        $alias = trim($alias);
        return preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias) ? $alias : 'a';
    }
}

if (!function_exists('sige_status_operacional_normalizar')) {
    function sige_status_operacional_normalizar($status): string {
        $valor = trim(strtolower((string)$status));
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

if (!function_exists('sige_aluno_status_canonico')) {
    /**
     * Canoniza o status de ALUNO na ESCRITA (grafia pré-AO90 da casa).
     * A LEITURA continua a aceitar ambas as grafias (compatibilidade com
     * dados históricos); este helper garante que dados NOVOS convergem.
     */
    function sige_aluno_status_canonico(string $status): string {
        $s = strtolower(trim($status));
        $s = str_replace(['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'], ['a','a','a','a','e','e','i','o','o','o','u','c'], $s);
        $mapa = [
            'ativo' => 'activo', 'ativa' => 'activo', 'activa' => 'activo',
            'inativo' => 'inactivo', 'inativa' => 'inactivo', 'inactiva' => 'inactivo',
            'transferida' => 'transferido', 'transferido saida' => 'transferido',
            'desistiu' => 'desistente', 'cancelada' => 'cancelado',
        ];
        if (isset($mapa[$s])) return $mapa[$s];
        $validos = ['activo','suspenso','transferido','desistente','inactivo','cancelado'];
        return in_array($s, $validos, true) ? $s : 'activo';
    }
}

if (!function_exists('sige_status_operacional_activo')) {
    function sige_status_operacional_activo($status): bool {
        $st = sige_status_operacional_normalizar($status);
        return $st === '' || in_array($st, ['activo','ativo','activa','ativa'], true);
    }
}

if (!function_exists('sige_status_operacional_inactivo')) {
    function sige_status_operacional_inactivo($status): bool {
        $st = sige_status_operacional_normalizar($status);
        return in_array($st, [
            'transferido','transferida','transferido saida','desistente','desistiu',
            'cancelado','cancelada','inactivo','inactiva','inativo','inativa'
        ], true);
    }
}

if (!function_exists('sige_aluno_activo_sql')) {
    function sige_aluno_activo_sql(string $alias = 'a'): string {
        $alias = sige_sql_alias_safe($alias);
        return "({$alias}.status IS NULL OR TRIM(LOWER({$alias}.status)) IN ('activo','ativo','activa','ativa'))";
    }
}

if (!function_exists('sige_matricula_activa_sql')) {
    function sige_matricula_activa_sql(string $alias = 'm'): string {
        $alias = sige_sql_alias_safe($alias);
        return "({$alias}.status_matricula IS NULL OR TRIM(LOWER({$alias}.status_matricula)) IN ('activa','ativa','activo','ativo'))";
    }
}

if (!function_exists('sige_aluno_matricula_activa_sql')) {
    function sige_aluno_matricula_activa_sql(string $aluno_alias = 'a', string $matricula_alias = 'm'): string {
        return sige_aluno_activo_sql($aluno_alias) . ' AND ' . sige_matricula_activa_sql($matricula_alias);
    }
}

if (!function_exists('sige_turma_ordem_chamada_order_sql')) {
    /**
     * Ordem canonica do numero de chamada de uma turma.
     * Alfabetica por nome completo, com desempate determinista por id.
     * Usada por todos os documentos que mostram o numero de chamada (MAP, pauta,
     * DEC, pauta final) e pela listagem de alunos da turma, para que o numero de
     * chamada seja sempre o mesmo em todo o lado.
     */
    function sige_turma_ordem_chamada_order_sql(string $aluno_alias = 'a'): string {
        $a = sige_sql_alias_safe($aluno_alias);
        return "{$a}.nome_completo ASC, {$a}.id ASC";
    }
}

if (!function_exists('sige_turma_numero_chamada')) {
    /**
     * Numero de chamada determinista de um aluno numa turma/ano.
     * Conta, na populacao canonica (aluno activo E matricula activa), quantos alunos
     * surgem antes dele na ordem canonica (nome_completo ASC, id ASC) e soma 1.
     * Nao usa variaveis de utilizador (@rn): e portavel e estavel entre execucoes,
     * ao contrario do varrimento por ordem de insercao da tabela. Devolve null se o
     * aluno nao for encontrado.
     */
    function sige_turma_numero_chamada(int $aluno_id, int $turma_id, int $ano_lectivo, int $escola_id): ?int {
        global $wpdb;
        if ($aluno_id <= 0 || $turma_id <= 0 || $ano_lectivo <= 0 || $escola_id <= 0) {
            return null;
        }
        $pA = $wpdb->prefix . 'sige_alunos';
        $pM = $wpdb->prefix . 'sige_matriculas';

        $alvo = $wpdb->get_row($wpdb->prepare(
            "SELECT a.nome_completo AS nome, a.id AS id FROM {$pA} a WHERE a.id = %d AND a.escola_id = %d LIMIT 1",
            $aluno_id, $escola_id
        ));
        if (!$alvo) {
            return null;
        }

        $pop = sige_aluno_matricula_activa_sql('a', 'm');

        $antes = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$pM} m
             JOIN {$pA} a ON a.id = m.aluno_id AND a.escola_id = m.escola_id
             WHERE m.turma_id = %d
               AND m.ano_lectivo = %d
               AND m.escola_id = %d
               AND {$pop}
               AND (a.nome_completo < %s OR (a.nome_completo = %s AND a.id < %d))",
            $turma_id, $ano_lectivo, $escola_id, $alvo->nome, $alvo->nome, (int) $alvo->id
        ));

        return $antes + 1;
    }
}

if (!function_exists('sige_aluno_inactivo_operacional_sql')) {
    function sige_aluno_inactivo_operacional_sql(string $alias = 'a'): string {
        $alias = sige_sql_alias_safe($alias);
        return "(TRIM(LOWER(COALESCE({$alias}.status,''))) IN ('transferido','transferida','desistente','desistiu','cancelado','cancelada','inactivo','inactiva','inativo','inativa'))";
    }
}

if (!function_exists('sige_matricula_inactiva_operacional_sql')) {
    function sige_matricula_inactiva_operacional_sql(string $alias = 'm'): string {
        $alias = sige_sql_alias_safe($alias);
        return "(TRIM(LOWER(COALESCE({$alias}.status_matricula,''))) IN ('transferido','transferida','transferido_saida','desistente','desistiu','cancelado','cancelada','inactiva','inativa','inactivo','inativo'))";
    }
}

if (!function_exists('sige_status_aluno_financeiro_label')) {
    function sige_status_aluno_financeiro_label($status): string {
        $st = strtolower(trim((string)$status));
        $st = str_replace(['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'], ['a','a','a','a','e','e','i','o','o','o','u','c'], $st);
        if (in_array($st, ['transferido','transferida'], true)) return 'Transferido';
        if (in_array($st, ['desistente','desistiu'], true)) return 'Desistente';
        if (in_array($st, ['cancelado','cancelada'], true)) return 'Cancelado';
        if (in_array($st, ['inactivo','inativa','inativo','inativa'], true)) return 'Inactivo';
        return 'Activo';
    }
}

if (!function_exists('sige_aluno_financeiramente_inactivo')) {
    function sige_aluno_financeiramente_inactivo(int $aluno_id, int $escola_id = 0, int $ano_lectivo = 0): bool {
        if ($aluno_id <= 0) return true;
        global $wpdb;
        $escola_id = $escola_id > 0 ? $escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        $ano_lectivo = $ano_lectivo > 0 ? $ano_lectivo : (function_exists('sige_ano_lectivo_atual') ? (int)sige_ano_lectivo_atual() : (int)wp_date('Y'));
        $tA = $wpdb->prefix . 'sige_alunos';
        $tM = $wpdb->prefix . 'sige_matriculas';

        $status = $wpdb->get_var($wpdb->prepare(
            "SELECT TRIM(LOWER(COALESCE(status,''))) FROM {$tA} WHERE id=%d AND escola_id=%d LIMIT 1",
            $aluno_id, $escola_id
        ));
        if ($status === null) return true;

        // O estado principal do aluno é a fonte canónica da operação.
        // Se o aluno foi reactivado, matrículas antigas com status legado
        // (ex.: desistente) não devem continuar a bloquear pagamentos/lotes.
        $aluno_activo_operacional = function_exists('sige_status_operacional_activo')
            ? sige_status_operacional_activo($status)
            : (bool)preg_match('/^(activ[oa]|ativ[oa])$/', (string)$status);

        if (function_exists('sige_status_operacional_inactivo')
            ? sige_status_operacional_inactivo($status)
            : (bool)preg_match('/^(transferid[oa]|desistente|desistiu|cancelad[oa]|inactiv[oa]|inativ[oa])$/', (string)$status)) {
            return true;
        }

        if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tM)) !== $tM) return false;
        $mat_status = $wpdb->get_var($wpdb->prepare(
            "SELECT TRIM(LOWER(COALESCE(status_matricula,'')))
             FROM {$tM}
             WHERE aluno_id=%d AND escola_id=%d AND ano_lectivo=%d
             ORDER BY id DESC LIMIT 1",
            $aluno_id, $escola_id, $ano_lectivo
        ));
        if ($mat_status === null || $mat_status === '') return false;

        $matricula_inactiva = function_exists('sige_status_operacional_inactivo')
            ? sige_status_operacional_inactivo($mat_status)
            : in_array((string)$mat_status, ['transferido','transferida','transferido_saida','desistente','desistiu','cancelado','cancelada','inactiva','inativa','inactivo','inativo'], true);

        if ($matricula_inactiva && $aluno_activo_operacional) {
            // Auto-cura segura para casos criados antes deste hotfix: o operador já
            // reactivou o aluno, mas a matrícula do ano ainda ficou em desistente/etc.
            if (function_exists('sige_sync_matricula_status_from_aluno')) {
                sige_sync_matricula_status_from_aluno($aluno_id, $ano_lectivo, 'activo', $escola_id);
            }
            return false;
        }

        return $matricula_inactiva;
    }
}

if (!function_exists('sige_fin_servico_recorrente_bloqueavel')) {
    function sige_fin_servico_recorrente_bloqueavel($servico): bool {
        $s = (object)$servico;
        $tipo = strtolower(trim((string)($s->tipo ?? '')));
        if (in_array($tipo, ['mensalidade','mensal','transporte'], true)) return true;
        if (isset($s->recorrente)) return (int)$s->recorrente === 1;
        if (in_array($tipo, ['atividade_extra','actividade_extra','ingles','almoco','almoço','pequeno_almoco','estudos','desporto'], true)) return true;
        return function_exists('sige_servico_eh_recorrente') ? sige_servico_eh_recorrente($s) : false;
    }
}

if (!function_exists('sige_fin_pode_gerar_cobranca_recorrente')) {
    function sige_fin_pode_gerar_cobranca_recorrente(int $aluno_id, $servico, int $escola_id = 0, int $ano_lectivo = 0): bool {
        if (!sige_fin_servico_recorrente_bloqueavel($servico)) return true;
        return !sige_aluno_financeiramente_inactivo($aluno_id, $escola_id, $ano_lectivo);
    }
}


// ============================================================================
// UPLOADS SCOPED POR ESCOLA
// ============================================================================
if (!function_exists('sige_upload_dir')) {
    /**
     * Devolve o directório de uploads scoped por escola.
     * Cria a pasta se não existir.
     *
     * Estrutura: wp-content/uploads/sige/{escola_id}/
     * Subpastas: alunos/, professores/, documentos/, comprovativos/
     *
     * @param string $subpasta  Subpasta (ex: 'alunos', 'comprovativos')
     * @return array ['path' => caminho absoluto, 'url' => URL pública]
     */
    function sige_upload_dir(string $subpasta = ''): array {
        $upload = wp_upload_dir();
        $eid = function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0;

        $base_path = $upload['basedir'] . '/sige/' . $eid;
        $base_url  = $upload['baseurl'] . '/sige/' . $eid;

        if ($subpasta) {
            $base_path .= '/' . sanitize_file_name($subpasta);
            $base_url  .= '/' . sanitize_file_name($subpasta);
        }

        if (!file_exists($base_path)) {
            wp_mkdir_p($base_path);
        }

        return ['path' => $base_path, 'url' => $base_url];
    }
}

/*
 * ---------------------------------------------------------------------------
 * Infraestrutura de nonce CSP (Content Security Policy).
 *
 * Gera um nonce unico por pedido, reutilizado em todas as tags de script inline
 * e, mais tarde, no proprio cabecalho CSP. Acrescentar o atributo nonce as tags
 * e inocuo enquanto o cabecalho CSP nao for activado: os navegadores ignoram o
 * nonce sem uma politica CSP. Prepara o enforcement do CSP sem alterar o
 * comportamento actual.
 * ---------------------------------------------------------------------------
 */
if (!function_exists('sige_csp_nonce')) {
    /**
     * Nonce CSP por pedido. Gerado uma so vez e reutilizado durante o pedido.
     * base64 de bytes aleatorios; recorre a wp_generate_password se random_bytes
     * nao estiver disponivel.
     *
     * @return string
     */
    function sige_csp_nonce() {
        static $nonce = null;
        if ($nonce === null) {
            try {
                $nonce = base64_encode(random_bytes(16));
            } catch (\Exception $e) {
                $nonce = base64_encode((string) wp_generate_password(24, false, false));
            }
        }
        return $nonce;
    }
}

if (!function_exists('sige_csp_script_attr')) {
    /**
     * Atributo nonce, ja escapado, para uma tag de script inline.
     * Uso: <script <?php echo sige_csp_script_attr(); ?>>
     * Inocuo ate o cabecalho CSP ser activado.
     *
     * @return string
     */
    function sige_csp_script_attr() {
        return 'nonce="' . esc_attr(sige_csp_nonce()) . '"';
    }
}


if (!function_exists('sige_csp_style_attr')) {
    /**
     * Atributo nonce, ja escapado, para uma tag <style> inline autorizada por CSP.
     * Uso: <style <?php echo sige_csp_style_attr(); ?>>
     * Mantem documentos autonomos/print views compativeis com CSP enforcement sem permissao-inline.
     *
     * @return string
     */
    function sige_csp_style_attr() {
        return 'nonce="' . esc_attr(sige_csp_nonce()) . '"';
    }
}

if (!function_exists('sige_csp_is_admin_shell_request')) {
    /**
     * Pedido do shell admin autenticado do SIGE. Mantem o CSP forte isolado ao
     * aplicativo interno e evita afectar wp-admin generico, AJAX, REST e login.
     *
     * @return bool
     */
    function sige_csp_is_admin_shell_request() {
        return function_exists('is_admin')
            && is_admin()
            && isset($_GET['page'])
            && sanitize_key((string) $_GET['page']) === 'sige-app'
            && (!function_exists('wp_doing_ajax') || !wp_doing_ajax());
    }
}

if (!function_exists('sige_csp_inline_script_attributes')) {
    /**
     * Acrescenta nonce CSP aos scripts inline gerados pelo WordPress
     * (wp_add_inline_script/wp_localize_script) no shell admin. Sem este filtro,
     * o CSP em enforcement bloquearia o proprio bootstrap AJAX/localized data.
     *
     * @param array $attributes
     * @return array
     */
    function sige_csp_inline_script_attributes($attributes) {
        if (function_exists('sige_csp_is_admin_shell_request') && sige_csp_is_admin_shell_request()) {
            if (is_array($attributes) && empty($attributes['nonce'])) {
                $attributes['nonce'] = sige_csp_nonce();
            }
        }
        return $attributes;
    }
}
add_filter('wp_inline_script_attributes', 'sige_csp_inline_script_attributes', 10, 1);

if (!function_exists('sige_csp_script_tag_nonce_for_inline')) {
    /**
     * Compatibilidade defensiva para versoes/fluxos que passem uma tag inline por
     * wp_script_attributes. Scripts externos continuam cobertos por script-src self.
     *
     * @param array $attributes
     * @return array
     */
    function sige_csp_script_tag_nonce_for_inline($attributes) {
        if (function_exists('sige_csp_is_admin_shell_request') && sige_csp_is_admin_shell_request()) {
            if (is_array($attributes) && empty($attributes['src']) && empty($attributes['nonce'])) {
                $attributes['nonce'] = sige_csp_nonce();
            }
        }
        return $attributes;
    }
}
add_filter('wp_script_attributes', 'sige_csp_script_tag_nonce_for_inline', 10, 1);
