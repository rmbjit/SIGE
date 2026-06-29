# Migracao e Rollback - v12.15.5 - Design System PRO Diagnostic Baseline

## Migracao
1. Instalar o ZIP sobre v12.15.4.
2. Limpar cache do navegador e qualquer cache de servidor/plugin.
3. Confirmar em WordPress que a versao apresentada e 12.15.5.
4. Executar teste rapido de sanidade: Dashboard, Alunos, Financeiro, Portaria e um documento financeiro.

## Impacto esperado
Nao deve haver mudanca visual perceptivel. Esta versao adiciona documentos e ferramentas de diagnostico, actualiza versionamento e integra gates.

## Rollback
Se for necessario reverter, reinstalar v12.15.4. Como esta versao nao altera base de dados nem layout de producao, o rollback e directo.

## Validacao apos rollback
- Confirmar versao 12.15.4.
- Confirmar Portaria/Leitor QR.
- Confirmar Alunos.
- Confirmar documentos e pagamentos.
