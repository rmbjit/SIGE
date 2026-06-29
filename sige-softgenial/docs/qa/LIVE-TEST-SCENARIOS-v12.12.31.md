# Cenarios de teste live - SIGE SoftGenial v12.12.31
Fase 9 incremento 4: Niveis de perfil editaveis

Objectivo: confirmar, num ambiente real, que o dono edita a hierarquia de niveis, que a edicao e exclusiva do administrador WordPress real, que os niveis editados sao respeitados imediatamente, e que sem desvios vale o valor por omissao.

Preparacao: um administrador WordPress real (A) e um gestor que nao seja administrador (B, por exemplo admin TI).

## 1. O dono ve e usa o painel
- Como A, abra Perfis e Permissoes e role ate ao painel Hierarquia de niveis.
- Esperado: cada perfil activo aparece com o seu nivel num campo numerico (0 a 100), e ha um botao Guardar niveis.

## 2. Gestor nao ve o painel
- Inicie sessao como B (admin TI).
- Esperado: B NAO ve o painel Hierarquia de niveis.

## 3. Editar persiste e tem efeito imediato
- Como A, suba um perfil (por exemplo, admin TI de 80 para 85) e guarde.
- Esperado: mensagem de sucesso; o desvio fica guardado.
- Confirme o efeito: as accoes e o selector de perfil passam a respeitar o novo nivel imediatamente (por exemplo, um admin TII a 85 passa a poder atribuir perfis ate 84).

## 4. Repor ao valor por omissao remove o desvio
- Como A, reponha o nivel original (admin TI de volta a 80) e guarde.
- Esperado: o desvio e removido; o nivel volta ao valor por omissao. (Internamente, a opcao deixa de conter esse perfil.)

## 5. Validacao 0 a 100
- Como A, tente gravar um nivel fora do intervalo (por exemplo, 250 ou um valor negativo, se o conseguir submeter).
- Esperado: o valor e limitado a 0..100; entradas invalidas (nao numericas) sao ignoradas.

## 6. Gestor nao consegue gravar nem por pedido forjado
- (Tecnico.) Mesmo que B forme um pedido save_niveis sem ver o painel, o handler verifica que o autor e administrador WordPress real antes de gravar.
- Esperado: recusado, com registo na auditoria; nada e alterado.

## 7. Sem desvios, comportamento de antes
- Numa instalacao sem desvios guardados, confirme que os niveis sao exactamente os valores por omissao (os do incremento da hierarquia).
- Esperado: o avaliador e o selector comportam-se como antes desta versao.

## Resultado esperado global
- So o administrador WordPress real edita a hierarquia de niveis.
- Os niveis editados persistem e sao respeitados imediatamente pelo avaliador e pelo selector.
- Repor ao valor por omissao remove o desvio.
- Sem desvios, vale o mapa por omissao.
