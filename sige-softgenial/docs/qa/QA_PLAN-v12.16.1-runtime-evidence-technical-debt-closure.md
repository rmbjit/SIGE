# QA PLAN v12.16.1 - Runtime Evidence & Technical Debt Closure

## Validações CLI obrigatórias
1. php -l em todos os ficheiros PHP.
2. php tools/smoke-release-gate.php.
3. php tools/run-gates.php.
4. php tools/check-v12-16-1-runtime-evidence.php.
5. php tools/smoke-v12-16-1-runtime-evidence.php.
6. php tools/check-v12-16-1-shell-contract.php.
7. php tools/check-v12-16-1-package-manifest.php.
8. unzip -t no ZIP final.

## Testes por perfil em staging
| Perfil | Views obrigatorias | Critério de aceite |
|---|---|---|
| Administrador | dashboard, config_center, sige_core_status, sige_permissoes | Abre sem erro e sem loop |
| Director | dashboard, alunos_lista, turmas, financeiro-dashboard | Visao operacional clara |
| Financeiro | financeiro-dashboard, financeiro-pagamentos, financeiro-extratos, financeiro-devedores | Abre sem alterar formulas |
| Secretaria | alunos_lista, aluno_portal, turmas, vinculacao | Pesquisa e cadastro acessiveis |
| Professor | minhas_turmas, notas, pautas | Sem loop e sem view indevida |
| Guarda | portaria | Leitura operacional simples |
| Encarregado | aluno_portal | Portal leve e compreensivel |
| Aluno | aluno_portal | Informacao propria visivel |

## Viewports obrigatorios
- 360x740.
- 390x844.
- 430x932.
- 768x1024.
- 1366x768.

## Testes negativos obrigatórios
- URL nao deve conter #038;view.
- Nenhuma tela branca.
- Nenhum erro fatal PHP.
- Nenhum redireccionamento circular.
- Perfil operacional nao deve ver ferramentas técnicas indevidas.
- Suite nao deve guardar senha no repositorio.

## Como validar em staging
1. Preparar storage state por perfil com Playwright codegen.
2. Copiar config.example.json para config.local.json.
3. Definir SIGE_EVIDENCE_BASE_URL com a URL de staging.
4. Definir variaveis SIGE_EVIDENCE_STORAGE_<PERFIL> para cada storage state.
5. Executar node tools/runtime-evidence/run-browser-evidence.mjs tools/runtime-evidence/config.local.json.
6. Guardar screenshots e summary JSON como evidência da RC.

## Como reverter se falhar
- Repor ZIP v12.16.0.
- Anexar screenshots e summary da falha.
- Tratar a falha como P0/P1 conforme impacto.
