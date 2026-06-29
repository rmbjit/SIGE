<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

if (!function_exists('sige_circular_pode_enviar') || !sige_circular_pode_enviar()) {
    echo '<div class="notice notice-warning" style="margin:20px;border-radius:8px;"><p>Esta área é reservada à Direcção e à Secretaria Geral.</p></div>';
    return;
}

$escola_id = function_exists('sige_get_escola_id') ? (int) sige_get_escola_id() : 0;
$ano = (int) date('Y');
$tT = $wpdb->prefix . 'sige_turmas';
$turmas = $wpdb->get_results($wpdb->prepare(
    "SELECT id, nome, classe FROM {$tT} WHERE escola_id = %d ORDER BY classe ASC, nome ASC", $escola_id
));
$nonce = wp_create_nonce('sige_circular');
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
$erro = isset($_GET['erro']) ? sanitize_key($_GET['erro']) : '';
$erros_msg = [
    'confirmacao' => 'Marque a confirmação de envio antes de enviar.',
    'curta' => 'A mensagem é demasiado curta. Escreva pelo menos 20 caracteres.',
    'longa' => 'A mensagem é demasiado longa. O limite são 800 caracteres.',
    'sem_destinatarios' => 'Não há famílias com contacto WhatsApp válido no escopo escolhido.',
];
?>
<div class="wrap" style="max-width:880px; padding:20px;">
    <h1 style="display:flex;align-items:center;gap:12px;color:#0d1259;">
        <span style="font-size:30px;">📣</span> Circulares aos Encarregados
    </h1>
    <p style="color:#475569;margin-top:0;max-width:680px;">
        Envie um comunicado oficial da escola por WhatsApp: reunião de pais, interrupção
        lectiva, eventos ou avisos gerais. Cada família recebe uma única mensagem,
        mesmo com vários educandos na escola.
    </p>

    <?php if ($ok > 0): ?>
    <div style="background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;border-radius:10px;padding:14px 16px;margin:14px 0;">
        ✅ Circular colocada na fila para <strong><?php echo (int)$ok; ?> família(s)</strong>.
        O envio é feito de forma gradual e segura; acompanhe na <a href="?page=sige-app&view=whatsapp_central">Central de Mensagens</a>.
    </div>
    <?php elseif ($erro && isset($erros_msg[$erro])): ?>
    <div style="background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:10px;padding:14px 16px;margin:14px 0;">
        ⚠ <?php echo esc_html($erros_msg[$erro]); ?>
    </div>
    <?php endif; ?>

    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="sgCircForm"
          style="background:#fff;border-radius:12px;padding:22px;box-shadow:0 2px 10px rgba(0,0,0,.06);">
        <input type="hidden" name="action" value="sige_circular_enviar">
        <input type="hidden" name="_wpnonce" value="<?php echo esc_attr($nonce); ?>">

        <div style="display:flex;gap:18px;flex-wrap:wrap;align-items:flex-end;">
            <div>
                <label style="font-weight:600;color:#0f172a;display:block;margin-bottom:6px;">Quem recebe</label>
                <select name="escopo" id="sgCircEscopo" style="min-width:220px;padding:8px 10px;border-radius:8px;border:1px solid #cbd5e1;">
                    <option value="escola">Escola inteira (todas as famílias)</option>
                    <option value="turma">Apenas uma turma</option>
                </select>
            </div>
            <div id="sgCircTurmaWrap" style="display:none;">
                <label style="font-weight:600;color:#0f172a;display:block;margin-bottom:6px;">Turma</label>
                <select name="turma_id" id="sgCircTurma" style="min-width:260px;padding:8px 10px;border-radius:8px;border:1px solid #cbd5e1;">
                    <option value="0">Escolha a turma</option>
                    <?php foreach ((array)$turmas as $t): ?>
                    <option value="<?php echo (int)$t->id; ?>"><?php echo esc_html(trim(($t->classe ? $t->classe . ' - ' : '') . $t->nome)); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="button" id="sgCircPreview" class="button" style="height:38px;">Pré-visualizar destinatários</button>
        </div>

        <div id="sgCircResumo" style="display:none;margin-top:14px;padding:12px 14px;border-radius:10px;background:#f8fafc;border:1px solid #e2e8f0;color:#334155;font-size:13.5px;"></div>

        <div style="margin-top:18px;">
            <label style="font-weight:600;color:#0f172a;display:block;margin-bottom:6px;">Mensagem</label>
            <textarea name="mensagem" id="sgCircMsg" rows="6" maxlength="800" required
                placeholder="Ex.: Caros Encarregados, a reunião de pais e encarregados realiza-se no sábado, 20 de Junho, às 8h00, na escola. Contamos com a vossa presença."
                style="width:100%;padding:12px;border-radius:10px;border:1px solid #cbd5e1;font-size:14px;line-height:1.5;"></textarea>
            <div style="display:flex;justify-content:space-between;color:#64748b;font-size:12px;margin-top:4px;">
                <span>O nome da escola é acrescentado automaticamente no topo da mensagem.</span>
                <span><span id="sgCircCount">0</span>/800</span>
            </div>
        </div>

        <div style="margin-top:16px;padding-top:14px;border-top:1px dashed #e2e8f0;display:flex;gap:14px;align-items:center;flex-wrap:wrap;">
            <label style="display:flex;gap:8px;align-items:center;color:#0f172a;">
                <input type="checkbox" name="confirmo_envio" value="1" id="sgCircConfirma">
                Confirmo o envio desta circular para <strong id="sgCircNum">as famílias do escopo escolhido</strong>.
            </label>
            <button type="submit" id="sgCircEnviar" class="button button-primary" disabled
                    style="height:40px;padding:0 22px;font-weight:600;">Enviar circular</button>
        </div>
    </form>

    <p style="color:#64748b;font-size:12.5px;margin-top:12px;">
        As mensagens entram na fila normal do WhatsApp e saem ao ritmo seguro definido
        pelo sistema. Cada envio fica registado na auditoria.
    </p>
