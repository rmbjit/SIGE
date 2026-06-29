<?php
if (!defined('ABSPATH')) { exit; }

/**
 * SIGE SoftGenial v12.19.1
 * Dashboard Executivo & Inteligência Operacional por Perfil
 *
 * Camada read-only para orientar o painel principal por perfil real. Não grava
 * dados, não altera permissões, não executa migrações e não substitui o
 * dashboard existente. Usa o mapa institucional já filtrado pelas guardas.
 */

if (!function_exists('sige_profile_dashboard_current_view_v121900')) {
    function sige_profile_dashboard_current_view_v121900(): string {
        if (function_exists('is_admin') && !is_admin()) { return ''; }
        $page = function_exists('sanitize_key') ? sanitize_key((string)($_GET['page'] ?? '')) : preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($_GET['page'] ?? '')));
        if ($page !== 'sige-app') { return ''; }
        $raw = (string)($_GET['view'] ?? 'dashboard');
        $view = function_exists('sanitize_key') ? sanitize_key($raw) : preg_replace('/[^a-z0-9_\-]/', '', strtolower($raw));
        return $view !== '' ? $view : 'dashboard';
    }
}

if (!function_exists('sige_profile_dashboard_url_v121900')) {
    function sige_profile_dashboard_url_v121900(string $view): string {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '', $view);
        if ($safe === '') { $safe = 'dashboard'; }
        if (function_exists('admin_url') && function_exists('add_query_arg')) {
            return (string) add_query_arg(['page' => 'sige-app', 'view' => $safe], admin_url('admin.php'));
        }
        return 'admin.php?page=sige-app&view=' . rawurlencode($safe);
    }
}


if (!function_exists('sige_profile_dashboard_human_text_v121900')) {
    function sige_profile_dashboard_human_text_v121900(string $text): string {
        $text = trim($text);
        if ($text === '') { return $text; }
        $map = [
            'Gestao' => 'Gestão',
            'gestao' => 'gestão',
            'Academico' => 'Académico',
            'academico' => 'académico',
            'Comunicacao' => 'Comunicação',
            'comunicacao' => 'comunicação',
            'Area' => 'Área',
            'area' => 'área',
            'Operacao' => 'Operação',
            'operacao' => 'operação',
            'permissoes' => 'permissões',
            'accao' => 'acção',
            'accoes' => 'acções',
            'sensivel' => 'sensível',
            'visao' => 'visão',
            'saude' => 'saúde',
            'dividas' => 'dívidas',
            'cobrancas' => 'cobranças',
            'validacao' => 'validação',
            'codigo' => 'código',
            'publico' => 'público',
            'historico' => 'histórico',
            'pendencias' => 'pendências',
            'prioritario' => 'prioritário',
            'prioritarios' => 'prioritários',
            'visivel' => 'visível',
            'visiveis' => 'visíveis',
        ];
        return strtr($text, $map);
    }
}

