# SIGE SoftGenial v12.11.9.24 - Fase 1 Blindagem Imediata P0.1

## Resumo executivo

Esta versão continua a Fase 1 - Blindagem imediata iniciada na v12.11.9.23. O foco desta ronda foi fechar o risco dos links públicos de recibo enviados por WhatsApp, reduzir bypasses por `manage_options`, adicionar validação central de IDs por escola, ampliar protecção documental e introduzir snapshot defensivo antes de restauros de configuração.

**Versão:** 12.11.9.24  
**Build:** `sige-12.11.9.24-phase1-blindagem-p0-1`  
**Canal:** `test`  
**Tipo:** Security / Public Receipts / Tenant Scope / Backup Safety

Não foram alteradas fórmulas financeiras, fórmulas académicas, cálculo de notas, cálculo de mensalidades, pautas ou boletins.

---

## Intervenções feitas nesta ronda, de acordo com o plano original da Fase 1

### 1. Links de recibo por WhatsApp corrigidos

**Estado:** concluído nesta ronda.

Antes, o recibo público validava token, mas ainda dependia de `sige_get_escola_id()` para procurar o recibo. Em ambiente multi-escola estrito, isso podia falhar se o link fosse aberto sem subdomínio/subdirectório da escola.

Agora:

- o link público continua sem exigir login;
- o token passa a ser tenant-aware e aluno-aware;
- a URL pública passa a incluir `e=escola_id` e `a=aluno_id`;
- o handler resolve a escola/aluno pela BD financeira;
- o recibo deixa de depender do fallback para escola `1`;
- links antigos continuam aceites quando não há ambiguidade;
- links antigos sem aluno/escola que possam expor vários alunos passam a pedir reenvio do recibo.

Ficheiros alterados:

- `includes/email-engine.php`
- `includes/documents-engine.php`

Funções principais:

- `sige_recibo_lookup_context()`
- `sige_recibo_token_key()` v2
- `sige_recibo_gerar_token()` v2
- `sige_recibo_validar_token()` com hints de escola/aluno
- `sige_recibo_url_publica()` com `e` e `a`
- `sige_recibo_publico_handler()` tenant-aware
- `sige_gerar_html_recibo_agrupado(..., $escola_id_contexto)`

### 2. Removido fallback público perigoso do recibo

**Estado:** concluído nesta ronda.

O renderer tinha fallback onde, se o recibo não encontrasse linhas para o `aluno_id` filtrado, voltava a renderizar o recibo inteiro. Isso era perigoso para recibos agrupados/familiares.

Agora, se o link público identifica um aluno e não encontra itens daquele aluno, o sistema bloqueia com “Recibo não encontrado para este aluno”, em vez de mostrar todos os itens.

### 3. Bypasses baseados em `manage_options` convertidos para admin WordPress real

**Estado:** avançado fortemente nesta ronda.

Foi feita uma varredura nos ficheiros PHP e as chamadas directas a:

```php
current_user_can('manage_options')
```

foram convertidas, onde aplicável, para:

```php
function_exists('sige_is_real_wp_admin_user') ? sige_is_real_wp_admin_user() : current_user_can('manage_options')
```

Isto mantém compatibilidade, mas evita que uma role SIGE legada com `manage_options` herdado seja tratada como administrador técnico real.

Observação: ainda existem referências textuais/capability strings a `manage_options` em menus, comentários, mapas legados e fallbacks técnicos. Isso não significa que o Director voltou a receber `manage_options`; a role `sige_director` continua sem essa capability.

### 4. Guarda central de escopo por escola para IDs sensíveis

**Estado:** implementado nesta ronda como camada transversal.

Foi adicionado:

- `includes/security-scope-guard.php`

Esta guarda corre em superfícies SIGE autenticadas e valida IDs recebidos em GET/POST/AJAX antes dos handlers funcionais executarem.

IDs cobertos nesta camada:

- `aluno_id`
- `turma_id`
- `disciplina_id`
- `professor_id`
- `matricula_id`
- `pagamento_id`
- `lancamento_id`
- `servico_id`
- `pacote_id`
- `plano_id`
- `prestacao_id`
- `despesa_id`
- `rota_id`
- `criterio_id`
- `centro_id`
- IDs específicos de WhatsApp queue em acções conhecidas

Se um ID não pertence à escola actual, a operação é bloqueada e registada como:

```text
tenant_scope_guard_denied
```

### 5. Protecção adicional de documentos do aluno

**Estado:** avançado nesta ronda.

Na v12.11.9.23, os documentos principais do portal já tinham endpoint seguro. Nesta ronda, também foi alterado o link de BI na lista de alunos para usar `sige_secure_document_url()` em vez do URL público directo.

Ficheiro alterado:

- `admin/academic/alunos_lista.php`

### 6. Snapshot antes de restauro de configuração

