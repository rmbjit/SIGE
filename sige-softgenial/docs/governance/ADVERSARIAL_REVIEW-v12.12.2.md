# Rediagnostico adversarial v12.12.2

## Versao auditada

SIGE SoftGenial v12.12.2 - Extracto de Divida Detalhado para Encarregados.

## Premissa adversarial

Foi assumido que a entrega poderia conter falhas escondidas em: formula financeira, tenant isolation, autorizacao documental, side effects, regressao funcional, gates falsamente verdes, nomenclatura confusa e documentos incompletos para encarregados.

## Evidencias executadas

- `php tools/smoke-devedores-extracto-detalhado-v12-12-2.php` - 25 verificacoes OK.
- `php tools/run-gates.php` - 28/28 gates verdes.
- `find . -name '*.php' -print0 | xargs -0 -n1 -P8 php -l` - 332 ficheiros PHP, 0 falhas.
- Evidencias arquivadas em `docs/qa/GATES-v12.12.2.txt`, `docs/qa/PHP-LINT-v12.12.2.json` e `docs/qa/QA-SMOKE-v12-12-2-extracto-divida-detalhado.md`.

## Itens do Phase Charter verificados

| Item | Estado | Observacao |
|---|---|---|
| Documento de divida detalhado | OK | `sige_gerar_html_factura()` passa a emitir `EXTRATO DE DÍVIDA DETALHADO`. |
| Formula canonica | OK | Helper usa `sige_fin_total_lancamento()` e `sige_fin_saldo_lancamento()` quando disponiveis. |
| Decomposicao por item | OK | Base/propina, transporte, extras, multa, descontos, desconto especial, pago e saldo. |
| Status `em_plano` | OK | Query inclui `pendente`, `parcial` e `em_plano`. |
| Pagamentos abatidos | OK | Consulta agrupada por `lancamento_id` com recibos e ultimo pagamento. |
| Sem side effects | OK | Nao chama recalculadores e nao escreve na base de dados. |
| Tenant | OK para o escopo | Queries do documento usam `aluno_id` e `escola_id`; fallbacks perigosos tocados foram removidos. |
| Botoes claros | OK | Central de Devedores usa `Extracto dívida` e `Histórico`. |
| Nonce em links novos | OK | Links da Central de Devedores usam `wp_nonce_url()`. |

## Achados P0

- Nenhum P0 conhecido apos a implementacao e validacao.

## Achados P1

- Nenhum P1 conhecido apos a implementacao e validacao.

## Achados P2

### P2-001 - Enforcement global de nonce em documentos adiado

Nonce foi adicionado aos links gerados pela Central de Devedores, mas o enforcement obrigatorio em todos os documentos impressos nao foi activado nesta versao para evitar quebrar links legados, portal, emails ou rotas historicas. Deve ser tratado no Security Kernel.

### P2-002 - Validacao funcional com dados reais ainda necessaria

Os gates provam a estrutura e a ausencia de regressao estatica, mas a escola deve validar em staging com alunos que tenham: propina, transporte, extras, multa, desconto normal, desconto especial, pagamento parcial, plano e pagamentos abatidos.

### P2-003 - Motor documental ainda e legado

A rota interna continua a chamar-se `factura` por compatibilidade historica. A renomeacao interna completa deve ser feita numa fase de refactor documental para evitar quebra de links.

## Achados P3

### P3-001 - Documento ainda usa janela de impressao HTML

A entrega melhora o conteudo do documento, mas nao muda a arquitectura para PDF server-side. Isto e aceitavel no escopo, pois a fase excluiu introducao de biblioteca nova de PDF.

## Falhas escondidas procuradas e resultado

| Risco procurado | Resultado |
|---|---|
| Formula parcial antiga sem extras/desconto especial | Nao encontrado no novo extracto detalhado. |
| `em_plano` esquecido | Nao encontrado; status incluido. |
| Query sem `escola_id` no documento tocado | Nao encontrado no caminho novo. |
| Escrita/recalculo durante impressao | Nao encontrado. |
| Pagamento parcial invisivel ao encarregado | Mitigado; mostra `Já pago`, recibos e ultimo pagamento. |
| Botoes com semantica antiga `Factura`/`Extracto` | Mitigado na Central de Devedores. |
| Gate especifico ausente | Mitigado; gate incluido em `tools/run-gates.php`. |
| Gate JS instavel no ambiente | Mitigado; corredor executa o verificador JS por lotes isolados, mantendo `tools/check-js-views.php` como fonte. |

## Decisao final

- P0 aberto: 0.
- P1 aberto: 0.
- P2 aberto: 3.
- P3 aberto: 1.

A versao pode ser considerada concluida para o escopo aprovado e deve seguir para validacao em staging, nao directamente para producao sem teste com dados reais.
