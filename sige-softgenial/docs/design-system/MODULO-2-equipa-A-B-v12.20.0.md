# Módulo 2 - Equipa e Professores (RH) - Portões A/B

Versão: 12.20.0
Data: 2026-06-29
Ficheiro: `admin/hr/equipe-view.php` (3739 linhas)
Modo: A é só leitura. B é plano. Nada implementado.

## Portão A - Inventário

| Dimensão | Estado |
|---|---|
| Tokens | Muito tokenizado (560 `var(--token)`) |
| CSP | **0 `onclick`** (usa `data-sige-act`/`data-sige-json`) |
| KPIs | Total Colaboradores, Docentes, Folha Salarial (variante "Activos/Inactivos" sem exposição salarial para quem não pode), Efectivos/Contratos |
| Hero | Grande e decorativo, com ilustração esbatida (`.sg-hero-school` opacity .72) e subtítulo longo; CSS próprio em `.sige-rh .sg-dash-hero` (1451+) |
| "salario" | Apenas **identificadores** (campo DB `salario_base`, id de input, `data-type`). O rótulo visível já é "Salário Base (MT)" (acentuado). Sem correcção |
| Hex cravado | Só nos **crachás imprimíveis** (`window.open`, 2 templates): documentos autónomos sem contexto de tokens. **Fora de âmbito** (como acta/pauta/PDF) |

Conclusão: o ecrã está em bom estado. A única dívida de apresentação alinhada
com o que já fizemos é o **herói** (igual ao do Painel antes da v12.19.5).

## Portão B - Plano (só apresentação)

Aplicar ao herói da Equipa a mesma compactação aprovada para o Painel Principal,
editando o CSS próprio do ecrã (`.sige-rh ...`) e o HTML do herói:

| # | Antes | Depois |
|---|---|---|
| 1 | Herói 2 colunas com `.sg-hero-art` (ilustração de escola esbatida) | Uma coluna, **sem ilustração** (remover o bloco `.sg-hero-art` no HTML) |
| 2 | `min-height:178px`, `padding:var(--space-8)`, brilho `:before` | Compacto: `min-height:0`, padding menor, sem brilho decorativo |
| 3 | Título grande + subtítulo longo | Título contido (`--fs-xl`) + subtítulo curto de uma linha |

Mantém-se tudo o resto (KPIs, toolbar, tabela, modais, crachás).

### Fronteira (Portão C, a confirmar)
Sem lógica/SQL/regras/`name`/`id`/permissões/CSP. Só o `<style>` scoped do ecrã e
o HTML do herói. Crachás imprimíveis intactos.

### Risco
Baixo: o CSS é scoped a `body.sige-view-equipe`/`.sige-rh`; não afecta outros ecrãs.

## Decisão
Aprova compactar o herói da Equipa (alinhado ao Painel)? Mais alguma área que
queira que eu reveja em detalhe (modal de colaborador, tabela)?
