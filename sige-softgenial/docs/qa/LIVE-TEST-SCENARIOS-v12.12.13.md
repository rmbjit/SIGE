# Cenarios de teste live - SIGE SoftGenial v12.12.13

Painel de controlo de seguranca (MFA), so super admin. Passos reproduziveis em
producao ou staging, do lado do Rogerio. Cada cenario indica o que fazer e o
resultado esperado. Nao requer codigo nem base de dados.

Preparacao: ter a v12.12.13 instalada e activa. Ter pelo menos duas contas:
(A) um administrador WordPress real (perfil administrator); (B) uma conta com o
perfil Admin IT (sige_admin_ti). Se nao tiver a conta (B), pode usar qualquer
conta com um perfil nativo do SIGE (por exemplo Director).

---

## Cenario 1 - O ADMIN Super ve e usa o ecra

1. Entrar como administrador WordPress (conta A).
2. No menu lateral, ir a Definicoes, Seguranca (MFA).
   - Esperado: a pagina abre com o titulo "Seguranca (MFA)" e quatro blocos: Step-up, Reposicao automatica, Modo estrito, e Perfis abrangidos, mais uma linha so de leitura com o numero de utilizadores com a aplicacao autenticadora.
3. Marcar "Step-up" (Ligado), deixar os restantes como estao, e carregar em Guardar.
   - Esperado: a pagina recarrega e mostra "Definicoes guardadas." em verde, com o Step-up agora marcado.

## Cenario 2 - O Admin IT NAO ve o ecra

1. Terminar a sessao e entrar com a conta Admin IT (sige_admin_ti) (ou outro perfil nativo do SIGE).
2. Olhar para o menu Definicoes.
   - Esperado: o item "Seguranca (MFA)" NAO aparece. Em muitos casos o proprio menu Definicoes nao aparece (os perfis SIGE nao tem manage_options).

## Cenario 3 - Acesso directo pelo URL e recusado

1. Ainda com a conta Admin IT (ou outro perfil nativo do SIGE), colar na barra de endereco o URL do ecra (substituir o dominio pelo da escola):
   - https://SEU-DOMINIO/wp-admin/options-general.php?page=sige-mfa-settings
2. Abrir.
   - Esperado: o WordPress recusa com "Acesso restrito ao administrador WordPress." (erro 403). O ecra nao e mostrado e nada e gravado.

## Cenario 4 - Tentativa de gravacao directa e recusada

Este cenario confirma que nem um POST forjado passa. E opcional (tecnico).

1. Com a conta Admin IT, tentar submeter o formulario do ecra (por exemplo, reutilizando um pedido) para wp-admin/admin-post.php com action=sige_mfa_settings_save.
   - Esperado: recusa com 403 ("Acesso restrito ao administrador WordPress."), antes de qualquer gravacao. Mesmo que a conta tenha manage_options herdado, e barrada.

## Cenario 5 - Auditoria das alteracoes

1. Voltar a entrar como administrador WordPress (conta A).
2. Em Definicoes, Seguranca (MFA), DESLIGAR o Step-up (desmarcar) e Guardar.
3. Abrir o registo de auditoria/seguranca do SIGE (onde costuma consultar os eventos).
   - Esperado: aparece um evento de alteracao das definicoes (mfa_settings_change) com a transicao do Step-up de on para off, e ainda um evento distinto a assinalar que o Step-up foi desligado (mfa_stepup_disabled), com o identificador do utilizador.
4. Voltar a LIGAR o Step-up e Guardar.
   - Esperado: novo evento mfa_settings_change com a transicao de off para on.

## Cenario 6 - Perfis abrangidos

1. Como administrador WordPress, em Perfis abrangidos, marcar apenas Director e Admin IT (deixar os outros desmarcados) e Guardar.
   - Esperado: guardado; so esses dois perfis ficam sujeitos ao step-up.
2. (Demonstrativo) Desmarcar todos os perfis com o Step-up ligado e Guardar.
   - Esperado: guardado; a descricao avisa que, sem perfis marcados, o step-up nao abrange ninguem (equivale a desligado). Voltar a marcar os perfis pretendidos a seguir.

## Cenario 7 - Avisos do PHP deixam de aparecer no wp-admin

1. Como administrador WordPress, navegar pelas paginas do wp-admin (incluindo Utilizadores, Autenticador SIGE).
   - Esperado: ja nao aparece a faixa amarela de aviso do PHP (a mensagem strip_tags ... null no topo). Os avisos continuam a ser registados no log do servidor, apenas deixam de ser mostrados no ecra.
2. (Opcional, para quem depura) Activar WP_DEBUG ou desligar a opcao sige_admin_hide_php_notices.
   - Esperado: os avisos do PHP voltam a ser mostrados no ecra (comportamento de depuracao).

---

## Resumo do que cada cenario prova

- 1: o dono gere os controlos a partir do painel, sem codigo.
- 2 e 3: o Admin IT e os outros perfis nativos do SIGE nao veem nem alcancam o ecra.
- 4: nem um POST forjado por um perfil SIGE consegue gravar.
- 5: desligar o step-up fica registado, com evento proprio.
- 6: o dono escolhe os perfis abrangidos.
- 7: o wp-admin deixa de mostrar avisos do PHP em producao.
