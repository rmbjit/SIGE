# QA / Definition of Done - v12.14.2 User Security & Privileged Role Integrity

## Definition of Done
- [x] Phase Charter criado.
- [x] Inventário técnico concluído antes da implementação.
- [x] Plano fechado convertido em checklist.
- [x] Matriz de rastreabilidade preenchida.
- [x] Permissões e tenant isolation verificados onde aplicável.
- [x] Auditoria estruturada implementada para role changes e bloqueios.
- [x] Sem novo fallback fail-open em área crítica.
- [x] Sem duplicação desnecessária de lógica de negócio: guarda central criada.
- [x] Gates/lint/smoke executados.
- [x] Regressão crítica dos módulos afectados verificada por análise estática e lint.
- [x] CHANGELOG actualizado.
- [x] BUILD.json actualizado.
- [x] Notas de migração e rollback escritas.
- [x] Rediagnóstico adversarial executado.
- [x] Riscos residuais declarados.
- [x] Zero bloqueadores P0/P1 conhecidos.

## Testes manuais recomendados em staging
1. Tentar 6+ logins falhados contra o mesmo username usando IPs/abas diferentes e confirmar bloqueio por username.
2. Confirmar que login com username inexistente e password errada devolvem mensagem genérica.
3. Abrir `/?author=1` e confirmar redirecionamento para login.
4. Aceder `/wp-json/wp/v2/users` desautenticado e confirmar que não expõe utilizadores.
5. Confirmar que `xmlrpc.php` fica inutilizável salvo `SIGE_XMLRPC_ALLOW`.
6. Tentar despromover `Admin TI` por utilizador não-WP-admin e confirmar bloqueio.
7. Confirmar que administrador WordPress real consegue gerir perfis privilegiados quando necessário.
8. No caso Edilson/Edmilson, verificar perfil activo SIGE em Perfis e Permissões e não apenas papel WordPress nativo.
