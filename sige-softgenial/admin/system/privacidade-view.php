<?php
/**
 * Inventario de Dados Pessoais - Fase 8 incremento 1.
 *
 * Ecra so de leitura de governanca de dados. Apresenta o mapa de PII por tabela
 * e campo (categoria, sensibilidade, finalidade, base legal), a cobertura face ao
 * esquema vivo, os desvios e lacunas, e agregados estatisticos por escola (apenas
 * contagens, nunca dados individuais). Nao tem formularios nem POST: nao cria
 * qualquer endpoint de escrita. Estilo so com tokens (assets/views/privacidade.css).
 */

if (!defined('ABSPATH')) exit;

if (!function_exists('sige_privacidade_pode_aceder')) {
    $sige_priv_lib = SIGE_PATH . 'includes/privacy/pii-inventario.php';
    if (file_exists($sige_priv_lib)) require_once $sige_priv_lib;
}

if (!function_exists('sige_privacidade_pode_aceder') || !sige_privacidade_pode_aceder()) {
    echo '<div class="notice notice-warning"><p>Esta área é reservada a quem pode consultar o inventário de dados pessoais (Direcção e administração).</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$inv = sige_pii_inventario($escola_id);
$resumo = $inv['resumo'];
$categorias = $inv['categorias'];
$bases = function_exists('sige_pii_bases_legais') ? sige_pii_bases_legais() : [];

$cat_rotulo = function ($k) use ($categorias) {
    return isset($categorias[$k]) ? $categorias[$k]['rotulo'] : $k;
};
$base_rotulo = function ($k) use ($bases) {
    return isset($bases[$k]) ? $bases[$k] : $k;
};

// Rotulos legiveis dos agregados.
$agg_rotulos = [
    'titulares_alunos'       => 'Alunos registados',
    'alunos_com_contacto'    => 'Alunos com contacto de encarregado',
    'titulares_funcionarios' => 'Funcionários registados',
    'alunos_com_saude'       => 'Alunos com registo de saúde',
    'pagamentos'             => 'Pagamentos registados',
    'transacoes_moveis'      => 'Transacções móveis',
    'registos_acesso'        => 'Registos de acesso (portaria)',
    'consent_whatsapp'       => 'Consentimento WhatsApp',
    'consent_email'          => 'Consentimento e-mail',
    'consent_sms'            => 'Consentimento SMS',
    'consent_chamada'        => 'Consentimento chamada',
];
?>
<div class="wrap">
    <h1 class="sige-priv-titulo">🛡️ Inventário de Dados Pessoais</h1>
    <p class="sige-priv-hint">
        Mapa dos dados pessoais que o sistema guarda, por tabela e campo, com a finalidade e a
        base legal de cada um. É só de leitura: serve para governança de dados (saber o que existe,
        para quê e com que fundamento). As contagens por escola são agregados estatísticos; nenhum
        dado individual é mostrado aqui. A base legal indicada é uma classificação por omissão,
        que a instituição deve rever enquanto responsável pelo tratamento.
    </p>

    <div class="sige-priv-cards">
        <div class="sige-priv-card">
            <div class="sige-priv-card__rotulo">Campos catalogados</div>
            <div class="sige-priv-card__valor"><?php echo (int) $resumo['total_campos']; ?></div>
        </div>
        <div class="sige-priv-card sige-priv-card--ok">
            <div class="sige-priv-card__rotulo">Presentes no esquema</div>
            <div class="sige-priv-card__valor"><?php echo (int) $resumo['campos_presentes']; ?></div>
        </div>
        <div class="sige-priv-card sige-priv-card--sensivel">
            <div class="sige-priv-card__rotulo">Campos sensíveis</div>
            <div class="sige-priv-card__valor"><?php echo (int) $resumo['campos_sensiveis']; ?></div>
        </div>
        <div class="sige-priv-card">
            <div class="sige-priv-card__rotulo">Tabelas com dados pessoais</div>
            <div class="sige-priv-card__valor"><?php echo (int) $resumo['tabelas_presentes']; ?> / <?php echo (int) $resumo['tabelas_catalogadas']; ?></div>
        </div>
        <div class="sige-priv-card <?php echo $resumo['desvios'] > 0 ? 'sige-priv-card--aviso' : ''; ?>">
            <div class="sige-priv-card__rotulo">Desvios (catálogo vs esquema)</div>
            <div class="sige-priv-card__valor"><?php echo (int) $resumo['desvios']; ?></div>
        </div>
        <div class="sige-priv-card <?php echo $resumo['lacunas'] > 0 ? 'sige-priv-card--aviso' : ''; ?>">
            <div class="sige-priv-card__rotulo">Lacunas (PII sem classificar)</div>
            <div class="sige-priv-card__valor"><?php echo (int) $resumo['lacunas']; ?></div>
        </div>
    </div>

    <?php if (empty($inv['escola_valida'])): ?>
        <div class="notice notice-info"><p>Sem escola activa no contexto: os agregados por escola não são apresentados. O mapa de classificação abaixo aplica-se a toda a instalação.</p></div>
    <?php else: ?>
        <h2 class="sige-priv-sec">Agregados desta escola</h2>
        <p class="sige-priv-hint">Apenas contagens, para dimensionar o tratamento de dados. Nenhum registo individual.</p>
        <div class="sige-priv-cards">
            <?php foreach ($inv['agregados'] as $chave => $valor): ?>
                <div class="sige-priv-card sige-priv-card--mini">
                    <div class="sige-priv-card__rotulo"><?php echo esc_html($agg_rotulos[$chave] ?? $chave); ?></div>
                    <div class="sige-priv-card__valor"><?php echo (int) $valor; ?></div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($inv['desvios'])): ?>
        <div class="notice notice-warning">
            <p><strong>Desvios:</strong> campos no catálogo que não foram encontrados no esquema (catálogo a precisar de revisão):</p>
            <ul class="sige-priv-lista">
                <?php foreach ($inv['desvios'] as $d): ?>
                    <li><code><?php echo esc_html($d['tabela'] . '.' . $d['coluna']); ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($inv['lacunas'])): ?>
        <div class="notice notice-warning">
            <p><strong>Lacunas:</strong> colunas com aspeto de dado pessoal que ainda não estão classificadas no catálogo (a rever num próximo incremento):</p>
            <ul class="sige-priv-lista">
                <?php foreach ($inv['lacunas'] as $l): ?>
                    <li><code><?php echo esc_html($l['tabela'] . '.' . $l['coluna']); ?></code></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <h2 class="sige-priv-sec">Mapa de dados pessoais por tabela</h2>
    <?php foreach ($inv['cobertura'] as $tab): ?>
        <h3 class="sige-priv-tabela">
            <code><?php echo esc_html($tab['tabela']); ?></code>
            <?php if (empty($tab['tabela_existe'])): ?>
                <span class="sige-priv-badge sige-priv-badge--ausente">tabela ausente nesta instalação</span>
            <?php endif; ?>
        </h3>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>Campo</th>
                    <th>Categoria</th>
                    <th>Sensibilidade</th>
                    <th>Finalidade</th>
                    <th>Base legal</th>
                    <th>No esquema</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tab['campos'] as $c): ?>
                    <tr>
                        <td><code><?php echo esc_html($c['coluna']); ?></code><?php if (!empty($c['nota'])): ?><br><span class="sige-priv-nota"><?php echo esc_html($c['nota']); ?></span><?php endif; ?></td>
                        <td><?php echo esc_html($cat_rotulo($c['categoria'])); ?></td>
                        <td>
                            <?php if ($c['sensibilidade'] === 'sensivel'): ?>
                                <span class="sige-priv-badge sige-priv-badge--sensivel">sensível</span>
                            <?php else: ?>
                                <span class="sige-priv-badge sige-priv-badge--normal">normal</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo esc_html($c['finalidade']); ?></td>
                        <td><?php echo esc_html($base_rotulo($c['base_legal'])); ?></td>
                        <td>
                            <?php if (!empty($c['presente'])): ?>
                                <span class="sige-priv-badge sige-priv-badge--ok">sim</span>
                            <?php else: ?>
                                <span class="sige-priv-badge sige-priv-badge--ausente">não</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
</div>
