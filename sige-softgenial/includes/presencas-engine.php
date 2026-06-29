<?php
/**
 * SIGE SoftGenial - Presenças Académicas (derivadas da Portaria)
 *
 * Fonte de verdade: sige_acessos (o log imutável do scanner da Portaria).
 * Presente no dia D = existe pelo menos uma 'entrada' nesse dia.
 * Atraso = primeira entrada depois da hora de corte da escola.
 * A tabela sige_presencas_excecoes guarda correcções humanas
 * (falta justificada, presença manual, dispensa) e VENCE a derivação.
 *
 * Estados de célula:
 *   P  presente (derivado)        AT atraso (derivado)
 *   F  falta (derivado)           J  falta justificada (excepção)
 *   PM presente manual (excepção) D  dispensado (excepção)
 *
 * O núcleo de derivação é puro (sem WordPress) para ser testável em CLI:
 * ver tools/smoke-presencas.php.
 */

if (!defined('ABSPATH') && !defined('SIGE_PRESENCAS_TEST_MODE')) exit;

// ============================================================================
// NÚCLEO PURO (testável em isolamento)
// ============================================================================

if (!function_exists('sige_presencas_derivar_estado')) {
    /**
     * Deriva o estado de UM aluno num dia.
     *
     * @param array       $entradas_horas Horas das entradas do dia, formato 'H:i:s'
     * @param array|null  $excecao        ['estado' => 'falta_justificada'|'presente_manual'|'dispensado'] ou null
     * @param string      $hora_corte     'H:i' (ex.: '07:30')
     * @return string P | AT | F | J | PM | D
     */
    function sige_presencas_derivar_estado(array $entradas_horas, ?array $excecao, string $hora_corte): string {
        if (is_array($excecao) && !empty($excecao['estado'])) {
            $mapa = ['falta_justificada' => 'J', 'presente_manual' => 'PM', 'dispensado' => 'D'];
            $e = (string)$excecao['estado'];
            if (isset($mapa[$e])) return $mapa[$e];
        }
        if (empty($entradas_horas)) return 'F';
        $primeira = min($entradas_horas);
        $corte = $hora_corte . (strlen($hora_corte) === 5 ? ':00' : '');
        return (strcmp(substr($primeira, 0, 8), $corte) > 0) ? 'AT' : 'P';
    }
}

if (!function_exists('sige_presencas_estados_permitidos')) {
    /** Estados que a edicao humana pode escrever ('auto' remove a excepcao). */
    function sige_presencas_estados_permitidos(): array {
        return ['falta_justificada', 'presente_manual', 'dispensado', 'auto'];
    }
}

if (!function_exists('sige_presencas_validar_item')) {
    /**
     * Valida UM item de marcacao (celula). Nucleo puro, partilhado pela marcacao
     * de celula unica e pela marcacao em lote: a mesma regra vale para as duas.
     *
     * @param int    $aluno_id
     * @param string $data   'Y-m-d'
     * @param string $estado um de sige_presencas_estados_permitidos()
     * @param string $hoje   'Y-m-d' (a data corrente da escola, injectada)
     * @return string|null   null se valido; mensagem de erro caso contrario
     */
    function sige_presencas_validar_item(int $aluno_id, string $data, string $estado, string $hoje): ?string {
        if ($aluno_id <= 0) return 'Aluno invalido.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) return 'Data invalida.';
        if ($data > $hoje) return 'Nao e possivel marcar dias futuros.';
        if (!in_array($estado, sige_presencas_estados_permitidos(), true)) return 'Estado invalido.';
        return null;
    }
}

if (!function_exists('sige_presencas_dias_lectivos')) {
    /**
     * Dias lectivos (segunda a sexta) de um mês. Devolve lista de
     * ['dia' => int, 'data' => 'Y-m-d', 'semana' => 'Seg'|...].
     */
    function sige_presencas_dias_lectivos(int $ano, int $mes): array {
        $nomes = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 7 => 'Dom'];
        $out = [];
        $n = (int)cal_days_in_month(CAL_GREGORIAN, $mes, $ano);
        for ($d = 1; $d <= $n; $d++) {
            $ts = mktime(12, 0, 0, $mes, $d, $ano);
            $dow = (int)date('N', $ts);
            if ($dow >= 6) continue; // fim-de-semana fora do mapa v1
            $out[] = ['dia' => $d, 'data' => date('Y-m-d', $ts), 'semana' => $nomes[$dow]];
        }
        return $out;
    }
}

