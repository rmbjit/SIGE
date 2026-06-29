<?php
/**
 * SIGE SoftGenial - Família Service
 *
 * Resolve a fragilidade F6: detecção de irmãos para pagamento em família.
 *
 * Antes (v15.1.0):
 *   SELECT ... FROM sige_alunos a
 *    WHERE REGEXP_REPLACE(telemovel_pai, '[^0-9]', '') = %s
 *       OR REGEXP_REPLACE(telemovel_mae, '[^0-9]', '') = %s
 *
 *   → Full scan em cada carga da página de pagamentos.
 *   → Pai que muda de número "separa" os irmãos.
 *   → Tios que pagam por sobrinhos são ignorados.
 *   → Case-sensitive em prefixo 258 vs sem prefixo.
 *
 * Depois (v15.2.0):
 *   SELECT ... FROM sige_alunos WHERE familia_id = :fid
 *
 *   → Index lookup O(log n).
 *   → Editável manualmente pelo Director (juntar primos, separar).
 *   → Backfill inicial via seed_familias_backfill (migration M20).
 *
 * Este serviço encapsula:
 *   1. Buscar irmãos do aluno (via familia_id)
 *   2. Fallback para método antigo (se familia_id ainda é NULL pós-backfill)
 *   3. Reconciliação manual (criar/editar agregado)
 *
 * @since v15.2.0
 */

if (!defined('ABSPATH')) exit;

final class SIGE_FinanceFamiliaService {

    /**
     * Verifica existência de tabela sem gerar warnings.
     */
    private static function tableExists(string $table): bool {
        global $wpdb;
        if (!$wpdb || $table === '') return false;
        return (string)$wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table;
    }

    /**
     * Verifica se a infraestrutura de agregados familiares está pronta.
     * Em bases antigas, a migração centralizada cria a tabela. Até lá, o
     * serviço deve cair em modo seguro e não bloquear pagamentos.
     */
    private static function familiaTableReady(): bool {
        global $wpdb;
        $tF = $wpdb->prefix . 'sige_agregados_familiares';
        return self::tableExists($tF);
    }

