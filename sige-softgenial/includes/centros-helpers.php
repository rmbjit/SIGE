<?php
/**
 * SIGE SoftGenial - Centros de Custo Helpers
 * Ficheiro: includes/centros-helpers.php
 *
 * Helpers para trabalhar com centros de custo (sige_fin_centros).
 * Usado em: dashboard, lancamentos, pagamentos, despesas, gerador, inscricoes.
 *
 * Multi-tenant: todas as queries filtram por escola_id implicitamente via
 * sige_get_escola_id().
 *
 * @since 13.1 (2026-04-17)
 */

if (!defined('ABSPATH')) exit;


// ============================================================================
// MIGRAÇÃO SEGURA - Centros de Custo
// ============================================================================
if (!function_exists('sige_fin_centros_maybe_install')) {
    /**
     * Cria a tabela de centros de custo e garante colunas necessárias nas tabelas financeiras.
     * Idempotente: pode correr em todas as instalações sem recalcular saldos nem alterar dados existentes.
     */
    function sige_fin_centros_maybe_install(): void {
        global $wpdb;
        if (!is_admin()) return;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = $wpdb->prefix . 'sige_fin_centros';

        $sql = "CREATE TABLE {$t} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            escola_id BIGINT UNSIGNED NOT NULL DEFAULT 1,
            nome VARCHAR(150) NOT NULL,
            descricao VARCHAR(500) NULL,
            cor VARCHAR(20) NOT NULL DEFAULT '#0A2E5C',
            ordem INT NOT NULL DEFAULT 10,
            activo TINYINT(1) NOT NULL DEFAULT 1,
            criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            actualizado_em DATETIME NULL,
            PRIMARY KEY  (id),
            KEY escola_id (escola_id),
            KEY activo (activo),
            KEY ordem (ordem)
        ) {$charset};";
        dbDelta($sql);

        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($eid <= 0) { return; }
        $exists = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$t} WHERE escola_id=%d", $eid));
        if ($exists === 0) {
            $wpdb->insert($t, [
                'escola_id' => $eid,
                'nome' => 'Geral',
                'descricao' => 'Centro de custo padrão da escola.',
                'cor' => '#0A2E5C',
                'ordem' => 1,
                'activo' => 1,
            ], ['%d','%s','%s','%s','%d','%d']);
        }

        $tables = [
            'sige_fin_servicos'    => 'centro_id BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'sige_fin_lancamentos' => 'centro_id BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'sige_fin_pagamentos'  => 'centro_id BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'sige_fin_despesas'    => 'centro_id BIGINT UNSIGNED NOT NULL DEFAULT 0',
            'sige_alunos'          => 'centro_id BIGINT UNSIGNED NOT NULL DEFAULT 0',
        ];
        foreach ($tables as $suffix => $definition) {
            $table = $wpdb->prefix . $suffix;
            if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) !== $table) continue;
            $col = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM {$table} LIKE %s", 'centro_id'));
            if (!$col) {
                $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN {$definition}");
            }
            $idx = $wpdb->get_var($wpdb->prepare(
                "SHOW INDEX FROM `{$table}` WHERE Key_name = %s",
                'centro_id'
            ));
            if (!$idx) {
                $wpdb->query("ALTER TABLE `{$table}` ADD INDEX `centro_id` (`centro_id`)");
            }
        }
    }
    add_action('admin_init', 'sige_fin_centros_maybe_install', 20);
}

// ============================================================================
// LISTAR CENTROS ACTIVOS DA ESCOLA ACTUAL
// ============================================================================
if (!function_exists('sige_fin_get_centros')) {
    /**
     * Devolve array de centros activos da escola actual, ordenado por 'ordem'.
     *
     * @param bool $incluir_inactivos Se true, inclui centros com activo=0
     * @return array<object> [{id, nome, cor, ordem, activo, ...}, ...]
     */
    function sige_fin_get_centros(bool $incluir_inactivos = false): array {
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $t   = $wpdb->prefix . 'sige_fin_centros';

        // Cache por request
        static $cache = [];
        $cache_key = $eid . ':' . ($incluir_inactivos ? '1' : '0');
        if (isset($cache[$cache_key])) {
            return $cache[$cache_key];
        }

        $where_activo = $incluir_inactivos ? '' : 'AND activo = 1';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$t}
             WHERE escola_id = %d {$where_activo}
             ORDER BY ordem ASC, id ASC",
            $eid
        ));

        $cache[$cache_key] = $rows ?: [];
        return $cache[$cache_key];
    }
}

// ============================================================================
// OBTER CENTRO DEFAULT (primeiro centro activo da escola)
// ============================================================================
if (!function_exists('sige_fin_get_centro_default_id')) {
    /**
     * Devolve o ID do primeiro centro activo da escola.
     * Usado como fallback quando nao ha centro explicito.
     *
     * @return int
     */
    function sige_fin_get_centro_default_id(): int {
        $centros = sige_fin_get_centros();
        if (empty($centros)) return 1;
        return (int)$centros[0]->id;
    }
}

