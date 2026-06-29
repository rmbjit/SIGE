<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/** Smoke render real do template v12.11.9.30 sem WordPress carregado. */
define('ABSPATH', __DIR__ . '/');
if (!function_exists('wp_strip_all_tags')) { function wp_strip_all_tags($s){ return strip_tags((string)$s); } }
if (!function_exists('remove_accents')) { function remove_accents($s){ return strtr((string)$s, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c','Á'=>'A','À'=>'A','Ã'=>'A','Â'=>'A','É'=>'E','Ê'=>'E','Í'=>'I','Ó'=>'O','Ô'=>'O','Õ'=>'O','Ú'=>'U','Ç'=>'C']); } }
if (!function_exists('sanitize_key')) { function sanitize_key($s){ return preg_replace('/[^a-z0-9_\-]/','', strtolower(remove_accents((string)$s))); } }
if (!function_exists('wp_date')) { function wp_date($fmt,$ts=null){ return date($fmt, $ts ?: time()); } }
if (!function_exists('get_bloginfo')) { function get_bloginfo($k=''){ return 'Escola Teste'; } }
if (!function_exists('add_action')) { function add_action(){ return true; } }
if (!function_exists('add_filter')) { function add_filter(){ return true; } }
if (!function_exists('get_option')) { function get_option($k,$d=null){ return $d; } }
if (!function_exists('update_option')) { function update_option(){ return true; } }
if (!function_exists('current_time')) { function current_time(){ return date('Y-m-d H:i:s'); } }
if (!function_exists('wp_doing_cron')) { function wp_doing_cron(){ return false; } }
if (!function_exists('sige_moeda')) { function sige_moeda(){ return 'MT'; } }
require_once dirname(__DIR__) . '/includes/whatsapp-templates-conversacional.php';
$cfg = (object)['nome_escola'=>'Colégio Teste'];
$aluno = (object)['id'=>1,'nome_completo'=>'Aibo Macuacua','genero'=>'M','nome_mae'=>'Bacar','nome_pai'=>'Ali'];
$msg = sige_wpp_tpl_v2_render('recibo', $cfg, $aluno, [
  'valor'=>100,
  'data'=>'2026-05-31',
  'detalhes'=>"• Transporte Escolar (05/2026) - 600,00 MT (parcial)",
  'id'=>'REC-2026-000034',
  'encarregado'=>'Bacar',
  'encarregado_tipo'=>'mae',
]);
$fail=0; $ok=0; $check=function($cond,$label) use (&$fail,&$ok){ echo ($cond?'OK  - ':'FAIL - ').$label."\n"; $cond?$ok++:$fail++; };
$check(strpos($msg, 'matrícula de') === false, 'não chama transporte de matrícula');
$check(strpos($msg, 'transporte escolar do aluno Aibo') !== false, 'usa serviço real transporte escolar');
$check(strpos($msg, 'Sra. Bacar') !== false, 'mantém tratamento da mãe');
$check(strpos($msg, 'Recibo: #REC-2026-000034') !== false, 'recibo preservado');
$check(strpos($msg, 'Valor') !== false || strpos($msg, 'recebido') !== false, 'miolo financeiro presente');
echo "\nMensagem de exemplo:\n".$msg."\n";
echo "\nResumo: {$ok} OK / {$fail} FAIL\n";
exit($fail>0?1:0);
