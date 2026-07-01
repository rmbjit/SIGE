# DEPLOY - SIGE SoftGenial v12.42.0

**RH: super admin fora de Assiduidade/Salários + correcção rigorosa do congelamento**
Data: 2026-07-01 - Tipo: correcção. Sem schema, sem permissões novas, sem protegidos.

## O que muda
- **Super admin fora da escola.** O administrador WordPress real (utilizador de
  manutenção do sistema) deixa de aparecer nas listagens de colaboradores das
  abas **Assiduidade** e **Salários** — e, por consequência, nos **mapas
  fiscais** (INSS/IRPS). Continua excluído da aba Equipa como antes.
- **Congelamento depois de gravar (corrigido).** O estado do "shell" (bloqueio
  de scroll + elevação do conteúdo enquanto há modal) passa a ter uma fonte de
  verdade única e a ser reconciliado ANTES do clique — sem "clique desperdiçado".

## Ficheiros alterados
```
sige-softgenial/includes/rh-assiduidade.php     (helper de exclusão + grelha)
sige-softgenial/includes/rh-salarios.php        (preview + mapa aplicam exclusão)
sige-softgenial/admin/hr/equipe-view.php        (reconciliador único do shell)
sige-softgenial/sige-softgenial.php             (versão 12.42.0)
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
- **Assiduidade** e **Salários**: o utilizador super admin (o vosso, de
  manutenção) já **não aparece** na grelha nem na lista/mapas. Os colaboradores
  reais da escola continuam todos presentes.
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
