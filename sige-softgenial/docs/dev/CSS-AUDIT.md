# Auditoria CSS - assets/style.css (v12.11.9.90)

Diagnóstico medido, não estimado. A consolidação NÃO foi executada nesta
versão: risco visual alto exige screenshots de referência antes de mexer.

## Números (medidos por script nesta release)

| Métrica | Valor |
|---|---|
| Tamanho | 281 KB / 4.906 linhas |
| Camadas append-only por versão | 26 (v12.10.2 até v12.10.x) |
| Selectores declarados | 2.561 |
| Selectores únicos | 1.759 |
| Selectores redeclarados 3x ou mais | 150 |
| Ocorrências redundantes (potencial de corte) | ~570 declarações |
| Usos de !important | 2.414 |

## Leitura

O ficheiro cresceu por sedimentação: cada versão acrescentou a sua camada
no fim em vez de editar a anterior. O grupo de selectores de view
(.sige-view-portaria, .sige-view-notas, etc.) aparece 15 vezes cada um,
porque cada camada repetiu o mesmo bloco de scoping. Os 2.414 !important
são consequência directa: cada camada nova precisa de gritar mais alto que
a anterior para vencer a especificidade acumulada.

## Plano de consolidação (3 passos, executar num sprint dedicado)

1. **Fotografar o estado actual.** Screenshot de cada view principal
   (desktop + 390px) nas 4 escolas + demo. É o contrato visual: depois da
   consolidação, pixel-diff manual contra estas imagens.
2. **Fundir por selector, preservando a ÚLTIMA declaração vencedora.**
   Script de fusão que, para cada selector repetido, mantém apenas o
   resultado final em cascata (a última camada vence, que é o que o
   browser já faz hoje). Resultado esperado: ~570 declarações a menos e
   queda significativa dos !important, porque sem camadas concorrentes
   deixa de ser preciso gritar.
3. **Congelar a regra append-only.** A partir da consolidação, alterações
   de CSS editam o bloco existente do selector; camadas novas por versão
   ficam proibidas (a regra do escuteiro de assets/views/ absorve o CSS
   novo por view).

## Critério de pronto

style.css abaixo de 150 KB, zero selectores com 3+ declarações, !important
abaixo de 500, e pixel-diff aprovado contra os screenshots do passo 1.
