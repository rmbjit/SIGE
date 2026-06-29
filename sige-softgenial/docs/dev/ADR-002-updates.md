# ADR-002 - Canal único de actualização

**Estado:** decidido e implementado (v12.11.9.90)

## Contexto
Desde a Fase 1B coexistem dois caminhos de actualização: o update-checker
clássico (servidor de updates próprio, prioridade default) e o do SigeHub
(licenciamento central, prioridade 99). Ambos enganchavam em
pre_set_site_transient_update_plugins, com o do Hub a vencer por correr
depois. Funcionava, mas era ambíguo: dois anúncios possíveis, dois pontos
de falha, e nenhum documento a dizer qual mandava.

## Decisão
O **Hub é o canal primário**. O checker clássico passa a ser fallback
explícito: só arranca quando sige_license_key está vazia ou o ficheiro do
Hub não existe. Implementado no rodapé de includes/update-checker.php.

## Consequências
- Escolas licenciadas (todas as actuais): updates só via SigeHub.
- Instalação sem Hub (demo isolada, desenvolvimento): clássico funciona
  como sempre, zero regressão.
- O diagnóstico simplifica: "que canal está activo?" tem uma resposta só.

## Validação pendente
Um ciclo de update real via Hub numa escola (publicar versão no SigeHub,
confirmar o aviso no wp-admin do tenant e o upgrade limpo).
