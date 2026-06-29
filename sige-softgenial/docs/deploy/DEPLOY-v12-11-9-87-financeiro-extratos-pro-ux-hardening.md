# Deploy - SIGE SoftGenial v12.11.9.87

**Build:** Financeiro Extractos PRO UX Hardening  
**Base:** v12.11.9.86 - Financeiro Extractos/Caixa UX State Hotfix  
**Data:** 2026-06-10

## Objectivo

Aplicar a Fase 1 PRO do módulo **Financeiro > Extractos/Caixa**, com melhorias de UX, UI, mobile, tablet, acessibilidade e prevenção de erros de interacção.

Esta build não altera regras financeiras, cálculos, recibos, estornos, fecho/reabertura de caixa, permissões existentes ou estrutura de base de dados.

## Antes de instalar

1. Fazer backup completo dos ficheiros do WordPress.
2. Fazer backup da base de dados.
3. Confirmar que a versão actualmente instalada é a v12.11.9.86 ou uma build compatível posterior.
4. Testar primeiro em ambiente de staging sempre que possível.

## Instalação

1. No WordPress, aceder a **Plugins > Adicionar novo > Enviar plugin**.
2. Enviar o ficheiro:

   `sige-softgenial-v12_11_9_87-financeiro-extratos-pro-ux-hardening.zip`

3. Substituir a versão existente quando o WordPress solicitar.
4. Activar o plugin, caso seja necessário.
5. Limpar cache do navegador e eventual cache do site.

## Testes funcionais recomendados

### Desktop / laptop

- Abrir **Financeiro > Extractos/Caixa**.
- Confirmar que a página carrega sem warnings PHP.
- Testar presets: Hoje, Ontem, Este mês, Mês anterior e Este ano.
- Alternar densidade: Compacta, Normal e Confortável.
- Confirmar que a densidade permanece ao recarregar a página.
- Filtrar por centro e período.
- Exportar Excel quando houver movimentos.
- Abrir e fechar modais com teclado e rato.

### Tablet

- Confirmar que filtros, KPIs, resumo operacional e tabela/cards não se sobrepõem.
- Validar que os presets continuam legíveis.
- Verificar botões principais e estados vazios.

### Mobile

- Confirmar presença da barra inferior de acções.
- Testar botão Filtros da barra mobile.
- Testar botão Excel com e sem movimentos.
- Testar botão Recibo quando houver selecção aplicável.
- Confirmar que a barra não cobre conteúdo importante no final da página.

### Segurança de interacção

- Tentar clicar duas vezes rapidamente em formulários de filtro/pesquisa.
- Tentar submeter duas vezes uma confirmação de caixa.
- Confirmar que o botão muda para estado de processamento.

### Acessibilidade básica

- Navegar com Tab pelos filtros e botões.
- Confirmar foco visível.
- Abrir modal e confirmar que o foco fica dentro dele.
- Fechar modal com Escape.

## Rollback

Caso seja necessário reverter:

1. Reinstalar o pacote da v12.11.9.86.
2. Limpar cache do navegador/site.
3. Validar novamente o módulo Financeiro > Extractos/Caixa.

Como esta build não altera tabelas nem dados, o rollback é apenas por ficheiros.

## Smoke test incluído

A build inclui:

`tools/smoke-financeiro-extratos-pro-ux-hardening-v12-11-9-87.php`

Para executar via terminal no directório do plugin:

```bash
php tools/smoke-financeiro-extratos-pro-ux-hardening-v12-11-9-87.php
```

Resultado esperado:

```text
SMOKE OK - financeiro-extratos PRO UX Hardening v12.11.9.87 validado.
```
