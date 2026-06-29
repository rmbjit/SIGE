<?php
/**
 * Catalogo de dados pessoais (PII) - Fase 8 incremento 1.
 *
 * Mapa DECLARADO de dados pessoais por tabela e campo, com categoria,
 * sensibilidade, finalidade e base legal. E a fonte da verdade da classificacao;
 * o inventario (pii-inventario.php) confronta este catalogo com o esquema vivo e
 * denuncia lacunas (campos PII fora do catalogo) e desvios (catalogo sem
 * correspondencia no esquema).
 *
 * Principios: minimizacao, finalidade, retencao limitada e direitos do titular.
 * A base legal indicada e uma classificacao por omissao, revisivel pela
 * instituicao (a escola e o seu responsavel pelo tratamento de dados); nao
 * constitui parecer juridico. Os nomes de tabela sao SEM prefixo: o inventario
 * aplica o prefixo do WordPress por escola.
 *
 * Este modulo nao escreve nada e nao depende de contexto de escola: e so a
 * declaracao. So leitura.
 */

if (!defined('ABSPATH') && !defined('SIGE_PRIVACY_TEST_MODE')) {
    exit;
}

if (!function_exists('sige_pii_categorias')) {
    /**
     * Categorias de dados pessoais reconhecidas, com rotulo e sensibilidade base.
     * sensibilidade_base: 'sensivel' marca categorias que exigem cuidado acrescido
     * (ex.: saude). Cada campo no catalogo pode ainda elevar a sua sensibilidade.
     */
    function sige_pii_categorias(): array {
        return [
            'identificacao'             => ['rotulo' => 'Identificacao', 'sensibilidade_base' => 'normal'],
            'contacto'                  => ['rotulo' => 'Contacto', 'sensibilidade_base' => 'normal'],
            'localizacao'               => ['rotulo' => 'Localizacao e morada', 'sensibilidade_base' => 'normal'],
            'filiacao'                  => ['rotulo' => 'Filiacao e encarregados', 'sensibilidade_base' => 'normal'],
            'dados_academicos'          => ['rotulo' => 'Dados academicos', 'sensibilidade_base' => 'normal'],
            'dados_financeiros'         => ['rotulo' => 'Dados financeiros', 'sensibilidade_base' => 'normal'],
            'dados_saude'               => ['rotulo' => 'Dados de saude', 'sensibilidade_base' => 'sensivel'],
            'comunicacao_consentimento' => ['rotulo' => 'Comunicacao e consentimento', 'sensibilidade_base' => 'normal'],
            'registo_acesso'            => ['rotulo' => 'Registos de acesso e auditoria', 'sensibilidade_base' => 'normal'],
        ];
    }
}

if (!function_exists('sige_pii_bases_legais')) {
    /**
     * Bases legais reconhecidas (classificacao por omissao, revisivel pela escola).
     */
    function sige_pii_bases_legais(): array {
        return [
            'contrato_educativo' => 'Execucao do contrato de prestacao de ensino',
            'obrigacao_legal'    => 'Cumprimento de obrigacao legal (registo academico, fiscal ou de seguranca)',
            'interesse_legitimo' => 'Interesse legitimo da instituicao (operacao, cobranca, seguranca)',
            'consentimento'      => 'Consentimento do titular (ou do encarregado), revogavel',
        ];
    }
}

