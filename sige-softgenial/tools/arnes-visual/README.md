# Arnes de Verificacao Visual - SIGE SoftGenial

Ferramenta para remover `!important` (e validar qualquer mudanca de CSS) com
**prova visual** em vez de aposta. Fotografa cada modulo num browser real
autenticado, aplica a mudanca, fotografa de novo e compara pixel a pixel. So
mantem a mudanca se o render ficar identico.

Vive em `tools/arnes-visual/` e e independente do plugin: nao corre em producao
nem faz parte de `run-gates.php`. Corre no SEU ambiente.

## Porque existe

O sistema tem cerca de 14 mil `!important`, na maioria "cobertores defensivos"
para vencer o tema do wp-admin. Removê-los por analise estatica e arriscado
porque dependem de estilos do wp-admin que so existem com o WordPress a correr.
Este arnes da a unica garantia honesta: o pixel antes e depois.

## Requisitos

- Node.js 18+ (testado em 22).
- PHP CLI (para o codemod `strip-important.php`).
- Um site de STAGING com o SIGE instalado (ex.: demo.softgenial.edu.mz).
  NUNCA correr `verify-strip` em producao: ele altera ficheiros do plugin no
  servidor (com backup, mas e uma alteracao em disco).
- Um utilizador wp-admin com acesso ao SIGE nesse site.

## Instalacao

```bash
cd tools/arnes-visual
npm install
npx playwright install chromium     # descarrega o browser headless
cp config.example.json arnes.config.json
```

Edite `arnes.config.json`:
- `baseUrl`: o site de staging.
- `pluginRoot`: caminho absoluto da pasta do plugin no servidor (deixe vazio
  para usar dois niveis acima de tools/arnes-visual, que e o normal).
- `modules`: ja vem com os 14 modulos principais; ajuste se precisar.
- `viewports`, `thresholdRatio`, `maskSelectors`: ver "Afinacao".

Credenciais por variaveis de ambiente (nunca no ficheiro):

```bash
export SIGE_ADMIN_USER="o-seu-utilizador"
export SIGE_ADMIN_PASS="a-sua-password"
```

## Uso

### Verificar a remocao de !important de um modulo (o comando principal)

```bash
node arnes.js verify-strip equipe
# ou limitando o ambito a um escopo de selector:
node arnes.js verify-strip equipe --scope=".sige-rh"
```

Faz tudo: captura ANTES, remove `!important` do ficheiro do modulo (preservando
blocos de impressao), captura DEPOIS, compara. Se VERDE (render identico), a
remocao fica e o backup `.important-bak` e guardado ao lado do ficheiro. Se
VERMELHO, reverte automaticamente e deixa as imagens de diferenca para inspeccao.

### Fluxo manual (mais controlo)

```bash
node arnes.js capture antes
php strip-important.php ../../admin/hr/equipe-view.php --apply --scope=".sige-rh"
node arnes.js capture depois
node arnes.js diff antes depois     # exit 0 se tudo igual; 1 se houver diferenca
# se mau:  php strip-important.php ../../admin/hr/equipe-view.php --restore
```

### Outros

```bash
node arnes.js capture antes dashboard   # so um modulo
node arnes.js list                      # modulos e viewports configurados
```

## Modulos: admin, login e portal

Cada modulo no config aponta para uma de duas coisas:
- `view`: uma vista do admin-app (`?page=sige-app&view=...`), autenticada no
  wp-admin. E o caso normal (`auth` omisso = `admin`).
- `url` + `auth: "none"`: uma saida FORA do admin, capturada num contexto
  anonimo (sem login). Serve os contextos sem tokens:
  - `login` -> `/wp-login.php?loggedout=true` (espera `#login`).
  - `portal_aluno` -> a pagina front-end com o shortcode do portal (espera
    `.sp-wrap`). Ajuste o `url` ao slug real da sua pagina de portal.

Estes dois entraram no arnes por causa da regressao do logotipo: sao contextos
que nao carregam `sige-tokens.css`, por isso qualquer token ali e um erro (o gate
`check-tokens-fora-de-contexto.php` ja o impede), e o arnes fotografa-os para
apanhar visualmente qualquer outra regressao. Pode usar `verify-strip` neles
tambem (ex.: `verify-strip login`).

## Como interpretar o diff

Para cada `modulo [viewport]` mostra pixels diferentes, racio e estado:
- `OK`: racio abaixo do limiar (`thresholdRatio`). Mudanca segura.
- `DIFERENCA`: ha pixels alterados. Veja `screens/diff__antes__depois/` (a
  diferenca aparece a vermelho sobre a imagem).
- `TAMANHO MUDOU`: a altura/largura da pagina mudou (quase sempre regressao de
  layout). Tratar como falha.

## Afinacao

- `thresholdRatio` (omissao 0.0008): fraccao de pixels que pode diferir e ainda
  contar como OK. Suba um pouco se houver ruido de antialiasing; desca para ser
  mais exigente.
- `maskSelectors`: regioes voláteis (relogio, contadores ao vivo) que devem ser
  mascaradas para nao darem falsos positivos. Acrescente os seletores do seu
  painel se notar diffs em numeros que mudam.
- `settleMs`: tempo de espera apos carregar, para conteudo assincrono assentar.
- O arnes ja congela animacoes e transicoes antes de fotografar, por isso o
  fadeInUp dos cartoes nao causa diferencas.
- `afterStripCmd`: comando opcional a correr apos o strip (ex.: limpar opcache
  com `php -r "opcache_reset();"` via um endpoint, ou tocar num ficheiro). Em
  muitos servidores o PHP-FPM tem opcache: se as mudancas nao aparecerem no
  DEPOIS, e opcache; configure aqui a limpeza ou reinicie o pool.

## Notas de seguranca

- Corra sempre em staging. O `verify-strip` escreve no ficheiro do plugin.
- O backup `.important-bak` permite reverter a qualquer momento:
  `php strip-important.php <ficheiro> --restore`.
- `screens/`, `node_modules/`, `arnes.config.json` e `*.important-bak` estao no
  `.gitignore` e nao entram no repositorio.
- Quando um modulo passar a VERDE e voce confirmar, traga a alteracao validada
  para o pacote do plugin e baixe a baseline com
  `php tools/check-consistencia-visual.php --set`.
```
