# QA - SIGE SoftGenial v12.12.14 (Secret Vault, incremento 1)

Execucao real (smoke com 26 verificacoes, incluindo integracao com os pagamentos e
o webhook). Base: v12.12.13. Regra: zero em tudo antes de avancar.

## 1. Cifra em repouso (o objectivo)

- M-Pesa (api_key, public_key), e-Mola (api_key, api_secret) e webhook_token: guardados CIFRADOS (prefixo sige2:/gcm1:), diferentes do texto em claro. Verificado.
- Leitura revela: os clientes M-Pesa/e-Mola recebem o valor em claro. Verificado.
- Valores nao-segredo (ambiente, merchant_code) ficam em claro e leem-se iguais. Verificado.

## 2. Cofre unificado

- sige_vault_seal cifra (sige_encrypt_token); idempotente; nunca perde o segredo (se a cifra falhar, mantem o original). Verificado.
- sige_vault_reveal decifra so os nossos formatos; texto em claro passa intacto; nunca expoe ciphertext. Verificado.
- Registo de segredos cataloga pagamentos, SMTP e WhatsApp. Verificado.

## 3. Compatibilidade e migracao

- Instalacao pre-cofre: a leitura devolve o valor em claro. Verificado.
- Auto-reparacao: so no admin, uma vez por segredo, regrava cifrado; fora do admin nao regrava; nunca no caminho publico do webhook. Verificado.
- Sem alteracao de esquema de base de dados.

## 4. Webhook

- find_school_by_token revela o webhook_token antes do hash_equals (ramos scoped e global). Token cifrado e revelado encontra a escola; token errado nao. Verificado.

## 5. Redaccao

- Os campos de segredo nos ecras de configuracao sao inputs de password sem value pre-preenchido; mostram apenas configurado/nao. Verificado no gate.

## 6. Integridade e governanca

- Sem novo endpoint: manifesto 196 inalterado; regras do Kernel inalteradas (enforce 30).
- Sem alteracao de regras de calculo: md5 de sige_fin_saldo_lancamento, sige_fin_saldo_sql e sige_fin_total_bruto_sql inalterados.
- 12 documentos de governanca e baselines presentes para v12.12.14 (gate verde).
- Versao sincronizada nas 3 fontes (cabecalho, SIGE_VERSION, BUILD.json) em 12.12.14; SIGE_GOV_VERSION em 12.12.14.
- Zero travessoes em codigo e documentos. Raiz com os 7 ficheiros canonicos. Lint PHP limpo.

## 7. Gates

- check-vault.php: OK. smoke-vault-v12-12-14.php: 26 verificacoes, todas verdes.
- Corredor completo tools/run-gates.php: verde (ver registo da entrega).

## 8. Rediagnostico adversarial

- Zero P0, zero P1. Credencial em claro, perda na transicao, webhook e escrita na leitura: mitigados e verificados. Achado P3 (cobertura do registo) e decisao de rotacao adiada documentados. Ver ADVERSARIAL_REVIEW-v12.12.14.md.
