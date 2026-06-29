<?php
/**
 * SIGE SoftGenial - Inscrições e Renovações Anuais
 * Camada visual harmonizada ao padrão Produto PRO.
 * Mantém a regra original de geração anual sem alterar fórmulas ou estrutura de dados.
 */

if (!defined('ABSPATH')) exit;

if (!sige_page_guard(
    ['financeiro.lancamentos_gerir','financeiro.pagar'],
    ['sige_director','sige_financeiro','sige_secretario']
)) return;

if (!sige_modulo_ativo('mod_financeiro')) {
    echo '<div class="sg-alert sg-alert-danger">A área financeira não está activa para esta escola.</div>';
    return;
}

global $wpdb;
$escola_id = sige_require_escola_id('inscricoes');
$tA = $wpdb->prefix . 'sige_alunos';
$tL = $wpdb->prefix . 'sige_fin_lancamentos';
$tS = $wpdb->prefix . 'sige_fin_servicos';

$moeda = function_exists('sige_moeda') ? sige_moeda() : 'MT';
$ano_lectivo = function_exists('sige_ano_lectivo_atual') ? (int)sige_ano_lectivo_atual() : (int)wp_date('Y');
$__sige_a_activo_ins = function_exists('sige_aluno_activo_sql') ? sige_aluno_activo_sql('a') : "(a.status IS NULL OR LOWER(a.status) IN ('activo','ativo','activa','ativa'))";

$srv_nova = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$tS} WHERE escola_id=%d AND tipo=%s AND ativo=1 LIMIT 1",
    $escola_id, 'inscricao_nova'
));
$srv_renov = $wpdb->get_row($wpdb->prepare(
    "SELECT * FROM {$tS} WHERE escola_id=%d AND tipo=%s AND ativo=1 LIMIT 1",
    $escola_id, 'renovacao_inscricao'
));

$total_alunos_activos = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tA} a WHERE a.escola_id=%d AND {$__sige_a_activo_ins}",
    $escola_id
));
$total_alunos_novos = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tA} a WHERE a.escola_id=%d AND {$__sige_a_activo_ins} AND YEAR(data_registo)=%d",
    $escola_id, $ano_lectivo
));
$total_renovacoes_previstas = max(0, $total_alunos_activos - $total_alunos_novos);
$mes_ref_anual = $ano_lectivo . '-00';
$total_ja_lancados = (int)$wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$tL} WHERE escola_id=%d AND mes_referencia=%s AND status != 'cancelado'",
    $escola_id, $mes_ref_anual
));

$mensagem = null;
$stats = null;

