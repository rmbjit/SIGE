# Prompt de trabalho — Auditoria e conformação ao Design System (SIGE)

> **Objectivo:** encontrar TODAS as páginas fora do design system, medir as
> lacunas com rigor (não de memória) e conformá-las ao padrão do sistema —
> tendo o **dashboard inicial** como referência de ouro — sem regressões, uma
> página de cada vez, com validação por gates e smokes.

---

## 0. Regras de ouro (contexto inegociável deste sistema)

1. **Fonte única da verdade visual:** `assets/sige-tokens.css`. Nenhuma view ou
   CSS pode inventar cor/raio/sombra/espaço/tipografia — tudo via `var(--token)`.
2. **Verificar, nunca assumir.** Antes de mexer, ler a página e correr os gates.
   Depois de mexer, correr os gates outra vez. Nada entra sem prova.
3. **Os gates só descem.** As baselines de valores mágicos representam dívida; a
   conformação tem de as **reduzir**, nunca aumentar. Se um número sobe, é
   regressão — corrigir antes de continuar.
4. **CSP zero-inline.** Nada de `style=` inline (vai para `data-sige-style`,
   hidratado por `assets/sige-ui.js`) nem `onclick=` (vai para `data-sige-act` /
   `data-sige-on-*`). Scripts inline só com nonce (`sige_csp_script_attr()`).
5. **Namespaces.** Kit em `sgk-`; a "casa" (views) em `sg-` / `sige-`. Sem
   colisões (gate `check-css-collisions`).
6. **Uma página de cada vez.** Cada entrega toca 1 página (ou 1 módulo coeso),
   com smoke próprio, gates verdes, changelog, deploy doc e ZIP. Código limpo e
   profissional — sem remendos.
7. **Fronteiras.** NÃO tocar em ficheiros protegidos por hash
   (`tools/check-v12-16-2-baseline-preservation.php` lista-os: finance-core,
   financeiro-pagamentos, financeiro-extratos, dashboard-view, permissions-layer,
   security-kernel-rules, admin-shell). Se um deles precisar de conformação
   visual, parar e propor plano antes de agir.

---

## 1. Referência de ouro — o dashboard

Ficheiro: `admin/system/dashboard-view.php`. Métrica actual: **0 hex**, ~213
`var(--token)`. É o padrão a replicar. Vocabulário canónico a reutilizar:

| Padrão | Classe(s) |
|---|---|
| Casca da página | `sg-dashboard-v2` → `sg-dash-shell` |
| Herói / cabeçalho | `sg-dash-hero`, `sg-hero-copy`, `sg-hero-status-pill` |
| Cartões | `sg-dash-card`, `sg-dash-card-header`, `sg-dash-card-body` |
| Título de secção | `sg-dash-title`, `sg-dash-title-icon` |
| KPIs | `sg-kpi-card`, `sg-kpi-value`, `sg-kpi-label`, `sg-kpi-note`, `sg-kpi-icon` |
| Botões | `sg-v2-btn`, `sg-v2-btn-primary`, `sg-v2-btn-secondary` |
| Acessos rápidos | `sg-quick-v2`, `sg-quick-ico`, `sg-quick-label` |
| Valores | `sg-val-pos`, `sg-val-neg`, `sg-val-hi` |
| Vazios | `sg-empty-note` |

> A aba **Equipa** (`admin/hr/equipe-view.php`) já foi conformada a este padrão
> (`sg-dashboard-v2`/`sg-dash-shell`/`sg-dash-hero`) — usar como 2.ª referência
> viva de "como fica bem feito".

**Princípios de UI/UX a preservar (o "porquê" do padrão):**
hierarquia clara (herói → KPIs → cartões), densidade confortável, uma só escala
tipográfica (tokens `--fs-*`), estados semânticos por cor-token (sucesso/perigo/
aviso), foco/ESC/teclado nos modais, feedback não-bloqueante (toasts), e tema da
escola respeitado via `--sg-theme-*`.

---

## 2. Fase 0 — Verificar o estado real (executar e registar)

