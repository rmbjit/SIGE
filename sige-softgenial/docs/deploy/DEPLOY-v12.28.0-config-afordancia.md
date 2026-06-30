# DEPLOY v12.28.0 - Config: afordância dos campos + Períodos colapsável

Âmbito: ecrã Preços e Serviços (view=financeiro-config). Só apresentação/UX.
File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/assets/style.css`
- `wp-content/plugins/sige-softgenial/admin/finance/financeiro-config.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 4 ficheiros actuais.
2. Substituir os 4 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5 (é CSS).

## Validar

- Em Plugins, confirmar Versão 12.28.0.
- Preços e Serviços:
  - Os campos (selects de tipo, categoria, classe, ciclo, centro, etc.) têm borda
    nítida e SETA de dropdown; ao passar o rato realçam (claramente clicáveis).
  - "Gestão de Períodos" agora é colapsável (começa fechada); clicar no cabeçalho
    expande/colapsa.
  - Abrir Adicionar/Editar Serviço: o formulário longo rola e o cabeçalho do modal
    fica fixo (já era assim).
- Confirmar que guardar regras/serviços continua a funcionar como antes.
- Seguir o LIVE-TEST: docs/qa/LIVE-TEST-v12.28.0-config-afordancia.md

## Rollback

- Repor os 4 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
