# LIVE-TEST Painel Principal v12.19.5

Objetivo: confirmar que o herói do painel ficou realmente compacto e os cartões
coerentes, e que NADA mudou nos outros ecrãs que partilham `.sg-dash-hero`.

## Pré-condições

- Versão 12.19.5. Ctrl+F5; aguardar 2 min (cache do painel) se preciso.

## Dashboard (deve mudar)

| Verificar | Esperado |
|---|---|
| Altura do herói | Compacto (ajusta ao conteúdo), não a barra alta de antes |
| Brilhos no herói | Sem manchas/brilhos decorativos de fundo |
| Título "Bem-vindo de volta…" | Tamanho contido (~24px), não enorme |
| Cartões grandes (Financeiro, Distribuição, Alertas, Acessos) | Sombra leve, igual à dos cartões KPI (aspecto coerente) |

## Outros ecrãs (NÃO devem mudar)

| Ecrã | Esperado |
|---|---|
| Painel Financeiro, Alunos, Turmas, Equipa (RH) | Herói grande de sempre, inalterado (a correcção é só do dashboard) |

## Anti-regressão

- KPIs e números do painel inalterados.
- Sem erros de consola; sem violações CSP.
- Responsivo (telemóvel/tablet/desktop) sem sobreposições.

## Clientes

Validar em pelo menos dois (ex.: teste e cicasacolorida).
