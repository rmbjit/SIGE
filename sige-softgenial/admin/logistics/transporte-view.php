<?php
/**
 * Módulo: Gestão de Transportes (Rotas & Preços)
 * Autor: SIGE SoftGenial
 *
 * v12.11.9.11 - Harmonia visual Produto PRO para Transportes.
 * - Mantém a matriz de permissões já validada.
 * - Mantém criação, edição e remoção segura de rotas dentro do escopo da escola actual.
 * - Moderniza a interface no padrão do Painel Principal: hero, cartões KPI, cards arredondados, ícones e tabela premium.
 * - Não altera fórmulas financeiras nem regras de cobrança.
 */

if (!defined('ABSPATH')) exit;

// [FIX R-02 + 12.9.6] Guard de acesso - Logística (matriz SIGE manda).
if (!sige_page_guard(
    ['transporte.ver', 'transporte.rotas_gerir', 'transporte.alunos_gerir'],
    ['sige_director', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente']
)) return;

global $wpdb;

$sige_transport_escola_id = sige_require_escola_id('transporte');
$sige_transport_table     = $wpdb->prefix . 'sige_transporte_rotas';

// v12.11.1 - Permissões granulares: ver transporte não deve permitir alterar rotas.
$sige_transport_can_manage_routes   = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || (function_exists('sige_can') && sige_can('transporte.rotas_gerir'));
$sige_transport_can_manage_students = (function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')) || (function_exists('sige_can') && sige_can('transporte.alunos_gerir'));

if (!function_exists('sige_transport_money_to_float')) {
    /**
     * Normaliza valores monetários introduzidos com ponto ou vírgula decimal.
     */
    function sige_transport_money_to_float($value): float {
        $raw = trim((string) $value);
        if ($raw === '') return 0.0;

        $raw = preg_replace('/[^0-9,\.\-]/', '', $raw);
        if ($raw === '' || $raw === '-' || $raw === ',' || $raw === '.') return 0.0;

        $last_comma = strrpos($raw, ',');
        $last_dot   = strrpos($raw, '.');

        if ($last_comma !== false && $last_dot !== false) {
            // O último separador é tratado como decimal; os anteriores são milhares.
            if ($last_comma > $last_dot) {
                $raw = str_replace('.', '', $raw);
                $raw = str_replace(',', '.', $raw);
            } else {
                $raw = str_replace(',', '', $raw);
            }
        } elseif ($last_comma !== false) {
            $raw = str_replace(',', '.', $raw);
        }

        return round((float) $raw, 2);
    }
}

if (!function_exists('sige_transport_rota_post_data')) {
    /**
     * Sanitiza os dados da rota antes de inserir/actualizar.
     */
    function sige_transport_rota_post_data(): array {
        return [
            'nome_rota'          => sanitize_text_field(wp_unslash($_POST['nome_rota'] ?? '')),
            'area_abrangencia'   => sanitize_textarea_field(wp_unslash($_POST['area'] ?? '')),
            'preco_mensal'       => sige_transport_money_to_float(wp_unslash($_POST['preco'] ?? '0')),
            'capacidade_maxima'  => max(0, (int) ($_POST['capacidade'] ?? 0)),
            'motorista'          => sanitize_text_field(wp_unslash($_POST['motorista'] ?? '')),
            'matricula_carro'    => sanitize_text_field(wp_unslash($_POST['matricula'] ?? '')),
            'ativo'              => 1,
            'activo'             => 1,
        ];
    }
}

if (!function_exists('sige_transport_form_value')) {
    function sige_transport_form_value($rota, string $field, $default = '') {
        return ($rota && isset($rota->{$field})) ? $rota->{$field} : $default;
    }
}

if (!function_exists('sige_transport_icon')) {
    function sige_transport_icon(string $name): string {
        $name = sanitize_key($name ?: 'grid');
        $custom = [
            'trash' => '<svg class="sg-svg-icon sg-svg-icon-trash" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 6h18"/><path d="M8 6V4h8v2"/><path d="M6 6l1 15h10l1-15"/><path d="M10 11v6"/><path d="M14 11v6"/></svg>',
            'x'     => '<svg class="sg-svg-icon sg-svg-icon-x" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 6 6 18"/><path d="m6 6 12 12"/></svg>',
            'user'  => '<svg class="sg-svg-icon sg-svg-icon-user" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 21a8 8 0 0 0-16 0"/><circle cx="12" cy="7" r="4"/></svg>',
            'map'   => '<svg class="sg-svg-icon sg-svg-icon-map" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 18 3 21V6l6-3 6 3 6-3v15l-6 3-6-3Z"/><path d="M9 3v15"/><path d="M15 6v15"/></svg>',
            'list'  => '<svg class="sg-svg-icon sg-svg-icon-list" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M8 6h13"/><path d="M8 12h13"/><path d="M8 18h13"/><path d="M3 6h.01"/><path d="M3 12h.01"/><path d="M3 18h.01"/></svg>',
            'alert' => '<svg class="sg-svg-icon sg-svg-icon-alert" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>',
        ];
        if (isset($custom[$name])) {
            return $custom[$name];
        }
        return function_exists('sige_ui_icon') ? sige_ui_icon($name) : '';
    }
}

// 1. PROCESSAR FORMULÁRIO (Criar/Editar Rota)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['sige_rota_nonce'])) {
    if (!$sige_transport_can_manage_routes) {
        echo '<div class="notice notice-error"><p>Sem permissão para criar ou alterar rotas de transporte.</p></div>';
    } elseif (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['sige_rota_nonce'])), 'salvar_rota')) {
        echo '<div class="notice notice-error"><p>Pedido inválido. Recarregue a página e tente novamente.</p></div>';
    } else {
        $rota_id = isset($_POST['rota_id']) ? absint($_POST['rota_id']) : 0;
        $dados   = sige_transport_rota_post_data();

        if ($dados['nome_rota'] === '') {
            echo '<div class="notice notice-error"><p>Informe o nome da rota.</p></div>';
        } elseif ($rota_id > 0) {
            // Edição protegida por escola_id para evitar alteração fora do tenant actual.
            $actualizou = $wpdb->update(
                $sige_transport_table,
                $dados,
                ['id' => $rota_id, 'escola_id' => $sige_transport_escola_id],
                ['%s', '%s', '%f', '%d', '%s', '%s', '%d', '%d'],
                ['%d', '%d']
            );

            if ($actualizou !== false) {
                if (function_exists('sige_log')) {
                    sige_log('rota_transporte_editada', 'transporte', ['rota_id' => $rota_id]);
                }
                echo '<div class="notice notice-success is-dismissible"><p>✅ Rota actualizada com sucesso!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Erro ao actualizar rota. Contacte o administrador.</p></div>';
            }
        } else {
            $dados['escola_id'] = $sige_transport_escola_id;
            $inseriu = $wpdb->insert(
                $sige_transport_table,
                $dados,
                ['%s', '%s', '%f', '%d', '%s', '%s', '%d', '%d', '%d']
            );

            if ($inseriu) {
                if (function_exists('sige_log')) {
                    sige_log('rota_transporte_criada', 'transporte', ['rota_id' => (int) $wpdb->insert_id]);
                }
                echo '<div class="notice notice-success is-dismissible"><p>✅ Rota criada com sucesso!</p></div>';
            } else {
                echo '<div class="notice notice-error"><p>❌ Erro ao criar rota. Contacte o administrador.</p></div>';
            }
        }
    }
}

