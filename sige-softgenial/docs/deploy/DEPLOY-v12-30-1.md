# DEPLOY - SIGE SoftGenial v12.30.1

**Equipa: paridade definitiva com Permissões (perfil SIGE actual) + cargo/KPIs por perfil + sem SUPER admin**
Data: 2026-06-30 - Tipo: correcção de listagem/cargo/KPIs (view=equipe).

## O que muda
- `admin/hr/equipe-view.php`:
  1. A união com `sige_user_roles` deixa de filtrar por `r.ativo` — passa a usar
     **exactamente** o mesmo critério da coluna "PERFIL SIGE ACTUAL" da página de
     Permissões (`ur.ativo = 1`, excluindo papéis de portal). As duas listas
     passam a coincidir, por construção.
  2. O **cargo** e a **categorização dos KPIs** (Docentes/Admin/Apoio) passam a
     seguir o **perfil SIGE actual** (não o papel WordPress legado). Um
     colaborador com papel WP "guarda" mas perfil actual "professor" aparece como
     **Professor** e conta em **Docentes**.
- `sige-softgenial.php`: versão -> 12.30.1 (header + `SIGE_VERSION`).
- `BUILD.json`: manifesto -> 12.30.1.
- Novo teste: `tools/smoke-rh-equipe-lista-completa-v12-30-1.php`.

> Não altera `includes/permissions-layer.php` (ficheiro protegido por hash); a
> Equipa apenas consome os seus helpers.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-equipe-lista-completa-v12-30-1.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia o ZIP por cima, mantendo a estrutura (`sige-softgenial/...`).
3. Sem migração de dados. Sem alteração de schema.

## Verificação rápida
- **Equipa** e **Permissões e Perfis** lado a lado: os colaboradores com perfil
  SIGE devem coincidir (exceto alunos/encarregados, que são portal).
- O colaborador que antes faltava (perfil actual Professor) aparece agora na
  Equipa, com o cargo **Professor** e contando em **Docentes**.
- O **super admin** (administrador WordPress) NÃO aparece.
- Os KPIs (Total, Docentes, Admin, Apoio) batem com a lista visível.

## Rollback
- Reponha a versão anterior dos ficheiros a partir do backup.

## Fronteira de segurança
- Sem mexer em fórmulas, schema, nonces, AJAX, name/id ou permissões reais.
  Tenant-scoped por escola.
