# assets/views/ - Regra do escuteiro para os monólitos

## O problema
As views maiores (alunos_lista.php com 9.228 linhas, financeiro-extratos.php
com 3.760, equipe-view.php com 3.724) misturam PHP, HTML, CSS e JS no mesmo
ficheiro. Reescrever de uma vez viola a regra "nunca regressão".

## A regra (em vigor desde a v12.11.9.90)
**Cada vez que uma view for tocada por qualquer motivo, o CSS e o JS dela
saem do PHP e entram aqui**, com o nome da view:

```
assets/views/alunos_lista.css
assets/views/alunos_lista.js
assets/views/financeiro-extratos.css
...
```

No PHP da view, substituir os blocos <style>/<script> por uma única linha:

```php
sige_view_assets('alunos_lista'); // carrega .css e .js desta pasta, se existirem
```

## Regras de extracção (não negociáveis)
1. A extracção é MOVIMENTAÇÃO, não reescrita: o conteúdo sai byte a byte;
   refactor de CSS/JS é outro commit, noutro dia.
2. Se o bloco tem PHP embebido (valores dinâmicos), esses valores passam
   por wp_localize_script ou data-attributes; o resto move-se igual.
3. Uma view por release. Extrair duas ao mesmo tempo duplica o risco e
   esconde a origem de qualquer regressão visual.
4. Testar a view extraída no live ANTES de tocar na seguinte.

## Porque funciona
Sem parar o produto nem agendar um "grande refactor", a dívida dissolve-se
ao ritmo natural do trabalho: em seis meses de releases normais, as views
mais tocadas (que são exactamente as mais críticas) ficam limpas sozinhas.
