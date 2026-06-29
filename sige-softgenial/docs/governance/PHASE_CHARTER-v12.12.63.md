# Carta da fase - v12.12.63 - Fase 4 incr 2 - CSP Report-Only no shell admin mais grande lote de onclick admin em data-sige-act

Continuacao do incremento 2 da Fase 4 (CSP e front-end seguro): activacao do CSP Report-Only no shell admin e conversao de um grande lote de onclick das vistas admin para o despachante data-sige-act. Passo decisivo para o enforce do shell admin, feito com seguranca.

## Objectivo

Activar o CSP Report-Only com script-src nonce no shell admin, o caminho seguro para o enforce numa superficie grande e nao testavel em browser daqui, recolhendo as violacoes reais sem bloquear nada, e reduzir os bloqueadores convertendo um grande lote de onclick das vistas admin para o despachante data-sige-act. Comportamento identico; catraca onclick desce de 66 para 45.

## Incluido

- Cabecalho Content-Security-Policy-Report-Only com apenas script-src self mais nonce por pedido, via admin_init, so em page=sige-app, antes do output, protegido por headers_sent e nao-ajax. Report-Only nao bloqueia nada.
- Conversao de 21 onclick das vistas admin: 18 com contratos ou wrappers ja existentes (window.print, activarTab, filtrarTabela, resetSenha, gerirHorario, anularLote, carregarHistorico, sigeFecharPopupBackdrop) e 3 com dois wrappers novos (sigeAlternarDisplay, sigeMarcarDownloadSemTransicao).
- Aperto da catraca check-inline-frontend: maximo de onclick de 66 para 45 e exigencia do Report-Only com script-src nonce no shell.

## Excluido

- O enforce do shell admin: vem depois de tratados os onclick restantes (que o Report-Only reporta) e dos estilos inline para o eixo do style-src.
- Os onclick window.open(this.href) e this.form, e os gerados dentro de blocos script: proximos passos.
- Os 2 onclick do canonico finance-core: estao numa pagina standalone de impressao, fora do shell admin, e nao se tocam.

## Riscos

- O cabecalho bloquear algo. Mitigacao: e Report-Only, nunca bloqueia; serve so para reportar.
- O cabecalho ir para paginas erradas. Mitigacao: so em page=sige-app, antes do output, fora de ajax, protegido por headers_sent.
- Mudar o comportamento de um botao convertido. Mitigacao: usa-se o despachante ja em producao e wrappers fieis; comportamento identico.

## Criterios de aceitacao

- O shell admin (page=sige-app) envia o Report-Only com script-src nonce; o admin funciona na integra (nada bloqueado).
- Os 21 botoes convertidos chamam as mesmas funcoes; php -l limpo; sige-ui.js passa node -c.
- O despachante data-sige-act mantem o contrato (smoke-inline-frontend verde).
- A catraca trava onclick em 45 (sem folga) e exige o Report-Only no shell; style 2051 e blocos script 7 inalterados.
- Superficie 199, vistas 60, opcoes 132, deps 9. Versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 99 e release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
