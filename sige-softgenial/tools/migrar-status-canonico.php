<?php
// Acesso restrito: este utilitário corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial - Migração de status para a forma canónica (pré-AO90)
 *
 * Converge dados HISTÓRICOS: alunos.status e matriculas.status_matricula
 * com grafia AO90 ('ativo', 'inativa', ...) passam à forma canónica
 * ('activo', 'inactiva', ...). As consultas continuam a aceitar ambas,
 * por isso esta migração é segura e reversível por backup.
 *
 * MODO POR DEFEITO: dry-run (só mostra o que faria).
 * Aplicar de verdade: acrescentar --aplicar E ter feito backup antes.
 *
 * Executar na raiz do WordPress da escola:
 *   wp eval-file wp-content/plugins/sige-softgenial/tools/migrar-status-canonico.php
 *   wp eval-file wp-content/plugins/sige-softgenial/tools/migrar-status-canonico.php --aplicar
 * (sem WP-CLI: php tools/migrar-status-canonico.php /caminho/para/wp-load.php [--aplicar])
 */

$APLICAR = in_array('--aplicar', $argv ?? [], true);

// Bootstrap fora do WP-CLI: primeiro argumento = caminho do wp-load.php
if (!defined('ABSPATH')) {
    $wp_load = $argv[1] ?? '';
    if ($wp_load === '' || $wp_load === '--aplicar' || !is_file($wp_load)) {
        fwrite(STDERR, "Uso: wp eval-file ESTE-FICHEIRO [--aplicar]\n");
        fwrite(STDERR, "  ou: php ESTE-FICHEIRO /caminho/wp-load.php [--aplicar]\n");
        exit(1);
    }
    require_once $wp_load;
}

global $wpdb;
$p = $wpdb->prefix;

$mapa_aluno = [
    'ativo' => 'activo', 'Ativo' => 'activo', 'ativa' => 'activo', 'activa' => 'activo',
    'inativo' => 'inactivo', 'inativa' => 'inactivo', 'inactiva' => 'inactivo',
    'transferida' => 'transferido', 'desistiu' => 'desistente', 'cancelada' => 'cancelado',
];
$mapa_matricula = [
    'ativa' => 'activa', 'Ativa' => 'activa', 'ativo' => 'activa', 'activo' => 'activa',
    'inativa' => 'inactiva', 'inativo' => 'inactiva', 'inactivo' => 'inactiva',
    'transferida' => 'transferido', 'desistiu' => 'desistente',
];

echo $APLICAR ? "MODO: APLICAR (escreve na base de dados)\n"
              : "MODO: DRY-RUN (nada será alterado; use --aplicar para executar)\n";
echo str_repeat('-', 60) . "\n";

if ($APLICAR) {
    echo "Confirma que FEZ BACKUP da base de dados agora? Escreva CONFIRMO: ";
    $resp = trim((string)fgets(STDIN));
    if ($resp !== 'CONFIRMO') { echo "Cancelado.\n"; exit(1); }
}

$total_alterado = 0;
$resumo = [];

$migrar = function (string $tabela, string $coluna, array $mapa) use ($wpdb, $APLICAR, &$total_alterado, &$resumo) {
    $existe = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $tabela));
    if ($existe !== $tabela) { echo "(tabela {$tabela} ausente neste tenant - ignorada)\n"; return; }
    foreach ($mapa as $de => $para) {
        if ($de === $para) continue;
        $n = (int)$wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$tabela} WHERE BINARY {$coluna} = %s", $de
        ));
        if ($n === 0) continue;
        $resumo[] = sprintf('%s.%s: %d linha(s) "%s" -> "%s"', $tabela, $coluna, $n, $de, $para);
        echo end($resumo) . "\n";
        if ($APLICAR) {
            $ok = $wpdb->query($wpdb->prepare(
                "UPDATE {$tabela} SET {$coluna} = %s WHERE BINARY {$coluna} = %s", $para, $de
            ));
            if ($ok === false) { echo "  !! ERRO: {$wpdb->last_error}\n"; }
            else { $total_alterado += (int)$ok; }
        }
    }
};

$migrar($p . 'sige_alunos', 'status', $mapa_aluno);
$migrar($p . 'sige_matriculas', 'status_matricula', $mapa_matricula);

echo str_repeat('-', 60) . "\n";
if ($APLICAR) {
    update_option('sige_migracao_status_canonico', [
        'quando' => current_time('mysql'),
        'linhas_alteradas' => $total_alterado,
        'resumo' => $resumo,
        'por' => 'cli',
    ], false);
    if (function_exists('sige_security_log')) {
        sige_security_log('migracao_status_canonico', 'linhas=' . $total_alterado);
    }
    echo "CONCLUÍDO: {$total_alterado} linha(s) convergidas para a forma canónica. Registo em sige_migracao_status_canonico.\n";
} else {
    echo empty($resumo)
        ? "Nada a convergir: os dados já estão na forma canónica.\n"
        : "DRY-RUN concluído. Reveja acima e repita com --aplicar após backup.\n";
}
