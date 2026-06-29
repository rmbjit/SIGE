<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Verificador de compatibilidade PHP por tenant
 *
 * Objectivo: confirmar, escola a escola, se o piso pode subir para PHP 8.1
 * (o que permite apagar includes/fin-fsm-php74.php e simplificar daqui
 * para a frente). Correr UMA VEZ em cada tenant; quando todos derem
 * "PRONTO PARA 8.1", aplica-se o patch do piso.
 *
 * Executar na raiz do WordPress da escola:
 *   wp eval-file wp-content/plugins/sige-softgenial/tools/check-php-compat.php
 * (sem WP-CLI: php tools/check-php-compat.php /caminho/para/wp-load.php)
 *
 * Alternativa sem acesso à consola: o heartbeat do Hub já reporta
 * php_version de cada tenant; o painel do Hub mostra a mesma resposta.
 */

if (!defined('ABSPATH')) {
    $wp_load = $argv[1] ?? '';
    if ($wp_load === '' || !is_file($wp_load)) {
        // Modo offline: avalia só o ambiente PHP local (sem WP).
        echo "MODO OFFLINE (sem wp-load.php): a avaliar apenas o PHP local.\n";
    } else {
        require_once $wp_load;
    }
}

$php = PHP_VERSION;
$php_ok_81 = version_compare($php, '8.1.0', '>=');
$ext_necessarias = ['mysqli', 'mbstring', 'json', 'openssl', 'curl', 'gd'];
$ext_em_falta = [];
foreach ($ext_necessarias as $e) {
    if (!extension_loaded($e)) $ext_em_falta[] = $e;
}

echo str_repeat('=', 60) . "\n";
echo "VERIFICAÇÃO DE COMPATIBILIDADE - SIGE SoftGenial\n";
echo str_repeat('=', 60) . "\n";
echo "PHP em execução........: {$php}\n";
echo "Piso pretendido........: 8.1\n";
echo "Caminho FSM em uso.....: " . ($php_ok_81 ? 'fin-fsm-php81.php (moderno)' : 'fin-fsm-php74.php (fallback legado)') . "\n";
echo "Extensões necessárias..: " . (empty($ext_em_falta) ? 'todas presentes' : ('EM FALTA: ' . implode(', ', $ext_em_falta))) . "\n";

if (defined('ABSPATH')) {
    global $wpdb;
    $mysql = (string) $wpdb->get_var('SELECT VERSION()');
    echo "WordPress..............: " . get_bloginfo('version') . "\n";
    echo "MySQL/MariaDB..........: {$mysql}\n";
    echo "Site...................: " . home_url() . "\n";
    if (defined('SIGE_VERSION')) {
        echo "SIGE instalado.........: " . SIGE_VERSION . "\n";
    }
}

echo str_repeat('-', 60) . "\n";
if ($php_ok_81 && empty($ext_em_falta)) {
    echo "VEREDICTO: PRONTO PARA 8.1 neste tenant.\n";
    echo "Quando TODOS os tenants derem este veredicto:\n";
    echo "  1. Mudar 'Requires PHP: 7.4' para 'Requires PHP: 8.1' no header;\n";
    echo "  2. Em includes/fin-fsm.php remover o ramo do php74;\n";
    echo "  3. Apagar includes/fin-fsm-php74.php;\n";
    echo "  4. Correr os gates e fazer release normal.\n";
    exit(0);
}
echo "VEREDICTO: AINDA NÃO. ";
if (!$php_ok_81) echo "Subir o PHP deste tenant para 8.1+ no CloudPanel (Sites > PHP Settings). ";
if (!empty($ext_em_falta)) echo "Instalar extensões em falta: " . implode(', ', $ext_em_falta) . '.';
echo "\n";
exit(1);
