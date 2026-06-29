# Cenarios de teste live - SIGE SoftGenial v12.12.14

Secret Vault, incremento 1: credenciais dos gateways de pagamento cifradas em
repouso. Passos reproduziveis em producao ou staging, do lado do Rogerio. O
objectivo e confirmar que os segredos ficam cifrados na base de dados e que os
pagamentos continuam a funcionar.

Preparacao: ter a v12.12.14 instalada e activa, e acesso a configuracao dos
pagamentos (M-Pesa ou e-Mola) como administrador. Para os cenarios tecnicos (3 e
6) e util ter acesso a base de dados (phpMyAdmin/Adminer) ou ao WP-CLI.

---

## Cenario 1 - Configurar M-Pesa e confirmar que funciona

1. Entrar no painel e abrir a configuracao do M-Pesa (Financas, Pagamentos, M-Pesa).
2. Introduzir a API Key e a Public Key (ou, se ja estavam definidas, deixar em branco para manter) e Guardar.
   - Esperado: guarda sem erro. O ecra indica que a API Key e a Public Key estao definidas, sem mostrar os valores.
3. Fazer um pagamento de teste (sandbox) pelo fluxo habitual.
   - Esperado: o pagamento corre normalmente, como antes desta versao.

## Cenario 2 - O ecra nunca mostra o segredo

1. Na configuracao do M-Pesa (e do e-Mola), observar os campos API Key, Public Key, API Secret.
   - Esperado: os campos aparecem vazios (tipo password), com a indicacao "deixar vazio para manter: definida". Em nenhum momento o valor do segredo e mostrado no ecra nem no codigo-fonte da pagina.

## Cenario 3 - Confirmar na base de dados que esta cifrado (tecnico)

1. Antes de mais, garantir que um administrador ja abriu a configuracao dos pagamentos depois de instalar a v12.12.14 (isso cifra os segredos existentes; ver Cenario 5).
2. Na base de dados, na tabela de opcoes (wp_options ou com o prefixo da instalacao), procurar a opcao da credencial. O nome segue o padrao por escola:
   - sige_mpesa_escola_{ID}_api_key (ou sige_emola_escola_{ID}_api_key, sige_emola_escola_{ID}_api_secret, etc.)
3. Observar o valor guardado.
   - Esperado: o valor comeca por sige2: (ou gcm1:), ou seja, esta cifrado. NAO aparece a chave em claro.
   - Alternativa por WP-CLI: wp option get sige_mpesa_escola_{ID}_api_key devolve o valor cifrado (comeca por sige2:/gcm1:).

## Cenario 4 - O webhook continua a validar

1. Confirmar que a URL de webhook do gateway (M-Pesa/e-Mola) esta configurada como antes.
2. Provocar um callback de teste (pagamento sandbox que gere notificacao), ou usar a ferramenta de teste do gateway.
   - Esperado: o callback e aceite e associado a escola correcta. A cifragem do webhook_token em repouso nao afecta a validacao (o sistema revela o token antes de comparar).

## Cenario 5 - Migracao automatica dos segredos antigos

Aplica-se a quem ja tinha credenciais configuradas antes de actualizar.

1. Atualizar para a v12.12.14 sem reconfigurar nada.
2. Fazer um pagamento de teste.
   - Esperado: continua a funcionar (a leitura aceita o valor em claro antigo).
3. Entrar como administrador e abrir a configuracao dos pagamentos (basta abrir o ecra).
4. Voltar a verificar a opcao na base de dados (como no Cenario 3).
   - Esperado: o valor que estava em claro passou a estar cifrado (comeca por sige2:/gcm1:). A cifragem ocorre uma so vez; a partir daqui fica sempre cifrado.

## Cenario 6 - Selar nao perde o segredo (tecnico, opcional)

1. Confirmar que, depois da cifragem, um pagamento de teste continua a obter a credencial correcta (o sistema decifra ao usar).
   - Esperado: o pagamento funciona, prova de que a credencial cifrada e corretamente revelada para o gateway.

---

## Resumo do que cada cenario prova

- 1 e 5: os pagamentos funcionam, antes e depois da cifragem, sem reconfigurar.
- 2: o segredo nunca e mostrado no ecra.
- 3: na base de dados, a credencial esta cifrada (sige2:/gcm1:), nao em claro.
- 4: o webhook continua a validar com o token cifrado em repouso.
- 6: a credencial cifrada e corretamente revelada para uso (nao se perde nada).
