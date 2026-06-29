# RISK REGISTER - v12.12.0

## P0

Nenhum P0 comprovado nesta fase.

## P1

### P1-001 - Views allowlisted sem permissao explicita

Estado: corrigido.  
Views: `comunicacoes_central`, `financeiro-mpesa`, `presencas`, `whatsapp_central`, `whatsapp_circulares`, `whatsapp_diag`.  
Evidencia: `php tools/check-view-permission-map.php`.

## P2

- P2-001: autorizacao legada ainda espalhada por `current_user_can`. Fase responsavel: Fases 1 e 2.
- P2-002: fallbacks tenant candidatos. Fase responsavel: Fase 3.
- P2-003: opcoes sensiveis fora de Secret Vault. Fase responsavel: Fase 5.
- P2-004: dependencias externas a reduzir. Fases responsaveis: Fases 9 e 10.
- P2-005: inline JS/CSS e CSP report-only. Fase responsavel: Fase 10.

## P3

- P3-001: documentacao operacional deve ser expandida nas fases futuras.

## Risco residual

Risco residual existe, mas esta declarado, rastreado e associado a fase futura. Nenhum P0/P1 residual e aceite para encerramento desta versao.
