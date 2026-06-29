# Cenarios de teste em producao - SIGE SoftGenial v12.12.24

Fase 8 incremento 2: Direito de acesso e portabilidade. Data: 2026-06-21.

Estes cenarios validam o comportamento real apos o deploy. Execute-os com uma
escola de teste e um aluno conhecido. Os dados pessoais exportados sao reais:
trate os ficheiros com confidencialidade e apague-os apos o teste.

## 1. Acesso (apresentacao do dossie no ecra)

1.1. Entrar como administrador ou direccao.
1.2. Menu lateral, seccao PRIVACIDADE E DADOS, abrir "Acesso e Portabilidade".
1.3. No campo de procura, escrever o numero de processo de um aluno e Procurar.
   Esperado: o aluno aparece na lista de resultados.
1.4. Carregar em "Ver dossie".
   Esperado: cabecalho com nome e numero de processo; seccoes por tabela (ficha
   do aluno e, conforme existam dados, saude, matriculas, pagamentos, contactos
   de cobranca, transacoes moveis, acessos, historico de encarregados), cada uma
   com as suas colunas e linhas. Campos sensiveis assinalados.
1.5. Repetir a procura por nome (parcial).
   Esperado: lista com os alunos cujo nome contem o termo, so desta escola.

## 2. Portabilidade (exportacao em JSON)

2.1. No dossie de um aluno, carregar em "Exportar dossie (JSON)".
   Esperado: transferencia de um ficheiro dossie-dados-pessoais-<processo>-<data>.json.
2.2. Abrir o ficheiro num editor de texto.
   Esperado: JSON legivel com documento, sistema, gerado_em, aluno (id, nome,
   numero de processo), aviso de confidencialidade e seccoes (cada uma com tabela,
   campos e registos). Os valores correspondem aos do ecra.

## 3. Isolamento por escola (fail-closed)

3.1. Trocar para outra escola (se a conta tiver acesso a mais do que uma) ou pedir
   a um utilizador de outra escola.
3.2. Tentar abrir o dossie de um aluno que NAO pertence a escola activa (por
   exemplo, manipulando o aluno_id no endereco).
   Esperado: mensagem de que nao e possivel abrir o dossie deste aluno nesta
   escola; nenhum dado apresentado.

## 4. Permissao (privilegio minimo)

4.1. Entrar como tesoureiro, secretaria ou professor.
   Esperado: a opcao "Acesso e Portabilidade" NAO aparece no menu.
4.2. Aceder directamente ao endereco da pagina (page=sige-app&view=privacidade-acesso).
   Esperado: mensagem de area reservada; sem dossie.
4.3. Tentar a exportacao directa (POST a admin-post.php com action=sige_privacidade_exportar)
   sem a permissao.
   Esperado: acesso negado (403).

## 5. Auditoria

5.1. Apos uma exportacao bem sucedida (passo 2), consultar o registo de auditoria
   de permissoes.
   Esperado: uma entrada para privacidade.acesso_exportar com origem exportar_dossie,
   o aluno_id e a escola_id, o utilizador e a data/hora.

## 6. Limite de frequencia

6.1. Repetir a exportacao muitas vezes seguidas (mais de 10 em 5 minutos).
   Esperado: o Security Kernel trava os pedidos excedentes.

## Criterio de aceitacao

Todos os cenarios passam: acesso e portabilidade funcionam para quem de direito,
o isolamento por escola e estrito, a permissao e respeitada, cada exportacao fica
auditada e o limite de frequencia trava o abuso. Zero P0/P1.
