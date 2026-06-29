# SPEC-PRESENCAS - Assiduidade académica derivada da Portaria

**Estado:** especificado (v12.11.9.90). Implementação: Sprint 2.
**Valor:** o MINEDH pede mapa de assiduidade; hoje só o Jardim tem presenças.
O ensino geral ganha o módulo SEM trabalho novo na recepção: a Portaria já
lê o crachá à entrada.

## Fundação que JÁ existe
Tabela sige_acessos (escola_id, aluno_id, data_hora, tipo entrada/saida,
porteiro_id, status_no_momento) alimentada em cada leitura do scanner.
O módulo de presenças do Jardim serve de referência de UX.

## Desenho

### Derivação (regra simples e auditável)
Presente no dia D = existe pelo menos um registo 'entrada' em sige_acessos
nesse dia para o aluno. Atraso = primeira entrada depois da hora de corte
da escola (option sige_presencas_hora_corte, defeito 07:30).

### Tabela de excepções (não duplica os acessos)
```
sige_presencas_excecoes
  id, escola_id, aluno_id, data, estado ('falta_justificada',
  'presente_manual', 'dispensado'), motivo, criado_por, criado_em
```
A excepção VENCE a derivação: professor/secretaria corrige sem mexer no
log da Portaria (que é imutável, como auditoria que é).

### Ecrãs
1. **Mapa mensal por turma** (admin/academic/presencas-view.php):
   grelha aluno x dia, P/A/F/J, totais e percentagem; derivado em tempo
   real de sige_acessos + excepções; exportação Excel pelo caminho normal;
2. **Correcção rápida**: clicar numa célula abre justificar/marcar manual
   (permissão academico.presencas_editar, seed para director/secretaria/
   professor da turma);
3. **Relatório mensal MINEDH**: mesmo motor do mapa, formatação oficial.

### Integrações
- Alerta opcional ao encarregado por WhatsApp à hora de corte se o aluno
  não entrou (reutiliza fila + política de destinatários + circulares como
  precedente de mensagem não-financeira); OFF por defeito;
- O relatório mensal automático à Direcção ganha uma linha de assiduidade
  média quando o módulo estiver activo.

### O que NUNCA faz
- Não escreve em sige_acessos (só lê);
- Não cria segunda fonte de verdade: a derivação é função, não cópia;
- Escolas sem scanner: marcação manual por turma no mesmo ecrã (o mapa
  funciona 100% a partir de excepções 'presente_manual').

## Critério de pronto
Mapa mensal correcto contra dados reais de sige_acessos numa escola piloto;
correcção manual auditada; exportação Excel; smoke próprio verde.
