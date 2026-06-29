# Migração e Rollback - v12.15.7

## Migração
1. Instalar o ZIP por cima da v12.15.6 aprovada.
2. Limpar cache do WordPress/servidor/navegador.
3. Abrir `wp-admin/admin.php?page=sige-app&view=alunos_lista`.
4. Confirmar que o CSS `sige-alunos-design-pro-v12157` e o JS `sige-alunos-design-pro-v12157` carregam apenas nesta view.

## Testes manuais obrigatórios
- Desktop 1440x900 e 1366x768: cards, filtros, três pontinhos, editar, ficha 360º.
- Tablet 1024x768 e 768x1024: scroll, filtros, cards, menu de acções.
- Mobile 430x932, 390x844 e 360x740: cards, botões rápidos, menu de acções, modal.
- Verificar que Financeiro, Portaria e PDFs não mudaram.

## Rollback localizado
Para desligar só esta camada visual:

```sql
INSERT INTO wp_options (option_name, option_value, autoload)
VALUES ('sige_design_alunos_v12157_enabled', '0', 'no')
ON DUPLICATE KEY UPDATE option_value = '0';
```

Ou, se o ambiente não suportar `ON DUPLICATE KEY`, actualizar/criar a option manualmente.

## Rollback total
Reinstalar v12.15.6.
