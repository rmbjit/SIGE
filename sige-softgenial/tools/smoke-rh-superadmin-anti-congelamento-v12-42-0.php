<?php
/**
 * Smoke v12.42.0 - (1) super admin fora de Assiduidade/Salarios; (2) correccao
 * rigorosa do congelamento (fonte de verdade unica do shell + rede em pointerdown).
 *
 * (1) DADOS: com stubs de get_users/sige_is_real_wp_admin_user, o helper
 *     sige_rh_emails_admin_sistema() devolve o email do admin WP real e
 *     sige_rh_salario_mapa_mes() SALTA a linha cujo email pertence ao admin.
 * (2) LIGACAO: grelha/preview seleccionam email e aplicam a exclusao; o
 *     reconciliador unico do shell existe e esta ligado (open/close, cracha,
 *     pointerdown de captura); versoes sincronizadas.
 *
 * Uso: php tools/smoke-rh-superadmin-anti-congelamento-v12-42-0.php
 */

$root  = dirname(__DIR__);
$fails = [];
function _p(&$a, $c, $m) { $a[] = [(bool)$c, $m]; }
function _eq($x, $y) { return abs((float)$x - (float)$y) < 0.01; }

if (!defined('ABSPATH')) define('ABSPATH', __DIR__);
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('get_option')) { function get_option($k, $d = null) { return $d; } }
if (!function_exists('update_option')) { function update_option(...$a) { return true; } }

// Stubs do administrador WP real (o "super admin" de manutencao do sistema).
if (!function_exists('get_users')) {
    function get_users($args = []) {
        // Devolve um administrador com email conhecido.
        $u = new stdClass(); $u->ID = 1; $u->user_email = 'admin@sistema.mz';
        return [$u];
    }
}
if (!function_exists('sige_is_real_wp_admin_user')) {
    function sige_is_real_wp_admin_user($uid = null) { return ((int) $uid) === 1; }
}

require_once $root . '/includes/rh-assiduidade.php';
require_once $root . '/includes/rh-salarios.php';

// ── (1) HELPER de exclusao ────────────────────────────────────────────────────
$emails = function_exists('sige_rh_emails_admin_sistema') ? sige_rh_emails_admin_sistema() : null;
_p($fails, is_array($emails) && in_array('admin@sistema.mz', $emails, true), 'Helper devolve o email do admin WP real');
_p($fails, function_exists('sige_rh_professor_e_admin_sistema') && sige_rh_professor_e_admin_sistema('ADMIN@sistema.MZ'), 'Reconhece o admin (case-insensitive)');
_p($fails, function_exists('sige_rh_professor_e_admin_sistema') && !sige_rh_professor_e_admin_sistema('prof@escola.mz'), 'Nao marca um professor normal como admin');
_p($fails, !sige_rh_professor_e_admin_sistema(''), 'Email vazio nunca e admin');

// ── (2) MAPA exclui a linha do admin (com $wpdb stub) ─────────────────────────
class _SmokeWpdbSA {
    public $prefix = 'wp_';
    public function prepare($q, ...$a) { return $q; }
    public function get_charset_collate() { return ''; }
    public function get_var($q) { if (strpos($q, 'SHOW TABLES') !== false) return $this->prefix . 'sige_rh_salarios'; return 0; }
    public function query($q) { return true; }
    public function get_results($q) {
        if (strpos($q, 'sige_rh_salarios') !== false) {
            return [
                (object) ['professor_id' => 1, 'nome' => 'Admin', 'nuit' => '000', 'email' => 'admin@sistema.mz', 'bruto' => 99999, 'inss' => 1, 'inss_empregador' => 1, 'irps' => 1, 'liquido' => 99997],
                (object) ['professor_id' => 5, 'nome' => 'Ana',   'nuit' => '123', 'email' => 'ana@escola.mz',   'bruto' => 30000, 'inss' => 900, 'inss_empregador' => 1200, 'irps' => 2614.5, 'liquido' => 25285.5],
            ];
        }
        return [];
    }
}
global $wpdb; $wpdb = new _SmokeWpdbSA();

