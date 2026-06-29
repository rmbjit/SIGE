<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Release Gate (gate vivo)
 *
 * Valida invariantes estruturais e de copy do pacote, sem literais de
 * versão congelados: continua válido em versões futuras.
 *
 * Executar: php tools/smoke-release-gate.php
 */

$root = dirname(__DIR__);
$fails = [];
$oks = 0;
$check = static function (bool $cond, string $label) use (&$fails, &$oks): void {
    if ($cond) { $oks++; echo "OK   {$label}\n"; }
    else { $fails[] = $label; echo "FAIL {$label}\n"; }
};
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

// ── 1. Sincronização de versão (3 fontes, sem literal congelado) ──
$main = $read('sige-softgenial.php');
$build = $read('BUILD.json');
preg_match('/^\s*\*\s*Version:\s*([0-9.]+)/mi', $main, $mH);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $main, $mC);
$bj = json_decode($build, true);
$vH = $mH[1] ?? ''; $vC = $mC[1] ?? ''; $vB = is_array($bj) ? (string)($bj['version'] ?? '') : '';
$check($vH !== '' && $vH === $vC && $vC === $vB, "Versão sincronizada nas 3 fontes (header={$vH}, const={$vC}, build={$vB})");

// ── 2. Higiene binária: sem CRLF/CR isolado/BOM/travessões em código ──
$crlf = $cr = $bom = $dash = 0; $guardless = [];
$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($rii as $f) {
    $p = $f->getPathname();
    if (strpos($p, '/docs/') !== false) continue;
    if (strpos($p, '/assets/vendor/') !== false) continue; // bibliotecas vendorizadas (third-party) isentas da higiene de codigo nosso
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if (!in_array($ext, ['php','js','css'], true)) continue;
    $b = (string)file_get_contents($p);
    if (strpos($b, "\r\n") !== false) $crlf++;
    if (strpos(str_replace("\r\n", '', $b), "\r") !== false) $cr++;
    if (strncmp($b, "\xEF\xBB\xBF", 3) === 0) $bom++;
    if (strpos($b, "\u{2014}") !== false || strpos($b, "\u{2013}") !== false) $dash++;
    if ($ext === 'php' && strpos($p, '/tools/') !== false && basename($p) !== 'index.php'
        && strpos($b, "PHP_SAPI !== 'cli'") === false && strpos($b, 'PHP_SAPI != "cli"') === false) {
        $guardless[] = basename($p);
    }
}
$check($crlf === 0, 'Zero ficheiros com CRLF em código');
$check($cr === 0, 'Zero CR isolado em código');
$check($bom === 0, 'Zero BOM em código');
$check($dash === 0, 'Zero travessões (em/en-dash) em código');
$check(empty($guardless), 'Todos os tools/*.php têm guarda CLI' . (empty($guardless) ? '' : ' (faltam: ' . implode(', ', $guardless) . ')'));

// ── 2b. Travessões em DOCUMENTOS (.md/.txt): a regra de zero em/en-dash aplica-se a todo o output ──
$docDash = 0; $docDashFiles = [];
$riiDoc = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($riiDoc as $f) {
    $p = $f->getPathname();
    $ext = strtolower(pathinfo($p, PATHINFO_EXTENSION));
    if (!in_array($ext, ['md','txt'], true)) continue;
    $b = (string)file_get_contents($p);
    if (strpos($b, "\u{2014}") !== false || strpos($b, "\u{2013}") !== false) { $docDash++; if (count($docDashFiles) < 8) $docDashFiles[] = basename($p); }
}
$check($docDash === 0, 'Zero travessões (em/en-dash) em documentos (.md/.txt)' . ($docDash ? ' (' . $docDash . ' ficheiros: ' . implode(', ', $docDashFiles) . ')' : ''));

