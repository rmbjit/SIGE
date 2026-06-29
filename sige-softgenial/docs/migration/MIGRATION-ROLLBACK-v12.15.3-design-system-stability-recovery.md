# Migração e Rollback - v12.15.3

## Instalação
1. Fazer backup do plugin actual e da base de dados.
2. Instalar o ZIP v12.15.3 por cima da v12.15.0/v12.15.1/v12.15.2.
3. Limpar cache do navegador/servidor.
4. Testar Alunos, Pagamentos e páginas longas em tablet.

## Rollback
- Se houver problema crítico, voltar para v12.14.4, que foi a última base aprovada antes da camada Design System PRO global.
- Atenção: ao voltar para v12.14.4 perde-se apenas a validação extra de confirmação de pagamento adicionada nesta correcção.

## Nota operacional
Não reinstalar v12.15.0, v12.15.1 ou v12.15.2 em produção/staging validado, porque essas versões carregam a camada global que causou regressões.
