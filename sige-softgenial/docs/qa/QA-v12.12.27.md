# QA - SIGE SoftGenial v12.12.27

Fase 8 incremento 4: Retencao e Expurgo (so leitura).
Data: 2026-06-22. Base: v12.12.26.

## Resultado

- Corredor de gates: 68/68 verde (sobe de 66; dois gates novos: check-retencao, smoke-retencao).
- Release gate: verde a partir de pasta limpa.
- Lint PHP: 0 erros em toda a arvore.
- Travessoes: 0.
- Calculo financeiro: finance-core.php, academic-logic.php e whatsapp-engine.php byte-identicos a baseline v12.12.21.
- Baselines de design: tokens 2414 e consistencia 7497 sem regressao; zero CSS nova (so classes existentes).
- Superficie de accao: 199 (enforce 33); manifesto == Kernel. Sem nova superficie. Views allowlist 59 -> 60.
- SCHEMA_VERSION inalterada (20260621.1).

## Cobertura do incremento

Gate estatico (check-retencao):
- Motor so de leitura: proibido qualquer insert/update/delete/drop/alter/truncate; presente SELECT COUNT; fail-closed por escola; lista branca de identificadores; isolamento por escola_id.
- Ecra: sem POST, sem accao destrutiva (sem admin-post.php, sem nonce, sem accao de apagar), encaminha para o Apagamento, sem estilo inline.
- Rota governada; modulo carregado; permissao de leitura registada, semeada e migrada (121227); sem regra do Kernel (so leitura); sem migracao de esquema.
- Calendario integro: campos validos, modos validos, acessos retidos (fonte das presencas), tabelas obrigatorias cobertas; funcoes puras (cutoff recua, prazo_legivel correcto).

Smoke runtime (smoke-retencao), $wpdb simulado:
- Panorama conta alem do prazo por categoria e por escola; total e total anonimizavel (exclui retidos) correctos.
- Contagem de sige_alunos restrita a nao activos (status <> activo) e isolada por escola_id; tabelas sem so_inactivos nao tem o filtro.
- Tabela sem coluna de data de aferir e nao mensuravel (devolve null); fail-closed (escola = 0); isolamento (outra escola conta 0).
- Funcoes puras correctas.

Nota: o smoke detectou um defeito real de duplo prefixo na leitura de colunas (que teria mostrado sem data em todas as categorias), corrigido na mesma sessao.

## Rediagnostico adversarial

Zero P0 / Zero P1. Ver docs/governance/ADVERSARIAL_REVIEW-v12.12.27.md.
