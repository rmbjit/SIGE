# Deploy - SIGE SoftGenial v12.11.9.88.2

## Build
`v12.11.9.88.2 - Financeiro Mensalidade Virtual & Classe Canonical Hotfix`

## Objectivo
Corrigir falhas no lançamento/pagamento de mensalidades quando:

- o aluno foi importado por Excel e a turma/classe não casava com a validação legacy;
- o serviço mensalidade usa classe composta, como `2º/3º Ano`;
- o pagamento pelo cartão mensal envia item virtual `MENS_01`, mas o backend não consegue transformá-lo numa dívida real;
- a matrícula tinha variação de status activa/ativa/activo/ativo.

## Passos de instalação
1. Fazer backup do plugin actual e da base de dados.
2. Instalar o ZIP `sige-softgenial-v12_11_9_88_2-financeiro-mensalidade-virtual-classe-canonical-hotfix.zip`.
3. Confirmar que o WordPress mostra a versão `12.11.9.88.2`.
4. Limpar cache do navegador e cache do WordPress, se existir.

## Testes recomendados

### Teste 1 - aluno importado por Excel
1. Confirmar que o aluno está numa turma do ano lectivo actual.
2. Confirmar que o status do aluno é `activo` ou `ativo`.
3. Abrir `Financeiro > Lançar Mensalidades`.
4. Seleccionar a mensalidade e o mês.
5. Lançar para esse aluno ou para a turma.
6. Resultado esperado: `Mensalidade: 1 criado` ou `1 actualizado`, em vez de `0 criados / 0 actualizados / 1 ignorado`.

### Teste 2 - pagamento via cartão mensal
1. Abrir `Financeiro > Registar Pagamento`.
2. Seleccionar o aluno.
3. No cartão do mês, marcar `Mensalidade`.
4. Registar pagamento.
5. Resultado esperado: o sistema materializa ou encontra a dívida real e regista o pagamento; não deve aparecer `Nenhuma dívida processada` com `MENS_01`.

### Teste 3 - classe composta
1. Usar um serviço com classe `2º/3º Ano`.
2. Testar aluno em turma de 2º Ano e aluno em turma de 3º Ano.
3. Resultado esperado: ambos são aceites como compatíveis.

### Teste 4 - aluno reactivado
1. Reactivar um aluno anteriormente desistente.
2. Abrir a página de pagamentos desse aluno.
3. Lançar ou pagar mensalidade.
4. Resultado esperado: o financeiro reconhece o aluno como activo operacionalmente.

## Rollback
Caso surja regressão inesperada, repor a build `v12.11.9.88.1` e comunicar:

- aluno afectado;
- turma/classe;
- serviço seleccionado;
- mês;
- screenshot do erro;
- se o aluno veio de importação Excel ou edição manual.

## Notas técnicas
Esta build não cria tabelas nem altera schema. A intervenção é de normalização, materialização segura de itens virtuais e diagnóstico.
