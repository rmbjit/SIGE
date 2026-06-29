# QA - SIGE SoftGenial v12.12.29
Fase 9 incremento 2: Hierarquia de perfis por nivel

## Ambito verificado
Hierarquia de perfis por nivel: mapa de niveis em codigo (tres gestores no topo, restantes por responsabilidade, personalizados no nivel 0), guarda nivel_insuficiente no avaliador nos dois ramos por cima da regra anti-escalada do Incr 1, interface que lista so perfis atribuiveis e torna so de leitura as linhas de nivel igual ou superior. Sem novo ecra, sem nova superficie, sem migracao de esquema.

## Resultado dos gates
- Gate estatico (check-permissoes-hierarquia): OK. Verifica as tres funcoes de nivel, o mapa com os gestores no topo e perfis operacionais conhecidos, a guarda nivel_insuficiente nos dois ramos, a regra anti-escalada mantida, a interface (nivel do actor, ramo so-leitura por nivel, filtro do selector por nivel e gestao) e o esquema inalterado.
- Smoke runtime (smoke-permissoes-hierarquia): OK. Cenarios H1 (atribuir abaixo, permitido), H2 (alvo de nivel superior, recusado), H3 (perfil de nivel igual, recusado), H4 (remover alvo de nivel superior, recusado), H5 (par do mesmo nivel, recusado), H6 (anti-escalada com precedencia), H7 (direccao geral gere admin TI com perfil nao-gestor, permitido), H8 (administrador WordPress com autoridade plena).
- Smoke do Incr 1 (smoke-permissoes-blindagem): OK apos a adicao da guarda de nivel (mock actualizado para fornecer nivel ao actor).
- Corredor completo (run-gates): 72 de 72 verdes.
- Release gate (smoke-release-gate): 54 verificacoes, OK (v12.12.29).
- Governanca (check-governance-docs, check-public-endpoints-policy): OK.

## Integridade e nao-regressao
- Lint PHP: 0 erros em todos os ficheiros.
- Zero travessoes (em dash / en dash) em todo o pacote.
- Nucleos de calculo (finance-core, academic-logic, whatsapp-engine): byte-identicos a baseline.
- Baselines de design (tokens, consistencia visual, colisoes CSS): sem regressao. Reutiliza o selo neutro do Incr 1; sem estilo inline novo.
- Superficie de accao: 199 (enforce 33); manifesto e Kernel alinhados; allowlist de views 60. Sem deriva.
- Esquema: SCHEMA_VERSION inalterada. Hierarquia em codigo.
- Sincronia de versao: header, SIGE_VERSION, BUILD.json e SIGE_GOV_VERSION em 12.12.29; base_version do inventario em 12.12.28.

## Testes negativos aos gates novos
- Removida temporariamente a guarda de nivel de um ramo: o gate estatico e o smoke falham. Reposto byte a byte: voltam a verde.
- Alterado o mapa de niveis (gestor fora do topo): o gate estatico falha. Reposto: verde.

## Achados durante o desenvolvimento
- O lint apanhou a remocao acidental do inicio da consulta de perfis activos ao inserir o calculo do nivel do actor na interface. Corrigido na mesma sessao, antes de qualquer empacotamento.
- O smoke do Incr 1 falhou no unico cenario de caso permitido (C6) porque a nova consulta de nivel nao existia no seu mock, devolvendo nivel 0 ao actor; o produto estava correcto, o teste e que estava desactualizado. Mock do Incr 1 actualizado para fornecer nivel ao actor.

## Decisoes de desenho documentadas (nao sao defeitos)
- Niveis num mapa em codigo (sem alteracao de esquema), a semelhanca do risco das permissoes.
- Perfis desconhecidos ou personalizados no nivel 0 (lado seguro).
- Regra estritamente inferior (preserva e reforca as garantias do Incr 1).
- Regra anti-escalada com precedencia sobre a de nivel (so o administrador WordPress real cria gestores).

## Conclusao
Zero P0 e zero P1. Aprovado para entrega como v12.12.29. Continua a Fase 9 (seguranca e governanca de acessos). Deferido para a Fase 9 Incr 3 (declarado): sobreposicao puramente aditiva (deixar de trocar o WP role) e niveis editaveis na base de dados ou na interface.
