# Carta da fase - v12.12.46 - Fase 4 incr 2 - Migracao de inline (vaga 1) e catraca

Segundo incremento da Fase 4 (CSP e front-end seguro). O incremento 1 trouxe as bibliotecas para dentro (self-host) e criou a infra de enqueue. Este incremento comeca a tirar o codigo de interface de dentro do HTML: cria o mecanismo que substitui onclick e faz a primeira vaga de migracao, protegida por um gate de catraca.

## Objectivo

Comecar a migrar o front-end inline para fora do HTML, com um mecanismo de delegacao reutilizavel e um gate de catraca que impede o inline de aumentar, a caminho de poder impor o CSP. Vaga inicial num ficheiro contido, sem mudanca de comportamento.

## Incluido

- Novo despachante declarativo data-sige-act em assets/sige-ui.js (ja enfileirado nas paginas sige-app, junto do enhancer data-sige-confirm ja existente): um unico ouvinte delegado dispara a funcao global indicada em data-sige-act, passando data-sige-arg como argumento ou, na sua ausencia, o proprio elemento (equivalente ao this do onclick).
- Primeira vaga: admin/finance/financeiro-lancamentos-view.php passa os seus dez onclick para data-sige-act (exportacao, abrir e fechar modais de detalhe, anulacao, isencao e reactivacao). Os handlers que recebiam um id passam-no por data-sige-arg; o de detalhes recebe o elemento.
- Novo gate de catraca tools/check-inline-frontend.php: conta as ocorrencias de onclick=, style=" e blocos <script> sem src nas views e includes e exige que nunca aumentem face a baseline (onclick 260 -> 250; style 2191; script 81).

## Excluido

- A migracao das restantes views (os outros onclick, os style inline e os blocos script): e o trabalho das vagas seguintes; a catraca garante que so descem.
- A externalizacao dos blocos <script> para ficheiros enfileirados e a conversao de style inline em classes: vagas proprias.
- CSP em enforcement (nonce por pedido e flip do cabecalho): incremento final da fase.
- Qualquer alteracao a regras de calculo, a ficheiros canonicos ou ao esquema.

## Riscos

- Um handler deixar de ser chamado apos a conversao. Mitigacao: o despachante chama a mesma funcao global; os handlers que recebiam um id recebem-no por data-sige-arg e o de detalhes recebe o elemento, tal como o this anterior. As funcoes nao mudaram.
- Colisao com o enhancer de confirmacao. Mitigacao: o despachante data-sige-act corre em fase de bolha; o de confirmacao corre em captura e antecede-o.
- O inline aumentar sem se dar por isso. Mitigacao: o gate de catraca falha se qualquer das tres categorias subir face a baseline.

## Criterios de aceitacao

- O ficheiro alvo fica com zero onclick (eram dez) e dez data-sige-act; o despachante existe em assets/sige-ui.js.
- A catraca confirma onclick=250, style=2191 e <script>=81 sem exceder a baseline.
- Superficie 199, vistas 60, opcoes 132, dependencias 9 inalteradas. Versao sincronizada nas cinco fontes, zero travessoes, JS valido (node -c).
- Corredor 98 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