if (!function_exists('sige_pii_catalogo')) {
    /**
     * Catalogo declarado. Cada entrada:
     *  tabela        - nome da tabela SEM prefixo
     *  coluna        - nome da coluna
     *  categoria     - chave de sige_pii_categorias()
     *  sensibilidade - 'normal' ou 'sensivel'
     *  finalidade    - para que serve este dado
     *  base_legal    - chave de sige_pii_bases_legais()
     *  nota          - observacao opcional (ex.: campo de texto livre)
     *
     * @return array<int,array<string,string>>
     */
    function sige_pii_catalogo(): array {
        $e = static function (string $tabela, string $coluna, string $categoria, string $sensibilidade, string $finalidade, string $base_legal, string $nota = ''): array {
            return [
                'tabela'        => $tabela,
                'coluna'        => $coluna,
                'categoria'     => $categoria,
                'sensibilidade' => $sensibilidade,
                'finalidade'    => $finalidade,
                'base_legal'    => $base_legal,
                'nota'          => $nota,
            ];
        };

        $cat = [];

        // ── Alunos: a tabela mais densa em PII ───────────────────────────────
        $fin_aluno = 'Identificacao e gestao academica e financeira do aluno';
        $cat[] = $e('sige_alunos', 'numero_processo', 'identificacao', 'normal', $fin_aluno, 'obrigacao_legal');
        $cat[] = $e('sige_alunos', 'nome_completo', 'identificacao', 'normal', $fin_aluno, 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'data_nascimento', 'identificacao', 'normal', 'Determinar idade, classe e elegibilidade', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'genero', 'identificacao', 'normal', 'Registo academico e estatistica', 'obrigacao_legal');
        $cat[] = $e('sige_alunos', 'documento_nr', 'identificacao', 'sensivel', 'Identificacao oficial do aluno', 'obrigacao_legal', 'Numero de documento de identidade');
        $cat[] = $e('sige_alunos', 'tipo_documento', 'identificacao', 'normal', 'Tipo do documento de identidade', 'obrigacao_legal');
        $cat[] = $e('sige_alunos', 'nacionalidade', 'identificacao', 'normal', 'Registo academico', 'obrigacao_legal');
        $cat[] = $e('sige_alunos', 'naturalidade', 'identificacao', 'normal', 'Registo academico', 'obrigacao_legal');
        $cat[] = $e('sige_alunos', 'provincia', 'localizacao', 'normal', 'Morada e contacto do aluno', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'distrito', 'localizacao', 'normal', 'Morada e contacto do aluno', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'bairro', 'localizacao', 'normal', 'Morada e contacto do aluno', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'morada', 'localizacao', 'normal', 'Morada do aluno', 'contrato_educativo', 'Texto livre');
        $cat[] = $e('sige_alunos', 'nome_pai', 'filiacao', 'normal', 'Filiacao e contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'nome_mae', 'filiacao', 'normal', 'Filiacao e contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'profissao_pai', 'filiacao', 'normal', 'Caracterizacao do agregado', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'profissao_mae', 'filiacao', 'normal', 'Caracterizacao do agregado', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'nuit_encarregado', 'identificacao', 'sensivel', 'Identificacao fiscal do encarregado para facturacao', 'obrigacao_legal', 'Numero fiscal');
        $cat[] = $e('sige_alunos', 'contacto_encarregado', 'contacto', 'normal', 'Contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'email_encarregado', 'contacto', 'normal', 'Contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'telemovel_pai', 'contacto', 'normal', 'Contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'telemovel_pai_2', 'contacto', 'normal', 'Contacto alternativo do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'email_pai', 'contacto', 'normal', 'Contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'telemovel_mae', 'contacto', 'normal', 'Contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'telemovel_mae_2', 'contacto', 'normal', 'Contacto alternativo do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'email_mae', 'contacto', 'normal', 'Contacto do encarregado', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'whatsapp_notificacoes', 'contacto', 'normal', 'Numero para notificacoes por WhatsApp', 'consentimento');
        $cat[] = $e('sige_alunos', 'encarregado_principal_nome', 'filiacao', 'normal', 'Encarregado principal', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'encarregado_principal_parentesco', 'filiacao', 'normal', 'Relacao com o aluno', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'encarregado_principal_telemovel', 'contacto', 'normal', 'Contacto do encarregado principal', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'encarregado_principal_email', 'contacto', 'normal', 'Contacto do encarregado principal', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'contacto_alternativo_nome', 'filiacao', 'normal', 'Contacto alternativo', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'contacto_alternativo_parentesco', 'filiacao', 'normal', 'Relacao com o aluno', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'contacto_alternativo_telemovel', 'contacto', 'normal', 'Contacto alternativo', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'autorizado_buscar_nome', 'filiacao', 'normal', 'Pessoa autorizada a buscar o aluno (seguranca)', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'canal_preferencial_comunicacao', 'comunicacao_consentimento', 'normal', 'Canal preferido de comunicacao', 'consentimento');
        $cat[] = $e('sige_alunos', 'consent_whatsapp', 'comunicacao_consentimento', 'normal', 'Consentimento para comunicacao por WhatsApp', 'consentimento');
        $cat[] = $e('sige_alunos', 'consent_email', 'comunicacao_consentimento', 'normal', 'Consentimento para comunicacao por e-mail', 'consentimento');
        $cat[] = $e('sige_alunos', 'consent_sms', 'comunicacao_consentimento', 'normal', 'Consentimento para comunicacao por SMS', 'consentimento');
        $cat[] = $e('sige_alunos', 'consent_chamada', 'comunicacao_consentimento', 'normal', 'Consentimento para chamadas', 'consentimento');
        $cat[] = $e('sige_alunos', 'observacoes', 'identificacao', 'sensivel', 'Notas livres sobre o aluno', 'interesse_legitimo', 'Texto livre: pode conter dados sensiveis');
        // v12.12.26 - Fase 8 Incr 3.2: classificacao das lacunas (colunas PII antes fora do catalogo).
        $cat[] = $e('sige_alunos', 'foto', 'identificacao', 'sensivel', 'Fotografia do aluno', 'contrato_educativo', 'Imagem identificativa');
        $cat[] = $e('sige_alunos', 'doc_bi_url', 'identificacao', 'sensivel', 'Documento de identidade digitalizado do aluno', 'obrigacao_legal', 'Ligacao a copia do documento');
        $cat[] = $e('sige_alunos', 'encarregado_principal_tipo', 'filiacao', 'normal', 'Tipo de encarregado principal', 'contrato_educativo');
        $cat[] = $e('sige_alunos', 'autorizado_buscar_parentesco', 'filiacao', 'normal', 'Parentesco da pessoa autorizada a buscar o aluno', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'autorizado_buscar_telemovel', 'contacto', 'normal', 'Contacto da pessoa autorizada a buscar o aluno', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'autorizado_buscar_documento', 'identificacao', 'sensivel', 'Documento da pessoa autorizada a buscar o aluno', 'interesse_legitimo', 'Numero de documento de terceiro');
        $cat[] = $e('sige_alunos', 'contacto_emergencia_1', 'contacto', 'normal', 'Contacto de emergencia', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'contacto_emergencia_2', 'contacto', 'normal', 'Contacto de emergencia alternativo', 'interesse_legitimo');
        $cat[] = $e('sige_alunos', 'encarregado_observacoes', 'filiacao', 'sensivel', 'Notas livres sobre o encarregado', 'interesse_legitimo', 'Texto livre');

        // ── Agregado familiar ────────────────────────────────────────────────
        $cat[] = $e('sige_agregados_familiares', 'nome_agregado', 'filiacao', 'normal', 'Identificacao do agregado familiar', 'contrato_educativo');
        $cat[] = $e('sige_agregados_familiares', 'telefone_chave', 'contacto', 'normal', 'Contacto chave do agregado', 'contrato_educativo');
        $cat[] = $e('sige_agregados_familiares', 'observacoes', 'filiacao', 'sensivel', 'Notas livres sobre o agregado', 'interesse_legitimo', 'Texto livre');

        // ── Historico de alteracoes de encarregados (auditoria de PII) ───────
        $cat[] = $e('sige_alunos_encarregados_historico', 'alteracoes_json', 'registo_acesso', 'sensivel', 'Registo de alteracoes aos dados do encarregado', 'obrigacao_legal', 'Contem valores antigos e novos de campos PII');
        $cat[] = $e('sige_alunos_encarregados_historico', 'ip_hash', 'registo_acesso', 'normal', 'Origem da alteracao (IP em hash)', 'interesse_legitimo');
        $cat[] = $e('sige_alunos_encarregados_historico', 'user_agent_hash', 'registo_acesso', 'normal', 'Origem da alteracao (agente em hash)', 'interesse_legitimo');
        $cat[] = $e('sige_alunos_encarregados_historico', 'user_display', 'registo_acesso', 'normal', 'Utilizador que alterou', 'obrigacao_legal');

        // ── Matriculas ───────────────────────────────────────────────────────
        $cat[] = $e('sige_matriculas', 'aluno_id', 'dados_academicos', 'normal', 'Vinculo do aluno a turma e ano lectivo', 'contrato_educativo', 'Identificador que liga ao aluno');

        // ── Saude (jardim/creche) - SENSIVEL ─────────────────────────────────
        $fin_saude = 'Acompanhamento de saude e bem-estar da crianca';
        $cat[] = $e('sige_jardim_saude', 'aluno_id', 'dados_saude', 'sensivel', $fin_saude, 'interesse_legitimo', 'Liga o registo de saude ao aluno');
        $cat[] = $e('sige_jardim_saude', 'febre', 'dados_saude', 'sensivel', $fin_saude, 'interesse_legitimo');
        $cat[] = $e('sige_jardim_saude', 'temperatura', 'dados_saude', 'sensivel', $fin_saude, 'interesse_legitimo');
        $cat[] = $e('sige_jardim_saude', 'queda_acidente', 'dados_saude', 'sensivel', $fin_saude, 'interesse_legitimo');
        $cat[] = $e('sige_jardim_saude', 'desc_ocorrencia', 'dados_saude', 'sensivel', $fin_saude, 'interesse_legitimo', 'Texto livre sobre ocorrencia de saude');
        $cat[] = $e('sige_jardim_saude', 'medicamento_dado', 'dados_saude', 'sensivel', $fin_saude, 'interesse_legitimo');
        $cat[] = $e('sige_jardim_saude', 'obs_alimentacao', 'dados_saude', 'sensivel', 'Acompanhamento alimentar da crianca', 'interesse_legitimo', 'Texto livre');

        // ── Professores e funcionarios ───────────────────────────────────────
        $fin_func = 'Gestao de recursos humanos e folha';
        $cat[] = $e('sige_professores', 'nome_completo', 'identificacao', 'normal', $fin_func, 'contrato_educativo');
        $cat[] = $e('sige_professores', 'nuit', 'identificacao', 'sensivel', 'Identificacao fiscal do funcionario', 'obrigacao_legal', 'Numero fiscal');
        $cat[] = $e('sige_professores', 'telemovel', 'contacto', 'normal', 'Contacto do funcionario', 'contrato_educativo');
        $cat[] = $e('sige_professores', 'email', 'contacto', 'normal', 'Contacto do funcionario', 'contrato_educativo');
        $cat[] = $e('sige_professores', 'dados_bancarios', 'dados_financeiros', 'sensivel', 'Pagamento de salario ao funcionario', 'contrato_educativo', 'Dados bancarios');
        $cat[] = $e('sige_professores', 'salario_base', 'dados_financeiros', 'sensivel', 'Folha salarial', 'contrato_educativo');
        $cat[] = $e('sige_professores', 'subsidio', 'dados_financeiros', 'sensivel', 'Folha salarial', 'contrato_educativo');
        $cat[] = $e('sige_professores', 'documentos_urls', 'identificacao', 'sensivel', 'Documentos do funcionario', 'obrigacao_legal', 'Pode conter copias de documentos');
        $cat[] = $e('sige_professores', 'foto_perfil', 'identificacao', 'normal', 'Fotografia do funcionario', 'interesse_legitimo');
        $cat[] = $e('sige_professores', 'observacoes', 'identificacao', 'sensivel', 'Notas livres sobre o funcionario', 'interesse_legitimo', 'Texto livre');

        // ── Pagamentos ───────────────────────────────────────────────────────
        $cat[] = $e('sige_fin_pagamentos', 'aluno_id', 'dados_financeiros', 'normal', 'Pagamento associado ao aluno', 'contrato_educativo', 'Liga o pagamento ao aluno');
        $cat[] = $e('sige_fin_pagamentos', 'referencia_externa', 'dados_financeiros', 'normal', 'Referencia do meio de pagamento', 'obrigacao_legal');
        $cat[] = $e('sige_fin_pagamentos', 'observacoes', 'dados_financeiros', 'normal', 'Notas do pagamento', 'interesse_legitimo', 'Texto livre');

        // ── Contactos de cobranca ────────────────────────────────────────────
        $cat[] = $e('sige_fin_contactos_cobranca', 'aluno_id', 'dados_financeiros', 'normal', 'Cobranca associada ao aluno', 'interesse_legitimo');
        $cat[] = $e('sige_fin_contactos_cobranca', 'canal', 'contacto', 'normal', 'Canal de contacto de cobranca', 'interesse_legitimo');
        $cat[] = $e('sige_fin_contactos_cobranca', 'notas', 'dados_financeiros', 'normal', 'Notas do contacto de cobranca', 'interesse_legitimo', 'Texto livre');
        $cat[] = $e('sige_fin_contactos_cobranca', 'data_contacto', 'dados_financeiros', 'normal', 'Data do contacto de cobranca', 'interesse_legitimo', 'Registo operacional de cobranca');
        $cat[] = $e('sige_fin_contactos_cobranca', 'proximo_contacto', 'dados_financeiros', 'normal', 'Data do proximo contacto de cobranca', 'interesse_legitimo', 'Registo operacional de cobranca');

        // ── Transacoes moveis (M-Pesa / e-Mola) ──────────────────────────────
        $cat[] = $e('sige_mpesa_transacoes', 'msisdn', 'contacto', 'sensivel', 'Numero de telemovel do pagador', 'contrato_educativo', 'Numero de telefone');
        $cat[] = $e('sige_mpesa_transacoes', 'referencia_mpesa', 'dados_financeiros', 'normal', 'Referencia da transacao do gateway', 'obrigacao_legal');
        $cat[] = $e('sige_mpesa_transacoes', 'referencia_cliente', 'dados_financeiros', 'normal', 'Referencia do cliente', 'obrigacao_legal');
        $cat[] = $e('sige_mpesa_transacoes', 'payload_json', 'dados_financeiros', 'sensivel', 'Resposta integral do gateway', 'obrigacao_legal', 'Pode conter dados pessoais do gateway');

        // ── Registo de acessos (portaria) ────────────────────────────────────
        $cat[] = $e('sige_acessos', 'aluno_id', 'registo_acesso', 'normal', 'Registo de entrada e saida do aluno', 'interesse_legitimo', 'Dado de presenca e movimento');
        $cat[] = $e('sige_acessos', 'data_hora', 'registo_acesso', 'normal', 'Momento de entrada ou saida', 'interesse_legitimo');

        return $cat;
    }
}

if (!function_exists('sige_pii_catalogo_por_tabela')) {
    /**
     * Catalogo agrupado por tabela: ['sige_alunos' => [entradas...], ...].
     */
    function sige_pii_catalogo_por_tabela(): array {
        $out = [];
        foreach (sige_pii_catalogo() as $entry) {
            $out[$entry['tabela']][] = $entry;
        }
        return $out;
    }
}
