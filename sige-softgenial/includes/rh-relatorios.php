<?php
/**
 * SIGE SoftGenial - Relatórios de RH (análise da equipa).
 *
 * Segundo tijolo do módulo de Recursos Humanos: o painel de Relatórios da
 * Equipa. Só LEITURA e AGREGAÇÃO de campos que já existem (categoria do
 * colaborador + ficha de sige_professores). Sem schema, sem writes, sem
 * fórmulas financeiras novas, sem alteração de permissões.
 *
 * A agregação é uma função PURA (sige_rh_build_reports) que recebe registos já
 * normalizados pela view (a mesma fonte das KPIs e da lista), para que os
 * números reconciliem com o resto do ecrã e para ser testável sem base de dados.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_rh_categoria_label')) {
    /** Rótulo humano da categoria do colaborador. */
    function sige_rh_categoria_label(string $slug): string {
        $map = [
            'docente'        => 'Docentes',
            'administrativo' => 'Administrativos',
            'apoio'          => 'Apoio',
        ];
        $slug = trim($slug);
        return $map[$slug] ?? ($slug === '' ? 'Sem categoria' : ucfirst(str_replace('_', ' ', $slug)));
    }
}

if (!function_exists('sige_rh_pretty_label')) {
    /** Rótulo legível para valores livres (nível de carreira, regime). */
    function sige_rh_pretty_label(string $value): string {
        $value = trim($value);
        if ($value === '' || $value === '0') return 'Não definido';
        $value = str_replace('_', ' ', $value);
        return function_exists('mb_convert_case') ? mb_convert_case($value, MB_CASE_TITLE, 'UTF-8') : ucwords($value);
    }
}

if (!function_exists('sige_rh_pct')) {
    /** Percentagem inteira segura (evita divisão por zero). */
    function sige_rh_pct(int $part, int $total): int {
        if ($total <= 0) return 0;
        return (int) round(($part / $total) * 100);
    }
}

