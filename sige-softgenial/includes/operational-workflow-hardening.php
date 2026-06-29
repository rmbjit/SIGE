<?php
if (!defined('ABSPATH')) { exit; }

/**
 * SIGE SoftGenial v12.18.0
 * Operational UX & Workflow Hardening
 *
 * Camada sem escrita: adiciona orientação contextual de fluxo nas views
 * operacionais críticas, sem alterar regras financeiras/académicas,
 * permissões, schema, queries sensíveis ou dados históricos.
 */

if (!function_exists('sige_operational_workflow_catalog_v121800')) {
    /**
     * Catálogo fixo de orientação por view. Apenas texto e passos de uso.
     *
     * @return array<string,array<string,mixed>>
     */
    function sige_operational_workflow_catalog_v121800(): array {
        return [
            'alunos_lista' => [
                'area' => 'secretaria',
                'tone' => 'secretaria',
                'title' => 'Fluxo seguro de Alunos',
                'lead' => 'Pesquise primeiro, confirme a ficha certa e só depois altere dados sensíveis.',
                'steps' => ['Pesquisar aluno ou turma', 'Abrir ficha/edição correcta', 'Rever encarregado, turma e documentos antes de guardar'],
                'guardrail' => 'Evite criar aluno duplicado quando a pesquisa já encontrar registo semelhante.',
                'action_label' => 'Continuar em Alunos',
            ],
            'financeiro-pagamentos' => [
                'area' => 'tesouraria',
                'tone' => 'financeiro',
                'title' => 'Fluxo seguro de Pagamento',
                'lead' => 'Recebimento deve ser conferido antes de gerar ou comunicar recibo.',
                'steps' => ['Pesquisar aluno', 'Confirmar dívida, serviço, mês e método', 'Guardar e validar recibo emitido'],
                'guardrail' => 'Não registe pagamento em duplicado sem comparar saldo antes/depois.',
                'action_label' => 'Continuar pagamentos',
            ],
            'financeiro-devedores' => [
                'area' => 'tesouraria',
                'tone' => 'financeiro',
                'title' => 'Fluxo seguro de Cobrança',
                'lead' => 'Priorize dívida real e comunicação clara com encarregados.',
                'steps' => ['Filtrar período e turma', 'Confirmar saldo individual', 'Comunicar apenas casos revistos'],
                'guardrail' => 'Evite cobrança sem conferir se houve pagamento recente ou ajuste pendente.',
                'action_label' => 'Continuar cobranças',
            ],
            'financeiro-gerador' => [
                'area' => 'tesouraria',
                'tone' => 'financeiro',
                'title' => 'Fluxo seguro de Lançamento',
                'lead' => 'Geração em lote exige pré-visualização e controlo de excepções.',
                'steps' => ['Escolher mês/serviço', 'Pré-visualizar alunos afectados', 'Confirmar lote apenas depois da revisão'],
                'guardrail' => 'Nunca execute lote sem rever turmas, isenções, transporte e duplicados.',
                'action_label' => 'Continuar lançamento',
            ],
            'financeiro-extratos' => [
                'area' => 'tesouraria',
                'tone' => 'financeiro',
                'title' => 'Fluxo seguro de Extractos',
                'lead' => 'Conferência financeira deve manter período, aluno e caixa alinhados.',
                'steps' => ['Seleccionar período correcto', 'Conferir movimentos e recibos', 'Exportar ou fechar apenas após revisão'],
                'guardrail' => 'Diferenças de caixa devem ser explicadas antes de considerar o dia fechado.',
                'action_label' => 'Continuar extractos',
            ],
            'notas' => [
                'area' => 'academico',
                'tone' => 'academico',
                'title' => 'Fluxo seguro de Notas',
                'lead' => 'Lançamento académico deve preservar turma, disciplina e trimestre correctos.',
                'steps' => ['Escolher turma/disciplina', 'Preencher grelha com calma', 'Submeter para revisão quando aplicável'],
                'guardrail' => 'Esta orientação não altera fórmulas nem regras de cálculo académico.',
                'action_label' => 'Continuar notas',
            ],
            'minhas_turmas' => [
                'area' => 'academico',
                'tone' => 'academico',
                'title' => 'Fluxo seguro do Professor',
                'lead' => 'Aceda apenas às turmas e tarefas pedagógicas necessárias para o dia.',
                'steps' => ['Escolher turma', 'Abrir actividade académica', 'Confirmar antes de submeter informação'],
                'guardrail' => 'Não navegue por áreas administrativas sem necessidade operacional.',
                'action_label' => 'Continuar nas turmas',
            ],
            'aprovar_notas' => [
                'area' => 'academico',
                'tone' => 'academico',
                'title' => 'Fluxo seguro de Aprovação',
                'lead' => 'Aprovação pedagógica deve deixar rastreio e evitar validação apressada.',
                'steps' => ['Ver pendências', 'Confirmar turma/disciplina', 'Aprovar apenas após revisão'],
                'guardrail' => 'Aprovar notas é acção sensível; confirme antes de avançar.',
                'action_label' => 'Continuar aprovação',
            ],
            'pautas' => [
                'area' => 'academico',
                'tone' => 'academico',
                'title' => 'Fluxo seguro de Pautas',
                'lead' => 'Pauta deve reflectir notas e parâmetros académicos já validados.',
                'steps' => ['Escolher turma/período', 'Rever pendências', 'Gerar ou exportar quando estiver consistente'],
                'guardrail' => 'Não use pauta como substituto de correcção de notas pendentes.',
                'action_label' => 'Continuar pautas',
            ],
            'portaria' => [
                'area' => 'portaria',
                'tone' => 'portaria',
                'title' => 'Fluxo seguro de Portaria',
                'lead' => 'Decisão deve ser rápida, visível e com motivo claro quando houver bloqueio.',
                'steps' => ['Ler código', 'Ver estado autorizado/bloqueado', 'Preparar nova leitura'],
                'guardrail' => 'Não ignore motivo de bloqueio; confirme o estado antes de autorizar entrada.',
                'action_label' => 'Continuar portaria',
            ],
            'whatsapp_central' => [
                'area' => 'comunicacao',
                'tone' => 'comunicacao',
                'title' => 'Fluxo seguro de Comunicação',
                'lead' => 'Mensagens institucionais devem ter público, contexto e histórico controlados.',
                'steps' => ['Escolher destinatários', 'Rever texto e motivo', 'Consultar histórico depois do envio'],
                'guardrail' => 'Evite enviar mensagem sem destinatário claro ou sem revisão do conteúdo.',
                'action_label' => 'Continuar comunicação',
            ],
            'comunicacoes_central' => [
                'area' => 'comunicacao',
                'tone' => 'comunicacao',
                'title' => 'Fluxo seguro de Comunicação',
                'lead' => 'Centralize o envio para reduzir erros e duplicações.',
                'steps' => ['Definir público', 'Rever canal e mensagem', 'Validar histórico/estado de envio'],
                'guardrail' => 'Evite duplicar avisos sem confirmar envios anteriores.',
                'action_label' => 'Continuar central',
            ],
            'turmas' => [
                'area' => 'secretaria',
                'tone' => 'secretaria',
                'title' => 'Fluxo seguro de Turmas',
                'lead' => 'Turmas afectam alunos, professores, notas e relatórios.',
                'steps' => ['Confirmar classe/turno', 'Rever professor e capacidade', 'Guardar apenas depois de validar alunos afectados'],
                'guardrail' => 'Mudanças de turma podem afectar pautas e relatórios; avance com conferência.',
                'action_label' => 'Continuar turmas',
            ],
        ];
    }
}

