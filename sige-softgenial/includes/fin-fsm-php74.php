<?php
/**
 * SIGE SoftGenial - FSMs fallback (PHP 7.4 / 8.0)
 *
 * Implementação em classes com constantes. Mesma API pública que a
 * versão PHP 8.1+ (fin-fsm-php81.php) mas sem type safety de enums.
 *
 * As matrizes de transição aqui DEVEM ser idênticas às do ficheiro 8.1.
 * Se alterar uma, alterar ambas.
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

final class SIGE_LancamentoStatus {
    const PENDENTE  = 'pendente';
    const PARCIAL   = 'parcial';
    const PAGO      = 'pago';
    const CANCELADO = 'cancelado';
    const ISENTO    = 'isento';
    const EM_PLANO  = 'em_plano';

    private static function matrix(): array {
        return [
            self::PENDENTE  => [self::PARCIAL, self::PAGO, self::CANCELADO, self::ISENTO, self::EM_PLANO],
            self::PARCIAL   => [self::PAGO, self::CANCELADO, self::EM_PLANO],
            self::PAGO      => [self::CANCELADO], // só via estorno
            self::CANCELADO => [self::PENDENTE],
            self::ISENTO    => [self::PENDENTE],
            self::EM_PLANO  => [self::PAGO, self::CANCELADO],
        ];
    }

    public static function canTransitionTo(string $from, string $to): bool {
        if ($from === $to) return false;
        $m = self::matrix();
        return isset($m[$from]) && in_array($to, $m[$from], true);
    }

    public static function nextStates(string $from): array {
        $m = self::matrix();
        return $m[$from] ?? [];
    }

    public static function isValid(string $status): bool {
        return array_key_exists($status, self::matrix());
    }

    public static function all(): array {
        return array_keys(self::matrix());
    }
}

final class SIGE_PlanoStatus {
    const ACTIVO    = 'activo';
    const CUMPRIDO  = 'cumprido';
    const QUEBRADO  = 'quebrado';
    const CANCELADO = 'cancelado';

    private static function matrix(): array {
        return [
            self::ACTIVO    => [self::CUMPRIDO, self::QUEBRADO, self::CANCELADO],
            self::QUEBRADO  => [self::ACTIVO, self::CANCELADO],
            self::CUMPRIDO  => [], // terminal
            self::CANCELADO => [], // terminal
        ];
    }

    public static function canTransitionTo(string $from, string $to): bool {
        if ($from === $to) return false;
        $m = self::matrix();
        return isset($m[$from]) && in_array($to, $m[$from], true);
    }

    public static function nextStates(string $from): array {
        $m = self::matrix();
        return $m[$from] ?? [];
    }

    public static function isValid(string $status): bool {
        return array_key_exists($status, self::matrix());
    }

    public static function all(): array {
        return array_keys(self::matrix());
    }
}

final class SIGE_PrestacaoStatus {
    const PENDENTE  = 'pendente';
    const PARCIAL   = 'parcial';
    const PAGO      = 'pago';
    const ATRASADO  = 'atrasado';
    const CANCELADO = 'cancelado';

    private static function matrix(): array {
        return [
            self::PENDENTE  => [self::PARCIAL, self::PAGO, self::ATRASADO, self::CANCELADO],
            self::PARCIAL   => [self::PAGO, self::ATRASADO, self::CANCELADO],
            self::ATRASADO  => [self::PARCIAL, self::PAGO, self::CANCELADO],
            self::PAGO      => [self::CANCELADO],
            self::CANCELADO => [],
        ];
    }

    public static function canTransitionTo(string $from, string $to): bool {
        if ($from === $to) return false;
        $m = self::matrix();
        return isset($m[$from]) && in_array($to, $m[$from], true);
    }

    public static function nextStates(string $from): array {
        $m = self::matrix();
        return $m[$from] ?? [];
    }

    public static function isValid(string $status): bool {
        return array_key_exists($status, self::matrix());
    }

    public static function all(): array {
        return array_keys(self::matrix());
    }
}
