# QA - SIGE SoftGenial v12.12.30
Fase 9 incremento 3: Sobreposicao reversivel do papel WordPress

## Ambito verificado
Substituicao do papel WordPress tornada reversivel: preservacao do papel original (idempotente, exclui sige_*, recurso ao papel por omissao) antes de substituir; reposicao na remocao (handler) e na sincronizacao do init (auto-cura quando o perfil sai mas ha papel sige_*). Sem novo ecra, sem nova superficie, sem migracao de esquema (usa user meta).

## Resultado dos gates
- Gate estatico (check-permissoes-sobreposicao): OK. Verifica as funcoes de preservacao e reposicao; a idempotencia, a exclusao de sige_* e o recurso ao papel por omissao; a preservacao antes do set_role na atribuicao; a reposicao no handler com a mensagem actualizada; a reposicao no init quando nao ha perfil activo e ha papel sige_*; e o esquema inalterado.
- Smoke runtime (smoke-permissoes-sobreposicao): OK. Cenarios S1 (preservar o original), S2 (idempotencia), S3 (repor e limpar), S4 (estado legado para papel por omissao), S5 (repor sem copia para papel por omissao), S6 (multiplos papeis), S7 (init repoe quando o perfil sai), S8 (init nao mexe quando coincide).
- Corredor completo (run-gates): 74 de 74 verdes.
- Release gate (smoke-release-gate): 54 verificacoes, OK (v12.12.30).
- Governanca (check-governance-docs, check-public-endpoints-policy): OK.

## Integridade e nao-regressao
- Lint PHP: 0 erros em todos os ficheiros.
- Zero travessoes (em dash / en dash) em todo o pacote.
- Nucleos de calculo (finance-core, academic-logic, whatsapp-engine): byte-identicos a baseline.
- Baselines de design (tokens, consistencia visual, colisoes CSS): sem regressao. So mudou uma mensagem de texto; sem estilo inline novo.
- Superficie de accao: 199 (enforce 33); manifesto e Kernel alinhados; allowlist de views 60. Sem deriva.
- Esquema: SCHEMA_VERSION inalterada. Copia do papel original em user meta.
- Sincronia de versao: header, SIGE_VERSION, BUILD.json e SIGE_GOV_VERSION em 12.12.30; base_version do inventario em 12.12.29.

## Testes negativos aos gates novos
- Removida temporariamente a preservacao antes do set_role: o gate estatico falha. Reposto byte a byte: verde.
- Removida a reposicao no handler de remocao: o gate estatico falha. Reposto: verde.

## Achados durante o desenvolvimento
- O gate estatico foi afinado para tolerar a guarda function_exists entre a preservacao e o set_role (o codigo estava correcto; era o padrao do gate que era estrito de mais).

## Decisoes de desenho documentadas (nao sao defeitos)
- Copia em user meta (sem alteracao de esquema).
- Preservacao idempotente que exclui papeis sige_* e tem recurso ao papel por omissao (evita guardar um estado ja substituido).
- Reposicao no handler e auto-cura no init; administrador WordPress real intocado.
- Variante puramente aditiva por filtro user_has_cap deferida para ambiente de teste vivo; esta entrega ja remove o risco ao tornar a operacao reversivel.

## Conclusao
Zero P0 e zero P1. Aprovado para entrega como v12.12.30. Continua a Fase 9 (seguranca e governanca de acessos). Deferido para a Fase 9 Incr 4 (declarado): niveis editaveis na base de dados ou na interface; e, com ambiente de teste vivo, a variante puramente aditiva por filtro user_has_cap.
