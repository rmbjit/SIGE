# DEFINITION OF DONE v12.16.1 - Runtime Evidence & Technical Debt Closure

## DoD global da fase
A fase so fica fechada quando a blindagem for verificável por uma pessoa técnica e validável por uma pessoa nao técnica em staging.

## Critérios bloqueadores
- P0 aberto bloqueia ZIP final.
- P1 aberto bloqueia ZIP final, salvo aceite explícito e documentado.
- Qualquer alteração em formula financeira ou académica bloqueia a fase.
- Qualquer alteração em schema ou dados bloqueia a fase.
- Qualquer expansão real de permissão bloqueia a fase.
- Qualquer gate vermelho bloqueia ZIP final.

## Critérios técnicos obrigatórios
- Header Version, SIGE_VERSION e BUILD.json sincronizados em 12.16.1.
- BUILD.json contém source_package_sha256 da v12.16.0.
- tools/run-gates.php contém os gates v12.16.1.
- Suite tools/runtime-evidence existe e nao contém credenciais.
- Contrato de shell verifica allowlist, mapa de rotas e matriz de permissões.
- Allowlist preserva o mesmo conjunto unico de views e remove duplicados históricos inofensivos.

## Critérios de QA local
- php -l em todos os PHP do pacote.
- php tools/smoke-release-gate.php.
- php tools/run-gates.php.
- unzip -t no ZIP final.
- Hash SHA256 final calculado fora do pacote.

## Critérios de QA staging
- Login por perfil com storage state isolado.
- Views principais abertas em desktop e mobile.
- Screenshots capturados por perfil e viewport.
- Ausencia de erro fatal, tela branca, loop e URL com #038;view.
- Header mobile sem compressao funcional.
- Professor entra em Minhas Turmas.
- Guarda usa Portaria sem menus indevidos.

## Regra de honestidade de QA
Sem acesso a staging autenticado, a fase pode preparar a suite runtime e provar os contratos locais, mas nao pode declarar browser staging executado. O resultado deve dizer: preparado, nao executado aqui.
