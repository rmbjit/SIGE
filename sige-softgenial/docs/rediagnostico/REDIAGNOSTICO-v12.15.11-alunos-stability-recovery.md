# Rediagnóstico Adversarial - v12.15.11

## Problema confirmado
A página de Alunos ficou lenta/sem resposta após tentativas de corrigir modais movendo elementos no DOM e adicionando dispatcher/observer.

## Causa provável
A solução anterior aumentou o acoplamento com handlers existentes da página e introduziu observação/reorganização dinâmica desnecessária.

## Decisão técnica
Rollback selectivo para a base aprovada v12.15.8 e correcção de modal exclusivamente por CSS scoped.

## Riscos residuais
- Ainda exige validação visual em browser real.
- A arquitectura antiga de modais permanece, mas está funcional e menos arriscada.
