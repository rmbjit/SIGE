<?php
/**
 * SoftGenial Core v1.0 Foundation - fila genérica para futuras notificações.
 * Nesta versão não interfere com envios existentes; apenas cria base segura.
 */
if (!defined('ABSPATH')) exit;

if (!class_exists('SIGE_Queue')) {
    final class SIGE_Queue {
        public static function enqueue(string $type, array $payload): bool {
            $queue = get_option('sige_core_queue', []);
            if (!is_array($queue)) $queue = [];
            $queue[] = [
                'id' => uniqid('sigeq_', true),
                'type' => sanitize_key($type),
                'payload' => $payload,
                'status' => 'pending',
                'attempts' => 0,
                'created_at' => time(),
            ];
            if (count($queue) > 1000) {
                $queue = array_slice($queue, -1000);
            }
            return update_option('sige_core_queue', $queue, false);
        }

        public static function stats(): array {
            $queue = get_option('sige_core_queue', []);
            if (!is_array($queue)) $queue = [];
            $stats = ['pending'=>0,'done'=>0,'failed'=>0,'total'=>count($queue)];
            foreach ($queue as $item) {
                $status = sanitize_key($item['status'] ?? 'pending');
                if (!isset($stats[$status])) $stats[$status] = 0;
                $stats[$status]++;
            }
            return $stats;
        }
    }
}
