# QA - SIGE SoftGenial v12.12.28
Fase 9 incremento 1: Blindagem do modulo de permissoes

## Ambito verificado
Blindagem do modulo de Perfis e Permissoes atraves de um avaliador unico de operacao com quatro guardas (alvo protegido, anti-escalada, auto-proteccao, ultimo gestor), bloqueio no handler nos dois ramos com auditoria, contas protegidas so de leitura com selo, porta do menu coerente com usuarios.gerir_permissoes, e migracao de reconciliacao para o admin_ti. Sem novo ecra, sem nova superficie, sem migracao de esquema.

## Resultado dos gates
- Gate estatico (check-permissoes-blindagem): OK. Verifica as seis funcoes de guarda, os cinco codigos de guarda no avaliador, a chamada do avaliador no handler nos dois ramos com auditoria, o ramo protegido sem formulario de accao com selo, a porta do menu por usuarios.gerir_permissoes (com Saude e Centro restritos ao core admin), a migracao 121228 ligada, e o esquema inalterado.
- Smoke runtime (smoke-permissoes-blindagem): OK. Cenarios C1 (alvo protegido), C1b (protegido sobre protegido), C2 (escalada), C3 (auto remocao), C4 (auto despromocao), C5/C5b (actor protegido com autoridade plena, incluindo mintar gestor), C6 (atribuir perfil normal a outro), C7 (ultimo gestor), C8 (contexto invalido).
- Corredor completo (run-gates): 70 de 70 verdes.
- Release gate (smoke-release-gate): 54 verificacoes, OK (v12.12.28).
- Governanca (check-governance-docs, check-public-endpoints-policy): OK.

## Integridade e nao-regressao
- Lint PHP: 0 erros em todos os ficheiros.
- Zero travessoes (em dash / en dash) em todo o pacote.
- Nucleos de calculo (finance-core, academic-logic, whatsapp-engine): byte-identicos a baseline.
- Baselines de design (tokens, consistencia visual, colisoes CSS): sem regressao. A unica classe nova (protegido) espelha a classe deprecated existente e usa apenas tokens; sem estilo inline novo.
- Superficie de accao: 199 (enforce 33); manifesto e Kernel alinhados; allowlist de views 60. Sem deriva.
- Esquema: SCHEMA_VERSION inalterada. Reconciliacao em role_permissions (dados).
- Sincronia de versao: header, SIGE_VERSION, BUILD.json e SIGE_GOV_VERSION em 12.12.28; base_version do inventario em 12.12.27.

## Testes negativos aos gates novos
- Removida temporariamente uma guarda do avaliador: o gate estatico e o smoke falham. Reposto byte a byte: voltam a verde.
- Alterado um codigo de guarda: o gate estatico falha. Reposto: verde.

## Achados durante o desenvolvimento
- O lint apanhou a remocao acidental da declaracao da funcao sige_can ao inserir os novos helpers (chaveta sem par). Corrigido na mesma sessao, antes de qualquer empacotamento. Sem impacto no pacote final.

## Decisoes de desenho documentadas (nao sao defeitos)
- Protegido = administrador WordPress real (sige_is_real_wp_admin_user), o criterio canonico do plugin.
- A protecca do ultimo gestor e da auto-proteccao aplicam-se a actores nao protegidos; um administrador WordPress real tem autoridade plena e nunca fica trancado.
- A deteccao de perfil de gestao e fail-safe para o lado restritivo (se a declaracao ou a base de dados conferir gestao, e tratado como perfil de gestao).
- Contas protegidas ficam visiveis e so de leitura (transparencia e auditabilidade), em vez de escondidas.

## Conclusao
Zero P0 e zero P1. Aprovado para entrega como v12.12.28. Abre a Fase 9 (seguranca e governanca de acessos). Deferido para a Fase 9 Incr 2 (declarado): sobreposicao puramente aditiva (deixar de trocar o WP role) e hierarquia de perfis por nivel.
