# Rediagnóstico Adversarial - v12.15.8

## Hipótese de falha
A v12.15.7 corrigiu a orientação do menu de acções, mas manteve cards vizinhos capazes de ganhar destaque por hover. Como transform/hover cria contexto visual elevado, um card vizinho podia ficar acima do menu aberto.

## Correcção escolhida
Não foi feita uma nova camada global. A correcção ficou confinada ao CSS/JS de Alunos:

- card com menu aberto passa a ter classe própria;
- body reflecte existência de menu aberto;
- card aberto recebe prioridade de stacking;
- menu aberto recebe prioridade superior;
- hover de cards vizinhos deixa de superar o card aberto.

## Riscos residuais
- Teste visual em browser autenticado continua necessário porque z-index depende da árvore real da página.
- O CSS histórico da própria view de Alunos ainda contém múltiplas camadas antigas; a limpeza física deve ser fase própria, não esta correcção.

## Conclusão
Sem bloqueador P0/P1 conhecido. A alteração é pontual, reversível pelo mesmo feature flag de Alunos e não toca em módulos externos.
