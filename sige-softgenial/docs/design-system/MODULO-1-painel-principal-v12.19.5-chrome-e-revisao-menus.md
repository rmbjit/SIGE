# Módulo 1 - Correcção do chrome do painel + revisão crítica dos menus (v12.19.5)

Versão: 12.19.5
Data: 2026-06-29

---

## Parte 1 - Correcção do chrome (na camada certa)

No v12.19.4 o painel foi simplificado na view, mas o aspecto do herói e dos
cartões é governado pelo tema PRO em `admin-shell.php` (`<style
id="sige-produto-pro-compliance-v121048">`), com `!important` e alta
especificidade, que se sobrepõe ao `<style>` inline da view. Por isso o "herói
compacto" não se via.

Correcção: bloco scoped a `body.sige-admin-app.sige-view-dashboard` (só o
dashboard), inserido no fim do bloco PRO, que ganha por especificidade + ordem:

| Alvo | Antes (tema PRO genérico) | Depois (só dashboard) |
|---|---|---|
| `.sg-dash-hero` | `min-height:178px;padding:32px 34px;box-shadow:0 24px 70px;radius:24px` | `min-height:0;padding:var(--space-5/6);box-shadow:var(--shadow-sm);radius:var(--radius-lg)` |
| `.sg-dash-hero:before/:after` | brilhos decorativos | `display:none` |
| `.sg-hero-title` | `clamp(30px,2.45vw,42px);weight:850` | `var(--fs-xl);weight:700` |
| `.sg-dash-card` | `radius:20px;box-shadow:0 20px 55px` | `var(--radius-lg);var(--shadow-sm)` (igual aos KPIs) |

Escopo: **não afecta** os heróis partilhados de Financeiro/Alunos/Turmas/RH, que
continuam com o tema PRO. Sem lógica/SQL/regras tocadas. `php -l` OK; gate 1929.

---

## Parte 2 - Revisão crítica da arrumação dos menus (barra lateral)

Análise da navegação em `admin-shell.php` (rail operacional + `nav.sg-app-nav`).

### Correcção a uma suposição minha

Os 11 `--ml-color` (cores por grupo) **NÃO produzem um arco-íris visível**:
`style.css:2344-2345` força `color:rgba(255,255,255,.46)!important` nos rótulos e
esconde a barra colorida (`::before{display:none!important}`). Logo são **código
morto** (limpeza opcional), não um problema visual.

### Problemas reais de arquitectura de informação

| # | Problema | Evidência | Impacto (óptica do utilizador) |
|---|---|---|---|
| M1 | **Comunicações mal arrumadas** | WhatsApp, Central de Comunicações e Circulares estão dentro do grupo **TESOURARIA** (`admin-shell.php:2132-2135`) | Procura-se comunicação em Finanças. Mistura conceptual |
| M2 | **TESOURARIA sobrecarregada** | ~20 itens num só grupo: operação diária + relatórios + configuração de preços + comunicações (2118-2151) | Lista enorme, difícil de varrer |
| M3 | **Dupla navegação** | "Comece aqui" (rail operacional, 2034-2065) por cima do menu completo | Repete links; alonga a barra; mais ruído |
| M4 | **Grupos de um só item** | PORTARIA (1 item) e OPERAÇÃO ESCOLAR/Transporte (1 item) | Rótulo de grupo a mais para uma linha |
| M5 | **Grupos académicos sobrepostos** | ACADÉMICO (cadastro+estrutura+ano), DOCENTES (notas), GESTÃO ESCOLAR (auditoria+estatística) | Fronteiras pouco claras entre os três |
| M6 | **ACADÉMICO é um saco** | Mistura cadastro (Alunos, Turmas, Contas) com estrutura (Disciplinas, Matriz) e gestão de ano (Abertura/Encerramento) (2086-2099) | Sem hierarquia legível |
| M7 | **"Aproveitamento" duplicado** | Em ACADÉMICO (2093) e em DOCENTES (2165) | Mesmo destino, dois sítios |

### Recomendação (a confirmar antes de aplicar, por mexer em gating)

1. **Criar grupo "COMUNICAÇÃO"** e mover para lá WhatsApp, Comunicações e
   Circulares (sair de TESOURARIA). Alinha com a sequência de módulos do plano.
2. **Dividir TESOURARIA** em "Tesouraria" (diário: pagamentos, cobranças,
   reconciliação) e "Finanças — Configuração" (preços, planos, centros, lançar
   mensalidades), reduzindo a lista.
3. **Avaliar remover o rail "Comece aqui"** (M3), coerente com a decisão de tirar
   a camada de coaching do painel.
4. **Fundir grupos de um item** (Portaria + Transporte) sob "Operação Escolar".
5. **Clarificar os 3 grupos académicos** ou fundir GESTÃO ESCOLAR em ACADÉMICO.
6. Limpeza opcional: remover os 11 `--ml-color` mortos.

Estas mudanças são de apresentação/IA, mas tocam as condições de permissão que
mostram cada grupo. Por isso ficam para confirmação: não foram aplicadas neste
incremento.
