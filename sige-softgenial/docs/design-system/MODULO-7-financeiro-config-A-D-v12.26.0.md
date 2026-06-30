# Módulo 7 - Preços e Serviços (view=financeiro-config) - Portões A-D

Versão: 12.26.0 · Data: 2026-06-30
View: `admin/finance/financeiro-config.php` (1838 linhas) - **NÃO TOCADO**.
Tudo em `assets/views/financeiro-core-design-pro.{js,css}` (já carregados aqui).

## Portão A
| Erro | Aplica-se? | Acção |
|---|---|---|
| `:root` sombrear globais | NÃO | View sem `:root` |
| Inline `on*=` | NÃO | 0 handlers inline server-rendered |
| Modal sem scroll | NÃO | Modais já com estrutura própria |
| ESC/classes de modal | OK | Já geridas pela view; só faltava a elevação |
| Modal tapado pela sidebar | SIM | `.sg-fincfg-service-modal`/`.sg-fincfg-modal` presos no contexto de `.sg-app-content`. Corrigido (elevação por classe) |
| Página muito longa | SIM (pedido) | Secções de regras colapsáveis |
| Checkboxes "ovo" | Já corrigido | v12.24.2 (raiz partilhada) |

## Portões C/D (só visual, fora do PHP)
1. Colapso: `.sg-fincfg-wrap .fc-card` com `.fc-card-header` ficam colapsáveis;
   regras (Multa, Descontos, Creche, Localização, Transporte) começam fechadas;
   "Lista de Serviços" aberta. Exclui `.fc-card` dentro de modais. Acessível;
   ignora cliques em controlos do cabeçalho; só mostra/esconde o corpo.
2. Elevação: `sg-fincfg-service-modal-open`/`sg-fincfg-modal-open` elevam
   `.sg-app-content` acima da sidebar.

## Limitação conhecida
"Gestão de Períodos" não tem `.fc-card-header` separado (título dentro do corpo);
foi ignorada com segurança e fica aberta. Colapsá-la exigiria um pequeno ajuste de
marcação no PHP (a confirmar).

## Fronteira
`financeiro-config.php` intacto (git). Sem lógica, fórmulas, SQL, nonces, AJAX,
`name`/`id`, permissões nem schema.

## Validação
`node --check` OK; gate de tokens estável; view PHP byte-a-byte igual.
