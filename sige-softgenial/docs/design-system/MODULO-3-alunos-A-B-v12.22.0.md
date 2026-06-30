# Módulo 3 - Alunos e Matrículas (view=alunos_lista) - Portões A/B

Versão alvo: 12.22.0
Data: 2026-06-30
Ficheiro: `admin/academic/alunos_lista.php` (9395 linhas)
Modo: A é só leitura. B é plano. Nada implementado.

## Contratos protegidos (NÃO tocar)

| Item | Onde |
|---|---|
| SELECT da lista / export / payload do cartão | `includes/alunos-performance-contract.php` |
| AJAX de ficha do aluno | `includes/aluno-fetch-ajax.php` |
| Hotfix do cabeçalho mobile (v12.17.1) | regras `max-width:760px` na view |
| Hashes protegidos (gate baseline) | `alunos_lista.php` **não** está protegido; `admin-shell.php` **está** (não tocar) |
| Camada de dados na view | consultas/`$wpdb`, `name`/`id`, permissões (`sigeAlunosCan*`, modo consulta) |
| Cartões/folhas imprimíveis (`window.open`) | templates às linhas 8530+/8599+/8656+/8735+ - pele própria, fora de âmbito |

## Portão A - auditoria contra os erros já corrigidos

| Erro corrigido antes | Aplica-se aqui? | Evidência |
|---|---|---|
| Modal tapado pela barra lateral (empilhamento) | **SIM (3 modais + popup)** | `.sg-app-content` é contexto (z-index:1) < sidebar (10070). `#modal-aluno` (z 100000), `#modal-aluno-360` (135000), `#modal-import` (130000) e `#sige-alunos-popup` (120000) estão dentro do conteúdo e ninguém eleva o conteúdo. As classes `sige-aluno-modal-open`/`sige-modal-open` só põem `overflow:hidden` |
| Modal sem scroll | **NÃO** | `.sige-modal-content` já é coluna flex; `.sige-modal-body{flex:1;min-height:0;overflow-y:auto}`. Correcto |
| `:root` a sombrear tokens globais | **NÃO** | Usa namespace `--sige-shadow-*`/`--sige-radius-*`/`--sige-slate-*`; não redefine `--shadow-*`/`--radius-*` globais |
| Handlers inline `on*=` (CSP) | **SIM (3)** | linha 5976 `onkeyup="filtrarAlunosClient()"`; 5979 e 5986 `onchange="...form-filtros...submit()"`. Funcionam via camada CSP, mas são inline na origem |
| Herói grande/decorativo | **SIM** | `.sige-hero` com brilho `::before/::after`, `.sige-hero-art` (ilustração de escola: telhado/bandeira/árvore/nuvem). Faixa escura grande (navy) |
| Selo BETA com raio mágico | N/A | não se acrescenta |
| Agrupar sem ler função | N/A (é uma view, não o menu) | - |

Conclusão: três dívidas reais (empilhamento dos modais, herói decorativo,
3 handlers inline). A mais importante e crítica é o **empilhamento** - é
exactamente o erro que o utilizador exige que não se repita.

## Portão B - plano (só apresentação)

| # | Acção | Camada | Risco |
|---|---|---|---|
| 1 | **Modais acima da barra lateral**: elevar o conteúdo enquanto há modal/popup aberto. Mesma técnica aprovada na Equipa, cobrindo as duas classes de estado | `<style>` da view (regra nova) | Baixo |
| 2 | **3 handlers inline -> `data-sige-on-*`** (`onkeyup`/`onchange`), CSP-limpos na origem | HTML da view | Baixo |
| 3 | **Compactar o herói**: remover a ilustração (`sige-hero-art`) e o brilho, reduzir padding/título, subtítulo de uma linha | `<style>` + HTML do herói | Médio (há vários blocos com `!important` a afinar o herói; é preciso limpá-los juntos) |

### Decisão pendente (do utilizador): pele do herói
O herói de Alunos é **escuro (navy)**, diferente do Painel/Equipa (faixa clara).
Opção A: compactar mantendo o escuro (preserva a identidade do módulo).
Opção B: alinhar ao claro do Painel/Equipa (consistência total).

### Fronteira (Portão C)
Sem lógica, SQL, `$wpdb`, `name`/`id`, permissões, AJAX, schema, nem contratos de
desempenho/mobile. Cartões imprimíveis intactos. Só `<style>` e HTML de
apresentação. Gate de tokens não sobe.

### Risco global
Baixo nos itens 1-2; médio no 3 por causa das camadas de afinação do herói.

## Portões C/D - implementado (v12.22.0)

CORRECÇÃO DE PRESSUPOSTO (verificar, não assumir): o herói **não** era escuro.
A definição base `.sige-hero` (linha 910) é navy, mas está **sobreposta** por
`.sige-alunos-page .sige-hero` (linha 2466, `!important`) que pinta uma **faixa
clara** (branco -> brand-100) com texto escuro. Logo, o herói já renderizava
claro (como o Painel/Equipa). A decisão escuro/claro ficou sem efeito.

Implementado:
1. Empilhamento: `body.sige-view-alunos_lista.{sige-aluno-modal-open|sige-modal-open}
   .sg-app-content{z-index:10090}`. Cobre os 3 modais e o popup.
2. CSP: `onkeyup`/`onchange` -> `data-sige-on-keyup`/`data-sige-on-change`.
3. Herói: removida a ilustração (HTML + CSS base) e o brilho `:before`; sem
   `min-height`; `box-shadow` md; padding `var(--space-6)/var(--space-8)`;
   `.sige-hero-content` de grelha 2-col para `block` (evita coluna vazia).

Revisão adversarial: apanhada e corrigida a grelha 2-col do `.sige-hero-content`
(deixaria coluna vazia sem a arte). Os `!important` responsivos da ilustração
ficam inertes (apontam para elementos removidos); não foram apagados um a um para
manter o diff seguro num ficheiro de 9395 linhas.

Validação: `php -l` OK; gate 1911 (baixou; baseline reposta); 0 handlers inline.