// ============================================================================
// OBTER CENTRO DE UM ALUNO (para herdar em lancamentos/pagamentos)
// ============================================================================
if (!function_exists('sige_fin_get_centro_do_aluno')) {
    /**
     * Devolve centro_id do aluno. Se aluno nao tem centro definido,
     * devolve o centro default da escola.
     *
     * @param int $aluno_id
     * @return int
     *
     * @deprecated 13.4.2 - O modelo mudou: centro_id pertence ao SERVIÇO,
     *   não ao aluno. Este helper mantém-se temporariamente por compatibilidade
     *   de código antigo, mas NÃO deve ser usado em INSERT novos de
     *   lançamentos ou pagamentos - usar sige_fin_get_centro_do_servico()
     *   em seu lugar. Em v14.x será removido.
     */
    function sige_fin_get_centro_do_aluno(int $aluno_id): int {
        global $wpdb;
        if ($aluno_id <= 0) return sige_fin_get_centro_default_id();

        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $cid = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(NULLIF(centro_id, 0), 0)
             FROM {$wpdb->prefix}sige_alunos
             WHERE id = %d AND escola_id = %d
             LIMIT 1",
            $aluno_id, $eid
        ));

        return $cid > 0 ? $cid : sige_fin_get_centro_default_id();
    }
}

// ============================================================================
// [v13.4.2] OBTER CENTRO DE UM SERVIÇO (fonte da verdade para herança)
// ============================================================================
if (!function_exists('sige_fin_get_centro_do_servico')) {
    /**
     * Devolve centro_id do serviço. Este é o MÉTODO CANÓNICO para determinar
     * o centro de um lançamento ou pagamento novo, a partir de v13.4.2.
     *
     * Regras:
     *   - servico_id <= 0 → cai para centro default (segurança; não devia
     *     acontecer em operação normal)
     *   - servico não encontrado → cai para centro default
     *   - centro_id = 0 ("não classificado") → cai para centro default MAS
     *     regista warning no error_log para o Director investigar - serviços
     *     sem centro deviam ter sido bloqueados no gerador
     *   - caso normal → devolve o centro_id do serviço
     *
     * Usado em includes/finance-core.php (3 sítios) para INSERT de novos
     * lançamentos. Substitui sige_fin_get_centro_do_aluno() no modelo
     * correcto. Ver HANDOFF_v13.4.2_PLAN.md secção 'Decisões arquitecturais'.
     *
     * @param int $servico_id
     * @return int
     */
    function sige_fin_get_centro_do_servico(int $servico_id): int {
        global $wpdb;
        if ($servico_id <= 0) return sige_fin_get_centro_default_id();

        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

        // Cache por request - um lote de 200 lançamentos com o mesmo serviço
        // não deve fazer 200 SELECTs
        static $cache = [];
        $key = $eid . ':' . $servico_id;
        if (isset($cache[$key])) return $cache[$key];

        $cid = $wpdb->get_var($wpdb->prepare(
            "SELECT centro_id
             FROM {$wpdb->prefix}sige_fin_servicos
             WHERE id = %d AND escola_id = %d
             LIMIT 1",
            $servico_id, $eid
        ));

        // Serviço não encontrado
        if ($cid === null) {
            $cache[$key] = sige_fin_get_centro_default_id();
            return $cache[$key];
        }

        $cid = (int)$cid;

        // Serviço existe mas centro_id = 0 (não classificado)
        if ($cid === 0) {
            // Log warning para diagnóstico - este serviço devia ter sido
            // bloqueado no gerador antes de chegar aqui
            if (function_exists('error_log')) {
                error_log(sprintf(
                    '[SIGE v13.4.2] Serviço #%d não tem centro_id atribuído. ' .
                    'Lançamento vai para centro default. Corrigir em Configurar Preços.',
                    $servico_id
                ));
            }
            $cache[$key] = sige_fin_get_centro_default_id();
            return $cache[$key];
        }

        $cache[$key] = $cid;
        return $cid;
    }
}

// ============================================================================
// [v13.4.2] VALIDAR SE SERVIÇO TEM CENTRO ATRIBUÍDO (gate do gerador)
// ============================================================================
if (!function_exists('sige_fin_servico_tem_centro')) {
    /**
     * Verifica se um serviço tem centro_id > 0 atribuído.
     * Usado pelo gerador e por outros pontos de entrada antes de criar
     * lançamentos, para evitar criar registos com classificação errada.
     *
     * @param int $servico_id
     * @return bool
     */
    function sige_fin_servico_tem_centro(int $servico_id): bool {
        if ($servico_id <= 0) return false;
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $cid = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT centro_id
             FROM {$wpdb->prefix}sige_fin_servicos
             WHERE id = %d AND escola_id = %d",
            $servico_id, $eid
        ));
        return $cid > 0;
    }
}

