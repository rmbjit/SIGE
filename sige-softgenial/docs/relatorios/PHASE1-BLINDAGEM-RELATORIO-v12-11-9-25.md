# SIGE SoftGenial v12.11.9.25 - Relatório Fase 1 Blindagem Final Candidate

Data: 2026-05-31  
Canal: test  
Build: `sige-12.11.9.25-phase1-blindagem-final-candidate`

## Veredito

Este pacote fecha tecnicamente, no código, os pontos pendentes da **Fase 1 - Blindagem imediata**. O build deve continuar em ambiente de teste/staging até validação prática dos cenários críticos.

A Fase 1 pode ser considerada **concluída operacionalmente** quando os testes reais em staging confirmarem:

1. Escola A não vê dados da Escola B.
2. Recibos WhatsApp novos abrem sem login.
3. Recibos WhatsApp antigos seguros continuam aceites.
4. Recibos antigos ambíguos falham fechado com mensagem clara.
5. Portal antigo e portal novo abrem documentos do aluno por endpoint autenticado.
6. Utilizador sem permissão não baixa documentos de outro aluno.
7. RH/equipa só abre anexos RH de colaboradores da escola actual.
8. Restauro de configuração cria snapshot antes de alterar dados.

## O que foi intervindo nesta ronda

### 1. Tenant guard alargado para IDs genéricos

Ficheiro principal:

- `includes/security-scope-guard.php`

Melhorias:

- Validação de `id`, `ids` e `user_id` por acção.
- Cobertura de campos adicionais: `acta_id`, `nota_id`, `queue_id`, `id_vinculo`, `turma_disciplina_id`.
- Cobertura de acções RH/equipa: `sige_get_staff_secure`, `sige_remover_usuario_staff`, `sige_resetar_senha`, `sige_editar_usuario_staff`, `sige_toggle_status_staff`.
- Cobertura de fila WhatsApp: `sige_wppc_get_full`, `sige_wppc_cancel`, `sige_wppc_retry`, `sige_wppc_delete`, `sige_wppc_send_link_now`.
- Cobertura de matriz curricular e centros.
- Validação de utilizadores WordPress operacionais por escola actual usando:
  - meta `sige_escola_id`;
  - meta `sige_professor_id`;
  - e-mail na tabela `sige_professores`;
  - meta `sige_aluno_id`;
  - matriz `sige_user_roles`, quando disponível.

Regra importante: atribuição inicial de perfil a um utilizador ainda sem escola só é permitida para administrador WordPress real. Utilizador já vinculado a outra escola continua bloqueado.

### 2. Impressões internas autenticadas validadas também fora de `admin_init`

Ficheiro:

- `includes/documents-engine.php`

Melhorias:

- `?sige_print=recibo` valida o pagamento contra a escola actual.
- `?sige_print=recibo_massa` valida todos os pagamentos do lote.
- `?sige_print=factura` valida o aluno.
- `?sige_print=extracto` valida o aluno.

Isto cobre links de impressão que correm em `template_redirect` e não necessariamente passam pela guarda de `admin_init`.

### 3. Recibos públicos/WhatsApp mais seguros

Ficheiro:

- `includes/email-engine.php`

Melhorias:

- Novos links públicos deixam de ser gerados quando o recibo é ambíguo.
- Recibos agrupados/familiares precisam de `aluno_id` resolvido com segurança.
- O link público continua com `e`, `a` e `tk`:
  - `e`: escola;
  - `a`: aluno;
  - `tk`: token.
- Links antigos ambíguos continuam a falhar fechado.

Resultado: os recibos WhatsApp continuam sem login, mas a geração de novos links evita ambiguidade entre escolas/alunos.

### 4. Portal antigo passou a usar documentos protegidos

Ficheiro:

- `includes/portal-logic.php`

Melhorias:

- A secção de documentos do portal antigo já não aponta directamente para `doc_bi_url`, `doc_cert_url` e `doc_vacina_url` quando o endpoint seguro está disponível.
- Passa a usar `sige_secure_document_url()`.

O portal novo já usava endpoint seguro desde a intervenção anterior.

