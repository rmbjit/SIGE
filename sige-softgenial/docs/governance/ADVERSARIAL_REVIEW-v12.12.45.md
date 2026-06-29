# Revisao adversarial - v12.12.45 - Fase 4 incr 1 - Self-host das bibliotecas e infra de enqueue

Rediagnostico adversarial do primeiro incremento da Fase 4. O exercicio assume a postura de um revisor hostil e procura partir cada criterio antes de o declarar pronto.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Servir uma biblioteca adulterada. Cada tag leva SRI (integrity) recalculado a partir do ficheiro local entregue; o navegador rejeita qualquer alteracao. Seis dos sete ficheiros sao byte-identicos ao que o CDN servia (o SRI coincide com o anterior); o setimo, Chart.js, e o mesmo 4.4.1 com SRI recalculado.
2. Quebrar um dos treze ecrans que usavam CDN. Os ecrans chamam sige_cdn_script, cujo catalogo foi repontado para local sem mudar a assinatura; o smoke confirma que sige_cdn_script emite src local com integrity. Nenhum ecra foi editado.
3. Deixar um literal de CDN esquecido. O gate e o smoke verificam zero literais cdnjs ou unpkg em todo o codigo de runtime (admin, includes e bootstrap), incluindo a portaria e o fallback do bootstrap.
4. Acrescentar superficie de accao com o registo. O registo usa add_action no admin_enqueue_scripts, hook ja usado por admin-shell e ui-kit, pelo que deduplica; o extractor mantem 199. Vistas 60, opcoes 132.
5. Inflar as dependencias com os URLs internos das bibliotecas. O scan de governanca passa a excluir assets/vendor/, contando apenas as chamadas externas do nosso codigo; o resultado e 9 hosts (cdnjs e unpkg removidos), confirmado pela baseline regenerada.
6. Enfileirar uma biblioteca sem SRI. O filtro script_loader_tag injecta integrity e crossorigin nas tags dos nossos handles e nao duplica o atributo se ja existir; o smoke confirma, e confirma tambem que tags nao-nossas ficam inalteradas.
7. Falhar quando o catalogo nao carrega. O fallback do bootstrap mantem sige_cdn_script com URLs locais; o registador so regista handles e nao quebra se o WordPress nao expuser as funcoes (guardas function_exists).
8. Partir os incrementos anteriores. Os gates de pagamentos (reconciliacao, resolucao, duplicados), cofre e Fase 1 continuam verdes; o release gate passa a partir de pasta limpa.

## P0

Nenhum. Camada habilitadora; nao toca em regras de calculo nem em ficheiros canonicos. As bibliotecas sao as mesmas versoes, agora locais e com SRI verificavel.

## P1

Nenhum. Self-host reduz a superficie de ataque (sem CDN de terceiros) e mantem a integridade via SRI recalculado a partir dos ficheiros entregues.

## P2 e P3

Nenhum novo. Os ecrans funcionam sem alteracao; o registador apenas regista handles, deixando a migracao do inline para as vagas seguintes, sem mudanca de comportamento agora.

## Decisao

Aprovado. Zero P0 e zero P1. O self-host e a infra de enqueue estao completos e provados por gate e smoke novos (corredor 97): sete bibliotecas locais com SRI, catalogo repontado para local, zero literais de CDN, registador a registar os sete handles no admin_enqueue_scripts e a injectar SRI, e dependencias externas reduzidas de 11 para 9. Aditivo, sem tocar em regras de calculo nem em ficheiros canonicos, sem nova superficie, sem nova vista, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Os gates anteriores continuam verdes. Release gate verde a partir de pasta limpa. Pronto para entrega. Segue-se o incremento 2: migracao dos blocos inline para enqueue, por vagas.
