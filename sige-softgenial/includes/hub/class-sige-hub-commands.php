<?php
/**
 * SIGE_Hub_Commands - executor local de comandos enviados pelo SoftGenial Hub.
 *
 * Segurança: comandos só chegam em respostas autenticadas do Hub, usando a mesma
 * chave/licença já configurada. O cliente apenas executa comandos conhecidos.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Hub_Commands')) {
    final class SIGE_Hub_Commands {

        public static function process(array $commands): array {
            $out = [];
            foreach ($commands as $cmd) {
                if (!is_array($cmd)) continue;
                $id = absint($cmd['id'] ?? 0);
                $type = sanitize_key((string)($cmd['type'] ?? ''));
                $payload = $cmd['payload'] ?? [];
                if (!is_array($payload)) $payload = [];
                if (!$id || $type === '') continue;

                $result = ['ok' => false, 'message' => 'Comando não executado.'];
                $error = '';
                try {
                    if ($type === 'force_refresh') {
                        $result = ['ok' => true, 'message' => 'Refresh recebido pelo cliente.', 'time' => current_time('mysql')];
                    } elseif ($type === 'send_debt_charges') {
                        $result = self::execute_send_debt_charges($payload);
                    } elseif ($type === 'issue_platform_charge') {
                        $result = class_exists('SIGE_Hub_Billing') ? SIGE_Hub_Billing::issue($payload) : ['ok' => false, 'message' => 'SIGE_Hub_Billing indisponível.'];
                    } elseif ($type === 'clear_platform_charge') {
                        $result = class_exists('SIGE_Hub_Billing') ? SIGE_Hub_Billing::clear($payload) : ['ok' => false, 'message' => 'SIGE_Hub_Billing indisponível.'];
                    } else {
                        $result = ['ok' => false, 'message' => 'Tipo de comando desconhecido: ' . $type];
                    }
                } catch (Throwable $e) {
                    $error = $e->getMessage();
                    $result = ['ok' => false, 'message' => $error];
                }

                self::ack($id, !empty($result['ok']) ? 'done' : 'failed', $result, $error ?: (string)($result['message'] ?? ''));
                $out[] = ['id' => $id, 'type' => $type, 'result' => $result];
            }
            if ($out) update_option('sige_hub_last_commands_result', $out, false);
            return $out;
        }

        private static function execute_send_debt_charges(array $payload): array {
            global $wpdb;
            $channel = sanitize_key((string)($payload['channel'] ?? 'email'));
            if (!in_array($channel, ['email','whatsapp','both'], true)) $channel = 'email';
            $limit = max(1, min(300, (int)($payload['limit'] ?? 50)));
            $escola_id = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;

            $tL = $wpdb->prefix . 'sige_fin_lancamentos';
            $tA = $wpdb->prefix . 'sige_alunos';
            if (!self::table_exists($tL) || !self::table_exists($tA)) {
                return ['ok' => false, 'message' => 'Tabelas financeiras/alunos não encontradas.'];
            }

            $rows = $wpdb->get_results($wpdb->prepare(
                "SELECT l.aluno_id,
                        SUM(COALESCE(l.valor,0) - COALESCE(l.valor_pago,0)) AS total_divida,
                        MIN(COALESCE(l.data_vencimento, l.criado_em)) AS min_venc,
                        COUNT(*) AS qtd
                 FROM {$tL} l
                 INNER JOIN {$tA} a ON a.id = l.aluno_id
                 WHERE l.escola_id = %d
                   AND LOWER(COALESCE(l.status,'')) IN ('pendente','parcial')
                 GROUP BY l.aluno_id
                 HAVING total_divida > 0
                 ORDER BY total_divida DESC
                 LIMIT %d",
                $escola_id, $limit
            ));

            $sent_email = 0; $sent_whatsapp = 0; $skipped = 0; $errors = [];
            foreach ((array)$rows as $r) {
                $aid = (int)$r->aluno_id;
                $total = (float)$r->total_divida;
                $min_venc = $r->min_venc ?: current_time('Y-m-d');
                $detalhes = 'Cobrança remota via SoftGenial Hub · ' . (int)$r->qtd . ' pendência(s).';

                if (($channel === 'email' || $channel === 'both') && function_exists('sige_enviar_email_financeiro')) {
                    try {
                        sige_enviar_email_financeiro($aid, 'cobranca', $detalhes, $total, $min_venc);
                        $sent_email++;
                    } catch (Throwable $e) {
                        $errors[] = 'Email aluno '.$aid.': '.$e->getMessage();
                    }
                }

                if (($channel === 'whatsapp' || $channel === 'both') && function_exists('sige_enviar_whatsapp')) {
                    try {
                        $ok = sige_enviar_whatsapp($aid, 'cobranca', [
                            'valor' => $total,
                            'descricao' => $detalhes,
                            'data_vencimento' => $min_venc,
                        ]);
                        if ($ok) $sent_whatsapp++; else $skipped++;
                    } catch (Throwable $e) {
                        $errors[] = 'WhatsApp aluno '.$aid.': '.$e->getMessage();
                    }
                }
            }

            return [
                'ok' => true,
                'message' => 'Cobranças processadas pelo cliente.',
                'alunos_encontrados' => count((array)$rows),
                'email_enfileirados' => $sent_email,
                'whatsapp_enfileirados' => $sent_whatsapp,
                'ignorados' => $skipped,
                'erros' => array_slice($errors, 0, 20),
                'executed_at' => current_time('mysql'),
            ];
        }

        private static function ack(int $command_id, string $status, array $result, string $error = ''): void {
            $endpoint = self::ack_endpoint();
            $api_key = sanitize_text_field((string)get_option('sige_license_key', ''));
            $domain = function_exists('sige_hub_get_domain') ? sige_hub_get_domain() : home_url();
            if ($endpoint === '' || $api_key === '') return;
            $payload = [
                'dominio' => $domain,
                'api_key' => $api_key, // compatibilidade com Hub antigo.
                'command_id' => $command_id,
                'status' => $status,
                'result' => $result,
                'error' => $error,
                'plugin_version' => defined('SIGE_VERSION') ? SIGE_VERSION : '',
            ];
            $body_json = wp_json_encode($payload);
            $headers = ['Content-Type' => 'application/json; charset=utf-8', 'Accept' => 'application/json'];
            if (function_exists('sige_sec_hub_headers')) {
                $headers = array_merge($headers, sige_sec_hub_headers($api_key, (string)$body_json, (string)$domain));
            }
            wp_remote_post($endpoint, [
                'timeout' => 8,
                'headers' => $headers,
                'body' => $body_json,
                'sslverify' => true,
            ]);
        }

        private static function ack_endpoint(): string {
            if (class_exists('SIGE_Hub_Client')) {
                $validate = SIGE_Hub_Client::resolve_validate_endpoint();
                if ($validate !== '') return preg_replace('#/licenca/validar/?$#', '/commands/ack', $validate);
            }
            $legacy = (string)get_option('sige_license_server_url', '');
            if ($legacy !== '') return preg_replace('#/licenca/validar/?$#', '/commands/ack', $legacy);
            return '';
        }

        private static function table_exists(string $table): bool {
            global $wpdb;
            return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table)) === $table;
        }
    }
}