### 5. Endpoint seguro para anexos RH/equipa

Ficheiro:

- `includes/secure-document-download.php`

Novo endpoint:

```text
admin-post.php?action=sige_secure_staff_document_download
```

Campos suportados:

- `doc_bi`
- `doc_cv`
- `doc_cert`

Validações:

- utilizador autenticado;
- nonce;
- escola actual;
- colaborador pertence à escola actual;
- utilizador actual tem permissão RH/equipa;
- ficheiro é local e pertence ao `wp_upload_dir`;
- ficheiros externos ou fora de uploads são bloqueados.

### 6. AJAX de equipa devolve URLs seguras

Ficheiro:

- `includes/ajax-handlers.php`

Melhoria:

- O detalhe sensível de colaborador passa a devolver `docs_secure`, preparado para abrir anexos RH por endpoint autenticado.
- Os campos brutos continuam preservados para edição/compatibilidade, mas agora há caminho seguro disponível.

### 7. Backup/restauro reforçado

Ficheiros:

- `includes/backup-safety.php`
- `includes/db-handler.php`

Melhorias:

- Directório `sige-secure-backups` reforçado com:
  - `.htaccess`;
  - `web.config`;
  - `index.php`;
  - `index.html`.
- Snapshots JSON passam a incluir `_manifest` com:
  - plugin;
  - versão;
  - site;
  - data;
  - schema.
- Criado snapshot leve da escola antes de restauro de configuração.
- Criada limpeza de snapshots antigos por retenção configurável.
- Mantido snapshot de configuração antes de restauro.

## Validações executadas

### PHP lint

Resultado:

```text
171 ficheiros PHP verificados
0 erros sintácticos detectados
```

### Smoke test Fase 1 final

Resultado:

```text
22 OK
0 FAIL
```

Itens verificados pelo smoke test:

- versão actualizada;
- `BUILD.json` correcto;
- roles SIGE sem `manage_options` explícito;
- filtro defensivo de capabilities técnicas activo;
- tenant guard com validação de WP users;
- tenant guard para equipa/RH;
- tenant guard para fila WhatsApp;
- validação de `?sige_print`;
- endpoint seguro de documentos do aluno;
- endpoint seguro de documentos RH;
- portal antigo com endpoint seguro;
- `docs_secure` no AJAX de equipa;
- recibos sem geração de links ambíguos;
- links de recibo com escola/aluno;
- links antigos ambíguos bloqueados;
- backup safety com manifest v2;
- snapshot leve da escola;
- retenção de snapshots;
- recibo público falha fechado quando aluno não bate.

### ZIP

Resultado:

```text
unzip -t: No errors detected
```

## O que não foi alterado

- Fórmulas financeiras.
- Fórmulas académicas.
- Cálculo de notas.
- Cálculo de mensalidades.
- Multas.
- Descontos.
- Pautas.
- Boletins.
- Regras de aprovação/reprovação.

## Estado da Fase 1

### Fechado no código

- Remoção/neutralização de `manage_options` para roles SIGE não técnicas.
- Fallback perigoso de escola mitigado.
- Recibos WhatsApp corrigidos para contexto seguro.
- Guardas centrais para IDs sensíveis.
- Impressões internas protegidas por tenant.
- Documentos principais do aluno protegidos.
- Portal antigo corrigido.
- Anexos RH com endpoint seguro.
- Backup/restauro com snapshots defensivos.
- Smoke tests estáticos criados e aprovados.

### Ainda precisa validação prática em staging

A Fase 1 fica operacionalmente concluída depois dos testes reais em ambiente seguro com:

- duas escolas;
- alunos diferentes;
- professor de uma escola;
- encarregado de uma escola;
- colaborador RH;
- recibos novos e antigos;
- documentos de aluno;
- anexos RH;
- restauro de configuração.

## Recomendação

Instalar este pacote primeiro no mesmo ambiente de teste seguro. Se os cenários acima passarem, a **Fase 1 pode ser marcada como concluída** e o próximo passo recomendado é iniciar a **Fase 2 - Corrigir arquitectura multi-escola**.
