# Deploy - SIGE SoftGenial v12.11.9.87.1

Build: **Financeiro Extractos Deep Smoke Gate Hotfix**

## Objectivo

Esta build é um hotfix de qualidade sobre a `v12.11.9.87`, criado após smoke test profundo antes da Fase 2. Ela corrige problemas de compatibilidade/renderização encontrados pelo gate, sem mexer em regras financeiras.

## Ordem recomendada

1. Fazer backup do WordPress e da base de dados.
2. Substituir o plugin pela build `sige-softgenial-v12_11_9_87_1-financeiro-extratos-deep-smoke-gate-hotfix.zip`.
3. Limpar cache do WordPress/navegador/CDN, se existir.
4. Validar o módulo em ambiente real.
5. Só avançar para a Fase 2 depois desta build estar estável.

## Testes manuais recomendados

### Extracto diário

- Abrir Financeiro > Extractos/Caixa em modo diário.
- Confirmar presets: Hoje, Ontem, Este mês, Mês anterior e Este ano.
- Alternar densidade: Compacta, Normal, Confortável.
- Exportar Excel.
- Verificar tabela/cards em mobile.

### Filtro por ciclo

- Abrir relatório mensal.
- Seleccionar Creche.
- Seleccionar Ensino Primário.
- Seleccionar Ensino Secundário.
- Confirmar que não há fatal error em servidores sem `mbstring`.

### Histórico de aluno

- Abrir `modo=aluno` sem aluno seleccionado.
- Pesquisar aluno.
- Abrir histórico de aluno.
- Seleccionar pagamentos para recibo unificado.

### Caixa

- Validar resumo operacional.
- Validar visualização de despesas mesmo quando categoria esteja vazia/ausente.
- Testar confirmação de fecho.
- Testar confirmação de reabertura em ambiente controlado.
- Testar estorno apenas em ambiente de testes.

## Sem migração de BD

Esta build não requer migração, criação de tabelas ou alteração de colunas.

## Rollback

Em caso de anomalia, voltar para `v12.11.9.87` ou `v12.11.9.86`. Como não há migração, o rollback é directo.
