<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
// Smoke estático v12.11.9.32 - lançamento com notificações opt-in.
$root = dirname(__DIR__);
$file = $root . '/admin/finance/financeiro-gerador.php';
$src = file_get_contents($file);
$checks = [
    'versao_build_32' => strpos(file_get_contents($root . '/BUILD.json'), '12.11.9.32') !== false,
    'checkbox_whatsapp_visivel' => strpos($src, 'id="sg_notificar_whatsapp"') !== false,
    'checkbox_email_visivel' => strpos($src, 'id="sg_notificar_email"') !== false,
    'hidden_whatsapp_zero' => strpos($src, 'name="notificar_whatsapp" value="0"') !== false,
    'hidden_email_zero' => strpos($src, 'name="notificar_email" value="0"') !== false,
    'leitura_directa_post_segura' => strpos($src, '$sg_lanc_bool_post') !== false,
    'policy_event_allowed_fatura' => strpos($src, "sige_notify_event_allowed('fatura')") !== false,
    'variavel_evento_lote_central' => strpos($src, '$notificacao_evento_lote') !== false,
    'whatsapp_apenas_optin' => strpos($src, 'if (!empty($notificacao_evento_lote) && !empty($notificar_whatsapp_lote) && !$_skip_wpp)') !== false
        && strpos($src, 'if (!empty($notificar_whatsapp_lote)) {') !== false,
    'email_apenas_optin' => strpos($src, 'if (!empty($notificacao_evento_lote) && !empty($notificar_email_lote)') !== false,
    'sem_numero_so_quando_whatsapp_on' => strpos($src, 'if (!empty($notificar_whatsapp_lote) && !$tel_num)') !== false,
    'fila_delay_batch' => strpos($src, 'sige_notify_apply_batch_delay') !== false,
    'modal_confirmacao_mensagens' => strpos($src, 'sg-generator-confirm-notifications') !== false,
    'modal_sucesso_email_stats' => strpos($src, '\'email_stats\' => $email_stats') !== false,
    'sucesso_whatsapp_agendadas' => strpos($src, 'agendadas') !== false,
    'alerta_risco_whatsapp' => strpos($src, 'Atenção ao envio em massa') !== false,
];
$ok = 0;
$fail = 0;
foreach ($checks as $name => $passed) {
    if ($passed) { echo "OK  - {$name}\n"; $ok++; }
    else { echo "FAIL- {$name}\n"; $fail++; }
}
echo "\nResultado: {$ok} OK / {$fail} FAIL\n";
exit($fail > 0 ? 1 : 0);
