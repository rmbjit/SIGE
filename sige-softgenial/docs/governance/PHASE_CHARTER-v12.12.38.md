# Carta da fase - v12.12.38 - Fase 2 - Cofre de segredos - cobertura, mascaramento e nao-vazamento

Segundo incremento da Fase 2 (cofre de segredos completo, que completa a Fase 5 do plano original). Constroi sobre o incremento 1, que cifrou as credenciais dos gateways de pagamento em repouso.

## Objectivo

Alargar o cofre para alem dos gateways: mascarar segredos na exibicao, impedir que segredos cheguem a registos (logs) ou a enderecos (URL), auditar quem altera um segredo (sem o valor) e completar o registo de segredos conhecidos. Tudo sem mexer no armazenamento ou na leitura dos segredos vivos, e sem tocar em ficheiros canonicos.

## Incluido

Frente 1 - Mascaramento central. Nova funcao sige_secret_mask que esconde um segredo para exibicao, revelando apenas os ultimos caracteres e sem revelar o comprimento real. Complementa o mascaramento ja existente no repositorio de definicoes, ficando disponivel para qualquer ecra.

Frente 2 - Proibicao de segredos em registos. Nova funcao sige_secret_scrub que redige de um texto os tokens selados pelo cofre (formatos sige2: e gcm1:) e os pares chave=valor de segredos conhecidos (senha, password, secret, api_key, token, webhook_token, entre outros). Aplicada no canal de seguranca (sige_security_log) antes de qualquer registo: um segredo nunca chega aos logs em claro. O texto normal passa intacto, sem sobre-redaccao.

Frente 3 - Proibicao de segredos em URL. Um gate estatico garante que nenhuma chave-credencial (webhook_token, api_key, api_secret, client_secret, password, senha) e colocada num URL via add_query_arg. A mesma redaccao trata URLs que sejam registados.

Frente 4 - Auditoria de alteracao. A gravacao de um segredo passa a registar um evento segredo_alterado com a chave (e o fornecedor, no caso dos pagamentos), nunca o valor. Cobre o repositorio de definicoes (campos cifrados e SMTP) e a gravacao de segredos de pagamento. O registo de segredos conhecidos foi alargado para incluir a chave de licenca, alem das credenciais de pagamento, SMTP e WhatsApp ja presentes.

## Excluido

- Rotacao de segredos e segredos por escola (alem do que ja existe para pagamentos) ficam para o incremento seguinte da Fase 2.
- Nenhum ficheiro canonico e tocado: o registo de auditoria financeiro (sige_audit_log, em finance-core) nao e alterado; a redaccao liga-se ao canal de seguranca (sige_security_log).
- O armazenamento e a leitura dos segredos vivos nao sao alterados (sem migracao de re-cifra nesta etapa).
- Nenhuma regra de calculo academico ou financeiro e tocada. Sem novo ecra. Sem alteracao de esquema.

## Riscos

- Sobre-redaccao de texto normal. Mitigacao: a redaccao usa padroes especificos (formatos selados do cofre e nomes de chave de segredo); o texto normal (por exemplo aluno_id=7, field=doc_bi) passa intacto, comprovado por smoke.
- Falsa sensacao de cobertura. Mitigacao: a redaccao cobre o canal de seguranca, nao todos os registos; isto fica documentado, e a convencao e redigir antes de registar usando sige_secret_scrub.
- Alargar o registo de segredos afectar a cifra de pagamentos. Mitigacao: a classificacao de segredos so e usada para selar nos pagamentos; adicionar a licenca e declarativo e nao altera o caminho de pagamentos, comprovado pelos gates de cofre anteriores que continuam verdes.

## Criterios de aceitacao

- Um segredo mascarado revela apenas os ultimos caracteres.
- Um token selado e um par chave=valor de segredo num texto de registo aparecem como [SEGREDO]; o texto normal fica intacto.
- A alteracao de um segredo regista segredo_alterado com a chave, nunca o valor.
- Nenhum ecra coloca um segredo num URL.
- Superficie de accao inalterada (199, enforce 33; views 60), sem opcoes novas (132), dependencias externas inalteradas (11), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 87/87 e release gate verde a partir de pasta limpa. Os gates de cofre anteriores continuam verdes. Rediagnostico adversarial Zero P0/P1.
