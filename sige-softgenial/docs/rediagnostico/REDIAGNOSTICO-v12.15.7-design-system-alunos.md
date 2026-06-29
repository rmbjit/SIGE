# Rediagnóstico Adversarial - v12.15.7

## Pergunta adversarial 1: voltámos a aplicar uma camada global?
Não. Os assets são carregados apenas em `view=alunos_lista` e os selectores exigem `body.sige-admin-app.sige-view-alunos_lista`.

## Pergunta adversarial 2: há risco de repetir a regressão dos botões mobile no desktop?
Mitigado. `.sige-mobile-card-actions-row` fica `display:none` por defeito e só entra no breakpoint até 900px.

## Pergunta adversarial 3: há risco de o menu dos três pontinhos voltar a abrir horizontal?
Mitigado por contrato CSS: o menu aberto usa `grid-template-columns: 1fr` em desktop, tablet e mobile.

## Pergunta adversarial 4: há risco de texto quebrar letra por letra?
Mitigado por grid mínimo, `min-width:0`, `overflow-wrap:break-word` e `word-break:normal` nos pontos críticos. Ainda exige QA com nomes reais muito longos.

## Pergunta adversarial 5: isto resolve todas as páginas do sistema?
Não. O escopo é apenas Alunos e Matrículas. A estratégia segura é continuar módulo por módulo.

## Riscos residuais
- CSS legado inline dentro de `alunos_lista.php` continua existente.
- Alguns ajustes de densidade podem surgir em dados reais extremos.
- QA browser autenticado é obrigatório antes de aprovar a versão.

## Bloqueadores P0/P1 conhecidos
Nenhum identificado por análise estática/gates. Browser staging é gate humano obrigatório.
