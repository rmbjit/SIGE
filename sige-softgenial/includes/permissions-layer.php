<?php
/**
 * SIGE SoftGenial - Permission Engine v12.8.1
 *
 * Fundação real para permissões granulares por acção.
 * Objectivos desta fase:
 *  - Criar registry central de permissões.
 *  - Criar/actualizar tabelas próprias via dbDelta.
 *  - Sem UI ainda.
 *  - Manter compatibilidade: administradores e roles/capabilities antigas continuam operacionais.
 *  - Permissões críticas passam por sige_can('area.acao').
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_permission_normalize')) {
    function sige_permission_normalize($permission): string {
        $permission = strtolower(trim((string)$permission));
        $permission = str_replace([' ', ':', '/'], ['_', '.', '.'], $permission);
        $permission = preg_replace('/[^a-z0-9_.-]/', '', $permission);
        return trim((string)$permission, '.-_');
    }
}

if (!function_exists('sige_permissions_registry')) {
    function sige_permissions_registry(): array {
        $base = [
            // Financeiro
            'financeiro.ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver financeiro','descricao'=>'Aceder ao módulo financeiro.'],
            'financeiro.pagar' => ['modulo'=>'financeiro','risco'=>'medio','nome'=>'Registar pagamentos','descricao'=>'Registar pagamentos e emitir recibos.'],
            'financeiro.estornar' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Estornar pagamentos','descricao'=>'Executar estornos totais ou parciais.'],
            'financeiro.bloquear_mes' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Bloquear mês','descricao'=>'Bloquear um mês financeiro.'],
            'financeiro.desbloquear_mes' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Desbloquear mês','descricao'=>'Remover bloqueio de mês financeiro.'],
            'financeiro.ver_relatorios' => ['modulo'=>'financeiro','risco'=>'medio','nome'=>'Ver relatórios financeiros','descricao'=>'Consultar relatórios e indicadores financeiros.'],
            'financeiro.imprimir_recibo' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Imprimir recibo','descricao'=>'Imprimir recibos e documentos financeiros.'],
            'financeiro.apagar_pagamento' => ['modulo'=>'financeiro','risco'=>'critico','nome'=>'Apagar pagamento','descricao'=>'Apagar pagamentos, quando aplicável.'],

            // Académico
            'academico.ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver académico','descricao'=>'Aceder ao módulo académico.'],
            'academico.lancar_notas' => ['modulo'=>'academico','risco'=>'medio','nome'=>'Lançar notas','descricao'=>'Lançar notas dos alunos.'],
            'academico.editar_notas' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Editar notas','descricao'=>'Editar notas já lançadas.'],
            'academico.fechar_ano' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Fechar ano lectivo','descricao'=>'Encerrar o ano lectivo.'],

            // Alunos / Secretaria
            'alunos.ver' => ['modulo'=>'secretaria','risco'=>'baixo','nome'=>'Ver alunos','descricao'=>'Consultar lista e perfil de alunos.'],
            'alunos.criar' => ['modulo'=>'secretaria','risco'=>'medio','nome'=>'Criar alunos','descricao'=>'Criar novos alunos.'],
            'alunos.editar' => ['modulo'=>'secretaria','risco'=>'medio','nome'=>'Editar alunos','descricao'=>'Editar dados de alunos.'],
            'alunos.apagar' => ['modulo'=>'secretaria','risco'=>'alto','nome'=>'Apagar alunos','descricao'=>'Apagar alunos, quando aplicável.'],
            'documentos.emitir' => ['modulo'=>'documentos','risco'=>'medio','nome'=>'Emitir documentos','descricao'=>'Emitir declarações e documentos escolares.'],
            'documentos.emitir_finais' => ['modulo'=>'documentos','risco'=>'alto','nome'=>'Emitir documentos finais','descricao'=>'Emitir documentos finais controlados.'],

            // Configurações / Utilizadores
            'configuracoes.ver' => ['modulo'=>'configuracoes','risco'=>'baixo','nome'=>'Ver configurações','descricao'=>'Consultar configurações.'],
            'configuracoes.editar' => ['modulo'=>'configuracoes','risco'=>'critico','nome'=>'Editar configurações','descricao'=>'Alterar configurações do sistema.'],
            'usuarios.ver' => ['modulo'=>'usuarios','risco'=>'medio','nome'=>'Ver utilizadores','descricao'=>'Consultar utilizadores.'],
            'usuarios.gerir_permissoes' => ['modulo'=>'usuarios','risco'=>'critico','nome'=>'Gerir permissões','descricao'=>'Gerir perfis e permissões.'],

            // Portal
            'portal.ver' => ['modulo'=>'portal','risco'=>'baixo','nome'=>'Ver portal','descricao'=>'Aceder ao portal.'],
            'portal.ver_pagamentos' => ['modulo'=>'portal','risco'=>'baixo','nome'=>'Ver pagamentos no portal','descricao'=>'Consultar pagamentos no portal.'],
            'portal.ver_boletim' => ['modulo'=>'portal','risco'=>'baixo','nome'=>'Ver boletim no portal','descricao'=>'Consultar boletins no portal.'],
            'portal.baixar_documentos' => ['modulo'=>'portal','risco'=>'baixo','nome'=>'Baixar documentos no portal','descricao'=>'Baixar documentos disponíveis no portal.'],
        ];
        return array_merge($base, sige_permissions_registry_expansion_1281());
    }
}
if (!function_exists('sige_permissions_registry_expansion_1281')) {
    function sige_permissions_registry_expansion_1281(): array {
        return [
            'financeiro.dashboard_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver dashboard financeiro','descricao'=>'Consultar indicadores gerais do financeiro.'],
            'financeiro.pagamento_parcial' => ['modulo'=>'financeiro','risco'=>'medio','nome'=>'Registar pagamento parcial','descricao'=>'Registar pagamentos inferiores ao total seleccionado.'],
            'financeiro.aplicar_desconto' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Aplicar desconto especial','descricao'=>'Aplicar desconto especial no acto de pagamento.'],
            'financeiro.isentar_multas' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Isentar multas','descricao'=>'Isentar multas ou juros no pagamento.'],
            'financeiro.extractos_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver extractos / caixa','descricao'=>'Consultar extractos, recibos e movimentos de caixa.'],
            'financeiro.caixa_fechar' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Fechar caixa','descricao'=>'Executar fecho de caixa.'],
            'financeiro.caixa_reabrir' => ['modulo'=>'financeiro','risco'=>'critico','nome'=>'Reabrir caixa','descricao'=>'Reabrir caixa ou período já fechado.'],
            'financeiro.relatorio_mensal_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver relatório mensal','descricao'=>'Consultar relatório financeiro mensal.'],
            'financeiro.auditoria_ver' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Ver auditoria financeira','descricao'=>'Consultar auditorias e saneamentos financeiros.'],
            'financeiro.pagamentos_turma_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver pagamentos por turma','descricao'=>'Consultar pagamentos agrupados por turma.'],
            'financeiro.cobrancas_ver' => ['modulo'=>'financeiro','risco'=>'medio','nome'=>'Ver central de cobranças','descricao'=>'Consultar central de cobranças.'],
            'financeiro.cobrancas_gerir' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Gerir cobranças','descricao'=>'Executar acções de cobrança e comunicação financeira.'],
            'financeiro.lancamentos_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver lançamentos','descricao'=>'Consultar lançamentos financeiros.'],
            'financeiro.lancamentos_gerir' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Gerir lançamentos','descricao'=>'Criar, corrigir ou remover lançamentos financeiros.'],
            'financeiro.lancar_mensalidades' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Lançar mensalidades','descricao'=>'Gerar mensalidades e cobranças recorrentes.'],
            'financeiro.servicos_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver serviços e preços','descricao'=>'Consultar catálogo de serviços e preços.'],
            'financeiro.configurar_precos' => ['modulo'=>'financeiro','risco'=>'critico','nome'=>'Configurar preços','descricao'=>'Alterar mensalidades, serviços, multas e regras financeiras.'],
            'financeiro.despesas_ver' => ['modulo'=>'financeiro','risco'=>'medio','nome'=>'Ver despesas','descricao'=>'Consultar despesas registadas.'],
            'financeiro.despesas_gerir' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Gerir despesas','descricao'=>'Criar, aprovar ou alterar despesas.'],
            'financeiro.centros_custo_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver centros de custo','descricao'=>'Consultar centros de custo.'],
            'financeiro.centros_custo_gerir' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Gerir centros de custo','descricao'=>'Criar ou alterar centros de custo.'],
            'financeiro.planos_ver' => ['modulo'=>'financeiro','risco'=>'baixo','nome'=>'Ver planos/prestações','descricao'=>'Consultar planos de pagamento e prestações.'],
            'financeiro.planos_gerir' => ['modulo'=>'financeiro','risco'=>'alto','nome'=>'Gerir planos/prestações','descricao'=>'Criar, aprovar ou alterar planos de pagamento.'],

            'academico.dashboard_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver Painel Principal','descricao'=>'Consultar o Painel Principal da escola. Para professores, esta permissão deve ficar desmarcada quando se pretende acesso apenas às áreas operacionais.'],
            'academico.turmas_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver turmas/grupos','descricao'=>'Consultar turmas e grupos.'],
            'academico.turmas_gerir' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Gerir turmas/grupos','descricao'=>'Criar, editar ou arquivar turmas e grupos.'],
            'academico.disciplinas_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver disciplinas','descricao'=>'Consultar disciplinas.'],
            'academico.disciplinas_gerir' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Gerir disciplinas','descricao'=>'Criar, editar ou remover disciplinas.'],
            'academico.matriz_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver matriz curricular','descricao'=>'Consultar matriz curricular.'],
            'academico.matriz_gerir' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Gerir matriz curricular','descricao'=>'Alterar matriz curricular por classe/turma.'],
            'academico.aprovar_notas' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Aprovar notas','descricao'=>'Aprovar ou validar notas lançadas.'],
            'academico.auditoria_notas_ver' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Ver auditoria de notas','descricao'=>'Consultar histórico e auditoria de notas.'],
            'academico.pautas_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver pautas','descricao'=>'Consultar pautas.'],
            'academico.pautas_emitir' => ['modulo'=>'academico','risco'=>'medio','nome'=>'Emitir pautas','descricao'=>'Gerar ou imprimir pautas.'],
            'academico.pauta_final_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver pauta final','descricao'=>'Consultar pauta final.'],
            'academico.pauta_final_gerir' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Gerir pauta final','descricao'=>'Preparar ou controlar pauta final.'],
            'academico.boletins_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver boletins','descricao'=>'Consultar boletins.'],
            'academico.boletins_emitir' => ['modulo'=>'academico','risco'=>'medio','nome'=>'Emitir boletins','descricao'=>'Gerar ou imprimir boletins.'],
            'academico.actas_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver actas','descricao'=>'Consultar actas.'],
            'academico.actas_emitir' => ['modulo'=>'academico','risco'=>'medio','nome'=>'Emitir/editar actas','descricao'=>'Gerar, guardar e imprimir actas oficiais.'],
            'academico.dec_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver DEC','descricao'=>'Consultar DEC / documentos estatísticos.'],
            'academico.dec_emitir' => ['modulo'=>'academico','risco'=>'medio','nome'=>'Emitir/gerar DEC','descricao'=>'Gerar, imprimir ou exportar documentos DEC.'],
            'academico.alocacao_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver alocação','descricao'=>'Consultar alocação pedagógica.'],
            'academico.alocacao_gerir' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Gerir alocação','descricao'=>'Alterar alocação de professores/turmas.'],
            'academico.estatisticas_ver' => ['modulo'=>'academico','risco'=>'baixo','nome'=>'Ver estatísticas académicas','descricao'=>'Consultar estatísticas demográficas e académicas.'],
            'academico.reabrir_ano' => ['modulo'=>'academico','risco'=>'critico','nome'=>'Reabrir ano lectivo','descricao'=>'Reabrir ano lectivo já encerrado.'],

            'alunos.contas_ver' => ['modulo'=>'secretaria','risco'=>'baixo','nome'=>'Ver contas de alunos','descricao'=>'Consultar contas de acesso dos alunos/encarregados.'],
            'alunos.contas_gerir' => ['modulo'=>'secretaria','risco'=>'alto','nome'=>'Gerir contas de alunos','descricao'=>'Criar, vincular ou repor contas de acesso.'],
            'matriculas.ver' => ['modulo'=>'secretaria','risco'=>'baixo','nome'=>'Ver matrículas','descricao'=>'Consultar matrículas e inscrições.'],
            'matriculas.criar' => ['modulo'=>'secretaria','risco'=>'medio','nome'=>'Criar matrículas','descricao'=>'Matricular ou inscrever alunos.'],
            'matriculas.editar' => ['modulo'=>'secretaria','risco'=>'medio','nome'=>'Editar matrículas','descricao'=>'Alterar dados de matrícula.'],
            'matriculas.cancelar' => ['modulo'=>'secretaria','risco'=>'alto','nome'=>'Cancelar matrículas','descricao'=>'Cancelar ou anular matrícula.'],
            'matriculas.transferir' => ['modulo'=>'secretaria','risco'=>'alto','nome'=>'Transferir aluno','descricao'=>'Transferir aluno entre turmas/classes.'],

            'documentos.ver' => ['modulo'=>'documentos','risco'=>'baixo','nome'=>'Ver documentos','descricao'=>'Consultar área de documentos.'],
            'documentos.reemitir' => ['modulo'=>'documentos','risco'=>'medio','nome'=>'Reemitir documentos','descricao'=>'Reemitir documentos anteriormente gerados.'],
            'documentos.anular' => ['modulo'=>'documentos','risco'=>'alto','nome'=>'Anular documentos','descricao'=>'Anular documentos emitidos.'],

            'transporte.ver' => ['modulo'=>'transporte','risco'=>'baixo','nome'=>'Ver transporte','descricao'=>'Consultar rotas e transporte escolar.'],
            'transporte.rotas_gerir' => ['modulo'=>'transporte','risco'=>'alto','nome'=>'Gerir rotas de transporte','descricao'=>'Criar ou editar rotas e preços de transporte.'],
            'transporte.alunos_gerir' => ['modulo'=>'transporte','risco'=>'medio','nome'=>'Gerir transporte de alunos','descricao'=>'Associar alunos a rotas de transporte.'],


            'jardim.ver' => ['modulo'=>'jardim','risco'=>'baixo','nome'=>'Ver jardim/creche','descricao'=>'Aceder aos módulos de jardim/creche.'],
            'jardim.diario_ver' => ['modulo'=>'jardim','risco'=>'baixo','nome'=>'Ver diário do jardim','descricao'=>'Consultar diário pedagógico.'],
            'jardim.diario_gerir' => ['modulo'=>'jardim','risco'=>'medio','nome'=>'Gerir diário do jardim','descricao'=>'Registar ou alterar diário pedagógico.'],
            'jardim.presencas_ver' => ['modulo'=>'jardim','risco'=>'baixo','nome'=>'Ver presenças do jardim','descricao'=>'Consultar presenças.'],
            'jardim.presencas_gerir' => ['modulo'=>'jardim','risco'=>'medio','nome'=>'Gerir presenças do jardim','descricao'=>'Registar ou alterar presenças.'],
            'jardim.saude_ver' => ['modulo'=>'jardim','risco'=>'medio','nome'=>'Ver saúde do aluno','descricao'=>'Consultar registos de saúde e cuidados.'],
            'jardim.saude_gerir' => ['modulo'=>'jardim','risco'=>'alto','nome'=>'Gerir saúde do aluno','descricao'=>'Criar ou alterar registos de saúde.'],
            'jardim.boletim_ver' => ['modulo'=>'jardim','risco'=>'baixo','nome'=>'Ver boletim do jardim','descricao'=>'Consultar boletins do jardim/creche.'],
            'jardim.boletim_emitir' => ['modulo'=>'jardim','risco'=>'medio','nome'=>'Emitir boletim do jardim','descricao'=>'Gerar ou imprimir boletins do jardim/creche.'],
            'jardim.relatorio_ver' => ['modulo'=>'jardim','risco'=>'baixo','nome'=>'Ver relatório do jardim','descricao'=>'Consultar relatórios do jardim/creche.'],
            'jardim.relatorio_emitir' => ['modulo'=>'jardim','risco'=>'medio','nome'=>'Emitir relatório do jardim','descricao'=>'Gerar relatórios do jardim/creche.'],

            'rh.equipe_ver' => ['modulo'=>'rh','risco'=>'baixo','nome'=>'Ver equipa','descricao'=>'Consultar equipa, professores e colaboradores.'],
            'rh.equipe_gerir' => ['modulo'=>'rh','risco'=>'alto','nome'=>'Gerir equipa','descricao'=>'Criar, editar ou remover membros da equipa.'],

            'comunicacao.ver' => ['modulo'=>'comunicacao','risco'=>'baixo','nome'=>'Ver comunicação','descricao'=>'Consultar área de comunicação e notificações.'],
            'comunicacao.enviar' => ['modulo'=>'comunicacao','risco'=>'alto','nome'=>'Enviar comunicação','descricao'=>'Enviar mensagens a encarregados, alunos ou equipa.'],
            'comunicacao.templates_gerir' => ['modulo'=>'comunicacao','risco'=>'medio','nome'=>'Gerir modelos de mensagem','descricao'=>'Criar ou alterar modelos de mensagens.'],
            // v12.12.0 - Permissoes explicitas para views previamente cobertas apenas por guards internos.
            'comunicacao.central_ver' => ['modulo'=>'comunicacao','risco'=>'medio','nome'=>'Ver Central de Comunicações','descricao'=>'Aceder à Central de Comunicações unificada de e-mail e WhatsApp.'],
            'comunicacao.whatsapp_ver' => ['modulo'=>'comunicacao','risco'=>'medio','nome'=>'Ver Central WhatsApp','descricao'=>'Aceder à fila operacional de mensagens WhatsApp.'],
            'comunicacao.whatsapp_diagnostico_ver' => ['modulo'=>'comunicacao','risco'=>'alto','nome'=>'Ver Diagnóstico WhatsApp','descricao'=>'Consultar estado técnico, erros e fila do WhatsApp.'],
            'comunicacao.circulares_enviar' => ['modulo'=>'comunicacao','risco'=>'alto','nome'=>'Enviar circulares','descricao'=>'Enviar comunicados oficiais por WhatsApp aos encarregados.'],
            'academico.presencas_ver' => ['modulo'=>'academico','risco'=>'medio','nome'=>'Ver presenças','descricao'=>'Consultar mapas de assiduidade e presenças.'],
            'academico.presencas_gerir' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Gerir presenças','descricao'=>'Registar ou alterar presenças dos alunos.'],
            'financeiro.mobile_payments_gerir' => ['modulo'=>'financeiro','risco'=>'critico','nome'=>'Gerir pagamentos móveis','descricao'=>'Configurar e acompanhar M-Pesa/e-Mola e webhooks de pagamentos móveis.'],

            'configuracoes.feature_flags' => ['modulo'=>'configuracoes','risco'=>'critico','nome'=>'Gerir feature flags','descricao'=>'Activar ou desactivar funcionalidades por escola.'],
            'usuarios.criar' => ['modulo'=>'usuarios','risco'=>'alto','nome'=>'Criar utilizadores','descricao'=>'Criar utilizadores do sistema.'],
            'usuarios.editar' => ['modulo'=>'usuarios','risco'=>'alto','nome'=>'Editar utilizadores','descricao'=>'Editar utilizadores do sistema.'],
            'usuarios.desactivar' => ['modulo'=>'usuarios','risco'=>'alto','nome'=>'Desactivar utilizadores','descricao'=>'Desactivar acessos de utilizadores.'],

            'sistema.estado_ver' => ['modulo'=>'sistema','risco'=>'baixo','nome'=>'Ver estado do sistema','descricao'=>'Consultar diagnóstico e estado técnico do sistema.'],
            'sistema.logs_ver' => ['modulo'=>'sistema','risco'=>'alto','nome'=>'Ver logs técnicos','descricao'=>'Consultar logs técnicos e auditoria.'],
            'sistema.migrations_ver' => ['modulo'=>'sistema','risco'=>'alto','nome'=>'Ver migrations','descricao'=>'Consultar histórico de migrações da base de dados.'],
            'sistema.migrations_executar' => ['modulo'=>'sistema','risco'=>'critico','nome'=>'Executar migrations','descricao'=>'Executar ou reparar migrações da base de dados.'],
            'sistema.license_ver' => ['modulo'=>'sistema','risco'=>'baixo','nome'=>'Ver licença','descricao'=>'Consultar estado da licença e plano.'],
            'sistema.license_gerir' => ['modulo'=>'sistema','risco'=>'critico','nome'=>'Gerir licença','descricao'=>'Alterar dados ou sincronização de licença.'],

            // v12.11.9.65 - Portaria como permissão própria, separada de Configurações.
            'portaria.ver' => ['modulo'=>'portaria','risco'=>'baixo','nome'=>'Usar Portaria Digital','descricao'=>'Aceder ao módulo Portaria Digital para consulta operacional de entradas e saídas.'],
            'portaria.validar_acesso' => ['modulo'=>'portaria','risco'=>'medio','nome'=>'Validar crachás na Portaria','descricao'=>'Escanear/validar crachás de alunos e registar eventos de acesso na portaria.'],

            'portal.ver_documentos' => ['modulo'=>'portal','risco'=>'baixo','nome'=>'Ver documentos no portal','descricao'=>'Consultar documentos no portal.'],
            'portal.actualizar_dados' => ['modulo'=>'portal','risco'=>'medio','nome'=>'Actualizar dados no portal','descricao'=>'Permitir actualização de dados pelo portal.'],

            // v12.12.23 - Fase 8 Incr 1: governanca de dados pessoais (inventario, so leitura).
            'privacidade.inventario_ver' => ['modulo'=>'sistema','risco'=>'medio','nome'=>'Ver inventário de dados pessoais','descricao'=>'Consultar o mapa de dados pessoais (PII) por tabela e campo, a sua finalidade, base legal e agregados por escola. Só leitura.'],
            // v12.12.24 - Fase 8 Incr 2: direito de acesso e portabilidade (exportar dossie do titular).
            'privacidade.acesso_exportar' => ['modulo'=>'sistema','risco'=>'alto','nome'=>'Exportar dossiê de dados pessoais','descricao'=>'Reunir e exportar os dados pessoais de um aluno (direito de acesso e portabilidade). Restrito à administração e direcção.'],
            // v12.12.25 - Fase 8 Incr 3: direito ao apagamento (anonimizacao do titular).
            'privacidade.apagamento_executar' => ['modulo'=>'sistema','risco'=>'critico','nome'=>'Apagar dados pessoais (anonimização)','descricao'=>'Anonimizar de forma permanente os dados pessoais identificáveis de um aluno, preservando número de processo e valores financeiros e académicos. Operação irreversível, restrita à administração e direcção.'],
            // v12.12.27 - Fase 8 Incr 4: retencao e expurgo (so leitura).
            'privacidade.retencao_ver' => ['modulo'=>'sistema','risco'=>'medio','nome'=>'Ver retenção de dados','descricao'=>'Ver a política de retenção e quantos registos já excederam o prazo, de forma agregada por escola. Só leitura; não elimina dados. Restrito à administração e direcção.'],
        ];
    }
}


if (!function_exists('sige_permissions_default_roles')) {
    /**
     * Perfis SIGE padrão alinhados às funções reais usadas no cadastro de funcionários.
     * Mantém slugs antigos para compatibilidade e adiciona perfis operacionais do RH.
     */
    function sige_permissions_default_roles(): array {
        $all_permissions = array_keys(sige_permissions_registry());

        $financeiro_operacao = [
            'financeiro.ver','financeiro.dashboard_ver','financeiro.pagar','financeiro.pagamento_parcial',
            'financeiro.aplicar_desconto','financeiro.isentar_multas','financeiro.imprimir_recibo',
            'financeiro.extractos_ver','financeiro.ver_relatorios','financeiro.relatorio_mensal_ver',
            'financeiro.pagamentos_turma_ver','financeiro.cobrancas_ver',
            'comunicacao.central_ver','comunicacao.whatsapp_ver','comunicacao.whatsapp_diagnostico_ver',
        ];
        // (v12.9.8.3) Permissões de leitura sobre o cadastro indispensáveis para
        // o tesoureiro fazer o seu trabalho num plano "Tesouraria (somente)":
        // sem ver alunos, turmas e matrículas, não consegue facturar ninguém.
        // Mantemos apenas leitura - criação/edição continua restrita à secretaria.
        $tesoureiro_cadastro_view = [
            'alunos.ver','alunos.contas_ver','academico.turmas_ver','matriculas.ver',
        ];
        $secretaria_operacao = [
            'alunos.ver','alunos.criar','alunos.editar','alunos.apagar','alunos.contas_ver',
            'matriculas.ver','matriculas.criar','matriculas.editar',
            'academico.ver','academico.turmas_ver','academico.disciplinas_ver',
            'documentos.ver','documentos.emitir','documentos.reemitir',
            'financeiro.ver','financeiro.extractos_ver','financeiro.imprimir_recibo',
            'academico.presencas_ver','academico.presencas_gerir',
            'comunicacao.central_ver','comunicacao.whatsapp_ver',
            'transporte.ver','transporte.alunos_gerir',
            'portaria.ver','portaria.validar_acesso',
        ];
        $docente_operacao = [
            'academico.ver','academico.turmas_ver',
            'academico.lancar_notas','academico.presencas_ver','academico.presencas_gerir','academico.boletins_ver','academico.pautas_ver',
            'alunos.ver','portal.ver',
        ];
        $educador_operacao = [
            'academico.ver','academico.turmas_ver','alunos.ver',
            'jardim.ver','jardim.diario_ver','jardim.diario_gerir','jardim.presencas_ver',
            'jardim.presencas_gerir','jardim.saude_ver','jardim.boletim_ver','portal.ver',
        ];
        $direccao_base = [
            'financeiro.ver','financeiro.dashboard_ver','financeiro.ver_relatorios','financeiro.relatorio_mensal_ver',
            'financeiro.extractos_ver','financeiro.auditoria_ver','financeiro.bloquear_mes','financeiro.desbloquear_mes',
            'financeiro.caixa_fechar','financeiro.caixa_reabrir','financeiro.centros_custo_ver','financeiro.mobile_payments_gerir',
            'comunicacao.central_ver','comunicacao.whatsapp_ver','comunicacao.whatsapp_diagnostico_ver','comunicacao.circulares_enviar',
            'academico.ver','academico.dashboard_ver','academico.fechar_ano','academico.reabrir_ano',
            'academico.aprovar_notas','academico.auditoria_notas_ver','academico.pautas_ver','academico.pautas_emitir',
            'academico.pauta_final_ver','academico.pauta_final_gerir','academico.boletins_ver','academico.boletins_emitir',
            'academico.dec_ver','academico.dec_emitir','academico.actas_ver','academico.actas_emitir',
            'academico.estatisticas_ver','academico.presencas_ver','academico.presencas_gerir','alunos.ver','alunos.apagar','matriculas.ver',
            'documentos.ver','documentos.emitir','documentos.emitir_finais',
            'configuracoes.ver','usuarios.ver','rh.equipe_ver',
            'transporte.ver','transporte.alunos_gerir','transporte.rotas_gerir',
            'portaria.ver','portaria.validar_acesso',
            'privacidade.inventario_ver',
            'privacidade.acesso_exportar',
            'privacidade.apagamento_executar',
            'privacidade.retencao_ver',
        ];

        return [
            'admin_escola' => ['nome'=>'Admin da Escola','descricao'=>'Gestão ampla da instância da escola.','permissions'=>$all_permissions],
            'admin_ti' => ['nome'=>'Admin TI','descricao'=>'Administração técnica do sistema, integrações, licença, migrations e permissões.','permissions'=>$all_permissions],
            'direccao_geral' => ['nome'=>'Direcção Geral','descricao'=>'Governação geral da escola, supervisão financeira, académica, RH e relatórios.','permissions'=>array_values(array_unique(array_merge($direccao_base, ['financeiro.estornar','financeiro.despesas_ver','financeiro.centros_custo_gerir','configuracoes.editar','usuarios.gerir_permissoes','usuarios.criar','usuarios.editar','rh.equipe_gerir','sistema.estado_ver','sistema.license_ver'])))],
            'director' => ['nome'=>'Director','descricao'=>'Perfil legado compatível com versões anteriores; direcção pedagógica e administrativa.','permissions'=>$direccao_base],
            'dir_pedagogico' => ['nome'=>'Dir. Pedagógico','descricao'=>'Direcção pedagógica: turmas, notas, pautas, boletins, actas e encerramento académico.','permissions'=>['academico.ver','academico.dashboard_ver','academico.turmas_ver','academico.turmas_gerir','academico.disciplinas_ver','academico.disciplinas_gerir','academico.matriz_ver','academico.matriz_gerir','academico.lancar_notas','academico.editar_notas','academico.presencas_ver','academico.presencas_gerir','academico.aprovar_notas','academico.auditoria_notas_ver','academico.pautas_ver','academico.pautas_emitir','academico.pauta_final_ver','academico.pauta_final_gerir','academico.boletins_ver','academico.boletins_emitir','academico.actas_ver','academico.actas_emitir','academico.dec_ver','academico.dec_emitir','academico.alocacao_ver','academico.alocacao_gerir','academico.estatisticas_ver','academico.fechar_ano','academico.reabrir_ano','alunos.ver','matriculas.ver','documentos.ver','documentos.emitir','documentos.emitir_finais','jardim.ver','jardim.relatorio_ver','jardim.relatorio_emitir']],
            'gestor_rh' => ['nome'=>'Gestor de RH','descricao'=>'Gestão de equipa, professores e funções internas.','permissions'=>['rh.equipe_ver','rh.equipe_gerir','usuarios.ver','usuarios.criar','usuarios.editar','academico.ver','academico.turmas_ver','alunos.ver','sistema.estado_ver']],
            'professor' => ['nome'=>'Professor','descricao'=>'Gestão pedagógica das turmas atribuídas.','permissions'=>$docente_operacao],
            'educador' => ['nome'=>'Educador','descricao'=>'Acompanhamento pedagógico e diário do jardim/creche.','permissions'=>$educador_operacao],
            'secretaria_geral' => ['nome'=>'Secretaria Geral','descricao'=>'Coordenação da secretaria académica, matrículas, alunos e documentos.','permissions'=>array_values(array_unique(array_merge($secretaria_operacao, ['matriculas.cancelar','matriculas.transferir','documentos.anular','academico.estatisticas_ver','usuarios.ver'])))],
            'secretaria' => ['nome'=>'Secretaria','descricao'=>'Perfil legado compatível; gestão de alunos, matrículas e documentos.','permissions'=>$secretaria_operacao],
            'secretario' => ['nome'=>'Secretário','descricao'=>'Operação diária da secretaria: alunos, matrículas e emissão de documentos.','permissions'=>$secretaria_operacao],
            'tesoureiro' => ['nome'=>'Tesouraria','descricao'=>'Operação financeira diária.','permissions'=>array_values(array_unique(array_merge($financeiro_operacao, $tesoureiro_cadastro_view)))],
            'assistente' => ['nome'=>'Assistente','descricao'=>'Apoio administrativo com acesso limitado a consulta e documentos.','permissions'=>['alunos.ver','matriculas.ver','academico.ver','documentos.ver','documentos.emitir','financeiro.ver','financeiro.extractos_ver','transporte.ver']],
            'recepcao' => ['nome'=>'Recepção','descricao'=>'Atendimento, consulta de alunos, contactos e encaminhamento.','permissions'=>['alunos.ver','matriculas.ver','documentos.ver','financeiro.ver','financeiro.extractos_ver','comunicacao.ver','transporte.ver','portaria.ver','portaria.validar_acesso']],
            'guarda' => ['nome'=>'Guarda / Portaria','descricao'=>'Operação restrita da Portaria Digital: validar crachás e consultar alunos sem criar, editar ou remover.','permissions'=>['portaria.ver','portaria.validar_acesso','alunos.ver']],
            'motorista' => ['nome'=>'Motorista','descricao'=>'Acesso limitado ao transporte e lista de alunos associados às rotas.','permissions'=>['transporte.ver','alunos.ver']],
            'limpeza' => ['nome'=>'Limpeza','descricao'=>'Perfil operacional de apoio sem acesso administrativo por defeito.','permissions'=>[]],
            'encarregado' => ['nome'=>'Encarregado','descricao'=>'Acesso limitado ao portal.','permissions'=>['portal.ver','portal.ver_pagamentos','portal.ver_boletim','portal.baixar_documentos','portal.ver_documentos','portal.actualizar_dados']],
        ];
    }
}


