# Cenarios de teste live - SIGE SoftGenial v12.12.29
Fase 9 incremento 2: Hierarquia de perfis por nivel

Objectivo: confirmar, num ambiente real, que um gestor so atribui e mexe em perfis abaixo do seu nivel, que a garantia do Incr 1 (so o dono cria gestores) se mantem, e que a interface so oferece o que e atribuivel.

Preparacao: tenha a mao um administrador WordPress real (A), um admin TI (B, nivel 80), uma direccao geral (C, nivel 90) e um utilizador comum (D, por exemplo professor, nivel 30).

## 1. O selector so mostra perfis atribuiveis
- Como B (admin TI), abra Configuracoes > Perfis e Permissoes e olhe para o selector de perfil de D.
- Esperado: nao aparecem os perfis de gestao (direccao geral, admin escola, admin TI) nem nenhum de nivel igual ou superior a 80. Aparecem director, secretaria, professor, etc.

## 2. Atribuir abaixo do nivel funciona
- Como B, atribua a D um perfil abaixo do nivel 80 (por exemplo, secretaria).
- Esperado: aplicado com sucesso.

## 3. Nao mexer em utilizador de nivel igual ou superior
- Como B, olhe para a linha de C (direccao geral, nivel 90).
- Esperado: a linha aparece so de leitura, com a nota de nivel superior ou igual; sem selector nem botoes.
- (Tecnico.) Mesmo um pedido forjado para atribuir ou remover o perfil de C e recusado pelo avaliador, com mensagem de nivel insuficiente e registo na auditoria.

## 4. A garantia do Incr 1 mantem-se (so o dono cria gestores)
- Como B, tente atribuir a D um perfil que confere gestao (se conseguir formar o pedido).
- Esperado: recusado por escalada de gestao, nao por nivel. Nenhum nao-administrador cria gestores.

## 5. Hierarquia entre gestores
- Como C (direccao geral, nivel 90), atribua a um admin TI (nivel 80) um perfil nao-gestor abaixo de 90 (por exemplo, director).
- Esperado: permitido (alvo abaixo, perfil nao-gestor abaixo). A direccao geral pode gerir um admin TI com perfis nao-gestores.
- Como C, tente atribuir admin escola (nivel 90, gestor) a alguem.
- Esperado: recusado por escalada de gestao (so o administrador WordPress real cria gestores), mesmo sendo C o topo da hierarquia SIGE.

## 6. Administrador WordPress mantem autoridade plena
- Como A, confirme que ve e pode atribuir todos os perfis, incluindo os de gestao, a qualquer utilizador nao protegido.
- Esperado: tudo funciona; A nao e limitado pela hierarquia.

## 7. Perfil personalizado
- (Se tiver perfis personalizados.) Um perfil personalizado fica no nivel 0.
- Esperado: so um actor de nivel acima de 0 o pode atribuir; se conferir gestao, a regra anti-escalada cobre-o.

## Resultado esperado global
- Cada gestor atribui e mexe so estritamente abaixo do seu nivel.
- So o administrador WordPress real cria gestores.
- O selector so oferece o atribuivel; linhas de nivel igual ou superior ficam so de leitura.
- O administrador WordPress mantem autoridade plena; contas protegidas continuam so de leitura.