// 2. AÇÕES (Excluir)
if (isset($_GET['del_rota'])) {
    // [AUTH-01] Nonce obrigatório para eliminação de rota
    if (!$sige_transport_can_manage_routes) {
        echo '<div class="notice notice-error" style="padding:12px;border-radius:8px;margin:12px 0;"><p>Sem permissão para remover rotas de transporte.</p></div>';
    } elseif (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'sige_del_rota')) {
        echo '<div class="notice notice-error" style="padding:12px;border-radius:8px;margin:12px 0;"><p>A sessão expirou por segurança. Recarregue a página e tente novamente.</p></div>';
    } else {
        $id = absint($_GET['del_rota']);
        $wpdb->delete($sige_transport_table, ['id' => $id, 'escola_id' => $sige_transport_escola_id], ['%d', '%d']);

        if (function_exists('sige_log')) {
            sige_log('rota_transporte_removida', 'transporte', ['rota_id' => $id]);
        }

        echo '<script ' . sige_csp_script_attr() . '>window.location.href="?page=sige-app&view=transporte";</script>';
    }
}

// 3. PREPARAR EDIÇÃO
$rota_em_edicao = null;
$edit_rota_id   = isset($_GET['edit_rota']) ? absint($_GET['edit_rota']) : 0;

if ($edit_rota_id > 0) {
    if (!$sige_transport_can_manage_routes) {
        echo '<div class="notice notice-error"><p>Sem permissão para editar rotas de transporte.</p></div>';
    } else {
        $rota_em_edicao = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM {$sige_transport_table} WHERE id = %d AND escola_id = %d AND ativo = 1 LIMIT 1",
            $edit_rota_id,
            $sige_transport_escola_id
        ));

        if (!$rota_em_edicao) {
            echo '<div class="notice notice-warning"><p>Rota não encontrada ou indisponível para edição.</p></div>';
        }
    }
}

// 4. LISTAR DADOS
$rotas = $wpdb->get_results($wpdb->prepare(
    "SELECT * FROM {$sige_transport_table} WHERE escola_id = %d AND ativo = 1 ORDER BY nome_rota ASC",
    $sige_transport_escola_id
));

$base_url = admin_url('admin.php?page=sige-app&view=transporte');

// 5. INDICADORES VISUAIS - leitura operacional; não altera regras financeiras.
$total_rotas             = is_array($rotas) ? count($rotas) : 0;
$total_capacidade        = 0;
$total_alunos_transporte = 0;
$total_receita_potencial = 0.0;
$rotas_no_limite         = 0;
$rotas_sem_motorista     = 0;
$rota_cards              = [];

