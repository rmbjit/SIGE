# Risk Register - v12.16.2

| ID | Risco | Gravidade | Estado | Mitigacao | Alternativas rejeitadas |
|---|---|---:|---|---|---|
| R-16-2-01 | Avancar para nova funcionalidade sem baseline congelado | P1 | Tratado | v12.16.1 registada como origem aprovada | Avancar directo para v12.17.0 |
| R-16-2-02 | Alteracao acidental de financeiro | P0 | Tratado | Hashes protegidos em gate | Refactor oportunista |
| R-16-2-03 | Alteracao acidental de academico | P0 | Tratado | Escopo excluido e hash gate | Ajustes misturados com readiness |
| R-16-2-04 | Permissao real alterada sem decisao institucional | P0 | Tratado | Hash de permissions-layer e security rules | Simplificacao visual em permissoes |
| R-16-2-05 | Staging validado sem lista minima | P1 | Tratado | Checklist pos-instalacao por perfil | Validacao livre e informal |
| R-16-2-06 | Mobile voltar a quebrar em fase futura | P2 | Tratado | Matriz de regressao com viewports | Teste apenas desktop |
| R-16-2-07 | Professor voltar ao loop #038;view | P1 | Tratado | Checklist e matriz com caso explicito | Validar so administrador |
| R-16-2-08 | Alunos ficar lento em fase futura | P2 | Monitorar | Registado como fase propria futura | Refactor dentro desta fase |
| R-16-2-09 | Manifesto divergir do ZIP | P3 | Tratado | Package manifest gate | Actualizacao manual sem gate |
| R-16-2-10 | Rollback improvisado | P1 | Tratado | Documento de rollback e condicoes de retorno | Decidir rollback sob pressao |

## Decisao profissional tomada
A v12.16.2 permanece pequena, reversivel e orientada a preservacao. O sistema fica preparado para evolucao futura sem alterar o nucleo aprovado.