if (!function_exists('sige_operational_workflow_current_view_v121800')) {
    function sige_operational_workflow_current_view_v121800(): string {
        if (!is_admin()) { return ''; }
        if (sanitize_key((string)($_GET['page'] ?? '')) !== 'sige-app') { return ''; }
        return sanitize_key((string)($_GET['view'] ?? 'dashboard'));
    }
}

if (!function_exists('sige_operational_workflow_url_v121800')) {
    function sige_operational_workflow_url_v121800(string $view): string {
        $view = preg_replace('/[^a-zA-Z0-9_\-]/', '', $view);
        if ($view === '') { $view = 'dashboard'; }
        if (function_exists('admin_url') && function_exists('add_query_arg')) {
            return (string) add_query_arg(['page' => 'sige-app', 'view' => $view], admin_url('admin.php'));
        }
        return 'admin.php?page=sige-app&view=' . rawurlencode($view);
    }
}

if (!function_exists('sige_operational_workflow_context_v121800')) {
    /**
     * @return array<string,mixed>|null
     */
    function sige_operational_workflow_context_v121800(string $view = ''): ?array {
        $view = $view !== '' ? sanitize_key($view) : sige_operational_workflow_current_view_v121800();
        if ($view === '' || in_array($view, ['dashboard', 'aluno_portal'], true)) { return null; }
        $catalog = sige_operational_workflow_catalog_v121800();
        if (empty($catalog[$view]) || !is_array($catalog[$view])) { return null; }
        $item = $catalog[$view];
        $steps = isset($item['steps']) && is_array($item['steps']) ? array_slice(array_values($item['steps']), 0, 3) : [];
        if (count($steps) < 3) { return null; }
        return [
            'enabled' => true,
            'version' => '12.18.0',
            'view' => $view,
            'area' => sanitize_key((string)($item['area'] ?? 'operacional')),
            'tone' => sanitize_key((string)($item['tone'] ?? 'operacional')),
            'title' => (string)($item['title'] ?? 'Fluxo seguro'),
            'lead' => (string)($item['lead'] ?? 'Orientação operacional para reduzir erro humano.'),
            'steps' => array_map('strval', $steps),
            'guardrail' => (string)($item['guardrail'] ?? ''),
            'actionLabel' => (string)($item['action_label'] ?? 'Continuar'),
            'actionHref' => sige_operational_workflow_url_v121800($view),
            'dismissKey' => 'sige_workflow_hint_' . $view . '_v121800',
        ];
    }
}

add_action('admin_enqueue_scripts', function ($hook) {
    if (!is_admin()) { return; }
    if (sanitize_key((string)($_GET['page'] ?? '')) !== 'sige-app') { return; }
    $context = sige_operational_workflow_context_v121800();
    if (empty($context)) { return; }

    $css = 'assets/operational-workflow-v12-18-0.css';
    $js = 'assets/operational-workflow-v12-18-0.js';
    if (is_file(SIGE_PATH . $css)) {
        wp_enqueue_style('sige-operational-workflow-v12-18-0', SIGE_URL . $css, [], SIGE_VERSION);
    }
    if (is_file(SIGE_PATH . $js)) {
        wp_enqueue_script('sige-operational-workflow-v12-18-0', SIGE_URL . $js, [], SIGE_VERSION, true);
        wp_add_inline_script(
            'sige-operational-workflow-v12-18-0',
            'window.SIGEOperationalWorkflowV121800 = ' . wp_json_encode($context) . ';',
            'before'
        );
    }
}, 34);
