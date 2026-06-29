<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
$root = dirname(__DIR__);
$build = json_decode(file_get_contents($root . '/BUILD.json'), true);
$gerador = file_get_contents($root . '/admin/finance/financeiro-gerador.php');
$policy = file_get_contents($root . '/includes/notification-policy.php');
$ok=0; $fail=0;
function check32($label, $cond) {
    global $ok, $fail;
    if ($cond) { $ok++; echo "OK   - {$label}\n"; }
    else { $fail++; echo "FAIL - {$label}\n"; }
}
check32('BUILD version 12.11.9.32', isset($build['version']) && $build['version'] === '12.11.9.32');
check32('Checkbox WhatsApp existe', strpos($gerador, 'id="sg_notificar_whatsapp"') !== false && strpos($gerador, 'name="notificar_whatsapp"') !== false);
check32('Checkbox E-mail existe', strpos($gerador, 'id="sg_notificar_email"') !== false && strpos($gerador, 'name="notificar_email"') !== false);
check32('Mensagens desactivadas por padrão no texto da UI', strpos($gerador, 'Mensagens desactivadas por padrão') !== false);
check32('POST hidden notificar_whatsapp=0 preservado', strpos($gerador, 'name="notificar_whatsapp" value="0"') !== false);
check32('POST hidden notificar_email=0 preservado', strpos($gerador, 'name="notificar_email" value="0"') !== false);
check32('Opt-in directo por POST no gerador', strpos($gerador, '$sg_lanc_bool_post') !== false && strpos($gerador, "array_key_exists(\$campo, \$_POST)") !== false);
check32('WhatsApp depende de opt-in explícito', strpos($gerador, 'if (!empty($notificar_whatsapp_lote)') !== false && strpos($gerador, 'sige_fin_queue_whatsapp') !== false);
check32('E-mail depende de opt-in explícito', strpos($gerador, '&& !empty($notificar_email_lote)') !== false && strpos($gerador, 'sige_fin_email_notificar_encarregados') !== false);
check32('Batch delay WhatsApp aplicado', strpos($gerador, 'sige_notify_apply_batch_delay') !== false && strpos($gerador, '$sige_notif_batch_index') !== false);
check32('Resumo modal inclui estatísticas de E-mail', strpos($gerador, "'email_stats' =>") !== false && strpos($gerador, 'E-mail:') !== false);
check32('Policy helper opt-in existe', strpos($policy, 'function sige_notify_lancamento_manual_optin') !== false);
check32('Policy libera fatura só com opt-in validado', strpos($policy, "['lancamento', 'fatura', 'mensalidade', 'nova_mensalidade']") !== false && strpos($policy, 'wp_verify_nonce') !== false);
check32('Aviso anti-envio em massa existe', strpos($gerador, 'Atenção ao envio em massa') !== false && strpos($gerador, 'risco de restrição no WhatsApp') !== false);
check32('Gerar ano todo bloqueia mensagens automáticas', strpos($gerador, 'Gerar ano todo') !== false && strpos($gerador, 'As mensagens foram desligadas para “Gerar ano todo”') !== false);
check32('Limite seguro de WhatsApp por lote existe', strpos($gerador, 'sige_fin_lancamento_whatsapp_max_lote') !== false && strpos($gerador, '80') !== false);
check32('Política central ainda bloqueia fatura por padrão', strpos($policy, "'fatura'          => false") !== false);
echo "\n{$ok} OK / {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
