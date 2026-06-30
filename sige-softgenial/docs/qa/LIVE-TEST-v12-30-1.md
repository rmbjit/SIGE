# LIVE-TEST - SIGE SoftGenial v12.30.1 (Equipa: paridade com Permissões)

Objectivo: confirmar que a Equipa coincide com a página de Permissões (quem tem
perfil SIGE), que o cargo e os KPIs seguem o perfil SIGE actual, e que o super
admin nunca aparece.

## Pré-requisitos
- v12.30.1 instalada (ver `docs/deploy/DEPLOY-v12-30-1.md`).
- Pelo menos um colaborador cujo **perfil SIGE actual** difere do papel WordPress
  legado (ex.: papel WP guarda, perfil actual Professor) — o caso reportado.

## Casos

### 1. Paridade Equipa ↔ Permissões (o bug principal)
- Abrir **Permissões e Perfis** e anotar quem tem "PERFIL SIGE ACTUAL"
  preenchido (ignorar alunos/encarregados).
- Abrir **Equipa**.
- ESPERADO: exactamente os mesmos colaboradores aparecem na Equipa. Ninguém com
  perfil SIGE fica de fora (inclui o caso com r.ativo do papel a 0).

### 2. Cargo segue o perfil SIGE actual
- Para o colaborador com papel WP ≠ perfil actual (ex.: guarda vs professor).
- ESPERADO: na Equipa o **cargo** mostra o perfil actual (Professor), igual ao
  que Permissões indica — não o papel WP legado.

### 3. KPIs por perfil actual
- ESPERADO: esse colaborador conta na categoria do perfil actual (Docentes), não
  na do papel WP (Apoio). Total/Docentes/Admin/Apoio batem com a lista.

### 4. Super admin fora
- ESPERADO: o administrador WordPress (super admin) NÃO aparece, mesmo com perfil
  SIGE. O Admin TI (sige_admin_ti) continua a aparecer.

### 5. Portal e multi-tenant
- ESPERADO: nenhum aluno/encarregado na Equipa; só a escola activa.

### 6. Não-regressão
- Acções por linha (reset senha, activar/desactivar, remover) funcionam.
- Arquivo de removidos continua consultivo.
- Impressão de crachá continua a funcionar.

## Resultado
- [ ] Caso 1 OK
- [ ] Caso 2 OK
- [ ] Caso 3 OK
- [ ] Caso 4 OK
- [ ] Caso 5 OK
- [ ] Caso 6 OK
