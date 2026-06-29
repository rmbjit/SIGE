# TESTES LIVE - v12.11.9.94 (Sprint UX-1)

Fazer primeiro na demo. Tempo total: ~12 minutos.

## 1. Inocuidade do kit (2 min, o teste mais importante)
Abrir Dashboard, Alunos e Financeiro > Pagamentos: visual EXACTAMENTE
igual à v93 (o kit é namespaced; nada muda fora do piloto).

## 2. Piloto Central WhatsApp: diálogos (5 min)
1. Acção por linha: Cancelar numa mensagem pendente > modal do SIGE com
   título "Cancelar mensagem", o número da mensagem no texto e botão
   "Cancelar mensagem" > confirmar > toast verde e linha esbatida;
2. Remover numa enviada > modal VERMELHO "Remover do histórico" com
   aviso de irreversibilidade > Esc fecha SEM remover > repetir e
   confirmar com Enter > toast e reload;
3. Lote: seleccionar 2-3 mensagens > "Remover seleccionadas" > o modal
   diz quantas e quais estados são abrangidos > confirmar > toast com
   o resultado (ok/total) antes do reload;
4. Overlay: clicar fora do modal fecha como Cancelar (nada acontece).

## 3. Piloto: link do recibo e operações de fila (3 min)
1. "Enviar link" num pendente > modal com o NÚMERO de telefone no
   texto > confirmar > botão entra em "A enviar..." > toast;
2. "Marcar como tratado" > modal explica o caso de uso > confirmar;
3. Esvaziar a lista de pendentes (filtro que dê zero): aparece o estado
   vazio bonito "Tudo tratado" com emoji, não uma linha seca;
4. Redistribuir agendadas e Preparar respostas: modais novos com verbo
   no botão; resultados como toast/linha de estado.

## 4. Terminologia e detalhe (2 min)
- Em todo o piloto: nenhum "Eliminar" (agora Remover), nenhum diálogo
  cinzento do browser, botões todos com estilo (nenhum botão "nu");
- Falha de rede simulada (desligar wifi e tentar): toast "Falha de
  ligação. Verifique a internet e tente novamente."

## Critério de aprovação
Tudo verde = v12.11.9.94 validada; Sprint UX-2 (financeiro-pagamentos)
pode arrancar sobre o kit provado.
