# QA - SIGE SoftGenial v12.12.13 (Painel de controlo de seguranca MFA, so super admin)

Execucao real, com o gate de acesso REAL (sige_is_real_wp_admin_user carregado de
security-hardening.php). Base: v12.12.12. Regra: zero em tudo antes de avancar.

## 1. Controlo de acesso (o requisito central)

- ADMIN Super (perfil administrator): acede. Verificado.
- Admin IT (sige_admin_ti): RECUSADO. Verificado em runtime (smoke e gate).
- Outros nativos do SIGE (sige_director, sige_financeiro, sige_secretario, ...): recusados. Verificado.
- Dono com administrator + perfil SIGE: acede (administrator tem prioridade). Verificado.
- Sem sessao: recusado. Verificado.
- Tripla camada: o ecra so e registado se super admin; o render e a gravacao recusam com wp_die 403; o filtro de hardening retira manage_options aos perfis SIGE.

## 2. Gravacao

- Handler admin_post:sige_mfa_settings_save com check_admin_referer('sige_mfa_settings') (nonce) e gate sige_mfa_settings_can_manage() antes de qualquer gravacao.
- Persiste sige_mfa_stepup, sige_mfa_autoreplay, sige_mfa_stepup_strict e sige_mfa_stepup_roles. Checkbox ausente vale off; perfis filtrados ao conjunto permitido (verificado).
- Post vazio: tudo off e perfis vazios (verificado).

## 3. Auditoria

- Cada alteracao gera sige_security_log('mfa_settings_change', de->para).
- Desligar o step-up gera um evento distinto sige_security_log('mfa_stepup_disabled').

## 4. Avisos do PHP em producao

- Em producao (fora de WP_DEBUG) e com a opcao sige_admin_hide_php_notices on (defeito), a exibicao de avisos no wp-admin e desligada (display_errors), mantendo o log. Remove a faixa strip_tags. Sem hook proprio (nao cria superficie nova).

## 5. Integridade e governanca

- Endpoint governado: manifesto 196 itens (195 -> 196); regra admin_post:sige_mfa_settings_save em observe; enforce mantem-se em 30; manifestIds == ruleIds; phpIds === jsonIds.
- Sem $_GET prefixado sige_ no modulo (evita superficie nao governada); admin_menu nao e superficie rastreada.
- Sem alteracao de regras de calculo: md5 de sige_fin_saldo_lancamento, sige_fin_saldo_sql e sige_fin_total_bruto_sql inalterados.
- 12 documentos de governanca e baselines presentes para v12.12.13 (gate verde).
- Versao sincronizada nas 3 fontes (cabecalho, SIGE_VERSION, BUILD.json) em 12.12.13; SIGE_GOV_VERSION em 12.12.13.
- Zero travessoes em codigo e documentos. Raiz com os 7 ficheiros canonicos. Lint PHP limpo.

## 6. Gates

- check-mfa-settings.php: OK. smoke-mfa-settings-v12-12-13.php: 14 verificacoes (gate de acesso real), todas verdes.
- Corredor completo tools/run-gates.php: verde (ver registo da entrega).

## 7. Rediagnostico adversarial

- Zero P0, zero P1. Acesso indevido, POST directo, CSRF e desligar sem rasto: mitigados e verificados. Dois achados P3 aceites e documentados (A-S6 supressao de display; A-S7 perfis vazios). Ver ADVERSARIAL_REVIEW-v12.12.13.md.
