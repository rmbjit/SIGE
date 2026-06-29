# Rediagnóstico Adversarial - v12.14.3

## Achado 1 - Desaparecimento pode ser UI, não invasão
A UI antiga listava principalmente WP roles de staff. Com a arquitectura aditiva, uma conta podia manter perfil SIGE activo e aparecer como `subscriber` no WordPress; nesse caso desaparecia da UI apesar de manter permissões efectivas.

## Achado 2 - Se o perfil SIGE também foi removido, não dá para adivinhar retroactivamente
Sem snapshot anterior, o sistema não pode saber com certeza que a conta devia ser Admin TI. Por isso foi adicionada recuperação break-glass auditada.

## Achado 3 - Downgrade futuro deve ser reversível
A partir desta versão, perfis privilegiados autorizados criam snapshot. Se desaparecerem sem aposentação autorizada, o reconciliador repõe o estado.

## Riscos residuais
- Investigação forense completa depende de logs externos ao plugin.
- Alterações directas no banco antes desta versão não têm snapshot retroactivo.
- O espelho WP acrescenta role crítico e preserva roles existentes, podendo deixar múltiplos roles visíveis no WordPress.
