# Protocolo de trabalho - Modernização visual do SIGE

Documento auto-imposto pelo assistente, escrito após erros reais cometidos nos
incrementos do Painel Principal. Tem precedência sobre o impulso de "entregar
depressa". Lê-se antes de cada módulo.

Frase que rege tudo: **sempre progresso, nunca regressão. Verificar, não assumir.**

---

## 0. Erros desta sessão que este protocolo existe para impedir

| Erro cometido | Lição |
|---|---|
| Reescrevi o CSS do dashboard só no `<style>` inline, ignorando que o aspecto vinha de `admin-shell.php` (tema PRO, `!important`) e de `style.css`. O "herói compacto" não se via | O estilo de um ecrã pode viver em **várias camadas**. Mapear TODAS antes de prometer um efeito visual |
| Afirmei "Alunos duplicado" sem ler o gating; eram ramos mutuamente exclusivos | **Ler a lógica de gating** antes de afirmar duplicação. Distinguir duplicação de código de duplicação de ecrã |
| Ia "limpar" um arco-íris de cores que já estava neutralizado por outra folha | **Verificar o estado renderizado real** antes de propor correcção |
| Corrigi "permissoes" na view, mas o texto vinha de `institutional-product-map.php` | Seguir o texto **até à fonte**, não ao primeiro sítio onde aparece |
| Tratei navegação de Comunicações como "só apresentação"; afinal toca permissões | Se toca gating/permissões, **é lógica**: confirmar e testar, não assumir |
| Revi o ecrã Equipa e disse "os modais funcionam" sem ver que o modal saía **tapado pela barra lateral**. Causa: `.sg-app-content` é um contexto de empilhamento (`position:relative;z-index:1`) abaixo da sidebar; um modal filho do conteúdo nunca sobe acima dela, por maior que seja o seu `z-index` | "Funciona" inclui **aparecer inteiro e por cima da moldura** (sidebar/topbar). Validar sempre overlays/modais/dropdowns contra o **contexto de empilhamento dos pais**, não só contra o `z-index` próprio. Quando há captura de ecrã, **olhar mesmo para ela** |
| Corrigi o modal para aparecer por cima, mas ele **não rolava** e cortava os campos de baixo (corpo com `overflow-y:auto` sem altura limitada; rodapé `position:sticky` sem contentor a rolar) | Corrigir a **aparição** não chega: validar o **funcionamento completo** (rolar até ao fim, rodapé sempre visível, submeter, fechar, teclado/Enter, foco). Um modal precisa de **coluna flex** (cabeçalho/separadores fixos, corpo `flex:1;min-height:0;overflow:auto`, rodapé fixo); `overflow:auto` sem altura limitada **não rola** |

---

## 1. Âmbito (inalterável)

Só camada de apresentação e arquitectura de informação. Em dúvida entre
"apresentação" e "lógica", é lógica e não se toca sem confirmação explícita.
Nunca: fórmulas financeiras/académicas, SQL, `name`/`id`, hooks, schema,
multi-tenant, CSP. Gating de permissões conta como lógica.

---

## 2. Antes de tocar (deep read obrigatório por módulo)

Não basta ler o ficheiro da view. Mapear:

1. **Todas as camadas de CSS** que estilizam as classes do ecrã: `<style>` inline
   da view, tema PRO em `admin-shell.php`, `style.css`, folhas por view, mobile.
   Para cada classe-alvo, saber **qual camada ganha** (especificidade + `!important`
   + ordem).
2. **JS** que depende de elementos/atributos do ecrã (procurar a classe/atributo
   em `assets/*.js` e includes antes de remover).
3. **Gating**: feature flags, papéis, `can_any`, e o **mapa de rota** correspondente.
   O menu tem de concordar com o guarda de rota.
4. **Fonte real dos textos** dinâmicos (seguir variáveis até à origem).
5. **O gate de tokens** e a baseline (`tools/check-design-tokens.php`).

Saída: inventário que diz, por alteração pretendida, em que camada se faz e o que
mais lhe toca.

---

## 3. Postura crítica (óptica do utilizador real)