if (!function_exists('sige_profile_dashboard_strategy_catalog_v121900')) {
    /**
     * @return array<string,array<string,string>>
     */
    function sige_profile_dashboard_strategy_catalog_v121900(): array {
        return [
            'gestao' => [
                'label' => 'Direcção executiva',
                'tone' => 'gestao',
                'headline' => 'Veja primeiro o que exige decisão hoje.',
                'focus' => 'visão institucional, riscos, receitas, alunos e saúde operacional',
                'risk' => 'Tomar decisões sem cruzar a situação financeira, académica e operacional.',
                'rule' => 'Comece pelo alerta de maior impacto e só depois entre no módulo correspondente.',
                'next' => 'Reveja os indicadores principais e abra a área com maior risco.',
            ],
            'tesouraria' => [
                'label' => 'Tesouraria',
                'tone' => 'financeiro',
                'headline' => 'Confirme recebimentos com segurança antes de fechar o dia.',
                'focus' => 'pagamentos, dívidas, recibos, cobranças e caixa',
                'risk' => 'Registar pagamento sem conferir valor, mês, serviço e recibo.',
                'rule' => 'Confirme aluno, valor, mês, serviço e método antes de guardar.',
                'next' => 'Comece pelos pagamentos do dia e pelos devedores prioritários.',
            ],
            'secretaria' => [
                'label' => 'Secretaria',
                'tone' => 'secretaria',
                'headline' => 'Mantenha cada aluno bem identificado desde o primeiro registo.',
                'focus' => 'alunos, matrículas, turmas, documentos e encarregados',
                'risk' => 'Criar aluno duplicado ou deixar informação essencial em falta.',
                'rule' => 'Pesquise primeiro. Registe ou edite apenas depois de confirmar que o aluno não existe.',
                'next' => 'Comece por fichas incompletas, alunos sem turma e documentos pendentes.',
            ],
            'academico' => [
                'label' => 'Área pedagógica',
                'tone' => 'academico',
                'headline' => 'Confirme turma, disciplina e período antes de trabalhar notas.',
                'focus' => 'turmas, notas, pautas, aprovação e boletins',
                'risk' => 'Aprovar ou exportar uma pauta com pendências por rever.',
                'rule' => 'Confira turma, disciplina e período antes de submeter, aprovar ou emitir.',
                'next' => 'Abra as tarefas pedagógicas e resolva pendências antes da emissão.',
            ],
            'portaria' => [
                'label' => 'Portaria escolar',
                'tone' => 'portaria',
                'headline' => 'Valide cada entrada com rapidez e motivo claro.',
                'focus' => 'entrada escolar, bloqueios e validação do aluno',
                'risk' => 'Autorizar entrada sem ler o motivo de bloqueio.',
                'rule' => 'Leia o código, confirme o estado e avance apenas quando o sinal estiver claro.',
                'next' => 'Mantenha a Portaria aberta e siga o sinal autorizado ou bloqueado.',
            ],
            'comunicacao' => [
                'label' => 'Comunicação',
                'tone' => 'comunicacao',
                'headline' => 'Envie a mensagem certa para as pessoas certas.',
                'focus' => 'avisos, circulares, cobranças e histórico de envios',
                'risk' => 'Enviar aviso duplicado ou para o público errado.',
                'rule' => 'Confirme destinatários, mensagem e canal antes de enviar.',
                'next' => 'Reveja comunicações pendentes e histórico recente.',
            ],
        ];
    }
}

if (!function_exists('sige_profile_dashboard_sanitized_actions_v121900')) {
    /**
     * @param array<int,array<string,mixed>> $actions
     * @return array<int,array<string,string>>
     */
    function sige_profile_dashboard_sanitized_actions_v121900(array $actions, int $limit = 4): array {
        $out = [];
        foreach ($actions as $action) {
            if (!is_array($action)) { continue; }
            $href = (string)($action['href'] ?? '');
            $label = (string)($action['label'] ?? 'Abrir');
            if ($href === '' || $label === '') { continue; }
            $out[] = [
                'label' => sige_profile_dashboard_human_text_v121900($label),
                'hint' => sige_profile_dashboard_human_text_v121900((string)($action['hint'] ?? 'Abrir tarefa')),
                'href' => $href,
                'area' => sige_profile_dashboard_human_text_v121900((string)($action['area'] ?? 'gestao')),
                'icon' => (string)($action['icon'] ?? 'activity'),
            ];
            if ($limit > 0 && count($out) >= $limit) { break; }
        }
        return $out;
    }
}

