<?php
/**
 * SIGE SoftGenial - FSMs via enums (PHP 8.1+)
 *
 * Implementação canónica das três máquinas de estado.
 * Ver fin-fsm.php para a camada pública que usa estes enums.
 *
 * Prefixo SIGE_ nas classes para não colidir com eventuais enums nativos
 * futuros ou namespaces de outros plugins.
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

// ═══════════════════════════════════════════════════════════════════════════
// LANÇAMENTO - status de uma conta a receber
// ═══════════════════════════════════════════════════════════════════════════

enum SIGE_LancamentoStatus_Enum: string {
    case Pendente  = 'pendente';
    case Parcial   = 'parcial';
    case Pago      = 'pago';
    case Cancelado = 'cancelado';
    case Isento    = 'isento';
    case EmPlano   = 'em_plano';

    /**
     * Matriz de transições permitidas.
     *
     * Regras de negócio:
     *   • pendente → qualquer excepto si mesmo
     *   • parcial  → pago (quando completa) | cancelado (raro) | em_plano (negociado)
     *   • pago     → cancelado (só via estorno, que limpa valor_pago) - para
     *                tudo o resto é imutável. Isento também é terminal.
     *   • cancelado → pendente (reactivação pelo Director)
     *   • isento   → pendente (reactivação pelo Director - raro mas possível)
     *   • em_plano → pago (prestações completas) | quebrado (plano falha)
     */
    public function canTransitionTo(self $to): bool {
        if ($this === $to) return false;

        return match ($this) {
            self::Pendente  => in_array($to, [self::Parcial, self::Pago, self::Cancelado, self::Isento, self::EmPlano], true),
            self::Parcial   => in_array($to, [self::Pago, self::Cancelado, self::EmPlano], true),
            self::Pago      => $to === self::Cancelado, // só via estorno
            self::Cancelado => $to === self::Pendente,   // reactivação
            self::Isento    => $to === self::Pendente,   // reactivação
            self::EmPlano   => in_array($to, [self::Pago, self::Cancelado], true), // quebrado decide-se no plano, não no lançamento
        };
    }

    public function nextStates(): array {
        $out = [];
        foreach (self::cases() as $target) {
            if ($this->canTransitionTo($target)) {
                $out[] = $target->value;
            }
        }
        return $out;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// PLANO DE PAGAMENTO - status do plano negociado (agregado de prestações)
// ═══════════════════════════════════════════════════════════════════════════

enum SIGE_PlanoStatus_Enum: string {
    case Activo    = 'activo';
    case Cumprido  = 'cumprido';
    case Quebrado  = 'quebrado';
    case Cancelado = 'cancelado';

    /**
     * Regras:
     *   • activo    → cumprido (todas prestações pagas)
     *                 quebrado (prestações atrasadas passam limite)
     *                 cancelado (decisão da direcção)
     *   • quebrado  → activo (reacordado) | cancelado (abandonado)
     *   • cumprido  → terminal
     *   • cancelado → terminal (não reactivável - abre-se novo plano)
     */
    public function canTransitionTo(self $to): bool {
        if ($this === $to) return false;

        return match ($this) {
            self::Activo    => in_array($to, [self::Cumprido, self::Quebrado, self::Cancelado], true),
            self::Quebrado  => in_array($to, [self::Activo, self::Cancelado], true),
            self::Cumprido  => false,
            self::Cancelado => false,
        };
    }

    public function nextStates(): array {
        $out = [];
        foreach (self::cases() as $target) {
            if ($this->canTransitionTo($target)) {
                $out[] = $target->value;
            }
        }
        return $out;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// PRESTAÇÃO - status de uma prestação individual dentro de um plano
// ═══════════════════════════════════════════════════════════════════════════

enum SIGE_PrestacaoStatus_Enum: string {
    case Pendente  = 'pendente';
    case Parcial   = 'parcial';
    case Pago      = 'pago';
    case Atrasado  = 'atrasado';
    case Cancelado = 'cancelado';

    /**
     * Regras:
     *   • pendente → parcial | pago | atrasado | cancelado
     *   • parcial  → pago | atrasado | cancelado
     *   • atrasado → parcial | pago | cancelado (pode ainda ser pago tarde)
     *   • pago     → cancelado (estorno)
     *   • cancelado → terminal
     */
    public function canTransitionTo(self $to): bool {
        if ($this === $to) return false;

        return match ($this) {
            self::Pendente  => in_array($to, [self::Parcial, self::Pago, self::Atrasado, self::Cancelado], true),
            self::Parcial   => in_array($to, [self::Pago, self::Atrasado, self::Cancelado], true),
            self::Atrasado  => in_array($to, [self::Parcial, self::Pago, self::Cancelado], true),
            self::Pago      => $to === self::Cancelado,
            self::Cancelado => false,
        };
    }

    public function nextStates(): array {
        $out = [];
        foreach (self::cases() as $target) {
            if ($this->canTransitionTo($target)) {
                $out[] = $target->value;
            }
        }
        return $out;
    }
}

// ═══════════════════════════════════════════════════════════════════════════
// FACADES - API pública com assinatura estável entre PHP 7.4 e 8.1
// ═══════════════════════════════════════════════════════════════════════════

/**
 * Facade estática para LancamentoStatus.
 * A camada pública (fin-fsm.php) chama SIGE_LancamentoStatus::canTransitionTo($from, $to).
 */
final class SIGE_LancamentoStatus {
    public static function canTransitionTo(string $from, string $to): bool {
        $f = SIGE_LancamentoStatus_Enum::tryFrom($from);
        $t = SIGE_LancamentoStatus_Enum::tryFrom($to);
        if (!$f || !$t) return false;
        return $f->canTransitionTo($t);
    }

    public static function nextStates(string $from): array {
        $f = SIGE_LancamentoStatus_Enum::tryFrom($from);
        return $f ? $f->nextStates() : [];
    }

    public static function isValid(string $status): bool {
        return SIGE_LancamentoStatus_Enum::tryFrom($status) !== null;
    }

    public static function all(): array {
        return array_map(fn($c) => $c->value, SIGE_LancamentoStatus_Enum::cases());
    }
}

final class SIGE_PlanoStatus {
    public static function canTransitionTo(string $from, string $to): bool {
        $f = SIGE_PlanoStatus_Enum::tryFrom($from);
        $t = SIGE_PlanoStatus_Enum::tryFrom($to);
        if (!$f || !$t) return false;
        return $f->canTransitionTo($t);
    }

    public static function nextStates(string $from): array {
        $f = SIGE_PlanoStatus_Enum::tryFrom($from);
        return $f ? $f->nextStates() : [];
    }

    public static function isValid(string $status): bool {
        return SIGE_PlanoStatus_Enum::tryFrom($status) !== null;
    }

    public static function all(): array {
        return array_map(fn($c) => $c->value, SIGE_PlanoStatus_Enum::cases());
    }
}

final class SIGE_PrestacaoStatus {
    public static function canTransitionTo(string $from, string $to): bool {
        $f = SIGE_PrestacaoStatus_Enum::tryFrom($from);
        $t = SIGE_PrestacaoStatus_Enum::tryFrom($to);
        if (!$f || !$t) return false;
        return $f->canTransitionTo($t);
    }

    public static function nextStates(string $from): array {
        $f = SIGE_PrestacaoStatus_Enum::tryFrom($from);
        return $f ? $f->nextStates() : [];
    }

    public static function isValid(string $status): bool {
        return SIGE_PrestacaoStatus_Enum::tryFrom($status) !== null;
    }

    public static function all(): array {
        return array_map(fn($c) => $c->value, SIGE_PrestacaoStatus_Enum::cases());
    }
}
