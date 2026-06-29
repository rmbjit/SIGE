# Diagnostico 360 da Consistencia Visual - SIGE SoftGenial v12.11.9.148

Data: 14 de Junho de 2026
Ambito: todo o plugin (204 ficheiros PHP/CSS varridos), excluindo `tools/` e `docs/`.

---

## 1. Veredicto em uma linha

A **infra-estrutura** de design esta excelente e blindada (fonte unica da verdade, ponte de compatibilidade, 14 gates verdes). O problema nao e de arquitectura: e de **adopcao**. O vocabulario de tokens existe mas, fora da cor, quase ninguem o usa. O sistema tem o dicionario certo na gaveta e continua a escrever a mao.

---

## 2. O que ja esta solido (nao mexer, so manter)

- **Fonte da verdade** (`assets/sige-tokens.css`): 7 familias de cor completas (50-900), escala de raios, sombras, espacamento (4px), tipografia, duracoes, easings e z-index. Bem desenhada.
- **Ponte de compatibilidade** `--sg-* -> --color-*` no `style.css`: zero variaveis orfas, dourado unificado em warning, `style.css` com **zero** cor hex magica.
- **Tipografia (pesos)**: 0 pesos nao-canonicos. A limpeza dos 17 pesos fantasma (650, 720, 850, 950...) foi feita e esta gated. Hierarquia limpa nos 36 ecrans auditados.
- **Icones**: 34 SVG, todos 24x24, `fill=none`, `stroke=currentColor`, normalizados opticamente. Consistencia exemplar.
- **Cascata**: tokens carregam antes do kit e das views. Ordem correcta.
- **Colisoes CSS** e **responsivo**: ambos a zero antipadroes.

---

## 3. O que esta inconsistente (a divida, medida)

A cor foi migrada. **Todo o resto continua literal.** Comparacao entre o token disponivel e o seu uso real:

| Dimensao | Token disponivel | Usos do token | Valores literais | Adopcao |
|---|---|---|---|---|
| Tipografia | `--fs-*` | **0** | 2478 font-size px | 0,0% |
| Espacamento | `--space-*` | 8 | 4773 padding/margin/gap px | 0,2% |
| Sombra | `--shadow-*` | 13 | 1122 box-shadow literais | 1,1% |
| Raio | `--radius-*` | 42 | 2084 border-radius px | 2,0% |
| Camadas | `--z-*` | 8 | 93 z-index literais | 7,9% |
| Movimento | `--duration-*` | 31 | 177 transition literais | 14,9% |
| Cor (referencia) | `--color-*` | muitos | 1927 hex restantes (em views) | avancado |

### Sinais estruturais

- **`!important`: 14 241 ocorrencias.** Este e o sintoma mais grave. Um ficheiro (`admin/academic/alunos_lista.php`) sozinho tem **2173**. `!important` em massa significa que estilos inline e blocos `<style>` embebidos estao a lutar contra a cascata em vez de a usar. E a causa-raiz de quase toda a inconsistencia: quando a especificidade falha, a solucao tem sido martelar.
- **2244 atributos `style="..."` inline** espalhados pelas views. Estilo inline e impossivel de tematizar e de manter coerente.
- **55 views injectam blocos `<style>` embebidos.** Cada uma reinventa o seu visual local.
- **51 `@keyframes` nao-canonicos** (69 definicoes), com `fadeInUp` definido 7x, `spin` 6x, `modalSlideIn` 3x, `pulse` 3x. Ja existem `sige-fade-in`, `sige-rise-in` e `sige-spin` canonicos para os substituir.
- **102 nomes de cor CSS** (`white`, `black`, `red`...) fora de token.

### Os 6 ficheiros que concentram a divida

1. `assets/style.css` (1198 primitivos: 368 fs, 651 space, 146 shadow)
2. `admin/academic/alunos_lista.php` (955 primitivos + 2173 !important)
3. `admin/finance/financeiro-pagamentos.php` (613, com 290 inline)
4. `admin/finance/financeiro-extratos.php` (374)
5. `admin/jardim/jardim_relatorio-view.php` (357, com 139 inline)
6. `admin/academic/turmas-view.php` (306)

Resolver estes 6 elimina perto de metade da divida total.

---

## 4. O que devemos melhorar (lista priorizada)

