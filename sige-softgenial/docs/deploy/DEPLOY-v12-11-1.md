# Deploy - SIGE SoftGenial v12.11.1 - Module Gate Consistency PRO (TEST)

## Ambiente recomendado
Instalar primeiro apenas no ambiente de testes.

## Testes pós-instalação
1. Confirmar que o sistema mostra v12.11.1.
2. Abrir Centro de Configuração > Módulos activos.
3. Confirmar que Transporte está activo.
4. Confirmar que o item Transporte aparece no menu lateral.
5. Confirmar que o link abre `admin.php?page=sige-app&view=transporte`.
6. Testar utilizador Admin TI / Direcção / Secretaria, conforme aplicável.
7. Confirmar que utilizadores com apenas `transporte.ver` conseguem consultar, mas não criar/remover rotas.
8. Testar regressão rápida: Dashboard, Matriz Curricular, Pagamentos, Financeiro, Notas e Pautas.

## Explicação técnica
Antes, uma parte do sistema chamava o módulo de transporte de `transporte`, outra chamava de `logistica`, e a tabela local usava `mod_transporte`. Como o menu exigia `logistica`, o Hub podia dizer que Transporte estava activo e mesmo assim o menu não aparecia.

A v12.11.1 introduz normalização central: o sistema passa a converter aliases antigos para a chave canónica antes de decidir o que aparece no menu.
