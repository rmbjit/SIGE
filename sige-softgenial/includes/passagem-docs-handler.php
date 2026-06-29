<?php
/**
 * SIGE SoftGenial - Documentos Finais de Passagem (Malisa v6)
 *
 * Gera páginas HTML imprimíveis/guardáveis como PDF para:
 * - Declaração de Passagem
 * - Boletim de Passagem
 *
 * Segurança: admin-post com nonce, sem alterações na BD.
 */
if (!defined('ABSPATH')) exit;

if (!function_exists('sige_passagem_current_user_can')) {
    /** Autoriza documentos finais para perfis SIGE sem abrir o backend normal do WordPress. */
    function sige_passagem_current_user_can(): bool {
        if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
            return true;
        }
        if (function_exists('sige_user_can_any_secure')) {
            return sige_user_can_any_secure(
                ['documentos.emitir_finais', 'documentos.emitir', 'academico.boletins_emitir', 'academico.dec_emitir', 'academico.pautas_emitir'],
                ['sige_admin_ti', 'sige_admin_escola', 'sige_director', 'sige_pedagogico', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente']
            );
        }
        if ((function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options'))) return true;
        if (function_exists('sige_can')) {
            foreach (['documentos.emitir_finais', 'documentos.emitir', 'academico.boletins_emitir', 'academico.dec_emitir', 'academico.pautas_emitir'] as $perm) {
                if (sige_can($perm)) return true;
            }
        }
        foreach (['sige_admin_ti', 'sige_admin_escola', 'sige_director', 'sige_pedagogico', 'sige_secretario', 'sige_secretaria_geral', 'sige_assistente'] as $cap) {
            if (current_user_can($cap)) return true;
        }
        return false;
    }
}

if (!function_exists('sige_passagem_prepare_html_response')) {
    function sige_passagem_prepare_html_response(): void {
        if (!headers_sent()) {
            while (ob_get_level() > 0) { @ob_end_clean(); }
            nocache_headers();
            header('Content-Type: text/html; charset=UTF-8');
            header('X-Robots-Tag: noindex, nofollow', true);
            header('X-Content-Type-Options: nosniff', true);
        }
    }
}


if (!function_exists('sige_passagem_safe')) {
    function sige_passagem_safe($v, $fallback = '________________') {
        $v = trim((string)$v);
        return esc_html($v !== '' ? $v : $fallback);
    }
}

if (!function_exists('sige_passagem_data_extenso')) {
    function sige_passagem_data_extenso($date): string {
        if (!$date || $date === '0000-00-00') return '____ de __________________ de ______';
        $ts = strtotime((string)$date);
        if (!$ts) return '____ de __________________ de ______';
        $meses = [1=>'Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
        return date('j', $ts) . ' de ' . $meses[(int)date('n', $ts)] . ' de ' . date('Y', $ts);
    }
}

if (!function_exists('sige_passagem_nota_extenso')) {
    function sige_passagem_nota_extenso($n): string {
        if ($n === null || $n === '') return '__________';
        $n = (int)round((float)$n);
        $map = [0=>'Zero',1=>'Um',2=>'Dois',3=>'Três',4=>'Quatro',5=>'Cinco',6=>'Seis',7=>'Sete',8=>'Oito',9=>'Nove',10=>'Dez',11=>'Onze',12=>'Doze',13=>'Treze',14=>'Catorze',15=>'Quinze',16=>'Dezasseis',17=>'Dezassete',18=>'Dezoito',19=>'Dezanove',20=>'Vinte'];
        return $map[max(0, min(20, $n))] ?? (string)$n;
    }
}

if (!function_exists('sige_passagem_sexo_extenso')) {
    function sige_passagem_sexo_extenso($g): string {
        $g = strtoupper(trim((string)$g));
        if ($g === 'M' || $g === 'MASCULINO') return 'Masculino';
        if ($g === 'F' || $g === 'FEMININO') return 'Feminino';
        return '__________';
    }
}

if (!function_exists('sige_passagem_sigla_normalizada')) {
    function sige_passagem_sigla_normalizada($sigla): string {
        if (function_exists('sige_sigla_oficial')) return sige_sigla_oficial($sigla);
        $s = strtoupper(trim((string)$sigla));
        $s = str_replace([' ', '-', '_'], ['', '', ''], $s);
        if (strpos($s, 'MAT') === 0) return 'MAT';
        if (in_array($s, ['EDVISUAL','ED.V','EDV'], true)) return 'ED.VISUAL';
        if (in_array($s, ['EDFISICA','ED.FISICA','ED.F'], true)) return 'ED.FISICA';
        return $s;
    }
}

if (!function_exists('sige_passagem_nome_disciplina_curto')) {
    function sige_passagem_nome_disciplina_curto($nome, $sigla): string {
        $sig = sige_passagem_sigla_normalizada($sigla);
        $map = [
            'POR' => 'Português',
            'MAT' => 'Matemática',
            'CS' => 'Ciências Sociais',
            'CN' => 'Ciências Naturais',
            'ING' => 'Inglês',
            'ED.VISUAL' => 'Ed. Visual',
            'OF' => 'Ofícios',
            'ED.FISICA' => 'Ed. Física',
        ];
        return $map[$sig] ?? trim((string)$nome);
    }
}

if (!function_exists('sige_passagem_get_context')) {
    function sige_passagem_get_context($aluno_id, $ano): array {
        global $wpdb;
        $p = $wpdb->prefix;
        $eid = function_exists('sige_get_escola_id') ? (int)sige_get_escola_id() : 0;
        $escola = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sige_config WHERE escola_id=%d LIMIT 1", $eid));

        $aluno = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}sige_alunos WHERE id=%d AND escola_id=%d LIMIT 1", $aluno_id, $eid));
        if (!$aluno) wp_die('Aluno não encontrado.', 404);

        $matricula = $wpdb->get_row($wpdb->prepare(
            "SELECT m.*, t.classe, t.nivel_ensino, COALESCE(NULLIF(t.nome_turma,''), NULLIF(t.nome,''), CONCAT('Turma ', t.id)) AS nome_turma, t.turno
             FROM {$p}sige_matriculas m
             JOIN {$p}sige_turmas t ON t.id=m.turma_id
             WHERE m.aluno_id=%d AND m.ano_lectivo=%d AND m.escola_id=%d AND COALESCE(m.status_matricula,'activa') != 'cancelada'
             ORDER BY m.id DESC LIMIT 1",
            $aluno_id, $ano, $eid
        ));
        if (!$matricula) wp_die('Sem matrícula para este aluno no ano lectivo informado.', 404);

        $turma_id = (int)$matricula->turma_id;
        $classe = (string)$matricula->classe;

        // [12.11.9.15] Defesa por URL: professor só emite documentos finais de alunos das suas turmas.
        if (function_exists('sige_is_scoped_professor_user') && sige_is_scoped_professor_user()) {
            $prof_id_scope = function_exists('sige_get_professor_atual_id') ? (int) sige_get_professor_atual_id() : 0;
            if (!function_exists('sige_professor_can_access_turma') || !sige_professor_can_access_turma((int)$turma_id, $prof_id_scope, $eid)) {
                wp_die('Acesso negado. Este documento pertence a uma turma que não está atribuída ao seu perfil de professor.', 'SIGE - Documentos', ['response' => 403]);
            }
        }

        $disciplinas = $wpdb->get_results($wpdb->prepare(
            "SELECT d.id, d.nome, d.sigla, d.categoria,
                    COALESCE(mc.ordem_pauta,
                        CASE
                            WHEN UPPER(d.sigla) LIKE 'POR%%' THEN 1
                            WHEN UPPER(d.sigla) LIKE 'MAT%%' THEN 2
                            WHEN UPPER(d.sigla) IN ('CS','C.S','C.SOCIAIS') THEN 3
                            WHEN UPPER(d.sigla) IN ('CN','C.N','C.NATURAIS') THEN 4
                            WHEN UPPER(d.sigla) LIKE 'ING%%' THEN 5
                            WHEN UPPER(REPLACE(d.sigla,' ','')) IN ('ED.VISUAL','EDVISUAL','ED.V') THEN 6
                            WHEN UPPER(d.sigla) LIKE 'OF%%' THEN 7
                            WHEN UPPER(REPLACE(d.sigla,' ','')) IN ('ED.FISICA','EDFISICA','ED.F') THEN 8
                            ELSE 99
                        END
                    ) AS ordem
             FROM {$p}sige_disciplinas d
             LEFT JOIN {$p}sige_matriz_curricular mc ON mc.disciplina_id=d.id AND mc.classe=%s AND mc.escola_id=%d
             WHERE d.id IN (
                 SELECT disciplina_id FROM {$p}sige_turma_disciplinas WHERE turma_id=%d AND escola_id=%d
                 UNION
                 SELECT disciplina_id FROM {$p}sige_matriz_curricular WHERE classe=%s AND escola_id=%d
             )
             ORDER BY ordem, d.nome",
            $classe, $eid, $turma_id, $eid, $classe, $eid
        ));
        if (function_exists('sige_apply_categoria_oficial_disciplinas')) $disciplinas = sige_apply_categoria_oficial_disciplinas($disciplinas, $classe);
        if (function_exists('sige_sort_disciplinas_oficial')) $disciplinas = sige_sort_disciplinas_oficial($disciplinas);

        $notas_raw = $wpdb->get_results($wpdb->prepare(
            "SELECT disciplina_id, trimestre, nota_ac, nota_acp, nota_exame
             FROM {$p}sige_notas
             WHERE aluno_id=%d AND turma_id=%d AND escola_id=%d AND ano_lectivo=%d
               AND (status IS NULL OR status='aprovado')",
            $aluno_id, $turma_id, $eid, $ano
        ));
        $notas = [];
        foreach ($notas_raw as $n) $notas[(int)$n->disciplina_id][(int)$n->trimestre] = $n;

        $regra = function_exists('sige_get_regra_academica') ? sige_get_regra_academica($ano, (int)filter_var($classe, FILTER_SANITIZE_NUMBER_INT)) : null;
        $peso_mfd = $regra && isset($regra->peso_mfd) ? (float)$regra->peso_mfd : 60.0;
        $peso_exame = $regra && isset($regra->peso_exame) ? (float)$regra->peso_exame : 40.0;
        $fim_ciclo = function_exists('sige_is_fim_ciclo_by_turma') ? (bool)sige_is_fim_ciclo_by_turma($turma_id) : in_array((int)filter_var($classe, FILTER_SANITIZE_NUMBER_INT), [3,6], true);

        $calc_med = function($ac, $acp) { return round(((float)($ac ?? 0) + (float)($acp ?? 0)) / 2, 1); };
        $calc_mt = function($ac, $acp, $at) use ($calc_med) { return (int)round((2 * $calc_med($ac,$acp) + (float)($at ?? 0)) / 3, 0); };
        $calc_mfd = function($a,$b,$c) { return (int)round(((float)$a + (float)$b + (float)$c) / 3, 0); };
        $calc_nf = function($mfd, $exame) use ($peso_mfd, $peso_exame) {
            $den = ($peso_mfd + $peso_exame) > 0 ? ($peso_mfd + $peso_exame) : 100;
            return (int)round(((float)$mfd * $peso_mfd + (float)($exame ?? 0) * $peso_exame) / $den, 0);
        };

        $linhas = [];
        $nucleares = [];
        foreach ($disciplinas as $disc) {
            $mts = [1=>0,2=>0,3=>0];
            $exame_final = null;
            for ($t=1; $t<=3; $t++) {
                $n = $notas[(int)$disc->id][$t] ?? null;
                $ac  = ($n && $n->nota_ac !== null && $n->nota_ac !== '') ? (float)$n->nota_ac : 0;
                $acp = ($n && $n->nota_acp !== null && $n->nota_acp !== '') ? (float)$n->nota_acp : 0;
                $at  = ($n && $n->nota_exame !== null && $n->nota_exame !== '') ? (float)$n->nota_exame : 0;
                $mts[$t] = $calc_mt($ac, $acp, $at);
                if ($t === 3) $exame_final = $at;
            }
            $mfd = $calc_mfd($mts[1], $mts[2], $mts[3]);
            $nf = $fim_ciclo ? $calc_nf($mfd, $exame_final) : $mfd;
            $categoria = strtolower((string)($disc->categoria ?? ''));
            $is_nuclear = $categoria === 'nuclear';
            if ($is_nuclear) $nucleares[] = $nf;
            $linhas[] = [
                'id' => (int)$disc->id,
                'nome' => sige_passagem_nome_disciplina_curto($disc->nome ?? '', $disc->sigla ?? ''),
                'sigla' => sige_passagem_sigla_normalizada($disc->sigla ?? ''),
                'categoria' => $is_nuclear ? 'nuclear' : 'complementar',
                'mt1' => $mts[1], 'mt2' => $mts[2], 'mt3' => $mts[3],
                'mfd' => $mfd, 'nf' => $nf,
            ];
        }

        $media_global = count($nucleares) ? (int)round(array_sum($nucleares) / count($nucleares), 0) : null;
        $classe_num = (int)filter_var($classe, FILTER_SANITIZE_NUMBER_INT);
        $situacao_data = function_exists('sige_calcular_situacao_final') ? sige_calcular_situacao_final($aluno_id, $turma_id, $ano, true) : null;
        if (is_array($situacao_data) && array_key_exists('media_global', $situacao_data) && $situacao_data['media_global'] !== null) {
            $media_global = (int)round((float)$situacao_data['media_global'], 0);
        }
        $verbo = sige_passagem_situacao_label($situacao_data, $classe_num, $media_global);

        return compact('aluno', 'matricula', 'turma_id', 'classe', 'classe_num', 'ano', 'linhas', 'media_global', 'verbo', 'fim_ciclo', 'eid', 'escola', 'situacao_data');
    }
}

if (!function_exists('sige_passagem_codigo_dp')) {
    function sige_passagem_codigo_dp($ano, $escola): string {
        $yy = substr((string)(int)$ano, -2);
        $fixo = '3C3A15XX';
        $cfg = function_exists('sige_passagem_escola_val') ? sige_passagem_escola_val($escola, 'codigo_escola', '') : '';
        if (strpos($cfg, '/') !== false) {
            $parts = explode('/', $cfg, 2);
            $candidate = trim((string)($parts[1] ?? ''));
            if ($candidate !== '') $fixo = $candidate;
        }
        return 'DP' . $yy . '/' . $fixo;
    }
}

if (!function_exists('sige_passagem_situacao_label')) {
    /** Situação final dinâmica: usa o motor académico como fonte da verdade e nunca força aprovação sem base real. */
    function sige_passagem_situacao_label($situacao_data, $classe_num, $media_global): string {
        $raw = '';
        if (is_array($situacao_data)) {
            $raw = strtoupper(trim((string)($situacao_data['situacao'] ?? '')));
        } elseif (is_object($situacao_data) && isset($situacao_data->situacao)) {
            $raw = strtoupper(trim((string)$situacao_data->situacao));
        }

        $fim = in_array((int)$classe_num, [3, 6], true);

        // Regra inviolável: média global inferior a 10 nunca pode gerar PROGREDIU/TRANSITOU.
        if ($media_global !== null && is_numeric($media_global) && (float)$media_global < 10) {
            return $fim ? 'NÃO TRANSITOU' : 'NÃO PROGREDIU';
        }

        $raw_norm = strtr($raw, [
            'Ã'=>'A','Á'=>'A','À'=>'A','Â'=>'A','Ä'=>'A',
            'Õ'=>'O','Ó'=>'O','Ô'=>'O','Ö'=>'O',
            'Í'=>'I','Ì'=>'I','É'=>'E','Ê'=>'E','Ç'=>'C'
        ]);
        $raw_norm = preg_replace('/[^A-Z0-9_ ]+/', ' ', $raw_norm);
        $raw_norm = trim(preg_replace('/\s+/', ' ', $raw_norm));

        if (strpos($raw_norm, 'REPROVA') !== false
            || strpos($raw_norm, 'NAO PROGR') !== false
            || strpos($raw_norm, 'NAO_PROGR') !== false
            || strpos($raw_norm, 'NAO TRANS') !== false
            || strpos($raw_norm, 'NAO_TRANS') !== false
            || strpos($raw_norm, 'NAO APROV') !== false
        ) {
            return $fim ? 'NÃO TRANSITOU' : 'NÃO PROGREDIU';
        }

        if ($raw_norm === 'TRANSITA' || $raw_norm === 'TRANSITOU') return 'TRANSITOU';
        if ($raw_norm === 'PROGRIDE' || $raw_norm === 'PROGREDIU') return 'PROGREDIU';

        // Fallback conservador: se não houver média calculável, não inventa aprovação.
        if ($media_global !== null && is_numeric($media_global) && (float)$media_global >= 10) {
            return $fim ? 'TRANSITOU' : 'PROGREDIU';
        }
        return $fim ? 'NÃO TRANSITOU' : 'NÃO PROGREDIU';
    }
}

if (!function_exists('sige_passagem_escola_val')) {
    function sige_passagem_escola_val($escola, string $campo, string $fallback = ''): string {
        return isset($escola->$campo) && trim((string)$escola->$campo) !== '' ? trim((string)$escola->$campo) : $fallback;
    }
}


if (!function_exists('sige_passagem_turma_label')) {
    /** Normaliza o nome da turma para documentos oficiais. Ex.: "4ª Cl. - Turma B (2026)" => "Turma B". */
    function sige_passagem_turma_label($matricula): string {
        $raw = '';
        foreach (['nome_turma', 'turma_nome', 'nome'] as $campo) {
            if (is_object($matricula) && isset($matricula->$campo) && trim((string)$matricula->$campo) !== '') {
                $raw = trim((string)$matricula->$campo);
                break;
            }
        }
        if ($raw === '') return '____';
        $raw = preg_replace('/\s*\([^)]*\)\s*/u', ' ', $raw);
        $raw = preg_replace('/^\s*\d+\s*[ªaºo]?\s*(classe|cl\.?|ano)?\s*[-\--|:]\s*/iu', '', $raw);
        $raw = preg_replace('/\s*[-\--|:]\s*(manhã|manha|tarde|noite)\s*$/iu', '', $raw);
        $raw = trim(preg_replace('/\s+/', ' ', $raw));
        return $raw !== '' ? $raw : '____';
    }
}

if (!function_exists('sige_passagem_directora_titulo')) {
    /** Título profissional da directora conforme modelo oficial; evita repetir "Directora Geral" nos parênteses. */
    function sige_passagem_directora_titulo($escola): string {
        foreach (['directora_titulo_profissional', 'director_titulo_profissional', 'titulo_directora', 'titulo_director'] as $campo) {
            if (isset($escola->$campo) && trim((string)$escola->$campo) !== '') return trim((string)$escola->$campo);
        }
        $cargo = sige_passagem_escola_val($escola, 'cargo_direcao', '');
        if ($cargo && !preg_match('/directora?\s*(geral|da escola)?/iu', $cargo)) return $cargo;
        return 'Instrutora e Téc. Pedag. N1';
    }
}
if (!function_exists('sige_passagem_logo_url')) {
    /** Logo institucional da escola: mantém o logotipo do colégio no canto esquerdo e marca de água. */
    function sige_passagem_logo_url($escola): string {
        $logo = sige_passagem_escola_val($escola, 'logo_documentos_url', '');
        if (!$logo) $logo = sige_passagem_escola_val($escola, 'logo_sistema_url', '');
        return $logo;
    }
}

if (!function_exists('sige_passagem_cabecalho_oficial_url')) {
    /** Emblema/cabeçalho oficial dos documentos: campo Cabeçalho Oficial (Documentos). */
    function sige_passagem_cabecalho_oficial_url($escola): string {
        foreach (['cabecalho_oficial_url', 'cabecalho_documentos_url', 'emblema_oficial_url', 'emblema_url'] as $campo) {
            $v = sige_passagem_escola_val($escola, $campo, '');
            if ($v !== '') return $v;
        }
        return '';
    }
}

if (!function_exists('sige_passagem_doc_css')) {
    function sige_passagem_doc_css() {
        $emblem_fallback = plugin_dir_url(dirname(__FILE__)) . 'assets/img/emblem-moz-fallback.svg';
        $perfil_doc = function_exists('sige_get_escola_perfil') ? sige_get_escola_perfil() : null;
        $doc_vars = function_exists('sige_theme_document_css_vars') ? sige_theme_document_css_vars($perfil_doc) : '--sg-doc-primary:#0d2a6b;--sg-doc-primary-rgb:13,42,107;--sg-doc-primary-50:#f7f4ff;--sg-doc-primary-100:#f1edff;--sg-doc-primary-700:#0d2a6b;--sg-doc-primary-800:#0d1259;--sg-doc-primary-900:#07103f;--sg-doc-accent:#34a853;--sg-doc-ink:#202037;--sg-doc-muted:#64748b;';
        echo '<style>
        :root{' . esc_html($doc_vars) . '}*{box-sizing:border-box}html,body{margin:0;padding:0}body{font-family:"Times New Roman",Georgia,serif;color:#111;background:#e5e7eb;font-size:10.5pt}.actions{width:297mm;margin:10px auto;display:flex;gap:10px;justify-content:flex-end}.btn{background:var(--sg-doc-primary-800);color:#fff;border:0;border-radius:8px;padding:9px 14px;font-weight:700;text-decoration:none;cursor:pointer}.btn2{background:#475569}.doc-land{position:relative;background:#fff;width:297mm;height:210mm;min-height:210mm;margin:0 auto;padding:9mm 12mm;box-shadow:0 10px 30px rgba(15,23,42,.16);overflow:hidden}.doc-land:before{content:"";position:absolute;inset:5mm;border:4px dashed var(--sg-doc-primary);box-shadow:inset 0 0 0 2px #fff,inset 0 0 0 7px rgba(var(--sg-doc-primary-rgb),.12),inset 0 0 0 9px var(--sg-doc-primary);pointer-events:none}.doc-land .orn{position:absolute;width:23mm;height:23mm;border-color:var(--sg-doc-primary);z-index:2;pointer-events:none}.doc-land .orn.tl{top:5.8mm;left:5.8mm;border-top:4px solid;border-left:4px solid;border-radius:12mm 0 0 0}.doc-land .orn.tr{top:5.8mm;right:5.8mm;border-top:4px solid;border-right:4px solid;border-radius:0 12mm 0 0}.doc-land .orn.bl{bottom:5.8mm;left:5.8mm;border-bottom:4px solid;border-left:4px solid;border-radius:0 0 0 12mm}.doc-land .orn.br{bottom:5.8mm;right:5.8mm;border-bottom:4px solid;border-right:4px solid;border-radius:0 0 12mm 0}.doc-land:after{content:"";position:absolute;left:50%;top:52%;width:135mm;height:135mm;transform:translate(-50%,-50%);background:var(--wm) center/contain no-repeat;opacity:.08;pointer-events:none}.decl-content{position:relative;z-index:1;min-height:188mm;display:flex;flex-direction:column;justify-content:center;padding-top:7mm;transform:translateY(-4mm)}.decl-header{display:grid;grid-template-columns:42mm 1fr 55mm;align-items:start;gap:8mm;margin-bottom:5mm}.decl-logo img{max-width:28mm;max-height:22mm;object-fit:contain;display:block;margin:0 auto 2mm}.decl-school-box{border:1px dashed #111;text-align:center;padding:2mm 1mm;font-size:8.5pt;line-height:1.3}.decl-center{text-align:center;line-height:1.45;padding-top:1mm}.decl-emblem{width:18mm;height:18mm;margin:0 auto 1.5mm;background:url(' . esc_url($emblem_fallback) . ') center/contain no-repeat}.decl-emblem img{display:block;width:100%;height:100%;object-fit:contain}.decl-center .rep{font-weight:700;text-transform:uppercase;font-size:9.6pt}.decl-director{border:1px dashed #111;padding:4.5mm 4mm;text-align:center;font-size:9pt;min-height:26mm}.decl-sign-line{border-top:1px solid #111;margin:8mm 0 2mm}.decl-title{text-align:center;color:var(--sg-doc-primary);font-size:30pt;line-height:1;font-style:italic;font-family:"Brush Script MT","Segoe Script",cursive;margin:5mm 0 5mm;text-shadow:0 0 1px var(--sg-doc-primary-800)}.decl-p{font-size:10.4pt;line-height:1.35;text-align:justify;margin:1.5mm 0}.decl-line{display:inline-block;border-bottom:1px solid #111;min-width:45mm;text-align:center;line-height:1.1}.decl-line.long{min-width:90mm}.decl-line.natural{min-width:64mm}.decl-grades{width:100%;border-collapse:collapse;margin:3mm 0;font-size:10pt}.decl-grades td{border-bottom:1px solid #777;padding:1.2mm 2mm;vertical-align:top}.decl-grades .disc{font-style:italic;font-weight:700;width:20%}.decl-grades .val{font-style:italic;width:30%}.decl-note{border:1px solid var(--sg-doc-accent);padding:1.8mm 4mm;text-align:center;font-size:9.2pt;font-style:italic;margin:2.5mm 0 3mm}.decl-footer{display:grid;grid-template-columns:1fr 1fr;gap:22mm;align-items:end;margin-top:2mm}.decl-lines div{margin:4mm 0}.decl-secretaria{text-align:center}.small-red{color:var(--sg-doc-primary-800);font-size:9pt}.boletim-sheet{position:relative;background:#fff;width:297mm;min-height:210mm;margin:0 auto;padding:8mm;box-shadow:0 10px 30px rgba(15,23,42,.16);display:grid;grid-template-columns:1fr 0 1fr;gap:7mm}.boletim-divider{border-left:2px dashed var(--sg-doc-primary);height:100%;align-self:stretch}.boletim-copy{position:relative;border:0;padding:7mm 6mm;min-height:190mm;overflow:hidden;background:#fff}.boletim-copy:before{content:"";position:absolute;inset:0;border:4px dashed var(--sg-doc-primary);box-shadow:inset 0 0 0 2px #fff,inset 0 0 0 7px rgba(var(--sg-doc-primary-rgb),.12),inset 0 0 0 9px var(--sg-doc-primary);pointer-events:none}.boletim-copy:after{content:"";position:absolute;left:50%;top:52%;width:112mm;height:150mm;transform:translate(-50%,-50%);background:var(--wm) center/contain no-repeat;opacity:.14;pointer-events:none}.boletim-copy .corner{position:absolute;width:22mm;height:22mm;border-color:var(--sg-doc-primary);pointer-events:none}.boletim-copy .c1{top:3mm;left:3mm;border-top:4px solid;border-left:4px solid}.boletim-copy .c2{top:3mm;right:3mm;border-top:4px solid;border-right:4px solid}.boletim-copy .c3{bottom:3mm;left:3mm;border-bottom:4px solid;border-left:4px solid}.boletim-copy .c4{bottom:3mm;right:3mm;border-bottom:4px solid;border-right:4px solid}.boletim-inner{position:relative;z-index:1}.boletim-logo{text-align:center}.boletim-logo img{max-height:18mm;max-width:30mm;object-fit:contain}.boletim-school{text-align:center;font-weight:700;font-size:10pt;margin-top:1mm}.boletim-addr{text-align:center;font-size:8.2pt;line-height:1.45;margin-top:1.5mm}.boletim-title{text-align:center;color:var(--sg-doc-primary);font-size:28pt;font-style:italic;font-family:"Brush Script MT","Segoe Script",cursive;margin:8mm 0 7mm;text-shadow:0 0 1px var(--sg-doc-primary-800)}.boletim-p{font-size:11.2pt;line-height:1.55;text-align:justify;margin:4mm 0}.boletim-student{border-bottom:1px solid #111;min-height:12mm;font-weight:700;text-align:center;font-size:13pt;padding-top:2mm}.boletim-inline{display:inline-block;border-bottom:1px solid #111;min-width:28mm;text-align:center}.boletim-red{color:var(--sg-doc-primary);font-style:italic;font-weight:800}.boletim-note{margin-top:6mm;text-align:center;color:var(--sg-doc-primary-900);font-size:11pt;font-style:italic;font-weight:700;line-height:1.4}.boletim-sign{text-align:center;margin-top:7mm}.boletim-sign-line{border-top:1px solid #111;width:65mm;margin:7mm auto 2mm}.boletim-warning{text-align:center;color:var(--sg-doc-primary);font-size:8.6pt;font-weight:700;font-style:italic;margin-top:4mm}.copy-label{position:absolute;top:4mm;right:7mm;z-index:2;font:700 7.5pt Arial;color:#475569;text-transform:uppercase;letter-spacing:.05em}@media print{body{background:#fff}.actions{display:none}.doc-land,.boletim-sheet{box-shadow:none;margin:0}.doc-land{width:297mm;height:210mm;min-height:210mm;padding:8mm 11mm;page-break-after:always}.decl-content{min-height:190mm;padding-top:7mm;transform:translateY(-4mm)}.boletim-sheet{width:297mm;height:210mm;min-height:210mm;padding:7mm;gap:6mm;page-break-after:always}@page{size:A4 landscape;margin:0}}
        </style>';
    }
}

function sige_declaracao_passagem_pdf_handler() {
    if (!sige_passagem_current_user_can()) wp_die('Acesso negado para emitir Declaração de Passagem. Verifique a permissão Documentos > Emitir documentos finais.', 403);
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'sige_declaracao_passagem_pdf')) wp_die('Nonce inválido.', 403);
    $ctx = sige_passagem_get_context((int)($_GET['aluno_id'] ?? 0), (int)($_GET['ano'] ?? date('Y')));
    extract($ctx);
    $logo = sige_passagem_logo_url($escola);
    $emblema_oficial = function_exists('sige_passagem_cabecalho_oficial_url') ? sige_passagem_cabecalho_oficial_url($escola) : '';
    $nome_escola = sige_passagem_escola_val($escola, 'nome_escola', 'COLÉGIO MALISA');
    $codigo = sige_passagem_codigo_dp($ano, $escola);
    $provincia = sige_passagem_escola_val($escola, 'provincia', 'MAPUTO');
    $director = sige_passagem_escola_val($escola, 'director_nome', 'Isabel Estêvão Langa');
    $cargo = sige_passagem_directora_titulo($escola);
    $secretaria = 'Neide Laura Langa';
    $turma_label = function_exists('sige_passagem_turma_label') ? sige_passagem_turma_label($matricula) : ($matricula->nome_turma ?? '');
    $numero = $matricula->numero_aluno ?? ($aluno->numero_processo ?? '');
    $wm = $logo ? esc_url($logo) : '';
    $grade_pairs = array_chunk($linhas, 2);
    sige_passagem_prepare_html_response();
    ?><!doctype html><html lang="pt"><head><meta charset="UTF-8"><title>Declaração de Passagem - <?php echo esc_html($aluno->nome_completo); ?></title><?php sige_passagem_doc_css(); ?></head><body style="--wm:url('<?php echo esc_url($wm); ?>')">
    <div class="actions"><button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button><button class="btn btn2" onclick="history.back()">Voltar</button></div>
    <main class="doc-land"><span class="orn tl"></span><span class="orn tr"></span><span class="orn bl"></span><span class="orn br"></span><div class="decl-content">
        <section class="decl-header">
            <div class="decl-logo"><?php if($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="Logotipo"><?php endif; ?><div class="decl-school-box"><strong><?php echo esc_html($nome_escola); ?></strong><br><?php echo esc_html($codigo); ?></div></div>
            <div class="decl-center"><div class="decl-emblem"><?php if (!empty($emblema_oficial)): ?><img src="<?php echo esc_url($emblema_oficial); ?>" alt="Emblema Oficial"><?php endif; ?></div><div class="rep">República de Moçambique</div><div>Ministério da Educação e Cultura</div><div>Direcção Provincial de <?php echo esc_html($provincia); ?></div></div>
            <div class="decl-director"><div>A Directora da Escola</div><div class="decl-sign-line"></div><strong><?php echo esc_html($director); ?></strong><br><span class="small-red">(<?php echo esc_html($cargo); ?>)</span></div>
        </section>
        <h1 class="decl-title">Declaração de Passagem</h1>
        <p class="decl-p"><span class="decl-line long"><?php echo esc_html($secretaria); ?></span>, Chefe da Secretaria do Colégio em alusão, <strong><em>DECLARO</em></strong>, em cumprimento do despacho exarado em requerimento que fica arquivado na secretaria desta instituição de ensino primário que:</p>
        <p class="decl-p"><span class="decl-line long"><?php echo esc_html($aluno->nome_completo); ?></span>, do sexo <?php echo esc_html(sige_passagem_sexo_extenso($aluno->genero ?? '')); ?>, Natural da <span class="decl-line natural"><?php echo sige_passagem_safe($aluno->naturalidade ?? '', ''); ?></span>, nascido aos <?php echo esc_html(sige_passagem_data_extenso($aluno->data_nascimento ?? '')); ?>, filho de <?php echo sige_passagem_safe($aluno->nome_pai ?? ''); ?> e de <?php echo sige_passagem_safe($aluno->nome_mae ?? ''); ?>, frequentou neste colégio, no ano <strong>Lectivo de <?php echo esc_html($ano); ?></strong> a <strong><?php echo esc_html($classe); ?></strong>, <strong><?php echo esc_html($turma_label); ?></strong>, nº <strong><?php echo esc_html($numero); ?></strong>, tendo obtido a seguinte classificação por disciplina:</p>
        <table class="decl-grades"><tbody>
        <?php foreach ($grade_pairs as $pair): ?><tr><?php foreach ($pair as $l): ?><td class="disc"><?php echo esc_html($l['nome']); ?>-------------------------</td><td class="val"><?php echo esc_html(sige_passagem_nota_extenso($l['nf']) . ' (' . (int)$l['nf'] . ') Valores'); ?></td><?php endforeach; ?><?php if (count($pair) < 2): ?><td></td><td></td><?php endif; ?></tr><?php endforeach; ?>
        <tr><td class="disc"><strong>Situação Final</strong>-------------------</td><td class="val"><strong><?php echo esc_html($verbo); ?></strong></td><td class="disc"><strong>Média Global</strong> ----------------------</td><td class="val"><strong><?php echo $media_global !== null ? esc_html(sige_passagem_nota_extenso($media_global) . ' (' . (int)$media_global . ') Valores') : '__________'; ?></strong></td></tr>
        </tbody></table>
        <div class="decl-note">E por constar verdade, mandei passar a presente Declaração que autentico com assinatura e carimbo a tinta de óleo em uso neste estabelecimento de ensino.<br>Marracuene, aos <?php echo esc_html(sige_passagem_data_extenso(current_time('Y-m-d'))); ?></div>
        <section class="decl-footer"><div class="decl-lines"><div><span class="small-red">Extrai:</span>________________________________________</div><div><span class="small-red">Conferi:</span>_______________________________________</div></div><div class="decl-secretaria"><div>A Chefe da Secretaria</div><div class="decl-sign-line"></div><strong><?php echo esc_html($secretaria); ?></strong><br><em>(Técnica Superior N1)</em></div></section>
    </div></main></body></html><?php
    exit;
}

function sige_boletim_passagem_pdf_handler() {
    if (!sige_passagem_current_user_can()) wp_die('Acesso negado para emitir Boletim de Passagem. Verifique a permissão Documentos > Emitir documentos finais.', 403);
    if (!isset($_GET['_wpnonce']) || !wp_verify_nonce(sanitize_text_field($_GET['_wpnonce']), 'sige_boletim_passagem_pdf')) wp_die('Nonce inválido.', 403);
    $ctx = sige_passagem_get_context((int)($_GET['aluno_id'] ?? 0), (int)($_GET['ano'] ?? date('Y')));
    extract($ctx);
    $logo = sige_passagem_logo_url($escola);
    $nome_escola = sige_passagem_escola_val($escola, 'nome_escola', 'Colégio Malisa');
    $endereco = sige_passagem_escola_val($escola, 'endereco_escola', 'Casa n.º 406 | Q. 01 | Bairro Zintava - Distrito de Marracuene - Maputo - Província - Moçambique');
    $telefone = sige_passagem_escola_val($escola, 'telefone_oficial', '+258 84 549 4234|84 233 5015|87/84 890 3878');
    $email = sige_passagem_escola_val($escola, 'email_institucional', 'colegioejimalisa@gmail.com');
    $director = sige_passagem_escola_val($escola, 'director_nome', 'Isabel Estêvão Langa');
    $media = $media_global !== null ? (int)$media_global : '____';
    $classe_limpa = trim((string)$classe);
    $turma_label = function_exists('sige_passagem_turma_label') ? sige_passagem_turma_label($matricula) : trim((string)($matricula->nome_turma ?? ''));
    $data = sige_passagem_data_extenso(current_time('Y-m-d'));
    $wm = $logo ? esc_url($logo) : '';
    $render_copy = function($label) use ($logo,$nome_escola,$endereco,$telefone,$email,$director,$aluno,$classe_limpa,$turma_label,$ano,$verbo,$media,$data) {
        ?><section class="boletim-copy"><span class="corner c1"></span><span class="corner c2"></span><span class="corner c3"></span><span class="corner c4"></span><div class="copy-label"><?php echo esc_html($label); ?></div><div class="boletim-inner">
            <div class="boletim-logo"><?php if($logo): ?><img src="<?php echo esc_url($logo); ?>" alt="Logotipo"><?php endif; ?></div>
            <div class="boletim-school"><?php echo esc_html($nome_escola); ?></div>
            <div class="boletim-addr"><?php echo esc_html($endereco); ?><br>Cel: <?php echo esc_html($telefone); ?><br>Email.: <?php echo esc_html($email); ?></div>
            <h1 class="boletim-title">Boletim de Passagem</h1>
            <p class="boletim-p"><strong><?php echo esc_html($director); ?></strong>, Directora - Geral, declara que o(a) aluno(a)</p>
            <div class="boletim-student"><?php echo esc_html($aluno->nome_completo); ?></div>
            <p class="boletim-p">da <span class="boletim-inline"><?php echo esc_html($classe_limpa); ?></span> Classe, <span class="boletim-inline"><?php echo esc_html($turma_label !== '' ? $turma_label : '____'); ?></span>, no ano Lectivo de <span class="boletim-inline"><?php echo esc_html($ano); ?></span>, <span class="boletim-red"><?php echo esc_html($verbo); ?></span> de Classe com a <strong><em>MÉDIA GERAL</em></strong> de <span class="boletim-inline"><?php echo esc_html($media); ?></span> valores.</p>
            <div class="boletim-note">E por constar verdade, passou-se o presente boletim, que será assinado e autenticado com carimbo a tinta de óleo em uso neste estabelecimento de ensino.</div>
            <div class="boletim-sign">Directora - Geral<div class="boletim-sign-line"></div><strong>(<?php echo esc_html($director); ?>)</strong></div>
            <p class="boletim-p" style="text-align:center;margin-top:7mm">Marracuene, <?php echo esc_html($data); ?></p>
            <div class="boletim-warning">Este boletim NÃO se destina em nenhuma circunstância para efeitos de matrículas e só é válido em original.</div>
        </div></section><?php
    };
    sige_passagem_prepare_html_response();
    ?><!doctype html><html lang="pt"><head><meta charset="UTF-8"><title>Boletim de Passagem - <?php echo esc_html($aluno->nome_completo); ?></title><?php sige_passagem_doc_css(); ?></head><body style="--wm:url('<?php echo esc_url($wm); ?>')">
    <div class="actions"><button class="btn" onclick="window.print()">Imprimir / Guardar PDF</button><button class="btn btn2" onclick="history.back()">Voltar</button></div>
    <main class="boletim-sheet"><?php $render_copy('Cópia da Escola'); ?><div class="boletim-divider"></div><?php $render_copy('Cópia do Estudante'); ?></main>
    </body></html><?php
    exit;
}
