# Cenarios de teste live - SIGE SoftGenial v12.12.22

Correccao de roteamento da Fase 7: as paginas "Reconciliacao e Divergencias" e
"Aprovacoes Pendentes" voltam a abrir. Testes reproduziveis numa instalacao real,
apos instalar o ZIP v12.12.22 e confirmar a versao 12.12.22 em Plugins.

Convencao: nao e preciso correr migracao (a versao do esquema nao muda). Cada
teste indica o utilizador, o passo e o resultado esperado.

## Pre-requisitos

- Plugin a ler versao 12.12.22.
- Pelo menos um utilizador com permissao de gestao de pagamentos moveis
  (financeiro.mobile_payments_gerir), tipicamente Direccao ou Secretaria Geral.
- Pelo menos um utilizador com permissao de estorno (financeiro.estornar) ou de
  reabertura de caixa (financeiro.caixa_reabrir).
- Um utilizador sem nenhuma destas permissoes (por exemplo, um professor), para o
  teste de acesso negado.

## Teste 1 - Reconciliacao abre (era o sintoma)

1. Entrar como utilizador com permissao de pagamentos moveis.
2. Abrir o menu financeiro (Tesouraria) e clicar em "Reconciliacao".
3. Esperado: abre o relatorio de divergencias (recebido por aplicar, divergencia
   de montante, pagamento sem gateway) e os totais por escola. NAO deve devolver a
   pagina inicial.

## Teste 2 - Aprovacoes Pendentes abre (era o sintoma relatado)

1. Entrar como utilizador com permissao de estorno ou de reabertura de caixa.
2. No menu financeiro, clicar em "Aprovacoes".
3. Esperado: abre a lista de pedidos pendentes e as decisoes recentes. NAO deve
   devolver a pagina inicial.

## Teste 3 - Acesso negado mostra mensagem (e nao o painel)

1. Entrar como utilizador sem as permissoes acima.
2. Tentar abrir "Reconciliacao" e depois "Aprovacoes" (se as entradas estiverem
   visiveis; caso contrario, navegar pelo endereco ?page=sige-app&view=financeiro-
   reconciliacao e ?page=sige-app&view=financeiro-aprovacoes).
3. Esperado: em cada uma, mensagem de area reservada (Reconciliacao: reservada a
   Direccao e Secretaria Geral; Aprovacoes: reservada a quem pode estornar ou
   reabrir caixa). NAO deve cair no painel inicial nem mostrar o conteudo.

## Teste 4 - Sem regressao nas restantes paginas financeiras

1. Como utilizador com acesso financeiro, percorrer Pagamentos, Extratos,
   Devedores, Lancamentos, Despesas, Centros de Custo, Relatorio Mensal e M-Pesa.
2. Esperado: todas abrem normalmente, como antes. A correccao nao afecta nenhuma
   outra pagina.

## Teste 5 - Navegacao assinala a pagina activa

1. Abrir "Reconciliacao" e depois "Aprovacoes".
2. Esperado: a entrada correspondente fica assinalada como activa no menu, e o
   titulo da pagina corresponde a pagina aberta.

## Teste 6 - Fluxo de quatro-olhos continua intacto (regressao funcional)

1. Como utilizador autorizado, pedir um estorno a partir de Extratos.
2. Esperado: nao executa de imediato; aparece em "Aprovacoes" como pendente.
3. Como segundo utilizador autorizado, aprovar em "Aprovacoes" (pode ser pedida
   confirmacao de identidade).
4. Esperado: a operacao executa e fica registada; o saldo corrige. Confirma que a
   pagina reposta serve de facto o fluxo para que foi criada.

## Resultado esperado global

As duas paginas da Fase 7 ficam acessiveis a quem tem a permissao respectiva e
fechadas (com mensagem) a quem nao tem; nenhuma outra pagina e afectada; o fluxo
de quatro-olhos opera de ponta a ponta a partir da pagina reposta.
