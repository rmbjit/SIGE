# PHASE_CHARTER - SIGE SoftGenial v12.12.30

Fase 9 (Seguranca e governanca de acessos) - Incremento 3: Sobreposicao reversivel do papel WordPress.

## Nota sobre o ambito

Estava declarado como deixar de trocar o papel WordPress com set_role. A versao verdadeiramente aditiva (nunca tocar no papel e conceder as capacidades em tempo de execucao por um filtro user_has_cap) tem um raio de impacto enorme (todas as verificacoes de capacidade e todos os menus legados) e exige um WordPress vivo para validar com seguranca. Por isso este incremento entrega a forma profissional e segura que fecha a mesma raiz: tornar a troca reversivel e nao destrutiva. A variante puramente aditiva fica para quando houver ambiente de teste.

## Objectivo

Converter a substituicao destrutiva do papel WordPress numa sobreposicao reversivel: o papel original e preservado na primeira atribuicao e reposto na remocao. Fecha a raiz do antigo vector de bloqueio (perda irreversivel do papel e papel obsoleto depois de remover), mantendo os menus legados a funcionar como hoje enquanto o perfil esta activo.

## Incluido

- Preservar o papel original: antes do primeiro set_role, guardar os papeis WordPress actuais numa copia em user meta. Idempotente (so guarda uma vez, captura o verdadeiro original). Papeis sige_* sao excluidos da copia; sem original, a copia assume o papel por omissao do WordPress.
- Repor na remocao: ao remover o perfil SIGE, repor os papeis originais a partir da copia e limpar a copia. Sem copia, repor o papel por omissao. A mensagem passa a dizer que o papel original foi reposto.
- Endurecer a sincronizacao no init: continua a espelhar o papel no perfil activo enquanto existir; mas se nao houver perfil activo e o utilizador ainda estiver num papel sige_*, repor a partir da copia (auto-cura para remocoes feitas fora da interface). Preservar tambem aqui, defensivamente, antes de qualquer set_role.
- Gate e smoke dedicados que provam a preservacao, a idempotencia, a reposicao e os casos de borda.

## Excluido

- Variante puramente aditiva por filtro user_has_cap (nunca tocar no papel). Fica para quando houver ambiente de teste vivo.
- Niveis editaveis na base de dados ou na interface. Fase 9 Incr 4.
- Sem alteracao de esquema (usa user meta, nao DDL) e sem alteracao a regras de calculo.

## Riscos

- Estado legado sem copia (utilizadores ja em sige_* de versoes anteriores): mitigado pela reposicao assumir o papel por omissao e pela auto-cura no init poder semear a copia.
- Reposicao para o papel errado: a copia captura o verdadeiro original uma unica vez e exclui papeis sige_*, evitando guardar um estado ja substituido.

## Criterios de aceitacao

- Atribuir um perfil SIGE a um nao-administrador preserva o papel WordPress original numa copia (uma vez).
- Remover o perfil SIGE repoe o papel original e limpa a copia; sem copia, repoe o papel por omissao.
- Um utilizador que fique sem perfil activo nao permanece num papel sige_* obsoleto.
- O administrador WordPress real continua intocado.
- Sem migracao de esquema, calculo byte-identico, baselines de design sem regressao, zero estilo inline novo, zero travessoes; gate e smoke verdes; corredor sobe de 72 para 74; release gate verde a partir de pasta limpa; rediagnostico adversarial Zero P0/P1.
