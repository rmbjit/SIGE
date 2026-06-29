# Notas de Migração e Rollback - v12.14.0 - Front-end Security & CSP Enforcement

## Migração
1. Fazer backup completo dos ficheiros do plugin e da base de dados.
2. Instalar o ZIP v12.14.0 em staging primeiro.
3. Limpar cache do WordPress, navegador, CDN/proxy se existir.
4. Entrar no WordPress como utilizador administrativo.
5. Abrir o SIGE e confirmar que o header `Content-Security-Policy` aparece no shell admin.
6. Testar navegação e acções críticas listadas no QA.

## Validação mínima pós-instalação
- Financeiro → Pagamentos: abrir recibo e confirmar botões principais.
- Financeiro → Extratos: confirmar botões de caixa e recibos.
- Académico → Alunos/Turmas/Disciplinas: confirmar botões de edição/visualização.
- HR/Equipe: confirmar edição/crachás/fecho de toast.
- Login e portal: confirmar layout sem dependência de Google Fonts.

## Rollback
1. Desactivar o plugin v12.14.0.
2. Repor o ZIP anterior validado: v12.12.64.
3. Limpar cache.
4. Confirmar que o SIGE volta a abrir normalmente.

## Nota de segurança
Rollback remove o CSP enforcement do shell admin e volta ao comportamento anterior. Deve ser usado apenas se staging detectar quebra funcional bloqueadora.
