<?php
/**
 * Apagamento por anonimizacao - Fase 8 incremento 3.
 *
 * Ecra de governanca de dados. Procura um aluno da escola activa, mostra a
 * pre-visualizacao de impacto (que campos vao ser redigidos, o que e preservado)
 * e oferece a accao destrutiva com confirmacao em dois passos: o operador escreve
 * o numero de processo exacto. A procura e a navegacao usam GET (so leitura). A
 * execucao e um POST para admin-post.php, tratado pelo endpoint governado
 * sige_privacidade_apagar (esta view nao processa POST). Estilo so com tokens.
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_pii_apagamento_pode_executar')) {
    $sige_apag_lib = SIGE_PATH . 'includes/privacy/pii-apagamento.php';
    if (file_exists($sige_apag_lib)) require_once $sige_apag_lib;
}

if (!function_exists('sige_pii_apagamento_pode_executar') || !sige_pii_apagamento_pode_executar()) {
    echo '<div class="notice notice-warning"><p>Esta área é reservada a quem pode apagar dados pessoais (Direcção e administração).</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$q = isset($_GET['q']) ? sanitize_text_field(wp_unslash($_GET['q'])) : '';
$aluno_id = isset($_GET['aluno_id']) ? (int) $_GET['aluno_id'] : 0;
$feito = isset($_GET['feito']) ? sanitize_text_field(wp_unslash($_GET['feito'])) : '';
$erro = isset($_GET['erro']) ? sanitize_text_field(wp_unslash($_GET['erro'])) : '';
$colunas_feitas = isset($_GET['colunas']) ? (int) $_GET['colunas'] : 0;

$base_url = admin_url('admin.php?page=sige-app&view=privacidade-apagamento');

// Procura (so leitura, sempre dentro da escola).
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

$plano = null;
if ($escola_id > 0 && $aluno_id > 0) {
    $plano = sige_pii_apagamento_plano($aluno_id, $escola_id);
}
?>
<div class="wrap">
    <h1 class="sige-priv-titulo">🧹 Apagamento por Anonimização</h1>
    <p class="sige-priv-hint">
        Responde a um pedido de apagamento (direito ao esquecimento) de um aluno. Os campos pessoais
        identificáveis (nome, contactos, documentos, morada, dados de saúde, dados do encarregado) são
        redigidos; o número de processo, os identificadores internos e todos os valores financeiros e
        académicos são preservados, por dever de retenção e integridade. Esta operação é permanente e
        não pode ser desfeita.
    </p>

    <?php if ($feito === '1'): ?>
        <div class="notice notice-success"><p>Aluno anonimizado com sucesso. <?php echo (int) $colunas_feitas; ?> campos redigidos. A operação ficou registada na auditoria.</p></div>
    <?php elseif ($feito === '0'): ?>
        <div class="notice notice-error"><p>Não foi possível concluir a anonimização. Verifique e tente novamente.</p></div>
    <?php endif; ?>
    <?php if ($erro === 'confirmacao'): ?>
        <div class="notice notice-error"><p>O número de processo escrito não coincide com o do aluno. A anonimização foi cancelada por segurança.</p></div>
    <?php endif; ?>

    <?php if ($escola_id <= 0): ?>
        <div class="notice notice-info"><p>Sem escola activa no contexto. Seleccione uma escola para usar esta área.</p></div>
        </div>
        <?php return; ?>
    <?php endif; ?>

    <form method="get" class="sige-priv-procura">
        <input type="hidden" name="page" value="sige-app" />
        <input type="hidden" name="view" value="privacidade-apagamento" />
        <label for="sige-apag-q" class="sige-priv-procura__label">Procurar aluno (número de processo ou nome)</label>
        <input type="search" id="sige-apag-q" name="q" value="<?php echo esc_attr($q); ?>" class="regular-text" placeholder="Ex.: 2024-0123 ou Maria Joao" />
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
                            <td><a class="button" href="<?php echo esc_url($base_url . '&aluno_id=' . (int) $r['id']); ?>">Pré-visualizar apagamento</a></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($aluno_id > 0): ?>
        <?php if (!$plano || empty($plano['ok'])): ?>
            <div class="notice notice-error"><p>Não foi possível preparar o apagamento deste aluno nesta escola.</p></div>
        <?php else: ?>
            <?php $proc = (string) ($plano['identificacao']['numero_processo'] ?? ''); ?>
            <h2 class="sige-priv-sec">
                Pré-visualização para <?php echo esc_html((string) ($plano['identificacao']['nome_completo'] ?? '')); ?>
                <span class="sige-priv-nota">(processo <?php echo esc_html($proc); ?>)</span>
            </h2>

            <?php if (!empty($plano['ja_anonimizado'])): ?>
                <div class="notice notice-info"><p>Este aluno já está anonimizado. Reexecutar não altera nada.</p></div>
            <?php endif; ?>

            <p class="sige-priv-hint">Vão ser redigidos <?php echo (int) $plano['total_colunas']; ?> campos em <?php echo (int) $plano['total_seccoes']; ?> tabelas. <?php echo esc_html((string) $plano['preservado']); ?></p>

            <?php foreach ($plano['seccoes'] as $sec): ?>
                <h3 class="sige-priv-tabela"><code><?php echo esc_html($sec['tabela']); ?></code> <span class="sige-priv-nota"><?php echo count($sec['alvos']); ?> campo(s) a redigir</span></h3>
                <table class="widefat striped">
                    <thead><tr><th>Campo</th><th>Categoria</th><th>Acção</th></tr></thead>
                    <tbody>
                        <?php foreach ($sec['alvos'] as $alvo): ?>
                            <tr>
                                <td>
                                    <code><?php echo esc_html($alvo['coluna']); ?></code>
                                    <?php if (($alvo['sensibilidade'] ?? 'normal') === 'sensivel'): ?>
                                        <span class="sige-priv-badge sige-priv-badge--sensivel">sensível</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html((string) $alvo['categoria']); ?></td>
                                <td><?php echo esc_html((string) $alvo['rotulo']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php if (!empty($sec['preservadas'])): ?>
                    <p class="sige-priv-nota">Preservadas: <?php echo esc_html(implode(', ', $sec['preservadas'])); ?>.</p>
                <?php endif; ?>
            <?php endforeach; ?>

            <div class="sige-priv-perigo">
                <h3 class="sige-priv-perigo__titulo">Confirmação necessária</h3>
                <p>Esta acção é permanente e não pode ser desfeita. Para confirmar, escreva o número de processo exacto do aluno: <code><?php echo esc_html($proc); ?></code></p>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="sige-priv-perigo__form">
                    <input type="hidden" name="action" value="sige_privacidade_apagar" />
                    <input type="hidden" name="aluno_id" value="<?php echo (int) $aluno_id; ?>" />
                    <?php wp_nonce_field('sige_privacidade_apagar'); ?>
                    <label for="sige-apag-conf" class="sige-priv-procura__label">Número de processo</label>
                    <input type="text" id="sige-apag-conf" name="confirmar_processo" value="" class="regular-text" autocomplete="off" placeholder="Escreva o número de processo" />
                    <button type="submit" class="button sige-priv-perigo__btn">Anonimizar definitivamente</button>
                </form>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
