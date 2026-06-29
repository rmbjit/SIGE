# LIVE-TEST Barra lateral - fluxo do ano lectivo (v12.20.0)

Mudança GRANDE de navegação que toca gating por papel. Validar por perfil.

## Pré-condições

- Versão 12.20.0. Ctrl+F5.

## Ordem esperada (perfil director, módulos todos activos)

Painel Principal · ESTRUTURA ACADÉMICA · EQUIPA · SECRETARIA · FATURAÇÃO ·
TESOURARIA · SALA DE AULA · AVALIAÇÃO & DOCUMENTOS · JARDIM DE INFÂNCIA ·
OPERAÇÃO ESCOLAR · COMUNICAÇÃO · RELATÓRIOS & ANÁLISE · CONFIGURAÇÃO ·
PRIVACIDADE E DADOS.

## Matriz por perfil

| Perfil | Esperado |
|---|---|
| **Director / Admin** | Vê os grupos aplicáveis na ordem acima; cada link abre (menu concorda com a rota) |
| **Secretaria** | SECRETARIA (Alunos, Turmas, Contas) + o que tiver de académico/comunicação. Cadastro tem casa própria |
| **Tesouraria (financeiro)** | FATURAÇÃO + TESOURARIA + (Relatórios financeiros) + Comunicação se tiver `comunicacao.*` |
| **Professor com escopo** | SALA DE AULA (Minhas Turmas, Notas, Pautas, Presenças) e o que a sua permissão permitir em AVALIAÇÃO. NÃO vê SECRETARIA/cadastro |
| **Educador / Jardim** | JARDIM DE INFÂNCIA com os seus itens |
| **Guarda** | OPERAÇÃO ESCOLAR (Portaria) |
| **Privilégio mínimo financeiro** (ex.: só extractos) | Vê apenas o seu item no grupo respectivo |

## Verificações-chave (gating por item)

- Cada item visível **abre** (não dá "acesso restrito"): menu == rota.
- Itens que antes apareciam por bloco e a rota bloqueava (Pautas, DEC, Pauta
  Final, Acta, Aprovar Notas, itens de jardim) agora só aparecem com a permissão
  exacta.
- "Turmas" aparece em SECRETARIA (não em Estrutura) e está presente quer o módulo
  académico esteja on/off (desde que cadastro on).
- Currículos, Pagamentos Móveis, Reconciliação, Aprovações mostram selo BETA.

## Anti-regressão

- Nenhum item de menu desaparece para quem tinha acesso real (rota permitida).
- Sem erros de consola; sem violações CSP.
- Restante shell (topbar, página do aluno, mobile) intacto.

## Edge case a confirmar

- Professor com escopo que tenha `alunos.ver`: já NÃO vê a lista de Alunos
  (a CONSULTA foi removida; SECRETARIA exclui professor com escopo). Confirmar que
  é aceitável (mais correcto em termos de privacidade).

## Clientes

Validar em pelo menos dois (ex.: teste e cicasacolorida), cobrindo um perfil
não-director (secretaria ou professor) para o gating.
