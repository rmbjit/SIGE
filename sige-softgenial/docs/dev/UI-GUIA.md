# UI-GUIA - o cânone de interface do SIGE SoftGenial

Este guia é a régua de TODOS os ecrãs a partir do Sprint UX-1. Quando uma
view é tocada, sai conforme; o auditor (tools/ux-audit.php) mede desvios.

## 1. Terminologia única (PT-MZ pré-AO90)
| Conceito | Palavra canónica | Proibidas |
|---|---|---|
| Persistir alterações | **Guardar** | Salvar, Gravar |
| Apagar registo | **Remover** | Excluir, Deletar, Apagar, Eliminar |
| Procurar | **Pesquisar** | Buscar, Procurar |
| Modificar | **Editar** | Alterar (como rótulo de botão) |
| Recuar sem efeitos | **Cancelar** | Voltar (quando há formulário aberto) |
| Pessoa que entra no sistema | **utilizador** | usuário |
| Estado ligado | **activo** | ativo |
| Criar conta/registo | **registar** | cadastrar |

## 2. Diálogos
- alert() e confirm() nativos são PROIBIDOS.
- Feedback de acção: `sigeUi.toast(mensagem, 'ok'|'erro'|'aviso'|'info')`.
- Confirmação destrutiva: `sigeUi.confirm({...})` SEMPRE com o nome do
  objecto no texto ("A circular \"Reunião de pais\" será removida e os
  envios pendentes cancelados.") e botão rotulado com o verbo da acção
  ("Remover circular"), nunca um "OK" genérico. Em formulários simples,
  usar o enhancer declarativo `data-sige-confirm` sem JS por ecrã.

## 3. Estados do ecrã
- Tabela sem dados nunca fica em branco nem num "Nenhum registo" seco:
  usar `sige_ui_empty(icone, titulo, texto, accao)` com a PRÓXIMA ACÇÃO
  ("Ainda sem circulares enviadas" + botão "Escrever a primeira").
- Toda a acção tem feedback visível: toast (acções AJAX) ou
  `sige_ui_banner` (pós-redirect). Mensagem humana: o que aconteceu +
  o que fazer a seguir. Nunca códigos crus ("Erro 0x80").
- Operações > 300ms mostram estado: `sigeUi.aCarregar(botao, true)`.

## 4. Hierarquia visual
- UM botão primário por ecrã (`sgk-btn sgk-btn-primario`); acções
  secundárias `sgk-btn-sec`; destrutivas `sgk-btn-perigo`; terciárias
  `sgk-btn-ghost`. Botão NUNCA sem classe.
- Cores, raios e sombras vêm dos tokens (`var(--sgk-*)`); novos hex
  soltos são dívida.

## 5. Formatos
- Datas: dd/mm/aaaa. Dinheiro: 1.234,56 MT (espaço antes de MT).
- Telefones: 84 XXX XXXX.

## 6. Componentes do kit (assets/sige-ui.css + js, includes/ui-kit.php)
.sgk-btn (+primario/sec/perigo/ghost/sm), .sgk-banner (+ok/erro/aviso/info),
.sgk-card, .sgk-badge (+tipos), .sgk-empty, .sgk-modal (via sigeUi.confirm),
.sgk-toast (via sigeUi.toast), .sgk-spinner, .sgk-table-wrap.
PHP: sige_ui_banner(), sige_ui_empty(). JS: sigeUi.toast/confirm/aCarregar.

## 7. Regra de adopção
O kit é namespaced: views não tocadas não mudam. Cada sprint UX converte
módulos por ordem do ranking (docs/dev/UX-AUDIT.md); dentro de um módulo
tocado, a conformidade é TOTAL: terminologia, diálogos, vazios, feedback,
botões. Inline de layout pontual tolera-se até à consolidação CSS;
inline de COMPONENTE (botão/banner/badge/cartão feitos à mão) não.


## Prefixo reservado do kit: sgk- (regra permanente, 12 Jun 2026)
O prefixo `sg-` pertence ao design system histórico (style.css), que já
definia `.sg-modal` (invisível sem `.active`), `.sg-btn`, `.sg-card`,
`.sg-toast` e `.sg-badge`. O kit pousou nesse namespace SEM verificar e
o resultado foi o bug do modal invisível em produção de teste.
Regra: TODO o componente do kit usa `sgk-` (classes, variáveis
`--sgk-*`, keyframes `sgk*`). Antes de criar qualquer classe nova em
qualquer CSS, correr `php tools/check-css-collisions.php`: o gate falha
se uma classe ou variável definida no kit ou nos CSS de views também
existir no style.css/mobile-tablet-ux.css. Lição registada: namespace
afirma-se com grep, não com fé.

## Diálogos nativos: EXTINTOS (regra permanente, UX-8, 12 Jun 2026)
O sistema não usa `alert()`, `confirm()` nem `prompt()` nativos do
browser em NENHUMA view. Três varredores globais na regressão
(smoke-regression-pack.php) falham a release se algum reaparecer.
Em vez deles:
- informação/erro/aviso/sucesso -> `sigeUi.toast(msg, 'ok|erro|aviso|info')`;
- pedido de texto -> `await sigeUi.prompt({...})`;
- confirmação por código -> `await sigeUi.confirm({...})`;
- confirmação declarativa em botões, submits E links -> atributos
  `data-sige-confirm`, `data-sige-titulo`, `data-sige-confirmar`,
  `data-sige-perigo="1"`. O enhancer do kit intercepta clique, Enter e
  links `<a>` (preservando href e nonce). Confirmação destrutiva inclui
  SEMPRE o nome do objecto (ex.: o nome da disciplina, do aluno, da rota).
