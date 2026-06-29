# PAINEL-UX - Programa de UX/UI/Intuitividade (scorecard medido)

Última actualização: v12.11.9.148 (diagnóstico responsivo + correções). Instrumento: auditor
v2.5d.

## Como se mede
Score por view = inconsistência visível ao utilizador. Programa =
30% fundação + 70% proporcional ao score corrigido sobre a linha de
base (547,4). Créditos medidos com baseline e actual sob a mesma régua
do respectivo sprint; recalibrações fora da conta.

## Estado do programa

| Bloco | Estado | % |
|---|---|---:|
| Fundação: UI Kit sgk- + UI-GUIA + auditor v2.5d + 7 gates | ENTREGUE | 100% |
| Módulos corrigidos/verificados | 16 de 53 (score corrigido 220,2 de 547,4) | 40,2% |
| **PROGRAMA UX** | sistema agora em **327,2** | **~58%** |

Diálogos nativos restantes: 11 alert + 5 confirm + 0 prompt (cauda
longa de ecrãs pequenos).

## Módulos corrigidos/verificados (16)

| Módulo | Baseline | Actual | Sprint |
|---|---:|---:|---|
| whatsapp_central | 77,5 | 7,3 | UX-1 |
| financeiro-pagamentos | 41,2 | 28,9 | UX-2 |
| financeiro-config | 26,2 | 14,2 | UX-3 |
| mpesa-view | 34,4 | 9,8 | UX-3 |
| jardim_relatorio | 27,9 | 13,9 | UX-4 |
| estatisticas-demograficas | 26,9 | 6,9 | UX-4 |
| turmas-view | 20,8 | 9,8 | UX-4 |
| acta-view | 21,6 | 15,6 | UX-5 |
| financeiro-planos | 13,6 | 9,5 | UX-5 |
| financeiro-devedores | 17,8 | 5,8 | UX-5 |
| aluno-portal | 1,7 | 1,7 | UX-6 (já conforme) |
| encerramento | 17,4 | 3,4 | UX-6 |
| financeiro-lancamentos | 8,8 | 0,8 | UX-6 |
| jardim_diario | 11,8 | 5,8 | UX-7 |
| alocacao | 14,1 | 2,1 | UX-7 (confirm com nome) |
| whatsapp_circulares | 14,5 | 2,5 | UX-7 (checkbox consciente cadeada) |

## Fila (leitura estratégica)

A natureza da dívida mudou. O que resta divide-se em quatro famílias:

| Família | Conteúdo | Próximo passo |
|---|---|---|
| Diálogos na cauda | 11 alert + 5 confirm em views pequenas fora do top | SPRINT-VASSOURA: varrer TODOS de uma vez e instalar invariantes GLOBAIS (zero alert/confirm em todas as views, como o do prompt) |
| AO90 a triar | dashboard (4), alunos_lista (2) | triagem caso a caso no mesmo sprint |
| Inline congelado | pagamentos 289, config 142, jardim_rel 139, diag 73... | fase de consolidação CSS com screenshots (decisão antiga mantida) |
| Residuais documentados | acta (vazios aninhados) | passagem manual dedicada |


## Nota v12.11.9.102 (feature de cliente, fora do programa UX)
Calendário de Devedores por Mês no topo do ecrã de devedores (pedido
real): 12 tiles clicáveis, saldo pela fórmula canónica, números sempre
coerentes com a lista. Score UX do ecrã mantido (5,8: zero inline novo,
CSS em ficheiro próprio coberto pelo gate). Qualidade: +19 testes do
motor puro (8º conjunto) e nasceu o corredor único tools/run-gates.php
após um exit code mascarado por pipe ter deixado um gate vermelho
passar; regressão 116 -> 124 invariantes.


## Marco v12.11.9.103 (UX-8): DIÁLOGOS NATIVOS EXTINTOS
Os 11 alert + 5 confirm restantes foram convertidos em 11 ficheiros.
O sistema tem agora 0 alert + 0 confirm + 0 prompt nativos, com TRÊS
invariantes globais a impedir o regresso de qualquer um. Confirmação
declarativa passou a cobrir links <a> (preservando href/nonce).
Sistema: 327,2 -> 263,2. Regressão: 116 -> 127 invariantes.

A categoria de dívida "diálogo nativo" está FECHADA. O que resta do
programa é inline congelado (fase de consolidação CSS), bolsas de AO90
a triar (dashboard 8, alunos_lista, ...) e residuais documentados
(acta). Próximo passo sugerido: triagem AO90 + arranque da consolidação
CSS com screenshots.


## Marco v12.11.9.104: categorias comportamentais TODAS a zero
Triagem AO90 concluída (6 rótulos visíveis canonizados cadastro->ficha;
restantes eram identificadores/SQL, excluídos pela régua v2.6) e os 11
botões sem classe restantes entraram no kit. Estado do sistema:

