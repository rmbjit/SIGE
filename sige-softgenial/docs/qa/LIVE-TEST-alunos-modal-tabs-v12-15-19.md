# LIVE-TEST: separadores do modal de aluno (v12.15.19)

Confirma que os separadores do modal de registo/edicao de aluno passam a abrir o
separador certo, em vez de voltar sempre a Dados gerais. O sintoma original era a
seccao de encarregados (pai/mae) parecer que falhava.

## Cenario 1 - Registar aluno: separador Encarregados

1. Abrir Academico > Alunos.
2. Clicar em Registar Aluno (abre o modal).
3. Clicar no separador Encarregados.
   **Esperado:** mostra os campos de encarregados (tipo de encarregado, pai, mae,
   encarregado principal). NAO volta a Dados gerais.
4. No selector Tipo de encarregado escolher Outro.
   **Esperado:** os campos Nome e Telemovel do encarregado passam a obrigatorios
   (a linha fica marcada como requerida).

## Cenario 2 - Todos os separadores

1. Ainda no modal, clicar em sequencia: Dados gerais, Encarregados, Saude e
   emergencia, Arquivo digital.
   **Esperado:** cada clique mostra o conteudo do separador clicado. O separador
   activo (sublinhado) acompanha o que foi clicado.

## Cenario 3 - Editar aluno mantem o comportamento

1. Fechar o modal. Num cartao de aluno, abrir o menu de accoes (botao com os tres
   pontos) e clicar em Editar.
2. Quando o modal abrir com os dados, clicar em Saude e emergencia.
   **Esperado:** mostra a seccao de saude. Os separadores funcionam tal como no
   registo.

## Cenario 4 - Teclado (ja funcionava, confirmar que nao regrediu)

1. No modal, dar foco a um separador e usar as setas esquerda/direita.
   **Esperado:** o foco e o conteudo mudam de separador; Home vai ao primeiro,
   End ao ultimo.

## Reproducao do bug (antes desta versao)

Antes da v12.15.19, clicar em Encarregados, Saude ou Arquivo voltava sempre a
Dados gerais, porque o override de switchTab lia event.currentTarget/target de um
elemento (undefined) e caia no primeiro separador. A seccao de pai/mae ficava
inacessivel pelo rato.

## Botoes de accao da lista

Os botoes do menu de accoes do cartao (Ficha 360, Acesso ao Portal, Cartao,
Declaracao, Boletim, Editar, Remover) foram auditados e estao bem ligados ao
dispatcher. Se algum ainda parecer falhar, registar o caso concreto (qual botao,
qual aluno, browser) para investigacao dirigida.

## Gates

1. `php tools/run-gates.php`.
   **Esperado:** `Alunos Modal Tabs Contract (v12.15.19)` VERDE, os quatro gates
   de alunos do corredor VERDES, o release gate VERDE para 12.15.19 e o conjunto
   de vermelhos pre-existentes inalterado.
