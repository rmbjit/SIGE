<?php
// Acesso restrito: este utilitario corre apenas via linha de comandos.
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

if (!defined('SIGE_GOV_VERSION')) {
    define('SIGE_GOV_VERSION', '12.12.63');
}

if (!function_exists('sige_gov_version')) {
    function sige_gov_version(): string { return (string)SIGE_GOV_VERSION; }
}

if (!function_exists('sige_gov_version_slug')) {
    function sige_gov_version_slug(): string { return str_replace('.', '-', sige_gov_version()); }
}

if (!function_exists('sige_gov_root')) {
    function sige_gov_root(): string { return dirname(__DIR__); }
}

if (!function_exists('sige_gov_read')) {
    function sige_gov_read(string $rel): string {
        $path = sige_gov_root() . '/' . ltrim($rel, '/');
        return is_file($path) ? (string)file_get_contents($path) : '';
    }
}

if (!function_exists('sige_gov_write_json')) {
    function sige_gov_write_json(string $rel, array $data): void {
        $path = sige_gov_root() . '/' . ltrim($rel, '/');
        $dir = dirname($path);
        if (!is_dir($dir)) mkdir($dir, 0775, true);
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");
    }
}

if (!function_exists('sige_gov_runtime_files')) {
    function sige_gov_runtime_files(array $exts = ['php']): array {
        $root = sige_gov_root();
        $skip = ['/docs/', '/tools/', '/.git/', '/assets/vendor/'];
        $files = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) {
            $path = $f->getPathname();
            $unix = str_replace('\\', '/', $path);
            $ignore = false;
            foreach ($skip as $frag) {
                if (strpos($unix, $frag) !== false) { $ignore = true; break; }
            }
            if ($ignore) continue;
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true)) continue;
            $files[] = substr($unix, strlen(str_replace('\\', '/', $root)) + 1);
        }
        sort($files);
        return $files;
    }
}

if (!function_exists('sige_gov_line_no')) {
    function sige_gov_line_no(string $src, int $offset): int {
        return substr_count(substr($src, 0, max(0, $offset)), "\n") + 1;
    }
}


