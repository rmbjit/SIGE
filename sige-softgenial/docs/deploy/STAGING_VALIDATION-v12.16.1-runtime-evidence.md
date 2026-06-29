# STAGING VALIDATION v12.16.1 - Runtime Evidence & Technical Debt Closure

## Objectivo
Validar a v12.16.1 em staging como release de blindagem, sem aceitar regressões funcionais.

## Perfis obrigatórios
- Administrador.
- Director.
- Financeiro.
- Secretaria.
- Professor.
- Guarda.
- Encarregado.
- Aluno.

## Fluxos obrigatórios
- Login e abertura do painel principal.
- Professor em Minhas Turmas.
- Financeiro em pagamentos, extratos e devedores.
- Secretaria em alunos e ficha do aluno.
- Guarda em portaria.
- Encarregado ou aluno em portal.
- Administrador em permissões e estado do sistema.

## Critério de aceite
- Sem erro fatal.
- Sem tela branca.
- Sem loop de redireccionamento.
- Sem #038;view em URL.
- Sem alargamento indevido de permissões.
- Mobile funcional em 360px, 390px e 430px.
- Desktop funcional em 1366px.

## Evidência esperada
- Summary JSON da suite runtime.
- Screenshots por perfil e viewport.
- Confirmação humana dos fluxos sensíveis.

## Honestidade de execução
Este pacote prepara a suite. A execução browser real depende de staging autenticado e deve ser anexada depois da instalação em staging.
