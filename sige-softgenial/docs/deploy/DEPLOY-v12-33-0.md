# DEPLOY - SIGE SoftGenial v12.33.0

**Modelos de crachá para a EQUIPA (4 modelos próprios) + cor + redes sociais**
Data: 2026-06-30 - Tipo: nova funcionalidade (view=equipe). Sem schema.

## O que muda
- `assets/cracha/sige-cracha-templates.js` — passa a expor também
  `SigeCrachaStaffTemplates` (4 modelos de equipa: Corporate, Lanyard, Executive,
  Slate). Mesmo ficheiro do estudante.
- `includes/cracha-config.php` — camada de config da equipa + AJAX
  `sige_save_cracha_staff_config` (option própria por escola, sem schema).
- `admin/hr/equipe-view.php` — botão "Modelo de Crachá" + modal (selector, cor,
  redes, pré-visualização ao vivo); impressão (individual e lote) passa a usar o
  modelo escolhido; registo entregue inline por filesystem.
- `sige-softgenial.php` -> 12.33.0; `BUILD.json` -> 12.33.0.

> Sem pasta nova: usa `assets/cracha/` (já existe no servidor). Não altera
> ficheiros protegidos por hash.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/assets/cracha/sige-cracha-templates.js
sige-softgenial/includes/cracha-config.php
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/.design-tokens-baseline.json
sige-softgenial/tools/smoke-cracha-modelos-v12-32-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. **Limpe a cache** do navegador
   (Ctrl+Shift+R). Sem migração de dados.

## Verificação rápida
- Em **Equipa**, com perfil de gestão: aparece o botão **"Modelo de Crachá"**.
  Abre o modal com 4 modelos próprios de equipa + pré-visualização.
- Trocar modelo/cor/activar redes → a pré-visualização actualiza.
- **Guardar** → toast de sucesso; o modal fecha.
- Imprimir crachá (individual e "Crachás" em lote) → sai no modelo escolhido,
  com nome e **cargo**, sem QR (é da equipa).
- Os modelos de equipa são visualmente distintos dos do estudante.

## Rollback
- Reponha os ficheiros anteriores a partir do backup. A option
  `sige_cracha_staff_config_{escola}` fica inerte sem a camada.

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
