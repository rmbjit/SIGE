# Revisao adversarial - v12.12.47 - Fase 4 incr 2 vaga 2 - Estilos academicos para utilitarios

Rediagnostico adversarial da segunda vaga do incremento 2 da Fase 4. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Provocar regressao visual ao trocar inline por classe. Cada utilitario leva !important, replicando a especificidade do estilo inline (1000); a regra vence na cascata tal como o inline vencia. O aspecto e identico. O smoke confirma que os estilos puros migrados deixam de existir nas vistas e que as classes sige-u- estao presentes.
2. Fundir mal duas fontes de classe. A migracao salta qualquer elemento que ja tenha um atributo class, deixando esses casos para vagas futuras; o smoke confirma zero class duplicada nas vistas migradas. As dez vistas passam no php -l.
3. Migrar um valor dinamico ou com PHP. So se migram atributos style 100 por cento compostos por declaracoes do mapa seguro, estaticas, sem PHP interpolado; os demais ficam intactos.
4. Deixar o inline voltar a subir. A catraca check-inline-frontend desce o maximo de style de 2191 para 2142 e passa a estar sem folga (2142 de 2142); qualquer novo style inline fa-la falhar. O smoke smoke-inline-frontend foi registado no corredor.
5. Ter dois gates de catraca em conflito. O gate duplicado foi removido; existe um unico gate (check-inline-frontend) e o seu smoke. O corredor conta 99.
6. Esquecer o enfileiramento do CSS. O sige-utilities.css e enfileirado no admin-shell, dependente do sige-design-system; o smoke confirma o enqueue.
7. Acrescentar superficie, vista ou opcao. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9. Nao ha novo add_action nem nova vista nem nova opcao.
8. Partir os incrementos anteriores. Os gates de pagamentos, cofre, Fase 1 e Fase 4 incr 1 continuam verdes; o despachante data-sige-act da vaga 1 mantem-se; o release gate passa a partir de pasta limpa.

## P0

Nenhum. Vaga de interface; nao toca em regras de calculo nem em ficheiros canonicos. So muda a forma como o estilo e aplicado.

## P1

Nenhum. Os utilitarios replicam a especificidade do inline via !important; nao ha regressao de cascata e o aspecto e identico.

## P2 e P3

Nenhum novo. A migracao e conservadora (so atributos seguros, sem class existente); a catraca consolidada e o smoke no corredor impedem que o inline volte a subir.

## Decisao

Aprovado. Zero P0 e zero P1. A vaga 2 esta completa e provada por gate e smoke (corredor 99): doze utilitarios com !important, enfileirados no admin-shell; 49 trocas conservadoras em dez vistas academicas sem class duplicada; estilos puros migrados eliminados; e a catraca a travar style em 2142 sem folga. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem nova vista, sem opcoes novas, sem alteracao de esquema, calculo byte-identico, deps 9. Os gates anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se as vagas seguintes do incremento 2: mais areas de estilo e de onclick, depois os blocos script, e por fim o CSP em enforcement.
