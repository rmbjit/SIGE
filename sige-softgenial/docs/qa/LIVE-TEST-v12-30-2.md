# LIVE-TEST - SIGE SoftGenial v12.30.2 (Consolidação: fonte única do staff)

Objectivo: confirmar que, após centralizar o critério, a Equipa e Permissões
continuam corre­ctas e consistentes entre si — e que nada regrediu.

## Pré-requisitos
- v12.30.2 instalada (ver `docs/deploy/DEPLOY-v12-30-2.md`).
- Acesso a Equipa (RH) e a Permissões e Perfis.
- De preferência, o colaborador do caso reportado (perfil actual ≠ papel WP).

## Casos

### 1. Equipa ↔ Permissões continuam a coincidir
- ESPERADO: os colaboradores com perfil SIGE aparecem nas duas páginas
  (exceto alunos/encarregados). Igual à v12.30.1 — agora via fonte única.

### 2. Permissões consistente com a sua coluna "Perfil SIGE actual"
- ESPERADO: todos os utilizadores que mostram um "PERFIL SIGE ACTUAL" preenchido
  estão na lista de Permissões (já não há quem apareça com perfil actual mas
  fora da lista, nem vice-versa por este critério).

### 3. Cargo e KPIs da Equipa por perfil actual
- ESPERADO: o colaborador com papel WP "guarda" e perfil actual "professor"
  continua a aparecer como **Professor** e a contar em **Docentes**.

### 4. Super admin e portal
- ESPERADO: super admin fora da Equipa; nenhum aluno/encarregado em qualquer das
  duas listas de staff.

### 5. Multi-tenant
- ESPERADO: cada página mostra apenas a escola activa.

### 6. Não-regressão de Permissões
- ESPERADO: atribuir/remover perfis, aplicar, e a contagem de "atribuídos"
  funcionam como antes.

### 7. Não-regressão da Equipa
- ESPERADO: acções por linha (reset senha, activar/desactivar, remover), arquivo
  de removidos e impressão de crachá funcionam.

## Resultado
- [ ] Caso 1 OK
- [ ] Caso 2 OK
- [ ] Caso 3 OK
- [ ] Caso 4 OK
- [ ] Caso 5 OK
- [ ] Caso 6 OK
- [ ] Caso 7 OK
