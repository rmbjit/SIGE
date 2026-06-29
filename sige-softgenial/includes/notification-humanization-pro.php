<?php
/**
 * SIGE SoftGenial - Notification Humanization PRO
 * Ficheiro: includes/notification-humanization-pro.php
 *
 * v12.11.3 - Concordância B2C Moçambique para notificações.
 *
 * Objectivo:
 * - retirar marcas visíveis de automação nas mensagens enviadas aos encarregados;
 * - aproximar o tom da comunicação de uma secretaria escolar real em Moçambique;
 * - manter opt-out/consentimento de forma humana, sem rodapés robóticos;
 * - sanear mensagens antigas antes de entrarem na fila;
 * - não alterar regras financeiras, académicas, curriculares ou base de dados de negócio.
 */
if (!defined('ABSPATH')) exit;

if (!defined('SIGE_NOTIFICATION_HUMANIZATION_PRO_VERSION')) {
    define('SIGE_NOTIFICATION_HUMANIZATION_PRO_VERSION', '12.11.9.30');
}

if (!function_exists('sige_notify_human_mz_tz')) {
    function sige_notify_human_mz_tz(): DateTimeZone {
        return new DateTimeZone(defined('SIGE_TIMEZONE') ? SIGE_TIMEZONE : 'Africa/Maputo');
    }
}

if (!function_exists('sige_notify_human_mz_saudacao')) {
    function sige_notify_human_mz_saudacao(): string {
        $h = (int)wp_date('H', null, sige_notify_human_mz_tz());
        if ($h >= 5 && $h < 12) return 'Bom dia';
        if ($h >= 12 && $h < 18) return 'Boa tarde';
        return 'Boa noite';
    }
}

if (!function_exists('sige_notify_human_first_name')) {
    function sige_notify_human_first_name(string $nome): string {
        $nome = trim(preg_replace('/\s+/u', ' ', wp_strip_all_tags($nome)));
        if ($nome === '') return '';
        if (preg_match('/(encarregado|educa[cç][aã]o|contacto|telefone|whatsapp)/iu', $nome)) return '';
        $parts = preg_split('/\s+/u', $nome) ?: [];
        $first = trim((string)($parts[0] ?? ''));
        $len = function_exists('mb_strlen') ? mb_strlen($first, 'UTF-8') : strlen($first);
        if ($len < 2) return '';
        if (function_exists('mb_convert_case')) {
            return mb_convert_case($first, MB_CASE_TITLE, 'UTF-8');
        }
        return ucfirst(strtolower($first));
    }
}


if (!function_exists('sige_notify_human_guess_title')) {
    /**
     * Tratamento conservador por primeiro nome.
     * Se não houver confiança suficiente, não força Sr./Sra. para evitar mensagens artificiais.
     */
    function sige_notify_human_guess_title(string $first): string {
        $n = function_exists('mb_strtolower') ? mb_strtolower(remove_accents(trim($first)), 'UTF-8') : strtolower(remove_accents(trim($first)));
        if ($n === '') return '';
        $male = [
            'abel','abdala','abdul','adriano','adolfo','agostinho','alcides','alexandre','alberto','alfredo','antonio','armando','augusto',
            'bacar','benjamim','benjamin','carlos','celio','daniel','david','edson','ernesto','eusebio','felipe','filipe','flavio','francisco',
            'helio','jaime','joao','jose','julio','manuel','miguel','milton','nelson','otavio','paulo','pedro','rafael','ricardo',
            'rogerio','romao','samuel','sergio','tomás','tomas','wilson'
        ];
        $female = [
            'alima','amina','ana','anita','beatriz','carla','celia','delfina','elisa','esmeralda','eunice','fatima','helena','iara','isabel','joana',
            'julieta','laura','maria','mercia','mércia','neide','paula','rita','sara','sofia','talita','tania','tânia','ursula','úrsula'
        ];
        if (in_array($n, $male, true)) return 'Sr.';
        if (in_array($n, $female, true)) return 'Sra.';
        return '';
    }
}

if (!function_exists('sige_notify_human_treatment')) {
    /**
     * Forma de tratamento B2C conservadora para Moçambique.
     * Evita "Olá João" demasiado casual e evita tratamento rígido quando não há género.
     */
    function sige_notify_human_treatment(string $nome = ''): string {
        $first = sige_notify_human_first_name($nome);
        if ($first !== '') {
            $title = function_exists('sige_notify_human_guess_title') ? sige_notify_human_guess_title($first) : '';
            return $title !== '' ? ($title . ' ' . $first) : $first;
        }
        return 'Encarregado de Educação';
    }
}