```bash
# 2.1 Baselines actuais dos gates de conformidade visual
php tools/check-design-tokens.php            # baseline de valores mágicos (hex + raios)
php tools/check-consistencia-visual.php       # baseline de primitivos (fontsize/spacing/shadow/…)
php tools/check-typography.php                 # hierarquia de pesos
php tools/check-tokens-fora-de-contexto.php    # contextos sem tokens
php tools/check-css-collisions.php             # namespaces

# 2.2 Ranking de páginas por COR MÁGICA (sinal mais forte de "fora do sistema")
for f in $(find admin -name '*.php'); do
  h=$(grep -oiE '#[0-9a-f]{3,8}\b' "$f" | wc -l)
  [ "$h" -gt 0 ] && printf "%4d hex  %s\n" "$h" "$f"
done | sort -rn

# 2.3 Ranking por primitivos px (excluir SVG viewBox/stroke ao avaliar manualmente)
for f in $(find admin -name '*.php'); do
  p=$(grep -oiE '[0-9]+px' "$f" | wc -l)
  [ "$p" -gt 0 ] && printf "%4d px   %s\n" "$p" "$f"
done | sort -rn
```

> **Nota metodológica:** `px` inclui `viewBox`/`stroke-width` de SVG (legítimos).
> Por isso a **cor mágica (hex)** é o indicador primário de não-conformidade; o
> gate `check-consistencia-visual` já separa categorias e é a métrica oficial.

### Diagnóstico-semente (medido nesta data — confirmar antes de agir)

| Prioridade | Página | Sinal |
|---|---|---|
| P1 (cor + volume) | `admin/finance/mpesa-view.php` | 86 hex |
| P1 | `admin/whatsapp_diag-view.php` | 71 hex |
| P1 | `admin/academic/alunos_lista.php` | 70 hex + 1733 px (tem CSS-pro próprio; migração incompleta) |
| P1 | `admin/academic/turmas-view.php` | 59 hex |
| P1 | `admin/finance/financeiro-planos-view.php` | 59 hex |
| P2 | `admin/academic/boletim-view.php` | 49 hex |
| P2 | `admin/whatsapp_central-view.php` | 32 hex |
| P2 | `admin/finance/financeiro-config.php` | 20 hex |
| P2 | `admin/academic/estatisticas-demograficas-view.php` | 28 hex |
| P3 (módulo Jardim, sem migração) | `jardim_relatorio/diario/saude/boletim/presencas` | muito px, layout antigo |
| P3 (académico volumoso) | `matriz`, `disciplinas`, `notas`, `acta`, `encerramento`, `abertura` | px alto, 0 hex (só estrutura) |

> **Inventário completo de páginas:** ver o `$map` em
> `includes/admin-shell.php` (~55 views). A auditoria tem de cobrir TODAS,
> mesmo as com 0 hex (podem ter espaçamentos/tipografia fora de token).

---

## 3. Fase 1 — Auditoria por página (ficha padronizada)

Para **cada** página, produzir uma ficha curta:

```
PÁGINA: admin/<módulo>/<ficheiro>.php
- Casca: usa sg-dash-shell/hero/card? (S/N) — se N, qual o layout actual
- Cores mágicas: N hex → quais famílias (mapear p/ --color-*)
- Espaços/raios/sombras fora de token: amostras
- Tipografia: pesos/tamanhos fora de --fs-*/--fw-*
- CSP: tem style= inline? onclick=? script sem nonce?
- Estados/UX: vazios, loading, erros, foco/ESC nos modais — conformes?
- Responsivo: quebra em <720px? bottom-nav?
- Risco: ficheiro protegido? lógica financeira/schema por perto?
- Esforço: S/M/L
```

### Taxonomia das lacunas (classificar cada achado)
- **L1 — Cor mágica:** hex literal em vez de `var(--color-*)` / `--sg-theme-*`.
- **L2 — Primitivo solto:** px/rem/raio/sombra/duração fora de token.
- **L3 — Tipografia:** tamanho/peso fora da escala.
- **L4 — Estrutura:** não usa a casca do dashboard (herói/cartões/KPIs).
- **L5 — CSP:** `style=`/`onclick=` inline, script sem nonce.
- **L6 — UX:** falta estado vazio/loading/erro, modal sem ESC/foco, feedback
  bloqueante (alert/confirm nativos em vez de `sigeUi`/`sigeConfirm`).
- **L7 — Responsivo/acessibilidade:** sem breakpoints, contraste, `aria-*`.

---

## 4. Fase 2 — Padrão de remediação robusto (o "como")

