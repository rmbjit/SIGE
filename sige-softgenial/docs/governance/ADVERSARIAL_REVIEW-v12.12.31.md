# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.31

Fase 9 incremento 4: niveis de perfil editaveis.

## Rediagnostico adversarial

Revisao hostil da edicao de niveis, procurando escalada pela edicao, contorno do guard de dono, valores invalidos, e regressao da hierarquia.

1. Escalada pela edicao de niveis. Se um gestor pudesse editar niveis, subiria o seu proprio nivel ou baixaria o dos outros para passar a geri-los. Por isso a edicao e exclusiva do administrador WordPress real: o handler save_niveis verifica sige_permissions_principal_protegido e, se nao for o dono, recusa e audita (ui_niveis_blocked); o painel so e renderizado quando o actor e administrador WordPress real. Um gestor nao-administrador nao ve o painel nem consegue gravar, mesmo com um POST forjado.

2. Contorno pela interface. A decisao de seguranca esta no handler, nao apenas na renderizacao do painel: mesmo que alguem forje o POST save_niveis sem ver o painel, o handler verifica o dono antes de gravar. Nonce partilhado da interface tambem aplicado.

3. Valores invalidos. A gravacao valida cada nivel como inteiro de 0 a 100 (limita fora do intervalo) e ignora entradas nao numericas ou de slug vazio. Verificado pelo smoke (clamp e entradas invalidas ignoradas).

4. Desvios versus base. A opcao guarda so desvios face ao mapa base; um valor igual ao base remove o desvio, evitando uma opcao inchada e mantendo a base como fonte por omissao. Sem desvio guardado, o comportamento do Incr 2 mantem-se intacto. Verificado pelo smoke (guarda so desvios; repor ao base remove o desvio; sem desvio vale a base).

5. Coerencia imediata. role_niveis funde a base com os desvios em cada leitura, por isso o avaliador e a filtragem do selector respeitam os niveis editados imediatamente apos a gravacao (a opcao e actualizada em memoria pelo update_option). Verificado pelo smoke (o avaliador respeita os niveis editados).

6. Divida de autorizacao. A verificacao de dono usa a funcao canonica (sige_permissions_principal_protegido), sem acrescentar chamadas avulsas de current_user_can; o baseline de autorizacao mantem-se congelado.

7. Deriva de superficie, esquema ou calculo. save_niveis e uma accao de formulario dentro da view ja listada (como assign_user_role), nao uma nova superficie rastreada: manifesto e Kernel mantem-se em 199 (enforce 33), alinhados; allowlist de views em 60. Regras de calculo byte-identicas. SCHEMA inalterada (opcao em wp_options, nao DDL). Baselines de design sem regressao; o painel reutiliza classes e tokens existentes, sem estilo inline novo.

## P0

Nenhum. A edicao de niveis e exclusiva do administrador WordPress real, validada e auditada, e a gravacao guarda so desvios sobre um mapa base.

## P1

Nenhum. Durante o desenvolvimento, o baseline de autorizacao apanhou uma chamada de recurso a current_user_can no guard de dono (que nunca era usada na pratica, pois a funcao canonica existe sempre); foi removida, deixando so a funcao canonica e mantendo o baseline congelado.

## Decisao

Aprovado para entrega como v12.12.31. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: niveis numa opcao (desvios sobre o mapa base, sem alteracao de esquema); validacao 0 a 100; edicao exclusiva do administrador WordPress real, no handler e no painel. Declarado: a variante puramente aditiva por filtro user_has_cap continua deferida para ambiente de teste vivo; uma coluna dedicada de nivel na tabela de perfis fica como refinamento futuro. Conclui o conjunto previsto para a Fase 9 (blindagem, hierarquia, sobreposicao reversivel, niveis editaveis).