if (!function_exists('sige_gov_strip_comments_preserve_lines')) {
    function sige_gov_strip_comments_preserve_lines(string $src): string {
        $src = preg_replace_callback('/\/\*.*?\*\//s', static function ($m) {
            return str_repeat("
", substr_count($m[0], "
"));
        }, $src);
        $lines = preg_split('/
/', (string)$src);
        foreach ($lines as &$line) {
            if (preg_match('/^\s*\/\//', $line)) $line = '';
        }
        unset($line);
        return implode("
", $lines);
    }
}

if (!function_exists('sige_gov_module_from')) {
    function sige_gov_module_from(string $rel, string $name = ''): string {
        $x = strtolower($rel . ' ' . $name);
        if (preg_match('/finance|pagamento|mpesa|emola|recibo|caixa|devedor|lancamento/', $x)) return 'financeiro';
        if (preg_match('/whatsapp|email|comunic|circular/', $x)) return 'comunicacao';
        if (preg_match('/portaria|acesso|cracha|crach/', $x)) return 'portaria';
        if (preg_match('/portal/', $x)) return 'portal';
        if (preg_match('/aluno|matricula|documento|agregado/', $x)) return 'secretaria';
        if (preg_match('/nota|turma|pauta|acta|boletim|presenca|academ/', $x)) return 'academico';
        if (preg_match('/config|permiss|user|usuario|role|license|hub|cron|sistema/', $x)) return 'sistema';
        if (preg_match('/jardim|creche/', $x)) return 'jardim';
        if (preg_match('/rh|equipe|professor/', $x)) return 'rh';
        if (preg_match('/transporte|rota/', $x)) return 'transporte';
        return 'sistema';
    }
}

if (!function_exists('sige_gov_risk_from')) {
    function sige_gov_risk_from(string $type, string $name, string $rel): string {
        $x = strtolower($type . ' ' . $name . ' ' . $rel);
        if (preg_match('/delete|apagar|remover|estornar|desbloquear|secret|token|webhook|callback|mpesa|emola|config|permission|permiss|license|migrat|purge|reset/', $x)) return 'critical';
        if (preg_match('/pagar|payment|pagamento|recibo|desp|despesa|despesas|lancar|guardar|save|enviar|send|process|queue|cron|nota|presenca|upload|download|export|import/', $x)) return 'high';
        if (preg_match('/get|ver|listar|consulta|diagnostico|status|health/', $x)) return 'medium';
        return 'medium';
    }
}

if (!function_exists('sige_gov_permission_hint')) {
    function sige_gov_permission_hint(string $module, string $risk): string {
        if ($module === 'financeiro') {
            if (strpos(strtolower($risk), 'critical') !== false) return 'financeiro.ver + permissao especifica';
            return 'financeiro.ver';
        }
        if ($module === 'comunicacao') return $risk === 'high' || $risk === 'critical' ? 'comunicacao.enviar ou permissao especifica' : 'comunicacao.ver';
        if ($module === 'academico') return 'academico.ver ou permissao especifica';
        if ($module === 'secretaria') return 'alunos.ver ou permissao especifica';
        if ($module === 'portaria') return 'portaria.ver ou portaria.validar_acesso';
        if ($module === 'portal') return 'portal.ver';
        if ($module === 'jardim') return 'jardim.ver ou permissao especifica';
        if ($module === 'rh') return 'rh.equipe_ver ou permissao especifica';
        if ($module === 'transporte') return 'transporte.ver ou permissao especifica';
        return 'sistema.estado_ver ou permissao especifica';
    }
}

if (!function_exists('sige_gov_add_surface_item')) {
    function sige_gov_add_surface_item(array &$items, string $type, string $name, string $rel, int $line, bool $public = false, string $extra = ''): void {
        $id = $type . ':' . $name . ($extra !== '' ? ':' . $extra : '');
        if (!isset($items[$id])) {
            $module = sige_gov_module_from($rel, $name);
            $risk = sige_gov_risk_from($type, $name, $rel);
            $items[$id] = [
                'id' => $id,
                'type' => $type,
                'name' => $name,
                'module' => $module,
                'risk' => $risk,
                'public' => $public,
                'tenant_required' => !in_array($module, ['sistema'], true),
                'csrf_or_hmac_required' => $public ? 'hmac_token_or_signature_expected' : 'nonce_expected',
                'audit_required' => in_array($risk, ['high','critical'], true),
                'rate_limit_expected' => in_array($risk, ['high','critical'], true),
                'permission_expected' => sige_gov_permission_hint($module, $risk),
                'status' => 'baseline_pending_phase_1_enforcement',
                'registrations' => [],
            ];
            if ($type === 'rest_route' && $extra !== '') {
                $items[$id]['route'] = $extra;
                $items[$id]['runtime_match'] = 'namespace_route';
            }
        }
        $reg = ['file' => $rel, 'line' => $line];
        foreach ($items[$id]['registrations'] as $existing) {
            if (($existing['file'] ?? '') === $rel && (int)($existing['line'] ?? 0) === $line) {
                return;
            }
        }
        $items[$id]['registrations'][] = $reg;
    }
}

if (!function_exists('sige_gov_collect_string_constants')) {
    function sige_gov_collect_string_constants(string $src): array {
        $constants = [];
        if (preg_match_all('/\bconst\s+([A-Z0-9_]+)\s*=\s*[\'\"]([^\'\"]+)[\'\"]\s*;/i', $src, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) $constants[$m[1]] = $m[2];
        }
        if (preg_match_all('/\bdefine\s*\(\s*[\'\"]([A-Z0-9_]+)[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]\s*\)/i', $src, $mm, PREG_SET_ORDER)) {
            foreach ($mm as $m) $constants[$m[1]] = $m[2];
        }
        return $constants;
    }
}

if (!function_exists('sige_gov_resolve_hook_expr')) {
    function sige_gov_resolve_hook_expr(string $expr, array $constants): ?string {
        $expr = trim($expr);
        if ($expr === '') return null;
        $parts = preg_split('/\s*\.\s*/', $expr);
        if (!$parts) return null;
        $out = '';
        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;
            if (preg_match('/^[\'\"]([^\'\"]*)[\'\"]$/', $part, $m)) {
                $out .= $m[1];
                continue;
            }
            if (preg_match('/^(?:self|static|[A-Za-z_][A-Za-z0-9_]*)::([A-Z0-9_]+)$/', $part, $m) && isset($constants[$m[1]])) {
                $out .= $constants[$m[1]];
                continue;
            }
            if (preg_match('/^([A-Z0-9_]+)$/', $part, $m) && isset($constants[$m[1]])) {
                $out .= $constants[$m[1]];
                continue;
            }
            return null;
        }
        return $out !== '' ? $out : null;
    }
}

if (!function_exists('sige_gov_register_hook_surface')) {
    function sige_gov_register_hook_surface(array &$items, string $hook, string $rel, int $line): void {
        if (strpos($hook, 'wp_ajax_nopriv_') === 0) {
            sige_gov_add_surface_item($items, 'wp_ajax_nopriv', substr($hook, strlen('wp_ajax_nopriv_')), $rel, $line, true);
            return;
        }
        if (strpos($hook, 'wp_ajax_') === 0) {
            sige_gov_add_surface_item($items, 'wp_ajax', substr($hook, strlen('wp_ajax_')), $rel, $line, false);
            return;
        }
        if (strpos($hook, 'admin_post_nopriv_') === 0) {
            sige_gov_add_surface_item($items, 'admin_post_nopriv', substr($hook, strlen('admin_post_nopriv_')), $rel, $line, true);
            return;
        }
        if (strpos($hook, 'admin_post_') === 0) {
            sige_gov_add_surface_item($items, 'admin_post', substr($hook, strlen('admin_post_')), $rel, $line, false);
            return;
        }
        if (in_array($hook, ['template_redirect','admin_init','send_headers','wp_headers','parse_request'], true)) {
            sige_gov_add_surface_item($items, 'wp_hook', $hook, $rel, $line, false);
            return;
        }
        if (preg_match('/(cron|queue|process|processar|conciliacao|evento|heartbeat|refresh|daily)/i', $hook)) {
            sige_gov_add_surface_item($items, 'cron_hook', $hook, $rel, $line, false);
        }
    }
}

if (!function_exists('sige_gov_query_surface_public')) {
    function sige_gov_query_surface_public(string $key): bool {
        return in_array($key, ['sige_recibo'], true);
    }
}


if (!function_exists('sige_gov_critical_view_actions_12127')) {
    function sige_gov_critical_view_actions_12127(): array {
        return [
            ['id'=>'view_action:financeiro-pagamentos:sige_fin_pagar_submit','type'=>'view_action','name'=>'sige_fin_pagar_submit','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.pagar','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-pagamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-pagamentos.php','line'=>340]]],
            ['id'=>'view_action:financeiro-pagamentos:sige_fin_pagar_familia_submit','type'=>'view_action','name'=>'sige_fin_pagar_familia_submit','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.pagar','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-pagamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-pagamentos.php','line'=>1200]]],
            ['id'=>'view_action:financeiro-pagamentos:sige_fin_cancelar_submit','type'=>'view_action','name'=>'sige_fin_cancelar_submit','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.lancamentos_gerir','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-pagamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-pagamentos.php','line'=>1016]]],
            ['id'=>'view_action:financeiro-pagamentos:sige_fin_isentar_lancamento_submit','type'=>'view_action','name'=>'sige_fin_isentar_lancamento_submit','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.isentar_multas','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-pagamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-pagamentos.php','line'=>1045]]],
            ['id'=>'view_action:financeiro-pagamentos:sige_fin_bloquear_mes_submit','type'=>'view_action','name'=>'sige_fin_bloquear_mes_submit','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.bloquear_mes','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-pagamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-pagamentos.php','line'=>1100]]],
            ['id'=>'view_action:financeiro-pagamentos:sige_fin_desbloquear_mes_submit','type'=>'view_action','name'=>'sige_fin_desbloquear_mes_submit','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.desbloquear_mes','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-pagamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-pagamentos.php','line'=>1140]]],
            ['id'=>'view_action:financeiro-despesas:nova_despesa','type'=>'view_action','name'=>'nova_despesa','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.despesas_gerir','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-despesas','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-despesas-view.php','line'=>43]]],
            ['id'=>'view_action:financeiro-despesas:aprovar_despesa','type'=>'view_action','name'=>'aprovar_despesa','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.despesas_gerir','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-despesas','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-despesas-view.php','line'=>97]]],
            ['id'=>'view_action:financeiro-despesas:anular_despesa','type'=>'view_action','name'=>'anular_despesa','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.despesas_gerir','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-despesas','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-despesas-view.php','line'=>136]]],
            ['id'=>'view_action:financeiro-lancamentos:cancelar_lancamento','type'=>'view_action','name'=>'cancelar_lancamento','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.lancamentos_gerir','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-lancamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-lancamentos-view.php','line'=>226]]],
            ['id'=>'view_action:financeiro-lancamentos:isentar_lancamento','type'=>'view_action','name'=>'isentar_lancamento','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.isentar_multas','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-lancamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-lancamentos-view.php','line'=>249]]],
            ['id'=>'view_action:financeiro-lancamentos:reativar_lancamento','type'=>'view_action','name'=>'reativar_lancamento','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.lancamentos_gerir','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-lancamentos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-lancamentos-view.php','line'=>272]]],
            ['id'=>'view_action:financeiro-extratos:sige_fechar_caixa','type'=>'view_action','name'=>'sige_fechar_caixa','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.caixa_fechar','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-extratos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-extratos.php','line'=>607]]],
            ['id'=>'view_action:financeiro-extratos:sige_reabrir_caixa','type'=>'view_action','name'=>'sige_reabrir_caixa','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.caixa_reabrir','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-extratos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-extratos.php','line'=>494]]],
            ['id'=>'view_action:financeiro-extratos:sige_anular_recibo','type'=>'view_action','name'=>'sige_anular_recibo','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.estornar','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-extratos','method'=>'POST','registrations'=>[['file'=>'admin/finance/financeiro-extratos.php','line'=>725]]],
            ['id'=>'view_action:financeiro-aprovacoes:sige_fin_aprovacao_decidir','type'=>'view_action','name'=>'sige_fin_aprovacao_decidir','module'=>'financeiro','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'financeiro.estornar','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'financeiro-aprovacoes','method'=>'POST','registrations'=>[['file'=>'admin/finance/aprovacoes-view.php','line'=>33]]],
            ['id'=>'view_action:sige_permissoes:save_role_permissions','type'=>'view_action','name'=>'save_role_permissions','module'=>'usuarios','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'usuarios.gerir_permissoes','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'sige_permissoes','method'=>'POST','registrations'=>[['file'=>'admin/system/permissions-ui.php','line'=>183]]],
            ['id'=>'view_action:sige_permissoes:assign_user_role','type'=>'view_action','name'=>'assign_user_role','module'=>'usuarios','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'usuarios.gerir_permissoes','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'sige_permissoes','method'=>'POST','registrations'=>[['file'=>'admin/system/permissions-ui.php','line'=>233]]],
            ['id'=>'view_action:sige_permissoes:unassign_user_role','type'=>'view_action','name'=>'unassign_user_role','module'=>'usuarios','risk'=>'critical','public'=>false,'tenant_required'=>true,'csrf_or_hmac_required'=>'nonce_expected','audit_required'=>true,'rate_limit_expected'=>true,'permission_expected'=>'usuarios.gerir_permissoes','status'=>'critical_actions_lockdown','page'=>'sige-app','view'=>'sige_permissoes','method'=>'POST','registrations'=>[['file'=>'admin/system/permissions-ui.php','line'=>278]]],
        ];
    }
}


