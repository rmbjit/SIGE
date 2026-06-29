# SIGE SoftGenial - Auditoria de Design (princípios e regras)

Iniciada a 13 Jun 2026. Vai além da consolidação técnica (tokens): aplica
PRINCÍPIOS de design de UI a cada ecrã, corrigindo o que não funciona
segundo a teoria da área. Documento vivo, ecrã a ecrã.

## As 3 regras-base (aplicadas como piloto ao Config Center)

### 1. Hierarquia tipográfica de poucos pesos REAIS
PROBLEMA: o sistema usava 17 pesos de fonte (650, 680, 720, 750, 760,
780, 850, 920, 950...). A maioria NÃO existe nas fontes - renderizam
arredondados a 400/500/600/700/800/900. Pior: 850-950 em todo o lado
destrói a hierarquia (quando tudo é extra-bold, nada se destaca).
REGRA: escala de 3 níveis. 400 = corpo/legendas (recuam), 600 =
ênfase/labels, 700 = títulos/valores (dominam). A hierarquia FAZ-SE
pelo contraste de peso; a legenda tem de recuar para o valor brilhar.
REFERÊNCIA: Stripe, Linear, Notion - nunca passam de 600 no corpo.

### 2. Raios de borda de escala finita
PROBLEMA: 13 raios diferentes num só ecrã (8/10/12/13/14/15/16/18/20/
22/24/26px). O raio comunica a "família" do elemento; raios arbitrários
fazem botões/cartões/campos parecerem de sistemas diferentes.
REGRA: escala canónica dos tokens - 4/8/12/16/22 + pill (999).

### 3. Sombras de escala de elevação subtil
PROBLEMA: 10 sombras distintas, várias gigantes (0 24px 70px, 0 20px
46px). Sombra comunica ALTURA; sombras enormes "incham" e "borram" as
bordas, perde-se a profundidade.
REGRA: 4 níveis de elevação subtis (xs/sm/md/lg) + realce de marca para
elementos activos. Tendência actual (Material 3, Apple HIG): sombras
curtas e leves.

## Guardião
tools/check-typography.php (12º gate): conta pesos não-canónicos e FALHA
se subirem da baseline (migração só reduz); garante que ecrãs auditados
têm zero pesos não-canónicos. Baseline inicial: 665.

## Estado da auditoria
| Ecrã | tipografia | raios | sombras | cores |
|---|---|---|---|---|
| config-center | 3 níveis | escala | escala | tokens (<style>) |
| alunos_lista | 3 níveis | escala | escala | tokens (CSS principal) |
| financeiro-extratos | 3 níveis | escala | escala | tokens (semântica fin. preservada) |
| matriz curricular | 3 níveis | escala | escala | tokens (zero hex restantes) |
| aluno-portal | 3 níveis | escala | escala | tokens (tema dinâmico preservado) |
| disciplinas | 3 níveis | escala | escala | tokens (cor de ciclo preservada) |
| boletim | 3 níveis | escala | escala | tokens (cor de nota + impressão preservadas) |
| equipe/RH | 3 níveis | escala | escala | tokens (KPIs + tema + crachá preservados) |
| minhas_turmas | 3 níveis | escala | escala | tokens (cor de estado preservada; FECHA ciclo ícones) |
| portaria | 3 níveis | escala | escala | tokens (cores JS preservadas; entrada/saída) |
| encerramento | 3 níveis | escala | escala | tokens (cor por taxa de aprovação preservada) |
| curriculum-engine | 3 níveis | escala | escala | tokens (inline + tema preservado) |
| transporte | 3 níveis | escala | escala | tokens (cor por lotação preservada) |
| jardim_saude | 3 níveis | escala | escala | tokens (estado de saúde preservado) |
| jardim_diario | 3 níveis | escala | escala | tokens (avaliação MB/B/S/NS preservada) |
| dashboard | 3 níveis | escala | escala | já em tokens (piloto v108) |
| acta | 3 níveis | escala | escala | tokens (estado de erro + impressão preservados) |
| pautas | 3 níveis | escala | escala | tokens (nota negativa + situação + impressão preservados) |
| estatisticas-demograficas | 3 níveis | escala | escala | tokens (cor por percentagem; impressão + Chart.js preservados) |
| abertura | 3 níveis | escala | escala | tokens (estado de passo; pesos inline apanhados pelos gates) |
| financeiro-pagamentos | 3 níveis | escala | escala | tokens (estado de pagamento + recibos + JS preservados; o maior ecrã) |
| permissions-ui | 3 níveis | escala | escala | tokens (cor por papel; peso inline apanhado) |
| jardim_presencas | 3 níveis | escala | escala | tokens (estado de presença preservado) |
| pauta-final | 3 níveis | escala | escala | tokens (situação + impressão preservados) |
| whatsapp_central | 3 níveis | escala | escala | tokens (estado de mensagem + tema + JS preservados) |
| notas | 3 níveis | escala | escala | tokens (nota negativa + tema + JS preservados) |
| jardim_relatorio | 3 níveis | escala | escala | tokens (2 mapas de avaliação + tema + JS preservados) |
| core-status | 3 níveis | escala | escala | tokens (estado de sistema ok/warn/grace preservado) |
| financeiro-devedores | 3 níveis | escala | escala | tokens (valor de dívida + cobrança + impressão preservados) |
| turmas | 3 níveis | escala | escala | tokens (4 blocos style; 3 templates impressão + Excel preservados) |
| auditoria_notas | 3 níveis | escala | escala | tokens (mapa de módulo + estado de erro preservados) |
| dec | 3 níveis | escala | escala | tokens (classificação NS + impressão preservados) |
| aprovar_notas | 3 níveis | escala | escala | tokens (estado de aprovação pendente/aprovada preservado) |
| jardim_boletim | 3 níveis | escala | escala | tokens (mapa MB/B/S/NS; impressão dinâmica herda estilos) |
| financeiro-lancamentos | 3 níveis | escala | escala | tokens (CSS genérico; JS preservado) |
| financeiro-config | 3 níveis | escala | escala | tokens (validação + estados de config + tema preservados) |

**AUDITORIA DE DESIGN COMPLETA - 36 ecrãs. Pesos não-canónicos: 665 -> 0 (100%). Mágicos: 9925 -> 3972 (60%). Invariantes: 301.**

Próximos ecrãs a auditar (por peso de problemas tipográficos):
alunos_lista (54), financeiro-extratos (47), matriz (40), aluno-portal
(32), disciplinas (27)...
