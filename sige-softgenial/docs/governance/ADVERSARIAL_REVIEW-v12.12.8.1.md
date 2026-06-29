# ADVERSARIAL REVIEW - v12.12.8.1

## Rediagnostico adversarial
Auditoria da propria entrega, assumindo falhas escondidas. Verificacoes executadas com evidencia real.

### (a) O gate impede regressao?
Injectados dois ficheiros temporarios em includes/ (um sumidouro com parametro de escola sem guard; uma escrita inline sem guard). O gate check-tenant-write-sinks falhou e devolveu exit 1, identificando ambos. Apos remocao, voltou a OK com exit 0. O gate bloqueia regressao de facto.

### (b) Abortos das Categorias A/B respeitam o contrato?
Verificados os chamadores dos sumidouros academicos que devolvem array:
- abertura-view.php e encerramento-view.php resolvem $_eid via sige_require_escola_id no topo (linhas 25 e 38). Logo, em modo estrito sem contexto, o handler ja faz 403 antes de chamar o sumidouro; o guard do sumidouro e defesa em profundidade e nao e alcancado por estes chamadores com escola 0.
- sige_ab_execute_opening: o chamador usa $exec['ok'] e $exec['message']; o aborto ['ok'=>false,'message'=>...] e tratado correctamente.
- sige_enc_criar_snapshot_final: detectada inconsistencia latente (o chamador faz $snapshot['snapshots'] sem verificar ['ok']). Embora inalcancavel (require a montante), o aborto foi tornado robusto para ['ok'=>false,'snapshots'=>0,'message'=>...], evitando qualquer aviso se algum dia for alcancado.
Restantes contratos confirmados por inspeccao e lint: array ok/error (fin-action, fin-familia, fin-fecho), array ok/message (acta), bool (alertas_config_set, guardian_set_consent, queue_receipt), void (associarAFamilia, alertas_auto_send, enc_marcar_reabertura), null (wpp get_contact/get_state), false (wpp contact_bump/set_state), null e WP_Error (finance-core).

### (c) Guards AJAX colocados correctamente?
Os handlers de Categoria B sao todos sige_ajax_* (e sige_salvar_horario_turma_cb), que ja usam wp_send_json_error. O aborto wp_send_json_error e consistente e termina a execucao. Guard colocado no topo de cada handler, antes do nonce; em mono-escola passa e segue para o nonce normalmente.

### (d) Excecoes de logging justificadas?
sige_audit_log e sige_registar_log ficam sem guard de bloqueio por design: auditoria/log devem registar o evento mesmo sem escola resolvida, ficando a anomalia auditada com escola 0 em vez de se perder. Documentado no TENANT_ISOLATION_REGISTER e na allowlist do gate.

### (e) Em/en-dash no codigo?
Scan python3 em todos os .php/.js/.css: 0 ficheiros de codigo com em/en-dash.

### (f) Regras de calculo intactas?
Diff de includes/finance-core.php contra a v12.12.8 entregue: conjunto de funcoes identico; apenas 2 linhas adicionadas (os guards de criar_credito_pendente e registar_pagamento_anual). md5 de sige_fin_saldo_lancamento, sige_fin_saldo_sql e sige_fin_total_bruto_sql identico ao da v12.12.8. Nenhuma regra de calculo tocada.

### (g) Superficie de escrita totalmente coberta?
Re-execucao do worklist de fluxo: Categoria A sem guard = 0; Categoria B sem guard = 2 (apenas as excecoes de logging). 46 chamadas a sige_tenant_write_guard (24 A + 22 B).

### (h) Colocacao dos guards em funcoes embrulhadas?
Inspeccao das funcoes whatsapp-recovery (dentro de if (!function_exists(...))): guard inserido dentro de cada funcao, logo apos a abertura, com o retorno correcto. Sem fuga de escopo.

### (i) Guards duplicados?
Scan de linhas adjacentes com sige_tenant_write_guard: os 3 casos sao a definicao do helper (multitenancy.php) e o codigo de deteccao do smoke, nao guards duplicados em funcoes de produto. Zero duplicacao real.

### (j) Coexistencia guard + escrita inline (Categoria B)?
Confirmado em sige_ajax_salvar_aluno: guard no topo e 4 escritas inline a seguir. Como sige_get_escola_id e estaticamente cacheada no request, o guard garante escola > 0 antes de qualquer escrita inline.

### Ambito dos ficheiros alterados
Diff de arvore contra a v12.12.8: exactamente 14 ficheiros de produto (todos na lista planeada) e 3 ficheiros raiz (sige-softgenial.php, BUILD.json, CHANGELOG.md). Nenhum ficheiro fora do escopo.

## P0
Nenhum.

## P1
Nenhum. O P1 da v12.12.8 (enumeracao de escrita incompleta) fica fechado.

## Decisao
Aprovado. Zero P0/P1 abertos. Riscos residuais (P2/P3) declarados no RISK_REGISTER e sem bloqueio de entrega. A inconsistencia latente do aborto de enc_criar_snapshot_final foi corrigida durante este rediagnostico.
