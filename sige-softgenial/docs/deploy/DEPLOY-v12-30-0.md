# DEPLOY - SIGE SoftGenial v12.30.0

**Equipa: lista completa (união com perfis SIGE) + KPIs correctos + sem SUPER admin**
Data: 2026-06-30 - Tipo: correcção de listagem/KPIs (view=equipe).

## O que muda
- `admin/hr/equipe-view.php`: a lista de Equipa passa a unir duas fontes —
  (A) staff WP com `sige_escola_id` da escola e (B) utilizadores com **perfil
  SIGE activo** nesta escola (tabela `sige_user_roles`, a mesma fonte da página
  de Permissões e Perfis, excluindo papéis de portal aluno/encarregado). Assim
  ninguém da equipa fica invisível. Os KPIs, que derivam da lista, corrigem-se
  automaticamente. O **administrador WordPress real / super admin** nunca aparece.
- `sige-softgenial.php`: versão -> 12.30.0 (header + `SIGE_VERSION`).
- `BUILD.json`: manifesto -> 12.30.0.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
```

## Instalação
1. Backup da pasta `sige-softgenial/` (ou da instalação WP).
2. Extraia o ZIP por cima, mantendo a estrutura (`sige-softgenial/...`).
   Substituir os 3 ficheiros.
3. Sem migração de dados. Sem alteração de schema.

## Verificação rápida
- Entre em **Equipa** e confirme que aparecem TODOS os colaboradores com perfil
  SIGE da escola (compare com a página **Permissões e Perfis** — devem coincidir,
  exceto alunos/encarregados, que não são equipa).
- Confirme que o **super admin** (administrador WordPress) NÃO aparece na lista.
- Confirme que os KPIs (Total, Docentes, Admin, Apoio, Folha, Activos/Inactivos)
  batem com a lista visível.

## Rollback
- Reponha a versão anterior dos 3 ficheiros a partir do backup.

## Fronteira de segurança
- Sem mexer em fórmulas financeiras/académicas, schema, nonces, AJAX, name/id ou
  permissões reais. Fontes tenant-scoped por escola (sem fuga entre escolas).