if (!function_exists('sige_permissions_tables')) {
    function sige_permissions_tables(): array {
        global $wpdb;
        return [
            'roles' => $wpdb->prefix . 'sige_roles',
            'permissions' => $wpdb->prefix . 'sige_permissions',
            'role_permissions' => $wpdb->prefix . 'sige_role_permissions',
            'school_role_permissions' => $wpdb->prefix . 'sige_school_role_permissions',
            'user_roles' => $wpdb->prefix . 'sige_user_roles',
            'user_overrides' => $wpdb->prefix . 'sige_user_permission_overrides',
            'school_user_overrides' => $wpdb->prefix . 'sige_school_user_permission_overrides',
            'audit' => $wpdb->prefix . 'sige_permission_audit',
        ];
    }
}

if (!function_exists('sige_permissions_install')) {
    function sige_permissions_install(): void {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        $charset = $wpdb->get_charset_collate();
        $t = sige_permissions_tables();

        dbDelta("CREATE TABLE {$t['roles']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            slug varchar(80) NOT NULL,
            nome varchar(190) NOT NULL,
            descricao text NULL,
            is_system tinyint(1) NOT NULL DEFAULT 1,
            ativo tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY slug (slug),
            KEY ativo (ativo)
        ) $charset;");

        dbDelta("CREATE TABLE {$t['permissions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            permission_key varchar(120) NOT NULL,
            nome varchar(190) NOT NULL,
            descricao text NULL,
            modulo varchar(80) NOT NULL,
            risco varchar(30) NOT NULL DEFAULT 'baixo',
            ativo tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY permission_key (permission_key),
            KEY modulo (modulo),
            KEY ativo (ativo)
        ) $charset;");

        dbDelta("CREATE TABLE {$t['role_permissions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            role_id bigint(20) unsigned NOT NULL,
            permission_key varchar(120) NOT NULL,
            allowed tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY role_permission (role_id, permission_key),
            KEY permission_key (permission_key),
            KEY allowed (allowed)
        ) $charset;");

        dbDelta("CREATE TABLE {$t['school_role_permissions']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            escola_id bigint(20) unsigned NOT NULL,
            role_id bigint(20) unsigned NOT NULL,
            permission_key varchar(120) NOT NULL,
            allowed tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY school_role_permission (escola_id, role_id, permission_key),
            KEY escola_id (escola_id),
            KEY role_id (role_id),
            KEY permission_key (permission_key),
            KEY allowed (allowed)
        ) $charset;");

        dbDelta("CREATE TABLE {$t['user_roles']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            role_id bigint(20) unsigned NOT NULL,
            escola_id bigint(20) unsigned NOT NULL DEFAULT 1,
            ativo tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_role_school (user_id, role_id, escola_id),
            KEY user_id (user_id),
            KEY role_id (role_id),
            KEY escola_id (escola_id),
            KEY ativo (ativo)
        ) $charset;");

        dbDelta("CREATE TABLE {$t['user_overrides']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NOT NULL,
            permission_key varchar(120) NOT NULL,
            allowed tinyint(1) NOT NULL DEFAULT 1,
            motivo text NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY user_permission (user_id, permission_key),
            KEY user_id (user_id),
            KEY permission_key (permission_key),
            KEY allowed (allowed)
        ) $charset;");

        dbDelta("CREATE TABLE {$t['school_user_overrides']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            escola_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned NOT NULL,
            permission_key varchar(120) NOT NULL,
            allowed tinyint(1) NOT NULL DEFAULT 1,
            motivo text NULL,
            created_by bigint(20) unsigned NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime NULL,
            PRIMARY KEY  (id),
            UNIQUE KEY school_user_permission (escola_id, user_id, permission_key),
            KEY escola_id (escola_id),
            KEY user_id (user_id),
            KEY permission_key (permission_key),
            KEY allowed (allowed)
        ) $charset;");

        dbDelta("CREATE TABLE {$t['audit']} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            user_id bigint(20) unsigned NULL,
            permission_key varchar(120) NOT NULL,
            allowed tinyint(1) NOT NULL DEFAULT 0,
            source varchar(60) NOT NULL DEFAULT 'unknown',
            contexto longtext NULL,
            ip varchar(64) NULL,
            created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY user_id (user_id),
            KEY permission_key (permission_key),
            KEY allowed (allowed),
            KEY created_at (created_at)
        ) $charset;");

        // Seed idempotente do registry de permissões.
        foreach (sige_permissions_registry() as $key => $meta) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['permissions']} (permission_key, nome, descricao, modulo, risco, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), modulo = VALUES(modulo), risco = VALUES(risco), ativo = 1, updated_at = NOW()",
                $key, $meta['nome'] ?? $key, $meta['descricao'] ?? '', $meta['modulo'] ?? 'core', $meta['risco'] ?? 'baixo'
            ));
        }

        // Seed idempotente dos perfis padrão e permissões.
        foreach (sige_permissions_default_roles() as $slug => $role) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['roles']} (slug, nome, descricao, is_system, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, 1, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ativo = 1, updated_at = NOW()",
                $slug, $role['nome'] ?? $slug, $role['descricao'] ?? ''
            ));
            $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", $slug));
            if ($role_id > 0) {
                foreach ((array)($role['permissions'] ?? []) as $perm) {
                    $perm = sige_permission_normalize($perm);
                    if ($perm === '') continue;
                    $wpdb->query($wpdb->prepare(
                        "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                         VALUES (%d, %s, 1, NOW(), NOW())
                         ON DUPLICATE KEY UPDATE updated_at = updated_at",
                        $role_id, $perm
                    ));
                }
            }
        }

        update_option('sige_permissions_engine_version', '12.12.0', false);
    }
}


