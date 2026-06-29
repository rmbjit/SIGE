# Carta da fase - v12.12.49 - Fase 4 incr 2 - Fixe de impressao utilitarios e vaga admin-shell e jardim

Incremento 2 da Fase 4 (CSP e front-end seguro), em duas partes: uma correccao de impressao das classes utilitarias introduzidas nas vagas anteriores, e mais uma vaga de migracao de estilo inline (admin-shell e jardim).

## Objectivo

Tornar fiel a impressao das vistas ja migradas, garantindo que as classes utilitarias resolvem tambem nas janelas de impressao autonomas, e prosseguir a reducao do estilo inline no admin-shell e no jardim, sob a catraca, sem mudar o comportamento visual nem as regras de calculo.

## Incluido

PARTE A - correccao de impressao:
- Helper sige_utilities_inline_css() em includes/assets-registry.php, que devolve as 12 regras utilitarias com !important (as mesmas do assets/sige-utilities.css), seguro de embeber em janelas de impressao (sem aspas simples nem backticks).
- Embebe-se esse helper no <style> de cada popup de impressao autonomo que clona ou gera marcacao com classes sige-u-: boletim, alunos_lista, turmas (dois popups), financeiro-devedores e, por uniformidade defensiva, estatisticas.
- Guarda nova no smoke-inline-frontend: qualquer vista com popup de impressao (window.open mais document.write) e classes sige-u- tem de embeber o helper.

PARTE B - vaga admin-shell e jardim:
- Migracao conservadora de 59 estilos inline para utilitarios: includes/admin-shell.php (55, sobretudo icones com flex-shrink) e admin/jardim/jardim_relatorio-view.php (4), ambos sem popup.
- Aperto da catraca check-inline-frontend: maximo de style de 2110 para 2051.

## Excluido

- Ficheiros que produzem saida autonoma (includes/documents-engine.php com geracao de PDF/HTML, includes/portal-logic.php com vista de portal, admin/jardim/jardim_boletim-view.php com popup de impressao): nao se migram, porque a saida deles nao carrega o sige-utilities.css.
- Ficheiros canonicos (finance-core, academic-logic, whatsapp-engine, db-handler): intocados.
- Estilos com class ja existente, com display ou com valores dinamicos ou PHP interpolado.
- As restantes areas, os onclick remanescentes (250) e os blocos script inline (81): vagas proprias.
- CSP em enforcement (nonce por pedido e flip do cabecalho): incremento final da fase.

## Riscos

- Impresso continuar a perder estilo. Mitigacao: o helper esta embebido no <style> de cada popup afectado e a guarda do smoke impede recaida; confirmado por simulacao da saida (o CSS cai logo apos <style>).
- Regressao de cascata no admin-shell (ficheiro nuclear, presente em todas as paginas). Mitigacao: so se migram atributos 100 por cento seguros, sem class existente; os utilitarios usam !important; a shell continua a enfileirar o sige-utilities.css; php -l limpo e corredor sem regressao.
- Tocar em saida autonoma sem o CSS. Mitigacao: documents-engine, portal-logic e jardim_boletim ficam de fora por desenho.
- O inline voltar a subir. Mitigacao: a catraca trava style em 2051 (sem folga).

## Criterios de aceitacao

- O helper devolve as 12 regras com !important, seguro de embeber; as 5 vistas com popup e classes sige-u- chamam-no; guarda do smoke verde.
- admin-shell e jardim_relatorio migrados sem class duplicada e com php -l limpo; admin-shell continua a enfileirar o sige-utilities.css.
- A catraca trava style em 2051 (sem folga); onclick 250 e script 81 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