if (isset($_POST['sige_gerar_inscricoes']) && check_admin_referer('sige_fin_gerar_inscricoes')) {
    $data_venc = isset($_POST['data_vencimento']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_POST['data_vencimento'])
        ? sanitize_text_field((string)$_POST['data_vencimento'])
        : wp_date('Y-m-d', strtotime('+15 days'));
    $modo_reexec = !empty($_POST['modo_reexec']);
    $aluno_alvo = isset($_POST['aluno_alvo']) ? (int)$_POST['aluno_alvo'] : 0;

    if (!$srv_nova && !$srv_renov) {
        $mensagem = [
            'tipo' => 'danger',
            'titulo' => 'Antes de gerar, configure os serviços',
            'texto' => 'Não existem serviços activos para inscrição ou renovação. Configure-os primeiro em Preços e Serviços.'
        ];
    } else {
        $mes_ref = $ano_lectivo . '-00';
        $where_aluno = $aluno_alvo ? $wpdb->prepare("AND a.id=%d", $aluno_alvo) : '';
        $alunos = $wpdb->get_results($wpdb->prepare(
            "SELECT a.id, a.nome_completo, a.data_registo
             FROM {$tA} a
             WHERE a.escola_id=%d AND {$__sige_a_activo_ins} {$where_aluno}
             ORDER BY nome_completo ASC",
            $escola_id
        ));

        $stats = [
            'processados' => 0,
            'inscricoes_criadas' => 0,
            'renovacoes_criadas' => 0,
            'ignorados_existente' => 0,
            'sem_servico' => 0,
            'erros' => 0,
        ];

        foreach ($alunos as $a) {
            $stats['processados']++;

            $ano_registo = (int)wp_date('Y', strtotime((string)$a->data_registo));
            $eh_novo = ($ano_registo === $ano_lectivo);
            $srv = $eh_novo ? $srv_nova : $srv_renov;

            if (!$srv) {
                $stats['sem_servico']++;
                continue;
            }

            $existe = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$tL}
                 WHERE escola_id=%d AND aluno_id=%d AND servico_id=%d
                   AND mes_referencia=%s AND status != 'cancelado'",
                $escola_id, $a->id, $srv->id, $mes_ref
            ));

            if ($existe && !$modo_reexec) {
                $stats['ignorados_existente']++;
                continue;
            }

            if ($existe && $modo_reexec) {
                $st = $wpdb->get_var($wpdb->prepare(
                    "SELECT status FROM {$tL} WHERE id=%d",
                    $existe
                ));
                if ($st === 'pago') {
                    $stats['ignorados_existente']++;
                    continue;
                }
                $wpdb->update($tL, [
                    'valor_original' => $srv->valor,
                    'data_vencimento' => $data_venc,
                ], ['id' => $existe]);
            } else {
                if (function_exists('sige_fin_get_centro_do_servico')) {
                    $__centro_id_ins = sige_fin_get_centro_do_servico((int)$srv->id);
                } elseif (function_exists('sige_fin_get_centro_default_id')) {
                    $__centro_id_ins = sige_fin_get_centro_default_id();
                } else {
                    $__centro_id_ins = 1;
                }
                $ok = $wpdb->insert($tL, [
                    'escola_id' => $escola_id,
                    'centro_id' => $__centro_id_ins,
                    'aluno_id' => $a->id,
                    'servico_id' => $srv->id,
                    'descricao' => $srv->nome,
                    'mes_referencia' => $mes_ref,
                    'valor_original' => $srv->valor,
                    'valor_multa' => 0,
                    'valor_desconto' => 0,
                    'valor_pago' => 0,
                    'data_vencimento' => $data_venc,
                    'status' => 'pendente',
                    'data_criacao' => current_time('mysql'),
                ]);
                if ($ok === false) {
                    $stats['erros']++;
                    continue;
                }
            }

            if ($eh_novo) {
                $stats['inscricoes_criadas']++;
            } else {
                $stats['renovacoes_criadas']++;
            }
        }

        if (function_exists('sige_fin_log')) {
            sige_fin_log('geracao_lote_inscricoes_anuais', [
                'ano_lectivo' => $ano_lectivo,
                'aluno_alvo' => $aluno_alvo ?: null,
                'modo_reexec' => (int)$modo_reexec,
                'stats' => $stats,
            ]);
        }

        $mensagem = [
            'tipo' => ($stats['erros'] > 0 ? 'warning' : 'success'),
            'titulo' => ($stats['erros'] > 0 ? 'Processamento concluído com atenção' : 'Processamento concluído'),
            'texto' => 'A geração anual terminou. Veja o resumo abaixo antes de avançar para cobranças ou recibos.'
        ];
    }
}

$alunos_lista = $wpdb->get_results($wpdb->prepare(
    "SELECT a.id, a.nome_completo, a.classe_atual
     FROM {$tA} a
     WHERE a.escola_id=%d AND a.status='activo'
     ORDER BY a.classe_atual, a.nome_completo ASC",
    $escola_id
));

$servicos_configurados = (int)(!empty($srv_nova)) + (int)(!empty($srv_renov));
$estado_operacional = ($servicos_configurados === 2) ? 'Pronto para gerar' : (($servicos_configurados === 1) ? 'Configuração parcial' : 'Por configurar');
?>

