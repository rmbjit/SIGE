# TECHNICAL INVENTORY - v12.12.8.1

Versao: 12.12.8.1 (Tenant Write Isolation). Base: 12.12.8.

## Superficie
Superficie de accao inalterada: manifesto com 193 itens. O incremento nao adiciona nem remove accoes; adiciona guards fail-closed na camada de escrita ja existente. Detalhe em ACTION_SURFACE_MANIFEST-v12.12.8.1.json.

## Views
54 views na allowlist, 54 com permissao mapeada (inalterado). As views academicas com sumidouros (abertura, encerramento) passam a ter guard fail-closed nos sumidouros sige_ab_execute_opening, sige_enc_criar_snapshot_final e sige_enc_marcar_reabertura.

## Permissoes
Inalterado: sige_can 60, current_user_can 475. Os guards sao ortogonais a autorizacao e correm depois da verificacao de permissao.

## Tenant
Nucleo do incremento. Novo helper auditado sige_tenant_write_guard. Categoria A: 24 sumidouros com parametro de escola endurecidos. Categoria B: 22 funcoes com escrita inline endurecidas (2 excecoes de logging). Handler delegado acta:1610 fechado. Baseline ": 1" 139 -> 138. Detalhe em TENANT_ISOLATION_REGISTER-v12.12.8.1.md e TENANT_FALLBACK_BASELINE-v12.12.8.1.json. Novo gate check-tenant-write-sinks governa a superficie real.

## Segredos
Sem segredos novos. Baseline em SECRETS_OPTIONS_BASELINE-v12.12.8.1.json.

## Dependencias
Sem dependencias nem hosts novos (12 hosts). Baseline em EXTERNAL_DEPENDENCIES_BASELINE-v12.12.8.1.json.

## Security Kernel
Contrato de regras inalterado: 193 regras, ids identicos a v12.12.8, em SECURITY_KERNEL_RULES-v12.12.8.1.json. Os guards de sumidouro complementam o Kernel com defesa em profundidade.
