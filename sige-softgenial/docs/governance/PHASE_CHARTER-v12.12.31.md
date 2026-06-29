# PHASE_CHARTER - SIGE SoftGenial v12.12.31

Fase 9 (Seguranca e governanca de acessos) - Incremento 4: Niveis de perfil editaveis.

## Objectivo

Tornar a hierarquia de niveis (introduzida no Incr 2 como mapa em codigo) editavel e persistida na base de dados, sem alteracao de esquema, com a edicao reservada ao dono. O mapa em codigo continua a ser o valor por omissao; a base de dados guarda apenas os desvios.

## Decisao de seguranca central

Editar a hierarquia e uma accao de dono, nao de gestor. Se um gestor nao-administrador pudesse editar niveis, poderia escalar (subir o seu proprio nivel, baixar o dos outros para passar a geri-los). Por isso o painel de niveis e o handler de gravacao ficam reservados ao administrador WordPress real, e nao a quem apenas gere permissoes. O gestor opera dentro da hierarquia; o dono define-a.

## Incluido

- Camada de persistencia: os niveis passam a ler-se de uma opcao (desvios por perfil) sobreposta ao mapa base em codigo. sige_permissions_role_niveis funde o mapa base com os desvios, por isso tudo o que ja usa o nivel (o avaliador, a filtragem do selector) passa a respeitar os niveis editados automaticamente. Perfis personalizados tambem ficam editaveis (por omissao 0).
- Interface: um painel Hierarquia de niveis no ecra de Perfis e Permissoes, visivel e editavel apenas a administradores WordPress reais, que lista cada perfil activo com o seu nivel efectivo num campo numerico, gravado por um botao. Validacao de 0 a 100, inteiros.
- Seguranca: nonce e sanitizacao; o painel e a gravacao sao reservados ao administrador WordPress real. Reutiliza classes e tokens ja existentes, sem CSS nova nem estilo inline.
- Gate e smoke dedicados.

## Excluido

- Coluna dedicada de nivel na tabela de perfis. Este incremento usa uma opcao, sem alteracao de esquema; uma coluna fica como refinamento futuro.
- Variante puramente aditiva por filtro user_has_cap. Continua deferida para quando houver ambiente de teste vivo.
- Sem alteracao a regras de calculo.

## Riscos

- Escalada pela edicao de niveis: mitigado por a edicao ser exclusiva do administrador WordPress real.
- Niveis invalidos: validacao de 0 a 100, inteiros; valores fora do intervalo recusados (limitados).
- Regressao: a opcao e aditiva. Sem desvio guardado, vale o mapa base, pelo que o comportamento do Incr 2 mantem-se intacto.

## Criterios de aceitacao

- Um administrador WordPress real ve e usa o painel de niveis; um gestor nao-administrador nao ve o painel nem consegue gravar, mesmo com pedido forjado.
- Editar um nivel persiste e e imediatamente respeitado pelo avaliador e pela filtragem do selector.
- Sem desvio guardado, o nivel e o do mapa base (Incr 2).
- Sem migracao de esquema, calculo byte-identico, baselines de design sem regressao, zero estilo inline novo, zero travessoes; gate e smoke verdes; corredor sobe de 74 para 76; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
