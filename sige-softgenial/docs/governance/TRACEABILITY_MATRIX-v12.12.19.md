# TRACEABILITY_MATRIX - v12.12.19 - MFA de Operacao (Step-up)

Rastreio de cada criterio de seguranca (SK) ate a evidencia que o comprova.

| ID | Criterio | Evidencia |
|---|---|---|
| SK-001 | Modulo MFA carregado apos o escudo de login | sige-softgenial.php require de includes/security-mfa-stepup.php; gate check-mfa-stepup |
| SK-002 | 12 primitivas definidas | smoke-mfa-stepup-v12-12-10 (12 primitivas definidas) |
| SK-003 | Step-up desligado por defeito | sige_mfa_stepup_enabled le option sige_mfa_stepup com defeito off |
| SK-004 | Desactivacao de emergencia | SIGE_MFA_STEPUP_OFF curto-circuita sige_mfa_stepup_enabled |
| SK-005 | Guard permite quando desligado | smoke (desligado: guard permite) |
| SK-006 | Guard permite fora do perfil | smoke (fora do perfil: permite) |
| SK-007 | Guard bloqueia fail-closed | smoke (no perfil, nao verificado: BLOQUEIA) |
| SK-008 | Janela de verificacao recente | smoke (verificacao recente: permite) |
| SK-009 | Anti-lockout de SMTP | smoke (email falha: permite) |
| SK-010 | Verificacao de codigo (ok/errado) | smoke (verify codigo certo/errado) |
| SK-011 | 6 operacoes de servico protegidas | check-mfa-stepup (contextos fin_*) |
| SK-012 | 2 handlers de credenciais protegidos | check-mfa-stepup (cfg_emola, cfg_mpesa) |
| SK-013 | 2 handlers de caixa protegidos | check-mfa-stepup (caixa_reabrir, caixa_fechar) |
| SK-014 | 10 contextos protegidos no total | check-mfa-stepup (10 operacoes criticas) |
| SK-015 | Endpoint de confirmacao com nonce | regra de Kernel admin_post:sige_mfa_confirm intent nonce |
| SK-016 | Regra de Kernel em observe; enforce=30 | check-security-kernel-rules (enforce=30) |
| SK-017 | Calculo intacto e baselines congelados | smoke (calculo intacto; baselines 138/139); check-tenant-fallbacks |
| SK-018 | Sem em-dashes; versao sincronizada; gates verdes | smoke-release-gate; run-gates |

Evidencia consolidada: a execucao de tools/run-gates.php cobre todos os criterios acima; o smoke dedicado fornece a verificacao de runtime do comportamento fail-closed.

## v12.12.11 (incremento TOTP)

- SK-TOTP-1 (nucleo RFC 6238): Evidencia: tools/check-mfa-totp.php e tools/smoke-mfa-totp-v12-12-11.php (vectores RFC).
- SK-TOTP-2 (QR correcto): Evidencia: validacao celula a celula contra referencia (versoes 1 a 10) e decodificacao por leitor real.
- SK-TOTP-3 (inscricao com confirmacao): Evidencia: ciclo de inscricao no smoke (guardar, nao inscrito, confirmar errado, confirmar certo, inscrito, desactivar).
- SK-TOTP-4 (step-up aceita TOTP, inscrito sem email): Evidencia: integracao no smoke (issue_challenge sem email, verify ok/errado, tecto esgotado).
- SK-TOTP-5 (governanca): Evidencia: regra 195 no kernel e no manifesto; gate de regras verde.

## v12.12.12 (reposicao automatica)

- SK-REP-1 (captura nas 6 operacoes): Evidencia: tools/check-mfa-autoreplay.php e smoke (6 capturas em fin-action-service.php).
- SK-REP-2 (uso unico, sem execucao dupla): Evidencia: smoke (segundo consumo devolve null, contador fica em 1).
- SK-REP-3 (re-validacao de tenant e permissao): Evidencia: sige_tenant_write_guard e self::userCan dentro de cada metodo re-executado.
- SK-REP-4 (gating): Evidencia: opcao sige_mfa_autoreplay e kill-switch SIGE_MFA_AUTOREPLAY_OFF; smoke do caminho off.
- SK-REP-5 (sem novo endpoint): Evidencia: manifesto mantem-se em 195; gate de regras verde.

## v12.12.13 (painel de controlo de seguranca MFA)