foreach ((array) $rotas as $r) {
    $qtd = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->prefix}sige_alunos WHERE rota_transporte_id = %d AND escola_id = %d",
        (int) $r->id,
        $sige_transport_escola_id
    ));

    $lotacao = max(0, (int) $r->capacidade_maxima);
    $preco   = (float) $r->preco_mensal;
    $pct     = $lotacao > 0 ? min(100, round(($qtd / max(1, $lotacao)) * 100)) : 0;
    $estado  = 'Disponível';

    if ($lotacao > 0 && $qtd >= $lotacao) {
        $estado = 'No limite';
        $rotas_no_limite++;
    } elseif ($qtd > 0) {
        $estado = 'Em uso';
    }

    if (empty($r->motorista)) {
        $rotas_sem_motorista++;
    }

    $total_capacidade        += $lotacao;
    $total_alunos_transporte += $qtd;
    $total_receita_potencial += ($preco * $qtd);

    $rota_cards[] = [
        'row'     => $r,
        'alunos'  => $qtd,
        'lotacao' => $lotacao,
        'pct'     => $pct,
        'estado'  => $estado,
    ];
}

$ocupacao_media = $total_capacidade > 0 ? min(100, round(($total_alunos_transporte / max(1, $total_capacidade)) * 100)) : 0;
?>

<style id="sg-transporte-v12-11-9-11">
/* ============================================================================
   SoftGenial Produto PRO - Transportes / Harmonia visual com Painel Principal
   Escopo: apresentação visual. Não altera cobranças, pagamentos ou fórmulas.
   ============================================================================ */
