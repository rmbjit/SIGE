# LIVE-TEST Preços e Serviços (v12.26.0)

Mudança só visual/UX: secções de regras colapsáveis (página mais curta) + modais
por cima da barra lateral. O PHP da config não foi tocado.

## Pré-condições
- Versão 12.26.0. Ctrl+F5 (CSS/JS). Perfil de Tesouraria/Configuração.

## Apresentação / navegação
| Verificação | Esperado |
|---|---|
| Abrir Preços e Serviços | Página mais curta: Multa, Descontos, Creche, Localização, Transporte começam FECHADAS |
| Lista de Serviços | Fica aberta (conteúdo principal) |
| Cabeçalho de secção | Seta; clicar expande/colapsa; rato realça; teclado (Enter/Espaço) |
| Gestão de Períodos | Fica aberta (sem cabeçalho separado - limitação conhecida) |

## Funcional - modais
| Fluxo | Esperado |
|---|---|
| Adicionar Serviço / Editar Serviço | Modal por cima da barra lateral; ESC/X fecham |
| Confirmar acção (apagar, etc.) | Modal de confirmação por cima da barra lateral |

## Anti-regressão (é financeiro)
| Verificação | Esperado |
|---|---|
| Guardar Multa/Descontos/Creche/Transporte | Funciona como antes; valores guardados correctos |
| Adicionar/Editar/Apagar serviço | Funciona como antes |
| Filtro por centro de custo (na Lista de Serviços) | Funciona como antes (clicar no filtro NÃO colapsa a secção) |
| Gestão de Períodos (fechar/abrir meses) | Funciona como antes |
| Botão "Adicionar Serviço" no cabeçalho | Abre o modal (não colapsa a secção) |

## Nota técnica
- `financeiro-config.php` ficou intacto; a melhoria está nos assets partilhados.

## Clientes
- Validar em pelo menos um cliente real (editar uma regra + adicionar um serviço).
