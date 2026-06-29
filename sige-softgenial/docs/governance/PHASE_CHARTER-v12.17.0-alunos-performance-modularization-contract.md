# Phase Charter - v12.17.0 Alunos Performance & Modularization Contract

## Objectivo
Melhorar a performance e a governabilidade do modulo Alunos sem alterar regras financeiras, regras academicas, permissoes criticas, schema ou dados historicos.

## Escopo incluido
- Criar camada partilhada `includes/alunos-performance-contract.php`.
- Trocar a listagem principal de `SELECT a.*` para SELECT explicito e leve.
- Manter edicao completa via AJAX `sige_get_aluno_full`.
- Minimizar payload inline usado nos botoes de cartao, boletim e declaracao.
- Minimizar SELECT do endpoint de exportacao conforme finalidade: Excel ou cartoes.
- Adiar carregamento bloqueante de bibliotecas pesadas na pagina de Alunos usando tags com `defer`.
- Adicionar gates especificos para impedir regressao para payload gigante.

## Escopo excluido
- Nao alterar formulas financeiras.
- Nao alterar formulas academicas.
- Nao alterar permissoes reais.
- Nao alterar tenant isolation.
- Nao alterar schema de base de dados.
- Nao migrar nem normalizar dados historicos.
- Nao refazer visualmente a pagina.
- Nao dividir fisicamente todo o ficheiro `alunos_lista.php` nesta fase.

## Perfis afectados
- Secretaria: listagem, pesquisa, Ficha 360, edicao e importacao.
- Direccao: consulta e supervisao.
- Financeiro: badges financeiros e links para historico/extractos.
- Assistente/Recepcao: consulta e operacao diaria.
- Guarda: consulta limitada sem dados financeiros sensiveis.
- Administrador tecnico: gates, rollback e rastreabilidade.

## Decisoes tecnicas tomadas

### Decisao 1
**Decisao tecnica tomada:** reduzir SELECT da listagem principal para campos explicitos.
**Justificacao:** a lista nao precisa carregar todos os campos da tabela para renderizar os cards.
**Risco tratado:** pagina lenta, memory pressure e HTML pesado.
**Alternativas rejeitadas:** refactor total do ficheiro ou mudar schema.
**Criterio usado:** nao regressao, performance e reversibilidade.

### Decisao 2
**Decisao tecnica tomada:** manter a edicao completa no endpoint existente `sige_get_aluno_full`.
**Justificacao:** a edicao precisa do registo completo, mas apenas quando o utilizador abre o modal.
**Risco tratado:** perda de campos no formulario de edicao.
**Alternativas rejeitadas:** retirar campos do fluxo de edicao.
**Criterio usado:** preservacao funcional.

### Decisao 3
**Decisao tecnica tomada:** nao modularizar agressivamente todo o ficheiro nesta fase.
**Justificacao:** `alunos_lista.php` e uma superficie critica e historica. A fase segura e criar contratos e reduzir peso antes de extrair grandes blocos.
**Risco tratado:** regressao visual, quebra de modal, quebra de importacao ou perda de accoes.
**Alternativas rejeitadas:** reescrita completa.
**Criterio usado:** estabilidade primeiro, modularizacao progressiva depois.

## Definition of Done
- Versao sincronizada em 12.17.0.
- PHP lint sem erros.
- Release gate verde.
- Run gates verde.
- Listagem principal sem `SELECT a.*` directo.
- Endpoint de exportacao com SELECT minimo por finalidade.
- Payload inline de cards/documentos minimizado.
- Ficheiros financeiros, academicos, permissoes e shell preservados por hash.
- ZIP integro.
- Validacao humana em staging sem erro fatal, tela branca, loop ou regressao.

## Plano de rollback
Repor v12.16.2 aprovada. Esta fase nao altera schema nem dados, portanto o rollback e troca de plugin/ficheiros.
