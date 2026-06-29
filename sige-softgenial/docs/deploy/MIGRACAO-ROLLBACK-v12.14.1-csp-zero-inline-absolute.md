# Migracao e rollback - v12.14.1

## Instalacao
1. Instalar o ZIP em staging.
2. Limpar cache de browser e cache WordPress, se aplicavel.
3. Abrir DevTools > Network > resposta HTML do SIGE.
4. Confirmar `Content-Security-Policy` com `script-src-attr 'none'` e `style-src-attr 'none'`.
5. Confirmar ausencia de `unsafe-inline`.
6. Testar views criticas: Dashboard, Alunos, Turmas, Financeiro/Pagamentos, Extratos, Devedores, Centros, HR, Portaria.

## Validacao de browser
- DevTools Console sem violacoes CSP criticas.
- Botoes com comportamentos historicos funcionam.
- Filtros `onchange` historicos continuam a submeter/calcular.
- Avatares/logos com fallback continuam adequados.

## Rollback
- Repor ZIP v12.14.0 se houver quebra visual/funcional critica.
- Como nao ha alteracao de schema, o rollback e apenas de ficheiros.
- Se o problema for handler especifico, preferir hotfix adicionando o padrao ao hidratador em `assets/sige-ui.js`.
