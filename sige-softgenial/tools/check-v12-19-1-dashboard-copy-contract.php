<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$errors = [];
$include = $root . '/includes/profile-dashboard-intelligence.php';
$js = $root . '/assets/profile-dashboard-intelligence-v12-19-0.js';
$main = (string) file_get_contents($root . '/sige-softgenial.php');
$src = is_file($include) ? (string) file_get_contents($include) : '';
$jsSrc = is_file($js) ? (string) file_get_contents($js) : '';

preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mVersion);
if (($mVersion[1] ?? '') !== '12.19.1') $errors[] = 'SIGE_VERSION deve ser 12.19.1.';
foreach (['Orientação do painel','Direcção executiva','Veja primeiro o que exige decisão hoje','permissões reais','Não altera dados nem regras do sistema','Primeiro passo recomendado','Atenção antes de agir','Regra de segurança','acção sensível','áreas operacionais visíveis'] as $needle) {
    if (strpos($src . "
" . $jsSrc, $needle) === false) $errors[] = 'copy final ausente: ' . $needle;
}
foreach (['Inteligencia por perfil','Direccao executiva','nao por menu','permissoes reais','accao sensivel','area(s) operacional(is) visivel(is)'] as $needle) {
    if (strpos($src . "
" . $jsSrc, $needle) !== false) $errors[] = 'copy antiga/não acentuada ainda presente: ' . $needle;
}
foreach (['update_option','wp_insert_post','ALTER TABLE','CREATE TABLE','INSERT INTO','UPDATE ','DELETE FROM'] as $needle) {
    if (stripos($src, $needle) !== false) $errors[] = 'hotfix de copy contém escrita proibida: ' . $needle;
}

if ($errors) {
    foreach ($errors as $error) fwrite(STDERR, "ERRO: {$error}
");
    exit(1);
}

echo "v12.19.1 DASHBOARD COPY CONTRACT OK - microcopy final, acentuação e escopo read-only validados.
";
