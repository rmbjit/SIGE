# Relatório de conformidade ao Design System (SIGE)

> Auditoria executada sobre o inventário real de views (`includes/admin-shell.php`
> `$map`, ~55 páginas). Referência de ouro: `admin/system/dashboard-view.php`.
> Método e critérios: ver `docs/dev/AUDITORIA-DESIGN-SYSTEM.md`.

## Como ler este relatório (metodologia e ressalvas)

- **Sinal primário de "fora do sistema" = cor mágica (`hex`)** — hex literal em vez
  de `var(--color-*)`/`--sg-theme-*`. É o indicador mais fiável.
- **`var()`** = densidade de adopção de tokens (quanto maior, mais conformado).
- **`shell`** = usa a casca do dashboard (`sg-dash-shell`/`sg-dashboard-v2`).
- **`style=` NÃO é, por si, violação:** o CSP zero-inline converte-o em
  `data-sige-style` em runtime; o próprio dashboard tem 11. Só conta como lacuna
  quando embrulha valores mágicos (o `hex` já os apanha).
- **Excluídos da migração de views:** `*/index.php` (stubs vazios) e os
  **templates de impressão** (`*-pdf-template.php`, `*-oficial-template.php`),
  que são documentos autónomos com regras próprias (tokens via `getComputedStyle`).
- Métrica oficial de regressão continua a ser os gates
  (`check-design-tokens` 1857, `check-consistencia-visual` 7142).

## Referência de ouro

| Página | hex | var() | shell |
|---|---:|---:|:--:|
| `admin/system/dashboard-view.php` | **0** | 213 | ✅ |

Vocabulário a replicar: `sg-dash-shell/hero/card/card-header/card-body/title`,
`sg-kpi-*`, `sg-v2-btn*`, `sg-hero-status-pill`, `sg-quick-*`, `sg-empty-note`.
Segunda referência viva (recém-migrada): `admin/hr/equipe-view.php` (shell + 1477 `var()`).

---

## 1. Scorecard por página (app views)

### Já conformadas / quase (usam a casca do dashboard)
| Página | hex | var() | shell | Nota |
|---|---:|---:|:--:|---|
| system/dashboard-view.php | 0 | 213 | ✅ | **Referência** |
| hr/equipe-view.php | 8 | 1477 | ✅ | Migrada; limpar 8 hex residuais |
| academic/encerramento-view.php | 0 | 240 | ✅ | Casca adoptada; afinar |

### FORA — prioridade P1 (muita cor mágica, baixo risco)
| Página | hex | var() | shell |
|---|---:|---:|:--:|
| finance/mpesa-view.php | **86** | 0 | ✗ |
| whatsapp_diag-view.php | **71** | 0 | ✗ |
| academic/alunos_lista.php | **70** | 1664 | ✗ |
| academic/turmas-view.php | **59** | 531 | ✗ |
| finance/financeiro-planos-view.php | **59** | 3 | ✗ |
| academic/boletim-view.php | **49** | 305 | ✗ |
| whatsapp_central-view.php | 32 | 328 | ✗ |
| academic/estatisticas-demograficas-view.php | 28 | 189 | ✗ |
| whatsapp_circulares-view.php | 23 | 0 | ✗ |
| finance/financeiro-config.php | 20 | 202 | ✗ |

### FORA — prioridade P2 (cor média; ou módulo coeso)
| Página | hex | var() | shell |
|---|---:|---:|:--:|
| academic/pauta-final-view.php | 15 | 150 | ✗ |
| academic/presencas-view.php | 15 | 0 | ✗ |
| system/config-center-view.php | 12 | 301 | ✗ |
| academic/dec-view.php | 12 | 131 | ✗ |
| academic/alocacao-view.php | 12 | 4 | ✗ |
| finance/financeiro-dashboard.php | 12 | 0 | ✗ |
| jardim/jardim_relatorio-view.php | 11 | 275 | ✗ |
| system/portaria-view.php | 9 | 244 | ✗ |
| finance/financeiro-relatorio-mensal-view.php | 9 | 0 | ✗ |
| finance/pagamentos-turma-view.php | 8 | 0 | ✗ |
| academic/pautas-view.php | 7 | 232 | ✗ |
| finance/financeiro-devedores-view.php | 7 | 367 | ✗ |
| system/config-view.php | 7 | 0 | ✗ |

### Módulo Jardim (coeso; migrar em bloco)
| Página | hex | var() | shell |
|---|---:|---:|:--:|
| jardim/jardim_relatorio-view.php | 11 | 275 | ✗ |
| jardim/jardim_diario-view.php | 4 | 288 | ✗ |
| jardim/jardim_presencas-view.php | 1 | 141 | ✗ |
| jardim/jardim_saude-view.php | 0 | 258 | ✗ |
| jardim/jardim_boletim-view.php | 0 | 97 | ✗ |