if (!function_exists('sige_rh_build_reports')) {
    /**
     * Constrói os relatórios de RH a partir de registos normalizados (função PURA).
     *
     * Cada pessoa é um array com:
     *   'categoria'      (docente|administrativo|apoio)
     *   'tem_ficha'      (bool)  - tem registo em sige_professores
     *   'ativo'          (bool)
     *   'tipo_contrato'  (efectivo|contrato|estagio|'')
     *   'nivel_carreira' (string), 'regime_trabalho' (string)
     *   'data_admissao'  (Y-m-d|''), 'salario' (float)
     *   'nuit','telemovel','email' (string), 'foto' (bool)
     *
     * As distribuições descrevem a força de trabalho ACTIVA; inactivos são
     * contados à parte. A massa salarial cobre apenas activos com valor > 0.
     *
     * @param array $people Registos normalizados pela view.
     * @param array $opts   ['today'=>'Y-m-d'] data de referência (antiguidade).
     * @return array
     */
    function sige_rh_build_reports(array $people, array $opts = []): array {
        $today = isset($opts['today']) && $opts['today'] !== ''
            ? (string) $opts['today']
            : (function_exists('wp_date') ? wp_date('Y-m-d') : date('Y-m-d'));
        $today_ts = strtotime($today . ' 00:00:00');
        if ($today_ts === false) { $today_ts = strtotime('today'); }
        $this_year = (int) date('Y', $today_ts);

        $total      = count($people);
        $ativos     = 0;
        $inativos   = 0;
        $sem_ficha  = 0;

        $categoria = ['docente' => 0, 'administrativo' => 0, 'apoio' => 0];
        $vinculo   = ['efectivo' => 0, 'contrato' => 0, 'estagio' => 0, '' => 0];
        $carreira  = []; // chave => count
        $regime    = []; // chave => count

        $admissoes      = []; // 'YYYY' => count (activos com data)
        $tenure_sum     = 0.0; // anos
        $tenure_n       = 0;

        $sal_massa = 0.0;
        $sal_n     = 0;

        // Qualidade de dados (sobre activos): quantos têm cada campo preenchido.
        $qual = [
            'ficha'     => ['ok' => 0, 'falta' => 0],
            'nuit'      => ['ok' => 0, 'falta' => 0],
            'telemovel' => ['ok' => 0, 'falta' => 0],
            'email'     => ['ok' => 0, 'falta' => 0],
            'foto'      => ['ok' => 0, 'falta' => 0],
            'admissao'  => ['ok' => 0, 'falta' => 0],
        ];

        foreach ($people as $p) {
            $p = (array) $p;
            $ativo = !empty($p['ativo']);
            if (!$ativo) { $inativos++; continue; }
            $ativos++;

            // Categoria.
            $cat = isset($p['categoria']) ? (string) $p['categoria'] : '';
            if (!isset($categoria[$cat])) { $categoria[$cat] = 0; }
            $categoria[$cat]++;

            // Vínculo.
            $tc = isset($p['tipo_contrato']) ? trim((string) $p['tipo_contrato']) : '';
            if (!array_key_exists($tc, $vinculo)) { $vinculo[$tc] = 0; }
            $vinculo[$tc]++;

            // Nível de carreira / regime (agrupa por valor; vazio -> "não definido").
            $car = trim((string) ($p['nivel_carreira'] ?? ''));
            $cark = $car === '' ? '' : $car;
            $carreira[$cark] = ($carreira[$cark] ?? 0) + 1;

            $reg = trim((string) ($p['regime_trabalho'] ?? ''));
            $regk = $reg === '' ? '' : $reg;
            $regime[$regk] = ($regime[$regk] ?? 0) + 1;

            // Admissão / antiguidade.
            $adm = trim((string) ($p['data_admissao'] ?? ''));
            $has_adm = $adm !== '' && $adm !== '0000-00-00' && strtotime($adm) !== false;
            if ($has_adm) {
                $adm_ts = strtotime($adm . ' 00:00:00');
                $yr = date('Y', $adm_ts);
                $admissoes[$yr] = ($admissoes[$yr] ?? 0) + 1;
                $anos = ($today_ts - $adm_ts) / (365.25 * 86400);
                if ($anos >= 0) { $tenure_sum += $anos; $tenure_n++; }
                $qual['admissao']['ok']++;
            } else {
                $qual['admissao']['falta']++;
            }

            // Salário (só com valor > 0).
            $sal = (float) ($p['salario'] ?? 0);
            if ($sal > 0) { $sal_massa += $sal; $sal_n++; }

            // Qualidade.
            $tem_ficha = !empty($p['tem_ficha']);
            $qual['ficha'][$tem_ficha ? 'ok' : 'falta']++;
            $qual['nuit'][trim((string) ($p['nuit'] ?? '')) !== '' ? 'ok' : 'falta']++;
            $qual['telemovel'][trim((string) ($p['telemovel'] ?? '')) !== '' ? 'ok' : 'falta']++;
            $qual['email'][trim((string) ($p['email'] ?? '')) !== '' ? 'ok' : 'falta']++;
            $qual['foto'][!empty($p['foto']) ? 'ok' : 'falta']++;
            if (!$tem_ficha) { $sem_ficha++; }
        }

        // Ordena admissões por ano (cronológico) e mantém os últimos 6 anos.
        ksort($admissoes);
        if (count($admissoes) > 6) {
            $admissoes = array_slice($admissoes, -6, 6, true);
        }

        // Converte mapas livres em listas ordenadas por contagem desc (para barras).
        $to_list = static function (array $map): array {
            $out = [];
            foreach ($map as $k => $n) {
                $out[] = [
                    'slug'  => (string) $k,
                    'label' => sige_rh_pretty_label((string) $k),
                    'count' => (int) $n,
                ];
            }
            usort($out, static function ($a, $b) {
                if ($a['count'] === $b['count']) return strcmp($a['label'], $b['label']);
                return $b['count'] <=> $a['count'];
            });
            return $out;
        };

        return [
            'total'     => $total,
            'ativos'    => $ativos,
            'inativos'  => $inativos,
            'sem_ficha' => $sem_ficha,
            'categoria' => [
                ['slug' => 'docente',        'label' => sige_rh_categoria_label('docente'),        'count' => (int) $categoria['docente']],
                ['slug' => 'administrativo', 'label' => sige_rh_categoria_label('administrativo'), 'count' => (int) $categoria['administrativo']],
                ['slug' => 'apoio',          'label' => sige_rh_categoria_label('apoio'),          'count' => (int) $categoria['apoio']],
            ],
            'vinculo' => [
                ['slug' => 'efectivo', 'label' => 'Efectivo (Quadro)',   'count' => (int) $vinculo['efectivo']],
                ['slug' => 'contrato', 'label' => 'Contrato a Prazo',    'count' => (int) $vinculo['contrato']],
                ['slug' => 'estagio',  'label' => 'Estagiário',          'count' => (int) $vinculo['estagio']],
                ['slug' => '',         'label' => 'Vínculo não definido','count' => (int) $vinculo['']],
            ],
            'carreira'   => $to_list($carreira),
            'regime'     => $to_list($regime),
            'admissoes'  => $admissoes,
            'antiguidade_media' => $tenure_n > 0 ? round($tenure_sum / $tenure_n, 1) : 0.0,
            'salario'    => [
                'com_valor' => $sal_n,
                'massa'     => round($sal_massa, 2),
                'media'     => $sal_n > 0 ? round($sal_massa / $sal_n, 2) : 0.0,
            ],
            'qualidade'  => $qual,
        ];
    }
}
