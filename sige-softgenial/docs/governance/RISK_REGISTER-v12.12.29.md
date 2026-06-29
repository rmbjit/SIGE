# RISK_REGISTER - v12.12.29 - MFA de Operacao (correccao pos-rediagnostico)

## Bloqueadores

| Severidade | Risco | Estado |
|---|---|---|
| P0 | Nenhum. | - |
| P1 | Nenhum. | - |

## Achados do rediagnostico (todos fechados nesta versao)

| ID | Achado | Resolucao |
|---|---|---|
| A1 (P2) | Formulario do codigo nao uniforme entre operacoes. | Corrigido: formulario nas 10 operacoes (inline em POST, aviso em GET, sem duplicacao). |
| A2 (P3) | Bypass de MFA sob falha de SMTP. | Corrigido: modo estrito opt-in que bloqueia; defeito anti-lockout explicito. |
| A5 (P3) | Operacoes pela janela nao auditadas. | Corrigido: auditoria satisfied_window por operacao. |
| A7 (P3) | Em-dashes em documentos historicos do pacote. | Corrigido: pacote a zero em/en-dashes; gate passa a verificar documentos. |
| A3 (P3) | Fail-open por function_exists. | Documentado como design defensivo (fail-closed congelaria a financa se faltasse o ficheiro); coberto pelo gate. |
| A4 (P3) | Rate limit do endpoint em observe. | Documentado: tecto de 5 tentativas do OTP e o controlo activo; nao praticavel forcar 6 digitos. |
| A6 (P3) | Janela por utilizador, nao por escola. | Documentado: semantica sudo correcta; o tenant e garantido por guard separado. |

## Residuais nao bloqueadores (roteiro)

| Severidade | Risco residual | Fase futura |
|---|---|---|
| P2 | Reposicao automatica da operacao apos confirmacao (hoje o utilizador repete manualmente). | Incremento seguinte da Fase 4. |
| P2 | So ha entrega por email; sem TOTP nem aplicacao autenticadora. | Incremento seguinte da Fase 4. |
| P3 | Ledger financeiro: incremento 1 entregue (operacoes criticas); pagamentos/lancamentos e ancoragem em incrementos seguintes. | Fase 6 (em curso). |

## Notas

A versao mantem-se desligada por defeito e sem alteracao de comportamento em instalacoes que nao a liguem. Nenhuma regra de calculo financeiro ou academico foi tocada.

## v12.12.11 (incremento TOTP)

- A2 (dependencia de email para autorizar a operacao): FECHADO para utilizadores inscritos em TOTP (confirmam com o codigo da aplicacao, sem email). Severidade residual P3 para quem nao inscrever (mitigado pelo modo estrito opt-in da v12.12.10.1).
- P2 (A-T6): codigo TOTP reutilizavel dentro da validade. Aceite; endurecimento futuro (contador de uso unico).
- P2 (A-T7): desactivar TOTP nao exige step-up. Aceite; endurecimento futuro (gating de definicoes de seguranca).
- P0: nenhum. P1: nenhum.

## v12.12.12 (reposicao automatica)

- Execucao dupla: MITIGADA (consumo atomico + guardas de estado das operacoes). P0 candidato fechado.
- Contorno de permissao/tenant pela reposicao: MITIGADO (re-validados dentro do metodo). P0 candidato fechado.
- P2 (A-R5): multiplas operacoes pendentes, vence a ultima. Aceite; repeticao manual para as anteriores.
- P2 (A-R6): descritor obsoleto. Mitigado por TTL curto e consumo na confirmacao.
- P0: nenhum. P1: nenhum.

## v12.12.13 (painel de controlo de seguranca MFA)

- Acesso indevido de perfil SIGE ao painel: MITIGADO (tripla camada + filtro de hardening; provado em runtime). P0 candidato fechado.
- POST directo ao endpoint por perfil SIGE: MITIGADO (gate duro no handler, wp_die 403). P0 candidato fechado.
- CSRF na gravacao: MITIGADO (nonce). P1 candidato fechado.
- Desligar step-up sem rasto: MITIGADO (evento de auditoria distinto). P1 candidato fechado.
- P3 (A-S6): supressao de display de avisos em producao. Aceite (so display, log mantido, opcional).
- P3 (A-S7): perfis abrangidos vazios com step-up ligado. Aceite (respeita intencao; UI avisa).
- P0: nenhum. P1: nenhum.

## v12.12.14 (Secret Vault, incremento 1)

- P2-003 (opcoes sensiveis fora do cofre): credenciais dos gateways em claro. FECHADO para o inventario conhecido (M-Pesa, e-Mola, webhook_token cifrados; SMTP e WhatsApp ja cifrados).
- Perder credencial na transicao: MITIGADO (passagem de texto em claro; selar nunca perde). P1 candidato fechado.
- Webhook deixar de validar: MITIGADO (revela antes do hash_equals). P1 candidato fechado.
- Escrita inesperada na leitura: MITIGADO (auto-reparacao so no admin, uma vez; nunca no webhook). P2 candidato fechado.
- P3 (A-V7): cobertura do registo; decisao de rotacao de chave adiada para incremento posterior. Aceite.
- P0: nenhum. P1: nenhum.

