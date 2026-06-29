# Cenarios de teste live - SIGE SoftGenial v12.12.28
Fase 9 incremento 1: Blindagem do modulo de permissoes

Objectivo: confirmar, num ambiente real, que o modulo de Perfis e Permissoes nao permite trancar nem despromover contas privilegiadas, nao permite escalada, e que a porta do menu fica coerente.

Preparacao: tenha a mao tres contas - (A) um administrador WordPress real, (B) um admin TI (perfil SIGE admin_ti) e (C) um utilizador comum (por exemplo, secretaria). Entre uma vez como administrador apos a actualizacao para a migracao correr.

## 1. Conta protegida aparece so de leitura
- Como A, abra Configuracoes > Perfis e Permissoes.
- Na lista, a conta A (e qualquer outro administrador WordPress) aparece com o selo Protegido e a nota de que e gerida pelo WordPress.
- Esperado: nessa linha NAO ha selector de perfil nem botoes Aplicar/Remover.

## 2. Conta protegida nao e gerivel nem por outro administrador
- (Se tiver dois administradores WordPress.) Como A, tente actuar sobre o outro administrador.
- Esperado: a linha continua so de leitura; nao ha como atribuir nem remover. O desenho nao oferece a accao.

## 3. Admin TI ja ve o menu
- Inicie sessao como B (admin TI).
- Esperado: o menu Configuracoes mostra Perfis e Permissoes, e a pagina abre. (Saude do Sistema e Centro de Configuracao NAO aparecem para B.)

## 4. Anti-escalada
- Como B, na linha do utilizador C, escolha um perfil que confira a gestao de permissoes (por exemplo, direccao geral) e carregue em Aplicar.
- Esperado: a operacao e recusada com mensagem de que nao pode atribuir um perfil que confere a gestao de permissoes; nada muda. O bloqueio fica registado na auditoria.
- Em seguida, como B, atribua a C um perfil normal (por exemplo, secretaria).
- Esperado: funciona normalmente.

## 5. Auto-proteccao (nao se despromove nem remove a si proprio)
- Como B, na sua propria linha, tente Remover o perfil.
- Esperado: recusado, com mensagem de que nao pode remover o seu proprio perfil de gestao.
- Como B, tente atribuir a si proprio um perfil sem gestao (por exemplo, secretaria).
- Esperado: recusado, com mensagem de que nao pode despromover-se da gestao de permissoes.

## 6. Ultimo gestor
- Cenario: deixe apenas um utilizador nao-administrador com perfil de gestao de permissoes na escola. Como B (ou outro gestor), tente remover esse ultimo gestor.
- Esperado: recusado, com mensagem de que a accao deixaria a escola sem nenhum gestor; e sugerido atribuir o perfil a outro utilizador primeiro.
- Nota: um administrador WordPress real tem autoridade plena e nao e travado por esta guarda (continua a poder gerir tudo e nunca fica trancado).

## 7. Administrador mantem autoridade plena
- Como A (administrador WordPress), atribua e remova perfis a utilizadores nao protegidos, incluindo perfis de gestao.
- Esperado: tudo funciona; A pode delegar a gestao a quem entender (mintar gestores), porque e administrador real.

## 8. Bloqueio mesmo com pedido forjado
- (Tecnico.) O bloqueio nao esta so na interface: esta no tratamento do pedido, que consulta o avaliador antes de qualquer escrita. Um POST direccionado a uma conta protegida ou de escalada e recusado e auditado, mesmo com nonce valido.
- Esperado: nenhuma alteracao na base de dados nesses casos; entrada de auditoria com a accao bloqueada.

## Resultado esperado global
- Nenhuma conta protegida e gerivel pelo modulo.
- Nenhuma escalada de privilegios pelo modulo.
- Nenhum gestor se tranca a si proprio, nem a escola fica sem gestor.
- O admin TI ve e usa o modulo, sob as guardas; Saude do Sistema e Centro de Configuracao continuam reservados ao administrador do sistema.
