<?php
/**
 * SIGE SoftGenial - Política de Destinatários WhatsApp
 * Ficheiro: includes/whatsapp-destinatarios-policy.php
 *
 * Define, por escola, quem deve receber mensagens WhatsApp por defeito.
 * Camada transversal: pagamentos, cobranças, recibos, alertas e qualquer
 * chamada futura que use sige_fin_queue_whatsapp() com aluno_id.
 *
 * @since 12.9.69
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_wpp_destinatarios_options')) {
    function sige_wpp_destinatarios_options(): array {
        return [
            'todos'                 => 'Pai + Mãe + WhatsApp de Notificações + Contacto do Encarregado',
            'pai_mae'               => 'Pai e Mãe',
            'pai'                   => 'Apenas Pai',
            'mae'                   => 'Apenas Mãe',
            'whatsapp_notificacoes' => 'Apenas WhatsApp para Notificações',
            'contacto_encarregado'  => 'Apenas Contacto do Encarregado',
            'primeiro_disponivel'   => 'Primeiro disponível: Notificações → Encarregado → Pai → Mãe',
        ];
    }
}

if (!function_exists('sige_wpp_destinatarios_normalize')) {
    function sige_wpp_destinatarios_normalize($value): string {
        $value = sanitize_key((string)$value);
        $aliases = [
            'ambos'            => 'pai_mae',
            'pai_e_mae'        => 'pai_mae',
            'pai_mae_notif'    => 'todos',
            'notificacoes'     => 'whatsapp_notificacoes',
            'numero_notificacao' => 'whatsapp_notificacoes',
            'numero_notificacoes' => 'whatsapp_notificacoes',
            'encarregado'      => 'contacto_encarregado',
            'todos_contactos'  => 'todos',
        ];
        if (isset($aliases[$value])) $value = $aliases[$value];
        $opts = sige_wpp_destinatarios_options();
        return isset($opts[$value]) ? $value : 'todos';
    }
}

if (!function_exists('sige_wpp_ensure_destinatarios_column')) {
    function sige_wpp_ensure_destinatarios_column(): void {
        global $wpdb;
        static $done = false;
        if ($done) return;
        $done = true;
        if (!$wpdb || empty($wpdb->prefix)) return;
        $table = $wpdb->prefix . 'sige_config';
        $exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$table}` LIKE %s", 'whatsapp_destinatarios_padrao'));
        if (!$exists) {
            $wpdb->query("ALTER TABLE `{$table}` ADD COLUMN `whatsapp_destinatarios_padrao` VARCHAR(40) NOT NULL DEFAULT 'todos' AFTER `whatsapp_token`");
        }
    }
}

if (!function_exists('sige_wpp_destinatarios_get')) {
    function sige_wpp_destinatarios_get($escola_id = null): string {
        global $wpdb;
        $eid = $escola_id !== null ? (int)$escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($eid <= 0) $eid = function_exists('sige_multitenancy_single_active_school_id') ? sige_multitenancy_single_active_school_id() : 0;
        sige_wpp_ensure_destinatarios_column();
        $table = $wpdb->prefix . 'sige_config';
        $valor = $wpdb->get_var($wpdb->prepare("SELECT whatsapp_destinatarios_padrao FROM `{$table}` WHERE escola_id = %d LIMIT 1", $eid));
        if ($valor === null || $valor === '') {
            $valor = get_option('sige_wpp_destinatarios_padrao_' . $eid, 'todos');
        }
        return sige_wpp_destinatarios_normalize($valor);
    }
}

if (!function_exists('sige_wpp_destinatarios_save')) {
    function sige_wpp_destinatarios_save($value, $escola_id = null): string {
        global $wpdb;
        $eid = $escola_id !== null ? (int)$escola_id : (function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0);
        if ($eid <= 0) { return ''; }
        $valor = sige_wpp_destinatarios_normalize($value);
        sige_wpp_ensure_destinatarios_column();
        $table = $wpdb->prefix . 'sige_config';
        $id = (int)$wpdb->get_var($wpdb->prepare("SELECT id FROM `{$table}` WHERE escola_id = %d LIMIT 1", $eid));
        if ($id <= 0) {
            $wpdb->insert($table, ['escola_id' => $eid, 'whatsapp_destinatarios_padrao' => $valor]);
        } else {
            $wpdb->update($table, ['whatsapp_destinatarios_padrao' => $valor], ['id' => $id]);
        }
        update_option('sige_wpp_destinatarios_padrao_' . $eid, $valor, false);
        return $valor;
    }
}

if (!function_exists('sige_wpp_policy_normalize_phone')) {
    function sige_wpp_policy_normalize_phone($raw): string {
        $raw = trim((string)$raw);
        if ($raw === '') return '';
        if (function_exists('sige_telefone_normalizar')) {
            $num = (string)sige_telefone_normalizar($raw);
        } else {
            $num = preg_replace('/[^0-9]/', '', $raw);
            if (strlen($num) === 9) $num = '258' . $num;
        }
        return preg_replace('/[^0-9]/', '', $num ?: '');
    }
}


if (!function_exists('sige_wpp_policy_identificar_por_numero')) {
    /**
     * Identifica se um número genérico de notificações pertence ao pai ou à mãe.
     * Evita saudar a mãe com o nome/tratamento do pai quando whatsapp_notificacoes
     * ou contacto_encarregado repetem o telemóvel da mãe.
     */
    function sige_wpp_policy_identificar_por_numero($aluno, $raw, string $tipo_default = 'encarregado', string $nome_default = ''): array {
        $num = sige_wpp_policy_normalize_phone($raw);
        $nome_pai = trim((string)($aluno->nome_pai ?? ''));
        $nome_mae = trim((string)($aluno->nome_mae ?? ''));
        $nums_pai = [
            sige_wpp_policy_normalize_phone($aluno->telemovel_pai ?? ''),
            sige_wpp_policy_normalize_phone($aluno->telemovel_pai_2 ?? ''),
        ];
        $nums_mae = [
            sige_wpp_policy_normalize_phone($aluno->telemovel_mae ?? ''),
            sige_wpp_policy_normalize_phone($aluno->telemovel_mae_2 ?? ''),
        ];
        if ($num !== '' && $nome_mae !== '' && in_array($num, array_filter($nums_mae), true)) {
            return ['tipo' => 'mae', 'nome' => $nome_mae];
        }
        if ($num !== '' && $nome_pai !== '' && in_array($num, array_filter($nums_pai), true)) {
            return ['tipo' => 'pai', 'nome' => $nome_pai];
        }
        $nome = trim($nome_default);
        if ($nome === '') $nome = 'Encarregado de Educação';
        return ['tipo' => $tipo_default, 'nome' => $nome];
    }
}