| Categoria comportamental | Estado |
|---|---|
| alert() nativo | 0 (cadeado) |
| confirm() nativo | 0 (cadeado) |
| prompt() nativo | 0 (cadeado) |
| AO90 visível (usuário/cadastro) | 0 (cadeado) |
| Botão sem classe | 0 (cadeado) |

Score: 263,2 -> 221,2. Regressão: 127 -> 129 invariantes. O score
residual (221,2) é AGORA exclusivamente inline de layout (1.792
ocorrências), a matéria da FASE SEGUINTE: consolidação CSS com
screenshots antes/depois. Depois dela, o VPS (cron de backups, etc.).


## Fase CSS arrancou: DASHBOARD (estreia, v105) -> score 0,0
Princípio: pixel-perfect (o ecrã fica idêntico; só a origem do estilo
muda). Descoberta: o dashboard já estava bem arquitectado - 23 dos 28
style= eram injecção de custom properties (padrão correcto) e o donut
usa conic-gradient dinâmico (legítimo). Só 5 eram estilo real, movidos
para classes utilitárias (.sg-val-pos/neg/hi + .sg-class-bar) com a
paleta provada idêntica antes/depois.

Auditor v2.7: custom properties e estilo dinâmico PHP deixam de contar
como inline-dívida (recalibração separada do progresso; sistema 263->213).

Dashboard: 14,8 (início do programa) -> 0,0. Regressão: 129 -> 133.
Próximos ecrãs da fila CSS por inline real (sob v2.7), a confirmar no
arranque do próximo: financeiro-pagamentos, whatsapp_diag, acta,
financeiro-config, jardim_relatorio.


## Fase CSS: EQUIPE/RH (2º ecrã, v106) -> 0,2
13 dos 15 estilos reais movidos para classes (larguras da tabela de
staff como col-* semânticas, margens, alinhamentos, separador). Larguras
e margens provadas idênticas antes/depois. 2 casos dinâmicos legítimos
preservados de propósito: o display:none gerido por JS e o spin dentro
de innerHTML em runtime. Equipe: 1,9 -> 0,2. Regressão: 133 -> 137.


## Fase CSS: DISCIPLINAS (3º ecrã, v107) -> 0,1
15 dos 17 estilos reais movidos para classes. Destaque: 2 estados
vazios gémeos (HTML em template strings JS, idênticos) passaram a
partilhar as mesmas classes - 8 declarações duplicadas viraram 4
classes reutilizadas. box-esg2 (slideDown/slideUp jQuery) preservado
de propósito. Disciplinas: 1,7 -> 0,1. Regressão: 137 -> 142.


## MUDANÇA DE FASE: Design System unificado (v108)
Diagnóstico expôs 936 cores hex, 29 raios, 500 sombras dispersos sem
fonte única. Resposta definitiva (não paliativa): assets/sige-tokens.css
como fonte única da verdade (7 famílias x escala 50-900, raios, sombras,
animações de entrada canónicas), carregada antes de toda a cascata, com
gate guardião (tools/check-design-tokens.php) que proíbe crescimento de
valores mágicos. Azul entrou como família info (significado estável);
roxos/verdes dispersos unificados. Dashboard migrado como piloto:
231 hex mágicos -> 0, 111 referências a var(--color-*). Baseline do
sistema: 12797 -> 12615. Regressão: 149 -> 152 invariantes. 9 gates.

Próximo: migrar os CSS legados (style.css, style-consolidado.css) e as
views mais pesadas (alunos_lista, financeiro-pagamentos), módulo a
módulo, baseline a descer monotonicamente.


## Fase CSS: LEGADOS RECONCILIADOS (v109)
Descoberta crítica: style-consolidado.css (2066 linhas) era CÓDIGO MORTO
(zero referências em produção) -> apagado com a ferramenta obsoleta que
o gerava (-1467 mágicos por deleção de lixo). O style.css VIVO tinha um
:root com sistema de tokens PARALELO que competia com o canónico (e com
outro roxo de marca!) -> reconciliado via PONTE DE COMPATIBILIDADE: cada
--sg-* referencia agora o --color-* canónico (196 tokens-ponte, zero
órfãs). 1150 cores migradas, style.css 769 hex -> 0. Dourado unificado
em warning. Novo smoke-design-system.php (15 verif, 10º gate). Baseline:
12615 -> 9925. Regressão: 151 -> 155.


## UI: ÍCONES NORMALIZADOS OPTICAMENTE (v110)
Problema reportado com imagens: ícones dos cartões com tamanhos
diferentes. Causa: cada SVG preenchia uma fracção diferente do viewBox
(dispersão de diagonal 8.2px). Solução: tools/normalizar-icones.py mede
a bbox real (render) e reescala todos para a mesma DIAGONAL aparente
(~21/24), com stroke compensado pela escala -> dispersão 1.2px.
sige_ui_icon canoniza o invólucro <svg> ao servir; regra base
.sg-svg-icon no design system. Novo smoke-icones.php (11 verif, 11º
gate). Regressão: 155 -> 160. Prova visual antes/depois gerada.


