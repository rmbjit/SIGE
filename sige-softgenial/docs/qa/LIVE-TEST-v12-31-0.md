# LIVE-TEST - SIGE SoftGenial v12.31.0 (Crachá do estudante)

Objectivo: confirmar a robustez do crachá (individual e em lote) e a paridade
com o portão, sem regressões.

## Pré-requisitos
- v12.31.0 instalada (ver `docs/deploy/DEPLOY-v12-31-0.md`).
- Perfil com permissão de emitir documentos/cartões.
- Pelo menos um aluno com foto e turma, e um sem matrícula no ano.

## Casos

### 1. Crachá individual
- Botão "Cartão" numa linha de aluno.
- ESPERADO: janela abre; logótipo + foto carregam; nome, processo e turma
  correctos; QR presente; imprime só depois das imagens.

### 2. Crachá em lote
- "Imprimir cartões" com uma turma seleccionada.
- ESPERADO: todos os cartões com foto/QR e turma correcta; a impressão dispara
  depois das imagens (testar com ligação lenta, se possível).

### 3. Aluno sem turma
- Aluno sem matrícula no ano lectivo actual.
- ESPERADO: mostra "S/ Turma" (correcto), restante crachá normal.

### 4. Nome com caracteres especiais
- Aluno com `&`, `<` ou `"` no nome (ou simular).
- ESPERADO: o nome aparece tal e qual, sem quebrar o layout do cartão.

### 5. Pop-up bloqueado
- Bloquear pop-ups no navegador e tentar imprimir.
- ESPERADO: aviso "Pop-up bloqueado…"; nada falha em silêncio.

### 6. QR indisponível (opcional)
- Se o QR não for gerado (ex.: biblioteca não carregou).
- ESPERADO: o rodapé mostra o número de processo legível (não fica vazio).

### 7. Validação no portão (Portaria)
- Ler o QR de um crachá impresso na Portaria Digital.
- ESPERADO: valida o aluno (processo numérico). Confirma o estado activo.

### 8. Não-regressão
- A lista de alunos, filtros, exportação Excel e ficha 360 continuam a funcionar.

## Resultado
- [ ] Caso 1 OK
- [ ] Caso 2 OK
- [ ] Caso 3 OK
- [ ] Caso 4 OK
- [ ] Caso 5 OK
- [ ] Caso 6 OK
- [ ] Caso 7 OK
- [ ] Caso 8 OK
