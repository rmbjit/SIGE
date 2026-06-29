# Contrato Visual - Alunos e Matrículas - v12.15.7

## Escopo
Esta vaga aplica Design System PRO apenas à view `alunos_lista`.

## Permitido
- `assets/views/alunos-design-pro.css`
- `assets/views/alunos-design-pro.js`
- Enqueue condicionado por `view=alunos_lista`
- Selectores escopados por `body.sige-admin-app.sige-view-alunos_lista`
- Ajustes de hero, filtros, cards, acções, estados, modais, responsividade e foco visível dentro de Alunos

## Proibido
- CSS global agressivo
- Normalizar `button`, `input`, `table`, `.card`, `.modal` sem escopo
- Tocar em Financeiro, Portaria, PDFs/documentos, RH, Sistema ou shell
- Reintroduzir `sige-design-system-pro.css/js`
- Usar `eval`, `new Function`, inline handlers ou relaxar CSP

## Feature flag
A camada pode ser desligada sem rollback total:

```sql
UPDATE wp_options SET option_value = '0' WHERE option_name = 'sige_design_alunos_v12157_enabled';
```

Se a option não existir, o padrão é activo.

## Contratos funcionais
- Desktop: botões rápidos mobile ocultos; acções principais via três pontinhos.
- Menu de três pontinhos: vertical, com rótulos visíveis quando aberto.
- Mobile/tablet estreito: menu entra no fluxo do card para não cobrir o card seguinte.
- Texto de nome, turma, badges e WhatsApp não deve quebrar letra por letra.
- Modais de aluno devem manter scroll interno.
- Foco visível obrigatório em botões, links, inputs, selects, textarea e summary.