// ============================================================================
// [v13.4.2] CONTAR SERVIÇOS NÃO CLASSIFICADOS (para audit no dashboard)
// ============================================================================
if (!function_exists('sige_fin_count_servicos_sem_centro')) {
    /**
     * Devolve quantos serviços activos têm centro_id = 0 na escola actual.
     * Usado para badge de auditoria: "⚠ N serviços sem centro atribuído".
     *
     * @return int
     */
    function sige_fin_count_servicos_sem_centro(): int {
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        return (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*)
             FROM {$wpdb->prefix}sige_fin_servicos
             WHERE escola_id = %d AND ativo = 1 AND (centro_id IS NULL OR centro_id = 0)",
            $eid
        ));
    }
}

// ============================================================================
// OBTER NOME DE UM CENTRO (para labels em UI)
// ============================================================================
if (!function_exists('sige_fin_get_centro_nome')) {
    function sige_fin_get_centro_nome(int $centro_id): string {
        if ($centro_id <= 0) return '-';
        $centros = sige_fin_get_centros(true);
        foreach ($centros as $c) {
            if ((int)$c->id === $centro_id) {
                return (string)$c->nome;
            }
        }
        return '-';
    }
}

// ============================================================================
// OBTER COR DE UM CENTRO (para chips visuais)
// ============================================================================
if (!function_exists('sige_fin_get_centro_cor')) {
    function sige_fin_get_centro_cor(int $centro_id): string {
        $centros = sige_fin_get_centros(true);
        foreach ($centros as $c) {
            if ((int)$c->id === $centro_id) {
                return (string)$c->cor;
            }
        }
        return '#64748b';
    }
}

// ============================================================================
// DROPDOWN HTML <SELECT> DE CENTROS (reutilizavel)
// ============================================================================
if (!function_exists('sige_fin_dropdown_centros')) {
    /**
     * Gera HTML de <select> com todos os centros activos.
     *
     * @param array $args ['name'=>, 'id'=>, 'selected'=>, 'class'=>, 'required'=>, 'include_todos'=>bool]
     * @return string HTML
     */
    function sige_fin_dropdown_centros(array $args = []): string {
        $defaults = [
            'name'          => 'centro_id',
            'id'            => 'centro_id',
            'selected'      => 0,
            'class'         => 'sige-input',
            'required'      => false,
            'include_todos' => false,  // se true, adiciona "Todos os centros" como primeira opção
            'todos_label'   => 'Todos os Centros',
            'todos_value'   => '0',
        ];
        $a = array_merge($defaults, $args);

        $centros = sige_fin_get_centros();
        if (empty($centros)) return '';

        $sel  = (int)$a['selected'];
        $req  = $a['required'] ? ' required' : '';
        $html = '<select name="' . esc_attr($a['name']) . '" id="' . esc_attr($a['id']) . '" class="' . esc_attr($a['class']) . '"' . $req . '>';

        if ($a['include_todos']) {
            $html .= '<option value="' . esc_attr($a['todos_value']) . '">' . esc_html($a['todos_label']) . '</option>';
        }

        foreach ($centros as $c) {
            $selected = ($sel === (int)$c->id) ? ' selected' : '';
            $html .= '<option value="' . (int)$c->id . '"' . $selected . '>' . esc_html($c->nome) . '</option>';
        }

        $html .= '</select>';
        return $html;
    }
}

// ============================================================================
// CHIP HTML DE UM CENTRO (badge colorido para listas)
// ============================================================================
if (!function_exists('sige_fin_chip_centro')) {
    function sige_fin_chip_centro(int $centro_id): string {
        if ($centro_id <= 0) return '';
        $nome = sige_fin_get_centro_nome($centro_id);
        $cor  = sige_fin_get_centro_cor($centro_id);
        return '<span style="display:inline-block;padding:2px 8px;background:' . esc_attr($cor) . ';color:#fff;border-radius:10px;font-size:11px;font-weight:600;line-height:1.4;">' . esc_html($nome) . '</span>';
    }
}

// ============================================================================
// AJAX: CRUD DE CENTROS
// ============================================================================

