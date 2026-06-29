# TESTES LIVE - v12.11.9.148 (diagnóstico responsivo)

Testar em teste.softgenial.edu.mz, IDEALMENTE NUM TELEMÓVEL real ou com as
ferramentas de programador do browser em modo 375px. Tempo: ~5 min.

## 1. Tabelas em celular (3 min) - o foco
Em ecrã de 375px (telemóvel ou DevTools):
1. Financeiro > Preços e Serviços: tabela de mensalidades scrolla na
   horizontal DENTRO da caixa; a página NÃO ganha scroll lateral;
2. Financeiro > Devedores: idem;
3. Financeiro > Pagamentos: idem;
4. Contas de aluno (aluno-accounts): idem;
5. WhatsApp Central: tabela idem.

## 2. Modal em celular (1 min)
1. Equipa > editar um membro: o modal abre, e os botões Gravar/Cancelar
   estão acessíveis (se o conteúdo for alto, há scroll DENTRO do modal).

## 3. Desktop não regrediu (1 min)
1. Em ecrã grande, as mesmas tabelas e modais aparecem IGUAIS a antes
   (o wrapper só age quando o ecrã é estreito).

## Critério de aprovação
Em celular: tabelas scrollam na caixa sem arrastar a página, modal com
botões acessíveis. Em desktop: tudo igual. Console limpo = v148 validada.
