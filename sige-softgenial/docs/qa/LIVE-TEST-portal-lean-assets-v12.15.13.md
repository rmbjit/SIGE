# LIVE-TEST: Portal Enxuto (v12.15.13)

Cenarios de teste vivo para validar a vaga de peso de entrega por papel. QA
visual em browser autenticado e obrigatorio porque o ambiente de construcao
nao tem acesso ao staging.

## Pre-requisitos

- Ter, na mesma escola, pelo menos um utilizador com papel `encarregado` (ou
  `aluno`) e um com papel de staff (`director` ou TI).
- Saber abrir as ferramentas de programador do browser (separador Network).

## Cenario 1 - Encarregado: portal mais leve (o ganho)

1. Entrar como `encarregado` e abrir a Pagina do Aluno (`?page=sige-app&view=aluno_portal`).
2. Abrir Network, filtrar por JS e CSS, recarregar a pagina.
3. **Esperado:**
   - NAO aparecem os scripts de media do WordPress (`media-views`, `media-editor`,
     `media-models`, `image-edit`, `mediaelement`, `plupload` e afins).
   - NAO aparecem `devedores.css`, `reconciliacao.css` nem `aprovacoes.css`.
   - Aparecem `style.css`, `sige-tokens.css`, `sige-ui.css`, `sige-shell-stability.css`.
   - No rodape do HTML, NAO ha os templates de media (`<script type="text/html" id="tmpl-...">` do uploader).
4. **Criterio:** a pagina renderiza visualmente identica a v12.15.12 (saldo, propinas,
   boletim, area de palavra-passe), so com menos pedidos de rede.

## Cenario 2 - Encarregado: identidade visual preservada

1. Comparar lado a lado a Pagina do Aluno em v12.15.12 e v12.15.13 (mesmo utilizador).
2. **Esperado:** zero diferencas visuais. Cores, tipografia, cartoes de KPI, hero,
   tabela de financas e o bloco de palavra-passe identicos.
3. **Atencao:** confirmar tambem no telemovel (largura ~390px) e no tablet.

## Cenario 3 - Aluno: mesmo comportamento

1. Repetir o Cenario 1 com um utilizador `aluno`.
2. **Esperado:** identico ao encarregado (bundle enxuto, render igual).

## Cenario 4 - Staff inspecciona o portal: shell completa

1. Entrar como `director` (ou TI) e abrir a Pagina do Aluno do mesmo aluno.
2. Abrir Network, recarregar.
3. **Esperado:** a shell COMPLETA carrega (media presente, CSS financeiros presentes).
   O staff nao e enxugado, porque pode navegar dali para modulos pesados.

## Cenario 5 - Reversao por flag

1. Definir a option: `update_option('sige_portal_lean_assets_v121513_enabled', '0');`
   (ou via interface de opcoes, se exposta).
2. Entrar como `encarregado`, abrir a Pagina do Aluno, recarregar Network.
3. **Esperado:** o comportamento anterior volta (media e CSS financeiros carregam).
4. Repor a option a `'1'` para reactivar o portal enxuto.

## Cenario 6 - Outras views intactas

1. Entrar como `secretario`/`director` e abrir Dashboard, Alunos, Financeiro-Pagamentos.
2. **Esperado:** nenhuma mudanca de assets em relacao a v12.15.12. O portal enxuto
   so afecta a view `aluno_portal` para papeis de portal.

## Gates automaticos (correr antes do deploy)

```
php tools/run-gates.php
```

Confirmar verdes, em particular:
- `Portal Enxuto Scope Guard (v12.15.13)`
- `Portal Enxuto decisao por papel (smoke v12.15.13)`
- `Financeiro Formula Integrity (v12.15.12)` (prova que o financeiro continua intacto)

Para correr so os dois gates novos, usar o intervalo apropriado de `--from`/`--to`.
