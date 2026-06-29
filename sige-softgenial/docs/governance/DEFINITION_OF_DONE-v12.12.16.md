# DEFINITION_OF_DONE - v12.12.16 - MFA de Operacao (Step-up)

Criterios de pronto do incremento 1 da Fase 4. Cada item e verificavel por gate, smoke ou execucao real.

- DoD-001: Modulo includes/security-mfa-stepup.php existe e passa php -l.
- DoD-002: As 12 primitivas sige_mfa_* estao definidas e protegidas por function_exists.
- DoD-003: O guard sige_mfa_require_step_up devolve true quando o step-up esta desligado.
- DoD-004: O guard devolve true quando o utilizador nao pertence aos perfis abrangidos.
- DoD-005: O guard bloqueia (devolve false) e emite desafio quando aplicavel e sem verificacao recente.
- DoD-006: O guard devolve true quando ha verificacao dentro da janela (SIGE_MFA_STEPUP_WINDOW).
- DoD-007: Anti-lockout: falha de wp_mail permite a operacao nessa tentativa, com registo.
- DoD-008: sige_mfa_verify_challenge com codigo correcto devolve ok e abre a janela; codigo errado devolve errado.
- DoD-009: As 6 operacoes de SIGE_FinanceActionService invocam o guard apos o guard de tenant.
- DoD-010: Os 2 handlers de credenciais (e-Mola, M-Pesa) invocam o guard apos validacao de escola.
- DoD-011: Os 2 handlers de caixa (reabrir, fechar) invocam o guard e renderizam o formulario inline.
- DoD-012: Total de 10 contextos de operacao critica protegidos.
- DoD-013: Endpoint admin_post:sige_mfa_confirm com login e nonce; resultado por transient (sem $_GET).
- DoD-014: Regra de Kernel admin_post:sige_mfa_confirm presente em modo observe, intent nonce; enforce mantem-se em 30.
- DoD-015: Versao sincronizada nas 4 fontes (cabecalho, SIGE_VERSION, SIGE_GOV_VERSION, base_version do inventario).
- DoD-016: Funcoes de calculo financeiro byte-identicas a v12.12.8.1 (sige_fin_saldo_lancamento, sige_fin_saldo_sql, sige_fin_total_bruto_sql).
- DoD-017: Baselines de tenant congelados intactos (138/139) e baseline corrente 0; zero em-dashes no codigo.
- DoD-018: Gate check-mfa-stepup e smoke smoke-mfa-stepup-v12-12-10 ligados ao corredor e verdes; todos os gates verdes.

Zero P0/P1: o incremento so e dado por pronto com rediagnostico adversarial sem achados P0 nem P1.

## Criterios adicionais da correccao (v12.12.11)

- DoD-019: As 10 operacoes renderizam o formulario de confirmacao (inline em POST, aviso em GET), sem formulario duplicado.
- DoD-020: Modo estrito opt-in (sige_mfa_stepup_strict) bloqueia a operacao quando o email falha; defeito mantem anti-lockout.
- DoD-021: Cada operacao autorizada pela janela e auditada (satisfied_window).
- DoD-022: Pacote inteiro a zero em/en-dashes (codigo e documentos); gate de release verifica documentos.

## v12.12.11 (incremento TOTP) - estado: cumprido

- TOTP-DoD-1: nucleo RFC 6238 byte-identico aos vectores de referencia. Cumprido.
- TOTP-DoD-2: QR validado celula a celula contra referencia independente (versoes 1 a 10) e por decodificacao. Cumprido.
- TOTP-DoD-3: inscricao completa (segredo cifrado, QR e segredo manual, confirmacao por codigo de teste). Cumprido.
- TOTP-DoD-4: step-up aceita TOTP ou OTP por email; inscrito nao gera email (A2 fechado para esses). Cumprido.
- TOTP-DoD-5: endpoint governado pelo Security Kernel (regra 195) e no manifesto (195 itens). Cumprido.
- TOTP-DoD-6: desligado por defeito; sem alteracao para quem nao inscrever. Cumprido.
- TOTP-DoD-7: Zero P0/P1 no rediagnostico adversarial; achados P2 aceites e documentados. Cumprido.
- TOTP-DoD-8: gate (check-mfa-totp) e smoke (smoke-mfa-totp-v12-12-11) verdes; zero travessoes em codigo e documentos. Cumprido.

## v12.12.12 (reposicao automatica) - estado: cumprido

