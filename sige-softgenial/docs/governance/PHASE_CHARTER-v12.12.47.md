# Carta da fase - v12.12.47 - Fase 4 incr 2 vaga 2 - Estilos academicos para utilitarios

Segunda vaga do incremento 2 da Fase 4 (CSP e front-end seguro). O incremento 2 migra o inline por vagas, area a area, sob uma catraca que garante que o inline so desce. A vaga 1 (v12.12.46) tratou os onclick da area financeira com o despachante data-sige-act. Esta vaga trata os estilos inline da area academica.

## Objectivo

Mover o estilo inline da area academica para classes utilitarias reutilizaveis, com a mesma especificidade, sob a catraca, sem mudar o comportamento visual. E mais um passo a caminho de poder impor o CSP.

## Incluido

- Novo assets/sige-utilities.css com doze utilitarios: alinhamento (esquerda, centro, direita), peso de letra (700, 600, 500), margin a zero, flex-shrink a zero, flex a um, white-space nowrap, overflow-x auto e width a 100 por cento. Cada um com !important, para replicar a especificidade do estilo inline (1000) e evitar regressao de cascata.
- Enfileiramento do sige-utilities.css no admin-shell, dependente do sige-design-system, nas paginas do SIGE.
- Migracao conservadora de 49 atributos style em dez vistas academicas (abertura, acta, alunos, aprovar notas, auditoria de notas, boletim, encerramento, estatisticas demograficas, matriz e turmas), so quando o style e 100 por cento composto por declaracoes seguras, sem PHP interpolado e sem class ja existente no elemento.
- Aperto da catraca check-inline-frontend: maximo de style de 2191 para 2142.
- Consolidacao do mecanismo: um unico gate de catraca (o duplicado foi removido) e o smoke smoke-inline-frontend registado no corredor.

## Excluido

- Estilos inline com class ja existente no elemento (exigiriam fusao de classes), estilos com display (podem ser alternados por JavaScript) e estilos com valores dinamicos ou PHP interpolado: ficam para vagas futuras.
- As restantes areas (financeira ja na vaga 1; recursos humanos, jardim, sistema e includes ficam para vagas seguintes).
- Os onclick remanescentes (250) e os blocos script inline (81): vagas proprias.
- CSP em enforcement (nonce por pedido e flip do cabecalho): incremento final da fase.
- Qualquer alteracao a regras de calculo, a ficheiros canonicos ou ao esquema.

## Riscos

- Regressao de cascata (uma classe perder para outra regra). Mitigacao: todos os utilitarios usam !important, replicando a especificidade do inline; o aspecto e identico.
- Fundir mal duas fontes de classe. Mitigacao: a migracao salta qualquer elemento que ja tenha class; o smoke confirma zero class duplicada nas vistas migradas.
- Migrar um valor dinamico. Mitigacao: so se migram atributos 100 por cento compostos por declaracoes seguras e estaticas, sem PHP.
- O inline voltar a subir. Mitigacao: a catraca trava style em 2142 (sem folga) e o smoke esta no corredor.

## Criterios de aceitacao

- O sige-utilities.css existe com os doze utilitarios e !important, e esta enfileirado no admin-shell.
- As dez vistas academicas migradas usam classes sige-u-, sem class duplicada, e nao tem os estilos puros migrados remanescentes.
- A catraca trava style em 2142 (sem folga); onclick 250 e script 81 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Gate e smoke da catraca verdes; corredor 99; release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
