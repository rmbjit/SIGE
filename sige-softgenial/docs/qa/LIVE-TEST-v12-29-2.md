# LIVE-TEST - SIGE SoftGenial v12.29.2 (Painel: KPIs em auto-fit)

Objectivo: confirmar que a grelha de KPIs do painel nao deixa colunas vazias
em perfis com menos permissoes e mantem o comportamento responsivo.

## Pre-requisitos
- v12.29.2 instalada (ver `docs/deploy/DEPLOY-v12-29-2.md`).
- Acesso a contas de perfis diferentes (administrador e um perfil restrito).

## Casos

### 1. Perfil completo (4 KPIs)
- Entrar no painel como administrador/gestao.
- ESPERADO: 4 cartoes (Alunos Activos, Docentes, Pagamentos Hoje, Divida Total)
  numa linha, larguras iguais, sem espaco a sobrar.

### 2. Perfil restrito (menos de 4 KPIs)
- Entrar como perfil que so ve 2 ou 3 KPIs (ex.: sem permissao financeira).
- ESPERADO: apenas os cartoes permitidos, a preencher a largura da linha.
  NAO devem existir colunas/espacos vazios onde estariam os KPIs ocultos.

### 3. Responsivo - portatil
- Janela tipica de portatil (conteudo = janela menos sidebar).
- ESPERADO: cartoes legiveis, sem corte; sem necessidade de zoom out.

### 4. Breakpoints
- Largura <=1100px -> 2 colunas.
- Largura <=680px  -> 1 coluna (cartoes empilhados).

### 5. Nao-regressao
- Valores dos KPIs iguais aos da versao anterior (so muda o layout da grelha).
- Restantes blocos do painel (Resumo Financeiro, Distribuicao por Classe,
  Alertas, Acessos Rapidos) inalterados.
- Sem erros no console; sem handlers inline novos.

## Resultado
- [ ] Caso 1 OK
- [ ] Caso 2 OK
- [ ] Caso 3 OK
- [ ] Caso 4 OK
- [ ] Caso 5 OK
