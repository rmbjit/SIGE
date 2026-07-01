# DEPLOY - SIGE SoftGenial v12.37.1

**Correcções: foto de perfil que não gravava + botões que "congelavam"**
Data: 2026-07-01 - Tipo: correcção de bugs. Sem schema, sem novo endpoint.

## O que muda
- `includes/ajax-handlers.php` — **foto de perfil**: o validador de URL de imagem
  deixa de exigir host idêntico ao do site (que falhava com CDN / www / proxy do
  CloudPanel e descartava a foto). Passa a aceitar URLs que sejam **anexos reais
  da biblioteca** (`attachment_url_to_postid`) ou que estejam **dentro do
  directório de uploads** (comparação por caminho), mantendo a recusa de URLs
  externos e a exigência de extensão de imagem.
- `assets/sige-ui.js` — **botões**: o despachante `data-sige-act` invoca os
  handlers dentro de `try/catch` (um erro num handler já não deixa a interface a
  meio; fica registado na consola).
- `admin/hr/equipe-view.php` — **botões**: auto-recuperação em fase de captura —
  se nenhum modal está aberto mas ficou um overlay órfão a capturar cliques (ou o
  body bloqueado), tudo é libertado no clique seguinte.
- `sige-softgenial.php` / `BUILD.json` -> 12.37.1.

> ✅ **Sem ficheiros novos no plugin.** Altera 3 ficheiros existentes (+ versão).
> Não toca em ficheiros protegidos por hash.
>
> ℹ️ `assets/sige-ui.js` é um JS global do sistema (afecta o despachante de
> acções em todas as views) — a alteração é apenas defensiva (try/catch).

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/ajax-handlers.php
sige-softgenial/assets/sige-ui.js
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-fix-foto-botoes-v12-37-1.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. Sem migração de dados.
   **Limpe a cache** do navegador (Ctrl+Shift+R) — há alteração de JS.

## Verificação rápida
- **Foto**: Equipa → editar um colaborador → "Carregar foto" (biblioteca) →
  Guardar. Após o refresh automático, a foto aparece na lista e na ficha.
- **Botões**: usar a Equipa normalmente (abrir/fechar modais, imprimir, editar).
  Não deve haver estados em que os cliques "não fazem nada"; se um handler falhar,
  o erro aparece na consola e a interface continua utilizável.

## Rollback
- Reponha os ficheiros anteriores a partir do backup. (Nota: repor o validador
  antigo faz voltar o problema da foto em ambientes com host/CDN diferente.)

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
