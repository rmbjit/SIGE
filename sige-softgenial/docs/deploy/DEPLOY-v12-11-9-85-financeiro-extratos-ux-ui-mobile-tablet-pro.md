# Deploy - SIGE SoftGenial v12.11.9.85

**Build:** `sige-12.11.9.85-financeiro-extratos-ux-ui-mobile-tablet-pro`  
**Módulo:** Financeiro > Extractos/Caixa  
**Data:** 2026-06-10

## Objectivo

Melhorar profundamente a UX/UI e intuitividade do módulo `financeiro-extratos`, com prioridade para mobile, tablet e laptop, sem mexer nas regras financeiras existentes.

## Antes de instalar

1. Fazer backup da pasta actual do plugin.
2. Fazer backup da base de dados.
3. Confirmar que a instalação actual está na linha `12.11.9.84` ou compatível.
4. Limpar cache do WordPress/CDN depois de activar a build, porque `assets/style.css` recebeu overrides globais versionados.

## Instalação recomendada

1. Substituir a pasta do plugin pela pasta `sige-softgenial` deste pacote.
2. Confirmar no WordPress que o plugin aparece como `Version: 12.11.9.85`.
3. Abrir `SIGE > Financeiro > Extractos/Caixa`.
4. Testar em três larguras: telemóvel, tablet e laptop/desktop.

## Testes funcionais rápidos

### Modo diário
- Abrir o extracto diário de hoje.
- Alterar período para mensal e anual.
- Aplicar ciclo e centro, quando disponível.
- Confirmar que os chips de filtros reflectem o filtro activo.
- Confirmar que o Excel ainda exporta.

### Caixa
- Em dia aberto, verificar se o cartão “Fecho de caixa” aparece para perfis autorizados.
- Confirmar que o modal de confirmação abre antes do fecho.
- Em dia fechado, confirmar que o resumo de fecho aparece uma vez, sem banner duplicado.
- Para perfil autorizado, verificar o formulário de reabertura com motivo obrigatório.

### Aluno
- Pesquisar por nome e por número de processo.
- Abrir um resultado.
- Confirmar que o histórico do aluno mostra cartão de perfil, estatísticas e tabela.
- Confirmar que o documento/PDF e Excel do histórico continuam acessíveis.

### Mobile/tablet
- Verificar que a tabela vira cards no telemóvel.
- Confirmar que cada card mostra rótulos claros: Data/Hora, Recibo, Descrição, Método, Valor e Acções.
- Marcar pagamentos e confirmar que a barra de recibo unificado aparece no fundo.
- Abrir e fechar modais com Escape e botões de voltar.

## Validação técnica incluída

Executar na raiz do plugin:

```bash
php tools/smoke-financeiro-extratos-ux-ui-v12-11-9-85.php
```

Resultado esperado:

```text
SMOKE OK - financeiro-extratos UX/UI v12.11.9.85 validado.
```

Validação sintática opcional:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

## Rollback

Se houver qualquer incompatibilidade visual com tema/cache local:

1. Repor a pasta do plugin da versão anterior.
2. Limpar cache do WordPress/CDN/navegador.
3. Confirmar que `SIGE_VERSION` voltou à versão anterior.

## Nota de escopo

Esta build não introduz migrações de base de dados. A alteração é de apresentação, organização de UI, acessibilidade e experiência responsiva.
