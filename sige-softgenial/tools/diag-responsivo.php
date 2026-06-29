<?php
/**
 * diag-responsivo.php - Gate de diagnóstico responsivo (13º gate)
 *
 * Mede antipadrões que quebram a experiência em celular/tablet:
 *   A) TABELAS com min-width grande (>=700px) sem contentor overflow-x - estouram o layout em mobile
 *   B) MODAIS reais (popup/dialog) sem max-height - botões de acção podem ficar fora do ecrã
 *
 * Filosofia (igual aos outros gates): a baseline só DESCE. Regista com --set.
 * Lição da auditoria: distinguir sinal real de ruído. Cartões de conteúdo
 * NÃO são modais; tabelas com wrapper de scroll NÃO contam.
 *
 * Uso:
 *   php tools/diag-responsivo.php          (verifica contra baseline)
 *   php tools/diag-responsivo.php --set    (regista nova baseline)
 */

if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }

$root = dirname(__DIR__);
$baseline_file = $root . '/tools/.baseline-responsivo.json';
$set_mode = in_array('--set', $argv, true);

// ── Recolher ficheiros UI ──
$ficheiros = array_merge(
    glob($root . '/admin/{*,*/*}.php', GLOB_BRACE) ?: [],
    glob($root . '/includes/{*,*/*}.php', GLOB_BRACE) ?: []
);

$tabelas_estouro = [];   // [ficheiro => max_min_width]
$modais_sem_maxh = [];   // [ficheiro => [classes]]

// Classes utilitarias globais (assets/sige-utilities.css e afins) com overflow:
// reconhece guardas de scroll movidas do estilo inline para utilitarios (vaga 2 da
// Fase 4), para que um <div class="sige-u-oxa"> a embrulhar uma tabela conte como
// protegido tal como o antigo style="overflow-x:auto".
$ov_cls_global = [];
foreach (['assets/sige-utilities.css'] as $cssrel) {
    $css = @file_get_contents($root . '/' . $cssrel);
    if ($css === false) continue;
    if (preg_match_all('/\.([a-z_][a-z0-9_-]*)\s*(?:,\s*\.[a-z0-9_-]+\s*)*\{([^}]*)\}/i', $css, $gms, PREG_SET_ORDER)) {
        foreach ($gms as $gm) {
            if (preg_match('/overflow(-x)?:\s*(auto|scroll)/', $gm[2])) {
                $sel = substr($gm[0], 0, strpos($gm[0], '{'));
                if (preg_match_all('/\.([a-z_][a-z0-9_-]*)/i', $sel, $gscs)) {
                    foreach ($gscs[1] as $gsc) { $ov_cls_global[$gsc] = true; }
                }
            }
        }
    }
}

