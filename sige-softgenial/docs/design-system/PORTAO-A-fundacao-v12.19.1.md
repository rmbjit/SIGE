# Portão A - Inventário da Fundação (Módulo 0)

Versão do plugin inventariada: 12.19.1
Data: 2026-06-29
Modo: só leitura. Nenhuma alteração de lógica, SQL, regras ou apresentação.
Branch: `claude/sige-visual-modernization-frhd1h`

---

## 1. Conclusão de topo

A Fundação (Módulo 0) **já existe e está madura**, acima do que o Master Prompt
propunha. O plugin tem fonte única de verdade visual, kit de componentes com
namespace próprio, enfileiramento com cache-busting, sistema CSP zero-inline e um
gate automático que impede o regresso a valores mágicos.

Consequência: o Módulo 0 não precisa de ser criado de raiz. O trabalho da
Fundação passa a ser **alinhar e consolidar** o que já existe, não introduzir uma
camada nova. Várias propostas do Master Prompt colidem com a realidade do código
e precisam de decisão antes do Portão B (ver secção 7).

---

## 2. Ficheiros da fundação

| Ficheiro | Linhas | Papel |
|---|---|---|
| `assets/sige-tokens.css` | 250 | Fonte única da verdade: paleta primitiva + tokens semânticos + raios, sombras, espaços, tipografia, durações, z-index |
| `assets/sige-ui.css` | 193 | UI Kit v1, namespace `.sgk-*`: botões, banners, cartões, badges, estado vazio, modal, toasts, spinner |
| `assets/sige-ui.js` | (externo) | Hidratação de `data-sige-style` / `data-sige-on-*`, modais e toasts |
| `assets/style.css` | 4668 | Folha legada principal (ainda com muitos valores literais por migrar) |
| `assets/sige-shell-stability.css` | - | Estabilidade do shell admin |
| `assets/sige-utilities.css` | 24 | Utilitárias |
| `tools/check-design-tokens.php` | - | 9º gate: conta valores mágicos fora dos ficheiros isentos e falha se subir da baseline |
| `tools/.design-tokens-baseline.json` | - | Baseline actual do gate |

---

## 3. Enfileiramento (como o CSS chega ao ecrã)

Centralizado em `includes/ui-kit.php` e `includes/admin-shell.php`. Tudo por
folha externa via `wp_enqueue_style`, com cache-busting por
`SIGE_VERSION . '.' . filemtime($ficheiro)`. Cadeia de dependências:

| Handle | Origem | Depende de |
|---|---|---|
| `sige-tokens` | `assets/sige-tokens.css` | (nada) |
| `sige-design-system` | handle interno (admin-shell) | `sige-tokens` |
| `sige-ui-kit` | `assets/sige-ui.css` | `sige-tokens`, `sige-design-system` |
| `sige-shell-stability` | `assets/sige-shell-stability.css` | `sige-ui-kit` |
| CSS por view (devedores, reconciliacao, aprovacoes, privacidade, ...) | `assets/views/*.css` | `sige-ui-kit` |

Implicação para a modernização: qualquer folha nova deve entrar nesta cadeia,
declarando `sige-tokens` como dependência, e nunca por `<style>` inline.

---

## 4. CSP e ratchet de inline

`includes/csp-zero-inline.php` (v12.14.1) é o guardião do shell autenticado:

- Remove `permissao-inline` da política.
- Converte `style=` em `data-sige-style` e `on*=` em `data-sige-on-*` antes da resposta.
- Aplica nonce defensivo a tags `script`/`style` legadas.
- Hidratação feita por `assets/sige-ui.js` (ficheiro externo).

Política activa (resumida): `script-src 'self' 'nonce-...'; script-src-attr 'none';
style-src 'self' 'nonce-...'; style-src-attr 'none'; default-src 'self'`.

Implicação: a via de entrega tem de ser folha externa enfileirada. Inline com
nonce é tecnicamente possível mas contraria o ratchet. **Decisão recomendada:
folha externa, sempre.** Bate certo com o harness de auditoria CSP do repo
(`runtime-harness/`), que valida exactamente `script-src-attr 'none'`, nonces
correctos e zero violações em 60 vistas.

---

## 5. Tokens existentes (dois sistemas coexistentes)

### 5.1 `sige-tokens.css` - marca ROXA

Paleta primitiva por família (50 a 900) e tokens semânticos por papel.

| Token | Valor | Papel |
|---|---|---|
| `--color-brand-500` | `#7c3aed` | Marca (roxo SIGE) |
| `--color-ink-500` | `#202037` | Texto primário |
| `--color-warning-500` | `#f59e0b` | Aviso (laranja/âmbar) |
| `--text-primary` | `var(--color-ink-500)` | Texto |
| `--bg-page` | `var(--color-slate-50)` | Fundo de página |
| `--radius-md` | `12px` | Raio de cartão |
| `--shadow-xs` | `0 1px 2px rgba(15,23,42,.04)` | Elevação base |

