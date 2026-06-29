<?php
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_ui_view_catalog')) {
    function sige_ui_view_catalog(): array {
        return [
            'dashboard' => ['area' => 'Painel', 'title' => 'Painel Principal', 'subtitle' => 'Visão geral da escola, movimentos recentes e indicadores principais.', 'icon' => 'dashboard'],
            'alunos_lista' => ['area' => 'Secretaria', 'title' => 'Alunos', 'subtitle' => 'Consulte, registe e acompanhe os dados dos alunos da escola.', 'icon' => 'users'],
            'turmas' => ['area' => 'Secretaria', 'title' => 'Turmas', 'subtitle' => 'Organize classes, turmas, turnos e listas escolares.', 'icon' => 'school'],
            'aluno_contas' => ['area' => 'Secretaria', 'title' => 'Contas dos Alunos', 'subtitle' => 'Faça a gestão de acessos e perfis ligados aos alunos.', 'icon' => 'key'],
            'disciplinas' => ['area' => 'Académico', 'title' => 'Disciplinas', 'subtitle' => 'Gerencie disciplinas, cargas horárias e vínculos curriculares.', 'icon' => 'book'],
            'matriz' => ['area' => 'Académico', 'title' => 'Matriz Curricular', 'subtitle' => 'Configure a estrutura curricular por classe e ciclo de ensino.', 'icon' => 'grid'],
            'minhas_turmas' => ['area' => 'Área Docente', 'title' => 'Minhas Turmas', 'subtitle' => 'Acesse as turmas atribuídas e actividades pedagógicas relacionadas.', 'icon' => 'book'],
            'notas' => ['area' => 'Área Docente', 'title' => 'Lançamento de Notas', 'subtitle' => 'Registe avaliações dentro das regras académicas já definidas.', 'icon' => 'edit'],
            'pautas' => ['area' => 'Académico', 'title' => 'Pautas', 'subtitle' => 'Consulte pautas de frequência, trimestrais e anuais.', 'icon' => 'file'],
            'boletim' => ['area' => 'Académico', 'title' => 'Aproveitamento', 'subtitle' => 'Analise resultados, boletins e situação de aproveitamento dos alunos.', 'icon' => 'file'],
            'dec' => ['area' => 'Académico', 'title' => 'DEC Estatístico', 'subtitle' => 'Prepare mapas estatísticos oficiais com base nos dados da escola.', 'icon' => 'chart'],
            'pauta_final' => ['area' => 'Académico', 'title' => 'Pauta Final Oficial', 'subtitle' => 'Acompanhe a pauta final e o fecho oficial do aproveitamento.', 'icon' => 'award'],
            'acta' => ['area' => 'Académico', 'title' => 'Acta do Conselho de Notas', 'subtitle' => 'Gere documentos de conselho de notas com dados consolidados.', 'icon' => 'check'],
            'aprovar_notas' => ['area' => 'Coordenação Pedagógica', 'title' => 'Aprovação de Notas', 'subtitle' => 'Reveja e aprove notas submetidas pelos docentes.', 'icon' => 'check'],
            'auditoria_notas' => ['area' => 'Gestão Escolar', 'title' => 'Auditoria de Notas', 'subtitle' => 'Acompanhe alterações e consistência dos lançamentos académicos.', 'icon' => 'shield'],
            'estatisticas_demo' => ['area' => 'Gestão Escolar', 'title' => 'Estatísticas da Escola', 'subtitle' => 'Visualize dados demográficos e indicadores de gestão escolar.', 'icon' => 'chart'],
            'encerramento' => ['area' => 'Ano Lectivo', 'title' => 'Encerramento do Ano', 'subtitle' => 'Conduza o encerramento académico de forma controlada.', 'icon' => 'lock'],
            'abertura' => ['area' => 'Ano Lectivo', 'title' => 'Abertura do Ano', 'subtitle' => 'Prepare a escola para o novo ano lectivo.', 'icon' => 'unlock'],
            'jardim_diario' => ['area' => 'Jardim de Infância', 'title' => 'Diário de Actividades', 'subtitle' => 'Registe actividades, observações e acompanhamento diário.', 'icon' => 'smile'],
            'jardim_saude' => ['area' => 'Jardim de Infância', 'title' => 'Nutrição e Saúde', 'subtitle' => 'Acompanhe informação de saúde e bem-estar das crianças.', 'icon' => 'heart'],
            'jardim_boletim' => ['area' => 'Jardim de Infância', 'title' => 'Boletim Pré-Escolar', 'subtitle' => 'Prepare boletins e relatórios de desenvolvimento infantil.', 'icon' => 'file'],
            'jardim_relatorio' => ['area' => 'Jardim de Infância', 'title' => 'Diários e Análises', 'subtitle' => 'Consulte registos e análises do pré-escolar.', 'icon' => 'chart'],
            'jardim_presencas' => ['area' => 'Jardim de Infância', 'title' => 'Presenças', 'subtitle' => 'Controle presenças e assiduidade das crianças.', 'icon' => 'check'],
            'financeiro-dashboard' => ['area' => 'Financeiro', 'title' => 'Painel Financeiro', 'subtitle' => 'Acompanhe receitas, dívidas, pagamentos e indicadores da tesouraria.', 'icon' => 'chart'],
            'financeiro-pagamentos' => ['area' => 'Financeiro', 'title' => 'Registar Pagamento', 'subtitle' => 'Registe pagamentos mantendo as regras financeiras da escola.', 'icon' => 'money'],
            'financeiro-devedores' => ['area' => 'Financeiro', 'title' => 'Central de Cobranças', 'subtitle' => 'Acompanhe saldos pendentes e comunicações de cobrança.', 'icon' => 'trending'],
            'financeiro-mpesa' => ['area' => 'Financeiro', 'title' => 'Pagamentos Móveis', 'subtitle' => 'Acompanhe integrações M-Pesa/e-Mola, callbacks e decisões manuais de pagamentos móveis.', 'icon' => 'money', 'status' => 'beta', 'beta_note' => 'Área em fase beta. Valide a conciliação e os recibos antes de usar em operação crítica; os fluxos M-Pesa/e-Mola ainda podem receber ajustes controlados.'],
            'whatsapp_central' => ['area' => 'Comunicação', 'title' => 'Operação WhatsApp', 'subtitle' => 'Saúde do número, quota, janela de envio e processamento da fila WhatsApp.', 'icon' => 'message'],
            'comunicacoes_central' => ['area' => 'Comunicação', 'title' => 'Central de Comunicações', 'subtitle' => 'Controle os e-mails e mensagens da escola: estado, cancelar, reenviar e editar o texto.', 'icon' => 'message', 'status' => 'beta', 'beta_note' => 'Área em fase beta. Use para acompanhamento assistido da fila de e-mail e WhatsApp; a operação ainda pode receber ajustes controlados.'],
            'whatsapp_diag' => ['area' => 'Comunicação', 'title' => 'Estado das Mensagens', 'subtitle' => 'Acompanhe a saúde operacional do envio de mensagens.', 'icon' => 'message'],
            'whatsapp_circulares' => ['area' => 'Comunicação', 'title' => 'Circulares aos Encarregados', 'subtitle' => 'Prepare e envie comunicados oficiais por WhatsApp para famílias, turmas ou toda a escola.', 'icon' => 'message', 'status' => 'beta', 'beta_note' => 'Área em fase beta. Confirme destinatários e mensagem antes de enviar; o envio em massa ainda pode receber ajustes controlados.'],
            'presencas' => ['area' => 'Académico', 'title' => 'Presenças por Turma', 'subtitle' => 'Acompanhe mapas de assiduidade derivados das leituras da Portaria e das correcções autorizadas.', 'icon' => 'check', 'status' => 'beta', 'beta_note' => 'Área em fase beta. Confirme amostras com a Portaria antes de adoptar como mapa oficial; as regras de atraso e justificação ainda podem receber ajustes controlados.'],
            'financeiro-auditoria' => ['area' => 'Financeiro', 'title' => 'Auditoria Financeira', 'subtitle' => 'Consulte acções, alterações e histórico financeiro relevante.', 'icon' => 'shield'],
            'pagamentos-turma' => ['area' => 'Financeiro', 'title' => 'Pagamentos por Turma', 'subtitle' => 'Analise a situação de pagamento por turma ou grupo.', 'icon' => 'clipboard'],
            'financeiro-relatorio-mensal' => ['area' => 'Financeiro', 'title' => 'Relatório Mensal', 'subtitle' => 'Prepare relatórios mensais de receitas e movimentos.', 'icon' => 'calendar'],
            'financeiro-extratos' => ['area' => 'Financeiro', 'title' => 'Extractos e Caixa', 'subtitle' => 'Consulte extractos, caixa e movimentos financeiros filtrados.', 'icon' => 'file'],
            'financeiro-lancamentos' => ['area' => 'Financeiro', 'title' => 'Lançamentos Financeiros', 'subtitle' => 'Consulte valores lançados, saldos, estados e histórico financeiro.', 'icon' => 'pin'],
            'financeiro-despesas' => ['area' => 'Financeiro', 'title' => 'Despesas', 'subtitle' => 'Registe e acompanhe despesas da escola.', 'icon' => 'activity'],
            'financeiro-centros' => ['area' => 'Financeiro', 'title' => 'Centros de Custo', 'subtitle' => 'Organize custos por áreas de gestão.', 'icon' => 'building'],
            'financeiro-gerador' => ['area' => 'Financeiro', 'title' => 'Lançar Mensalidades', 'subtitle' => 'Execute lançamentos conforme as regras já configuradas.', 'icon' => 'rocket'],
            'financeiro-planos' => ['area' => 'Financeiro', 'title' => 'Planos de Pagamento', 'subtitle' => 'Acompanhe acordos de pagamento faseado com encarregados.', 'icon' => 'clipboard'],
            'financeiro-config' => ['area' => 'Financeiro', 'title' => 'Preços e Serviços', 'subtitle' => 'Configure serviços, preços e condições financeiras da escola.', 'icon' => 'settings'],
            'equipe' => ['area' => 'Recursos Humanos', 'title' => 'Equipa e Professores', 'subtitle' => 'Gerencie colaboradores, docentes e dados de RH.', 'icon' => 'users'],
            'portaria' => ['area' => 'Operação Escolar', 'title' => 'Portaria Digital', 'subtitle' => 'Controle entradas e saídas através de QR Code ou pesquisa manual.', 'icon' => 'shield'],
            'transporte' => ['area' => 'Operação Escolar', 'title' => 'Transporte Escolar', 'subtitle' => 'Organize rotas, alunos transportados e informação logística.', 'icon' => 'truck'],
            'config_center' => ['area' => 'Administração', 'title' => 'Centro de Configuração', 'subtitle' => 'Ajuste identidade, ano lectivo, documentos, comunicação e preferências.', 'icon' => 'settings'],
            'config' => ['area' => 'Administração', 'title' => 'Centro de Configuração', 'subtitle' => 'Ajuste identidade, ano lectivo, documentos, comunicação e preferências.', 'icon' => 'settings'],
            'sige_permissoes' => ['area' => 'Administração', 'title' => 'Perfis e Permissões', 'subtitle' => 'Defina o que cada equipa pode consultar ou executar no sistema.', 'icon' => 'users'],
            'sige_core_status' => ['area' => 'Administração', 'title' => 'Saúde do Sistema', 'subtitle' => 'Verifique a condição operacional da instalação SoftGenial.', 'icon' => 'shield'],
        ];
    }
}

