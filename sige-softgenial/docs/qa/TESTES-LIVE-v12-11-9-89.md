# TESTES LIVE v12.11.9.89

Tempo estimado: 20 a 25 minutos. Fazer primeiro no subdomínio demo/teste; só depois nas escolas.

## 0. Sanidade da instalação
- [ ] Estado do Sistema mostra versão 12.11.9.89 e nenhum aviso de divergência de build.
- [ ] Login normal de um Director e de um Secretário funciona; menu carrega completo.

## 1. Rota reparada (a correcção principal de navegação)
- [ ] Abrir `wp-admin/admin.php?page=sige-app&view=vincular` com perfil Director ou Secretaria: a página "Atribuição de Carga Horária" abre (antes respondia "Área indisponível").
- [ ] Central WhatsApp e Diagnóstico WhatsApp continuam a abrir pelas rotas habituais.

## 2. Segurança dos utilitários (teste no browser, sem sessão)
- [ ] Abrir `https://ESCOLA.softgenial.edu.mz/wp-content/plugins/sige-softgenial/tools/smoke-release-gate.php`: deve devolver 403 Forbidden (nunca conteúdo).
- [ ] Abrir `.../wp-content/plugins/sige-softgenial/includes/`: não deve listar ficheiros (página em branco).

## 3. Copys de produto (verificação visual)
- [ ] Diagnóstico WhatsApp: título sem badge de versão; bloco "Tarefas automáticas de envio" presente.
- [ ] Central WhatsApp: se aparecer o aviso de recibos antigos, o botão diz "Preparar resposta automática para N recibo(s)" e a confirmação está em linguagem humana.
- [ ] Acta (turma com Censo): selos dizem "Censo oficial guardado/ainda não confirmado"; o aviso de Censo desactualizado, se existir, não mostra número de versão.
- [ ] Currículos: painel "Gestão de Currículos"; badge "Modo seguro".
- [ ] Permissões: selo "Matriz de permissões activa".

## 4. Financeiro (fluxo intocado nas regras; verificar comportamento e copy)
- [ ] Registar um pagamento de teste: recibo gera normal; valores e saldo correctos.
- [ ] Forçar sessão expirada (deixar o formulário aberto 15+ min ou abrir noutro separador e terminar sessão) e submeter: mensagem "A sessão expirou por segurança. Recarregue a página e tente novamente." em vez de "(nonce)".
- [ ] Extractos/Caixa: fechar o caixa do dia de teste com a checklist de reconciliação; o selo final diz "Caixa encerrado · Reconciliação concluída" e o termo imprime.
- [ ] Confirmar nas observações do fecho que o cabeçalho novo é "FECHO DE CAIXA COM RECONCILIAÇÃO"; um fecho ANTIGO continua a mostrar o histórico anterior sem corrupção.
- [ ] Lançamentos: cancelar e reactivar um lançamento de teste; o botão "Confirmar Reactivação" executa e o estado volta correctamente.
- [ ] Mobile (telemóvel real ou DevTools): na tabela de extractos, os cartões mostram "Seleccionar" e "Acções".

## 5. Académico
- [ ] Boletim e Pauta de uma turma real abrem e exportam PDF sem alterações de notas.
- [ ] Acta: download/PDF de uma acta existente mantém estatística por género igual à de antes do deploy.

## 6. Perfis restritos
- [ ] Guarda/Portaria: scanner abre, aluno activo autoriza, suspenso/transferido bloqueia com a mensagem clara; nenhum menu extra apareceu.
- [ ] Professor: continua limitado às suas turmas/disciplinas.

## 7. Comunicação
- [ ] Enviar 1 mensagem WhatsApp de teste pela Central: sai e o estado actualiza.
- [ ] Se alguma mensagem antiga da fila aparecer cancelada, o motivo lê-se em linguagem humana (sem "v12.9.x" nem "template legado").

## 8. Regressão rápida de dados (consulta, sem alterar nada)
- [ ] Total de alunos activos no Painel = mesmo número de antes do deploy.
- [ ] Saldo em aberto de 2 alunos conhecidos = igual ao de antes do deploy.

## Critério de aprovação
Tudo verde nas secções 0 a 4 = aprovar nas escolas. Qualquer vermelho na secção 4 ou 8 = rollback imediato (apenas ficheiros; sem migração de BD nesta release) e reportar.