- SK-SET-1 (acesso so super admin): Evidencia: smoke e gate (Admin IT recusado, administrator aceite) com o gate real sige_is_real_wp_admin_user.
- SK-SET-2 (CSRF): Evidencia: check_admin_referer e wp_nonce_field('sige_mfa_settings').
- SK-SET-3 (auditoria de alteracoes): Evidencia: sige_security_log mfa_settings_change e mfa_stepup_disabled no handler.
- SK-SET-4 (endpoint governado): Evidencia: manifesto 196 e regra admin_post:sige_mfa_settings_save (observe); gate de regras verde.
- SK-SET-5 (sem superficie nova alem do endpoint): Evidencia: sem $_GET prefixado sige_; admin_menu nao e superficie rastreada.

## v12.12.14 (Secret Vault, incremento 1)

- SK-VAULT-1 (cifra em repouso): Evidencia: smoke (valor guardado cifrado, difere do claro) e gate; helper de pagamentos sela as chaves-segredo.
- SK-VAULT-2 (transparencia): Evidencia: leitura revela; cliente recebe o valor em claro; webhook valida.
- SK-VAULT-3 (compatibilidade): Evidencia: passagem de texto em claro; auto-reparacao so no admin.
- SK-VAULT-4 (registo auditavel): Evidencia: sige_vault_secret_registry cataloga pagamentos, SMTP e WhatsApp; gate verifica ausencia de gravacao em claro.
- SK-VAULT-5 (redaccao): Evidencia: campos de segredo sao password sem value pre-preenchido.

## v12.12.15 (Ledger financeiro, incremento 1)

- SK-LED-1 (imutabilidade): Evidencia: HMAC encadeado; smoke deteta adulteracao de conteudo.
- SK-LED-2 (remocao/insercao no meio): Evidencia: sequencia + prev_hash; smoke deteta salto.
- SK-LED-3 (forja exige chave): Evidencia: hash depende da chave dos salts; smoke confirma.
- SK-LED-4 (cobertura): Evidencia: 6 operacoes criticas instrumentadas (gate verifica os 6 eventos).
- SK-LED-5 (nao quebra operacao): Evidencia: append apos sucesso, try/catch, function_exists.
- SK-LED-6 (so leitura/super admin): Evidencia: ecra gated por sige_is_real_wp_admin_user, sem POST.

## v12.12.16 (patch correctivo do Ledger)

- COR-1 (criacao da tabela): Evidencia: SCHEMA_VERSION subida; gate falha se ficar no valor antigo com a tabela adicionada.
- COR-2 (resiliencia sem tabela): Evidencia: sige_ledger_table_exists nos 4 pontos; smoke prova append=false, verify vazio, lista vazia, sem erro.
- COR-3 (sem regressao): Evidencia: corredor 56/56; md5 intactos; manifesto 196.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- I2-1 (pagamentos no ledger): Evidencia: sige_ledger_append('fin_registar_pagamento') no choke point; gate verifica.
- I2-2 (cobertura do anual): Evidencia: o anual delega em sige_fin_registar_pagamento (sem duplicar).
- I2-3 (truncagem da cauda): Evidencia: ancora externa; smoke deteta truncagem.
- I2-4 (adulteracao da cabeca): Evidencia: hash da ancora confrontado; smoke deteta divergencia.
- I2-5 (ancora ausente): Evidencia: estado distinto, nao fatal; smoke confirma sem falso positivo.
- I2-6 (resiliencia/atomicidade): Evidencia: temp+rename dentro do bloqueio; best-effort.

## v12.12.18 (Ledger incr 3: lancamentos)

- I3-1 (criacao de cobranca): Evidencia: sige_ledger_record_charge('fin_criar_lancamento') no ponto criado; gate verifica.
- I3-2 (alteracao de valor): Evidencia: sige_ledger_record_charge('fin_actualizar_lancamento') no ponto actualizado.
- I3-3 (escrita em bloco): Evidencia: sige_ledger_append_many com um bloqueio e uma ancora; smoke encadeia ao genesis e entre si.
- I3-4 (diferimento): Evidencia: buffer + flush no shutdown; smoke confirma que acumula sem gravar e grava no flush.
- I3-5 (mistura imediato/diferido): Evidencia: smoke confirma a mesma cadeia integra.
- I3-6 (sem tocar calculo/gerador): Evidencia: md5 intactos; sem cirurgia no gerador.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- I4-1 (despesa criada): Evidencia: fin_criar_despesa no ponto de criacao; gate verifica.
- I4-2 (despesa transitada): Evidencia: fin_despesa_transitar apos a transicao.
- I4-3 (credito criado): Evidencia: fin_criar_credito apos sucesso.
- I4-4 (fecho fechado): Evidencia: fin_fechar_turno apos a insercao (fecho_id capturado antes).
- I4-5 (fecho reaberto): Evidencia: fin_reabrir_turno apos a reabertura.
- I4-6 (cadeia integra): Evidencia: smoke confirma os 5 tipos numa cadeia integra com ancora confirmada.
