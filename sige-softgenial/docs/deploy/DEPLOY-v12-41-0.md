# DEPLOY - SIGE SoftGenial v12.41.0

**RH: relatórios fiscais (Mapa INSS/IRPS mensal) + recibo com NUIT**
Data: 2026-07-01 - Tipo: afinação do Processamento de Salário. Só leitura.

## O que muda
- `includes/rh-salarios.php` — nova função `sige_rh_salario_mapa_mes()` (junta os
  recibos processados com nome + NUIT e calcula totais INSS 3%/4%/7% e IRPS) +
  AJAX `sige_rh_salario_mapa` (gated). O preview passa a incluir o NUIT.
- `admin/hr/equipe-view.php` — na aba **Salários**, botões **"Mapa INSS"** e
  **"Mapa IRPS"** geram documentos mensais imprimíveis/PDF para entrega
  (documentos autónomos com tokens da página; zero cores mágicas). O **recibo**
  passa a mostrar o **NUIT**.
- `sige-softgenial.php` / `BUILD.json` -> 12.41.0.

> ✅ **Sem ficheiros novos e sem tabela nova.** Só altera 2 ficheiros existentes
> (+ versão). Não toca em ficheiros protegidos.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/rh-salarios.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-mapas-fiscais-v12-41-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima. Sem migração de dados. **Limpe a cache** (Ctrl+Shift+R) —
   há alteração de JS.

## Verificação rápida
- Em **Salários**: escolher um mês já **processado** → **Mapa INSS** abre o
  documento com Nome/NUIT/Remuneração/INSS 3%/INSS 4%/Total 7% e totais; **Mapa
  IRPS** abre o mapa de retenção. Escolher "Guardar como PDF" para entregar.
- Um mês sem recibos processados avisa para processar primeiro.
- O **recibo** de cada colaborador mostra agora o NUIT.

## Rollback
- Reponha os ficheiros anteriores a partir do backup. Sem efeitos colaterais.

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
