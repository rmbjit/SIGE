# QA - SIGE SoftGenial v12.12.25

Fase 8 incremento 3: Apagamento por anonimizacao (direito ao apagamento).
Data: 2026-06-21. Base: v12.12.24.

## Resultado

- Corredor de gates: 66/66 verde (sobe de 64; dois gates novos: check-apagamento, smoke-apagamento).
- Release gate: verde a partir de pasta limpa.
- Lint PHP: 0 erros em toda a arvore.
- Travessoes (em/en-dash): 0 em .php/.md/.css/.json/.txt.
- Calculo financeiro: finance-core.php, academic-logic.php e whatsapp-engine.php byte-identicos a baseline v12.12.21.
- Baselines de design: tokens 2414 e consistencia visual 7497 sem regressao; sem hex nem estilo inline novos.
- Superficie de accao: 199 (admin_post 41); enforce 33; manifesto == Kernel (199 == 199).
- SCHEMA_VERSION inalterada (20260621.1).

## Cobertura de teste do incremento

Gate estatico (check-apagamento):
- Motor destrutivo-governado: so UPDATE, nunca DELETE/DROP/TRUNCATE/ALTER.
- Fail-closed por escola e por pertenca; lista branca de identificadores; lista de preservacao (numero_processo, aluno_id, data_hora); escola_id no WHERE.
- Handler: guarda de permissao (wp_die), nonce, contexto de escola, confirmacao em dois passos (strcasecmp do numero de processo), auditoria antes e depois, redireccao e fim de pedido.
- Ecra: sem POST, aponta a admin-post.php, accao e nonce correctos, campo de confirmacao presente, sem estilo inline.
- Rota governada (mapa, allowlist, matriz, menu); modulos carregados; permissao critica registada, semeada, migrada (121225) e sempre auditada; regra do Kernel em enforce/critico; CSS da zona de perigo; sem migracao de esquema.
- Resolver de redaccao seguro quanto ao tipo (texto, data anulavel/nao anulavel, numero, enumerado; marcador nunca excede o comprimento).

Smoke runtime (smoke-apagamento), $wpdb simulado e stateful:
- Plano reune seccoes e exclui a lista de preservacao.
- Execucao redige nome (marcador), data (sentinela) e consentimento (nulo); NAO toca numero_processo nem colunas financeiras simuladas; isola por escola_id; filtra pelo aluno.
- Fail-closed nos quatro casos (aluno fora da escola no plano e na execucao, aluno = 0, escola = 0).
- Idempotencia: apos a anonimizacao o aluno e detectado como anonimizado e a reexecucao mantem o estado.
- Seguranca de tipo do resolver reconfirmada.

## Rediagnostico adversarial

Zero P0 / Zero P1. Ver docs/governance/ADVERSARIAL_REVIEW-v12.12.25.md (12 vectores cobertos, incluindo a superficie destrutiva).
