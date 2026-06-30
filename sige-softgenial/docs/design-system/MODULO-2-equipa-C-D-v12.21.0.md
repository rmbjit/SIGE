# Módulo 2 - Equipa e Professores (RH) - Portões C/D (Atestado + Implementação)

Versão: 12.21.0
Data: 2026-06-30
Ficheiro: `admin/hr/equipe-view.php`
Decisão do utilizador: "escolhe a opção mais profissional, faz um prompt rigoroso
para ti, e trabalha." Opção escolhida: as 3 acções do Portão B (tokens + herói +
`onsubmit`), por ser a mais limpa e consistente com o resto do produto.

## Prompt rigoroso (auto-imposto)

> Trabalha SÓ na camada de apresentação do ecrã Equipa. Não toques em lógica,
> SQL, `$wpdb`, nonces, endpoints AJAX, fórmulas, `name`/`id` de campos,
> permissões, schema nem multi-inquilino. Corrige a dívida no estrato
> autoritativo (o `<style>` scoped e o HTML do herói), nunca por cima com mais
> `!important`. Verifica antes de assumir: confirma que cada token removido
> existe no sistema (`assets/sige-tokens.css`) ou tem destino; confirma que o
> handler de submit re-ligado pelo `sige-ui.js` chama `preventDefault`. Mede o
> gate de tokens antes e depois. Os crachás imprimíveis (`window.open`) ficam
> intactos (documentos autónomos). Auto-crítica adversarial no fim.

## Portão C - Atestado de fronteira

| Item | Decisão | Justificação verificada |
|---|---|---|
| `:root` sombreia tokens globais (`--shadow-*`, `--radius-*`) | Remover redefinições; herdar do sistema | `--shadow-xs/sm/md/lg` e `--radius-sm/md/lg/xl` existem em `sige-tokens.css`. `--shadow-xl` e `--shadow-glow` têm **0 usos** no ecrã. `--radius-full` (6 usos, sem equivalente global) passa a `var(--radius-pill)` |
| Fuga para o shell | Corrigida pela remoção acima | `:root` num `<style>` inline aplica-se à página toda; redefinir tokens contaminava o shell nesta rota. Remover elimina a fuga |
| Herói grande + ilustração | Compactar ao nível do Painel | Mesmo padrão da v12.19.5 (faixa única, `--fs-xl`, sem grelha 2-col, sem brilho, sem arte) |
| `onsubmit` inline (linha 2285) | Trocar por `data-sige-on-submit` | `sige-ui.js` (hidratarEventos, 598+) liga `submit` e executa `guardarStaff(event)` via `executarExpressaoLegada`; `parseArg('event')` devolve o `ev`; `guardarStaff` chama `e.preventDefault()`. Comportamento idêntico, sem handler inline na origem |
| Crachás imprimíveis | **Fora de âmbito** | Documentos `window.open` autónomos, mantêm pele própria |
| Lógica / dados / permissões / AJAX | **Não tocar** | Fronteira respeitada |

## Portão D - Implementação (resumo)

1. Bloco de tokens (linhas ~250-263): removidas as redefinições de sombras e
   raios; mantido só `--radius-full: var(--radius-pill)`.
2. Herói (CSS ~1451-1495): faixa única (`display:block`), `min-height` removido,
   `padding:var(--space-6) var(--space-8)`, `box-shadow:var(--shadow-md)`,
   título `--fs-xl`, removido o brilho `:before`, removidas as regras de arte
   (`.sg-hero-art`/`.sg-hero-*`/`.sg-school-*`) e a media query de 2 colunas.
3. Herói (HTML ~1937-1946): removido o bloco `.sg-hero-art`; subtítulo encurtado
   para uma linha.
4. Formulário (linha 2285): `onsubmit="guardarStaff(event)"` ->
   `data-sige-on-submit="guardarStaff(event)"`.

## Portão E - Anti-regressão

- `php -l` sem erros.
- Gate de tokens estável/abaixo da baseline (raios mágicos diminuíram).
- Tabela, KPIs, filtros, pesquisa, modal (4 separadores), confirmações, uploads,
  toggle/remover/reset, exportações e crachás: lógica intacta (nada tocado).
- CSS scoped a `.sige-rh`/`sige-view-equipe`; raios herdados deslocam cantos em
  2px (alinhamento ao sistema), sem reflow.

## Risco

Baixo. Só apresentação, scoped. O `onsubmit` passou a depender da hidratação já
existente e usada noutros ecrãs.
