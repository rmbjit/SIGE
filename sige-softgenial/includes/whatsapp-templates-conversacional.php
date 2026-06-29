<?php
/**
 * SIGE SoftGenial - WhatsApp Templates Conversacional v2
 * Ficheiro: includes/whatsapp-templates-conversacional.php
 *
 * v12.9.53 - "Secretaria a falar".
 *
 * Objectivo desta camada
 * ----------------------
 * Substituir as mensagens WhatsApp financeiras por um fluxo conversacional,
 * humano, sem padrões de mass-marketing, com micro-variação real por envio.
 *
 * Princípios:
 *  1. APENAS três cenários geram mensagens financeiras - e nunca por automatismo:
 *      a) Pagamento confirmado    → tipo 'recibo'   (acção: registar pagamento)
 *      b) Lançamento de mensalidade → tipo 'fatura' (acção: gerar mensalidade)
 *      c) Cobrança de pendência    → tipo 'cobranca' (acção humana na Central)
 *  2. Todo o restante (lembretes pré-vencimento, alertas D-5/D-0/D+5/D+15) está
 *     já desactivado pelo motor; aqui reforçamos com guarda anti-cron explícita.
 *  3. Cada mensagem é única: aberturas/miolos/fechos sorteados de bibliotecas
 *     com 8-12 variações cada → milhares de combinações possíveis.
 *  4. Sem asteriscos de negrito, sem emojis de template, sem ALL-CAPS,
 *     sem palavras-disparo ("URGENTE", "IMEDIATAMENTE", "PENALIZAÇÕES").
 *  5. Tom: secretária real a escrever a um encarregado real, em PT-MZ pré-AO90,
 *     com convite à conversa e espaço para resposta.
 *  6. Aplicação automática: ao subir a versão, os templates persistidos em
 *     wp_sige_config são limpos para forçar uso destes defaults novos, e as
 *     mensagens já enfileiradas (status='pendente') destes 3 tipos são
 *     re-renderizadas com o novo modelo.
 *
 * Dependências:
 *  - whatsapp-engine.php (para o helper de fallback e numeralia base)
 *  - whatsapp-recovery-mode.php (humanizador, openers conversacionais legacy)
 *  - finance-core.php (sige_fin_queue_whatsapp / sige_fin_log)
 *
 * Pre-AO90: actualizar, actualização, actividade, actual, sector, objecto,
 *           directo, contactar, etc.
 *
 * @since   12.9.53
 * @author  RMBJ Consultoria
 */

if (!defined('ABSPATH')) exit;

// ============================================================================
// VERSÃO DO MODELO DE TEMPLATE
// ----------------------------------------------------------------------------
// Subir esta string aciona automaticamente:
//   • limpeza dos templates persistidos em wp_sige_config (uso dos defaults v2)
//   • re-renderização das mensagens 'pendente' nos 3 tipos financeiros
//   • registo da migração em sige_fin_log
// ============================================================================
if (!defined('SIGE_WPP_TPL_VERSION')) {
    define('SIGE_WPP_TPL_VERSION', '2026-06-01.v7-servicos-contextuais-pt-mz');
}

if (!defined('SIGE_WPP_TPL_OPT_VERSION')) {
    define('SIGE_WPP_TPL_OPT_VERSION', 'sige_wpp_tpl_version_aplicada');
}

// Tipos protegidos: estes três são SEMPRE de origem humana, nunca cron.
if (!defined('SIGE_WPP_TPL_TIPOS_PROTEGIDOS')) {
    define('SIGE_WPP_TPL_TIPOS_PROTEGIDOS', 'fatura,recibo,cobranca,lembrete');
}

// ============================================================================
// HELPERS BÁSICOS
// ============================================================================

if (!function_exists('sige_wpp_tpl_pick')) {
    /**
     * Escolhe um item aleatório de um array.
     * Usa mt_rand para distribuição mais uniforme do que array_rand padrão
     * em arrays pequenos.
     */
    function sige_wpp_tpl_pick(array $items): string {
        if (empty($items)) return '';
        return (string)$items[mt_rand(0, count($items) - 1)];
    }
}

if (!function_exists('sige_wpp_tpl_primeiro_nome')) {
    function sige_wpp_tpl_primeiro_nome(string $nome_completo): string {
        $partes = preg_split('/\s+/', trim($nome_completo)) ?: [];
        return $partes[0] ?? '';
    }
}

if (!function_exists('sige_wpp_tpl_saudacao_temporal')) {
    /**
     * Devolve "Bom dia", "Boa tarde" ou "Boa noite" conforme a hora local Maputo.
     * Mantém a mensagem realista - uma secretária não diz "bom dia" às 18h.
     */
    function sige_wpp_tpl_saudacao_temporal(): string {
        $hora = (int) wp_date('H');
        if ($hora >= 5 && $hora < 12)  return 'Bom dia';
        if ($hora >= 12 && $hora < 18) return 'Boa tarde';
        return 'Boa noite';
    }
}

if (!function_exists('sige_wpp_tpl_escola_nome')) {
    function sige_wpp_tpl_escola_nome($config_row): string {
        if (function_exists('sige_get_escola_perfil')) {
            $perfil = sige_get_escola_perfil();
            if (!empty($perfil->nome_escola)) return (string)$perfil->nome_escola;
        }
        if (!empty($config_row->nome_escola)) return (string)$config_row->nome_escola;
        return (string)get_bloginfo('name');
    }
}

if (!function_exists('sige_wpp_tpl_moeda')) {
    function sige_wpp_tpl_moeda(): string {
        return function_exists('sige_moeda') ? (string)sige_moeda() : 'MT';
    }
}

if (!function_exists('sige_wpp_tpl_format_valor')) {
    function sige_wpp_tpl_format_valor(float $valor): string {
        // Formato Mozambicano: 12.345,67 MT
        return number_format($valor, 2, ',', '.') . ' ' . sige_wpp_tpl_moeda();
    }
}

if (!function_exists('sige_wpp_tpl_format_data')) {
    function sige_wpp_tpl_format_data(string $data_raw): string {
        if (trim($data_raw) === '') return '';
        $ts = strtotime($data_raw);
        return $ts ? wp_date('d/m/Y', $ts) : $data_raw;
    }
}


if (!function_exists('sige_wpp_tpl_escola_sujeito')) {
    /**
     * Devolve o nome da escola como sujeito gramatical natural.
     * Ex.: "O Colégio e Jardim Infantil Malisa", "A Escola Primária...".
     */
    function sige_wpp_tpl_escola_sujeito(string $nome, bool $capitalizar = true): string {
        $nome = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($nome)));
        if ($nome === '') return $capitalizar ? 'A escola' : 'a escola';
        $n = function_exists('mb_strtolower') ? mb_strtolower(remove_accents($nome), 'UTF-8') : strtolower(remove_accents($nome));
        $masc = ['colegio','liceu','instituto','centro','jardim','externato'];
        $fem  = ['escola','creche','academia','universidade','faculdade'];
        $art = '';
        foreach ($masc as $w) { if (strpos($n, $w) === 0) { $art = $capitalizar ? 'O ' : 'o '; break; } }
        if ($art === '') {
            foreach ($fem as $w) { if (strpos($n, $w) === 0) { $art = $capitalizar ? 'A ' : 'a '; break; } }
        }
        return $art !== '' ? ($art . $nome) : $nome;
    }
}

