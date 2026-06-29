<?php
/**
 * SIGE SoftGenial - Pesquisa Global (P3, v12.15.21)
 * ==========================================================
 *
 * Caixa de pesquisa unica na barra de topo da shell, acessivel de qualquer
 * ecra. Pesquisa, de uma so vez e por AJAX, nas entidades de alta frequencia:
 * Alunos, Turmas e Recibos. Cada grupo so e devolvido se o utilizador tiver a
 * permissao respectiva, e toda a query e isolada por escola (multi-tenant).
 *
 * REGRAS:
 *   1. Multi-tenant: escola_id em TODA a query, sempre $wpdb->prepare.
 *   2. Permissao por grupo: alunos.ver, academico.turmas_ver,
 *      financeiro.extractos_ver/financeiro.pagar. Sem permissao, o grupo nem e
 *      consultado.
 *   3. Nonce: usa o nonce global ja auto-injectado (_sige_nonce_g) via
 *      sige_check_nonce_global().
 *   4. Apenas leitura. Nao toca em formulas, valores nem queries financeiras
 *      existentes; nao escreve nada.
 *   5. Deep-links que ja funcionam no sistema (lista de alunos por filtro_search,
 *      recibo por sige_print=recibo&id, lista de turmas).
 *
 * @package SIGE\Pesquisa
 * @since   12.15.21
 */

if (!defined('ABSPATH')) exit;

add_action('wp_ajax_sige_pesquisa_global', 'sige_pesquisa_global_handler');

if (!function_exists('sige_pesquisa_global_pode')) {
    /** Verificacao de permissao com fallback seguro (nega se nao houver matriz). */
    function sige_pesquisa_global_pode(string $permissao): bool {
        return function_exists('sige_can') ? (bool) sige_can($permissao) : false;
    }
}

if (!function_exists('sige_pesquisa_global_handler')) {
    function sige_pesquisa_global_handler() {
        // Nonce (sai com erro JSON se invalido).
        if (function_exists('sige_check_nonce_global')) {
            sige_check_nonce_global();
        } else {
            wp_send_json_error(['message' => 'Pesquisa indisponivel.'], 500);
        }

        $eid = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        if ($eid <= 0) {
            wp_send_json_error(['message' => 'Contexto de escola invalido.'], 400);
        }

        $termo = isset($_POST['q']) ? sanitize_text_field((string) wp_unslash($_POST['q'])) : '';
        $termo = trim($termo);
        if (function_exists('mb_strlen') ? mb_strlen($termo) < 2 : strlen($termo) < 2) {
            wp_send_json_success(['termo' => $termo, 'grupos' => [], 'curto' => true]);
        }

        $limite = 6;
        $grupos = [];

        // Blindagem: estas consultas sao so leitura. Silenciar erros de BD evita
        // que um erro de coluna imprima texto no output e corrompa o JSON (que e
        // o que faz o cliente cair em "erro de comunicacao"). Cada grupo corre
        // dentro de try/catch para que uma falha pontual nao derrube a pesquisa
        // toda; o estado anterior do $wpdb e restaurado no fim.
        global $wpdb;
        $prev_suppress = $wpdb->suppress_errors(true);

        $coletar = function (callable $fn, string $tipo, string $titulo, string $icone) use (&$grupos, $eid, $termo, $limite) {
            try {
                $itens = $fn($eid, $termo, $limite);
                if (!empty($itens)) {
                    $grupos[] = ['tipo' => $tipo, 'titulo' => $titulo, 'icone' => $icone, 'itens' => $itens];
                }
            } catch (\Throwable $e) {
                // grupo indisponivel: ignora-se sem quebrar os restantes
            }
        };

        if (sige_pesquisa_global_pode('alunos.ver')) {
            $coletar('sige_pesquisa_global_alunos', 'alunos', 'Alunos', 'users');
        }
        if (sige_pesquisa_global_pode('academico.turmas_ver')) {
            $coletar('sige_pesquisa_global_turmas', 'turmas', 'Turmas', 'layers');
        }
        if (sige_pesquisa_global_pode('financeiro.extractos_ver') || sige_pesquisa_global_pode('financeiro.pagar')) {
            $coletar('sige_pesquisa_global_recibos', 'recibos', 'Recibos', 'receipt');
        }
        if (sige_pesquisa_global_pode('financeiro.planos_ver') || sige_pesquisa_global_pode('financeiro.planos_gerir')) {
            $coletar('sige_pesquisa_global_planos', 'planos', 'Planos de pagamento', 'plan');
        }
        if (sige_pesquisa_global_pode('financeiro.despesas_ver')) {
            $coletar('sige_pesquisa_global_despesas', 'despesas', 'Despesas', 'expense');
        }

        $wpdb->suppress_errors($prev_suppress);
        wp_send_json_success(['termo' => $termo, 'grupos' => $grupos]);
    }
}

