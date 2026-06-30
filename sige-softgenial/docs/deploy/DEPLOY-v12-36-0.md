# DEPLOY - SIGE SoftGenial v12.36.0

**RH (passo 3): Ficha do Colaborador (perfil 360, só leitura) na view=equipe**
Data: 2026-06-30 - Tipo: nova funcionalidade. Só leitura. Sem schema, sem novo endpoint.

## O que muda
- `includes/ajax-handlers.php` — o endpoint **já existente** `sige_get_staff_secure`
  passa a devolver, de forma **aditiva**, 3 campos não sensíveis já existentes na
  tabela: `data_admissao`, `nivel_carreira`, `regime_trabalho`. Mantém intactos o
  nonce, o gating por gestão e a auditoria.
- `admin/hr/equipe-view.php` — botão **"Ver ficha"** em cada linha (gated por
  gestão) abre um **modal só leitura** (`#box-ficha`) que faz o fetch seguro e
  rende um perfil 360: identidade, **banner do estado do contrato**, contacto,
  vínculo & carreira (com **antiguidade calculada**), remuneração, documentos
  (links seguros) e **completude da ficha**. 100% design system (tokens; zero
  cores mágicas).
- `sige-softgenial.php` / `BUILD.json` -> 12.36.0.

> ✅ **Sem ficheiros novos no plugin.** Esta entrega só altera ficheiros já
> existentes — não há pastas/ficheiros novos a criar no servidor.
>
> **Segurança/privacidade:** a ficha reutiliza o endpoint autorizado existente;
> os dados sensíveis (salário/banco/NUIT) **não** ficam no DOM da lista — só são
> obtidos sob pedido, com nonce e permissão de gestão. **Não toca** em ficheiros
> protegidos por hash.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/includes/ajax-handlers.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-rh-ficha-v12-36-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. Sem migração de dados. Limpe a cache
   do navegador (Ctrl+Shift+R).

## Verificação rápida
- Em **Equipa** (com perfil de gestão), cada linha tem agora o botão **"Ver
  ficha"** (ícone de documento, antes do crachá).
- Clicar abre o modal com o perfil consolidado do colaborador (carrega a ficha
  segura). O banner mostra o estado do contrato; a remuneração e os documentos
  aparecem; a barra de completude indica a qualidade dos dados.
- Fechar pelo X ou pelo botão "Fechar".
- Perfis sem permissão de gestão não veem o botão (a coluna de acções é deles
  oculta) e o endpoint recusa o pedido.

## Nota (dívida pré-existente, **não** causada por esta versão)
- O gate `tools/check-v12-16-2-baseline-preservation.php` continua a acusar
  hashes desactualizados em `admin/system/dashboard-view.php` e
  `includes/admin-shell.php` (alterados legitimamente em v12.20.0/v12.29.2).
  **Esta versão não toca nesses ficheiros.**

## Rollback
- Reponha os ficheiros anteriores a partir do backup. Sem efeitos colaterais
  (só leitura; o endpoint volta ao payload anterior).

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