if (!function_exists('sige_wpp_tpl_limpar_texto_humano')) {
    function sige_wpp_tpl_limpar_texto_humano(string $txt): string {
        $txt = (string)$txt;
        $txt = preg_replace('/_([^_\n]{1,80})_/u', '$1', $txt);
        $txt = str_replace(['__', '_'], '', $txt);
        $txt = preg_replace('/\bdo\(a\)\s+/iu', 'de ', $txt);
        $txt = preg_replace('/\bao\(à\)\s+/iu', 'a ', $txt);
        $txt = preg_replace('/\bo\(a\)\s+/iu', '', $txt);
        $txt = preg_replace('/\bdo\/da\s+/iu', 'de ', $txt);
        return trim($txt);
    }
}

if (!function_exists('sige_wpp_tpl_compor_destinatario')) {
    /**
     * Devolve a forma de tratamento adequada para o início da mensagem:
     *  - Se temos um nome real: usa o primeiro nome ("Olá João,")
     *  - Caso contrário: forma neutra ("Olá,")
     */
    function sige_wpp_tpl_compor_destinatario(string $nome): string {
        $nome = trim($nome);
        if ($nome === '' || stripos($nome, 'encarregado') !== false) {
            return '';
        }
        $primeiro = sige_wpp_tpl_primeiro_nome($nome);
        return $primeiro !== '' ? $primeiro : '';
    }
}


if (!function_exists('sige_wpp_tpl_genero_aluno')) {
    /**
     * Devolve genero normalizado do aluno quando existe no cadastro.
     * Aceita os campos mais comuns usados no histórico do SIGE: genero, sexo.
     */
    function sige_wpp_tpl_genero_aluno($aluno_row): string {
        $raw = '';
        if (is_object($aluno_row)) {
            if (isset($aluno_row->genero)) $raw = (string)$aluno_row->genero;
            elseif (isset($aluno_row->sexo)) $raw = (string)$aluno_row->sexo;
        }
        $g = function_exists('mb_strtolower') ? mb_strtolower(remove_accents(trim($raw)), 'UTF-8') : strtolower(remove_accents(trim($raw)));
        if (in_array($g, ['m','masculino','homem','rapaz','male'], true)) return 'm';
        if (in_array($g, ['f','feminino','mulher','rapariga','female'], true)) return 'f';
        return '';
    }
}

if (!function_exists('sige_wpp_tpl_aluno_referencia')) {
    /**
     * Referência gramaticalmente correcta ao estudante quando a frase exige
     * "referente a...". Se o género não estiver confirmado, usa o nome sem
     * forçar "aluno(a)" nem transformar todo pagamento em matrícula.
     */
    function sige_wpp_tpl_aluno_referencia($aluno_row, bool $usar_nome_completo = false): string {
        $nome = trim((string)($aluno_row->nome_completo ?? ''));
        if ($nome === '') return 'ao aluno';
        $nome_ref = $usar_nome_completo ? $nome : sige_wpp_tpl_primeiro_nome($nome);
        if ($nome_ref === '') $nome_ref = $nome;
        $g = sige_wpp_tpl_genero_aluno($aluno_row);
        if ($g === 'm') return 'ao aluno ' . $nome_ref;
        if ($g === 'f') return 'à aluna ' . $nome_ref;
        return 'a ' . $nome_ref;
    }
}

if (!function_exists('sige_wpp_tpl_aluno_posse')) {
    /**
     * Forma possessiva para contextos como "mensalidade do aluno Aibo" ou
     * "transporte escolar da aluna Amina". Quando o género é incerto, usa
     * "de Nome", que é gramaticalmente seguro em PT-MZ.
     */
    function sige_wpp_tpl_aluno_posse($aluno_row, bool $usar_nome_completo = false): string {
        $nome = trim((string)($aluno_row->nome_completo ?? ''));
        if ($nome === '') return 'do aluno';
        $nome_ref = $usar_nome_completo ? $nome : sige_wpp_tpl_primeiro_nome($nome);
        if ($nome_ref === '') $nome_ref = $nome;
        $g = sige_wpp_tpl_genero_aluno($aluno_row);
        if ($g === 'm') return 'do aluno ' . $nome_ref;
        if ($g === 'f') return 'da aluna ' . $nome_ref;
        return 'de ' . $nome_ref;
    }
}

if (!function_exists('sige_wpp_tpl_normalizar_servico_texto')) {
    function sige_wpp_tpl_normalizar_servico_texto(string $txt): string {
        $txt = wp_strip_all_tags($txt);
        $txt = html_entity_decode($txt, ENT_QUOTES, 'UTF-8');
        $txt = function_exists('remove_accents') ? remove_accents($txt) : $txt;
        $txt = function_exists('mb_strtolower') ? mb_strtolower($txt, 'UTF-8') : strtolower($txt);
        $txt = preg_replace('/[^a-z0-9\s]+/u', ' ', $txt);
        return trim(preg_replace('/\s+/u', ' ', (string)$txt));
    }
}

if (!function_exists('sige_wpp_tpl_detectar_servicos_financeiros')) {
    /**
     * Detecta, a partir dos detalhes do lançamento/pagamento, o tipo real de
     * serviço. Isto evita escrever "matrícula" quando o item é mensalidade,
     * transporte, exame, uniforme ou outro serviço escolar.
     */
    function sige_wpp_tpl_detectar_servicos_financeiros(string $detalhes): array {
        $t = sige_wpp_tpl_normalizar_servico_texto($detalhes);
        $found = [];
        $add = function(string $k) use (&$found) { if (!in_array($k, $found, true)) $found[] = $k; };

        if (preg_match('/\b(mensalidade|propina|mensalidades|mes)\b/u', $t)) $add('mensalidade');
        if (preg_match('/\b(transporte|rota|autocarro|carrinha|machimbombo)\b/u', $t)) $add('transporte');
        if (preg_match('/\b(inscricao|inscricoes)\b/u', $t)) $add('inscricao');
        if (preg_match('/\b(renovacao|renovacoes|rematricula|rematriculas)\b/u', $t)) $add('renovacao');
        if (preg_match('/\b(matricula|matriculas)\b/u', $t)) $add('matricula');
        if (preg_match('/\b(exame|exames|avaliacao|avaliacoes)\b/u', $t)) $add('exame');
        if (preg_match('/\b(uniforme|uniformes|material|materiais|livro|livros|caderno|cadernos)\b/u', $t)) $add('material');
        if (preg_match('/\b(alimentacao|lanche|refeicao|refeicoes|cantina)\b/u', $t)) $add('alimentacao');
        if (preg_match('/\b(actividade|actividades|atividade|atividades|excursao|excursao|visita)\b/u', $t)) $add('actividade');
        if (preg_match('/\b(biblioteca|emprestimo|emprestimos)\b/u', $t)) $add('biblioteca');
        if (preg_match('/\b(servico|servicos|taxa|taxas|extra|extras)\b/u', $t) && empty($found)) $add('servicos');

        return $found;
    }
}