    /**
     * Devolve os irmãos (outros alunos da mesma família) de um aluno.
     *
     * @param int $aluno_id
     * @param int $escola_id
     * @return array<int, object>  Lista de alunos (outros), excluindo o próprio
     */
    public static function buscarIrmaos(int $aluno_id, int $escola_id): array {
        global $wpdb;
        $tA = $wpdb->prefix . 'sige_alunos';

        // 1. Tentar via familia_id (método novo)
        $familia_id = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT familia_id FROM {$tA} WHERE id = %d AND escola_id = %d",
            $aluno_id, $escola_id
        ));

        if ($familia_id > 0) {
            return $wpdb->get_results($wpdb->prepare(
                "SELECT id, nome_completo, numero_processo,
                        telemovel_pai, telemovel_mae, familia_id
                   FROM {$tA}
                  WHERE escola_id = %d
                    AND id != %d
                    AND status = 'activo'
                    AND familia_id = %d
                  ORDER BY nome_completo ASC",
                $escola_id, $aluno_id, $familia_id
            )) ?: [];
        }

        // 2. Fallback legacy - aluno sem familia_id (pós-backfill incompleto)
        //    Só entra aqui se for aluno criado APÓS o backfill M20.
        //    Aproveitamos para LIGAR este aluno a uma família existente ou criar nova.
        return self::buscarIrmaosLegacyEAssociar($aluno_id, $escola_id);
    }

    /**
     * Fallback: procura irmãos por telefone (como antes) e OPORTUNAMENTE
     * associa o aluno a uma família para que na próxima query use o index.
     */
    private static function buscarIrmaosLegacyEAssociar(int $aluno_id, int $escola_id): array {
        global $wpdb;
        $tA = $wpdb->prefix . 'sige_alunos';

        $aluno = $wpdb->get_row($wpdb->prepare(
            "SELECT id, telemovel_pai, telemovel_mae FROM {$tA} WHERE id = %d AND escola_id = %d",
            $aluno_id, $escola_id
        ));

        if (!$aluno) return [];

        $tel_pai = self::normalizarTelefone($aluno->telemovel_pai ?? '');
        $tel_mae = self::normalizarTelefone($aluno->telemovel_mae ?? '');

        if ($tel_pai === '' && $tel_mae === '') return [];

        // Procurar irmãos pelos telefones
        $conds  = [];
        $params = [$escola_id, $aluno_id];

        if ($tel_pai !== '') {
            $conds[] = "RIGHT(REGEXP_REPLACE(COALESCE(telemovel_pai,''), '[^0-9]', ''), 9) = %s";
            $params[] = $tel_pai;
        }
        if ($tel_mae !== '') {
            $conds[] = "RIGHT(REGEXP_REPLACE(COALESCE(telemovel_mae,''), '[^0-9]', ''), 9) = %s";
            $params[] = $tel_mae;
        }

        $cond_sql = implode(' OR ', $conds);

        $irmaos = $wpdb->get_results($wpdb->prepare(
            "SELECT id, nome_completo, numero_processo,
                    telemovel_pai, telemovel_mae, familia_id
               FROM {$tA}
              WHERE escola_id = %d
                AND id != %d
                AND status = 'activo'
                AND ({$cond_sql})
              ORDER BY nome_completo ASC",
            ...$params
        )) ?: [];

        // Associar este aluno a uma família (oportunidade de self-healing)
        if (!empty($irmaos)) {
            self::associarAFamilia($aluno_id, $irmaos, $escola_id, $tel_pai ?: $tel_mae);
        }

        return $irmaos;
    }

    /**
     * Associa o aluno a uma família. Se algum dos irmãos já tem familia_id,
     * reutiliza essa. Caso contrário, cria uma nova.
     */
    private static function associarAFamilia(int $aluno_id, array $irmaos, int $escola_id, string $tel_chave): void {
        if (!sige_tenant_write_guard((int) $escola_id, 'associarAFamilia')) { return; }
        global $wpdb;
        $tA = $wpdb->prefix . 'sige_alunos';
        $tF = $wpdb->prefix . 'sige_agregados_familiares';

        // v12.10.135 - se a tabela ainda não existe, não tentar wpdb->insert().
        // Isto evita o warning SHOW FULL COLUMNS e mantém o pagamento operacional.
        if (!self::familiaTableReady()) {
            return;
        }

        // Alguma das irmãos já tem familia_id?
        $familia_existente = 0;
        foreach ($irmaos as $irmao) {
            if ((int)($irmao->familia_id ?? 0) > 0) {
                $familia_existente = (int)$irmao->familia_id;
                break;
            }
        }

        $fam_id = $familia_existente;

        if ($fam_id === 0) {
            // Criar nova família
            $wpdb->insert($tF, [
                'escola_id'      => $escola_id,
                'telefone_chave' => $tel_chave,
                'nome_agregado'  => null,
                'criado_em'      => current_time('mysql'),
            ]);
            $fam_id = (int)$wpdb->insert_id;
        }

        if ($fam_id <= 0) return; // falha silenciosa - não bloquear o fluxo

        // Associar este aluno + os irmãos sem familia_id
        $aluno_ids = [$aluno_id];
        foreach ($irmaos as $irmao) {
            if ((int)($irmao->familia_id ?? 0) === 0) {
                $aluno_ids[] = (int)$irmao->id;
            }
        }
        $in_sql = implode(',', array_map('intval', $aluno_ids));

        $wpdb->query($wpdb->prepare(
            "UPDATE {$tA}
                SET familia_id = %d
              WHERE id IN ({$in_sql})
                AND escola_id = %d
                AND (familia_id IS NULL OR familia_id = 0)",
            $fam_id, $escola_id
        ));
    }

    /**
     * Normaliza telefone para os últimos 9 dígitos (padrão MZ).
     * Aceita formatos: '+258 84 123 4567', '841234567', '0258841234567', etc.
     */
    public static function normalizarTelefone(string $tel): string {
        $digits = preg_replace('/[^0-9]/', '', $tel);
        if (strlen($digits) < 8) return '';
        return substr($digits, -9); // últimos 9 dígitos
    }

    /**
     * Lista todos os agregados familiares de uma escola.
     * Usado pela UI de gestão manual.
     */
    public static function listarFamilias(int $escola_id): array {
        global $wpdb;
        $tF = $wpdb->prefix . 'sige_agregados_familiares';
        $tA = $wpdb->prefix . 'sige_alunos';

        if (!self::familiaTableReady()) {
            return [];
        }

        return $wpdb->get_results($wpdb->prepare(
            "SELECT f.*,
                    (SELECT COUNT(*) FROM {$tA} a WHERE a.familia_id = f.id AND a.status = 'activo') AS n_alunos
               FROM {$tF} f
              WHERE f.escola_id = %d
              ORDER BY f.nome_agregado ASC, f.id DESC",
            $escola_id
        )) ?: [];
    }

    /**
     * Atribui manualmente um aluno a uma família.
     * Usado pela UI de edição (director pode juntar primos).
     */
    public static function atribuirFamilia(int $aluno_id, int $familia_id, int $escola_id): array {
        if (!sige_tenant_write_guard((int) $escola_id, 'atribuirFamilia')) { return ['ok' => false, 'error' => 'Contexto de escola invalido.']; }
        global $wpdb;

        if (!(function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) && !current_user_can('sige_director')) {
            return ['ok' => false, 'error' => 'Sem permissão.'];
        }

        $tA = $wpdb->prefix . 'sige_alunos';
        $tF = $wpdb->prefix . 'sige_agregados_familiares';

        if (!self::familiaTableReady()) {
            return ['ok' => false, 'error' => 'Agregados familiares ainda não inicializados. Actualize a página como administrador para concluir a preparação do sistema.'];
        }

        // Validar que família existe na mesma escola
        $fam = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM {$tF} WHERE id = %d AND escola_id = %d",
            $familia_id, $escola_id
        ));

        if (!$fam) return ['ok' => false, 'error' => 'Família não encontrada.'];

        $ok = $wpdb->update($tA,
            ['familia_id' => $familia_id],
            ['id' => $aluno_id, 'escola_id' => $escola_id]
        );

        if ($ok === false) {
            return ['ok' => false, 'error' => 'Erro ao atribuir: ' . $wpdb->last_error];
        }

        if (function_exists('sige_audit_log')) {
            sige_audit_log('atribuir_familia', [
                'aluno_id' => $aluno_id,
                'familia_id' => $familia_id,
            ], 'financeiro');
        }

        return ['ok' => true];
    }
}
