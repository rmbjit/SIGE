# Módulo 1 - Painel Principal (Gestão) - Portões A/B/C

Versão: 12.19.3
Data: 2026-06-29
Ficheiro do módulo: `admin/system/dashboard-view.php` (1051 linhas)
Decisões herdadas: marca roxa `#7c3aed`; prefixo `sgk-`; CSS por folha; só apresentação.

---

## Portão A - Inventário

O Painel Principal ("Dashboard V2 MJS-grade") já está modernizado a alto nível:

| Dimensão | Estado |
|---|---|
| Marca | Roxa, via `var(--color-brand-*)` (217 usos de `var(--token)`, zero hex cravado) |
| Layout | Hero + KPIs + grelha de cartões; responsivo (5 breakpoints) |
| Gráficos | Com legenda/eixo: barras Jan..mês, donut com centro, barras por classe |
| Números | Consistentes: helper `dash_fmt` em K/M MT |
| Estados vazios | Presentes ("Sem distribuição disponível", "Sem atalhos disponíveis") |
| Acessibilidade | `aria-label` nas secções e itens |
| Entrega CSS | Bloco `<style>` inline (com nonce pela camada CSP zero-inline) |

A maioria dos problemas da secção 4.4 do Master Prompt já está resolvida aqui.

---

## Portão B - Diagnóstico (o que sobra, real e seguro)

| # | Problema | Localização | Antes → Depois |
|---|---|---|---|
| 1 | Diacrítico em falta | linha 468 | "permissoes" → "permissões" |
| 2 | Rótulo truncado | `.sg-focus-copy small` (CSS inline) | `nowrap`+ellipsis → quebra em 2 linhas (`-webkit-line-clamp:2`). Mostra "documentos e encarregados" por inteiro |
| 3 | Comentário desatualizado | linha 9 | "Design System sg-* (Navy/Amber)" → "tokens, marca roxa" |

### Fora de âmbito (oportunidades futuras, não aplicadas)

| Oportunidade | Porquê adiar |
|---|---|
| Externalizar o `<style>` inline (~298 linhas) para folha enfileirada | Maior risco; exige paridade visual exacta e validação CSP. Vale como incremento próprio |
| Redesenhar a ilustração do herói / encurtar o herói | Subjectivo; depende de validação visual de Rogério |

---

## Portão C - Atestado de fronteira

Neste incremento NÃO se alterou: lógica PHP, consultas SQL, `$wpdb->prepare`,
fórmulas financeiras/académicas, `name`/`id` de campos, hooks, permissões,
schema, multi-tenant, nem a política CSP. Sem `onclick`/inline novo. Apenas
texto visível e CSS de apresentação.

---

## Portão E - Anti-regressão (resultado)

- `php -l`: sem erros.
- Gate de design tokens: 1934/1934 (sem regressão; nenhum hex/raio novo).
- Diff mínimo: 3 hunks num só ficheiro.
- Fins de linha: LF preservado.
- Nenhum número renderizado alterado.

## Portões F/G

- Versão 12.19.3 sincronizada (cabeçalho, `SIGE_VERSION`, `BUILD.json`).
- DEPLOY: `docs/deploy/DEPLOY-v12.19.3-painel-principal.md`
- LIVE-TEST: `docs/qa/LIVE-TEST-painel-principal-v12.19.3.md`
- Changelog: `docs/changelog/CHANGELOG-v12-19-3.txt`
