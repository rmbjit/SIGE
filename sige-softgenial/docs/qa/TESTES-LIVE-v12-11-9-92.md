# TESTES LIVE - v12.11.9.92 (Sprint 3)

Fazer primeiro na demo. Tempo total: ~15 minutos.

## 1. Saúde operacional + interruptores (5 min)
1. Saúde do Sistema: cartão "Saúde operacional" presente com fila
   WhatsApp, fila email, crons ("daqui a ...") e piso PHP;
2. Ligar o interruptor do relatório mensal + escrever o teu email nos
   destinatários > Guardar interruptores > banner verde;
3. Confirmar na base: `wp option get sige_relatorio_mensal_email` = on;
4. Mudar a hora de corte para 07:15 > Guardar > abrir Presenças e
   confirmar o novo valor no cabeçalho do mapa; repor 07:30;
5. Entrar como secretário (não Director): o cartão mostra métricas mas
   SEM o formulário de interruptores;
6. Auditoria: evento saude_toggles registado.

## 2. Presenças pós-extracção (3 min)
1. Académico > Presenças > escolher turma: o mapa carrega e as cores
   estão idênticas à v91 (P verde, AT amarelo, F vermelho...);
2. Ver código-fonte da página: existe link para
   assets/views/presencas.css?v=... e script presencas.js?v=...;
3. Como editor: clicar numa célula abre o modal e guardar funciona;
   como Guarda: células sem cursor de mão e sem modal;
4. CSV e Imprimir continuam a funcionar.

## 3. Cobrar agora (M-Pesa push, com sandbox configurado) (5 min)
1. Financeiro > Pagamentos M-Pesa: cartão "Cobrar agora" visível;
2. Introduzir um processo real com dívida + o MSISDN de teste do sandbox
   + o valor do lançamento mais antigo > Enviar pedido;
3. Esperado: mensagem verde "Pagamento confirmado..." e, após o reload,
   a transacção CONCILIADA na tabela; recibo na fila WhatsApp;
4. Testar um erro: valor 999999 ou MSISDN de saldo insuficiente do
   sandbox > mensagem humana ("Saldo insuficiente...") sem entrada órfã;
5. Sem credenciais ainda? Usar o simulador no servidor:
```
php wp-content/plugins/sige-softgenial/tools/simular-callback-mpesa.php "URL_DO_WEBHOOK" PROCESSO VALOR
```
   e repetir o MESMO comando para provar a idempotência ("já registada").

## 4. Candidato CSS (1 min, só verificação de inocuidade)
1. Confirmar que o visual de TODO o sistema está igual à v91 (o candidato
   não está em uso);
2. `ls wp-content/plugins/sige-softgenial/assets/style-consolidado.css`
   existe; a promoção fica para o dia dos screenshots (relatório próprio).

## 5. Regressão de sanidade (1 min)
- Login normal; Portaria lê crachá; um pagamento ao balcão regista.

## Critério de aprovação
Tudo verde = v12.11.9.92 validada; avançar escola a escola.
