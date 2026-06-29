# DEPLOY - SIGE SoftGenial v12.11.7 - WhatsApp Send Test PRO (TEST)

## Objectivo
Instalar a versão que acrescenta um mecanismo de teste real de envio WhatsApp no painel de diagnóstico.

## Ficheiros alterados
- `sige-softgenial.php`
- `BUILD.json`
- `includes/whatsapp-diagnostics.php`
- `admin/whatsapp_diag-view.php`
- `admin/whatsapp_central-view.php`
- `includes/admin-shell.php`

## Teste recomendado depois da instalação
1. Aceder a `SIGE SoftGenial → Testar WhatsApp` ou abrir:
   `admin.php?page=sige-app&view=whatsapp_diag`
2. Confirmar que a configuração Z-API aparece com endpoint e token mascarado.
3. Clicar em “Verificar Estado da Sessão”.
4. Inserir um número de teste com indicativo, por exemplo `25884xxxxxxx`.
5. Clicar em “Enviar teste agora”.
6. Confirmar:
   - se o painel mostra sucesso, HTTP e ID da mensagem quando disponível;
   - se a mensagem chegou no telefone.

## Interpretação
- Sessão conectada + teste recebido: a configuração e envio directo estão funcionais.
- Sessão conectada + teste não recebido: verificar resposta da API, token, endpoint e painel Z-API.
- Teste directo funciona, mas fila não envia: verificar cron, janela segura, modo de saúde, limites diários, mensagens pendentes e guardrails.

## Áreas não alteradas
Esta versão não altera pagamentos, mensalidades, recibos, multas, notas, pautas, DEC, ACTA, currículos, permissões ou fórmulas de negócio.