if (!function_exists('sige_permissions_migrate_12115_dashboard_defaults')) {
    /**
     * v12.11.5 - remove o Painel Principal dos perfis docentes por defeito.
     *
     * Motivo: o dashboard estava tecnicamente ligado à permissão
     * academico.dashboard_ver, mas o perfil Professor vinha semeado com essa
     * permissão. Isso dava a impressão de que o Painel Principal era inevitável
     * para professores. A partir desta versão, professores continuam a poder
     * receber DEC/ACTA/Pautas por matriz, mas o Painel Principal só aparece se a
     * escola marcar explicitamente a permissão "Ver Painel Principal".
     */
    function sige_permissions_migrate_12115_dashboard_defaults(): void {
        global $wpdb;
        $t = sige_permissions_tables();
        $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", 'professor'));
        if ($role_id > 0) {
            $wpdb->delete($t['role_permissions'], [
                'role_id' => $role_id,
                'permission_key' => 'academico.dashboard_ver',
            ], ['%d','%s']);
        }
        update_option('sige_permissions_engine_version', '12.11.9.65', false);
    }
}


if (!function_exists('sige_permissions_migrate_1211965_guarda_portaria')) {
    /**
     * v12.11.9.65 - migração mínima para o perfil Guarda / Portaria.
     *
     * Não re-semeia permissões de outros perfis: cria/actualiza apenas as
     * permissões de portaria e o perfil guarda com leitura de alunos.
     */
    function sige_permissions_migrate_1211965_guarda_portaria(): void {
        global $wpdb;
        $t = sige_permissions_tables();

        $permissions = [
            'portaria.ver' => ['modulo'=>'portaria','risco'=>'baixo','nome'=>'Usar Portaria Digital','descricao'=>'Aceder ao módulo Portaria Digital para consulta operacional de entradas e saídas.'],
            'portaria.validar_acesso' => ['modulo'=>'portaria','risco'=>'medio','nome'=>'Validar crachás na Portaria','descricao'=>'Escanear/validar crachás de alunos e registar eventos de acesso na portaria.'],
        ];
        foreach ($permissions as $key => $meta) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['permissions']} (permission_key, nome, descricao, modulo, risco, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), modulo = VALUES(modulo), risco = VALUES(risco), ativo = 1, updated_at = NOW()",
                $key, $meta['nome'], $meta['descricao'], $meta['modulo'], $meta['risco']
            ));
        }

        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$t['roles']} (slug, nome, descricao, is_system, ativo, created_at, updated_at)
             VALUES (%s, %s, %s, 1, 1, NOW(), NOW())
             ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), ativo = 1, updated_at = NOW()",
            'guarda', 'Guarda / Portaria', 'Operação restrita da Portaria Digital: validar crachás e consultar alunos sem criar, editar ou remover.'
        ));
        $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", 'guarda'));
        if ($role_id > 0) {
            // O perfil Guarda / Portaria é intencionalmente fechado: exactamente estas permissões.
            $wpdb->delete($t['role_permissions'], ['role_id' => $role_id], ['%d']);
            foreach (['portaria.ver','portaria.validar_acesso','alunos.ver'] as $perm) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                     VALUES (%d, %s, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE allowed = 1, updated_at = NOW()",
                    $role_id, $perm
                ));
            }
        }
        update_option('sige_permissions_engine_version', '12.11.9.65', false);
    }
}


