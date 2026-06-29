# LIVE-TEST: P2 fechada, caminho rapido de presencas ligado por defeito (v12.15.18)

A flag do caminho rapido esta agora LIGADA por defeito. Estes testes confirmam
o novo comportamento por defeito e que a reversao continua a funcionar. Os
cenarios funcionais completos (popover, lote, teclado, limite, seguranca) estao
em docs/qa/LIVE-TEST-presencas-fastpath-v12-15-17.md.

## Cenario 1 - Default ligado: secretaria ve o caminho rapido

1. Sem mexer em opcoes, entrar como secretaria (ou director) e abrir
   Academico > Presencas, escolher turma, ano e mes com dias lectivos passados.
2. Clicar numa celula editavel.
   **Esperado:** abre o popover inline ancorado a celula, NAO o modal de ecra
   inteiro. As setas movem o foco entre celulas; Shift-clique selecciona um
   intervalo e mostra a barra de lote.

## Cenario 2 - Totais do servidor

1. Aplicar uma correccao (popover ou lote).
   **Esperado:** os totais da linha (P, AT, F, J, percentagem) actualizam com
   valores recalculados no servidor.

## Cenario 3 - Reversao funciona

1. `update_option('sige_presencas_fast_path_v121517_enabled', '0');`
2. Recarregar Presencas e clicar numa celula.
   **Esperado:** volta o modal classico (sem dialogos nativos). O popover, a
   barra e os atalhos de teclado desaparecem.
3. Repor a `'1'` (ou apagar a option) para voltar ao default ligado.

## Cenario 4 - Gates verdes

1. `php tools/run-gates.php`.
   **Esperado:** `Presencas caminho rapido (gate v12.15.18)` VERDE (exige a flag
   ligada por defeito) e `Presencas caminho rapido (smoke v12.15.17)` VERDE. O
   conjunto de vermelhos pre-existentes nao cresce e o release gate fica verde
   para 12.15.18.
