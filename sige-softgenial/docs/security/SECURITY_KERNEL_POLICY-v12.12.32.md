# SECURITY KERNEL POLICY - v12.12.32

## Security Kernel
Contrato de regras do Kernel inalterado (193 regras, ids identicos a v12.12.8). O endurecimento deste incremento e na camada de sumidouro de escrita (guards fail-closed) e complementa o Kernel com defesa em profundidade.

## enforce
Regras em enforce mantidas. Para handlers de escrita, o Kernel continua a fechar acesso sem tenant em modo estrito; os guards de sumidouro reforcam essa garantia no proprio ponto de escrita.

## observe
observe so para acoes nao criticas. risk=critical com observe permanece proibido. Nenhuma acao critica em observe.

## rate limit
Sem alteracoes a limites neste incremento.

## auditoria
Todo bloqueio de escrita por falta de escola gera tenant_write_blocked, agora tambem nos sumidouros e funcoes de biblioteca (via sige_tenant_write_guard), fechando a lacuna de auditoria silenciosa da v12.12.8.

## Nota v12.12.11 (MFA de operacao)

A Fase 4 (incremento 1) acrescenta a accao admin_post:sige_mfa_confirm ao Security Kernel em modo observe (risco high, intent nonce, auditoria activa, rate limit sk_admin_post_sige_mfa_confirm 20/300). O enforcement mantem-se nas 30 operacoes da Fase 3; o endpoint de confirmacao MFA auto-protege-se por login e nonce e e observado pelo kernel. Total de regras: 194 (enforce 30, delegated 17, observe 147).

## v12.12.11 (incremento TOTP)

- Nova regra: admin_post:sige_mfa_totp_enroll, risco alto, em observe, com intent nonce (sige_mfa_totp_enroll), auditoria e rate limit. Espelha admin_post:sige_mfa_confirm.
- Total de regras: 195 (mais uma). Em enforce: 30 (inalterado). Em observe: 148.
- O endpoint trata inscricao, confirmacao e desactivacao da aplicacao autenticadora do proprio utilizador; cada utilizador so altera a sua conta.

## v12.12.12 (reposicao automatica)

- Regras de Kernel inalteradas. A reposicao reutiliza o endpoint existente admin_post:sige_mfa_confirm (ja governado, em observe) para disparar a re-execucao apos a confirmacao; nao adiciona endpoint nem regra.
- Total de regras: 195 (inalterado). Em enforce: 30. Em observe: 148.
- A re-execucao passa pelo mesmo metodo de servico, que mantem os guards de tenant e permissao; o kernel continua a observar o endpoint de confirmacao.

## v12.12.13 (painel de controlo de seguranca MFA)

- Regra nova admin_post:sige_mfa_settings_save em observe, modulo sistema, risco alto. legacy_caps apenas ['administrator'] (sem perfis SIGE); a enforcement real e o gate sige_is_real_wp_admin_user no handler.
- Total de regras: 196 (195 -> 196). Em enforce: 30 (inalterado). Em observe: 149.
- O painel so e acessivel ao super admin real; o kernel observa o endpoint de gravacao, que e auditado (mfa_settings_change e mfa_stepup_disabled).

## v12.12.14 (Secret Vault, incremento 1)

- Sem alteracao as regras do Kernel: nao ha endpoint novo. Total 196; enforce 30; observe 149.
- O cofre reforca a confidencialidade dos segredos em repouso (defesa em profundidade), complementar ao Kernel (que governa o acesso e as accoes).

## v12.12.15 (Ledger financeiro, incremento 1)

- Sem alteracao as regras do Kernel: nao ha endpoint novo (ecra so de leitura). Total 196; enforce 30; observe 149.
- O ledger complementa o Kernel: o Kernel governa o acesso e as accoes; o ledger torna o efeito das operacoes criticas imutavel e auditavel a prova de adulteracao.

## v12.12.16 (patch correctivo do Ledger)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Patch de migracao e resiliencia, sem superficie nova.

## v12.12.17 (Ledger incr 2: pagamentos e ancora)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Instrumentacao interna e ancora em ficheiro, sem superficie nova.

## v12.12.18 (Ledger incr 3: lancamentos)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Instrumentacao interna e escrita diferida no shutdown, sem superficie nova.

## v12.12.19 (Ledger incr 4: despesas, creditos, fechos)

- Sem alteracao as regras do Kernel: 196; enforce 30; observe 149. Eventos internos imediatos, sem superficie nova.

## Actualizacao v12.12.21 (Fase 7 incremento 2)

Security Kernel: nova regra em enforce para view_action:financeiro-aprovacoes:sige_fin_aprovacao_decidir (enforce passa de 30 para 31; observe 149; delegated 17; total 197). Intent por nonce, rate limit e auditoria activos. Manifesto igual a regras.

## Actualizacao v12.12.22 (release correctiva)

