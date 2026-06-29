<?php
if (!defined('ABSPATH')) exit;

// [FIX R-02] Guard de acesso - Secretaria Académica
// [12.9.6] Guarda centralizada via matriz SIGE (alocacao_ver); WP roles fallback.
if (!sige_page_guard(
    ['academico.alocacao_ver','academico.alocacao_gerir'],
    ['sige_director','sige_secretario','sige_secretaria_geral','sige_assistente']
)) return;

global $wpdb;

$turma_id = isset($_GET['turma_id']) ? intval($_GET['turma_id']) : 0;

$turma = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sige_turmas WHERE id = %d AND escola_id = %d", $turma_id, sige_get_escola_id()));

if (!$turma) {

    echo "<h2>Turma não encontrada.</h2>";

    return;

}

?>

<div class="sige-card" style="background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05);">

    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid #eee; padding-bottom: 15px; margin-bottom: 20px;">

        <h2 style="color: var(--sg-theme-primary-800,#3b2f8d); margin:0;">👥 Enturmação: <?php echo $turma->nome_turma; ?> (<?php echo $turma->classe; ?>ª Classe)</h2>

        <a href="?page=sige-app&view=turmas" style="text-decoration:none; color:#666; font-weight:bold;">← Voltar às Turmas</a>

    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px;">

        

        <div style="background: #f8f9fa; padding: 20px; border-radius: 8px;">

            <h4 style="margin-top:0; color:#2e7d32;">🌟 Alunos Disponíveis (<?php echo $turma->classe; ?>ª Classe)</h4>

            <div style="max-height: 400px; overflow-y: auto;">

                <?php

                // Busca alunos da mesma classe que ainda NÃO estão em NENHUMA turma

                $alunos_disponiveis = $wpdb->get_results($wpdb->prepare("

                    SELECT a.id, a.nome_completo FROM {$wpdb->prefix}sige_alunos a

                    WHERE a.classe_atual = %d AND a.escola_id = %d

                    AND a.id NOT IN (SELECT aluno_id FROM {$wpdb->prefix}sige_matriculas WHERE escola_id = %d AND ano_lectivo = %d AND status_matricula = 'activa')

                ", $turma->classe, sige_get_escola_id(), sige_get_escola_id(), (int)$turma->ano_lectivo));

                if ($alunos_disponiveis) : ?>

                    <table style="width:100%; border-collapse: collapse;">

                        <?php foreach ($alunos_disponiveis as $al) : ?>

                            <tr style="border-bottom: 1px solid #ddd;">

                                <td style="padding: 10px;"><?php echo esc_html($al->nome_completo); ?></td>

                                <td style="text-align: right; padding: 10px;">

                                    <button data-sige-act="alocarAluno" data-sige-arg="<?php echo esc_attr($al->id); ?>" class="sgk-btn sgk-btn-sm" style="background:var(--sg-theme-primary-800,#3b2f8d);border-color:transparent;color:#fff;padding:5px 10px;">+</button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </table>

                <?php else : echo "<p>Nenhum aluno livre encontrado para esta classe.</p>"; endif; ?>

            </div>

        </div>

        <div style="background: var(--sg-theme-soft,#f1edff); padding: 20px; border-radius: 8px; border: 1px solid #c5cae9;">

            <h4 style="margin-top:0; color:var(--sg-theme-primary-800,#3b2f8d);">📋 Alunos nesta Turma</h4>

            <div style="max-height: 400px; overflow-y: auto;">

                <?php

                $alunos_turma = $wpdb->get_results($wpdb->prepare("

                    SELECT m.id as rel_id, a.nome_completo 

                    FROM {$wpdb->prefix}sige_matriculas m

                    JOIN {$wpdb->prefix}sige_alunos a ON m.aluno_id = a.id

                    WHERE m.turma_id = %d AND m.escola_id = %d AND m.status_matricula = 'activa'

                ", $turma_id, sige_get_escola_id()));

                if ($alunos_turma) : ?>

                    <table style="width:100%; border-collapse: collapse;">

                        <?php foreach ($alunos_turma as $at) : ?>

                            <tr style="border-bottom: 1px solid #9fa8da;">

                                <td style="padding: 10px;"><?php echo esc_html($at->nome_completo); ?></td>

                                <td style="text-align: right; padding: 10px;">

                                    <button data-sige-act="removerAluno" data-sige-args="<?php echo esc_attr(wp_json_encode([$at->rel_id, $at->nome_completo])); ?>" class="sgk-btn sgk-btn-perigo sgk-btn-sm" style="padding:5px 10px;">x</button>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </table>

                <?php else : echo "<p>A turma ainda está vazia.</p>"; endif; ?>

            </div>

        </div>

    </div>

</div>

<script <?php echo sige_csp_script_attr(); ?>>

function alocarAluno(alunoId) {

    jQuery.post(ajaxurl, {

        action: 'sige_alocar_aluno',

        aluno_id: alunoId,

        turma_id: <?php echo $turma_id; ?>

    }, function(res) {

        if(res.success) { sigeUi.toast('Aluno enturmado.', 'ok'); setTimeout(function(){ location.reload(); }, 600); }

        else sigeUi.toast(res.data || 'Não foi possível enturmar.', 'erro');

    });

}

async function removerAluno(relId, nome) {

    var ok = await sigeUi.confirm({
        titulo: 'Remover da turma',
        texto: (nome || 'O aluno') + ' sai desta turma. A matrícula mantém-se e o aluno pode ser enturmado novamente.',
        confirmar: 'Remover',
        perigo: true
    });
    if (ok) {

        jQuery.post(ajaxurl, {

            action: 'sige_remover_alocacao',

            rel_id: relId

        }, function(res) {

            if(res.success) { sigeUi.toast((nome || 'Aluno') + ' removido da turma.', 'ok'); setTimeout(function(){ location.reload(); }, 600); }

        });

    }

}

</script>