if (!function_exists('sige_pesquisa_global_alunos')) {
    function sige_pesquisa_global_alunos(int $eid, string $termo, int $limite): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($termo) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.nome_completo, a.numero_processo, t.classe, t.nome AS turma_nome
               FROM {$wpdb->prefix}sige_alunos a
               LEFT JOIN {$wpdb->prefix}sige_matriculas m ON (a.id = m.aluno_id)
               LEFT JOIN {$wpdb->prefix}sige_turmas t ON (m.turma_id = t.id AND t.escola_id = a.escola_id)
              WHERE a.escola_id = %d
                AND (a.nome_completo LIKE %s
                     OR a.numero_processo LIKE %s
                     OR a.contacto_encarregado LIKE %s
                     OR a.telemovel_pai LIKE %s
                     OR a.telemovel_mae LIKE %s)
              GROUP BY a.id
              ORDER BY a.nome_completo ASC
              LIMIT %d",
            $eid, $like, $like, $like, $like, $like, $limite
        ));
        $out = [];
        foreach ((array) $rows as $r) {
            $sub_partes = [];
            if (!empty($r->numero_processo)) $sub_partes[] = 'Nº ' . $r->numero_processo;
            if (!empty($r->turma_nome)) $sub_partes[] = trim(((string) $r->classe) . ' ' . ((string) $r->turma_nome));
            $alvo = !empty($r->numero_processo) ? (string) $r->numero_processo : (string) $r->nome_completo;
            $out[] = [
                'titulo' => (string) $r->nome_completo,
                'sub'    => implode(' · ', $sub_partes),
                'url'    => admin_url('admin.php?page=sige-app&view=alunos_lista&filtro_search=' . rawurlencode($alvo)),
            ];
        }
        return $out;
    }
}

if (!function_exists('sige_pesquisa_global_turmas')) {
    function sige_pesquisa_global_turmas(int $eid, string $termo, int $limite): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($termo) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT t.id, t.nome, t.classe,
                    (SELECT COUNT(*) FROM {$wpdb->prefix}sige_matriculas m
                      WHERE m.turma_id = t.id) AS n_alunos
               FROM {$wpdb->prefix}sige_turmas t
              WHERE t.escola_id = %d
                AND (t.nome LIKE %s OR t.classe LIKE %s)
              ORDER BY t.classe ASC, t.nome ASC
              LIMIT %d",
            $eid, $like, $like, $limite
        ));
        $out = [];
        foreach ((array) $rows as $r) {
            $sub_partes = [];
            if (!empty($r->classe)) $sub_partes[] = (string) $r->classe;
            if ($r->n_alunos !== null) $sub_partes[] = ((int) $r->n_alunos) . ' aluno(s)';
            $out[] = [
                'titulo' => (string) $r->nome,
                'sub'    => implode(' · ', $sub_partes),
                'url'    => admin_url('admin.php?page=sige-app&view=turmas'),
            ];
        }
        return $out;
    }
}

if (!function_exists('sige_pesquisa_global_recibos')) {
    function sige_pesquisa_global_recibos(int $eid, string $termo, int $limite): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($termo) . '%';
        // Um recibo agrupa varias linhas; pegamos a primeira linha de cada recibo
        // (MIN(id)) para o deep-link e os totais por recibo para o subtitulo.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.recibo_numero,
                    MIN(p.id) AS pagamento_id,
                    MAX(p.data_pagamento) AS data_pagamento,
                    MAX(p.data_efectiva) AS data_efectiva,
                    SUM(p.valor_pago) AS total,
                    MAX(a.nome_completo) AS aluno_nome
               FROM {$wpdb->prefix}sige_fin_pagamentos p
               LEFT JOIN {$wpdb->prefix}sige_alunos a ON (a.id = p.aluno_id)
              WHERE p.escola_id = %d
                AND p.recibo_numero <> ''
                AND p.recibo_numero NOT LIKE %s
                AND (p.recibo_numero LIKE %s OR a.nome_completo LIKE %s)
              GROUP BY p.recibo_numero
              ORDER BY MAX(p.data_pagamento) DESC
              LIMIT %d",
            $eid, 'PLN%', $like, $like, $limite
        ));
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        $out = [];
        foreach ((array) $rows as $r) {
            $dia = function_exists('sige_fin_recibo_data_efectiva_dia')
                ? sige_fin_recibo_data_efectiva_dia($r->data_pagamento, $r->data_efectiva)['dia']
                : substr((string) $r->data_pagamento, 0, 10);
            $data_lbl = function_exists('sige_doc_fin_date') ? sige_doc_fin_date($dia, false) : (string) $dia;
            $sub_partes = [];
            if (!empty($r->aluno_nome)) $sub_partes[] = (string) $r->aluno_nome;
            $sub_partes[] = number_format((float) $r->total, 2) . ' ' . $moeda;
            if ($data_lbl && $data_lbl !== '-') $sub_partes[] = $data_lbl;
            $out[] = [
                'titulo' => (string) $r->recibo_numero,
                'sub'    => implode(' · ', $sub_partes),
                'url'    => admin_url('admin.php?sige_print=recibo&id=' . (int) $r->pagamento_id),
            ];
        }
        return $out;
    }
}

