# Adversarial Review v12.12.22

## Rediagnostico adversarial - Correccao de roteamento da Fase 7

Revisao adversarial da release correctiva v12.12.22, conduzida sobre a versao
empacotada, na perspectiva de um atacante interno e de um revisor de seguranca.
Objectivo: confirmar que a reposicao das duas views nao abre nenhum buraco de
acesso, que o defeito original esta de facto fechado, e que a classe de defeito
fica trancada por gate.

### Superficie analisada

- admin-shell.php: allowlist anti-LFI (array $_views_ok), matriz de permissoes
  (array sige_view_permission_map), mapa de despacho (array $map) e a ordem de
  execucao das duas portas de controlo (allowlist antes do despacho; matriz por
  isset).
- Guardas internas das duas views: sige_mpesa_pode_gerir (reconciliacao) e
  sige_fin_aprovacao_pode_aceder (aprovacoes), e as permissoes que cada uma
  concede.
- Gates de QA tocados: governance-lib.php (extractor de views), check-view-
  permission-map.php (invariante novo), check-aprovacoes.php e check-
  reconciliacao.php (verificacao de allowlist e matriz).

### Vectores testados e resultado

- V1: a view volta a abrir para quem tem a permissao? CONFIRMADO. O slug consta
  agora da allowlist (nao e reescrito para o painel) e da matriz com as permissoes
  da propria guarda; a guarda interna da view continua a ser a autoridade final.
- V2: a reposicao na matriz bloqueia quem a guarda interna deixaria entrar?
  NEUTRALIZADO. As permissoes da matriz espelham exactamente as guardas: a
  reconciliacao usa a mesma permissao da view financeiro-mpesa, ja em producao e
  validada; as aprovacoes usam financeiro.estornar OU financeiro.caixa_reabrir,
  exactamente como sige_fin_aprovacao_pode_aceder.
- V3: a reposicao na allowlist abre acesso a quem nao devia? NAO. A allowlist e
  apenas defesa em profundidade contra LFI: nao concede acesso, so impede a
  reescrita para o painel. O acesso continua governado pela matriz (porta A) e
  pela guarda interna da view.
- V4: LFI por slug arbitrario? NAO. O slug continua filtrado pela allowlist
  (in_array estrito) e, no ramo sem mapa, por preg_replace que limita a
  [a-z0-9_-]; nada disto foi enfraquecido.
- V5: o defeito pode reaparecer numa view futura sem ser detectado? BLOQUEADO. O
  gate check-view-permission-map passa a exigir que toda a rota do mapa de despacho
  conste da allowlist; provado por teste negativo (remocao dos slugs faz o gate
  falhar com mensagem explicita de rota morta; reposicao volta a verde).
- V6: a correccao mexeu no calculo financeiro ou nos dados? NAO. finance-core.php
  intocado (tres funcoes byte-identicas a v12.12.21); SCHEMA_VERSION inalterada;
  manifesto e Kernel em 197.

### Achados

- P0: nenhum.
- P1: nenhum em aberto. O unico P1 desta janela era o proprio defeito corrigido
  (duas views da Fase 7 inalcancaveis na interface), agora fechado e trancado por
  gate.
- P2: nenhum. Considerou-se se a guarda sige_mpesa_pode_gerir concede acesso por
  papel (Direccao, Secretaria Geral) alem da permissao; como a view financeiro-mpesa
  ja opera com a mesma matriz e guarda em producao sem queixa, o comportamento e
  identico e nao constitui regressao.
- P3: nota documental. As contagens de views na allowlist e na matriz sobem de 54
  para 56; reflectido no inventario e nos registos.

### Decisao

Aprovado para empacotamento. A correccao e definitiva (repoe o roteamento e
mantem o acesso governado pelas guardas internas), nao introduz nova superficie e
fecha a lacuna de QA por invariante estrutural. Rediagnostico adversarial a Zero
P0/P1. Pronto para validar de pasta limpa e entregar; so depois se inicia a Fase 8.
