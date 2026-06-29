<?php
if (!defined('ABSPATH')) exit;
global $wpdb;

// [FIX R-02] Guard de acesso - Secretaria Académica
// [12.9.6] Bug pré-existente corrigido: a guarda original estava FORA do
// contexto PHP (após `?>` na linha 1) - era impressa como texto e nunca
// executada. Agora corre dentro do PHP e usa a matriz SIGE.
if (!sige_page_guard(
    ['matriculas.editar','alunos.editar'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;
?>

<div class="sige-card" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">

    <h2 style="color: var(--sg-theme-primary-800,#3b2f8d);">🔗 Atribuição de Carga Horária</h2>

    

    <form id="form-vincular-carga" style="background: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 30px;">

        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px;">

            <div>

                <label>Professor</label><br>

                <select name="professor_id" required style="width:100%; padding:8px;">

                    <option value="">Seleccione o Professor...</option>

                    <?php 

                    $profs = sige_get_professores();

                    foreach($profs as $p) echo "<option value='" . (int)$p->id . "'>" . esc_html($p->nome_completo) . "</option>"; 

                    ?>

                </select>

            </div>

            <div>

                <label>Turma</label><br>

                <select name="turma_id" required style="width:100%; padding:8px;">

                    <option value="">Seleccione a Turma...</option>

                    <?php 

                    $turmas = sige_get_turmas();

                    foreach($turmas as $t) echo "<option value='{$t->id}'>{$t->nome_turma} ({$t->classe}ª)</option>"; 

                    ?>

                </select>

            </div>

            <div>

                <label>Disciplina</label><br>

                <select name="disciplina_id" required style="width:100%; padding:8px;">

                    <option value="">Seleccione a Disciplina...</option>

                    <?php 

                    $discs = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_disciplinas WHERE escola_id = %d ORDER BY nome ASC", function_exists('sige_get_escola_id') ? sige_get_escola_id() : 0));

                    foreach($discs as $d) echo "<option value='{$d->id}'>{$d->nome_disciplina}</option>"; 

                    ?>

                </select>

            </div>

        </div>

        <button type="submit" class="sgk-btn sgk-btn-primario" style="margin-top:15px;background:#2e7d32;border-color:#2e7d32;color: white; border: none; border-radius: 4px; cursor: pointer; font-weight:bold;">

            ✅ Confirmar Atribuição

        </button>

    </form>

</div>

<script <?php echo sige_csp_script_attr(); ?>>

jQuery('#form-vincular-carga').on('submit', function(e) {

    e.preventDefault();

    jQuery.post(ajaxurl, jQuery(this).serialize() + '&action=sige_vincular_carga', function(res) {

        sigeUi.toast(res.data || (res.success ? 'Carga vinculada.' : 'Não foi possível vincular.'), res.success ? 'ok' : 'erro');

        if(res.success) setTimeout(function(){ location.reload(); }, 700);

    });

});

</script>