# LIVE-TEST Config - Editar serviço como pop-up (v12.27.0)

Mudança de UX: "Editar" abre o modal no cliente (sem reload). A gravação é normal.

## Pré-condições
- Versão 12.27.0. Ctrl+F5. Perfil de Tesouraria/Configuração.

## Funcional
| Fluxo | Esperado |
|---|---|
| Editar um serviço (Lista de Serviços) | Modal abre INSTANTANEAMENTE, já preenchido (sem recarregar a página) |
| Título do modal | "Editar Serviço" |
| Fechar (X / clicar fora / ESC) | Fecha sem recarregar |
| Editar serviço de Transporte ("Editar serviço completo") | Idem, abre pop-up preenchido |
| Adicionar Serviço | Abre limpo (pop-up); título "Adicionar Serviço" |
| Alternar Editar -> fechar -> Adicionar | O Adicionar abre com o formulário LIMPO (não mantém dados do editado) |

## Anti-regressão (CRÍTICO - integridade dos dados)
| Verificação | Esperado |
|---|---|
| Gravar (Actualizar) após editar | Guarda como antes; TODOS os campos correctos: nome, tipo, categoria, classe, ciclo, centro, valor, dia, SNE, multa (tipo+valor) |
| Regras (checkboxes) | Multa / Desc. Irmãos / Desc. Pronto Pag. / Desc. Funcionário / Activo guardam exactamente o estado mostrado |
| Sem JS (fallback) | O link "Editar" continua a funcionar via ?edit=ID (recarrega, como antes) |
| Gravar novo serviço (Adicionar) | Funciona como antes |

## Nota
- Não se alterou a lógica de gravação no servidor nem as fórmulas; só a forma de
  abrir o modal de edição (cliente em vez de reload).

## Clientes
- Validar num cliente real: editar um serviço, gravar, e confirmar na lista que os
  valores ficaram correctos.
