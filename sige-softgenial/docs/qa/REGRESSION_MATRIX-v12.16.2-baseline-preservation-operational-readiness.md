# Regression Matrix - v12.16.2

| Area | Perfil | O que pode quebrar | Teste obrigatorio | Criterio de aceite | Rollback |
|---|---|---|---|---|---|
| Financeiro | Financeiro | Pagamentos, recibos, saldos, dividas | Abrir pagamentos, extractos e devedores | Sem erro e sem alteracao de formula | v12.16.1 |
| Academico | Professor, Director | Notas, pautas, boletins | Abrir Minhas Turmas, notas e pautas | Sem loop e sem erro fatal | v12.16.1 |
| Permissoes | Administrador | Perfil ver ou alterar area indevida | Teste por perfil | Menu filtrado correctamente | v12.16.1 |
| Mobile header | Todos mobile | Header apertado ou menu inacessivel | 360, 390, 430 px | Sem overflow critico | v12.16.1 |
| Portaria | Guarda | Validacao lenta ou confusa | Abrir Portaria mobile | Nova leitura e resultado claros | v12.16.1 |
| Alunos | Secretaria | Pagina lenta ou nao abre | Abrir alunos e pesquisa | Carregamento aceitavel | v12.16.1 |
| Portal | Encarregado, Aluno | Assets pesados ou erro | Abrir portal | Sem fatal e sem peso excessivo visivel | v12.16.1 |
| Shell | Todos | Rota errada ou loop | Abrir views criticas | View correcta carregada | v12.16.1 |
| Manifesto | Tecnico | Versao divergente | Release gate | 3 fontes sincronizadas | Corrigir manifesto |
| ZIP | Tecnico | Pacote corrompido | unzip -t | Sem erros | Regerar ZIP |
