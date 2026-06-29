<?php
/**
 * SIGE SoftGenial - Centros de Custo
 * Gestão visual dos centros usados para organizar receitas e despesas.
 */

if (!defined('ABSPATH')) exit;

if (!sige_page_guard(
    ['financeiro.centros_custo_ver','financeiro.centros_custo_gerir'],
    ['sige_director','sige_financeiro']
)) return;

$nonce = wp_create_nonce('sige_centros_crud');
global $wpdb;
$centros = sige_fin_get_centros(true);

$__servicos_list = [];
$__tSrv = $wpdb->prefix . 'sige_fin_servicos';
if ($wpdb->get_var("SHOW TABLES LIKE '{$__tSrv}'") === $__tSrv) {
    $__servicos_list = $wpdb->get_results($wpdb->prepare(
        "SELECT id, nome, centro_id, tipo FROM {$__tSrv}
         WHERE escola_id = %d AND ativo = 1
         ORDER BY nome ASC",
        function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0
    ));
}

$centros_total = is_array($centros) ? count($centros) : 0;
$centros_activos = 0;
$centros_inactivos = 0;
foreach ((array)$centros as $__centro) {
    if ((int)($__centro->activo ?? 0) === 1) {
        $centros_activos++;
    } else {
        $centros_inactivos++;
    }
}
$servicos_total = is_array($__servicos_list) ? count($__servicos_list) : 0;
$servicos_sem_centro = 0;
foreach ((array)$__servicos_list as $__servico) {
    if ((int)($__servico->centro_id ?? 0) <= 0) {
        $servicos_sem_centro++;
    }
}
$__centros_ativos = array_values(array_filter((array)$centros, function($c) { return (int)($c->activo ?? 0) === 1; }));