add_action('wp_ajax_sige_centro_salvar', 'sige_ajax_centro_salvar');
if (!function_exists('sige_ajax_centro_salvar')) {
    function sige_ajax_centro_salvar() {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')
            && !current_user_can('sige_admin') && !current_user_can('sige_financeiro')) {
            wp_send_json_error('Sem permissao.');
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'sige_centros_crud')) {
            wp_send_json_error('Nonce invalido. Recarregue a pagina.');
        }

        global $wpdb;
        $t   = $wpdb->prefix . 'sige_fin_centros';
        $eid = sige_require_escola_id('centro_salvar');

        $id        = isset($_POST['id']) ? (int)$_POST['id'] : 0;
        $nome      = sanitize_text_field($_POST['nome'] ?? '');
        $descricao = sanitize_text_field($_POST['descricao'] ?? '');
        $cor       = sanitize_hex_color($_POST['cor'] ?? '') ?: '#0A2E5C';
        $ordem     = isset($_POST['ordem']) ? (int)$_POST['ordem'] : 10;
        $activo    = isset($_POST['activo']) ? (int)(bool)$_POST['activo'] : 1;

        if (empty($nome)) {
            wp_send_json_error('O nome do centro e obrigatorio.');
        }
        if (mb_strlen($nome) > 150) {
            wp_send_json_error('O nome e demasiado longo (max 150 chars).');
        }

        $dados = [
            'escola_id' => $eid,
            'nome'      => $nome,
            'descricao' => $descricao,
            'cor'       => $cor,
            'ordem'     => $ordem,
            'activo'    => $activo,
        ];

        if ($id > 0) {
            // Update: confirmar que pertence a esta escola
            $existe = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$t} WHERE id = %d AND escola_id = %d",
                $id, $eid
            ));
            if ($existe === 0) {
                wp_send_json_error('Centro nao encontrado.');
            }
            $ok = $wpdb->update($t, $dados, ['id' => $id, 'escola_id' => $eid]);
            if ($ok === false) {
                wp_send_json_error('Erro ao actualizar: ' . $wpdb->last_error);
            }
            if (function_exists('sige_fin_log')) {
                sige_fin_log('centro_actualizado', ['id' => $id, 'nome' => $nome]);
            }
            wp_send_json_success(['msg' => 'Centro actualizado.', 'id' => $id]);
        } else {
            $dados['criado_em'] = current_time('mysql');
            $ok = $wpdb->insert($t, $dados);
            if ($ok === false) {
                wp_send_json_error('Erro ao criar: ' . $wpdb->last_error);
            }
            $new_id = (int)$wpdb->insert_id;
            if (function_exists('sige_fin_log')) {
                sige_fin_log('centro_criado', ['id' => $new_id, 'nome' => $nome]);
            }
            wp_send_json_success(['msg' => 'Centro criado.', 'id' => $new_id]);
        }
    }
}

add_action('wp_ajax_sige_centro_eliminar', 'sige_ajax_centro_eliminar');
if (!function_exists('sige_ajax_centro_eliminar')) {
    function sige_ajax_centro_eliminar() {
        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')) {
            wp_send_json_error('Apenas Director pode eliminar centros.');
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'sige_centros_crud')) {
            wp_send_json_error('Nonce invalido.');
        }

        global $wpdb;
        $t   = $wpdb->prefix . 'sige_fin_centros';
        $eid = sige_require_escola_id('centro_eliminar');
        $id  = isset($_POST['id']) ? (int)$_POST['id'] : 0;

        if ($id <= 0) {
            wp_send_json_error('ID invalido.');
        }

        // Bloquear eliminacao se houver lancamentos/pagamentos/despesas associados
        $refs = 0;
        $refs += (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sige_fin_lancamentos WHERE centro_id = %d", $id
        ));
        $refs += (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sige_fin_pagamentos WHERE centro_id = %d", $id
        ));
        $refs += (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sige_fin_despesas WHERE centro_id = %d", $id
        ));
        $refs += (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$wpdb->prefix}sige_alunos WHERE centro_id = %d", $id
        ));

        if ($refs > 0) {
            wp_send_json_error(
                "Nao e possivel eliminar: este centro tem {$refs} registos associados. "
                . "Desactive-o em vez de eliminar (campo 'activo')."
            );
        }

        $ok = $wpdb->delete($t, ['id' => $id, 'escola_id' => $eid]);
        if ($ok === false) {
            wp_send_json_error('Erro ao eliminar: ' . $wpdb->last_error);
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('centro_eliminado', ['id' => $id]);
        }
        wp_send_json_success(['msg' => 'Centro eliminado.']);
    }
}

