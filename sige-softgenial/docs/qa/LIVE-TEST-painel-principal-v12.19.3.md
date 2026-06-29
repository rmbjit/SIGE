# LIVE-TEST Painel Principal v12.19.3

Objetivo: confirmar as 3 correcções de apresentação, sem regressão de números,
layout ou comportamento. Validar em staging.

## Pré-condições

- Versão do plugin: 12.19.3.
- Cache do painel: aguardar 2 min ou recarregar; Ctrl+F5.
- Entrar com um perfil que veja o painel completo (ex.: director/gestão).

## O que verificar

| # | Cenário | Esperado |
|---|---|---|
| 1 | Sub-título do herói quando há "foco operacional" por perfil | Lê "...respeitam as **permissões** do seu perfil." (com acento) |
| 2 | Cartões de foco (faixa "Prioridades rápidas"), cartão "Qualidade dos dados" | A linha pequena mostra "**documentos e encarregados**" por inteiro (até 2 linhas), sem cortar em "encarre..." |
| 3 | Restantes cartões de foco | Texto legível; altura do cartão coerente |

## Anti-regressão (tem de continuar verdadeiro)

- KPIs (Alunos Activos, Docentes, Pagamentos Hoje, Dívida Total) com os mesmos
  números de antes.
- Resumo Financeiro, Distribuição por Classe (donut), Alertas, Acessos Rápidos:
  iguais em dados e layout.
- Gráficos com legenda (Jan..mês; centro do donut com total).
- Nenhum botão/atalho partido; navegação intacta.
- Sem erros na consola; sem violações CSP de script.
- Painel responsivo: telemóvel, tablet e desktop sem sobreposições.

## Clientes a cobrir

cicasacolorida, lmuhalaze, malisa, demo, teste. Validar em pelo menos dois.
