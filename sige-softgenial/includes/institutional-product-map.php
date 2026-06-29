<?php
if (!defined('ABSPATH')) { exit; }

/**
 * SIGE SoftGenial v12.16.0 RC1
 * Institutional Product Map
 *
 * Camada de leitura para ordenar atalhos do painel por rotina real.
 * Nao grava dados, nao altera formulas, nao altera schema e nao substitui
 * as guardas centrais de rota. A matriz de permissoes continua a ser a
 * fonte de verdade.
 */

if (!function_exists('sige_ipu_url_v121600')) {
    function sige_ipu_url_v121600(string $view): string {
        $safe_view = preg_replace('/[^a-zA-Z0-9_\-]/', '', $view);
        if ($safe_view === '') { $safe_view = 'dashboard'; }
        if (function_exists('admin_url') && function_exists('add_query_arg')) {
            return (string) add_query_arg(['page' => 'sige-app', 'view' => $safe_view], admin_url('admin.php'));
        }
        return 'admin.php?page=sige-app&view=' . rawurlencode($safe_view);
    }
}

if (!function_exists('sige_ipu_can_any_v121600')) {
    /**
     * Verifica permissao apenas pela guarda central ja existente.
     * Sem alargamento de acesso: se a guarda nao existir, a acao fica oculta.
     */
    function sige_ipu_can_any_v121600(array $permissions, array $legacy_caps = []): bool {
        $permissions = array_values(array_filter(array_map('strval', $permissions), static function (string $permission): bool {
            return $permission !== '';
        }));
        $legacy_caps = array_values(array_filter(array_map('strval', $legacy_caps), static function (string $capability): bool {
            return $capability !== '';
        }));
        if (!function_exists('sige_page_guard_allows')) { return false; }
        return (bool) sige_page_guard_allows($permissions, $legacy_caps);
    }
}

