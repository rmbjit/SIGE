# Revisao adversarial - v12.12.59 - Fase 4 incr 2 - CSP enforce com nonce nas paginas autonomas

Rediagnostico adversarial do incremento que impoe o primeiro CSP enforce com nonce, nas paginas autonomas.

## Rediagnostico adversarial

Tentou-se quebrar a entrega pelos seguintes vectores:

1. O script-src estrito bloquear algum recurso e partir a pagina. Verificou-se recurso a recurso: as cinco paginas (motor de documentos, modelos PDF de pauta e boletim, boletim do jardim, pagina da camara) tem zero handlers inline e zero URLs javascript; todos os scripts inline levam nonce desde a v12.12.55 a v12.12.57; os unicos scripts externos sao a QRious e a html5-qrcode, servidas de assets/vendor do plugin, ou seja da propria origem, cobertas por self. Logo script-src self mais nonce nao bloqueia nada.

2. O nonce do cabecalho nao casar com o nonce das tags, bloqueando os proprios scripts. Confirmou-se por teste que o nonce do cabecalho e o nonce das tags provem ambos de sige_csp_nonce e sao byte-identicos. O esc_attr aplicado ao atributo nao altera o base64, mesmo com os caracteres mais barra igual, pelo que o valor no cabecalho e no atributo coincidem.

3. O cabecalho nao poder ser enviado por o output ja ter comecado. Nos modelos PDF, o cabecalho vai no handler (pauta-pdf-handler e boletim-pdf-handler) a seguir ao Content-Type, antes do output do template. No boletim do jardim, o cabecalho vai no topo da vista, protegido por headers_sent, antes do DOCTYPE. Na camara, a funcao de cabecalhos ja guardava headers_sent. Em nenhum caso ha aviso de cabecalhos ja enviados.

4. O CSP fugir para outras paginas e quebra-las. A funcao da camara so envia o CSP no pedido da camara (guarda is_request); os handlers PDF so correm na impressao; o boletim do jardim e o motor de documentos so emitem os seus cabecalhos nas suas proprias paginas. Nenhum destes cabecalhos e enviado noutras paginas.

5. A validacao de crachas da camara ser bloqueada pelo connect-src. A validacao usa fetch para admin-ajax na propria origem (admin_url de admin-ajax.php), coberta por connect-src self. A camara usa BarcodeDetector nativo e getUserMedia, governado pela Permissions-Policy e nao pelo CSP, pelo que o acesso a camara nao e afectado.

6. Os estilos inline serem bloqueados. Mantem-se style-src self unsafe-inline em todas as paginas, pelo que os estilos inline continuam a funcionar; so o script-src foi apertado.

7. O popup de impressao do jardim ser quebrado. O popup, construido por document.write, contem HTML e estilos inline mas nao scripts inline (a impressao e disparada do lado da pagina principal via a propriedade onload do popup, nao por script inline no popup). Mesmo que herde o CSP da pagina principal, os estilos inline sao permitidos e nao ha scripts a bloquear.

8. A catraca afrouxar. Pelo contrario: acrescentou-se uma asserccao que exige script-src com nonce nas cinco paginas autonomas e proibe a presenca de unsafe-inline no script-src, travando a regressao.

9. Regressao de inventario. Verificou-se: superficie 199 e enforce 33, vistas 60, opcoes 132, dependencias externas 9 (tudo self), sem alteracao de esquema. Versao sincronizada nas cinco fontes.

## P0

Nenhum. Nao toca em regras de calculo nem no ficheiro canonico, e o script-src self mais nonce nao bloqueia nada (verificado recurso a recurso).

## P1

Nenhum. O nonce do cabecalho casa exactamente com o das tags; cada CSP esta isolado a sua pagina; a validacao da camara vai para a propria origem; os estilos inline continuam permitidos. php -l limpo nos ficheiros tocados.

## P2 e P3

Nenhum novo. O CSP enforce das vistas admin (com onclick e estilos inline) e um esforco grande a parte. O portal publico, o finance-core, a pagina de login (pre-auth) e o popup document.write ficam para abordagem propria. A catraca exige script-src com nonce nas paginas autonomas e proibe unsafe-inline no script-src.

## Decisao

Aprovado. Zero P0 e zero P1. As paginas autonomas passam a impor um script-src baseado em nonce: o motor de documentos troca unsafe-inline por self mais nonce nos seus tres cabecalhos, e os modelos PDF, o boletim do jardim e a camara passam a enviar cabecalho CSP com nonce. Verificou-se recurso a recurso que nada e bloqueado, que o nonce do cabecalho casa com o das tags e que cada CSP esta isolado a sua pagina. A catraca passa a travar a regressao a unsafe-inline no script-src destas paginas. O CSP enforce das vistas admin, maior e dependente da conversao dos onclick e estilos inline restantes, fica para esforco proprio. Pronto para empacotar.
