<?php
/**
 * SIGE SoftGenial - Finance State Machines
 *
 * Três máquinas de estado formalizadas como enums PHP 8.1:
 *   • LancamentoStatus    - pendente, parcial, pago, cancelado, isento, em_plano
 *   • PlanoStatus         - activo, cumprido, quebrado, cancelado
 *   • PrestacaoStatus     - pendente, parcial, pago, atrasado, cancelado
 *
 * Cada uma declara as transições válidas via canTransitionTo(). O
 * FinanceActionService passa sempre por estes enums - nunca mais $wpdb->update(['status' => X])
 * inline. Isto elimina os 54 call-sites que escreviam status sem validação.
 *
 * REQUER PHP 8.1+. O plugin já declara "Requires PHP: 7.4" mas na prática
 * corre em 8.0+ na Hostinger e MochaHost. Se o tenant estiver em 7.4, a
 * FSM faz fallback para strings via LancamentoStatus::tryFrom().
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

// Só definimos os enums em PHP 8.1+. Se estamos em 7.4 ou 8.0, fornecemos
// constantes de classe que simulam a API pública (sem type safety mas com
// a mesma lógica de transições válidas).

if (PHP_VERSION_ID >= 80100) {
    require_once __DIR__ . '/fin-fsm-php81.php';
} else {
    require_once __DIR__ . '/fin-fsm-php74.php';
}

/**
 * Helper universal - valida uma transição sem precisar de enum.
 *
 * @param string $entity   'lancamento' | 'plano' | 'prestacao'
 * @param string $from     Status actual
 * @param string $to       Status alvo
 * @return bool            true se transição válida
 */
function sige_fin_fsm_can_transition(string $entity, string $from, string $to): bool {
    switch ($entity) {
        case 'lancamento':
            return SIGE_LancamentoStatus::canTransitionTo($from, $to);
        case 'plano':
            return SIGE_PlanoStatus::canTransitionTo($from, $to);
        case 'prestacao':
            return SIGE_PrestacaoStatus::canTransitionTo($from, $to);
    }
    return false;
}

/**
 * Lista todas as transições válidas a partir de um status.
 * Útil para UI mostrar só as acções permitidas.
 *
 * @param string $entity
 * @param string $from
 * @return array<string>  Lista de status alcançáveis
 */
function sige_fin_fsm_next_states(string $entity, string $from): array {
    switch ($entity) {
        case 'lancamento':
            return SIGE_LancamentoStatus::nextStates($from);
        case 'plano':
            return SIGE_PlanoStatus::nextStates($from);
        case 'prestacao':
            return SIGE_PrestacaoStatus::nextStates($from);
    }
    return [];
}

/**
 * Label legível em PT-MZ para um status.
 * Usado pela UI para substituir strings técnicas por texto humano.
 */
function sige_fin_fsm_label(string $entity, string $status): string {
    $labels = [
        'lancamento' => [
            'pendente'  => 'Pendente',
            'parcial'   => 'Pago parcialmente',
            'pago'      => 'Pago',
            'cancelado' => 'Cancelado',
            'isento'    => 'Isento',
            'em_plano'  => 'Em plano negociado',
        ],
        'plano' => [
            'activo'    => 'Activo',
            'cumprido'  => 'Cumprido',
            'quebrado'  => 'Quebrado',
            'cancelado' => 'Cancelado',
        ],
        'prestacao' => [
            'pendente'  => 'Pendente',
            'parcial'   => 'Pago parcialmente',
            'pago'      => 'Pago',
            'atrasado'  => 'Atrasado',
            'cancelado' => 'Cancelado',
        ],
    ];
    return $labels[$entity][$status] ?? ucfirst($status);
}
