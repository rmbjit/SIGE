# Carta da fase - v12.12.39 - Fase 2 - Cofre de segredos - rotacao e segredos por escola

Terceiro incremento da Fase 2 (cofre de segredos completo, que completa a Fase 5 do plano original). Constroi sobre os incrementos 1 (gateways cifrados) e 2 (mascaramento, redaccao em registos e auditoria de alteracao).

## Objectivo

Permitir que cada escola tenha os seus proprios segredos cifrados e isolados, dar visibilidade sobre segredos a precisar de rotacao, e oferecer rotacao real (geracao e instalacao de novos segredos, com auditoria). Tudo sem mexer no armazenamento ou na leitura dos segredos ja existentes, na cifra partilhada nem em ficheiros canonicos.

## Incluido

Frente 1 - Segredos por escola. Novas funcoes sige_secret_set_for_school, sige_secret_get_for_school e sige_secret_delete_for_school guardam e leem um segredo isolado por escola, cifrado em repouso pelo cofre, com nome de opcao dinamico por escola (sufixo _esc). Texto em claro de instalacoes pre-cofre passa intacto. Generaliza o isolamento por escola que ja existia nos pagamentos para qualquer segredo.

Frente 2 - Rastreio de rotacao. Cada gravacao de segredo regista o instante da ultima rotacao. A funcao sige_secret_rotation_due indica se um segredo nunca foi rodado ou ja excedeu a idade maxima (constante SIGE_SECRET_ROTATION_DAYS, por omissao 180 dias), dando visibilidade operacional.

Frente 3 - Geracao e rotacao. sige_secret_generate_token gera um segredo aleatorio forte e url-safe (random_bytes). sige_secret_rotate_for_school gera um novo segredo, guarda-o cifrado por escola, marca a rotacao, audita o evento segredo_rodado e devolve o novo segredo em claro para configurar no fornecedor.

Frente 4 - Rotacao concreta do webhook de pagamento. sige_mobile_payment_rotate_webhook_token gera e instala um novo webhook_token por escola, reutilizando o armazenamento cifrado e auditado que ja existia. Inclui ainda sige_vault_reseal, que re-sela um valor por higiene de formato sem nunca perder um valor nao decifravel.

## Excluido

- Re-cifra automatica para sobreviver a rotacao dos salts do WordPress: a chave da cifra deriva dos salts, pelo que rotar os salts continua a exigir re-introducao (ou re-selagem previa) dos segredos. Isto fica documentado no guia de instalacao e nao e tocado aqui (evita mexer na cifra partilhada).
- Interface grafica de rotacao e relatorio de segredos a vencer: a logica fica disponivel; a apresentacao podera vir num passe de interface.
- Nenhuma alteracao ao armazenamento ou a leitura dos segredos ja existentes. Nenhum ficheiro canonico tocado. Sem alteracao de esquema. Sem regras de calculo tocadas.

## Riscos

- Perda de um segredo na re-selagem. Mitigacao: sige_vault_reseal so sela o que conseguir revelar; um valor nao decifravel fica intacto, comprovado por smoke.
- Colisao de nomes de opcao por escola. Mitigacao: o nome e saneado (apenas minusculas, digitos e underscore) e leva o sufixo _esc com o id da escola, garantindo unicidade.
- Aleatoriedade fraca na geracao. Mitigacao: usa random_bytes; em falha extrema, recorre a uma combinacao de fontes apenas para evitar falha total, nunca como caminho normal.

## Criterios de aceitacao

- Duas escolas guardam o mesmo nome de segredo com valores diferentes e cada uma le o seu; o valor guardado esta cifrado.
- Um segredo nunca rodado ou antigo aparece como a precisar de rotacao; um acabado de gravar nao.
- Rodar o webhook_token de pagamento por escola gera um novo token, guarda-o cifrado e audita segredo_rodado sem o valor.
- Um segredo legado em claro continua a ser lido.
- Superficie de accao inalterada (199, enforce 33; views 60), sem opcoes novas (132), dependencias externas inalteradas (11), versao sincronizada nas cinco fontes, zero travessoes.
- Corredor 89/89 e release gate verde a partir de pasta limpa. Os gates de cofre anteriores continuam verdes. Rediagnostico adversarial Zero P0/P1.
