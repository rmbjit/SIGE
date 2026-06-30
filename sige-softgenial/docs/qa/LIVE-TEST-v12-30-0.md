# LIVE-TEST - SIGE SoftGenial v12.30.0 (Equipa: lista completa + KPIs + sem SUPER admin)

Objectivo: confirmar que a lista de Equipa mostra toda a equipa com perfil SIGE,
que os KPIs batem certo e que o super admin nunca aparece.

## Pré-requisitos
- v12.30.0 instalada (ver `docs/deploy/DEPLOY-v12-30-0.md`).
- Acesso a: Equipa (RH) e Permissões e Perfis.
- De preferência, uma escola onde exista pelo menos um colaborador cujo perfil
  SIGE foi atribuído via Permissões (tabela de perfis) — o caso que antes ficava
  invisível.

## Casos

### 1. Lista completa (o bug principal)
- Abrir **Permissões e Perfis** e anotar os colaboradores STAFF listados
  (ignorar alunos/encarregados).
- Abrir **Equipa**.
- ESPERADO: todos esses colaboradores aparecem também na Equipa. Ninguém com
  perfil SIGE fica de fora.

### 2. Super admin fora da Equipa
- Identificar o utilizador administrador WordPress (super admin / role
  `administrator`).
- ESPERADO: NÃO aparece na lista de Equipa, mesmo que também tenha um perfil SIGE.
- NOTA: um utilizador com perfil **Admin TI (sige_admin_ti)** — que não é o
  administrador WP nativo — DEVE continuar a aparecer.

### 3. KPIs correctos
- Conferir os cartões: Total Colaboradores, Docentes (Admin/Apoio), Folha
  Salarial, Activos/Inactivos.
- ESPERADO: os números batem com a lista visível (e já incluem os colaboradores
  que antes estavam em falta; já não contam o super admin).

### 4. Sem alunos/encarregados
- ESPERADO: nenhum aluno ou encarregado aparece na Equipa (papéis de portal).

### 5. Isolamento por escola (multi-tenant)
- Se houver mais de uma escola, confirmar que a Equipa mostra só a escola activa.
- ESPERADO: sem colaboradores de outras escolas.

### 6. Não-regressão
- Acções por linha (reset senha, activar/desactivar, remover) continuam a
  funcionar.
- Arquivo de removidos continua acessível e apenas consultivo.

## Resultado
- [ ] Caso 1 OK
- [ ] Caso 2 OK
- [ ] Caso 3 OK
- [ ] Caso 4 OK
- [ ] Caso 5 OK
- [ ] Caso 6 OK
