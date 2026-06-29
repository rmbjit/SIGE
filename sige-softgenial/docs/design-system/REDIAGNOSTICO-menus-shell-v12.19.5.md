# Rediagnóstico crítico da navegação (barra lateral) - admin-shell.php

Versão de referência: 12.19.5
Data: 2026-06-29
Âmbito: navegação do shell administrativo (`includes/admin-shell.php`).
Estado: diagnóstico. Nenhuma alteração aplicada. Substitui a Parte 2 de
`MODULO-1-painel-principal-v12.19.5-chrome-e-revisao-menus.md`.

---

## 1. Método (desta vez a partir da lógica, não só da marcação)

Lido o gating real, não apenas o HTML:

- Feature flags por escola: `$ff_cadastro/academico/financeiro/jardim/rh/transporte/portaria`
  (`admin-shell.php:1768-1776`, via `sige_feature()`).
- Papéis: `$is_director/financeiro/...`, `$is_core_tech`, `$sige_scoped_professor`
  (`1728-1740`, `1796`).
- Permissões: `$sige_menu_can_any([...])` (`1791-1794`), super-admin real faz bypass
  (`1786-1789`).
- Mapa de rota: `$sige_view_permission_map` (`1799-1828`) e o guarda de rota
  (`1905-1908`) que bloqueia a view se faltar permissão.

---

## 2. Correcções a diagnósticos anteriores (honestidade)

| Afirmação anterior | Veredicto após ler o gating |
|---|---|
| "Alunos/Turmas/Contas duplicados em vários grupos" | **Impreciso.** SECRETARIA (`!$ff_academico`, `2078`), ACADÉMICO (`$ff_academico`, `2086`) e CONSULTA (sem perms académicas, `2105`) são mutuamente exclusivos. O utilizador vê uma vez. É duplicação de **código-fonte**, não de ecrã |
| "Arco-íris de cores nos grupos" | Código morto (já neutralizado por `style.css:2344-2345`) |

---

## 3. Achados reais (com prova e gravidade)

| # | Gravidade | Achado | Prova |
|---|---|---|---|
| M1 | Alto (bug) | Comunicações (WhatsApp, Central de Comunicações) renderizadas dentro de TESOURARIA **sem verificação de permissão própria**; surgem por permissão financeira | `2132-2133` sem `if`, dentro de `if(can_any(financeiro.*))` em `2120` |
| M1b | Alto (bug) | Menu e guarda de rota discordam: rota exige `comunicacao.whatsapp_ver`/`comunicacao.central_ver` (`1818-1819`), menu mostra por permissão financeira. Financeiro sem comunicação vê o link e é bloqueado ao clicar; comunicação sem financeiro não vê o link | `1818-1819` vs `2132-2133` |
| M2 | Médio | "Aproveitamento" aparece duas vezes no ecrã para director/pedagógico (ACADÉMICO e DOCENTES coexistem) | `2093` e `2165` (ambos `academico.boletins_ver`) |
| M3 | Médio | TESOURARIA sobrecarregada (~20 itens: diário + relatórios + configuração + comunicações) | `2119-2151` |
| M4 | Médio | Director vê ACADÉMICO + DOCENTES + GESTÃO ESCOLAR ao mesmo tempo: navegação longa, fronteiras difusas | `2086`, `2153`, `2173` (não exclusivos) |
| M5 | Baixo | Dupla navegação: rail "Comece aqui" por cima do menu repete links | `2020-2065` |
| M6 | Baixo | Grupos de um só item (Portaria, Transporte) | `2101`, `2181` |
| M7 | Baixo | CONSULTA ignora `$ff_academico` (SECRETARIA verifica) | `2105` vs `2078` |

---

## 4. Reclassificação do problema

O ponto crítico deixou de ser estética: **M1/M1b é um defeito de acesso**. A
navegação de Comunicações não respeita as permissões de comunicação e contradiz o
guarda de rota. Isto **toca gating** (logica), por isso sai do âmbito "só
apresentação" e exige confirmação explícita e teste por perfil.

---

## 5. Plano revisto (valor vs risco)

| Prioridade | Acção | Natureza | Risco |
|---|---|---|---|
| 1 | Grupo COMUNICAÇÃO com gating por `comunicacao.*` (corrige M1/M1b) | Apresentação + gating | Médio (permissões) |
| 2 | Remover "Aproveitamento" repetido (M2) | Apresentação | Baixo |
| 3 | Remover rail "Comece aqui" (M5) | Apresentação | Baixo |
| 4 | Dividir TESOURARIA (M3) e fundir grupos de 1 item (M6) | Apresentação | Baixo |
| 5 | Clarificar grupos académicos (M4) | Apresentação/IA | Médio |

## 6. Questão aberta (antes de mexer na prioridade 1)

Confirmar a regra de visibilidade de Comunicações: mostrar a quem tiver qualquer
`comunicacao.*` (whatsapp_ver, central_ver, circulares_enviar), independentemente
de ter permissões financeiras. Confirmar também se deve permanecer acessível a
quem só tem finanças (provavelmente não).