</div>

<script <?php echo sige_csp_script_attr(); ?>>
(function(){
    var ajaxurl = <?php echo wp_json_encode(admin_url('admin-ajax.php')); ?>;
    var nonce = <?php echo wp_json_encode($nonce); ?>;
    var escopo = document.getElementById('sgCircEscopo');
    var turmaWrap = document.getElementById('sgCircTurmaWrap');
    var turma = document.getElementById('sgCircTurma');
    var btnPrev = document.getElementById('sgCircPreview');
    var resumo = document.getElementById('sgCircResumo');
    var msg = document.getElementById('sgCircMsg');
    var count = document.getElementById('sgCircCount');
    var confirma = document.getElementById('sgCircConfirma');
    var enviar = document.getElementById('sgCircEnviar');
    var num = document.getElementById('sgCircNum');
    var familiasPrev = 0;

    function syncEscopo(){ turmaWrap.style.display = (escopo.value === 'turma') ? '' : 'none'; resumo.style.display = 'none'; familiasPrev = 0; syncBotao(); }
    function syncBotao(){ enviar.disabled = !(confirma.checked && msg.value.trim().length >= 20); }
    escopo.addEventListener('change', syncEscopo);
    msg.addEventListener('input', function(){ count.textContent = msg.value.length; syncBotao(); });
    confirma.addEventListener('change', syncBotao);

    btnPrev.addEventListener('click', function(){
        if (escopo.value === 'turma' && (!turma.value || turma.value === '0')) { sigeUi.toast('Escolha a turma primeiro.', 'aviso'); return; }
        btnPrev.disabled = true; btnPrev.textContent = 'A contar...';
        var fd = new FormData();
        fd.append('action', 'sige_circular_preview');
        fd.append('_wpnonce', nonce);
        fd.append('escopo', escopo.value);
        fd.append('turma_id', turma.value || '0');
        fetch(ajaxurl, { method:'POST', credentials:'same-origin', body: fd })
            .then(function(r){ return r.json(); })
            .then(function(j){
                btnPrev.disabled = false; btnPrev.textContent = 'Pré-visualizar destinatários';
                if (!j || !j.success) { sigeUi.toast((j && j.data) ? j.data : 'Não foi possível pré-visualizar.', 'erro'); return; }
                familiasPrev = j.data.familias;
                num.textContent = familiasPrev + ' família(s)';
                var html = '<strong>' + familiasPrev + ' família(s)</strong> receberão esta circular '
                         + '(' + j.data.alunos + ' aluno(s) no escopo).';
                if (j.data.amostra && j.data.amostra.length) {
                    html += '<br><span style="color:#64748b;">Ex.: ' + j.data.amostra.join(' · ') + (familiasPrev > j.data.amostra.length ? ' ...' : '') + '</span>';
                }
                resumo.innerHTML = html; resumo.style.display = '';
            })
            .catch(function(){ btnPrev.disabled = false; btnPrev.textContent = 'Pré-visualizar destinatários'; sigeUi.toast('Falha de ligação. Tente novamente.', 'erro'); });
    });

    syncEscopo();
})();
</script>