if (!function_exists('sige_pesquisa_global_planos')) {
    function sige_pesquisa_global_planos(int $eid, string $termo, int $limite): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($termo) . '%';
        // Planos de pagamento vivem em sige_fin_pagamentos com prefixo PLN-.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT p.recibo_numero,
                    SUM(p.valor_pago) AS total,
                    MAX(p.data_pagamento) AS data_pagamento,
                    MAX(a.nome_completo) AS aluno_nome
               FROM {$wpdb->prefix}sige_fin_pagamentos p
               LEFT JOIN {$wpdb->prefix}sige_alunos a ON (a.id = p.aluno_id)
              WHERE p.escola_id = %d
                AND p.recibo_numero LIKE %s
                AND (p.recibo_numero LIKE %s OR a.nome_completo LIKE %s)
              GROUP BY p.recibo_numero
              ORDER BY MAX(p.data_pagamento) DESC
              LIMIT %d",
            $eid, 'PLN%', $like, $like, $limite
        ));
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        $out = [];
        foreach ((array) $rows as $r) {
            $data_lbl = function_exists('sige_doc_fin_date') ? sige_doc_fin_date(substr((string) $r->data_pagamento, 0, 10), false) : substr((string) $r->data_pagamento, 0, 10);
            $sub_partes = [];
            if (!empty($r->aluno_nome)) $sub_partes[] = (string) $r->aluno_nome;
            $sub_partes[] = number_format((float) $r->total, 2) . ' ' . $moeda;
            if ($data_lbl && $data_lbl !== '-') $sub_partes[] = $data_lbl;
            $out[] = [
                'titulo' => (string) $r->recibo_numero,
                'sub'    => implode(' · ', $sub_partes),
                'url'    => admin_url('admin.php?page=sige-app&view=financeiro-planos'),
            ];
        }
        return $out;
    }
}

if (!function_exists('sige_pesquisa_global_despesas')) {
    function sige_pesquisa_global_despesas(int $eid, string $termo, int $limite): array {
        global $wpdb;
        $like = '%' . $wpdb->esc_like($termo) . '%';
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.descricao, d.fornecedor, d.categoria, d.valor, d.referencia, d.data_despesa
               FROM {$wpdb->prefix}sige_fin_despesas d
              WHERE d.escola_id = %d
                AND (d.descricao LIKE %s OR d.fornecedor LIKE %s OR d.categoria LIKE %s OR d.referencia LIKE %s)
              ORDER BY d.data_despesa DESC
              LIMIT %d",
            $eid, $like, $like, $like, $like, $limite
        ));
        $moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
        $out = [];
        foreach ((array) $rows as $r) {
            $data_lbl = function_exists('sige_doc_fin_date') ? sige_doc_fin_date(substr((string) $r->data_despesa, 0, 10), false) : substr((string) $r->data_despesa, 0, 10);
            $sub_partes = [];
            if (!empty($r->fornecedor)) $sub_partes[] = (string) $r->fornecedor;
            $sub_partes[] = number_format((float) $r->valor, 2) . ' ' . $moeda;
            if ($data_lbl && $data_lbl !== '-') $sub_partes[] = $data_lbl;
            $titulo = !empty($r->descricao) ? (string) $r->descricao : ('Despesa #' . (int) $r->id);
            $out[] = [
                'titulo' => $titulo,
                'sub'    => implode(' · ', $sub_partes),
                'url'    => admin_url('admin.php?page=sige-app&view=financeiro-despesas'),
            ];
        }
        return $out;
    }
}
