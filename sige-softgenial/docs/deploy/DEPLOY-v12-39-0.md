# DEPLOY - SIGE SoftGenial v12.39.0

**RH (passo 6): Assiduidade / Ponto — Fase 1 (tabela nova)**
Data: 2026-07-01 - Tipo: nova funcionalidade. Marcação diária por estado.

## O que muda
- **NOVO** `includes/rh-assiduidade.php` — módulo de Assiduidade:
  * tabela `{prefix}sige_rh_assiduidade` criada **por código** (idempotente),
    UNIQUE por (escola, colaborador, dia);
  * funções puras (dias úteis do mês; rótulos);
  * camada de dados tenant-scoped: grelha do dia, guardar em lote (não sobrepõe
    dias cobertos por **ausência aprovada**), resumo mensal (alimenta o salário);
  * AJAX gated por gestão (nonce, tenant-scope, auditoria). **Sem permissões
    novas.**
- `admin/hr/equipe-view.php` — nova aba **"Assiduidade"** (só gestão): seletor de
  dia, "marcar todos presentes", grelha com estado por colaborador (minutos no
  atraso), linhas bloqueadas "Em ausência", e "Guardar marcações" em lote.
- `sige-softgenial.php` / `BUILD.json` -> 12.39.0.

> ⚠️ **1 FICHEIRO NOVO** (`includes/rh-assiduidade.php`) e **1 TABELA NOVA**
> (`wp_sige_rh_assiduidade`), criada automaticamente por código no primeiro
> acesso admin após o deploy — **sem SQL manual**. Confirme que o ficheiro novo
> chegou ao servidor. Não toca em ficheiros protegidos.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/rh-assiduidade.php          <-- NOVO
sige-softgenial/sige-softgenial.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-assiduidade-v12-39-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/` (e da base de dados).
2. Extraia por cima. **Confirme** que `includes/rh-assiduidade.php` ficou no
   servidor. Abra o painel uma vez (cria a tabela). Limpe a cache (Ctrl+Shift+R).

## Verificação rápida
- Em **Equipa** (gestão) → aba **"Assiduidade"** → escolher um dia → marcar
  estados → **Guardar marcações**.
- Um colaborador com ausência aprovada nesse dia aparece bloqueado ("Em
  ausência — <tipo>").
- "Atraso" mostra o campo de minutos; "Marcar todos presentes" preenche a grelha.

## Rollback
- Reponha os ficheiros anteriores e remova `includes/rh-assiduidade.php`. A
  tabela fica inerte (dados preservados; `DROP TABLE wp_sige_rh_assiduidade` se
  desejado).

## Fronteira
- Sem mexer em fórmulas, schema existente, permissões reais ou ficheiros
  protegidos.
