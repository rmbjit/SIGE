# Carta da fase - v12.12.62 - Fase 4 incr 2 - switchTab e args com esc_js das vistas admin em data-sige-act

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): switchTab e onclick com argumentos string via esc_js das vistas admin para o despachante data-sige-act, continuando a preparar o CSP enforce do shell admin.

## Objectivo

Remover os onclick das vistas admin que exigiam cuidado proprio (o switchTab, refeito para ler data-tab, e os argumentos string via esc_js, serializados com esc_attr(wp_json_encode) em data-sige-args), convertendo-os para o despachante data-sige-act, reduzindo os bloqueadores de um futuro script-src baseado em nonce no shell admin. Comportamento identico; catraca onclick desce de 79 para 66.

## Incluido

- switchTab das abas da ficha do aluno: refeito de switchTab(event, tabId) para switchTab(el), lendo o tabId do atributo data-tab que as abas ja possuem; os 4 call-sites passam a data-sige-act (a via por omissao do despachante passa o proprio elemento).
- 9 onclick com argumentos string via esc_js (alocarProfessores, verAlunos, apagarTurma, verAcesso, apagarAluno, toggleStatus, removerUser, sigeAbrirAnular, plAbrirPagar) passam a data-sige-act mais data-sige-args, com os argumentos serializados por esc_attr(wp_json_encode([...])).
- Total 13 conversoes em 5 vistas: alunos_lista (4 switchTab, verAcesso, apagarAluno), turmas-view (alocarProfessores, verAlunos, apagarTurma), equipe-view (toggleStatus, removerUser), financeiro-extratos (sigeAbrirAnular), financeiro-planos-view (plAbrirPagar).
- Aperto da catraca check-inline-frontend: maximo de onclick de 79 para 66.

## Excluido

- Onclick com codigo inline (sessionStorage, location.reload, condicoes) e os gerados dentro de blocos script: incrementos proprios.
- Onclick em includes (portal publico, finance-core canonico): nao se tocam.
- O cabecalho CSP do shell admin: so vem depois de tratados os onclick (66) e estilos inline (2051) restantes.

## Riscos

- O switchTab marcar a aba errada. Mitigacao: as abas sao div sem filhos, pelo que event.target era ja o elemento que o despachante passa; o tabId vem do data-tab que as abas ja possuem; switchTab so e chamado por esses 4 onclick.
- Os argumentos serializados perderem tipos ou partir o atributo. Mitigacao: o wp_json_encode preserva inteiros, decimais e cadeias; o esc_attr codifica os caracteres HTML-especiais; testou-se o round-trip ate JSON.parse com um valor dificil.
- A funcao alvo nao ser encontrada. Mitigacao: cada funcao e global (funcao ou window.nome) e o despachante resolve via window.

## Criterios de aceitacao

- As abas da ficha do aluno trocam como antes; os 9 botoes recebem os mesmos argumentos; php -l limpo nas 5 vistas.
- O despachante data-sige-act mantem o contrato (smoke-inline-frontend verde).
- Os verificadores de JavaScript embebido mantem-se verdes.
- A catraca trava onclick em 66 (sem folga); style 2051 e blocos script 7 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
