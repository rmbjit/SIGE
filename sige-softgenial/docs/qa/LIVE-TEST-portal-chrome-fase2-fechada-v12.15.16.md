# LIVE-TEST: Fase 2 fechada, Portal Chrome ligado por defeito (v12.15.16)

A flag de chrome esta agora LIGADA por defeito. Estes testes confirmam o novo
comportamento por defeito e que a reversao continua a funcionar.

## Cenario 1 - Default ligado: portal carrega a folha de chrome

1. Sem mexer em opcoes, entrar como `encarregado` e abrir a Pagina do Aluno.
2. No Network, filtrar CSS e recarregar.
   **Esperado:** `sige-design-system` aponta para `style-portal-chrome.css` (~109 KB),
   NAO para `style.css`. O peso de CSS do design system caiu ~194 KB.
3. **Confirmar render identico** ao que validaste no staging: topbar, sidebar,
   hero, KPIs, tabela de financas, area de palavra-passe.

## Cenario 2 - Aluno: mesmo comportamento

1. Repetir com um utilizador `aluno`. **Esperado:** folha de chrome, render igual.

## Cenario 3 - Staff inalterado

1. Entrar como `director` e abrir a Pagina do Aluno do mesmo aluno.
   **Esperado:** `sige-design-system` aponta para `style.css` completo (staff nunca
   usa a folha de chrome).
2. Abrir Financeiro > Pagamentos, Devedores, Extractos.
   **Esperado:** render identico, `style.css` completo, sem regressao.

## Cenario 4 - Reversao funciona

1. `update_option('sige_portal_chrome_css_v121514_enabled', '0');`
2. Entrar como `encarregado`, recarregar.
   **Esperado:** `sige-design-system` volta a `style.css` completo.
3. Repor a `'1'` (ou apagar a option) para voltar ao default ligado.

## Cenario 5 - Drift continua protegido

1. Editar trivialmente uma regra de chrome no `style.css` sem regenerar.
2. `php tools/run-gates.php`.
   **Esperado:** `Portal Chrome vs Views split (v12.15.16)` fica VERMELHO.
3. `php tools/gen-portal-chrome.php` e correr os gates de novo.
   **Esperado:** verde. (Reverter a edicao de teste.)

## Gates (antes do deploy)

```
php tools/gen-portal-chrome.php
php tools/run-gates.php
```
Confirmar verde, em particular `Portal Chrome vs Views split (v12.15.16)`,
`Portal Enxuto Scope Guard (v12.15.13)` e `Financeiro Formula Integrity (v12.15.12)`.