if (!function_exists('sige_permissions_migrate_12120_governance_view_permissions')) {
    /**
     * v12.12.0 - permissões explícitas para views que estavam apenas na allowlist.
     * A migração é aditiva e idempotente: não remove permissões personalizadas.
     */
    function sige_permissions_migrate_12120_governance_view_permissions(): void {
        global $wpdb;
        $t = sige_permissions_tables();

        $permissions = [
            'comunicacao.central_ver' => ['modulo'=>'comunicacao','risco'=>'medio','nome'=>'Ver Central de Comunicações','descricao'=>'Aceder à Central de Comunicações unificada de e-mail e WhatsApp.'],
            'comunicacao.whatsapp_ver' => ['modulo'=>'comunicacao','risco'=>'medio','nome'=>'Ver Central WhatsApp','descricao'=>'Aceder à fila operacional de mensagens WhatsApp.'],
            'comunicacao.whatsapp_diagnostico_ver' => ['modulo'=>'comunicacao','risco'=>'alto','nome'=>'Ver Diagnóstico WhatsApp','descricao'=>'Consultar estado técnico, erros e fila do WhatsApp.'],
            'comunicacao.circulares_enviar' => ['modulo'=>'comunicacao','risco'=>'alto','nome'=>'Enviar circulares','descricao'=>'Enviar comunicados oficiais por WhatsApp aos encarregados.'],
            'academico.presencas_ver' => ['modulo'=>'academico','risco'=>'medio','nome'=>'Ver presenças','descricao'=>'Consultar mapas de assiduidade e presenças.'],
            'academico.presencas_gerir' => ['modulo'=>'academico','risco'=>'alto','nome'=>'Gerir presenças','descricao'=>'Registar ou alterar presenças dos alunos.'],
            'financeiro.mobile_payments_gerir' => ['modulo'=>'financeiro','risco'=>'critico','nome'=>'Gerir pagamentos móveis','descricao'=>'Configurar e acompanhar M-Pesa/e-Mola e webhooks de pagamentos móveis.'],
        ];
        foreach ($permissions as $key => $meta) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['permissions']} (permission_key, nome, descricao, modulo, risco, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), modulo = VALUES(modulo), risco = VALUES(risco), ativo = 1, updated_at = NOW()",
                $key, $meta['nome'], $meta['descricao'], $meta['modulo'], $meta['risco']
            ));
        }

        $role_grants = [
            'admin_escola' => array_keys($permissions),
            'admin_ti' => array_keys($permissions),
            'direccao_geral' => array_keys($permissions),
            'director' => array_keys($permissions),
            'dir_pedagogico' => ['academico.presencas_ver','academico.presencas_gerir'],
            'professor' => ['academico.presencas_ver','academico.presencas_gerir'],
            'secretaria_geral' => ['comunicacao.central_ver','comunicacao.whatsapp_ver','comunicacao.whatsapp_diagnostico_ver','comunicacao.circulares_enviar','academico.presencas_ver','academico.presencas_gerir','financeiro.mobile_payments_gerir'],
            'secretaria' => ['comunicacao.central_ver','comunicacao.whatsapp_ver','academico.presencas_ver','academico.presencas_gerir'],
            'secretario' => ['comunicacao.central_ver','comunicacao.whatsapp_ver','comunicacao.whatsapp_diagnostico_ver','academico.presencas_ver','academico.presencas_gerir'],
            'tesoureiro' => ['comunicacao.central_ver','comunicacao.whatsapp_ver','comunicacao.whatsapp_diagnostico_ver'],
        ];

        foreach ($role_grants as $slug => $perms) {
            $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", $slug));
            if ($role_id <= 0) continue;
            foreach ($perms as $perm) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                     VALUES (%d, %s, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE updated_at = updated_at",
                    $role_id, $perm
                ));
            }
        }

        update_option('sige_permissions_engine_version', '12.12.0', false);
    }
}


if (!function_exists('sige_permissions_migrate_12127_tenant_scoped_permissions')) {
    /**
     * v12.12.7 - estruturas tenant-scoped para overrides de perfis e utilizadores.
     * Aditiva e idempotente: preserva os templates globais em sige_role_permissions
     * e cria apenas as tabelas onde a escola pode sobrepor a matriz sem afectar
     * outras escolas da mesma instalação.
     */
    function sige_permissions_migrate_12127_tenant_scoped_permissions(): void {
        if (function_exists('sige_permissions_install')) {
            sige_permissions_install();
        }
        update_option('sige_permissions_engine_version', '12.12.7', false);
    }
}

if (!function_exists('sige_permissions_migrate_121223_privacidade')) {
    /**
     * v12.12.23 - Fase 8 Incr 1: permissao de governanca de dados pessoais.
     * Aditiva e idempotente: regista a permissao e concede-a aos perfis de
     * administracao e direccao, sem afectar as demais.
     */
    function sige_permissions_migrate_121223_privacidade(): void {
        global $wpdb;
        $t = sige_permissions_tables();

        $permissions = [
            'privacidade.inventario_ver' => ['modulo'=>'sistema','risco'=>'medio','nome'=>'Ver inventário de dados pessoais','descricao'=>'Consultar o mapa de dados pessoais (PII) por tabela e campo, a sua finalidade, base legal e agregados por escola. Só leitura.'],
        ];
        foreach ($permissions as $key => $meta) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['permissions']} (permission_key, nome, descricao, modulo, risco, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), modulo = VALUES(modulo), risco = VALUES(risco), ativo = 1, updated_at = NOW()",
                $key, $meta['nome'], $meta['descricao'], $meta['modulo'], $meta['risco']
            ));
        }

        $role_grants = [
            'admin_escola'   => array_keys($permissions),
            'admin_ti'       => array_keys($permissions),
            'direccao_geral' => array_keys($permissions),
            'director'       => array_keys($permissions),
        ];
        foreach ($role_grants as $slug => $perms) {
            $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", $slug));
            if ($role_id <= 0) continue;
            foreach ($perms as $perm) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                     VALUES (%d, %s, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE updated_at = updated_at",
                    $role_id, $perm
                ));
            }
        }

        update_option('sige_permissions_engine_version', '12.12.23', false);
    }
}

if (!function_exists('sige_permissions_migrate_121224_privacidade_acesso')) {
    /**
     * v12.12.24 - Fase 8 Incr 2: permissao de exportacao do dossie de dados
     * pessoais (direito de acesso e portabilidade). Aditiva e idempotente:
     * concede so a administracao e direccao.
     */
    function sige_permissions_migrate_121224_privacidade_acesso(): void {
        global $wpdb;
        $t = sige_permissions_tables();

        $permissions = [
            'privacidade.acesso_exportar' => ['modulo'=>'sistema','risco'=>'alto','nome'=>'Exportar dossiê de dados pessoais','descricao'=>'Reunir e exportar os dados pessoais de um aluno (direito de acesso e portabilidade). Restrito à administração e direcção.'],
        ];
        foreach ($permissions as $key => $meta) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['permissions']} (permission_key, nome, descricao, modulo, risco, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), modulo = VALUES(modulo), risco = VALUES(risco), ativo = 1, updated_at = NOW()",
                $key, $meta['nome'], $meta['descricao'], $meta['modulo'], $meta['risco']
            ));
        }

        $role_grants = [
            'admin_escola'   => array_keys($permissions),
            'admin_ti'       => array_keys($permissions),
            'direccao_geral' => array_keys($permissions),
            'director'       => array_keys($permissions),
        ];
        foreach ($role_grants as $slug => $perms) {
            $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", $slug));
            if ($role_id <= 0) continue;
            foreach ($perms as $perm) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                     VALUES (%d, %s, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE updated_at = updated_at",
                    $role_id, $perm
                ));
            }
        }

        update_option('sige_permissions_engine_version', '12.12.24', false);
    }
}

if (!function_exists('sige_permissions_migrate_121225_privacidade_apagamento')) {
    /**
     * v12.12.25 - Fase 8 Incr 3: permissao de apagamento por anonimizacao
     * (direito ao apagamento). Risco critico. Aditiva e idempotente: concede so
     * a administracao e direccao.
     */
    function sige_permissions_migrate_121225_privacidade_apagamento(): void {
        global $wpdb;
        $t = sige_permissions_tables();

        $permissions = [
            'privacidade.apagamento_executar' => ['modulo'=>'sistema','risco'=>'critico','nome'=>'Apagar dados pessoais (anonimização)','descricao'=>'Anonimizar de forma permanente os dados pessoais identificáveis de um aluno, preservando número de processo e valores financeiros e académicos. Operação irreversível, restrita à administração e direcção.'],
        ];
        foreach ($permissions as $key => $meta) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['permissions']} (permission_key, nome, descricao, modulo, risco, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), modulo = VALUES(modulo), risco = VALUES(risco), ativo = 1, updated_at = NOW()",
                $key, $meta['nome'], $meta['descricao'], $meta['modulo'], $meta['risco']
            ));
        }

        $role_grants = [
            'admin_escola'   => array_keys($permissions),
            'admin_ti'       => array_keys($permissions),
            'direccao_geral' => array_keys($permissions),
            'director'       => array_keys($permissions),
        ];
        foreach ($role_grants as $slug => $perms) {
            $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", $slug));
            if ($role_id <= 0) continue;
            foreach ($perms as $perm) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                     VALUES (%d, %s, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE updated_at = updated_at",
                    $role_id, $perm
                ));
            }
        }

        update_option('sige_permissions_engine_version', '12.12.25', false);
    }
}

if (!function_exists('sige_permissions_migrate_121227_privacidade_retencao')) {
    /**
     * v12.12.27 - Fase 8 Incr 4: permissao de leitura da retencao e expurgo.
     * Risco medio, so leitura. Aditiva e idempotente: concede so a administracao
     * e direccao.
     */
    function sige_permissions_migrate_121227_privacidade_retencao(): void {
        global $wpdb;
        $t = sige_permissions_tables();

        $permissions = [
            'privacidade.retencao_ver' => ['modulo'=>'sistema','risco'=>'medio','nome'=>'Ver retenção de dados','descricao'=>'Ver a política de retenção e quantos registos já excederam o prazo, de forma agregada por escola. Só leitura; não elimina dados. Restrito à administração e direcção.'],
        ];
        foreach ($permissions as $key => $meta) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['permissions']} (permission_key, nome, descricao, modulo, risco, ativo, created_at, updated_at)
                 VALUES (%s, %s, %s, %s, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE nome = VALUES(nome), descricao = VALUES(descricao), modulo = VALUES(modulo), risco = VALUES(risco), ativo = 1, updated_at = NOW()",
                $key, $meta['nome'], $meta['descricao'], $meta['modulo'], $meta['risco']
            ));
        }

        $role_grants = [
            'admin_escola'   => array_keys($permissions),
            'admin_ti'       => array_keys($permissions),
            'direccao_geral' => array_keys($permissions),
            'director'       => array_keys($permissions),
        ];
        foreach ($role_grants as $slug => $perms) {
            $role_id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", $slug));
            if ($role_id <= 0) continue;
            foreach ($perms as $perm) {
                $wpdb->query($wpdb->prepare(
                    "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                     VALUES (%d, %s, 1, NOW(), NOW())
                     ON DUPLICATE KEY UPDATE updated_at = updated_at",
                    $role_id, $perm
                ));
            }
        }

        update_option('sige_permissions_engine_version', '12.12.27', false);
    }
}

