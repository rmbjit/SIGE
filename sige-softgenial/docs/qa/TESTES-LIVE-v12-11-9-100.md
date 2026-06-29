# TESTES LIVE - v12.11.9.100 (Sprint UX-6)

Fazer em teste.softgenial.edu.mz. Tempo total: ~8 minutos.
F12 > Console aberto: zero linhas vermelhas.

## AVISO IMPORTANTE ANTES DO TESTE 1
"Encerrar o Ano Lectivo" executa de verdade (snapshot, bloqueios,
auditoria). O teste principal é o CANCELAR. Só confirma o Encerrar se
o ano seleccionado for descartável nesse ambiente de teste.

## 1. Encerramento: modais à altura do risco (4 min)
1. "Activar Pauta Final em Todas as Turmas": modal do SIGE com a
   explicação; Cancelar não faz nada; (opcional, ano de teste)
   Confirmar executa como antes;
2. "Encerrar Ano Lectivo": modal VERMELHO com as consequências por
   extenso (checklist, pauta final, snapshot, bloqueios, auditoria,
   reabertura autorizada); Esc/Cancelar NÃO encerra: conferir que o
   ano continua aberto;
3. "Reabrir Ano Lectivo" (se houver ano encerrado de teste): modal
   próprio; Cancelar não reabre;
4. Tecla ENTER num destes formulários abre o MESMO modal (não submete
   direto).

## 2. Lançamentos (2 min)
1. Filtrar para lista vazia > Exportar: toast laranja;
2. Abrir detalhes de um lançamento normal: funciona; (se falhar de
   propósito sem rede: toast vermelho com mensagem humana).

## 3. Portal do aluno (verificação de conformidade, 2 min)
1. Abrir o portal de um aluno SEM notas no ano: a tabela mostra
   "Ainda não existem notas registadas..." (já existia; agora está
   protegido);
2. Aluno sem lançamentos no período: "Sem lançamentos financeiros...".

## Critério de aprovação
Tudo verde + console limpo = v100 validada; UX-7 ataca jardim_diario +
alocacao + whatsapp_circulares.
