# Matriz de Rastreabilidade - v12.19.0

| Problema | Causa | Solucao | Ficheiro/modulo afectado | Teste obrigatorio | Criterio de aceitacao |
|---|---|---|---|---|---|
| Dashboard ainda muito geral para perfis diferentes | O mesmo painel pode nao orientar a primeira accao de cada perfil | Bloco read-only com prioridade, risco e regra por perfil | includes/profile-dashboard-intelligence.php | smoke-v12-19-0-dashboard-intelligence.php | Contexto devolve perfil, cards, accoes e grupos |
| Risco de atalhos indevidos | Atalhos manuais poderiam ignorar permissao | Reutilizar mapa institucional ja filtrado pela guarda | includes/profile-dashboard-intelligence.php | check-v12-19-0-dashboard-intelligence-contract.php | Nenhum atalho nasce sem href/label filtrado |
| Risco de regressao no dashboard core | Alterar dashboard-view.php poderia quebrar indicadores existentes | Injecao por asset dedicado sem alterar dashboard core | assets/profile-dashboard-intelligence-v12-19-0.js | check-v12-19-0-no-sensitive-regression.php | Hash de dashboard-view.php preservado |
| Risco de peso excessivo | Dashboard ja possui indicadores e cards | CSS/JS pequenos, sem fetch e sem consultas novas no browser | assets/profile-dashboard-intelligence-v12-19-0.css/js | check-v12-19-0-dashboard-intelligence-contract.php | JS sem fetch, AJAX, cookies ou escrita remota |
| Risco de confundir mobile | Cards podem apertar no telemovel | Layout responsivo em coluna unica | assets/profile-dashboard-intelligence-v12-19-0.css | Validacao mobile em staging | Sem overflow e leitura clara |
