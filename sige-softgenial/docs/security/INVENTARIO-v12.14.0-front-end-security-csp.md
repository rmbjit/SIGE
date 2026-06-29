# Inventário Técnico - v12.14.0 - Front-end Security & CSP Enforcement

## Superfícies afectadas

### CSP e core
- `includes/admin-shell.php`
  - Antes: CSP do shell em `Content-Security-Policy-Report-Only`.
  - Depois: CSP real/enforcement em `Content-Security-Policy` apenas no shell admin `page=sige-app`.
- `includes/core-helpers.php`
  - Mantém `sige_csp_nonce()` e `sige_csp_script_attr()`.
  - Acrescenta `sige_csp_is_admin_shell_request()`.
  - Acrescenta filtros para nonce em scripts inline gerados pelo WordPress.

### JavaScript global
- `assets/sige-ui.js`
  - Mantém o despachante `data-sige-act`.
  - Acrescenta helpers declarativos: `sigeAbrirJanela`, `sigeExecutarJsonData`, `sigeRemoverPai`, `sigeConfirmacaoCaixaSubmit`.

### Views/admin convertidas parcialmente de inline para declarativo
- `admin/finance/financeiro-pagamentos.php`
- `admin/finance/financeiro-devedores-view.php`
- `admin/finance/financeiro-dashboard.php`
- `admin/finance/financeiro-extratos.php`
- `admin/finance/financeiro-centros-view.php`
- `admin/academic/alunos_lista.php`
- `admin/academic/turmas-view.php`
- `admin/academic/disciplinas-view.php`
- `admin/hr/equipe-view.php`
- `includes/aluno-accounts.php`

### Dependências externas/fontes
- `includes/login-page.php`
  - Google Fonts removido.
- `includes/portal-logic.php`
  - Google Fonts removido.

### Gates e smoke tests
- `tools/check-inline-frontend.php`
  - Baseline de `onclick` reduzido de 45 para 17.
  - Passa a exigir CSP enforcement no shell admin.
- `tools/smoke-csp-enforcement-v12-14-0.php`
  - Novo smoke específico da fase.

## Contagens de catraca após implementação
- `onclick=`: 17, máximo permitido 17.
- `style="`: 2051, máximo permitido 2051.
- Blocos `<script>` inline: 7, máximo permitido 7.

## Permissões e tenant isolation
A fase não introduz novas actions críticas, endpoints, tabelas, opções ou regras de tenant. A alteração é de transporte/execução no navegador. Os guards existentes continuam como fonte de verdade.

## Auditoria estruturada
Não foram criadas novas acções de negócio. A auditoria existente permanece aplicável às acções críticas já existentes. A fase evita criar caminhos alternativos que contornem auditoria.
