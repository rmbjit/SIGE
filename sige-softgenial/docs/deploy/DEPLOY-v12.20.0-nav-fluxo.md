# DEPLOY v12.20.0 - Barra lateral reordenada pelo fluxo do ano lectivo

Âmbito: barra lateral (apresentação/IA; toca gating por papel). File Manager sem SSH.

## Ficheiros a substituir

- `wp-content/plugins/sige-softgenial/includes/admin-shell.php`
- `wp-content/plugins/sige-softgenial/sige-softgenial.php`
- `wp-content/plugins/sige-softgenial/BUILD.json`

## Ordem

1. Backup dos 3 ficheiros actuais (importante: mudança grande).
2. Substituir os 3 ficheiros.
3. Sem migração de base de dados; sem activar/desactivar.

## Limpar cache

- Purgar cache de página/objeto se existir. Ctrl+F5.

## Validar (por perfil - é uma mudança de navegação)

- Em Plugins, confirmar **Versão 12.20.0**.
- A barra lateral deve ler-se na ordem: Painel -> Estrutura Académica -> Equipa
  -> Secretaria -> Faturação -> Tesouraria -> Sala de Aula -> Avaliação &
  Documentos -> Jardim -> Operação Escolar -> Comunicação -> Relatórios & Análise
  -> Configuração -> Privacidade.
- Seguir o LIVE-TEST por perfil (director, secretaria, tesouraria, professor com
  escopo, educador/jardim, guarda).

## Rollback

- Repor os 3 ficheiros do backup. Reverte na hora; sem efeitos em dados ou lógica.