if (!function_exists('sige_wpp_tpl_servico_frase_pagamento')) {
    function sige_wpp_tpl_servico_frase_pagamento(array $servicos): string {
        $map = [
            'mensalidade' => 'da mensalidade',
            'transporte'  => 'do transporte escolar',
            'inscricao'   => 'da inscrição',
            'renovacao'   => 'da renovação da matrícula',
            'matricula'   => 'da matrícula',
            'exame'       => 'dos exames',
            'material'    => 'do material escolar',
            'alimentacao' => 'da alimentação',
            'actividade'  => 'da actividade escolar',
            'biblioteca'  => 'da biblioteca',
            'servicos'    => 'dos serviços escolares',
        ];
        $parts = [];
        foreach ($servicos as $s) {
            if (isset($map[$s]) && !in_array($map[$s], $parts, true)) $parts[] = $map[$s];
        }
        if (empty($parts)) return 'dos serviços escolares';
        if (count($parts) === 1) return $parts[0];
        if (count($parts) === 2) return $parts[0] . ' e ' . $parts[1];
        return 'dos serviços escolares';
    }
}

if (!function_exists('sige_wpp_tpl_servico_frase_sobre')) {
    function sige_wpp_tpl_servico_frase_sobre(array $servicos): string {
        $map = [
            'mensalidade' => 'a mensalidade',
            'transporte'  => 'o transporte escolar',
            'inscricao'   => 'a inscrição',
            'renovacao'   => 'a renovação da matrícula',
            'matricula'   => 'a matrícula',
            'exame'       => 'os exames',
            'material'    => 'o material escolar',
            'alimentacao' => 'a alimentação',
            'actividade'  => 'a actividade escolar',
            'biblioteca'  => 'a biblioteca',
            'servicos'    => 'os serviços escolares',
        ];
        $parts = [];
        foreach ($servicos as $s) {
            if (isset($map[$s]) && !in_array($map[$s], $parts, true)) $parts[] = $map[$s];
        }
        if (empty($parts)) return 'os serviços escolares';
        if (count($parts) === 1) return $parts[0];
        if (count($parts) === 2) return $parts[0] . ' e ' . $parts[1];
        return 'os serviços escolares';
    }
}

if (!function_exists('sige_wpp_tpl_contexto_pagamento')) {
    function sige_wpp_tpl_contexto_pagamento(string $detalhes, $aluno_row): string {
        $servicos = sige_wpp_tpl_detectar_servicos_financeiros($detalhes);
        return sige_wpp_tpl_servico_frase_pagamento($servicos) . ' ' . sige_wpp_tpl_aluno_posse($aluno_row, false);
    }
}

if (!function_exists('sige_wpp_tpl_contexto_financeiro')) {
    function sige_wpp_tpl_contexto_financeiro(string $detalhes, $aluno_row): string {
        $servicos = sige_wpp_tpl_detectar_servicos_financeiros($detalhes);
        return 'sobre ' . sige_wpp_tpl_servico_frase_sobre($servicos) . ' ' . sige_wpp_tpl_aluno_posse($aluno_row, false);
    }
}

if (!function_exists('sige_wpp_tpl_contexto_situacao')) {
    function sige_wpp_tpl_contexto_situacao(string $detalhes, $aluno_row): string {
        $servicos = sige_wpp_tpl_detectar_servicos_financeiros($detalhes);
        return 'relacionada com ' . sige_wpp_tpl_servico_frase_sobre($servicos) . ' ' . sige_wpp_tpl_aluno_posse($aluno_row, false);
    }
}

if (!function_exists('sige_wpp_tpl_tratamento_encarregado')) {
    /**
     * Tratamento do destinatário com respeito ao tipo do contacto.
     * - pai  -> Sr.
     * - mãe  -> Sra.
     * - genérico -> usa heurística conservadora existente; se não houver confiança,
     *   usa apenas o primeiro nome ou "Encarregado de Educação".
     */
    function sige_wpp_tpl_tratamento_encarregado(string $nome = '', string $tipo_contacto = ''): string {
        $nome = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($nome)));
        $tipo = sanitize_key(remove_accents($tipo_contacto));
        $primeiro = $nome !== '' ? sige_wpp_tpl_primeiro_nome($nome) : '';
        if ($primeiro !== '') {
            if (function_exists('mb_convert_case')) $primeiro = mb_convert_case($primeiro, MB_CASE_TITLE, 'UTF-8');
            else $primeiro = ucfirst(strtolower($primeiro));
        }
        if ($primeiro !== '') {
            if ($tipo === 'mae' || $tipo === 'mãe') return 'Sra. ' . $primeiro;
            if ($tipo === 'pai') return 'Sr. ' . $primeiro;
            if (function_exists('sige_notify_human_guess_title')) {
                $titulo = sige_notify_human_guess_title($primeiro);
                if ($titulo !== '') return $titulo . ' ' . $primeiro;
            }
            return $primeiro;
        }
        return 'Encarregado de Educação';
    }
}

// ============================================================================
// BIBLIOTECA - ABERTURAS POR CENÁRIO
// ----------------------------------------------------------------------------
// Nota sobre o uso: cada item pode conter os marcadores {saudacao}, {nome} e
// {escola} que são substituídos no momento da composição.
// ============================================================================

if (!function_exists('sige_wpp_tpl_aberturas_pagamento')) {
    function sige_wpp_tpl_aberturas_pagamento(): array {
        return [
            "{saudacao}{virgula_nome}.
Confirmamos que o pagamento {contexto_pagamento} foi recebido e registado.",
            "{saudacao}{virgula_nome}.
O pagamento {contexto_pagamento} ficou devidamente registado. Partilhamos abaixo o resumo.",
            "{saudacao}{virgula_nome}.
Recebemos o pagamento {contexto_pagamento}. Segue o resumo para o seu controlo.",
            "{saudacao}{virgula_nome}.
Fica confirmada a recepção do pagamento {contexto_pagamento}.",
            "{saudacao}{virgula_nome}.
{escola_sujeito} confirma a recepção do pagamento {contexto_pagamento}.",
            "{saudacao}{virgula_nome}.
Confirmamos consigo que o pagamento {contexto_pagamento} já consta como recebido.",
            "{saudacao}{virgula_nome}.
Está tudo certo com o pagamento {contexto_pagamento}; deixamos abaixo o resumo.",
            "{saudacao}{virgula_nome}.
A secretaria confirma que o pagamento {contexto_pagamento} foi registado com sucesso.",
        ];
    }
}

if (!function_exists('sige_wpp_tpl_aberturas_mensalidade')) {
    function sige_wpp_tpl_aberturas_mensalidade(): array {
        return [
            "{saudacao}{virgula_nome}.
Partilhamos consigo a informação financeira {contexto_financeiro}.",
            "{saudacao}{virgula_nome}.
{escola_sujeito} deixa consigo a informação financeira {contexto_financeiro}.",
            "{saudacao}{virgula_nome}.
Segue o detalhe {contexto_financeiro}, para o seu controlo.",
            "{saudacao}{virgula_nome}.
A informação financeira {contexto_financeiro} ficou disponível para pagamento.",
            "{saudacao}{virgula_nome}.
Partilhamos, de forma resumida, a informação financeira {contexto_financeiro}.",
            "{saudacao}{virgula_nome}.
Deixamos consigo o ponto financeiro {contexto_financeiro}.",
            "{saudacao}{virgula_nome}.
Fica abaixo o detalhe {contexto_financeiro}.",
            "{saudacao}{virgula_nome}.
Para mantermos a informação alinhada consigo, partilhamos o detalhe {contexto_financeiro}.",
        ];
    }
}

