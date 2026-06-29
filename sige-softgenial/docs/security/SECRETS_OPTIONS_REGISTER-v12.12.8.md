# SECRETS OPTIONS REGISTER - v12.12.8

## segredo
Nenhum segredo novo foi introduzido na v12.12.8 (Tenant Isolation Hardening, escritas). O endurecimento dos call-sites de escrita e o resolvedor `sige_require_escola_id` nao manipulam tokens nem credenciais.

## opcoes
Nenhuma opcao sensivel nova. As opcoes existentes continuam registadas em `SECRETS_OPTIONS_BASELINE-v12.12.8.json`, sem alteracao face a v12.12.7.1.

## Secret Vault
A fase Secret Vault universal continua planeada para migrar segredos para cofre central com rotacao, mascaramento e auditoria fina. Sem impacto neste incremento.
