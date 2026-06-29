<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * Smoke da blindagem do modulo de permissoes (Fase 9 incremento 1).
 *
 * Executa o avaliador unico contra um $wpdb e helpers WP simulados, validando:
 *  - alvo protegido (administrador WP) nunca e gerivel, por qualquer actor;
 *  - actor nao protegido nao pode atribuir um perfil que confere gestao (escalada);
 *  - actor nao protegido nao se pode despromover nem remover a si proprio;
 *  - nao se deixa a escola sem nenhum gestor de permissoes;
 *  - actor protegido (administrador WP) tem autoridade plena (excepto sobre outra
 *    conta protegida);
 *  - contexto invalido e recusado.
 */

$root = dirname(__DIR__);
if (!defined('ABSPATH')) { define('ABSPATH', '/tmp/'); }

// --- Stubs WP minimos ---
if (!function_exists('get_current_user_id')) { function get_current_user_id() { return $GLOBALS['__actor'] ?? 0; } }
if (!function_exists('sanitize_key')) { function sanitize_key($s) { return preg_replace('/[^a-z0-9_]/', '', strtolower((string) $s)); } }
if (!function_exists('user_can')) { function user_can($u, $c) { return false; } }
if (!function_exists('current_time')) { function current_time($t = 'mysql') { return gmdate('Y-m-d H:i:s'); } }
if (!function_exists('update_option')) { function update_option($k, $v, $a = null) { return true; } }
if (!function_exists('get_option')) { function get_option($k, $d = false) { return $d; } }
if (!function_exists('add_action')) { function add_action(...$a) {} }
if (!function_exists('add_filter')) { function add_filter(...$a) {} }
if (!function_exists('apply_filters')) { function apply_filters($t, $v, ...$a) { return $v; } }

// Contas protegidas (administradores WP reais): apenas o utilizador 1.
$GLOBALS['__protegidos'] = [1 => true];
if (!function_exists('sige_is_real_wp_admin_user')) {
    function sige_is_real_wp_admin_user($id = null) { $id = $id ?: ($GLOBALS['__actor'] ?? 0); return !empty($GLOBALS['__protegidos'][(int) $id]); }
}

/**
 * Mock $wpdb.
 * Perfis: 10=admin_ti (confere gestao via registry), 11=professor (nao),
 *         12=direccao_geral (confere gestao via registry).
 * Perfil activo por utilizador/escola (get_row): 5 e 9 -> admin_ti; 7 -> professor.
 * O conjunto de gestores activos da escola (get_results) tem so o utilizador 5
 * por omissao; o cenario do ultimo gestor usa o utilizador 7 como unico gestor.
 */
class SigeBlindMock {
    public $prefix = 'wp_';
    public $assignments = [['user_id' => 5, 'role_id' => 10]]; // gestores activos por omissao
    public $user_active_role = [5 => 10, 9 => 10, 7 => 11];

    public function prepare($q, ...$a) {
        if (count($a) === 1 && is_array($a[0])) { $a = $a[0]; }
        $i = 0;
        return preg_replace_callback('/%[ds]/', function ($m) use (&$i, $a) {
            $v = $a[$i++] ?? '';
            return $m[0] === '%d' ? (string) (int) $v : "'" . addslashes((string) $v) . "'";
        }, $q);
    }
    public function get_var($q) {
        if (strpos($q, 'SELECT slug FROM') !== false && preg_match('/id = (\d+)/', $q, $m)) {
            return ['10' => 'admin_ti', '11' => 'professor', '12' => 'direccao_geral'][$m[1]] ?? null;
        }
        if (strpos($q, 'SELECT id FROM') !== false && preg_match("/slug = '([^']+)'/", $q, $m)) {
            return ['admin_ti' => 10, 'professor' => 11, 'direccao_geral' => 12][$m[1]] ?? 0;
        }
        if (strpos($q, 'SELECT allowed FROM') !== false) { return null; } // sem override em BD; vale o registry
        if (strpos($q, 'SELECT ur.role_id FROM') !== false && preg_match('/ur\.user_id = (\d+)/', $q, $m)) {
            // niveis para a guarda de hierarquia: admin_ti=80, professor=30.
            $map = [5 => 10, 7 => 11, 9 => 0];
            return $map[(int) $m[1]] ?? null;
        }
        return null;
    }
    public function get_row($q) {
        if (preg_match('/ur\.user_id = (\d+)/', $q, $m)) {
            $uid = (int) $m[1];
            if (isset($this->user_active_role[$uid])) {
                $rid = $this->user_active_role[$uid];
                return (object) ['id' => $rid, 'slug' => ['10' => 'admin_ti', '11' => 'professor', '12' => 'direccao_geral'][$rid]];
            }
        }
        return null;
    }
    public function get_results($q) {
        if (strpos($q, 'FROM') !== false && strpos($q, 'ur.escola_id') !== false) {
            return array_map(function ($a) { return (object) $a; }, $this->assignments);
        }
        return [];
    }
    public function query($q) { return true; }
    public function update(...$a) { return 1; }
    public function insert(...$a) { return 1; }
}

