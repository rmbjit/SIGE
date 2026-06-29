# SIGE SoftGenial v12.11.9.23 - Relatório da Fase 1: Blindagem Imediata P0

## Estado desta intervenção

Pacote base analisado: `sige-softgenial-v12_11_9_22.zip`  
Pacote intervencionado: `sige-softgenial-v12_11_9_23-phase1-blindagem-p0.zip`  
Canal: `test`  
Tipo: Segurança / multi-escola / documentos protegidos  
Data: 2026-05-31

Esta intervenção aplicou correcções P0 da Fase 1 sem alterar fórmulas financeiras, fórmulas académicas, regras de cálculo de notas, mensalidades, multas ou descontos.

---

## Intervenções feitas de acordo com o plano original da Fase 1

### 1. Remover `manage_options` da role `sige_director`

**Ficheiro principal:** `includes/security-roles.php`

- A role `sige_director` deixou de receber a capability técnica `manage_options`.
- Foi adicionada uma limpeza automática para remover `manage_options` de roles SIGE não-nativas caso a capability tenha ficado gravada de versões anteriores.
- Foi adicionada migração defensiva única para remover `manage_options` directamente atribuído a utilizadores com roles SIGE, preservando administradores WordPress reais.

**Objectivo cumprido:** reduzir o risco de um Director escolar herdar poderes técnicos do WordPress inteiro.

---

### 2. Rever bypasses com `manage_options` nos pontos críticos iniciais

**Ficheiros principais:**

- `includes/security-hardening.php`
- `includes/security-roles.php`
- `includes/permissions-layer.php`
- `includes/cron-tasks.php`
- `includes/multitenancy.php`

**Intervenção:**

- Foi criada a função `sige_is_real_wp_admin_user()` para distinguir administrador WordPress real de utilizador SIGE que herdou `manage_options` por erro/legado.
- Guardas críticos passaram a usar administrador real quando o objectivo é poder técnico.
- O endpoint de processamento de fila passou a exigir administrador real ou permissão segura SIGE, com logs de tentativas negadas.

**Objectivo parcialmente cumprido:** os bypasses mais críticos foram protegidos, mas ainda falta uma auditoria completa de todos os ficheiros que usam `current_user_can('manage_options')`.

---

### 3. Bloquear fallback silencioso para escola ID 1 em modo multi-escola

**Ficheiro principal:** `includes/multitenancy.php`

**Intervenção:**

- Foi adicionada contagem de escolas activas.
- Quando há mais de uma escola activa, o sistema passa para modo estrito e deixa de cair silenciosamente para a escola `1`.
- Pode ser forçado com `SIGE_MULTITENANT_STRICT=true`.
- Pode ser temporariamente relaxado com `SIGE_ALLOW_ESCOLA_FALLBACK=true`, apenas para compatibilidade controlada.
- `sige_escola_where()` passa a fechar consultas com `1 = 0` quando não existe escola válida.
- `sige_add_escola_id()` deixa de atribuir escola 1 silenciosamente quando não existe contexto válido.

**Objectivo cumprido parcialmente:** o fallback perigoso foi bloqueado nas funções centrais, mas ainda falta testar endpoint por endpoint em cenário Escola A / Escola B.

---

### 4. Rever rotas que recebem IDs e garantir pertença à escola actual

**Ficheiros principais:**

- `includes/security-hardening.php`
- `includes/core-helpers.php`

**Intervenção:**

- Foram adicionados helpers centrais:
  - `sige_table_has_column_secure()`
  - `sige_object_belongs_to_current_school()`
  - `sige_require_object_belongs_to_current_school()`
- `sige_get_aluno_id_do_utilizador()` passou a validar que o aluno associado ao utilizador pertence à escola actual.
- `sige_get_aluno_id_do_pagamento()` passou a validar o pagamento também por `escola_id`.

**Objectivo parcialmente cumprido:** a base técnica está criada e dois pontos críticos foram ligados; falta aplicar estes helpers a todos os handlers que recebem `aluno_id`, `turma_id`, `pagamento_id`, `lancamento_id`, `professor_id`, `documento_id` e equivalentes.

---

### 5. Proteger documentos do portal por endpoint autenticado

**Ficheiros principais:**

- `includes/secure-document-download.php`
- `sige-softgenial.php`
- `admin/academic/aluno-portal-view.php`
- `includes/security-roles.php`

**Intervenção:**

- Foi criado o endpoint autenticado `admin-post.php?action=sige_secure_document_download`.
- O endpoint valida:
  - login;
  - nonce;
  - campo permitido;
  - aluno;
  - escola actual;
  - permissão do utilizador;
  - ficheiro local dentro de `wp_upload_dir`.
