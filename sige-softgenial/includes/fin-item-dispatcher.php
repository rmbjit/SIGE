<?php
/**
 * SIGE SoftGenial - Item Dispatcher (Pagamento)
 *
 * Resolve a fragilidade F2: na v15.1.0, o POST de pagamento aceita itens
 * virtuais como strings codificadas (`PACK_04`, `MENS_04`, `TRAN_04`,
 * `EXT_123_04`, `EXT_estudos_04`, `NF_42`, `ATIV_04_42`) parseadas por
 * preg_match em cascata de 7 branches. Adicionar um oitavo formato obriga a
 * tocar em todos os branches. Case-sensitivity do regex `[a-z_]+` já rejeita
 * silenciosamente serviços criados com maiúsculas/acentos.
 *
 * Solução: dispatcher baseado em array tipado. O POST passa a enviar:
 *   items[] = {"kind": "mens",    "mes": 4}
 *   items[] = {"kind": "tran",    "mes": 4}
 *   items[] = {"kind": "ext",     "servico_id": 123, "mes": 4}
 *   items[] = {"kind": "nf",      "servico_id": 42, "qty": 2}
 *   items[] = {"kind": "pack",    "mes": 4}   // materializa mens + tran + extras
 *   items[] = {"kind": "ativ",    "servico_id": 7, "mes": 4}
 *
 * A UI continua a emitir os formatos legados via checkbox `virtuais[]` para
 * não quebrar o form actual - este dispatcher aceita AMBOS. Na v15.3 podemos
 * descontinuar o formato de strings.
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

final class SIGE_FinanceItemDispatcher {

    /**
     * Normaliza uma lista heterogénea de itens virtuais para estrutura tipada.
     *
     * Aceita:
     *   • array de strings legadas ("PACK_04", "EXT_estudos_04", ...)
     *   • array de dicts ["kind" => "mens", "mes" => 4, ...]
     *   • mix dos dois
     *
     * @param array<mixed> $itens_raw  Lista como vem do POST
     * @return array<int, array>       Lista de dicts normalizados
     */
    public static function normalizar(array $itens_raw): array {
        $out = [];

        foreach ($itens_raw as $item) {
            // Caso 1: já é array (formato novo)
            if (is_array($item)) {
                $kind = strtolower(trim((string)($item['kind'] ?? '')));
                if (self::isKindValid($kind)) {
                    $out[] = self::sanitizar($kind, $item);
                }
                continue;
            }

            // Caso 2: string legada - parsear
            if (is_string($item) && $item !== '') {
                $parsed = self::parseLegacy($item);
                if ($parsed) {
                    $out[] = $parsed;
                }
            }
        }

        return $out;
    }

    /**
     * Valida se um kind é conhecido.
     */
    public static function isKindValid(string $kind): bool {
        return in_array($kind, ['pack', 'mens', 'tran', 'ext', 'nf', 'ativ'], true);
    }

    /**
     * Sanitiza um item já tipado.
     */
    private static function sanitizar(string $kind, array $item): array {
        $safe = ['kind' => $kind];

        // Campos comuns
        if (isset($item['mes'])) {
            $mes = (int)$item['mes'];
            if ($mes >= 1 && $mes <= 12) $safe['mes'] = $mes;
        }

        if (isset($item['servico_id'])) {
            $sid = (int)$item['servico_id'];
            if ($sid > 0) $safe['servico_id'] = $sid;
        }

        if (isset($item['qty'])) {
            $qty = (int)$item['qty'];
            $safe['qty'] = max(1, min(99, $qty));
        }

        if (isset($item['tipo'])) {
            // tipo textual (legacy EXT_estudos_04)
            $tipo = strtolower(trim((string)$item['tipo']));
            if (preg_match('/^[a-z0-9_]+$/', $tipo)) {
                $safe['tipo'] = $tipo;
            }
        }

        return $safe;
    }

    /**
     * Parsing do formato legado.
     *
     * Formatos reconhecidos:
     *   PACK_04       → pack completo Abril (mensalidade + transporte)
     *   MENS_04       → só mensalidade Abril
     *   TRAN_04       → só transporte Abril
     *   EXT_123_04    → serviço extra ID 123 em Abril (formato novo por ID)
     *   EXT_estudos_04 → serviço extra tipo=estudos em Abril (formato legacy)
     *   NF_42         → serviço avulso ID 42
     *   ATIV_04_7     → actividade ID 7 em Abril
     *
     * Nota sobre case-sensitivity: aceitamos case-insensitive agora (era
     * bug silencioso quando um serviço tinha tipo com maiúscula).
     */
    public static function parseLegacy(string $str): ?array {
        $s = strtoupper(trim($str)); // normaliza para case-insensitive

        // Ordem importa - EXT_<id>_<mes> antes de EXT_<tipo>_<mes>
        if (preg_match('/^PACK_(\d{1,2})$/', $s, $m)) {
            return ['kind' => 'pack', 'mes' => (int)$m[1]];
        }

        if (preg_match('/^MENS_(\d{1,2})$/', $s, $m)) {
            return ['kind' => 'mens', 'mes' => (int)$m[1]];
        }

        if (preg_match('/^TRAN_(\d{1,2})$/', $s, $m)) {
            return ['kind' => 'tran', 'mes' => (int)$m[1]];
        }

        if (preg_match('/^EXT_(\d+)_(\d{1,2})$/', $s, $m)) {
            return ['kind' => 'ext', 'servico_id' => (int)$m[1], 'mes' => (int)$m[2]];
        }

        // EXT_TIPO_MES - tipo pode ter underscores (ex: PEQUENO_ALMOCO_04)
        if (preg_match('/^EXT_([A-Z][A-Z0-9_]*)_(\d{1,2})$/', $s, $m)) {
            return ['kind' => 'ext', 'tipo' => strtolower($m[1]), 'mes' => (int)$m[2]];
        }

        if (preg_match('/^NF_(\d+)$/', $s, $m)) {
            return ['kind' => 'nf', 'servico_id' => (int)$m[1]];
        }

        if (preg_match('/^ATIV_(\d{1,2})_(\d+)$/', $s, $m)) {
            return ['kind' => 'ativ', 'mes' => (int)$m[1], 'servico_id' => (int)$m[2]];
        }

        return null; // formato desconhecido
    }

    /**
     * Codifica um item tipado de volta no formato legado (para UI antiga).
     * Só é necessário para manter form HTML compatível durante a transição.
     */
    public static function toLegacy(array $item): ?string {
        $kind = $item['kind'] ?? '';
        $mes = str_pad((string)($item['mes'] ?? 0), 2, '0', STR_PAD_LEFT);

        switch ($kind) {
            case 'pack': return "PACK_{$mes}";
            case 'mens': return "MENS_{$mes}";
            case 'tran': return "TRAN_{$mes}";
            case 'ext':
                if (!empty($item['servico_id'])) return "EXT_{$item['servico_id']}_{$mes}";
                if (!empty($item['tipo']))       return "EXT_" . strtoupper($item['tipo']) . "_{$mes}";
                return null;
            case 'nf':   return isset($item['servico_id']) ? "NF_{$item['servico_id']}" : null;
            case 'ativ':
                if (!empty($item['servico_id'])) return "ATIV_{$mes}_{$item['servico_id']}";
                return null;
        }
        return null;
    }
}
