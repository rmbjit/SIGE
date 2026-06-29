# Rediagnostico Adversarial - v12.15.6 - Shell, Scroll, Topbar e Sidebar

## Pergunta adversarial
Esta versao pode repetir a regressao visual das v12.15.0 a v12.15.2?

## Resposta
O risco foi reduzido porque a versao nao cria enhancer global, nao reintroduz `sige-design-system-pro.*` e nao actua em cards, tabelas, botoes internos ou accoes dos modulos. O escopo fica limitado ao shell.

## Tentativas de quebra avaliadas

### 1. Bloquear scroll em tablet
A camada desktop passa a ser considerada apenas a partir de 1101px. Entre 861px e 1100px, o body e o wrapper voltam a permitir scroll natural.

### 2. Alterar cards de alunos ou menus internos
O gate `check-shell-scope-v12-15-6.php` bloqueia selectores de cards, tabelas, botoes internos e familias de classes dos modulos.

### 3. Quebrar CSP
Os novos assets sao externos, enfileirados e nao usam `eval`, `new Function` ou eventos inline.

### 4. Sidebar deixar body bloqueado
O JS sincroniza sidebar, overlay e `sg-app-menu-open`, e fecha sidebar em desktop apos resize.

### 5. Topbar cortar titulo em ecras pequenos
O contrato usa `min-width:0`, `text-overflow:ellipsis` e evita quebra letra por letra no shell.

## Riscos residuais
- O CSS historico ainda tem muitas regras com `!important`.
- O teste real em browser autenticado continua indispensavel.
- Esta versao nao corrige menus ou cards internos fora do shell.
