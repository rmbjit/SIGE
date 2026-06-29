# LIVE-TEST: Portal Chrome vs Views split (v12.15.14)

Esta vaga vem com a flag DESLIGADA por defeito. O objectivo destes testes e
PROVAR em browser que a folha de chrome rende a Pagina do Aluno identica ao
style.css completo, para depois se poder ligar a flag com confianca.

## Estado por defeito (verificar primeiro)

1. Com a flag por defeito (sem mexer em opcoes), entrar como `encarregado` e abrir
   a Pagina do Aluno.
2. No separador Network, confirmar que `sige-design-system` aponta para
   `style.css` (o ficheiro completo). **Esperado:** comportamento identico a
   v12.15.13. Nada mudou ainda.

## Activar em staging (para a prova)

```
update_option('sige_portal_lean_assets_v121513_enabled', '1'); // Fase 1 (ja é o defeito)
update_option('sige_portal_chrome_css_v121514_enabled', '1');  // Fase 2: ligar a folha de chrome
```

## Cenario 1 - A folha de chrome carrega no portal (encarregado)

1. Entrar como `encarregado`, abrir a Pagina do Aluno, abrir Network, recarregar.
2. **Esperado:**
   - `sige-design-system` aponta agora para `style-portal-chrome.css` (~112 KB), nao `style.css`.
   - O peso transferido de CSS do design system cai ~194 KB face ao estado anterior.

## Cenario 2 - Identidade visual pixel-a-pixel (o teste que fecha a fase)

1. Capturar a Pagina do Aluno com a flag DESLIGADA (style.css completo) e com a
   flag LIGADA (folha de chrome), no mesmo utilizador encarregado.
2. Comparar lado a lado:
   - Topbar, sidebar, hero do portal, cartoes de KPI, tabela de financas, area de
     palavra-passe, rodape.
   - Tipografia, cores, espacamentos, raios, sombras.
3. **Esperado:** zero diferencas visuais.
4. **Repetir em tres larguras:** telemovel (~390px), tablet (~800px), desktop.
5. Se aparecer QUALQUER diferenca, desligar a flag (`= 0`), registar o elemento e o
   seletor em falta, e reextrair a folha incluindo a seccao de chrome em falta.

## Cenario 3 - Aluno: mesmo resultado

1. Repetir os Cenarios 1 e 2 com um utilizador `aluno`.

## Cenario 4 - Staff e views financeiras intactos

1. Com a flag LIGADA, entrar como `director` e abrir a Pagina do Aluno: deve
   carregar o `style.css` completo (staff nunca usa a folha de chrome).
2. Abrir Financeiro > Pagamentos, Devedores, Extractos: devem renderizar identicos,
   carregando o `style.css` completo. **Esperado:** nenhuma regressao visual nas
   views financeiras.

## Cenario 5 - Reversao

1. `update_option('sige_portal_chrome_css_v121514_enabled', '0');`
2. Entrar como `encarregado`, recarregar: `sige-design-system` volta a `style.css`.

## Gates automaticos (antes do deploy)

```
php tools/run-gates.php
```

Confirmar verde, em particular:
- `Portal Chrome vs Views split (v12.15.14)`
- `Portal Enxuto Scope Guard (v12.15.13)` e o smoke (Fase 1 intacta)
- `Financeiro Formula Integrity (v12.15.12)` (financeiro intacto)

## Fechar a Fase 2

Apos os Cenarios 2 e 3 passarem em telemovel, tablet e desktop, ligar a flag por
defeito (mudar o default de `'0'` para `'1'` na shell) e entregar essa alteracao
de uma linha como o fecho da fase.
