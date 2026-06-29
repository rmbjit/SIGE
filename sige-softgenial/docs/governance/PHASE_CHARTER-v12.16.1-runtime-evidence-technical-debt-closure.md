# PHASE CHARTER v12.16.1 - Runtime Evidence & Technical Debt Closure

## Nome da fase
v12.16.1 - Runtime Evidence & Technical Debt Closure

## Objectivo
Blindar a v12.16.0 aprovada com evidência reproduzível de QA runtime, contrato de shell, manifesto final e fecho documental, sem alterar regras financeiras, regras académicas, permissões reais, schema ou dados históricos.

## Baseline protegida
- Versao de origem: 12.16.0 final.
- Pacote de origem SHA256: f02cd9260f33bfc454b40bf5fe4b4629b97d147ac70eee158b0851283e53ff2a.
- Staging humano: aprovado pelo utilizador antes desta fase.
- Escopo de código runtime: minimo e reversivel.

## Escopo incluido
- RE-16-01: Suite de evidência browser autenticada preparada por perfil.
- RE-16-02: Contrato de shell, rotas, allowlist e permissões reforçado por gate.
- RE-16-03: Manifesto BUILD final enriquecido com origem, hash, restrições e honestidade de QA.
- RE-16-04: Plano de staging com perfis reais e dispositivos minimos.
- RE-16-05: Limpeza segura de duplicados inofensivos na allowlist sem alterar o conjunto de views.
- RE-16-06: QA local repetível: lint, gates, release gate e zip integrity.

## Escopo excluido
- Alterar formulas financeiras, multas, descontos, saldos, recibos ou transporte.
- Alterar formulas académicas, notas, pautas, boletins, DEC ou actas.
- Alterar permissões reais ou ampliar acesso por perfil.
- Alterar schema, tabelas, migrações ou dados históricos.
- Refactor profundo de alunos_lista.php.
- Mudar UI visual global alem do contrato documental e da limpeza segura de allowlist.

## Perfis afectados
Director, financeiro, secretaria, professor, guarda, encarregado, aluno, administrador técnico e utilizador nao técnico. A fase nao deve alterar a experiencia funcional destes perfis, apenas melhorar a capacidade de provar que a experiencia continua estável.

## Módulos afectados
- tools: novos gates e suite de evidência browser.
- docs: charter, QA, risco, rastreabilidade, staging e rollback.
- BUILD.json e CHANGELOG: manifesto da release.
- includes/admin-shell.php: apenas limpeza de duplicados na allowlist, preservando o conjunto unico de views.

## Regras que nao podem ser alteradas
- Financeiro: formulas, lançamentos, recibos, dívidas, descontos, multas e transporte.
- Académico: formulas, notas, pautas, boletins, actas e DEC.
- Segurança: tenant isolation, permissões reais, MFA, vault, kernel e uploads.
- Dados: nenhuma escrita, migração, apagamento, normalização ou alteração histórica.

## Ordem de intervenção
1. Congelar baseline e hashes protegidos.
2. Criar artefactos formais da v12.16.1.
3. Criar gates de contrato runtime, shell e manifesto.
4. Preparar suite browser autenticada, sem guardar credenciais.
5. Actualizar manifesto e changelog.
6. Executar lint, gates e integridade de ZIP.
7. Gerar ZIP final apenas se P0 e P1 forem zero.

## Plano de rollback
- Repor ZIP v12.16.0 final se qualquer P0/P1 surgir em staging.
- Reverter includes/admin-shell.php para a baseline se a allowlist apresentar falha.
- Manter tools/docs como nao runtime se for preciso reverter apenas interface.

## Definition of Done resumida
- P0 = 0.
- P1 = 0.
- PHP lint verde.
- run-gates verde.
- release gate verde.
- ZIP íntegro.
- Suite browser preparada para staging por perfil.
- Honestidade de QA documentada: browser real preparado, mas nao executado neste ambiente sem staging autenticado.
