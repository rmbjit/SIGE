# DEPLOY - SIGE SoftGenial v12.37.0

**RH (passo 4): imprimir/exportar (PDF) a Ficha do Colaborador**
Data: 2026-06-30 - Tipo: nova funcionalidade. Só leitura. Sem schema, sem novo endpoint.

## O que muda
- `admin/hr/equipe-view.php` —
  * botão **"Imprimir / Exportar (PDF)"** no rodapé do modal da Ficha;
  * gera um **documento autónomo A4** (cabeçalho com escola + título, identidade
    com foto/estado/vínculo, banner do estado do contrato, secções
    Identificação & contacto / Vínculo & carreira / Remuneração / Documentos, e
    rodapé com data de geração + "Confidencial — uso interno");
  * **Exportar = imprimir para PDF** pelo diálogo do navegador.
  * Engenharia para não subir a baseline de tokens: o documento lê os **valores**
    dos tokens já computados na página (`getComputedStyle`) e injecta-os no
    `:root` do documento; o CSS usa só `var(--token)` (cores **e** raios) — zero
    literais no código-fonte. Respeita o **tema/cor da escola**.
  * Reutiliza `sgRhPrintWindow` (espera imagens + imprime) e `sgRhLiveNonce`
    (nonce de CSP). Nome da escola exposto em `sigeEquipeAjax.escola`.
- `sige-softgenial.php` / `BUILD.json` -> 12.37.0.

> ✅ **Sem ficheiros novos no plugin.** Só altera `admin/hr/equipe-view.php`
> (+ versão). Não toca em ficheiros protegidos por hash.

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-ficha-imprimir-v12-37-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. Sem migração de dados. Limpe a cache
   do navegador (Ctrl+Shift+R).

## Verificação rápida
- Em **Equipa** → "Ver ficha" → no rodapé, **"Imprimir / Exportar (PDF)"**.
- Abre o diálogo de impressão com um documento A4 limpo (cabeçalho com o nome da
  escola na cor do tema, dados do colaborador, estado do contrato).
- Em **Destino**, escolher "Guardar como PDF" para exportar.
- Permitir pop-ups (o documento abre em nova janela).

## Rollback
- Reponha o ficheiro anterior a partir do backup. Sem efeitos colaterais.

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