// ── 3. Estrutura: raiz limpa e docs/ presente ──
$rootFiles = array_values(array_filter(scandir($root), static fn($f) => is_file($root . '/' . $f)));
sort($rootFiles);
$expectedRoot = ['.gitignore','BUILD.json','CHANGELOG.md','README.md','index.php','sige-softgenial.php','uninstall.php'];
sort($expectedRoot);
$check($rootFiles === $expectedRoot, 'Raiz contém apenas os 7 ficheiros canónicos (' . implode(', ', $rootFiles) . ')');
// Pastas de tooling permitidas na raiz (além das funcionais): .github para o CI
$rootDirs = array_values(array_filter(scandir($root), static fn($f) => $f !== '.' && $f !== '..' && is_dir($root . '/' . $f)));
$dirsPermitidas = ['.github','admin','assets','docs','includes','languages','tools'];
$dirsForaDaLista = array_diff($rootDirs, $dirsPermitidas);
$check(empty($dirsForaDaLista), 'Raiz sem pastas fora da lista canónica' . (empty($dirsForaDaLista) ? '' : ' (intrusas: ' . implode(', ', $dirsForaDaLista) . ')'));
foreach (['docs/changelog','docs/deploy','docs/qa','docs/relatorios','docs/legado','languages','tools'] as $d) {
    $check(is_dir($root . '/' . $d), "Pasta {$d}/ presente");
}
$check(is_file($root . '/tools/.htaccess'), 'tools/.htaccess presente');
foreach (['admin','includes','assets','tools','admin/finance','admin/academic','includes/settings'] as $d) {
    $check(is_file($root . '/' . $d . '/index.php'), "index.php de silêncio em {$d}/");
}
$check(!file_exists($root . '/admin/academic/sedw9tqQh'), 'Ficheiro órfão sedw9tqQh ausente');

// ── 4. Router: rotas críticas mapeadas ──
$shell = $read('includes/admin-shell.php');
$check(strpos($shell, "'vincular'        => 'admin/academic/vincular-view.php'") !== false, "Rota 'vincular' mapeada");
$check(strpos($shell, "'whatsapp_central' => 'admin/whatsapp_central-view.php'") !== false, "Rota 'whatsapp_central' mapeada");
$check(strpos($shell, "'whatsapp_diag'    => 'admin/whatsapp_diag-view.php'") !== false, "Rota 'whatsapp_diag' mapeada");
$check(strpos($shell, "'whatsapp_circulares' => 'admin/whatsapp_circulares-view.php'") !== false, "Rota 'whatsapp_circulares' mapeada");
$check(strpos($shell, "'presencas'        => 'admin/academic/presencas-view.php'") !== false, "Rota 'presencas' mapeada");
$check(strpos($shell, "'financeiro-mpesa' => 'admin/finance/mpesa-view.php'") !== false, "Rota 'financeiro-mpesa' mapeada");
// Allowlist x mapa x ficheiros: tudo o que está na allowlist resolve para ficheiro existente
if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $mAl) && preg_match('/\$map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $mMp)) {
    preg_match_all("/'([a-z0-9_\\-]+)'/", $mAl[1], $av);
    preg_match_all("/'([a-z0-9_\\-]+)'\s*=>\s*'([^']+)'/", $mMp[1], $mv, PREG_SET_ORDER);
    $map = [];
    foreach ($mv as $row) { $map[$row[1]] = $row[2]; }
    $broken = [];
    foreach (array_unique($av[1]) as $v) {
        $rel = $map[$v] ?? ('admin/' . $v . '-view.php');
        if (!is_file($root . '/' . $rel) && !is_file($root . '/admin/' . basename($rel))) $broken[] = $v;
    }
    $check(empty($broken), 'Todas as views da allowlist resolvem para ficheiro' . (empty($broken) ? '' : ' (quebradas: ' . implode(', ', $broken) . ')'));
} else {
    $check(false, 'Allowlist e mapa de views encontrados no admin-shell');
}