if (!function_exists('sige_profile_dashboard_context_v121900')) {
    /**
     * @return array<string,mixed>|null
     */
    function sige_profile_dashboard_context_v121900(string $view = ''): ?array {
        $view = $view !== '' ? (function_exists('sanitize_key') ? sanitize_key($view) : preg_replace('/[^a-z0-9_\-]/', '', strtolower($view))) : sige_profile_dashboard_current_view_v121900();
        if ($view !== 'dashboard') { return null; }

        $profile = function_exists('sige_institutional_profile_context_v121600')
            ? sige_institutional_profile_context_v121600(8)
            : ['slug' => 'gestao', 'label' => 'Gestão', 'focus' => 'indicadores e riscos', 'actions' => [], 'areas' => []];
        $slug = (string)($profile['slug'] ?? 'gestao');
        $catalog = sige_profile_dashboard_strategy_catalog_v121900();
        if (!isset($catalog[$slug])) { $slug = 'gestao'; }
        $strategy = $catalog[$slug];

        $actions = isset($profile['actions']) && is_array($profile['actions']) ? sige_profile_dashboard_sanitized_actions_v121900($profile['actions'], 4) : [];
        if (!$actions && function_exists('sige_institutional_actions_v121600')) {
            $actions = sige_profile_dashboard_sanitized_actions_v121900(sige_institutional_actions_v121600(4), 4);
        }
        $groups = function_exists('sige_institutional_navigation_groups_v121600') ? sige_institutional_navigation_groups_v121600(2, 3) : [];
        $safeGroups = [];
        foreach ((array)$groups as $group) {
            if (!is_array($group)) { continue; }
            $safeGroups[] = [
                'label' => sige_profile_dashboard_human_text_v121900((string)($group['label'] ?? 'Área')),
                'focus' => sige_profile_dashboard_human_text_v121900((string)($group['focus'] ?? 'Rotina operacional')),
                'primary' => !empty($group['is_primary']),
            ];
            if (count($safeGroups) >= 3) { break; }
        }

        $areas = isset($profile['areas']) && is_array($profile['areas']) ? $profile['areas'] : [];
        $areaCount = count(array_filter($areas));
        $firstAction = $actions[0] ?? null;

        return [
            'enabled' => true,
            'version' => '12.19.1',
            'view' => 'dashboard',
            'profile' => [
                'slug' => $slug,
                'label' => sige_profile_dashboard_human_text_v121900((string)($strategy['label'] ?? ($profile['label'] ?? 'Gestão'))),
                'focus' => sige_profile_dashboard_human_text_v121900((string)($strategy['focus'] ?? ($profile['focus'] ?? 'indicadores e riscos'))),
                'tone' => (string)($strategy['tone'] ?? 'gestao'),
            ],
            'headline' => (string)($strategy['headline'] ?? 'Veja primeiro o que precisa de atenção.'),
            'summary' => 'Este painel organiza as informações de acordo com o seu perfil e mostra apenas atalhos permitidos. Não altera dados nem regras do sistema.',
            'cards' => [
                [
                    'label' => 'Primeiro passo recomendado',
                    'value' => $firstAction ? (string)$firstAction['label'] : (string)($strategy['label'] ?? 'Operação'),
                    'hint' => $firstAction ? (string)$firstAction['hint'] : (string)($strategy['next'] ?? 'Abrir tarefas prioritárias'),
                ],
                [
                    'label' => 'Atenção antes de agir',
                    'value' => (string)($strategy['risk'] ?? 'Erro operacional'),
                    'hint' => 'Confirme a informação antes de executar uma acção sensível.',
                ],
                [
                    'label' => 'Regra de segurança',
                    'value' => (string)($strategy['rule'] ?? 'Confirmar dados antes de guardar.'),
                    'hint' => (string)($strategy['next'] ?? 'Siga pelo fluxo principal.'),
                ],
            ],
            'actions' => $actions,
            'groups' => $safeGroups,
            'meta' => [
                'actionsLabel' => count($actions) . ' atalhos prioritários',
                'areasLabel' => max(1, $areaCount) . ' áreas operacionais visíveis',
            ],
            'dismissKey' => 'sige_profile_dashboard_v121901_' . $slug,
        ];
    }
}

add_action('admin_enqueue_scripts', function (): void {
    // v12.19.4 - Painel Principal simplificado: o bloco de coaching/estratégia
    // por perfil deixa de ser injectado, para reduzir distracção e duplicação
    // com os cartões do painel. Funções mantidas; apenas não se injecta a UI.
    return;
    if (!function_exists('is_admin') || !is_admin()) { return; }
    if ((function_exists('sanitize_key') ? sanitize_key((string)($_GET['page'] ?? '')) : preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)($_GET['page'] ?? '')))) !== 'sige-app') { return; }
    $context = sige_profile_dashboard_context_v121900();
    if (empty($context)) { return; }

    $css = 'assets/profile-dashboard-intelligence-v12-19-0.css';
    $js = 'assets/profile-dashboard-intelligence-v12-19-0.js';
    if (is_file(SIGE_PATH . $css)) {
        wp_enqueue_style('sige-profile-dashboard-intelligence-v12-19-0', SIGE_URL . $css, [], SIGE_VERSION);
    }
    if (is_file(SIGE_PATH . $js)) {
        wp_enqueue_script('sige-profile-dashboard-intelligence-v12-19-0', SIGE_URL . $js, [], SIGE_VERSION, true);
        wp_add_inline_script(
            'sige-profile-dashboard-intelligence-v12-19-0',
            'window.SIGEProfileDashboardV121900 = ' . wp_json_encode($context) . ';',
            'before'
        );
    }
}, 36);
