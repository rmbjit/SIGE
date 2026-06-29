# Carta da fase - v12.12.45 - Fase 4 incr 1 - Self-host das bibliotecas e infra de enqueue

Primeiro incremento da Fase 4 (CSP e front-end seguro, que fecha a Fase 10 do plano original). A Fase 4 e a maior divida de seguranca do navegador: ha CSP mas em unsafe-inline ou Report-Only, dezenas de ficheiros com onclick e style inline, blocos script inline, e bibliotecas servidas por CDN de terceiros. Antes de impor o CSP, e preciso construir a base. Este incremento e essa base, na sua primeira metade: retirar as bibliotecas dos CDN (self-host) e criar a infra de enqueue.

## Objectivo

Servir as bibliotecas de front-end a partir da propria instalacao (self-host) com SRI, e criar um registador central de enqueue, removendo cdnjs e unpkg das dependencias externas e estabelecendo a base para migrar o inline e impor o CSP. Camada habilitadora, sem mudanca de comportamento.

## Incluido

- Self-host das sete bibliotecas (Chart.js, xlsx, exceljs, Sortable, qrious, FileSaver, html5-qrcode) em assets/vendor/, com SRI (integrity) recalculado a partir dos ficheiros locais entregues.
- Catalogo central includes/cdn-scripts.php (sige_cdn_catalog) a construir as URLs a partir de SIGE_URL, sem literais https:// de CDN no codigo. Os treze ecrans que chamam sige_cdn_script ficam inalterados e passam a carregar local.
- A referencia directa a unpkg na portaria e o fallback do bootstrap passam a local.
- Novo includes/assets-registry.php: registador central que regista as bibliotecas locais como handles do WordPress (sige_assets_registar no admin_enqueue_scripts) e injecta SRI nas tags enfileiradas (filtro script_loader_tag), com sige_enqueue_lib para as vagas seguintes.
- Exclusao de assets/vendor/ do scan de governanca, por o codigo vendorizado ser first-party self-hosted. Dependencias externas de 11 para 9.

## Excluido

- Migracao dos blocos inline (onclick, style, script) para enqueue: e o trabalho das vagas seguintes (incrementos 2 a N).
- Self-host de fontes (fonts.googleapis.com) e de ui-avatars.com: sao style/img, ficam para um incremento proprio.
- CSP em enforcement (nonce por pedido e flip do cabecalho): e o incremento final da fase.
- Qualquer alteracao a regras de calculo, a ficheiros canonicos ou ao esquema.

## Riscos

- Uma biblioteca self-hosted comportar-se de forma diferente da do CDN. Mitigacao: sao as mesmas versoes; seis dos sete ficheiros sao byte-identicos ao que o CDN servia (SRI confirma) e o setimo (Chart.js) e o mesmo 4.4.1 (build UMD), com SRI recalculado.
- Carregar de forma insegura. Mitigacao: SRI (integrity) em todas as tags, recalculado a partir dos ficheiros entregues; o registador injecta integrity e crossorigin nas tags enfileiradas.
- Acrescentar superficie de accao. Mitigacao: o registo usa o hook admin_enqueue_scripts, ja rastreado, pelo que a superficie se mantem em 199.
- Inflar as dependencias com os URLs internos das bibliotecas. Mitigacao: o scan exclui assets/vendor/, contando apenas as chamadas externas do nosso codigo.

## Criterios de aceitacao

- As sete bibliotecas existem em assets/vendor/ com tamanho, e o SRI cobre as sete.
- O catalogo aponta local e nao ha um unico literal cdnjs ou unpkg em todo o codigo de runtime.
- O registador regista os sete handles no admin_enqueue_scripts e injecta SRI nas tags dos nossos handles, deixando as outras inalteradas.
- Dependencias externas em 9 (cdnjs e unpkg removidos). Superficie 199, vistas 60, opcoes 132. Versao sincronizada nas cinco fontes, zero travessoes.
- Novos gate e smoke da Fase 4 verdes; corredor 97; release gate verde a partir de pasta limpa; gates anteriores sem regressao. Rediagnostico adversarial Zero P0/P1.
