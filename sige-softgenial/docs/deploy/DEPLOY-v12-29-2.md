# DEPLOY - SIGE SoftGenial v12.29.2

**Painel: KPIs em auto-fit (sem colunas vazias para perfis com menos permissoes)**
Data: 2026-06-30 - Tipo: apresentacao (dashboard). Sem logica.

## O que muda
- `admin/system/dashboard-view.php`: a grelha de KPIs (`.sg-kpi-grid`) passa de
  4 colunas fixas para `repeat(auto-fit,minmax(220px,1fr))`. Perfis que veem
  menos de 4 KPIs (cartoes condicionais por permissao) deixam de ter colunas
  vazias; os cartoes presentes preenchem a linha.
- `sige-softgenial.php`: versao -> 12.29.2 (header + `SIGE_VERSION`).
- `BUILD.json`: manifesto -> 12.29.2.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/system/dashboard-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
```

## Instalacao
1. Faca backup da pasta `sige-softgenial/` actual (ou da instalacao WP).
2. Extraia o ZIP por cima da instalacao, mantendo a estrutura de pastas
   (`sige-softgenial/...`). Substituir os 3 ficheiros.
3. O bump de versao invalida a cache (transient) do painel automaticamente.
   Se preferir, force um refresh entrando de novo no painel.

## Verificacao rapida
- Entre no painel com um perfil COMPLETO (4 KPIs): a linha deve mostrar 4
  cartoes lado a lado.
- Entre com um perfil com MENOS permissoes (2 ou 3 KPIs): os cartoes presentes
  devem preencher a largura, sem colunas vazias.
- Reduza a janela: <=1100px -> 2 colunas; <=680px -> 1 coluna.

## Rollback
- Reponha a versao anterior dos 3 ficheiros a partir do backup.

## Fronteira de seguranca
- So CSS da view. Sem mexer em formulas, SQL, nonces, AJAX, name/id, permissoes
  ou schema. Sem impacto em desempenho/cache (chave de transient inalterada).