// ============================================================================
// [v13.4.2] AJAX: REATRIBUIR SERVIÇO DE UM CENTRO PARA OUTRO (bulk)
// ============================================================================
add_action('wp_ajax_sige_centro_reatribuir_servico', 'sige_ajax_centro_reatribuir_servico');
if (!function_exists('sige_ajax_centro_reatribuir_servico')) {
    /**
     * Move um serviço (e opcionalmente o seu histórico de lançamentos e
     * pagamentos) de um centro para outro.
     *
     * Caso de uso: Fátima decide que "Almoço" deve deixar de ser facturado
     * pelo Centro de Actividades e passa a ser da Casa Colorida. Um clique
     * na bulk reassign atualiza o serviço + histórico de uma vez.
     *
     * Fluxo:
     *   1. UPDATE sige_fin_servicos SET centro_id = destino WHERE id = X
     *   2. (opcional) UPDATE sige_fin_lancamentos SET centro_id = destino WHERE servico_id = X
     *   3. (opcional) UPDATE sige_fin_pagamentos SET centro_id = destino
     *      WHERE lancamento_id IN (SELECT id FROM lancamentos WHERE servico_id = X)
     *
     * Transaccional - rollback se qualquer falhar.
     */
    function sige_ajax_centro_reatribuir_servico() {
        global $wpdb;

        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')
            && !current_user_can('sige_admin') && !current_user_can('sige_financeiro')) {
            wp_send_json_error('Sem permissão para reatribuir serviços.');
        }

        $nonce = sanitize_text_field($_POST['nonce'] ?? '');
        if (!wp_verify_nonce($nonce, 'sige_centros_crud')) {
            wp_send_json_error('Nonce inválido. Recarregue a página.');
        }

        $servico_id    = (int)($_POST['servico_id'] ?? 0);
        $destino_id    = (int)($_POST['destino_id'] ?? 0);
        $incluir_hist  = !empty($_POST['incluir_historico']);
        $preview       = !empty($_POST['preview']);
        $eid           = (int)sige_get_escola_id();

        if ($servico_id <= 0 || $destino_id <= 0) {
            wp_send_json_error('Escolhe serviço e centro destino.');
        }

        $tS = $wpdb->prefix . 'sige_fin_servicos';
        $tL = $wpdb->prefix . 'sige_fin_lancamentos';
        $tP = $wpdb->prefix . 'sige_fin_pagamentos';

        // Validar serviço
        $srv = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nome, centro_id FROM {$tS} WHERE id = %d AND escola_id = %d",
            $servico_id, $eid
        ));
        if (!$srv) wp_send_json_error('Serviço não encontrado.');

        $origem_id = (int)$srv->centro_id;
        if ($origem_id === $destino_id) {
            wp_send_json_error('O serviço já está no centro destino.');
        }

        // Validar centro destino
        $centros = sige_fin_get_centros(true);
        $map = [];
        foreach ($centros as $c) $map[(int)$c->id] = $c;
        if (!isset($map[$destino_id])) {
            wp_send_json_error('Centro destino inválido.');
        }

        // PREVIEW
        $n_lanc = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tL} WHERE escola_id = %d AND servico_id = %d",
            $eid, $servico_id
        ));
        $n_pag = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tP} p
             INNER JOIN {$tL} l ON l.id = p.lancamento_id
             WHERE p.escola_id = %d AND l.servico_id = %d",
            $eid, $servico_id
        ));

        if ($preview) {
            wp_send_json_success([
                'preview' => true,
                'servico_nome' => $srv->nome,
                'origem_nome'  => $origem_id > 0 && isset($map[$origem_id]) ? $map[$origem_id]->nome : '(sem centro)',
                'destino_nome' => $map[$destino_id]->nome,
                'n_lancamentos' => $n_lanc,
                'n_pagamentos'  => $n_pag,
            ]);
        }

        // EXECUÇÃO
        $wpdb->query('START TRANSACTION');
        try {
            // 1. Actualizar o serviço
            $up_srv = $wpdb->update($tS,
                ['centro_id' => $destino_id],
                ['id' => $servico_id, 'escola_id' => $eid]
            );

            $up_lanc = 0;
            $up_pag  = 0;

            if ($incluir_hist) {
                // 2. Actualizar todos os lançamentos deste serviço
                $up_lanc = $wpdb->query($wpdb->prepare(
                    "UPDATE {$tL} SET centro_id = %d
                     WHERE escola_id = %d AND servico_id = %d",
                    $destino_id, $eid, $servico_id
                ));

                // 3. Actualizar pagamentos associados aos lançamentos deste serviço
                $up_pag = $wpdb->query($wpdb->prepare(
                    "UPDATE {$tP} p
                     INNER JOIN {$tL} l ON l.id = p.lancamento_id
                     SET p.centro_id = %d
                     WHERE p.escola_id = %d AND l.servico_id = %d",
                    $destino_id, $eid, $servico_id
                ));
            }

            $wpdb->query('COMMIT');
        } catch (Throwable $e) {
            $wpdb->query('ROLLBACK');
            wp_send_json_error('Erro na transacção: ' . $e->getMessage());
        }

        // Audit log
        if (function_exists('sige_fin_log')) {
            sige_fin_log('servico_reatribuido_centro', [
                'servico_id'    => $servico_id,
                'servico_nome'  => $srv->nome,
                'origem_id'     => $origem_id,
                'destino_id'    => $destino_id,
                'incluir_hist'  => $incluir_hist,
                'lancamentos'   => (int)$up_lanc,
                'pagamentos'    => (int)$up_pag,
            ]);
        }

        wp_send_json_success([
            'msg' => sprintf(
                'Serviço "%s" movido para "%s".%s',
                esc_html($srv->nome),
                esc_html($map[$destino_id]->nome),
                $incluir_hist ? sprintf(' Histórico movido: %d lançamentos, %d pagamentos.', (int)$up_lanc, (int)$up_pag) : ' (Histórico não tocado.)'
            ),
        ]);
    }
}

