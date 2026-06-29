# QA - v12.17.1 Alunos Mobile Header Hotfix

## Testes automaticos

- php tools/check-v12-17-1-alunos-mobile-header-contract.php
- php tools/smoke-v12-17-1-alunos-mobile-header.php
- php tools/check-v12-17-1-no-sensitive-regression.php
- php tools/check-v12-17-1-package-manifest.php
- php tools/run-gates.php

## Testes manuais em staging

| Perfil | View | Validacao |
|---|---|---|
| Administrador | Alunos | Header mobile sem avatar cortado |
| Secretaria | Alunos | Pesquisa, filtro e lista continuam funcionais |
| Director | Alunos | Ficha 360 abre normalmente |
| Guarda | Portaria | Sem regressao fora de Alunos |
| Financeiro | Pagamentos ou Extractos | Sem regressao fora de Alunos |
| Professor | Minhas Turmas | Sem loop e sem #038;view |

## Criterio de aceitacao

- Mobile Alunos mostra menu, logo, SoftGenial e avatar alinhados.
- Chip de ano lectivo nao aparece no header mobile de Alunos.
- Foto de perfil nao corta.
- Nenhum modulo sensivel apresenta regressao.
