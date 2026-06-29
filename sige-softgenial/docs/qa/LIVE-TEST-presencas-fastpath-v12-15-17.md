# LIVE-TEST: caminho rapido de presencas para a secretaria (v12.15.17)

O caminho rapido fica atras da flag `sige_presencas_fast_path_v121517_enabled`,
DESLIGADA por defeito. Estes testes confirmam (1) que por defeito nada muda
para a secretaria a nao ser o fim dos dialogos nativos, (2) que ligando a flag
aparece o caminho rapido com todos os modos, e (3) que a reversao funciona.

Pre-requisito: entrar com um utilizador que possa corrigir presencas (papel da
secretaria/director) e abrir Academico > Presencas, escolher turma, ano e mes
com dias lectivos ja passados.

## Custo antes e depois (porque esta vaga existe)

| Accao tipica                          | Antes (modal por registo) | Depois (caminho rapido) |
|---------------------------------------|---------------------------|-------------------------|
| Corrigir 1 falta de 1 aluno           | abrir modal, escolher, gravar, fechar (4 passos, troca de contexto) | clicar na celula, clicar no estado (2 passos, sem sair da grelha) |
| Marcar a mesma falta a 1 dia inteiro  | repetir o modal N vezes   | clicar no cabecalho do dia, 1 clique na barra de lote |
| Justificar 1 semana de 1 aluno        | 5 modais                  | Shift-clique do 1.o ao 5.o dia, 1 clique na barra |

## Cenario 0 - Default DESLIGADO: modal classico, sem dialogos nativos

1. Sem mexer em opcoes (flag no default), abrir Presencas e clicar numa celula.
   **Esperado:** abre o modal classico de sempre. NAO aparece o popover inline.
2. Forcar um erro (por exemplo, tentar gravar sem ligacao) ou um sucesso.
   **Esperado:** a mensagem aparece como toast (`sigeUi.toast`), NUNCA como
   caixa nativa do browser (`alert`). Confirmar que nao surge nenhum dialogo
   nativo em todo o fluxo do modal.

## Cenario 1 - Ligar a flag

1. `update_option('sige_presencas_fast_path_v121517_enabled', '1');`
2. Recarregar Presencas.
   **Esperado:** as celulas editaveis (dias passados/hoje) ganham foco visivel
   ao navegar; clicar numa celula abre agora o popover inline ancorado a celula,
   NAO o modal de ecra inteiro.

## Cenario 2 - Popover inline numa celula

1. Clicar numa celula editavel.
   **Esperado:** popover junto da celula com 4 estados (falta justificada,
   presente manual, dispensado, auto) e um campo de motivo opcional.
2. Escolher um estado.
   **Esperado:** a celula actualiza, o popover fecha, e os totais da linha
   (P, AT, F, J, percentagem) mudam. **Confirmar que os totais batem certo**:
   foram recalculados no servidor, nao no browser.
3. Escolher "auto" numa celula que tinha excepcao.
   **Esperado:** a excepcao e removida e a celula volta ao estado derivado
   automaticamente das entradas.

## Cenario 3 - Seleccao e accao em lote

1. Clicar numa celula, depois Shift-clicar noutra celula da mesma linha.
   **Esperado:** seleccao do intervalo; aparece a barra de lote com a contagem
   ("N celulas").
2. Ctrl/Cmd-clicar numa celula adicional.
   **Esperado:** alterna essa celula na seleccao (junta ou tira), a contagem
   actualiza.
3. Clicar no nome de um aluno (primeira coluna).
   **Esperado:** selecciona a linha inteira desse aluno (so celulas editaveis).
4. Clicar no cabecalho de um dia.
   **Esperado:** selecciona a coluna inteira desse dia (so celulas editaveis).
5. Com 2 ou mais celulas seleccionadas, escrever um motivo (opcional) e clicar
   num estado na barra de lote.
   **Esperado:** todas as celulas seleccionadas mudam de uma vez, as linhas
   afectadas recalculam os totais (do servidor), e a seleccao limpa-se. Um toast
   confirma quantas foram aplicadas.
6. Clicar em "Limpar seleccao".
   **Esperado:** a seleccao e a barra desaparecem.

## Cenario 4 - Teclado

1. Clicar numa celula para lhe dar foco; usar as setas.
   **Esperado:** o foco move-se entre celulas editaveis (roving tabindex).
2. Premir Enter ou Espaco.
   **Esperado:** abre o popover na celula focada.
3. Com uma celula focada, premir `j`, `p`, `d` ou `a`.
   **Esperado:** aplica directo, respectivamente, falta justificada, presente
   manual, dispensado, auto (sem motivo), e os totais da linha actualizam.
4. Premir Esc.
   **Esperado:** fecha o popover e limpa a seleccao.

## Cenario 5 - Limites e seguranca

1. Tentar aplicar um lote muito grande (acima de 1000 celulas, se possivel
   construir tal seleccao).
   **Esperado:** o servidor recusa com mensagem para dividir em lotes menores;
   nada e gravado.
2. Confirmar no registo de seguranca que uma correccao em lote gera uma entrada
   `presenca_corrigida_lote` (com n, alunos e estados).

## Cenario 6 - Reversao funciona

1. `update_option('sige_presencas_fast_path_v121517_enabled', '0');`
2. Recarregar Presencas e clicar numa celula.
   **Esperado:** volta o modal classico; o popover, a barra e os atalhos de
   teclado desaparecem. A secretaria fica exactamente como antes desta versao
   (sem dialogos nativos).

## Cenario 7 - Gates verdes

1. `php tools/run-gates.php`.
   **Esperado:** `Presencas caminho rapido (gate v12.15.17)` e
   `Presencas caminho rapido (smoke v12.15.17)` VERDES; o conjunto de vermelhos
   pre-existentes nao cresce e o release gate fica verde para 12.15.17.
