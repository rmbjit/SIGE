# Post Install Checklist - v12.16.2

## Antes de instalar
- Confirmar backup recente.
- Confirmar que v12.16.1 esta aprovada.
- Confirmar que nao ha testes financeiros ou academicos pendentes durante a instalacao.
- Guardar ZIP de rollback v12.16.1.

## Depois de instalar
| Perfil | Validacao | Criterio |
|---|---|---|
| Administrador | Entrar no painel e abrir Estado do Sistema | Sem fatal |
| Director | Abrir Dashboard e Comece Aqui | Navegacao clara |
| Financeiro | Abrir Pagamentos, Extractos e Devedores | Sem erro e sem alteracao de formula |
| Secretaria | Abrir Alunos, pesquisar e abrir ficha | Pagina abre |
| Professor | Abrir Minhas Turmas | Sem loop e sem #038;view |
| Guarda | Abrir Portaria no telemovel | Header e leitura funcionais |
| Encarregado | Abrir Portal | Informacao visivel |
| Aluno | Abrir Portal | Sem erro fatal |

## Validacao mobile
- 360 px.
- 390 px.
- 430 px.
- 768 px.

## Bloqueadores
Qualquer P0 ou P1 bloqueia aprovacao da fase. Nao corrigir por improviso em producao. Voltar para v12.16.1 e diagnosticar.
