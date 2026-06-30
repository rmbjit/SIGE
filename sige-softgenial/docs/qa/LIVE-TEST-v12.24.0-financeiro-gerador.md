# LIVE-TEST Financeiro Gerador (v12.24.0)

Mudança mínima: o modal do gerador deixa de ficar atrás da barra lateral.

## Pré-condições
- Versão 12.24.0. Ctrl+F5 (é CSS). Desktop/portátil (barra lateral visível).
- Perfil com permissão de Tesouraria (gerar lançamentos).

## Funcional (o ponto central)
| Fluxo | Esperado |
|---|---|
| Lançar Mensalidades -> gerar | O modal de **confirmação** aparece por cima da barra lateral (sidebar esbatida atrás) |
| Confirmar | O modal de **sucesso** também por cima da barra lateral |
| ESC / clique fora | Fecham e a página volta ao normal (scroll reposto) |
| Scroll dentro do modal | Corpo rola; cabeçalho/rodapé fixos |

## Anti-regressão (CRÍTICO - é financeiro)
- A geração de mensalidades funciona exactamente como antes (valores, período,
  turma/aluno, ano todo). NENHUMA mudança de lógica/fórmula.
- O herói (painel com ano lectivo / serviços / turmas / alunos) inalterado.
- Sem erros de consola novos; sem violações CSP novas.

## Outros ecrãs financeiros (não alterados nesta versão)
- Centros de Custo, Auditoria, Despesas, Lançamentos: continuam como antes. Se
  notar o mesmo efeito de modal atrás da barra lateral neles, é o mesmo padrão
  partilhado e será corrigido quando forem revistos.

## Clientes
- Validar em pelo menos um cliente real, gerando um lançamento de teste.