if (!function_exists('sige_wpp_policy_add_contact')) {
    function sige_wpp_policy_add_contact(array &$out, array &$vistos, string $tipo, string $nome, $raw): void {
        $num = sige_wpp_policy_normalize_phone($raw);
        if (!$num || strlen($num) < 9) return;
        if (isset($vistos[$num])) return;
        $vistos[$num] = true;
        $out[] = [
            'tipo'   => $tipo,
            'nome'   => trim($nome) !== '' ? trim($nome) : 'Encarregado(a)',
            'numero' => $num,
        ];
    }
}

if (!function_exists('sige_wpp_contactos_whatsapp_aluno_row')) {
    function sige_wpp_contactos_whatsapp_aluno_row($aluno, $policy = null): array {
        $out = [];
        $vistos = [];
        if (!$aluno) return $out;

        $policy = sige_wpp_destinatarios_normalize($policy ?: sige_wpp_destinatarios_get());
        $nome_pai = (string)($aluno->nome_pai ?? '');
        $nome_mae = (string)($aluno->nome_mae ?? '');
        // Para números genéricos, não assumir o pai por defeito.
        // O nome será resolvido pelo número: se bater com telemóvel da mãe, usa a mãe; se bater com o pai, usa o pai.
        $nome_enc = 'Encarregado de Educação';

        $add_pai = function() use (&$out, &$vistos, $aluno, $nome_pai) {
            sige_wpp_policy_add_contact($out, $vistos, 'pai', $nome_pai, $aluno->telemovel_pai ?? '');
        };
        $add_mae = function() use (&$out, &$vistos, $aluno, $nome_mae) {
            sige_wpp_policy_add_contact($out, $vistos, 'mae', $nome_mae, $aluno->telemovel_mae ?? '');
        };
        $add_notif = function() use (&$out, &$vistos, $aluno, $nome_enc) {
            $r = sige_wpp_policy_identificar_por_numero($aluno, $aluno->whatsapp_notificacoes ?? '', 'whatsapp_notificacoes', $nome_enc);
            sige_wpp_policy_add_contact($out, $vistos, $r['tipo'], $r['nome'], $aluno->whatsapp_notificacoes ?? '');
        };
        $add_enc = function() use (&$out, &$vistos, $aluno, $nome_enc) {
            $r = sige_wpp_policy_identificar_por_numero($aluno, $aluno->contacto_encarregado ?? '', 'contacto_encarregado', $nome_enc);
            sige_wpp_policy_add_contact($out, $vistos, $r['tipo'], $r['nome'], $aluno->contacto_encarregado ?? '');
        };

        switch ($policy) {
            case 'pai':
                $add_pai();
                break;
            case 'mae':
                $add_mae();
                break;
            case 'pai_mae':
                $add_pai();
                $add_mae();
                break;
            case 'whatsapp_notificacoes':
                $add_notif();
                break;
            case 'contacto_encarregado':
                $add_enc();
                break;
            case 'primeiro_disponivel':
                $notif = sige_wpp_policy_identificar_por_numero($aluno, $aluno->whatsapp_notificacoes ?? '', 'whatsapp_notificacoes', $nome_enc);
                $enc   = sige_wpp_policy_identificar_por_numero($aluno, $aluno->contacto_encarregado ?? '', 'contacto_encarregado', $nome_enc);
                $candidatos = [
                    [$notif['tipo'], $notif['nome'], $aluno->whatsapp_notificacoes ?? ''],
                    [$enc['tipo'],   $enc['nome'],   $aluno->contacto_encarregado ?? ''],
                    ['pai',          $nome_pai,      $aluno->telemovel_pai ?? ''],
                    ['mae',          $nome_mae,      $aluno->telemovel_mae ?? ''],
                ];
                foreach ($candidatos as $c) {
                    sige_wpp_policy_add_contact($out, $vistos, $c[0], $c[1], $c[2]);
                    if (!empty($out)) break;
                }
                break;
            case 'todos':
            default:
                $add_pai();
                $add_mae();
                $add_notif();
                $add_enc();
                break;
        }

        return $out;
    }
}

if (!function_exists('sige_wpp_destinatario_permitido')) {
    function sige_wpp_destinatario_permitido(int $aluno_id, $telefone, $policy = null): bool {
        global $wpdb;
        if ($aluno_id <= 0) return true;
        $telefone_norm = sige_wpp_policy_normalize_phone($telefone);
        if ($telefone_norm === '') return false;

        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT id, nome_pai, nome_mae, telemovel_pai, telemovel_mae, whatsapp_notificacoes, contacto_encarregado
             FROM {$wpdb->prefix}sige_alunos
             WHERE id = %d AND escola_id = %d
             LIMIT 1",
            $aluno_id, $eid
        ));
        if (!$aluno) return true; // se não é aluno financeiro normal, não bloquear acções legítimas.

        $permitidos = sige_wpp_contactos_whatsapp_aluno_row($aluno, $policy);
        if (empty($permitidos)) return false;
        foreach ($permitidos as $c) {
            if (sige_wpp_policy_normalize_phone($c['numero'] ?? '') === $telefone_norm) return true;
        }
        return false;
    }
}
