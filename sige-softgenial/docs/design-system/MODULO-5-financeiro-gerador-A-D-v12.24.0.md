# Módulo 5 - Financeiro Gerador (view=financeiro-gerador) - Portões A-D

Versão: 12.24.0
Data: 2026-06-30
Ficheiro: `admin/finance/financeiro-gerador.php` (1472 linhas) + `assets/style.css`

## Portão A - auditoria contra os erros já corrigidos

| Erro corrigido antes | Aplica-se? | Evidência |
|---|---|---|
| Modal tapado pela barra lateral | **SIM** | `.sg-modal-backdrop` (z-index 999999) e `.sige-lanc-modal` (1000000) vivem dentro de `.sg-app-content` (contexto z-index:1 < sidebar). Sem elevação para `body.sg-modal-open`. Trap confirmado |
| Modal sem scroll | **NÃO** | `.sige-lanc-modal` é coluna flex (max-height 88vh, overflow hidden); `.sige-lanc-modal-body{flex:1;overflow-y:auto}`. Correcto |
| ESC/fecho deixa estado preso | **NÃO** | ESC chama `closeModal`/`closeSuccessModal` e o `sg-modal-open` é posto/retirado correctamente |
| `:root` a sombrear tokens globais | **NÃO** | A view não tem `<style>` nem `:root`; usa o sistema partilhado `sg-finpro`/`sg-generator` |
| Handlers inline `on*=` (server) | **NÃO** | 0 handlers inline (usa `data-sige-act`/`addEventListener`) |
| Herói grande/decorativo | **NÃO (não tocar)** | `sg-finpro-hero` é design partilhado e **funcional** (painel real com ano lectivo / nº de serviços, turmas, alunos). Não é ilustração; é património. Verificado o estado renderizado |
| Botões responsivos | **NÃO** | 2 botões-link na coluna de conteúdo; sem o problema 2+1 |

Conclusão: este ecrã já estava em muito bom estado (sistema financeiro PRO). A
única dívida é o **empilhamento do modal** (partilhado).

## Portões C/D - implementado (só apresentação)

- `assets/style.css`: elevação do conteúdo enquanto há modal aberto, **scoped a
  esta view**:
  `body.sige-admin-app.sige-view-financeiro-gerador.sg-modal-open .sg-app-content{z-index:10090}`.
  Resolve os modais de confirmação e de sucesso do gerador.

## Achado partilhado (a confirmar) - NÃO alterado

O mesmo trap de empilhamento afecta os outros ecrãs financeiros que usam
`.sg-modal-backdrop` + `body.sg-modal-open` (ex.: Centros de Custo, Auditoria,
Despesas, Lançamentos). A correcção definitiva seria **global** (uma linha em
`style.css` sem o selector de view), mas mantive-a **scoped** ao gerador para
respeitar o ritmo de validação por ecrã. Quando quiser, promovo a global (ou
aplico ecrã a ecrã) com um teste de modal por cada um.

## Fronteira
Sem lógica, SQL, `$wpdb`, fórmulas financeiras, recibos, dívida, descontos,
nonces, AJAX, `name`/`id`, permissões nem schema. Só uma regra de z-index de
apresentação, scoped à view. Contratos financeiros intactos.

## Validação
Gate de tokens estável (regra sem hex/raio). Body class `sige-view-financeiro-gerador`
confirmada (padrão `sige-view-financeiro-*` já usado no CSS).
