# TENANT ISOLATION REGISTER - v12.12.26

Fase 3 (Isolamento de Tenant) - incremento final: leitura e resolvedor.

## Principio

Toda a resolucao de contexto de escola (`escola_id`) e determinista ou fail-closed. O sistema nunca adivinha a escola 1. Quando o contexto e ambiguo (zero ou varias escolas activas e sem contexto explicito), a resolucao devolve 0 e audita `tenant_context_missing`.

## Resolvedor `sige_get_escola_id()`

Cadeia de 5 passos: (1) constante `SIGE_CURRENT_ESCOLA`; (2) meta `sige_escola_id` do utilizador autenticado; (3/3b) subdominio por slug; (4) subdirectorio por slug; (5) resolucao final.

Antes da v12.12.11, o passo 5 em modo relaxado devolvia `SIGE_ESCOLA_MALISA` (constante = 1) de forma cega. A partir da v12.12.11, o passo 5 usa `sige_multitenancy_single_active_school_id()`: devolve o id REAL da unica escola activa quando existe exactamente uma; senao 0 (auditado). O fallback cego foi removido.

## Helper `sige_multitenancy_single_active_school_id()`

Devolve o id sse `sige_multitenancy_active_school_count() === 1`; caso contrario 0. Static-cached. Trata tabela ausente (0).

## Constante `SIGE_ESCOLA_MALISA`

OBSOLETA desde a v12.12.11. Mantida definida (= 1) apenas por retrocompatibilidade. Zero `return SIGE_ESCOLA_MALISA` em codigo. Nao deve voltar a ser usada como fallback.

## Classes de fallback cego eliminadas

| Classe | Padrao | Antes | Agora |
|--------|--------|-------|-------|
| Ternario (cast) | `? (int)sige_get_escola_id() : 1` | 120 | 0 (`: 0`) |
| Ternario (sem cast) | `? sige_get_escola_id() : 1` | 12 | 0 (`: 0`) |
| Fixo | `$escola_id/$escola_id_contexto/$eid = 1;` | 7 | 0 (escola unica activa) |
| Default de linha | `escola_id ?? 1` (inc. `max(1, ...)`) | 9 | 0 (`?? 0`) |
| Constante | `return SIGE_ESCOLA_MALISA` | 1 | 0 |

Baseline de fallbacks de tenant: 138 -> 0 (`docs/security/TENANT_FALLBACK_BASELINE-v12.12.11.json`).

## Contextos publicos e cron

- Webhooks M-Pesa/e-Mola: resolvem `escola_id` por token + payload (`escola_id`/`school_id`), com idempotencia por `UNIQUE (escola_id, referencia)`. O resolvedor e apenas secundario e fail-closed (aborta se 0).
- Cron de email (`sige_processar_email_queue`): `escola_id` por linha (coluna), sem dependencia do resolvedor.
- Hub: opera por site; comandos resolvem por escola unica activa em sites mono-escola cliente.

## Enforcement

Gate `tools/check-tenant-read-resolver.php` impede a reintroducao das quatro classes. Sumidouros de escrita (v12.12.9) bloqueiam escola 0 (`sige_tenant_write_guard`). Pedidos de escrita exigem escola valida (`sige_require_escola_id`).

## v12.12.12 (reposicao automatica)

- A reposicao re-executa a operacao pelo mesmo metodo de servico, cuja primeira linha e sige_tenant_write_guard(escola_id). O escola_id e o capturado da chamada original ja validada (Fase 3, fail-closed), nao vem do cliente na confirmacao.
- Se o utilizador deixar de ter acesso a essa escola no intervalo, o guard devolve contexto invalido e a operacao nao corre. Sem novo fallback de tenant.

## v12.12.13 (painel de controlo de seguranca MFA)

- Nao aplicavel a tenant: os controlos sao globais da instalacao (modulo sistema, tenant_required false). O painel nao le nem escreve dados por escola; apenas opcoes globais de seguranca. Sem novo fallback de tenant.

## v12.12.14 (Secret Vault, incremento 1)

- Sem alteracao ao modelo de tenant. As opcoes de pagamento mantem o scoping por escola (sige_{provider}_escola_{id}_{key}); o cofre apenas cifra o valor guardado, sem mudar o nome nem o ambito da opcao. O webhook continua a resolver a escola pelo token (agora revelado antes da comparacao).

## v12.12.15 (Ledger financeiro, incremento 1)

