# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.32

Fase 9 incremento 5: sobreposicao puramente aditiva por capacidades.

## Rediagnostico adversarial

Revisao hostil da eliminacao da substituicao de papel, procurando perda de acesso, dependencia escondida do papel sige_*, escalada, recursao, e regressao do estado legado.

1. Perda de acesso por contexto de escola. O risco era o filtro conceder capacidades so com contexto de escola, deixando o utilizador sem capacidades onde antes (com set_role) as tinha em todo o lado. Resolvido: caps_for_user prefere o perfil da escola actual mas, sem contexto, usa o perfil activo mais recente em qualquer escola, replicando fielmente o antigo set_role. A isolacao de dados por escola (escola_id) e separada e nao depende disto. Verificado pelo smoke (o filtro concede as capacidades do papel mapeado).

2. Dependencia escondida do papel sige_* no array de papeis. O risco real do filtro: codigo que verifica o slug do papel (in_array sige_* em ->roles) em vez da capacidade partiria, porque o papel deixa de ser substituido. Mitigado de duas formas: (a) as verificacoes por slug de staff encontradas foram convertidas para verificacao por capacidade (user_can), que passa pelo filtro: a proteccao do Admin TI (ajax-handlers, sensivel) e o rotulo do cracha (equipe-view, cosmetico); (b) um gate de invariante falha se existir qualquer verificacao por slug de papel sige_* de staff no codigo. Esta e a garantia que substitui o WordPress vivo. Verificado: o gate passa com zero ocorrencias.

3. Verificacoes por capacidade. A esmagadora maioria do codigo ja usa current_user_can(sige_*), que funciona porque cada papel sige_* concede uma capacidade com o seu proprio nome; o filtro concede o conjunto completo do papel mapeado, incluindo essa capacidade e as em cascata. Verificado pelo smoke (caps_for_user inclui a capacidade do proprio nome e as em cascata).

4. Escalada. O filtro concede exactamente as capacidades do papel sige_* mapeado, que sao apenas capacidades sige_* mais read/upload_files; nunca manage_options nem administrator. Nao ha caminho de escalada por aqui. Administradores reais sao ignorados pelo filtro (ja tem tudo).

5. Recursao. O filtro corre em cada verificacao de capacidade; chama helpers que poderiam, por sua vez, verificar capacidades. Resolvido por uma guarda anti-recursao por chamada (static $in com try/finally): uma reentrada devolve o conjunto sem alteracao, e a guarda e reposta no fim para a chamada seguinte funcionar. Verificado pelo smoke (reentrada nao quebra; chamada seguinte volta a conceder).

6. Regressao do estado legado. Utilizadores ainda num papel sige_* (de versoes que substituiam o papel) recebem capacidades por ambos os caminhos (papel guardado e filtro), uniao identica, sem perda nem excesso; a sincronizacao no init repoe o papel original na sessao seguinte (limpeza preguicosa). A reposicao foi tornada segura para o fluxo aditivo: sem copia, so repoe o papel por omissao quando ha papel sige_* de staff, nunca mexendo num utilizador novo ja num papel real. Verificado pelo smoke do incremento 3 (reposicao a partir da copia; limpeza de papel sige_* de staff legado; sem tocar num papel real).

7. Fluxo de portal. Os papeis sige_aluno e sige_encarregado tem fluxo proprio (contas de aluno/encarregado), que mantem o seu set_role; ficam fora do ambito de staff, as suas verificacoes por slug mantem-se validas e o gate de invariante nao as sinaliza (lista de staff exclui portal). Verificado: as verificacoes de portal permanecem intactas.

8. Deriva de superficie, esquema ou calculo. Sem nova superficie (manifesto e Kernel em 199, enforce 33, alinhados; views 60). SCHEMA inalterada. Calculo byte-identico. As conversoes usam user_can, funcao distinta de current_user_can, por isso o baseline de autorizacao nao sobe.

## P0

Nenhum. O modulo deixa de tocar no papel WordPress; as capacidades vem de um filtro que concede exactamente o papel mapeado; nenhuma dependencia por slug de staff subsiste (gate a provar); administradores reais e fluxo de portal intocados.

## P1

Nenhum. Notas de desenho: a reposicao sem copia foi limitada ao caso de papel sige_* de staff para nao resetar um utilizador novo ja num papel real; o gate do incremento 3 foi actualizado para a realidade aditiva (a reversibilidade da troca deixou de fazer sentido porque a troca foi eliminada), passando a verificar a seguranca da reposicao em vez da preservacao antes do set_role; o smoke do incremento 3 actualizou o cenario do init (limpa sempre papel sige_* de staff legado).

## Decisao

Aprovado para entrega como v12.12.32. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: capacidades por filtro user_has_cap (perfil activo mais recente, fiel ao antigo set_role, com cache e guarda anti-recursao); init so limpa; reposicao segura para o fluxo aditivo; verificacoes por slug de staff convertidas para capacidade; invariante por gate que proibe verificacoes por slug de staff; papeis de portal fora do ambito. Conclui o conjunto previsto para a Fase 9 (blindagem, hierarquia, sobreposicao reversivel, niveis editaveis, sobreposicao aditiva). A Fase 9 fica sem nada adiado.
