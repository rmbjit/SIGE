# QA - SIGE SoftGenial v12.12.11 (MFA de Operacao: TOTP)

Execucao real, sem bootstrap do WordPress (stubs em memoria onde aplicavel).
Base: v12.12.10.1. Regra: zero em tudo antes de avancar.

## 1. Nucleo TOTP (RFC 6238)

- Semente de referencia "12345678901234567890" produz o base32 conhecido GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ.
- Os 6 vectores de referencia da norma batem certo (6 e 8 digitos): t=59, 1111111109, 1111111111, 1234567890, 2000000000, 20000000000.
- verify aceita o codigo do passo anterior (tolerancia de desvio de relogio) e recusa codigo errado. base32 round-trip exacto. Comparacao em tempo constante.

## 2. Codigo QR (servidor, SVG, sem JS nem CDN)

- Matriz validada celula a celula contra referencia independente (qrcode Python, byte puro, mascara 0, EC nivel L): zero celulas diferentes para todas as versoes 1 a 10.
- Decodificacao com leitor real (OpenCV) confirma a leitura do URI exacto. Nota: o detector do OpenCV e mais fraco a escala pequena; cameras de telemovel e aplicacoes autenticadoras sao bem mais robustas. A prova decisiva e a igualdade byte-a-byte a referencia.
- Se o URI exceder a capacidade da versao 10, o SVG vem vazio e a interface mostra apenas a chave manual (degradacao graciosa).

## 3. Ciclo de inscricao (stubs de user meta e cripto)

- Segredo de 160 bits -> 32 caracteres base32. Guardado cifrado (prefixo enc: no stub; sodium secretbox em producao), estado nao confirmado.
- Antes de confirmar: nao inscrito, segredo pendente presente. Confirmar com codigo errado nao activa. Confirmar com o codigo actual activa. Apos confirmar: inscrito, sem segredo pendente.
- verify_user aceita o codigo actual e recusa errado. Desactivar remove inscricao e segredo.

## 4. Integracao no step-up

- issue_challenge para inscrito devolve verdadeiro e NAO aciona o caminho de email (A2 fechado para esses utilizadores); coloca a marca de desafio TOTP pendente.
- pending verdadeiro para inscrito com desafio. verify_challenge recusa codigo errado (errado) e mantem o pending; aceita o codigo certo (ok), abre a janela de step-up e limpa a marca.
- Tecto de tentativas: 10 falhas levam a esgotado.

## 5. Governanca e integridade

- Regra de Security Kernel admin_post:sige_mfa_totp_enroll presente no PHP, no JSON de regras v12.12.11 e no manifesto (195 itens). manifestIds == ruleIds; phpIds === jsonIds. enforce mantem-se em 30.
- 12 documentos de governanca e 7 baselines JSON presentes para v12.12.11 (gate de governanca verde).
- Versao sincronizada nas 3 fontes (cabecalho, SIGE_VERSION, BUILD.json) em 12.12.11; SIGE_GOV_VERSION em 12.12.11.
- Zero travessoes (em-dash e en-dash) em codigo e documentos. Raiz com exactamente os 7 ficheiros canonicos. Lint PHP limpo.

## 6. Gates

- check-mfa-totp.php: OK. smoke-mfa-totp-v12-12-11.php: 44 verificacoes, todas verdes.
- Corredor completo tools/run-gates.php: verde (ver registo da entrega).

## 7. Rediagnostico adversarial

- Zero P0, zero P1. Dois achados P2 aceites e documentados como decisoes de desenho (A-T6 codigo reutilizavel dentro da validade; A-T7 desactivar nao exige step-up), com endurecimentos futuros identificados. Ver ADVERSARIAL_REVIEW-v12.12.11.md.