<div class="sg-finpro-wrap sg-inspro-wrap">
    <section class="sg-finpro-hero sg-inspro-hero">
        <div class="sg-finpro-hero-copy">
            <span class="sg-finpro-kicker"><?php echo function_exists('sige_ui_icon') ? sige_ui_icon('calendar') : ''; ?> Tesouraria</span>
            <h1>Inscrições e Renovações</h1>
            <p>Gere a cobrança anual de inscrição para alunos novos e renovação para alunos que continuam na escola, mantendo os lançamentos organizados por ano lectivo.</p>
            <div class="sg-finpro-hero-actions">
                <a class="sg-finpro-btn sg-finpro-btn-primary" href="#sg-inspro-form">Gerar cobranças anuais</a>
                <a class="sg-finpro-btn sg-finpro-btn-light" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-config')); ?>">Preços e Serviços</a>
            </div>
        </div>
        <div class="sg-finpro-hero-panel">
            <div class="sg-finpro-mini-label">Estado da preparação</div>
            <strong><?php echo esc_html($estado_operacional); ?></strong>
            <span><?php echo (int)$servicos_configurados; ?> de 2 serviços necessários estão activos.</span>
            <div class="sg-finpro-progress" aria-hidden="true"><i style="width:<?php echo (int)(($servicos_configurados / 2) * 100); ?>%"></i></div>
            <small>Ano Lectivo <?php echo (int)$ano_lectivo; ?></small>
        </div>
    </section>

    <section class="sg-finpro-kpi-grid sg-inspro-kpis">
        <article class="sg-finpro-kpi sg-finpro-tone-blue">
            <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg></div>
            <div><span>Ano lectivo</span><strong><?php echo (int)$ano_lectivo; ?></strong><small>Período de referência</small></div>
        </article>
        <article class="sg-finpro-kpi sg-finpro-tone-green">
            <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/></svg></div>
            <div><span>Alunos activos</span><strong><?php echo number_format($total_alunos_activos, 0, ',', '.'); ?></strong><small>Elegíveis para análise</small></div>
        </article>
        <article class="sg-finpro-kpi sg-finpro-tone-amber">
            <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/><circle cx="12" cy="12" r="9"/></svg></div>
            <div><span>Alunos novos</span><strong><?php echo number_format($total_alunos_novos, 0, ',', '.'); ?></strong><small>Inscrição anual</small></div>
        </article>
        <article class="sg-finpro-kpi sg-finpro-tone-coral">
            <div class="sg-finpro-kpi-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-3-6.7"/><path d="M21 3v6h-6"/></svg></div>
            <div><span>Renovações previstas</span><strong><?php echo number_format($total_renovacoes_previstas, 0, ',', '.'); ?></strong><small><?php echo number_format($total_ja_lancados, 0, ',', '.'); ?> já lançadas no ano</small></div>
        </article>
    </section>

    <section class="sg-finpro-grid sg-inspro-grid">
        <article class="sg-finpro-card sg-inspro-services-card">
            <div class="sg-finpro-card-head">
                <div>
                    <span class="sg-finpro-section-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg></span>
                    <div>
                        <h2>Serviços necessários</h2>
                        <p>Confirme se os dois serviços anuais estão disponíveis antes de gerar cobranças.</p>
                    </div>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-config')); ?>">Ajustar preços</a>
            </div>
            <div class="sg-inspro-service-list">
                <div class="sg-inspro-service <?php echo $srv_nova ? 'is-ready' : 'is-missing'; ?>">
                    <span><?php echo $srv_nova ? 'Disponível' : 'Por configurar'; ?></span>
                    <strong>Inscrição de aluno novo</strong>
                    <small><?php echo $srv_nova ? esc_html($srv_nova->nome) . ' · ' . esc_html(number_format((float)$srv_nova->valor, 2, ',', '.')) . ' ' . esc_html($moeda) : 'Configure este serviço em Preços e Serviços.'; ?></small>
                </div>
                <div class="sg-inspro-service <?php echo $srv_renov ? 'is-ready' : 'is-missing'; ?>">
                    <span><?php echo $srv_renov ? 'Disponível' : 'Por configurar'; ?></span>
                    <strong>Renovação anual</strong>
                    <small><?php echo $srv_renov ? esc_html($srv_renov->nome) . ' · ' . esc_html(number_format((float)$srv_renov->valor, 2, ',', '.')) . ' ' . esc_html($moeda) : 'Configure este serviço em Preços e Serviços.'; ?></small>
                </div>
            </div>
        </article>

        <article class="sg-finpro-card sg-inspro-form-card" id="sg-inspro-form">
            <div class="sg-finpro-card-head">
                <div>
                    <span class="sg-finpro-section-icon sg-finpro-soft-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6"/><path d="M9 15l2 2 4-4"/></svg></span>
                    <div>
                        <h2>Gerar cobranças anuais</h2>
                        <p>Escolha o vencimento, seleccione todos os alunos ou apenas um aluno específico e confirme antes de gravar.</p>
                    </div>
                </div>
            </div>

            <?php if ($mensagem && empty($stats)): ?>
                <div class="sg-finpro-alert">
                    <span class="sg-finpro-alert-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><path d="M12 9v4M12 17h.01"/></svg></span>
                    <div><strong><?php echo esc_html($mensagem['titulo']); ?></strong><p><?php echo esc_html($mensagem['texto']); ?></p></div>
                </div>
            <?php endif; ?>

            <form method="post" class="sg-inspro-form" data-sg-inspro-form="1">
                <?php wp_nonce_field('sige_fin_gerar_inscricoes'); ?>
                <input type="hidden" name="sige_gerar_inscricoes" value="1">

                <div class="sg-inspro-fields">
                    <label>
                        <span>Data de vencimento</span>
                        <input type="date" name="data_vencimento" value="<?php echo esc_attr(wp_date('Y-m-d', strtotime('+15 days'))); ?>" required>
                        <small>Data limite para pagamento da inscrição ou renovação.</small>
                    </label>
                    <label>
                        <span>Aluno</span>
                        <select name="aluno_alvo">
                            <option value="0">Todos os alunos activos</option>
                            <?php foreach ($alunos_lista as $al): ?>
                                <option value="<?php echo (int)$al->id; ?>">
                                    <?php echo esc_html($al->nome_completo); ?><?php if ($al->classe_atual): ?> · <?php echo (int)$al->classe_atual; ?>ª classe<?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small>Use a opção individual quando quiser gerar para apenas um aluno.</small>
                    </label>
                    <label class="sg-inspro-check">
                        <input type="checkbox" name="modo_reexec" value="1">
                        <span>Actualizar pendentes existentes</span>
                        <small>Actualiza apenas valores e vencimentos ainda não pagos. Registos pagos permanecem intactos.</small>
                    </label>
                </div>

                <div class="sg-inspro-actions">
                    <button type="submit" class="sg-finpro-btn sg-finpro-btn-primary" <?php echo (!$srv_nova && !$srv_renov) ? 'disabled' : ''; ?>>Gerar cobranças anuais</button>
                    <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-lancamentos')); ?>" class="sg-finpro-btn sg-finpro-btn-light">Ver lançamentos</a>
                </div>
            </form>
        </article>
    </section>

    <?php if ($stats): ?>
        <section class="sg-finpro-card sg-inspro-result-inline" aria-live="polite">
            <div class="sg-finpro-card-head">
                <div>
                    <span class="sg-finpro-section-icon sg-finpro-soft-green"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></span>
                    <div>
                        <h2><?php echo esc_html($mensagem['titulo'] ?? 'Processamento concluído'); ?></h2>
                        <p><?php echo esc_html($mensagem['texto'] ?? 'A operação terminou.'); ?></p>
                    </div>
                </div>
                <a href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-lancamentos')); ?>">Ver lançamentos</a>
            </div>
            <div class="sg-inspro-result-grid">
                <div><span>Alunos processados</span><strong><?php echo (int)$stats['processados']; ?></strong></div>
                <div><span>Inscrições geradas</span><strong><?php echo (int)$stats['inscricoes_criadas']; ?></strong></div>
                <div><span>Renovações geradas</span><strong><?php echo (int)$stats['renovacoes_criadas']; ?></strong></div>
                <div><span>Já existentes</span><strong><?php echo (int)$stats['ignorados_existente']; ?></strong></div>
                <div><span>Sem serviço configurado</span><strong><?php echo (int)$stats['sem_servico']; ?></strong></div>
                <div><span>Com atenção</span><strong><?php echo (int)$stats['erros']; ?></strong></div>
            </div>
        </section>
    <?php endif; ?>
