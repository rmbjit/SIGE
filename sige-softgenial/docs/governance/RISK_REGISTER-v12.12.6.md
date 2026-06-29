# RISK REGISTER v12.12.6

## P0
Nenhum P0 aberto apos a implementacao.

## P1
- P1 fechado: query handlers dependiam apenas de `template_redirect`.
- P1 fechado: `sige_portaria_camera` podia renderizar antes do kernel por prioridade igual.
- P1 fechado: `settings_save` nao exigia tenant no kernel e o repository podia cair para escola 1 em escrita.

## P2
- 170 superficies continuam em observe ate Critical Actions Lockdown.
- REST publico ainda precisa de enforcement token/HMAC especifico.
- Secret Vault definitivo ainda pendente.
- Tenant ownership por objecto ainda pendente para Tenant Isolation Hardening.

## P3
- Alguns documentos historicos mantem nomes legados por compatibilidade.
- Evidencias visuais reais continuam dependentes de staging.
