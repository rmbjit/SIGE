<?php
/**
 * SIGE SoftGenial - Classe Canónica Helper
 *
 * Resolve a fragilidade N4: um serviço do Casa Colorida tem
 * classe = '2º/3º Ano'. A classe do aluno, depois de passar por
 * preg_replace('/[ªº]/u', '', ...) em financeiro-pagamentos.php:256,
 * vem como "2" ou "3". O match LOWER(classe)=LOWER(%s) nunca bate e
 * o serviço nunca é encontrado - o fallback cai no "geral" (classe='todas').
 *
 * Solução: função canónica que normaliza AMBOS os lados (classe do aluno
 * E classe do serviço) para a mesma forma, e suporta ranges tipo '2/3'.
 *
 * Formatos reconhecidos no catálogo de serviços:
 *   '1', '2', '3', ..., '12'          → classe específica
 *   '1ª', '2ª', '3ª', ...             → idem (com ordinal)
 *   'todas', '', '0', NULL            → geral (aplica-se a todas)
 *   '2º/3º Ano', '2/3', '2 e 3'       → range (aplica-se a 2 E 3)
 *   'Pré-primário', 'pre_primario'    → classe especial
 *   '11A', '11B', '11C'               → classes com letra
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

final class SIGE_FinanceClasseHelper {

    /**
     * Lowercase UTF-8 seguro: usa mbstring quando disponível, mas não quebra
     * servidores PHP sem a extensão mbstring.
     */
    private static function lower(string $value): string {
        return function_exists('mb_strtolower') ? mb_strtolower($value, 'UTF-8') : strtolower($value);
    }

    /**
     * Normaliza uma classe (aluno ou serviço) para forma canónica.
     *
     * Canónica = lowercase, sem ordinais (ª/º), sem espaços extra, dígitos
     * principais extraídos. Multi-classe mantém o separador '/'.
     *
     * Exemplos:
     *   '2ª'            → '2'
     *   '3º'            → '3'
     *   '2º/3º Ano'     → '2/3'
     *   'Pré-primário'  → 'pre_primario'
     *   '11A'           → '11a'
     *   '  todas '      → 'todas'
     *   ''              → 'todas'
     */
    public static function canonica(?string $classe): string {
        if ($classe === null) return 'todas';
        $c = trim($classe);
        if ($c === '' || $c === '0') return 'todas';

        $c_lower = self::lower($c);
        if ($c_lower === 'todas') return 'todas';

        // Remover ordinais ª/º
        $c = preg_replace('/[ªº]/u', '', $c);

        // Casos especiais (antes da extração de dígitos)
        $c_lower = self::lower(trim($c));
        if (strpos($c_lower, 'pré-primário') !== false || strpos($c_lower, 'pre-primario') !== false) {
            return 'pre_primario';
        }

        // Range "2/3 Ano", "2 e 3", "2/3"
        // Extrai sequência de dígitos separados por / ou " e "
        if (preg_match_all('/\b(\d{1,2})\b/', $c, $matches) && count($matches[1]) >= 2) {
            $classes = array_unique(array_map('intval', $matches[1]));
            sort($classes);
            return implode('/', array_map('strval', $classes));
        }

        // Classe com letra (11A, 12B)
        if (preg_match('/^(\d{1,2})\s*([a-z])$/i', $c, $m)) {
            return $m[1] . strtolower($m[2]);
        }

        // Classe simples: extrair primeiro número
        if (preg_match('/(\d{1,2})/', $c, $m)) {
            $n = (int)$m[1];
            if ($n >= 1 && $n <= 12) return (string)$n;
        }

        // Fallback: lowercase + remover espaços
        return preg_replace('/\s+/', '_', self::lower($c));
    }

    /**
     * Verifica se um serviço é aplicável a um aluno, dada a classe do aluno.
     *
     * Regras:
     *   • Serviço com classe='todas' ou vazia → sempre aplicável
     *   • Serviço com classe única (ex '5') → só aluno dessa classe
     *   • Serviço com range (ex '2/3') → aluno em qualquer das classes do range
     *   • Serviço com classe especial (ex 'pre_primario') → match exacto
     */
    public static function servicoAplicavelAoAluno($servico, ?string $classe_aluno): bool {
        $cls_srv   = self::canonica($servico->classe ?? null);
        $cls_aluno = self::canonica($classe_aluno);

        // Serviço geral - sempre aplicável
        if ($cls_srv === 'todas') return true;

        // Match directo
        if ($cls_srv === $cls_aluno) return true;

        // Range - verificar se a classe do aluno está nos elementos do range
        if (strpos($cls_srv, '/') !== false) {
            $items = explode('/', $cls_srv);
            return in_array($cls_aluno, $items, true);
        }

        return false;
    }

    /**
     * Devolve uma lista legível das classes cobertas por um serviço.
     * Útil para a UI do catálogo.
     */
    public static function classesDoServico($servico): array {
        $c = self::canonica($servico->classe ?? null);

        if ($c === 'todas') return ['Todas as classes'];
        if ($c === 'pre_primario') return ['Pré-primário'];

        if (strpos($c, '/') !== false) {
            $items = explode('/', $c);
            return array_map(fn($x) => is_numeric($x) ? "{$x}ª" : ucfirst($x), $items);
        }

        return [is_numeric($c) ? "{$c}ª" : ucfirst($c)];
    }
}