if (!function_exists('sige_institutional_action_catalog_v121600')) {
    /**
     * Catalogo sem efeitos laterais. Cada item aponta para uma view ja existente.
     * A filtragem por permissao ocorre em sige_institutional_actions_v121600().
     *
     * @return array<int,array<string,mixed>>
     */
    function sige_institutional_action_catalog_v121600(): array {
        return [
            [
                'area' => 'tesouraria',
                'label' => 'Registar pagamento',
                'hint' => 'Ver divida, confirmar valor e emitir recibo',
                'view' => 'financeiro-pagamentos',
                'icon' => 'wallet',
                'priority' => 10,
                'permissions' => ['financeiro.pagar'],
                'legacy_caps' => ['sige_director', 'sige_financeiro', 'sige_secretario'],
                'color' => 'var(--color-info-500)',
                'soft' => 'var(--color-info-50)',
            ],
            [
                'area' => 'tesouraria',
                'label' => 'Devedores',
                'hint' => 'Priorizar cobrancas e saldos vencidos',
                'view' => 'financeiro-devedores',
                'icon' => 'trending',
                'priority' => 20,
                'permissions' => ['financeiro.cobrancas_ver'],
                'legacy_caps' => ['sige_director', 'sige_financeiro', 'sige_secretario'],
                'color' => 'var(--color-danger-500)',
                'soft' => 'var(--color-danger-50)',
            ],
            [
                'area' => 'tesouraria',
                'label' => 'Resumo financeiro',
                'hint' => 'Receitas, dividas e indicadores do mes',
                'view' => 'financeiro-dashboard',
                'icon' => 'chart',
                'priority' => 30,
                'permissions' => ['financeiro.dashboard_ver', 'financeiro.ver'],
                'legacy_caps' => ['sige_director', 'sige_financeiro', 'sige_secretario'],
                'color' => 'var(--color-success-500)',
                'soft' => 'var(--color-success-50)',
            ],
            [
                'area' => 'secretaria',
                'label' => 'Alunos',
                'hint' => 'Consultar cadastro, matricula e ficha 360',
                'view' => 'alunos_lista',
                'icon' => 'users',
                'priority' => 40,
                'permissions' => ['alunos.ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente', 'sige_recepcao'],
                'color' => 'var(--color-brand-500)',
                'soft' => 'var(--color-brand-50)',
            ],
            [
                'area' => 'secretaria',
                'label' => 'Turmas',
                'hint' => 'Organizar salas, classes e ocupacao',
                'view' => 'turmas',
                'icon' => 'school',
                'priority' => 50,
                'permissions' => ['academico.turmas_ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente', 'sige_pedagogico'],
                'color' => 'var(--color-success-500)',
                'soft' => 'var(--color-success-50)',
            ],
            [
                'area' => 'academico',
                'label' => 'Minhas turmas',
                'hint' => 'Abrir turmas e tarefas pedagogicas',
                'view' => 'minhas_turmas',
                'icon' => 'school',
                'priority' => 60,
                'permissions' => ['academico.turmas_ver'],
                'legacy_caps' => ['sige_professor', 'sige_pedagogico', 'sige_director'],
                'color' => 'var(--color-success-500)',
                'soft' => 'var(--color-success-50)',
            ],
            [
                'area' => 'academico',
                'label' => 'Lancar notas',
                'hint' => 'Registar notas pendentes por turma',
                'view' => 'notas',
                'icon' => 'file',
                'priority' => 70,
                'permissions' => ['academico.lancar_notas'],
                'legacy_caps' => ['sige_professor', 'sige_pedagogico', 'sige_director'],
                'color' => 'var(--color-warning-500)',
                'soft' => 'var(--color-warning-50)',
            ],
            [
                'area' => 'academico',
                'label' => 'Aprovar notas',
                'hint' => 'Validar pendencias antes de pautas e boletins',
                'view' => 'aprovar_notas',
                'icon' => 'shield',
                'priority' => 80,
                'permissions' => ['academico.aprovar_notas'],
                'legacy_caps' => ['sige_director', 'sige_pedagogico'],
                'color' => 'var(--color-brand-500)',
                'soft' => 'var(--color-brand-50)',
            ],
            [
                'area' => 'portaria',
                'label' => 'Portaria',
                'hint' => 'Validar entrada e ver motivo de bloqueio',
                'view' => 'portaria',
                'icon' => 'shield',
                'priority' => 5,
                'permissions' => ['portaria.ver', 'portaria.validar_acesso'],
                'legacy_caps' => ['sige_guarda', 'sige_director'],
                'color' => 'var(--color-danger-500)',
                'soft' => 'var(--color-danger-50)',
            ],
            [
                'area' => 'comunicacao',
                'label' => 'Comunicacao',
                'hint' => 'Enviar avisos, circulares e mensagens',
                'view' => 'whatsapp_central',
                'icon' => 'message',
                'priority' => 90,
                'permissions' => ['comunicacoes.ver', 'whatsapp.ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral'],
                'color' => 'var(--color-info-500)',
                'soft' => 'var(--color-info-50)',
            ],
            [
                'area' => 'gestao',
                'label' => 'Saude do sistema',
                'hint' => 'Versao, seguranca, gates e estado tecnico',
                'view' => 'sige_core_status',
                'icon' => 'settings',
                'priority' => 100,
                'permissions' => ['sistema.estado_ver'],
                'legacy_caps' => ['administrator', 'sige_admin_ti'],
                'color' => 'var(--color-info-500)',
                'soft' => 'var(--color-info-50)',
            ],
            [
                'area' => 'gestao',
                'label' => 'Configuracoes',
                'hint' => 'Parametros institucionais e administracao',
                'view' => 'config_center',
                'icon' => 'settings',
                'priority' => 110,
                'permissions' => ['sistema.estado_ver', 'configuracoes.ver'],
                'legacy_caps' => ['administrator', 'sige_admin_ti'],
                'color' => 'var(--color-brand-500)',
                'soft' => 'var(--color-brand-50)',
            ],
        ];
    }
}

