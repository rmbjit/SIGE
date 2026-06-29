<?php
/**
 * SIGE SoftGenial - Aparência da Escola
 *
 * Motor seguro de temas visuais para permitir que cada escola personalize
 * a identidade cromática sem quebrar o padrão visual aprovado do produto.
 *
 * O motor não altera fórmulas, regras financeiras, regras académicas,
 * base de dados nem permissões. Apenas injeta variáveis visuais seguras.
 *
 * @since 12.10.31
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_theme_default_palette')) {
    function sige_theme_default_palette(): array {
        return [
            'mode'      => 'softgenial',
            'primary'   => '#5a3fd6',
            'secondary' => '#3f2c9f',
            'accent'    => '#34a853',
            'soft'      => '#f1edff',
            'ink'       => '#202037',
            'surface'   => '#ffffff',
            'background'=> '#f6f4fb',
        ];
    }
}


if (!function_exists('sige_theme_font_choices')) {
    function sige_theme_font_choices(): array {
        return [
            'softgenial' => "'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif",
            'inter'      => "'Inter','Plus Jakarta Sans','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif",
            'system'     => "system-ui,-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif",
            'segoe'      => "'Segoe UI',Roboto,Arial,sans-serif",
            'arial'      => "Arial,Helvetica,sans-serif",
            'verdana'    => "Verdana,Geneva,sans-serif",
            'serif'      => "Georgia,'Times New Roman',serif",
        ];
    }
}

if (!function_exists('sige_theme_font_stack')) {
    function sige_theme_font_stack($value = null): string {
        $choices = sige_theme_font_choices();
        $key = is_string($value) && $value !== '' ? sanitize_key($value) : 'softgenial';
        return $choices[$key] ?? $choices['softgenial'];
    }
}

if (!function_exists('sige_theme_current_font_key')) {
    function sige_theme_current_font_key(): string {
        $key = (string) sige_theme_get_option_value('aparencia.fonte', 'softgenial');
        $key = sanitize_key($key);
        return array_key_exists($key, sige_theme_font_choices()) ? $key : 'softgenial';
    }
}

if (!function_exists('sige_theme_hex')) {
    function sige_theme_hex($value, string $fallback): string {
        $value = is_string($value) ? trim($value) : '';
        if (function_exists('sanitize_hex_color')) {
            $hex = sanitize_hex_color($value);
            if ($hex) return strtolower($hex);
        }
        return strtolower($fallback);
    }
}

if (!function_exists('sige_theme_hex_to_rgb')) {
    function sige_theme_hex_to_rgb(string $hex): array {
        $hex = ltrim($hex, '#');
        if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
    }
}

if (!function_exists('sige_theme_rgb_string')) {
    function sige_theme_rgb_string(string $hex): string {
        return implode(',', sige_theme_hex_to_rgb($hex));
    }
}

if (!function_exists('sige_theme_mix')) {
    function sige_theme_mix(string $a, string $b, float $amount): string {
        $amount = max(0, min(1, $amount));
        [$ar,$ag,$ab] = sige_theme_hex_to_rgb($a);
        [$br,$bg,$bb] = sige_theme_hex_to_rgb($b);
        $r = (int) round($ar + ($br - $ar) * $amount);
        $g = (int) round($ag + ($bg - $ag) * $amount);
        $bl = (int) round($ab + ($bb - $ab) * $amount);
        return sprintf('#%02x%02x%02x', $r, $g, $bl);
    }
}

if (!function_exists('sige_theme_luminance')) {
    function sige_theme_luminance(string $hex): float {
        $rgb = array_map(function($v){
            $v = $v / 255;
            return ($v <= 0.03928) ? ($v / 12.92) : pow(($v + 0.055) / 1.055, 2.4);
        }, sige_theme_hex_to_rgb($hex));
        return 0.2126*$rgb[0] + 0.7152*$rgb[1] + 0.0722*$rgb[2];
    }
}

if (!function_exists('sige_theme_contrast')) {
    function sige_theme_contrast(string $a, string $b): float {
        $la = sige_theme_luminance($a);
        $lb = sige_theme_luminance($b);
        $lighter = max($la, $lb);
        $darker  = min($la, $lb);
        return ($lighter + 0.05) / ($darker + 0.05);
    }
}

if (!function_exists('sige_theme_make_accessible_primary')) {
    function sige_theme_make_accessible_primary(string $hex): string {
        $hex = sige_theme_hex($hex, '#5a3fd6');
        // O menu usa texto branco. Se a cor for clara, escurece progressivamente.
        $safe = $hex;
        $guard = 0;
        while (sige_theme_contrast($safe, '#ffffff') < 4.5 && $guard < 12) {
            $safe = sige_theme_mix($safe, '#111827', 0.16);
            $guard++;
        }
        return strtolower($safe);
    }
}

if (!function_exists('sige_theme_get_option_value')) {
    function sige_theme_get_option_value(string $key, $default = '') {
        if (class_exists('SIGE_Settings_Repository')) {
            $value = SIGE_Settings_Repository::get($key, null);
            if ($value !== null && $value !== '') return $value;
        }
        $option_map = [
            'aparencia.tema'       => 'sige_theme_mode',
            'aparencia.primaria'   => 'sige_theme_primary',
            'aparencia.secundaria' => 'sige_theme_secondary',
            'aparencia.destaque'   => 'sige_theme_accent',
            'aparencia.fonte'      => 'sige_theme_font',
        ];
        return get_option($option_map[$key] ?? $key, $default);
    }
}

if (!function_exists('sige_theme_current_palette')) {
    function sige_theme_current_palette(): array {
        $d = sige_theme_default_palette();
        $mode = (string) sige_theme_get_option_value('aparencia.tema', $d['mode']);
        $mode = in_array($mode, ['softgenial','escola'], true) ? $mode : 'softgenial';

        $raw_primary   = sige_theme_hex((string) sige_theme_get_option_value('aparencia.primaria', $d['primary']), $d['primary']);
        $raw_secondary = sige_theme_hex((string) sige_theme_get_option_value('aparencia.secundaria', $d['secondary']), $d['secondary']);
        $raw_accent    = sige_theme_hex((string) sige_theme_get_option_value('aparencia.destaque', $d['accent']), $d['accent']);
        $has_custom_colors = (
            strtolower($raw_primary) !== strtolower($d['primary']) ||
            strtolower($raw_secondary) !== strtolower($d['secondary']) ||
            strtolower($raw_accent) !== strtolower($d['accent'])
        );

        // Se a escola já guardou cores próprias antes de activar o selector,
        // aplicamos o tema da escola para evitar a sensação de que guardar não fez efeito.
        if ($mode !== 'escola' && $has_custom_colors) $mode = 'escola';

        if ($mode !== 'escola') {
            $primary = $d['primary'];
            $secondary = $d['secondary'];
            $accent = $d['accent'];
        } else {
            $primary = sige_theme_make_accessible_primary($raw_primary);
            $secondary = sige_theme_make_accessible_primary($raw_secondary ?: sige_theme_mix($primary, '#111827', 0.22));
            $accent = $raw_accent;
        }

        return [
            'mode'       => $mode,
            'primary'    => $primary,
            'primary_700'=> sige_theme_mix($primary, '#111827', 0.18),
            'primary_800'=> sige_theme_mix($primary, '#111827', 0.28),
            'primary_900'=> sige_theme_mix($primary, '#111827', 0.42),
            'primary_100'=> sige_theme_mix($primary, '#ffffff', 0.84),
            'primary_50' => sige_theme_mix($primary, '#ffffff', 0.92),
            'secondary'  => $secondary,
            'accent'     => $accent,
            'accent_100' => sige_theme_mix($accent, '#ffffff', 0.84),
            'soft'       => sige_theme_mix($primary, '#ffffff', 0.88),
            'background' => '#f6f4fb',
            'surface'    => '#ffffff',
            'ink'        => '#202037',
            'font_key'   => sige_theme_current_font_key(),
            'font_stack' => sige_theme_font_stack(sige_theme_current_font_key()),
        ];
    }
}


if (!function_exists('sige_theme_document_palette')) {
    /**
     * Paleta segura para documentos impressos.
     * Usa a Cor dos documentos quando estiver definida e, como fallback,
     * acompanha a cor principal activa da Aparência da escola.
     */
    function sige_theme_document_palette($perfil = null): array {
        $app = function_exists('sige_theme_current_palette') ? sige_theme_current_palette() : sige_theme_default_palette();
        $doc_primary = '';

        if (is_object($perfil) && !empty($perfil->cor_primaria)) {
            $doc_primary = (string) $perfil->cor_primaria;
        }
        if ($doc_primary === '' && function_exists('sige_get_escola_perfil')) {
            $cfg = sige_get_escola_perfil();
            if (is_object($cfg) && !empty($cfg->cor_primaria)) $doc_primary = (string) $cfg->cor_primaria;
        }

        $primary = sige_theme_make_accessible_primary($doc_primary !== '' ? $doc_primary : ($app['primary'] ?? '#5a3fd6'));
        $accent  = sige_theme_hex((string)($app['accent'] ?? '#34a853'), '#34a853');

        return [
            'primary'     => $primary,
            'primary_700' => sige_theme_mix($primary, '#111827', 0.18),
            'primary_800' => sige_theme_mix($primary, '#111827', 0.28),
            'primary_900' => sige_theme_mix($primary, '#111827', 0.42),
            'primary_100' => sige_theme_mix($primary, '#ffffff', 0.84),
            'primary_50'  => sige_theme_mix($primary, '#ffffff', 0.92),
            'accent'      => $accent,
            'accent_100'  => sige_theme_mix($accent, '#ffffff', 0.84),
            'ink'         => '#202037',
            'muted'       => '#64748b',
        ];
    }
}

