# PHASE_CHARTER - SIGE SoftGenial v12.12.32

Fase 9 (Seguranca e governanca de acessos) - Incremento 5: Sobreposicao puramente aditiva por capacidades.

## Objectivo

Eliminar a substituicao do papel WordPress. As capacidades passam a ser concedidas em tempo de execucao por um filtro user_has_cap, a partir do papel sige_* mapeado ao perfil SIGE activo. O papel WordPress original nunca e alterado pelo modulo. A maquina de substituicao de papel e aposentada.

## Porque agora e porque definitivo

Esta era a divida real adiada nos incrementos anteriores, com a justificacao de nao haver WordPress vivo para validar. O incremento 3 tornou a troca de papel reversivel, mas continuava a troca-lo. O definitivo e o modulo nunca tocar no papel. A recon provou que e seguro fazer sem ambiente vivo, porque a garantia nao vem de testar menus a mao: vem de um invariante mecanico, um gate que falha se algum codigo depender de um papel sige_* de staff estar guardado. Se nada depende disso, o filtro e suficiente por construcao.

## Incluido

- Filtro user_has_cap (sige_permissions_grant_caps_filter): para um utilizador com perfil SIGE de staff activo e que nao seja administrador WordPress real, funde as capacidades do papel sige_* mapeado (conjunto completo: capacidade com o nome do papel e as em cascata) no conjunto de capacidades, sem tocar no papel guardado. Cache por pedido e guarda anti-recursao. Administradores reais e utilizadores sem perfil activo nao sao afectados.
- Fidelidade ao antigo set_role sem regressao: as capacidades sao do perfil activo mais recente em qualquer escola (sige_permissions_get_latest_active_role), por isso ficam disponiveis em todo o lado, como antes; a isolacao de dados por escola (escola_id) e separada e nao muda.
- Parar o set_role: na atribuicao (sync_user_role) e na sincronizacao do init. A sincronizacao passa a so limpar: repoe o papel original quando o utilizador esta num papel sige_* de staff legado (migracao preguicosa).
- Reposicao segura para o fluxo aditivo: sem copia, so repoe o papel por omissao quando ha papel sige_* de staff; nunca mexe num utilizador ja num papel real.
- Conversao das verificacoes por slug de staff para verificacao por capacidade (proteccao do Admin TI em ajax-handlers; rotulo do cracha em equipe-view).
- Gate de invariante que proibe verificacoes por slug de papel sige_* de staff; mais filtro testado em isolamento e smoke.

## Excluido

- Papeis de portal (sige_aluno, sige_encarregado): tem fluxo proprio (contas de aluno/encarregado, que mantem o seu set_role), fora do ambito de staff. Nao sao divida desta fase.
- Coluna dedicada de nivel na tabela de perfis (Incr 4): a solucao por opcao e completa; uma coluna seria churn de esquema sem ganho. Refinamento futuro, se necessario para consulta.
- Sem alteracao a regras de calculo.

## Riscos

- Uma verificacao por slug que escape: mitigado pelo gate de invariante (falha se existir qualquer uma) e por uma busca exaustiva antes de converter. Esta e a salvaguarda que substitui o WordPress vivo.
- Capacidade em falta no filtro: o filtro le o conjunto completo do papel mapeado (get_role); testado em isolamento.
- Estado legado: utilizadores ainda em sige_* recebem capacidades por ambos (papel e filtro, uniao identica) ate a limpeza preguicosa os repor; sem perda nem excesso.
- Reposicao a resetar um utilizador novo: mitigado por a reposicao sem copia so actuar quando ha papel sige_* de staff.

## Criterios de aceitacao

- A atribuicao de um perfil de staff nao altera o papel WordPress; as capacidades vem do filtro; current_user_can(sige_*) e user_can(id, sige_*) devolvem o mesmo de antes.
- Zero verificacoes por slug de papel sige_* de staff no codigo (gate verde a provar).
- A proteccao do Admin TI (so administrador real edita ou remove) mantem-se, agora por capacidade.
- Um utilizador num papel sige_* de staff legado e reposto ao papel original na sessao seguinte, sem perder acesso.
- O administrador WordPress real intocado; os papeis de portal intocados.
- Sem migracao de esquema, calculo byte-identico, baselines de design sem regressao, sem aumento de current_user_can, zero estilo inline novo, zero travessoes; gate e smoke verdes; corredor sobe de 76 para 78; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1. Conclui o conjunto previsto para a Fase 9.
