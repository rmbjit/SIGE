# PHASE_CHARTER - SIGE SoftGenial v12.12.25

Fase 8 (Dados, privacidade e retencao) - Incremento 3: Apagamento por anonimizacao (direito ao apagamento).

## Objectivo

Dar a instituicao o meio auditavel e seguro de responder a um pedido de apagamento (direito ao esquecimento) de um aluno, eliminando os seus dados pessoais identificaveis sem destruir os registos financeiros e academicos que tem o dever legal de reter. O apagamento e feito por anonimizacao: a pessoa deixa de ser identificavel, mas os registos continuam estruturalmente e financeiramente integros.

## Incluido

- Motor de anonimizacao (includes/privacy/pii-apagamento.php): dado um aluno da escola activa, calcula a pre-visualizacao de impacto (que campos PII, em que tabelas, serao redigidos, e o que e preservado) e executa a anonimizacao. A redaccao e segura quanto ao tipo, lido em runtime via SHOW COLUMNS: texto recebe um marcador truncado ao comprimento da coluna; datas e numeros anulaveis ficam nulos; datas nao anulaveis recebem uma sentinela neutra; numeros nao anulaveis ficam a zero; tipos enumerados sao mantidos. So toca colunas PII catalogadas (Incr 1), nunca estruturais ou financeiras. Idempotente e fail-closed por escola.
- Lista de preservacao: numero_processo, aluno_id e data_hora sao catalogados como PII mas nunca sao redigidos, por serem pseudonimos, chaves de ligacao ou carimbos estruturais. Todos os valores financeiros e academicos (nao catalogados como PII) sao preservados.
- Ecra privacidade-apagamento: procura de aluno (so leitura), pre-visualizacao de impacto e confirmacao em dois passos. A accao destrutiva so fica disponivel depois de o operador escrever o numero de processo exacto do aluno.
- Endpoint admin_post sige_privacidade_apagar, governado pelo Security Kernel em modo enforce, risco critico: revalida o aluno na escola, confirma o numero de processo no servidor, executa a anonimizacao, regista auditoria antes e depois e devolve ao ecra. Com nonce, rate limit (5/300s) e isolamento por escola.
- Nova permissao privacidade.apagamento_executar (risco critico), semeada so a administracao e direccao por migracao idempotente e sempre auditada.
- Gate e smoke dedicados (check-apagamento, smoke-apagamento).

## Excluido

- Eliminacao fisica de linhas da base de dados. Fica de fora por dever de retencao e integridade; a anonimizacao e a forma de apagamento neste incremento.
- Apagamento do titular funcionario (este incremento cobre o aluno, que ja inclui o encarregado e o agregado registados na sua ficha).
- Desfazer (undo): a anonimizacao e permanente por desenho. Guardar o original para reverter contrariaria o proprio apagamento. A salvaguarda e a confirmacao em dois passos, nao a reversao.
- Alteracoes a regras de calculo financeiro ou academico.
- Migracao de esquema (SCHEMA_VERSION inalterada).

## Riscos

- Operacao destrutiva e irreversivel: mitigada por confirmacao em dois passos no servidor (numero de processo escrito a mao), guarda de permissao critica, fail-closed por escola e auditoria antes e depois.
- Erro de tipo no UPDATE de redaccao: mitigado por leitura do tipo vivo de cada coluna e por uma resolucao segura por classe de tipo (texto, data, numero, enumerado), com o marcador truncado ao comprimento da coluna.
- Perda de integridade financeira ou estrutural: mitigada por so redigir colunas PII catalogadas, nunca financeiras ou estruturais, e por preservar a lista de pseudonimos e ligacao. Verificado que nenhuma coluna redigida participa numa chave unica (sem colisoes).
- Vazamento entre escolas: mitigado por escola_id no WHERE de cada UPDATE e pela revalidacao de pertenca do aluno.
- Crescimento descontrolado da superficie: mitigado por uma unica nova superficie (o endpoint de apagamento), com manifesto e Kernel regenerados e alinhados (199 == 199).

## Criterios de aceitacao

- A anonimizacao redige os campos PII catalogados de um aluno da escola activa (proprios e do encarregado) e preserva numero de processo, identificadores internos e valores financeiros e academicos; aluno de outra escola e recusado.
- O ecra abre so para quem detem privacidade.apagamento_executar; a pre-visualizacao mostra o impacto; a accao so executa apos o numero de processo escrito coincidir no servidor.
- O endpoint e critico, em enforce, com nonce, rate limit, isolamento por escola e auditoria antes e depois.
- Permissao com privilegio minimo; negada a tesouraria, secretaria e docencia.
- Manifesto e Kernel em 199 (enforce 33), coerentes; SCHEMA inalterada; regras de calculo financeiro byte-identicas; baselines de design sem regressao (2414/7497); zero estilo inline; zero literais hexadecimais; zero travessoes.
- Gate e smoke dedicados verdes; corredor sobe de 64 para 66; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