if (!function_exists('sige_presencas_totais_linha')) {
    /** Conta estados de uma linha e calcula a percentagem de presença. */
    function sige_presencas_totais_linha(array $estados): array {
        $c = ['P' => 0, 'AT' => 0, 'F' => 0, 'J' => 0, 'PM' => 0, 'D' => 0];
        foreach ($estados as $e) { if (isset($c[$e])) $c[$e]++; }
        $consideradas = count($estados) - $c['D']; // dispensas fora do denominador
        $presentes = $c['P'] + $c['AT'] + $c['PM'];
        $pct = $consideradas > 0 ? round(($presentes / $consideradas) * 100, 1) : 0.0;
        return ['contagens' => $c, 'pct_presenca' => $pct];
    }
}

if (!function_exists('sige_presencas_totais_por_dia')) {
    /**
     * Conta, por dia, quantos alunos estiveram presentes (P + AT + PM).
     * @param array $alunos linhas com ['estados' => [dia => estado]]
     * @param array $dias   lista de ['dia' => int]
     * @return array [dia => int]
     */
    function sige_presencas_totais_por_dia(array $alunos, array $dias): array {
        $out = [];
        foreach ($dias as $d) {
            $n = 0;
            foreach ($alunos as $a) {
                $e = $a['estados'][$d['dia']] ?? '';
                if ($e === 'P' || $e === 'AT' || $e === 'PM') $n++;
            }
            $out[$d['dia']] = $n;
        }
        return $out;
    }
}

