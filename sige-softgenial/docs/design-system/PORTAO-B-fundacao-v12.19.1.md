# Portão B - Diagnóstico e plano da Fundação (Módulo 0)

Versão: 12.19.1
Data: 2026-06-29
Decisões fixadas no Portão A: marca canónica **roxo `#7c3aed`**; prefixo **`sgk-`**.
Modo: planeamento. Nenhuma alteração aplicada ainda.

---

## 1. Diagnóstico

A fundação de tokens (`sige-tokens.css`) já é roxa e é a fonte da verdade. A
incoerência de marca vive em dois sítios:

| Sítio | Problema | Natureza |
|---|---|---|
| `assets/sige-ui.css` (kit `.sgk-*`) | Crava navy `--sgk-primario:#0d1259` em vez de referir o token de marca | Fundação (in-scope) |
| 11 views legadas | Navy `#0d1259` literal inline (36 ocorrências) | Por módulo (out-of-scope da Fundação) |

Evidência de coerência da escolha roxa: 50 ficheiros já consomem `var(--brand)`
(roxo) via tokens; o navy é dívida legada, não a marca.

---

## 2. Âmbito deste incremento (Fundação)

Só o alinhamento do **kit** `sige-ui.css` à marca canónica. As views legadas com
navy literal ficam para os seus módulos respectivos (secção 6).

---

## 3. Antes → depois (edições propostas em `assets/sige-ui.css`)

| Token | Antes | Depois | Muda no ecrã? |
|---|---|---|---|
| `--sgk-primario` | `#0d1259` (navy) | `var(--brand)` (#7c3aed roxo) | **Sim**: botões/links primários do kit passam a roxo |
| `--sgk-primario-escuro` | `#090d40` | `var(--color-brand-700)` (#4c10b2) | **Sim**: hover/active primário passa a roxo escuro |
| `--sgk-primario-claro` | `#eef0fb` | `var(--bg-brand-soft)` (#f6f1fe) | Subtil: fundo claro de marca afina para roxo |
| `--sgk-foco` | `rgba(13,18,89,.25)` (navy) | `rgba(124,58,237,.25)` (roxo) | **Sim**: anel de foco passa a roxo |
| `--sgk-acento` | `#f59e0b` | `var(--color-warning-500)` (#f59e0b) | Não (match exacto) |
| `--sgk-ok` | `#16a34a` | `var(--color-success-500)` (#16a34a) | Não (match exacto) |

Os tokens de erro/info/aviso do kit ficam **inalterados**: os seus hex não têm
match exacto na paleta, e remapeá-los mudaria tons sem necessidade. O kit está na
lista de isentos do gate, por isso esses literais são legítimos.

Resultado: uma só mudança visual intencional e uniforme (navy → roxo) nos
componentes do kit, alinhando-os às views que já são roxas.

---

## 4. Raio de impacto

- Ficheiro alterado: **1** (`assets/sige-ui.css`).
- Superfícies repintadas: componentes `.sgk-*` (botões primários, anel de foco,
  fundo de marca) onde quer que apareçam. **17 ficheiros** usam classes `sgk-`.
- Cache-busting automático por `filemtime()` já tratado pelo `ui-kit.php`.

Nota de filosofia: isto é uma mudança de token partilhado, logo o raio não é "um
só ecrã". É deliberado: a unificação de marca é, por natureza, um acto de
fundação. Validar com a checklist do Portão E e um LIVE-TEST dedicado.

---

## 5. Conflitos potenciais e mitigação

| Conflito | Mitigação |
|---|---|
| Algum ecrã depender visualmente do contraste navy do kit | LIVE-TEST cobre os ecrãs com mais `.sgk-` antes de aprovar |
| Gate de design tokens | Edições só trocam hex por `var(--token)`; o total de valores mágicos **desce**, nunca sobe |
| CSP / ratchet de inline | Zero inline novo; só CSS enfileirado já existente. Harness CSP do repo valida |
| Fins de linha | Ficheiro em LF, fixado por `.gitattributes`; diff mínimo |
| Lógica/SQL/regras | Não tocadas. Confirmação formal no Portão C |

---

## 6. Fora de âmbito (backlog mapeado por módulo)

Navy `#0d1259` literal a migrar para `var(--brand)` quando cada módulo chegar:

| Ficheiro | Módulo |
|---|---|
| `admin/academic/presencas-view.php`, `assets/views/presencas.css` | Académico |
| `admin/finance/mpesa-view.php` | Tesouraria |
| `admin/whatsapp_circulares-view.php`, `admin/whatsapp_diag-view.php` | Comunicações |
| `admin/system/config-view.php` | Configurações |

---

## 7. Decisão pendente antes do Portão C/D

Aprovar (ou não) o repintar do kit de navy para roxo (secção 3). É a única
mudança visível deste incremento. Alternativa conservadora: aplicar só os
mapeamentos sem impacto visual (`--sgk-acento`, `--sgk-ok`) e adiar o repintar.
