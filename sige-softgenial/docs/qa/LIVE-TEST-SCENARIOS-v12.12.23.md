# Cenarios de Teste Live - SIGE SoftGenial v12.12.23

## Fase 8 incremento 1: Inventario de Dados Pessoais (governanca de PII, so leitura)

Testes manuais a executar no ambiente real apos o deploy. Cada cenario indica os passos e o resultado esperado.

---

### Cenario 1: acesso permitido (administracao ou direccao)
Passos:
1. Entrar no wp-admin com um utilizador de administracao (admin da escola ou admin TI) ou de direccao (Direccao Geral ou Director).
2. No menu lateral, localizar a seccao PRIVACIDADE E DADOS.
3. Clicar em Inventario de Dados.

Esperado:
- A pagina abre (nao volta ao painel inicial).
- No topo, o titulo Inventario de Dados Pessoais e um paragrafo de explicacao.
- Uma fila de cartoes de resumo: campos catalogados, presentes no esquema, campos sensiveis, tabelas com dados pessoais, desvios e lacunas.

---

### Cenario 2: acesso negado (tesouraria, secretaria, docencia)
Passos:
1. Entrar com um utilizador de tesouraria (ou secretaria, ou professor).
2. Verificar o menu lateral.
3. Forcar o endereco da pagina, acrescentando view=privacidade-dados ao endereco da aplicacao.

Esperado:
- O item Inventario de Dados NAO aparece no menu para este utilizador.
- Ao forcar o endereco, surge a mensagem de area reservada (a Direccao e administracao), e nenhum dado e mostrado.

---

### Cenario 3: agregados por escola (apenas contagens)
Passos:
1. Com acesso permitido, na pagina do inventario, ver a seccao Agregados desta escola.

Esperado:
- Cartoes com numeros: alunos registados, alunos com contacto de encarregado, funcionarios registados, alunos com registo de saude, pagamentos, transacoes moveis, registos de acesso, e os quatro consentimentos (WhatsApp, e-mail, SMS, chamada).
- Sao apenas numeros. Nenhum nome, telefone ou outro dado individual aparece.
- Os numeros correspondem a escola activa. Se trocar de escola (em instalacao multi-escola), os numeros mudam em conformidade.

---

### Cenario 4: mapa de dados pessoais por tabela
Passos:
1. Descer ate a seccao Mapa de dados pessoais por tabela.
2. Percorrer as tabelas (por exemplo sige_alunos, sige_jardim_saude, sige_professores).

Esperado:
- Para cada tabela, um quadro com as colunas: Campo, Categoria, Sensibilidade, Finalidade, Base legal e No esquema.
- Os campos de saude (em sige_jardim_saude) e os campos bancarios e salariais (em sige_professores) aparecem marcados como sensivel.
- A coluna No esquema indica sim ou nao consoante a coluna exista mesmo na base de dados desta instalacao.

---

### Cenario 5: deteccao de desvios e lacunas
Passos:
1. No resumo do topo, observar os cartoes Desvios e Lacunas.
2. Se algum for maior que zero, ver os avisos amarelos correspondentes mais abaixo.

Esperado:
- Desvios lista campos classificados que nao existem no esquema desta instalacao (catalogo a precisar de revisao).
- Lacunas lista colunas com aspeto de dado pessoal que ainda nao estao classificadas (a rever num proximo incremento).
- Numa instalacao tipica, espera-se zero ou poucos; o objectivo e que o numero seja visivel e accionavel, nao escondido.

---

### Cenario 6: confirmacao de que e so leitura
Passos:
1. Percorrer a pagina inteira do inventario.

Esperado:
- Nao existe qualquer botao de gravar, formulario, caixa de edicao ou accao destrutiva.
- A pagina apenas apresenta informacao. Nada nela altera dados.

---

### Cenario 7: versao e ausencia de regressao
Passos:
1. Em Plugins, confirmar a versao.
2. Abrir uma pagina financeira (por exemplo Pagamentos) e uma academica (por exemplo Pautas) e confirmar que abrem normalmente.

Esperado:
- A versao le 12.12.23.
- As paginas financeiras e academicas funcionam como antes; o incremento nao toca em calculo nem em fluxos existentes.

---

## Resultado esperado global
Todos os cenarios passam. O inventario abre so para administracao e direccao, mostra contagens e o mapa de PII sem expor dados individuais, e e inteiramente de leitura. Sem regressao nas restantes areas.
