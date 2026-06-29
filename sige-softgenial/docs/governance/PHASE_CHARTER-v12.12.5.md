# PHASE CHARTER v12.12.5 - Security Kernel Corrective Runtime Audit

## Objectivo
Corrigir os P1 encontrados no rediagnostico adversarial da v12.12.4 e provar que o Security Kernel tem runtime real, nao apenas cobertura documental.

## Incluido
- REST routes com route metadata explicito e matching runtime por namespace + route.
- Dispatch real para shortcode e wp_hook em modo observe.
- Configuracoes M-Pesa/e-Mola em enforcement piloto com storage tenant-scoped em wp_options por escola.
- Gates runtime/adversariais para REST, shortcode, wp_hook, tenant storage e testes negativos.
- Documentacao, evidencias QA, lint, gates e rediagnostico adversarial.

## Excluido
- MFA obrigatorio.
- Secret Vault definitivo.
- Financial Ledger.
- Lockdown total das 170 superficies em observe.
- Tenant fail-closed global.
- CSP enforcement.

## Riscos
P1 se REST continuar sem matching runtime; P1 se shortcode/wp_hook forem declarados mas nao interceptados; P1 se M-Pesa/e-Mola escreverem opcoes globais em multi-escola; P2 se observe mode continuar com pouca telemetria.

## Criterios de aceitacao
Zero P0/P1 aberto, PHP lint verde, gates oficiais verdes, testes negativos verdes, evidencia de matching REST, dispatch shortcode/wp_hook e tenant-scoped mobile payment options.