if (!function_exists('sige_theme_document_css_vars')) {
    function sige_theme_document_css_vars($perfil = null): string {
        $p = sige_theme_document_palette($perfil);
        $pairs = [
            '--sg-doc-primary'       => $p['primary'],
            '--sg-doc-primary-rgb'   => sige_theme_rgb_string($p['primary']),
            '--sg-doc-primary-50'    => $p['primary_50'],
            '--sg-doc-primary-100'   => $p['primary_100'],
            '--sg-doc-primary-700'   => $p['primary_700'],
            '--sg-doc-primary-800'   => $p['primary_800'],
            '--sg-doc-primary-900'   => $p['primary_900'],
            '--sg-doc-accent'        => $p['accent'],
            '--sg-doc-accent-100'    => $p['accent_100'],
            '--sg-doc-ink'           => $p['ink'],
            '--sg-doc-muted'         => $p['muted'],
        ];
        $out = [];
        foreach ($pairs as $k => $v) {
            if (preg_match('/^#[0-9a-f]{6}$/i', (string)$v) || preg_match('/^[0-9]+,[0-9]+,[0-9]+$/', (string)$v)) {
                $out[] = $k . ':' . strtolower((string)$v);
            }
        }
        return implode(';', $out) . ';';
    }
}

if (!function_exists('sige_theme_css_vars')) {
    function sige_theme_css_vars(array $p): string {
        $pairs = [
            '--sg-theme-primary'       => $p['primary'],
            '--sg-theme-primary-rgb'   => sige_theme_rgb_string($p['primary']),
            '--sg-theme-primary-50'    => $p['primary_50'],
            '--sg-theme-primary-100'   => $p['primary_100'],
            '--sg-theme-primary-700'   => $p['primary_700'],
            '--sg-theme-primary-800'   => $p['primary_800'],
            '--sg-theme-primary-900'   => $p['primary_900'],
            '--sg-theme-secondary'     => $p['secondary'],
            '--sg-theme-secondary-rgb' => sige_theme_rgb_string($p['secondary']),
            '--sg-theme-accent'        => $p['accent'],
            '--sg-theme-accent-rgb'    => sige_theme_rgb_string($p['accent']),
            '--sg-theme-accent-100'    => $p['accent_100'],
            '--sg-theme-soft'          => $p['soft'],
            '--sg-theme-bg'            => $p['background'],
            '--sg-theme-surface'       => $p['surface'],
            '--sg-theme-ink'           => $p['ink'],
            // v12.11.9.20 - rampa primaria COMPLETA e coerente para QUALQUER tema
            // (corrige a fuga de indigo nos tons que o runtime nao cobria: 50/100/200/300/400/900/950).
            '--sg-primary-50'          => $p['primary_50'],
            '--sg-primary-100'         => $p['primary_100'],
            '--sg-primary-200'         => sige_theme_mix($p['primary'], '#ffffff', 0.68),
            '--sg-primary-300'         => sige_theme_mix($p['primary'], '#ffffff', 0.50),
            '--sg-primary-400'         => sige_theme_mix($p['primary'], '#ffffff', 0.26),
            '--sg-primary-500'         => $p['primary'],
            '--sg-primary-600'         => $p['primary_700'],
            '--sg-primary-700'         => $p['primary_800'],
            '--sg-primary-800'         => $p['primary_900'],
            '--sg-primary-900'         => sige_theme_mix($p['primary'], '#111827', 0.55),
            '--sg-primary-950'         => sige_theme_mix($p['primary'], '#111827', 0.70),
            '--sg-border-focus'        => $p['primary'],
            '--sg-link'                => $p['primary_700'],
            '--sg-link-hover'          => $p['primary_900'],
        ];
        if (!empty($p['font_stack'])) {
            $pairs['--sg-theme-font-family'] = (string) $p['font_stack'];
            $pairs['--sg-font-display'] = (string) $p['font_stack'];
            $pairs['--sg-font-body'] = (string) $p['font_stack'];
        }
        $out = [];
        foreach ($pairs as $k => $v) {
            $vv = (string) $v;
            if (strpos((string)$k, '--sg-font') === 0 || (string)$k === '--sg-theme-font-family') {
                $out[] = $k . ':' . $vv;
                continue;
            }
            if (preg_match('/^#[0-9a-f]{6}$/i', $vv) || preg_match('/^[0-9]+,[0-9]+,[0-9]+$/', $vv)) {
                $out[] = $k . ':' . strtolower($vv);
            }
        }
        return implode(';', $out) . ';';
    }
}