// ============================================================================
// HELPER: Totais financeiros por centro (para dashboard)
// ============================================================================
if (!function_exists('sige_fin_totais_por_centro')) {
    /**
     * Devolve array de totais agregados por centro para o intervalo dado.
     *
     * @param string $data_inicio YYYY-MM-DD
     * @param string $data_fim    YYYY-MM-DD
     * @return array<int,object> [centro_id => {centro_id, nome, cor, receita_bruta,
     *                                          estornos, receita_liquida, despesa, resultado}]
     *
     * [v13.3.0] Refactor: corpo reescrito para delegar ao motor de KPIs
     * consolidado (fin-kpi-engine.php). Assinatura externa e contrato de
     * retorno PRESERVADOS - todos os consumidores continuam a funcionar.
     *
     * Nota sobre o intervalo: a função recebe $data_inicio/$data_fim em formato
     * ISO. Se o intervalo cobre exactamente um ano civil completo, delega a
     * sige_kpi_receita_ano / sige_kpi_despesa_ano (mais rápidos, usam o engine).
     * Se o intervalo é arbitrário (mês, semana, range custom), cai para queries
     * directas com o mesmo filtro de status canónico.
     */
    function sige_fin_totais_por_centro(string $data_inicio, string $data_fim): array {
        global $wpdb;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($eid <= 0) return [];

        // Validação de formato de data (defesa em profundidade)
        $re = '/^\d{4}\-\d{2}\-\d{2}$/';
        if (!preg_match($re, $data_inicio) || !preg_match($re, $data_fim)) {
            return [];
        }

        $tP = $wpdb->prefix . 'sige_fin_pagamentos';
        $tD = $wpdb->prefix . 'sige_fin_despesas';

        // Detectar se o intervalo é "ano civil inteiro" (YYYY-01-01..YYYY-12-31);
        // nesse caso podemos delegar ao engine. Caso contrário, mantemos a lógica
        // legada baseada em BETWEEN de datas.
        $ano_inicio = (int)substr($data_inicio, 0, 4);
        $ano_fim    = (int)substr($data_fim, 0, 4);
        $ano_civil_inteiro = (
            $ano_inicio === $ano_fim
            && substr($data_inicio, 5) === '01-01'
            && substr($data_fim, 5)    === '12-31'
        );

        $centros = sige_fin_get_centros(true);
        $out = [];

        if ($ano_civil_inteiro && function_exists('sige_kpi_receita_ano')) {
            // ── Caminho rápido: intervalo = ano inteiro → delega ao engine ──
            $ano = $ano_inicio;
            foreach ($centros as $c) {
                $cid = (int)$c->id;
                // receita_liquida = SUM(valor_pago) no ano com estornos negativos
                $receita_liq = sige_kpi_receita_ano($eid, $ano, $cid);
                $despesa     = sige_kpi_despesa_ano($eid, $ano, $cid);

                // Para manter o contrato (receita_bruta e estornos separados),
                // fazemos um segundo cálculo discriminado por centro. Nota:
                // receita_bruta = receita_liquida + estornos_abs, portanto:
                $estornos = (float) $wpdb->get_var($wpdb->prepare(
                    "SELECT COALESCE(SUM(ABS(valor_pago)), 0)
                     FROM {$tP}
                     WHERE escola_id = %d
                       AND YEAR(data_pagamento) = %d
                       AND (metodo_pagamento = 'estorno' OR valor_pago < 0)
                       AND centro_id = %d",
                    $eid, $ano, $cid
                ));
                $receita_bruta = $receita_liq + $estornos;

                $out[$cid] = (object)[
                    'centro_id'       => $cid,
                    'nome'            => (string)$c->nome,
                    'cor'             => (string)$c->cor,
                    'receita_bruta'   => $receita_bruta,
                    'estornos'        => $estornos,
                    'receita_liquida' => $receita_liq,
                    'despesa'         => $despesa,
                    'resultado'       => $receita_liq - $despesa,
                ];
            }
            return $out;
        }

        // ── Caminho legado: intervalo arbitrário (BETWEEN data_inicio..data_fim) ──
        // Mantém exactamente a lógica pré-v13.3.0 (já validada pelo BUG-CRIT-01 fix).
        // Eventual consolidação futura pode expor sige_kpi_* em modo "range" -
        // fora do escopo do Bloco 2.

        // Receita bruta (exclui estornos)
        $receitas = $wpdb->get_results($wpdb->prepare(
            "SELECT centro_id, SUM(valor_pago) AS total
             FROM {$tP}
             WHERE escola_id = %d
               AND DATE(data_pagamento) BETWEEN %s AND %s
               AND metodo_pagamento <> 'estorno'
               AND valor_pago > 0
             GROUP BY centro_id",
            $eid, $data_inicio, $data_fim
        ), OBJECT_K);

        // Estornos (em valor absoluto)
        $estornos = $wpdb->get_results($wpdb->prepare(
            "SELECT centro_id, SUM(ABS(valor_pago)) AS total
             FROM {$tP}
             WHERE escola_id = %d
               AND DATE(data_pagamento) BETWEEN %s AND %s
               AND (metodo_pagamento = 'estorno' OR valor_pago < 0)
             GROUP BY centro_id",
            $eid, $data_inicio, $data_fim
        ), OBJECT_K);

        // Despesas (excl. anuladas) - BUG-CRIT-01 fix preservado
        $despesas = $wpdb->get_results($wpdb->prepare(
            "SELECT centro_id, SUM(valor) AS total
             FROM {$tD}
             WHERE escola_id = %d
               AND data_despesa BETWEEN %s AND %s
               AND LOWER(status) <> 'anulado'
             GROUP BY centro_id",
            $eid, $data_inicio, $data_fim
        ), OBJECT_K);

        foreach ($centros as $c) {
            $cid = (int)$c->id;
            $rec = isset($receitas[$cid]) ? (float)$receitas[$cid]->total : 0.0;
            $est = isset($estornos[$cid]) ? (float)$estornos[$cid]->total : 0.0;
            $des = isset($despesas[$cid]) ? (float)$despesas[$cid]->total : 0.0;
            $out[$cid] = (object)[
                'centro_id'       => $cid,
                'nome'            => (string)$c->nome,
                'cor'             => (string)$c->cor,
                'receita_bruta'   => $rec,
                'estornos'        => $est,
                'receita_liquida' => $rec - $est,
                'despesa'         => $des,
                'resultado'       => ($rec - $est) - $des,
            ];
        }
        return $out;
    }
}

