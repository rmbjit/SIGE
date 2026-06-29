# LIVE-TEST Fundação v12.19.2 - kit alinhado à marca roxa

Objetivo: confirmar que os componentes do kit `.sgk-*` passaram a roxo, sem
qualquer regressão funcional, de número ou de layout. Validar em staging.

## Pré-condições

- Versão do plugin no ecrã: 12.19.2.
- Cache limpa; recarregar com Ctrl+F5.

## O que mudou (esperado)

| Elemento | Antes | Agora |
|---|---|---|
| Botão primário `.sgk-btn-primario` | Fundo navy | Fundo roxo (#7c3aed) |
| Hover do primário | Navy escuro | Roxo escuro (#4c10b2) |
| Anel de foco `.sgk-foco` | Navy translúcido | Roxo translúcido |
| Banners/badges ok/aviso | Igual | Igual (sem mudança) |
| Erro/info/aviso | Igual | Igual (sem mudança) |

## Cenários

1. **Botões do kit**: abrir ecrãs que usam `.sgk-btn-primario` (modais de
   confirmação, ações do kit). Confirmar fundo roxo e texto legível.
2. **Foco por teclado**: dar Tab até um botão do kit. O anel de foco deve ser
   roxo e bem visível.
3. **Modais e toasts**: disparar `sigeUi.confirm` e um toast. Cor e contraste
   coerentes; nada partido.
4. **Estados ok/erro/aviso/info**: confirmar que banners e badges destes estados
   mantêm exactamente as cores anteriores (não foram tocados).
5. **Coerência de marca**: comparar um ecrã do kit com um ecrã já roxo
   (ex.: views académicas). A marca deve ser a mesma tonalidade.

## Anti-regressão (tem de continuar verdadeiro)

- Nenhum número, saldo, total ou contagem muda.
- Nenhum botão deixa de funcionar; nenhum handler partido.
- Sem novos `onclick` inline; CSP intacta (sem violações `script-src`).
- Layout idêntico (só a cor muda).
- Gate de design tokens: 1934/1934, sem regressão.

## Clientes a cobrir

cicasacolorida, lmuhalaze, malisa, demo, teste. A camada visual é igual para
todos; confirmar em pelo menos dois.
