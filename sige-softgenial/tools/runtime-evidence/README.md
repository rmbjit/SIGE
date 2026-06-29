# Runtime Evidence Suite v12.16.1

Esta pasta contem uma suite de evidência browser para staging autenticado. Ela nao substitui os gates PHP locais; complementa-os com prova visual e funcional por perfil real.

## Princípios
- Nao guardar passwords no repositorio.
- Usar storage state isolado por perfil.
- Nao executar contra producao por defeito.
- Capturar screenshots e summary JSON.
- Falhar se houver erro fatal, tela branca, loop ou #038;view.

## Preparacao
1. Instalar Playwright fora do plugin, num ambiente de QA.
2. Criar storage state por perfil com login manual.
3. Copiar config.example.json para config.local.json.
4. Definir SIGE_EVIDENCE_BASE_URL.
5. Definir SIGE_EVIDENCE_STORAGE_ADMINISTRADOR, SIGE_EVIDENCE_STORAGE_DIRECTOR, SIGE_EVIDENCE_STORAGE_FINANCEIRO, SIGE_EVIDENCE_STORAGE_SECRETARIA, SIGE_EVIDENCE_STORAGE_PROFESSOR, SIGE_EVIDENCE_STORAGE_GUARDA, SIGE_EVIDENCE_STORAGE_ENCARREGADO e SIGE_EVIDENCE_STORAGE_ALUNO.

## Execucao
node tools/runtime-evidence/run-browser-evidence.mjs tools/runtime-evidence/config.local.json

## Resultado esperado
- artifacts/runtime-evidence/summary.json.
- screenshots por perfil, view e viewport.
- Exit code 0 apenas quando os fluxos minimos passam.
