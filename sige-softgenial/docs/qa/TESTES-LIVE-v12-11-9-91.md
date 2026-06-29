# TESTES LIVE - v12.11.9.91 (Sprint 2)

Fazer primeiro na demo. Tempo total: ~25 minutos.

## 0. Migração de schema (1 min)
Após instalar, abrir o Dashboard do SIGE uma vez e confirmar na base:
```
wp db query "SHOW TABLES LIKE '%sige_presencas_excecoes'"
wp db query "SHOW TABLES LIKE '%sige_mpesa_transacoes'"
```
As duas tabelas devem existir.

## 1. Presenças (8 min)
1. Menu Académico > Presenças > escolher uma turma real;
2. Conferir o mapa contra a realidade: alunos que passaram o crachá hoje
   devem ter P (ou AT se entraram depois das 07:30); tooltip da célula
   mostra a hora da 1ª entrada;
3. Comparar 2 ou 3 células com a tabela sige_acessos:
```
wp db query "SELECT aluno_id, DATE(data_hora) d, MIN(TIME(data_hora)) h FROM wp_sige_acessos WHERE tipo='entrada' AND DATE(data_hora)=CURDATE() GROUP BY aluno_id, d LIMIT 5"
```
4. Clicar numa célula F > "Falta justificada" + motivo > Guardar:
   a célula passa a J e os totais recalculam;
5. Na mesma célula > "Voltar ao automático": regressa a F;
6. Navegar para o mês anterior e voltar (setas ‹ ›);
7. Exportar CSV: abre no Excel com acentos correctos;
8. Imprimir: pré-visualização mostra só o mapa, com cores;
9. Entrar como perfil Guarda: o menu Presenças NÃO deve aparecer e o URL
   directo ?view=presencas mostra o aviso de área reservada;
10. Auditoria: o evento presenca_corrigida deve constar.

## 2. M-Pesa em sandbox (12 min)
1. Financeiro > Pagamentos M-Pesa > preencher credenciais sandbox do
   portal developer.mpesa.vm.co.mz > ambiente Sandbox > Guardar;
2. Copiar o endereço do webhook (botão Copiar);
3. Simular um callback da Vodacom (substituir URL+token pelos copiados e
   PROCESSO por um numero_processo real com dívida em aberto):
```
curl -X POST "URL_DO_WEBHOOK" -H "Content-Type: application/json" -d '{"input_TransactionID":"TESTE001","input_ThirdPartyReference":"PROCESSO","input_CustomerMSISDN":"258841234567","input_Amount":"VALOR_DO_LANCAMENTO_MAIS_ANTIGO"}'
```
4. Esperado: resposta {"output_ResponseCode":"INS-0",...}; na tabela do
   ecrã, a transacção aparece CONCILIADA com o lançamento certo; nos
   Extractos do aluno, o pagamento consta com método M-Pesa; o recibo
   WhatsApp entra na fila como num pagamento ao balcão;
5. Repetir EXACTAMENTE o mesmo curl: resposta "Transacção já registada"
   e NENHUM pagamento duplicado (idempotência);
6. Enviar um callback com valor superior ao lançamento mais antigo
   (TransactionID novo): a transacção fica PENDENTE MANUAL com o motivo;
7. No ecrã, clicar Conciliar nessa pendente > escolher o lançamento no
   modal > Registar pagamento: passa a conciliada (conciliado_por user:N);
8. Enviar um callback com token ERRADO no URL: resposta 401/403 e nada
   entra na tabela;
9. Testar Rejeitar numa transacção de teste: estado rejeitada com motivo;
10. Auditoria: eventos mpesa_callback_recebido, mpesa_conciliada (ou
    manual) presentes.

## 3. Caixa fechada (3 min, opcional mas recomendado)
1. Fechar a caixa do dia no financeiro;
2. Enviar um callback de teste novo: a transacção fica RECEBIDA com a nota
   "Caixa fechada; nova tentativa automática no próximo dia útil";
3. Reabrir a caixa e correr `wp eval 'do_action("sige_evento_diario");'`:
   a transacção concilia.

## 4. Regressão de sanidade (2 min)
- Portaria: ler um crachá (estado coerente);
- Financeiro: registar um pagamento normal ao balcão (nada mudou);
- Login: continua normal (escudo do Sprint 1 intacto).

## Critério de aprovação
Tudo acima verde = v12.11.9.91 validada na demo; avançar escola a escola.
M-Pesa em PRODUÇÃO só depois do ciclo sandbox completo + credenciais de
produção da Vodacom + um pagamento real de teste de 10 MT supervisionado.
