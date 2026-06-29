# TESTES LIVE - v12.11.9.90 (Sprint 1)

Fazer primeiro na demo. Tempo total: ~20 minutos.

## 1. Escudo de login (5 min)
1. Janela anónima > wp-login > utilizador real + password ERRADA 5 vezes;
2. Esperado à 5ª: "o acesso desta conta está temporariamente suspenso...
   Tente novamente dentro de 10 minuto(s)";
3. Na janela normal (outra sessão), confirmar que o TEU login continua a
   funcionar (o bloqueio é por utilizador+IP, não global);
4. Auditoria do sistema: deve existir o evento login_shield com "lock".

## 2. 2FA por email (5 min, na demo)
1. `wp option update sige_2fa_email on`;
2. Iniciar sessão com um utilizador Director de teste: após a password,
   deve aparecer "Enviámos um código de verificação para d***@...";
3. Abrir o email (Zoho), introduzir password + código: entra;
4. Repetir com código errado: "Código de verificação incorrecto";
5. `wp option update sige_2fa_email off` no fim do teste.
NOTA: se o email não chegar (SMTP), o sistema DEIXA ENTRAR e regista
otp_email_falhou na auditoria. É desenho intencional: anti lock-out.

## 3. Circulares (5 min)
1. Entrar como Director > menu "Circulares";
2. Escopo "Apenas uma turma" > escolher turma > Pré-visualizar:
   confirmar contagem de famílias plausível e amostra de nomes;
3. Escrever mensagem de teste (>20 caracteres), marcar a confirmação,
   Enviar > banner verde com o número de famílias;
4. Central de Mensagens: itens tipo "circular" em estado pendente;
5. Aguardar o ciclo do cron: a mensagem chega ao WhatsApp com o nome
   da escola em negrito no topo;
6. Entrar como professor/guarda: o menu "Circulares" NÃO deve existir
   e o URL directo ?view=whatsapp_circulares deve mostrar o aviso de
   área reservada.

## 4. Relatório mensal (2 min, validação antecipada)
Sem esperar pelo dia 1:
```
wp option update sige_relatorio_mensal_email on
wp option update sige_relatorio_mensal_destinatarios "teu-email@dominio"
wp eval 'update_option("sige_relatorio_mensal_ultimo",""); do_action("sige_evento_diario");'
```
NOTA: o handler só dispara no dia 1; para teste imediato, correr:
```
wp eval 'if((int)current_time("j")!==1){echo "Hoje não é dia 1; o teste real fica agendado. Handler carregado: ".(has_action("sige_evento_diario")?"sim":"nao");}'
```
No dia 1 real: confirmar a chegada do email com os KPIs do mês fechado.

## 5. Heartbeat enriquecido (1 min)
```
wp eval 'print_r(SIGE_Hub_Heartbeat::send());'
```
Esperado: ok=true e, no Hub, o último payload com wpp_queue_pendentes,
cron_wpp_proximo, login_locks_24h preenchidos.

## 6. Regressão de sanidade (2 min)
- Portaria: ler um crachá (autorizado/bloqueado coerentes);
- Financeiro: abrir extractos de um aluno (saldos inalterados);
- Alunos: gravar uma edição trivial num aluno e confirmar status 'activo'
  na base (grafia canónica na escrita).

## Critério de aprovação
Tudo acima verde = v12.11.9.90 validada. Qualquer falha: reportar o ponto
exacto e NÃO avançar para as escolas pagantes.
