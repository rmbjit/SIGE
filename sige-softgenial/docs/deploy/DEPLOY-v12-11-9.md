# DEPLOY - SIGE SoftGenial v12.11.9

## Escopo
Hardening do módulo Equipa e Professores (`&view=equipe`).

## Antes de instalar
1. Fazer backup completo dos ficheiros e da base de dados.
2. Instalar primeiro em ambiente de teste.
3. Confirmar que o utilizador administrador tem acesso real ao WordPress.

## Smoke test automatizado
```bash
php tools/smoke-equipe-professores-v12-11-9.php
```

## Smoke test funcional recomendado
1. Abrir `&view=equipe` como administrador.
2. Criar colaborador normal.
3. Editar colaborador existente e confirmar que dados bancários/documentos carregam apenas após abrir o modal.
4. Tentar exportar folha salarial.
5. Resetar senha de um colaborador da escola actual.
6. Activar/desactivar colaborador da escola actual.
7. Confirmar que perfil sem `rh.equipe_gerir` vê a página sem botões de gestão.
8. Confirmar que tentativa de atribuir `sige_admin_ti` por utilizador não-administrador é bloqueada.

## Áreas não alteradas
Financeiro, académico, WhatsApp, notificações, portal, pautas, DEC, boletins e fórmulas canónicas.
