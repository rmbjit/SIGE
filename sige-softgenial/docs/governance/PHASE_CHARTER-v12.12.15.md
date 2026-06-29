# Phase Charter - v12.12.15

Fase 6 (Ledger financeiro), incremento 1. Livro-razao imutavel e a prova de
adulteracao das operacoes criticas. Base: v12.12.14. Estado: entregue.

## Estado real apurado

- Os eventos financeiros ja eram registados em sige_logs_auditoria, mas esse log e MUTAVEL (sem encadeamento): qualquer linha podia ser alterada ou apagada sem deteccao. Nao existia cadeia de hash.
- As 6 operacoes criticas (cancelLancamento, isentarLancamento, reactivarLancamento, bloquearMes, desbloquearMes, estornarPagamento) tem ponto de sucesso uniforme e ja estavam protegidas por tenant e MFA. Sao os ajustes que um agente interno usaria para manipular estado financeiro.
- O registo de pagamentos e de lancamentos nao tem ponto unico, pelo que fica para o incremento 2.

## Objectivo

Criar um livro-razao append-only e a prova de adulteracao, encadeado por
HMAC-SHA256 com chave derivada dos salts do WordPress, que regista de forma
imutavel as operacoes criticas. Alteracao, remocao ou insercao no meio da historia
passam a ser detectaveis, e um agente com acesso so a base de dados nao consegue
forjar uma cadeia coerente.

## Incluido

- Tabela append-only sige_fin_ledger (criada idempotentemente pela migracao): escola_id, seq (monotonica por escola), event_type, entidade, entidade_id, montante, actor, ocorrido_em, payload (JSON canonico), prev_hash, hash. Chave unica (escola_id, seq).
- Escritor sige_ledger_append: serializa por escola com GET_LOCK, calcula prev_hash e o hash HMAC do conteudo canonico, e insere. So insere, nunca actualiza nem apaga. Nunca quebra a operacao chamadora.
- Verificador sige_ledger_verify: percorre a cadeia por escola, recalcula o hash e confirma o encadeamento e a sequencia; aponta a primeira seq afectada.
- Instrumentacao das 6 operacoes criticas, apos sucesso, com evento estruturado. Apenas adicao de registo; sem tocar nas regras de calculo (md5 bloqueados intactos).
- Ecra de integridade do Ledger, so super admin (sige_is_real_wp_admin_user), so de leitura: mostra por escola o numero de eventos e se a cadeia esta integra ou onde quebrou. Sem POST, logo sem endpoint novo.

## Excluido (adiado)

- Instrumentacao de pagamentos, lancamentos, despesas, creditos e fechos de caixa: incremento 2.
- Ancoragem periodica externa (para detectar truncagem da cauda), visualizador completo e exportacao assinada: incrementos posteriores.

## Modelo de ameaca (honesto)

O ledger deteta adulteracao ao nivel da base de dados: a chave HMAC deriva dos
salts (wp-config.php), que um atacante so com acesso a base de dados nao tem, logo
nao recalcula a cadeia. Limites honestos desta fase: (1) a truncagem da CAUDA
(apagar as entradas mais recentes de forma contigua) nao deixa salto de sequencia e
so e detectavel com ancoragem externa, que fica para um incremento posterior; (2)
um compromisso ao nivel dos ficheiros que exponha os salts e uma brecha mais ampla,
fora deste ambito. Ambos ficam registados.

## Riscos e mitigacao

- Concorrencia: serializacao por escola (GET_LOCK) e chave unica (escola_id, seq); uma corrida nao corrompe a cadeia (no maximo um evento nao e registado sob timeout de bloqueio, sem criar salto).
- Nao quebrar as operacoes: o registo e feito apos a operacao suceder, com try/catch, e nunca altera o seu resultado. Sem tocar calculo.
- Rotacao de salts: invalida a verificacao das entradas antigas (a verificacao assinala), sem afectar os dados financeiros. Documentado.

## Criterios de aceitacao

1. Tabela append-only criada idempotentemente; chave unica (escola_id, seq).
2. Escritor encadeia por HMAC (chave dos salts), serializado por escola; so insere.
3. Verificador deteta alteracao de conteudo, remocao no meio (salto) e mudanca de chave.
4. As 6 operacoes registam no ledger apos sucesso, sem tocar calculo (md5 intactos).
5. Ecra de integridade so super admin, so leitura. Sem endpoint novo (manifesto 196 inalterado).
6. Gate e smoke verdes; corredor completo verde; zero travessoes; versao sincronizada; raiz canonica.
