# Rediagnostico adversarial - v12.12.7

## Escopo revisto
Foram procuradas falhas escondidas em: manifesto, Security Kernel, view_action, M-Pesa manual, despesas, pagamentos, permissões por escola, remover aluno, webhooks e gates.

## Achados
- P0: 0 aberto.
- P1: 0 aberto.
- P2: riscos residuais planeados para fases futuras.
- P3: melhorias documentais e refinamentos de UX.

## Verificações adversariais
- Zero `risk=critical` em `observe` nas regras do Kernel.
- `view_action` executa em `admin_init` antes das views administrativas.
- M-Pesa manual exige `id` + `escola_id` nas consultas e updates.
- Despesas não usam a primeira escola da base.
- Pagamento exige `financeiro.pagar`; `financeiro.ver` não basta.
- Permissões por escola usam tabelas tenant-scoped.
- Remover aluno arquiva e não apaga histórico.
- Webhooks móveis possuem validator tokenizado.

## Decisao
Decisao: versão apta para validação em staging como RC/final técnica, com P0=0 e P1=0 no conjunto de testes automatizados executados. Produção depende de validação humana por perfil e escola.
