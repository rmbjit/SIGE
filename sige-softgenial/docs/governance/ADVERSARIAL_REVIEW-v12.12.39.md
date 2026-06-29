# Revisao adversarial - v12.12.39 - Fase 2 - Cofre de segredos - rotacao e segredos por escola

Rediagnostico adversarial do terceiro incremento do cofre. O exercicio assume a postura de um revisor hostil e procura partir cada frente antes de a declarar pronta.

## Rediagnostico adversarial (tentativas de quebra e resposta)

1. Ler o segredo de outra escola. O nome da opcao por escola leva o sufixo _esc com o id da escola, pelo que cada escola so acede ao seu. O smoke confirma que a escola 5 e a escola 7 leem valores diferentes para o mesmo nome.
2. Apanhar o segredo em claro no armazenamento. set_for_school sela o valor antes de gravar; o smoke confirma que o valor guardado esta no formato selado e nao em claro.
3. Partir instalacoes pre-cofre. get_for_school passa texto em claro intacto, pelo que segredos legados continuam a ser lidos. O smoke confirma.
4. Esconder que um segredo esta velho. Cada gravacao marca o instante de rotacao; rotation_due devolve verdadeiro para um segredo nunca rodado ou alem da idade maxima, e falso para um acabado de gravar. O smoke confirma, incluindo a idade maxima ajustavel.
5. Gerar segredos previsiveis. A geracao usa random_bytes e produz texto url-safe; dois tokens gerados sao distintos. O smoke confirma o comprimento, o alfabeto e a distincao.
6. Rodar sem deixar rasto ou sem trocar o valor. A rotacao gera um novo segredo, guarda-o cifrado, marca a rotacao, audita segredo_rodado (sem o valor) e devolve o novo segredo. O smoke confirma que o valor guardado revela o novo segredo. A rotacao do webhook de pagamento por escola faz o mesmo, reutilizando o armazenamento ja auditado.
7. Perder um segredo ao re-selar. sige_vault_reseal so sela o que conseguir revelar; um valor nao decifravel fica intacto. O smoke confirma.
8. Mexer na cifra partilhada ou num ficheiro canonico. A camada e aditiva e nao toca em sige_encrypt_token, sige_decrypt_token nem em finance-core; reutiliza seal/reveal. Os gates de cofre anteriores continuam verdes.
9. Inflar a superficie ou as opcoes. As novas funcoes usam nomes de opcao dinamicos por escola (nao literais) e nao registam hooks; o extractor confirma 199 itens de superficie e 132 opcoes.

## P0

Nenhum. O armazenamento e a leitura dos segredos ja existentes nao sao alterados; a cifra partilhada nao e tocada.

## P1

Nenhum. Os segredos por escola sao cifrados e isolados; a rotacao gera segredos fortes e auditados.

## P2 e P3

Nenhum novo. A rotacao dos salts do WordPress continua a exigir re-introducao dos segredos, documentada no guia de instalacao; a re-selagem nao perde valores nao decifraveis.

## Decisao

Aprovado. Zero P0 e zero P1. As quatro frentes estao completas e provadas por gate e smoke dedicados (corredor 89/89): segredos por escola cifrados e isolados, rastreio e decisao de rotacao, geracao forte e rotacao auditada, rotacao concreta do webhook de pagamento por escola e re-selagem segura. Aditivo, sem tocar na cifra partilhada nem em ficheiros canonicos, sem nova superficie, sem opcoes novas, sem alteracao de esquema, calculo byte-identico. Os gates de cofre anteriores continuam verdes. Release gate verde a partir de pasta limpa. A cobertura central da Fase 2 fica completa. Pronto para entrega.