if (!function_exists('sige_permissions_migrate_121228_admin_ti_gestao')) {
    /**
     * v12.12.28 - Fase 9 Incr 1: reconciliacao. A concessao de
     * usuarios.gerir_permissoes ao perfil admin_ti estava declarada nos defaults
     * mas podia faltar na base de dados em instalacoes antigas (o menu Perfis e
     * Permissoes nao aparecia ao admin TI). Esta migracao garante a linha em
     * role_permissions. Aditiva e idempotente.
     */
    function sige_permissions_migrate_121228_admin_ti_gestao(): void {
        global $wpdb;
        $t = sige_permissions_tables();
        $perm = 'usuarios.gerir_permissoes';
        $role_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", 'admin_ti'));
        if ($role_id > 0) {
            $wpdb->query($wpdb->prepare(
                "INSERT INTO {$t['role_permissions']} (role_id, permission_key, allowed, created_at, updated_at)
                 VALUES (%d, %s, 1, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE allowed = 1, updated_at = NOW()",
                $role_id, $perm
            ));
        }
        update_option('sige_permissions_engine_version', '12.12.28', false);
    }
}

if (!function_exists('sige_permissions_maybe_install')) {
    function sige_permissions_maybe_install(): void {
        $installed = (string)get_option('sige_permissions_engine_version', '');
        if (version_compare($installed ?: '0.0.0', '12.11.5', '<')) {
            sige_permissions_install();
            if (function_exists('sige_permissions_migrate_12115_dashboard_defaults')) {
                sige_permissions_migrate_12115_dashboard_defaults();
            }
            $installed = '12.11.5';
        }
        if (version_compare($installed ?: '0.0.0', '12.11.9.65', '<') && function_exists('sige_permissions_migrate_1211965_guarda_portaria')) {
            sige_permissions_migrate_1211965_guarda_portaria();
            $installed = '12.11.9.65';
        }
        if (version_compare($installed ?: '0.0.0', '12.12.0', '<') && function_exists('sige_permissions_migrate_12120_governance_view_permissions')) {
            sige_permissions_migrate_12120_governance_view_permissions();
            $installed = '12.12.0';
        }
        if (version_compare($installed ?: '0.0.0', '12.12.7', '<') && function_exists('sige_permissions_migrate_12127_tenant_scoped_permissions')) {
            sige_permissions_migrate_12127_tenant_scoped_permissions();
            $installed = '12.12.7';
        }
        if (version_compare($installed ?: '0.0.0', '12.12.23', '<') && function_exists('sige_permissions_migrate_121223_privacidade')) {
            sige_permissions_migrate_121223_privacidade();
            $installed = '12.12.23';
        }
        if (version_compare($installed ?: '0.0.0', '12.12.24', '<') && function_exists('sige_permissions_migrate_121224_privacidade_acesso')) {
            sige_permissions_migrate_121224_privacidade_acesso();
            $installed = '12.12.24';
        }
        if (version_compare($installed ?: '0.0.0', '12.12.25', '<') && function_exists('sige_permissions_migrate_121225_privacidade_apagamento')) {
            sige_permissions_migrate_121225_privacidade_apagamento();
            $installed = '12.12.25';
        }
        if (version_compare($installed ?: '0.0.0', '12.12.27', '<') && function_exists('sige_permissions_migrate_121227_privacidade_retencao')) {
            sige_permissions_migrate_121227_privacidade_retencao();
            $installed = '12.12.27';
        }
        if (version_compare($installed ?: '0.0.0', '12.12.28', '<') && function_exists('sige_permissions_migrate_121228_admin_ti_gestao')) {
            sige_permissions_migrate_121228_admin_ti_gestao();
            $installed = '12.12.28';
        }
    }
}
add_action('admin_init', 'sige_permissions_maybe_install', 5);

if (!function_exists('sige_permission_feature_for_module')) {
    /**
     * Resolve a feature que deve governar uma permissão.
     *
     * v12.9.8.8 - Correcção crítica para planos parciais.
     * Antes, permissões do módulo interno `secretaria` passavam por
     * sige_feature('secretaria'), e o alias legado convertia `secretaria` em
     * `academico`. Resultado: em plano Tesouraria, o utilizador podia ter
     * `alunos.ver` / `academico.turmas_ver` na matriz, mas a feature académica
     * desligada anulava o menu e a rota.
     *
     * A partir daqui usamos o mapa oficial módulo→feature. Assim:
     * - secretaria/alunos/matrículas => cadastro_base;
     * - turmas_ver, quando usada como consulta base, também pode depender de
     *   cadastro_base;
     * - permissões académicas sensíveis continuam dependentes de academico.
     */
    function sige_permission_feature_for_module(string $permission, string $modulo): string {
        $permission = sige_permission_normalize($permission);
        $modulo = sanitize_key($modulo);

        $permission_feature_overrides = [
            'alunos.ver' => 'cadastro_base',
            'alunos.contas_ver' => 'cadastro_base',
            'matriculas.ver' => 'cadastro_base',
            'academico.turmas_ver' => 'cadastro_base',
        ];
        if (isset($permission_feature_overrides[$permission])) {
            return $permission_feature_overrides[$permission];
        }

        if (function_exists('sige_feature_registry_module_to_feature_map')) {
            $map = sige_feature_registry_module_to_feature_map();
            if (isset($map[$modulo])) return (string)$map[$modulo];
        }

        $fallback = [
            'secretaria' => 'cadastro_base',
            'alunos' => 'cadastro_base',
            'matriculas' => 'cadastro_base',
            'usuarios' => 'configuracoes',
            'sistema' => 'core_status',
            'portaria' => 'portaria',
        ];
        return $fallback[$modulo] ?? $modulo;
    }
}

if (!function_exists('sige_permission_module_active')) {
    function sige_permission_module_active(string $permission): bool {
        $registry = sige_permissions_registry();
        $modulo = $registry[$permission]['modulo'] ?? '';
        if ($modulo === '' || in_array($modulo, ['core','usuarios','configuracoes'], true)) return true;
        if (function_exists('sige_feature')) {
            $feature = function_exists('sige_permission_feature_for_module')
                ? sige_permission_feature_for_module($permission, (string)$modulo)
                : (string)$modulo;
            return (bool)sige_feature($feature, true);
        }
        return true;
    }
}
if (!function_exists('sige_permissions_user_legacy_allows')) {
    function sige_permissions_user_legacy_allows(string $permission, int $user_id): bool {
        // Compatibilidade com capabilities antigas já existentes no plugin - só admin WP real faz bypass técnico.
        if (function_exists('sige_permissions_is_super_admin') ? sige_permissions_is_super_admin($user_id) : user_can($user_id, 'manage_options')) return true;

        // (v12.9.8.8) Bypass para roles técnicas admin_ti / admin_escola.
        //
        // O design v12.9.6 (page-guard.php) é: as roles admin_ti e admin_escola
        // recebem $all_permissions no seed → autoridade vem da matriz SIGE.
        // PROBLEMA: este fluxo só funciona se o utilizador tem registo em
        // wp_sige_user_roles. Em produção (Malisa, Casa Colorida) muitos
        // utilizadores TI nunca foram sincronizados para essa tabela e o
        // sige_can cai aqui - mas este map só conhece roles operacionais
        // (sige_director, sige_secretario, etc.), não as técnicas.
        // Resultado: TI/Admin Escola ficavam sem ver Cadastro Escolar nem
        // qualquer outra secção que dependa de matriz.
        //
        // Esta cláusula repõe o "$all_permissions" prometido pelo seed para
        // estes perfis quando a matriz não está activa para o utilizador.
        // Quando a matriz ESTÁ activa (sige_user_roles populado), o fluxo
        // não chega aqui - sige_can usa o caminho da role e permite ao
        // administrador remover permissões explicitamente, conforme v12.9.6.
        if (user_can($user_id, 'sige_admin_ti') || user_can($user_id, 'sige_admin_escola')) {
            return true;
        }

        $legacy_map = [
            'financeiro.estornar' => ['sige_director','sige_financeiro','sige_secretario'],
            'financeiro.bloquear_mes' => ['sige_director','sige_financeiro'],
            'financeiro.desbloquear_mes' => ['sige_director','sige_financeiro'],
            'financeiro.pagar' => ['sige_director','sige_financeiro','sige_secretario'],
            'financeiro.ver' => ['sige_director','sige_financeiro','sige_secretario'],
            'financeiro.ver_relatorios' => ['sige_director','sige_financeiro'],
            'financeiro.imprimir_recibo' => ['sige_director','sige_financeiro','sige_secretario'],
            'academico.ver' => ['sige_director','sige_secretario','sige_professor'],
            'academico.lancar_notas' => ['sige_professor','sige_director'],
            'academico.editar_notas' => ['sige_director'],
            'academico.fechar_ano' => ['sige_director'],
            'alunos.ver' => ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda'],
            'alunos.criar' => ['sige_director','sige_secretario'],
            'alunos.editar' => ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente'],
            'alunos.apagar' => ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente'],
            'documentos.emitir' => ['sige_director','sige_secretario'],
            'documentos.emitir_finais' => ['sige_director','sige_secretario'],
            'configuracoes.editar' => ['manage_options'],
            'usuarios.gerir_permissoes' => ['manage_options'],
            'portaria.ver' => ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda'],
            'portaria.validar_acesso' => ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente','sige_recepcao','sige_guarda'],
            'comunicacao.central_ver' => ['sige_director','sige_admin','sige_admin_ti','sige_financeiro','sige_secretario','sige_secretaria_geral'],
            'comunicacao.whatsapp_ver' => ['sige_director','sige_admin','sige_admin_ti','sige_financeiro','sige_secretario','sige_secretaria_geral'],
            'comunicacao.whatsapp_diagnostico_ver' => ['sige_director','sige_admin','sige_admin_ti','sige_financeiro','sige_secretario'],
            'comunicacao.circulares_enviar' => ['sige_director','sige_secretaria_geral'],
            'academico.presencas_ver' => ['sige_director','sige_secretaria_geral','sige_secretario','sige_professor'],
            'academico.presencas_gerir' => ['sige_director','sige_secretaria_geral','sige_secretario','sige_professor'],
            'financeiro.mobile_payments_gerir' => ['sige_director','sige_secretaria_geral'],
        ];
        foreach (($legacy_map[$permission] ?? []) as $cap) {
            if (user_can($user_id, $cap)) return true;
        }
        return false;
    }
}

if (!function_exists('sige_permission_audit')) {
    function sige_permission_audit(int $user_id, string $permission, bool $allowed, string $source, $context = null): void {
        // Auditar sempre permissões críticas; para as restantes, só em modo debug.
        $critical = in_array($permission, [
            'financeiro.estornar','financeiro.bloquear_mes','financeiro.desbloquear_mes','financeiro.apagar_pagamento',
            'configuracoes.editar','usuarios.gerir_permissoes','academico.fechar_ano','financeiro.mobile_payments_gerir','comunicacao.circulares_enviar',
            'privacidade.acesso_exportar','privacidade.apagamento_executar'
        ], true);
        if (!$critical && !(defined('WP_DEBUG') && WP_DEBUG)) return;

        global $wpdb;
        $t = sige_permissions_tables();
        $wpdb->insert($t['audit'], [
            'user_id' => $user_id ?: null,
            'permission_key' => $permission,
            'allowed' => $allowed ? 1 : 0,
            'source' => $source,
            'contexto' => is_scalar($context) || $context === null ? (string)$context : wp_json_encode($context),
            'ip' => isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : null,
            'created_at' => current_time('mysql'),
        ], ['%d','%s','%d','%s','%s','%s','%s']);
    }
}


if (!function_exists('sige_permissions_is_super_admin')) {
    /**
     * Bypass técnico obrigatório para proprietários/administradores reais da instância.
     *
     * v12.9.6 - Bypass restringido: APENAS administradores WordPress reais
     * (`manage_options`/`is_super_admin`). Os perfis técnicos `admin_ti` /
     * `admin_escola` deixam de fazer bypass automático: a sua autoridade passa
     * a vir da matriz SIGE (onde já recebem `$all_permissions` no seed).
     *
     * Justificação: o objectivo é tornar a matriz *efectiva*. Se a role `admin_ti`
     * tiver uma permissão removida explicitamente pelo administrador, essa
     * remoção deve aplicar-se. O administrador WordPress real (`manage_options`)
     * permanece como rede de segurança e pode sempre recuperar uma matriz mal
     * configurada via permissions-ui.php.
     */
    function sige_permissions_is_super_admin(?int $user_id = null): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) return false;
        if (function_exists('sige_is_real_wp_admin_user')) return sige_is_real_wp_admin_user($user_id);
        $user = function_exists('get_userdata') ? get_userdata($user_id) : null;
        $roles = ($user && !empty($user->roles)) ? array_map('strval', (array)$user->roles) : [];
        if (in_array('administrator', $roles, true)) return true;
        if (in_array('super_admin', $roles, true)) return true;
        return false;
    }
}



