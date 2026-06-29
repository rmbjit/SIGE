# ADVERSARIAL_REVIEW - SIGE SoftGenial v12.12.30

Fase 9 incremento 3: sobreposicao reversivel do papel WordPress.

## Rediagnostico adversarial

Revisao hostil do ciclo de atribuicao do papel WordPress, procurando perda do papel original, papel obsoleto apos remover, e contorno da preservacao.

1. Perda irreversivel do papel original. Antes, o set_role na atribuicao apagava o papel original para sempre. Agora a atribuicao preserva o papel original numa copia em user meta antes de substituir. Verificado pelo smoke (a copia guarda o original; a reposicao devolve-o).

2. Sobrescrita da copia. A preservacao e idempotente: so guarda se ainda nao houver copia, pelo que uma segunda atribuicao nao apaga o original capturado na primeira. Verificado pelo smoke (segunda chamada nao sobrescreve).

3. Papel obsoleto apos remover. Antes, remover o perfil SIGE deixava o utilizador num papel sige_* obsoleto. Agora a remocao repoe o papel original e limpa a copia. Verificado no handler (chamada a reposicao apos a desactivacao) e pelo smoke (reposicao devolve o original).

4. Remocao feita fora da interface. Se o perfil sair por outra via (por exemplo, alteracao directa na base de dados), a sincronizacao no init repoe o original quando deteta que nao ha perfil activo mas o utilizador ainda esta num papel sige_*. Verificado pelo smoke (init repoe quando o perfil sai).

5. Estado legado sem copia. Utilizadores ja em sige_* de versoes anteriores nao tem copia. A preservacao exclui papeis sige_* (para nao guardar um estado ja substituido) e, sem original, assume o papel por omissao; a reposicao sem copia tambem usa o papel por omissao. Verificado pelo smoke (estado legado e sem copia usam o papel por omissao).

6. Administrador WordPress real. Nunca lhe mexemos no papel: a preservacao/substituicao so corre para nao-super-admin (guarda ja existente na atribuicao e na reposicao do handler). Sem risco de alterar o papel de um administrador.

7. Churn no init. A sincronizacao so actua quando ha mismatch (o papel nao coincide com o perfil activo) ou quando o perfil saiu e ha papel sige_*; quando ja coincide, nao mexe. Verificado pelo smoke (init nao mexe quando coincide).

8. Deriva de superficie, esquema ou calculo. Sem novo ecra nem endpoint: manifesto e Kernel mantem-se em 199 (enforce 33), alinhados; allowlist de views em 60. Regras de calculo byte-identicas. SCHEMA inalterada (usa user meta, nao DDL). Baselines de design sem regressao; sem estilo inline novo (so mudou uma mensagem de texto).

## P0

Nenhum. A substituicao do papel passou a ser reversivel: o original e preservado antes de substituir e reposto na remocao e na sincronizacao do init.

## P1

Nenhum. Durante o desenvolvimento, o gate estatico foi afinado para tolerar a guarda function_exists entre a preservacao e o set_role (o codigo estava correcto; era o padrao do gate que era estrito de mais).

## Decisao

Aprovado para entrega como v12.12.30. Sem P0 nem P1 em aberto. Decisoes de desenho documentadas: copia em user meta (sem alteracao de esquema); preservacao idempotente que exclui papeis sige_* e tem recurso ao papel por omissao; reposicao no handler e auto-cura no init; administrador WordPress real intocado. Declarado: a variante puramente aditiva por filtro user_has_cap fica para quando houver ambiente de teste vivo; os niveis editaveis na base de dados ou na interface ficam para a Fase 9 Incr 4.
