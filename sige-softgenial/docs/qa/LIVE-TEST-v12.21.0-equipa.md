# LIVE-TEST Equipa e Professores (v12.21.0)

Mudança só de apresentação no ecrã Equipa. Foco: herói compacto, nada de
regressão funcional no modal e na tabela.

## Pré-condições

- Versão 12.21.0. Ctrl+F5. Perfil com gestão de equipa (director/gestor RH) e,
  em separado, um perfil sem gestão (ver KPI sem salário).

## Apresentação (herói)

| Verificação | Esperado |
|---|---|
| Herói | Faixa única e compacta, sem a ilustração de escola esbatida |
| Título | Uma linha, dimensão contida (estilo Painel Principal) |
| Subtítulo | Uma linha curta |
| Acções | Novo colaborador, Crachás, Folha salarial (e Arquivo removidos se houver) |
| Resto da página | Cantos e sombras ligeiramente alinhados ao sistema; sem saltos de layout |

## Funcional (sem regressão)

| Fluxo | Esperado |
|---|---|
| Tabela | Lista os colaboradores com cargo, validade ID, status e acções |
| Filtros + pesquisa | Filtram as linhas correctamente |
| Novo colaborador | Abre o modal nos 4 separadores |
| **Guardar** | Botão Guardar grava (valida obrigatórios, mostra toast, recarrega). **Testar Enter no campo também** |
| Editar | Carrega a ficha segura e preenche o modal |
| Activar/Desactivar, Remover, Reset senha | Pedem confirmação e executam |
| Crachá individual e em lote | Abrem a janela de impressão (documento próprio, inalterado) |
| Folha salarial | Exporta (só para quem pode) |

## Anti-regressão

- Sem erros de consola; sem violações CSP (o form usa data-sige-on-submit).
- KPI sem exposição salarial para quem não gere equipa (mostra Activos/Inactivos).
- Crachás imprimíveis com a mesma aparência de antes.

## Clientes

- Validar em pelo menos um cliente real, cobrindo o fluxo de gravar e editar.
