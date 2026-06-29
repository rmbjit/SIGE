# TESTES LIVE - v12.11.9.93 (Sprint 4 parcial)

Fazer primeiro na demo. Tempo total: ~12 minutos.

## 1. Pagamentos Móveis (4 min)
1. Financeiro > Pagamentos Móveis: título novo, cartões e-Mola
   (laranja, "webhook-first") com ambiente Desligado;
2. Guardar uma configuração e-Mola de teste (ambiente Sandbox, merchant
   de teste, api key qualquer) > banner verde "Configuração e-Mola
   guardada" > voltar a Desligado no fim;
3. Copiar o webhook e-Mola e simular um callback:
```
curl -X POST "URL_EMOLA_COM_TOKEN" -H "Content-Type: application/json" -d '{"transid":"EMTESTE1","clientreference":"PROCESSO_REAL","msisdn":"258861234567","amount":"VALOR_DO_LANCAMENTO"}'
```
   Esperado: {"status":"ok"...}; na tabela, a linha com badge laranja
   e-Mola CONCILIADA; nos extractos, o pagamento com método e-Mola;
4. Repetir o mesmo curl: "Transacção já registada", sem duplicar;
5. Token errado: 401/403 e nada entra.

## 2. Valores europeus (1 min, regressão do bug corrigido)
Callback M-Pesa com amount "2.350,50" para um processo com dívida >= 2350:
o valor registado deve ser 2.350,50 MT (não 2,35).

## 3. Mapa oficial de assiduidade (5 min)
1. Académico > Presenças > turma > "Relatório oficial";
2. Conferir: cabeçalho com o nome real da escola (e logo, se definido),
   coluna S preenchida (M/F), dias do mês, P/AT/F/J por aluno,
   linha "Presentes no dia" com somas certas, resumo M/F/média;
3. Imprimir: pré-visualização A4 PAISAGEM, só a folha (sem menus),
   linhas de assinatura no fundo;
4. Sem turma escolhida no mapa: o botão "Relatório oficial" está
   desactivado; URL manual ?relatorio=1 sem turma_id mostra aviso;
5. Como Guarda: ?view=presencas&relatorio=1&turma_id=X devolve o aviso
   de área reservada (guarda à frente do ramo).

## 4. Regressão de sanidade (2 min)
- Mapa normal de Presenças: cores e modal como na v92;
- Um callback M-Pesa normal concilia como antes (badge M-Pesa);
- Login, Portaria e pagamento ao balcão intactos.

## Critério de aprovação
Tudo verde = v12.11.9.93 validada; avançar escola a escola.