if (!function_exists('sige_wpp_tpl_aberturas_cobranca')) {
    function sige_wpp_tpl_aberturas_cobranca(): array {
        // Tom humano, formal e B2C: conversa de escola, não disparo de cobrança.
        return [
            "{saudacao}{virgula_nome}.
Gostaríamos de confirmar consigo uma situação financeira {contexto_situacao}.",
            "{saudacao}{virgula_nome}.
Estamos a rever algumas contas e surgiu uma situação {contexto_situacao} que gostaríamos de alinhar consigo.",
            "{saudacao}{virgula_nome}.
Escrevemos para verificar consigo, com calma, um valor em aberto {contexto_situacao}.",
            "{saudacao}{virgula_nome}.
Estamos a fazer o ponto da tesouraria e gostaríamos de confirmar consigo a situação {contexto_situacao}.",
            "{saudacao}{virgula_nome}.
{escola_sujeito} identificou um valor por regularizar {contexto_situacao} e preferimos confirmar consigo primeiro.",
            "{saudacao}{virgula_nome}.
Deixamos consigo este ponto para confirmação e eventual regularização.",
            "{saudacao}{virgula_nome}.
Sabemos que podem existir atrasos ou acertos por confirmar; por isso escrevemos para alinhar consigo a situação {contexto_situacao}.",
            "{saudacao}{virgula_nome}.
Gostaríamos de conversar consigo sobre um valor que aparece em aberto {contexto_situacao}.",
        ];
    }
}

// ============================================================================
// BIBLIOTECA - MIOLOS (apresentação do detalhe)
// ============================================================================

if (!function_exists('sige_wpp_tpl_miolos_pagamento')) {
    function sige_wpp_tpl_miolos_pagamento(): array {
        return [
            "Resumo do pagamento:
{detalhes}
Valor recebido: {valor}.",
            "Detalhe do pagamento:
{detalhes}
Valor pago: {valor}.",
            "Resumo para o seu controlo:
{detalhes}
Total recebido: {valor}.",
            "Ficou registado o seguinte:
{detalhes}
Total recebido: {valor}.",
            "Resumo do valor recebido:
{detalhes}
Montante recebido: {valor}.",
        ];
    }
}

if (!function_exists('sige_wpp_tpl_miolos_mensalidade')) {
    function sige_wpp_tpl_miolos_mensalidade(): array {
        return [
            "Detalhe:
{detalhes}
Valor: {valor}
Vencimento: {vencimento}.",
            "Composição:
{detalhes}
Total: {valor}
Data de vencimento: {vencimento}.",
            "Para o seu controlo:
{detalhes}
Montante: {valor}
Prazo indicado: {vencimento}.",
            "Resumo da mensalidade:
{detalhes}
Valor a pagar: {valor}
Vencimento: {vencimento}.",
            "Informação financeira:
{detalhes}
Valor: {valor}
Vencimento: {vencimento}.",
        ];
    }
}

if (!function_exists('sige_wpp_tpl_miolos_cobranca')) {
    function sige_wpp_tpl_miolos_cobranca(): array {
        return [
            "O valor que aparece em aberto é:
{detalhes}
Total por regularizar: {valor}.",
            "Para o seu controlo, temos esta situação:
{detalhes}
Montante em aberto: {valor}.",
            "O detalhe que temos na secretaria é:
{detalhes}
Total: {valor}.",
            "Neste momento, consta o seguinte:
{detalhes}
Valor por regularizar: {valor}.",
            "Resumo da situação:
{detalhes}
Montante: {valor}.",
        ];
    }
}

// ============================================================================
// BIBLIOTECA - FECHOS / CONVITES À CONVERSA
// ============================================================================

if (!function_exists('sige_wpp_tpl_fechos_pagamento')) {
    function sige_wpp_tpl_fechos_pagamento(): array {
        return [
            "Deseja que enviemos o link do recibo por esta conversa?",
            "Podemos partilhar consigo o link do recibo por aqui?",
            "Pretende receber o comprovativo digital nesta conversa?",
            "Quer que a secretaria envie o link do recibo por WhatsApp?",
            "Fica bem para si receber o link do recibo por este número?",
            "Podemos seguir com o envio do comprovativo digital por aqui?",
        ];
    }
}

if (!function_exists('sige_wpp_tpl_fechos_mensalidade')) {
    function sige_wpp_tpl_fechos_mensalidade(): array {
        return [
            "Qualquer dúvida, responda por aqui que acompanhamos consigo.",
            "Se precisar de esclarecer algum detalhe ou combinar uma data, estamos disponíveis.",
            "Caso haja alguma diferença, diga-nos por favor para conferirmos com calma.",
            "Se quiser falar sobre a melhor forma de pagamento, pode responder por aqui.",
            "Agradecemos a sua atenção. Estamos disponíveis para qualquer esclarecimento.",
            "Se precisar de apoio, basta responder a esta mensagem.",
        ];
    }
}

if (!function_exists('sige_wpp_tpl_fechos_cobranca')) {
    function sige_wpp_tpl_fechos_cobranca(): array {
        return [
            "Se já regularizou, pedimos desculpa pelo incómodo; responda por aqui para confirmarmos. Se ainda não foi possível, podemos combinar consigo a melhor forma.",
            "Caso já tenha tratado, diga-nos por favor para actualizarmos o registo. Se ainda estiver pendente, estamos disponíveis para conversar.",
            "Se houver alguma dificuldade neste momento, fale connosco com calma para encontrarmos uma solução possível.",
            "Pode ter sido apenas um desencontro de informação; se já pagou, responda por aqui para conferirmos.",
            "Se precisar de alinhar prazo ou forma de pagamento, estamos disponíveis para conversar consigo.",
            "Agradecemos a sua atenção. Se esta informação não estiver correcta, diga-nos para verificarmos.",
        ];
    }
}

// ============================================================================
// COMPOSIÇÃO PRINCIPAL - sige_wpp_tpl_v2_render()
// ----------------------------------------------------------------------------
// Devolve a mensagem final (sem link, que é tratado a jusante pelo Guardian).
// ============================================================================

