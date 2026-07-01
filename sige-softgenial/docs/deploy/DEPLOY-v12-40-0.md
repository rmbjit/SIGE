# DEPLOY - SIGE SoftGenial v12.40.0

**RH (passo 7, final): Processamento de Salário — Fase 1 (INSS/IRPS Moçambique)**
Data: 2026-07-01 - Tipo: nova funcionalidade. Tabela nova. Fecha o módulo de RH.

## O que muda
- **NOVO** `includes/rh-salarios.php` — motor de processamento:
  * **config de impostos por escola** (editável): INSS % e **tabela de IRPS**
    (escalões), pré-preenchida com o padrão de Moçambique;
  * funções **puras** de cálculo (INSS, IRPS por escalões, faltas, líquido);
  * tabela `{prefix}sige_rh_salarios` (1 recibo por colaborador/mês) criada por
    código (idempotente);
  * `preview` (calcula sem gravar, puxa faltas da Assiduidade) e `processar`
    (**recalcula no servidor**, upsert); AJAX gated por gestão (nonce, auditoria).
  * **Sem permissões novas.**
- `admin/hr/equipe-view.php` — nova aba **"Salários"** (só gestão): mês/ano +
  Calcular + **Configurar impostos** + **Processar mês**; tabela com totais e
  "outros" editável; **recibo de vencimento** imprimível/PDF por colaborador.
- `sige-softgenial.php` / `BUILD.json` -> 12.40.0.

> ⚠️ **1 FICHEIRO NOVO** (`includes/rh-salarios.php`) + **1 TABELA NOVA**
> (`wp_sige_rh_salarios`), criada por código no 1.º acesso admin — **sem SQL
> manual**. Não toca em ficheiros protegidos nem no módulo financeiro existente.
>
> 🧮 **FISCAL:** antes do uso real, abra **"Configurar impostos"** e **confirme a
> tabela de INSS/IRPS em vigor**. Os valores por omissão são o padrão de
> Moçambique e devem ser validados pela escola (há um aviso visível na aba).

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/rh-salarios.php              <-- NOVO
sige-softgenial/sige-softgenial.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-salarios-v12-40-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/` (e da base de dados).
2. Extraia por cima. **Confirme** que `includes/rh-salarios.php` ficou no
   servidor. Abra o painel uma vez (cria a tabela). Limpe a cache (Ctrl+Shift+R).
3. Em **Equipa → Salários → Configurar impostos**, valide INSS/IRPS.

## Verificação rápida
- Em **Salários** (gestão): escolher mês → **Calcular** → surge a tabela com
  Bruto/INSS/IRPS/Faltas/Líquido e totais.
- Editar "outros" recalcula o líquido na hora. **Recibo** imprime o documento.
- **Processar mês** grava os recibos (aparece o selo "processado").
- Um colaborador com faltas injustificadas na Assiduidade mostra o desconto.

## Rollback
- Reponha os ficheiros anteriores e remova `includes/rh-salarios.php`. A tabela
  e a option de config ficam inertes (dados preservados).

## Fronteira
- Sem mexer em fórmulas financeiras existentes, schema anterior, permissões
  reais ou ficheiros protegidos.
