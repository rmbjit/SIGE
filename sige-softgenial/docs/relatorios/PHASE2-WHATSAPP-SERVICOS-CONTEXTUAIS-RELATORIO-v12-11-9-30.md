# SIGE SoftGenial v12.11.9.30 - WhatsApp Serviços Contextuais PT-MZ

## Base usada

Usei como base a versão imediatamente anterior:

```text
sige-softgenial-v12_11_9_29-whatsapp-pt-mz-genero-saudacao-unica.zip
```

## Problema corrigido

A versão anterior tinha corrigido a gramática quando o género do aluno era incerto, mas a solução neutra usada - “pagamento referente à matrícula de Nome” - podia ficar semanticamente errada.

O motivo: nem todo pagamento registado no sistema é matrícula. Pode ser mensalidade, transporte escolar, inscrição, exames, material escolar, alimentação, actividades, biblioteca ou outro serviço.

## O que foi alterado

### 1. Criação de contexto financeiro por serviço

Foi criado um detector simples e conservador que lê os detalhes do pagamento/lançamento e tenta identificar o tipo real de serviço.

Exemplos detectados:

```text
Mensalidade
Transporte Escolar
Inscrição
Matrícula
Renovação da matrícula
Exames
Material escolar
Alimentação
Actividade escolar
Biblioteca
Serviços escolares
```

Quando o item é claro, a mensagem usa esse item. Quando há múltiplos itens ou o sistema não consegue identificar com segurança, usa “serviços escolares”.

### 2. Remoção da frase genérica “pagamento referente à matrícula”

As mensagens deixam de transformar todos os pagamentos em matrícula.

Antes, podia sair:

```text
O pagamento referente à matrícula de Aibo ficou devidamente registado.
```

Agora deve sair, conforme os detalhes:

```text
O pagamento do transporte escolar do aluno Aibo ficou devidamente registado.
```

ou:

```text
O pagamento da mensalidade da aluna Amina ficou devidamente registado.
```

ou, quando não houver serviço suficientemente claro:

```text
O pagamento dos serviços escolares de Aibo ficou devidamente registado.
```

### 3. Matrícula continua permitida quando for realmente matrícula

Não removi a palavra “matrícula” do sistema. Apenas impedi que ela seja usada como fallback universal.

Se o item pago for realmente matrícula ou renovação da matrícula, a mensagem pode continuar a dizer:

```text
pagamento da matrícula
pagamento da renovação da matrícula
```

### 4. Faturas/lançamentos e cobranças também foram corrigidos

As mensagens de lançamento e cobrança deixam de usar “referente à matrícula” como frase genérica.

Agora usam frases como:

```text
informação financeira sobre a mensalidade do aluno...
situação financeira relacionada com o transporte escolar da aluna...
informação financeira sobre os serviços escolares de Nome...
```

### 5. Fallbacks antigos também foram ajustados

Foram corrigidos fallbacks em:

```text
includes/whatsapp-engine.php
includes/cron-tasks.php
admin/finance/financeiro-gerador.php
admin/finance/financeiro-devedores-view.php
```

Assim, mesmo se o renderer conversacional não for usado por algum motivo, a mensagem não volta a chamar tudo de matrícula.

## Exemplo real renderizado no smoke test

```text
Boa noite, Sra. Bacar.
O pagamento do transporte escolar do aluno Aibo ficou devidamente registado. Partilhamos abaixo o resumo.

Resumo para o seu controlo:
• Transporte Escolar (05/2026) - 600,00 MT (parcial)
Total recebido: 100,00 MT.
Recibo: #REC-2026-000034
Data: 31/05/2026

Podemos partilhar consigo o link do recibo por aqui?
```

## Ficheiros principais alterados

```text
BUILD.json
sige-softgenial.php
includes/whatsapp-templates-conversacional.php
includes/notification-humanization-pro.php
includes/whatsapp-engine.php
includes/whatsapp-recovery-mode.php
includes/cron-tasks.php
admin/finance/financeiro-gerador.php
admin/finance/financeiro-devedores-view.php
tools/smoke-whatsapp-servicos-contextuais-v12-11-9-30.php
tools/smoke-whatsapp-render-servicos-v12-11-9-30.php
```

## Validações feitas

```text
PHP lint: 176 ficheiros verificados
PHP lint: 0 erros sintácticos
Smoke WhatsApp serviços contextuais: 22 OK / 0 FAIL
Smoke render real com Transporte Escolar: 5 OK / 0 FAIL
BUILD.json: JSON válido
```

## O que não foi alterado

```text
pagamentos
recibos
links públicos de recibo
fórmulas financeiras
fórmulas académicas
notas
pautas
boletins
regras de aprovação
arquitectura multi-escola
```

## O que falta validar no ambiente seguro

1. Registar pagamento de transporte escolar e confirmar que a mensagem diz “transporte escolar”.
2. Registar pagamento de mensalidade e confirmar que a mensagem diz “mensalidade”.
3. Registar pagamento de inscrição/matrícula e confirmar que só nesse caso aparece “matrícula” ou “inscrição”.
4. Registar pagamento com múltiplos serviços e confirmar que a mensagem usa “serviços escolares” ou lista os itens no resumo.
5. Confirmar que a mãe continua tratada como “Sra.” quando o número de WhatsApp for o da mãe.
6. Confirmar que a pergunta sobre o link do recibo aparece uma única vez.

## Estado

```text
Versão: 12.11.9.30
Build: sige-12.11.9.30-whatsapp-servicos-contextuais-pt-mz
Estado: hotfix de mensagens WhatsApp pronto para teste seguro
```