## AUDITORIA DE DESIGN iniciada (Config Center piloto, v111)
Nova postura: corrigir o que não FUNCIONA segundo princípios de UI, não
só consolidar. Diagnóstico do Config Center: 17 pesos de fonte (850/950
destroem hierarquia), 13 raios, 10 sombras pesadas. Correcção: hierarquia
de 3 níveis (400 corpo/600 ênfase/700 título), raios e sombras da escala
canónica, 183 cores migradas. Gate de tipografia (12º, baseline 665).
Prova visual antes/depois gerada. Regressão: 160 -> 164.

Próximos a auditar: alunos_lista, financeiro-extratos, matriz...


## AUDITORIA DE DESIGN: alunos_lista (ecrã mais usado, v112)
9228 linhas, a maior dívida de design: 12 pesos, 20 raios, 110 sombras,
695 cores. Tratado o CSS principal (zonas 33-242, 687-5668); templates
de impressão e JS preservados. Hierarquia de 3 níveis (matrícula/meta
recuam para 400, nomes/valores 700), raios da escala (6), sombras da
escala (9), 695 cores para tokens. Zero pesos não-canónicos no ficheiro.
Prova visual (cartão de aluno) gerada. Baseline mágicos: 9742 -> 9047.
Tipografia: 665 -> 611. Regressão: 164 -> 168.

Próximos: financeiro-extratos, matriz, aluno-portal...