</div>

<div class="sg-inspro-modal" id="sgInsproConfirm" aria-hidden="true">
    <div class="sg-inspro-modal-box" role="dialog" aria-modal="true" aria-labelledby="sgInsproConfirmTitle">
        <div class="sg-inspro-modal-head">
            <span class="sg-inspro-modal-icon"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg></span>
            <div>
                <span>Confirmação</span>
                <h2 id="sgInsproConfirmTitle">Confirmar geração anual</h2>
            </div>
        </div>
        <div class="sg-inspro-modal-body">
            <p>Confirme os dados antes de gerar as cobranças anuais.</p>
            <div class="sg-inspro-confirm-list">
                <div><span>Vencimento</span><strong data-sg-inspro-summary="date">-</strong></div>
                <div><span>Aluno</span><strong data-sg-inspro-summary="student">-</strong></div>
                <div><span>Actualização de pendentes</span><strong data-sg-inspro-summary="mode">-</strong></div>
            </div>
            <div class="sg-inspro-warning">Esta acção cria lançamentos financeiros anuais. Registos já pagos não serão alterados.</div>
        </div>
        <div class="sg-inspro-modal-footer">
            <button type="button" class="sg-finpro-btn sg-finpro-btn-light" data-sg-inspro-close>Voltar e rever</button>
            <button type="button" class="sg-finpro-btn sg-finpro-btn-primary" data-sg-inspro-submit>Confirmar e gerar</button>
        </div>
    </div>
</div>

