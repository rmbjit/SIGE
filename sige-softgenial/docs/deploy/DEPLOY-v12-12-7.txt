v12.12.7 - Critical Actions Lockdown

Instalar primeiro em staging. Validar perfis Admin TI, Direcção, Tesouraria, Financeiro apenas leitura, Secretaria e utilizador sem permissão.

Pontos obrigatórios de validação:
- Registar pagamento com financeiro.pagar.
- Tentar registar pagamento com financeiro.ver apenas.
- Conciliar/rejeitar M-Pesa com transacção da escola corrente e de outra escola.
- Criar/aprovar/anular despesa na escola corrente.
- Alterar matriz de permissões numa escola e verificar outra escola.
- Arquivar aluno e confirmar preservação de pagamentos/notas/matrículas.
- Testar callbacks M-Pesa/e-Mola com token correcto e inválido.

Schema: migração aditiva, criando tabelas tenant-scoped de permissões se ainda não existirem.
Rollback: restaurar pacote anterior e backup da base caso a validação funcional identifique regressão crítica.
