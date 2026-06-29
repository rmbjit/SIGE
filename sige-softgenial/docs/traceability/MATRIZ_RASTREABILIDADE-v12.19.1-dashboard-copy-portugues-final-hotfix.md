# Matriz de Rastreabilidade - v12.19.1

| Problema | Causa | Solução | Ficheiro/módulo afectado | Teste obrigatório | Critério de aceitação |
|---|---|---|---|---|---|
| Copy sem acentos no dashboard por perfil | Strings iniciais foram escritas como contrato técnico, não como texto final de produto | Corrigir textos visíveis para português final e acentuado | includes/profile-dashboard-intelligence.php; assets/profile-dashboard-intelligence-v12-19-0.js | check-v12-19-1-dashboard-copy-contract.php | Não aparecem textos antigos como "Inteligencia", "Direccao" ou "permissoes" |
| Copy pouco natural para utilizador final | Headline e cards estavam mais técnicos do que operacionais | Reescrever headline, resumo, labels e hints para orientação de uso real | includes/profile-dashboard-intelligence.php | smoke-v12-19-1-dashboard-copy.php | Contexto devolve "Primeiro passo recomendado", "Atenção antes de agir" e "Regra de segurança" |
| Risco de regressão por hotfix visual | Mexer no dashboard core poderia afectar indicadores | Alterar apenas include e asset da camada read-only | includes/profile-dashboard-intelligence.php; assets/profile-dashboard-intelligence-v12-19-0.js/css | check-v12-19-1-no-sensitive-regression.php | Hashes de financeiro, académico, shell, Portaria, Alunos e dashboard core preservados |