Ordem por impacto/esforco. Cada item reduz a baseline dos gates e sobe o placar.

**Prioridade 1 - parar a hemorragia da cascata**
1. Migrar `admin/academic/alunos_lista.php` (campeao de `!important` com 2173): mover o `<style>` embebido para CSS com classes, eliminar `!important` substituindo por especificidade correcta.
2. Definir uma meta de teto de `!important` por ficheiro e fazer descer a baseline a cada entrega.

**Prioridade 2 - tipografia e espacamento (maior volume, troca mecanica)**
3. Substituir os 2478 `font-size: Npx` por `var(--fs-*)` (mapa: 11->xs, 13->sm, 14->base, 16->md, 20->lg, 24->xl).
4. Substituir os 4773 `padding/margin/gap: Npx` por `var(--space-*)` (escala 4px ja existe).

**Prioridade 3 - sombras, raios, movimento, camadas**
5. Trocar 1122 `box-shadow` literais por `var(--shadow-*)`.
6. Trocar 2084 `border-radius` literais por `var(--radius-*)`.
7. Trocar 177 `transition` e 93 `z-index` literais pelos `--duration-*` / `--z-*`.

**Prioridade 4 - estilo inline e animacoes**
8. Extrair os 2244 `style="..."` inline para classes utilitarias ou CSS de view.
9. Apagar os 51 `@keyframes` duplicados e apontar tudo para `sige-fade-in` / `sige-rise-in` / `sige-spin`.
10. Eliminar os 102 nomes de cor CSS soltos.

**Prioridade 5 - higiene final**
11. Reduzir as 1927 cores hex ainda em views (o `style.css` ja esta limpo; falta as views).
12. Consolidar os 55 blocos `<style>` embebidos num CSS de view por modulo.

---

## 5. O gate que impede a regressao

Ficheiro novo: **`tools/check-consistencia-visual.php`** (irmao do `check-design-tokens.php`).

Vigia as 6 dimensoes que ainda nao tinham gate (font-size, espacamento, box-shadow, transition, z-index, estilo inline) com a mesma filosofia da casa: **a divida nunca sobe**. Conta os valores magicos fora da fonte da verdade e falha se o total OU qualquer dimensao subir acima da baseline (impede trocar uma divida por outra). A baseline so desce, com `--set` na mesma entrega da migracao.

```
php tools/check-consistencia-visual.php            # verifica (verde se nao subiu)
php tools/check-consistencia-visual.php --set       # regista novo minimo apos migrar
php tools/check-consistencia-visual.php --report     # piores 20 ficheiros por dimensao
```

Baseline inicial registada: **10 887** primitivos magicos. Ja esta ligado ao corredor `tools/run-gates.php` (passou de 13 para **14 gates**, todos verdes).

---

## 6. O controlador de sucesso

Ficheiro novo: **`tools/controlador-consistencia-visual.php`**.

Enquanto o gate impede regressao, o controlador mede **progresso**. Calibra um ponto de referencia (o estado actual) e calcula, por dimensao e global, quanto da divida ja foi eliminado (0-100), ponderado pelo peso de cada divida. So toca o sino quando **tudo** chega a zero.

```
php tools/controlador-consistencia-visual.php             # placar 0-100 por dimensao
php tools/controlador-consistencia-visual.php --calibrar    # fixa o ponto de partida
php tools/controlador-consistencia-visual.php --gate        # sai 1 enquanto houver divida; 0 ao 100/100
```

O `--gate` e o "sino de fim de missao": fica vermelho ate a consistencia ser total. Foi mantido **fora** do `run-gates.php` de proposito, para nao bloquear cada entrega durante a longa migracao. Corre-se a pedido, para ver o quanto falta.

Estado actual do placar: 0,0/100 (recem-calibrado). Cada migracao da seccao 4 fa-lo subir; quando marcar 100/100 e o `--gate` ficar verde, todas as inconsistencias detectadas por este diagnostico estarao resolvidas.

---

## 7. Como saber que terminamos

A missao esta cumprida quando, em simultaneo:
- `php tools/run-gates.php` -> 14 de 14 verdes (nao regredimos), e
- `php tools/controlador-consistencia-visual.php --gate` -> "MISSAO CUMPRIDA" exit 0 (resolvemos tudo).

O primeiro garante que nao andamos para tras. O segundo garante que chegamos ao fim.