if (!function_exists('sige_notify_human_opening')) {
    function sige_notify_human_opening(string $nome = ''): string {
        return sige_notify_human_mz_saudacao() . ', ' . sige_notify_human_treatment($nome) . '.';
    }
}

if (!function_exists('sige_notify_human_strip_emojis')) {
    function sige_notify_human_strip_emojis(string $msg): string {
        // Remove emojis/símbolos frequentemente associados a template/disparo.
        $msg = preg_replace('/[\x{1F300}-\x{1FAFF}\x{2600}-\x{27BF}]/u', '', $msg);
        return (string)$msg;
    }
}


if (!function_exists('sige_notify_human_fix_mz_concordance')) {
    /**
     * Corrige marcas típicas de template: do(a), ao(à), artigos com nomes de escola
     * e markdown visível (_texto_) que denuncia mensagem automática.
     */
    function sige_notify_human_fix_mz_concordance(string $msg): string {
        $msg = (string)$msg;

        // Markdown de enfase que por vezes sai visivel (_texto_) -> texto.
        // NAO se removem underscores soltos: pertencem a URLs (?sige_recibo=),
        // identificadores e nomes tecnicos. Remover tudo partia o link do recibo.
        $msg = preg_replace('/(?<![A-Za-z0-9])_([^_\n]{1,80})_(?![A-Za-z0-9])/u', '$1', $msg);

        // Fórmulas artificiais de neutralidade gramatical.
        $msg = preg_replace('/\bdo\(a\)\s+/iu', 'de ', $msg);
        $msg = preg_replace('/\bao\(à\)\s+/iu', 'a ', $msg);
        $msg = preg_replace('/\bo\(a\)\s+/iu', '', $msg);
        $msg = preg_replace('/\bdo\/da\s+/iu', 'de ', $msg);
        $msg = preg_replace('/\bao\/à\s+/iu', 'a ', $msg);

        // Em mensagens financeiras antigas, evita a construção pouco natural
        // "pagamento referente a Nome" e não transforma todo pagamento em
        // matrícula. Usa uma referência genérica aos serviços escolares.
        $msg = preg_replace('/\bpagamento referente a ([^\n\.;,]{2,60})(?=\s+(?:foi|ficou|j[aá]|consta)|[\.\n])/iu', 'pagamento dos serviços escolares de $1', $msg);

        // Concordância com nomes institucionais comuns em Moçambique.
        $pairs = [
            '/\bda\s+(Col[eé]gio)\b/u' => 'do $1',
            '/\bda\s+(Liceu)\b/u' => 'do $1',
            '/\bda\s+(Instituto)\b/u' => 'do $1',
            '/\bda\s+(Centro)\b/u' => 'do $1',
            '/\bda\s+(Jardim)\b/u' => 'do $1',
            '/\bdo\s+(Escola)\b/u' => 'da $1',
            '/\bdo\s+(Creche)\b/u' => 'da $1',
            '/\bdo\s+(Academia)\b/u' => 'da $1',
        ];
        foreach ($pairs as $pattern => $replacement) {
            $msg = preg_replace($pattern, $replacement, $msg);
        }

        // Limpezas de frases ainda com cheiro de robô.
        $msg = str_replace('Passamos apenas para confirmar consigo que', 'Confirmamos que', $msg);
        $msg = str_replace('Para sua referência:', 'Resumo para o seu controlo:', $msg);
        $msg = str_replace('Detalhe registado pela secretaria:', 'Detalhe do pagamento:', $msg);
        return $msg;
    }
}

