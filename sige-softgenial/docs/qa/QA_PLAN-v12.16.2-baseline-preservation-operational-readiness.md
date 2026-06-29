# QA Plan - v12.16.2

## Validações CLI obrigatorias
1. php -l em todos os ficheiros PHP.
2. php tools/smoke-release-gate.php.
3. php tools/run-gates.php.
4. unzip -t do ZIP final.
5. sha256sum do ZIP final.

## Gates novos
- tools/check-v12-16-2-baseline-preservation.php.
- tools/smoke-v12-16-2-operational-readiness.php.
- tools/check-v12-16-2-package-manifest.php.
- tools/check-v12-16-2-regression-matrix.php.

## Viewports obrigatorios para validacao humana ou Playwright
- 360 px.
- 390 px.
- 430 px.
- 768 px.
- 1366 px.

## Como validar em staging
Instalar o ZIP, limpar cache se existir, entrar com perfis reais e executar o checklist pos-instalacao. Qualquer erro fatal, loop, permissao indevida, quebra financeira ou academica bloqueia a release.

## Fluxos minimos
| Perfil | Fluxo minimo | Criterio |
|---|---|---|
| Administrador | Dashboard, Estado do sistema, Permissoes | Sem fatal e sem acesso indevido |
| Director | Dashboard, Comece Aqui, mapa operacional | Navegacao clara |
| Financeiro | Pagamentos, Extractos, Devedores | Abre sem alterar formulas |
| Secretaria | Alunos, pesquisa, ficha 360 | Abre sem lentidao extrema |
| Professor | Minhas Turmas, Notas, Pautas | Sem loop e sem #038;view |
| Guarda | Portaria mobile | Operacional em segundos |
| Encarregado | Portal | Leve e compreensivel |
| Aluno | Portal e documentos | Sem erro fatal |
