<?php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('Forbidden'); }
/**
 * SIGE SoftGenial v12.15.20 - Gate do contrato da data no recibo.
 *
 * Bug corrigido: o recibo (individual e consolidado) imprimia data_pagamento
 * cru (carimbo de registo = hoje) em vez de respeitar a data efectiva, ao
 * contrario do extracto/KPI que ja usavam COALESCE(data_efectiva, ...). Um
 * pagamento recebido em dia anterior e registado depois saia no recibo com a
 * data de hoje, contradizendo o extracto.
 *
 * Este gate fixa o contrato:
 *  (a) existe o helper de exibicao sige_fin_recibo_data_efectiva_dia;
 *  (b) o recibo individual usa o helper sobre head->data_pagamento;
 *  (c) o recibo consolidado usa o helper sobre r->data_pagamento;
 *  (d) a nota de registo (auditoria) esta presente no recibo individual;
 *  (e) nao reaparece o date() cru das datas do recibo (regressao).
 *
 * Nao verifica formulas nem valores: a correccao e so de exibicao.
 */
$root = dirname(__DIR__);
$errors = [];

$fde = $root . '/includes/finance-data-efectiva.php';
$doc = $root . '/includes/documents-engine.php';

foreach ([$fde => 'includes/finance-data-efectiva.php', $doc => 'includes/documents-engine.php'] as $path => $label) {
    if (!is_file($path)) {
        fwrite(STDERR, "check-recibo-data-efectiva-v12-15-20: FALHOU\n- Ficheiro em falta: {$label}\n");
        exit(1);
    }
}

$srcFde = (string) file_get_contents($fde);
$srcDoc = (string) file_get_contents($doc);

// (a) Helper de exibicao existe.
if (strpos($srcFde, 'function sige_fin_recibo_data_efectiva_dia') === false) {
    $errors[] = 'Falta o helper sige_fin_recibo_data_efectiva_dia em finance-data-efectiva.php.';
}

// (b) Recibo individual usa o helper sobre o cabecalho do recibo.
if (strpos($srcDoc, 'sige_fin_recibo_data_efectiva_dia($head->data_pagamento') === false) {
    $errors[] = 'O recibo individual nao usa a data efectiva (helper sobre head->data_pagamento ausente).';
}

// (c) Recibo consolidado usa o helper por linha.
if (strpos($srcDoc, 'sige_fin_recibo_data_efectiva_dia($r->data_pagamento') === false) {
    $errors[] = 'O recibo consolidado nao usa a data efectiva por linha (helper sobre r->data_pagamento ausente).';
}

// (d) Nota de registo (auditoria) presente.
if (strpos($srcDoc, 'registado em') === false) {
    $errors[] = 'A nota de auditoria "(registado em ...)" nao esta no recibo.';
}

// (e) Regressao: date() cru das datas do recibo nao pode voltar.
if (strpos($srcDoc, "date('d/m/Y H:i', strtotime(\$head->data_pagamento))") !== false) {
    $errors[] = 'Regressao: recibo individual voltou a imprimir data_pagamento cru com date().';
}
if (strpos($srcDoc, "date('d/m/y', strtotime(\$r->data_pagamento))") !== false) {
    $errors[] = 'Regressao: recibo consolidado voltou a imprimir data_pagamento cru com date().';
}

if ($errors) {
    fwrite(STDERR, "check-recibo-data-efectiva-v12-15-20: FALHOU\n- " . implode("\n- ", $errors) . "\n");
    exit(1);
}
printf("check-recibo-data-efectiva-v12-15-20: OK - recibo individual e consolidado respeitam a data efectiva, com nota de registo, sem o date() cru anterior.\n");
