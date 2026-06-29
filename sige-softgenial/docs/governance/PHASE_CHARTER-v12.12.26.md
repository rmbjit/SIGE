# PHASE_CHARTER - SIGE SoftGenial v12.12.26

Fase 8 (Dados, privacidade e retencao) - Incremento 3.2: Completar o catalogo de PII (fechar as lacunas).

## Objectivo

Fechar as 12 lacunas do catalogo de dados pessoais (colunas com aspeto de PII que o inventario da Incr 1 detectou no esquema mas que estavam fora do catalogo), para que os direitos do titular ja entregues funcionem por completo. Como o dossie de acesso/portabilidade (Incr 2) e o motor de anonimizacao (Incr 3) leem o catalogo como fonte da verdade, uma coluna nao catalogada e invisivel para ambos: hoje, campos identificaveis como o documento de identidade digitalizado e os contactos de emergencia sobrevivem a um apagamento. Este incremento corrige isso pela classificacao, sem novo codigo de motor.

## Incluido

- Classificacao das 12 colunas em lacuna no catalogo (includes/privacy/pii-catalog.php), com categoria, sensibilidade, finalidade e base legal. O catalogo passa de 76 para 88 campos; as lacunas passam de 12 para 0; os campos sensiveis passam de 19 para 24.
- As nove colunas identificaveis de sige_alunos (foto, doc_bi_url, encarregado_principal_tipo, autorizado_buscar_parentesco, autorizado_buscar_telemovel, autorizado_buscar_documento, contacto_emergencia_1, contacto_emergencia_2, encarregado_observacoes) passam a aparecer no dossie do titular e a ser redigidas pelo motor de anonimizacao, automaticamente, por ja estarem catalogadas.
- As duas datas operacionais de cobranca (sige_fin_contactos_cobranca.data_contacto e proximo_contacto) sao classificadas mas preservadas na anonimizacao: entram na lista de preservacao, a par de aluno_id e numero_processo, por serem registo financeiro operacional. Aparecem no dossie do titular sem corromper a cronologia de cobranca.
- A coluna de notas de funcionario (sige_professores.observacoes) e classificada, mas fica fora do ambito do apagamento do aluno, porque a tabela nao se liga ao aluno; sera tratada no futuro incremento do titular funcionario.
- Extensao do smoke do apagamento para provar que os novos campos identificaveis (incluindo o documento de identidade e os contactos de emergencia) sao redigidos e que as datas de cobranca sao preservadas.

## Excluido

- Nova superficie de accao (endpoints, formularios). O manifesto e as regras do Kernel mantem-se em 199 (enforce 33).
- Migracao de esquema (SCHEMA_VERSION inalterada).
- Alteracoes a regras de calculo financeiro ou academico.
- Apagamento do titular funcionario (continua deferido).

## Riscos

- Classificar de menos (deixar uma lacuna por fechar): mitigado por o inventario detectar as lacunas de forma deterministica pelo nome e por o smoke do apagamento exercitar os campos identificaveis recem-catalogados.
- Redigir um campo que devia ser preservado (registo operacional): mitigado por colocar as datas de cobranca na lista de preservacao, verificado pelo smoke (data_contacto e proximo_contacto nunca entram no UPDATE).
- Introduzir uma categoria ou base legal invalida: mitigado por validacao contra o conjunto declarado de categorias e bases.
- Regressao no apagamento ou no inventario por o catalogo crescer: mitigado por todos os gates de privacidade e de apagamento se manterem verdes com o catalogo de 88 campos.

## Criterios de aceitacao

- O catalogo cobre as 12 colunas antes em lacuna; o inventario passa a indicar 0 lacunas (88 campos catalogados, 0 desvios).
- O dossie do titular passa a incluir os campos identificaveis recem-catalogados; o motor de anonimizacao passa a redigi-los.
- As datas operacionais de cobranca aparecem no dossie mas sao preservadas na anonimizacao.
- Manifesto e Kernel inalterados (199 == 199, enforce 33); SCHEMA inalterada; regras de calculo byte-identicas; baselines de design sem regressao; zero estilo inline; zero travessoes.
- Smoke do apagamento estendido verde; corredor mantem-se em 66; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
