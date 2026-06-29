# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.33

Correccao do numero de chamada no Mapa de Aproveitamento Pedagogico e varredura canonica das superficies que numeram alunos.

## Rediagnostico adversarial

Revisao hostil da correccao, procurando regressao na numeracao, divergencia entre documentos, alteracao acidental de calculo, e efeitos colaterais na modal de alunos.

1. A numeracao ainda depende da ordem de varrimento da tabela? Nao. A variavel de utilizador (@rn := @rn + 1) foi eliminada do codigo de producao (confirmado por busca: nenhuma ocorrencia de @rn := em codigo, apenas comentarios explicativos). O numero passa a ser um COUNT correlacionado das linhas estritamente menores na ordem total (nome_completo, id), independente da ordem fisica de leitura.

2. O numero pode divergir entre o MAP e a pauta? Nao em dados validos. As duas superficies passam a usar a mesma populacao canonica (aluno e matricula activos) e a mesma ordem total estrita (nome_completo ASC, id ASC). Como id e chave primaria unica, (nome, id) e uma ordem total: o posto de cada aluno e deterministico e igual em qualquer superficie que use a definicao. Provado por simulacao de equivalencia (numero do auxiliar identico ao numero da modal para todos os alunos, incluindo um caso sintetico com desistencia).

3. Empates de nome. Dois alunos com o mesmo nome_completo: o desempate por id ASC garante ordem estavel e reproduzivel, sem saltos nem repeticoes de numero. Antes, sem desempate explicito, a ordem entre homonimos podia variar entre documentos.

4. Alteracao de calculo academico ou financeiro. Nenhuma. O numero de chamada e apresentacao e identidade; nao alimenta MFD, transicao, progressao nem qualquer formula financeira. Os ficheiros canonicos de calculo nao foram tocados. As consultas de notas mantem o padrao UNION obrigatorio (turma_disciplinas mais matriz_curricular).

5. Efeito colateral na modal de alunos da turma. A modal era a unica superficie que nao excluia matriculas inactivas (filtrava so o estado do aluno). Convergi-la para a populacao canonica fa-la excluir desistencias, alinhando-a com os documentos oficiais que ja as excluiam. Os totais da modal (Total, Masculino, Feminino) sao calculados em JavaScript a partir do proprio array de dados devolvido, pelo que permanecem coerentes automaticamente. O unico chamador da accao AJAX foi verificado (uma so origem).

6. Seguranca de tenant. A convergencia acrescentou a condicao escola_id no join das superficies de pauta, identica em dados saudaveis, reforcando o isolamento por escola sem alterar resultados.

7. Deriva de superficie, esquema ou calculo. Os auxiliares novos sao funcoes simples (function_exists), nao accoes registadas: o manifesto e o Kernel mantem-se em 199 (enforce 33) e a allowlist de views em 60. Sem alteracao de esquema. Sem aumento de current_user_can. Calculo byte-identico.

## P0

Nenhum. O defeito de numeracao foi corrigido na raiz (eliminada a variavel de utilizador), a numeracao e agora deterministica e identica entre MAP, modal e pauta, e nenhuma regra de calculo foi tocada.

## P1

Nenhum. Notas de desenho: a modal de alunos passa a excluir matriculas inactivas por convergencia com os documentos oficiais (comportamento desejado, documentado no DEPLOY); o numero de chamada usa COUNT e nao COUNT DISTINCT, para paridade exacta com a multiplicidade de linhas do join INNER da modal; o (@rn := @rn + 1) foi removido de producao, ficando apenas comentarios que o explicam.

## Decisao

Aprovado para entrega como v12.12.33. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: rolo canonico unico (populacao de aluno e matricula activos, ordem nome_completo ASC com desempate por id ASC) calculado por auxiliares deterministicos sem variavel de utilizador; convergencia do MAP, da modal de alunos e das pautas para essa definicao; seguranca de tenant reforcada no join. Patch de defeito que nao altera o roteiro; as fases planeadas retomam a seguir, sem nada adiado.
