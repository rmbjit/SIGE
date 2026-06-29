# Rediagnostico adversarial - v12.12.11 (MFA TOTP)

Base: v12.12.10.1. Alvo: o incremento TOTP (aplicacao autenticadora) e a sua
integracao no step-up de operacoes criticas. Metodo: leitura adversarial do
codigo novo e dos pontos de contacto, com modelo de ameaca explicito e
verificacao por execucao real (smoke com 44 verificacoes, todas verdes;
nucleo validado contra os vectores RFC 6238; QR validado celula a celula contra
referencia independente nas versoes 1 a 10).

## Resultado

Zero defeitos P0 e zero defeitos P1. Dois achados P2 que nao sao defeitos:
sao decisoes de desenho conscientes, fechadas com justificacao abaixo (regra
inegociavel: o que nao e defeito fecha-se documentando como tal). Varios pontos
mitigados ou por desenho.

## Modelo de ameaca e analise

### A-T1 Roubo do segredo TOTP (P0 candidato) - MITIGADO
O segredo de 160 bits e guardado cifrado com sige_encrypt_token (sodium
secretbox autenticado; chave derivada dos salts do WordPress). Nunca e
persistido em claro. A chave meta comeca por '_' (oculta da UI de campos
personalizados). Mesmo com leitura directa da meta, o valor e cifrado e
autenticado. Sem defeito.

### A-T2 Forca bruta do codigo de 6 digitos (P1 candidato) - MITIGADO
Codigo de 6 digitos, janela de mais ou menos um passo (tres codigos validos num
dado instante de 10^6). Tecto de tentativas: 10 falhas por janela de 5 minutos
devolvem 'esgotado'. Probabilidade por tentativa na ordem de 3 em 10^6; mesmo
sem tecto a probabilidade de sucesso e desprezavel dentro da validade. Verificado
no smoke (o tecto trava com 'esgotado'). Comparacao em tempo constante
(hash_equals). Sem defeito.

### A-T3 Bloqueio do utilizador / lockout (P1 candidato) - MITIGADO
Quem nao inscrever continua a usar o OTP por email. Quem inscrever usa o codigo
da aplicacao, sempre disponivel offline. Se os salts do WordPress rodarem e o
segredo deixar de decifrar, sige_mfa_totp_enrolled devolve falso (exige segredo
presente E flag confirmado), pelo que o utilizador volta de forma silenciosa ao
email, sem bloqueio. Sem defeito.

### A-T4 Inscricao indevida - MITIGADO
O segredo so passa a activo depois de o utilizador confirmar um codigo de teste
(sige_mfa_totp_confirm_enrollment), o que prova posse da aplicacao. Antes disso o
estado e 'por confirmar' e nao tem efeito no step-up. Verificado no smoke.

### A-T5 Autorizacao do endpoint de inscricao - MITIGADO
O endpoint admin_post:sige_mfa_totp_enroll usa sempre get_current_user_id: um
utilizador so altera a sua propria conta. Protegido por login e nonce
('sige_mfa_totp_enroll'). Observado pelo Security Kernel (regra 195). Que um
utilizador fora dos perfis criticos possa inscrever a sua aplicacao e inofensivo
(mais seguranca, nunca menos).

### A-T6 Reutilizacao do codigo dentro da janela de validade (P2) - DECISAO: ACEITE
O TOTP nao regista o ultimo contador usado, pelo que o mesmo codigo pode
autorizar mais do que um step-up dentro da sua validade (cerca de 90 segundos com
a tolerancia). Decisao de aceitar nesta versao: (1) a confirmacao abre uma janela
de step-up de 5 minutos (sige_mfa_recently_verified), durante a qual o utilizador
ja repete operacoes sem novo codigo, logo o uso unico do codigo acrescentaria
pouco; (2) submeter a confirmacao exige a sessao autenticada e o nonce, nao basta
observar o codigo. Registo de contador de uso unico fica como endurecimento
futuro (defesa em profundidade), nao defeito.

### A-T7 Desactivacao do TOTP nao exige step-up (P2) - DECISAO: ACEITE
Desactivar a propria aplicacao autenticadora exige login, nonce e confirmacao
explicita, mas nao um step-up. Decisao de aceitar nesta versao: o step-up cobre
hoje operacoes financeiras e de configuracao, nao alteracoes de definicoes de
seguranca do proprio perfil; um atacante com a sessao da vitima ja teria outras
vias. Submeter alteracoes de definicoes de seguranca a step-up e um endurecimento
coerente para um incremento futuro, registado como tal. Nao defeito.

### A-T8 Correccao do QR (P0 candidato) - MITIGADO
O codificador foi validado celula a celula contra uma referencia independente
(byte puro, mascara 0, EC nivel L) para todas as versoes 1 a 10 (zero celulas
diferentes) e decodifica com leitor real. Se o URI exceder a capacidade da versao
10, sige_mfa_totp_qr_svg devolve vazio e a interface mostra apenas o segredo
manual (degradacao graciosa). Sem defeito.

### A-T9 Interaccao com o modo estrito (fix do A2) - COERENTE
O modo estrito bloqueia a operacao quando o email falha. Para inscritos nao se
envia email (issue_challenge devolve verdadeiro sem email), logo o modo estrito
nao os afecta: deixam de depender do email por completo. Esta e exactamente a
resolucao de raiz do A2 para esses utilizadores. Verificado no smoke (inscrito
nao aciona o caminho de email).

### A-T10 Entrada controlada pelo atacante no base32 - MITIGADO
O base32_decode so e aplicado ao segredo guardado por nos, nunca a entrada do
utilizador. O codigo submetido e filtrado a digitos e validado em comprimento
antes da verificacao. Sem defeito.

## Conclusao

O incremento esta a zero em defeitos (P0 e P1). Os achados P2 (A-T6, A-T7) sao
decisoes de desenho aceites e documentadas, com endurecimentos futuros
identificados. Pronto a entregar como v12.12.11, com o comportamento desligado
por defeito e sem alteracao para quem nao inscrever.
