# Rediagnóstico Adversarial - v12.15.3

## Falha assumida
As versões v12.15.0-v12.15.2 tentaram corrigir consistência visual por uma camada global que classificava automaticamente botões, campos, tabelas e superfícies. A abordagem passou nos gates CLI, mas falhou no browser porque interferiu com componentes customizados.

## Causa raiz
- Falta de validação visual real por viewport antes da entrega.
- CSS/JS global demasiado ambicioso.
- Tentativa de resolver regressões com mais CSS, em vez de voltar à última base estável.

## Correcção aplicada
- Quarentena do Design System PRO global.
- Retorno à base v12.14.4 aprovada.
- Preservação apenas da correcção pontual de pagamento.
- Criação de smoke contra reintrodução do enqueue global.

## Riscos residuais
- A fase Design System PRO ainda não está concluída; ela foi estabilizada, não finalizada.
- A evolução visual futura deve ser feita por módulo, com screenshots comparativos desktop/tablet/mobile antes de fechar versão.