if (!function_exists('sige_notify_human_strip_auto_spoilers')) {
    function sige_notify_human_strip_auto_spoilers(string $msg): string {
        $msg = (string)$msg;

        $replacements = [
            'Mensagem enviada automaticamente pelo SIGE SoftGenial. Por favor, responda apenas se o endereço de resposta da escola estiver configurado.' => 'Comunicação enviada pela secretaria da escola.',
            'Documento gerado automaticamente pelo SIGE SoftGenial' => 'Comunicação emitida pela secretaria da escola.',
            'Este é um e-mail de teste enviado pelo SIGE SoftGenial.' => 'Este é um e-mail de teste enviado pela secretaria da escola.',
            'SIGE: ' => '',
            'SIGE:' => '',
            'SoftGenial' => '',
            'sistema' => 'registo',
            'Sistema' => 'Registo',
            'no nosso registo' => 'no nosso registo',
            'no nosso registo interno' => 'no nosso registo',
            'foi lançado com sucesso' => 'ficou registado',
            'processado' => 'registado',
            'Mensagem Entregue!' => 'Mensagem enviada.',
            'enviado automaticamente' => 'enviado pela secretaria',
            'enviada automaticamente' => 'enviada pela secretaria',
            'gerado automaticamente' => 'emitido pela secretaria',
            'gerada automaticamente' => 'emitida pela secretaria',
            'envio automático' => 'envio pela secretaria',
            'envio automatico' => 'envio pela secretaria',
            'automatizado' => 'organizado',
            'automatizada' => 'organizada',
            'automático' => 'organizado',
            'automatica' => 'organizada',
            'automática' => 'organizada',
            'Não responda a esta mensagem.' => 'Pode responder a esta mensagem se precisar de falar com a secretaria.',
            'Nao responda a esta mensagem.' => 'Pode responder a esta mensagem se precisar de falar com a secretaria.',
            'NÃO RESPONDA' => 'Pode responder',
            'NAO RESPONDA' => 'Pode responder',
        ];
        $msg = strtr($msg, $replacements);

        // "robo"/"robô"/"robot" so como palavra inteira -> evita corromper
        // palavras como "botao"/"botar". "bot" isolado e ambiguo de mais em
        // portugues, por isso nao se substitui.
        $msg = (string)preg_replace('/\b(rob[ôo]|robot)\b/iu', 'secretaria', $msg);

        // Remove chamadas mecânicas de opt-in/opt-out antigas, sem remover a possibilidade humana de conversa.
        $msg = preg_replace('/\s*Para continuar a receber avisos da escola por WhatsApp[,\s]*responda SIM\.\s*Se preferir parar[,\s]*responda N[AÃ]O ou PARAR\.?\s*$/iu', '', $msg);
        $msg = preg_replace('/\s*Para parar de receber[,\s]*responda PARAR\.?\s*$/iu', '', $msg);
        $msg = preg_replace('/\s*Se preferir n[aã]o receber avisos por WhatsApp[,\s]*responda PARAR\.?\s*$/iu', '', $msg);
        $msg = preg_replace('/\*{1,}/u', '', $msg); // remove negritos de template WhatsApp
        $msg = sige_notify_human_strip_emojis($msg);
        $msg = function_exists('sige_notify_human_fix_mz_concordance') ? sige_notify_human_fix_mz_concordance($msg) : $msg;
        return $msg;
    }
}

if (!function_exists('sige_notify_human_normalize_lines')) {
    function sige_notify_human_normalize_lines(string $msg): string {
        $msg = preg_replace("/\r\n|\r/", "\n", $msg);
        $msg = preg_replace('/[ \t]+\n/u', "\n", $msg);
        $msg = preg_replace('/\n{3,}/u', "\n\n", $msg);
        $msg = preg_replace('/[ \t]{2,}/u', ' ', $msg);
        $msg = function_exists('sige_notify_human_fix_mz_concordance') ? sige_notify_human_fix_mz_concordance((string)$msg) : (string)$msg;
        return trim((string)$msg);
    }
}

if (!function_exists('sige_notify_human_append_soft_consent')) {
    function sige_notify_human_append_soft_consent(string $msg, string $tipo = ''): string {
        $tipo_key = sanitize_key(remove_accents(strtolower($tipo)));
        // Não anexar em recibos/pagamentos para não poluir comunicação transaccional positiva.
        if (strpos($tipo_key, 'recibo') !== false || strpos($tipo_key, 'pagamento') !== false) return $msg;
        if (preg_match('/preferir n[aã]o receber|parar de receber|diga-nos por aqui/iu', $msg)) return $msg;
        // Frase humana, não comando robótico. Ajuda compliance sem parecer disparo de marketing.
        return rtrim($msg) . "\n\nSe esta mensagem não devia ter sido enviada para este número, diga-nos por aqui que a secretaria corrige o contacto.";
    }
}


