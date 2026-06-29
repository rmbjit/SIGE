# PHASE CHARTER - SIGE SoftGenial v12.12.0

Fase: Fase 0 - Governacao de engenharia  
Versao base: 12.11.9.166  
Versao alvo: 12.12.0 - Engineering Governance Baseline  
Data: 2026-06-16

## Objectivo

Criar a fundacao formal de controlo de qualidade, inventario tecnico, matriz de rastreabilidade, registos de risco e gates automatizados para impedir entregas incompletas, regressao silenciosa e encerramento de fases sem evidencia.

## Incluido no escopo

1. Inventario reproduzivel da superficie tecnica do plugin.
2. Documentos formais de governacao em `docs/governance/`.
3. Registos de seguranca em `docs/security/`.
4. Gates de governacao em `tools/` e integracao no corredor `tools/run-gates.php`.
5. Correcao do P1 identificado: views allowlisted sem permissao explicita no mapa de views, com registry proprio e migracao aditiva de permissoes.
6. Baseline congelado de autorizacao legada, fallbacks tenant, opcoes sensiveis e dependencias externas.
7. Actualizacao de metadados de release para v12.12.0.

## Excluido do escopo

1. MFA definitivo.
2. Security Kernel enforcement completo.
3. Financial Ledger.
4. Secret Vault completo.
5. Tenant isolation fail-closed total.
6. CSP enforcement.
7. Refactor modular.
8. Exportacoes assincronas.
9. Observabilidade completa.

Estes itens ficam registados como fases futuras. Esta versao nao deve fingir que resolveu o que apenas inventariou.

## Riscos

- P1: documentos sem gate real. Mitigacao: cada documento essencial tem gate.
- P1: manifesto incompleto. Mitigacao: gate compara manifesto contra codigo activo.
- P1: view allowlisted sem permissao. Mitigacao: gate dedicado `check-view-permission-map.php`.
- P2: baseline confundido com correcao definitiva. Mitigacao: registos declaram fase futura responsavel.
- P2: aumento de divida tecnica sem deteccao. Mitigacao: gates congelam baselines.

## Criterios de aceitacao

1. 0 views allowlisted sem permissao explicita.
2. Manifesto cobre a superficie activa de acoes.
3. Endpoints publicos documentados.
4. Baselines de autorizacao, tenant, opcoes e hosts externos criados.
5. Lint PHP completo verde.
6. `tools/run-gates.php` verde com gates antigos e novos.
7. Rediagnostico adversarial com P0 aberto = 0 e P1 aberto = 0.
