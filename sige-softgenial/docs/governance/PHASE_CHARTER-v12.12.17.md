# Phase Charter - v12.12.17

Fase 6 (Ledger), incremento 2. Pagamentos no ledger e ancora externa contra
truncagem da cauda. Base: v12.12.16. Estado: entregue.

## Objectivo

Estender o livro-razao imutavel aos movimentos de dinheiro (pagamentos) e fechar a
deteccao de truncagem da cauda com uma ancora guardada fora da base de dados, de
modo que um agente com acesso apenas a base de dados nao possa apagar as entradas
mais recentes sem deteccao.

## Incluido

- Instrumentacao do registo de pagamento (sige_fin_registar_pagamento), apos sucesso, com evento estruturado (entidade pagamento, id do pagamento, montante aplicado, metodo, lancamento, referencia). Cobre M-Pesa, e-Mola, admin e planos, e tambem o pagamento anual, que delega nesta funcao (logo um unico ponto, sem duplicar). Sem tocar no calculo (md5 intactos); o pagamento_id e capturado antes do registo.
- Ancora externa por escola, em ficheiro fora da base de dados (wp-content/uploads/sige-private/ledger, com index.php e .htaccess a negar acesso web): a ultima seq, o ultimo hash e a contagem. Escrita atomica (temp mais rename) dentro da seccao critica do escritor, de forma resiliente.
- Verificador confronta a cadeia da base de dados com a ancora: ancora a frente da base de dados significa truncagem da cauda; hash divergente na cabeca significa adulteracao; ancora ausente e um estado distinto, nao fatal; ancora atrasada e tolerada (confere o hash no ponto registado).
- Ecra de integridade reflecte o estado da ancora numa coluna propria (sem endpoint novo; so leitura, so super admin).

## Excluido (adiado)

- Instrumentacao de lancamentos (com desenho de lote para a geracao mensal): incremento 3.
- Despesas, creditos e fechos de caixa: incremento posterior.
- Ancora adicional em SigeHub (anel extra, exige trabalho do servidor de licencas): posterior.

## Riscos e mitigacao

- Persistencia da ancora: vive em uploads e deve entrar nos backups; uma ancora atrasada e tolerada pela verificacao. A ancora persiste com o ledger (a remocao do plugin e suave e preserva as tabelas), mantendo a continuidade da integridade.
- Exposicao web do ficheiro: index.php e .htaccess a negar; o conteudo (seq mais hash) nao e segredo e nao permite forjar sem a chave HMAC.
- Compromisso ao nivel dos ficheiros (ancora mais salts): brecha mais ampla, fora de ambito, declarada.
- Nao quebrar operacoes: pagamento e ancora registados apos sucesso, com try/catch e best-effort; falha do ledger ou da ancora nao altera o resultado financeiro.

## Criterios de aceitacao

1. O registo de pagamento alimenta o ledger apos sucesso, cobrindo todos os fluxos (inclui o anual por delegacao), sem tocar no calculo.
2. Ancora por escola escrita em ficheiro fora da base de dados, atomica, dentro do bloqueio por escola, resiliente.
3. Verificador deteta truncagem da cauda, adulteracao da ancora e assinala ancora ausente.
4. Ecra reflecte o estado da ancora; sem endpoint novo (manifesto 196 inalterado).
5. Gate e smoke estendidos verdes; corredor completo verde; zero travessoes; versao sincronizada; raiz canonica.
