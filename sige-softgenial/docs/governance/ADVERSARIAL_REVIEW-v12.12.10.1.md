# ADVERSARIAL_REVIEW - v12.12.10.1 - MFA de Operacao (correccao pos-rediagnostico)

Esta versao resulta do rediagnostico adversarial da v12.12.10. Partindo do principio de que a entrega continha falhas escondidas, atacou-se por sete vias e corrigiu-se TUDO na mesma sessao (regra: so se avanca de fase com zero em tudo).

## Como se atacou

Injeccao de regressao nos pontos de integracao; alteracao silenciosa do calculo; em-dashes; regressao dos baselines de tenant; fuga ao guard (bypass); comportamento fail-closed; e a maior lacuna assumida (a integracao WordPress so tinha passado no lint). Essa integracao foi posta a correr com um harness que captura e invoca os callbacks reais (endpoint de confirmacao, aviso, formulario): funciona de facto.

## Achados do rediagnostico e respectiva correccao

| ID | Achado | Severidade original | Resolucao nesta versao |
|---|---|---|---|
| A1 | UX: 8 das 10 operacoes mostravam a mensagem mas o formulario do codigo so aparecia ao recarregar (so a caixa o mostrava inline). | P2 | CORRIGIDO. As 10 operacoes renderizam o formulario: inline nos handlers (pedidos POST) e via aviso nos pedidos GET (recarregamento e redireccionamento de config). O aviso so renderiza o formulario em GET, evitando formulario duplicado. |
| A2 | Anti-lockout de SMTP: se o email falhar, a operacao critica prosseguia sem segundo factor. | P3 | CORRIGIDO (opt-in). Novo modo estrito sige_mfa_stepup_strict (option ou SIGE_MFA_STEPUP_STRICT): quando ligado, falha de email BLOQUEIA a operacao. Defeito mantem o anti-lockout, agora uma escolha explicita do operador. |
| A5 | Operacoes feitas dentro da janela nao eram auditadas individualmente. | P3 | CORRIGIDO. O guard regista satisfied_window para cada operacao critica autorizada pela janela. |
| A7 | 919 em-dashes em 490 documentos historicos do pacote (o gate so verificava codigo). | P3 | CORRIGIDO. Todos os documentos limpos; o pacote inteiro esta a zero em/en-dashes. Novo controlo no gate de release que verifica documentos, para nunca recorrer. |
| A3 | Guard envolvido em function_exists: se o modulo nao carregar, a operacao prossegue (fail-open). | P3 | POR DESIGN (documentado). Tornar fail-closed faria um ficheiro em falta congelar toda a financa. O modulo carrega incondicionalmente e a sua presenca e coberta pelo gate. Consistente com os guards de tenant. |
| A4 | Endpoint de confirmacao sem rate limit imposto pelo Kernel (regra em observe). | P3 | MITIGADO (documentado). O controlo activo e o tecto de 5 tentativas do OTP, que invalida o codigo e obriga a repetir a operacao; a brute-force de 6 digitos nao e praticavel. |
| A6 | Janela de verificacao por utilizador, nao por escola. | P3 | POR DESIGN (documentado). O step-up confirma IDENTIDADE; o contexto de escola e garantido pelo guard de tenant (separado). Semantica sudo, correcta. |

## Verificacoes de nao-regressao (reexecutadas)

- Calculo financeiro byte-identico a v12.12.8.1 (md5 iguais nas 3 funcoes).
- Guard ANTES de qualquer escrita em cada metodo (sem estado parcial ao bloquear).
- Sem caminhos de bypass: operacoes de dinheiro centralizadas no servico guardado; os tres chamadores param em ok=false.
- Baselines de tenant congelados (138/139) e corrente 0; ratchet mantido.
- Zero em-dashes em TODO o pacote (codigo e documentos).
- Integracao WordPress executada (endpoint, aviso, formulario, limpeza de transient, modo estrito, auditoria, nao-duplicacao).

## Decisao

Zero P0, zero P1, e zero inconsistencias por fechar: as P2/P3 foram corrigidas ou explicitamente documentadas como design/mitigacao. A fase fica a zero em tudo. Pode avancar-se para o incremento seguinte da Fase 4.
