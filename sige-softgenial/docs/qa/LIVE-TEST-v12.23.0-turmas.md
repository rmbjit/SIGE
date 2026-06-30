# LIVE-TEST Turmas (v12.23.0)

Mudança de apresentação/UI. Foco: modais por cima da barra lateral, ESC sem
bloquear o scroll, herói compacto.

## Pré-condições
- Versão 12.23.0. Ctrl+F5. Desktop/portátil (barra lateral visível).

## Apresentação (herói)
| Verificação | Esperado |
|---|---|
| Herói | Faixa única clara compacta, sem a ilustração de escola |
| Botões | Nova Turma + Imprimir mapa numa linha (sem 2+1 no portátil) |

## Funcional - modais (o ponto central)
| Fluxo | Esperado |
|---|---|
| Nova Turma / Editar | Modal por cima da barra lateral (sidebar esbatida atrás) |
| Docentes / Horário / Alunos | Idem, por cima da sidebar |
| **ESC** | Fecha o modal **E a página volta a fazer scroll** (antes ficava bloqueada) |
| Clique fora / botão X | Fecham e repõem o scroll |
| Scroll dentro do modal | Corpo rola; cabeçalho/rodapé fixos |
| Guardar turma | Grava e recarrega a lista |

## Funcional - pesquisa/filtros (handlers convertidos)
| Acção | Esperado |
|---|---|
| Escrever na pesquisa | Filtra as turmas (data-sige-on-keyup) |
| Mudar filtro de turno | Filtra (data-sige-on-change) |

## A confirmar (achado pré-existente, NÃO alterado)
- Editor de **Horário** (inputs de hora) e dropdown de **Docente**: confirmar se
  respondem. Suspeita de bloqueio por CSP (handlers inline injectados). Se não
  funcionarem, é pré-existente e será corrigido numa entrega dedicada.

## Anti-regressão
- Lista de turmas, cartões, ocupação e impressão de mapa intactos.
- Sem erros de consola novos; sem violações CSP novas.

## Clientes
- Validar em pelo menos um cliente real (criar/editar uma turma + ESC).