- Documentos do portal do aluno deixaram de usar URL directa para:
  - `doc_bi_url`;
  - `doc_cert_url`;
  - `doc_vacina_url`.
- Foi adicionado log para download seguro e bloqueio de ficheiros não locais.

**Objectivo cumprido para os documentos principais do portal.**  
**Limite conhecido:** documentos externos ou antigos fora de `wp_upload_dir` serão bloqueados até serem recarregados de forma segura.

---

### 6. Reforçar endpoint público de fila/cron

**Ficheiro principal:** `includes/cron-tasks.php`

**Intervenção:**

- O processamento manual/autenticado da fila passou a usar administrador WordPress real ou permissão segura SIGE.
- Tentativas negadas passam a ser registadas em log.
- O endpoint público `admin_post_nopriv_sige_process_queue_now` foi mantido para cron, mas continua dependente de chave válida.

**Objectivo parcialmente cumprido:** o endpoint foi reforçado, mas ainda deve ser testado em staging com chave inválida, chave válida e utilizadores sem permissão.

---

### 7. Activar logs adicionais para acções críticas

**Ficheiro principal:** `includes/audit-hooks.php`

**Intervenção:**

- Adicionados hooks de auditoria explícita para:
  - pedido de download documental;
  - aprovação/alteração de nota votada em acta;
  - gravação de acta.
- Foram mantidos os logs já existentes de documentos, notas, ano lectivo e financeiro.

**Objectivo parcialmente cumprido:** houve reforço, mas ainda falta mapear todos os handlers sensíveis e garantir logs consistentes em cada um.

---

## Validações executadas

- `php -l` executado nos ficheiros PHP alterados.
- `php -l` executado em todos os ficheiros PHP do plugin.
- Resultado: nenhum erro sintáctico PHP detectado.
- `BUILD.json` validado como JSON válido.

---

## O que ainda falta nesta Fase 1, de acordo com o plano original

### Falta 1 - Auditoria completa de permissões sensíveis

Ainda é necessário rever todos os pontos que usam `current_user_can('manage_options')`, especialmente em:

- académico;
- financeiro;
- documentos PDF;
- AJAX handlers;
- settings;
- hub/update;
- WhatsApp;
- Jardim;
- transporte;
- RH.

A intervenção actual removeu o risco principal da role Director, mas não substituiu todos os usos legados de `manage_options`.

---

### Falta 2 - Aplicar validação de escola em todos os IDs recebidos por GET/POST/AJAX

Os helpers foram criados, mas ainda não foram ligados em todos os handlers.

Ainda falta validar sistematicamente:

- `aluno_id`;
- `turma_id`;
- `pagamento_id`;
- `lancamento_id`;
- `professor_id`;
- `documento_id`;
- `rota_id`;
- `funcionario_id`;
- `disciplina_id`;
- `matricula_id`.

---

### Falta 3 - Testes multi-escola Escola A / Escola B

Ainda falta criar testes automáticos para garantir que:

- Escola A não vê alunos da Escola B;
- Escola A não vê pagamentos da Escola B;
- professor da Escola A não vê turmas da Escola B;
- portal de encarregado da Escola A não vê documentos da Escola B;
- utilizador sem escola válida não recebe dados por fallback.

---

### Falta 4 - Matriz final de roles e permissões

A remoção de `manage_options` foi aplicada, mas ainda falta fechar formalmente a matriz final de roles:

- Admin técnico;
- Direcção;
- Secretaria geral;
- Secretaria académica;
- Financeiro;
- Professor;
- Coordenação pedagógica;
- RH;
- Transporte;
- Jardim;
- Encarregado;
- Aluno.

Também falta confirmar a matriz na interface de permissões.

---

### Falta 5 - Proteger todos os tipos de documentos e anexos

A intervenção actual protege os principais documentos do portal do aluno. Ainda falta ampliar o modelo para:

- recibos;
- boletins;
- pautas;
- actas;
- declarações;
- documentos anexados noutros módulos;
- documentos financeiros exportados;
- eventuais ficheiros antigos com URL directa.

---

### Falta 6 - Política de backup e restore testado

Esta intervenção não implementou backup/restore.

Ainda falta definir e implementar:

- backup automático;
- frequência;
- retenção;
- encriptação;
- restore testado;
- procedimento de recuperação de desastre.

---

### Falta 7 - Testes automatizados da Fase 1

Ainda falta uma suíte formal de testes para:

- permissões;
- portal;
- documentos;
- financeiro;
- notas;
- multi-escola;
- endpoint de fila;
- tentativas negadas.

---

## Nota honesta

Esta versão melhora a blindagem P0 e reduz riscos importantes, mas ainda não fecha a Fase 1 por completo. A próxima intervenção deve ser a varredura endpoint por endpoint para aplicar validação de escola e permissão em todos os handlers sensíveis.
