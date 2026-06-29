# RISK REGISTER - v12.12.8.1

Riscos residuais apos a remediacao Tenant Write Isolation. Classificacao P0/P1/P2/P3.

## P0
Nenhum. Nenhuma quebra de seguranca, tenant, financeiro, login, dados ou instalacao introduzida. Os guards so disparam com escola <= 0 (impossivel em mono-escola, onde o resolvedor devolve 1).

## P1
Nenhum. O P1 da v12.12.8 (enumeracao de escrita incompleta) fica fechado: superficie reclassificada por fluxo (Categorias A, B, C delegado), 0 sumidouros sem guard fora das 2 excecoes de logging, gate dedicado a impedir regressao.

## P2
- P2-01 Excecoes de logging escrevem com escola 0 em modo estrito sem contexto. sige_audit_log e sige_registar_log gravam o evento mesmo sem escola resolvida (decisao deliberada: nao perder o registo da anomalia). A linha fica orfa (escola 0), visivel a super-admin, invisivel as escolas. Plano: numa fase de observabilidade, marcar estes registos com flag de anomalia e alerta. Risco limitado (sao registos, nao dados de utilizador).
- P2-02 Sumidouros academicos que devolvem array de stats abortam com array minimo ok=false. sige_enc_criar_snapshot_final devolvia $stats; o aborto devolve ['ok'=>false,'message'=>...]. Um chamador que dereferencie chaves de stats sem verificar ok poderia gerar aviso, apenas em modo estrito sem contexto. Verificado no rediagnostico que os chamadores tratam o caminho de erro. Plano: confirmar em teste de integracao multi-escola futuro.

## P3
- P3-01 Resolvedor mantem fallback final SIGE_ESCOLA_MALISA em modo relaxado. Diferido (precisa tratamento de contextos publicos/cron antes de remover). Sem efeito em escrita: os guards de sumidouro ja fecham escola <= 0; em relaxado a escola e 1 (valida).
- P3-02 permissions-ui.php:70 mantem fallback para super-admin (gated). Documentado.
- P3-03 permissions-layer.php:1014 mantem fallback relaxado (gated). Documentado.
- P3-04 137 call-sites de LEITURA por tenant ainda nao endurecidos. Fora do ambito deste incremento (escrita). Risco de leitura, nao de escrita; planeado para incremento posterior.
- P3-05 Detector do gate e heuristico (regex de corpo de funcao). Pode, em teoria, marcar como guardada uma funcao com `<= 0` nao relacionado. Mitigacao: guards reais usam helpers nomeados (sige_tenant_write_guard / sige_require_escola_id), reconhecidos de forma inequivoca.
