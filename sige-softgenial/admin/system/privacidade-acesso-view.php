<?php
/**
 * Direito de acesso e portabilidade - Fase 8 incremento 2.
 *
 * Ecra de governanca de dados. Permite procurar um aluno da escola activa (por
 * numero de processo ou nome), apresentar no ecra o dossie dos seus dados
 * pessoais (direito de acesso) e exporta-lo em JSON (portabilidade). A procura e
 * a navegacao usam GET (so leitura). A exportacao e um POST para admin-post.php,
 * tratado pelo endpoint governado sige_privacidade_exportar (esta view nao
 * processa POST). Estilo so com tokens (assets/views/privacidade.css).
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_pii_dossier_pode_exportar')) {
    $sige_dossie_lib = SIGE_PATH . 'includes/privacy/pii-dossier.php';
    if (file_exists($sige_dossie_lib)) require_once $sige_dossie_lib;
}

if (!function_exists('sige_pii_dossier_pode_exportar') || !sige_pii_dossier_pode_exportar()) {
    echo '<div class="notice notice-warning"><p>Esta área é reservada a quem pode exportar dados pessoais (Direcção e administração).</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
$aluno_id = isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0;

$categorias = function_exists('sige_pii_categorias') ? sige_pii_categorias() : [];
$cat_rotulo = function ($k) use ($categorias) {
    return isset($categorias[$k]) ? $categorias[$k]['rotulo'] : $k;
};

// Procura de alunos (so leitura, sempre dentro da escola).
$resultados = [];
if ($escola_id > 0 && $q !== '' && $aluno_id <= 0) {
    global $wpdb;
    $tab = str_replace('`', '', $wpdb->prefix . 'sige_alunos');
    $like = '%' . $wpdb->esc_like($q) . '%';
    $resultados = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nome_completo, numero_processo FROM `{$tab}` WHERE escola_id = %d AND (numero_processo LIKE %s OR nome_completo LIKE %s) ORDER BY nome_completo ASC LIMIT 15",
        $escola_id, $like, $like
    ), ARRAY_A);
    if (!is_array($resultados)) { $resultados = []; }
}

// Dossie do aluno seleccionado.
$dossie = null;
if ($escola_id > 0 && $aluno_id > 0) {
    $dossie = sige_pii_dossier($aluno_id, $escola_id);
}

$base_url = admin_url('admin.php?page=sige-app&view=privacidade-acesso');
$limite_ecra = 25;
?>
<div class="wrap">
    <h1 class="sige-priv-titulo">🔎 Direito de Acesso e Portabilidade</h1>
    <p class="sige-priv-hint">
        Reúne, para um aluno desta escola, os seus dados pessoais (direito de acesso) e permite exportá-los
        num ficheiro estruturado (portabilidade). A procura é só de leitura. A exportação fica registada
        (quem exportou, que aluno e quando). Trate o ficheiro exportado com confidencialidade.
    </p>

    <?php if ($escola_id <= 0): ?>
        <div class="notice notice-info"><p>Sem escola activa no contexto. Seleccione uma escola para usar esta área.</p></div>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <form method="get" class="sige-priv-procura">
        <input type="hidden" name="page" value="sige-app" />
        <input type="hidden" name="view" value="privacidade-acesso" />
        <label for="sige-priv-q" class="sige-priv-procura__label">Procurar aluno (número de processo ou nome)</label>
        <input type="search" id="sige-priv-q" name="q" value="<?php echo esc_attr($q); ?>" class="regular-text" placeholder="Ex.: 2024-0123 ou Maria Joao" />
        <button type="submit" class="button button-primary">Procurar</button>
    </form>

    <?php if ($q !== '' && $aluno_id <= 0): ?>
        <h2 class="sige-priv-sec">Resultados</h2>
        <?php if (empty($resultados)): ?>
            <div class="notice notice-info"><p>Nenhum aluno encontrado para a procura indicada.</p></div>
        <?php else: ?>
            <table class="widefat striped">
                <thead><tr><th>Número de processo</th><th>Nome</th><th>Acção</th></tr></thead>
                <tbody>
                    <?php foreach ($resultados as $r): ?>
                        <tr>
                            <td><code><?php echo esc_html((string) $r['numero_processo']); ?></code></td>
                            <td><?php echo esc_html((string) $r['nome_completo']); ?></td>
                            <td><a class="button" href="<?php echo esc_url($base_url . '&aluno_id=' . (int) $r['id']); ?>">Ver dossiê</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($aluno_id > 0): ?>
        <?php if (!$dossie || empty($dossie['ok'])): ?>
            <div class="notice notice-error"><p>Não foi possível abrir o dossiê deste aluno nesta escola.</p></div>
        <?php else: ?>
            <h2 class="sige-priv-sec">
                Dossiê de <?php echo esc_html((string) ($dossie['identificacao']['nome_completo'] ?? '')); ?>
                <span class="sige-priv-nota">(processo <?php echo esc_html((string) ($dossie['identificacao']['numero_processo'] ?? '')); ?>)</span>
            </h2>
            <p class="sige-priv-hint">
                <?php echo (int) $dossie['total_seccoes']; ?> secções, <?php echo (int) $dossie['total_registos']; ?> registos. Apresentadas até <?php echo (int) $limite_ecra; ?> linhas por secção; a exportação contém o total.
            </p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sige-priv-export">
                <input type="hidden" name="action" value="sige_privacidade_exportar" />
                <input type="hidden" name="aluno_id" value="<?php echo (int) $aluno_id; ?>" />
                <?php wp_nonce_field('sige_privacidade_exportar'); ?>
                <button type="submit" class="button button-primary">Exportar dossiê (JSON)</button>
            </form>

            <?php foreach ($dossie['seccoes'] as $sec): ?>
                <h3 class="sige-priv-tabela"><code><?php echo esc_html($sec['tabela']); ?></code> <span class="sige-priv-nota"><?php echo count($sec['linhas']); ?> registo(s)</span></h3>
                <table class="widefat striped">
                    <thead>
                        <tr>
                            <?php foreach ($sec['colunas'] as $col): ?>
                                <th>
                                    <?php echo esc_html($col); ?>
                                    <?php if (($sec['col_sensibilidade'][$col] ?? 'normal') === 'sensivel'): ?>
                                        <span class="sige-priv-badge sige-priv-badge--sensivel">sensível</span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (array_slice($sec['linhas'], 0, $limite_ecra) as $linha): ?>
                            <tr>
                                <?php foreach ($sec['colunas'] as $col): ?>
                                    <td><?php echo esc_html((string) ($linha[$col] ?? '')); ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (count($sec['linhas']) > $limite_ecra): ?>
                    <p class="sige-priv-nota">Mais <?php echo (count($sec['linhas']) - $limite_ecra); ?> registo(s) na exportação.</p>
                <?php endif; ?>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</div>
