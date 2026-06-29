# v12.15.11 - Alunos Stability Recovery

Regra desta versão: não mover modais no DOM e não instalar dispatcher/MutationObserver para corrigir layering.

A solução correcta para este hotfix é elevar o stacking context da área de conteúdo enquanto `.sige-aluno-modal-open` estiver activo. Essa classe já é usada pelos handlers existentes do módulo, portanto a correcção preserva o comportamento original.