if (!function_exists('sige_gov_extract_query_surface_from_source')) {
    function sige_gov_extract_query_surface_from_source(array &$items, string $src, string $rel): void {
        if (preg_match_all('/\$_(?:GET|REQUEST)\s*\[\s*[\'\"](sige_[A-Za-z0-9_\-]+)[\'\"]\s*\]/', $src, $qm, PREG_OFFSET_CAPTURE)) {
            foreach ($qm[1] as $hit) {
                $key = (string)$hit[0];
                sige_gov_add_surface_item($items, 'query_handler', $key, $rel, sige_gov_line_no($src, (int)$hit[1]), sige_gov_query_surface_public($key));
            }
        }
    }
}

if (!function_exists('sige_gov_extract_action_surface_independent')) {
    function sige_gov_extract_action_surface_independent(): array {
        $items = [];
        foreach (sige_gov_runtime_files(['php']) as $rel) {
            $src = sige_gov_read($rel);
            $scan = sige_gov_strip_comments_preserve_lines($src);
            $constants = sige_gov_collect_string_constants($scan);
            if (preg_match_all('/add_action\s*\(\s*([^,\n\r]+)\s*,/m', $scan, $hm, PREG_OFFSET_CAPTURE)) {
                foreach ($hm[1] as $hit) {
                    $hook = sige_gov_resolve_hook_expr((string)$hit[0], $constants);
                    if ($hook !== null) sige_gov_register_hook_surface($items, $hook, $rel, sige_gov_line_no($scan, (int)$hit[1]));
                }
            }
            if (preg_match_all('/add_shortcode\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*,/', $scan, $sm, PREG_OFFSET_CAPTURE)) {
                foreach ($sm[1] as $hit) sige_gov_add_surface_item($items, 'shortcode', (string)$hit[0], $rel, sige_gov_line_no($scan, (int)$hit[1]), true);
            }
            if (preg_match_all('/register_rest_route\s*\(\s*[\'\"]([^\'\"]+)[\'\"]\s*,\s*[\'\"]([^\'\"]+)[\'\"]/', $scan, $rm, PREG_OFFSET_CAPTURE)) {
                foreach ($rm[1] as $i => $ns) sige_gov_add_surface_item($items, 'rest_route', (string)$ns[0], $rel, sige_gov_line_no($scan, (int)$ns[1]), true, (string)$rm[2][$i][0]);
            }
            sige_gov_extract_query_surface_from_source($items, $scan, $rel);
        }
        foreach (sige_gov_critical_view_actions_12127() as $va) {
            $items[$va['id']] = $va;
        }
        ksort($items);
        return array_values($items);
    }
}

