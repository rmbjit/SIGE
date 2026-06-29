# Revisao adversarial - v12.12.63 - Fase 4 incr 2 - CSP Report-Only no shell admin e lote de onclick admin

Rediagnostico adversarial do incremento que activa o CSP Report-Only no shell admin e converte um grande lote de onclick das vistas admin para o despachante data-sige-act.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. O CSP do shell admin bloquear a interface. Nao bloqueia: o cabecalho e Content-Security-Policy-Report-Only, que apenas reporta as violacoes a consola do browser, sem impedir nada. Foi a escolha deliberada por o shell admin ser toda a interface e nao ser testavel em browser deste ambiente; o caminho profissional para uma superficie grande e Report-Only primeiro, observar as violacoes reais, corrigi-las e so depois passar a enforce.

2. O cabecalho fugir para outras paginas ou para chamadas ajax. Esta protegido: e enviado via admin_init apenas quando page e sige-app, antes do output, com guarda de headers_sent e de nao estar a decorrer ajax. Logo so afecta as paginas do shell admin.

3. Restringir tipos de recurso a mais e poluir a consola. So o script-src e restringido (self mais nonce por pedido); os restantes tipos de recurso ficam sem restricao no Report-Only, para a consola reportar apenas o que interessa nesta fase (handlers inline e scripts sem nonce).

4. O nonce do cabecalho nao casar com o das tags. O cabecalho usa sige_csp_nonce, a mesma fonte das tags ja convertidas via sige_csp_script_attr; ja se confirmou em versoes anteriores que o esc_attr nao altera o base64, pelo que casam.

5. Os 2 onclick do ficheiro canonico finance-core. Confirmou-se que estao em sige_desp_print_page, uma pagina standalone de impressao com DOCTYPE proprio, fora do shell admin, pelo que nao caem sob este CSP nem precisam de ser tocados. O ficheiro canonico nao foi alterado.

6. As 21 conversoes mudarem comportamento. Usaram-se contratos e wrappers ja em producao para 18 (window.print via sigeImprimirPagina; activarTab por data-sige-args; filtrarTabela com data-sige-self; resetSenha e gerirHorario por inteiro; anularLote sem argumentos; carregarHistorico com booleano; fecho de popup backdrop via sigeFecharPopupBackdrop) e dois wrappers novos para 3 (sigeAlternarDisplay para alternar a visibilidade por id, em dois toggles; sigeMarcarDownloadSemTransicao). Cada wrapper e fiel a expressao que substitui; o despachante chama a mesma logica.

7. Os onclick restantes ficarem por mapear sem rede. Os que ficam (window.open(this.href), this.form, e os gerados dentro de blocos script) passam a ser reportados pela propria consola via Report-Only, pelo que ha telemetria para os tratar antes do enforce.

8. A catraca afrouxar. Pelo contrario: o maximo de onclick desceu de 66 para 45 e a catraca passa a exigir o CSP Report-Only com script-src nonce no shell admin.

9. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9, sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. Report-Only nao bloqueia nada e nao toca em regras de calculo nem no ficheiro canonico finance-core.

## P1

Nenhum. O cabecalho esta scoped as paginas do shell admin, protegido por headers_sent e nao-ajax; o nonce casa com o das tags; as 21 conversoes usam contratos e wrappers fieis; os onclick canonicos estao fora do shell. php -l limpo nos ficheiros tocados.

## P2 e P3

Nenhum novo. O enforce do shell admin so vem depois de tratados, com a ajuda da telemetria do Report-Only, os onclick restantes (window.open(this.href), this.form, gerados por JavaScript) e, para o style-src, os estilos inline. A catraca (onclick 45, sem folga) impede regressao.

## Decisao

Aprovado. Zero P0 e zero P1. Activou-se o CSP Report-Only com script-src baseado em nonce nas paginas do shell admin (sem bloquear, so reportar), passo profissional para preparar o enforce de uma superficie que nao e testavel em browser deste ambiente, e converteram-se 21 onclick das vistas admin para o despachante data-sige-act (18 com contratos e wrappers existentes, 3 com dois wrappers novos fieis). Os 2 onclick do finance-core estao numa pagina standalone fora do shell e nao foram tocados. O comportamento e identico, os verificadores de JavaScript embebido mantem-se verdes e a catraca de onclick desceu de 66 para 45. A consola passa a reportar as violacoes que faltam tratar antes do enforce. Pronto para empacotar.
