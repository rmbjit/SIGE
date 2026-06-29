# Revisao adversarial - v12.12.48 - Fase 4 incr 2 vaga 3 - Estilos financeiros para utilitarios

Rediagnostico adversarial da terceira vaga do incremento 2 da Fase 4. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Provocar regressao visual ao trocar inline por classe. Cada utilitario leva !important, replicando a especificidade do estilo inline (1000); a regra vence na cascata tal como o inline vencia. O aspecto e identico. O smoke confirma os usos de sige-u- nas vistas financeiras.
2. Partir a proteccao de scroll de uma tabela financeira. Uma das trocas moveu a guarda de uma tabela do financeiro-pagamentos de style overflow-x auto para a classe sige-u-oxa. O diag-responsivo ja reconhecia a classe (desde a vaga 2); o smoke-regression-pack passa a conta-la tambem (overflow-x auto inline mais sige-u-oxa). Confirmado por teste de injeccao: ao remover a guarda, ambos os gates voltam a acusar tabela a estourar.
3. Tocar em regras financeiras. So se migraram atributos de estilo presentacional (alinhamento, peso, margem, flex, overflow); nenhuma logica nem ficheiro canonico foi tocado. O smoke-regression-pack confirma 306 invariantes verdes (chavetas balanceadas, tokens, classes de estado de pagamento preservadas).
4. Fundir mal duas fontes de classe. A migracao salta qualquer elemento que ja tenha class (em financeiro-config e financeiro-pagamentos varios casos foram saltados por desenho); o smoke confirma zero class duplicada. As sete vistas passam no php -l.
5. Deixar o inline voltar a subir. A catraca desce o maximo de style de 2142 para 2110 e fica sem folga; qualquer novo style inline fa-la falhar.
6. Acrescentar superficie, vista ou opcao. Nada disso muda: superficie 199, vistas 60, opcoes 132, deps 9.
7. Partir os incrementos anteriores. Os gates de pagamentos, cofre, Fase 1, Fase 4 incr 1 e a vaga 2 continuam verdes; o despachante data-sige-act mantem-se; o release gate passa a partir de pasta limpa.

## P0

Nenhum. Vaga de interface; nao toca em regras de calculo financeiro nem em ficheiros canonicos.

## P1

Nenhum. Os utilitarios replicam a especificidade do inline via !important; a guarda de scroll movida e reconhecida pelos dois gates responsivos; nao ha regressao.

## P2 e P3

Nenhum novo. A migracao e conservadora (so atributos seguros, sem class existente); a catraca (sem folga) e os gates responsivos atualizados impedem regressao.

## Decisao

Aprovado. Zero P0 e zero P1. A vaga 3 esta completa e provada por gate e smokes (corredor 99): 32 trocas conservadoras em sete vistas financeiras sem class duplicada; a catraca a travar style em 2110 sem folga; a guarda de scroll do financeiro-pagamentos preservada e reconhecida pelos dois gates responsivos; e o smoke-regression-pack verde (306 invariantes). Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem nova vista, sem opcoes novas, sem alteracao de esquema, calculo byte-identico, deps 9. Os gates anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. Seguem-se as vagas seguintes do incremento 2: admin-shell e restantes areas de estilo, depois os onclick remanescentes, depois os blocos script, e por fim o CSP em enforcement.