global $wpdb;
$wpdb = new SigeBlindMock();
require_once $root . '/includes/permissions-layer.php';

$falhas = [];
$ok = function (bool $cond, string $msg) use (&$falhas) { if (!$cond) { $falhas[] = $msg; } };

// Sanidade dos helpers.
$ok(sige_permissions_principal_protegido(1) === true, 'utilizador 1 deve ser protegido');
$ok(sige_permissions_principal_protegido(5) === false, 'utilizador 5 nao deve ser protegido');
$ok(sige_permissions_role_concede_gestao(10) === true, 'admin_ti deve conferir gestao');
$ok(sige_permissions_role_concede_gestao(11) === false, 'professor nao deve conferir gestao');
$ok(sige_permissions_role_concede_gestao(12) === true, 'direccao_geral deve conferir gestao');

// C1: actor admin_ti (5, nao protegido) atribui professor ao super admin (1) -> alvo_protegido.
$GLOBALS['__actor'] = 5;
$r = sige_permissions_avaliar_operacao(5, 1, 'assign_user_role', 11, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'alvo_protegido', 'C1 alvo protegido deve ser recusado');

// C1b: ate um actor protegido (1) nao pode gerir outra conta protegida (1 sobre 1).
$GLOBALS['__actor'] = 1;
$r = sige_permissions_avaliar_operacao(1, 1, 'assign_user_role', 11, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'alvo_protegido', 'C1b conta protegida nao gerivel mesmo por actor protegido');

// C2: actor nao protegido (5) atribui direccao_geral (confere gestao) a 7 -> escalada_gestao.
$GLOBALS['__actor'] = 5;
$r = sige_permissions_avaliar_operacao(5, 7, 'assign_user_role', 12, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'escalada_gestao', 'C2 escalada de gestao deve ser recusada');

// C3: actor nao protegido (5) remove o seu proprio perfil -> auto_remocao.
$r = sige_permissions_avaliar_operacao(5, 5, 'unassign_user_role', 0, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'auto_remocao', 'C3 auto remocao deve ser recusada');

// C4: actor nao protegido (5, tem gestao) atribui professor (nao gestao) a si proprio -> auto_despromocao.
$r = sige_permissions_avaliar_operacao(5, 5, 'assign_user_role', 11, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'auto_despromocao', 'C4 auto despromocao deve ser recusada');

// C5: actor protegido (1) atribui professor a 7 -> permitido (autoridade plena).
$GLOBALS['__actor'] = 1;
$r = sige_permissions_avaliar_operacao(1, 7, 'assign_user_role', 11, 3);
$ok($r['permitido'], 'C5 actor protegido deve poder atribuir perfil normal');

// C5b: actor protegido (1) pode atribuir um perfil de gestao (mintar gestor) a 7.
$r = sige_permissions_avaliar_operacao(1, 7, 'assign_user_role', 12, 3);
$ok($r['permitido'], 'C5b actor protegido deve poder atribuir perfil de gestao');

// C6: actor nao protegido (5) atribui professor (normal) a OUTRO (7) -> permitido.
$GLOBALS['__actor'] = 5;
$r = sige_permissions_avaliar_operacao(5, 7, 'assign_user_role', 11, 3);
$ok($r['permitido'], 'C6 atribuir perfil normal a outro deve ser permitido');

// C7: remover o ultimo gestor. Unico gestor activo = utilizador 7; actor 9 (nao protegido) remove 7.
$wpdb->assignments = [['user_id' => 7, 'role_id' => 10]]; // unico gestor activo e o 7 (admin_ti)
$wpdb->user_active_role[7] = 10; // 7 passa a ter perfil de gestao activo
$GLOBALS['__actor'] = 9;
$r = sige_permissions_avaliar_operacao(9, 7, 'unassign_user_role', 0, 3);
$ok(!$r['permitido'] && $r['codigo'] === 'ultimo_gestor', 'C7 remover o ultimo gestor deve ser recusado');
// Restaurar.
$wpdb->assignments = [['user_id' => 5, 'role_id' => 10]];
$wpdb->user_active_role[7] = 11;

// C8: contexto invalido (escola 0).
$r = sige_permissions_avaliar_operacao(5, 7, 'assign_user_role', 11, 0);
$ok(!$r['permitido'] && $r['codigo'] === 'contexto_invalido', 'C8 contexto invalido deve ser recusado');

if (!empty($falhas)) {
    echo "SMOKE PERMISSOES-BLINDAGEM FALHOU:\n";
    foreach ($falhas as $f) echo " - {$f}\n";
    exit(1);
}
echo "SMOKE PERMISSOES-BLINDAGEM OK - alvo protegido nunca gerivel (mesmo por actor protegido), anti-escalada, auto-proteccao (despromocao e remocao), ultimo gestor preservado, actor protegido com autoridade plena, contexto invalido recusado.\n";
exit(0);
