# DEPLOY - SIGE SoftGenial v12.9.105

Saneamento Legacy Fechado · Base validada: v12.9.104

---

## 1. O que esta versão faz

- Corrige o vazamento multi-tenant no handler antigo de gravação de configurações.
- Bloqueia a gravação dos campos sem efeito real via POST legacy.
- Mantém intacta toda a UI visível, regras financeiras, motor WhatsApp, SMTP, Hub, permissões e schema da BD.
- Pode ser revertida apenas substituindo 5 ficheiros pelos da v12.9.104.

## 2. Ficheiros alterados (apenas 5 + 2 novos)

Modificados:
- `sige-softgenial.php`
- `BUILD.json`
- `includes/db-handler.php`
- `includes/audit-hooks.php`
- `admin/system/config-center-view.php`

Novos:
- `CHANGELOG-v12-9-105-saneamento-legacy-fechado.txt`
- `DEPLOY-v12-9-105.md`

Todos os restantes ficheiros são byte-a-byte idênticos à v12.9.104.

## 3. Pré-requisitos

- Backup da BD do(s) cliente(s) feito hoje (procedimento standard).
- Acesso ao painel Hostinger (`hpanel.hostinger.com`) com o utilizador da subscrição certa.
- Acesso ao CloudPanel se o cliente estiver no VPS.
- Manter aberta uma sessão WP-admin do cliente em paralelo para smoke test imediato.

## 4. Deploy via Hostinger CloudPanel (clientes no VPS)

### 4.1. Pasta do plugin
1. Login em `cloudpanel.softgenial.edu.mz` (ou o domínio que uses).
2. Sites > escolher o site do cliente (ex: `cicasacolorida.softgenial.edu.mz`).
3. File Manager > navegar até `htdocs/wp-content/plugins/`.

### 4.2. Backup do plugin actual
1. Selecionar a pasta `sige-softgenial`.
2. Clicar em "Compress" e gravar como `sige-softgenial-12.9.104-backup-AAAAMMDD.zip` na mesma pasta.

### 4.3. Substituir os ficheiros (NÃO apagar a pasta inteira)
Esta versão só toca em 5 ficheiros, por isso a forma mais segura é substituir só esses, não apagar a pasta toda.

Para cada um dos 7 ficheiros abaixo:
1. Eliminar o ficheiro existente na pasta do plugin instalado.
2. Fazer upload do ficheiro correspondente do ZIP v12.9.105 para a mesma localização.

Lista (caminhos relativos a `wp-content/plugins/sige-softgenial/`):
- `sige-softgenial.php`
- `BUILD.json`
- `includes/db-handler.php`
- `includes/audit-hooks.php`
- `admin/system/config-center-view.php`
- `CHANGELOG-v12-9-105-saneamento-legacy-fechado.txt` (novo)
- `DEPLOY-v12-9-105.md` (novo)

### 4.4. Confirmar versão
1. Abrir WP-admin do cliente: `wp-admin/plugins.php`.
2. Confirmar que SIGE SoftGenial aparece com versão **12.9.105**.
3. Não precisa de desactivar/reactivar.

## 5. Deploy via Hostinger File Manager partilhado (clientes no plano partilhado)

1. Login em `hpanel.hostinger.com`.
2. Hosting > Manage > File Manager.
3. Navegar até `public_html/wp-content/plugins/sige-softgenial/`.
4. Repetir os passos 4.2, 4.3 e 4.4 acima.

## 6. Smoke test obrigatório por cliente (5 minutos)

Por ordem, com utilizador director:

1. Login no admin do cliente.
2. Abrir o Centro de Configuração: WP-admin > SIGE > Configuração ou directo em `admin.php?page=sige-app&view=config_center`.
3. Confirmar que a aba Escola mostra os mesmos campos da v12.9.104 (nome, código MINEDH, NUIT, contactos, direcção).
4. Alterar o número de telefone oficial por um valor falso, gravar, recarregar a página, confirmar que ficou.
5. Reverter para o valor real, gravar.
6. Abrir um aluno qualquer > Recibos > gerar um recibo > confirmar que aparece o nome correcto da escola e o logo.
7. Em modo técnico (botão "Entrar no modo técnico SoftGenial"), abrir a aba Comunicação, confirmar que os campos WhatsApp aparecem mascarados, gravar sem mudanças.
8. Sair do modo técnico.
9. Abrir o ecrã antigo via `admin.php?page=sige-app&view=config&sgcp_legacy=1` (precisa de modo técnico activo). Confirmar que carrega.
10. Tentar gravar pela página antiga sem alterar nada - deve responder "Configurações Gravadas com Sucesso" (idempotente).
11. Confirmar no módulo financeiro que cobranças, multas e descontos do mês actual aparecem inalterados.
12. Confirmar que o relatório `whatsapp_diag-view` continua a indicar a fila operacional.

Se algum passo falhar, executar o rollback da secção 7 e contactar o suporte técnico SoftGenial antes de continuar.

## 7. Rollback (se necessário)

1. No File Manager, eliminar os 7 ficheiros listados em 4.3.
2. Extrair o ZIP de backup criado em 4.2 (`sige-softgenial-12.9.104-backup-AAAAMMDD.zip`) para a mesma localização.
3. Confirmar no `wp-admin/plugins.php` que voltou a aparecer 12.9.104.
4. Smoke test reduzido: login + abrir Centro de Configuração + gerar um recibo.

A reversão demora menos de 2 minutos e não envolve a BD em nada - nem schema nem dados.

## 8. Ordem recomendada de deploy nos clientes

1. `demo.softgenial.edu.mz` (ambiente de demonstração - começar aqui)
2. `softgenial.edu.mz/teste` (sandbox)
3. `lmuhalaze.softgenial.edu.mz` (Liceu Muhalaze - segundo cliente activo)
4. `cicasacolorida.softgenial.edu.mz` (Casa Colorida - só depois dos 3 acima passarem todos os smoke tests)
5. `malisa.softgenial.edu.mz` (Colégio Malisa - por último, com janela de 24h sem operações financeiras críticas)

Espaçar pelo menos 30 minutos entre clientes para captar qualquer regressão antes de propagar.

## 9. O que NÃO mudou (lista de garantias para o director da escola)

Se algum cliente perguntar o que muda nesta actualização:

- Nada visível na interface diária.
- Nada nos cálculos financeiros (mensalidades, multas, descontos, recibos).
- Nada nas notas, pautas, boletins ou regras académicas.
- Nada no envio de mensagens WhatsApp ou emails.
- Nada nas permissões de utilizadores.
- Nenhum dado antigo foi apagado.

A actualização é puramente de segurança e arrumação interna - fecha duas portas que estavam abertas sem necessidade e remove código que já não era usado.

## 10. Contacto para suporte

RMBJ Consultoria - Suporte técnico SoftGenial
Em caso de regressão observada após o deploy, capturar:
- URL da página onde ocorreu
- Mensagem de erro (se houver)
- Print do ecrã
- Versão WP do cliente (Dashboard > Welcome ou About)
- Nome do cliente e timestamp do deploy