if (!function_exists('sige_ui_view_meta')) {
    function sige_ui_view_meta(string $view): array {
        $catalog = sige_ui_view_catalog();
        return $catalog[$view] ?? [
            'area' => 'SoftGenial',
            'title' => 'Área da Escola',
            'subtitle' => 'Área integrada ao sistema de gestão escolar.',
            'icon' => 'grid',
        ];
    }
}

if (!function_exists('sige_ui_icon')) {
    function sige_ui_icon(string $name): string {
        $name = sanitize_key($name ?: 'grid');
        static $svg_cache = [];
        $svg_path = defined('SIGE_PATH') ? SIGE_PATH . 'assets/icons/sg/' . $name . '.svg' : '';
        if ($svg_path && isset($svg_cache[$svg_path])) {
            return $svg_cache[$svg_path];
        }
        if ($svg_path && is_readable($svg_path)) {
            $svg = file_get_contents($svg_path);
            if (is_string($svg) && strpos($svg, '<svg') !== false) {
                // GARANTIA DE CONSISTÊNCIA: força os atributos canónicos da <svg> raiz
                // (tamanho, fill, stroke, linecaps) independentemente de como o ficheiro
                // foi gravado. O dimensionamento óptico do CONTEÚDO é normalizado no build
                // (ver tools/normalizar-icones + smoke-icones); aqui blindamos o invólucro.
                $svg = sige_ui_icon_canonizar($svg, $name);
                $svg_cache[$svg_path] = $svg;
                return $svg;
            }
        }
        $fallback = '<rect x="3" y="3" width="7" height="7" rx="1.8"/><rect x="14" y="3" width="7" height="7" rx="1.8"/><rect x="3" y="14" width="7" height="7" rx="1.8"/><rect x="14" y="14" width="7" height="7" rx="1.8"/>';
        return '<svg class="sg-svg-icon sg-svg-icon-grid" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $fallback . '</svg>';
    }
}

