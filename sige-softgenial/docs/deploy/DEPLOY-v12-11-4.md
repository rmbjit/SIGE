# Deploy - SIGE SoftGenial v12.11.4 Permission Matrix Enforcement PRO (TEST)

Ambiente recomendado: teste.

## Depois de instalar
1. Fazer Ctrl+F5 no navegador.
2. Abrir Centro de Configuração > Permissões.
3. Seleccionar o perfil Professor.
4. Confirmar/atribuir:
   - academico.dec_ver
   - academico.dec_emitir
   - academico.actas_ver
   - academico.actas_emitir
5. Entrar com um utilizador Professor vinculado a esse perfil.
6. Confirmar que DEC e ACTA aparecem no menu lateral e abrem por URL directa.
7. Remover uma dessas permissões e confirmar que deixa de aparecer/abrir.

## Nota técnica
A matriz SIGE passa a ser soberana também em handlers/admin-post que usam sige_user_can_any_secure(). O fallback por WP role só actua quando o utilizador ainda não tem perfil SIGE activo.
