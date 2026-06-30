# LIVE-TEST - SIGE SoftGenial v12.32.0 (Modelos de crachá por escola)

Objectivo: confirmar a escolha do modelo de crachá, a pré-visualização ao vivo,
a gravação por escola e que a impressão usa o modelo escolhido — sem regressões.

## Pré-requisitos
- v12.32.0 instalada (ver `docs/deploy/DEPLOY-v12-32-0.md`).
- Conta com permissão de configuração (Direcção / Admin TI / Gestor RH /
  Secretaria) e uma conta sem essa permissão.

## Casos

### 1. Visibilidade do botão
- Com perfil de Direcção: em **Alunos**, a barra mostra **"Modelo de Crachá"**.
- Com perfil sem `configuracoes.editar`: o botão **não** aparece.

### 2. Seletor + pré-visualização ao vivo
- Abrir o modal. ESPERADO: 4 modelos (Aurora, Clássico, Vivid, Minimal) e uma
  pré-visualização.
- Trocar de modelo → a pré-visualização muda na hora.
- Mudar a cor de destaque → a pré-visualização reflecte a cor.
- Activar "Mostrar redes sociais" e preencher Instagram/Facebook/Website → as
  redes aparecem na pré-visualização.

### 3. Gravar (por escola)
- Guardar. ESPERADO: "Modelo guardado para toda a escola."
- Recarregar a página e reabrir o modal → as escolhas persistem.

### 4. Impressão usa o modelo escolhido
- Imprimir crachá individual (botão "Cartão" numa linha) → sai no modelo/cor/
  redes escolhidos.
- Imprimir em lote ("Cartões (Lote)") → todos no modelo escolhido.

### 5. Consistência preview ↔ impressão
- O que se viu na pré-visualização é o que sai impresso (mesmo registo).

### 6. Robustez
- QR presente; se indisponível, sai o nº de processo legível.
- Nome com `&`/`<` não quebra o cartão (escape).
- Bloquear pop-ups → aviso ao imprimir (não falha em silêncio).

### 7. Não-regressão
- Sem alterar o modelo, a impressão continua a funcionar (default Aurora).
- Lista de alunos, filtros, ficha 360, exportação Excel inalterados.
- Validação no portão (Portaria) continua a ler o QR.

## Resultado
- [ ] Caso 1 OK
- [ ] Caso 2 OK
- [ ] Caso 3 OK
- [ ] Caso 4 OK
- [ ] Caso 5 OK
- [ ] Caso 6 OK
- [ ] Caso 7 OK
