
## v12.12.14 - rediagnostico posterior (mesma sessao)

- A-V9 colisao de prefixo (valor em claro a comecar por sige2:/gcm1:): P3 aceite (implausivel para os tipos de segredo abrangidos; sem vantagem para atacante).
- A-V10 normalizacao do default na leitura: nao-defeito (todos os defaults de segredo sao vazios; trim('') = '').
- Evidencia: cofre validado contra a cifra real (sodium), alem do stub do smoke.
- Sem novos P0/P1. Codigo do produto inalterado.
