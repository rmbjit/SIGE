# DEPLOY - SIGE SoftGenial v12.38.1

**RH: Férias & Ausências — Fase 2 (resumo na Ficha e nos Relatórios)**
Data: 2026-07-01 - Tipo: nova funcionalidade (leitura/agregação). Sem schema novo.

## O que muda
- `includes/rh-ausencias.php` — novas funções de **resumo** (só leitura, só
  ausências aprovadas): por colaborador (`sige_rh_ausencia_resumo_professor`) e
  por escola (`sige_rh_ausencia_resumo_escola`).
- `includes/ajax-handlers.php` — o endpoint da Ficha (`sige_get_staff_secure`)
  devolve, de forma **aditiva**, `ausencias` (resumo anual do colaborador).
- `admin/hr/equipe-view.php` —
  * **Ficha**: nova secção **"Ausências em <ano>"** (banner de "ausente hoje",
    dias por tipo, total);
  * **Relatórios**: novo cartão **"Ausências em <ano>"** ("Ausentes hoje" +
    barras de dias por tipo).
- `sige-softgenial.php` / `BUILD.json` -> 12.38.1.

> ✅ **Sem ficheiros novos e sem tabela nova** (usa a tabela da Fase 1). Só altera
> 3 ficheiros existentes. Não toca em ficheiros protegidos por hash.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/rh-ausencias.php
sige-softgenial/includes/ajax-handlers.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-ausencias-fase2-v12-38-1.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. Sem migração de dados.
   **Limpe a cache** do navegador (Ctrl+Shift+R) — há alteração de CSS/JS.

## Verificação rápida
- **Ficha**: em Equipa → "Ver ficha" de alguém com ausências aprovadas → surge a
  secção "Ausências em <ano>" (e o aviso "ausente hoje" quando aplicável).
- **Relatórios**: na aba Relatórios, o cartão "Ausências" mostra quem está
  ausente hoje e as barras de dias por tipo no ano.

## Rollback
- Reponha os ficheiros anteriores a partir do backup. Sem efeitos colaterais.

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
