# Phase Charter - v12.15.7 - Design System PRO: Alunos e Matrículas

## Objectivo
Modernizar e estabilizar visualmente apenas o módulo Alunos e Matrículas, seguindo a estratégia incremental aprovada na v12.15.5 e a base de shell aprovada na v12.15.6.

## Escopo
- View `alunos_lista`.
- Hero e botões principais.
- Filtros e pesquisa.
- Cards de alunos.
- Menu de três pontinhos/acções.
- Estados vazios/sem dados.
- Modal/ficha do aluno naquilo que depende da página de Alunos.
- Acessibilidade mínima: foco, `aria-expanded`, Escape/click externo.
- Responsividade em desktop, laptop, tablet e mobile.

## Não-escopo
- Financeiro.
- Portaria/QR.
- PDFs/documentos.
- RH/Equipe.
- Sistema/permissões.
- Reestruturação total do CSS legado inline em `alunos_lista.php`.
- Motor PDF server-side.

## Riscos
- A view Alunos ainda contém CSS histórico inline e muitos `!important`.
- Sem teste em browser autenticado neste ambiente, ainda é obrigatório validar em staging.
- Dados reais longos podem expor ajustes finos de densidade.

## Critérios de aceitação
- Assets por view carregados apenas em `alunos_lista`.
- Sem alteração visual global.
- Menu dos três pontinhos vertical e sem sobreposição em mobile.
- Cards sem compressão textual.
- Scroll de modais preservado.
- Gates CLI verdes.
- CSP zero-inline preservada.