if (!function_exists('sige_gov_extract_action_surface')) {
    function sige_gov_extract_action_surface(): array {
        $items = [];
        foreach (sige_gov_runtime_files(['php']) as $rel) {
            $src = sige_gov_read($rel);
            $scan = sige_gov_strip_comments_preserve_lines($src);
            $constants = sige_gov_collect_string_constants($scan);

            if (preg_match_all('/add_action\s*\(\s*([^,\n\r]+)\s*,/m', $scan, $hm, PREG_OFFSET_CAPTURE)) {
                foreach ($hm[1] as $hit) {
                    $hook = sige_gov_resolve_hook_expr((string)$hit[0], $constants);
                    if ($hook !== null) {
                        sige_gov_register_hook_surface($items, $hook, $rel, sige_gov_line_no($scan, (int)$hit[1]));
                    }
                }
            }

            $patterns = [
                'wp_ajax_nopriv' => "/add_action\s*\(\s*['\"]wp_ajax_nopriv_([^'\"]+)['\"]\s*,/",
                'wp_ajax' => "/add_action\s*\(\s*['\"]wp_ajax_(?!nopriv_)([^'\"]+)['\"]\s*,/",
                'admin_post_nopriv' => "/add_action\s*\(\s*['\"]admin_post_nopriv_([^'\"]+)['\"]\s*,/",
                'admin_post' => "/add_action\s*\(\s*['\"]admin_post_(?!nopriv_)([^'\"]+)['\"]\s*,/",
                'shortcode' => "/add_shortcode\s*\(\s*['\"]([^'\"]+)['\"]\s*,/",
            ];
            foreach ($patterns as $type => $pattern) {
                if (preg_match_all($pattern, $scan, $mm, PREG_OFFSET_CAPTURE)) {
                    foreach ($mm[1] as $hit) {
                        $public = in_array($type, ['wp_ajax_nopriv','admin_post_nopriv'], true);
                        sige_gov_add_surface_item($items, $type, (string)$hit[0], $rel, sige_gov_line_no($scan, (int)$hit[1]), ($type === 'shortcode' ? true : $public));
                    }
                }
            }
            if (preg_match_all("/register_rest_route\s*\(\s*['\"]([^'\"]+)['\"]\s*,\s*['\"]([^'\"]+)['\"]/", $scan, $rm, PREG_OFFSET_CAPTURE)) {
                foreach ($rm[1] as $i => $ns) {
                    $route = (string)$rm[2][$i][0];
                    sige_gov_add_surface_item($items, 'rest_route', (string)$ns[0], $rel, sige_gov_line_no($scan, (int)$ns[1]), true, $route);
                }
            }
            if (preg_match_all("/wp_(?:schedule_event|schedule_single_event|next_scheduled)\s*\((.*?)\)/s", $scan, $cm, PREG_OFFSET_CAPTURE)) {
                foreach ($cm[1] as $match) {
                    if (preg_match_all("/['\"]([^'\"]*sige_[^'\"]*)['\"]/", (string)$match[0], $hm2)) {
                        foreach ($hm2[1] as $hook) {
                            if (preg_match('/(cron|queue|process|processar|conciliacao|evento|heartbeat|refresh|daily)/i', $hook)) {
                                sige_gov_add_surface_item($items, 'cron_hook', $hook, $rel, sige_gov_line_no($scan, (int)$match[1]), false);
                            }
                        }
                    }
                }
            }
            if (preg_match_all("/add_action\s*\(\s*['\"](sige_[^'\"]+)['\"]\s*,/", $scan, $hm2, PREG_OFFSET_CAPTURE)) {
                foreach ($hm2[1] as $hit) {
                    $hook = (string)$hit[0];
                    if (preg_match('/(cron|queue|process|processar|conciliacao|evento|heartbeat|refresh|daily)/i', $hook)) {
                        sige_gov_add_surface_item($items, 'cron_hook', $hook, $rel, sige_gov_line_no($scan, (int)$hit[1]), false);
                    }
                }
            }

            sige_gov_extract_query_surface_from_source($items, $scan, $rel);
        }
        foreach (sige_gov_critical_view_actions_12127() as $va) {
            $items[$va['id']] = $va;
        }
        ksort($items);
        return array_values($items);
    }
}

