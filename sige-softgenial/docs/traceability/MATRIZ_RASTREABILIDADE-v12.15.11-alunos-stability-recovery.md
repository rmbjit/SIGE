# Matriz de Rastreabilidade - v12.15.11

| Requisito | Implementação | Teste |
|---|---|---|
| Página de Alunos não pode ficar lenta/bloqueada | JS volta ao padrão v12.15.8 sem observer/dispatcher/portal | `smoke-alunos-stability-recovery-v12-15-11.php` |
| Botões não podem ficar mudos | Sem fallback dispatcher que intercepta handlers existentes | `check-alunos-stability-scope-v12-15-11.php` |
| Modais não podem ficar sob sidebar | stacking context scoped em `.sige-aluno-modal-open .sg-app-content` | QA browser + smoke |
| Menu dos três pontinhos permanece vertical e acima do hover | Mantida lógica v12.15.8 | smoke v12.15.8 + QA browser |
| CSP preservada | sem inline novo, sem eval/new Function | `check-inline-frontend.php` |
