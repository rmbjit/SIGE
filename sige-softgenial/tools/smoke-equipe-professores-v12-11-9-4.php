<?php
/**
 * SIGE SoftGenial v12.11.9.4 - Deep Smoke Test Estático
 * Módulo: Equipa e Professores (&view=equipe)
 *
 * Objectivo: validar fluxos visíveis para o utilizador antes de empacotar.
 * Execução:
 *   php tools/smoke-equipe-professores-v12-11-9-4.php
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$root = dirname(__DIR__);
$failures = [];

function st_read($root, $file, &$failures) {
    $path = $root . DIRECTORY_SEPARATOR . $file;
    if (!file_exists($path)) {
        $failures[] = "Ficheiro em falta: {$file}";
        return '';
    }
    return file_get_contents($path);
}

$main = st_read($root, 'sige-softgenial.php', $failures);
$build = st_read($root, 'BUILD.json', $failures);
$view = st_read($root, 'admin/hr/equipe-view.php', $failures);
$ajax = st_read($root, 'includes/ajax-handlers.php', $failures);
$migration = st_read($root, 'includes/class-sige-migration.php', $failures);

$must = [
    'sige-softgenial.php' => [
        'Version: 12.11.9.4',
        "define('SIGE_VERSION', '12.11.9.4');",
    ],
    'BUILD.json' => [
        '12.11.9.4',
        'equipe-professores-ajax-fatal-fix',
    ],
    'admin/hr/equipe-view.php' => [
        'window.sigeEquipeAjax',
        'sigeEquipeAjax.ajaxurl',
        'sigeEquipeAjax.nonce',
        'function novoFuncionario()',
        'function editarStaff(data)',
        'function guardarStaff(e)',
        'function toggleStatus(userId, statusAtual, email)',
        'function removerUser(id, nome)',
        'function resetSenha(id)',
        'function exportarFolhaSalario()',
        'sigeEquipeAjaxFailMessage',
        "action: 'sige_get_staff_secure'",
        "formData.append('action', 'sige_salvar_funcionario')",
        "action: 'sige_toggle_status_staff'",
        "action: 'sige_remover_usuario_staff'",
        "action: 'sige_resetar_senha'",
        "action: 'sige_exportar_folha_staff'",
    ],
    'includes/ajax-handlers.php' => [
        'sige_ajax_equipe_can_manage',
        'sige_ajax_equipe_audit',
        "sige_audit_log(\$acao, \$detalhes, 'professores', get_current_user_id())",
        'sige_ajax_equipe_user_belongs_to_school',
        'sige_ajax_equipe_begin_buffer',
        'sige_ajax_equipe_send_error',
        'sige_ajax_equipe_send_success',
        "wp_ajax_sige_get_staff_secure",
        "wp_ajax_sige_salvar_funcionario",
        "wp_ajax_sige_toggle_status_staff",
        "wp_ajax_sige_remover_usuario_staff",
        "wp_ajax_sige_resetar_senha",
        "wp_ajax_sige_exportar_folha_staff",
        'Apenas administrador WordPress real pode atribuir o perfil Admin TI',
        'Operação bloqueada: o colaborador não pertence à escola actual',
    ],
    'includes/class-sige-migration.php' => [
        'uniq_prof_escola_email',
        'private function add_unique_index_if_no_duplicates',
    ],
];

$contents = [
    'sige-softgenial.php' => $main,
    'BUILD.json' => $build,
    'admin/hr/equipe-view.php' => $view,
    'includes/ajax-handlers.php' => $ajax,
    'includes/class-sige-migration.php' => $migration,
];

foreach ($must as $file => $needles) {
    foreach ($needles as $needle) {
        if (strpos($contents[$file] ?? '', $needle) === false) {
            $failures[] = "Não encontrado em {$file}: {$needle}";
        }
    }
}

// Não pode voltar a depender de sigeAjax como fonte operacional dentro da view.
foreach (['sigeAjax.avatarUrl','sigeAjax.moeda','sigeAjax.nonce_equipe','jQuery.post(ajaxurl'] as $forbidden) {
    if (strpos($view, $forbidden) !== false) {
        $failures[] = "Dependência AJAX/global proibida na view: {$forbidden}";
    }
}

// Dados sensíveis não podem voltar ao DOM.
foreach (['meta-nuit', 'meta-banco', 'meta-nib', 'meta-mpesa', 'meta-base', 'meta-sub', 'meta-total'] as $forbidden) {
    if (strpos($view, $forbidden) !== false) {
        $failures[] = "Metadado sensível ainda presente no DOM: {$forbidden}";
    }
}

// Todos os jQuery.post da view devem usar o endpoint isolado do módulo.
preg_match_all('/jQuery\.post\(([^,]+),/', $view, $matches);
foreach (($matches[1] ?? []) as $target) {
    if (trim($target) !== 'sigeEquipeAjax.ajaxurl') {
        $failures[] = "jQuery.post fora do endpoint isolado: {$target}";
    }
}

// O endpoint sensível deve limpar buffer antes de responder JSON para evitar parsererror.
foreach (['sige_get_staff_secure','sige_salvar_funcionario','sige_toggle_status_staff','sige_remover_usuario_staff','sige_resetar_senha','sige_exportar_folha_staff'] as $action) {
    $pos = strpos($ajax, "add_action('wp_ajax_{$action}'");
    if ($pos === false) {
        $failures[] = "Endpoint não registado: {$action}";
        continue;
    }
    $slice = substr($ajax, max(0, $pos - 300), 1400);
    if (strpos($slice, 'sige_ajax_equipe_begin_buffer();') === false) {
        $failures[] = "Endpoint sem buffer próprio: {$action}";
    }
}


// Chamadas de auditoria do bloco Equipa não podem usar a assinatura antiga errada
// sige_audit_log(acao, 'staff', user_id, 'mensagem'), que provoca TypeError/HTTP 500.
$equipaAuditPatterns = [
    '/sige_audit_log\\([^;]+,\\s*\'staff\'\\s*,\\s*\\$user_id\\s*,/s',
    '/sige_audit_log\\([^;]+,\\s*\'staff\'\\s*,\\s*\\$staff_id\\s*,/s',
    '/sige_audit_log\\([^;]+,\\s*\'staff\'\\s*,\\s*0\\s*,/s',
];
foreach ($equipaAuditPatterns as $rx) {
    if (preg_match($rx, $ajax)) {
        $failures[] = 'Chamada RH insegura a sige_audit_log() ainda presente: ' . $rx;
    }
}

foreach (['staff_criado','staff_editado','staff_detalhe_sensivel_consultado','staff_removido','reset_senha_link_seguro','folha_salarial_exportada'] as $acaoAudit) {
    $pos = strpos($ajax, $acaoAudit);
    if ($pos === false) {
        $failures[] = "Acção de auditoria RH não localizada: {$acaoAudit}";
    } else {
        $slice = substr($ajax, max(0, $pos - 200), 500);
        if (strpos($slice, 'sige_ajax_equipe_audit') === false) {
            $failures[] = "Acção de auditoria RH fora do wrapper seguro: {$acaoAudit}";
        }
    }
}

$sgSafeStart = strpos($view, 'function sgRhSafeUrl');
$sgSafeEnd = strpos($view, 'function gerarCracha');
$sgSafeBlock = ($sgSafeStart !== false && $sgSafeEnd !== false) ? substr($view, $sgSafeStart, $sgSafeEnd - $sgSafeStart) : '';
if ($sgSafeBlock === '') {
    $failures[] = 'Função sgRhSafeUrl não localizada.';
} elseif (strpos($sgSafeBlock, "'data:'") !== false || strpos($sgSafeBlock, 'data:') !== false) {
    $failures[] = 'sgRhSafeUrl ainda permite protocolo data:.';
}

if ($failures) {
    echo "DEEP SMOKE TEST FALHOU\n";
    foreach ($failures as $f) echo "- {$f}\n";
    exit(1);
}

echo "DEEP SMOKE TEST OK - Equipa e Professores v12.11.9.4\n";
