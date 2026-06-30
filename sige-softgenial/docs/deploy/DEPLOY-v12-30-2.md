# DEPLOY - SIGE SoftGenial v12.30.2

**Consolidação: fonte de verdade ÚNICA do staff com perfil SIGE (Equipa + Permissões)**
Data: 2026-06-30 - Tipo: refactor de sustentabilidade. Sem alterar o comportamento da Equipa.

## O que muda
- **NOVO** `includes/sige-staff-roster.php` — única definição de "quem tem perfil
  SIGE actual nesta escola" (`sige_staff_active_profile_user_ids`,
  `sige_staff_active_profile_map`, `sige_staff_portal_role_slugs`). Critério
  canónico: `ur.ativo = 1`, sem `r.ativo`, excluindo portal.
- `sige-softgenial.php` — carrega o roster logo após a permission engine.
- `admin/hr/equipe-view.php` — consome as funções partilhadas (sem query inline).
- `admin/system/permissions-ui.php` — consome a **mesma** função; removida a cópia
  local com `AND r.ativo = 1`.
- `BUILD.json` -> 12.30.2.
- Testes: novo `tools/smoke-rh-equipe-roster-consolidado-v12-30-2.php`.

> **Não** altera `includes/permissions-layer.php` (protegido por hash). A fonte
> única apenas consome os seus helpers.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/sige-staff-roster.php          (NOVO)
sige-softgenial/sige-softgenial.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/admin/system/permissions-ui.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-equipe-roster-consolidado-v12-30-2.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia o ZIP por cima, mantendo a estrutura (`sige-softgenial/...`).
3. Sem migração de dados. Sem alteração de schema.
4. (Opcional, limpeza) pode apagar o ficheiro de teste antigo
   `tools/smoke-rh-equipe-lista-completa-v12-30-1.php` — é uma ferramenta de
   desenvolvimento, nunca corre em produção; foi substituída pela v12-30-2.

## Verificação rápida
- **Equipa** e **Permissões e Perfis** continuam a coincidir (quem tem perfil
  SIGE), tal como na v12.30.1 — agora garantido pela fonte única.
- Em Permissões, a lista fica consistente com a coluna "PERFIL SIGE ACTUAL".
- Nenhum aluno/encarregado nas listas de staff; super admin continua fora da Equipa.

## Rollback
- Reponha a versão anterior dos ficheiros a partir do backup e remova
  `includes/sige-staff-roster.php` (a v12.30.1 não o requer).

## Fronteira de segurança
- Sem mexer em fórmulas, schema, nonces, AJAX, name/id ou permissões reais.
  Tenant-scoped por escola. Nenhuma capacidade nova é concedida.
