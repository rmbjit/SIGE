# Riscos Residuais - v12.16.0 RC5

## Resumo

Nao ha P0/P1 local aberto depois do RC5. Os riscos residuais sao de validacao humana, staging, mobile e dados reais. Eles nao impedem RC local, mas impedem ZIP final sem evidencia.

| Codigo | Risco residual | Prioridade | Estado | Evidencia necessaria |
|---|---|---:|---|---|
| RR-16-01 | Browser autenticado nao executado neste ambiente | P2 | Pendente | Capturas ou relato validado em staging |
| RR-16-02 | Mobile real nao executado neste ambiente | P2 | Pendente | Teste em telemovel/tablet com dashboard, menu e portaria |
| RR-16-03 | Dados reais da escola nao executados neste ambiente | P2 | Pendente | Confirmacao de sinais operacionais com dados reais ou copia de staging |
| RR-16-04 | Aceitacao humana da utilidade operacional | P1 | Pendente | Utilizador valida se a rotina ficou mais clara por perfil |

## Regra de bloqueio

Qualquer RR que revele acesso indevido, erro financeiro, erro academico, regressao de portaria ou lentidao critica deve ser promovido para P0/P1 e bloquear ZIP final.