**Estado:** implementado como segurança defensiva inicial.

Foi adicionado:

- `includes/backup-safety.php`

Antes de restaurar configurações via JSON, o sistema tenta criar um snapshot da configuração actual da escola em pasta protegida:

```text
wp-content/uploads/sige-secure-backups/
```

Com protecção básica por `.htaccess` e `index.html`.

Ficheiro alterado:

- `includes/db-handler.php`

Observação honesta: isto ainda não é uma estratégia completa de backup/restore de produção. É uma camada de segurança antes de restauros de configuração.

### 7. Teste estático da Fase 1

**Estado:** implementado nesta ronda.

Foi adicionado:

- `tools/smoke-phase1-blindagem-v12-11-9-24.php`

Resultado:

```text
15 OK, 0 FAIL
```

O smoke cobre:

- versão/build;
- carregamento do scope guard;
- carregamento do backup safety;
- recibo público tenant-aware;
- URL pública com escola/aluno;
- renderer com escola explícita;
- remoção do fallback inseguro;
- BI via endpoint seguro;
- Director sem `manage_options`;
- migração defensiva de `manage_options`.

---

## Validações executadas

### PHP lint

```text
170 ficheiros PHP verificados
0 erros sintácticos detectados
```

### Smoke test Fase 1

```text
15 OK
0 FAIL
```

### ZIP

```text
unzip -t: No errors detected
```

---

## O que esta versão resolve directamente

1. Links de recibo por WhatsApp não devem quebrar por falta de contexto de escola.
2. Novos links de recibo já saem com escola e aluno na URL.
3. Recibos públicos deixam de depender da escola `1` como fallback.
4. Link público não mostra recibo inteiro quando o token/URL aponta para aluno sem itens.
5. IDs sensíveis passam por guarda central de escopo por escola.
6. Vários bypasses por `manage_options` passam a reconhecer apenas administrador WordPress real.
7. Documentos de BI na lista de alunos deixam de apontar directamente para uploads públicos.
8. Restauro de configuração passa a criar snapshot defensivo antes de aplicar alterações.

---

## O que ainda falta nesta Fase 1, de acordo com o plano original

### 1. Auditoria endpoint por endpoint

A guarda central reduz risco, mas ainda falta uma revisão manual endpoint por endpoint para confirmar regras específicas de cada acção.

Prioridade ainda pendente:

- pagamentos;
- anulação/cancelamento;
- isenções;
- lançamento de mensalidades;
- notas;
- actas;
- mapas oficiais;
- documentos finais;
- RH/staff;
- transporte;
- Jardim.

### 2. Testes reais Escola A / Escola B

Ainda falta executar testes dinâmicos com duas escolas reais na mesma instalação:

- Escola A não vê alunos da Escola B;
- Escola A não vê pagamentos da Escola B;
- professor da Escola A não vê turmas da Escola B;
- encarregado da Escola A não vê documentos da Escola B;
- link de recibo da Escola A não abre recibo da Escola B;
- utilizador sem escola válida não recebe dados por fallback.

### 3. Proteger todos os documentos/anexos do sistema

Já foram protegidos os principais documentos do aluno e o link de BI da lista. Ainda falta rever:

- declarações;
- boletins gerados;
- pautas;
- actas;
- documentos financeiros antigos;
- documentos de RH;
- anexos do Jardim;
- anexos futuros de disciplina/ocorrência;
- uploads legados já gravados como URL directa.

### 4. Backup/restore completo

Foi criado snapshot defensivo antes de restauro de configuração. Ainda falta plano completo:

- backup de base de dados;
- backup de uploads críticos;
- retenção;
- encriptação;
- teste de restauração;
- procedimento de recuperação de desastre;
- exportação segura por escola.

### 5. Matriz final de roles como política formal de produto

A role `sige_director` já está sem `manage_options`, mas ainda falta transformar a matriz final de roles em política operacional completa com permissões por acção crítica.

Perfis a fechar:

- Admin WordPress real;
- Admin técnico SIGE;
- Direcção;
- Secretaria geral;
- Secretaria académica;
- Financeiro;
- Coordenação pedagógica;
- Professor;
- RH;
- Transporte;
- Jardim;
- Encarregado;
- Aluno.

### 6. Testes automatizados mais profundos

O smoke desta versão é estático. Ainda falta suíte dinâmica para:

- permissões;
- portal;
- documentos;
- financeiro;
- recibos públicos;
- notas;
- multi-escola;
- WhatsApp queue;
- restore/snapshot.

---

## Recomendação de uso

Esta versão deve continuar em ambiente de teste/staging. Ela resolve um risco real dos recibos por WhatsApp e melhora a blindagem geral, mas ainda não fecha toda a Fase 1. A próxima ronda recomendada é testar Escola A / Escola B em ambiente real e depois reforçar manualmente os endpoints financeiros e académicos mais sensíveis.
