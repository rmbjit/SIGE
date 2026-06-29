# Rediagnostico adversarial - v12.12.14 (Secret Vault, incremento 1)

Base: v12.12.13. Alvo: a cifra em repouso dos segredos dos gateways de pagamento e
a sua transparencia para os clientes e para o webhook. Metodo: leitura adversarial
e verificacao por execucao real (smoke com 26 verificacoes e gate proprio, ambos
verdes), incluindo o caminho do webhook e a auto-reparacao.

## Resultado

Zero defeitos P0 e zero defeitos P1. As credenciais dos gateways passam a estar
cifradas em repouso sem alterar o comportamento dos pagamentos. Achados residuais
sao decisoes documentadas.

## Modelo de ameaca e analise

### A-V1 Credencial em claro na base de dados (P1 candidato) - MITIGADO E VERIFICADO
sige_mobile_payment_update_option passa a selar as chaves-segredo com
sige_vault_seal (sige_encrypt_token). Verificado: o valor guardado em repouso esta
cifrado (prefixo sige2:/gcm1:) e difere do texto em claro; a leitura devolve o
valor em claro ao cliente. Sem defeito.

### A-V2 Perder a credencial na transicao (P1 candidato) - MITIGADO
sige_vault_reveal so decifra os nossos formatos com prefixo; texto em claro de
instalacoes pre-cofre passa intacto. selar nunca devolve um resultado nao-selado
(se a cifra falhar, mantem o original). Verificado round-trip e passagem. Sem
defeito.

### A-V3 Webhook deixar de validar por causa da cifra (P1 candidato) - MITIGADO
find_school_by_token revela o webhook_token antes do hash_equals, nos dois ramos
(scoped e global). Verificado: token cifrado e revelado e a escola e encontrada;
token errado nao encontra. Sem defeito.

### A-V4 Escrita inesperada durante leitura (auto-reparacao) (P2 candidato) - MITIGADO
A auto-reparacao so ocorre uma vez por segredo, so quando is_admin e verdadeiro, e
so se o valor estava em claro. Nunca no caminho publico do webhook. Verificado:
fora do admin nao regrava; no admin regrava cifrado. Sem defeito.

### A-V5 Fuga do segredo pelo ecra (P2 candidato) - MITIGADO
Os campos de segredo nos ecras de configuracao sao inputs de password sem value
pre-preenchido; mostram apenas o estado (configurado/nao). Nenhum diagnostico
revela o segredo decifrado. Sem defeito.

### A-V6 Chave de cifra e gestao - COERENTE
Reutiliza a derivacao existente a partir dos salts do WordPress (AUTH_SALT,
SECURE_AUTH_SALT). Sem novo armazenamento de chave. A rotacao de chave fica para
um incremento posterior (decisao registada), sem afectar a proteccao entregue.

### A-V7 Cobertura do registo de segredos (P3) - DECISAO: ACEITE
O registo cataloga os segredos conhecidos (pagamentos, SMTP, WhatsApp). SMTP e
WhatsApp ja cifravam e ficam conformes por catalogacao, sem alterar o codigo que
ja funciona (evita regressao). Eventuais segredos futuros entram no registo.

### A-V8 Sem novo endpoint - COERENTE
O cofre e uma camada interna; nao adiciona endpoint nem altera permissoes. Manifesto
mantem-se em 196; regras inalteradas.

## Conclusao

Zero P0 e zero P1. A cifra em repouso dos segredos dos gateways esta provada em
execucao real, transparente para clientes e webhook. Os achados P3 (A-V7) e as
decisoes (rotacao adiada) ficam documentados. Pronto a entregar como v12.12.14.

## Rediagnostico posterior a entrega (mesma sessao)

Verificacao adicional, a tentar partir a versao ja entregue. Resultado: mantem-se
Zero P0 e Zero P1. Dois achados P3 novos, fechados como decisoes documentadas, e
uma evidencia importante acrescentada.

### Evidencia: cofre validado contra a cifra REAL (sodium), nao so contra o stub
O smoke usa uma cifra simulada fiel (prefixo sige2:). Para fechar essa lacuna,
extraiu-se sige_encrypt_token e sige_decrypt_token reais (sodium secretbox, chave
dos salts) e testou-se o cofre por cima delas: selar produz sige2:, difere do
texto, revelar reconstroi, e idempotente, texto em claro passa intacto, e um PEM
multi-linha sobrevive a selar/revelar. Confirmou-se ainda que duas selagens dao
ciphertext diferente (nonce aleatorio) mas decifram para o mesmo valor, o que
torna seguras as corridas de auto-reparacao. Tudo OK.

### A-V9 Colisao de prefixo (P3) - DECISAO: ACEITE
Se um valor em claro comecasse exactamente por sige2: ou gcm1:, is_sealed
classifica-lo-ia como selado e revelar devolveria vazio. Para os tipos de segredo
abrangidos (chaves de API alfanumericas, PEM que comeca por -----BEGIN, e tokens
wp_generate_password), isto e implausivel. Nao da vantagem a um atacante (no maximo
a sua propria credencial deixaria de funcionar). Aceite como aresta de robustez;
o prefixo e um marcador de formato deliberado.

### A-V10 Normalizacao do default na leitura (P3) - NAO-DEFEITO
O getter reescrito devolve o default sem trim quando a opcao falta, enquanto o
codigo anterior aplicava trim ao default. Verificou-se que todos os defaults
passados aos getters de segredo sao vazios (''), pelo que trim('') continua ''. Sem
impacto pratico. Nao-defeito; documentado para rasto.

### Regressoes verificadas como ausentes
- O trim da public_key ja existia antes do cofre (mesmo comportamento); o PEM
  continua a funcionar no cliente M-Pesa. Sem regressao.
- As duas leituras do webhook_token no caminho publico (scoped e global) revelam
  antes do hash_equals. Caminho publico coberto nos dois ramos.
- SMTP e WhatsApp nao foram tocados (mantem a sua propria cifra); zero risco para
  esses fluxos.
- Apos rotacao dos salts do WordPress, as credenciais cifradas deixam de decifrar
  e o ecra mostra-as como nao definidas, exigindo reintroducao. E o mesmo modelo ja
  aceite para SMTP e WhatsApp; documentado no DEPLOY. Nao e regressao.

### Conclusao do rediagnostico posterior
Zero P0 e Zero P1. A versao entregue mantem-se valida; o codigo do produto nao
precisou de alteracao. Acrescentaram-se os achados P3 (A-V9, A-V10), a evidencia da
cifra real, e um caso de smoke para o ramo global do webhook.