1. **Ler a página inteira primeiro.** Perceber a intenção antes de mexer.
2. **Extrair o CSS para ficheiro de view** em `assets/views/<pagina>.css`,
   enfileirado com dependência de `sige-ui-kit` (ver `includes/ui-kit.php` →
   secção de enqueue por view) e versão `SIGE_VERSION . '.' . filemtime()`.
   Seguir os "pro" já existentes (`alunos-design-pro.css`,
   `financeiro-core-design-pro.css`, `presencas.css`, etc.).
3. **Substituir valores mágicos por tokens:** cada hex → o `--color-*` mais
   próximo da paleta (ou `--sg-theme-*` quando é a cor da marca da escola);
   cada px → `--space-*`/`--radius-*`/`--fs-*`; sombras → `--shadow-*`.
4. **Adoptar a casca do dashboard:** `sg-dash-shell` + `sg-dash-hero` +
   `sg-dash-card`… reutilizando classes existentes; criar novas só quando não
   houver equivalente, sempre no namespace `sg-`.
5. **CSP:** remover `style=`/`onclick=`; passar a `data-sige-style` /
   `data-sige-act`/`data-sige-on-*`; scripts inline com `sige_csp_script_attr()`.
   Confirmar em `check-inline-frontend`.
6. **UX:** estados vazios (`sg-empty-note`), loading, erros; modais com ESC/foco;
   toasts (`sigeUi.toast`) e `sigeConfirm` em vez de `alert/confirm`.
7. **Não inventar dívida nova.** Depois da migração, os totais dos gates têm de
   **descer**; correr `check-consistencia-visual --set` **só** para registar a
   descida (nunca uma subida).

> **Anti-regressão de interacção (lição do módulo Equipa):** se a página for
> grande e depender do despachante `data-sige-act` do rodapé, garantir que a
> interacção não fica "muda" durante o carregamento (ver padrão do despachante
> inline autossuficiente já aplicado em `equipe-view.php`).

---

## 5. Fase 3 — Execução incremental (ordem sugerida)

Ordenar por **impacto/risco**: primeiro páginas muito usadas e com muita cor
mágica, mas **fora** dos ficheiros protegidos.

1. `mpesa-view.php`, `whatsapp_diag-view.php`, `whatsapp_central-view.php`
   (muita cor, baixo risco).
2. `turmas-view.php`, `financeiro-planos-view.php`, `boletim-view.php`,
   `estatisticas-demograficas-view.php`, `financeiro-config.php`.
3. Módulo **Jardim** completo (`jardim_*`) — coeso, actualmente o mais antigo.
4. Académico volumoso (`matriz`, `disciplinas`, `notas`, `acta`, `abertura`,
   `encerramento`) — sobretudo estrutura/espaços.
5. `alunos_lista.php` — grande e já tem CSS-pro; **acabar** a migração.
6. Páginas protegidas (`financeiro-pagamentos/extratos`, `dashboard`): **só com
   plano aprovado e re-baseline de hash** (não tocar sem autorização explícita).

Cada passo = 1 entrega: código + `assets/views/<pagina>.css` + smoke
`tools/smoke-ds-<pagina>-vX.php` + gates verdes + changelog + deploy + ZIP +
commit/push no branch designado.

---

## 6. Definition of Done (por página)

- [ ] `0` cores mágicas novas; hex existentes → tokens (ou justificado no smoke).
- [ ] `check-design-tokens`, `check-consistencia-visual`, `check-typography`,
      `check-tokens-fora-de-contexto`, `check-css-collisions`, `check-inline-frontend`
      **verdes**, com baselines **iguais ou menores**.
- [ ] Casca visual coerente com o dashboard (herói/cartões/KPIs/botões).
- [ ] CSP zero-inline mantido; interacção responde ao 1.º clique.
- [ ] Estados vazio/loading/erro e modais (ESC/foco) presentes.
- [ ] Responsivo <720px validado; sem colisões de namespace.
- [ ] `php -l` + `node --check` (JS extraído) OK; smoke próprio a passar.
- [ ] Changelog + deploy doc + ZIP; commit e push no branch designado.

---

## 7. Entregável final da auditoria

Um **relatório** (`docs/dev/RELATORIO-DESIGN-SYSTEM.md`) com: (a) tabela de todas
as ~55 páginas × estado (conforme / lacunas L1–L7 / esforço); (b) plano de
migração faseado com ordem e risco; (c) baseline "antes" dos gates e alvo "depois".
A partir daí, executar página a página segundo as Fases 2–3.