if (!function_exists('sige_notify_human_remove_duplicate_openings')) {
    /**
     * Remove dupla abertura como:
     * "Bom dia! Confirmamos..." + "Boa noite, Sra. ...".
     * A mensagem deve ter apenas uma saudação temporal, sempre a mais específica.
     */
    function sige_notify_human_remove_duplicate_openings(string $msg): string {
        $msg = preg_replace("/\r\n|\r/", "\n", (string)$msg);
        $lines = preg_split('/\n/', $msg) ?: [];
        $nonempty = [];
        foreach ($lines as $i => $line) {
            if (trim($line) !== '') $nonempty[] = $i;
            if (count($nonempty) >= 2) break;
        }
        if (count($nonempty) >= 2) {
            $i1 = $nonempty[0];
            $i2 = $nonempty[1];
            $l1 = trim((string)$lines[$i1]);
            $l2 = trim((string)$lines[$i2]);
            $greeting = '(?:Bom dia|Boa tarde|Boa noite|Olá|Saudações)';
            $first_is_generic = preg_match('/^' . $greeting . '[!,.]?\s*(?:confirmamos|a secretaria|o pagamento|partilhamos|recebemos|fica confirmada)/iu', $l1);
            $second_is_personal = preg_match('/^' . $greeting . '\s*,\s*[^\n\.]{2,80}\.?$/iu', $l2);
            if ($first_is_generic && $second_is_personal) {
                array_splice($lines, $i1, ($i2 - $i1));
                $msg = implode("\n", $lines);
            }
        }
        // Remove duplicação exacta de saudações temporais consecutivas.
        $msg = preg_replace('/^\s*((?:Bom dia|Boa tarde|Boa noite|Olá|Saudações)[^\n]{0,120})\n\s*\1\s*\n/iu', "$1\n", $msg);
        $msg = preg_replace('/\n{3,}/', "\n\n", $msg);
        return trim($msg);
    }
}

if (!function_exists('sige_notify_humanize_outbound_whatsapp')) {
    /**
     * Sanitizador final transversal antes de inserir qualquer WhatsApp na fila.
     * Mantém a mensagem como conversa humana e remove sinais de automação.
     */
    function sige_notify_humanize_outbound_whatsapp(string $msg, string $tipo = '', array $context = []): string {
        $original = $msg;
        $msg = sige_notify_human_strip_auto_spoilers($msg);
        $msg = sige_notify_human_remove_duplicate_openings($msg);

        // Substituir headers templateiros por início humano quando aparecem no começo.
        $msg = preg_replace('/^\s*(Pagamento Confirmado|Aviso de Pend[eê]ncias|Aviso de Pagamento|Lembrete de Pagamento|Mensalidade pendente|Vencimento hoje)\s*(?:-|-)\s*[^\n]+\n*/iu', '', $msg);
        $msg = preg_replace('/^\s*(Pagamento Confirmado|Aviso de Pend[eê]ncias|Aviso de Pagamento|Lembrete de Pagamento|Mensalidade pendente|Vencimento hoje)\s*\n*/iu', '', $msg);

        // Vocabulário mais B2C e menos cobrança agressiva.
        $msg = str_ireplace('Solicitamos a regularização o mais breve possível.', 'Quando for possível, pedimos que fale com a secretaria para alinharmos a melhor forma de regularizar.', $msg);
        $msg = str_ireplace('Por favor regularize o mais breve possível.', 'Quando for possível, pedimos que fale com a secretaria para alinharmos a melhor forma de regularizar.', $msg);
        $msg = str_ireplace('Efectue o pagamento atempadamente para evitar encargos adicionais.', 'Agradecemos a sua atenção. Se precisar de combinar uma data, pode responder por aqui.', $msg);
        $msg = str_ireplace('evitar suspensão de serviços', 'evitar constrangimentos na prestação dos serviços', $msg);
        $msg = str_ireplace('urgência na regularização', 'a sua atenção para regularizarmos a situação', $msg);

        $msg = sige_notify_human_normalize_lines($msg);
        $msg = sige_notify_human_remove_duplicate_openings($msg);
        $msg = sige_notify_human_append_soft_consent($msg, $tipo);
        $msg = sige_notify_human_normalize_lines($msg);

        if ($msg === '') $msg = sige_notify_human_normalize_lines(sige_notify_human_strip_auto_spoilers($original));
        return $msg;
    }
}

