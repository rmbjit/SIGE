# Portal Chrome split productionizado: gerador e gate drift-proof (v12.15.15)

## Porque

Na Fase 2 (v12.15.14), a folha `style-portal-chrome.css` era uma copia manual das
seccoes de chrome do `style.css`. Funcionava, mas tinha um risco: se alguem
editasse o chrome do `style.css` e nao reextraisse a folha, o portal (com a flag
ligada) renderizaria com chrome desactualizado, sem ninguem dar conta.

Esta versao elimina esse risco.

## Como

A folha de chrome passa a ser **gerada** a partir do `style.css`, com uma fonte
unica de transformacao e um gate que impoe igualdade byte-a-byte.

| Peca | Papel |
|---|---|
| `tools/lib-portal-chrome.php` | Transformacao pura: `sige_portal_chrome_render(style.css)` devolve cabecalho + chrome. Usada pelo gerador e pelo gate. |
| `tools/portal-chrome-header.txt` | Cabecalho canonico, num ficheiro unico, para o conteudo ser identico independentemente de quem gera. |
| `tools/gen-portal-chrome.php` | Gerador CLI: reescreve `assets/style-portal-chrome.css`. |
| `tools/check-portal-chrome-split-v12-15-15.php` | Gate: compara a folha comprometida com a saida do gerador. Drift => vermelho. |

O `style.css` NAO e editado. As seccoes de view sao localizadas pelos seus
titulos unicos: "Financeiro MJS-grade", "Pagamentos por Turma: Compliance",
"Extractos/Caixa UX-first". Cada uma e removida desde a abertura do seu comentario
de seccao ate a abertura da seccao de chrome seguinte (ou ate ao fim, no caso dos
Extractos).

## Fluxo de manutencao

1. Mudou algo no chrome do `style.css`? Correr `php tools/gen-portal-chrome.php`.
2. Correr `php tools/run-gates.php`. O gate `Portal Chrome vs Views split (v12.15.15)` confirma que a folha esta sincronizada.
3. Se um titulo de seccao usado como ancora for renomeado, o gerador/gate falham de
   proposito; actualizar a lista de ancoras em `tools/lib-portal-chrome.php`.

## Garantia de paridade (ambiente sem PHP)

A folha foi gerada por um equivalente em Python, identico em logica e a usar o
mesmo `tools/portal-chrome-header.txt`. O `gen-portal-chrome.php` produz os mesmos
bytes. O gate confirma-o no ambiente do operador.

## O que NAO muda

- Comportamento por defeito: flag `sige_portal_chrome_css_v121514_enabled`
  desligada; portal carrega o `style.css` completo.
- Nenhuma regra de CSS foi reescrita; os 36 ecras continuam a usar o `style.css`
  completo, intacto.
- PHP financeiro/academico intacto.

## Fechar a Fase 2

O unico passo que falta e a **prova visual em browser** (ver LIVE-TEST). Depois de
passar, ligar a flag por defeito (mudar `'0'` para `'1'` na shell) fecha a fase em
producao.
