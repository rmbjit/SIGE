# TENANT ISOLATION REGISTER - v12.12.32

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


## Actualizacao v12.12.27 - Fase 8 incremento 4 (retencao e expurgo)

Este incremento acrescenta um ecra de governanca de dados, so de leitura, que mostra o calendario de retencao declarado e quantos registos ja excederam o prazo, de forma agregada por escola. Decisao de seguranca central: neste sistema nao ha expurgo por eliminacao em massa, porque as presencas sao derivadas ao vivo do registo de acessos e os registos financeiros, academicos e de auditoria tem dever de retencao; o expurgo de um titular faz-se pela anonimizacao ja existente (Apagamento), que preserva a integridade. Nova permissao privacidade.retencao_ver (risco medio, so leitura), semeada a administracao e direccao. Sem nova superficie de accao: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (SCHEMA_VERSION inalterada).


## Actualizacao v12.12.28 - Fase 9 incremento 1 (blindagem do modulo de permissoes)

Abre a Fase 9 (seguranca e governanca de acessos). O modulo de Perfis e Permissoes passa a ser seguro por desenho atraves de um avaliador unico de operacao que aplica quatro guardas: alvo protegido (administradores WordPress reais nao sao geriveis pelo modulo, por qualquer actor), anti-escalada (um gestor que nao seja administrador WP real nao pode atribuir um perfil que confira a gestao de permissoes), auto-proteccao (um gestor nao se despromove nem se remove a si proprio) e ultimo gestor (nao se deixa a escola sem nenhum gestor). O handler chama o avaliador e bloqueia nos dois ramos, com auditoria, mesmo com nonce valido. As contas protegidas aparecem na lista so de leitura, com selo Protegido. A porta do menu fica coerente (link mostrado a quem tem usuarios.gerir_permissoes, com Saude do Sistema e Centro de Configuracao restritos ao core admin); migracao idempotente garante a concessao ao admin_ti na base de dados real. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (reconciliacao em role_permissions).


## Actualizacao v12.12.29 - Fase 9 incremento 2 (hierarquia de perfis por nivel)

Continua a Fase 9. Cada perfil passa a ter um nivel (mapa em codigo, sem alteracao de esquema), com os tres gestores no topo (direccao_geral e admin_escola a 90, admin_ti a 80) e os restantes por responsabilidade; perfis desconhecidos ou personalizados ficam no nivel 0, o mais restritivo. O avaliador unico ganha a guarda nivel_insuficiente, por cima da regra anti-escalada do Incr 1: um actor nao protegido so atribui perfis estritamente abaixo do seu nivel e so mexe em utilizadores estritamente abaixo do seu nivel; a regra do Incr 1 mantem precedencia (nenhum nao-administrador atribui um perfil que confira gestao). A interface fica coerente: o selector lista so os perfis atribuiveis e as linhas de nivel igual ou superior ficam so de leitura; contas protegidas continuam so de leitura com selo e administradores WordPress reais mantem autoridade plena. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem migracao de esquema.


## Actualizacao v12.12.30 - Fase 9 incremento 3 (sobreposicao reversivel do papel WordPress)

Continua a Fase 9. Converte a substituicao destrutiva do papel WordPress numa sobreposicao reversivel, fechando a raiz do antigo vector de bloqueio. A atribuicao preserva o papel original numa copia em user meta (idempotente, exclui papeis sige_*, com recurso ao papel por omissao quando nao ha original); a remocao repoe o original e limpa a copia; a sincronizacao no init repoe quando nao ha perfil activo mas o utilizador ainda esta num papel sige_* (auto-cura), preservando tambem antes de qualquer set_role. Os menus legados continuam a funcionar enquanto o perfil esta activo; o administrador WordPress real continua intocado. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem migracao de esquema (usa user meta).


## Actualizacao v12.12.31 - Fase 9 incremento 4 (niveis de perfil editaveis)

Continua a Fase 9. A hierarquia de niveis (Incr 2, mapa em codigo) passa a ser editavel e persistida na base de dados, sem alteracao de esquema. O mapa em codigo continua a ser o valor por omissao; uma opcao guarda apenas os desvios, e role_niveis funde a base com os desvios (o avaliador e a filtragem do selector respeitam os niveis editados automaticamente). A gravacao valida 0 a 100 e guarda so desvios. Editar a hierarquia e accao de dono: o painel Hierarquia de niveis e o handler save_niveis ficam reservados ao administrador WordPress real, com nonce e auditoria. save_niveis e uma accao de formulario dentro da view ja listada, nao uma nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem alteracao de esquema (opcao em wp_options). Sem aumento de current_user_can.


## Actualizacao v12.12.32 - Fase 9 incremento 5 (sobreposicao puramente aditiva por capacidades)

Conclui a Fase 9. Elimina-se a substituicao do papel WordPress: o modulo deixa de chamar set_role (na atribuicao e na sincronizacao do init) e passa a conceder as capacidades em tempo de execucao por um filtro user_has_cap, a partir do papel sige_* mapeado ao perfil SIGE activo, sem nunca tocar no papel guardado. As capacidades sao o conjunto completo do papel mapeado (incluindo a capacidade com o nome do papel, que e o que current_user_can(sige_*) usa). Para nao haver regressao face ao antigo set_role, as capacidades sao do perfil activo mais recente (independente do contexto de escola); a isolacao de dados por escola e separada e nao muda. O filtro ignora administradores reais e utilizadores sem perfil, com cache por pedido e guarda anti-recursao. A sincronizacao no init passa a so limpar: repoe o papel original quando o utilizador esta num papel sige_* de staff legado. As poucas verificacoes por slug de papel sige_* de staff foram convertidas para verificacao por capacidade. Garantia sem WordPress vivo: um gate que falha se existir qualquer verificacao por slug de papel sige_* de staff. Papeis de portal (sige_aluno, sige_encarregado) intocados. Sem nova superficie (199/33, views 60), sem alteracao de esquema, sem aumento de current_user_can.
