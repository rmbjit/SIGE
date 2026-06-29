# LIVE-TEST Painel Principal v12.19.4 (simplificado)

Objetivo: confirmar o painel simplificado, sem regressão de números nem de
navegação. Validar em staging.

## Pré-condições

- Versão 12.19.4. Cache do painel: aguardar 2 min ou recarregar; Ctrl+F5.
- Entrar com perfil de gestão (director) e, se possível, um perfil mais restrito.

## O que deve ver (mais simples)

| Bloco | Esperado |
|---|---|
| Herói | Uma faixa compacta: "Bem-vindo de volta, <nome>", linha de estado (Hoje, Alertas, Cobrança) e 2 botões. Sem ilustração nem parágrafo longo |
| KPIs | 4 cartões: Alunos Activos, Docentes, Pagamentos Hoje, Dívida Total |
| Resumo Financeiro | Receitas, Lançado no mês, Taxa de cobrança (números reais). **Sem gráfico de barras** |
| Distribuição por Classe | Donut com total ao centro e lista por classe |
| Alertas de Conformidade | Lista accionável |
| Acessos Rápidos | Atalhos para as áreas |

## O que deve ter DESAPARECIDO

- Faixa "Prioridades rápidas" (cartões de foco).
- Gráfico financeiro de barras (era fictício).
- "Resumo do Dia", "Checklist Operacional", "Fluxos Guiados", "Visão Operacional".
- Painel de estratégia/coaching por perfil.
- O texto "Foco operacional para Gestao... configuracoes e saude" (com erros).

## Anti-regressão (tem de continuar verdadeiro)

- KPIs com os mesmos números de antes (Alunos, Docentes, Pagamentos, Dívida).
- Resumo Financeiro com os mesmos valores (Receitas, Lançado, Taxa).
- Donut com o mesmo total e classes.
- Todos os atalhos e o botão "Registar Pagamento"/"Gerir Alunos" funcionam.
- Sem erros na consola; sem violações CSP de script.
- Responsivo: telemóvel, tablet, desktop sem sobreposições.
- Acentuação correcta em todo o painel.

## Perfis e clientes

- Confirmar que perfis restritos só veem os blocos a que têm acesso (ex.: sem
  financeiro não aparece o Resumo Financeiro nem os KPIs financeiros).
- Validar em pelo menos dois clientes (ex.: teste e cicasacolorida).
