# Inventario tecnico - v12.14.1 - CSP Zero-Inline Absolute Guard

## Superficies afectadas
- Shell administrativo SIGE (`page=sige-app`).
- Admin-posts SIGE (`action=sige_*`).
- Paginas autonomas SIGE com CSP proprio.
- Views com `style=` historico.
- Views com handlers `onclick`, `onchange`, `oninput`, `onsubmit`, `onerror`, `onkeyup`, `onmouseover`, `onmouseout`.

## Ficheiros alterados
- `sige-softgenial.php`: versao e carregamento do guard.
- `includes/csp-zero-inline.php`: politica CSP, header enforcement e sanitizador.
- `includes/admin-shell.php`: remove politica CSP antiga com compatibilidade inline.
- `assets/sige-ui.js`: hidratador externo para estilos/eventos declarativos.
- Handlers autonomos: `documents-engine`, `boletim`, `pauta`, `portaria-camera`, `historico-aluno`, `jardim_boletim`.
- Gates: `check-inline-frontend.php`, `smoke-csp-enforcement-v12-14-0.php`.

## Permissoes e tenant isolation
Sem mudanca funcional de permissoes, queries ou tenant isolation. A fase actua na camada de resposta HTML/CSP.

## Auditoria estruturada
Sem novos fluxos criticos de escrita. Nao foi introduzida accao financeira, academica ou administrativa nova.
