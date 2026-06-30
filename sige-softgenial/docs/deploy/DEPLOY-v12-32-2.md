# DEPLOY - SIGE SoftGenial v12.32.2 (HOTFIX 2)

**Crachá modelos: registo entregue INLINE (à prova de falhas de HTTP)**
Data: 2026-06-30 - Tipo: correcção de entrega (view=alunos_lista).

## Corrige (definitivo)
- Pré-visualização "indisponível" e impressão a usar o cartão simples — causadas
  pelo ficheiro do registo não ser servido por HTTP no ambiente do utilizador.

## Como
- O registo de modelos passa a ser **embutido inline** pela view, lido do
  **filesystem** (`readfile`), com o nonce de CSP. Sem pedido HTTP → imune a
  404/CDN/cache. Removido o enqueue HTTP (já desnecessário).
- Mantém nonce vivo, `contentDocument.write` e toast de sucesso (da 12.32.1).

> Verificado com Chromium sob CSP: pré-visualização e impressão renderizam os
> modelos estilizados.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/academic/alunos_lista.php
sige-softgenial/includes/ui-kit.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/assets/cracha/sige-cracha-templates.js
sige-softgenial/includes/cracha-config.php
sige-softgenial/tools/smoke-cracha-modelos-v12-32-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, **garantindo que `assets/cracha/sige-cracha-templates.js`
   fica no servidor** (é lido pelo PHP). Sem migração de dados.
3. Limpe a cache do navegador (o HTML/JS da página mudou).

## Verificação rápida
- Abrir "Modelo de Crachá" → a **pré-visualização mostra o cartão estilizado**
  (já não "indisponível").
- Trocar modelo/cor/redes → a pré-visualização actualiza.
- **Guardar** → toast de sucesso com o nome do modelo; o modal fecha.
- Imprimir (individual e lote) → sai **no modelo escolhido, com estilos** (não o
  cartão simples).

## Fronteira
- Só entrega/cliente. Sem tocar em config/schema/permissões/fórmulas/ficheiros
  protegidos.
