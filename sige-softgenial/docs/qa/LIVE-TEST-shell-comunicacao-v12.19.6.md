# LIVE-TEST Navegação - Grupo COMUNICAÇÃO (v12.19.6)

Mudança que TOCA GATING. Validar por perfil, não só visualmente.

## Pré-condições

- Versão 12.19.6. Ctrl+F5.

## Matriz por perfil (o essencial)

| Perfil | Esperado |
|---|---|
| Tem `comunicacao.central_ver` | Vê grupo COMUNICAÇÃO com "Central de Comunicações" |
| Tem `comunicacao.whatsapp_ver` | Vê "Operação WhatsApp" |
| Pode enviar circulares (`sige_circular_pode_enviar`) | Vê "Circulares" |
| **Finanças SEM comunicação** | **Já NÃO vê** comunicações no menu (antes via e era bloqueado ao clicar) |
| **Comunicação SEM finanças** | **Passa a VER** o grupo COMUNICAÇÃO (antes não via) |
| Sem qualquer `comunicacao.*` nem circulares | Não vê o grupo |

## Concordância menu ↔ rota

- Para cada item visível, clicar e confirmar que **abre** (não dá "acesso
  restrito"). Antes, finanças sem comunicação via o link e era bloqueado: já não
  deve acontecer (o link deixou de aparecer sem a permissão certa).

## Anti-regressão

- TESOURARIA mantém todos os itens financeiros (Pagamentos, Cobranças, Auditoria,
  Relatório, Extractos, Lançamentos, etc.); só saíram os 3 de comunicação.
- Nenhum número/saldo muda. Sem erros de consola; sem violações CSP.
- Restantes grupos da barra lateral inalterados.

## Clientes

Validar em pelo menos dois (ex.: teste e cicasacolorida), idealmente com um perfil
de comunicação/secretaria sem finanças.