if (!function_exists('sige_wpp_tpl_v2_render')) {
    function sige_wpp_tpl_v2_render(string $tipo, $config_row, $aluno_row, array $vars = []): string {
        $tipo = strtolower(trim($tipo));

        // ── Variáveis comuns ────────────────────────────────────────────────
        $escola = sige_wpp_tpl_escola_nome($config_row);
        $escola_sujeito = function_exists('sige_wpp_tpl_escola_sujeito') ? sige_wpp_tpl_escola_sujeito($escola, true) : $escola;
        $aluno_completo = (string)($aluno_row->nome_completo ?? '');
        $aluno_primeiro = sige_wpp_tpl_primeiro_nome($aluno_completo);
        if ($aluno_primeiro === '') $aluno_primeiro = 'estudante';

        // [v12.9.58] Saudação usa nome do ENCARREGADO. Fallback fixo:
        // "Encarregado de Educação" quando não há nome do encarregado registado
        // no formulário do aluno (nome_pai/nome_mae/contacto_encarregado vazios).
        $encarregado_completo = '';
        if (!empty($vars['encarregado'])) {
            $cand = trim((string)$vars['encarregado']);
            // Ignorar marcadores genéricos vindos de notificacoes-encarregados.php
            if ($cand !== '' && stripos($cand, 'Encarregado(a)') === false) {
                $encarregado_completo = $cand;
            }
        }
        if ($encarregado_completo === '' && !empty($aluno_row->nome_pai)) {
            $encarregado_completo = trim((string)$aluno_row->nome_pai);
        }
        if ($encarregado_completo === '' && !empty($aluno_row->nome_mae)) {
            $encarregado_completo = trim((string)$aluno_row->nome_mae);
        }
        if ($encarregado_completo === '' && !empty($aluno_row->contacto_encarregado)) {
            $encarregado_completo = trim((string)$aluno_row->contacto_encarregado);
        }

        $tipo_encarregado = isset($vars['encarregado_tipo']) ? sanitize_key(remove_accents((string)$vars['encarregado_tipo'])) : '';
        if (function_exists('sige_wpp_tpl_tratamento_encarregado')) {
            $saudacao_nome = sige_wpp_tpl_tratamento_encarregado($encarregado_completo, $tipo_encarregado);
        } elseif (function_exists('sige_notify_human_treatment')) {
            $saudacao_nome = sige_notify_human_treatment($encarregado_completo);
        } elseif ($encarregado_completo === '') {
            $saudacao_nome = 'Encarregado de Educação';
        } else {
            $primeiro = sige_wpp_tpl_primeiro_nome($encarregado_completo);
            $saudacao_nome = $primeiro !== '' ? $primeiro : $encarregado_completo;
        }

        $virgula_nome  = ', ' . $saudacao_nome;
        $virgula_aluno = $virgula_nome;

        $aluno_ref = function_exists('sige_wpp_tpl_aluno_referencia') ? sige_wpp_tpl_aluno_referencia($aluno_row, false) : ('a ' . $aluno_primeiro);

        $valor_num = isset($vars['valor']) ? (float)$vars['valor'] : 0.0;
        $valor_fmt = sige_wpp_tpl_format_valor($valor_num);

        $vencimento_fmt = isset($vars['vencimento']) ? sige_wpp_tpl_format_data((string)$vars['vencimento']) : '';
        $detalhes_str = trim((string)($vars['detalhes'] ?? ''));
        if (function_exists('sige_wpp_tpl_limpar_texto_humano')) {
            $detalhes_str = sige_wpp_tpl_limpar_texto_humano($detalhes_str);
        }
        if ($detalhes_str === '') {
            $detalhes_str = '- (sem detalhe registado)';
        } else {
            $detalhes_str = preg_replace('/^\*\s*/m', '• ', $detalhes_str);
        }

        $contexto_pagamento  = function_exists('sige_wpp_tpl_contexto_pagamento') ? sige_wpp_tpl_contexto_pagamento($detalhes_str, $aluno_row) : ('dos serviços escolares ' . (function_exists('sige_wpp_tpl_aluno_posse') ? sige_wpp_tpl_aluno_posse($aluno_row, false) : ('de ' . $aluno_primeiro)));
        $contexto_financeiro = function_exists('sige_wpp_tpl_contexto_financeiro') ? sige_wpp_tpl_contexto_financeiro($detalhes_str, $aluno_row) : ('sobre os serviços escolares ' . (function_exists('sige_wpp_tpl_aluno_posse') ? sige_wpp_tpl_aluno_posse($aluno_row, false) : ('de ' . $aluno_primeiro)));
        $contexto_situacao   = function_exists('sige_wpp_tpl_contexto_situacao') ? sige_wpp_tpl_contexto_situacao($detalhes_str, $aluno_row) : ('relacionada com os serviços escolares ' . (function_exists('sige_wpp_tpl_aluno_posse') ? sige_wpp_tpl_aluno_posse($aluno_row, false) : ('de ' . $aluno_primeiro)));

        $recibo_id = (string)($vars['id'] ?? '');

        // ── v12.11.3 - RECIBO DE PAGAMENTO COM CONCORDÂNCIA B2C ──────────────
        // Sem emojis, sem "sistema", sem aparência de disparo automático.
        if ($tipo === 'recibo' || strpos($tipo, 'pagamento') !== false) {
            $data_pag = isset($vars['data']) && $vars['data'] !== ''
                ? sige_wpp_tpl_format_data((string)$vars['data'])
                : wp_date('d/m/Y');

            $itens = trim($detalhes_str);
            if ($itens !== '' && stripos($itens, '- (sem detalhe') === false) {
                $linhas = preg_split('/\r?\n/', $itens) ?: [];
                $linhas = array_map(function($l) {
                    $l = trim(preg_replace('/^[•\-\*]\s*/u', '', (string)$l));
                    if (function_exists('sige_wpp_tpl_limpar_texto_humano')) { $l = sige_wpp_tpl_limpar_texto_humano($l); }
                    return $l === '' ? '' : '• ' . $l;
                }, $linhas);
                $linhas = array_filter($linhas, function($l) { return $l !== ''; });
                $itens = implode("\n", $linhas);
            } else {
                $itens = '• Pagamento recebido';
            }

            $abertura = sige_wpp_tpl_pick(sige_wpp_tpl_aberturas_pagamento());
            $miolo    = sige_wpp_tpl_pick(sige_wpp_tpl_miolos_pagamento());
            $fecho    = sige_wpp_tpl_pick(sige_wpp_tpl_fechos_pagamento());

            $subs_recibo = [
                '{saudacao}'      => sige_wpp_tpl_saudacao_temporal(),
                '{virgula_nome}'  => $virgula_nome,
                '{virgula_aluno}' => $virgula_aluno,
                '{escola}'        => $escola,
                '{escola_sujeito}'=> $escola_sujeito,
                '{aluno}'         => $aluno_primeiro,
                '{aluno_ref}'     => $aluno_ref,
                '{aluno_referencia}' => $aluno_ref,
                '{contexto_pagamento}'  => $contexto_pagamento,
                '{contexto_financeiro}' => $contexto_financeiro,
                '{contexto_situacao}'   => $contexto_situacao,
                '{aluno_completo}'=> $aluno_completo,
                '{detalhes}'      => $itens,
                '{valor}'         => $valor_fmt,
                '{vencimento}'    => $data_pag,
                '{id}'            => $recibo_id,
                '{recibo}'        => $recibo_id,
            ];

            $msg = strtr($abertura, $subs_recibo) . "\n\n" . strtr($miolo, $subs_recibo);
            if ($recibo_id !== '') {
                $msg .= "\nRecibo: #" . $recibo_id;
            }
            $msg .= "\nData: " . $data_pag;
            $msg .= "\n\n" . strtr($fecho, $subs_recibo);

            $msg = preg_replace("/\r\n|\r/", "\n", $msg);
            $msg = preg_replace("/\n{3,}/", "\n\n", $msg);
            $msg = trim($msg);
            return function_exists('sige_notify_humanize_outbound_whatsapp')
                ? sige_notify_humanize_outbound_whatsapp($msg, $tipo, ['aluno_id' => (int)($aluno_row->id ?? 0)])
                : $msg;
        }

        // ── Restantes cenários (mensalidade / cobrança) ───────────────────
        if ($tipo === 'fatura' || strpos($tipo, 'mensalidade') !== false || strpos($tipo, 'lanc') !== false) {
            $abertura = sige_wpp_tpl_pick(sige_wpp_tpl_aberturas_mensalidade());
            $miolo    = sige_wpp_tpl_pick(sige_wpp_tpl_miolos_mensalidade());
            $fecho    = sige_wpp_tpl_pick(sige_wpp_tpl_fechos_mensalidade());
        } else {
            $abertura = sige_wpp_tpl_pick(sige_wpp_tpl_aberturas_cobranca());
            $miolo    = sige_wpp_tpl_pick(sige_wpp_tpl_miolos_cobranca());
            $fecho    = sige_wpp_tpl_pick(sige_wpp_tpl_fechos_cobranca());
        }

        $subs = [
            '{saudacao}'      => sige_wpp_tpl_saudacao_temporal(),
            '{virgula_nome}'  => $virgula_nome,
            '{virgula_aluno}' => $virgula_aluno,
            '{escola}'        => $escola,
            '{escola_sujeito}'=> $escola_sujeito,
            '{aluno}'         => $aluno_primeiro,
            '{aluno_ref}'     => $aluno_ref,
            '{aluno_referencia}' => $aluno_ref,
            '{contexto_pagamento}'  => $contexto_pagamento,
            '{contexto_financeiro}' => $contexto_financeiro,
            '{contexto_situacao}'   => $contexto_situacao,
            '{aluno_completo}'=> $aluno_completo,
            '{detalhes}'      => $detalhes_str,
            '{valor}'         => $valor_fmt,
            '{vencimento}'    => $vencimento_fmt,
            '{id}'            => $recibo_id,
            '{recibo}'        => $recibo_id,
        ];

        $msg = strtr($abertura, $subs) . "\n\n" . strtr($miolo, $subs);
        $msg .= "\n\n" . strtr($fecho, $subs);

        $msg = preg_replace('/\*+/', '', $msg);
        $msg = preg_replace("/\r\n|\r/", "\n", $msg);
        $msg = preg_replace("/\n{3,}/", "\n\n", $msg);
        $msg = trim($msg);

        return function_exists('sige_notify_humanize_outbound_whatsapp')
            ? sige_notify_humanize_outbound_whatsapp($msg, $tipo, ['aluno_id' => (int)($aluno_row->id ?? 0)])
            : $msg;
    }
}

