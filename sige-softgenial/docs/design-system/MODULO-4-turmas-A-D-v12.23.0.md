# Módulo 4 - Turmas (view=turmas) - Portões A-D

Versão: 12.23.0
Data: 2026-06-30
Ficheiro: `admin/academic/turmas-view.php` (2376 linhas)

## Portão A - auditoria contra os erros já corrigidos

| Erro corrigido antes | Aplica-se aqui? | Evidência / acção |
|---|---|---|
| Modal tapado pela barra lateral (empilhamento) | **SIM (4 modais + confirm)** | `.sg-app-content` é contexto (z-index:1) < sidebar. Os modais (turma/docentes/horário/alunos) abriam sem `sige-modal-open`; só o confirm a punha. Corrigido: classe em todos + regra de elevação |
| ESC/fecho deixa estado preso | **SIM** | ESC fazia `fadeOut` mas NÃO retirava `sige-modal-open` -> `body{overflow:hidden}` ficava e a página deixava de rolar. Corrigido (ESC/clique-fora/X limpam a classe) |
| Modal sem scroll | **NÃO** | `.sige-modal-content` já é coluna flex; corpo `flex:1;overflow-y:auto`. Correcto |
| `:root` a sombrear tokens globais | **NÃO** | Usa namespace `--sige-*`; não redefine `--shadow-*`/`--radius-*` globais |
| Handlers inline `on*=` (CSP) - server | **SIM (3)** | `onkeyup=filtrarTurmas` (pesquisa), `onchange=filtrarTurmas` (turno), `onchange=renderHorario` (h-periodos) -> `data-sige-on-*` |
| Herói grande/decorativo + verificar estado renderizado | **SIM** | A faixa já renderizava CLARA (override em `.sige-turmas-page .sige-hero`), com grelha 2-col + ilustração de escola + `min-height:210px`. Compactado |
| Botões responsivos (largura do conteúdo, não da janela) | **SIM (preventivo)** | 2 botões; aplicado o padrão compacto + nowrap + empilhar <=720px |

## Achado funcional (CSP) - FORA do âmbito de apresentação, a confirmar

Os editores de **Horário** e de **Docentes** injectam HTML em runtime com handlers
inline (`onchange="hSetTempo(...)"`, `onchange="salvarDocente(...)"`,
`onmouseover`). Como a CSP tem `script-src-attr 'none'`, estes handlers
**injectados** (ao contrário dos server-rendered) **são bloqueados** -> o editor
de horário e o dropdown de docente provavelmente **não disparam**. É um problema
funcional pré-existente que exige refactor de JS (usar `addEventListener` ou
`data-sige-on-*` com leitura de `this.value` dentro da função, que o hidratador
partilhado não converte). NÃO corrigido nesta entrega (apresentação). Recomendo
uma correcção dedicada a seguir, com teste do fluxo de gravação do horário.

## Portões C/D - implementado (só apresentação)

1. Elevação do conteúdo enquanto há modal aberto:
   `body.sige-view-turmas.sige-modal-open .sg-app-content{z-index:10090}`.
   `sige-modal-open` passa a ser posta nos 4 modais principais (antes só no
   confirm) e retirada em TODOS os fechos (ESC, clique-fora, X, confirm).
2. ESC/clique-fora/X retiram `sige-modal-open` (corrige o scroll bloqueado).
3. CSP: 3 handlers server-rendered -> `data-sige-on-*`.
4. Herói: removida a ilustração (HTML + CSS base) e o `min-height`; grelha 2-col
   -> bloco a 100%; `box-shadow` lg -> md; padding `var(--space-6)/var(--space-8)`.
   Botões compactos numa linha (largura de conteúdo do portátil), empilham
   <=720px.

## Fronteira
Sem lógica, SQL, `$wpdb`, nonces, AJAX, `name`/`id`, permissões nem schema.
Mapas/cartões imprimíveis (`window.open`) intactos. Gravações continuam a
recarregar a página (`location.reload`).

## Validação
`php -l` OK; gate de tokens 1908 (baixou; baseline reposta); 0 handlers inline
server-rendered; ilustração HTML removida; 6 `addClass` (4 modais + confirm +
1 duplicado de editar/novo) e elevação activa.
