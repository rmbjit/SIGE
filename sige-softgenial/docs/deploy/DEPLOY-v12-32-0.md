# DEPLOY - SIGE SoftGenial v12.32.0

**Modelos de crachá por escola (4 modelos modernos + cor + redes sociais)**
Data: 2026-06-30 - Tipo: nova funcionalidade de apresentação. Sem schema.

## O que muda
- **NOVO** `assets/cracha/sige-cracha-templates.js` — registo único dos 4 modelos
  (Aurora, Clássico, Vivid, Minimal), usado pela pré-visualização e pela impressão.
- **NOVO** `includes/cracha-config.php` — definições do crachá por escola
  (validação + WP option por escola + AJAX de gravação).
- `sige-softgenial.php` — carrega a camada.
- `includes/ui-kit.php` — enfileira o registo no ecrã Alunos.
- `admin/academic/alunos_lista.php` — botão "Modelo de Crachá" + modal (seletor,
  cor, redes, pré-visualização ao vivo); impressão delega no registo.
- `BUILD.json` -> 12.32.0; `tools/.design-tokens-baseline.json` -> 1879.
- Novo teste `tools/smoke-cracha-modelos-v12-32-0.php`.

> Não altera ficheiros protegidos por hash, fórmulas, SQL de cálculo, permissões
> reais nem schema.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/assets/cracha/sige-cracha-templates.js     (NOVO)
sige-softgenial/includes/cracha-config.php                 (NOVO)
sige-softgenial/sige-softgenial.php
sige-softgenial/includes/ui-kit.php
sige-softgenial/admin/academic/alunos_lista.php
sige-softgenial/BUILD.json
sige-softgenial/tools/.design-tokens-baseline.json
sige-softgenial/tools/smoke-cracha-modelos-v12-32-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia o ZIP por cima, mantendo a estrutura de pastas (cria a pasta
   `assets/cracha/`). Sem migração de dados.

## Verificação rápida
- Em **Alunos**, com perfil de Direcção/Admin: aparece o botão **"Modelo de
  Crachá"**. Abre o modal com 4 modelos + pré-visualização ao vivo.
- Trocar de modelo / cor / activar redes → a pré-visualização actualiza na hora.
- **Guardar** → mensagem de sucesso; imprimir um crachá (individual ou lote) usa
  já o modelo escolhido.
- Perfis sem permissão de configuração **não** veem o botão.

## Rollback
- Reponha os ficheiros anteriores a partir do backup e remova a pasta
  `assets/cracha/` e `includes/cracha-config.php`. A opção guardada
  (`sige_cracha_config_{escola}`) fica inerte (ignorada) sem a camada.

## Fronteira de segurança
- AJAX com nonce + permissão (configuracoes.editar / papéis). Input validado e
  sanitizado no servidor. CSP respeitada (nonce no `<style>` da pré-visualização;
  sem handlers inline). Tenant-scoped por escola.
