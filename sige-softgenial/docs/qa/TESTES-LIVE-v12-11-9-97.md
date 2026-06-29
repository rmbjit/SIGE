# TESTES LIVE - v12.11.9.97 (Sprint UX-4)

Fazer primeiro na demo. Tempo total: ~9 minutos.

## 1. Jardim Relatório: envios com confirmação digna (4 min)
1. Escolher turma com dados > "Enviar resumo mensal": modal do SIGE
   com título "Enviar resumo mensal" e botão "Enviar"; Esc/Cancelar
   NÃO envia (confirmar que nenhuma mensagem entrou na fila);
2. Confirmar: o envio segue como sempre (fila WhatsApp/email);
3. Na tabela de alunos, botão verde "Enviar" de uma linha: modal
   "Enviar resumo individual"; cancelar e depois confirmar;
4. Botões "Aplicar" e "Enviar resumo mensal" com o mesmo visual de
   antes (classe local .sige-jrel-btn; nada mudou aos olhos).

## 2. Turmas: horário sem caixas cinzentas (3 min)
1. Turmas > Horário de uma turma > clicar numa célula: modal
   "Horário: Segunda, 1º período" com a DISCIPLINA ACTUAL pré-escrita;
2. Continuar > segundo modal pede o professor (também pré-preenchido);
   Guardar > célula actualiza; Limpar horário continua com o modal
   próprio antigo da view;
3. Esc no primeiro modal: nada muda na célula;
4. Apagar o texto e Guardar: célula fica limpa (regra preservada).

## 3. Estatísticas (1 min)
Com popups bloqueados no browser, Imprimir: toast laranja com a
instrução; desbloquear e repetir: janela de impressão abre.

## 4. Inocuidade (1 min)
Pagamentos (balcão) e Central WhatsApp: idênticos à v96.

## Critério de aprovação
Tudo verde = v97 validada; UX-5 ataca acta + planos + devedores.
