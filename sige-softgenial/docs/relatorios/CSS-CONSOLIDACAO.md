# Relatório - Consolidação CSS (candidato gerado, v12.11.9.92)

## O que foi feito
A ferramenta tools/css-consolidar.php gerou assets/style-consolidado.css,
um CANDIDATO de substituição do style.css. O ficheiro vivo NÃO foi tocado
e nenhum PHP carrega o candidato: risco zero nesta versão.

## Números medidos

| Métrica | style.css | candidato |
|---|---|---|
| Tamanho | 281 KB | 242 KB (86%) |
| Grupos de selectores repetidos fundidos | - | 72 |
| Blocos anteriores absorvidos | - | 92 |
| Declarações redundantes removidas | - | 310 |
| !important | 2.414 | 2.402 |
| @keyframes / @media | 12 / 64 | 12 / 64 (preservados) |

## Algoritmo (e porque é conservador)
Fusão apenas de selectores com texto EXACTAMENTE igual no mesmo contexto
de media, colapsados na posição da ÚLTIMA ocorrência, com a última
declaração de cada propriedade a vencer (replica a cascata que o browser
já calculava). Selectores parecidos mas não idênticos (ex.: as listas
:is() das views, que cresceram de camada para camada) ficam intactos de
propósito: fundir "parecidos" é onde nascem as regressões visuais.

A grande redução de !important só acontece DEPOIS da troca, quando as
camadas deixarem de competir entre si; este passo remove o peso morto.

## Limite conhecido (a razão de ser candidato)
Mover declarações de uma ocorrência anterior para a posição da última
pode, em casos raros de especificidade igual com outro selector declarado
entre as duas posições, alterar o vencedor. Probabilidade baixa, mas não
zero: por isso a troca exige validação visual.

## Como promover o candidato (quando decidires)
1. Screenshots de referência: cada view principal, desktop + 390px,
   nas 4 escolas + demo (passo 1 do CSS-AUDIT);
2. Na demo: renomear style.css -> style-original.css e
   style-consolidado.css -> style.css; limpar cache;
3. Percorrer as views contra os screenshots; qualquer diferença visual:
   reverter os nomes (rollback de 10 segundos) e reportar a view;
4. Verde na demo durante 2-3 dias de uso real -> repetir escola a escola;
5. Depois da troca em todas: congelar a regra append-only (CSS novo só
   em assets/views/ por view, ou editando o bloco existente do selector).