if (!function_exists('sige_theme_runtime_css')) {
    function sige_theme_runtime_css(): string {
        $p = sige_theme_current_palette();
        $vars = sige_theme_css_vars($p);
        $pr = esc_attr($p['primary']);
        $p700 = esc_attr($p['primary_700']);
        $p800 = esc_attr($p['primary_800']);
        $p900 = esc_attr($p['primary_900']);
        $soft = esc_attr($p['soft']);
        $accent = esc_attr($p['accent']);
        $primary_rgb = esc_attr(sige_theme_rgb_string($p['primary']));
        $secondary_rgb = esc_attr(sige_theme_rgb_string($p['secondary']));
        $accent_rgb = esc_attr(sige_theme_rgb_string($p['accent']));

        return "
:root, body.sige-admin-app, body.sige-admin-app #sige-layout{{$vars}}
body.sige-admin-app #sige-top-progress{background:linear-gradient(90deg,var(--sg-theme-primary),var(--sg-theme-accent))!important;}
body.sige-admin-app,
body.sige-admin-app #wpwrap,
body.sige-admin-app #wpcontent,
body.sige-admin-app #sige-layout,
body.sige-admin-app .sg-product-pro-shell,
body.sige-admin-app .sg-app-content,
body.sige-admin-app .sg-app-main{font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif)!important;}
body.sige-admin-app .sg-product-pro-shell *:not(svg):not(path):not(circle):not(rect):not(line):not(polyline):not(polygon):not(g),
body.sige-admin-app .sg-product-pro-shell input,
body.sige-admin-app .sg-product-pro-shell select,
body.sige-admin-app .sg-product-pro-shell textarea,
body.sige-admin-app .sg-product-pro-shell button{font-family:var(--sg-theme-font-family,'Plus Jakarta Sans','Inter','Segoe UI',system-ui,-apple-system,BlinkMacSystemFont,sans-serif)!important;}
body.sige-admin-app .sg-product-pro-shell .sg-app-sidebar{background:linear-gradient(180deg,var(--sg-theme-primary) 0%,var(--sg-theme-primary-800) 46%,var(--sg-theme-primary-900) 100%)!important;}
body.sige-admin-app .sg-product-pro-shell .sg-app-sidebar::before{background:radial-gradient(circle at top right,rgba(255,255,255,.20),transparent 35%)!important;}
body.sige-admin-app .sg-product-pro-shell .sige-menu-item.active{color:var(--sg-theme-primary)!important;background:#fff!important;}
body.sige-admin-app .sg-product-pro-shell .sg-app-chip,
body.sige-admin-app .sg-app-topbar .sg-app-topbar-pill{background:var(--sg-theme-soft)!important;color:var(--sg-theme-primary)!important;border-color:rgba(var(--sg-theme-primary-rgb),.12)!important;}
body.sige-admin-app .sg-product-pro-shell .sg-app-hamburger{background:linear-gradient(135deg,var(--sg-theme-primary),var(--sg-theme-primary-800))!important;box-shadow:0 18px 34px rgba(var(--sg-theme-primary-rgb),.30)!important;}
body.sige-admin-app .sg-dashboard-v2,
body.sige-admin-app .sg-finpro-wrap,
body.sige-admin-app .sgcc-pro,
body.sige-admin-app .sg-classpay-wrap,
body.sige-admin-app .sg-paypro-wrap,
body.sige-admin-app .sg-finreport-wrap,
body.sige-admin-app .sg-generator-wrap,
body.sige-admin-app .sg-extracts-wrap,
body.sige-admin-app .sg-fincfg-wrap,
body.sige-admin-app .sg-plans-wrap,
body.sige-admin-app .sg-centers-wrap,
body.sige-admin-app .sg-expense-wrap,
body.sige-admin-app .sg-auditpro-wrap,
body.sige-admin-app .sg-inspro-wrap,
body.sige-admin-app .sg-alunospro-wrap,
body.sige-admin-app .sg-turmaspro-wrap,
body.sige-admin-app .sige-alunos-page,
body.sige-admin-app .sige-turmas-page,
body.sige-admin-app .sige-ap-page{--sgv2-purple:var(--sg-theme-primary)!important;--sgv2-purple-dark:var(--sg-theme-primary-800)!important;--sgv2-purple-soft:var(--sg-theme-soft)!important;--sgr-purple:var(--sg-theme-primary)!important;--sgr-purple-dark:var(--sg-theme-primary-800)!important;--sgr-purple-soft:var(--sg-theme-soft)!important;}
body.sige-admin-app .sg-v2-btn-primary,
body.sige-admin-app .sg-finpro-btn-primary,
body.sige-admin-app .sg-paypro-btn-primary,
body.sige-admin-app .sgcc-pro-btn-primary,
body.sige-admin-app .sg-classpay-action,
body.sige-admin-app .sg-extracts-btn-primary,
body.sige-admin-app .sg-finreport-btn-primary,
body.sige-admin-app .sg-generator-btn-primary,
body.sige-admin-app .sg-fincfg-btn-primary,
body.sige-admin-app .sg-plans-btn-primary,
body.sige-admin-app .sg-centers-btn-primary,
body.sige-admin-app .sg-expense-btn-primary,
body.sige-admin-app .sg-auditpro-btn-primary,
body.sige-admin-app .sg-inspro-btn-primary,
body.sige-admin-app .sg-alunospro-btn-primary,
body.sige-admin-app .sg-turmaspro-btn-primary,
body.sige-admin-app .sige-alunos-page .sige-btn-hero,
body.sige-admin-app .sige-alunos-page .sige-btn-toolbar.btn-search,
body.sige-admin-app .sige-alunos-page .sige-toolbar .btn-search,
body.sige-admin-app .sige-alunos-page .sige-page-btn.current,
body.sige-admin-app .sige-alunos-page .sige-btn-submit,
body.sige-admin-app .sige-turmas-page .sige-btn-hero,
body.sige-admin-app .sige-turmas-page .sige-btn-submit,
body.sige-admin-app .sgcc-btn-primary,
body.sige-admin-app .sige-btn-primary{background:linear-gradient(135deg,var(--sg-theme-primary),var(--sg-theme-primary-800))!important;border-color:var(--sg-theme-primary)!important;color:#fff!important;box-shadow:0 16px 32px rgba(var(--sg-theme-primary-rgb),.24)!important;}
body.sige-admin-app .sg-v2-btn-primary:hover,
body.sige-admin-app .sg-finpro-btn-primary:hover,
body.sige-admin-app .sg-paypro-btn-primary:hover,
body.sige-admin-app .sgcc-pro-btn-primary:hover{filter:brightness(.98)!important;}
body.sige-admin-app .sg-finpro-kicker,
body.sige-admin-app .sg-extracts-kicker,
body.sige-admin-app .sgcc-confirm-kicker,
body.sige-admin-app .sgcc-kicker,
body.sige-admin-app .sg-finreport-kicker,
body.sige-admin-app .sg-generator-kicker,
body.sige-admin-app .sg-fincfg-kicker,
body.sige-admin-app .sg-plans-kicker,
body.sige-admin-app .sg-centers-kicker,
body.sige-admin-app .sg-expense-kicker,
body.sige-admin-app .sg-auditpro-kicker,
body.sige-admin-app .sg-inspro-kicker{color:var(--sg-theme-primary)!important;}
body.sige-admin-app .sg-finpro-section-icon,
body.sige-admin-app .sgcc-card-icon,
body.sige-admin-app .sgcc-tab-icon,
body.sige-admin-app .sgcc-section-icon,
body.sige-admin-app .sg-extracts-modal-icon,
body.sige-admin-app .sige-mode-btn .mode-icon,
body.sige-admin-app .sg-day-icon,
body.sige-admin-app .sg-paypro-step span,
body.sige-admin-app .sg-paypro-wrap .sige-student-avatar,
body.sige-admin-app .sg-paypro-operation-card .sige-card-title svg{background:var(--sg-theme-soft)!important;color:var(--sg-theme-primary)!important;}
body.sige-admin-app .sgcc-tab.active{background:linear-gradient(135deg,var(--sg-theme-primary),var(--sg-theme-primary-800))!important;color:#fff!important;box-shadow:0 20px 46px rgba(var(--sg-theme-primary-rgb),.25)!important;}
body.sige-admin-app .sgcc-tab.active .sgcc-tab-icon{background:rgba(255,255,255,.18)!important;color:#fff!important;}
body.sige-admin-app .sg-finpro-card-head a,
body.sige-admin-app .sg-finpro-full-link,
body.sige-admin-app .sg-finpro-receipt,
body.sige-admin-app .sg-badge-purple,
body.sige-admin-app .sgcc-btn-light,
body.sige-admin-app .sgcc-btn-link,
body.sige-admin-app .sg-paypro-hints .sige-badge,
body.sige-admin-app .sg-paypro-actions-row button,
body.sige-admin-app .btn-recibo,
body.sige-admin-app .sige-btn-ghost{background:var(--sg-theme-soft)!important;color:var(--sg-theme-primary)!important;border-color:rgba(var(--sg-theme-primary-rgb),.14)!important;}
body.sige-admin-app input:focus,
body.sige-admin-app select:focus,
body.sige-admin-app textarea:focus,
body.sige-admin-app .sgcc-field input:focus,
body.sige-admin-app .sgcc-field select:focus,
body.sige-admin-app .sgcc-field textarea:focus{border-color:var(--sg-theme-primary)!important;box-shadow:0 0 0 4px rgba(var(--sg-theme-primary-rgb),.10)!important;}
body.sige-admin-app .sgcc-check input:checked + span{color:var(--sg-theme-primary)!important;}
body.sige-admin-app .sg-finpro-progress i,
body.sige-admin-app .sgcc-score-bar i,
body.sige-admin-app .sg-progress-fill{background:linear-gradient(90deg,var(--sg-theme-accent),var(--sg-theme-primary))!important;}
body.sige-admin-app .sg-finpro-ring{background:conic-gradient(var(--sg-theme-primary) calc(var(--sg-ring)*1%),#eceaf6 0)!important;}
body.sige-admin-app .sg-class-dot,
body.sige-admin-app .sg-class-bar i{background:var(--dot,var(--sg-theme-primary))!important;}
body.sige-admin-app .sige-alunos-page .sige-hero{border-color:rgba(var(--sg-theme-primary-rgb),.12)!important;background:linear-gradient(110deg,#ffffff 0%,#ffffff 46%,var(--sg-theme-soft) 100%)!important;}
body.sige-admin-app .sige-alunos-page .sige-hero-art,
body.sige-admin-app .sige-turmas-page .sige-hero-art{background:linear-gradient(135deg,rgba(var(--sg-theme-primary-rgb),.08),rgba(var(--sg-theme-primary-rgb),.18))!important;}
body.sige-admin-app .sige-alunos-page .sige-stat-card[style*='--kpi-color:#6d5dfc'],
body.sige-admin-app .sige-alunos-page .sige-stat-card[style*='--kpi-color: #6d5dfc']{--kpi-soft:var(--sg-theme-soft)!important;--kpi-color:var(--sg-theme-primary)!important;}
body.sige-admin-app .sige-turmas-page .sige-hero{border-color:rgba(var(--sg-theme-primary-rgb),.10)!important;background:linear-gradient(135deg,#fff 0%,#fbfaff 54%,var(--sg-theme-soft) 100%)!important;}
body.sige-admin-app .sige-turmas-page .sige-ocupacao-fill{background:linear-gradient(90deg,var(--sg-theme-primary),var(--sg-theme-accent))!important;}
body.sige-admin-app.sige-view-turmas .sige-modal-header h2 svg,
body.sige-admin-app.sige-view-turmas .sige-modal-close{color:var(--sg-theme-primary)!important;background:var(--sg-theme-soft)!important;}
body.sige-admin-app .sg-paypro-wrap .sige-payment-summary{background:linear-gradient(135deg,var(--sg-theme-primary-900),var(--sg-theme-primary-800) 52%,var(--sg-theme-primary))!important;box-shadow:0 26px 62px rgba(var(--sg-theme-primary-rgb),.26)!important;}
body.sige-admin-app .sg-paypro-wrap .sige-payment-total .sige-btn{background:linear-gradient(135deg,var(--sg-theme-primary),var(--sg-theme-primary-700))!important;box-shadow:0 16px 34px rgba(var(--sg-theme-primary-rgb),.24)!important;}
body.sige-admin-app .sg-paypro-wrap .sige-total-value{color:var(--sg-theme-primary)!important;}
body.sige-admin-app input[type=checkbox], body.sige-admin-app input[type=radio]{accent-color:var(--sg-theme-primary)!important;}
body.sige-admin-app .sg-dashboard-v2 .sg-fin-mini strong[style],
body.sige-admin-app [style*='#5a3fd6'],
body.sige-admin-app [style*='#5A3FD6']{color:var(--sg-theme-primary)!important;}
body.sige-admin-app .sg-theme-primary-bg{background:var(--sg-theme-primary)!important;color:#fff!important;}
body.sige-admin-app .sg-theme-primary-soft{background:var(--sg-theme-soft)!important;color:var(--sg-theme-primary)!important;}
body.sige-admin-app .sg-paypro-kicker:before,body.sige-admin-app .sg-paypro-kicker::before,body.sige-admin-app .sgcc-pro-kicker:before,body.sige-admin-app .sgcc-pro-kicker::before,body.sige-admin-app .sg-hero-kicker:before,body.sige-admin-app .sg-hero-kicker::before,body.sige-admin-app .sg-finpro-kicker:before,body.sige-admin-app .sg-finpro-kicker::before,body.sige-admin-app .sg-extracts-kicker:before,body.sige-admin-app .sg-extracts-kicker::before,body.sige-admin-app .sg-finreport-kicker:before,body.sige-admin-app .sg-finreport-kicker::before,body.sige-admin-app .sg-fincfg-kicker:before,body.sige-admin-app .sg-fincfg-kicker::before,body.sige-admin-app .sgcc-kicker:before,body.sige-admin-app .sgcc-kicker::before,body.sige-admin-app .sige-hero-kicker:before,body.sige-admin-app .sige-hero-kicker::before{display:none!important;content:none!important;box-shadow:none!important;background:transparent!important;}
";
    }
}

add_action('admin_head', function () {
    if (!is_admin()) return;
    $page = isset($_GET['page']) ? sanitize_key($_GET['page']) : '';
    if ($page !== 'sige-app') return;
    echo '<style id="sige-theme-runtime">' . sige_theme_runtime_css() . '</style>';
}, 80);