$icon_building = function_exists('sige_ui_icon') ? sige_ui_icon('building') : '';
$icon_plus     = function_exists('sige_ui_icon') ? sige_ui_icon('plus') : '';
$icon_check    = function_exists('sige_ui_icon') ? sige_ui_icon('check') : '';
$icon_activity = function_exists('sige_ui_icon') ? sige_ui_icon('activity') : '';
$icon_file     = function_exists('sige_ui_icon') ? sige_ui_icon('file') : '';
$icon_settings = function_exists('sige_ui_icon') ? sige_ui_icon('settings') : '';
$icon_shield   = function_exists('sige_ui_icon') ? sige_ui_icon('shield') : '';
?>
<div class="sg-finpro-wrap sg-centers-wrap">
    <section class="sg-finpro-hero sg-centers-hero" aria-label="Centros de Custo">
        <div class="sg-finpro-hero-copy">
            <div class="sg-finpro-kicker"><?php echo $icon_building; ?> Organização financeira</div>
            <h1>Centros de Custo</h1>
            <p>Organize receitas, despesas e serviços por áreas da escola para acompanhar cada unidade com mais clareza.</p>
            <div class="sg-finpro-hero-actions">
                <a class="sg-finpro-btn sg-finpro-btn-primary" href="#sige-centro-form-card"><?php echo $icon_plus; ?> Novo centro</a>
                <?php if (count($__centros_ativos) >= 2 && !empty($__servicos_list)): ?>
                    <a class="sg-finpro-btn sg-finpro-btn-light" href="#sige-centro-reassign-card"><?php echo $icon_settings; ?> Mover serviço</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="sg-finpro-hero-panel">
            <div class="sg-finpro-mini-label">Centros activos</div>
            <strong><?php echo (int)$centros_activos; ?></strong>
            <span><?php echo (int)$centros_total; ?> centro(s) registado(s) na escola</span>
            <div class="sg-finpro-progress"><i style="width:<?php echo $centros_total > 0 ? min(100, round(($centros_activos / max(1, $centros_total)) * 100)) : 0; ?>%"></i></div>
            <small><?php echo (int)$servicos_total; ?> serviço(s) activos para classificação financeira</small>
        </div>
    </section>

    <div id="sige-centro-feedback" class="sg-centers-feedback" aria-live="polite"></div>

    <section class="sg-finpro-kpi-grid sg-centers-kpis" aria-label="Resumo dos centros de custo">
        <article class="sg-finpro-kpi sg-finpro-tone-blue">
            <div class="sg-finpro-kpi-icon"><?php echo $icon_building; ?></div>
            <div><span>Centros registados</span><strong><?php echo (int)$centros_total; ?></strong><small>Áreas financeiras da escola</small></div>
        </article>
        <article class="sg-finpro-kpi sg-finpro-tone-green">
            <div class="sg-finpro-kpi-icon"><?php echo $icon_check; ?></div>
            <div><span>Centros activos</span><strong><?php echo (int)$centros_activos; ?></strong><small>Disponíveis para operação</small></div>
        </article>
        <article class="sg-finpro-kpi sg-finpro-tone-amber">
            <div class="sg-finpro-kpi-icon"><?php echo $icon_file; ?></div>
            <div><span>Serviços activos</span><strong><?php echo (int)$servicos_total; ?></strong><small>Podem ser atribuídos a centros</small></div>
        </article>
        <article class="sg-finpro-kpi sg-finpro-tone-coral">
            <div class="sg-finpro-kpi-icon"><?php echo $icon_activity; ?></div>
            <div><span>Por classificar</span><strong><?php echo (int)$servicos_sem_centro; ?></strong><small>Serviços sem centro definido</small></div>
        </article>
    </section>

    <section class="sg-finpro-grid sg-centers-main-grid">
        <article id="sige-centro-form-card" class="sg-finpro-card sg-centers-form-card">
            <div class="sg-finpro-card-head">
                <div>
                    <span class="sg-finpro-section-icon"><?php echo $icon_plus; ?></span>
                    <div>
                        <h2 id="sige-form-titulo">Novo Centro</h2>
                        <p>Defina a área, a cor de identificação e o estado de utilização.</p>
                    </div>
                </div>
            </div>

            <form id="sige-form-centro" class="sg-centers-form" onsubmit="return false;">
                <input type="hidden" name="centro_id" id="centro_id" value="0">
                <div class="sg-centers-form-grid">
                    <div class="sg-form-group sg-centers-span-2">
                        <label for="centro_nome">Nome do Centro <span>*</span></label>
                        <input class="sg-input" type="text" name="nome" id="centro_nome" required maxlength="150" placeholder="Ex: Centro Infantil">
                    </div>
                    <div class="sg-form-group sg-centers-span-2">
                        <label for="centro_descricao">Descrição</label>
                        <input class="sg-input" type="text" name="descricao" id="centro_descricao" maxlength="500" placeholder="Breve descrição da área">
                    </div>
                    <div class="sg-form-group">
                        <label for="centro_cor">Cor</label>
                        <input class="sg-input sg-centers-color" type="color" name="cor" id="centro_cor" value="#5f45dc">
                    </div>
                    <div class="sg-form-group">
                        <label for="centro_ordem">Ordem</label>
                        <input class="sg-input" type="number" name="ordem" id="centro_ordem" min="1" max="99" value="10">
                    </div>
                    <div class="sg-form-group sg-centers-span-2">
                        <label for="centro_activo">Estado</label>
                        <select class="sg-input" name="activo" id="centro_activo">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
                <div class="sg-centers-form-actions">
                    <button type="button" id="sige-btn-salvar" class="sg-finpro-btn sg-finpro-btn-primary" data-sige-act="sigeCentroSalvar" data-sige-noargs><?php echo $icon_check; ?> Guardar centro</button>
                    <button type="button" id="sige-btn-cancelar" class="sg-finpro-btn sg-finpro-btn-light" data-sige-act="sigeCentroLimparForm" data-sige-noargs style="display:none;">Cancelar edição</button>
                </div>
            </form>
        </article>

        <aside class="sg-finpro-card sg-centers-guide-card">
            <div class="sg-finpro-card-head">
                <div>
                    <span class="sg-finpro-section-icon sg-finpro-soft-blue"><?php echo $icon_shield; ?></span>
                    <div>
                        <h2>Como usar</h2>
                        <p>Boa organização evita confusão entre áreas com contas separadas.</p>
                    </div>
                </div>
            </div>
            <div class="sg-centers-guide-list">
                <div><strong>1</strong><span>Crie os centros que representam áreas reais da escola.</span></div>
                <div><strong>2</strong><span>Atribua serviços, receitas e despesas ao centro correcto.</span></div>
                <div><strong>3</strong><span>Use os relatórios para acompanhar cada área com maior clareza.</span></div>
            </div>
        </aside>
    </section>

    <article class="sg-finpro-card sg-centers-table-card">
        <div class="sg-finpro-card-head">
            <div>
                <span class="sg-finpro-section-icon"><?php echo $icon_building; ?></span>
                <div>
                    <h2>Centros registados</h2>
                    <p><?php echo (int)$centros_total; ?> centro(s) disponíveis para organização financeira.</p>
                </div>
            </div>
        </div>

        <?php if (empty($centros)): ?>
            <div class="sg-centers-empty">
                <strong>Ainda não há centros registados</strong>
                <p>Crie o primeiro centro para organizar receitas, despesas e serviços por área de gestão.</p>
                <a class="sg-finpro-btn sg-finpro-btn-primary" href="#sige-centro-form-card"><?php echo $icon_plus; ?> Criar primeiro centro</a>
            </div>
        <?php else: ?>
            <div class="sg-centers-table-wrap">
                <table class="sg-centers-table">
                    <thead>
                        <tr>
                            <th>Cor</th>
                            <th>Nome</th>
                            <th>Descrição</th>
                            <th>Ordem</th>
                            <th>Estado</th>
                            <th>Acções</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($centros as $c): ?>
                        <tr>
                            <td><span class="sg-centers-color-dot" style="background:<?php echo esc_attr($c->cor ?: '#5f45dc'); ?>;"></span></td>
                            <td><strong class="sg-centers-name"><?php echo esc_html($c->nome); ?></strong></td>
                            <td><span class="sg-centers-muted"><?php echo esc_html($c->descricao ?: 'Sem descrição'); ?></span></td>
                            <td><span class="sg-centers-order"><?php echo (int)$c->ordem; ?></span></td>
                            <td>
                                <?php if ((int)$c->activo === 1): ?>
                                    <span class="sg-centers-badge sg-centers-badge-active">Activo</span>
                                <?php else: ?>
                                    <span class="sg-centers-badge sg-centers-badge-inactive">Inactivo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="sg-centers-actions">
                                    <button type="button" class="sg-centers-btn sg-centers-btn-edit" data-sige-act="sigeExecutarJsonData" data-sige-json-fn="sigeCentroEditar" data-sige-json='<?php echo esc_attr(wp_json_encode([
                                        "id"        => (int)$c->id,
                                        "nome"      => $c->nome,
                                        "descricao" => $c->descricao,
                                        "cor"       => $c->cor,
                                        "ordem"     => (int)$c->ordem,
                                        "activo"    => (int)$c->activo,
                                    ])); ?>'>Editar</button>
                                    <button type="button" class="sg-centers-btn sg-centers-btn-danger" data-sige-act="sigeCentroEliminar" data-sige-args="<?php echo esc_attr(wp_json_encode([(int)$c->id, $c->nome])); ?>">Eliminar</button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </article>

    <?php if (count($__centros_ativos) >= 2 && !empty($__servicos_list)): ?>
    <article id="sige-centro-reassign-card" class="sg-finpro-card sg-centers-reassign-card">
        <div class="sg-finpro-card-head">
            <div>
                <span class="sg-finpro-section-icon sg-finpro-soft-amber"><?php echo $icon_settings; ?></span>
                <div>
                    <h2>Mover serviço entre centros</h2>
                    <p>Use esta opção para corrigir a classificação de um serviço e manter os relatórios organizados.</p>
                </div>
            </div>
        </div>

        <div id="sige-reassign-feedback" class="sg-centers-feedback" aria-live="polite"></div>

        <div class="sg-centers-reassign-grid">
            <div class="sg-form-group">
                <label for="reassign_servico">Serviço a mover</label>
                <select class="sg-input" id="reassign_servico">
                    <option value="0">Seleccione serviço</option>
                    <?php foreach ($__servicos_list as $__s):
                        $__cid_srv = (int)($__s->centro_id ?? 0);
                        $__centro_atual_nome = '';
                        foreach ($__centros_ativos as $__c2) {
                            if ((int)$__c2->id === $__cid_srv) { $__centro_atual_nome = $__c2->nome; break; }
                        }
                        $__display = $__s->nome . ($__centro_atual_nome ? ' - actual: ' . $__centro_atual_nome : ' - sem centro');
                    ?>
                    <option value="<?php echo (int)$__s->id; ?>" data-centro-atual="<?php echo $__cid_srv; ?>">
                        <?php echo esc_html($__display); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="sg-centers-arrow" aria-hidden="true">→</div>
            <div class="sg-form-group">
                <label for="reassign_destino">Centro de destino</label>
                <select class="sg-input" id="reassign_destino">
                    <option value="0">Seleccione destino</option>
                    <?php foreach ($__centros_ativos as $__c): ?>
                    <option value="<?php echo (int)$__c->id; ?>"><?php echo esc_html($__c->nome); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <label class="sg-centers-checkline">
            <input type="checkbox" id="reassign_incluir_hist" checked>
            <span><strong>Mover também o histórico financeiro</strong> - recomendado quando a classificação anterior estava errada.</span>
        </label>

        <div class="sg-centers-form-actions">
            <button type="button" data-sige-act="sigeReassignServicoPreview" data-sige-noargs id="btn-reassign-preview" class="sg-finpro-btn sg-finpro-btn-light">Pré-visualizar impacto</button>
            <button type="button" data-sige-act="sigeReassignServicoExecutar" data-sige-noargs id="btn-reassign-executar" class="sg-finpro-btn sg-finpro-btn-primary" disabled>Executar movimentação</button>
        </div>
    </article>
    <?php endif; ?>
