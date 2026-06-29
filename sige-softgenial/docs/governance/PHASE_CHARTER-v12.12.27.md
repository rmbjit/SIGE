# PHASE_CHARTER - SIGE SoftGenial v12.12.27

Fase 8 (Dados, privacidade e retencao) - Incremento 4: Retencao e expurgo (so leitura).

## Objectivo

Dar a instituicao uma politica de retencao de dados pessoais (quanto tempo cada categoria deve ser guardada, com que fundamento) e a visibilidade do que ja a excedeu, fechando o arco da Fase 8: inventario (o que existe), acesso e portabilidade (ver e levar), apagamento (esquecer um titular) e retencao (quanto tempo e o que ja passou do prazo).

## Incluido

- Calendario de retencao declarado (includes/privacy/pii-retencao.php): mapeia cada categoria/tabela com dados pessoais a um prazo, a base legal, a coluna de data usada para aferir a idade do registo e o modo de expurgo (anonimizacao, retido ou fora do ambito). Valores por omissao conservadores, a rever pela instituicao. Sem base de dados propria, sem migracao de esquema.
- Ecra privacidade-retencao (so leitura): mostra o calendario e, por categoria, a contagem agregada de registos que ja excederam o prazo, sempre dentro da escola. Como o inventario, so contagens, nenhum dado individual, fail-closed por escola. Encaminha para o Apagamento e nao oferece qualquer eliminacao.
- Nova permissao privacidade.retencao_ver (risco medio, so leitura), semeada a administracao e direccao por migracao idempotente.
- Gate e smoke dedicados (check-retencao, smoke-retencao).

## Excluido

- Eliminacao em massa de qualquer tabela. Fica de fora por quebrar integridade: as presencas sao derivadas ao vivo do registo de acessos (apagar acessos antigos apagaria o historico de assiduidade) e os registos financeiros, academicos e de auditoria tem dever de retencao. Esta e a decisao de seguranca central deste incremento.
- Anonimizacao em lote de alunos. Fica deferida: exige um modelo fiavel de data de saida ou inactividade que o esquema actual nao tem de forma limpa. O expurgo de individuos faz-se um a um pelo ecra de Apagamento.
- Nova superficie destrutiva: o ecra e so de leitura. O manifesto e as regras do Kernel mantem-se em 199 (enforce 33).
- Migracao de esquema e alteracoes a regras de calculo.

## Riscos

- Contagem enganosa (apresentar como expurgavel algo que e estrutural ou ainda activo): mitigado por a contagem de alunos se restringir a nao activos, por classificar o registo de acessos e a auditoria como retidos, e por uma tabela sem coluna de data ser marcada como nao mensuravel em vez de contar zero silenciosamente.
- Vazamento entre escolas: mitigado por escola_id no WHERE de cada contagem e por fail-closed sem escola.
- Tratar a politica como parecer juridico: mitigado pelo aviso, no ecra e no calendario, de que e classificacao por omissao a rever pela instituicao enquanto responsavel pelo tratamento.
- Deriva de superficie: mitigado por nao haver qualquer endpoint ou accao destrutiva; manifesto e Kernel inalterados.

## Criterios de aceitacao

- O calendario cobre as categorias com dados pessoais, com prazo, base legal, coluna de data e modo de expurgo validos.
- O ecra abre so para quem tem privacidade.retencao_ver; mostra, por categoria, quantos registos excederam o prazo (agregado, por escola); fail-closed sem escola; nenhum dado individual; encaminha para o Apagamento e nao oferece eliminacao.
- O registo de acessos consta como retido (fonte das presencas, nao expurgavel).
- Sem nova superficie (199/33), SCHEMA inalterada, regras de calculo byte-identicas, baselines de design sem regressao, zero estilo inline, zero travessoes.
- Gate e smoke dedicados verdes; corredor sobe de 66 para 68; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
