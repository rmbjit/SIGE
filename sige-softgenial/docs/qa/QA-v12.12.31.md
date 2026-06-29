# QA - SIGE SoftGenial v12.12.31
Fase 9 incremento 4: Niveis de perfil editaveis

## Ambito verificado
Hierarquia de niveis editavel e persistida: mapa base em codigo mais desvios numa opcao, fundidos em role_niveis (avaliador e selector respeitam os niveis editados); gravacao validada 0..100 que guarda so desvios; edicao exclusiva do administrador WordPress real (handler save_niveis e painel). Sem nova superficie, sem migracao de esquema (opcao em wp_options).

## Resultado dos gates
- Gate estatico (check-permissoes-niveis-editaveis): OK. Verifica as funcoes de base, desvios e gravacao; a fusao base mais desvios em role_niveis; a opcao de desvios; a validacao 0..100 e a remocao do desvio ao voltar ao base; a accao save_niveis reservada ao administrador WordPress real com auditoria; o painel reservado ao dono, com formulario de niveis e sem estilo inline novo; e o esquema inalterado.
- Smoke runtime (smoke-permissoes-niveis-editaveis): OK. Cenarios E1 (sem desvio vale a base), E2 (gravar guarda so desvios), E3 (avaliador respeita os niveis editados), E4 (repor ao base remove o desvio), E5 (validacao 0..100), E6 (entradas invalidas ignoradas).
- Smoke do Incr 2 (hierarquia): OK apos a mudanca de role_niveis (sem desvios, vale a base).
- Corredor completo (run-gates): 76 de 76 verdes.
- Release gate (smoke-release-gate): 54 verificacoes, OK (v12.12.31).
- Baseline de autorizacao (check-authorization-baseline): OK, divida congelada sem aumento de current_user_can.
- Governanca (check-governance-docs, check-public-endpoints-policy): OK.

## Integridade e nao-regressao
- Lint PHP: 0 erros em todos os ficheiros.
- Zero travessoes (em dash / en dash) em todo o pacote.
- Nucleos de calculo (finance-core, academic-logic, whatsapp-engine): byte-identicos a baseline.
- Baselines de design (tokens, consistencia visual, colisoes CSS): sem regressao. O painel reutiliza classes e tokens existentes; sem estilo inline novo.
- Superficie de accao: 199 (enforce 33); manifesto e Kernel alinhados; allowlist de views 60. Sem deriva (save_niveis e accao de formulario dentro da view ja listada).
- Esquema: SCHEMA_VERSION inalterada. Desvios em wp_options.
- Sincronia de versao: header, SIGE_VERSION, BUILD.json e SIGE_GOV_VERSION em 12.12.31; base_version do inventario em 12.12.30.

## Testes negativos aos gates novos
- Removida temporariamente a fusao dos desvios em role_niveis: o gate estatico falha. Reposto byte a byte: verde.
- Removido o guard de dono em save_niveis: o gate estatico falha. Reposto: verde.

## Achados durante o desenvolvimento
- O baseline de autorizacao apanhou uma chamada de recurso a current_user_can no guard de dono do handler (que nunca era usada na pratica, pois a funcao canonica existe sempre). Removida na mesma sessao, deixando so a funcao canonica e mantendo o baseline congelado.

## Decisoes de desenho documentadas (nao sao defeitos)
- Niveis numa opcao (desvios sobre o mapa base), sem alteracao de esquema.
- Validacao 0 a 100; entradas invalidas ignoradas; um valor igual ao base remove o desvio.
- Edicao exclusiva do administrador WordPress real, no handler e no painel.

## Conclusao
Zero P0 e zero P1. Aprovado para entrega como v12.12.31. Conclui o conjunto previsto para a Fase 9 (blindagem, hierarquia, sobreposicao reversivel, niveis editaveis). Deferido (declarado): a variante puramente aditiva por filtro user_has_cap para ambiente de teste vivo; uma coluna dedicada de nivel na tabela de perfis como refinamento futuro.
