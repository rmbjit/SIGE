# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.25

Fase 8 incremento 3: apagamento por anonimizacao (direito ao apagamento). Primeira operacao destrutiva do produto.

## Rediagnostico adversarial

Revisao hostil da superficie destrutiva, procurando formas de causar dano, vazamento ou perda de integridade. Cada vector e confrontado com a sua mitigacao e verificacao.

1. Eliminacao de linhas a partir-integridade. O apagamento e por anonimizacao, nunca por DELETE/DROP/TRUNCATE. O gate estatico proibe estas operacoes no motor; o smoke confirma que so sao emitidos UPDATE. Registos financeiros, academicos e de auditoria mantem-se. Verificado.

2. Redaccao apaga um pseudonimo ou chave de ligacao. A lista de preservacao (numero_processo, aluno_id, data_hora) e excluida dos alvos. O smoke confirma que numero_processo e aluno_id nunca aparecem no SET dos UPDATE e que o aluno continua referenciavel. Verificado.

3. Redaccao apaga um valor financeiro. So sao redigidas colunas PII catalogadas. As colunas financeiras (mensalidade_base, valor, etc.) nao estao no catalogo, logo nunca entram nos alvos. O smoke inclui colunas financeiras simuladas e confirma que ficam intactas. Verificado.

4. Erro de tipo no UPDATE (data, numero, enumerado). A redaccao le o tipo vivo de cada coluna e resolve por classe: texto recebe marcador truncado ao comprimento; data anulavel fica nula e nao anulavel recebe sentinela; numero anulavel fica nulo e nao anulavel fica a zero; enumerado e mantido. Gate e smoke exercitam todos os casos. Verificado.

5. Colisao de chave unica ao redigir com marcador constante. Verificado no esquema que nenhuma coluna redigida participa numa chave unica (as unicas existentes sao sobre escola_id, aluno_id, turma_id e datas estruturais, todas preservadas). Sem colisoes possiveis. Verificado.

6. Execucao sem confirmacao (clique acidental). A confirmacao e em dois passos e validada no servidor: o operador escreve o numero de processo exacto e o handler so executa se coincidir (strcasecmp). Caso contrario, redirecciona com erro e nao toca em nada. Verificado no gate e no handler.

7. Execucao sem permissao. O handler exige privacidade.apagamento_executar (ou administrador real do WordPress) e recusa com wp_die 403. A permissao e critica, semeada so a administracao e direccao, negada a tesouraria, secretaria e docencia. Verificado.

8. CSRF ou repeticao. O endpoint exige nonce (check_admin_referer) e tem rate limit (5/300s) aplicado pelo Kernel em enforce. Verificado.

9. Vazamento entre escolas. Cada UPDATE inclui escola_id no WHERE e o aluno e revalidado por pertenca (fail-closed). O smoke confirma o isolamento e a recusa de aluno de outra escola, escola = 0 e aluno = 0. Verificado.

10. Falta de rasto. A auditoria regista a intencao antes (apagar_inicio) e o resultado depois (apagar_fim), com quem, que aluno, que campos e quando. A permissao consta da lista de auditoria sempre-registada. Verificado.

11. Reexecucao corrompe ou duplica. A operacao e idempotente: reanonimizar produz o mesmo estado final. O aluno e detectado como anonimizado pelo marcador no nome. O smoke reexecuta e confirma estado estavel. Verificado.

12. Deriva de governanca. Uma unica nova superficie (o endpoint de apagamento). Manifesto e Kernel regenerados e alinhados (199 == 199, enforce 33), zero criticos em observe. SCHEMA inalterada. Regras de calculo byte-identicas. Baselines de design sem regressao. Verificado.

## P0

Nenhum. Nao foram encontrados defeitos criticos. A operacao destrutiva esta contida por permissao critica, confirmacao em dois passos no servidor, fail-closed por escola, redaccao segura quanto ao tipo, preservacao de pseudonimos e valores financeiros, e auditoria antes e depois.

## P1

Nenhum. Os vectores de tipo, colisao, isolamento e confirmacao estao cobertos por gate estatico e smoke runtime.

## Decisao

Aprovado para entrega como v12.12.25. Sem P0 nem P1 em aberto. Achados que nao sao defeitos ficam documentados como decisoes de desenho: o apagamento e por anonimizacao (nao por eliminacao) por dever de retencao e integridade; nao ha desfazer por a anonimizacao ser permanente por desenho, sendo a salvaguarda a confirmacao em dois passos; o titular funcionario fica para incremento futuro. Proximo incremento da Fase 8: retencao e expurgo.
