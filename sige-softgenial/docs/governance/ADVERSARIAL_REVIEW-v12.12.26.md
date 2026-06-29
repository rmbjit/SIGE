# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.26

Fase 8 incremento 3.2: completar o catalogo de PII (fechar as lacunas).

## Rediagnostico adversarial

Revisao hostil da classificacao, procurando classificacao incompleta, redaccao indevida ou regressao nos direitos do titular ja entregues.

1. Lacuna por fechar. O inventario detecta lacunas de forma deterministica pelo nome da coluna. As 12 colunas sinalizadas foram todas classificadas; o catalogo passa de 76 para 88 campos e as lacunas para 0. Verificado pelo recontar do catalogo e pelos gates de privacidade.

2. Campo identificavel continua a sobreviver a um apagamento. As nove colunas identificaveis de sige_alunos estao agora catalogadas e, por isso, sao redigidas pelo motor de anonimizacao. O smoke prova que foto, doc_bi_url (documento de identidade), contacto_emergencia_1, autorizado_buscar_telemovel e autorizado_buscar_documento e encarregado_observacoes sao redigidos com marcador. Buraco fechado. Verificado.

3. Redaccao indevida de registo operacional. As datas de cobranca (data_contacto, proximo_contacto) sao classificadas mas entram na lista de preservacao, para nao corromper a cronologia da cobranca. O smoke prova que nunca entram no UPDATE. Verificado.

4. Funcionario apanhado no apagamento do aluno. sige_professores.observacoes e classificada, mas a tabela nao se liga ao aluno (sem aluno_id), pelo que o fluxo de apagamento do aluno nao lhe toca. Verificado pela logica de ligacao do motor (so sige_alunos por id e tabelas com aluno_id).

5. Categoria ou base legal invalida. Todas as 12 entradas usam categorias e bases do conjunto declarado. Verificado por validacao explicita.

6. Colisao na lista de preservacao. data_contacto e proximo_contacto sao nomes especificos da tabela de cobranca; nao existem noutras tabelas redigidas, pelo que a preservacao por nome nao afecta outras colunas. Verificado.

7. Regressao no inventario por o catalogo crescer. O detector de lacunas usa um esquema simulado com uma coluna sintetica; continua a detecta-la. Os gates de privacidade mantem-se verdes com o catalogo de 88 campos. Verificado.

8. Deriva de superficie ou de calculo. Sem novos endpoints: manifesto e Kernel mantem-se em 199 (enforce 33), alinhados. Regras de calculo byte-identicas. SCHEMA inalterada. Baselines de design sem regressao. Verificado.

## P0

Nenhum. A classificacao fecha o buraco do apagamento sem introduzir nova superficie nem tocar no calculo.

## P1

Nenhum. A separacao entre redigir (identificavel) e preservar (operacional) esta coberta pelo smoke.

## Decisao

Aprovado para entrega como v12.12.26. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: as datas operacionais de cobranca sao preservadas (registo financeiro, como as datas de pagamento); as notas de funcionario ficam para o futuro incremento do titular funcionario. As bases legais e finalidades sao classificacao por omissao a rever pela instituicao enquanto responsavel pelo tratamento. Proximo incremento da Fase 8: retencao e expurgo (Incr 4).
