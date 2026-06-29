# Phase Charter - v12.12.16

Patch correctivo da Fase 6 incremento 1. Corrige um defeito da entrega v12.12.15
apanhado em uso real: a tabela do ledger nao era criada nas instalacoes
actualizadas por substituicao de ficheiros, e o ecra mostrava o erro cru da base
de dados. Base: v12.12.15. Estado: entregue.

## Objectivo

Garantir que a tabela do ledger e criada em todas as instalacoes (nao so as
reactivadas) e que o ecra de integridade nunca mostra o erro cru da base de dados,
sem alterar a logica do ledger nem o calculo financeiro.

## Defeito apurado (em producao/teste)

Ao abrir Definicoes > Integridade do Ledger, o ecra mostrava:
"Table '..._sige_fin_ledger' doesn't exist". Duas causas:

1. Causa-raiz: a migracao so re-corre quando a constante interna SCHEMA_VERSION
   muda (maybe_upgrade no admin_init compara o option sige_schema_version com
   SCHEMA_VERSION). Na v12.12.15 acrescentou-se a tabela mas NAO se subiu a
   SCHEMA_VERSION, pelo que o gate devolvia cedo e a tabela nunca era criada por
   actualizacao de ficheiros (so por reactivacao do plugin, que corre activate sem
   gate).
2. Fragilidade: as funcoes de leitura do ledger consultavam a tabela sem verificar
   a sua existencia, emitindo o erro cru do WordPress no ecra.

## Correccao

- Subida da SCHEMA_VERSION (20260611.1 para 20260620.1): maybe_upgrade volta a
  correr a migracao em qualquer instalacao desactualizada e cria sige_fin_ledger
  via dbDelta (idempotente, sem perda de dados).
- Guard de existencia sige_ledger_table_exists() (com SHOW TABLES e supressao de
  erros), aplicado ao escritor, ao verificador, a lista de escolas e ao ecra. Sem
  tabela: o escritor nao regista (devolve false), o verificador devolve vazio, e o
  ecra mostra uma mensagem clara a indicar a actualizacao, em vez do erro cru.

## Incluido

- includes/class-sige-migration.php: SCHEMA_VERSION subida.
- includes/finance-ledger.php: sige_ledger_table_exists() e guards.
- Smoke reforcado (resiliencia a tabela ausente) e gate reforcado (exige a subida da
  SCHEMA_VERSION e o guard de existencia aplicado).

## Excluido

- Nenhuma alteracao a logica do ledger, ao encadeamento HMAC, as 6 operacoes
  instrumentadas, ao calculo financeiro ou a superficie de accoes. So a criacao da
  tabela e a resiliencia de leitura.

## Riscos e mitigacao

- dbDelta sobre as tabelas existentes: idempotente, nao apaga nem altera dados; so cria o que falta. Backup de rotina antes de actualizar.
- Criacao da tabela durante o pedido: maybe_upgrade corre no admin_init antes do render; o guard so memoriza o resultado positivo, pelo que o ecra ja ve a tabela criada no mesmo pedido.
- Sem alteracao de calculo nem de superficie: risco de regressao minimo; md5 das regras de calculo verificados intactos.

## Criterios de aceitacao

1. maybe_upgrade cria a tabela em instalacoes desactualizadas (SCHEMA_VERSION subida).
2. Sem tabela, o ecra e as funcoes degradam sem erro cru; com tabela, funcionam como antes.
3. Gate e smoke verdes; corredor completo verde; manifesto 196 inalterado; zero travessoes; versao sincronizada; raiz canonica.
4. md5 das regras de calculo intactos.