- O ledger e isolado por escola: escola_id em cada entrada, sequencia e cadeia por escola, e chave unica (escola_id, seq). A verificacao e a serializacao (GET_LOCK) sao por escola. O escritor resolve o escola_id do contexto (sige_get_escola_id) ou recebe-o explicitamente das operacoes.

## v12.12.16 (patch correctivo do Ledger)

- Sem alteracao ao isolamento. O guard de existencia e o escritor mantem o escola_id explicito e o fail-closed. Baseline de fallbacks: 0.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- A ancora e por escola (anchor-{escola}.json) e a escrita ocorre dentro do bloqueio por escola. O registo de pagamento resolve o escola_id do lancamento (l->escola_id) ou do contexto. Baseline de fallbacks: 0.

## v12.12.18 (Ledger incr 3: lancamentos)

- A escrita em bloco e por escola (sige_ledger_append_many recebe escola_id; o buffer agrupa por escola e o bloqueio e por escola). O registo de cobranca resolve o escola_id do contexto (sige_get_escola_id) e e fail-closed (sem escola valida nao regista). Baseline de fallbacks: 0.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- Cada evento passa o escola_id explicito (do row da despesa, do contexto do credito, ou do parametro do fecho). O escritor e fail-closed (sem escola valida nao regista). Baseline de fallbacks: 0.

## v12.12.21 (Fase 7 incr 1: reconciliacao e divergencias)

- O relatorio consulta sempre por escola_id (sige_get_escola_id) e a funcao de dominio e fail-closed (escola <= 0 devolve vazio). Sem leitura entre escolas. Baseline de fallbacks: 0.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

A tabela sige_fin_aprovacoes e sempre acedida por escola_id (insercao, leitura, decisao). Nao ha fallback de tenant novo. Operacoes fail-closed para escola_id <= 0, em linha com a Fase 3.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

O isolamento por escola_id mantem-se inalterado e nao ha alteracao de fallback face a Fase 3. As duas views repostas conservam as suas guardas internas, fail-closed por escola; a correccao e apenas de roteamento na shell, nao toca no acesso a dados por tenant.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

Os agregados do inventario sao calculados sempre com filtro WHERE escola_id, e a funcao de contagem e fail-closed: escola_id menor ou igual a zero devolve zero sem consultar a base. Nenhum novo fallback de tenant foi introduzido (a disciplina da Fase 3 mantem-se). O ecra nunca expoe linhas nem dados individuais, apenas contagens por escola.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- O dossie e a exportacao usam escola_id em todas as consultas e sao fail-closed: aluno de outra escola e recusado sem devolver dados (verificacao sige_pii_dossier_aluno_pertence antes de qualquer leitura).
- Sem novo fallback de tenant; a regra do Kernel exige tenant_required=true em runtime. Politica de isolamento alinhada com a Fase 3.


## Actualizacao v12.12.25 - Fase 8 incremento 3 (apagamento por anonimizacao)

Este incremento acrescenta a primeira operacao destrutiva do produto: o apagamento por anonimizacao (direito ao apagamento). Foi adicionado um endpoint admin_post governado em modo enforce e risco critico (admin_post:sige_privacidade_apagar), com confirmacao em dois passos por numero de processo, nonce, rate limit (5/300s), isolamento por escola e auditoria antes e depois. A superficie de accao passou de 198 para 199 e o enforce de 32 para 33. Nova permissao critica privacidade.apagamento_executar, semeada so a administracao e direccao e sempre auditada. Sem eliminacao fisica de linhas e sem migracao de esquema (SCHEMA_VERSION inalterada). Manifesto e Kernel mantem-se alinhados (199 == 199).


## Actualizacao v12.12.26 - Fase 8 incremento 3.2 (completar o catalogo de PII)

Este incremento classifica as 12 colunas com aspeto de dado pessoal que estavam fora do catalogo (lacunas detectadas pelo inventario da Incr 1), levando o catalogo de 76 para 88 campos e as lacunas de 12 para 0. As nove colunas identificaveis de sige_alunos (incluindo o documento de identidade digitalizado, a fotografia, os contactos de emergencia e os dados da pessoa autorizada a buscar o aluno) passam a ser tratadas pelo dossie de acesso/portabilidade e pelo motor de anonimizacao, fechando o buraco em que sobreviviam a um apagamento. As duas datas operacionais de cobranca sao classificadas mas preservadas na anonimizacao (lista de preservacao); a coluna de notas de funcionario e classificada mas fica fora do ambito do apagamento do aluno. Sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (SCHEMA_VERSION inalterada).