// ── 5. Copy de produto: jargão técnico ausente do texto visível ──
$diag = $read('admin/whatsapp_diag-view.php');
$central = $read('admin/whatsapp_central-view.php');
$acta = $read('admin/academic/acta-view.php');
$extr = $read('admin/finance/financeiro-extratos.php');
$pag = $read('admin/finance/financeiro-pagamentos.php');
$transp = $read('admin/logistics/transporte-view.php');
$cur = $read('admin/system/curriculum-engine-view.php');
$perm = $read('admin/system/permissions-ui.php');
$check(!preg_match('/>v12\.11\.8</', $diag), 'Diagnóstico WhatsApp sem badge de versão obsoleta');
$check(strpos($diag, 'Tarefas automáticas de envio') !== false, "Diagnóstico usa 'Tarefas automáticas de envio'");
$check(strpos($central, 'Registar pending') === false && strpos($central, 'pending_receipt para os recibos') === false, 'Central WhatsApp sem jargão pending/backfill na copy');
$check(strpos($central, 'criados antes da v12.9.61') === false, 'Central WhatsApp sem referência de versão na copy');
$check(strpos($acta, '(v12.11.9.16)') === false, 'Acta sem referência de versão na copy');
$check(strpos($acta, 'Censo oficial guardado') !== false, "Acta usa 'Censo' em vez de 'snapshot' na copy");
$check(strpos($extr, 'Reconciliação concluída') !== false && strpos($extr, 'Reconciliação PRO</span>') === false, "Extractos: selo 'Reconciliação concluída'");
$check(strpos($extr, 'FECHO DE CAIXA COM RECONCILIAÇÃO') !== false, 'Extractos: cabeçalho humano da nota de fecho');
$check(strpos($extr, '[SIGE_RECON_V1]') !== false, 'Extractos: marcador de reconciliação intacto');
$check(strpos($pag, 'Pedido inválido (nonce)') === false && strpos($transp, 'Pedido inválido (nonce)') === false, "Mensagens '(nonce)' substituídas por linguagem de sessão");
$check(strpos($pag, 'A sessão expirou por segurança') !== false, 'Pagamentos: nova mensagem de sessão presente');
$check(strpos($cur, 'Gestão de Currículos') !== false && strpos($cur, 'migração do schema') === false && strpos($cur, 'Legacy safe') === false, 'Currículos: copy de produto');
$check(strpos($perm, 'Matriz de permissões activa') !== false, 'Permissões: copy de produto');

// ── 6. Ortografia pré-AO90 nas strings corrigidas ──
$check(strpos($extr, 'data-label="Seleccionar"') !== false && strpos($extr, 'data-label="Acções"') !== false, 'Extractos: data-labels pré-AO90');
$check(strpos($read('assets/style.css'), "content:'Seleccionar'") !== false, 'CSS: pseudo-elemento Seleccionar pré-AO90');
$check(strpos($read('includes/db-handler.php'), 'Ficha actualizada com sucesso!') !== false, 'db-handler: mensagem actualizada pré-AO90');
$check(strpos($read('admin/finance/financeiro-lancamentos-view.php'), 'Confirmar Reactivação') !== false, 'Lançamentos: Reactivação pré-AO90');
// Contratos de dados intocados
$check(strpos($read('admin/finance/financeiro-lancamentos-view.php'), "value=\"reativar_lancamento\"") !== false, "Contrato de acção 'reativar_lancamento' intocado");
$check(strpos($read('includes/core-helpers.php'), "'activo','ativo'") !== false, 'Dupla grafia de compatibilidade na camada de dados intocada');

// ── 7. Higiene de pacote ──
$check(is_file($root . '/uninstall.php') && strpos($read('uninstall.php'), 'WP_UNINSTALL_PLUGIN') !== false, 'uninstall.php presente e guardado');
$check(strpos($read('CHANGELOG.md'), '## v' . $vC) !== false, 'CHANGELOG.md contém a entrada da versão corrente');
$check(is_file($root . '/docs/changelog/CHANGELOG-v' . str_replace('.', '-', $vC) . '.txt'), 'Changelog detalhado da versão corrente em docs/changelog/');

echo str_repeat('-', 60) . "\n";
if ($fails) {
    fwrite(STDERR, 'RELEASE GATE FALHOU: ' . count($fails) . " falha(s)\n");
    foreach ($fails as $f) fwrite(STDERR, " - {$f}\n");
    exit(1);
}
echo "RELEASE GATE OK - {$oks} verificações passaram (v{$vC}).\n";
