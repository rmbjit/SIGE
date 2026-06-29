# PHASE_CHARTER - SIGE SoftGenial v12.12.33

Correccao cirurgica: numero de chamada do Mapa de Aproveitamento Pedagogico alinhado com a ordem real da pauta da turma. Nao e uma nova fase do roteiro; e um patch de defeito antes de retomar as fases planeadas do programa Alto Calibre.

## Objectivo

Garantir que o numero de chamada (No) apresentado no Mapa de Aproveitamento Pedagogico corresponde exactamente a posicao alfabetica do aluno na pauta da turma, e que essa numeracao e identica em todas as superficies que numeram alunos: MAP individual, MAP em lote por turma, modal de alunos da turma e pautas (pauta, DEC e pauta final).

## Diagnostico e causa raiz

No Mapa de Aproveitamento, a aluna Eluenny Ivan Barrama (4a Classe, Turma A), alfabeticamente a primeira da pauta, aparecia com No 6 em vez de No 1. A causa raiz estava em includes/map-pdf-handler.php (funcao sige_map_build_data): o numero de chamada era calculado por uma variavel de utilizador do MariaDB no padrao (@rn := @rn + 1) sobre uma subconsulta. O @rn incrementa na ordem de varrimento da tabela (proxima da ordem de insercao ou de chave primaria) e nao na ordem ORDER BY a.nome_completo ASC, pelo que o aluno alfabeticamente primeiro recebia o seu posto de insercao em vez da sua posicao alfabetica. A mesma funcao alimenta o MAP individual e o MAP em lote por turma, pelo que o defeito afectava ambos.

## Incluido

- Dois auxiliares deterministicos novos em includes/core-helpers.php: sige_turma_ordem_chamada_order_sql (ordem canonica nome_completo ASC, id ASC) e sige_turma_numero_chamada (numero de chamada por COUNT correlacionado das linhas estritamente menores na ordem canonica, mais um, sem variavel de utilizador).
- Definicao de um rolo canonico unico para o numero de chamada: populacao sige_aluno_matricula_activa_sql (aluno activo e matricula activa), ordem nome_completo ASC com desempate por id ASC.
- O Mapa de Aproveitamento (individual e em lote) passa a obter o numero de chamada por sige_turma_numero_chamada.
- Varredura e convergencia das restantes superficies que numeram alunos para a mesma populacao e ordem canonicas, com desempate por id e seguranca de tenant (escola_id) no join: modal de alunos da turma (ajax-handlers), pauta-pdf, pauta-excel, dec-view e pauta-final-view.

## Excluido

- Regras de calculo academico (MFD, transicao, progressao) e financeiro: intocadas. O numero de chamada e apresentacao e identidade, nao entra em nenhuma formula.
- Acta (numero de chamada introduzido manualmente pelo utilizador) e Declaracao de Passagem (imprime um numero de identidade ou de matricula, nao um posto na pauta): fora do ambito.
- Nenhuma nova superficie, view, permissao, opcao, segredo, dependencia externa ou alteracao de esquema.

## Riscos

- Divergencia de numeracao entre documentos em dados com desistencias ou nomes duplicados: mitigado pela definicao canonica unica (mesma populacao e mesma ordem total estrita (nome, id) em toda a parte), que torna o numero igual em MAP, modal e pauta por construcao.
- Alteracao visivel inesperada: o unico numero que muda e o que estava errado (o do MAP passa a coincidir com a pauta); em dados saudaveis todas as superficies ja concordam, pelo que a mudanca e de correccao e nao de regressao.
- Modal de alunos da turma passa a excluir matriculas inactivas (desistencias), alinhando-se com os documentos oficiais que ja as excluiam: comportamento esperado e desejado, documentado no DEPLOY. Os totais da modal (Total, Masculino, Feminino) sao calculados em JavaScript a partir do proprio array devolvido, pelo que permanecem coerentes automaticamente.

## Criterios de aceitacao

- No Mapa de Aproveitamento da 4a Classe Turma A, Eluenny Ivan Barrama aparece com No 1, coincidente com a modal de alunos e com a pauta.
- O numero de chamada do MAP, da modal e da pauta coincidem para todos os alunos activos, incluindo turmas com pelo menos uma matricula inactiva.
- A variavel de utilizador (@rn := @rn + 1) deixa de existir no codigo de producao (apenas comentarios a explicam).
- Calculo academico e financeiro byte-identico; sem nova superficie (mantem 199, enforce 33, views 60); sem alteracao de esquema; sem aumento de current_user_can; zero travessoes; versao sincronizada nas cinco fontes; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
