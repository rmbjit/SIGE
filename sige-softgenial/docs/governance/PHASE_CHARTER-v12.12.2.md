# PHASE CHARTER v12.12.2 - Extracto de Divida Detalhado para Encarregados

## Objectivo
Responder ao pedido urgente de cliente sem quebrar a baseline de governacao v12.12.1, melhorando o documento de devedores para que pais e encarregados entendam a decomposicao da divida.

## Incluido
- Melhorar `?sige_print=factura` em `includes/documents-engine.php`, que passa a imprimir `EXTRATO DE DÍVIDA DETALHADO`.
- Mostrar base/propina, transporte, extras, multa efectiva, desconto, desconto especial, ja pago e saldo por item.
- Usar `sige_fin_total_lancamento` e `sige_fin_saldo_lancamento` quando disponiveis.
- Incluir status `pendente`, `parcial` e `em_plano`.
- Mostrar pagamentos abatidos por lancamento, recibos e ultimo pagamento quando existirem.
- Renomear os botoes da Central de Devedores para `Extracto dívida` e `Histórico`.
- Adicionar nonce aos links documentais gerados pela Central de Devedores, sem enforcement global ainda.
- Criar smoke test dedicado e integra-lo em `tools/run-gates.php`.

## Excluido
- Alteracao de schema.
- Financial Ledger.
- Enforcement global de nonce em todos os documentos.
- Reescrita completa do motor documental.
- Alteracao de M-Pesa/e-Mola, recibos, pagamentos ou regras globais de lancamento.
- CSP enforcement e refactor modular.

## Riscos
- P1: divergencia financeira se o documento voltar a usar formula parcial.
- P1: omissao de `em_plano` no documento de divida.
- P2: excesso de largura visual se a decomposicao for feita em muitas colunas.
- P2: links antigos sem nonce continuam aceites por compatibilidade ate Security Kernel.

## Criterios de aceitacao
- Documento mostra decomposicao financeira suficiente por item.
- Documento usa formula canonica quando disponivel.
- Todas as queries novas usam `aluno_id` e `escola_id`.
- Documento nao escreve na base de dados e nao chama recalculadores com side effects.
- PHP lint verde.
- `php tools/run-gates.php` verde.
- Rediagnostico adversarial sem P0/P1 aberto.