if (!function_exists('sige_ui_icon_canonizar')) {
    /**
     * Garante que a tag <svg> raiz tem sempre os mesmos atributos de
     * apresentação. A consistência ÓPTICA do desenho (escala/centragem)
     * é feita no build; esta função assegura o invólucro uniforme.
     */
    function sige_ui_icon_canonizar(string $svg, string $name): string {
        // Capturar só a tag de abertura <svg ...>
        if (!preg_match('/<svg\b[^>]*>/i', $svg, $m)) return $svg;
        $abertura_canonica = '<svg class="sg-svg-icon sg-svg-icon-' . $name . '" '
            . 'xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" '
            . 'fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" '
            . 'stroke-linejoin="round" aria-hidden="true">';
        return str_replace($m[0], $abertura_canonica, $svg);
    }
}

if (!function_exists('sige_ui_render_module_header')) {
    function sige_ui_render_module_header(string $view, $escola = null, $user = null): void {
        $meta = sige_ui_view_meta($view);
        $area = (string)($meta['area'] ?? 'SoftGenial');
        $title = (string)($meta['title'] ?? 'Área da Escola');
        $subtitle = (string)($meta['subtitle'] ?? 'Área integrada ao sistema de gestão escolar.');
        $icon = (string)($meta['icon'] ?? 'grid');
        echo '<section class="sg-product-page-head" aria-label="' . esc_attr($title) . '">';
        echo '<div class="sg-product-page-icon">' . sige_ui_icon($icon) . '</div>';
        $status = sanitize_key((string)($meta['status'] ?? ''));
        $beta_note = trim((string)($meta['beta_note'] ?? ''));
        echo '<div class="sg-product-page-text">';
        echo '<div class="sg-product-breadcrumb"><span>SoftGenial</span><span>' . esc_html($area) . '</span></div>';
        echo '<div class="sg-product-page-title-row"><h1>' . esc_html($title) . '</h1>';
        if ($status === 'beta') {
            echo '<span class="sg-product-status-badge sg-product-status-beta">BETA</span>';
        }
        echo '</div>';
        echo '<p>' . esc_html($subtitle) . '</p>';
        if ($status === 'beta' && $beta_note !== '') {
            echo '<div class="sg-product-beta-note" role="note"><strong>Nota beta:</strong> ' . esc_html($beta_note) . '</div>';
        }
        echo '</div>';
        echo '</section>';
    }
}

