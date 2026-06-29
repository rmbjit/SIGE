<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$base = dirname(__DIR__);
$plugin = file_get_contents($base . '/sige-softgenial.php');
$ajax = file_get_contents($base . '/includes/aluno-fetch-ajax.php');
$view = file_get_contents($base . '/admin/academic/alunos_lista.php');
function ok($cond, $msg) { echo ($cond ? "[OK] " : "[FAIL] ") . $msg . PHP_EOL; if (!$cond) exit(1); }
ok(strpos($plugin, 'Version: 12.11.9.48') !== false, 'Plugin header actualizado para 12.11.9.48');
ok(strpos($plugin, "SIGE_VERSION', '12.11.9.48'") !== false, 'Constante SIGE_VERSION actualizada');
ok(strpos($ajax, 'function sige_ajax_get_aluno_360_core') !== false, 'Handler core da Ficha 360 existe');
ok(strpos($ajax, 'catch (Throwable $e)') !== false, 'Wrapper AJAX defensivo com try/catch existe');
ok(strpos($ajax, 'sige_aluno_360_secure_document_link') !== false, 'Helper de link seguro documental preservado');
ok(strpos($ajax, "return '';\n        if (!function_exists('sige_secure_document_url'))") !== false || strpos($ajax, 'if (!function_exists(\'sige_secure_document_url\')) return \'\';') !== false, 'Sem fallback directo para URL documental');
ok(strpos($ajax, 'telefone de destino nem erro técnico') !== false, 'Minimização WhatsApp preservada');
ok(strpos($ajax, '$mat_status_expr') !== false, 'Consulta de matrículas com coluna status defensiva');
ok(strpos($ajax, '$nota_status_expr') !== false, 'Consulta de notas com coluna status defensiva');
ok(strpos($ajax, '$wpp_status_expr') !== false, 'Consulta WhatsApp com coluna status defensiva');
ok(strpos($view, 'O servidor não respondeu ao carregar a ficha 360º') !== false, 'Mensagem frontend de falha ainda presente');
echo "[OK] Smoke test v12.11.9.48 concluído" . PHP_EOL;