## v12.12.15 (Ledger financeiro, incremento 1)

- Historia financeira mutavel sem deteccao: MITIGADO para as 6 operacoes criticas (cadeia HMAC append-only; deteta modificacao e remocao/insercao no meio). P2 do roteiro parcialmente fechado.
- Truncagem da cauda: LIMITE CONHECIDO (P2), adiado para ancoragem externa (incremento posterior).
- Compromisso de ficheiros (salts): modelo declarado (fora do ambito da tamper-evidence ao nivel da base de dados).
- Concorrencia sob timeout de bloqueio: P3 aceite (no maximo um evento nao registado; sem corromper a cadeia).
- P0: nenhum. P1: nenhum.

## v12.12.16 (patch correctivo do Ledger)

- Tabela do ledger nao criada por actualizacao de ficheiros: CORRIGIDO (SCHEMA_VERSION subida; maybe_upgrade cria a tabela). Travado por gate.
- Erro cru no ecra sem tabela: CORRIGIDO (guard de existencia; mensagem clara). Travado por gate.
- P0: nenhum. P1: nenhum.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- Movimentos de dinheiro fora do ledger: MITIGADO (pagamentos instrumentados, cobrem todos os fluxos).
- Truncagem da cauda: FECHADO contra o atacante so com base de dados (ancora externa em ficheiro). P2 do incremento 1 resolvido.
- Compromisso de ficheiros (ancora mais salts): modelo declarado (fora de ambito).
- Perda da ancora: deteccao de truncagem cega ate reconstruir; cadeia interna mantem deteccao de modificacao e remocao no meio. Documentado.
- P0: nenhum. P1: nenhum.

## v12.12.18 (Ledger incr 3: lancamentos)

- Cobrancas fora do ledger: MITIGADO (criacao e alteracao de valor instrumentadas, por cobranca).
- Desempenho da geracao em massa: RESOLVIDO por desenho (escrita em lote diferida, um bloqueio e uma ancora por escola e por pedido).
- Limite da escrita diferida: HONESTO (eventos de cobranca gravados no fim do pedido; falha catastrofica antes do shutdown perde o buffer desse pedido; cobrancas tambem podem ficar incompletas e o gerador e idempotente). Documentado.
- P0: nenhum. P1: nenhum.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- Despesas, creditos e fechos fora do ledger: MITIGADO (5 eventos instrumentados). Cobertura financeira do ledger completa.
- Consumo de credito e edicao de despesa: adiados (inline, sem ponto unico). Documentado.
- P0: nenhum. P1: nenhum.

## v12.12.21 (Fase 7 incr 1: reconciliacao e divergencias)

- Divergencias de reconciliacao invisiveis: MITIGADO (relatorio so leitura: recebido por aplicar, divergencia de montante, pagamento sem gateway, com totais).
- Idempotencia e-Mola: CONFIRMADO (pre-verificacao por referencia + chave unica, igual ao M-Pesa).
- Estorno com razao e inverso, reabertura com permissao, auditoria via Ledger: criterios da Fase 7 ja cumpridos, fechados.
- Regra de quatro-olhos (dupla aprovacao): adiada para o incremento 2. Documentado.
- P0: nenhum. P1: nenhum.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

- P1 mitigado: bypass da separacao de funcoes (decisor != solicitante, nonce, permissao, MFA).
- P1 mitigado: dupla execucao (FOR UPDATE, guarda de duplo estorno, anti-duplicado de pedidos).
- P2 aceite: pedidos pendentes sem auto-expiracao (cron pertence a infraestrutura); documentado.
- P3 aceite: dupla confirmacao de identidade (requerente e aprovador) mantida como defesa em profundidade.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

Esta release correctiva fecha um risco de nivel P1 (duas views da Fase 7 inalcancaveis na interface por omissao na allowlist e na matriz). Nao ha novos riscos P0, P1, P2 nem P3 introduzidos; ver Adversarial Review v12.12.22.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

- P0: nenhum.
- P1: nenhum.
- P2: classificacao de base legal por omissao pode nao reflectir a realidade de cada escola. Mitigacao: marcada explicitamente como revisivel pela instituicao, responsavel pelo tratamento.
- P3: catalogo de PII pode ficar incompleto com a evolucao do esquema. Mitigacao: deteccao automatica de lacunas (colunas PII fora do catalogo) denuncia o que falta classificar, em vez de o esconder.

## Actualizacao v12.12.24 - Fase 8 incremento 2

