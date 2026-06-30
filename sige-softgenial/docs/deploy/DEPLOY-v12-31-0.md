# DEPLOY - SIGE SoftGenial v12.31.0

**Crachá do estudante: construtor único + impressão robusta**
Data: 2026-06-30 - Tipo: correcção/robustez de apresentação (cliente/JS).

## O que muda
- `admin/academic/alunos_lista.php`:
  - **Construtor único** de crachá partilhado por impressão individual e em lote
    (fim da duplicação de ~80 linhas).
  - **Impressão espera as imagens** (foto/logótipo) antes de imprimir, com
    salvaguarda de 6 s — adeus crachás com foto em branco em ligação lenta.
  - **Pop-up bloqueado** tratado com aviso claro.
  - **Todos os campos escapados** (nome, escola, turma, processo, foto).
  - **QR com fallback**: se o QR não puder ser gerado, imprime o nº de processo
    legível (entrada manual no portão).
- `sige-softgenial.php` -> 12.31.0; `BUILD.json` -> 12.31.0.
- Novo teste `tools/smoke-cracha-estudante-v12-31-0.php`.
- `tools/.design-tokens-baseline.json` -> 1888 (baixou 18 com a deduplicação).

> Sem alterações no servidor/SQL. O contrato QR↔Portaria mantém-se
> (`Aluno:<processo>` → o portão reduz a dígitos).

## Ficheiros no pacote (mini-ZIP)
```
sige-softgenial/admin/academic/alunos_lista.php
sige-softgenial/sige-softgenial.php
sige-softgenial/BUILD.json
sige-softgenial/tools/.design-tokens-baseline.json
sige-softgenial/tools/smoke-cracha-estudante-v12-31-0.php
```

## Instalação
1. Backup da pasta `sige-softgenial/`.
2. Extraia o ZIP por cima, mantendo a estrutura. Sem migração de dados.

## Verificação rápida
- **Crachá individual** (botão "Cartão" numa linha): abre, foto/logótipo
  presentes, QR legível; turma correcta.
- **Lote** ("Imprimir cartões"): todos com foto/QR; com ligação lenta, a
  impressão só dispara depois de as imagens carregarem.
- Um aluno **sem matrícula no ano** mostra "S/ Turma" (esperado).
- Bloquear pop-ups → aparece aviso, não falha em silêncio.
- (Se quiser) desligar a net antes de abrir → QR cai para o nº de processo.

## Rollback
- Reponha os ficheiros anteriores a partir do backup (e o baseline anterior 1906,
  embora 1888 também passe).

## Fronteira de segurança
- Só cliente/JS. Sem mexer em fórmulas, SQL, nonces, endpoints, permissões ou schema.
