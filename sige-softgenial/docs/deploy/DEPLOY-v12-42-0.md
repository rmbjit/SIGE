# DEPLOY - SIGE SoftGenial v12.42.3

**view=equipe: correcção DEFINITIVA do "click mudo" (despachante inline autossuficiente)**
Data: 2026-07-01 - Tipo: correcção. Sem schema, sem permissões novas, sem protegidos.

> **v12.42.3 resolve a causa-raiz do "click mudo":** toda a interacção de
> view=equipe (todos os botões + o formulário) dependia de **um único ficheiro
> no rodapé** (`assets/sige-ui.js`) para ligar. Nesta view — a maior do sistema
> (>300 KB) e sobretudo logo após gravar (que recarrega a página) — havia uma
> janela em que os botões apareciam mas o rodapé ainda não tinha ligado; em
> ligação lenta podia até falhar. Agora a view é **autossuficiente**: um
> despachante **inline** liga os botões ao **primeiro clique**, sem esperar pelo
> rodapé e mesmo que este falhe. De-duplicação por evento evita duplo disparo.

## O que muda
- **Botões de view=equipe respondem ao primeiro clique** — deixa de haver o
  "click mudo" / congelamento até o script do rodapé chegar (incluindo logo após
  gravar). Vale para toda a gente que abre a página.
- Nada muda visualmente nem no comportamento das acções; só a **fiabilidade** do
  arranque da interacção.

> As correcções de v12.42.0–v12.42.2 (super admin/órfãos fora das sub-abas via
> lista canónica, e o anti-congelamento de modais) continuam incluídas.
- **Congelamento depois de gravar (corrigido).** O estado do "shell" (bloqueio
  de scroll + elevação do conteúdo enquanto há modal) passa a ter uma fonte de
  verdade única e a ser reconciliado ANTES do clique — sem "clique desperdiçado".

## Ficheiros alterados (v12.42.3)
```
sige-softgenial/admin/hr/equipe-view.php        (despachante INLINE autossuficiente)
sige-softgenial/assets/sige-ui.js               (de-dup por evento ev.__sigeAct)
sige-softgenial/sige-softgenial.php             (versão 12.42.3)
sige-softgenial/BUILD.json                       (versão + sumário)
sige-softgenial/tools/smoke-rh-anti-click-mudo-v12-42-3.php               (novo)
```
Inclui também (v12.42.0–v12.42.2):
```
sige-softgenial/includes/rh-assiduidade.php     (resolver canónico + grelha)
sige-softgenial/includes/rh-salarios.php        (preview + mapa usam lista canónica)
sige-softgenial/tools/smoke-rh-superadmin-anti-congelamento-v12-42-0.php  (novo)
sige-softgenial/tools/.consistencia-visual-baseline.json                  (reconciliação)
```

> ✅ **Sem ficheiros novos de runtime e sem tabela nova.** Não toca em ficheiros
> protegidos, fórmulas, schema nem permissões reais.

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima. Sem migração de dados. **Limpe a cache** (Ctrl+Shift+R) —
   há alteração de JS.

## Verificação rápida
- **Click mudo (o foco desta versão):** abrir **Equipa e Professores**, e — mesmo
  imediatamente após o carregamento e **logo após gravar** — clicar em qualquer
  botão (Novo colaborador, tabs, editar, remover, Salários→Calcular/Processar,
  etc.). Deve responder ao **primeiro clique**, sem precisar de esperar nem de
  refresh. Testar também numa ligação lenta / com cache limpa.
- **Sub-abas alinhadas (v12.42.2):** a lista de colaboradores em **Ausências**
  (dropdown), **Assiduidade** e **Salários** é **igual** à da aba **Equipa** — o
  super admin e fichas órfãs **não aparecem**.
- **Anti-congelamento**: abrir/fechar modais (Novo/Editar, Ficha, Ausência,
  Configurar impostos, confirmação de Processar), **gravar** e depois clicar em
  vários botões — a página responde **ao primeiro clique**, sem precisar de
  refresh nem de um clique "para desbloquear".

## Rollback
- Reponha os ficheiros anteriores a partir do backup. Sem efeitos colaterais.

## Fronteira
- Exclusão feita por email (chave do roster RH; `sige_professores` não tem
  `user_id`). Os admins WP são globais, por isso a exclusão é consistente em
  todas as escolas.
