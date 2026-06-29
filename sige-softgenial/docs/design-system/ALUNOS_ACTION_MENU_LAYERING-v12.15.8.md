# v12.15.8 - Alunos Action Menu Layering Closure

## Problema observado em staging
Na página **Alunos e Matrículas**, a v12.15.7 melhorou o menu dos três pontinhos para abrir verticalmente. Contudo, ao mover o cursor sobre o card do aluno imediatamente atrás do menu aberto, esse card podia elevar-se por `hover` e ficar acima do menu, dificultando o clique nas acções.

## Contrato corrigido
- O menu de acções continua vertical.
- O card que contém um menu aberto recebe classe `sige-card-actions-open`.
- O `body` recebe classe `sige-alunos-actions-open` enquanto existir qualquer menu aberto.
- O card aberto fica acima dos cards vizinhos no contexto de stacking.
- O menu aberto fica acima do próprio card e acima de cards em hover.
- Cards vizinhos não podem elevar-se acima do menu enquanto houver menu aberto.
- O clique no menu preserva `pointer-events:auto`.

## Escopo
A alteração é restrita a:

- `assets/views/alunos-design-pro.css`
- `assets/views/alunos-design-pro.js`
- `includes/ui-kit.php` apenas para versionar o handle dos assets de Alunos.

## Fora do escopo
- Financeiro.
- Portaria/QR.
- PDFs/documentos.
- Shell, topbar e sidebar.
- Limpeza física do CSS histórico inline dentro de `admin/academic/alunos_lista.php`.
