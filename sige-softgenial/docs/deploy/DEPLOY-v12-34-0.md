# DEPLOY - SIGE SoftGenial v12.34.0

**RH (passo 1): alertas de contratos a expirar (view=equipe)**
Data: 2026-06-30 - Tipo: nova funcionalidade. Só leitura. Sem schema.

## O que muda
- **NOVO** `includes/rh-alertas.php` — lógica isolada e testável dos alertas de
  contrato. Função pura `sige_rh_evaluate_contract_alerts(rows, hoje, opts)`
  (classifica expirado/crítico/aviso, ignora inactivos e datas vazias, ordena
  pelo mais urgente) + `sige_rh_contract_alerts(escola)` (consulta) + helpers de
  limiar/rótulo/frase. Só leitura de `sige_professores`; sem writes, sem schema.
- `sige-softgenial.php` — carrega o novo include no bootstrap; versão -> 12.34.0.
- `admin/hr/equipe-view.php` — secção **"Alertas de Contrato"** entre os KPIs e a
  tabela: resumo (expirados/críticos/a expirar), lista dos 8 mais urgentes e
  estado "tudo em dia". Reutiliza as linhas `$_profs_raw` já carregadas (sem 2.ª
  query). 100% design system (tokens; zero cores mágicas).
- `BUILD.json` -> 12.34.0.

> ⚠️ **PASTA já existe, mas há 1 FICHEIRO NOVO:** `includes/rh-alertas.php`.
> No seu pipeline (CloudPanel), confirme que o ficheiro novo chega ao servidor —
> ficheiros novos podem precisar de atenção manual. A pasta `includes/` já existe.
> Não altera ficheiros protegidos por hash.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/rh-alertas.php          <-- NOVO
sige-softgenial/sige-softgenial.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-alertas-v12-34-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. **Confirme** que
   `includes/rh-alertas.php` ficou no servidor (é ficheiro novo).
3. Sem migração de dados. Limpe a cache do navegador (Ctrl+Shift+R).

## Verificação rápida
- Em **Equipa**, entre os KPIs e a tabela, aparece a secção **"Alertas de
  Contrato"**.
- Se houver contratos com `fim_contrato` <= 90 dias: surgem os chips de resumo e
  a lista (expirados primeiro), cada um com nome, tipo de vínculo, data e a frase
  ("expirou há N dias" / "faltam N dias").
- Sem contratos a expirar: mostra o estado "tudo em dia".
- Colaboradores inactivos e datas vazias (`0000-00-00`) não geram alerta.

## Rollback
- Reponha os ficheiros anteriores a partir do backup e remova
  `includes/rh-alertas.php`. Sem efeitos colaterais (só leitura).

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
