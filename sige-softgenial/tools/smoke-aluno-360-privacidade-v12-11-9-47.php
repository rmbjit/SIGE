<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$ajax = file_get_contents($root . '/includes/aluno-fetch-ajax.php');
$view = file_get_contents($root . '/admin/academic/alunos_lista.php');
$main = file_get_contents($root . '/sige-softgenial.php');
$build = file_get_contents($root . '/BUILD.json');

function ok($cond, $msg) {
    if (!$cond) {
        fwrite(STDERR, "[FAIL] {$msg}\n");
        exit(1);
    }
    echo "[OK] {$msg}\n";
}

ok(strpos($main, 'Version: 12.11.9.47') !== false, 'Versão do plugin actualizada para 12.11.9.47');
ok(strpos($main, "define('SIGE_VERSION', '12.11.9.47')") !== false, 'Constante SIGE_VERSION actualizada');
ok(strpos($build, '12.11.9.47') !== false, 'BUILD.json actualizado');

ok(strpos($ajax, 'function sige_aluno_360_text') !== false, 'Helper de sanitização de texto existe');
ok(strpos($ajax, 'function sige_aluno_360_secure_document_link') !== false, 'Helper de link documental seguro existe');
ok(strpos($ajax, 'não expõe URL directo gravado na BD') !== false, 'Política de não expor URL directo documentada no código');
ok(strpos($ajax, 'SELECT m.*, t.nome AS turma_nome') === false, 'Ficha 360 não usa SELECT m.* no histórico de matrículas');
ok(strpos($ajax, "SELECT tipo, telefone, status, criado_em, enviado_em, erro") === false, 'Ficha 360 não expõe telefone/erro técnico da fila WhatsApp');
ok(strpos($ajax, "Pagamentos recentes") === false, 'Smoke sanity: string antiga de pagamentos recentes não é requisito da Ficha 360');
ok(strpos($ajax, "'links' => [") === false, 'Payload Ficha 360 não envia links administrativos não utilizados');

ok(strpos($view, 'esc_attr(strtolower(wp_strip_all_tags($nome') !== false, 'data-search passa por escape/sanitização');
ok(strpos($view, 'esc_attr(sanitize_key($raw_status))') !== false, 'data-status passa por sanitize_key/esc_attr');
ok(strpos($view, 'Informação de saúde registada. Consulte a Ficha 360º') !== false, 'Tooltip de saúde deixa de expor detalhes médicos');
ok(strpos($view, '? sige_secure_document_url((int)$a->id') === false, 'Atalho BI não faz fallback para URL directo');
ok(strpos($view, "url.charAt(1) !== '/'") !== false, 'Safe URL JS bloqueia URLs protocol-relative');

echo "\nSmoke v12.11.9.47 concluído com sucesso.\n";