if (!function_exists('sige_rh_user_is_active_for_school')) {
    /**
     * Estado efectivo do colaborador RH na escola corrente.
     *
     * v12.11.9.6 - Desactivação real: quando status_ativo=0 em sige_professores
     * ou no meta sige_staff_status_ativo, o utilizador deixa de obter permissões SIGE
     * e é bloqueado no acesso administrativo. Administradores WordPress reais continuam
     * como rede de segurança para recuperação operacional.
     */
    function sige_rh_user_is_active_for_school(?int $user_id = null, ?int $escola_id = null): bool {
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) return false;

        // v12.11.9.7 - bypass estrito apenas para administradores WordPress reais.
        // Não usar user_can('manage_options') aqui: algumas roles legadas SIGE podem
        // herdar essa capability e, se estiverem desactivadas em RH, não devem
        // contornar a desactivação efectiva.
        if (function_exists('sige_permissions_is_super_admin') && sige_permissions_is_super_admin($user_id)) {
            return true;
        }

        if ($escola_id === null || $escola_id <= 0) {
            $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
            if ($escola_id <= 0) {
                $escola_id = (int)get_user_meta($user_id, 'sige_escola_id', true);
            }
        }
        if ($escola_id <= 0) {
            if (function_exists('sige_multitenancy_strict_enabled') && sige_multitenancy_strict_enabled()) return false;
            $escola_id = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;
        }

        // v12.11.9.8 - Remover colaborador é soft delete: o WP user fica preservado
        // para histórico, mas nunca deve autenticar nem receber permissões operacionais.
        if ((string)get_user_meta($user_id, 'sige_staff_removed_at', true) !== '') {
            return false;
        }

        $meta_status = get_user_meta($user_id, 'sige_staff_status_ativo', true);
        if ($meta_status !== '' && (int)$meta_status === 0) {
            return false;
        }

        $table = $wpdb->prefix . 'sige_professores';
        static $table_exists_cache = [];
        if (!array_key_exists($table, $table_exists_cache)) {
            $table_exists_cache[$table] = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table);
        }
        if (!$table_exists_cache[$table]) {
            return $meta_status === '' ? true : ((int)$meta_status === 1);
        }

        $prof_id = (int)get_user_meta($user_id, 'sige_professor_id', true);
        $status = null;
        if ($prof_id > 0) {
            $status = $wpdb->get_var($wpdb->prepare(
                "SELECT status_ativo FROM {$table} WHERE id = %d AND escola_id = %d LIMIT 1",
                $prof_id, $escola_id
            ));
        }

        if ($status === null) {
            $user = get_userdata($user_id);
            if ($user && !empty($user->user_email)) {
                $status = $wpdb->get_var($wpdb->prepare(
                    "SELECT status_ativo FROM {$table} WHERE email = %s AND escola_id = %d ORDER BY id DESC LIMIT 1",
                    $user->user_email, $escola_id
                ));
            }
        }

        if ($status === null) {
            return $meta_status === '' ? true : ((int)$meta_status === 1);
        }
        return ((int)$status) === 1;
    }
}

if (!function_exists('sige_rh_block_inactive_staff_authenticate')) {
    function sige_rh_block_inactive_staff_authenticate($user, $username, $password) {
        if ($user instanceof WP_User && function_exists('sige_rh_user_is_active_for_school')) {
            if (!sige_rh_user_is_active_for_school((int)$user->ID)) {
                return new WP_Error('sige_staff_inactive', __('Esta conta foi desactivada pela escola. Contacte a administração.', 'sige-softgenial'));
            }
        }
        return $user;
    }
}
add_filter('authenticate', 'sige_rh_block_inactive_staff_authenticate', 35, 3);

if (!function_exists('sige_rh_enforce_inactive_staff_admin_access')) {
    function sige_rh_enforce_inactive_staff_admin_access(): void {
        if (!is_user_logged_in() || !function_exists('sige_rh_user_is_active_for_school')) return;
        $user_id = get_current_user_id();
        if ($user_id <= 0 || sige_rh_user_is_active_for_school($user_id)) return;

        if (function_exists('wp_doing_ajax') && wp_doing_ajax()) {
            wp_send_json_error('Conta desactivada. Contacte a administração da escola.', 403);
        }

        if (class_exists('WP_Session_Tokens')) {
            WP_Session_Tokens::get_instance($user_id)->destroy_all();
        }
        wp_logout();
        wp_safe_redirect(add_query_arg('sige_inactive', '1', wp_login_url()));
        exit;
    }
}
add_action('admin_init', 'sige_rh_enforce_inactive_staff_admin_access', 0);

if (!function_exists('sige_permissions_get_active_role')) {
    /**
     * Fonte principal de verdade do perfil operacional SIGE.
     * Retorna o perfil SIGE activo do utilizador na escola corrente, ou null.
     */
    function sige_permissions_get_active_role(?int $user_id = null, ?int $escola_id = null) {
        global $wpdb;
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0 || !function_exists('sige_permissions_tables')) return null;
        $escola_id = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($escola_id <= 0) {
            if (function_exists('sige_multitenancy_strict_enabled') && sige_multitenancy_strict_enabled()) return null;
            $escola_id = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;
        }
        $t = sige_permissions_tables();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.*
               FROM {$t['user_roles']} ur
               INNER JOIN {$t['roles']} r ON r.id = ur.role_id
              WHERE ur.user_id = %d
                AND ur.escola_id = %d
                AND ur.ativo = 1
                AND r.ativo = 1
              ORDER BY ur.updated_at DESC, ur.id DESC
              LIMIT 1",
            $user_id, $escola_id
        ));
    }
}

if (!function_exists('sige_permissions_user_has_active_role')) {
    function sige_permissions_user_has_active_role(?int $user_id = null, ?int $escola_id = null): bool {
        return (bool)sige_permissions_get_active_role($user_id, $escola_id);
    }
}

if (!function_exists('sige_permissions_role_to_wp_role')) {
    function sige_permissions_role_to_wp_role(string $slug): string {
        $slug = sanitize_key($slug);
        $map = [
            'admin_escola'      => 'sige_admin_ti',
            'admin_ti'          => 'sige_admin_ti',
            'direccao_geral'    => 'sige_director',
            'director'          => 'sige_director',
            'dir_pedagogico'    => 'sige_pedagogico',
            'gestor_rh'         => 'sige_gestor_rh',
            'professor'         => 'sige_professor',
            'educador'          => 'sige_educador',
            'secretaria_geral'  => 'sige_secretaria_geral',
            'secretaria'        => 'sige_secretaria_geral',
            'secretario'        => 'sige_secretario',
            'tesoureiro'        => 'sige_financeiro',
            'assistente'        => 'sige_assistente',
            'recepcao'          => 'sige_recepcao',
            'guarda'            => 'sige_guarda',
            'motorista'         => 'sige_motorista',
            'limpeza'           => 'sige_limpeza',
            'encarregado'       => 'sige_encarregado',
        ];
        return $map[$slug] ?? '';
    }
}

if (!function_exists('sige_permissions_wp_role_to_sige_role')) {
    function sige_permissions_wp_role_to_sige_role(string $wp_role): string {
        $wp_role = sanitize_key($wp_role);
        $map = [
            'sige_admin_ti'          => 'admin_ti',
            'sige_director'          => 'direccao_geral',
            'sige_pedagogico'        => 'dir_pedagogico',
            'sige_gestor_rh'         => 'gestor_rh',
            'sige_professor'         => 'professor',
            'sige_educador'          => 'educador',
            'sige_secretaria_geral'  => 'secretaria_geral',
            'sige_secretario'        => 'secretario',
            'sige_assistente'        => 'assistente',
            'sige_recepcao'          => 'recepcao',
            'sige_guarda'            => 'guarda',
            'sige_financeiro'        => 'tesoureiro',
            'sige_motorista'         => 'motorista',
            'sige_limpeza'           => 'limpeza',
            'sige_encarregado'       => 'encarregado',
        ];
        return $map[$wp_role] ?? '';
    }
}

if (!function_exists('sige_permissions_sync_user_role')) {
    /**
     * Atribui um perfil SIGE activo ao utilizador e sincroniza o WP role legado.
     * Idempotente: não altera administradores WordPress com manage_options.
     */
    function sige_permissions_sync_user_role(int $user_id, string $sige_role_slug, ?int $escola_id = null, bool $sync_wp_role = true): bool {
        global $wpdb;
        if ($user_id <= 0 || !function_exists('sige_permissions_tables')) return false;
        $sige_role_slug = sanitize_key($sige_role_slug);
        if ($sige_role_slug === '') return false;
        $escola_id = $escola_id ?: (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($escola_id <= 0) {
            if (function_exists('sige_multitenancy_strict_enabled') && sige_multitenancy_strict_enabled()) return false;
            $escola_id = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;
        }
        $t = sige_permissions_tables();
        $role = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$t['roles']} WHERE slug = %s AND ativo = 1 LIMIT 1", $sige_role_slug));
        if (!$role) return false;

        // v12.14.2 - guarda central de integridade: perfis privilegiados nao podem
        // ser atribuídos/despromovidos por fluxos não-autorizados nem por helpers internos.
        if (function_exists('sige_user_integrity_can_change_sige_role')
            && !sige_user_integrity_can_change_sige_role($user_id, $sige_role_slug, $escola_id)) {
            return false;
        }

        $wpdb->update($t['user_roles'], ['ativo' => 0, 'updated_at' => current_time('mysql')], ['user_id' => $user_id, 'escola_id' => $escola_id], ['%d','%s'], ['%d','%d']);
        $existing_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$t['user_roles']} WHERE user_id = %d AND role_id = %d AND escola_id = %d LIMIT 1",
            $user_id, (int)$role->id, $escola_id
        ));
        if ($existing_id > 0) {
            $wpdb->update($t['user_roles'], ['ativo' => 1, 'updated_at' => current_time('mysql')], ['id' => $existing_id], ['%d','%s'], ['%d']);
        } else {
            $wpdb->insert($t['user_roles'], [
                'user_id' => $user_id,
                'role_id' => (int)$role->id,
                'escola_id' => $escola_id,
                'ativo' => 1,
                'created_at' => current_time('mysql'),
                'updated_at' => current_time('mysql'),
            ], ['%d','%d','%d','%d','%s','%s']);
        }

        // Sobreposicao puramente aditiva (Fase 9 Incr 5): a atribuicao NAO altera o
        // papel WordPress. As capacidades do papel sige_* mapeado sao concedidas em
        // tempo de execucao pelo filtro user_has_cap (sige_permissions_grant_caps_filter),
        // que preserva o papel WordPress original do utilizador. O parametro
        // $sync_wp_role mantem-se por compatibilidade de assinatura, mas ja nao
        // substitui o papel.
        unset($sync_wp_role);
        if (function_exists('sige_user_integrity_mark_sige_role_change')) {
            sige_user_integrity_mark_sige_role_change($user_id, $sige_role_slug, $escola_id, 'permissions_sync');
        }
        return true;
    }
}
if (!function_exists('sige_permissions_backup_wp_roles')) {
    /**
     * Preserva o papel WordPress original do utilizador antes de o substituir por
     * um papel sige_*. Idempotente: so guarda uma vez (captura o verdadeiro
     * original). Papeis sige_* sao excluidos da copia; se nao sobrar nada (estado
     * legado ja substituido), a copia assume o papel por omissao do WordPress.
     * Sobreposicao reversivel: torna a substituicao do papel nao destrutiva.
     */
    function sige_permissions_backup_wp_roles(int $user_id): void {
        if ($user_id <= 0) return;
        $existing = get_user_meta($user_id, '_sige_wp_roles_backup', true);
        if (!empty($existing)) return; // ja preservado; nao sobrescrever o original
        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) return;
        $roles = array_values(array_filter((array) $user->roles, static function ($r) {
            return strpos((string) $r, 'sige_') !== 0;
        }));
        if (empty($roles)) {
            $default = function_exists('get_option') ? (string) get_option('default_role', 'subscriber') : 'subscriber';
            $roles = [$default !== '' ? $default : 'subscriber'];
        }
        update_user_meta($user_id, '_sige_wp_roles_backup', $roles);
    }
}

if (!function_exists('sige_permissions_restore_wp_roles')) {
    /**
     * Repoe os papeis WordPress originais a partir da copia e limpa a copia. Se
     * nao houver copia, so intervem quando o utilizador esta num papel sige_* de
     * staff (estado legado a limpar), repondo o papel por omissao; se ja estiver
     * num papel real (fluxo aditivo, nunca substituido), nao toca em nada. O
     * primeiro papel substitui, os restantes acrescentam.
     */
    function sige_permissions_restore_wp_roles(int $user_id): bool {
        if ($user_id <= 0) return false;
        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) return false;
        $backup = get_user_meta($user_id, '_sige_wp_roles_backup', true);
        $roles = is_array($backup) ? array_values(array_filter($backup, 'is_string')) : [];
        if (!empty($roles)) {
            $first = array_shift($roles);
            $user->set_role((string) $first);
            foreach ($roles as $r) { $user->add_role((string) $r); }
            delete_user_meta($user_id, '_sige_wp_roles_backup');
            return true;
        }
        // Sem copia: so repor o papel por omissao se o utilizador estiver num papel
        // sige_* de staff (legado). Caso contrario, nao tocar (ja esta num papel real).
        $on_staff_sige = false;
        foreach ((array) $user->roles as $r) {
            if (function_exists('sige_permissions_is_staff_wp_role') && sige_permissions_is_staff_wp_role((string) $r)) { $on_staff_sige = true; break; }
        }
        if (!$on_staff_sige) return false;
        $default = function_exists('get_option') ? (string) get_option('default_role', 'subscriber') : 'subscriber';
        $user->set_role($default !== '' ? $default : 'subscriber');
        return true;
    }
}

if (!function_exists('sige_permissions_perm_gestao')) {
    /** Permissao que confere a gestao de permissoes (fonte unica). */
    function sige_permissions_perm_gestao(): string {
        return 'usuarios.gerir_permissoes';
    }
}