// ============================================================================
// [v13.4.2 BLOCO 3] FILTRO UNIVERSAL POR CENTRO - CONTEXTO
// ============================================================================
if (!function_exists('sige_fin_centro_ativo')) {
    /**
     * Lê e valida o filtro de centro activo a partir de $_GET['centro_id'].
     *
     * Semântica:
     *   - Ausente ou 0        → 0 (todos os centros, equivalente a "não filtrar")
     *   - Inteiro válido e
     *     activo nesta escola → centro_id do centro escolhido
     *   - Inteiro inválido,
     *     não pertence à
     *     escola, ou inactivo → 0 (defesa multi-tenant)
     *
     * Esta função é o ÚNICO ponto autorizado para ler o filtro - todas as
     * views devem usá-la em vez de tocar $_GET directamente.
     *
     * @return int Centro activo validado (0 = todos os centros)
     */
    function sige_fin_centro_ativo(): int {
        if (!isset($_GET['centro_id'])) return 0;
        $raw = (int) $_GET['centro_id'];
        if ($raw <= 0) return 0;

        $centros = sige_fin_get_centros(false);
        foreach ($centros as $c) {
            if ((int)$c->id === $raw) return $raw;
        }
        return 0;
    }
}

if (!function_exists('sige_fin_render_filtro_centro')) {
    /**
     * Renderiza widget de filtro por centro (label + dropdown) para toolbars
     * de views financeiras.
     *
     * Política:
     *   - Único ponto autorizado para renderizar o selector de filtro.
     *   - Devolve '' se a escola só tem 1 centro activo (hide_if_single).
     *   - Não inclui <form> - caller é responsável.
     *
     * @param array $args {
     *   @type int    $selected       Centro pré-seleccionado (default: -1 → lê sige_fin_centro_ativo())
     *   @type string $label          Texto do <label> (default: 'Centro')
     *   @type bool   $include_label  Se false, devolve só o <select> (default: true)
     *   @type string $name           Atributo name (default: 'centro_id')
     *   @type bool   $auto_submit    Submete form on change (default: false)
     *   @type string $class          Classes CSS (default: 'sige-input')
     *   @type string $style          Estilos inline (default: '')
     *   @type string $todos_label    Texto da opção "Todos" (default: 'Todos os Centros')
     *   @type bool   $hide_if_single Esconde quando só 1 centro (default: true)
     * }
     * @return string HTML ou '' quando escondido
     */
    function sige_fin_render_filtro_centro(array $args = []): string {
        $defaults = [
            'selected'       => -1,
            'label'          => 'Centro',
            'include_label'  => true,
            'name'           => 'centro_id',
            'auto_submit'    => false,
            'class'          => 'sige-input',
            'style'          => '',
            'todos_label'    => 'Todos os Centros',
            'hide_if_single' => true,
        ];
        $a = array_merge($defaults, $args);

        $centros = sige_fin_get_centros(false);
        $n = count($centros);
        if ($n === 0) return '';
        if ($n === 1 && $a['hide_if_single']) return '';

        $selected = ((int)$a['selected'] === -1) ? sige_fin_centro_ativo() : (int)$a['selected'];

        $onchange = $a['auto_submit'] ? ' onchange="this.form.submit()"' : '';
        $style_attr = $a['style'] !== '' ? ' style="' . esc_attr($a['style']) . '"' : '';

        $html = '';
        if ($a['include_label']) {
            $html .= '<label style="display:inline-flex;align-items:center;gap:6px;font-weight:600;color:#1e293b;margin:0 4px 0 12px;">';
            $html .= esc_html($a['label']);
            $html .= '</label>';
        }
        $html .= '<select name="' . esc_attr($a['name']) . '" id="' . esc_attr($a['name']) . '" class="' . esc_attr($a['class']) . '"' . $style_attr . $onchange . '>';
        $html .= '<option value="0"' . ($selected === 0 ? ' selected' : '') . '>' . esc_html($a['todos_label']) . '</option>';
        foreach ($centros as $c) {
            $cid = (int)$c->id;
            $sel = ($selected === $cid) ? ' selected' : '';
            $html .= '<option value="' . $cid . '"' . $sel . '>' . esc_html($c->nome) . '</option>';
        }
        $html .= '</select>';

        return $html;
    }
}