<?php if ($mensagem): ?>
<div class="sg-inspro-modal" id="sgInsproResult" aria-hidden="true" data-auto-open="1">
    <div class="sg-inspro-modal-box" role="dialog" aria-modal="true" aria-labelledby="sgInsproResultTitle">
        <div class="sg-inspro-modal-head">
            <span class="sg-inspro-modal-icon <?php echo !empty($stats) && (int)($stats['erros'] ?? 0) === 0 ? 'is-success' : 'is-warning'; ?>"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="M22 4L12 14.01l-3-3"/></svg></span>
            <div>
                <span>Resultado</span>
                <h2 id="sgInsproResultTitle"><?php echo esc_html($mensagem['titulo']); ?></h2>
            </div>
        </div>
        <div class="sg-inspro-modal-body">
            <p><?php echo esc_html($mensagem['texto']); ?></p>
            <?php if ($stats): ?>
                <div class="sg-inspro-result-grid is-modal">
                    <div><span>Alunos processados</span><strong><?php echo (int)$stats['processados']; ?></strong></div>
                    <div><span>Inscrições geradas</span><strong><?php echo (int)$stats['inscricoes_criadas']; ?></strong></div>
                    <div><span>Renovações geradas</span><strong><?php echo (int)$stats['renovacoes_criadas']; ?></strong></div>
                    <div><span>Já existentes</span><strong><?php echo (int)$stats['ignorados_existente']; ?></strong></div>
                    <div><span>Sem serviço configurado</span><strong><?php echo (int)$stats['sem_servico']; ?></strong></div>
                    <div><span>Com atenção</span><strong><?php echo (int)$stats['erros']; ?></strong></div>
                </div>
            <?php endif; ?>
        </div>
        <div class="sg-inspro-modal-footer">
            <button type="button" class="sg-finpro-btn sg-finpro-btn-light" data-sg-inspro-close>Fechar</button>
            <a class="sg-finpro-btn sg-finpro-btn-primary" href="<?php echo esc_url(admin_url('admin.php?page=sige-app&view=financeiro-lancamentos')); ?>">Ver lançamentos</a>
        </div>
    </div>
</div>
<?php endif; ?>

<script <?php echo sige_csp_script_attr(); ?>>
document.addEventListener('DOMContentLoaded', function(){
    var form = document.querySelector('[data-sg-inspro-form="1"]');
    var confirmModal = document.getElementById('sgInsproConfirm');
    var pendingSubmit = false;

    function openModal(modal){
        if (!modal) return;
        if (modal.parentNode !== document.body) document.body.appendChild(modal);
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden','false');
        document.documentElement.classList.add('sg-inspro-modal-open');
        document.body.classList.add('sg-inspro-modal-open');
    }
    function closeModal(modal){
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden','true');
        if (!document.querySelector('.sg-inspro-modal.is-open')) {
            document.documentElement.classList.remove('sg-inspro-modal-open');
            document.body.classList.remove('sg-inspro-modal-open');
        }
    }

    document.addEventListener('click', function(e){
        if (e.target.matches('[data-sg-inspro-close]')) {
            closeModal(e.target.closest('.sg-inspro-modal'));
        }
        if (e.target.classList && e.target.classList.contains('sg-inspro-modal')) {
            closeModal(e.target);
        }
        if (e.target.matches('[data-sg-inspro-submit]') && form) {
            pendingSubmit = true;
            form.submit();
        }
    });

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape') {
            document.querySelectorAll('.sg-inspro-modal.is-open').forEach(closeModal);
        }
    });

    if (form && confirmModal) {
        form.addEventListener('submit', function(e){
            if (pendingSubmit) return;
            e.preventDefault();
            var date = form.querySelector('[name="data_vencimento"]');
            var student = form.querySelector('[name="aluno_alvo"]');
            var mode = form.querySelector('[name="modo_reexec"]');
            var dateTarget = confirmModal.querySelector('[data-sg-inspro-summary="date"]');
            var studentTarget = confirmModal.querySelector('[data-sg-inspro-summary="student"]');
            var modeTarget = confirmModal.querySelector('[data-sg-inspro-summary="mode"]');
            if (dateTarget) dateTarget.textContent = date && date.value ? date.value : 'Sem data';
            if (studentTarget) studentTarget.textContent = student && student.selectedOptions[0] ? student.selectedOptions[0].textContent.trim() : 'Todos os alunos activos';
            if (modeTarget) modeTarget.textContent = mode && mode.checked ? 'Sim, actualizar pendentes' : 'Não, manter existentes';
            openModal(confirmModal);
        });
    }

    document.querySelectorAll('.sg-inspro-modal[data-auto-open="1"]').forEach(function(modal){
        openModal(modal);
    });
});
</script>
