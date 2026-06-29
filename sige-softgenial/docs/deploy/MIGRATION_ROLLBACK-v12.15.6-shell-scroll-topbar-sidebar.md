# Migracao e Rollback - v12.15.6 - Shell, Scroll, Topbar e Sidebar

## Migracao
1. Fazer backup do plugin actual.
2. Instalar o ZIP v12.15.6 em staging.
3. Limpar cache do navegador, servidor e qualquer cache WordPress.
4. Confirmar versao 12.15.6 em `BUILD.json` e no plugin.
5. Testar shell, scroll, topbar e sidebar em desktop, tablet e mobile.

## Testes obrigatorios apos instalar
- Abrir Dashboard e rolar a area principal.
- Abrir Alunos e rolar a pagina em tablet.
- Abrir Financeiro e testar scroll em tabela longa.
- Abrir Portaria e confirmar que nao voltou a HTML cru.
- Abrir sidebar no tablet e fechar por overlay.
- Abrir sidebar no mobile e fechar por Escape ou clique no menu.

## Rollback
Se surgir regressao P0/P1:
1. Repor ZIP v12.15.5 ou v12.15.4 aprovada.
2. Limpar cache.
3. Confirmar que `assets/sige-shell-stability.css` e `assets/sige-shell-stability.js` nao estao activos.
4. Documentar viewport, modulo, browser e screenshot.

## Feature rollback tecnico
A reversao desta vaga consiste em remover o enqueue dos handles `sige-shell-stability` e `sige-shell-stability.js` em `includes/ui-kit.php` e restaurar a versao anterior do `includes/admin-shell.php`.
