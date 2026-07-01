# DEPLOY - SIGE SoftGenial v12.38.0

**RH (passo 5): Férias & Ausências — Fase 1 (tabela nova)**
Data: 2026-07-01 - Tipo: nova funcionalidade. Primeira com tabela própria.

## O que muda
- **NOVO** `includes/rh-ausencias.php` — módulo de Férias & Ausências:
  * tabela `{prefix}sige_rh_ausencias` criada **por código** (idempotente,
    `dbDelta` no `admin_init`) — **sem SQL manual**;
  * funções puras (contagem de dias úteis/corridos, meio-dia, validação);
  * camada de dados tenant-scoped (listar/get/guardar/estado/eliminar);
  * AJAX gated por gestão (`sige_ajax_equipe_can_manage`), com nonce, tenant-scope
    e auditoria. **Não cria permissões novas** (reutiliza a de gerir equipa).
- `admin/hr/equipe-view.php` — nova aba **"Ausências"** (só para quem gere), com
  filtros (ano/tipo/estado), lista com badges, e modal de registo/edição com
  resumo de dias ao vivo; aprovar/rejeitar/eliminar por linha. 100% design system.
- `sige-softgenial.php` / `BUILD.json` -> 12.38.0.

> ⚠️ **1 FICHEIRO NOVO:** `includes/rh-ausencias.php` (pasta `includes/` já
> existe). Confirme que chegou ao servidor.
>
> ⚠️ **1 TABELA NOVA:** `wp_sige_rh_ausencias` é criada **automaticamente por
> código** no primeiro acesso a uma página de administração do SIGE após o
> deploy — **não é preciso correr SQL**. (Requer que o utilizador abra o painel
> uma vez; a partir daí a tabela existe.)
>
> Não toca em ficheiros protegidos por hash.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/rh-ausencias.php          <-- NOVO
sige-softgenial/sige-softgenial.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-ausencias-v12-38-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/` (e, por segurança, da base de dados).
2. Extraia por cima, mantendo a estrutura. **Confirme** que
   `includes/rh-ausencias.php` ficou no servidor.
3. Abra o painel do SIGE uma vez (cria a tabela). **Limpe a cache** do navegador
   (Ctrl+Shift+R) — há alteração de CSS/JS.

## Verificação rápida
- Em **Equipa** (perfil de gestão), aparece a aba **"Ausências"**.
- **Registar ausência**: escolher colaborador, tipo, datas → o resumo mostra os
  dias úteis; Guardar → aparece na lista.
- **Aprovar/Rejeitar** um registo pendente; **Editar**; **Eliminar** (com
  confirmação). Filtros por ano/tipo/estado recarregam a lista.

## Rollback
- Reponha os ficheiros anteriores e remova `includes/rh-ausencias.php`. A tabela
  fica inerte (dados preservados; pode ser removida manualmente se desejado:
  `DROP TABLE wp_sige_rh_ausencias`).

## Fronteira
- Sem mexer em fórmulas, schema existente, permissões reais ou ficheiros
  protegidos. A tabela nova é isolada.
