# Guiao da Campanha de Remocao de !important - SIGE SoftGenial

Ordem recomendada para limpar os ~14 mil `!important` com o arnes de verificacao
visual, do modulo MAIS SEGURO ao MAIS SENSIVEL. A ideia: ganhar confianca e
ritmo com vitorias rapidas, e so depois atacar os modulos de maior risco e maior
raio de impacto, sempre com prova de pixel.

Regra de ouro: o arnes decide, nao a intuicao. Se `verify-strip` der VERMELHO,
nao se discute; reverte e tenta-se com ambito mais estreito.

## Como ler o risco

Cada modulo foi medido por: total de `!important` no bloco de view, quantos
batem em elementos genericos (que competem com o wp-admin) e quantos vivem em
`@media`. O risco nao e o numero de `!important` (o arnes verifica todos); o
risco e a probabilidade de o `verify-strip` vir VERMELHO e precisar de ambito.

| Sinal | Significado |
|---|---|
| Wrapper unico de pagina (`.sg-...`, `.sige-...-page`) | seguro: so aquele ecran usa |
| Estilo de tabela/formulario generico (`.sige-table`, `table`, `input`) | risco: compete com o wp-admin |
| Muitos `@media` | risco moderado: queries sobrepostas |
| Shell partilhada (`admin-shell`) | raio maximo: afecta TODAS as paginas |

## Operacao (vale para todos os modulos)

1. Sempre em STAGING (demo), nunca em producao.
2. Um modulo de cada vez:
   ```bash
   cd tools/arnes-visual
   node arnes.js verify-strip <modulo>
   ```
3. VERDE: a remocao fica. Traga o ficheiro validado para o pacote do plugin e
   baixe a baseline: `php tools/check-consistencia-visual.php --set`.
4. VERMELHO: o arnes ja reverteu. Abra `screens/diff__.../` para ver o que mexeu
   e repita com ambito progressivamente mais estreito (ver "Quando da vermelho").
5. Se houver opcache no servidor, garanta a limpeza entre o strip e a captura
   "depois" (campo `afterStripCmd` da config).

## Quando da vermelho: estreitar o ambito

O `--scope` limita a remocao as regras cujo selector contem a substring dada.
Estrategia de funil:
1. Tente primeiro sem ambito (`verify-strip equipe`).
2. Se vermelho, use o wrapper da pagina:
   `verify-strip equipe --scope=".sige-rh"`.
3. Se ainda vermelho, identifique no diff a zona afectada e estreite ao
   componente: `--scope=".sige-rh .sige-stat-card"`.
4. O que sobrar com `!important` legitimo (geralmente overrides de tabela/form
   do wp-admin) fica, e e correcto que fique.

## A ordem (5 ondas)

### Onda 1 - Provas rapidas (isolados, quase de certeza VERDE)
Comecar aqui valida o fluxo todo com risco minimo.

| Ordem | Modulo | !important | Nota |
|---|---|---|---|
| 1 | boletim | 13 | minusculo, scoped |
| 2 | config_center | 22 | scoped |
| 3 | portaria | 30 | maioria SVG (ja provados seguros) |
| 4 | whatsapp_central | 57 | scoped |

```bash
node arnes.js verify-strip boletim
node arnes.js verify-strip config_center
node arnes.js verify-strip portaria
node arnes.js verify-strip whatsapp_central
```

### Onda 2 - Medios bem isolados (bom retorno, baixo risco)

| Ordem | Modulo | !important | Wrapper |
|---|---|---|---|
| 5 | jardim_presencas | 254 | .sige-jpres-hero |
| 6 | notas | 363 | .sige-notas-page |
| 7 | turmas | 382 | .sige-turmas-page |
| 8 | financeiro-devedores | 400 | .sige-view-financeiro-devedores |

Tente sem ambito primeiro; se vermelho, use o wrapper da tabela.

### Onda 3 - Maiores mas scoped (volume, risco controlado)

| Ordem | Modulo | !important | Nota |
|---|---|---|---|
| 9 | disciplinas | 571 | scoped .sige-disciplinas |
| 10 | equipe | 613 | ja tokenizado; provavelmente precisa de `--scope=".sige-rh"` |
| 11 | matriz | 1342 | grande mas scoped .sige-matriz-page; tem grelha pesada |

Em matriz, se a tabela de matriz curricular acusar diff, isole-a:
`--scope=".sige-matriz-page .sige-matriz-tabela"` (ajuste ao selector real).

### Onda 4 - Sensiveis (estilo generico de tabela / volume alto)
Aqui o VERMELHO sem ambito e esperado. Ir directo ao funil de `--scope`.

| Ordem | Modulo | !important | Porque e sensivel |
|---|---|---|---|
| 12 | financeiro-extratos | 1068 | `.sige-table` reescreve tabelas (compete com wp-admin) |
| 13 | alunos_lista | 2868 | monstro; fatiar por componente |

Para alunos_lista, nao tente tudo de uma vez. Fatie por seccao:
```bash
node arnes.js verify-strip alunos_lista --scope=".sige-alunos-page .sige-aluno-card"
node arnes.js verify-strip alunos_lista --scope=".sige-alunos-page .sige-aluno-toolbar"
# ... um componente de cada vez; o que passar a VERDE fica.
```

### Onda 5 - A shell partilhada (raio maximo, por ultimo)

| Ordem | Modulo | !important | Cuidado especial |
|---|---|---|---|
| 14 | admin-shell (includes/admin-shell.php) | 587 | aparece em TODAS as paginas |

A shell e a moldura comum (menu lateral, topo, layout). Uma regressao aqui
afecta todos os ecrans. Por isso, ao testa-la, NAO basta um modulo: capture o
conjunto todo antes e depois.

```bash
node arnes.js capture shell-antes                 # todos os modulos
php strip-important.php ../../includes/admin-shell.php --apply --scope=".sige-admin-app"
node arnes.js capture shell-depois
node arnes.js diff shell-antes shell-depois        # tem de estar TUDO verde
# se algum modulo acusar diff:
php strip-important.php ../../includes/admin-shell.php --restore
```
Estreite o ambito as zonas que passarem (ex.: `--scope=".sige-menu-item"`,
`--scope=".sige-topbar"`) e deixe com `!important` o que reescreve o layout do
wp-admin (`#wpbody`, `.wrap`, `#adminmenu`), que e onde o `!important` e mesmo
necessario.

## Fecho de cada onda

Depois de cada modulo VERDE consolidado no plugin:
1. `php tools/check-consistencia-visual.php --set` (baixa a baseline de cascata).
2. `php tools/controlador-consistencia-visual.php` (ver o placar de cascata subir).
3. `php tools/run-gates.php` (confirmar 14/14).
4. Bump de versao + changelog na entrega do lote.

## Expectativa realista

As Ondas 1-3 (11 modulos) devem render a maioria dos `!important` "cobertor" sem
grande luta, porque sao custom-classes scoped. As Ondas 4-5 vao deixar um residuo
legitimo de `!important` (os que vencem mesmo o wp-admin), e esse residuo e para
ficar: marca o ponto onde `!important` deixa de ser divida e passa a ser
necessidade. O objectivo nao e zero absoluto; e zero EVITAVEL, provado a pixel.