body.sige-view-transporte .sg-product-page-head{display:none!important;}
.sg-transport-v2{--sg-purple:var(--color-brand-500);--sg-purple-dark:var(--color-brand-700);--sg-purple-soft:var(--color-brand-50);--sg-ink:var(--color-ink-500);--sg-muted:var(--color-slate-500);--sg-line:var(--color-ink-100);--sg-bg:var(--color-ink-50);--sg-green:var(--color-success-700);--sg-red:var(--color-danger-500);--sg-amber:var(--color-warning-500);--sg-blue:var(--color-info-400);font-family:'Poppins','Inter','Segoe UI',system-ui,sans-serif;color:var(--sg-ink);}
.sg-transport-v2 *{box-sizing:border-box;}
.sg-transport-shell{display:flex;flex-direction:column;gap:var(--space-5);}
.sg-transport-hero{position:relative;overflow:hidden;min-height:178px;border-radius:var(--radius-xl);background:linear-gradient(110deg,var(--color-white) 0%,var(--color-white) 46%,var(--color-brand-100) 100%);border:1px solid rgba(92,64,187,.12);box-shadow:var(--shadow-lg);padding:32px 34px;display:grid;grid-template-columns:minmax(0,1.06fr) minmax(320px,.94fr);gap:22px;align-items:center;}
.sg-transport-hero:before{content:"";position:absolute;inset:auto -80px -130px auto;width:420px;height:300px;background:radial-gradient(circle,rgba(109,93,252,.18),rgba(109,93,252,0) 67%);pointer-events:none;}
.sg-transport-kicker{display:flex;align-items:center;gap:var(--space-2);font-size:12px;font-weight:700;letter-spacing:.11em;text-transform:uppercase;color:var(--sg-purple);margin-bottom:10px;}
.sg-transport-kicker svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-transport-title{margin:0;font-size:31px;line-height:1.08;font-weight:700;letter-spacing:-.04em;color:var(--color-black);}
.sg-transport-subtitle{max-width:720px;margin:var(--space-3) 0 0;font-size:15px;line-height:1.65;color:var(--color-slate-700);font-weight:500;}
.sg-transport-actions{display:flex;flex-wrap:wrap;gap:var(--space-3);margin-top:24px;}
.sg-transport-btn{min-height:46px;display:inline-flex;align-items:center;justify-content:center;gap:10px;border-radius:var(--radius-md);padding:0 22px;font-size:var(--fs-base);font-weight:700;text-decoration:none;border:1px solid transparent;transition:transform .18s ease,box-shadow .18s ease,border-color .18s ease;background:var(--color-white);color:var(--color-slate-900);cursor:pointer;}
.sg-transport-btn svg{width:18px!important;height:18px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-transport-btn-primary{background:linear-gradient(135deg,var(--color-brand-400),var(--color-brand-600));color:var(--color-white)!important;box-shadow:var(--shadow-md);}
.sg-transport-btn-secondary{background:var(--color-white);color:var(--color-ink-900)!important;border-color:var(--color-ink-100);box-shadow:var(--shadow-sm);}
.sg-transport-btn-soft{background:var(--color-brand-50);color:var(--sg-purple)!important;border-color:var(--color-brand-100);}
.sg-transport-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);}
.sg-transport-hero-art{position:relative;min-height:150px;border-radius:var(--radius-xl);background:linear-gradient(135deg,rgba(109,93,252,.08),rgba(109,93,252,.18));overflow:hidden;}
.sg-bus-scene{position:absolute;inset:0;color:var(--color-brand-300);}
.sg-bus-road{position:absolute;left:32px;right:28px;bottom:33px;height:10px;border-radius:var(--radius-pill);background:rgba(109,93,252,.18);box-shadow:var(--shadow-xs);}
.sg-bus-body{position:absolute;right:58px;bottom:48px;width:238px;height:76px;border-radius:22px 28px 18px 18px;background:rgba(109,93,252,.34);box-shadow:inset 0 0 0 2px rgba(109,93,252,.20),0 18px 34px rgba(70,52,170,.10);}
.sg-bus-body:before{content:"";position:absolute;left:22px;top:15px;width:44px;height:24px;border-radius:var(--radius-sm);background:rgba(255,255,255,.58);box-shadow:var(--shadow-lg);}
.sg-bus-body:after{content:"";position:absolute;right:18px;top:16px;width:34px;height:28px;border-radius:8px 16px 8px 8px;background:rgba(255,255,255,.55);}
.sg-bus-wheel{position:absolute;bottom:-15px;width:34px;height:34px;border-radius:50%;background:var(--color-white);border:8px solid rgba(109,93,252,.62);box-shadow:var(--shadow-sm);}
.sg-bus-wheel.one{left:42px}.sg-bus-wheel.two{right:44px}
.sg-bus-pin{position:absolute;left:52px;top:35px;width:58px;height:58px;border-radius:50% 50% 50% 10px;transform:rotate(-45deg);background:rgba(109,93,252,.18);border:2px solid rgba(109,93,252,.22);}
.sg-bus-pin:after{content:"";position:absolute;left:16px;top:16px;width:22px;height:22px;border-radius:50%;background:rgba(255,255,255,.62);}
.sg-bus-cloud{position:absolute;width:86px;height:28px;border-radius:var(--radius-pill);background:rgba(255,255,255,.58);right:270px;top:28px;box-shadow:var(--shadow-xs);opacity:.70;}
.sg-transport-kpi-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:var(--space-4);}
.sg-transport-kpi{position:relative;overflow:hidden;display:grid;grid-template-columns:auto minmax(0,1fr);gap:var(--space-4);align-items:center;min-height:104px;padding:18px 20px;border-radius:var(--radius-xl);background:var(--color-white);border:1px solid rgba(28,32,54,.08);box-shadow:var(--shadow-md);}
.sg-transport-kpi:after{content:"";position:absolute;right:-28px;top:-34px;width:92px;height:92px;border-radius:50%;background:var(--kpi-soft,var(--color-brand-50));}
.sg-transport-kpi-icon{width:52px;height:52px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--kpi-soft,var(--color-brand-50));color:var(--kpi-color,var(--sg-purple));position:relative;z-index:1;}
.sg-transport-kpi-icon svg{width:24px!important;height:24px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-transport-kpi-label{font-size:var(--fs-sm);font-weight:600;color:var(--color-slate-600);margin-bottom:6px;}
.sg-transport-kpi-value{font-size:27px;line-height:1;font-weight:700;letter-spacing:-.03em;color:var(--color-black);}
.sg-transport-kpi-note{margin-top:7px;font-size:12px;font-weight:600;color:var(--color-ink-400);}
.sg-transport-main-grid{display:grid;grid-template-columns:minmax(320px,.85fr) minmax(0,1.35fr);gap:18px;align-items:start;}
.sg-transport-card{background:var(--color-white);border:1px solid rgba(30,34,60,.08);border-radius:var(--radius-xl);box-shadow:var(--shadow-md);overflow:hidden;min-width:0;}
.sg-transport-card-head{display:flex;align-items:center;justify-content:space-between;gap:var(--space-4);padding:20px 22px 14px;}
.sg-transport-card-title{display:flex;align-items:center;gap:var(--space-3);min-width:0;}
.sg-transport-card-icon{width:40px;height:40px;border-radius:var(--radius-md);background:var(--icon-bg,var(--color-brand-50));color:var(--icon-color,var(--sg-purple));display:flex;align-items:center;justify-content:center;flex:0 0 auto;}
.sg-transport-card-icon svg{width:20px!important;height:20px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-transport-card-title h3{margin:0;font-size:17px;line-height:1.1;font-weight:700;letter-spacing:-.03em;color:var(--color-ink-500);}
.sg-transport-card-title p{margin:5px 0 0;font-size:12px;font-weight:600;color:var(--color-ink-400);}
.sg-transport-card-body{padding:10px 22px 22px;}
.sg-edit-alert{display:grid;grid-template-columns:auto 1fr;gap:10px;align-items:flex-start;padding:13px 14px;border-radius:var(--radius-lg);background:var(--color-warning-100);border:1px solid var(--color-warning-200);color:var(--color-warning-800);margin:0 0 var(--space-4);font-size:12px;line-height:1.45;font-weight:600;}
.sg-form-group{margin-bottom:15px;}
.sg-form-grid-2{display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);}
.sg-form-grid-driver{display:grid;grid-template-columns:minmax(0,1fr) 118px;gap:var(--space-3);}
.sg-form-label{display:flex;align-items:center;gap:7px;margin-bottom:7px;font-size:12px;font-weight:700;color:var(--color-ink-800);}
.sg-form-label svg{width:15px!important;height:15px!important;color:var(--sg-purple);stroke:currentColor!important;fill:none!important;}
.sg-transport-input,.sg-transport-textarea{width:100%!important;border:1px solid var(--color-info-100)!important;border-radius:var(--radius-md)!important;background:var(--color-white)!important;box-shadow:none!important;font-size:var(--fs-sm)!important;font-weight:600!important;color:var(--color-ink-900)!important;transition:border-color .16s ease,box-shadow .16s ease!important;}
.sg-transport-input{min-height:44px!important;padding:0 13px!important;}
.sg-transport-textarea{min-height:78px!important;padding:12px 13px!important;resize:vertical;}
.sg-transport-input:focus,.sg-transport-textarea:focus{border-color:var(--color-brand-200)!important;box-shadow:var(--shadow-xs);outline:none!important;}
.sg-form-divider{height:1px;background:linear-gradient(90deg,transparent,var(--color-slate-100),transparent);margin:18px 0;}
.sg-form-actions{display:flex;flex-direction:column;gap:9px;margin-top:18px;}
.sg-form-actions .sg-transport-btn{width:100%;border:0;}
.sg-permission-box{padding:15px;border-radius:var(--radius-lg);background:var(--color-warning-50);border:1px solid var(--color-warning-200);color:var(--color-warning-800);font-weight:600;line-height:1.45;}
.sg-routes-toolbar{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:0 0 var(--space-4);}
.sg-routes-summary{display:flex;flex-wrap:wrap;gap:var(--space-2);}
.sg-chip{display:inline-flex;align-items:center;gap:7px;min-height:32px;padding:0 11px;border-radius:var(--radius-pill);font-size:12px;font-weight:700;background:var(--color-brand-50);color:var(--sg-purple);border:1px solid var(--color-brand-100);white-space:nowrap;}
.sg-chip.green{background:var(--color-success-50);color:var(--color-success-800);border-color:var(--color-success-200);}.sg-chip.amber{background:var(--color-warning-100);color:var(--color-warning-800);border-color:var(--color-warning-200);}.sg-chip.blue{background:var(--color-info-50);color:var(--color-info-500);border-color:var(--color-info-100);}
.sg-route-list{display:flex;flex-direction:column;gap:var(--space-3);}
.sg-route-card{display:grid;grid-template-columns:minmax(0,1.35fr) minmax(120px,.45fr) minmax(150px,.55fr) minmax(120px,.45fr) auto;gap:14px;align-items:center;padding:15px;border-radius:var(--radius-lg);background:var(--color-white);border:1px solid var(--color-slate-100);box-shadow:var(--shadow-sm);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease;}
.sg-route-card:hover{transform:translateY(-1px);box-shadow:var(--shadow-md);border-color:var(--color-info-100);}
.sg-route-name{display:flex;align-items:flex-start;gap:var(--space-3);min-width:0;}
.sg-route-avatar{width:42px;height:42px;border-radius:var(--radius-lg);display:flex;align-items:center;justify-content:center;background:var(--color-brand-50);color:var(--sg-purple);flex:0 0 auto;}
.sg-route-avatar svg{width:21px!important;height:21px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-route-copy{min-width:0;}
.sg-route-copy strong{display:block;font-size:var(--fs-base);font-weight:700;color:var(--color-ink-500);line-height:1.25;}
.sg-route-copy small{display:block;margin-top:4px;font-size:12px;line-height:1.35;color:var(--color-ink-400);font-weight:600;overflow-wrap:anywhere;}
.sg-route-money span,.sg-route-driver span,.sg-route-occupancy span{display:block;font-size:var(--fs-xs);font-weight:700;text-transform:uppercase;letter-spacing:.04em;color:var(--color-ink-400);margin-bottom:5px;}
.sg-route-money strong{font-size:15px;font-weight:700;color:var(--color-success-500);}
.sg-route-driver strong{display:block;font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-800);line-height:1.25;}
.sg-route-driver small{display:block;margin-top:3px;font-size:12px;font-weight:600;color:var(--color-ink-400);}
.sg-occupancy-line{display:flex;align-items:center;justify-content:space-between;gap:var(--space-2);margin-bottom:6px;}
.sg-occupancy-line strong{font-size:var(--fs-sm);font-weight:700;color:var(--color-ink-800);}
.sg-progress-mini{height:8px;border-radius:var(--radius-pill);background:var(--color-slate-100);overflow:hidden;}
.sg-progress-mini i{display:block;height:100%;width:var(--p,0%);border-radius:inherit;background:linear-gradient(90deg,var(--bar,var(--color-brand-400)),rgba(109,93,252,.45));}
.sg-status-pill{display:inline-flex;align-items:center;justify-content:center;min-height:27px;padding:0 9px;border-radius:var(--radius-pill);background:var(--status-bg,var(--color-success-50));color:var(--status-color,var(--color-success-800));font-size:var(--fs-xs);font-weight:700;white-space:nowrap;}
.sg-route-actions{display:flex;align-items:center;justify-content:flex-end;gap:var(--space-2);}
.sg-action-btn{width:36px;height:36px;border-radius:var(--radius-md);display:inline-flex;align-items:center;justify-content:center;text-decoration:none;border:1px solid var(--color-slate-100);background:var(--color-white);color:var(--color-slate-500);transition:transform .16s ease,box-shadow .16s ease,border-color .16s ease,color .16s ease;}
.sg-action-btn svg{width:17px!important;height:17px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;opacity:1!important;}
.sg-action-btn.edit{color:var(--color-brand-500);background:var(--color-brand-50);border-color:var(--color-brand-100);}.sg-action-btn.del{color:var(--color-danger-600);background:var(--color-danger-50);border-color:var(--color-danger-100);}
.sg-action-btn:hover{transform:translateY(-1px);box-shadow:var(--shadow-sm);}
.sg-consulta-label{display:inline-flex;align-items:center;min-height:30px;padding:0 10px;border-radius:var(--radius-pill);background:var(--color-slate-50);border:1px solid var(--color-slate-100);color:var(--color-slate-400);font-size:12px;font-weight:700;}
.sg-empty-state{display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;padding:42px 18px;border-radius:var(--radius-lg);background:linear-gradient(180deg,var(--color-white),var(--color-white));border:1px dashed var(--color-info-100);color:var(--color-slate-500);}
.sg-empty-state-icon{width:56px;height:56px;border-radius:var(--radius-lg);background:var(--color-brand-50);color:var(--sg-purple);display:flex;align-items:center;justify-content:center;}
.sg-empty-state-icon svg{width:26px!important;height:26px!important;stroke:currentColor!important;color:currentColor!important;fill:none!important;}
.sg-empty-state h3{margin:var(--space-1) 0 0;font-size:18px;font-weight:700;color:var(--color-ink-500);}.sg-empty-state p{margin:0;max-width:420px;font-size:var(--fs-sm);line-height:1.55;font-weight:600;}
@media (max-width:1500px){.sg-route-card{grid-template-columns:minmax(0,1fr) 140px 160px;}.sg-route-occupancy,.sg-route-actions{grid-column:auto;}.sg-route-actions{justify-content:flex-start;}.sg-transport-hero{grid-template-columns:1fr;}.sg-transport-hero-art{display:none;}}
@media (max-width:1180px){.sg-transport-kpi-grid{grid-template-columns:repeat(2,minmax(0,1fr));}.sg-transport-main-grid{grid-template-columns:1fr;}.sg-route-card{grid-template-columns:1fr 1fr;}.sg-route-name{grid-column:1/-1;}.sg-route-actions{justify-content:flex-end;}}
@media (max-width:720px){.sg-transport-hero{padding:var(--space-6) var(--space-5);}.sg-transport-title{font-size:var(--fs-xl);}.sg-transport-kpi-grid{grid-template-columns:1fr;}.sg-route-card{grid-template-columns:1fr;}.sg-route-actions{justify-content:flex-start;}.sg-form-grid-2,.sg-form-grid-driver{grid-template-columns:1fr;}.sg-routes-toolbar{align-items:flex-start;flex-direction:column;}}
</style>

<div class="sg-transport-v2">
    <div class="sg-transport-shell">
        <section class="sg-transport-hero" aria-label="Resumo do transporte escolar">
            <div>
                <div class="sg-transport-kicker"><?php echo sige_transport_icon('truck'); ?> Operação Escolar</div>
                <h1 class="sg-transport-title">Transporte Escolar</h1>
                <p class="sg-transport-subtitle">Organize rotas, preços, lotação, motorista e viatura num ambiente visual alinhado ao Painel Principal do SoftGenial.</p>
                <div class="sg-transport-actions">
                    <?php if ($sige_transport_can_manage_routes): ?>
                        <a href="#sg-transporte-form" class="sg-transport-btn sg-transport-btn-primary"><?php echo sige_transport_icon($rota_em_edicao ? 'edit' : 'plus'); ?> <?php echo $rota_em_edicao ? 'Actualizar rota' : 'Nova rota'; ?></a>
                    <?php endif; ?>
                    <span class="sg-transport-btn sg-transport-btn-secondary"><?php echo sige_transport_icon('shield'); ?> Escopo da escola actual</span>
                    <span class="sg-transport-btn sg-transport-btn-soft"><?php echo sige_transport_icon('check'); ?> <?php echo esc_html($ocupacao_media); ?>% de ocupação</span>
                </div>
            </div>
            <div class="sg-transport-hero-art" aria-hidden="true">
                <div class="sg-bus-scene">
                    <div class="sg-bus-cloud"></div><div class="sg-bus-pin"></div><div class="sg-bus-body"><div class="sg-bus-wheel one"></div><div class="sg-bus-wheel two"></div></div><div class="sg-bus-road"></div>
                </div>
            </div>
        </section>

        <section class="sg-transport-kpi-grid" aria-label="Indicadores do transporte">
            <article class="sg-transport-kpi" style="--kpi-soft:var(--color-brand-50);--kpi-color:var(--color-brand-400);">
                <div class="sg-transport-kpi-icon"><?php echo sige_transport_icon('truck'); ?></div>
                <div><div class="sg-transport-kpi-label">Rotas activas</div><div class="sg-transport-kpi-value"><?php echo esc_html($total_rotas); ?></div><div class="sg-transport-kpi-note">Zonas configuradas</div></div>
            </article>
            <article class="sg-transport-kpi" style="--kpi-soft:var(--color-success-50);--kpi-color:var(--color-success-500);">
                <div class="sg-transport-kpi-icon"><?php echo sige_transport_icon('users'); ?></div>
                <div><div class="sg-transport-kpi-label">Alunos transportados</div><div class="sg-transport-kpi-value"><?php echo esc_html($total_alunos_transporte); ?></div><div class="sg-transport-kpi-note"><?php echo esc_html($total_capacidade); ?> lugares registados</div></div>
            </article>
            <article class="sg-transport-kpi" style="--kpi-soft:var(--color-info-50);--kpi-color:var(--color-info-500);">
                <div class="sg-transport-kpi-icon"><?php echo sige_transport_icon('chart'); ?></div>
                <div><div class="sg-transport-kpi-label">Ocupação média</div><div class="sg-transport-kpi-value"><?php echo esc_html($ocupacao_media); ?>%</div><div class="sg-transport-kpi-note"><?php echo esc_html($rotas_no_limite); ?> rota(s) no limite</div></div>
            </article>
            <article class="sg-transport-kpi" style="--kpi-soft:var(--color-warning-50);--kpi-color:var(--color-warning-500);">
                <div class="sg-transport-kpi-icon"><?php echo sige_transport_icon('wallet'); ?></div>
                <div><div class="sg-transport-kpi-label">Potencial mensal</div><div class="sg-transport-kpi-value"><?php echo esc_html(number_format($total_receita_potencial, 0, ',', '.')); ?></div><div class="sg-transport-kpi-note">MT, com alunos actuais</div></div>
            </article>
        </section>

        <section class="sg-transport-main-grid" aria-label="Gestão de rotas">
            <article class="sg-transport-card" id="sg-transporte-form">
                <div class="sg-transport-card-head">
                    <div class="sg-transport-card-title">
                        <div class="sg-transport-card-icon" style="--icon-bg:var(--color-brand-50);--icon-color:var(--color-brand-400);"><?php echo sige_transport_icon($rota_em_edicao ? 'edit' : 'plus'); ?></div>
                        <div>
                            <h3><?php echo $rota_em_edicao ? 'Editar Rota' : 'Nova Rota'; ?></h3>
                            <p><?php echo $rota_em_edicao ? 'Actualize os dados da rota seleccionada' : 'Crie uma rota com preço, lotação e viatura'; ?></p>
                        </div>
                    </div>
                </div>
                <div class="sg-transport-card-body">
                    <?php if ($rota_em_edicao): ?>
                        <div class="sg-edit-alert">
                            <span><?php echo sige_transport_icon('shield'); ?></span>
                            <span>Está a editar <strong><?php echo esc_html($rota_em_edicao->nome_rota); ?></strong>. As alterações serão aplicadas apenas à escola actual.</span>
                        </div>
                    <?php endif; ?>

                    <?php if ($sige_transport_can_manage_routes): ?>
                    <form method="post" action="<?php echo esc_url($base_url); ?>">
                        <?php wp_nonce_field('salvar_rota', 'sige_rota_nonce'); ?>
                        <?php if ($rota_em_edicao): ?>
                            <input type="hidden" name="rota_id" value="<?php echo esc_attr((int) $rota_em_edicao->id); ?>">
                        <?php endif; ?>

                        <div class="sg-form-group">
                            <label class="sg-form-label"><?php echo sige_transport_icon('pin'); ?> Nome da Rota *</label>
                            <input class="sg-transport-input" type="text" name="nome_rota" required placeholder="Ex: Rota Matola Rio" value="<?php echo esc_attr(sige_transport_form_value($rota_em_edicao, 'nome_rota')); ?>">
                        </div>

                        <div class="sg-form-group">
                            <label class="sg-form-label"><?php echo sige_transport_icon('map'); ?> Bairros / Abrangência</label>
                            <textarea class="sg-transport-textarea" name="area" rows="2" placeholder="Ex: Matola Gare, Tchumene, Novare..."><?php echo esc_textarea(sige_transport_form_value($rota_em_edicao, 'area_abrangencia')); ?></textarea>
                        </div>

                        <div class="sg-form-grid-2">
                            <div class="sg-form-group">
                                <label class="sg-form-label"><?php echo sige_transport_icon('money'); ?> Mensalidade (MT) *</label>
                                <input class="sg-transport-input" type="number" step="0.01" min="0" name="preco" required placeholder="0.00" value="<?php echo esc_attr($rota_em_edicao ? number_format((float) $rota_em_edicao->preco_mensal, 2, '.', '') : ''); ?>">
                            </div>

                            <div class="sg-form-group">
                                <label class="sg-form-label"><?php echo sige_transport_icon('users'); ?> Lotação Max.</label>
                                <input class="sg-transport-input" type="number" min="0" name="capacidade" value="<?php echo esc_attr((int) sige_transport_form_value($rota_em_edicao, 'capacidade_maxima', 15)); ?>">
                            </div>
                        </div>

                        <div class="sg-form-divider"></div>

                        <div class="sg-form-grid-driver">
                            <div class="sg-form-group">
                                <label class="sg-form-label"><?php echo sige_transport_icon('user'); ?> Motorista</label>
                                <input class="sg-transport-input" type="text" name="motorista" placeholder="Sr. João" value="<?php echo esc_attr(sige_transport_form_value($rota_em_edicao, 'motorista')); ?>">
                            </div>

                            <div class="sg-form-group">
                                <label class="sg-form-label"><?php echo sige_transport_icon('truck'); ?> Matrícula</label>
                                <input class="sg-transport-input" type="text" name="matricula" placeholder="ABC-123" value="<?php echo esc_attr(sige_transport_form_value($rota_em_edicao, 'matricula_carro')); ?>">
                            </div>
                        </div>

                        <div class="sg-form-actions">
                            <button type="submit" class="sg-transport-btn sg-transport-btn-primary"><?php echo sige_transport_icon('check'); ?> <?php echo $rota_em_edicao ? 'Actualizar Rota' : 'Gravar Rota'; ?></button>
                            <?php if ($rota_em_edicao): ?>
                                <a href="<?php echo esc_url($base_url); ?>" class="sg-transport-btn sg-transport-btn-secondary"><?php echo sige_transport_icon('x'); ?> Cancelar edição</a>
                            <?php endif; ?>
                        </div>
                    </form>
                    <?php else: ?>
                        <div class="sg-permission-box">Tem permissão para consultar o transporte, mas não para criar ou alterar rotas.</div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="sg-transport-card">
                <div class="sg-transport-card-head">
                    <div class="sg-transport-card-title">
                        <div class="sg-transport-card-icon" style="--icon-bg:var(--color-success-50);--icon-color:var(--color-success-500);"><?php echo sige_transport_icon('list'); ?></div>
                        <div>
                            <h3>Rotas Activas</h3>
                            <p>Preços, viaturas, lotação e acções disponíveis</p>
                        </div>
                    </div>
                </div>
                <div class="sg-transport-card-body">
                    <div class="sg-routes-toolbar">
                        <div class="sg-routes-summary">
                            <span class="sg-chip"><?php echo sige_transport_icon('truck'); ?> <?php echo esc_html($total_rotas); ?> rota(s)</span>
                            <span class="sg-chip green"><?php echo sige_transport_icon('users'); ?> <?php echo esc_html($total_alunos_transporte); ?> aluno(s)</span>
                            <span class="sg-chip blue"><?php echo sige_transport_icon('chart'); ?> <?php echo esc_html($ocupacao_media); ?>% ocupação</span>
                            <?php if ($rotas_sem_motorista > 0): ?><span class="sg-chip amber"><?php echo sige_transport_icon('alert'); ?> <?php echo esc_html($rotas_sem_motorista); ?> sem motorista</span><?php endif; ?>
                        </div>
                    </div>

                    <?php if (!empty($rota_cards)): ?>
                        <div class="sg-route-list">
                            <?php foreach ($rota_cards as $item):
                                $r       = $item['row'];
                                $qtd     = (int) $item['alunos'];
                                $lotacao = (int) $item['lotacao'];
                                $pct     = (int) $item['pct'];
                                $estado  = (string) $item['estado'];
                                $status_bg = 'var(--color-success-50)';
                                $status_color = 'var(--color-success-800)';
                                $bar_color = 'var(--color-success-400)';
                                if ($estado === 'No limite') { $status_bg = 'var(--color-danger-50)'; $status_color = 'var(--color-danger-600)'; $bar_color = 'var(--color-danger-500)'; }
                                elseif ($estado === 'Em uso') { $status_bg = 'var(--color-info-50)'; $status_color = 'var(--color-info-500)'; $bar_color = 'var(--color-info-400)'; }
                            ?>
                                <div class="sg-route-card">
                                    <div class="sg-route-name">
                                        <div class="sg-route-avatar"><?php echo sige_transport_icon('truck'); ?></div>
                                        <div class="sg-route-copy">
                                            <strong><?php echo esc_html($r->nome_rota); ?></strong>
                                            <small><?php echo esc_html($r->area_abrangencia ?: 'Abrangência ainda não informada.'); ?></small>
                                        </div>
                                    </div>

                                    <div class="sg-route-money">
                                        <span>Mensalidade</span>
                                        <strong><?php echo esc_html(number_format((float) $r->preco_mensal, 2, ',', '.')); ?> MT</strong>
                                    </div>

                                    <div class="sg-route-driver">
                                        <span>Viatura</span>
                                        <strong><?php echo esc_html($r->motorista ?: 'Motorista N/D'); ?></strong>
                                        <small><?php echo esc_html($r->matricula_carro ?: 'Matrícula N/D'); ?></small>
                                    </div>

                                    <div class="sg-route-occupancy">
                                        <div class="sg-occupancy-line"><span>Lotação</span><strong><?php echo esc_html($qtd); ?> / <?php echo esc_html($lotacao); ?></strong></div>
                                        <div class="sg-progress-mini" style="--p:<?php echo esc_attr($pct); ?>%;--bar:<?php echo esc_attr($bar_color); ?>;"><i></i></div>
                                        <div style="margin-top:7px;"><span class="sg-status-pill" style="--status-bg:<?php echo esc_attr($status_bg); ?>;--status-color:<?php echo esc_attr($status_color); ?>;"><?php echo esc_html($estado); ?></span></div>
                                    </div>

                                    <div class="sg-route-actions">
                                        <?php if ($sige_transport_can_manage_routes): ?>
                                            <a class="sg-action-btn edit" href="<?php echo esc_url(add_query_arg('edit_rota', (int) $r->id, $base_url)); ?>" title="Editar rota"><?php echo sige_transport_icon('edit'); ?></a>
                                            <a class="sg-action-btn del" href="<?php echo esc_url(wp_nonce_url(add_query_arg('del_rota', (int) $r->id, $base_url), 'sige_del_rota')); ?>" data-sige-confirm="Esta rota será removida. Os alunos associados deixam de a ter atribuída." data-sige-titulo="Remover rota" data-sige-confirmar="Remover" data-sige-perigo="1" title="Remover rota"><?php echo sige_transport_icon('trash'); ?></a>
                                        <?php else: ?>
                                            <span class="sg-consulta-label">Consulta</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="sg-empty-state">
                            <div class="sg-empty-state-icon"><?php echo sige_transport_icon('truck'); ?></div>
                            <h3>Nenhuma rota configurada</h3>
                            <p>Quando a escola criar as suas rotas, elas aparecerão aqui com preço, viatura, motorista, lotação e acções de gestão.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    </div>
</div>
