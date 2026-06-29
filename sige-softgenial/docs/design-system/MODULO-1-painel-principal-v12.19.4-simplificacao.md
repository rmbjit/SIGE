# Módulo 1 - Painel Principal - Simplificação (v12.19.4)

Versão: 12.19.4
Data: 2026-06-29
Mandato: "analisar tudo no módulo dashboard e refazer, removendo tudo o que está
demais". Painel simples, fácil de usar, com menos distracções.
Decisões herdadas: marca roxa; prefixo `sgk-`/`sg-`; só apresentação.

---

## Deep read: o que o painel tinha a mais

O Painel Principal acumulava **duas camadas de "inteligência"/coaching** e vários
blocos que repetiam os mesmos números de formas diferentes:

| Bloco | Problema (óptica do utilizador) |
|---|---|
| Herói extenso + ilustração de escola desenhada a CSS | Ocupava muito espaço, empurrava os indicadores para baixo; ilustração com ar de placeholder |
| Sub-título "Foco operacional para Gestao: ... configuracoes e saude institucional" | Texto longo e **sem acentos** (vinha de `institutional-product-map.php`, sem passar pelo corrector) |
| Faixa "Prioridades rápidas" (3 cartões) | Repetia KPIs e Alertas |
| Gráfico financeiro de barras (Jan..mês) | **Dados fictícios** (4 barras fixas, só a última real): induz em erro |
| "Resumo do Dia" | Repetia KPIs (pagamentos, matrículas, turmas) |
| "Checklist Operacional", "Fluxos Guiados", "Visão Operacional" | Camada de coaching por perfil; muito texto, distrai de um pulso rápido |
| Painel de coaching injectado por JS (`profile-dashboard-intelligence`) | Segundo bloco de estratégia, redundante com o anterior |

---

## O que ficou (o essencial)

| Bloco | Porquê |
|---|---|
| Herói compacto | Saudação numa linha + estado rápido (Hoje, Alertas, Cobrança) + 2 acções |
| KPIs (4) | Alunos Activos, Docentes, Pagamentos Hoje, Dívida Total: o pulso da escola |
| Resumo Financeiro | Só números reais (Receitas, Lançado, Taxa). Sem gráfico fictício |
| Distribuição por Classe | Donut real, útil ao director |
| Alertas de Conformidade | Lista accionável (o que pede atenção e onde agir) |
| Acessos Rápidos | Navegação para as operações mais usadas |

---

## Como foi feito (fronteira)

- `admin/system/dashboard-view.php`: **bloco de dados e consultas (linhas 1-296)
  e bloco de cache (fim) mantidos byte a byte**. Só a camada de apresentação foi
  reescrita (1051 → 627 linhas).
- `includes/profile-dashboard-intelligence.php`: o `enqueue` do painel injectado
  passa a sair cedo (`return`). Hook mantido; funções mantidas; só não injecta UI.
- Os erros de acento ("Gestao", "configuracoes", "saude") desapareceram do painel
  porque o conteúdo que os trazia foi removido. (O ficheiro-fonte
  `institutional-product-map.php` continua igual; se outro módulo o mostrar,
  corrige-se na vez desse módulo.)

NÃO se tocou: lógica PHP, consultas SQL, `$wpdb->prepare`, fórmulas, regras,
`name`/`id`, permissões (as guardas e chaves de capacidade são idênticas),
schema, multi-tenant, CSP.

---

## Anti-regressão (resultado)

- `php -l`: sem erros (ambos os ficheiros).
- Bloco de dados (1-296) confirmado **idêntico** ao original (diff vazio).
- Gate de design tokens: 1934 → **1929** (5 abaixo; nova baseline registada).
- Nenhum KPI/saldo/total muda de valor.
- Fins de linha LF preservados.

## Entrega

- Versão 12.19.4 em 3 fontes (cabeçalho, `SIGE_VERSION`, `BUILD.json`).
- DEPLOY: `docs/deploy/DEPLOY-v12.19.4-painel-principal.md`
- LIVE-TEST: `docs/qa/LIVE-TEST-painel-principal-v12.19.4.md`
- Changelog: `docs/changelog/CHANGELOG-v12-19-4.txt`

## Reversível

Repor os 2 ficheiros do backup repõe o painel anterior por inteiro. Nada removido
é destrutivo: as funções de "inteligência" continuam no código, apenas não são
renderizadas.
