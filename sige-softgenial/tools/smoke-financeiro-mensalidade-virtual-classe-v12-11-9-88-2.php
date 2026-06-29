<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - v12.11.9.88.2 Financeiro Mensalidade Virtual & Classe Canonical Hotfix.
 * Valida o hotfix que corrige MENS_MM/TRAN_MM/PACK_MM e classes importadas/compostas.
 */
$root = dirname(__DIR__);
$errors = [];
$read = static function (string $rel) use ($root): string {
    $path = $root . '/' . $rel;
    if (!is_file($path)) {
        throw new RuntimeException("Ficheiro ausente: {$rel}");
    }
    return file_get_contents($path);
};
$assert_contains = static function (string $haystack, string $needle, string $label) use (&$errors): void {
    if (strpos($haystack, $needle) === false) {
        $errors[] = "FALHA: {$label}";
    }
};

try {
    $main = $read('sige-softgenial.php');
    $build = $read('BUILD.json');
    $core = $read('includes/finance-core.php');
    $classe = $read('includes/fin-classe-helper.php');
    $pag = $read('admin/finance/financeiro-pagamentos.php');
    $ger = $read('admin/finance/financeiro-gerador.php');

    $assert_contains($main, "Version: 12.11.9.88.2", 'header do plugin actualizado');
    $assert_contains($main, "define('SIGE_VERSION', '12.11.9.88.2');", 'constante SIGE_VERSION actualizada');
    $assert_contains($build, 'financeiro-mensalidade-virtual-classe-canonical-hotfix', 'BUILD.json identifica o hotfix');

    $assert_contains($classe, 'private static function lower', 'helper de classe tem fallback sem mbstring');
    if (preg_match('/[^?]mb_strtolower\s*\(/', $classe) && strpos($classe, "function_exists('mb_strtolower')") === false) {
        $errors[] = 'FALHA: fin-classe-helper.php tem mb_strtolower sem guarda';
    }

    $assert_contains($core, "SIGE_FinanceClasseHelper::servicoAplicavelAoAluno", 'finance-core delega validação de classe para helper canónico');
    $assert_contains($core, 'function sige_fin_find_lancamento_aberto_por_tipo_mes', 'helper de dívida aberta por tipo+mês existe');
    $assert_contains($core, "l.status IN ('pendente','parcial')", 'helper só captura lançamentos accionáveis');
    $assert_contains($core, 'function sige_fin_upsert_acao_label', 'label legível de falhas do upsert existe');
    $assert_contains($core, 'sige_fin_obter_classe_actual_aluno', 'upsert usa resolução canónica de classe actual');

    $assert_contains($pag, "sige_fin_find_lancamento_aberto_por_tipo_mes((int)$" . "aluno_id, 'mensalidade'", 'MENS/PACK procuram dívida aberta de mensalidade antes de criar');
    $assert_contains($pag, "sige_fin_find_lancamento_aberto_por_tipo_mes((int)$" . "aluno_id, 'transporte'", 'TRAN/PACK procuram dívida aberta de transporte antes de criar');
    $assert_contains($pag, 'Sem serviço mensalidade para a classe/turma do aluno.', 'MENS deixa diagnóstico quando serviço não resolve');
    $assert_contains($pag, 'Mensalidade incompatível com a classe/turma do aluno.', 'MENS deixa diagnóstico quando classe bloqueia');
    $assert_contains($pag, 'sige_fin_upsert_acao_label($resM)', 'MENS mostra motivo real do upsert');

    $assert_contains($ger, '$__sige_a_activo_gerador', 'gerador usa condição canónica de aluno activo');
    $assert_contains($ger, 'sige_fin_servico_permitido_para_classe', 'gerador continua validando classe via função canónica');

    // Teste comportamental mínimo do helper de classes sem carregar WordPress.
    if (!defined('ABSPATH')) define('ABSPATH', $root . '/');
    require_once $root . '/includes/fin-classe-helper.php';
    $cases = [
        [(object)['classe' => '2º/3º Ano'], '2', true],
        [(object)['classe' => '2º/3º Ano'], '3º Ano', true],
        [(object)['classe' => '1ª Classe'], '1º Ano', true],
        [(object)['classe' => 'todas'], '11B', true],
        [(object)['classe' => '4ª'], '5ª', false],
    ];
    foreach ($cases as $idx => [$srv, $classe_aluno, $expected]) {
        $got = SIGE_FinanceClasseHelper::servicoAplicavelAoAluno($srv, $classe_aluno);
        if ($got !== $expected) {
            $errors[] = 'FALHA: classe helper case #' . ($idx + 1) . ' esperava ' . ($expected ? 'true' : 'false') . ' e retornou ' . ($got ? 'true' : 'false');
        }
    }

} catch (Throwable $e) {
    $errors[] = 'EXCEPÇÃO: ' . $e->getMessage();
}

if (!empty($errors)) {
    fwrite(STDERR, implode(PHP_EOL, $errors) . PHP_EOL);
    exit(1);
}

echo "SMOKE OK - financeiro mensalidade virtual/classe v12.11.9.88.2 validado." . PHP_EOL;
