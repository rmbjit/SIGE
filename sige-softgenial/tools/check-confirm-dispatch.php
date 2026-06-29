<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Gate estatico: despacho do dialogo de confirmacao.
 * Tranca o invariante corrigido na v12.12.36:
 *   1. O handler de submissao respeita o botao premido (submitter) e nao herda o
 *      dialogo de outro botao do mesmo formulario.
 *   2. Nenhum formulario tem um botao de submissao com [data-sige-confirm] ao lado
 *      de outro sem, padrao que mostrava o dialogo errado e submetia a accao errada.
 *   3. O botao Aprovar do view aprovar_notas tem o seu proprio dialogo.
 */
$root = dirname(__DIR__);
$fails = [];
$read = static function (string $rel) use ($root): string {
    $p = $root . '/' . $rel;
    return is_file($p) ? (string)file_get_contents($p) : '';
};

// 1. Handler corrige a heranca do dialogo.
$js = $read('assets/sige-ui.js');
if ($js === '') {
    $fails[] = 'assets/sige-ui.js ausente';
} else {
    // A versao corrigida usa if/else sobre ev.submitter; a buggy usava o ternario
    // "? ev.submitter ... : form.querySelector(...)" que herdava outro botao.
    if (strpos($js, '? ev.submitter') !== false) {
        $fails[] = 'handler ainda usa o recuo por ternario (herda o dialogo de outro botao)';
    }
    if (strpos($js, 'if (ev.submitter)') === false) {
        $fails[] = 'handler nao respeita explicitamente o submitter premido';
    }
}

// 2. Nenhum formulario com padrao misto (confirm + nao-confirm no mesmo form).
$scan_files = [];
foreach (['admin', 'includes'] as $d) {
    $dir = $root . '/' . $d;
    if (!is_dir($dir)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $fileInfo) {
        if ($fileInfo->isFile() && strtolower($fileInfo->getExtension()) === 'php') {
            $scan_files[] = $fileInfo->getPathname();
        }
    }
}
$mistos = [];
foreach ($scan_files as $abs) {
    $s = (string)@file_get_contents($abs);
    if (strpos($s, 'data-sige-confirm') === false) continue;
    if (preg_match_all('/<form\b.*?<\/form>/is', $s, $forms)) {
        foreach ($forms[0] as $block) {
            $btns = [];
            if (preg_match_all('/<button\b[^>]*type=["\']submit["\'][^>]*>/i', $block, $b1)) $btns = array_merge($btns, $b1[0]);
            if (preg_match_all('/<input\b[^>]*type=["\']submit["\'][^>]*>/i', $block, $b2)) $btns = array_merge($btns, $b2[0]);
            if (count($btns) < 2) continue;
            $with = 0; $without = 0;
            foreach ($btns as $b) { (strpos($b, 'data-sige-confirm') !== false) ? $with++ : $without++; }
            if ($with > 0 && $without > 0) $mistos[] = str_replace($root . '/', '', $abs);
        }
    }
}
if ($mistos) {
    $fails[] = 'formularios com padrao misto (um botao confirma e outro nao): ' . implode(', ', array_unique($mistos));
}

// 3. Aprovar notas tem o seu proprio dialogo.
$apn = $read('admin/academic/aprovar_notas-view.php');
if ($apn !== '') {
    if (!preg_match('/value=["\']aprovar["\'][^>]*data-sige-confirm=/', $apn)) {
        $fails[] = 'botao Aprovar sem o seu proprio data-sige-confirm';
    }
    if (strpos($apn, 'data-sige-titulo="Aprovar notas"') === false) {
        $fails[] = 'dialogo do botao Aprovar sem o titulo correcto';
    }
} else {
    $fails[] = 'admin/academic/aprovar_notas-view.php ausente';
}

if ($fails) {
    fwrite(STDERR, "DESPACHO DE CONFIRMACAO FALHOU\n - " . implode("\n - ", $fails) . "\n");
    exit(1);
}
echo 'DESPACHO DE CONFIRMACAO OK - handler respeita o submitter, zero formularios mistos, Aprovar com dialogo proprio.' . "\n";
