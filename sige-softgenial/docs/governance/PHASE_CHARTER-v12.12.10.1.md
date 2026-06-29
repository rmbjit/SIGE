# PHASE_CHARTER - v12.12.10.1 - MFA de Operacao (Step-up)

Fase 4 (MFA critico), incremento 1. Base validada: v12.12.10 (Tenant Read Isolation, fim da Fase 3).

## Objectivo

Exigir um segundo factor (re-autenticacao por OTP) imediatamente antes de executar operacoes criticas que movem dinheiro ou alteram credenciais de pagamento. O 2FA de login (escudo) ja protege a porta de entrada; este incremento protege a operacao sensivel em si, reutilizando as primitivas OTP existentes (sige_otp_issue, sige_otp_verify, sige_otp_pending). Modelo de janela: apos confirmar uma vez, abre-se um periodo curto (SIGE_MFA_STEPUP_WINDOW, defeito 300s) durante o qual as operacoes criticas prosseguem sem novo codigo.

## Incluido

- Modulo includes/security-mfa-stepup.php com 12 primitivas: sige_mfa_stepup_enabled, sige_mfa_stepup_roles, sige_mfa_applies_to_user, sige_mfa_window_key, sige_mfa_recently_verified, sige_mfa_mark_verified, sige_mfa_pending, sige_mfa_send_challenge_email, sige_mfa_issue_challenge, sige_mfa_verify_challenge, sige_mfa_require_step_up (o guard fail-closed) e sige_mfa_render_challenge_form.
- 10 pontos de operacao critica protegidos pelo guard: 6 metodos da camada de servico SIGE_FinanceActionService (cancelLancamento, isentarLancamento, reactivarLancamento, bloquearMes, desbloquearMes, estornarPagamento - este ultimo cobre tambem a anulacao de recibo), 2 handlers de credenciais (sige_emola_guardar_config, sige_mpesa_guardar_config) e 2 handlers de caixa (reabrir, fechar).
- Endpoint admin_post:sige_mfa_confirm (login + nonce) para submeter o codigo; resultado conduzido por transient (sem query string).
- Desligado por defeito (opt-in sige_mfa_stepup = on); desactivacao de emergencia SIGE_MFA_STEPUP_OFF; perfis configuraveis (defeito sige_director, sige_admin_ti); anti-lockout (falha de SMTP permite a operacao). Tudo auditado via sige_security_log.
- Governacao: gate tools/check-mfa-stepup.php, smoke tools/smoke-mfa-stepup-v12-12-10.php, regra de Kernel admin_post:sige_mfa_confirm em modo observe, 12 documentos versionados, 7 baselines JSON.

## Excluido (incrementos seguintes)

- TOTP / aplicacao autenticadora e entrega por SMS.
- Step-up nas restantes operacoes enforce que nao movem dinheiro nem alteram credenciais.
- Reposicao automatica da operacao apos confirmacao (no incremento 1 o utilizador repete a operacao manualmente).
- Revisao do 2FA de login.

## Riscos

- Lock-out: mitigado por opt-in, desactivacao de emergencia e anti-lockout de SMTP; uma falha so impede a operacao critica, nunca o login nem o resto do sistema.
- UX de intercepcao: o formulario do codigo aparece via aviso administrativo e, na caixa, inline (por ordenacao do admin_notices).
- Regressao de calculo: nenhuma funcao de calculo financeiro ou academico e tocada.

## Criterios de aceitacao

- O guard bloqueia (fail-closed) a operacao critica quando ligado, aplicavel ao perfil e sem verificacao recente; permite quando desligado, fora do perfil, recentemente verificado ou em falha de SMTP.
- Reutiliza as primitivas OTP; nao reimplementa criptografia.
- Desligado por defeito; desactivacao de emergencia funcional.
- Gate e smoke verdes; regra de Kernel presente em observe; enforce mantem-se em 30.
- Calculo intacto; baselines de tenant congelados (138/139) e baseline corrente 0.
- Rediagnostico adversarial: Zero P0/P1.

## Nota de correccao (v12.12.10.1)

Esta versao incorpora as correccoes do rediagnostico adversarial da v12.12.10, na mesma sessao (regra: zero em tudo antes de avancar). Acrescenta a primitiva sige_mfa_stepup_strict (13 no total), torna o formulario de confirmacao uniforme nas 10 operacoes (inline em POST, aviso em GET, sem duplicacao), audita cada operacao autorizada pela janela, e deixa o pacote inteiro a zero em/en-dashes (com novo controlo no gate de release sobre documentos). Criterios de aceitacao adicionais: A1, A2, A5 e A7 fechados; A3, A4 e A6 documentados como design/mitigacao.