### PARCIAL — 0/poucos hex, tokenizadas, mas sem a casca (afinação estrutural)
| Página | hex | var() | shell |
|---|---:|---:|:--:|
| academic/disciplinas-view.php | 0 | 596 | ✗ |
| academic/matriz-view.php | 0 | 493 | ✗ |
| academic/notas-view.php | 0 | 296 | ✗ |
| academic/acta-view.php | 0 | 243 | ✗ |
| academic/abertura-view.php | 0 | 234 | ✗ |
| academic/auditoria_notas-view.php | 0 | 171 | ✗ |
| academic/aprovar_notas-view.php | 0 | 132 | ✗ |
| academic/minhas_turmas-view.php | 1 | 223 | ✗ |
| comunicacoes_central-view.php | 0 | 209 | ✗ |
| logistics/transporte-view.php | 0 | 199 | ✗ |
| system/permissions-ui.php | 0 | 248 | ✗ |
| system/curriculum-engine-view.php | 0 | 240 | ✗ |
| system/core-status-view.php | 0 | 146 | ✗ |
| academic/aluno-portal-view.php | 3 | 255 | ✗ |
| finance/financeiro-lancamentos-view.php | 0 | 101 | ✗ |
| finance/financeiro-inscricoes-view.php | 0 | 0 | ✗ |

### Casos especiais
| Página | hex | Observação |
|---|---:|---|
| **finance/financeiro-pagamentos.php** | 32 | **PROTEGIDA (hash)** — só com plano + re-baseline |
| **finance/financeiro-extratos.php** | 12 | **PROTEGIDA (hash)** — idem |
| academic/alunos_lista.php | 70 | Tem CSS-pro próprio (`alunos-design-pro.css`); **migração incompleta** |
| finance/* (devedores/reconciliacao/aprovacoes) | baixo | Renderizam via `financeiro-core-design-pro.css` (engine) |
| *-pdf-template / *-oficial-template | 19–69 | **Docs de impressão** — regras próprias (tokens via getComputedStyle) |

---

## 2. Lacunas dominantes (taxonomia)

- **L1 Cor mágica** — o problema mais frequente e visível. Concentra-se em
  finance (mpesa, planos, config), comunicações (whatsapp ×3) e académico
  (turmas, boletim, estatísticas). ~**700 hex** em app views.
- **L4 Estrutura** — quase nenhuma página (fora dashboard/equipe/encerramento)
  usa a casca `sg-dash-*`; falta hierarquia herói→KPIs→cartões.
- **L2 Primitivos soltos** — espaçamentos/raios/sombras em px literais dentro de
  `style=`/blocos `<style>` inline (o gate `check-consistencia-visual` mede).
- **L6 UX** — estados vazio/loading/erro e padrões de modal (ESC/foco) e feedback
  (`sigeUi`/`sigeConfirm`) inconsistentes entre módulos.
- **L3/L5/L7** — tipografia fora de escala, resíduos inline e responsivo
  irregular, em menor grau.

**Conclusão:** o sistema tem uma base sólida (tokens + gates + dashboard/equipe
como padrão), mas a maioria das views ainda não adoptou a **casca visual** e
mantém **cor mágica** — sobretudo Finance, Comunicações, Académico e Jardim.

---

## 3. Solução robusta proposta (faseada, sem regressões)

**Padrão canónico de migração por página** (o "como", idêntico ao usado na Equipa):
1. Ler a página inteira; mapear cada `hex` → `--color-*`/`--sg-theme-*` e cada px
   → `--space-*`/`--radius-*`/`--fs-*`/`--shadow-*`.
2. Extrair o CSS para `assets/views/<pagina>.css` (enqueue com dep `sige-ui-kit`
   e versão `SIGE_VERSION.filemtime`), seguindo os "pro" já existentes.
3. Adoptar a casca do dashboard (`sg-dash-shell/hero/card/kpi/v2-btn`),
   reutilizando classes; criar novas só no namespace `sg-`.
4. CSP: remover resíduos inline; `data-sige-style`/`data-sige-act`.
5. UX: estados vazio/loading/erro, modais ESC/foco, toasts/`sigeConfirm`.
6. Correr gates; baselines têm de **descer** (`--set` só para registar descida).

**Ordem de execução (impacto/risco):**
1. **P1 baixo risco:** `mpesa`, `whatsapp_diag`, `whatsapp_central`,
   `whatsapp_circulares`, `financeiro-planos`, `turmas`, `boletim`, `estatisticas`.
2. **P2 + módulo Jardim** em bloco (coeso).
3. **P3 (0 hex, sem casca):** académico volumoso — sobretudo estrutura/espaços.
4. **Finalizar `alunos_lista`** (grande, já tem CSS-pro).
5. **Protegidas** (`financeiro-pagamentos/extratos`): só com plano aprovado e
   re-baseline dos hashes — **não tocar sem autorização explícita**.
6. **Docs de impressão:** alinhar ao padrão getComputedStyle (não usam token CSS).

**Definition of Done (por página):** ver checklist na secção 6 de
`docs/dev/AUDITORIA-DESIGN-SYSTEM.md`. Resumo: 0 cor mágica nova; gates verdes com
baselines iguais/menores; casca coerente; CSP mantido; UX (vazio/loading/erro,
ESC/foco); responsivo <720px; `php -l` + `node --check` + smoke próprio; changelog
+ deploy + ZIP + push.

**Cada página = uma entrega** (código + `assets/views/<pagina>.css` + smoke
`tools/smoke-ds-<pagina>-vX.php`). Uma de cada vez, validada e empacotada.

---

## 4. Recomendação de arranque

Começar por **`finance/mpesa-view.php`** (86 hex, baixo risco, muito usada em
Moçambique) como caso-piloto que fixa o padrão de migração reutilizável, seguido
das 3 páginas de **WhatsApp** (mesma família visual, ganho rápido).
