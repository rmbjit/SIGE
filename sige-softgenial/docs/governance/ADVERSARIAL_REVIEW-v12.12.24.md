# Adversarial Review - SIGE SoftGenial v12.12.24

Fase 8 incremento 2: Direito de acesso e portabilidade. Data: 2026-06-21.

## Rediagnostico adversarial

Revisao hostil da nova superficie (consulta do dossie e exportacao) procurando fuga de dados, escalonamento de privilegio, contorno de tenant, injeccao e desalinhamento de governanca. Vectores examinados:

1. Acesso sem permissao. O ecra e o endpoint chamam sige_pii_dossier_pode_exportar (sige_can('privacidade.acesso_exportar') com fallback para administrador real). O Kernel aplica a permissao em enforce antes do handler. Sem permissao: mensagem de area reservada no ecra; wp_die(403) no endpoint. Resultado: coberto.

2. Fuga entre escolas. Toda a leitura do dossie passa por sige_pii_dossier_aluno_pertence (SELECT id ... WHERE id=%d AND escola_id=%d) e por escola_id em cada consulta de linhas. Aluno de outra escola devolve ok=false sem dados. Validado por smoke-acesso (aluno 7 na escola 99 recusado; linhas para escola errada vazias). Resultado: coberto.

3. Injeccao por identificador de coluna. As colunas vem do catalogo e sao filtradas por preg_match('/^[a-z0-9_]+$/') antes de entrarem no SQL; o nome da tabela e derivado do prefixo do WordPress; aluno e escola sao sempre preparados (%d). smoke-acesso confirma que uma coluna com 'mau; DROP TABLE x' e descartada. Resultado: coberto.

4. Falsificacao de pedido (CSRF). A exportacao exige nonce: check_admin_referer('sige_privacidade_exportar') no handler e intent nonce no Kernel. O formulario do ecra emite wp_nonce_field('sige_privacidade_exportar'). Resultado: coberto.

5. Abuso por volume. rate limit de 10 pedidos por 300 segundos na regra do Kernel. Resultado: coberto.

6. Exportacao sem rasto. privacidade.acesso_exportar foi adicionada a lista de permissoes sempre auditadas em sige_permission_audit, e o handler regista exportar_dossie com aluno_id e escola_id. Cada exportacao fica registada independentemente de WP_DEBUG. Resultado: coberto.

7. Escrita inadvertida. O helper do dossie nao contem INSERT/UPDATE/DELETE; o gate check-acesso proibe-os estaticamente. O handler so le e regista auditoria; nao muta dados de negocio. Resultado: coberto.

8. Desalinhamento de governanca. O endpoint entra no manifesto pela deteccao de add_action (198) e tem regra correspondente no Kernel (198); ids identicos validados. O gate check-security-kernel-rules confirma a regra enforce (intent nonce, audit, rate_limit, permissoes). Resultado: coberto.

9. Regressao de calculo ou de esquema. finance-core.php, academic-logic.php e whatsapp-engine.php byte-identicas a baseline; SCHEMA_VERSION inalterada. Resultado: coberto.

10. Regressao visual. Sem estilo inline; classes novas com tokens (sige-priv-procura, sige-priv-export); baselines de design (2414/7497) sem subida. Resultado: coberto.

## Resultado

- P0: nenhum.
- P1: nenhum.
- P2 (aceites, mitigados): a exportacao e sensivel por natureza; mitigada por permissao de risco alto, nonce, rate limit, isolamento por escola e auditoria obrigatoria. Encerrada por documentacao.
- P3 (aceites): a base legal e finalidade exibidas sao classificacao por omissao do catalogo, a rever pela instituicao; o titular funcionario fica para incremento posterior. Encerrados por documentacao.

## Decisao

Avancar. Zero P0/P1. Os P2/P3 ficam registados e encerrados por documentacao, sem adiar. O incremento esta pronto para entrega e nao bloqueia o Incr 3 (apagamento e anonimizacao).
