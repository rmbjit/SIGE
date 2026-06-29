# PHASE CHARTER - v12.12.7.1 Mapa de Cobranca em PDF

## Objectivo
Entregar, como tarefa extraordinaria sobre a baseline congelada v12.12.7, um botao definitivo na Central de Cobrancas (view=financeiro-devedores) que gera a lista completa de devedores da escola num documento pronto a imprimir e a guardar como PDF. A solucao e definitiva, segura e escalavel, sem dependencia de biblioteca de PDF no servidor.

## Incluido
- Novo query handler `sige_dev_print` (?sige_dev_print=lista) em `includes/finance-devedores-pdf.php`, despachado em template_redirect.
- Regra `query_handler:sige_dev_print` no Security Kernel em `enforce`, com nonce, permissao, tenant, rate limit e auditoria, e runtime antecipado multi-hook (admin_init, parse_request, template_redirect).
- Botao Imprimir lista (PDF) na Central de Cobrancas, com URL nonce e abertura em nova aba.
- Dataset canonico `sige_fin_devedores_dataset()` que replica exactamente a Central de Cobrancas: mesma populacao (alunos activos com matricula activa, o default do ecra) e divida calculada com `sige_fin_saldo_lancamento()` (a mesma funcao dos pagamentos) sobre os lancamentos em aberto, sem JOIN a matriculas (sem fan-out). O total do PDF e igual ao do ecra.
- Documento de impressao com cabecalho de escola, cartoes de resumo, tabela e total, CSS de impressao A4 com cabecalho repetido e linhas sem quebra.
- Smoke dedicado e manifesto, baselines, inventario e regras do Kernel regenerados para a versao.

## Excluido
- Biblioteca de PDF no servidor (mPDF/Dompdf/TCPDF): mantem-se o padrao HTML pronto a imprimir, coerente com boletim, pautas e despesas.
- O PDF reproduz a vista por defeito do ecra (alunos activos com matricula activa). Impressao com filtros explicitos diferentes do default (mes, centro, ou situacao 'todos'/'transferidos') fica para fase posterior.
- Tenant Isolation Hardening global (continua reservado para a v12.12.8).
- Qualquer alteracao a regras financeiras ou academicas aprovadas.

## Riscos
- P2: o PDF reproduz o default do ecra (activos); se o utilizador mudar o filtro de situacao para 'todos' ou 'transferidos', o PDF nao acompanha (mostra o default).
- P2: listas muito grandes dependem do motor de impressao do browser para paginar (mitigado por cabecalho repetido e linhas sem quebra).
- P3: contacto exibido escolhe o primeiro disponivel (WhatsApp, depois telemovel do pai); pode nao ser o contacto preferido em todos os casos.

## Criterios de aceitacao
- `php tools/run-gates.php` verde, incluindo o novo smoke.
- PHP lint verde.
- Manifesto e regras do Kernel alinhados (mesmo conjunto de ids), com `sige_dev_print` em enforce.
- Zero `risk=critical` em `observe`.
- O total do PDF coincide com o total do ecra por construcao (mesma populacao, mesma funcao sige_fin_saldo_lancamento, sem fan-out).
- Zero P0/P1 no rediagnostico adversarial.
