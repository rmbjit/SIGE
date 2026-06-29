# TESTES LIVE - v12.11.9.106 (consolidação CSS: equipe/RH)

Fazer em teste.softgenial.edu.mz. Tempo total: ~4 minutos.
Teste VISUAL: confirmar que NADA mudou aos olhos.
F12 > Console aberto: zero linhas vermelhas.

## 1. Tabela de equipa pixel-perfect (2 min)
1. RH/Equipa: a tabela de colaboradores com as colunas nas MESMAS
   proporções (Colaborador a coluna mais larga, Cargo/Validade/Status
   no meio, Acções à direita alinhada à direita);
2. Estado vazio (se filtrar para zero): a mensagem com o mesmo
   espaçamento.

## 2. Formulários e confirmação (2 min)
1. Adicionar/Editar colaborador: o formulário com os mesmos
   espaçamentos entre campos (sem campos colados nem afastados a mais);
2. Acção que pede confirmação (ex.: remover): o modal aparece e, quando
   há um detalhe (ex.: nome do colaborador), ele APARECE dentro do
   modal - depois desaparece ao cancelar. Este é o ponto sensível:
   confirmar que o detalhe ainda surge;
3. Botão que mostra "A gerar..." com o ícone a rodar: o spinner roda
   como antes.

## Critério de aprovação
A página de RH está IDÊNTICA + o detalhe da confirmação ainda aparece +
console limpo = v106 validada.
