# Migração e Rollback - v12.15.4 - Standalone UI/CSP Recovery

## Migração

1. Instalar o ZIP v12.15.4 por cima da v12.15.3.
2. Limpar cache do navegador e qualquer cache do servidor/CDN.
3. Entrar no SIGE como administrador/direcção.
4. Validar primeiro a Portaria Digital / Leitor de QR.
5. Validar documentos e popups de impressão.

## Rollback

Se houver regressão crítica:

1. Repor v12.15.3.
2. Limpar cache.
3. Confirmar que o shell principal e o módulo de alunos voltaram ao estado anterior.
4. Manter nota de qual página autónoma falhou para patch específico.

## Observação

Rollback para v12.15.0-v12.15.2 não é recomendado, pois essas versões continham regressões visuais severas já confirmadas.