if (!function_exists('sige_ui_render_access_unavailable')) {
    function sige_ui_render_access_unavailable(bool $permission_blocked = false): void {
        $title = $permission_blocked ? 'Acesso não disponível' : 'Área não activa';
        $text = $permission_blocked
            ? 'O seu perfil não inclui esta área. Peça à direcção da escola para rever as permissões, caso precise deste acesso.'
            : 'Esta área não está activa para a escola neste momento. A operação normal do sistema permanece disponível nas restantes áreas.';
        echo '<div class="sg-product-state sg-product-state-warning">';
        echo '<div class="sg-product-state-icon">' . sige_ui_icon('lock') . '</div>';
        echo '<div><h2>' . esc_html($title) . '</h2><p>' . esc_html($text) . '</p>';
        echo '<a class="sg-product-btn" href="' . esc_url(admin_url('admin.php?page=sige-app')) . '">Voltar ao painel</a>';
        echo '</div></div>';
    }
}

if (!function_exists('sige_ui_render_missing_module')) {
    function sige_ui_render_missing_module(): void {
        echo '<div class="sg-product-state">';
        echo '<div class="sg-product-state-icon">' . sige_ui_icon('grid') . '</div>';
        echo '<div><h2>Área indisponível</h2><p>Esta área não está disponível nesta instalação. As restantes áreas do sistema continuam operacionais.</p>';
        echo '<a class="sg-product-btn" href="' . esc_url(admin_url('admin.php?page=sige-app')) . '">Voltar ao painel</a>';
        echo '</div></div>';
    }
}
