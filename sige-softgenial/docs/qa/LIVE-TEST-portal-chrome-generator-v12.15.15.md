# LIVE-TEST: Portal Chrome generator + drift gate (v12.15.15)

Esta versao productioniza a folha de chrome. Os testes provam que a geracao e a
proteccao de drift funcionam. A flag continua desligada por defeito.

## Cenario 1 - Geracao e gate em sincronia

1. `php tools/gen-portal-chrome.php`
   **Esperado:** escreve `assets/style-portal-chrome.css` e imprime o tamanho (~109 KB de ~302 KB, -64%).
2. `php tools/run-gates.php`
   **Esperado:** verde, incluindo `Portal Chrome vs Views split (v12.15.15)`.

## Cenario 2 - Proteccao de drift (o teste central)

1. Editar trivialmente uma regra de CHROME no `style.css` (por exemplo, mudar uma
   cor de fundo do topbar) e GUARDAR, sem regenerar a folha.
2. `php tools/run-gates.php`
   **Esperado:** o gate `Portal Chrome vs Views split (v12.15.15)` fica VERMELHO,
   com a mensagem de dessincronizacao e a instrucao de regenerar.
3. `php tools/gen-portal-chrome.php` e correr os gates de novo.
   **Esperado:** verde outra vez. (Reverter a edicao de teste se foi so para o teste.)

## Cenario 3 - Ancora removida falha de proposito

1. Renomear (so para teste) o titulo de seccao "Pagamentos por Turma: Compliance"
   no `style.css`.
2. `php tools/gen-portal-chrome.php`
   **Esperado:** falha com mensagem de ancora ausente, forcando actualizacao
   consciente das ancoras. Reverter o nome.

## Cenario 4 - Comportamento por defeito inalterado

1. Sem mexer em opcoes, entrar como `encarregado` e abrir a Pagina do Aluno.
2. No Network, `sige-design-system` aponta para `style.css` (completo).
   **Esperado:** identico a 12.15.13/12.15.14. Nada mudou para o utilizador.

## O passo que fecha a Fase 2 (prova visual, teu)

Continua valido o protocolo do LIVE-TEST da v12.15.14
(`LIVE-TEST-portal-chrome-split-v12.15.14.md`): ligar a flag no staging, comparar
a Pagina do Aluno com a folha de chrome face ao `style.css` completo, como
encarregado e como aluno, em telemovel, tablet e desktop, e confirmar zero
diferencas. So depois disso se liga a flag por defeito.

## Gates (antes do deploy)

```
php tools/gen-portal-chrome.php   # garantir folha sincronizada
php tools/run-gates.php
```
Confirmar verde, em particular `Portal Chrome vs Views split (v12.15.15)`,
`Portal Enxuto Scope Guard (v12.15.13)`, e `Financeiro Formula Integrity (v12.15.12)`.