if (!function_exists('sige_permissions_principal_protegido')) {
    /**
     * Conta protegida: administrador WordPress real. Estas contas NAO sao
     * geriveis pelo modulo de permissoes (nem atribuir nem remover perfil),
     * para que nunca possam ser trancadas ou despromovidas a partir dali. A
     * gestao destas contas faz-se pela gestao de utilizadores do WordPress.
     */
    function sige_permissions_principal_protegido(?int $user_id = null): bool {
        $user_id = $user_id ?: get_current_user_id();
        if ($user_id <= 0) return false;
        if (function_exists('sige_is_real_wp_admin_user')) {
            return sige_is_real_wp_admin_user((int) $user_id);
        }
        return user_can((int) $user_id, 'manage_options');
    }
}

if (!function_exists('sige_permissions_role_concede_gestao')) {
    /**
     * O perfil confere a gestao de permissoes? Verifica a declaracao (registry
     * por slug) E a base de dados (role_permissions). Fail-safe: se qualquer das
     * fontes conceder, e tratado como perfil de gestao (mais restritivo para a
     * regra anti-escalada).
     *
     * @param int|string $role role_id (int) ou slug (string).
     */
    function sige_permissions_role_concede_gestao($role): bool {
        global $wpdb;
        $perm = sige_permissions_perm_gestao();
        $t = sige_permissions_tables();
        $slug = '';
        $role_id = 0;
        if (is_int($role) || ctype_digit((string) $role)) {
            $role_id = (int) $role;
            $slug = (string) $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$t['roles']} WHERE id = %d LIMIT 1", $role_id));
        } else {
            $slug = sanitize_key((string) $role);
            $role_id = (int) $wpdb->get_var($wpdb->prepare("SELECT id FROM {$t['roles']} WHERE slug = %s LIMIT 1", $slug));
        }
        // Declaracao (registry).
        if ($slug !== '' && function_exists('sige_permissions_default_roles')) {
            $defaults = sige_permissions_default_roles();
            if (isset($defaults[$slug]['permissions']) && in_array($perm, (array) $defaults[$slug]['permissions'], true)) {
                return true;
            }
        }
        // Base de dados (role_permissions).
        if ($role_id > 0) {
            $allowed = $wpdb->get_var($wpdb->prepare(
                "SELECT allowed FROM {$t['role_permissions']} WHERE role_id = %d AND permission_key = %s LIMIT 1",
                $role_id, $perm
            ));
            if ($allowed !== null && (int) $allowed === 1) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('sige_permissions_user_tem_gestao_sige')) {
    /**
     * O utilizador tem gestao de permissoes pelo seu perfil SIGE activo nesta
     * escola (independentemente de bypass de administrador WP). Usado para a
     * auto-proteccao: quem se sustenta so no perfil SIGE nao se pode despromover.
     */
    function sige_permissions_user_tem_gestao_sige(int $user_id, int $escola_id): bool {
        global $wpdb;
        if ($user_id <= 0 || $escola_id <= 0) return false;
        $t = sige_permissions_tables();
        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT r.id, r.slug FROM {$t['user_roles']} ur
             INNER JOIN {$t['roles']} r ON r.id = ur.role_id
             WHERE ur.user_id = %d AND ur.escola_id = %d AND ur.ativo = 1 LIMIT 1",
            $user_id, $escola_id
        ));
        if (!$row) return false;
        return sige_permissions_role_concede_gestao((int) $row->id);
    }
}

if (!function_exists('sige_permissions_contar_gestores_sige')) {
    /**
     * Numero de utilizadores com perfil SIGE activo de gestao de permissoes nesta
     * escola, opcionalmente excluindo um utilizador. Usado para nao deixar a
     * escola sem nenhum gestor.
     */
    function sige_permissions_contar_gestores_sige(int $escola_id, int $excluir_user_id = 0): int {
        global $wpdb;
        if ($escola_id <= 0) return 0;
        $t = sige_permissions_tables();
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT ur.user_id, r.id AS role_id FROM {$t['user_roles']} ur
             INNER JOIN {$t['roles']} r ON r.id = ur.role_id
             WHERE ur.escola_id = %d AND ur.ativo = 1",
            $escola_id
        ));
        if (!is_array($rows)) return 0;
        $n = 0;
        foreach ($rows as $r) {
            if ((int) $r->user_id === $excluir_user_id) continue;
            if (sige_permissions_role_concede_gestao((int) $r->role_id)) $n++;
        }
        return $n;
    }
}

if (!function_exists('sige_permissions_niveis_base')) {
    /**
     * Mapa base de niveis em codigo (valor por omissao). Os tres gestores no topo;
     * os restantes por responsabilidade. Os desvios editados pelo dono sao
     * guardados a parte e sobrepostos a este mapa (ver sige_permissions_role_niveis).
     */
    function sige_permissions_niveis_base(): array {
        return [
            'direccao_geral'   => 90,
            'admin_escola'     => 90,
            'admin_ti'         => 80,
            'director'         => 70,
            'dir_pedagogico'   => 70,
            'gestor_rh'        => 70,
            'secretaria_geral' => 60,
            'secretaria'       => 50,
            'secretario'       => 50,
            'tesoureiro'       => 50,
            'recepcao'         => 40,
            'assistente'       => 40,
            'professor'        => 30,
            'educador'         => 30,
            'guarda'           => 10,
            'motorista'        => 10,
            'limpeza'          => 10,
            'encarregado'      => 0,
        ];
    }
}

if (!function_exists('sige_permissions_niveis_overrides')) {
    /** Desvios de nivel guardados na base de dados (opcao). So contem desvios face ao mapa base. */
    function sige_permissions_niveis_overrides(): array {
        $o = function_exists('get_option') ? get_option('sige_permissions_niveis_overrides', []) : [];
        return is_array($o) ? $o : [];
    }
}

if (!function_exists('sige_permissions_role_niveis')) {
    /**
     * Hierarquia de perfis por nivel (Fase 9 Incr 2), agora editavel (Incr 4).
     * Funde o mapa base em codigo com os desvios guardados na base de dados, por
     * isso tudo o que usa o nivel (avaliador, filtragem do selector) respeita os
     * niveis editados automaticamente. Sem desvio guardado, vale o mapa base.
     * Perfis desconhecidos ou personalizados ficam no nivel 0, o mais restritivo.
     * Filtravel.
     */
    function sige_permissions_role_niveis(): array {
        $niveis = sige_permissions_niveis_base();
        foreach (sige_permissions_niveis_overrides() as $slug => $nivel) {
            $slug = sanitize_key((string) $slug);
            if ($slug === '') continue;
            $niveis[$slug] = max(0, min(100, (int) $nivel));
        }
        return (array) apply_filters('sige_permissions_role_niveis', $niveis);
    }
}

if (!function_exists('sige_permissions_guardar_niveis')) {
    /**
     * Grava os niveis editados, guardando so os desvios face ao mapa base
     * (um valor igual ao base remove o desvio). Validacao: inteiros de 0 a 100.
     * Acao de dono: o chamador (handler) reserva isto ao administrador WordPress
     * real; esta funcao apenas valida e persiste.
     *
     * @param array $entradas slug => nivel
     * @return array{ok:bool,aplicados:int,desvios:int}
     */
    function sige_permissions_guardar_niveis(array $entradas): array {
        $base = sige_permissions_niveis_base();
        $overrides = sige_permissions_niveis_overrides();
        $aplicados = 0;
        foreach ($entradas as $slug => $nivel) {
            $slug = sanitize_key((string) $slug);
            if ($slug === '' || !is_numeric($nivel)) continue;
            $n = max(0, min(100, (int) $nivel));
            $base_n = isset($base[$slug]) ? (int) $base[$slug] : 0;
            if ($n === $base_n) {
                unset($overrides[$slug]); // voltou ao valor base: remover desvio
            } else {
                $overrides[$slug] = $n;
            }
            $aplicados++;
        }
        $clean = [];
        foreach ($overrides as $k => $v) {
            $k = sanitize_key((string) $k);
            if ($k === '') continue;
            $clean[$k] = max(0, min(100, (int) $v));
        }
        if (function_exists('update_option')) {
            update_option('sige_permissions_niveis_overrides', $clean, false);
        }
        return ['ok' => true, 'aplicados' => $aplicados, 'desvios' => count($clean)];
    }
}

if (!function_exists('sige_permissions_role_nivel')) {
    /**
     * Nivel de um perfil. Aceita role_id (int) ou slug (string). Perfil
     * desconhecido ou personalizado: nivel 0 (o mais restritivo).
     *
     * @param int|string $role
     */
    function sige_permissions_role_nivel($role): int {
        global $wpdb;
        $slug = '';
        if (is_int($role) || ctype_digit((string) $role)) {
            $t = sige_permissions_tables();
            $slug = (string) $wpdb->get_var($wpdb->prepare("SELECT slug FROM {$t['roles']} WHERE id = %d LIMIT 1", (int) $role));
        } else {
            $slug = sanitize_key((string) $role);
        }
        if ($slug === '') return 0;
        $niveis = sige_permissions_role_niveis();
        return (int) ($niveis[$slug] ?? 0);
    }
}

if (!function_exists('sige_permissions_user_nivel')) {
    /**
     * Nivel de um utilizador pelo seu perfil SIGE activo nesta escola. Sem perfil
     * activo: nivel 0. Nota: administradores WordPress reais sao avaliados a parte
     * (autoridade plena), pelo que este nivel nao se aplica a eles.
     */
    function sige_permissions_user_nivel(int $user_id, int $escola_id): int {
        global $wpdb;
        if ($user_id <= 0 || $escola_id <= 0) return 0;
        $t = sige_permissions_tables();
        $rid = $wpdb->get_var($wpdb->prepare(
            "SELECT ur.role_id FROM {$t['user_roles']} ur
             WHERE ur.user_id = %d AND ur.escola_id = %d AND ur.ativo = 1 LIMIT 1",
            $user_id, $escola_id
        ));
        if ($rid === null) return 0;
        return sige_permissions_role_nivel((int) $rid);
    }
}

if (!function_exists('sige_permissions_avaliar_operacao')) {
    /**
     * Fonte unica de verdade para a decisao de blindagem do modulo de permissoes.
     * Avalia se um actor pode atribuir/remover um perfil a um alvo, aplicando:
     *  - alvo protegido (administrador WP): nunca gerivel aqui;
     *  - anti-escalada: actor nao protegido nao atribui perfil que confere gestao;
     *  - auto-proteccao: actor nao protegido nao se despromove da gestao;
     *  - ultimo gestor: nao deixar a escola sem nenhum gestor de permissoes.
     * Administradores WP reais (protegidos) tem autoridade plena e ficam isentos
     * das guardas anti-escalada/auto-proteccao (mantem o bypass), mas continuam
     * impedidos de gerir OUTRA conta protegida por aqui.
     *
     * @return array{permitido:bool,codigo:string,motivo:string}
     */
    function sige_permissions_avaliar_operacao(int $actor_id, int $target_id, string $accao, int $role_id, int $escola_id): array {
        $nega = static function (string $codigo, string $motivo): array {
            return ['permitido' => false, 'codigo' => $codigo, 'motivo' => $motivo];
        };
        if ($target_id <= 0 || $escola_id <= 0) {
            return $nega('contexto_invalido', 'Contexto invalido. Nenhuma alteracao foi aplicada.');
        }
        // Guarda absoluta: alvo protegido nunca e gerivel aqui (qualquer actor).
        if (sige_permissions_principal_protegido($target_id)) {
            return $nega('alvo_protegido', 'Esta conta e um administrador WordPress protegido e nao pode ser gerida pelo modulo de permissoes. Faca-o pela gestao de utilizadores do WordPress.');
        }

        $actor_protegido = sige_permissions_principal_protegido($actor_id);

        if ($accao === 'assign_user_role') {
            if (!$actor_protegido) {
                if ($role_id > 0 && sige_permissions_role_concede_gestao($role_id)) {
                    return $nega('escalada_gestao', 'Nao pode atribuir um perfil que confere a gestao de permissoes. Apenas um administrador WordPress o pode fazer.');
                }
                if ($target_id === $actor_id
                    && sige_permissions_user_tem_gestao_sige($actor_id, $escola_id)
                    && ($role_id <= 0 || !sige_permissions_role_concede_gestao($role_id))) {
                    return $nega('auto_despromocao', 'Nao pode despromover-se a si proprio da gestao de permissoes.');
                }
                // Hierarquia: so atribui perfis abaixo do seu nivel e so mexe em
                // utilizadores abaixo do seu nivel (estritamente).
                $actor_nivel = sige_permissions_user_nivel($actor_id, $escola_id);
                if ($actor_nivel <= sige_permissions_role_nivel($role_id)) {
                    return $nega('nivel_insuficiente', 'Nao pode atribuir um perfil de nivel igual ou superior ao seu.');
                }
                if ($target_id !== $actor_id && $actor_nivel <= sige_permissions_user_nivel($target_id, $escola_id)) {
                    return $nega('nivel_insuficiente', 'Nao pode alterar um utilizador de nivel igual ou superior ao seu.');
                }
            }
            return ['permitido' => true, 'codigo' => 'ok', 'motivo' => ''];
        }

        if ($accao === 'unassign_user_role') {
            if (!$actor_protegido) {
                if ($target_id === $actor_id && sige_permissions_user_tem_gestao_sige($actor_id, $escola_id)) {
                    return $nega('auto_remocao', 'Nao pode remover o seu proprio perfil de gestao de permissoes.');
                }
                if (sige_permissions_user_tem_gestao_sige($target_id, $escola_id)
                    && sige_permissions_contar_gestores_sige($escola_id, $target_id) === 0) {
                    return $nega('ultimo_gestor', 'Esta accao deixaria a escola sem nenhum gestor de permissoes. Atribua o perfil a outro utilizador primeiro.');
                }
                // Hierarquia: so mexe em utilizadores estritamente abaixo do seu nivel.
                if ($target_id !== $actor_id
                    && sige_permissions_user_nivel($actor_id, $escola_id) <= sige_permissions_user_nivel($target_id, $escola_id)) {
                    return $nega('nivel_insuficiente', 'Nao pode alterar um utilizador de nivel igual ou superior ao seu.');
                }
            }
            return ['permitido' => true, 'codigo' => 'ok', 'motivo' => ''];
        }

        return $nega('accao_desconhecida', 'Accao desconhecida.');
    }
}

