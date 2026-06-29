# Cenarios de teste live - SIGE SoftGenial v12.12.30
Fase 9 incremento 3: Sobreposicao reversivel do papel WordPress

Objectivo: confirmar, num ambiente real, que atribuir e remover um perfil SIGE preserva e repoe o papel WordPress original, que ninguem fica preso num papel sige_*, e que o administrador WordPress nunca e tocado.

Preparacao: tenha um utilizador comum (U) com um papel WordPress conhecido (por exemplo, subscritor) e acesso de administrador para atribuir e remover perfis.

## 1. Atribuir preserva o original
- Anote o papel WordPress actual de U (por exemplo, subscritor).
- Atribua a U um perfil SIGE (por exemplo, secretaria).
- Esperado: U passa ao papel sige_* correspondente, como antes. Internamente, o papel original (subscritor) fica guardado numa copia.

## 2. Remover repoe o original
- Remova o perfil SIGE de U.
- Esperado: U volta ao papel WordPress original (subscritor); a mensagem confirma que o papel original foi reposto.

## 3. Reatribuir nao perde o original
- Atribua de novo um perfil SIGE a U, depois um perfil SIGE diferente (sem remover entre eles).
- Remova o perfil.
- Esperado: U volta ao papel original (subscritor), e nao a um papel intermedio. A copia do original e preservada uma unica vez.

## 4. Multiplos papeis originais
- (Se aplicavel.) Um utilizador com mais do que um papel WordPress (por exemplo, editor e autor) que recebe e depois perde um perfil SIGE.
- Esperado: ao remover, recupera os varios papeis originais.

## 5. Remocao fora da interface (auto-cura)
- (Tecnico.) Se o perfil de um utilizador for desactivado por outra via (por exemplo, directamente na base de dados), o utilizador pode ficar momentaneamente num papel sige_*.
- Esperado: na sessao seguinte desse utilizador, o sistema repoe o papel original sozinho (auto-cura na sincronizacao do init).

## 6. Estado legado sem copia
- (Se tiver utilizadores ja em papeis sige_* de versoes anteriores.) Esses utilizadores nao tem copia do original.
- Esperado: ao serem removidos, sao repostos para o papel por omissao do WordPress (estado limpo), em vez de ficarem presos no papel sige_*.

## 7. Administrador WordPress intocado
- Confirme que um administrador do sistema nunca tem o papel alterado por este modulo, nem ao receber nem ao perder um perfil SIGE (caso de teste).
- Esperado: o papel de administrador permanece inalterado.

## Resultado esperado global
- Atribuir um perfil preserva o papel WordPress original; remover repoe-o.
- Ninguem fica preso num papel sige_* depois de o perfil sair.
- O administrador WordPress real nunca e tocado.
- Os menus continuam a funcionar enquanto o perfil esta activo.
