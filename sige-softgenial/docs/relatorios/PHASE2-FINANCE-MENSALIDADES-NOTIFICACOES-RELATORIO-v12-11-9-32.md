# SIGE SoftGenial v12.11.9.32 - Mensalidades com opt-in seguro de WhatsApp e E-mail

## Base usada

- Base: `12.11.9.31`
- Nova versão: `12.11.9.32`
- Build: `sige-12.11.9.32-mensalidades-notificacoes-optin-seguro`
- Canal: `test`

## Pedido atendido

No módulo **Lançar Mensalidades**, foi adicionada a possibilidade de a secretaria escolher, em cada operação, se pretende enviar mensagens por:

- WhatsApp;
- E-mail;
- ambos;
- nenhum.

O padrão seguro continua a ser **não enviar mensagens**.

## O que foi feito

### 1. Opção explícita no lançamento de mensalidades

No formulário de lançamento, foram adicionadas duas opções visíveis:

- `Enviar WhatsApp aos encarregados`;
- `Enviar E-mail aos encarregados`.

Foram preservados inputs ocultos com valor `0`, para garantir que, quando a pessoa não marca nada, o sistema recebe claramente a instrução de **não enviar**.

### 2. Política central continua conservadora

A política global em `includes/notification-policy.php` continua a tratar lançamento/fatura/mensalidade como eventos bloqueados por padrão.

Foi adicionada uma excepção estreita, válida apenas quando:

- a requisição é POST;
- vem do gerador de mensalidades;
- tem nonce válido;
- o operador marcou WhatsApp e/ou E-mail.

Isto evita reactivar envios em massa por acidente.

### 3. WhatsApp continua a ser enfileirado, não enviado no clique

O fluxo de WhatsApp continua a usar:

- `sige_fin_queue_whatsapp()`;
- humanização de mensagem;
- guardrails anti-duplicado/cooldown;
- `scheduled_at`;
- atraso progressivo com `sige_notify_apply_batch_delay()`.

Ou seja, o clique em “Gerar lançamentos” não dispara mensagens directamente para todos os números.

### 4. Protecção contra envio agressivo

Foram adicionadas duas travas de segurança:

1. **Gerar ano todo**: se a pessoa marcar mensagens e também “Gerar ano todo”, as mensagens são desligadas automaticamente.
2. **Lote grande de WhatsApp**: por padrão, se o lote tiver mais de 80 alunos, o WhatsApp é desligado automaticamente. Esse limite pode ser ajustado via filtro `sige_fin_lancamento_whatsapp_max_lote`.

O e-mail pode continuar quando escolhido, mesmo que o WhatsApp seja bloqueado por limite de lote.

### 5. Resumo de confirmação e sucesso

O modal de confirmação agora mostra quais canais foram seleccionados.

O modal de sucesso mostra:

- canal escolhido;
- tentativas WhatsApp;
- mensagens WhatsApp agendadas;
- falhas WhatsApp;
- alunos sem número;
- tentativas de e-mail;
- e-mails enviados;
- falhas de e-mail.

### 6. Avisos visuais

O formulário informa claramente que mensagens estão desactivadas por padrão e que o WhatsApp deve ser usado com responsabilidade.

Quando o sistema bloqueia mensagens por segurança, mostra aviso na própria página.

## Avaliação honesta sobre risco de bloqueio de números

Sim, **existe risco de bloqueio/restrição de número** se a escola usar WhatsApp como envio em massa repetitivo, especialmente para lançamento de mensalidades de toda a escola, várias turmas ou ano inteiro.

A forma actual do SoftGenial é relativamente segura porque:

- não envia no clique;
- coloca mensagens na fila;
- agenda com `scheduled_at`;
- aplica guardrails e cooldown;
- evita duplicados;
- humaniza a mensagem.

Mesmo assim, não existe garantia absoluta contra restrições, porque as políticas dos provedores WhatsApp podem reagir a volume, frequência, repetição de texto, denúncias, destinatários sem consentimento ou comportamento parecido com spam.

Por isso, a versão `12.11.9.32` torna o envio opcional, desligado por padrão e com travas para cenários de maior risco.

## Recomendação de uso

Para reduzir risco:

- usar WhatsApp apenas quando a comunicação for necessária;
- evitar envio para toda a escola de uma só vez;
- preferir turma específica ou aluno específico;
- não usar WhatsApp no modo “Gerar ano todo”;
- manter mensagens de cobrança concentradas na Central de Cobranças;
- garantir que os encarregados aceitaram receber mensagens da escola;
- manter textos claros, respeitosos e não agressivos.

## O que não foi alterado

Não foram alterados:

- cálculos de mensalidade;
- descontos;
- multas;
- valores;
- recibos;
- pagamentos;
- notas;
- pautas;
- boletins;
- regras académicas;
- schema multi-escola.

## Validação técnica

Foram validados:

- sintaxe PHP de todos os ficheiros;
- `BUILD.json`;
- smoke test específico `tools/smoke-lancamento-notificacoes-v12-11-9-32.php`;
- smoke test financeiro `tools/smoke-finance-lancamento-notificacoes-v12-11-9-32.php`;
- integridade do ZIP.

## O que falta validar em ambiente seguro

Testar:

1. lançar mensalidade sem marcar WhatsApp/E-mail;
2. lançar mensalidade marcando apenas WhatsApp;
3. lançar mensalidade marcando apenas E-mail;
4. lançar mensalidade marcando WhatsApp + E-mail;
5. marcar “Gerar ano todo” + mensagens e confirmar que mensagens são desligadas;
6. testar lote maior que o limite de WhatsApp;
7. confirmar que WhatsApp entra na fila, não sai imediatamente;
8. confirmar que e-mail é enviado apenas quando marcado;
9. confirmar que pagamentos, recibos e fórmulas continuam normais.
