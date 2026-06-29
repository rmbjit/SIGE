# QA - SIGE SoftGenial v12.12.24

Fase 8 incremento 2: Direito de acesso e portabilidade. Data: 2026-06-21.

## Ambito testado

- Helper do dossie (includes/privacy/pii-dossier.php): reune dados pessoais reais
  de um aluno por tabela e categoria; so leitura; fail-closed por escola; lista
  branca de identificadores de coluna; serializador JSON puro.
- Endpoint de exportacao (includes/privacy/pii-dossier-export.php): admin_post
  governado em enforce; permissao, nonce, escola, auditoria, cabecalhos de ficheiro.
- Ecra (admin/system/privacidade-acesso-view.php): procura por GET, dossie no ecra,
  exportacao por POST a admin-post.php; sem processamento de POST no ecra; sem
  estilo inline.
- Permissao privacidade.acesso_exportar: registo, semente, migracao, auditoria
  sempre-registada.
- Governanca: manifesto e Kernel a 198 (enforce 32), alinhados; SCHEMA inalterada.

## Verificacoes automaticas

- check-acesso: OK (dossie so de leitura e fail-closed; exportacao governada; ecra
  sem POST nem estilo inline; rota governada; permissao semeada e auditada; regra
  do Kernel em enforce; sem migracao de esquema).
- smoke-acesso: OK (dossie reune dados reais; fail-closed por escola/aluno;
  isolamento por escola nas linhas; lista branca de colunas; JSON valido).
- check-action-surface-manifest: OK (198 superficies, extractores alinhados).
- check-security-kernel-rules: OK (198 regras; enforce 32; PHP e JSON alinhados).
- check-governance-docs: OK (12 documentos e 7 baselines presentes).
- run-gates: 64/64 verde.
- smoke-release-gate: OK a partir de pasta limpa.

## Resultado

Zero P0/P1. Pronto para entrega. Cenarios de teste em producao em
docs/qa/LIVE-TEST-SCENARIOS-v12.12.24.md.