</div>

<div id="sg-centers-confirm-modal" class="sg-modal-backdrop sg-centers-modal" aria-hidden="true">
    <div class="sige-lanc-modal sg-centers-modal-card" role="dialog" aria-modal="true" aria-labelledby="sg-centers-confirm-title">
        <div class="sige-lanc-modal-header">
            <h3 id="sg-centers-confirm-title" class="sige-lanc-modal-title">Confirmar acção</h3>
        </div>
        <div class="sige-lanc-modal-body">
            <p id="sg-centers-confirm-message" class="sg-centers-modal-text"></p>
            <div id="sg-centers-confirm-details" class="sg-expense-confirm-box" style="display:none;"></div>
        </div>
        <div class="sige-lanc-modal-footer">
            <button type="button" class="sg-finpro-btn sg-finpro-btn-light" data-sg-centers-cancel>Voltar</button>
            <button type="button" class="sg-finpro-btn sg-finpro-btn-primary" data-sg-centers-confirm>Confirmar</button>
        </div>
    </div>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
    'use strict';
    var SIGE_CENTROS_NONCE = <?php echo wp_json_encode($nonce); ?>;
    var ajaxUrl = (typeof sigeAjax !== 'undefined' && sigeAjax.url) ? sigeAjax.url : ajaxurl;
    var pendingAction = null;

    function escHtml(value) {
        return String(value == null ? '' : value).replace(/[&<>"]/g, function(ch){
            return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[ch];
        });
    }

    function feedback(msg, ok) {
        var box = document.getElementById('sige-centro-feedback');
        if (!box) return;
        box.innerHTML = '<div class="' + (ok ? 'sg-centers-notice sg-centers-notice-ok' : 'sg-centers-notice sg-centers-notice-error') + '">' + msg + '</div>';
        setTimeout(function(){ box.innerHTML = ''; }, 5000);
    }

    function reassignFeedback(msg, kind) {
        var box = document.getElementById('sige-reassign-feedback');
        if (!box) return;
        var cls = kind === 'ok' ? 'sg-centers-notice-ok' : (kind === 'info' ? 'sg-centers-notice-info' : 'sg-centers-notice-error');
        box.innerHTML = '<div class="sg-centers-notice ' + cls + '">' + msg + '</div>';
    }

    function openModal(title, message, details, onConfirm, confirmText) {
        var modal = document.getElementById('sg-centers-confirm-modal');
        var titleEl = document.getElementById('sg-centers-confirm-title');
        var messageEl = document.getElementById('sg-centers-confirm-message');
        var detailsEl = document.getElementById('sg-centers-confirm-details');
        var confirmBtn = modal ? modal.querySelector('[data-sg-centers-confirm]') : null;
        if (!modal || !titleEl || !messageEl || !detailsEl || !confirmBtn) return;
        pendingAction = onConfirm;
        titleEl.textContent = title || 'Confirmar acção';
        messageEl.textContent = message || '';
        confirmBtn.textContent = confirmText || 'Confirmar';
        if (details && details.length) {
            detailsEl.style.display = 'grid';
            detailsEl.innerHTML = details.map(function(item){
                return '<span>' + escHtml(item.label) + '</span><strong>' + escHtml(item.value) + '</strong>';
            }).join('');
        } else {
            detailsEl.style.display = 'none';
            detailsEl.innerHTML = '';
        }
        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sg-modal-open');
    }

    function closeModal() {
        var modal = document.getElementById('sg-centers-confirm-modal');
        if (!modal) return;
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('sg-modal-open');
        pendingAction = null;
    }

    document.addEventListener('click', function(ev){
        if (ev.target && ev.target.matches('[data-sg-centers-cancel]')) { closeModal(); }
        if (ev.target && ev.target.matches('[data-sg-centers-confirm]')) {
            var fn = pendingAction;
            closeModal();
            if (typeof fn === 'function') fn();
        }
        var modal = document.getElementById('sg-centers-confirm-modal');
        if (modal && ev.target === modal) closeModal();
    });
    document.addEventListener('keydown', function(ev){ if (ev.key === 'Escape') closeModal(); });

    window.sigeCentroSalvar = function() {
        var btn = document.getElementById('sige-btn-salvar');
        var id     = document.getElementById('centro_id').value || 0;
        var nome   = document.getElementById('centro_nome').value.trim();
        var desc   = document.getElementById('centro_descricao').value.trim();
        var cor    = document.getElementById('centro_cor').value;
        var ordem  = document.getElementById('centro_ordem').value || 10;
        var activo = document.getElementById('centro_activo').value;

        if (!nome) { feedback('O nome do centro é obrigatório.', false); return; }

        btn.disabled = true; btn.textContent = 'A guardar...';

        var fd = new FormData();
        fd.append('action', 'sige_centro_salvar');
        fd.append('nonce', SIGE_CENTROS_NONCE);
        fd.append('id', id);
        fd.append('nome', nome);
        fd.append('descricao', desc);
        fd.append('cor', cor);
        fd.append('ordem', ordem);
        fd.append('activo', activo);

        fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                btn.disabled = false; btn.textContent = 'Guardar centro';
                if (d.success) {
                    feedback(escHtml((d.data && d.data.msg) || 'Centro guardado.'), true);
                    setTimeout(function(){ location.reload(); }, 800);
                } else {
                    feedback(escHtml((d.data && d.data.msg) || d.data || 'Não foi possível guardar o centro.'), false);
                }
            })
            .catch(function(err){
                btn.disabled = false; btn.textContent = 'Guardar centro';
                feedback('Erro de ligação: ' + escHtml(err.message), false);
            });
    };

    window.sigeCentroEditar = function(c) {
        document.getElementById('centro_id').value        = c.id;
        document.getElementById('centro_nome').value      = c.nome || '';
        document.getElementById('centro_descricao').value = c.descricao || '';
        document.getElementById('centro_cor').value       = c.cor || '#5f45dc';
        document.getElementById('centro_ordem').value     = c.ordem || 10;
        document.getElementById('centro_activo').value    = (c.activo == 1 ? '1' : '0');
        document.getElementById('sige-form-titulo').textContent = 'Editar Centro';
        document.getElementById('sige-btn-cancelar').style.display = 'inline-flex';
        document.getElementById('sige-centro-form-card').scrollIntoView({behavior:'smooth', block:'start'});
    };

    window.sigeCentroLimparForm = function() {
        document.getElementById('centro_id').value = 0;
        document.getElementById('centro_nome').value = '';
        document.getElementById('centro_descricao').value = '';
        document.getElementById('centro_cor').value = '#5f45dc';
        document.getElementById('centro_ordem').value = 10;
        document.getElementById('centro_activo').value = '1';
        document.getElementById('sige-form-titulo').textContent = 'Novo Centro';
        document.getElementById('sige-btn-cancelar').style.display = 'none';
    };

    window.sigeCentroEliminar = function(id, nome) {
        openModal(
            'Eliminar centro de custo',
            'Confirme se pretende eliminar este centro. Esta acção só será concluída se não existirem registos associados.',
            [{label:'Centro', value:nome}],
            function(){
                var fd = new FormData();
                fd.append('action', 'sige_centro_eliminar');
                fd.append('nonce', SIGE_CENTROS_NONCE);
                fd.append('id', id);

                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        if (d.success) {
                            feedback(escHtml((d.data && d.data.msg) || 'Centro eliminado.'), true);
                            setTimeout(function(){ location.reload(); }, 800);
                        } else {
                            feedback(escHtml((d.data && d.data.msg) || d.data || 'Não foi possível eliminar o centro.'), false);
                        }
                    })
                    .catch(function(err){ feedback('Erro de ligação: ' + escHtml(err.message), false); });
            },
            'Eliminar'
        );
    };

    window.sigeReassignServicoPreview = function() {
        var servico = document.getElementById('reassign_servico').value;
        var destino = document.getElementById('reassign_destino').value;
        var incluirHist = document.getElementById('reassign_incluir_hist').checked;

        if (servico === '0' || destino === '0') {
            reassignFeedback('Escolha o serviço e o centro de destino.', 'err');
            return;
        }
        var sel = document.getElementById('reassign_servico').selectedOptions[0];
        if (sel && sel.dataset.centroAtual === destino) {
            reassignFeedback('O serviço já está atribuído a este centro.', 'err');
            return;
        }

        var fd = new FormData();
        fd.append('action', 'sige_centro_reatribuir_servico');
        fd.append('nonce', SIGE_CENTROS_NONCE);
        fd.append('servico_id', servico);
        fd.append('destino_id', destino);
        fd.append('incluir_historico', incluirHist ? '1' : '0');
        fd.append('preview', '1');

        var btn = document.getElementById('btn-reassign-preview');
        btn.disabled = true; btn.textContent = 'A calcular...';

        fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                btn.disabled = false; btn.textContent = 'Pré-visualizar impacto';
                if (!d.success) { reassignFeedback(escHtml(d.data || 'Não foi possível calcular o impacto.'), 'err'); return; }
                var p = d.data;
                var html = '<strong>Impacto previsto</strong><br>' +
                           'O serviço <strong>' + escHtml(p.servico_nome) + '</strong> será movido de <strong>' +
                           escHtml(p.origem_nome) + '</strong> para <strong>' + escHtml(p.destino_nome) + '</strong>.';
                if (incluirHist) {
                    html += '<br><br><strong>' + escHtml(p.n_lancamentos) + '</strong> lançamento(s) e <strong>' + escHtml(p.n_pagamentos) + '</strong> pagamento(s) serão também actualizados.';
                } else {
                    html += '<br><br>O histórico financeiro anterior será mantido como está.';
                }
                html += '<br><br>Depois de rever, clique em <strong>Executar movimentação</strong>.';
                reassignFeedback(html, 'info');

                var btnExec = document.getElementById('btn-reassign-executar');
                btnExec.disabled = false;
            })
            .catch(function(err){
                btn.disabled = false; btn.textContent = 'Pré-visualizar impacto';
                reassignFeedback('Erro de ligação: ' + escHtml(err.message), 'err');
            });
    };

    window.sigeReassignServicoExecutar = function() {
        var servicoEl = document.getElementById('reassign_servico');
        var destinoEl = document.getElementById('reassign_destino');
        var servico = servicoEl.value;
        var destino = destinoEl.value;
        var incluirHist = document.getElementById('reassign_incluir_hist').checked;

        if (servico === '0' || destino === '0') {
            reassignFeedback('Escolha o serviço e o centro de destino antes de continuar.', 'err');
            return;
        }

        openModal(
            'Confirmar movimentação',
            'Confirme se os dados estão correctos antes de executar a movimentação.',
            [
                {label:'Serviço', value: servicoEl.selectedOptions[0] ? servicoEl.selectedOptions[0].text : ''},
                {label:'Destino', value: destinoEl.selectedOptions[0] ? destinoEl.selectedOptions[0].text : ''},
                {label:'Histórico', value: incluirHist ? 'Mover também o histórico financeiro' : 'Manter histórico anterior como está'}
            ],
            function(){
                var fd = new FormData();
                fd.append('action', 'sige_centro_reatribuir_servico');
                fd.append('nonce', SIGE_CENTROS_NONCE);
                fd.append('servico_id', servico);
                fd.append('destino_id', destino);
                fd.append('incluir_historico', incluirHist ? '1' : '0');
                fd.append('preview', '0');

                var btn = document.getElementById('btn-reassign-executar');
                btn.disabled = true; btn.textContent = 'A executar...';

                fetch(ajaxUrl, { method:'POST', body:fd, credentials:'same-origin' })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        btn.disabled = false; btn.textContent = 'Executar movimentação';
                        if (!d.success) { reassignFeedback(escHtml(d.data || 'Não foi possível executar a movimentação.'), 'err'); return; }
                        reassignFeedback(escHtml((d.data && d.data.msg) || 'Movimentação concluída.'), 'ok');
                        setTimeout(function(){ location.reload(); }, 2500);
                    })
                    .catch(function(err){
                        btn.disabled = false; btn.textContent = 'Executar movimentação';
                        reassignFeedback('Erro de ligação: ' + escHtml(err.message), 'err');
                    });
            },
            'Executar'
        );
    };
})();
</script>
