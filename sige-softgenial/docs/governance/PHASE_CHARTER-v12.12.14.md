# Phase Charter - v12.12.14

Fase 5 (Secret Vault), incremento 1. Cifra de segredos sensiveis em repouso, com
um cofre unificado sobre a cifra forte existente. Base: v12.12.13. Estado: entregue.

## Estado real apurado

- Cifra existente e forte: sige_encrypt_token/sige_decrypt_token (sodium secretbox, recuo para AES-256-GCM; chave dos salts do WordPress; formatos com prefixo sige2: e gcm1:). Em texto em claro, o decifrar devolve vazio por seguranca.
- Ja cifrados em repouso: password SMTP (sige_smtp_config) e token WhatsApp/Z-API (db-handler).
- Em claro nas opcoes (a lacuna fechada nesta fase): credenciais dos gateways de pagamento. sige_mobile_payment_update_option gravava update_option sem cifrar. Afecta M-Pesa (api_key, public_key), e-Mola (api_key, api_secret) e o webhook_token.

## Objectivo

Garantir que nenhum segredo conhecido fica em claro em repouso, com um cofre
unificado (sige_vault_*) e um registo de segredos auditavel. Fechar o risco
residual P2-003 selando as credenciais dos gateways, de forma transparente para
quem as usa (os clientes M-Pesa/e-Mola continuam a receber o valor em claro).

## Incluido

- Cofre unificado includes/security-vault.php, sobre a cifra existente: sige_vault_seal (cifra), sige_vault_reveal (decifra so os nossos formatos; texto em claro passa intacto), sige_vault_is_sealed, e um registo das chaves-segredo por area (sige_vault_secret_registry, sige_vault_is_secret_key). Selar nunca perde o segredo; idempotente.
- Selagem em repouso das credenciais dos gateways: sige_mobile_payment_update_option sela as chaves-segredo; sige_mobile_payment_get_option revela-as; o caminho publico do webhook (find_school_by_token) revela o webhook_token antes do hash_equals. Os pagamentos mantem o comportamento.
- Migracao retrocompativel sem operacao em massa: leitura com passagem de texto em claro, e auto-reparacao na leitura privilegiada (a primeira leitura no admin de cada segredo regrava-o cifrado, uma so vez; nunca no webhook publico). Sem alteracao de esquema de base de dados.
- Registo de segredos auditavel: cataloga SMTP, WhatsApp e pagamentos. Gate verifica que nenhuma chave-segredo registada e gravada em claro.
- Redaccao: confirmado que os ecras de configuracao dos gateways nao pre-preenchem o segredo (campos password sem value; mostram apenas configurado/nao).

## Excluido (adiado)

- SMTP e WhatsApp ja cifram em repouso; ficam conformes por catalogacao, sem mexer no codigo que ja funciona.
- Ferramenta de rotacao de chave e comando de re-cifragem em massa: runbook e tooling operacional, para um incremento posterior do cofre.

## Riscos

- Perder credenciais na transicao: o revelar passa o texto em claro intacto; os gateways continuam a receber o valor em claro. Provado por round-trip e por teste de passagem.
- Escrita durante leitura (auto-reparacao): so uma vez por segredo, so na leitura privilegiada (is_admin), nunca no webhook publico.
- Sem alteracao de regras de calculo, de permissoes de configuracao dos pagamentos, nem de esquema. Sem novo endpoint (manifesto mantem-se em 196).

## Criterios de aceitacao

1. Credenciais M-Pesa e e-Mola e webhook_token cifradas em repouso; os clientes continuam a receber o valor em claro.
2. Cofre unificado com passagem de texto em claro e auto-reparacao so no admin.
3. Registo de segredos cataloga SMTP, WhatsApp e pagamentos; gate verifica ausencia de gravacao em claro.
4. Ecras nao pre-preenchem o segredo.
5. Gate e smoke do cofre verdes; corredor completo verde; manifesto 196 inalterado; zero travessoes; versao sincronizada; raiz canonica; md5 de calculo intactos.