if (!function_exists('sige_notify_humanize_outbound_email_html')) {
    function sige_notify_humanize_outbound_email_html(string $html): string {
        // Proteger URLs (em href ou em texto) de QUALQUER transformacao do
        // humanizador: humanizamos o texto humano, nunca os enderecos. Sem isto,
        // regras como a limpeza de markdown removeriam o underscore de
        // ?sige_recibo=... e o link abriria uma pagina em branco.
        $urls = [];
        $html = (string)preg_replace_callback('#https?://[^\s"\'<>]+#i', static function ($m) use (&$urls) {
            $ph = "\x01U" . count($urls) . "\x01";
            $urls[$ph] = $m[0];
            return $ph;
        }, $html);

        $html = sige_notify_human_strip_auto_spoilers($html);
        $html = str_replace('Obrigado(a) pela confiança! ', 'Obrigado(a) pela confiança.', $html);
        $html = str_replace('✅ ', '', $html);
        $html = str_replace('💰 ', '', $html);
        $html = str_replace('📋 ', '', $html);
        $html = str_replace('🧾 ', '', $html);
        $html = str_replace('📅 ', '', $html);
        $html = str_replace('🙏', '', $html);

        if ($urls) $html = strtr($html, $urls); // repor URLs intactos
        return $html;
    }
}

if (!function_exists('sige_notify_humanization_pro_migrate')) {
    function sige_notify_humanization_pro_migrate(): void {
        $opt = 'sige_notification_humanization_pro_version';
        if ((string)get_option($opt, '') === SIGE_NOTIFICATION_HUMANIZATION_PRO_VERSION) return;

        global $wpdb;
        $res = [
            'version' => SIGE_NOTIFICATION_HUMANIZATION_PRO_VERSION,
            'pending_whatsapp_cleaned' => 0,
            'email_templates_cleaned' => 0,
            'alert_templates_cleaned' => 0,
            'at' => current_time('mysql'),
        ];

        // Limpar mensagens WhatsApp ainda não enviadas.
        $tq = $wpdb->prefix . 'sige_whatsapp_queue';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tq)) === $tq) {
            $rows = $wpdb->get_results("SELECT id, tipo, mensagem FROM {$tq} WHERE status IN ('pendente','forcar_envio') ORDER BY id DESC LIMIT 30000");
            foreach ($rows as $r) {
                $new = sige_notify_humanize_outbound_whatsapp((string)$r->mensagem, (string)$r->tipo);
                if ($new !== (string)$r->mensagem) {
                    $wpdb->update($tq, ['mensagem' => $new], ['id' => (int)$r->id], ['%s'], ['%d']);
                    $res['pending_whatsapp_cleaned']++;
                }
            }
        }

        // Limpar templates de e-mail salvos pela escola sem apagar personalizações úteis.
        $email_tpl = get_option('sige_email_templates_config', []);
        if (is_array($email_tpl)) {
            $changed = false;
            foreach ($email_tpl as $k => $tpl) {
                if (!is_array($tpl)) continue;
                foreach (['subject','body'] as $field) {
                    if (!isset($tpl[$field])) continue;
                    $val = (string)$tpl[$field];
                    $new = sige_notify_humanize_outbound_email_html($val);
                    if ($new !== $val) { $email_tpl[$k][$field] = $new; $changed = true; }
                }
            }
            if ($changed) {
                update_option('sige_email_templates_config', $email_tpl, false);
                $res['email_templates_cleaned'] = 1;
            }
        }

        // Limpar templates de alertas salvos.
        $alert_tpl = get_option('sige_alertas_templates', []);
        if (is_array($alert_tpl)) {
            $changed = false;
            foreach ($alert_tpl as $k => $tpl) {
                if (!is_array($tpl)) continue;
                foreach (['whatsapp','email_assunto','email_corpo'] as $field) {
                    if (!isset($tpl[$field])) continue;
                    $val = (string)$tpl[$field];
                    $new = ($field === 'whatsapp')
                        ? sige_notify_humanize_outbound_whatsapp($val, (string)$k)
                        : sige_notify_humanize_outbound_email_html($val);
                    if ($new !== $val) { $alert_tpl[$k][$field] = $new; $changed = true; }
                }
            }
            if ($changed) {
                update_option('sige_alertas_templates', $alert_tpl, false);
                $res['alert_templates_cleaned'] = 1;
            }
        }

        update_option($opt, SIGE_NOTIFICATION_HUMANIZATION_PRO_VERSION, false);
        if (function_exists('sige_fin_log')) {
            sige_fin_log('notification_humanization_pro_applied', $res);
        }
    }
}

add_action('init', 'sige_notify_humanization_pro_migrate', 40);
add_action('admin_init', 'sige_notify_humanization_pro_migrate', 40);