if (!function_exists('sige_permissions_staff_wp_roles')) {
    /**
     * Papeis WordPress sige_* de STAFF (geridos pelo fluxo de Perfis e Permissoes).
     * Exclui os papeis de portal (sige_aluno, sige_encarregado), que tem fluxo
     * proprio (contas de aluno/encarregado) e mantem o seu papel WordPress.
     */
    function sige_permissions_staff_wp_roles(): array {
        return [
            'sige_admin_ti', 'sige_director', 'sige_pedagogico', 'sige_gestor_rh',
            'sige_professor', 'sige_educador', 'sige_secretaria_geral', 'sige_secretario',
            'sige_financeiro', 'sige_assistente', 'sige_recepcao', 'sige_guarda',
            'sige_motorista', 'sige_limpeza',
        ];
    }
}

if (!function_exists('sige_permissions_is_staff_wp_role')) {
    /** O slug e um papel WordPress sige_* de staff (e nao de portal)? */
    function sige_permissions_is_staff_wp_role(string $slug): bool {
        return in_array(sanitize_key($slug), sige_permissions_staff_wp_roles(), true);
    }
}

if (!function_exists('sige_permissions_get_latest_active_role')) {
    /**
     * Perfil SIGE activo mais recente do utilizador, em QUALQUER escola. Replica
     * fielmente o efeito do antigo set_role (que punha o papel da ultima
     * atribuicao, disponivel em todo o lado), sem depender do contexto de escola.
     * A isolacao de dados por escola e separada (escola_id) e nao depende disto.
     */
    function sige_permissions_get_latest_active_role(int $user_id) {
        global $wpdb;
        if ($user_id <= 0 || !function_exists('sige_permissions_tables')) return null;
        $t = sige_permissions_tables();
        return $wpdb->get_row($wpdb->prepare(
            "SELECT r.* FROM {$t['user_roles']} ur
             INNER JOIN {$t['roles']} r ON r.id = ur.role_id
             WHERE ur.user_id = %d AND ur.ativo = 1 AND r.ativo = 1
             ORDER BY ur.updated_at DESC, ur.id DESC LIMIT 1",
            $user_id
        ));
    }
}

if (!function_exists('sige_permissions_caps_for_user')) {
    /**
     * Capacidades que o perfil SIGE activo do utilizador concede, lidas do papel
     * sige_* mapeado (conjunto completo, incluindo a capacidade com o nome do papel
     * e as em cascata). Prefere o perfil da escola actual; se nao houver contexto,
     * usa o perfil activo mais recente em qualquer escola (igual ao antigo set_role).
     */
    function sige_permissions_caps_for_user(int $user_id): array {
        if ($user_id <= 0) return [];
        $role = function_exists('sige_permissions_get_active_role') ? sige_permissions_get_active_role($user_id) : null;
        if (!$role || empty($role->slug)) {
            $role = sige_permissions_get_latest_active_role($user_id);
        }
        if (!$role || empty($role->slug)) return [];
        $wp_role = sige_permissions_role_to_wp_role((string) $role->slug);
        if ($wp_role === '') return [];
        $r = function_exists('get_role') ? get_role($wp_role) : null;
        if (!$r || empty($r->capabilities)) return [];
        $caps = [];
        foreach ((array) $r->capabilities as $cap => $granted) {
            if ($granted) { $caps[] = (string) $cap; }
        }
        return $caps;
    }
}

if (!function_exists('sige_permissions_grant_caps_filter')) {
    /**
     * Sobreposicao puramente aditiva (Fase 9 Incr 5). Em vez de substituir o papel
     * WordPress, concede em tempo de execucao as capacidades do papel sige_* mapeado
     * ao perfil SIGE activo, sem tocar no papel guardado. Administradores reais e
     * utilizadores sem perfil activo nao sao afectados. Cache por pedido e guarda
     * anti-recursao.
     */
    function sige_permissions_grant_caps_filter($allcaps, $caps, $args, $user) {
        static $in = false;
        if ($in || !is_array($allcaps)) return $allcaps;
        $user_id = ($user instanceof WP_User) ? (int) $user->ID : (isset($args[1]) ? (int) $args[1] : 0);
        if ($user_id <= 0) return $allcaps;
        $in = true;
        try {
            if (function_exists('sige_is_real_wp_admin_user') && sige_is_real_wp_admin_user($user_id)) {
                return $allcaps;
            }
            static $cache = [];
            if (!array_key_exists($user_id, $cache)) {
                $cache[$user_id] = sige_permissions_caps_for_user($user_id);
            }
            foreach ($cache[$user_id] as $cap) { $allcaps[$cap] = true; }
            return $allcaps;
        } finally {
            $in = false;
        }
    }
}

if (!function_exists('sige_can')) {
    function sige_can(string $permission, $context = null, ?int $user_id = null): bool {
        global $wpdb;
        $permission = sige_permission_normalize($permission);
        $user_id = $user_id ?: get_current_user_id();
        if ($permission === '' || $user_id <= 0) return false;

        // v12.11.9.6 - colaborador RH desactivado não recebe permissões SIGE,
        // mesmo que a role legada ainda tenha capabilities fortes como manage_options.
        // Administradores WordPress reais continuam protegidos dentro do helper.
        if (function_exists('sige_rh_user_is_active_for_school') && !sige_rh_user_is_active_for_school((int)$user_id)) {
            sige_permission_audit((int)$user_id, $permission, false, 'rh_staff_inactive', $context);
            return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
        }

        // Bypass absoluto antes de registry/feature gate.
        // Evita bloquear Admin TI/Super Admin em páginas críticas como Permissões.
        if (function_exists('sige_permissions_is_super_admin') && sige_permissions_is_super_admin((int)$user_id)) {
            sige_permission_audit((int)$user_id, $permission, true, 'super_admin_bypass', $context);
            return (bool)apply_filters('sige_can', true, $permission, $context, $user_id);
        }

        $registry = sige_permissions_registry();
        if (!isset($registry[$permission])) {
            sige_permission_audit((int)$user_id, $permission, false, 'unknown_permission', $context);
            return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
        }

        if (!sige_permission_module_active($permission)) {
            sige_permission_audit((int)$user_id, $permission, false, 'feature_disabled', $context);
            return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
        }

        $t = sige_permissions_tables();

        // v12.11.9.65 - Guarda / Portaria é um perfil fechado.
        // Mesmo com overrides acidentais, este perfil só pode usar Portaria e consultar alunos.
        $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        if ($escola_id <= 0) {
            if (function_exists('sige_multitenancy_strict_enabled') && sige_multitenancy_strict_enabled()) {
                sige_permission_audit((int)$user_id, $permission, false, 'tenant_context_missing', $context);
                return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
            }
            $escola_id = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;
        }
        $active_role = function_exists('sige_permissions_get_active_role') ? sige_permissions_get_active_role((int)$user_id, $escola_id) : null;
        if ($active_role && !empty($active_role->slug) && (string)$active_role->slug === 'guarda') {
            $guarda_allowlist = ['portaria.ver','portaria.validar_acesso','alunos.ver'];
            if (!in_array($permission, $guarda_allowlist, true)) {
                sige_permission_audit((int)$user_id, $permission, false, 'guarda_scope_denied', $context);
                return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
            }
        }

        // Overrides explícitos por utilizador têm prioridade sobre role, excepto no perfil fechado Guarda / Portaria.
        // v12.12.7: primeiro o override tenant-scoped; o global legado só é consultado
        // se não houver linha da escola corrente.
        if (!empty($t['school_user_overrides'])) {
            $override = $wpdb->get_var($wpdb->prepare(
                "SELECT allowed FROM {$t['school_user_overrides']} WHERE escola_id = %d AND user_id = %d AND permission_key = %s LIMIT 1",
                $escola_id, $user_id, $permission
            ));
            if ($override !== null) {
                $allowed = ((int)$override) === 1;
                sige_permission_audit((int)$user_id, $permission, $allowed, 'school_user_override', $context);
                return (bool)apply_filters('sige_can', $allowed, $permission, $context, $user_id);
            }
        }
        $override = $wpdb->get_var($wpdb->prepare(
            "SELECT allowed FROM {$t['user_overrides']} WHERE user_id = %d AND permission_key = %s LIMIT 1",
            $user_id, $permission
        ));
        if ($override !== null) {
            $allowed = ((int)$override) === 1;
            sige_permission_audit((int)$user_id, $permission, $allowed, 'legacy_user_override', $context);
            return (bool)apply_filters('sige_can', $allowed, $permission, $context, $user_id);
        }

        // Permissões por perfil SIGE - fonte principal de verdade.
        if ($active_role && !empty($active_role->id)) {
            if (!empty($t['school_role_permissions'])) {
                $school_rows = (int)$wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(1) FROM {$t['school_role_permissions']} WHERE escola_id = %d AND role_id = %d LIMIT 1",
                    $escola_id, (int)$active_role->id
                ));
                if ($school_rows > 0) {
                    $allowed_by_school_role = (int)$wpdb->get_var($wpdb->prepare(
                        "SELECT COUNT(1)
                           FROM {$t['school_role_permissions']} srp
                          WHERE srp.escola_id = %d
                            AND srp.role_id = %d
                            AND srp.permission_key = %s
                            AND srp.allowed = 1
                          LIMIT 1",
                        $escola_id, (int)$active_role->id, $permission
                    ));
                    if ($allowed_by_school_role > 0) {
                        sige_permission_audit((int)$user_id, $permission, true, 'school_role_primary', $context);
                        return (bool)apply_filters('sige_can', true, $permission, $context, $user_id);
                    }
                    sige_permission_audit((int)$user_id, $permission, false, 'school_role_primary_denied', $context);
                    return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
                }
            }
            $allowed_by_role = (int)$wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(1)
                   FROM {$t['role_permissions']} rp
                  WHERE rp.role_id = %d
                    AND rp.permission_key = %s
                    AND rp.allowed = 1
                  LIMIT 1",
                (int)$active_role->id, $permission
            ));
            if ($allowed_by_role > 0) {
                sige_permission_audit((int)$user_id, $permission, true, 'sige_role_template', $context);
                return (bool)apply_filters('sige_can', true, $permission, $context, $user_id);
            }

            // Quando há perfil SIGE activo, não cair para capabilities antigas.
            // A matriz de permissões passa a mandar de verdade.
            sige_permission_audit((int)$user_id, $permission, false, 'sige_role_template_denied', $context);
            return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
        }

        // Compatibilidade controlada com roles/capabilities antigas apenas se ainda não há Perfil SIGE activo.
        if (sige_permissions_user_legacy_allows($permission, (int)$user_id)) {
            sige_permission_audit((int)$user_id, $permission, true, 'legacy_capability_fallback', $context);
            return (bool)apply_filters('sige_can', true, $permission, $context, $user_id);
        }

        sige_permission_audit((int)$user_id, $permission, false, 'deny_default', $context);
        return (bool)apply_filters('sige_can', false, $permission, $context, $user_id);
    }
}

if (!function_exists('sige_permissions_sync_current_user_legacy_role')) {
    /**
     * Sobreposicao puramente aditiva (Fase 9 Incr 5): o modulo NAO mantem o
     * utilizador num papel sige_*. As capacidades vem do filtro user_has_cap. Esta
     * sincronizacao no init passa a fazer so limpeza: se o utilizador actual estiver
     * num papel sige_* de staff (estado legado de versoes anteriores que substituiam
     * o papel), repoe o papel WordPress original (o filtro continua a dar as
     * capacidades). Migracao preguicosa, uma vez por utilizador na sessao seguinte.
     */
    function sige_permissions_sync_current_user_legacy_role(): void {
        if (!is_user_logged_in()) return;
        $user_id = get_current_user_id();
        if ($user_id <= 0 || (function_exists('sige_permissions_is_super_admin') ? sige_permissions_is_super_admin($user_id) : user_can($user_id, 'manage_options'))) return;
        $user = get_user_by('id', $user_id);
        if (!($user instanceof WP_User)) return;
        $tem_staff_sige = false;
        foreach ((array) $user->roles as $r) {
            if (sige_permissions_is_staff_wp_role((string) $r)) { $tem_staff_sige = true; break; }
        }
        if ($tem_staff_sige && function_exists('sige_permissions_restore_wp_roles')) {
            sige_permissions_restore_wp_roles($user_id);
        }
    }
}
add_action('init', 'sige_permissions_sync_current_user_legacy_role', 20);
add_filter('user_has_cap', 'sige_permissions_grant_caps_filter', 10, 4);