// ============================================================================
// HOOK NO RENDERER ANTIGO - substituição transparente
// ----------------------------------------------------------------------------
// O whatsapp-engine.php define sige_wpp_render_finance_template() apenas se
// ainda não existir. Carregamos este ficheiro ANTES do carregamento que possa
// definir a versão antiga, ou usamos um filtro de saída se o original já
// existir. Neste plugin garantimos a ordem via sige-softgenial.php (carregado
// imediatamente a seguir a whatsapp-engine.php), e aqui registamos um filtro
// caso a opção de filtro esteja disponível em versões futuras.
//
// A estratégia aplicada nesta release: sobrescrever a definição via condicional
// no whatsapp-engine.php (já alterado neste pacote para delegar quando
// sige_wpp_tpl_v2_render existe).
// ============================================================================

// ============================================================================
// GUARDA ANTI-CRON - bloqueia envios financeiros automatizados
// ----------------------------------------------------------------------------
// Os 3 cenários financeiros (fatura/recibo/cobranca) são SEMPRE de origem
// humana. Se algum trigger automático tentar enfileirar uma mensagem destes
// tipos durante DOING_CRON ou wp_doing_cron(), bloqueamos e registamos.
// O lembrete pré-vencimento (cron-tasks.php:554) já é no-op, mas esta camada
// existe como defesa em profundidade.
// ============================================================================

if (!function_exists('sige_wpp_tpl_origem_eh_cron')) {
    function sige_wpp_tpl_origem_eh_cron(): bool {
        if (function_exists('wp_doing_cron') && wp_doing_cron()) return true;
        if (defined('DOING_CRON') && DOING_CRON) return true;
        // wp-cron.php directo
        if (!empty($_GET['doing_wp_cron'])) return true;
        return false;
    }
}

if (!function_exists('sige_wpp_tpl_block_cron_origin')) {
    /**
     * Devolve true se a chamada actual deve ser BLOQUEADA por ser automatizada
     * para um tipo financeiro protegido. Regista a tentativa em log.
     */
    function sige_wpp_tpl_block_cron_origin(string $tipo, int $aluno_id = 0, string $telefone = ''): bool {
        if (!sige_wpp_tpl_origem_eh_cron()) return false;

        $tipo_low = strtolower(trim($tipo));
        $protegidos = array_filter(array_map('trim', explode(',', SIGE_WPP_TPL_TIPOS_PROTEGIDOS)));
        $bloqueia = false;
        foreach ($protegidos as $p) {
            if ($p === '' ) continue;
            if ($tipo_low === $p || strpos($tipo_low, $p) !== false) {
                $bloqueia = true;
                break;
            }
        }
        if (!$bloqueia) return false;

        if (function_exists('sige_fin_log')) {
            sige_fin_log('wpp_envio_cron_bloqueado', [
                'tipo'      => $tipo,
                'aluno_id'  => $aluno_id,
                'telefone'  => $telefone,
                'motivo'    => 'Envio automático bloqueado: mensagens financeiras só seguem com origem humana.',
                'timestamp' => current_time('mysql'),
            ]);
        }
        return true;
    }
}

// ============================================================================
// MIGRAÇÃO - aplicar v2 quando a versão sobe
// ----------------------------------------------------------------------------
// 1. Limpa msg_nova_fatura, msg_recibo_pago, msg_cobranca em wp_sige_config
//    para forçar uso do default novo (defaults agora vêm do renderer v2).
// 2. v3 hotfix: percorre TODAS as mensagens 'pendente'/'forcar_envio' dos
//    tipos protegidos (sem limite de 72h) e:
//      a) se a mensagem tem padrões de texto antigo (asteriscos múltiplos,
//         "Aviso de Pendências", footer SIM/NÃO/PARAR, "Solicitamos a
//         regularização", emojis 📋 ⚠️ 💰 em headers) → re-renderiza com v2
//         se conseguir extrair vars; senão CANCELA o envio (status='cancelado')
//         para impedir que mensagens antigas escapem para os pais.
//      b) caso contrário, deixa intacta.
// 3. Strippa o footer "Para continuar a receber avisos…" de qualquer
//    mensagem pendente onde apareça.
// 4. Marca a versão aplicada em option SIGE_WPP_TPL_OPT_VERSION.
// 5. Regista linha em sige_fin_log com event 'wpp_template_v3_aplicado'.
// ============================================================================

