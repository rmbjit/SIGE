# Portal Chrome vs Views: split do design system (v12.15.14)

## Problema (o ganho grande que sobrava)

A Pagina do Aluno servia o `style.css` completo (~302 KB) ao encarregado/aluno,
mas ~64% desse ficheiro sao modulos de view que o portal nunca renderiza: o
bloco Financeiro MJS (~170 KB), Pagamentos por Turma (~22 KB) e Extractos PRO
(~6 KB). A Fase 1 ja tinha evitado o uploader de media e o CSS financeiro de
staff; este era o peso restante.

## Medicao

| Parte | Bytes | % |
|---|---|---|
| `style.css` completo | 308 954 | 100% |
| Modulos de view (Financeiro, Pagamentos por Turma, Extractos) | 197 092 | 64% |
| Chrome/core que o portal precisa | 111 862 | 36% |

## Solucao: extraccao nao-destrutiva

`assets/style-portal-chrome.css` e uma copia **byte-a-byte** das seccoes de
chrome do `style.css`, omitindo os modulos de view. Nenhuma regra foi reescrita:
onde estas regras se aplicam, aplicam-se de forma identica ao ficheiro completo.

O `style.css` partilhado pelos 36 ecras NAO foi alterado. As views financeiras,
abertas por staff, continuam a carregar o `style.css` completo. So o portal
enxuto usa a folha de chrome.

### Fronteira (seccoes do style.css)

| Estado | Seccoes |
|---|---|
| MANTIDO (chrome) | 1. DESIGN TOKENS ate 10. PRINT STYLES, 11. APP SHELL, Visual Foundation, App Shell V2, App Shell Scroll Inteligente, Topbar acompanha conteudo, Failsafe global |
| OMITIDO (views) | Financeiro MJS-grade, Central de Cobrancas, Relatorio Mensal, Despesas, Auditoria, Centros de Custo, Lancar Mensalidades, Planos, Precos, Inscricoes, Pagamentos por Turma, Extractos PRO |

## Porque e seguro (prova estrutural)

| Verificacao | Resultado |
|---|---|
| Classes do portal definidas nos blocos omitidos | 0 de 101 (namespaces distintos: portal usa `.ap-*`/`.sg-app-*`/`.sgk-*`) |
| Regras dos blocos omitidos escopadas a view ou ao namespace `.sg-finpro-*` | Sim (226 regras escopadas a `body.sige-view-*`) |
| Selectores de elemento bare nos blocos omitidos (risco de bleed) | 0 |
| `body.sige-view-financeiro` na folha de chrome | 0 |

Conclusao estrutural: nenhuma regra que hoje se aplica ao portal foi removida.
A prova final de identidade pixel-a-pixel exige browser (ver LIVE-TEST).

### Inclusao inofensiva conhecida

Uma regra de reset `.sg-finpro-kicker:before` vive dentro da seccao do topbar
(chrome), nao no bloco financeiro. Foi mantida na copia fiel. E um no-op no
portal, que nao tem nenhum elemento `.sg-finpro-kicker`. Remove-la implicaria
reescrever uma regra copiada, o que se evita.

## Ligacao (flag desligada por defeito)

```
sige_portal_chrome_css_v121514_enabled = 0   (defeito: DESLIGADA)
```

Com a flag desligada, o portal carrega o `style.css` completo, exactamente como
na v12.15.13. So quando a flag = 1 (e o contexto e portal enxuto) e que a shell
serve a folha de chrome, sob o mesmo handle `sige-design-system`.

Fechar a Fase 2 = ligar a flag por defeito, apos a prova visual em browser.

## Regeneracao (eliminar o risco de drift)

A folha de chrome e estatica. Se o chrome do `style.css` mudar, e preciso
reextrair, senao a folha fica desactualizada. Metodo de reextraccao:

1. Localizar os cabecalhos de seccao que marcam o inicio de cada modulo de view
   omitido: "Financeiro MJS-grade", "Pagamentos por Turma", "Extractos".
2. Copiar do `style.css` tudo EXCEPTO os intervalos desses tres modulos (cada um
   vai do seu cabecalho ate ao cabecalho da seccao de chrome seguinte).
3. Reanexar o cabecalho de proveniencia no topo.
4. Correr `php tools/run-gates.php` e confirmar verde o gate
   `Portal Chrome vs Views split (v12.15.14)`.

Productionizacao recomendada (proxima vaga): inserir marcadores de comentario
`CHROME`/`VIEW` no `style.css` e gerar a folha por script, para a reextraccao
deixar de depender de intervalos de linha.
