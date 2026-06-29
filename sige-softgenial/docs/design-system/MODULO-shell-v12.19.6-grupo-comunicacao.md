# Navegação - Grupo COMUNICAÇÃO com gating próprio (v12.19.6)

Versão: 12.19.6
Data: 2026-06-29
Prioridade 1 do rediagnóstico (`REDIAGNOSTICO-menus-shell-v12.19.5.md`).
Decisão do utilizador: mostrar Comunicações a quem tiver `comunicacao.*`,
independente de finanças; cada item respeita a sua permissão.

---

## Problema corrigido (M1/M1b)

Antes, WhatsApp e Central de Comunicações eram renderizados **dentro** do grupo
TESOURARIA, **sem verificação de permissão própria**, surgindo por permissão
financeira. Isto contrariava o guarda de rota (que exige `comunicacao.*`):

- Utilizador de finanças sem `comunicacao.*`: via o link, mas era bloqueado ao clicar.
- Utilizador de comunicação sem finanças: não via o link de todo.

## O que mudou (`includes/admin-shell.php`)

| Antes | Depois |
|---|---|
| WhatsApp/Comunicações/Circulares dentro de `if($ff_financeiro && ...)` | Saíram para um grupo próprio **COMUNICAÇÃO**, fora do bloco financeiro |
| Sem `if` de permissão (WhatsApp/Comunicações) | Cada item gated pela sua permissão |

Gating do novo grupo (concordante com `$sige_view_permission_map`):

| Item | Permissão do menu | Permissão da rota (1818-1819) |
|---|---|---|
| Central de Comunicações | `comunicacao.central_ver` | `comunicacao.central_ver` |
| Operação WhatsApp | `comunicacao.whatsapp_ver` | `comunicacao.whatsapp_ver` |
| Circulares | `sige_circular_pode_enviar()` (mantido) | `comunicacao.circulares_enviar` |

Grupo visível se houver qualquer um dos três. Sem feature flag (Comunicação não
tem `$ff_*`; é só permissão). Sem `--ml-color` hardcoded (evita somar hex ao gate;
a cor do rótulo é uniformizada por `style.css`).

## Fronteira

- Não se alterou lógica de negócio, SQL, fórmulas, schema, CSP nem os nomes das
  permissões (usadas as já existentes). Alterou-se **quem vê** os links de
  comunicação na barra lateral, por pedido explícito (correcção de acesso).
- TESOURARIA mantém todos os itens financeiros; só perdeu os 3 de comunicação.

## Validação

- `php -l includes/admin-shell.php`: sem erros.
- Gate de design tokens: 1929 (estável; nenhum hex novo).
- Menu concordante com o guarda de rota (sem links que a rota bloqueie).
- Teste por perfil obrigatório (ver LIVE-TEST).

## Por fazer (próximas prioridades do rediagnóstico)

M2 (Aproveitamento duplicado), M5 (rail "Comece aqui"), M3/M6 (dividir Tesouraria,
fundir grupos de 1 item), M4 (clarificar grupos académicos).
