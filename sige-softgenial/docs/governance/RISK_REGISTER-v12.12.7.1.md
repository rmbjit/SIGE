# RISK REGISTER - v12.12.7.1

## P0
- Nenhum aberto.

## P1
- Nenhum aberto. (O rediagnostico encontrou um P1, identidade da escola pela fonte errada, ja CORRIGIDO: passou a usar sige_get_escola_perfil()->nome_escola/->logotipo.)

## P2
- O PDF reproduz a vista por DEFEITO do ecra (alunos activos com matricula activa) e o total coincide com o do ecra. Se o utilizador mudar filtros para alem do default (mes, centro, ou situacao 'todos'/'transferidos'), o PDF nao acompanha. Impressao com filtros fica para fase posterior.
- (Resolvido no rediagnostico) Permissao alargada: o handler aceitava financeiro.ver alem de cobrancas_ver/gerir. Removido; agora exige exactamente as permissoes da pagina.
- Mantem-se o padrao HTML pronto a imprimir em vez de PDF gerado no servidor; coerente com o sistema, mas sem assinatura digital do ficheiro.

## P3
- O contacto exibido escolhe o primeiro disponivel (WhatsApp das notificacoes, depois telemovel do pai); pode nao ser o preferido em todos os casos.
- O documento nao impoe LIMIT de linhas; a query e a mesma do ecra e a paginacao e do browser.
- tools/inventory-surface.php mantem strings de versao fixas (base_version/purpose) que precisam de actualizacao manual; divida pre-existente.
