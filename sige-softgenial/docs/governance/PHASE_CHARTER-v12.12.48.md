# Carta da fase - v12.12.48 - Fase 4 incr 2 vaga 3 - Estilos financeiros para utilitarios

Terceira vaga do incremento 2 da Fase 4 (CSP e front-end seguro). O incremento 2 migra o inline por vagas, area a area, sob uma catraca que garante que o inline so desce. A vaga 1 (v12.12.46) tratou os onclick da area financeira; a vaga 2 (v12.12.47) tratou os estilos academicos. Esta vaga trata os estilos inline da area financeira.

## Objectivo

Mover o estilo inline da area financeira para classes utilitarias reutilizaveis, com a mesma especificidade, sob a catraca, sem mudar o comportamento visual nem as regras financeiras. E mais um passo a caminho de poder impor o CSP.

## Incluido

- Migracao conservadora de 32 atributos style em sete vistas financeiras (config, devedores, gerador, pagamentos, planos, relatorio mensal e mpesa), reutilizando o assets/sige-utilities.css da vaga 2, so quando o style e 100 por cento composto por declaracoes do mapa seguro, estaticas, sem PHP interpolado e sem class ja existente no elemento.
- Aperto da catraca check-inline-frontend: maximo de style de 2142 para 2110.
- Reconhecimento da classe sige-u-oxa como guarda de scroll valida tambem no smoke-regression-pack (o diag-responsivo ja a reconhecia desde a vaga 2), apos uma das trocas mover a guarda de uma tabela do financeiro-pagamentos de style overflow-x auto para a classe.
- Extensao do smoke-inline-frontend as vistas financeiras (usos de utilitarios e class duplicada).

## Excluido

- Estilos inline com class ja existente no elemento, estilos com display e estilos com valores dinamicos ou PHP interpolado: ficam para vagas futuras.
- As restantes areas (academica ja na vaga 2; admin-shell, jardim, recursos humanos, sistema e includes ficam para vagas seguintes).
- Os onclick remanescentes (250) e os blocos script inline (81): vagas proprias.
- CSP em enforcement (nonce por pedido e flip do cabecalho): incremento final da fase.
- Qualquer alteracao a regras de calculo financeiro, a ficheiros canonicos ou ao esquema.

## Riscos

- Regressao de cascata. Mitigacao: todos os utilitarios usam !important, replicando a especificidade do inline; o aspecto e identico.
- Quebrar a proteccao de scroll de uma tabela financeira. Mitigacao: a guarda movida para sige-u-oxa e contada pelos dois gates responsivos (diag-responsivo e smoke-regression-pack), confirmado por teste; a deteccao de tabelas genuinamente desprotegidas mantem-se.
- Tocar em regras financeiras. Mitigacao: so se migram atributos de estilo presentacional; nenhuma logica nem ficheiro canonico e tocado; o smoke-regression-pack confirma 306 invariantes.
- O inline voltar a subir. Mitigacao: a catraca trava style em 2110 (sem folga).

## Criterios de aceitacao

- As sete vistas financeiras migradas usam classes sige-u-, sem class duplicada, e passam no php -l.
- A catraca trava style em 2110 (sem folga); onclick 250 e script 81 inalterados.
- O financeiro-pagamentos mantem duas guardas de scroll (overflow-x auto inline mais sige-u-oxa); smoke-regression-pack verde.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