// ============================================================================
// INTEGRAÇÃO WORDPRESS
// ============================================================================
if (!defined('SIGE_PRESENCAS_TEST_MODE')) {

    if (!function_exists('sige_presencas_hora_corte')) {
        function sige_presencas_hora_corte(): string {
            $h = (string) get_option('sige_presencas_hora_corte', '07:30');
            return preg_match('/^\d{2}:\d{2}$/', $h) ? $h : '07:30';
        }
    }

    if (!function_exists('sige_presencas_pode_ver')) {
        function sige_presencas_pode_ver(): bool {
            if (function_exists('sige_can') && sige_can('academico.presencas_ver')) return true;
            if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) return true;
            foreach (['sige_director','sige_secretaria_geral','sige_secretario','sige_professor'] as $cap) {
                if (current_user_can($cap)) return true;
            }
            return false;
        }
    }
    if (!function_exists('sige_presencas_pode_editar')) {
        function sige_presencas_pode_editar(): bool {
            if (function_exists('sige_can') && sige_can('academico.presencas_gerir')) return true;
            if (function_exists('sige_page_guard_is_real_admin') && sige_page_guard_is_real_admin()) return true;
            foreach (['sige_director','sige_secretaria_geral','sige_secretario','sige_professor'] as $cap) {
                if (current_user_can($cap)) return true;
            }
            return false;
        }
    }

    if (!function_exists('sige_presencas_grelha_mes')) {
        /**
         * Grelha completa do mês para uma turma.
         * Devolve ['dias'=>[], 'alunos'=>[['id','nome','estados'=>[dia=>estado],'horas'=>[dia=>'H:i'],'totais'=>...]]].
         */
        function sige_presencas_grelha_mes(int $escola_id, int $turma_id, int $ano, int $mes): array {
            global $wpdb;
            $tA = $wpdb->prefix . 'sige_alunos';
            $tM = $wpdb->prefix . 'sige_matriculas';
            $tAc = $wpdb->prefix . 'sige_acessos';
            $tE = $wpdb->prefix . 'sige_presencas_excecoes';

            $dias = sige_presencas_dias_lectivos($ano, $mes);
            if (empty($dias)) return ['dias' => [], 'alunos' => []];
            $ini = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
            $fim = sprintf('%04d-%02d-%02d 23:59:59', $ano, $mes, (int)cal_days_in_month(CAL_GREGORIAN, $mes, $ano));
            $ano_lectivo = function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : (int)date('Y');

            $cond_aluno = function_exists('sige_aluno_activo_sql')
                ? sige_aluno_activo_sql('a')
                : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo'))";

            $alunos = $wpdb->get_results($wpdb->prepare(
                "SELECT a.id, a.nome_completo, a.numero_processo, a.genero
                   FROM {$tA} a
                  INNER JOIN {$tM} m ON m.aluno_id = a.id AND m.escola_id = a.escola_id
                                    AND m.ano_letivo = %d AND m.turma_id = %d
                  WHERE a.escola_id = %d AND {$cond_aluno}
                  ORDER BY a.nome_completo ASC",
                $ano_lectivo, $turma_id, $escola_id
            ));
            if (empty($alunos)) return ['dias' => $dias, 'alunos' => []];

            $ids = wp_list_pluck($alunos, 'id');
            $ph = implode(',', array_fill(0, count($ids), '%d'));

            // Entradas do mês (uma query)
            $acessos = $wpdb->get_results($wpdb->prepare(
                "SELECT aluno_id, DATE(data_hora) AS d, TIME(data_hora) AS h
                   FROM {$tAc}
                  WHERE escola_id = %d AND tipo = 'entrada'
                    AND data_hora BETWEEN %s AND %s AND aluno_id IN ({$ph})",
                array_merge([$escola_id, $ini, $fim], $ids)
            ));
            $entradas = [];
            foreach ((array)$acessos as $r) {
                $entradas[(int)$r->aluno_id][(string)$r->d][] = (string)$r->h;
            }

            // Excepções do mês (uma query)
            $exrows = $wpdb->get_results($wpdb->prepare(
                "SELECT aluno_id, data, estado, motivo
                   FROM {$tE}
                  WHERE escola_id = %d AND data BETWEEN %s AND %s AND aluno_id IN ({$ph})",
                array_merge([$escola_id, substr($ini, 0, 10), substr($fim, 0, 10)], $ids)
            ));
            $excecoes = [];
            foreach ((array)$exrows as $r) {
                $excecoes[(int)$r->aluno_id][(string)$r->data] = ['estado' => (string)$r->estado, 'motivo' => (string)$r->motivo];
            }

            $corte = sige_presencas_hora_corte();
            $hoje = (string) current_time('Y-m-d');
            $out = [];
            foreach ($alunos as $a) {
                $linha = ['id' => (int)$a->id, 'nome' => (string)$a->nome_completo, 'processo' => (string)$a->numero_processo, 'genero' => strtoupper(substr((string)($a->genero ?? ''), 0, 1)), 'estados' => [], 'horas' => [], 'motivos' => []];
                $estados_para_totais = [];
                foreach ($dias as $d) {
                    if ($d['data'] > $hoje) { $linha['estados'][$d['dia']] = ''; continue; } // futuro em branco
                    $eh = $entradas[(int)$a->id][$d['data']] ?? [];
                    $ex = $excecoes[(int)$a->id][$d['data']] ?? null;
                    $estado = sige_presencas_derivar_estado($eh, $ex, $corte);
                    $linha['estados'][$d['dia']] = $estado;
                    $estados_para_totais[] = $estado;
                    if (!empty($eh)) $linha['horas'][$d['dia']] = substr(min($eh), 0, 5);
                    if ($ex && !empty($ex['motivo'])) $linha['motivos'][$d['dia']] = $ex['motivo'];
                }
                $linha['totais'] = sige_presencas_totais_linha($estados_para_totais);
                $out[] = $linha;
            }
            return ['dias' => $dias, 'alunos' => $out, 'hora_corte' => $corte];
        }
    }

    if (!function_exists('sige_presencas_linhas_para')) {
        /**
         * Recalcula as linhas de um conjunto de alunos para um mes, com os MESMOS
         * derivadores e totalizadores canonicos da grelha. Existe para que toda a
         * escrita (celula ou lote) devolva os totais ja calculados pelo servidor:
         * o cliente nunca recalcula percentagens ou contagens.
         *
         * @return array [aluno_id => ['estados'=>[dia=>e],'horas'=>[dia=>'H:i'],'motivos'=>[dia=>txt],'totais'=>...]]
         */
        function sige_presencas_linhas_para(int $escola_id, array $aluno_ids, int $ano, int $mes): array {
            global $wpdb;
            $ids = array_values(array_unique(array_filter(array_map('intval', $aluno_ids), static function ($v) { return $v > 0; })));
            if (empty($ids)) return [];
            $dias = sige_presencas_dias_lectivos($ano, $mes);
            if (empty($dias)) return [];

            $tAc = $wpdb->prefix . 'sige_acessos';
            $tE  = $wpdb->prefix . 'sige_presencas_excecoes';
            $ini = sprintf('%04d-%02d-01 00:00:00', $ano, $mes);
            $fim = sprintf('%04d-%02d-%02d 23:59:59', $ano, $mes, (int)cal_days_in_month(CAL_GREGORIAN, $mes, $ano));
            $ph = implode(',', array_fill(0, count($ids), '%d'));

            $acessos = $wpdb->get_results($wpdb->prepare(
                "SELECT aluno_id, DATE(data_hora) AS d, TIME(data_hora) AS h
                   FROM {$tAc}
                  WHERE escola_id = %d AND tipo = 'entrada'
                    AND data_hora BETWEEN %s AND %s AND aluno_id IN ({$ph})",
                array_merge([$escola_id, $ini, $fim], $ids)
            ));
            $entradas = [];
            foreach ((array)$acessos as $r) { $entradas[(int)$r->aluno_id][(string)$r->d][] = (string)$r->h; }

            $exrows = $wpdb->get_results($wpdb->prepare(
                "SELECT aluno_id, data, estado, motivo
                   FROM {$tE}
                  WHERE escola_id = %d AND data BETWEEN %s AND %s AND aluno_id IN ({$ph})",
                array_merge([$escola_id, substr($ini, 0, 10), substr($fim, 0, 10)], $ids)
            ));
            $excecoes = [];
            foreach ((array)$exrows as $r) {
                $excecoes[(int)$r->aluno_id][(string)$r->data] = ['estado' => (string)$r->estado, 'motivo' => (string)$r->motivo];
            }

            $corte = sige_presencas_hora_corte();
            $hoje = (string) current_time('Y-m-d');
            $out = [];
            foreach ($ids as $aluno_id) {
                $linha = ['estados' => [], 'horas' => [], 'motivos' => []];
                $estados_para_totais = [];
                foreach ($dias as $d) {
                    if ($d['data'] > $hoje) { $linha['estados'][$d['dia']] = ''; continue; }
                    $eh = $entradas[$aluno_id][$d['data']] ?? [];
                    $ex = $excecoes[$aluno_id][$d['data']] ?? null;
                    $estado = sige_presencas_derivar_estado($eh, $ex, $corte);
                    $linha['estados'][$d['dia']] = $estado;
                    $estados_para_totais[] = $estado;
                    if (!empty($eh)) $linha['horas'][$d['dia']] = substr(min($eh), 0, 5);
                    if ($ex && !empty($ex['motivo'])) $linha['motivos'][$d['dia']] = $ex['motivo'];
                }
                $linha['totais'] = sige_presencas_totais_linha($estados_para_totais);
                $out[$aluno_id] = $linha;
            }
            return $out;
        }
    }

    if (!function_exists('sige_presencas_aplicar_excecao')) {
        /**
         * Aplica UMA excepcao (ou remove-a, se 'auto'). Fonte unica da escrita,
         * partilhada pela celula e pelo lote. Multi-tenant: escola_id em toda a
         * query, $wpdb->prepare implicito em insert/update/delete tipados.
         * Nao toca em nenhuma regra de calculo financeiro ou academico.
         */
        function sige_presencas_aplicar_excecao(int $escola_id, int $aluno_id, string $data, string $estado, string $motivo): void {
            global $wpdb;
            // Fail-closed: sem escola valida nao se escreve (evita fuga cross-tenant).
            if (!sige_tenant_write_guard($escola_id, 'sige_presencas_aplicar_excecao')) {
                return;
            }
            $tE = $wpdb->prefix . 'sige_presencas_excecoes';
            if ($estado === 'auto') {
                $wpdb->delete($tE, ['escola_id' => $escola_id, 'aluno_id' => $aluno_id, 'data' => $data], ['%d','%d','%s']);
                return;
            }
            $existe = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tE} WHERE escola_id=%d AND aluno_id=%d AND data=%s",
                $escola_id, $aluno_id, $data
            ));
            $row = [
                'escola_id' => $escola_id, 'aluno_id' => $aluno_id, 'data' => $data,
                'estado' => $estado, 'motivo' => $motivo,
                'criado_por' => get_current_user_id(), 'criado_em' => current_time('mysql'),
            ];
            if ($existe > 0) {
                $wpdb->update($tE, $row, ['id' => $existe], ['%d','%d','%s','%s','%s','%d','%s'], ['%d']);
            } else {
                $wpdb->insert($tE, $row, ['%d','%d','%s','%s','%s','%d','%s']);
            }
        }
    }

    if (!function_exists('sige_presencas_relatorio_dados')) {
        /**
         * Dados completos para o Mapa Mensal de Assiduidade (formato oficial):
         * cabeçalho da escola, identificação da turma, grelha, totais por dia
         * e agregados M/F. O layout exacto MINEDH afina-se quando o modelo
         * em papel chegar; a estrutura de dados já cobre o que esses mapas pedem.
         */
        function sige_presencas_relatorio_dados(int $escola_id, int $turma_id, int $ano, int $mes): array {
            global $wpdb;
            $g = sige_presencas_grelha_mes($escola_id, $turma_id, $ano, $mes);

            $perfil = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
            $tT = $wpdb->prefix . 'sige_turmas';
            $turma = $wpdb->get_row($wpdb->prepare(
                "SELECT nome, classe FROM {$tT} WHERE id = %d AND escola_id = %d", $turma_id, $escola_id
            ));

            $m = 0; $f = 0; $soma_pct = 0.0;
            foreach ($g['alunos'] as $a) {
                if (($a['genero'] ?? '') === 'M') $m++;
                elseif (($a['genero'] ?? '') === 'F') $f++;
                $soma_pct += (float)($a['totais']['pct_presenca'] ?? 0);
            }
            $n = count($g['alunos']);

            return [
                'escola' => [
                    'nome' => $perfil && !empty($perfil->nome) ? (string)$perfil->nome : get_bloginfo('name'),
                    'endereco' => $perfil && !empty($perfil->endereco) ? (string)$perfil->endereco : '',
                    'telefone' => $perfil && !empty($perfil->telefone) ? (string)$perfil->telefone : '',
                    'logo_url' => $perfil && !empty($perfil->logo_url) ? (string)$perfil->logo_url : '',
                ],
                'turma' => [
                    'nome' => $turma ? (string)$turma->nome : ('#' . $turma_id),
                    'classe' => $turma ? (string)$turma->classe : '',
                ],
                'ano' => $ano,
                'mes' => $mes,
                'ano_lectivo' => function_exists('sige_get_ano_lectivo_atual') ? (int)sige_get_ano_lectivo_atual() : $ano,
                'dias' => $g['dias'],
                'alunos' => $g['alunos'],
                'hora_corte' => $g['hora_corte'] ?? '07:30',
                'totais_dia' => sige_presencas_totais_por_dia($g['alunos'], $g['dias']),
                'resumo' => [
                    'total' => $n, 'masculino' => $m, 'feminino' => $f,
                    'media_presenca' => $n > 0 ? round($soma_pct / $n, 1) : 0.0,
                ],
            ];
        }
    }

    // ── AJAX: grelha (para mudança de turma/mês sem reload) ────────────────
    add_action('wp_ajax_sige_presencas_grid', function () {
        if (!sige_presencas_pode_ver()) wp_send_json_error('Sem permissão para ver presenças.');
        check_ajax_referer('sige_presencas', '_wpnonce');
        $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $turma_id = (int)($_POST['turma_id'] ?? 0);
        $ano = (int)($_POST['ano'] ?? current_time('Y'));
        $mes = (int)($_POST['mes'] ?? current_time('n'));
        if ($turma_id <= 0 || $mes < 1 || $mes > 12) wp_send_json_error('Parâmetros inválidos.');
        wp_send_json_success(sige_presencas_grelha_mes($escola_id, $turma_id, $ano, $mes));
    });

    // ── AJAX: marcar excepção (justificar / presença manual / dispensa) ────
    add_action('wp_ajax_sige_presencas_marcar', function () {
        if (!sige_presencas_pode_editar()) wp_send_json_error('Sem permissão para corrigir presenças.');
        check_ajax_referer('sige_presencas', '_wpnonce');

        $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $aluno_id = (int)($_POST['aluno_id'] ?? 0);
        $data = sanitize_text_field((string)($_POST['data'] ?? ''));
        $estado = sanitize_key((string)($_POST['estado'] ?? ''));
        $motivo = sanitize_text_field(wp_unslash((string)($_POST['motivo'] ?? '')));

        $erro = sige_presencas_validar_item($aluno_id, $data, $estado, (string) current_time('Y-m-d'));
        if ($erro !== null) wp_send_json_error($erro);

        sige_presencas_aplicar_excecao($escola_id, $aluno_id, $data, $estado, $motivo);

        if (function_exists('sige_security_log')) {
            sige_security_log('presenca_corrigida', "aluno={$aluno_id} data={$data} estado={$estado} user=" . get_current_user_id());
        }

        // Estado recalculado da célula e linha recalculada (totais sempre do servidor).
        global $wpdb;
        $tAc = $wpdb->prefix . 'sige_acessos';
        $eh = $wpdb->get_col($wpdb->prepare(
            "SELECT TIME(data_hora) FROM {$tAc} WHERE escola_id=%d AND aluno_id=%d AND tipo='entrada' AND DATE(data_hora)=%s",
            $escola_id, $aluno_id, $data
        ));
        $ex = ($estado === 'auto') ? null : ['estado' => $estado];
        $ano = (int) substr($data, 0, 4);
        $mes = (int) substr($data, 5, 2);
        $linhas = sige_presencas_linhas_para($escola_id, [$aluno_id], $ano, $mes);
        wp_send_json_success([
            'estado' => sige_presencas_derivar_estado((array)$eh, $ex, sige_presencas_hora_corte()),
            'aluno_id' => $aluno_id,
            'linha' => $linhas[$aluno_id] ?? null,
        ]);
    });

    // ── AJAX: marcar excepções em LOTE (caminho rápido da secretaria) ──────
    // Aplica a mesma regra de validação e a mesma escrita da célula única, mas a
    // muitas células numa só ida ao servidor. Devolve as linhas afectadas já com
    // os totais recalculados pelo servidor (o cliente nunca recalcula nada).
    add_action('wp_ajax_sige_presencas_marcar_lote', function () {
        if (!sige_presencas_pode_editar()) wp_send_json_error('Sem permissão para corrigir presenças.');
        check_ajax_referer('sige_presencas', '_wpnonce');

        $escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
        $motivo = sanitize_text_field(wp_unslash((string)($_POST['motivo'] ?? '')));

        $bruto = json_decode(wp_unslash((string)($_POST['itens'] ?? '')), true);
        if (!is_array($bruto) || empty($bruto)) wp_send_json_error('Nenhuma célula seleccionada.');

        $LIMITE = 1000; // tecto defensivo: ~50 alunos x 20 dias lectivos
        if (count($bruto) > $LIMITE) wp_send_json_error('Selecção demasiado grande. Divida em lotes mais pequenos.');

        $hoje = (string) current_time('Y-m-d');
        $itens = [];
        foreach ($bruto as $i => $it) {
            if (!is_array($it)) wp_send_json_error('Item inválido na posição ' . (int)$i . '.');
            $aluno_id = (int)($it['aluno_id'] ?? 0);
            $data = sanitize_text_field((string)($it['data'] ?? ''));
            $estado = sanitize_key((string)($it['estado'] ?? ''));
            $erro = sige_presencas_validar_item($aluno_id, $data, $estado, $hoje);
            if ($erro !== null) wp_send_json_error('Item inválido (' . esc_html($data) . '): ' . $erro);
            $itens[] = ['aluno_id' => $aluno_id, 'data' => $data, 'estado' => $estado];
        }

        // Aplicar tudo (a validação acima já passou para todos).
        $alunos_afectados = [];
        $estados_vistos = [];
        foreach ($itens as $it) {
            sige_presencas_aplicar_excecao($escola_id, $it['aluno_id'], $it['data'], $it['estado'], $motivo);
            $alunos_afectados[$it['aluno_id']] = true;
            $estados_vistos[$it['estado']] = true;
        }

        if (function_exists('sige_security_log')) {
            sige_security_log('presenca_corrigida_lote',
                'n=' . count($itens) . ' alunos=' . count($alunos_afectados)
                . ' estados=' . implode('|', array_keys($estados_vistos))
                . ' user=' . get_current_user_id());
        }

        // Recalcular as linhas afectadas para o mês corrente da grelha.
        $ano = (int)($_POST['ano'] ?? current_time('Y'));
        $mes = (int)($_POST['mes'] ?? current_time('n'));
        if ($mes < 1 || $mes > 12) { $ano = (int) substr($itens[0]['data'], 0, 4); $mes = (int) substr($itens[0]['data'], 5, 2); }
        $linhas = sige_presencas_linhas_para($escola_id, array_keys($alunos_afectados), $ano, $mes);

        wp_send_json_success(['aplicados' => count($itens), 'linhas' => $linhas]);
    });
}
