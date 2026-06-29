# DEFINITION_OF_DONE - v12.12.11 - MFA de Operacao (Step-up)

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
