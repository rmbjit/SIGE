# Phase Charter - v12.15.11 Alunos Stability Recovery

## Objectivo
Recuperar estabilidade operacional da página Alunos e Matrículas após regressões de performance e botões sem resposta.

## Escopo
- `assets/views/alunos-design-pro.css`
- `assets/views/alunos-design-pro.js`
- versionamento, gates e documentação.

## Não-escopo
- Financeiro.
- Portaria.
- PDF/documentos.
- Shell/topbar/sidebar.
- Redesign global.

## Critério de aceitação
- Página de Alunos deve abrir normalmente.
- Botões Registar Aluno, Importar Lista, Ficha 360º e três pontinhos devem responder.
- Modais devem aparecer acima da sidebar.
- Sem MutationObserver, sem dispatcher adicional e sem mover modais para `body`.
