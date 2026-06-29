# Phase Charter v12.12.22

## Programa SIGE SoftGenial Alto Calibre - Correccao de roteamento da Fase 7

Release correctiva entre a Fase 7 (concluida) e a Fase 8 (a iniciar). Base:
v12.12.21. Antes de avancar para a Fase 8, repoe-se o acesso a duas paginas
entregues na Fase 7 que estavam inalcancaveis na interface, e fecha-se a lacuna
de QA que deixou o defeito passar como validado.

## Objectivo

Garantir que as duas views entregues na Fase 7, financeiro-reconciliacao
(Reconciliacao e Divergencias, da v12.12.20) e financeiro-aprovacoes (Aprovacoes
Pendentes, da regra de quatro-olhos, da v12.12.21), voltam a abrir a partir do
menu financeiro, com o mesmo controlo de acesso pretendido. O sintoma era: ao
clicar em qualquer das duas, a aplicacao devolvia a pagina inicial.

Causa: ambos os slugs estavam registados no mapa de despacho do admin-shell, no
Security Kernel, no manifesto e na navegacao, mas em falta na allowlist anti-LFI
(o array $_views_ok) e na matriz de permissoes (o array sige_view_permission_map).
A guarda anti-LFI, que corre antes do despacho, reescrevia qualquer view fora da
allowlist para o painel inicial. Resultado: as duas paginas ficavam mortas na
interface mesmo estando completas e governadas.

## Incluido

- Reposicao de financeiro-reconciliacao e financeiro-aprovacoes na allowlist
  anti-LFI (admin-shell.php), repondo o roteamento das duas views.
- Reposicao das mesmas na matriz de permissoes, com permissoes identicas as
  guardas internas de cada view: reconciliacao exige financeiro.mobile_payments_gerir
  (igual a view financeiro-mpesa, ja validada); aprovacoes exige
  financeiro.estornar OU financeiro.caixa_reabrir (igual a guarda
  sige_fin_aprovacao_pode_aceder).
- Endurecimento de QA em tres camadas, para a classe de defeito nao voltar:
  (1) invariante estrutural no gate check-view-permission-map, exigindo que toda
  a rota do mapa de despacho conste da allowlist (extractor de views estendido em
  governance-lib.php para captar o mapa de despacho);
  (2) verificacao explicita no gate check-aprovacoes de que o slug consta da
  allowlist e da matriz;
  (3) verificacao explicita no gate check-reconciliacao de que o slug consta da
  allowlist e da matriz.
- Regeneracao da governanca para v12.12.22: manifesto, baselines, regras do
  Kernel, 12 documentos, CHANGELOG, BUILD.json, DEPLOY e QA.

## Excluido

- Nenhuma alteracao da superficie de accao: nao ha novo endpoint, AJAX, REST,
  admin-post nem view-action. O manifesto e as regras do Security Kernel mantem-se
  em 197 (enforce 31); manifestIds == ruleIds preservado.
- Nenhuma migracao de dados: SCHEMA_VERSION mantem-se 20260621.1.
- Nenhuma alteracao das regras de calculo financeiro: finance-core.php intocado;
  as tres funcoes bloqueadas (sige_fin_saldo_lancamento, sige_fin_saldo_sql,
  sige_fin_total_bruto_sql) ficam byte-identicas a v12.12.21.
- Nenhuma alteracao de design tokens, de logica de negocio das views, nem do
  comportamento das paginas para alem de voltarem a estar acessiveis.

## Riscos

- Risco de regressao de acesso (resolvido): as permissoes da matriz poderiam
  bloquear quem a guarda interna deixaria entrar. Mitigado por espelhamento exacto
  das guardas internas e por escolha de permissoes ja provadas pela view
  financeiro-mpesa, que partilha a mesma guarda da reconciliacao.
- Risco de gate fragil (mitigado): o novo invariante estrutural foi validado por
  teste negativo (reintroducao do defeito faz o gate falhar com mensagem
  explicita; reposicao volta a verde).
- Risco residual: P0 e P1 a zero apos rediagnostico adversarial (ver Adversarial
  Review v12.12.22).

## Criterios de aceitacao

- As duas views abrem a partir do menu para utilizadores com a permissao
  respectiva, e mostram a mensagem de area reservada a quem nao a tem (em vez de
  cair no painel inicial).
- Corredor de gates completo verde; release gate verde; lint a todo o PHP sem
  erros; zero travessoes em codigo e em documentos.
- Manifesto e regras do Kernel em 197 (enforce 31); SCHEMA_VERSION 20260621.1;
  tres funcoes de calculo byte-identicas a v12.12.21.
- Invariante de roteamento activo e provado por teste negativo.
- Rediagnostico adversarial a Zero P0/P1 antes de fechar.
