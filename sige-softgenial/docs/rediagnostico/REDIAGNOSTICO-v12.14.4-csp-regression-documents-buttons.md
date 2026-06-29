# Rediagnóstico Adversarial - v12.14.4

## Perguntas adversariais

### A correcção enfraquece CSP?
Não. Não foi reintroduzido `unsafe-inline`; não se usou `eval`/`new Function`; acções documentais usam ficheiro JS externo self-hosted.

### O problema do PDF era o navegador?
Não exactamente. A causa imediata era técnica: o documento ainda dependia de CSS/JS inline que CSP passou a bloquear. O navegador apenas expôs a dívida técnica.

### Devemos instalar já um motor PDF?
Não nesta correcção. Um motor PDF self-hosted é sustentável, mas deve ser fase própria porque altera a cadeia documental, dependências, memória, fontes e paginação.

### Ficaram documentos antigos por migrar?
Sim, possível P2. A versão migra o documento reportado e cria infraestrutura reutilizável. A migração total dos restantes documentos deve ser inventariada numa fase documental.

### Pode quebrar cálculo financeiro?
Não foram alteradas queries, totais, lançamentos, pagamentos ou regras de cálculo. Só apresentação e handlers de interface.

## Conclusão
Zero P0/P1 conhecido no escopo. Risco residual P2: migrar os restantes documentos para CSS/JS externos e planear motor PDF self-hosted.
