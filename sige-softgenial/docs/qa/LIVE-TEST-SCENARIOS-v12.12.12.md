# Cenarios de teste live - v12.12.12 (reposicao automatica)

Passos reproduziveis em producao ou staging para validar a reposicao automatica
do seu lado. Faca-os de preferencia numa escola de teste (ex.: demo ou teste).
Cada cenario indica o que fazer e o que deve observar.

## 0. Preparacao

1. Instale a v12.12.12 (substituir o plugin pelo ZIP desta versao).
2. Confirme a versao em Plugins (deve ler 12.12.12).
3. Ligue o step-up (se ainda nao estiver): no painel, com um utilizador que possa,
   garanta a opcao sige_mfa_stepup = on. Em alternativa rapida, via WP-CLI ou um
   bloco temporario: update_option('sige_mfa_stepup','on').
4. Ligue a reposicao automatica: update_option('sige_mfa_autoreplay','on').
5. Tenha a mao dois utilizadores com perfil critico (sige_director ou
   sige_admin_ti):
   - Utilizador E: sem aplicacao autenticadora (confirma por email). Garanta que
     recebe email (caixa de entrada acessivel).
   - Utilizador T: com aplicacao autenticadora inscrita (Perfil > Autenticador
     SIGE, inscrito na v12.12.11).
6. Abra noutro separador o registo de seguranca (onde ja consulta os eventos
   sige_security_log) para acompanhar os eventos mfa_stepup e mfa_replay.

## Cenario A - Reposicao com email (utilizador E)

1. Entre como utilizador E.
2. Va a um lancamento de teste e clique Estornar (ou Cancelar) um pagamento.
3. Esperado: a operacao NAO se conclui de imediato; surge o pedido de
   confirmacao de identidade e um codigo e enviado por email. No registo aparece
   challenge_issued (ou challenge_totp_expected, se fosse T) e o evento
   mfa_replay capture.
4. Introduza o codigo recebido e confirme.
5. Esperado: a operacao conclui-se automaticamente. Surge o aviso "Identidade
   confirmada. A operacao critica foi concluida automaticamente." No registo
   aparece verify_ok e mfa_replay run. O estado do lancamento ja reflecte o
   estorno (ou cancelamento), sem ter repetido a operacao a mao.

## Cenario B - Reposicao com aplicacao autenticadora (utilizador T)

1. Entre como utilizador T.
2. Repita uma operacao das 6 (ex.: Isentar um lancamento de teste).
3. Esperado: surge o pedido de confirmacao a pedir o codigo da aplicacao (sem
   email). No registo, challenge_totp_expected e mfa_replay capture.
4. Introduza o codigo de 6 digitos da aplicacao e confirme.
5. Esperado: a operacao conclui-se automaticamente, com o mesmo aviso de
   conclusao. No registo, verify_ok_totp e mfa_replay run.

## Cenario C - Sem execucao dupla

1. Apos concluir o Cenario A ou B (operacao ja concluida automaticamente),
   tente repetir a MESMA operacao a mao no mesmo lancamento.
2. Esperado: a operacao NAO se executa de novo. Para um pagamento ja estornado
   ou um lancamento ja cancelado, surge a mensagem da guarda de estado (ex.: nao
   e possivel cancelar um lancamento ja cancelado, ou pagamento ja estornado). O
   valor/estado nao muda uma segunda vez.

## Cenario D - As 6 operacoes

Repita o ciclo (accao, confirmar, ver conclusao automatica) uma vez para cada uma
das 6 operacoes, em registos de teste:
1. Cancelar lancamento.
2. Isentar lancamento.
3. Reactivar lancamento.
4. Bloquear mes (de um aluno de teste).
5. Desbloquear mes.
6. Estornar pagamento (cobre tambem anular recibo).
Esperado em todas: bloqueio, confirmacao, e conclusao automatica da operacao
correcta, com os mesmos dados que tinha indicado (ex.: o motivo, o mes, o valor
do estorno).

## Cenario E - Config e caixa mantem repeticao manual

1. Como utilizador critico, va a Configuracao de pagamentos (e-Mola ou M-Pesa) e
   tente guardar uma alteracao. Confirme a identidade quando pedido.
2. Esperado: apos confirmar, a operacao NAO se repoe automaticamente; tem de
   guardar de novo (repeticao manual). Nao aparece o aviso de conclusao
   automatica.
3. Repita o mesmo na caixa (Reabrir ou Fechar caixa): apos confirmar, repete-se a
   mao. (E o comportamento desejado: a reposicao so abrange as 6 operacoes de
   servico.)

## Cenario F - Reposicao desligada (volta a v12.12.11)

1. Desligue a reposicao: update_option('sige_mfa_autoreplay','off').
2. Repita uma das 6 operacoes e confirme a identidade.
3. Esperado: apos confirmar, surge "Identidade confirmada. Pode repetir a
   operacao critica." e tem de repetir a operacao a mao. Sem reposicao
   automatica. (Comportamento identico ao da v12.12.11.)
4. Volte a ligar para os restantes testes: update_option('sige_mfa_autoreplay','on').

## Cenario G - Kill-switch de emergencia

1. Defina a constante no wp-config.php (ou onde define constantes do plugin):
   define('SIGE_MFA_AUTOREPLAY_OFF', true);
2. Esperado: a reposicao fica desligada independentemente da opcao (forca off).
   Confirme repetindo o Cenario F (comportamento manual). Remova a constante para
   reactivar.

## Cenario H - Janela e operacoes seguintes

1. Com a reposicao ligada, faca uma operacao e confirme (conclui-se sozinha).
2. Dentro de 5 minutos (janela de step-up), faca outra operacao das 6.
3. Esperado: a segunda operacao prossegue sem pedir nova confirmacao (a janela
   ainda esta aberta) e conclui-se normalmente. Nao ha duplicacao: cada operacao
   corre uma vez.

## Reposicao do ambiente apos os testes

- Reverta os lancamentos/alteracoes de teste conforme necessario.
- Se nao quiser a reposicao em producao para ja, deixe sige_mfa_autoreplay = off.
- O step-up e o TOTP podem ficar ligados de forma independente.

## O que reportar

Para cada cenario, indique: passou ou nao, o aviso que viu, e os eventos no
registo (mfa_replay capture / run, verify_ok ou verify_ok_totp). Se algum
cenario nao se comportar como descrito, envie o evento do registo e o ecra do
aviso para eu analisar.
