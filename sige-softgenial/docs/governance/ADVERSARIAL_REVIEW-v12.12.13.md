# Rediagnostico adversarial - v12.12.13 (painel de controlo de seguranca MFA)

Base: v12.12.12. Alvo: o ecra de definicoes e o endpoint de gravacao, com foco no
controlo de acesso (so super admin) e na auditoria. Metodo: leitura adversarial e
verificacao por execucao real, com o gate REAL sige_is_real_wp_admin_user
carregado (smoke com 14 verificacoes e gate proprio, ambos verdes).

## Resultado

Zero defeitos P0 e zero defeitos P1. O requisito central (so o ADMIN Super acede;
Admin IT e outros nativos do SIGE sao recusados) esta provado em runtime. Achados
residuais sao decisoes documentadas.

## Modelo de ameaca e analise

### A-S1 Perfil SIGE a aceder ao ecra (P0 candidato) - MITIGADO E VERIFICADO
Tripla camada: o ecra so e registado se sige_is_real_wp_admin_user(); o render e a
gravacao voltam a verificar e recusam com wp_die 403; e o filtro de hardening
retira manage_options aos perfis SIGE. Verificado: sige_admin_ti e sige_director
sao recusados pelo gate em runtime; administrator (mesmo com perfil SIGE
adicional) acede. Sem defeito.

### A-S2 POST directo ao endpoint por perfil SIGE (P0 candidato) - MITIGADO
O handler sige_mfa_settings_handle_save comeca por is_user_logged_in() e
sige_mfa_settings_can_manage() e recusa com wp_die 403 caso contrario, antes de
qualquer gravacao. Mesmo um perfil SIGE com manage_options herdado (que o filtro
ja retira) e barrado. Verificado que o handler chama o gate. Sem defeito.

### A-S3 CSRF na gravacao (P1 candidato) - MITIGADO
check_admin_referer('sige_mfa_settings') exige nonce valido. Formulario emite
wp_nonce_field('sige_mfa_settings'). Sem defeito.

### A-S4 Desligar o step-up sem rasto (P1 candidato) - MITIGADO
Cada alteracao gera sige_security_log('mfa_settings_change', de->para). Desligar o
step-up gera ainda um evento distinto sige_security_log('mfa_stepup_disabled').
Auditavel. Sem defeito.

### A-S5 Coerencia da regra de Kernel - COERENTE
Regra admin_post:sige_mfa_settings_save em observe, modulo sistema, legacy_caps
apenas ['administrator'] (sem perfis SIGE). A enforcement real e o gate do handler.
Manifesto 196 == regras 196; enforce mantem-se em 30. Coerente.

### A-S6 Supressao de display de avisos do PHP (P3) - DECISAO: ACEITE
So suprime a EXIBICAO (nao o registo), so no wp-admin, so fora de WP_DEBUG, e e
opcional (sige_admin_hide_php_notices). Erros fatais continuam a ir para o log. E
o comportamento correcto de producao (nao expor caminhos/erros aos utilizadores) e
remove a faixa strip_tags do nucleo do WordPress. Nao defeito.

### A-S7 Perfis abrangidos vazios com step-up ligado (P3) - DECISAO: ACEITE
Se o super admin desmarcar todos os perfis com o step-up ligado, o step-up nao
abrange ninguem (equivale a desligado). Respeita-se a intencao explicita do super
admin; a UI avisa disso. Nao defeito.

### A-S8 Fuga do ecra por menu visivel a perfis SIGE - COERENTE
O ecra esta sob Definicoes (options page), que os perfis SIGE nem veem
(manage_options retirado), e so e registado para o super admin. Sem fuga.

## Conclusao

Zero P0 e zero P1. O controlo de acesso esta provado em runtime com o gate real.
Os achados P3 (A-S6, A-S7) sao decisoes aceites e documentadas. Pronto a entregar
como v12.12.13.
