# SIGE SoftGenial - Design System (fonte única da verdade)

Estabelecido a 13 Jun 2026 (v12.11.9.108). Resolve definitivamente a
fragmentação visual diagnosticada: 936 cores hex, 29 raios, 500 sombras
espalhados pelas views, sem fonte única que alguém seguisse.

## A fonte da verdade
`assets/sige-tokens.css` é o ÚNICO sítio onde valores visuais primitivos
são definidos. Carrega ANTES de tudo (dependência de toda a cascata).
Nenhuma view ou outro CSS deve inventar cores/raios/sombras: tudo se
refere via `var(--token)`.

### Paleta (7 famílias × escala 50-900)
Cada família ancorada (passo 500) na cor REAL mais usada do sistema, para
migração fiel. Escala de luminosidade coerente por matiz.
- `--color-brand-*`   roxo SIGE (#7c3aed)   - identidade
- `--color-ink-*`     tinta/texto (#202037)
- `--color-slate-*`   neutro (#64748b)
- `--color-success-*` verde (#16a34a)
- `--color-danger-*`  vermelho (#ef4444)
- `--color-warning-*` laranja (#f59e0b)
- `--color-info-*`    azul (#2563eb)         - links, estados informativos

Decisão de arquitectura: azul entrou como família (significado semântico
estável: info/links, padrão de toda a indústria). Verdes-água e roxos
dispersos foram UNIFICADOS nas famílias success/brand (eram ruído, não
significado). Critério: cor com significado estável fica; variação
acidental colapsa.

### Tokens semânticos (usar ESTES nos componentes)
`--text-primary/secondary/...`, `--bg-page/surface/...`,
`--border-subtle/default/...`, `--brand/-hover/-active`. Mudar a marca um
dia = mudar uma linha, não 900 sítios.

### Raios, sombras, espaçamento, tipografia, durações
Escalas finitas: `--radius-{xs..pill}`, `--shadow-{xs..lg}`,
`--space-{1..10}`, `--fs-{xs..xl}`, `--duration-{fast..slow}` + easings.

### Animações de entrada canónicas
`@keyframes sige-fade-in / sige-rise-in / sige-spin` + utilitárias
`.sige-anim-fade/-rise/-spin`. Uma definição partilhada (antes: cada view
tinha a sua).

## O guardião (não-regressão)
`tools/check-design-tokens.php` conta valores mágicos (hex literais +
raios soltos) fora da fonte da verdade e FALHA se subirem acima da
baseline (`tools/.design-tokens-baseline.json`). A migração só pode
REDUZIR; cada módulo migrado regista novo mínimo via `--set`.
- `--report`: lista os piores ficheiros (mapa da migração)
- baseline inicial: 12797 → após dashboard: 12615

É o 9º gate do corredor `tools/run-gates.php`.

## Como migrar uma view (processo)
1. `tools/check-design-tokens.php --report` → ver o peso da view;
2. mapear cada cor para o token perceptualmente mais próximo (script de
   migração calcula distância; desvios >=25 são unificações a rever);
3. converter SÓ cores em contexto CSS/style; cores em LÓGICA que
   alimentam custom properties também migram (var() resolve aninhado);
4. nunca tocar em cores que sejam dados de regra de negócio;
5. impressão digital de papéis preservada (sucesso continua verde, etc.);
6. `--set` regista o novo mínimo; invariantes selam o módulo.

## Estado da migração
| Módulo | hex mágicos antes | depois |
|---|---:|---:|
| dashboard (piloto) | 231 | 0 |

Reservatório restante (próximos): style.css (1544), style-consolidado
(1467), alunos_lista (1003), financeiro-pagamentos (471)...