if (!function_exists('sige_institutional_actions_v121600')) {
    /**
     * @return array<int,array<string,mixed>>
     */
    function sige_institutional_actions_v121600(int $limit = 8): array {
        $actions = [];
        foreach (sige_institutional_action_catalog_v121600() as $item) {
            $permissions = isset($item['permissions']) && is_array($item['permissions']) ? $item['permissions'] : [];
            $legacy_caps = isset($item['legacy_caps']) && is_array($item['legacy_caps']) ? $item['legacy_caps'] : [];
            if (!sige_ipu_can_any_v121600($permissions, $legacy_caps)) { continue; }
            $view = (string)($item['view'] ?? '');
            if ($view === '') { continue; }
            $item['href'] = sige_ipu_url_v121600($view);
            $item['enabled'] = true;
            unset($item['permissions'], $item['legacy_caps']);
            $actions[] = $item;
        }
        usort($actions, static function (array $a, array $b): int {
            $pa = isset($a['priority']) ? (int)$a['priority'] : 999;
            $pb = isset($b['priority']) ? (int)$b['priority'] : 999;
            if ($pa === $pb) { return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')); }
            return $pa <=> $pb;
        });
        if ($limit > 0) { return array_slice($actions, 0, $limit); }
        return $actions;
    }
}

if (!function_exists('sige_institutional_profile_context_v121600')) {
    /**
     * @return array<string,mixed>
     */
    function sige_institutional_profile_context_v121600(int $limit = 8): array {
        $actions = sige_institutional_actions_v121600($limit);
        $area_counts = [];
        foreach ($actions as $action) {
            $area = (string)($action['area'] ?? 'gestao');
            if ($area === '') { $area = 'gestao'; }
            $area_counts[$area] = ($area_counts[$area] ?? 0) + 1;
        }
        arsort($area_counts);
        $dominant = $area_counts ? (string)array_key_first($area_counts) : 'gestao';
        $profiles = [
            'portaria' => ['label' => 'Portaria', 'focus' => 'validacao rapida de entrada, bloqueios e historico recente'],
            'tesouraria' => ['label' => 'Tesouraria', 'focus' => 'recebimentos, dividas, recibos e caixa'],
            'secretaria' => ['label' => 'Secretaria', 'focus' => 'cadastro, matriculas, turmas e dados dos alunos'],
            'academico' => ['label' => 'Academico', 'focus' => 'turmas, notas, pautas e pendencias pedagogicas'],
            'comunicacao' => ['label' => 'Comunicacao', 'focus' => 'avisos, circulares, cobrancas e historico de envios'],
            'gestao' => ['label' => 'Gestao', 'focus' => 'indicadores, riscos, configuracoes e saude institucional'],
        ];
        $profile = $profiles[$dominant] ?? $profiles['gestao'];
        if (count($area_counts) >= 3 && (($area_counts['tesouraria'] ?? 0) > 0) && (($area_counts['secretaria'] ?? 0) > 0)) {
            $profile = $profiles['gestao'];
            $dominant = 'gestao';
        }
        return [
            'slug' => $dominant,
            'label' => $profile['label'],
            'focus' => $profile['focus'],
            'actions' => $actions,
            'areas' => $area_counts,
        ];
    }
}

if (!function_exists('sige_institutional_signal_int_v121600')) {
    function sige_institutional_signal_int_v121600(array $signals, string $key): int {
        if ($key === '' || !array_key_exists($key, $signals)) { return 0; }
        $value = $signals[$key];
        if (is_numeric($value)) { return max(0, (int)$value); }
        return 0;
    }
}

if (!function_exists('sige_institutional_checklist_catalog_v121600')) {
    /**
     * Catalogo de checklists operacionais por rotina real.
     * Nao consulta base de dados e nao grava dados; recebe apenas sinais ja
     * calculados pelo dashboard. A permissao continua filtrada pela guarda
     * central via sige_ipu_can_any_v121600().
     *
     * @return array<int,array<string,mixed>>
     */
    function sige_institutional_checklist_catalog_v121600(): array {
        return [
            [
                'area' => 'tesouraria',
                'label' => 'Cobranças vencidas',
                'active_label' => 'Priorizar encarregados com dívida vencida',
                'done_label' => 'Sem devedores sinalizados no painel',
                'view' => 'financeiro-devedores',
                'icon' => 'wallet',
                'priority' => 10,
                'metric_key' => 'alunos_com_divida',
                'metric_suffix' => ' aluno(s)',
                'permissions' => ['financeiro.cobrancas_ver'],
                'legacy_caps' => ['sige_director', 'sige_financeiro', 'sige_secretario'],
            ],
            [
                'area' => 'tesouraria',
                'label' => 'Pagamentos do dia',
                'active_label' => 'Confirmar recibos e caixa do dia',
                'done_label' => 'Sem pagamentos registados hoje',
                'view' => 'financeiro-pagamentos',
                'icon' => 'money',
                'priority' => 20,
                'metric_key' => 'pagamentos_hoje',
                'metric_suffix' => ' movimento(s)',
                'permissions' => ['financeiro.pagar'],
                'legacy_caps' => ['sige_director', 'sige_financeiro', 'sige_secretario'],
            ],
            [
                'area' => 'secretaria',
                'label' => 'Documentos em falta',
                'active_label' => 'Completar BI/NUIT nas fichas dos alunos',
                'done_label' => 'Documentação principal em ordem',
                'view' => 'alunos_lista',
                'icon' => 'file',
                'priority' => 30,
                'metric_key' => 'alunos_sem_doc',
                'metric_suffix' => ' aluno(s)',
                'permissions' => ['alunos.ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente', 'sige_recepcao'],
            ],
            [
                'area' => 'secretaria',
                'label' => 'Encarregados por completar',
                'active_label' => 'Completar ligação aluno-encarregado',
                'done_label' => 'Encarregados principais registados',
                'view' => 'alunos_lista',
                'icon' => 'users',
                'priority' => 40,
                'metric_key' => 'alunos_sem_encarregado',
                'metric_suffix' => ' aluno(s)',
                'permissions' => ['alunos.ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente', 'sige_recepcao'],
            ],
            [
                'area' => 'academico',
                'label' => 'Alunos sem turma',
                'active_label' => 'Alocar alunos activos às turmas correctas',
                'done_label' => 'Alunos activos com turma atribuída',
                'view' => 'turmas',
                'icon' => 'school',
                'priority' => 50,
                'metric_key' => 'alunos_sem_turma',
                'metric_suffix' => ' aluno(s)',
                'permissions' => ['academico.turmas_ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente', 'sige_pedagogico'],
            ],
            [
                'area' => 'academico',
                'label' => 'Notas e pautas',
                'active_label' => 'Abrir lançamentos e validações académicas',
                'done_label' => 'Acompanhar tarefas pedagógicas',
                'view' => 'notas',
                'icon' => 'edit',
                'priority' => 60,
                'metric_key' => '',
                'permissions' => ['academico.lancar_notas'],
                'legacy_caps' => ['sige_professor', 'sige_pedagogico', 'sige_director'],
            ],
            [
                'area' => 'academico',
                'label' => 'Aprovação académica',
                'active_label' => 'Rever notas submetidas antes de pautas',
                'done_label' => 'Acompanhar aprovações pendentes',
                'view' => 'aprovar_notas',
                'icon' => 'check',
                'priority' => 70,
                'metric_key' => '',
                'permissions' => ['academico.aprovar_notas'],
                'legacy_caps' => ['sige_director', 'sige_pedagogico'],
            ],
            [
                'area' => 'portaria',
                'label' => 'Entrada escolar',
                'active_label' => 'Validar aluno e motivo de bloqueio',
                'done_label' => 'Abrir validação da portaria',
                'view' => 'portaria',
                'icon' => 'shield',
                'priority' => 5,
                'metric_key' => '',
                'permissions' => ['portaria.ver', 'portaria.validar_acesso'],
                'legacy_caps' => ['sige_guarda', 'sige_director'],
            ],
            [
                'area' => 'comunicacao',
                'label' => 'Comunicações institucionais',
                'active_label' => 'Rever avisos, circulares e cobranças',
                'done_label' => 'Abrir central de comunicação',
                'view' => 'whatsapp_central',
                'icon' => 'message',
                'priority' => 80,
                'metric_key' => '',
                'permissions' => ['comunicacoes.ver', 'whatsapp.ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral'],
            ],
            [
                'area' => 'gestao',
                'label' => 'Saúde institucional',
                'active_label' => 'Verificar versão, gates e segurança',
                'done_label' => 'Abrir saúde do sistema',
                'view' => 'sige_core_status',
                'icon' => 'settings',
                'priority' => 90,
                'metric_key' => '',
                'permissions' => ['sistema.estado_ver'],
                'legacy_caps' => ['administrator', 'sige_admin_ti'],
            ],
        ];
    }
}

if (!function_exists('sige_institutional_operational_checklist_v121600')) {
    /**
     * @param array<string,mixed> $signals
     * @return array<int,array<string,mixed>>
     */
    function sige_institutional_operational_checklist_v121600(array $signals = [], int $limit = 5): array {
        $items = [];
        foreach (sige_institutional_checklist_catalog_v121600() as $item) {
            $permissions = isset($item['permissions']) && is_array($item['permissions']) ? $item['permissions'] : [];
            $legacy_caps = isset($item['legacy_caps']) && is_array($item['legacy_caps']) ? $item['legacy_caps'] : [];
            if (!sige_ipu_can_any_v121600($permissions, $legacy_caps)) { continue; }

            $view = (string)($item['view'] ?? '');
            if ($view === '') { continue; }
            $metric_key = (string)($item['metric_key'] ?? '');
            $metric_value = $metric_key !== '' ? sige_institutional_signal_int_v121600($signals, $metric_key) : 0;
            $has_metric = $metric_key !== '';
            $status = $has_metric ? ($metric_value > 0 ? 'attention' : 'ok') : 'next';
            $state_label = $status === 'attention'
                ? (string)($item['active_label'] ?? 'Rever pendencia')
                : (string)($item['done_label'] ?? 'Acompanhar tarefa');
            $badge = $has_metric
                ? ((string)$metric_value . (string)($item['metric_suffix'] ?? ''))
                : 'Abrir';

            $items[] = [
                'area' => (string)($item['area'] ?? 'gestao'),
                'label' => (string)($item['label'] ?? 'Tarefa operacional'),
                'state_label' => $state_label,
                'status' => $status,
                'href' => sige_ipu_url_v121600($view),
                'view' => $view,
                'icon' => (string)($item['icon'] ?? 'check'),
                'priority' => isset($item['priority']) ? (int)$item['priority'] : 999,
                'metric_key' => $metric_key,
                'metric_value' => $metric_value,
                'badge' => $badge,
            ];
        }

        $rank = ['attention' => 0, 'next' => 1, 'ok' => 2];
        usort($items, static function (array $a, array $b) use ($rank): int {
            $ra = $rank[(string)($a['status'] ?? 'ok')] ?? 9;
            $rb = $rank[(string)($b['status'] ?? 'ok')] ?? 9;
            if ($ra !== $rb) { return $ra <=> $rb; }
            $pa = isset($a['priority']) ? (int)$a['priority'] : 999;
            $pb = isset($b['priority']) ? (int)$b['priority'] : 999;
            if ($pa === $pb) { return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')); }
            return $pa <=> $pb;
        });

        if ($limit > 0) { return array_slice($items, 0, $limit); }
        return $items;
    }
}

if (!function_exists('sige_institutional_area_meta_v121600')) {
    /**
     * Metadados de areas operacionais usados por dashboard e shell.
     * Read-only: nao consulta banco, nao escreve dados, nao altera permissoes.
     *
     * @return array<string,array<string,string>>
     */
    function sige_institutional_area_meta_v121600(): array {
        return [
            'portaria' => [
                'label' => 'Portaria',
                'focus' => 'validacao rapida de entrada e bloqueios',
            ],
            'tesouraria' => [
                'label' => 'Tesouraria',
                'focus' => 'pagamentos, cobrancas, recibos e caixa',
            ],
            'secretaria' => [
                'label' => 'Secretaria',
                'focus' => 'alunos, turmas, matriculas e cadastro',
            ],
            'academico' => [
                'label' => 'Academico',
                'focus' => 'turmas, notas, pautas e aprovacao pedagogica',
            ],
            'comunicacao' => [
                'label' => 'Comunicacao',
                'focus' => 'avisos, circulares, cobrancas e historico',
            ],
            'gestao' => [
                'label' => 'Gestao',
                'focus' => 'indicadores, riscos, configuracoes e saude institucional',
            ],
        ];
    }
}

if (!function_exists('sige_institutional_navigation_groups_v121600')) {
    /**
     * Agrupa accoes permitidas por area institucional para orientar a navegacao.
     * A fonte de verdade continua a ser a guarda central de permissoes usada em
     * sige_institutional_actions_v121600().
     *
     * @return array<int,array<string,mixed>>
     */
    function sige_institutional_navigation_groups_v121600(int $limit_per_group = 3, int $max_groups = 4): array {
        $actions = sige_institutional_actions_v121600(0);
        if (!$actions) { return []; }

        $meta = sige_institutional_area_meta_v121600();
        $profile = sige_institutional_profile_context_v121600(0);
        $primary_area = (string)($profile['slug'] ?? '');
        $groups = [];

        foreach ($actions as $action) {
            $area = (string)($action['area'] ?? 'gestao');
            if ($area === '') { $area = 'gestao'; }
            if (!isset($groups[$area])) {
                $groups[$area] = [
                    'slug' => $area,
                    'label' => (string)($meta[$area]['label'] ?? ucfirst($area)),
                    'focus' => (string)($meta[$area]['focus'] ?? 'rotina operacional'),
                    'priority' => isset($action['priority']) ? (int)$action['priority'] : 999,
                    'is_primary' => ($area === $primary_area),
                    'actions' => [],
                ];
            }
            $groups[$area]['priority'] = min((int)$groups[$area]['priority'], isset($action['priority']) ? (int)$action['priority'] : 999);
            if ($limit_per_group <= 0 || count($groups[$area]['actions']) < $limit_per_group) {
                $groups[$area]['actions'][] = $action;
            }
        }

        $groups = array_values($groups);
        usort($groups, static function (array $a, array $b): int {
            $ap = !empty($a['is_primary']) ? 0 : 1;
            $bp = !empty($b['is_primary']) ? 0 : 1;
            if ($ap !== $bp) { return $ap <=> $bp; }
            $pa = isset($a['priority']) ? (int)$a['priority'] : 999;
            $pb = isset($b['priority']) ? (int)$b['priority'] : 999;
            if ($pa === $pb) { return strcmp((string)($a['label'] ?? ''), (string)($b['label'] ?? '')); }
            return $pa <=> $pb;
        });

        if ($max_groups > 0) { return array_slice($groups, 0, $max_groups); }
        return $groups;
    }
}


if (!function_exists('sige_institutional_flow_guidance_catalog_v121600')) {
    /**
     * Microcopy institucional para orientar fluxos sensiveis sem alterar regras.
     * Apenas devolve texto, views existentes e permissoes a validar pela guarda.
     *
     * @return array<int,array<string,mixed>>
     */
    function sige_institutional_flow_guidance_catalog_v121600(): array {
        return [
            [
                'area' => 'tesouraria',
                'title' => 'Registar pagamento',
                'lead' => 'Recebimento com conferência antes do recibo.',
                'view' => 'financeiro-pagamentos',
                'action_label' => 'Abrir pagamento',
                'icon' => 'wallet',
                'priority' => 10,
                'metric_key' => 'alunos_com_divida',
                'metric_suffix' => ' dívida(s)',
                'steps' => ['Pesquisar aluno', 'Confirmar dívida e item', 'Gravar e emitir recibo'],
                'guardrail' => 'Confirme saldo antes/depois e evite duplicados.',
                'permissions' => ['financeiro.pagar'],
                'legacy_caps' => ['sige_director', 'sige_financeiro', 'sige_secretario'],
            ],
            [
                'area' => 'tesouraria',
                'title' => 'Lançar mensalidades',
                'lead' => 'Geração em lote com pré-visualização obrigatória.',
                'view' => 'financeiro-gerador',
                'action_label' => 'Abrir lançador',
                'icon' => 'rocket',
                'priority' => 20,
                'metric_key' => '',
                'steps' => ['Escolher mês', 'Pré-visualizar alunos', 'Confirmar lote e auditoria'],
                'guardrail' => 'Não execute lote sem rever alunos afectados e excepções.',
                'permissions' => ['financeiro.lancar_mensalidades'],
                'legacy_caps' => ['sige_director', 'sige_financeiro'],
            ],
            [
                'area' => 'tesouraria',
                'title' => 'Fecho de caixa',
                'lead' => 'Conferência diária antes do relatório financeiro.',
                'view' => 'financeiro-extratos',
                'action_label' => 'Abrir caixa',
                'icon' => 'file',
                'priority' => 30,
                'metric_key' => 'pagamentos_hoje',
                'metric_suffix' => ' movimento(s)',
                'steps' => ['Conferir pagamentos', 'Separar métodos', 'Registar observações'],
                'guardrail' => 'Diferenças de caixa devem ficar explicadas antes do fecho.',
                'permissions' => ['financeiro.extractos_ver', 'financeiro.caixa_fechar'],
                'legacy_caps' => ['sige_director', 'sige_financeiro'],
            ],
            [
                'area' => 'secretaria',
                'title' => 'Novo aluno',
                'lead' => 'Cadastro completo antes da ficha institucional.',
                'view' => 'alunos_lista',
                'action_label' => 'Abrir alunos',
                'icon' => 'users',
                'priority' => 40,
                'metric_key' => 'alunos_sem_doc',
                'metric_suffix' => ' pendência(s)',
                'steps' => ['Dados básicos', 'Encarregado e turma', 'Revisão antes de guardar'],
                'guardrail' => 'Serviços, transporte e documentos devem ser revistos juntos.',
                'permissions' => ['alunos.criar', 'matriculas.criar'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente'],
            ],
            [
                'area' => 'academico',
                'title' => 'Lançar notas',
                'lead' => 'Grelha académica com validação antes da submissão.',
                'view' => 'notas',
                'action_label' => 'Abrir notas',
                'icon' => 'edit',
                'priority' => 50,
                'metric_key' => '',
                'steps' => ['Escolher turma', 'Preencher grelha', 'Submeter para revisão'],
                'guardrail' => 'Esta orientação não altera cálculo, fórmula ou pauta.',
                'permissions' => ['academico.lancar_notas'],
                'legacy_caps' => ['sige_professor', 'sige_pedagogico', 'sige_director'],
            ],
            [
                'area' => 'academico',
                'title' => 'Aprovar pauta',
                'lead' => 'Revisão pedagógica antes de boletins e actas.',
                'view' => 'aprovar_notas',
                'action_label' => 'Abrir aprovação',
                'icon' => 'check',
                'priority' => 60,
                'metric_key' => '',
                'steps' => ['Ver pendências', 'Confirmar turma', 'Aprovar com auditoria'],
                'guardrail' => 'A aprovação deve manter rastreabilidade de quem validou.',
                'permissions' => ['academico.aprovar_notas'],
                'legacy_caps' => ['sige_director', 'sige_pedagogico'],
            ],
            [
                'area' => 'portaria',
                'title' => 'Validar entrada',
                'lead' => 'Decisão rápida, sem menus administrativos.',
                'view' => 'portaria',
                'action_label' => 'Abrir portaria',
                'icon' => 'shield',
                'priority' => 5,
                'metric_key' => '',
                'steps' => ['Ler código', 'Ver estado', 'Preparar nova leitura'],
                'guardrail' => 'Resultado deve ser autorizado ou bloqueado com motivo claro.',
                'permissions' => ['portaria.ver', 'portaria.validar_acesso'],
                'legacy_caps' => ['sige_guarda', 'sige_director'],
            ],
            [
                'area' => 'comunicacao',
                'title' => 'Enviar comunicação',
                'lead' => 'Avisos e cobranças com destinatários controlados.',
                'view' => 'whatsapp_central',
                'action_label' => 'Abrir comunicação',
                'icon' => 'message',
                'priority' => 70,
                'metric_key' => '',
                'steps' => ['Escolher público', 'Rever mensagem', 'Consultar histórico'],
                'guardrail' => 'Evite envios sem destinatário e sem contexto operacional.',
                'permissions' => ['comunicacoes.ver', 'whatsapp.ver'],
                'legacy_caps' => ['sige_director', 'sige_secretario', 'sige_secretaria_geral'],
            ],
        ];
    }
}

if (!function_exists('sige_institutional_flow_guidance_v121600')) {
    /**
     * @param array<string,mixed> $signals
     * @return array<int,array<string,mixed>>
     */
    function sige_institutional_flow_guidance_v121600(array $signals = [], int $limit = 3): array {
        $flows = [];
        foreach (sige_institutional_flow_guidance_catalog_v121600() as $flow) {
            $permissions = isset($flow['permissions']) && is_array($flow['permissions']) ? $flow['permissions'] : [];
            $legacy_caps = isset($flow['legacy_caps']) && is_array($flow['legacy_caps']) ? $flow['legacy_caps'] : [];
            if (!sige_ipu_can_any_v121600($permissions, $legacy_caps)) { continue; }

            $view = (string)($flow['view'] ?? '');
            if ($view === '') { continue; }
            $metric_key = (string)($flow['metric_key'] ?? '');
            $metric_value = $metric_key !== '' ? sige_institutional_signal_int_v121600($signals, $metric_key) : 0;
            $status = $metric_value > 0 ? 'attention' : 'guided';
            $badge = $metric_key !== '' && $metric_value > 0
                ? ((string)$metric_value . (string)($flow['metric_suffix'] ?? ''))
                : 'Guiado';
            $steps = isset($flow['steps']) && is_array($flow['steps']) ? array_slice(array_values($flow['steps']), 0, 3) : [];

            $flows[] = [
                'area' => (string)($flow['area'] ?? 'gestao'),
                'title' => (string)($flow['title'] ?? 'Fluxo guiado'),
                'lead' => (string)($flow['lead'] ?? 'Orientação operacional'),
                'href' => sige_ipu_url_v121600($view),
                'view' => $view,
                'action_label' => (string)($flow['action_label'] ?? 'Abrir'),
                'icon' => (string)($flow['icon'] ?? 'check'),
                'priority' => isset($flow['priority']) ? (int)$flow['priority'] : 999,
                'metric_key' => $metric_key,
                'metric_value' => $metric_value,
                'status' => $status,
                'badge' => $badge,
                'steps' => $steps,
                'guardrail' => (string)($flow['guardrail'] ?? ''),
            ];
        }

        usort($flows, static function (array $a, array $b): int {
            $ra = (string)($a['status'] ?? 'guided') === 'attention' ? 0 : 1;
            $rb = (string)($b['status'] ?? 'guided') === 'attention' ? 0 : 1;
            if ($ra !== $rb) { return $ra <=> $rb; }
            $pa = isset($a['priority']) ? (int)$a['priority'] : 999;
            $pb = isset($b['priority']) ? (int)$b['priority'] : 999;
            if ($pa === $pb) { return strcmp((string)($a['title'] ?? ''), (string)($b['title'] ?? '')); }
            return $pa <=> $pb;
        });

        if ($limit > 0) { return array_slice($flows, 0, $limit); }
        return $flows;
    }
}