Pesos definidos: 400, 500 e **700** (`--fw-bold: 700`).

### 5.2 `sige-ui.css` - kit `.sgk-*` com marca NAVY

| Token | Valor | Papel |
|---|---|---|
| `--sgk-primario` | `#0d1259` | Primário (navy) |
| `--sgk-acento` | `#f59e0b` | Acento (âmbar) |
| `--sgk-ok` / `--sgk-erro` | `#16a34a` / `#b91c1c` | Estados |
| `--sgk-radius` | `10px` | Raio do kit |

Os botões `.sgk-btn` usam `font-weight: 600`.

---

## 6. Fontes de versão

| Fonte esperada (Master Prompt) | Estado real |
|---|---|
| Cabeçalho do plugin (`Version:`) | `12.19.1` (existe) |
| Constante `SIGE_VERSION` | `12.19.1` (existe) |
| `BUILD.json` | `12.19.1` (existe) |
| `SIGE_DEMO_MAIN_VERSION` em `demo-loader.php` | **NÃO EXISTE** neste plugin |

São **3** fontes de versão, não 4. Não há `demo-loader.php` nem
`SIGE_DEMO_MAIN_VERSION` em nenhum ficheiro do plugin.

---

## 7. Achados críticos / discrepâncias com o Master Prompt

| # | Tema | Proposta do Master Prompt | Realidade no código | Impacto |
|---|---|---|---|---|
| D1 | Prefixo `sg-` | Introduzir camada nova com prefixo `sg-` | `sg-` **já é usado**: tokens `--sg-*` em CSS de views, classe `.sg-svg-icon`, e `sg-` em 61 ficheiros admin/includes. O kit usa `.sgk-*` (17 ficheiros) | Prefixo `sg-` não está livre. Reusar o existente ou escolher outro |
| D2 | Cores da marca | `--sg-navy #1A2742`, `--sg-amber #E8A33D` | Marca real é **roxa** `#7c3aed` (`sige-tokens.css`). O navy/âmbar existe mas no kit: `--sgk-primario #0d1259`, `--sgk-acento #f59e0b`. Hexes não coincidem com os do prompt | A direcção navy/âmbar contradiz a marca roxa em uso. Decidir qual é a pele canónica |
| D3 | Dois sistemas de cor | (não previsto) | `sige-tokens.css` (roxo) e `sige-ui.css` (navy `sgk-`) coexistem com primários diferentes | Inconsistência de marca a resolver antes de propagar |
| D4 | Fundação a criar | Criar `sg-tokens.css` + componentes base | Já existem `sige-tokens.css` + kit `sgk-` + gate. Criar de novo seria duplicação e regressão | Módulo 0 é consolidação, não criação |
| D5 | Pesos tipográficos | Só 400 e 500, nunca 600/700 | Tokens definem `--fw-bold: 700`; `.sgk-btn` usa 600 | A regra do prompt já é violada pela própria fundação. Decidir |
| D6 | 4 fontes de versão | Incluir `demo-loader.php` | Só existem 3 fontes; sem demo-loader | Portão F passa a sincronizar 3 fontes |
| D7 | Gate de tokens | (não previsto) | `check-design-tokens.php` falha se valores mágicos subirem da baseline (`total: 1934`) | Toda a CSS nova tem de usar `var(--token)`; nada de hex literais |

---

## 8. Restrições herdadas (a respeitar em todos os incrementos)

- Folha externa enfileirada, nunca inline. Respeitar CSP zero-inline e o ratchet.
- Sem hex literais nem raios soltos fora dos ficheiros isentos: o gate falha.
- Cache-busting por `SIGE_VERSION . '.' . filemtime()` (padrão já em uso).
- Fins de linha: baseline importada em LF, fixada por `.gitattributes`.
- Não tocar em `name`/`id` de campos, hooks, SQL nem regras aprovadas.

---

## 9. Recomendação para o Portão B (Diagnóstico e plano)

1. Resolver a identidade de marca (D2/D3): **roxo `#7c3aed`** dos tokens ou
   **navy `#0d1259`** do kit. Sem isto, não há "pele" canónica para propagar.
2. Definir a estratégia de prefixo (D1): adoptar `sgk-` (o namespace de kit já
   existente e isolado) em vez de criar `sg-` novo, evitando colisão.
3. Tratar o Módulo 0 como **consolidação**: unificar os dois sistemas de tokens
   numa só fonte, sem alterar valores renderizados de nenhuma view já migrada.
4. Confirmar a regra de pesos (D5) contra o que a fundação já usa.
5. Aceitar 3 fontes de versão (D6) e manter o gate de tokens como rede (D7).

Nenhuma destas acções foi executada. O Portão A termina aqui, só com leitura e
este registo.
