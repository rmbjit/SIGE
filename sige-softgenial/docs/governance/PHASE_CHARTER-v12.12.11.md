# Phase Charter - v12.12.11

Fase 4 (MFA critico), incremento 2: TOTP (aplicacao autenticadora).
Base: v12.12.10.1. Estado: entregue.

## Objectivo

Adicionar um segundo factor alternativo ao OTP por email no step-up de operacoes criticas: TOTP (Time-based One-Time Password, RFC 6238), o codigo de 6 digitos das aplicacoes autenticadoras (Google Authenticator, Microsoft Authenticator, Authy, FreeOTP).

Quem inscrever uma aplicacao autenticadora passa a confirmar as operacoes criticas com o codigo da aplicacao, sem depender do email. Isto fecha de raiz, para esses utilizadores, o risco A2 identificado na v12.12.10 (dependencia da entrega de email para autorizar a operacao).

## Incluido

- Modulo novo includes/security-mfa-totp.php com o nucleo TOTP conforme a RFC 6238 (HMAC-SHA1, 6 digitos, periodo de 30 segundos, tolerancia de mais ou menos um passo para desvio de relogio), validado contra os vectores de referencia da norma.
- Segredo TOTP de 160 bits, guardado cifrado (sige_encrypt_token, sodium secretbox autenticado) em user meta, com flag de confirmacao separada: so se considera inscrito depois de o utilizador confirmar um codigo de teste.
- Codificador de codigo QR proprio, calculado no servidor, com saida em SVG (sem JavaScript e sem CDN), validado celula a celula contra uma referencia independente para todas as versoes suportadas. O segredo e tambem mostrado em texto para introducao manual (caminho garantido).
- Endpoint admin_post:sige_mfa_totp_enroll (inscricao, confirmacao e desactivacao da propria aplicacao), protegido por login e nonce; cada utilizador altera apenas a sua conta. Pagina de gestao em Perfil.
- Integracao no step-up: o endpoint de confirmacao aceita o codigo TOTP (se inscrito) ou o OTP por email (retrocompativel). Inscrito nao gera email. Tecto de tentativas para o TOTP.
- Regra de Security Kernel para o novo endpoint (observe), manifesto a 195 itens, baselines e gate proprios.

## Excluido

- SMS como factor (adiado).
- Codigos de recuperacao (backup codes), candidatos ao incremento 3. O OTP por email permanece como recurso, excepto em modo estrito.
- WebAuthn e passkeys (adiado).
- Reposicao automatica da operacao apos confirmacao: e o incremento seguinte, v12.12.12.

## Riscos

- Roubo do segredo TOTP: mitigado por cifra autenticada em user meta; nunca em texto fora do ecra de inscricao.
- Forca bruta do codigo de 6 digitos: mitigado pela janela curta (mais ou menos um passo) e por tecto de tentativas; a probabilidade por tentativa e praticamente nula mesmo sem tecto.
- Bloqueio do utilizador (lockout): mitigado porque o email continua disponivel para quem nao inscrever; o modo estrito e opt-in e consciente.
- Inscricao indevida: o segredo so e activado depois de confirmar um codigo, o que prova posse da aplicacao.

## Criterios de aceitacao

1. Nucleo TOTP byte-identico aos vectores RFC 6238.
2. QR validado contra referencia independente (decodificacao e igualdade de matriz) nas versoes suportadas.
3. Inscricao completa: segredo cifrado, QR e segredo manual, confirmacao por codigo de teste.
4. Confirmacao do step-up aceita TOTP ou OTP; inscrito nao gera email.
5. Endpoint governado e no manifesto; gate e smoke proprios verdes.
6. Desligado por defeito; sem alteracao de comportamento para quem nao inscrever.
7. Zero em tudo: gates, lint, travessoes (em codigo e documentos), versao sincronizada nas fontes.
