# DEPLOY - SIGE SoftGenial v12.35.0

**RH (passo 2): aba de Relatórios (análise da equipa) na view=equipe**
Data: 2026-06-30 - Tipo: nova funcionalidade. Só leitura/agregação. Sem schema.

## O que muda
- **NOVO** `includes/rh-relatorios.php` — agregação isolada e testável.
  `sige_rh_build_reports(people, opts)` é uma **função pura** que recebe registos
  normalizados pela view e devolve distribuições (categoria, vínculo, regime,
  nível de carreira), admissões por ano, antiguidade média, massa salarial e
  qualidade/completude dos dados. Helpers de rótulo/percentagem. Sem schema, sem
  writes.
- `sige-softgenial.php` — carrega o novo include no bootstrap; versão -> 12.35.0.
- `admin/hr/equipe-view.php` — o conteúdo passa a ter duas **abas**: **"Equipa"**
  (gestão: KPIs, alertas, tabela, arquivo) e **"Relatórios"** (análise). A troca
  é sem recarregar (`sgRhSwitchTab`, CSP-safe, com ARIA). O loop de staff
  existente passa a montar também `$sige_rh_people` (mesma fonte das KPIs ->
  números reconciliam), sem 2.ª query. 100% design system (tokens; zero cores
  mágicas). A massa salarial é gated por `can_manage_equipe`.
- `BUILD.json` -> 12.35.0.

> ⚠️ **PASTA já existe, mas há 1 FICHEIRO NOVO:** `includes/rh-relatorios.php`.
> No seu pipeline (CloudPanel), confirme que o ficheiro novo chega ao servidor.
> A pasta `includes/` já existe.
>
> **Decisão de arquitectura:** a funcionalidade vive **dentro** da view já
> roteada (`view=equipe`), por isso **não toca** em `includes/admin-shell.php`
> (ficheiro protegido por hash) nem no mapa de rotas/permissões/menu.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/rh-relatorios.php          <-- NOVO
sige-softgenial/sige-softgenial.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-relatorios-v12-35-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. **Confirme** que
   `includes/rh-relatorios.php` ficou no servidor (é ficheiro novo).
3. Sem migração de dados. Limpe a cache do navegador (Ctrl+Shift+R).

## Verificação rápida
- Em **Equipa**, por baixo do cabeçalho, aparecem duas abas: **Equipa** e
  **Relatórios**.
- A aba **Equipa** mostra tudo o que já existia (KPIs, alertas, tabela).
- A aba **Relatórios** mostra os indicadores, as distribuições (barras), as
  admissões por ano (colunas), a massa salarial (só gestão) e a qualidade dos
  dados. Os números reconciliam com as KPIs.
- Trocar de aba não recarrega a página.

## Nota (dívida pré-existente, **não** causada por esta versão)
- O gate `tools/check-v12-16-2-baseline-preservation.php` acusa hashes
  desactualizados em `admin/system/dashboard-view.php` e
  `includes/admin-shell.php` — alterados de forma legítima em versões anteriores
  (v12.20.0, v12.29.2) sem reconciliar os hashes (fixados em v12.19.1). **Esta
  versão não toca nesses ficheiros.** Reconciliação a fazer como passo próprio.

## Rollback
- Reponha os ficheiros anteriores a partir do backup e remova
  `includes/rh-relatorios.php`. Sem efeitos colaterais (só leitura).

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
