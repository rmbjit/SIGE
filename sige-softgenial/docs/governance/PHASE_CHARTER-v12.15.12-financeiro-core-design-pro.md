# Phase Charter - v12.15.12 - Design System PRO: Financeiro Core

## Objectivo
Aplicar uma melhoria visual e de UX conservadora ao Financeiro Core, mantendo estabilidade operacional e sem tocar em fórmulas, Finance Score, saldos, dívida, pagamentos, recibos, reconciliação, queries ou regras de negócio.

## Escopo incluído
- Views financeiras core carregadas pelo app shell:
  - `financeiro-dashboard`
  - `financeiro-pagamentos`
  - `financeiro-devedores`
  - `financeiro-extratos`
  - `financeiro-lancamentos`
  - `financeiro-relatorio-mensal`
  - `financeiro-centros`
  - `financeiro-config`
  - `financeiro-planos`
  - `financeiro-despesas`
  - `financeiro-auditoria`
  - `financeiro-inscricoes`
  - `financeiro-gerador`
  - `pagamentos-turma`
  - `mpesa`
  - `reconciliacao`
  - `aprovacoes`
- CSS por view em `assets/views/financeiro-core-design-pro.css`.
- JS marcador passivo em `assets/views/financeiro-core-design-pro.js`.
- Enqueue condicionado por `view` e por feature flag.
- Gates de integridade para impedir alteração de PHP financeiro crítico.

## Fora de escopo
- Alterar fórmulas financeiras.
- Alterar Finance Score.
- Alterar queries, lançamentos, recibos, reconciliação, plano de pagamento ou dívida.
- Alterar `admin/finance/*.php`.
- Alterar `includes/finance-core.php`.
- Alterar Alunos, Portaria, documentos/PDFs, Segurança, Tenant Isolation ou Shell.
- Reintroduzir Design System global agressivo.
- Mover modais no DOM, criar MutationObserver, interceptar submits/clicks financeiros.

## Critérios de aceitação
- A versão fica sincronizada em `sige-softgenial.php`, `BUILD.json` e `CHANGELOG.md`.
- Nenhum ficheiro financeiro PHP protegido é alterado.
- Assets financeiros só carregam em views financeiras listadas.
- Existe opt-out por `sige_design_financeiro_core_v121512_enabled = 0`.
- CSP zero-inline continua preservado.
- Gates existentes e novos passam.

## Riscos principais
- Regressão visual pontual em submódulos financeiros com CSS legado próprio.
- QA em browser autenticado continua necessário porque este ambiente não tem sessão real do staging.

## Mitigação
- Escopo por view.
- Feature flag reversível.
- Sem JS funcional sobre pagamentos.
- Gate de hashes para PHP financeiro crítico.
