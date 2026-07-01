# DEPLOY - SIGE SoftGenial v12.42.2

**RH: sub-abas alinhadas com a Equipa (lista canónica) + correcção rigorosa do congelamento**
Data: 2026-07-01 - Tipo: correcção. Sem schema, sem permissões novas, sem protegidos.

> **v12.42.2 resolve a causa-raiz:** a Equipa mostrava o número certo, mas
> Ausências/Assiduidade/Salários mostravam gente a mais porque liam a tabela
> `sige_professores` **em bruto** (que tem linhas que não são staff: admin WP
> real, fichas órfãs/antigas). Agora todas as sub-abas usam a **mesma lista
> canónica da Equipa** — batem sempre certo.

## O que muda
- **Sub-abas de RH alinhadas com a Equipa.** Ausências (dropdown), Assiduidade e
  Salários — e, por consequência, os **mapas fiscais** (INSS/IRPS) — passam a
  listar exactamente os mesmos colaboradores que a aba Equipa (conjunto canónico:
  perfil SIGE activo + meta de escola, **sem** o admin WP real e **sem** fichas
  órfãs que não são staff).
- **Congelamento depois de gravar (corrigido).** O estado do "shell" (bloqueio
  de scroll + elevação do conteúdo enquanto há modal) passa a ter uma fonte de
  verdade única e a ser reconciliado ANTES do clique — sem "clique desperdiçado".

## Ficheiros alterados
```
sige-softgenial/includes/rh-assiduidade.php     (resolver canónico + grelha)
sige-softgenial/includes/rh-salarios.php        (preview + mapa usam lista canónica)
sige-softgenial/admin/hr/equipe-view.php        (dropdown Ausências + reconciliador do shell)
sige-softgenial/sige-softgenial.php             (versão 12.42.2)
sige-softgenial/BUILD.json                       (versão + sumário)
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
- **Ausências** (dropdown), **Assiduidade** e **Salários**: a lista de
  colaboradores é **exactamente igual** à da aba **Equipa** — o super admin (o
  vosso, de manutenção) e quaisquer fichas que não sejam staff **não aparecem**;
  os colaboradores reais continuam todos presentes. Confirme comparando o número
  de pessoas com a aba Equipa.
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
