# LIVE-TEST Navegação - limpeza M2 + M5 (v12.19.8)

Só apresentação. Validar em staging.

## Pré-condições

- Versão 12.19.8. Ctrl+F5.

## M2 - Aproveitamento (sem duplicar)

| Perfil | Esperado |
|---|---|
| Director / Dir. Pedagógico (vê ACADÉMICO e DOCENTES) | "Aproveitamento" aparece **uma só vez** (no grupo DOCENTES), não duas |
| Quem tem `academico.boletins_ver` | Continua a ver "Aproveitamento" (não perdeu o acesso) |
| Professor com boletins_ver | Vê "Aproveitamento" no grupo DOCENTES |

## M5 - Rail "Comece aqui"

| Verificar | Esperado |
|---|---|
| Topo da barra lateral | O bloco escuro "Comece aqui" deixou de aparecer |
| Restante navegação | Todos os grupos e links continuam presentes e funcionais |

## Anti-regressão

- Nenhum link de navegação desaparece (além do "Aproveitamento" repetido e do rail).
- Abrir "Aproveitamento" continua a funcionar.
- Sem erros de consola; sem violações CSP.
- Restantes grupos (Comunicação, Tesouraria, etc.) inalterados.

## Clientes

Validar em pelo menos dois (ex.: teste e cicasacolorida), idealmente com um perfil
de gestão/pedagógico que via "Aproveitamento" duas vezes.
