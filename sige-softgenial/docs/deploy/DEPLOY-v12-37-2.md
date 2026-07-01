# DEPLOY - SIGE SoftGenial v12.37.2

**Congelamento (causa-raiz), fotos AVIF e cargo real no crachá**
Data: 2026-07-01 - Tipo: correcção de bugs. Sem schema, sem novo endpoint.

## O que muda
- `admin/hr/equipe-view.php` —
  * **Congelamento (causa-raiz)**: a visibilidade dos modais passa a depender
    **só** de `.active` (antes dependia também de `aria-hidden`, que podia
    dessincronizar e deixar um overlay invisível a capturar cliques — a página
    "congelava" até refresh). A auto-recuperação da 12.37.1 fica como reforço.
  * **Crachá**: o cargo passa a ser o **perfil SIGE real** (`sige_hr_role_label`)
    — Direcção, Gestor RH, Dir. Pedagógico, Secretaria, etc. — em vez do genérico
    "DOCENTE/STAFF".
- `includes/security-uploads.php` — **AVIF**: novo filtro `upload_mimes`
  (`avif => image/avif`), idempotente. O prefilter de segurança continua a
  validar o conteúdo real.
- `includes/ajax-handlers.php` — **AVIF**: `avif` aceite no validador do URL da
  foto de perfil.
- `sige-softgenial.php` / `BUILD.json` -> 12.37.2.

> ✅ **Sem ficheiros novos no plugin.** Não toca em ficheiros protegidos por hash.
> ℹ️ `includes/security-uploads.php` governa uploads de todo o site — a alteração
> apenas **adiciona** o mime AVIF (não remove nada).

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/hr/equipe-view.php
sige-softgenial/includes/security-uploads.php
sige-softgenial/includes/ajax-handlers.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/smoke-fix-freeze-avif-cracha-v12-37-2.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia por cima, mantendo a estrutura. Sem migração de dados.
   **Limpe a cache** do navegador (Ctrl+Shift+R) — há alteração de CSS/JS.

## Verificação rápida
- **Botões**: gravar um colaborador / activar-desactivar / configurar crachá e
  continuar a clicar normalmente — sem estados em que os cliques "não fazem nada".
- **AVIF**: editar colaborador → "Carregar foto" → escolher/enviar um `.avif` →
  Guardar → a foto aparece na lista e na ficha.
- **Crachá**: imprimir o crachá de alguém da direcção/secretaria/RH — o cargo
  apresentado é o real (ex.: "Direcção", "Gestor RH"), não "STAFF".

## Rollback
- Reponha os ficheiros anteriores a partir do backup.

## Fronteira
- Sem mexer em fórmulas, schema, permissões reais ou ficheiros protegidos.