- REP-DoD-1: descritor de uso unico capturado nas 6 operacoes de servico quando bloqueadas; nunca em config/caixa. Cumprido.
- REP-DoD-2: reposicao re-executa exactamente uma vez, com tenant e permissoes re-validados (mesmo metodo). Cumprido.
- REP-DoD-3: nao-execucao-dupla provada por execucao real (consumo atomico; segundo consumo nao dispara). Cumprido.
- REP-DoD-4: funciona com TOTP e com email; aviso de resultado ao utilizador. Cumprido.
- REP-DoD-5: desligado por defeito (opcao + kill-switch); repeticao manual intacta. Cumprido.
- REP-DoD-6: sem novo endpoint (manifesto 195); regras de Kernel inalteradas (enforce 30). Cumprido.
- REP-DoD-7: Zero P0/P1 no rediagnostico; achados P2 aceites e documentados. Cumprido.
- REP-DoD-8: gate (check-mfa-autoreplay) e smoke (smoke-mfa-autoreplay-v12-12-12) verdes; zero travessoes. Cumprido.

## v12.12.13 (painel de controlo de seguranca MFA) - estado: cumprido

- SET-DoD-1: ecra acessivel so ao super admin real; sige_admin_ti e outros nativos recusados no menu, no render e na gravacao (provado por smoke e gate em runtime). Cumprido.
- SET-DoD-2: alterna e persiste step-up, reposicao, estrito e perfis, com sanitizacao. Cumprido.
- SET-DoD-3: cada alteracao auditada via sige_security_log, com evento distinto ao desligar o step-up. Cumprido.
- SET-DoD-4: faixa de deprecation do PHP deixa de aparecer no wp-admin em producao (display off fora de WP_DEBUG; log mantido); opcional. Cumprido.
- SET-DoD-5: endpoint governado: manifesto 196, regra nova em observe, enforce 30; manifestIds == ruleIds; phpIds === jsonIds. Cumprido.
- SET-DoD-6: gate (check-mfa-settings) e smoke (smoke-mfa-settings-v12-12-13) verdes; zero travessoes. Cumprido.
- SET-DoD-7: Zero P0/P1 no rediagnostico; achados P3 aceites e documentados. Cumprido.

## v12.12.14 (Secret Vault, incremento 1) - estado: cumprido

- VAULT-DoD-1: credenciais M-Pesa e e-Mola e webhook_token cifradas em repouso; clientes recebem o valor em claro (provado por smoke). Cumprido.
- VAULT-DoD-2: cofre unificado sige_vault_seal/reveal/is_sealed com passagem de texto em claro e auto-reparacao so no admin (nunca no webhook publico). Cumprido.
- VAULT-DoD-3: registo de segredos cataloga SMTP, WhatsApp e pagamentos; gate verifica ausencia de gravacao em claro. Cumprido.
- VAULT-DoD-4: ecras de configuracao nao pre-preenchem o segredo (campos password sem value). Cumprido.
- VAULT-DoD-5: gate (check-vault) e smoke (smoke-vault-v12-12-14, 26 verificacoes) verdes; manifesto 196 inalterado (sem endpoint novo). Cumprido.
- VAULT-DoD-6: Zero P0/P1 no rediagnostico; achados P3 e decisao de rotacao adiada documentados. Cumprido.

## v12.12.15 (Ledger financeiro, incremento 1) - estado: cumprido

- LED-DoD-1: tabela append-only sige_fin_ledger criada idempotentemente; chave unica (escola_id, seq). Cumprido.
- LED-DoD-2: sige_ledger_append encadeia por HMAC (chave dos salts), serializado por escola (GET_LOCK); so insere. Cumprido.
- LED-DoD-3: sige_ledger_verify deteta adulteracao de conteudo, remocao no meio (salto) e mudanca de chave; aponta a primeira seq. Cumprido (smoke, 11 verificacoes).
- LED-DoD-4: as 6 operacoes criticas registam no ledger apos sucesso, sem tocar calculo (md5 intactos; insert_id capturado antes no bloquearMes). Cumprido.
- LED-DoD-5: ecra de integridade so super admin, so leitura. Sem endpoint novo (manifesto 196 inalterado). Cumprido.
- LED-DoD-6: gate (check-ledger) e smoke verdes; zero travessoes. Cumprido.
- LED-DoD-7: Zero P0/P1; truncagem da cauda como limite conhecido adiado para ancoragem; achados P3 documentados. Cumprido.

## v12.12.16 (patch correctivo do Ledger) - estado: cumprido

- COR-DoD-1: SCHEMA_VERSION subida; maybe_upgrade cria sige_fin_ledger em instalacoes desactualizadas (dbDelta idempotente). Cumprido.
- COR-DoD-2: guard sige_ledger_table_exists() aplicado a escritor, verificador, lista e ecra; sem tabela degrada sem erro cru. Cumprido (smoke, 14 verificacoes).
- COR-DoD-3: gate reforcado (exige SCHEMA_VERSION subida e guard aplicado); smoke e corredor verdes. Cumprido.
- COR-DoD-4: sem regressao; md5 das regras de calculo intactos; manifesto 196 inalterado. Cumprido.
