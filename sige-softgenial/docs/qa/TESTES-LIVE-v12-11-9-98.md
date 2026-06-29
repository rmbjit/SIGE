# TESTES LIVE - v12.11.9.98 (hotfix estrutural)

Fazer em teste.softgenial.edu.mz. Tempo total: ~10 minutos.
Em cada ecrã, deixar o F12 > Console aberto: zero linhas vermelhas.

## 1. O bug relatado: horário das turmas (3 min)
1. Turmas > Horário > clicar numa célula para adicionar disciplina:
   o MODAL APARECE (título "Horário: dia, Nº período") com a
   disciplina actual pré-escrita;
2. Continuar > segundo modal pede o professor (pré-preenchido);
   Guardar > a célula actualiza;
3. Esc no primeiro modal: nada muda; campo vazio + Guardar: limpa
   a célula (regra preservada).

## 2. Mesma raiz, outros ecrãs do kit (4 min)
1. Central WhatsApp: qualquer Remover abre modal visível; toasts
   aparecem no canto e desaparecem sozinhos;
2. Pagamentos Móveis: cartões, badges de canal/estado e banners com o
   visual do kit (era este ecrã o mais contaminado); Rejeitar abre
   modal com a referência e exige motivo;
3. Financeiro > Pagamentos: "Seleccionar Tudo / Limpar" visíveis e
   clicáveis (dívidas e família, com o violeta preservado).

## 3. Escudo do dinheiro (2 min)
Registar um pagamento pequeno e fazer DUPLO-CLIQUE rápido no "Sim,
confirmar pagamento": o botão desactiva ao primeiro clique; confirmar
nos extractos que existe UM pagamento e UM recibo na fila.

## 4. Correcção do Enter (1 min)
Jardim > Relatório > na linha de um aluno, clicar no campo do canal e
carregar ENTER: o modal de confirmação APARECE (antes enviava direto);
Esc cancela sem enviar; clique normal em "Enviar" pede o mesmo modal.

## Critério de aprovação
Tudo verde + console limpo = v98 validada; a fila UX-5 reabre.
Qualquer falha: mandar ecrã + acção + 1ª linha vermelha do console.
