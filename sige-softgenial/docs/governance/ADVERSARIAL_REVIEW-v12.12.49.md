# Revisao adversarial - v12.12.49 - Fase 4 incr 2 - Fixe de impressao utilitarios e vaga admin-shell e jardim

Rediagnostico adversarial. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto. Esta versao nasceu de uma falha encontrada exactamente neste exercicio: as classes utilitarias migradas nas vagas 2 e 3 nao resolviam no impresso de vistas com janela de impressao autonoma.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Imprimir um boletim e ver a celula migrada perder alinhamento. A janela de impressao do boletim faz window.open mais document.write e clona a pauta (tbl.cloneNode), cujo tfoot tem o <td class="sige-u-tar">; o <style> proprio do popup nao definia .sige-u-tar, logo a regra table.sige-nt td com text-align center voltava a vencer. Resolvido: o helper sige_utilities_inline_css() esta embebido no <style> do popup, com .sige-u-tar e !important. Confirmado por simulacao: o CSS sai logo apos <style>, antes do conteudo clonado.
2. Repetir para as outras vistas com popup. alunos_lista (ficha em template literal com sige-u-tac), turmas (dois popups que clonam tabela-exportar com sige-u-tac e geram horario e lista por genero) e financeiro-devedores (tabela impressa em template string com sige-u-tar e sige-u-fw7) recebem todas o helper no respectivo <style>. estatisticas nao precisa (o clone remove o atributo class e so imprime graficos), mas recebe o helper por uniformidade defensiva.
3. Introduzir uma nova vista com popup e classes sige-u- sem o helper. A guarda nova do smoke-inline-frontend percorre admin e includes e falha se uma vista com window.open ou document.write e classes sige-u- nao chamar sige_utilities_inline_css. Trava a recaida.
4. Partir a shell ao migrar o admin-shell (ficheiro nuclear, em todas as paginas). So se migraram 55 atributos 100 por cento seguros (sobretudo flex-shrink em icones), sem class existente; os utilitarios usam !important; a shell continua a enfileirar o sige-utilities.css; php -l limpo; zero class duplicada; corredor sem regressao.
5. Migrar saida autonoma sem o CSS. documents-engine (gera PDF/HTML), portal-logic (vista de portal) e jardim_boletim (popup de impressao) ficam de fora por desenho, porque a saida deles nao carrega o sige-utilities.css. So se migrou o jardim_relatorio (sem popup).
6. Embeber CSS que parta a string JS do popup. O helper nao tem aspas simples nem backticks nem ${; foi confirmado por simulacao. O <?php echo ... ?> cai dentro do <style> de cada popup, mantendo a string valida.
7. Deixar o inline voltar a subir. A catraca desce o maximo de style de 2110 para 2051 e fica sem folga.
8. Acrescentar superficie, vista ou opcao, ou partir incrementos anteriores. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9; gates de pagamentos, cofre, Fase 1, Fase 4 incr 1 e vagas 2 e 3 continuam verdes; release gate verde a partir de pasta limpa.

## P0

Nenhum. Correccao de apresentacao no impresso e vaga de interface; nao toca em regras de calculo nem em ficheiros canonicos.

## P1

Nenhum em aberto. A regressao cosmetica de impressao (classes sem efeito no impresso de 5 vistas) que existia desde as vagas 2 e 3 fica resolvida nesta versao, com guarda a impedir recaida.

## P2 e P3

Nenhum novo. Migracao conservadora; saida autonoma e canonicos excluidos; catraca sem folga; guarda anti-regressao de impressao activa.

## Decisao

Aprovado. Zero P0 e zero P1 em aberto. A versao corrige uma regressao real de impressao (classes utilitarias agora resolvem nas janelas de impressao autonomas, via helper embebido em 5 popups, com guarda no smoke) e avanca a migracao de inline no admin-shell (55 icones) e jardim_relatorio (4), com a catraca a travar style em 2051 sem folga. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, vista ou opcoes, sem alteracao de esquema, calculo byte-identico, deps 9. Corredor 99; gates anteriores verdes; release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se as restantes areas de estilo, os onclick remanescentes, os blocos script e, por fim, o CSP em enforcement.