## AUDITORIA DE DESIGN: financeiro-extratos (ecrã financeiro, v113)
3760 linhas. 9 pesos (950 o mais usado!), 15 raios, 23 sombras, 107
cores. CUIDADO acrescido: semântica financeira (verde reconciliação OK
#128754, vermelho diferença #b42318) preservada migrando para tokens
correctos; template de impressão do Termo de Fecho preservado com cores
absolutas. CSS principal: 3 níveis de peso, raios e sombras da escala,
268 cores para tokens. Baseline mágicos: 9047 -> 8779. Tipografia: 611
-> 564. Regressão: 168 -> 173.

NOTA: 1ª afinação de legendas foi agressiva demais (62x 400), revertida e
refeita com mapa conservador. Verificar antes de aplicar regra cega.

Próximos: matriz, aluno-portal, disciplinas...


## AUDITORIA DE DESIGN: matriz curricular (v114)
1123 linhas. Concentração tipográfica extrema (950 usado 30x, 900 14x).
Dois blocos CSS tratados, JS preservado. 3 níveis de peso, raios (4),
sombras (4), 202 cores para tokens, zero hex restantes. Baseline mágicos:
8779 -> 8577. Tipografia: 564 -> 524. Regressão: 173 -> 176.
Próximos: aluno-portal, disciplinas, boletim...


## AUDITORIA DE DESIGN: aluno-portal (portal externo, v115)
1366 linhas, cara externa do produto. 10 pesos (950 dominante 21x), 12
raios, 13 sombras. CSS + estilos inline migrados; tema dinâmico da escola
(--sg-theme-primary) preservado com fallback. 3 níveis de peso, raios (4),
sombras (4), 139 cores. Baseline mágicos: 8577 -> 8436. Tipografia: 524 ->
492. Regressão: 176 -> 180.
Próximos: disciplinas, boletim, equipe...


## AUDITORIA DE DESIGN: disciplinas (v116)
2157 linhas. 10 pesos (950 lidera), 14 raios, 33 sombras (2ª maior
dispersão). Dois blocos CSS tratados. 3 níveis de peso (28/28), raios (6),
sombras (6), 233 cores incluindo ícones de ciclo de ensino (distinção por
cor preservada via tokens semânticos). Zero hex restantes. Baseline
mágicos: 8436 -> 8203. Tipografia: 492 -> 465. Regressão: 180 -> 183.
Próximos: boletim, equipe, portaria...


## AUDITORIA DE DESIGN: boletim (documento, v117)
1341 linhas, vai para casa. 8 pesos (900/950 dominam), 11 raios, 17
sombras. CUIDADO: função PHP de cor por nota preservada (cor calculada
como dado); classes de classificação migradas para tokens semânticos;
template de impressão do aproveitamento preservado. 3 níveis de peso (2
legendas afinadas a 400 uma a uma), raios (5), sombras (6), 147 cores. 49
hex restantes todos em zonas preservadas. Baseline mágicos: 8203 -> 8056.
Tipografia: 465 -> 440. Regressão: 183 -> 188.
Próximos: equipe, portaria, minhas_turmas...


## AUDITORIA DE DESIGN: equipe/RH (v118)
3737 linhas. 9 pesos, 17 raios (maior dispersão vista), 55 sombras. Bloco
CSS principal tratado; templates de crachá e JS preservados. 3 níveis de
peso (30/33), raios (5), sombras (7), 230 cores. KPIs migrados, tema
dinâmico preservado. Baseline mágicos: 8056 -> 7824. Tipografia: 440 ->
417. Regressão: 188 -> 191.
Próximos: portaria, minhas_turmas, encerramento...


## AUDITORIA DE DESIGN: minhas_turmas (fecha o ciclo, v119)
O ecrã que o utilizador mostrou no início (ícones desproporcionais).
689 linhas. 6 pesos (850 dominante 15x), 7 raios, 17 sombras. Agora tem
ícones normalizados (v110) E hierarquia. 3 níveis de peso, raios (4),
sombras (6), 123 cores incluindo cores de estado de progresso (via tokens
semânticos). Baseline mágicos: 7824 -> 7700. Tipografia: 417 -> 395.
Regressão: 191 -> 194.

MARCO: 9 ecrãs auditados. Mágicos 9925 -> 7700 na auditoria; pesos
não-canónicos 665 -> 395.
Próximos: portaria, encerramento, curriculum-engine...


## AUDITORIA DE DESIGN: portaria (v120)
1332 linhas. 8 pesos (850/900 dominam), 11 raios, 22 sombras. Bloco CSS
tratado; cores JS de lógica preservadas. 3 níveis de peso (2 legendas a
400), raios (5), sombras (6), 130 cores. Semântica entrada/saída
(verde/vermelho) preservada. Invariante mede só o <style> (JS tem cores
próprias). Baseline mágicos: 7700 -> 7568. Tipografia: 395 -> 373.
Regressão: 194 -> 197.
Próximos: encerramento, curriculum-engine, transporte...


## AUDITORIA DE DESIGN: encerramento (v121)
1340 linhas. 7 pesos (850 dominante), 9 raios, 13 sombras. Bloco <style>
(inclui @media print) tratado; redirects JS preservados. 3 níveis de
peso, raios (4), sombras (6), 139 cores incluindo lógica de cor por taxa
de aprovação (verde/amarelo/vermelho via tokens). Zero hex restantes.
Baseline mágicos: 7568 -> 7425. Tipografia: 373 -> 351. Regressão: 197 ->
201 (passou 200).

MARCO: 11 ecrãs auditados. Mágicos 9925 -> 7425; pesos não-canónicos 665
-> 351.
Próximos: curriculum-engine, transporte...


## AUDITORIA DE DESIGN: curriculum-engine (v122)
449 linhas. 8 pesos (850 dominante 15 de 29, o caso mais extremo), 10
raios, 17 sombras. Bloco <style> + inline tratados; tema dinâmico
preservado. 3 níveis de peso (1 legenda a 400), raios (4), sombras (7),
118 cores. Zero hex restantes. Baseline mágicos: 7425 -> 7290.
Tipografia: 351 -> 331. Regressão: 201 -> 205.

MARCO: 12 ecrãs auditados. Mágicos 9925 -> 7290; pesos não-canónicos 665
-> 331.
Próximos: transporte, e a cauda mais leve...


## AUDITORIA DE DESIGN: transporte (v123)
546 linhas. 7 pesos (850 dominante 12 de 25), 12 raios, 16 sombras. Bloco
<style> + inline tratados. 3 níveis de peso, raios (5), sombras (6), 107
cores incluindo lógica de cor por lotação (disponível/no limite/em uso
via tokens). Zero hex restantes. Baseline mágicos: 7290 -> 7175.
Tipografia: 331 -> 312. Regressão: 205 -> 209.

MARCO: 13 ecrãs auditados. Mágicos 9925 -> 7175; pesos não-canónicos 665
-> 312.
Próximos: a cauda mais leve...


## AUDITORIA DE DESIGN: jardim_saude (v124)
818 linhas, informação médica de crianças. 8 pesos (850/700/900), 11
raios, 18 sombras. Bloco <style> + zona HTML inline tratados; JS
preservado. 3 níveis de peso, raios (5), sombras (5), 158 cores incluindo
codificação de estado de saúde (saudável verde/alerta vermelho via tokens
semânticos). Zero hex restantes. Baseline mágicos: 7175 -> 7017.
Tipografia: 312 -> 293. Regressão: 209 -> 212.

MARCO: 14 ecrãs auditados. Mágicos 9925 -> 7017; pesos não-canónicos 665
-> 293.
Próximos: jardim_diario, dashboard, pautas...


## AUDITORIA DE DESIGN: jardim_diario (v125)
1098 linhas, módulo jardim. 8 pesos (700/850/900), 12 raios, 23 sombras.
Bloco <style> + zona HTML inline tratados; JS preservado. 3 níveis de
peso, raios (4), sombras (6), 179 cores incluindo mapa de cor por
avaliação MB/B/S/NS (verde/azul/laranja/vermelho via tokens semânticos).
Baseline mágicos: 7017 -> 6838 (abaixo de 7000!). Tipografia: 293 -> 274.
Regressão: 212 -> 216.

MARCO: 15 ecrãs auditados. Mágicos 9925 -> 6838; pesos não-canónicos 665
-> 274.
Próximos: dashboard, pautas, acta...


## AUDITORIA DE DESIGN: dashboard (v126, 2ª camada apos piloto v108)
888 linhas. CASO ESPECIAL: foi o piloto de COR da v108. Zero hex (v108
intacta). Esta versão acrescenta tipografia/raios/sombras. 9 pesos ->
500/600/700, raios (5), sombras (6). DECISÃO: .sg-day-meta mantido a 700
(é pill de destaque, não legenda - verificação caso-a-caso). Os 6
invariantes de cor da v108 + 3 novos de tipografia. Baseline mágicos:
mantém 6838. Tipografia: 274 -> 256.

MARCO: 16 ecrãs auditados. Mágicos 9925 -> 6838; pesos não-canónicos 665
-> 256.
Próximos: pautas, acta, estatisticas-demograficas...


## AUDITORIA DE DESIGN: acta (v127)
1448 linhas, documento oficial. 8 pesos (850/900), 9 raios, 11 sombras.
Bloco <style> (inclui @media print) + zona HTML tratados; scripts
preservados. 3 níveis de peso, raios (4), sombras (5), 151 cores incluindo
estados de erro/alerta (via tokens). @media print preservada. Zero hex
restantes. Baseline mágicos: 6838 -> 6687. Tipografia: 256 -> 238.
Regressão: 219 -> 223.

MARCO: 17 ecrãs auditados. Mágicos 9925 -> 6687; pesos não-canónicos 665
-> 238.
Próximos: pautas, estatisticas-demograficas, abertura...


## AUDITORIA DE DESIGN: pautas (v128)
2374 linhas, documento oficial de notas. 10 pesos (850/900), 8 raios, 14
sombras. Dois blocos <style> (ambos com @media print) tratados; JS
preservado. 3 níveis de peso, raios (5), sombras (6), 148 cores incluindo
classes de nota negativa e mapa de situação PROGRIDE/TRANSITA/REPROVA/
PENDENTE (via tokens). Duas @media print preservadas. Zero hex restantes.
Baseline mágicos: 6687 -> 6539. Tipografia: 238 -> 220. Regressão: 223 ->
228.

MARCO: 18 ecrãs auditados. Mágicos 9925 -> 6539; pesos não-canónicos 665
-> 220.
Próximos: estatisticas-demograficas, abertura, financeiro-pagamentos...


## AUDITORIA DE DESIGN: estatisticas-demograficas (v129)
960 linhas. 8 pesos (850/700), 8 raios, 13 sombras. Bloco <style> + zona
HTML tratados; template de impressão (<!DOCTYPE> com <style> próprio) e
gráficos Chart.js preservados. 3 níveis de peso, raios (4), sombras (6),
113 cores incluindo lógica de cor por percentagem (verde/laranja/vermelho
via tokens). Baseline mágicos: 6539 -> 6426. Tipografia: 220 -> 203.
Regressão: 228 -> 232.

MARCO: 19 ecrãs auditados. Mágicos 9925 -> 6426; pesos não-canónicos 665
-> 203.
Próximos: abertura, financeiro-pagamentos, permissions-ui...


## AUDITORIA DE DESIGN: abertura (v130)
1236 linhas, par do encerramento. 7 pesos no <style> (850/900) + 2 pesos
650 INLINE fora do bloco. 10 raios, 16 sombras. Bloco <style> (inclui
@media print) tratado; scripts preservados. 3 níveis de peso, raios (4),
sombras (7), 138 cores. @media print preservada. Zero hex restantes.
LIÇÃO: 2 pesos inline (650) fora do <style> falharam o gate e foram
apanhados antes do ZIP - corrigidos para 600. Os guardiões funcionaram.
Baseline mágicos: 6426 -> 6288. Tipografia: 203 -> 187. Regressão: 232 ->
236.

MARCO: 20 ecrãs auditados. Mágicos 9925 -> 6288; pesos não-canónicos 665
-> 187.
Próximos: financeiro-pagamentos, permissions-ui, jardim_presencas...


## AUDITORIA DE DESIGN: financeiro-pagamentos (v131)
3744 linhas - o MAIOR ecrã. Recordes: 8 pesos (700 33x, 800, 900, 950),
15 raios, 36 sombras, 217 cores. Bloco <style> tratado; JS preservado. 3
níveis de peso (.sige-badge mantido 700, pill), raios (6), sombras (6),
361 cores em 3 zonas HTML. Classes de estado de pagamento (pago/vencido/
pendente) e tema preservados. Sem template de recibo embebido. Lição da
abertura aplicada (pesos no ficheiro inteiro). Zero hex de estilo. Baseline
mágicos: 6288 -> 5927 (MAIOR salto, abaixo de 6000). Tipografia: 187 ->
171. Regressão: 236 -> 240.

MARCO: 21 ecrãs auditados. Mágicos 9925 -> 5927; pesos não-canónicos 665
-> 171.
Próximos: permissions-ui, jardim_presencas, pauta-final...


## AUDITORIA DE DESIGN: permissions-ui (v132)
1294 linhas. 8 pesos (850/900) + 1 peso 800 inline. 9 raios, 16 sombras.
Bloco <style> tratado; JS preservado. 3 níveis de peso (1 legenda a 400,
1 peso inline 800->700), raios (4), sombras (6), 143 cores. Zero hex
restantes. LIÇÃO DA ABERTURA aplicada: peso inline apanhado proactivamente.
Baseline mágicos: 5927 -> 5784. Tipografia: 171 -> 156. Regressão: 240 ->
243.

MARCO: 22 ecrãs auditados. Mágicos 9925 -> 5784; pesos não-canónicos 665
-> 156.
Próximos: jardim_presencas, pauta-final, whatsapp_central...


## AUDITORIA DE DESIGN: jardim_presencas (v133)
578 linhas, módulo jardim. 6 pesos (850 dominante), 8 raios, 14 sombras.
Bloco <style> tratado. 3 níveis de peso, raios (4), sombras (5), 77 cores
incluindo mapa de estado de presença (presente/atraso/falta/justificada
via tokens semânticos). Zero hex restantes. Baseline mágicos: 5784 ->
5707. Tipografia: 156 -> 141. Regressão: 243 -> 247.

MARCO: 23 ecrãs auditados. Mágicos 9925 -> 5707; pesos não-canónicos 665
-> 141.
Próximos: pauta-final, whatsapp_central, notas...


## AUDITORIA DE DESIGN: pauta-final (v134)
578 linhas, documento oficial irmão do pautas/acta. 8 pesos (850/800), 4
raios, 10 sombras. Bloco <style> (inclui @media print) + corpo HTML
tratados; template de impressão (<!DOCTYPE>) e JS preservados. 3 níveis de
peso (.pf-meta-pill mantido 700, pill), raios (3), sombras (5), 104 cores
incluindo classes de situação (progride/transita/reprova) e cabeçalhos de
tabela. Zero hex de estilo. Baseline mágicos: 5707 -> 5603. Tipografia:
141 -> 126. Regressão: 247 -> 252.

MARCO: 24 ecrãs auditados. Mágicos 9925 -> 5603; pesos não-canónicos 665
-> 126.
Próximos: whatsapp_central, notas, jardim_relatorio...


## AUDITORIA DE DESIGN: whatsapp_central (v135)
1507 linhas. 8 pesos (850/900/800/700), 10 raios, 19 sombras. Bloco
<style> + corpo HTML tratados; JS preservado. 3 níveis de peso (1 legenda
a 400), raios (4), sombras (7), 203 cores incluindo mapa de estado de
mensagem (enviada/pendente/falhou/cancelada via tokens semânticos). Tema e
JS preservados. Zero hex de estilo. Baseline mágicos: 5603 -> 5400.
Tipografia: 126 -> 112. Regressão: 252 -> 256.

MARCO: 25 ecrãs auditados. Mágicos 9925 -> 5400; pesos não-canónicos 665
-> 112.
Próximos: notas, jardim_relatorio, core-status...


## AUDITORIA DE DESIGN: notas (v136)
2545 linhas, ecrã nuclear. 8 pesos (850/700/800), 12 raios, 16 sombras.
Bloco <style> + corpo HTML tratados; JS preservado. 3 níveis de peso,
raios (6), sombras (7), 198 cores incluindo cabeçalhos de tabela, campos
de nota e nota negativa vermelha. Tema preservado (fallback em token).
Zero hex restantes. Baseline mágicos: 5400 -> 5202. Tipografia: 112 -> 98
(abaixo de 100!). Regressão: 256 -> 260.

MARCO: 26 ecrãs auditados. Mágicos 9925 -> 5202; pesos não-canónicos 665
-> 98.
Próximos: jardim_relatorio, core-status, financeiro-devedores...


## AUDITORIA DE DESIGN: jardim_relatorio (v137)
1243 linhas, módulo jardim. 9 pesos (700/850), 14 raios, 15 sombras. Bloco
<style> + corpo HTML tratados; JS preservado. 3 níveis de peso, raios (4),
sombras (6), 201 cores incluindo DOIS mapas de avaliação (estado emocional
+ desempenho) via tokens semânticos. Tema e JS preservados. Zero hex de
estilo. Baseline mágicos: 5202 -> 5001. Tipografia: 98 -> 85. Regressão:
260 -> 264.

MARCO: 27 ecrãs auditados. Mágicos 9925 -> 5001 (quase metade); pesos
não-canónicos 665 -> 85.
Próximos: core-status, financeiro-devedores, turmas...


## AUDITORIA DE DESIGN: core-status (v138)
660 linhas, diagnóstico do sistema. 7 pesos (850 dominante), 5 raios, 12
sombras. Bloco <style> (quase todo o ficheiro) tratado. 3 níveis de peso,
raios (4), sombras (6), 81 cores incluindo classes de estado de sistema
(ok/warn/grace via tokens semânticos). Zero hex restantes. Baseline
mágicos: 5001 -> 4920 (abaixo de 5000!). Tipografia: 85 -> 73. Regressão:
264 -> 268.

MARCO: 28 ecrãs auditados. Mágicos 9925 -> 4920 (mais de metade); pesos
não-canónicos 665 -> 73.
Próximos: financeiro-devedores, turmas, auditoria_notas...


## AUDITORIA DE DESIGN: financeiro-devedores (v139)
1872 linhas, dinheiro em falta. 8 pesos (900/850/800), 12 raios, 25
sombras. Bloco <style> + corpo HTML tratados; template de impressão (Lista
de Cobrança, <!doctype>) e JS preservados. 3 níveis de peso, raios (4),
sombras (7), 212 cores incluindo valor de dívida vermelho (.sige-debt-
amount) e tipo de cobrança. Tema preservado. Zero hex de estilo. Baseline
mágicos: 4920 -> 4708. Tipografia: 73 -> 61. Regressão: 268 -> 273.

MARCO: 29 ecrãs auditados. Mágicos 9925 -> 4708; pesos não-canónicos 665
-> 61.
Próximos: turmas, auditoria_notas, dec...


## AUDITORIA DE DESIGN: turmas (v140)
2376 linhas, o mais complexo da cauda. 7 pesos (700/800/900/850), 13
raios, 22 sombras. ESTRUTURA: 4 blocos <style> + 3 templates de impressão
(Mapa de Turmas, Horário, Lista de Alunos) + 1 exportação Excel. Blocos
CSS reais (topo + principal) tratados; todos os templates preservados. 3
níveis de peso, raios (4), sombras (6), 181 cores. 59 hex preservados nos
templates. Zero hex de estilo. Baseline mágicos: 4708 -> 4527. Tipografia:
61 -> 49. Regressão: 273 -> 278.

MARCO: 30 ecrãs auditados. Mágicos 9925 -> 4527; pesos não-canónicos 665
-> 49.
Próximos: auditoria_notas, dec, aprovar_notas...


## AUDITORIA DE DESIGN: auditoria_notas (v141)
969 linhas, rastreio de notas. 9 pesos (850 dominante), 8 raios, 12
sombras. Bloco <style> + zonas HTML/PHP tratados. 3 níveis de peso, raios
(4), sombras (5), 101 cores incluindo mapa de cor por módulo (Financeiro/
Alunos/Notas/Turmas/etc) e lógica de estado de erro vermelho. Tema
preservado. Zero hex restantes. Baseline mágicos: 4527 -> 4426.
Tipografia: 49 -> 37. Regressão: 278 -> 282.

MARCO: 31 ecrãs auditados. Mágicos 9925 -> 4426; pesos não-canónicos 665
-> 37.
Próximos: dec, aprovar_notas e a cauda final.


## AUDITORIA DE DESIGN: dec (v142)
604 linhas, documento académico que se imprime. 7 pesos (850/800), 4
raios, 10 sombras. Bloco <style> (inclui @media print) + corpo HTML
tratados; template de impressão (<!DOCTYPE>) e JS preservados. 3 níveis de
peso (.dec-meta-pill mantido 700, pill), raios (3), sombras (5), 85 cores
incluindo cabeçalhos de tabela e classificação NS vermelha. Zero hex de
estilo. Baseline mágicos: 4426 -> 4341. Tipografia: 37 -> 26 (abaixo de
30!). Regressão: 282 -> 286.

MARCO: 32 ecrãs auditados. Mágicos 9925 -> 4341; pesos não-canónicos 665
-> 26.
Próximos: aprovar_notas e a cauda final.


## AUDITORIA DE DESIGN: aprovar_notas (v143)
617 linhas. 7 pesos (850 dominante), 8 raios, 10 sombras. Bloco <style>
tratado; JS preservado. 3 níveis de peso, raios (4), sombras (4), 74 cores
incluindo estado de aprovação (KPI pendente laranja, botão rejeitar
vermelho via tokens). Zero hex restantes. Baseline mágicos: 4341 -> 4267.
Tipografia: 26 -> 15 (abaixo de 20!). Regressão: 286 -> 290.

MARCO: 33 ecrãs auditados. Mágicos 9925 -> 4267; pesos não-canónicos 665
-> 15.
Próximo: a cauda final com <11 pesos.


## AUDITORIA DE DESIGN: jardim_boletim (v144)
869 linhas, boletim que vai para os pais. 8 pesos (850/800), 11 raios, 16
sombras. Bloco <style> (inclui @media print) + corpo HTML tratados; JS
preservado. 3 níveis de peso, raios (4), sombras (6), 94 cores incluindo
mapa de avaliação MB/B/S/NS via tokens semânticos. Impressão DINÂMICA
herda os estilos migrados. Zero hex restantes. Baseline mágicos: 4267 ->
4173. Tipografia: 15 -> 5 (quase a zerar!). Regressão: 290 -> 294.

MARCO: 34 ecrãs auditados. Mágicos 9925 -> 4173; pesos não-canónicos 665
-> 5.
Próximos: financeiro-lancamentos, financeiro-config (os últimos).


## AUDITORIA DE DESIGN: financeiro-lancamentos (v145)
1510 linhas, o penúltimo ecrã. 6 pesos (850), 9 raios, 8 sombras. <script>
antes do <style> (CSS no fim do ficheiro). Bloco <style> tratado; JS
preservado. 3 níveis de peso (só 600/700), raios (4), sombras (6), 42
cores. CSS com inputs/botões genéricos, sem semântica de crédito/débito
específica. Zero hex em todo o ficheiro. Baseline mágicos: 4173 -> 4131.
Tipografia: 5 -> 1 (falta 1 ecrã!). Regressão: 294 -> 297.

MARCO: 35 ecrãs auditados. Mágicos 9925 -> 4131; pesos não-canónicos 665
-> 1.
Próximo: financeiro-config (o último). Depois: auditoria COMPLETA.


## AUDITORIA DE DESIGN: financeiro-config (v146) - O ÚLTIMO ECRÃ
1835 linhas. 5 pesos (quase limpo: só 800/850 por afinar), 9 raios, 7
sombras. Bloco <style> + corpo HTML tratados; JS preservado. 3 níveis de
peso, raios (5), sombras (4), 159 cores incluindo validação vermelha,
sucesso verde e estados de config. Tema preservado (fallback em token).
Zero hex restantes. Baseline mágicos: 4131 -> 3972 (abaixo de 4000!).
Tipografia: 1 -> 0 (ZERO!). Regressão: 297 -> 301.

═══ AUDITORIA DE DESIGN COMPLETA - 36 ECRÃS ═══
Pesos não-canónicos: 665 -> 0 (100%). Mágicos: 9925 -> 3972 (60%).
Invariantes: 301. Todos os ecrãs com hierarquia de 3 níveis, raios/sombras
da escala, e cores em tokens com toda a semântica preservada.
Próximo (fora da auditoria): VPS (cron backups urgente, Git+CI, deploys).


## CORRECÇÃO CRÍTICA: bug de entidades HTML (v147)
O script de migração de cor (regex #[0-9a-fA-F]{6}) apanhava por engano os
6 dígitos de emojis em entidades HTML decimais (ex: família 👨‍👩‍👧 =
&#128104;&#8205;...), deixando &var(--color-...); como texto visível.
Afectou financeiro-pagamentos (20x) e financeiro-config (7x). CORRIGIDO:
ficheiros restaurados do original limpo (v109=v130) e re-auditados com
script BLINDADO (mascara entidades antes de migrar cor). Emojis e acentos
intactos. NOVO invariante global anti-bug. 12 gates, 302 invariantes,
tipografia 0.

DESCOBERTA: a varredura revelou que includes/ tem ficheiros NÃO auditados
com pesos não-canónicos (documents-engine 23, portaria-camera 20,
admin-shell 8, aluno-accounts 8, etc). A auditoria não estava completa.
Próximo: auditar a pasta includes/.


## DIAGNÓSTICO RESPONSIVO (v148)
Novo tipo de diagnóstico: comportamento em celular/tablet/laptop/pc.
Scanner mede tabelas largas sem scroll, modais sem max-height, larguras
fixas. HONESTIDADE: dos 12 suspeitos de tabela, 7 eram reais (resto já
tinha overflow:auto). Corrigidas 7 tabelas (financeiro-config, whatsapp,
aluno-accounts, financeiro-pagamentos, acta) com wrapper overflow-x, e o
modal do equipe com max-height:90vh. NOVO 13º gate diag-responsivo.php
(resolve classes CSS, exclui overlays, baseline só desce, actual 0). 13
gates verdes, 306 invariantes. Próximo: para profundidade total (z-index,
sobreposições runtime), renderização headless em viewports reais no teste.