if (!function_exists('sige_gov_extract_views')) {
    function sige_gov_extract_views(): array {
        $shell = sige_gov_read('includes/admin-shell.php');
        $allow = [];
        $perm = [];
        if (preg_match('/\$_views_ok\s*=\s*\[(.*?)\];/s', $shell, $m)) {
            preg_match_all('/["\']([a-z0-9_\-]+)["\']/', $m[1], $a);
            $allow = array_values(array_unique($a[1]));
            sort($allow);
        }
        if (preg_match('/\$sige_view_permission_map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $m)) {
            preg_match_all('/["\']([a-z0-9_\-]+)["\']\s*=>\s*\[(.*?)\]/s', $m[1], $rows, PREG_SET_ORDER);
            foreach ($rows as $row) {
                preg_match_all('/["\']([a-z0-9_.\-]+)["\']/', $row[2], $ps);
                $perm[$row[1]] = array_values(array_unique($ps[1]));
            }
            ksort($perm);
        }
        // v12.12.22 - Mapa de despacho (?page=sige-app&view=<slug>). Permite ao gate
        // exigir o invariante: toda a rota despachada tem de estar na allowlist, senao
        // a guarda anti-LFI reescreve o pedido para o painel inicial (rota morta na UI).
        $dispatch = [];
        if (preg_match('/\$map\s*=\s*\[(.*?)\n\s*\];/s', $shell, $md)) {
            preg_match_all('/["\']([a-z0-9_\-]+)["\']\s*=>/', $md[1], $dm);
            $dispatch = array_values(array_unique($dm[1]));
            sort($dispatch);
        }
        return ['allowlist' => $allow, 'permission_map' => $perm, 'dispatch_map' => $dispatch];
    }
}

if (!function_exists('sige_gov_option_names')) {
    function sige_gov_option_names(): array {
        $ops = [];
        foreach (sige_gov_runtime_files(['php']) as $rel) {
            $src = sige_gov_read($rel);
            if (preg_match_all('/\b(get_option|update_option|add_option|delete_option)\s*\(\s*["\']([^"\']+)["\']/', $src, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[2] as $i => $hit) {
                    $name = (string)$hit[0];
                    if (!isset($ops[$name])) $ops[$name] = ['name' => $name, 'operations' => [], 'files' => []];
                    $ops[$name]['operations'][] = (string)$mm[1][$i][0];
                    $ops[$name]['files'][] = $rel . ':' . sige_gov_line_no($src, (int)$hit[1]);
                }
            }
        }
        foreach ($ops as &$op) {
            $op['operations'] = array_values(array_unique($op['operations']));
            $op['files'] = array_values(array_unique($op['files']));
            sort($op['operations']); sort($op['files']);
            $n = strtolower($op['name']);
            $op['sensitive_candidate'] = (bool)preg_match('/(key|secret|token|senha|password|smtp|license|hub|api|webhook|cron|whatsapp|mpesa|emola)/', $n);
        }
        unset($op);
        ksort($ops);
        return array_values($ops);
    }
}

if (!function_exists('sige_gov_external_hosts')) {
    function sige_gov_external_hosts(): array {
        $hosts = [];
        foreach (sige_gov_runtime_files(['php','js','css']) as $rel) {
            $src = sige_gov_read($rel);
            if (preg_match_all('#https?://([a-z0-9.-]+)(?:[:/][^\s"\'<>)]*)?#i', $src, $mm, PREG_OFFSET_CAPTURE)) {
                foreach ($mm[1] as $hit) {
                    $host = strtolower((string)$hit[0]);
                    if (!isset($hosts[$host])) $hosts[$host] = ['host' => $host, 'files' => []];
                    $hosts[$host]['files'][] = $rel . ':' . sige_gov_line_no($src, (int)$hit[1]);
                }
            }
        }
        foreach ($hosts as &$h) { $h['files'] = array_values(array_unique($h['files'])); sort($h['files']); }
        unset($h);
        ksort($hosts);
        return array_values($hosts);
    }
}

if (!function_exists('sige_gov_auth_baseline')) {
    function sige_gov_auth_baseline(): array {
        $files = [];
        $total_current = 0; $total_sige = 0;
        foreach (sige_gov_runtime_files(['php']) as $rel) {
            $src = sige_gov_read($rel);
            $c = preg_match_all('/\bcurrent_user_can\s*\(/', $src);
            $s = preg_match_all('/\bsige_can\s*\(/', $src);
            if ($c || $s) {
                $files[$rel] = ['current_user_can' => $c, 'sige_can' => $s];
                $total_current += $c; $total_sige += $s;
            }
        }
        ksort($files);
        return ['current_user_can_total' => $total_current, 'sige_can_total' => $total_sige, 'files' => $files];
    }
}

if (!function_exists('sige_gov_tenant_fallbacks')) {
    function sige_gov_tenant_fallbacks(): array {
        $hits = [];
        foreach (sige_gov_runtime_files(['php']) as $rel) {
            $src = sige_gov_read($rel);
            $lines = preg_split('/\n/', $src);
            foreach ($lines as $i => $line) {
                if (strpos($line, 'escola_id') === false && strpos($line, 'sige_get_escola_id') === false) continue;
                if (preg_match('/(:\s*1\b|\?\s*\(int\)\s*sige_get_escola_id\(\)\s*:\s*1\b|\?\s*sige_get_escola_id\(\)\s*:\s*1\b|=\s*1\s*;)/', $line)) {
                    $hits[] = ['file' => $rel, 'line' => $i + 1, 'code' => trim($line)];
                }
            }
        }
        return $hits;
    }
}

if (!function_exists('sige_gov_expense_print_tenant_findings')) {
    function sige_gov_expense_print_tenant_findings(): array {
        $src = sige_gov_read('includes/finance-core.php');
        $findings = [];
        if ($src === '') {
            return [['id' => 'finance_core_missing', 'message' => 'includes/finance-core.php ausente']];
        }
        if (strpos($src, 'SELECT * FROM $tD WHERE id=%d", $id') !== false) {
            $findings[] = ['id' => 'despesa_comprovativo_unscoped', 'message' => 'Comprovativo de despesa consulta por id sem escola_id.'];
        }
        if (strpos($src, '$where = ["1=1"];') !== false && strpos($src, "sige_desp_print") !== false) {
            $findings[] = ['id' => 'despesa_relatorio_where_unscoped', 'message' => 'Relatorio de despesas usa WHERE 1=1 em vez de escola_id obrigatório.'];
        }
        if (strpos($src, 'SELECT * FROM $tD WHERE id=%d AND escola_id=%d') === false) {
            $findings[] = ['id' => 'despesa_comprovativo_scope_missing', 'message' => 'Comprovativo de despesa nao contem consulta id+escola_id.'];
        }
        if (strpos($src, '$where = ["escola_id = %d"];') === false) {
            $findings[] = ['id' => 'despesa_relatorio_scope_missing', 'message' => 'Relatorio de despesas nao inicia filtros com escola_id.'];
        }
        if (strpos($src, "wp_verify_nonce(\$nonce, 'sige_desp_print')") === false) {
            $findings[] = ['id' => 'despesa_print_nonce_missing', 'message' => 'sige_desp_print nao exige nonce.'];
        }
        if (strpos($src, "sige_audit_log('despesa_comprovativo_impresso'") === false || strpos($src, "sige_audit_log('despesas_relatorio_impresso'") === false) {
            $findings[] = ['id' => 'despesa_print_audit_missing', 'message' => 'sige_desp_print nao audita comprovativo e relatorio.'];
        }
        return $findings;
    }
}

if (!function_exists('sige_gov_summary')) {
    function sige_gov_summary(): array {
        $root = sige_gov_root();
        $all = [];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
        foreach ($rii as $f) { $all[] = $f->getPathname(); }
        $php = sige_gov_runtime_files(['php']);
        $surface = sige_gov_extract_action_surface();
        $views = sige_gov_extract_views();
        $by_type = [];
        foreach ($surface as $it) { $by_type[$it['type']] = ($by_type[$it['type']] ?? 0) + 1; }
        ksort($by_type);
        return [
            'total_files' => count($all),
            'runtime_php_files' => count($php),
            'action_surface_total' => count($surface),
            'action_surface_by_type' => $by_type,
            'views_allowlist' => count($views['allowlist']),
            'views_permission_map' => count($views['permission_map']),
            'authorization' => sige_gov_auth_baseline(),
            'tenant_fallbacks_count' => count(sige_gov_tenant_fallbacks()),
            'option_names_count' => count(sige_gov_option_names()),
            'external_hosts_count' => count(sige_gov_external_hosts()),
        ];
    }
}