// ============================================================================
// [v13.7.0 BLOCO 3 RESIDUAL] WHERE CLAUSE PARA CENTRO DE CUSTO
// ============================================================================
if (!function_exists('sige_fin_centro_where_clause')) {
    /**
     * Gera fragmento SQL pronto-a-concatenar para filtrar por centro.
     *
     * Esta helper codifica o idiom que já estava espalhado pelas views:
     *
     *     $_centro_sql = $centro_id_filtro > 0
     *         ? ' AND centro_id = ' . (int)$centro_id_filtro
     *         : '';
     *
     * Consolidado aqui como ponto único. Evita repetição e elimina o risco
     * de cada view inventar uma variante ligeiramente diferente.
     *
     * Nota sobre segurança: o valor é interpolado directamente com cast a
     * (int), sem passar por prepare(). Isto segue o padrão já usado em
     * dashboard, extratos, etc. e é seguro porque:
     *
     *   1. O input é sempre um int (coerção na chamada);
     *   2. O valor vem de sige_fin_centro_ativo() que valida contra a lista
     *      de centros activos da escola (defesa multi-tenant);
     *   3. O alias passa por whitelist regex (só [A-Za-z0-9_]).
     *
     * Alternativa com prepare(): o caller teria de gerir um array de params
     * extras. Para manter o DX fluente (concatenação directa num $where_sql
     * já construído), optamos pela interpolação segura.
     *
     * @param int    $centro_id ID do centro. <= 0 → string vazia ("todos").
     * @param string $alias     Prefixo de tabela opcional. '' → 'centro_id';
     *                          'l' → 'l.centro_id'. Caracteres inválidos
     *                          são removidos por segurança.
     * @return string Fragmento SQL com espaço no início e fim, ou string vazia.
     *
     * @example
     *   $sql = "SELECT COUNT(*) FROM {$tD} WHERE escola_id = %d"
     *        . sige_fin_centro_where_clause($centro_id_filtro);
     *   $rows = $wpdb->get_var($wpdb->prepare($sql, $escola_id));
     *
     * @example com alias
     *   $sql = "SELECT * FROM {$tL} l WHERE l.escola_id = %d"
     *        . sige_fin_centro_where_clause($centro_id_filtro, 'l');
     */
    function sige_fin_centro_where_clause(int $centro_id, string $alias = ''): string {
        if ($centro_id <= 0) return '';

        // Whitelist defensiva do alias: só alfanuméricos e underscore.
        // Se o caller passou algo estranho (aspas, espaços, ponto),
        // strip tudo que não seja [A-Za-z0-9_]. Alias vazio é OK.
        $alias_clean = preg_replace('/[^A-Za-z0-9_]/', '', $alias);
        $prefix = ($alias_clean !== '') ? ($alias_clean . '.') : '';

        return ' AND ' . $prefix . 'centro_id = ' . (int)$centro_id . ' ';
    }
}