Utilizador: gestor/secretaria/tesouraria de escola em Moçambique, telemóvel
incluído. Perguntar a cada bloco: isto ajuda a decidir/agir agora, ou é ruído?
Preferir simples a "inteligente". Remover o que é decorativo, fictício
(ex.: gráficos com dados falsos) ou redundante. Menos blocos, melhor hierarquia.

---

## 4. Plano (Portão B) honesto

Tabela antes -> depois com a **camada** onde cada mudança é feita e o **raio de
impacto real** (não só a view). Listar o que fica de fora e porquê. Marcar o que
toca gating como "precisa de confirmação".

---

## 5. Implementação profissional

- Corrigir na **camada autoritária**, não na mais fácil. Se o tema PRO manda,
  é lá que se mexe, com escopo restrito (ex.: `body.sige-view-<modulo>`) para não
  afectar outros ecrãs que partilham classes.
- Preservar byte a byte o que não é o alvo (ex.: bloco de dados/consultas de uma
  view ao reescrever a apresentação; confirmar com diff).
- Sem `!important` desnecessário; sem inline novo; respeitar a CSP.
- CSS por `var(--token)`; nunca hex literal novo (o gate tem de descer ou manter).

---

## 6. Auto-revisão adversarial (antes de entregar)

Assumir que há erros. Reler o que se entregou e procurar activamente:
- Conflitos de especificidade entre camadas.
- Layout partido quando a estrutura muda (ex.: remover um filho de um grid).
- **Empilhamento (z-index/stacking):** abrir cada modal, overlay, dropdown e
  tooltip e confirmar que aparecem **inteiros e acima da sidebar/topbar**. Um
  `z-index` alto não escapa de um pai que é contexto de empilhamento
  (`position`+`z-index`, `transform`, `filter`, `opacity<1`). Padrões já usados na
  base de código: portar o nó para `document.body` (ex.: inscrições, disciplinas)
  ou elevar o conteúdo enquanto o modal está aberto (Equipa). Se a estilização do
  modal depende de um ancestral (ex.: `.sige-rh .field-group`), **portar partiria
  o estilo** - nesse caso elevar o conteúdo é mais seguro.
- Menu que mostra links que a rota bloqueia (ou vice-versa).
- **Funcionamento completo de cada componente interactivo**, não só a aparência:
  um modal abre, **rola até ao último campo**, mostra o rodapé, submete, valida,
  fecha (X, Cancelar, ESC, clique fora), responde ao teclado/Enter e gere o foco.
  Testar com conteúdo alto (ecrã baixo / muitos campos) para forçar o scroll.
- Estados restritos (perfis com poucas permissões) e ecrãs vazios.
- Mobile/tablet, não só desktop.

Se um efeito prometido não se confirma na camada certa, dizê-lo antes de o
utilizador descobrir.

---

## 7. Anti-regressão (Portão E)

`php -l` aos ficheiros tocados. Gate de tokens sem subir. Diff mínimo. Fins de
linha preservados (LF nesta baseline). Nenhum número renderizado muda.
Concordância menu <-> guarda de rota. Comportamento por perfil verificado.

---

## 8. Entrega (Portões F/G)

Versão sincronizada nas 3 fontes reais (cabeçalho, `SIGE_VERSION`, `BUILD.json`;
não há 4ª). `DEPLOY-*` em bullets para File Manager. `LIVE-TEST-*` com cenários
concretos e secção "o que NÃO deve mudar". Changelog. Mini-ZIP só dos ficheiros
alterados, com a árvore `sige-softgenial/...`.

---

## 9. Relação com o utilizador

Agir com juízo; não micro-gerir nem inundar de perguntas. Perguntar só quando a
decisão é genuinamente do utilizador: identidade de marca, regras de gating,
remoção de função que ele possa querer. Recomendar sempre o caminho mais
profissional, mesmo que dê mais trabalho. Reportar falhas com franqueza.

---

## 10. Recomendação de caminho (próximos módulos)

1. Fechar o Painel Principal: validar 12.19.5 em staging.
2. Navegação (este shell): tratar M1/M1b (Comunicações/gating) como correcção de
   acesso, com confirmação e teste por perfil, antes da arrumação estética.
3. Seguir a sequência por módulo (RH, Tesouraria, Académico, Portaria,
   Configurações) sempre com este protocolo, um módulo de cada vez, raio de
   impacto contido e validação isolada.
