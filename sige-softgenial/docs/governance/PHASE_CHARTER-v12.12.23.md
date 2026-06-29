# PHASE CHARTER - SIGE SoftGenial v12.12.23

## Fase 8 - Dados, privacidade e retencao (Incremento 1: Inventario e classificacao de PII)

Fase multi-incremento, fail-closed por escola, alinhada com os principios de minimizacao, finalidade, retencao limitada e direitos do titular. Roteiro: Incr 1 inventario e classificacao de dados pessoais (so leitura); Incr 2 direito de acesso e portabilidade; Incr 3 apagamento e anonimizacao preservando o Ledger e a contabilidade; Incr 4 retencao e expurgo automatico mais registo de acessos a PII; Incr 5 consentimento e base legal por finalidade. Este documento cobre o Incremento 1.

## Objectivo

Saber, de forma auditavel e por escola, que dados pessoais o SIGE guarda, onde, para que e com que base legal. Entregar um ecra de governanca de dados so de leitura que apresenta o mapa de PII por tabela e campo (categoria, sensibilidade, finalidade, base legal), deteta lacunas e desvios face ao esquema vivo, e mostra agregados estatisticos por escola sem nunca expor dados individuais.

## Incluido

- Catalogo declarado de PII em includes/privacy/pii-catalog.php: 76 campos em 10 tabelas centrais (sige_alunos, sige_agregados_familiares, sige_alunos_encarregados_historico, sige_matriculas, sige_jardim_saude, sige_professores, sige_fin_pagamentos, sige_fin_contactos_cobranca, sige_mpesa_transacoes, sige_acessos), 19 sensiveis incluindo dados de saude e dados bancarios e salariais de funcionarios. Cada campo classificado por categoria, sensibilidade, finalidade e base legal.
- Helper de inventario so de leitura em includes/privacy/pii-inventario.php: confronta o catalogo com o esquema vivo (SHOW TABLES e SHOW COLUMNS), sinaliza desvios (campo catalogado ausente do esquema) e lacunas (coluna com aspeto de PII fora do catalogo), e calcula agregados por escola apenas com contagens.
- Ecra de governanca admin/system/privacidade-view.php, rota privacidade-dados: read-only, sem POST, sem formulario, sem endpoint de escrita. Estilo so com tokens (assets/views/privacidade.css, namespace sige-priv-).
- Acesso governado: nova permissao privacidade.inventario_ver, guarda sige_privacidade_pode_aceder, registo na allowlist e na matriz, semeada a administracao e direccao por migracao idempotente.
- Base legal por campo: valores por omissao prudentes (contrato educativo, obrigacao legal, interesse legitimo, consentimento), declarados como classificacao revisivel pela instituicao, responsavel pelo tratamento, e nao como parecer juridico.
- Gate e smoke dedicados (tools/check-privacidade.php, tools/smoke-privacidade.php), elevando o corredor para 62 verificacoes.

## Excluido

- Sem exportacao, apagamento ou anonimizacao (Incr 2 e 3). Sem expurgo nem cron (Incr 4). Sem alterar o modelo de consentimento existente (Incr 5).
- Sem nova superficie de accao: o ecra e so leitura, logo o manifesto e o Kernel mantem-se em 197. Sem migracao de esquema: o catalogo e codigo, os agregados leem tabelas existentes; SCHEMA_VERSION fica 20260621.1.
- Sem tocar nas regras de calculo financeiro nem em qualquer logica academica.

## Riscos

- Fuga de PII pelo proprio ecra de governanca. Mitigacao: por desenho so devolve agregados e contagens, nunca linhas; fail-closed por escola; sem POST.
- Catalogo incompleto. Mitigacao: deteccao automatica de lacunas, que denuncia tabelas e campos PII fora do catalogo em vez de os esconder.
- Classificacao legal imprecisa. Mitigacao: base legal marcada como classificacao institucional revisivel, com valores por omissao prudentes.

## Criterios de aceitacao

- Catalogo declara as tabelas PII centrais com categoria, sensibilidade, finalidade e base legal por campo; deteccao de lacunas e desvios a funcionar.
- Ecra abre so para quem tem privacidade.inventario_ver (aviso de area reservada caso contrario), apanhado pelo invariante de roteamento; so leitura, sem POST; tokenizado.
- Manifesto e Kernel em 197; SCHEMA inalterada; tres funcoes de calculo byte-identicas.
- Gate e smoke dedicados verdes; corredor verde (sobe de 60 para 62); rediagnostico adversarial Zero P0/P1.