if (!function_exists('sige_wpp_tpl_tem_padrao_legado')) {
    /**
     * Detecta se uma mensagem usa padrões do template antigo:
     *  - "Aviso de Pendências" no início
     *  - mais de 6 asteriscos (uso forte de negrito)
     *  - footer "Para continuar a receber avisos da escola por WhatsApp"
     *  - emojis em headers (📋, 💰, ⚠️, 🔔 nas primeiras 60 chars)
     *  - "Solicitamos a regularização o mais breve possível"
     *  - "Efectue o pagamento atempadamente"
     */
    function sige_wpp_tpl_tem_padrao_legado(string $msg): bool {
        if (strpos($msg, 'Aviso de Pendências') !== false) return true;
        if (strpos($msg, 'Pagamento Confirmado') !== false) return true;
        if (strpos($msg, 'Documento gerado automaticamente') !== false) return true;
        if (strpos($msg, 'Mensagem enviada automaticamente') !== false) return true;
        if (strpos($msg, 'SIGE:') !== false) return true;
        if (strpos($msg, 'Para continuar a receber avisos da escola por WhatsApp') !== false) return true;
        if (strpos($msg, 'Solicitamos a regularização') !== false) return true;
        if (strpos($msg, 'Efectue o pagamento atempadamente') !== false) return true;
        if (strpos($msg, 'Efectue o pagamento até à data para evitar') !== false) return true;
        if (preg_match('/⚠️\s*\*Pagamento em atraso\*/u', $msg)) return true;
        if (preg_match('/📋\s*\*Mensalidade pendente\*/u', $msg)) return true;
        if (preg_match('/🔔\s*\*Lembrete/u', $msg)) return true;

        // Mais de 6 asteriscos sugere uso massivo de negrito (formato antigo)
        $asteriscos = substr_count($msg, '*');
        if ($asteriscos >= 6) return true;

        // Header com emoji nas primeiras 60 chars
        $header = substr($msg, 0, 60);
        if (preg_match('/[📋💰🔔⚠️📅]/u', $header)) return true;

        return false;
    }
}

if (!function_exists('sige_wpp_tpl_strip_footer_consent')) {
    /**
     * Remove o footer SIM/NÃO/PARAR e variantes de qualquer mensagem.
     */
    function sige_wpp_tpl_strip_footer_consent(string $msg): string {
        $patterns = [
            '/\s*Para continuar a receber avisos da escola por WhatsApp[,\s]*responda SIM\.\s*Se preferir parar[,\s]*responda NÃO ou PARAR\.?\s*$/iu',
            '/\s*Se preferir não receber avisos por WhatsApp[,\s]*responda PARAR\.?\s*$/iu',
            '/\s*Para parar de receber[,\s]*responda PARAR\.?\s*$/iu',
        ];
        foreach ($patterns as $p) {
            $msg = preg_replace($p, '', $msg);
        }
        // Limpar quebras múltiplas finais
        $msg = preg_replace("/\n{3,}/", "\n\n", $msg);
        return rtrim($msg);
    }
}

if (!function_exists('sige_wpp_tpl_aplicar_migracao')) {
    function sige_wpp_tpl_aplicar_migracao(): void {
        $aplicada = (string)get_option(SIGE_WPP_TPL_OPT_VERSION, '');
        if ($aplicada === SIGE_WPP_TPL_VERSION) return; // nada a fazer

        global $wpdb;
        $resultado = [
            'versao_anterior'    => $aplicada ?: '(nenhuma)',
            'versao_aplicada'    => SIGE_WPP_TPL_VERSION,
            'configs_limpas'     => 0,
            'mensagens_regen'    => 0,
            'mensagens_canceladas' => 0,
            'mensagens_footer_strip' => 0,
            'mensagens_falhadas' => 0,
            'timestamp'          => current_time('mysql'),
        ];

        // ── 1) Limpar templates persistidos por escola ──────────────────────
        $tCfg = $wpdb->prefix . 'sige_config';
        $existe_cfg = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tCfg));
        if ($existe_cfg === $tCfg) {
            $cols_existentes = $wpdb->get_col("SHOW COLUMNS FROM {$tCfg}");
            $cols_alvo = ['msg_nova_fatura', 'msg_recibo_pago', 'msg_cobranca'];
            $cols_validas = array_intersect($cols_alvo, $cols_existentes);
            if (!empty($cols_validas)) {
                $set_parts = [];
                foreach ($cols_validas as $c) {
                    $set_parts[] = "`{$c}` = ''";
                }
                $sql = "UPDATE {$tCfg} SET " . implode(', ', $set_parts);
                $resultado['configs_limpas'] = (int) $wpdb->query($sql);
            }
        }

        // ── 2+3) Re-renderizar / cancelar / strippar mensagens pendentes ────
        $tQ = $wpdb->prefix . 'sige_whatsapp_queue';
        $existe_q = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tQ));
        if ($existe_q === $tQ) {
            $tA = $wpdb->prefix . 'sige_alunos';

            // v3 hotfix: SEM limite de 72h. Toca tudo o que ainda não saiu.
            $linhas = $wpdb->get_results(
                "SELECT q.id, q.escola_id, q.aluno_id, q.telefone, q.tipo, q.mensagem
                 FROM {$tQ} q
                 WHERE q.status IN ('pendente', 'forcar_envio')
                   AND (
                        LOWER(q.tipo) LIKE 'fatura%' OR
                        LOWER(q.tipo) LIKE 'recibo%' OR
                        LOWER(q.tipo) LIKE 'cobranca%' OR
                        LOWER(q.tipo) LIKE 'lembrete%' OR
                        LOWER(q.tipo) LIKE 'alerta_%'
                   )
                 ORDER BY q.id DESC
                 LIMIT 20000"
            );

            $cols_q = $wpdb->get_col("SHOW COLUMNS FROM {$tQ}");
            $tem_guardian_meta = in_array('guardian_meta', $cols_q, true);

            foreach ($linhas as $row) {
                $eid = (int)$row->escola_id;
                $aid = (int)$row->aluno_id;
                $tipo_msg = (string)$row->tipo;
                $msg_actual = (string)$row->mensagem;

                $tem_legado = sige_wpp_tpl_tem_padrao_legado($msg_actual);

                if ($tem_legado) {
                    // Tentar re-renderizar; se não conseguir, CANCELAR.
                    $vars = sige_wpp_tpl_extrair_vars_legado($msg_actual);
                    $cfg_row = $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$tCfg} WHERE escola_id = %d LIMIT 1",
                        $eid
                    ));
                    $aluno_row = $aid > 0 ? $wpdb->get_row($wpdb->prepare(
                        "SELECT * FROM {$tA} WHERE id = %d AND escola_id = %d LIMIT 1",
                        $aid, $eid
                    )) : null;

                    if (!empty($vars) && $aluno_row && function_exists('sige_wpp_tpl_v2_render')) {
                        $tipo_render = (strpos(strtolower($tipo_msg), 'alerta_') === 0) ? 'cobranca' : $tipo_msg;
                        $nova = sige_wpp_tpl_v2_render($tipo_render, $cfg_row, $aluno_row, $vars);
                        if ($nova !== '' && !sige_wpp_tpl_tem_padrao_legado($nova)) {
                            $upd = ['mensagem' => $nova, 'tentativas' => 0, 'erro' => null];
                            $upd_fmt = ['%s', '%d', '%s'];
                            if ($tem_guardian_meta) {
                                $upd['guardian_meta'] = wp_json_encode([
                                    'regenerated_at' => current_time('mysql'),
                                    'tpl_version'    => SIGE_WPP_TPL_VERSION,
                                    'origem'         => 'migration_v3_regen',
                                ]);
                                $upd_fmt[] = '%s';
                            }
                            $wpdb->update($tQ, $upd, ['id' => (int)$row->id], $upd_fmt, ['%d']);
                            $resultado['mensagens_regen']++;
                            continue;
                        }
                    }

                    // Não conseguiu re-renderizar com segurança - CANCELA.
                    $upd = [
                        'status' => 'cancelado',
                        'erro'   => 'Cancelada por segurança: modelo de mensagem desactualizado.',
                    ];
                    $upd_fmt = ['%s', '%s'];
                    if ($tem_guardian_meta) {
                        $upd['guardian_meta'] = wp_json_encode([
                            'cancelled_at' => current_time('mysql'),
                            'tpl_version'  => SIGE_WPP_TPL_VERSION,
                            'origem'       => 'migration_v3_cancel',
                            'motivo'       => 'padrão legado sem vars suficientes para regenerar',
                        ]);
                        $upd_fmt[] = '%s';
                    }
                    $wpdb->update($tQ, $upd, ['id' => (int)$row->id], $upd_fmt, ['%d']);
                    $resultado['mensagens_canceladas']++;
                } else {
                    // Sem padrão legado, mas pode ter footer SIM/NÃO/PARAR
                    $sem_footer = sige_wpp_tpl_strip_footer_consent($msg_actual);
                    if ($sem_footer !== $msg_actual) {
                        $upd = ['mensagem' => $sem_footer];
                        $wpdb->update($tQ, $upd, ['id' => (int)$row->id], ['%s'], ['%d']);
                        $resultado['mensagens_footer_strip']++;
                    }
                }
            }
        }

        // ── 4) Marcar versão aplicada ───────────────────────────────────────
        update_option(SIGE_WPP_TPL_OPT_VERSION, SIGE_WPP_TPL_VERSION, false);

        // ── 5) Log ───────────────────────────────────────────────────────────
        if (function_exists('sige_fin_log')) {
            sige_fin_log('wpp_template_v3_aplicado', $resultado);
        }
    }
}

