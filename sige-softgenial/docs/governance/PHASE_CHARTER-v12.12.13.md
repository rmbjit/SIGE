# Phase Charter - v12.12.13

Painel de controlo de seguranca (MFA), so super admin. Segue a Fase 4 (completa) e
torna-a operavel pelo dono, sem codigo/CLI/BD. Base: v12.12.12. Estado: entregue.

## Objectivo

Dar ao administrador WordPress real um ecra no painel para ligar e desligar os
controlos de MFA (step-up, reposicao automatica, modo estrito) e escolher os
perfis abrangidos. Acesso restrito ao ADMIN Super; o Admin IT (sige_admin_ti) e os
outros perfis nativos do SIGE nunca veem nem alcancam o ecra.

## Incluido

- Modulo novo includes/security-mfa-settings.php: ecra de definicoes "Seguranca (MFA)" sob Definicoes (menu que os perfis SIGE nem veem, por terem manage_options retirado). Mostra o estado e alterna: step-up on/off (sige_mfa_stepup), reposicao automatica on/off (sige_mfa_autoreplay), modo estrito on/off (sige_mfa_stepup_strict) e os perfis abrangidos (sige_mfa_stepup_roles). Informacao so-de-leitura do TOTP (numero de inscritos).
- Handler de gravacao admin_post:sige_mfa_settings_save com nonce, gate duro, sanitizacao e auditoria de cada alteracao, com enfase em desligar o step-up.
- Controlo de acesso pela funcao canonica sige_is_real_wp_admin_user(), em tripla camada: o ecra so e registado se for super admin real; o render e a gravacao voltam a verificar e recusam (wp_die 403); e a defesa existente que retira capacidades tecnicas aos perfis SIGE permanece.
- Secundario: em producao (fora de WP_DEBUG) deixa de EXIBIR avisos/deprecations do PHP no wp-admin (continuam no log), removendo a faixa amarela strip_tags. Opcional (opcao sige_admin_hide_php_notices).

## Excluido

- A inscricao do autenticador por utilizador mantem-se em Utilizadores. Sem alteracao a logica nem ao comportamento do MFA. Sem alteracao ao modelo de capacidades (reutiliza os helpers existentes).

## Regra de acesso (explicita)

- Permitido: administrador WordPress real / super admin (perfil administrator ou super admin de multisite), conforme sige_is_real_wp_admin_user. Se o dono tambem tiver um perfil SIGE, continua permitido (a funcao da prioridade ao administrator).
- Recusado: sige_admin_ti (Admin IT) e TODOS os outros perfis nativos do SIGE, mesmo com manage_options herdado (retirado pelo filtro de hardening, alem do gate explicito).

## Riscos

- Correccao do gate: usa-se a funcao canonica ja auditada (Fase 1/P0), com tripla verificacao. Verificado por execucao real (smoke e gate) que sige_admin_ti e recusado e o administrador real acede.
- Bloquear o dono por engano: a funcao admite administrator mesmo que ele tenha tambem um perfil SIGE.
- Auditoria: toda a alteracao de um controlo e registada (quem, o que, de->para), sobretudo desligar o step-up.

## Criterios de aceitacao

1. Ecra acessivel so ao super admin real; sige_admin_ti e outros nativos recusados no menu, no render e na gravacao.
2. Alterna e persiste os quatro controlos com sanitizacao; cada alteracao auditada.
3. Endpoint governado: manifesto 196, regra nova em observe, enforce 30; manifestIds == ruleIds.
4. Faixa de deprecation deixa de aparecer no wp-admin em producao (display off fora de WP_DEBUG; log mantido).
5. Zero em tudo: gates, lint, travessoes, versao sincronizada, raiz canonica.
