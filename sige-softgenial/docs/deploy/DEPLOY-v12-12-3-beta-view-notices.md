SIGE SoftGenial v12.12.3 - Notas Beta nos Módulos em Validação

Tipo de alteração
- UI informativa e governança de comunicação ao utilizador.

Impacto
- Os views financeiro-mpesa, whatsapp_circulares, comunicacoes_central e presencas passam a mostrar selo BETA e nota operacional no cabeçalho central.
- Não há alteração de base de dados.
- Não há migração.
- Não há alteração de permissões.
- Não há alteração de regras de negócio.

Validação em staging
1. Abrir `?page=sige-app&view=financeiro-mpesa` e confirmar selo BETA + nota.
2. Abrir `?page=sige-app&view=whatsapp_circulares` e confirmar selo BETA + nota.
3. Abrir `?page=sige-app&view=comunicacoes_central` e confirmar selo BETA + nota.
4. Abrir `?page=sige-app&view=presencas` e confirmar selo BETA + nota.
5. Confirmar que os formulários e botões existentes continuam funcionais.

Rollback
- Reinstalar v12.12.2 se necessário. Como não há schema/migração, rollback é directo.