// ============================================================================
// EXTRACTOR LEGADO - recupera vars de mensagens antigas
// ----------------------------------------------------------------------------
// Quando re-renderizamos uma mensagem na fila precisamos de saber valor,
// vencimento e detalhes. Como esses dados não foram persistidos como JSON,
// fazemos extracção heurística do próprio texto. Se não conseguirmos algo
// confiável, devolvemos array vazio e a mensagem antiga fica intacta (a
// nova versão entra a partir do próximo lançamento).
// ============================================================================

if (!function_exists('sige_wpp_tpl_extrair_vars_legado')) {
    function sige_wpp_tpl_extrair_vars_legado(string $msg_antiga): array {
        $vars = [];

        // valor: aceitar tanto "4.500,00" (PT-MZ) como "4500.00" (US/numérico)
        // Padrão 1: PT-MZ com vírgula decimal - "12.345,67" ou "1234,67"
        if (preg_match('/(?:total(?:\s+(?:em\s+d[ií]vida|pago))?|valor(?:\s+total)?|montante)[\s\*:\-]+([\d\.\s]+,\d{2})/iu', $msg_antiga, $m)) {
            $raw = trim($m[1]);
            $raw = str_replace(['.', ' '], '', $raw);
            $raw = str_replace(',', '.', $raw);
            $val = (float)$raw;
            if ($val > 0) $vars['valor'] = $val;
        }
        // Padrão 2: numérico com ponto decimal - "4000.00" ou "4500"
        if (empty($vars['valor']) && preg_match('/(?:total(?:\s+(?:em\s+d[ií]vida|pago))?|valor(?:\s+total)?|montante)[\s\*:\-]+([\d]+(?:\.\d{2})?)\s*(?:MT|MZN)/iu', $msg_antiga, $m)) {
            $val = (float)$m[1];
            if ($val > 0) $vars['valor'] = $val;
        }

        // vencimento
        if (preg_match('/(?:vencimento(?:\s+mais\s+antigo)?|vence(?:m| em|\s+a)?|prazo|at[ée])\s*[:\-]?\s*(\d{2}[\/\-]\d{2}[\/\-]\d{4})/iu', $msg_antiga, $m)) {
            $vars['vencimento'] = $m[1];
        }

        // detalhes: linhas que começam por "•" ou "-" ou "*" depois de bullet
        if (preg_match_all('/^[\s]*(?:•|\-|\*)\s*(.+)$/mu', $msg_antiga, $m)) {
            $linhas = array_map('trim', $m[1]);
            // Remover linhas que são headers de secção ("Serviços em dívida:", "Total:", etc)
            $linhas = array_filter($linhas, function($l) {
                if (stripos($l, 'serviços em dívida') !== false) return false;
                if (stripos($l, 'total') !== false && stripos($l, 'MT') === false) return false;
                if (stripos($l, 'vencimento') !== false) return false;
                if (preg_match('/^\*+/', $l)) return false; // linhas só com asteriscos
                return $l !== '';
            });
            // Limpar resíduos de asteriscos dentro do texto da linha
            $linhas = array_map(function($l){ return trim(preg_replace('/\*+/', '', $l)); }, $linhas);
            $linhas = array_filter($linhas);
            if (!empty($linhas)) {
                $vars['detalhes'] = "• " . implode("\n• ", $linhas);
            }
        }

        // Se não conseguimos NADA, devolver vazio para preservar mensagem actual
        if (empty($vars)) return [];

        // Conservador: só consideramos vars válidas se tivermos valor > 0 E
        // detalhes não-vazios. Sem isto, a mensagem regenerada ficaria com
        // "(sem detalhe registado)" - é melhor cancelar e a escola reenvia
        // manualmente com os dados certos do que mandar uma mensagem sem
        // contexto.
        if (empty($vars['valor']) || $vars['valor'] <= 0) return [];
        if (empty($vars['detalhes']) || strpos((string)$vars['detalhes'], '(sem detalhe registado)') !== false) return [];

        return $vars;
    }
}

// ============================================================================
// BOOT - registar a migração no init (corre uma vez quando versão muda)
// ============================================================================

add_action('init', function () {
    // Só corre no contexto "real" (admin ou cron de WP), evita corrida em
    // pedidos AJAX leves; se for a primeira página carregada após upload do
    // plugin, esta passagem aplica.
    if (!is_admin() && !defined('DOING_CRON') && !wp_doing_cron() && !defined('REST_REQUEST')) {
        // Mesmo assim corremos: a migração é idempotente e barata.
    }
    sige_wpp_tpl_aplicar_migracao();
}, 30);

// Também aplicar via admin_init para cobrir o caso de Director que abre o
// admin imediatamente após o upload do plugin. Idempotente.
add_action('admin_init', function () {
    sige_wpp_tpl_aplicar_migracao();
}, 30);
