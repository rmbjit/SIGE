# Deploy - SIGE SoftGenial v12.11.9.88

## Build

**v12.11.9.88 - Financeiro Cash Reconciliation PRO**

Base recomendada: `v12.11.9.87.1 - Financeiro Extractos Deep Smoke Gate Hotfix`.

## Antes de instalar

1. Fazer backup completo dos ficheiros do plugin actual.
2. Fazer backup da base de dados.
3. Confirmar que a build actualmente instalada é a `12.11.9.87.1` ou posterior.
4. Instalar primeiro em ambiente de teste, se disponível.

## Instalação

1. Ir ao WordPress Admin.
2. Desactivar o plugin SIGE SoftGenial actual.
3. Substituir a pasta do plugin pela pasta desta build, ou instalar o ZIP desta versão.
4. Activar o plugin.
5. Abrir: `SIGE > Financeiro > Extractos/Caixa`.

## Teste funcional recomendado

### 1. Caixa aberto

- Abrir o extracto diário de hoje.
- Confirmar que aparece o assistente de fecho.
- Confirmar que cada método com movimento mostra:
  - valor do sistema;
  - campo de valor contado;
  - diferença automática.

### 2. Sem divergência

- Manter os valores contados iguais ao sistema.
- Marcar a confirmação da checklist.
- Fechar caixa.
- Verificar se o resumo mostra diferença `0,00`.

### 3. Com divergência

- Reabrir a caixa em ambiente de teste.
- Alterar um valor contado para criar diferença.
- Tentar fechar sem observação.
- Resultado esperado: o sistema deve bloquear e exigir motivo.
- Inserir motivo e fechar.
- Verificar se o resumo mostra a diferença e a observação.

### 4. Termo de fecho

- Após fechar, clicar em `Imprimir termo`.
- Confirmar que abre uma vista limpa de impressão/PDF.

### 5. Histórico de fechos

- Confirmar a secção `Histórico de fechos` na data seleccionada.
- Confirmar que mostra utilizador, data/hora, estado e diferença.

## Smoke test técnico

No servidor/SSH, dentro da pasta do plugin:

```bash
php tools/smoke-financeiro-extratos-cash-reconciliation-v12-11-9-88.php
```

Resultado esperado:

```text
SMOKE OK - financeiro-extratos Cash Reconciliation PRO v12.11.9.88 validado.
```

## Observações técnicas

- Esta build não altera a estrutura da base de dados.
- A reconciliação é gravada em `observacoes` com marcador interno `[SIGE_RECON_V1]`.
- `total_por_metodo` continua a guardar o snapshot financeiro canónico por método.
- A reabertura passa a preservar observações anteriores em vez de as substituir.

## Rollback

Em caso de problema:

1. Desactivar o plugin.
2. Repor a pasta da build anterior (`v12.11.9.87.1`).
3. Confirmar que o módulo `financeiro-extratos` abre correctamente.
4. Não é necessário rollback de schema, porque esta build não cria nem altera tabelas.