- P0: nenhum.
- P1: nenhum.
- P2: exportacao de dados pessoais e operacao sensivel; mitigada por permissao de risco alto restrita a administracao/direccao, nonce, rate limit (10/300s), isolamento por escola e auditoria obrigatoria de cada exportacao.
- P3: a base legal e as finalidades exibidas sao classificacao por omissao do catalogo, a rever pela instituicao; o dossie limita-se a tabelas ligadas ao aluno (encarregado e agregado constam por estarem na ficha do aluno; o titular funcionario fica para incremento posterior).


## Actualizacao v12.12.25 - Fase 8 incremento 3 (apagamento por anonimizacao)

Este incremento acrescenta a primeira operacao destrutiva do produto: o apagamento por anonimizacao (direito ao apagamento). Foi adicionado um endpoint admin_post governado em modo enforce e risco critico (admin_post:sige_privacidade_apagar), com confirmacao em dois passos por numero de processo, nonce, rate limit (5/300s), isolamento por escola e auditoria antes e depois. A superficie de accao passou de 198 para 199 e o enforce de 32 para 33. Nova permissao critica privacidade.apagamento_executar, semeada so a administracao e direccao e sempre auditada. Sem eliminacao fisica de linhas e sem migracao de esquema (SCHEMA_VERSION inalterada). Manifesto e Kernel mantem-se alinhados (199 == 199).


## Actualizacao v12.12.26 - Fase 8 incremento 3.2 (completar o catalogo de PII)

Este incremento classifica as 12 colunas com aspeto de dado pessoal que estavam fora do catalogo (lacunas detectadas pelo inventario da Incr 1), levando o catalogo de 76 para 88 campos e as lacunas de 12 para 0. As nove colunas identificaveis de sige_alunos (incluindo o documento de identidade digitalizado, a fotografia, os contactos de emergencia e os dados da pessoa autorizada a buscar o aluno) passam a ser tratadas pelo dossie de acesso/portabilidade e pelo motor de anonimizacao, fechando o buraco em que sobreviviam a um apagamento. As duas datas operacionais de cobranca sao classificadas mas preservadas na anonimizacao (lista de preservacao); a coluna de notas de funcionario e classificada mas fica fora do ambito do apagamento do aluno. Sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (SCHEMA_VERSION inalterada).


## Actualizacao v12.12.27 - Fase 8 incremento 4 (retencao e expurgo)

Este incremento acrescenta um ecra de governanca de dados, so de leitura, que mostra o calendario de retencao declarado e quantos registos ja excederam o prazo, de forma agregada por escola. Decisao de seguranca central: neste sistema nao ha expurgo por eliminacao em massa, porque as presencas sao derivadas ao vivo do registo de acessos e os registos financeiros, academicos e de auditoria tem dever de retencao; o expurgo de um titular faz-se pela anonimizacao ja existente (Apagamento), que preserva a integridade. Nova permissao privacidade.retencao_ver (risco medio, so leitura), semeada a administracao e direccao. Sem nova superficie de accao: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (SCHEMA_VERSION inalterada).


## Actualizacao v12.12.28 - Fase 9 incremento 1 (blindagem do modulo de permissoes)

Abre a Fase 9 (seguranca e governanca de acessos). O modulo de Perfis e Permissoes passa a ser seguro por desenho atraves de um avaliador unico de operacao que aplica quatro guardas: alvo protegido (administradores WordPress reais nao sao geriveis pelo modulo, por qualquer actor), anti-escalada (um gestor que nao seja administrador WP real nao pode atribuir um perfil que confira a gestao de permissoes), auto-proteccao (um gestor nao se despromove nem se remove a si proprio) e ultimo gestor (nao se deixa a escola sem nenhum gestor). O handler chama o avaliador e bloqueia nos dois ramos, com auditoria, mesmo com nonce valido. As contas protegidas aparecem na lista so de leitura, com selo Protegido. A porta do menu fica coerente (link mostrado a quem tem usuarios.gerir_permissoes, com Saude do Sistema e Centro de Configuracao restritos ao core admin); migracao idempotente garante a concessao ao admin_ti na base de dados real. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33). Sem migracao de esquema (reconciliacao em role_permissions).


## Actualizacao v12.12.29 - Fase 9 incremento 2 (hierarquia de perfis por nivel)

Continua a Fase 9. Cada perfil passa a ter um nivel (mapa em codigo, sem alteracao de esquema), com os tres gestores no topo (direccao_geral e admin_escola a 90, admin_ti a 80) e os restantes por responsabilidade; perfis desconhecidos ou personalizados ficam no nivel 0, o mais restritivo. O avaliador unico ganha a guarda nivel_insuficiente, por cima da regra anti-escalada do Incr 1: um actor nao protegido so atribui perfis estritamente abaixo do seu nivel e so mexe em utilizadores estritamente abaixo do seu nivel; a regra do Incr 1 mantem precedencia (nenhum nao-administrador atribui um perfil que confira gestao). A interface fica coerente: o selector lista so os perfis atribuiveis e as linhas de nivel igual ou superior ficam so de leitura; contas protegidas continuam so de leitura com selo e administradores WordPress reais mantem autoridade plena. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem migracao de esquema.
