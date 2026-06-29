# Deploy - SIGE SoftGenial v12.11.9.86

## Objectivo

Aplicar o hotfix do módulo **Financeiro > Extractos/Caixa** que remove o aviso `Undefined variable $sg_period_range` e corrige o estado `modo=aluno` sem `aluno_id`.

## Instalação recomendada

1. Fazer backup da instalação actual do WordPress e da base de dados.
2. Desactivar temporariamente cache/opcache se o ambiente usar cache agressiva.
3. Substituir o plugin actual pelo pacote:
   `sige-softgenial-v12_11_9_86-financeiro-extratos-ux-state-hotfix.zip`
4. Confirmar no painel que a versão exibida é `12.11.9.86`.
5. Limpar cache do navegador, cache de página e OPcache/PHP-FPM, se aplicável.

## Teste funcional obrigatório

Abrir:

`wp-admin/admin.php?page=sige-app&view=financeiro-extratos&modo=aluno`

Resultado esperado:

- Não deve aparecer `Warning: Undefined variable $sg_period_range`.
- O separador **Histórico de aluno** deve ficar activo.
- A página deve mostrar a pesquisa de aluno.
- A tabela deve mostrar estado vazio orientado para pesquisar aluno.
- Não deve carregar nem apresentar movimentos do caixa diário enquanto nenhum aluno for escolhido.

Depois testar:

`wp-admin/admin.php?page=sige-app&view=financeiro-extratos&modo=diario`

Resultado esperado:

- O caixa diário continua a mostrar filtros, resumo operacional, movimentos e acções normais.
- Fecho/reabertura/estorno continuam disponíveis conforme permissões e estado do caixa.

## Validações técnicas incluídas

```bash
php -l admin/finance/financeiro-extratos.php
php tools/smoke-financeiro-extratos-ux-state-hotfix-v12-11-9-86.php
```

Smoke esperado:

```text
SMOKE OK - financeiro-extratos UX State Hotfix v12.11.9.86 validado.
```

## Rollback

Se for necessário reverter, reinstalar o pacote anterior `v12.11.9.85`. O hotfix não executa migrações e não altera dados financeiros.