$m = sige_rh_salario_mapa_mes(1, 2026, 7);
_p($fails, count($m['itens']) === 1, 'Mapa exclui a linha do admin -> ' . count($m['itens']) . ' (esperado 1)');
_p($fails, !empty($m['itens']) && $m['itens'][0]['professor_id'] === 5, 'A unica linha e a do colaborador real (Ana)');
_p($fails, _eq($m['totais']['bruto'], 30000), 'Totais NAO incluem o admin (bruto 30000)');

// ── (3) LIGACAO no codigo ─────────────────────────────────────────────────────
$assi   = (string) @file_get_contents($root . '/includes/rh-assiduidade.php');
$sal    = (string) @file_get_contents($root . '/includes/rh-salarios.php');
$equipe = (string) @file_get_contents($root . '/admin/hr/equipe-view.php');
$boot   = (string) @file_get_contents($root . '/sige-softgenial.php');
$build  = json_decode((string) @file_get_contents($root . '/BUILD.json'), true);

_p($fails, strpos($assi, 'function sige_rh_emails_admin_sistema') !== false, 'Helper unico definido em rh-assiduidade.php');
_p($fails, strpos($assi, 'SELECT id, nome_completo, email FROM') !== false, 'Grelha selecciona o email');
_p($fails, strpos($assi, 'sige_rh_professor_e_admin_sistema($p->email') !== false, 'Grelha aplica a exclusao do admin');
_p($fails, strpos($sal, 'SELECT id, nome_completo, nuit, salario_base, subsidio, email FROM') !== false, 'Preview selecciona o email');
_p($fails, substr_count($sal, 'sige_rh_professor_e_admin_sistema') >= 2, 'Preview e Mapa aplicam a exclusao (>=2 usos)');
_p($fails, strpos($sal, 'p.email AS email') !== false, 'Mapa junta o email para exclusao');

// Anti-congelamento: fonte de verdade unica + ligacao.
_p($fails, strpos($equipe, 'function sigeRhReconciliarShell(') !== false, 'Reconciliador unico do shell presente');
_p($fails, strpos($equipe, 'function sigeRhModalAberto(') !== false, 'Deteccao de modal aberto presente');
_p($fails, substr_count($equipe, 'sigeRhReconciliarShell()') >= 4, 'Reconciliador chamado em open/close/cracha (>=4)');
_p($fails, strpos($equipe, "addEventListener('pointerdown', function () {") !== false && strpos($equipe, '}, true);') !== false, 'Rede de seguranca em pointerdown de captura');
// A antiga rede baseada em "click" (clique desperdicado) foi removida.
_p($fails, strpos($equipe, "document.addEventListener('click', function () {\n    if (document.querySelector('.sige-modal.active, .is-open')) return;") === false, 'Self-heal antigo por clique removido');
// O invariante do CSS (fechado => nao captura) mantem-se.
_p($fails, strpos($equipe, '#box-salario-cfg.sige-modal:not(.active)') !== false && strpos($equipe, 'pointer-events:none!important;') !== false, 'Invariante CSS (fechado nao captura) mantido');

preg_match('/Version:\s*([0-9.]+)/', $boot, $vh);
preg_match("/define\('SIGE_VERSION',\s*'([0-9.]+)'\)/", $boot, $vc);
$vb = is_array($build) ? (string)($build['version'] ?? '') : '';
_p($fails, !empty($vh[1]) && ($vh[1] === ($vc[1] ?? '')) && (($vc[1] ?? '') === $vb) && $vb === '12.42.0', 'Versoes sincronizadas em 12.42.0 -> ' . ($vh[1] ?? '?'));

$erros = array_values(array_filter($fails, fn($x) => !$x[0]));
foreach ($fails as $x) { echo ($x[0] ? 'OK   ' : 'FALHA ') . $x[1] . "\n"; }
echo "\n";
if ($erros) { echo 'SMOKE RH-SUPERADMIN/ANTI-CONGELAMENTO FALHOU: ' . count($erros) . " verificacao(oes).\n"; exit(1); }
echo "SMOKE RH-SUPERADMIN/ANTI-CONGELAMENTO OK.\n";
exit(0);
