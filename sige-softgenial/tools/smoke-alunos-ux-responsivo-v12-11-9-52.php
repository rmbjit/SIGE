<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke test - SIGE SoftGenial v12.11.9.52
 * UX Responsivo Alunos Mobile/Tablet PRO
 */
$root = dirname(__DIR__);
$main = $root . '/sige-softgenial.php';
$view = $root . '/admin/academic/alunos_lista.php';
$build = $root . '/BUILD.json';
$checks = [];
function sige52_check($label, $condition) {
    global $checks;
    $checks[] = [$label, (bool)$condition];
}
function sige52_contains($haystack, $needle) {
    return strpos($haystack, $needle) !== false;
}
$mainContent = is_file($main) ? file_get_contents($main) : '';
$viewContent = is_file($view) ? file_get_contents($view) : '';
$buildContent = is_file($build) ? file_get_contents($build) : '';

sige52_check('Plugin principal existe', is_file($main));
sige52_check('View de alunos existe', is_file($view));
sige52_check('BUILD.json existe', is_file($build));
sige52_check('Header do plugin actualizado para 12.11.9.52', sige52_contains($mainContent, 'Version: 12.11.9.52'));
sige52_check('Constante SIGE_VERSION actualizada', sige52_contains($mainContent, "define('SIGE_VERSION', '12.11.9.52')"));
sige52_check('BUILD.json actualizado', sige52_contains($buildContent, '12.11.9.52'));
sige52_check('Changelog v12.11.9.52 criado', is_file($root . '/CHANGELOG-v12-11-9-52-ux-responsivo-alunos-mobile-tablet-pro.txt'));

sige52_check('Camada CSS UX 12.11.9.52 presente', sige52_contains($viewContent, 'v12.11.9.52 - UX Responsivo Alunos Mobile/Tablet PRO'));
sige52_check('Botão mobile de filtros previsto', sige52_contains($viewContent, 'sige-mobile-filter-toggle'));
sige52_check('Estado de filtros mobile previsto', sige52_contains($viewContent, 'sige-mobile-filters-open'));
sige52_check('Acções mobile/tablet com texto via title', sige52_contains($viewContent, 'content:attr(title)'));
sige52_check('Stepbar mobile previsto', sige52_contains($viewContent, 'sige-mobile-stepbar'));
sige52_check('Botão Anterior previsto', sige52_contains($viewContent, 'sige-mobile-step-prev'));
sige52_check('Botão Próximo previsto', sige52_contains($viewContent, 'sige-mobile-step-next'));
sige52_check('Acordeões da Ficha 360º previstos', sige52_contains($viewContent, 'sige-360-card.is-collapsed'));
sige52_check('Script UX 12.11.9.52 presente', sige52_contains($viewContent, 'sige-alunos-ux-responsivo-v1211952'));
sige52_check('Acessibilidade das abas prevista', sige52_contains($viewContent, 'enhanceTabA11y'));
sige52_check('Navegação por teclado das abas prevista', sige52_contains($viewContent, 'ArrowRight') && sige52_contains($viewContent, 'ArrowLeft'));
sige52_check('Wrapper seguro da Ficha 360º previsto', sige52_contains($viewContent, 'renderFichaAluno360.__sigeUX52Wrapped'));
sige52_check('Sem alteração de action de salvar aluno', sige52_contains($viewContent, 'action="sige_salvar_aluno"') || sige52_contains($viewContent, 'name="action" value="sige_salvar_aluno"'));
sige52_check('Sem alteração de action de importação', sige52_contains($viewContent, 'sige_importar_alunos_csv'));
sige52_check('Nenhuma migração estrutural adicionada nesta versão visual', !sige52_contains($viewContent, 'v12.11.9.52') || !preg_match('/ALTER\s+TABLE|CREATE\s+TABLE/i', $viewContent));

$ok = 0;
foreach ($checks as $c) {
    echo ($c[1] ? '[OK] ' : '[FAIL] ') . $c[0] . PHP_EOL;
    if ($c[1]) $ok++;
}
$total = count($checks);
echo "Resultado: {$ok}/{$total} verificações OK" . PHP_EOL;
exit($ok === $total ? 0 : 1);
