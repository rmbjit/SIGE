# Plano de Rollback - v12.16.0 - Institutional Product Architecture & Operational UX Hardening

## Baseline de retorno

- Versao de retorno: `12.15.25`.
- ZIP de retorno: `sige-softgenial-v12_15_25-fecho-4-gates.zip`.
- SHA-256: `e9354b459a5bfef5ab126f76fa92e149aec9e8172070111ba73e7ab947305f27`.

## Principio de rollback

Cada RC e reversivel por bloco. Nao ha migracao de base de dados nesta fase, portanto o rollback tecnico esperado e substituicao de ficheiros do plugin pela baseline ou remocao do bloco especifico.

## Rollback por RC

| RC | Tipo de alteracao | Rollback |
|---|---|---|
| RC0 | Documentacao e gate de governanca | Remover docs v12.16.0 e entrada no corredor. Nenhum efeito runtime. |
| RC1 | Mapa operacional por perfil no dashboard | Remover chamadas ao helper no dashboard e reverter `includes/institutional-product-map.php` para antes do RC1. Nenhum dado afectado. |
| RC2 | Checklists operacionais por perfil | Remover bloco `Checklist Operacional` do dashboard e funcoes de checklist do helper. Nenhum schema afectado. |
| RC3 | Faixa operacional no shell | Reverter alteracao em `includes/admin-shell.php` e funcoes de navegacao do helper. Menu principal continua intacto. |
| RC4 | Fluxos guiados e microcopy | Remover bloco `Fluxos Guiados` do dashboard e catalogo de fluxos no helper. Nao afecta regras. |
| RC5 | Hardening final e readiness | Remover docs/gates RC5 se algum contrato estiver errado. Nao gera ZIP e nao altera runtime de negocio. |

## Condições de rollback imediato

1. Gate financeiro vermelho.
2. Gate academico vermelho.
3. Gate de permissoes vermelho.
4. Gate de pesquisa global vermelho.
5. Regressao em portaria.
6. Erro de lint PHP.
7. Acesso indevido por perfil.
8. Lentidao critica nova em alunos, financeiro ou dashboard.
9. Perda ou corrupcao de dados.
10. Divergencia entre documentacao RC e implementacao.

## Observacao sobre base de dados

A v12.16.0 nao cria migracao nem altera schema. Se uma necessidade real de schema surgir em fase futura, o trabalho deve parar e abrir decisao de utilizador no formato obrigatorio.
