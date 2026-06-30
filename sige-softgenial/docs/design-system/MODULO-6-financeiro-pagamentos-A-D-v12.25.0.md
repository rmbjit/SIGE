# Módulo 6 - Registar Pagamento (view=financeiro-pagamentos) - Portões A-D

Versão: 12.25.0
Data: 2026-06-30
Ficheiro da view: `admin/finance/financeiro-pagamentos.php` (3874 linhas) - **NÃO
TOCADO** (ficheiro financeiro hash-protegido). Todas as melhorias foram feitas em
`assets/views/financeiro-core-design-pro.{js,css}` (já carregados nesta view).

## Restrição crítica
- `financeiro-pagamentos.php` está na lista de **hashes protegidos** do gate de
  baseline (ficheiro financeiro core). O utilizador pediu "não mexer nas fórmulas,
  só na estruturação visual". Por isso **não editei o PHP da view**: todo o
  enriquecimento é feito pelos assets partilhados do financeiro (CSS+JS), que já
  são carregados em pagamentos via `includes/ui-kit.php`.

## Portão A - auditoria contra os erros já corrigidos

| Erro | Aplica-se? | Evidência / acção |
|---|---|---|
| `:root` a sombrear tokens globais | NÃO | Usa namespace `--sige-*`; não redefine `--shadow-*`/`--radius-*` globais |
| Handlers inline `on*=` | Presentes (3) mas **deixados** | `onchange/oninput` chamam `recalcularTotal()` (cálculo do pagamento). São server-rendered (a camada CSP converte-os e funcionam). Mexer aqui é risco no formulário de pagamento sem ganho - não tocado |
| Modal tapado pela barra lateral | SIM | Modais de sucesso (`.sige-modal`) presos no contexto de `.sg-app-content`. Corrigido via classe `sige-paypro-modal-open` (JS) + elevação (CSS) |
| Página demasiado longa | SIM (pedido principal) | 3 secções `.sg-paypro-operation-card` (Dívidas, Adiantar 12 meses, Outros). Tornadas colapsáveis |
| Checkboxes "ovo" | Já corrigido | v12.24.2 (raiz partilhada). Confirmado no screenshot (WhatsApp/E-mail quadrados) |

## Portões C/D - implementado (só apresentação, fora do PHP financeiro)

1. **Secções colapsáveis** (`financeiro-core-design-pro.js`, scoped a pagamentos):
   - `.sg-paypro-operation-card` ganham cabeçalho clicável + seta.
   - **Dívidas Actuais** abre por defeito; **Adiantar Meses Futuros** e **Outros
     Serviços** começam fechadas (a página fica muito mais curta; o utilizador
     expande só o que precisa).
   - Defensivo: qualquer secção com um `input:checked` abre automaticamente (não
     esconde escolhas já feitas).
   - Acessível (role=button, tabindex, Enter/Espaço). Ignora cliques em controlos
     dentro do cabeçalho.
   - **Não intercepta** submissão/cliques de pagamento nem move nós do formulário;
     os inputs colapsados continuam no DOM e a submeter; `recalcularTotal()` lê-os
     na mesma. Zero impacto em valores.
2. **Modal acima da barra lateral**: `sige-paypro-modal-open`/`sgk-modal-open`
   elevam `.sg-app-content` enquanto há modal visível (inclui os modais de sucesso
   renderizados pelo servidor).

## Fronteira
- `financeiro-pagamentos.php` byte-a-byte intacto (confirmado por git + gate de
  baseline). Sem lógica, fórmulas, SQL, nonces, AJAX, `name`/`id`, permissões nem
  schema. Só CSS/JS visual nos assets partilhados, scoped a pagamentos.

## Validação
- `node --check` ao JS: OK. Gate de tokens 1907 (estável). Gate de baseline: só os
  2 desvios pré-existentes (dashboard, admin-shell); pagamentos NÃO aparece
  (intacto).
