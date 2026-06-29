# Phase Charter - v12.12.12

Fase 4 (MFA critico), incremento 3: reposicao automatica da operacao apos confirmacao.
Base: v12.12.11. Estado: entregue.

## Objectivo

Eliminar a repeticao manual da operacao critica apos a confirmacao de identidade. Hoje, quando o step-up bloqueia uma operacao, o utilizador confirma (TOTP ou email) e tem de repetir a operacao a mao. Este incremento re-executa automaticamente, uma unica vez, a operacao de servico que foi bloqueada, com tenant e permissoes re-validados.

## Incluido

- Modulo novo includes/security-mfa-replay.php: captura de descritor de uso unico (contexto + argumentos da chamada) quando uma das 6 operacoes de servico e bloqueada pelo step-up; consumo atomico (apaga antes de despachar) e re-execucao uma unica vez no handler de confirmacao partilhado.
- Abrange exactamente as 6 operacoes de SIGE_FinanceActionService: cancelLancamento, isentarLancamento, reactivarLancamento, bloquearMes, desbloquearMes, estornarPagamento (cobre a anulacao de recibo).
- Captura adicionada no ramo de bloqueio de cada uma das 6 operacoes (func_get_args). Re-execucao por call_user_func_array sobre o mesmo metodo estatico, pelo que tenant (sige_require_escola_id) e permissoes sao re-validados.
- Resultado da reposicao devolvido ao utilizador por aviso. Funciona igual com TOTP ou com email (o handler de confirmacao e o mesmo).

## Excluido

- Configuracao de pagamentos (e-Mola, M-Pesa) e caixa (reabrir, fechar): mantem a repeticao manual, por desenho. Nao capturam descritor.
- SMS, codigos de recuperacao, WebAuthn e passkeys (continuam fora de fase).

## Riscos

- Execucao dupla: mitigada por uso unico com consumo atomico (apaga o descritor antes de despachar; confirmacao concorrente nao o encontra) e pelas guardas de estado internas das proprias operacoes. Se o utilizador repetir a mao, o TTL curto impede o descritor de disparar fora de tempo.
- Despacho indevido ou adulteracao de argumentos: o descritor e capturado do lado do servidor a partir da chamada original ja validada; a reposicao nao aceita argumentos do cliente. O escola_id capturado e o da chamada original, logo o tenant e preservado.
- Multiplas operacoes pendentes: vence a ultima (sobrescreve); as anteriores repetem-se a mao. Documentado.

## Criterios de aceitacao

1. Descritor de uso unico capturado nas 6 operacoes de servico quando bloqueadas; nunca nas de config/caixa.
2. Reposicao re-executa exactamente uma vez, com tenant e permissoes re-validados (mesmo metodo).
3. Nao-execucao-dupla provada por execucao real (consumo atomico; segundo consumo nao dispara).
4. Funciona com TOTP e com email; aviso de resultado ao utilizador.
5. Desligado por defeito (opcao sige_mfa_autoreplay; kill-switch SIGE_MFA_AUTOREPLAY_OFF); repeticao manual intacta; comportamento da v12.12.11 preservado para quem nao ligar.
6. Sem novo endpoint (manifesto mantem-se em 195); regras de Kernel inalteradas (enforce 30). Sem migracao de base de dados. Sem alteracao de regras de calculo.
7. Zero em tudo: gates, lint, travessoes (codigo e documentos), versao sincronizada nas fontes.