Esta versao e uma correccao de roteamento da Fase 7, construida sobre a v12.12.21. As views financeiro-reconciliacao (Incremento 1) e financeiro-aprovacoes (Incremento 2, regra de quatro-olhos), entregues na Fase 7 mas em falta na allowlist anti-LFI e na matriz de permissoes do admin-shell, ficavam inalcancaveis: a guarda reescrevia o pedido para o painel inicial. Foram repostas em ambas as listas, com as permissoes identicas as guardas internas de cada view. Nao ha alteracao da superficie de accao (manifesto e regras do Security Kernel mantem-se em 197), nao ha migracao de dados (SCHEMA_VERSION inalterada) e as regras de calculo financeiro nao sao tocadas.

O Security Kernel permanece inalterado: enforce 31, observe 149, delegated 17. A auditoria e o rate limit das regras criticas mantem-se. Esta release nao adiciona, remove nem altera regras; apenas repoe o roteamento de duas views ja governadas.

## Actualizacao v12.12.24 (Fase 8 Incr 1)

O Security Kernel mantem-se inalterado: 197 regras, 31 em modo enforce, as restantes em observe. O novo ecra de inventario de dados pessoais nao acrescenta endpoint governado pelo Kernel (e so leitura, sem POST), pelo que nao ha nova regra, nem alteracao de rate limit nem de auditoria. Manifesto e Kernel permanecem alinhados (197 == 197).

## Actualizacao v12.12.24 - Fase 8 incremento 2

- O Security Kernel passa a 198 regras (sobe de 197), com enforce em 32 (sobe de 31). A regra nova, admin_post:sige_privacidade_exportar, esta em modo enforce.
- A regra aplica: permissao privacidade.acesso_exportar, intencao por nonce (_wpnonce, accao sige_privacidade_exportar), rate limit (10/300s) e auditoria de cada exportacao. Manifesto e Kernel alinhados (198 == 198). observe mantem as restantes regras de lockdown progressivo.


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


## Actualizacao v12.12.30 - Fase 9 incremento 3 (sobreposicao reversivel do papel WordPress)

Continua a Fase 9. Converte a substituicao destrutiva do papel WordPress numa sobreposicao reversivel, fechando a raiz do antigo vector de bloqueio. A atribuicao preserva o papel original numa copia em user meta (idempotente, exclui papeis sige_*, com recurso ao papel por omissao quando nao ha original); a remocao repoe o original e limpa a copia; a sincronizacao no init repoe quando nao ha perfil activo mas o utilizador ainda esta num papel sige_* (auto-cura), preservando tambem antes de qualquer set_role. Os menus legados continuam a funcionar enquanto o perfil esta activo; o administrador WordPress real continua intocado. Sem novo ecra e sem nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem migracao de esquema (usa user meta).


## Actualizacao v12.12.31 - Fase 9 incremento 4 (niveis de perfil editaveis)

Continua a Fase 9. A hierarquia de niveis (Incr 2, mapa em codigo) passa a ser editavel e persistida na base de dados, sem alteracao de esquema. O mapa em codigo continua a ser o valor por omissao; uma opcao guarda apenas os desvios, e role_niveis funde a base com os desvios (o avaliador e a filtragem do selector respeitam os niveis editados automaticamente). A gravacao valida 0 a 100 e guarda so desvios. Editar a hierarquia e accao de dono: o painel Hierarquia de niveis e o handler save_niveis ficam reservados ao administrador WordPress real, com nonce e auditoria. save_niveis e uma accao de formulario dentro da view ja listada, nao uma nova superficie: manifesto e Kernel mantem-se em 199 (enforce 33); allowlist de views em 60. Sem alteracao de esquema (opcao em wp_options). Sem aumento de current_user_can.


## Actualizacao v12.12.32 - Fase 9 incremento 5 (sobreposicao puramente aditiva por capacidades)

Conclui a Fase 9. Elimina-se a substituicao do papel WordPress: o modulo deixa de chamar set_role (na atribuicao e na sincronizacao do init) e passa a conceder as capacidades em tempo de execucao por um filtro user_has_cap, a partir do papel sige_* mapeado ao perfil SIGE activo, sem nunca tocar no papel guardado. As capacidades sao o conjunto completo do papel mapeado (incluindo a capacidade com o nome do papel, que e o que current_user_can(sige_*) usa). Para nao haver regressao face ao antigo set_role, as capacidades sao do perfil activo mais recente (independente do contexto de escola); a isolacao de dados por escola e separada e nao muda. O filtro ignora administradores reais e utilizadores sem perfil, com cache por pedido e guarda anti-recursao. A sincronizacao no init passa a so limpar: repoe o papel original quando o utilizador esta num papel sige_* de staff legado. As poucas verificacoes por slug de papel sige_* de staff foram convertidas para verificacao por capacidade. Garantia sem WordPress vivo: um gate que falha se existir qualquer verificacao por slug de papel sige_* de staff. Papeis de portal (sige_aluno, sige_encarregado) intocados. Sem nova superficie (199/33, views 60), sem alteracao de esquema, sem aumento de current_user_can.
