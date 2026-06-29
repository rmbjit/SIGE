# Cenarios de teste live - SIGE SoftGenial v12.12.32
Fase 9 incremento 5: Sobreposicao puramente aditiva por capacidades

Objectivo: confirmar, num ambiente real, que o modulo deixou de substituir o papel WordPress, que as capacidades vem do filtro (mesmo efeito de antes), que os colaboradores legados sao repostos ao papel original, que a proteccao do Admin TI se mantem, e que os fluxos de portal e o administrador real ficam intactos.

Preparacao: um administrador WordPress real (A), um colaborador de teste (C) num papel WordPress comum (por exemplo, Assinante), e, se possivel, um colaborador legado (L) que ja estivesse num papel sige_* antes desta versao.

## 1. Atribuir perfil nao troca o papel WordPress
- Como A, atribua a C um perfil de staff (por exemplo, Professor) no ecra de Perfis e Permissoes.
- Esperado: C passa a ver as areas do perfil Professor.
- Na gestao de utilizadores do WordPress, o papel de C continua a ser o original (Assinante), NAO sige_professor.

## 2. As capacidades vem do filtro
- Ainda como C, confirme que as areas e menus do perfil aparecem normalmente.
- Esperado: o acesso e o mesmo de antes desta versao, apesar de o papel WordPress nao ter mudado.

## 3. Colaborador legado e reposto ao papel original
- Para L (que estava num papel sige_*), peca-lhe para iniciar sessao uma vez.
- Esperado: o papel WordPress de L volta ao original (ou ao papel por omissao, se nao havia original guardado), sem perder o acesso as areas do perfil. Limpeza unica.

## 4. Proteccao do Admin TI mantem-se
- De a C o perfil de Admin TI. Como um gestor que NAO seja administrador WordPress real, tente editar ou remover C.
- Esperado: bloqueado. So um administrador WordPress real edita ou remove um Admin TI.

## 5. Remover perfil retira o acesso
- Como A, remova o perfil de C.
- Esperado: C deixa de ver as areas do perfil. O papel WordPress de C permanece o original (ou e reposto, no caso legado).

## 6. Contas de aluno e encarregado intactas
- Confirme que uma conta de aluno e uma de encarregado continuam a funcionar como antes (login, portal).
- Esperado: sem alteracao; estes fluxos sao proprios e fora do ambito de staff.

## 7. Administrador real intacto
- Confirme que A continua com acesso total.
- Esperado: o filtro nao afecta administradores WordPress reais.

## 8. Isolacao por escola inalterada
- Num ambiente com mais de uma escola, confirme que cada colaborador continua a ver apenas os dados da sua escola.
- Esperado: a isolacao de dados por escola e separada das capacidades e nao muda.

## Resultado esperado global
- O papel WordPress dos colaboradores nunca e alterado pelo modulo.
- As capacidades vem do filtro, com o mesmo efeito de antes.
- Colaboradores legados sao repostos ao papel original sem perder acesso.
- A proteccao do Admin TI, os fluxos de portal e o administrador real ficam intactos.
