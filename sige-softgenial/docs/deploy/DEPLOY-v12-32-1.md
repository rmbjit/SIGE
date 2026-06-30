# DEPLOY - SIGE SoftGenial v12.32.1 (HOTFIX)

**Crachá modelos: pré-visualização e impressão com estilos + sucesso claro**
Data: 2026-06-30 - Tipo: correcção de cliente/JS (view=alunos_lista).

## Corrige
1. Pré-visualização vazia.
2. Impressão "plain branco sem css".
3. Gravação sem mensagem de sucesso clara.

## Como
- **Auto-cura** do registo de modelos (carrega o asset sob demanda se faltar).
- **Nonce vivo** lido da CSP em vigor (estilos passam a aplicar-se).
- Pré-visualização via `contentDocument.write` (mais fiável que `srcdoc`).
- Impressão garante o registo antes de escrever o documento.
- Gravação mostra um **toast de sucesso** claro e fecha o modal.

> Verificado com Chromium sob CSP real: a pré-visualização renderiza estilizada.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/academic/alunos_lista.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/assets/cracha/sige-cracha-templates.js
sige-softgenial/includes/cracha-config.php
sige-softgenial/includes/ui-kit.php
sige-softgenial/tools/smoke-cracha-modelos-v12-32-0.php
```
(Inclui os ficheiros da funcionalidade para o pacote ser auto-suficiente.)

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. **Limpe a cache** do navegador/CDN para
   garantir o JS novo. Sem migração de dados.

## Verificação rápida
- Abrir "Modelo de Crachá" → a **pré-visualização** mostra o cartão estilizado.
- Trocar modelo/cor/redes → a pré-visualização actualiza.
- **Guardar** → aparece um aviso de sucesso com o nome do modelo; o modal fecha.
- Imprimir (individual e lote) → sai no modelo escolhido, **com estilos**.

## Fronteira
- Só cliente/JS. Sem tocar em config/schema/permissões/fórmulas/ficheiros protegidos.
