# RISK REGISTER v12.16.1 - Runtime Evidence & Technical Debt Closure

| ID | Risco | Gravidade | Estado | Mitigacao |
|---|---|---:|---|---|
| R-16-1-01 | Declarar QA browser sem staging autenticado | P1 | Controlado | Suite preparada e honestidade de QA explicita |
| R-16-1-02 | Alterar shell e afectar navegacao | P1 | Controlado | Alteracao limitada a duplicados de allowlist, com gate de conjunto unico |
| R-16-1-03 | Quebrar financeiro por mudança indirecta | P0 | Controlado | Hashes protegidos em financeiro-core, pagamentos e extratos |
| R-16-1-04 | Quebrar académico por mudança indirecta | P0 | Controlado | Sem alteração em views de notas, pautas, boletins, DEC ou acta |
| R-16-1-05 | Expor credenciais na suite browser | P0 | Controlado | Storage state via ambiente, sem password literal no pacote |
| R-16-1-06 | Alargar permissões reais | P0 | Controlado | Gate de matriz e mapa de permissões |
| R-16-1-07 | Manter dependencia de validacao manual | P2 | Mitigado | Runner Playwright preparado para staging |
| R-16-1-08 | Regressao mobile futura | P2 | Mitigado | Viewports minimos 360, 390, 430, 768 e 1366 no contrato |
| R-16-1-09 | Manifesto nao reflectir pacote final | P3 | Controlado | BUILD enriquecido e hash final externo no fecho |
| R-16-1-10 | Refactor prematuro de Alunos | P1 | Evitado | Alunos fica fora do escopo desta fase |

## Decisao tecnica tomada
Abrir fase curta de evidência e contrato, nao uma fase de funcionalidade.

## Justificacao
A v12.16.0 foi aprovada, mas precisava de provas runtime reproduzíveis e manifesto mais forte.

## Risco tratado
Evita regressões invisíveis em mobile, professor, permissões e staging.

## Alternativas rejeitadas
- Refactor profundo de alunos_lista.php.
- Nova funcionalidade institucional.
- Alteração visual global.
- Alteração em financeiro ou académico.

## Critério usado
Segurança, preservação de dados, nao regressão e clareza para utilizadores reais.