foreach ($ficheiros as $f) {
    $src = @file_get_contents($f);
    if ($src === false || strpos($src, '<style') === false) continue;
    $rel = str_replace($root . '/', '', $f);

    // ── A) Tabelas que estouram (resolve classes CSS com overflow) ──
    // min-width >= 700px + tem <table cujo wrapper NAO tem overflow auto/scroll/x
    if (strpos($src, '<table') !== false) {
        preg_match_all('/min-width:\s*(\d{3,})px/', $src, $mw);
        $grandes = array_filter(array_map('intval', $mw[1]), fn($v) => $v >= 700);
        if ($grandes) {
            // classes CSS que tem overflow auto/scroll/x (inline na view + utilitarios globais)
            $ov_cls = $ov_cls_global;
            if (preg_match_all('/\.([a-z_][a-z0-9_-]*)\s*(?:,\s*\.[a-z0-9_-]+\s*)*\{([^}]*)\}/i', $src, $cms, PREG_SET_ORDER)) {
                foreach ($cms as $cm) {
                    if (preg_match('/overflow(-x)?:\s*(auto|scroll)/', $cm[2])) {
                        $sel = substr($cm[0], 0, strpos($cm[0], '{'));
                        if (preg_match_all('/\.([a-z_][a-z0-9_-]*)/i', $sel, $scs)) {
                            foreach ($scs[1] as $sc) { $ov_cls[$sc] = true; }
                        }
                    }
                }
            }
            $linhas = explode("\n", $src);
            $desprotegida = false;
            foreach ($linhas as $i => $ln) {
                if (strpos($ln, '<table') === false) continue;
                if (strpos($ln, "'<table") !== false || strpos($ln, '"<table') !== false) continue;
                $ctx = implode("\n", array_slice($linhas, max(0, $i - 4), 5));
                if (strpos($ctx, 'document.write') !== false) continue;
                $prot = (bool) preg_match('/overflow(-x)?:\s*(auto|scroll)/', $ctx);
                if (!$prot && preg_match_all('/class\s*=\s*["\']([^"\']+)["\']/', $ctx, $clsm)) {
                    foreach ($clsm[1] as $clstr) {
                        foreach (preg_split('/\s+/', $clstr) as $tok) {
                            if (isset($ov_cls[$tok])) { $prot = true; break 2; }
                        }
                    }
                }
                if (!$prot) { $desprotegida = true; break; }
            }
            if ($desprotegida) {
                $tabelas_estouro[$rel] = max($grandes);
            }
        }
    }

    // ── B) Modais reais sem max-height ──
    // Contentor de modal = classe com 'modal'/'popup'/'dialog' (NÃO 'card'/'close'/'icon'/'title')
    // que tem width E background (é o painel visual), e não tem max-height nem overflow.
    if (preg_match_all('/(\.[a-z_-]*(?:modal|popup|dialog)[a-z_-]*)\s*\{([^}]*)\}/i', $src, $ms, PREG_SET_ORDER)) {
        foreach ($ms as $m) {
            $cls = $m[1]; $corpo = $m[2];
            // excluir elementos auxiliares (botão fechar, ícone, título, overlay/backdrop)
            if (preg_match('/(close|icon|title|overlay|backdrop|x|btn|head|footer|body|row|line)/i', $cls)) continue;
            // tem de ser o painel: width + background, e não ser largura responsiva por min()/max-width
            $tem_painel = preg_match('/\bwidth:/', $corpo) && strpos($corpo, 'background') !== false;
            $tem_responsivo = strpos($corpo, 'min(') !== false; // width:min(...) já é responsivo
            // overlay/backdrop (cobre o ecra todo) NAO e painel de conteudo
            $eh_overlay = (strpos($corpo, 'width:100%') !== false || preg_match('/width:\s*100%/', $corpo)) && (preg_match('/height:\s*100%/', $corpo) || strpos($corpo, 'position: fixed') !== false || strpos($corpo, 'position:fixed') !== false) && strpos($corpo, 'background') !== false && (strpos($corpo, 'rgba') !== false || strpos($corpo, 'backdrop') !== false);
            if ($tem_painel && !$tem_responsivo && !$eh_overlay) {
                if (strpos($corpo, 'max-height') === false && strpos($corpo, 'overflow') === false) {
                    $modais_sem_maxh[$rel][] = $cls;
                }
            }
        }
    }
}

$total = count($tabelas_estouro) + array_sum(array_map('count', $modais_sem_maxh));

// ── Baseline ──
if ($set_mode) {
    $dados = [
        'total' => $total,
        'tabelas_estouro' => count($tabelas_estouro),
        'modais_sem_maxh' => array_sum(array_map('count', $modais_sem_maxh)),
    ];
    file_put_contents($baseline_file, json_encode($dados, JSON_PRETTY_PRINT));
    echo "Baseline responsiva registada: {$total} antipadrões ";
    echo "(" . count($tabelas_estouro) . " tabelas a estourar + ";
    echo array_sum(array_map('count', $modais_sem_maxh)) . " modais sem max-height).\n";
    exit(0);
}

if (!is_file($baseline_file)) {
    echo "AVISO: sem baseline responsiva. Corra com --set primeiro.\n";
    exit(0);
}

$base = json_decode(file_get_contents($baseline_file), true);
$base_total = (int) ($base['total'] ?? PHP_INT_MAX);

if ($total > $base_total) {
    echo "RESPONSIVO FALHOU - antipadrões subiram de {$base_total} para {$total} (regressão).\n";
    echo "  Tabelas a estourar: " . count($tabelas_estouro) . "\n";
    echo "  Modais sem max-height: " . array_sum(array_map('count', $modais_sem_maxh)) . "\n";
    exit(1);
}

echo "RESPONSIVO OK - {$total} antipadrões (baseline {$base_total}; só desce).\n";
exit(0);
