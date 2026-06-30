<?php
/**
 * Plugin Name: SIGE SoftGenial - Gestão Escolar Moçambique
 * Plugin URI: https://softgenial.edu.mz
 * Description: Software de Gestão Integrado para Escolas (SaaS Ready). Versão Modularizada.
 * Version: 12.21.0
 * Author: RMBJ Consultoria
 * Author URI: https://rmbjconsulting.com
 * Text Domain: sige-softgenial
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) exit;

// v12.9.34 - WhatsApp Engine Cron Token + fuso horário Moçambique
// Mantém datas operacionais do SIGE alinhadas com Africa/Maputo, mesmo quando o servidor/MySQL estão em UTC.
if (!defined('SIGE_TIMEZONE')) {
    define('SIGE_TIMEZONE', 'Africa/Maputo');
}
if (function_exists('date_default_timezone_set')) {
    @date_default_timezone_set(SIGE_TIMEZONE);
}


// ============================================================================
// CONSTANTES DO PLUGIN
// ============================================================================
define('SIGE_PATH', plugin_dir_path(__FILE__));
define('SIGE_URL', plugin_dir_url(__FILE__));
define('SIGE_VERSION', '12.21.0');
define('SIGE_HUB_VERSION', '1.1.1');


// ============================================================================
// HISTÓRICO DE VERSÕES
// O registo completo vive em CHANGELOG.md (consolidado) e docs/changelog/
// (detalhe integral por versão). Este ficheiro mantém apenas o bootstrap.
// ============================================================================

// ============================================================================
// VERSIONAMENTO PRO - mantém versão, build e diagnóstico alinhados
// ============================================================================
if (file_exists(SIGE_PATH . 'includes/versioning.php')) {
    require_once SIGE_PATH . 'includes/versioning.php';
}

// ============================================================================
// LOGIN PAGE (carregado PRIMEIRO - antes de qualquer módulo)
// ============================================================================
require_once SIGE_PATH . 'includes/login-page.php';

// ============================================================================
// AUTO-UPDATE (verifica softgenial.edu.mz/updates/info.json)
// ============================================================================
if (is_admin() && file_exists(SIGE_PATH . 'includes/update-checker.php')) {
    require_once SIGE_PATH . 'includes/update-checker.php';
}

// ============================================================================
// CARREGAMENTO DE MÓDULOS (ordem de dependência)
// ============================================================================

// 1. Core Helpers (funções base usadas por todos)
require_once SIGE_PATH . 'includes/core-helpers.php';

// 1b. Portal enxuto (v12.15.13): política de peso de entrega por papel. Carrega
// cedo para que os hooks de enqueue (admin-shell e ui-kit) possam consultar
// sige_portal_lean_is_active() ao decidir o que enfileirar. So decide assets,
// nunca toca em PHP financeiro/academico.
if (file_exists(SIGE_PATH . 'includes/portal-lean-assets.php')) {
    require_once SIGE_PATH . 'includes/portal-lean-assets.php';
}

// 1.0 Política global de destinatários WhatsApp por escola
if (file_exists(SIGE_PATH . 'includes/whatsapp-destinatarios-policy.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-destinatarios-policy.php';
}

// 1.0 Segurança de produção - guards, auditoria limpa e helpers de acesso
if (file_exists(SIGE_PATH . 'includes/security-hardening.php')) {
    require_once SIGE_PATH . 'includes/security-hardening.php';
}
if (file_exists(SIGE_PATH . 'includes/security-vault.php')) {
    require_once SIGE_PATH . 'includes/security-vault.php';
}

// 1.0.1 Escudo de login - rate limit de autenticação (activo) + 2FA email (opcional)
if (file_exists(SIGE_PATH . 'includes/security-login-shield.php')) {
    require_once SIGE_PATH . 'includes/security-login-shield.php';
}
if (file_exists(SIGE_PATH . 'includes/security-mfa-stepup.php')) {
    require_once SIGE_PATH . 'includes/security-mfa-stepup.php';
}
if (file_exists(SIGE_PATH . 'includes/security-mfa-totp.php')) {
    require_once SIGE_PATH . 'includes/security-mfa-totp.php';
}
if (file_exists(SIGE_PATH . 'includes/security-mfa-replay.php')) {
    require_once SIGE_PATH . 'includes/security-mfa-replay.php';
}
if (file_exists(SIGE_PATH . 'includes/security-mfa-settings.php')) {
    require_once SIGE_PATH . 'includes/security-mfa-settings.php';
}

// 1.0.1.1 User Security & Role Integrity - anti-enumeracao, brute-force por username e guarda de despromocao privilegiada
if (file_exists(SIGE_PATH . 'includes/security-user-integrity.php')) {
    require_once SIGE_PATH . 'includes/security-user-integrity.php';
}

// 1.0.2 Carregador de assets por view (convenção assets/views/, ADR no README dessa pasta)
if (file_exists(SIGE_PATH . 'includes/view-assets.php')) {
    require_once SIGE_PATH . 'includes/view-assets.php';
}

// [v12.10.142] Security Baseline PRO - update origin hotfix, segredos por instalação, REST hardening e updates assinados.
if (file_exists(SIGE_PATH . 'includes/security-baseline-pro.php')) {
    require_once SIGE_PATH . 'includes/security-baseline-pro.php';
}

// Fase 1 - snapshots defensivos antes de restauros sensíveis
if (file_exists(SIGE_PATH . 'includes/backup-safety.php')) {
    require_once SIGE_PATH . 'includes/backup-safety.php';
}

// Seguranca de uploads e ficheiros - blindagem anti-execucao do directorio de
// uploads e bloqueio de tipos perigosos a entrada (Biblioteca de Media e importacoes).
if (file_exists(SIGE_PATH . 'includes/security-uploads.php')) {
    require_once SIGE_PATH . 'includes/security-uploads.php';
}

// Armazenamento privado dos documentos sensiveis - move BI, certidao, vacinas, CV e
// certificados para fora do directorio publico e serve-os so pelo endpoint autenticado.
if (file_exists(SIGE_PATH . 'includes/security-uploads-private.php')) {
    require_once SIGE_PATH . 'includes/security-uploads-private.php';
}

// 1.0 Perfis/tenant - módulos configuráveis por escola
if (file_exists(SIGE_PATH . 'includes/tenant-profiles.php')) {
    require_once SIGE_PATH . 'includes/tenant-profiles.php';
}

// [HUB v3] Helper sige_feature() - fonte única de verdade
if (file_exists(SIGE_PATH . 'includes/hub/sige-feature.php')) {
    require_once SIGE_PATH . 'includes/hub/sige-feature.php';
}

// [v12.5.3] Observability Layer - diagnóstico leve sem UI\/schema\/cálculo
if (file_exists(SIGE_PATH . 'includes/observability-layer.php')) {
    require_once SIGE_PATH . 'includes/observability-layer.php';
}

// [v12.5.0] Config Layer + Permissions Layer - fundação sem alteração de schema/UI/cálculo
if (file_exists(SIGE_PATH . 'includes/config-layer.php')) {
    require_once SIGE_PATH . 'includes/config-layer.php';
}

// [v12.10.0] Centro de Configuração - refundação interna: schema único,
// controller único, view gerada por schema, interceptação dos handlers legacy
// dos outros módulos (db-handler, whatsapp-engine, email-engine) sem tocar
// uma única linha fora de /includes/settings/.
if (file_exists(SIGE_PATH . 'includes/settings/settings-bootstrap.php')) {
    require_once SIGE_PATH . 'includes/settings/settings-bootstrap.php';
}

// [v12.10.31] Aparência da escola - motor seguro de temas visuais
if (file_exists(SIGE_PATH . 'includes/theme-engine.php')) {
    require_once SIGE_PATH . 'includes/theme-engine.php';
}

// [v12.11.0] Curriculum Engine Foundation PRO - fundação multicurrículo em modo seguro/teste
if (file_exists(SIGE_PATH . 'includes/curriculum-engine.php')) {
    require_once SIGE_PATH . 'includes/curriculum-engine.php';
}

// [v12.6.0+] Permission Engine - deve carregar antes da UI, feature sync e módulos críticos
if (file_exists(SIGE_PATH . 'includes/permissions-layer.php')) {
    require_once SIGE_PATH . 'includes/permissions-layer.php';
}

// [v12.9.6] Page Guard Helper - guarda centralizada baseada na matriz SIGE.
// Carrega depois da Permission Engine para que `sige_can()` esteja disponível.
if (file_exists(SIGE_PATH . 'includes/page-guard.php')) {
    require_once SIGE_PATH . 'includes/page-guard.php';
}

// [v12.16.0 RC1] Institutional Product Map - atalhos operacionais filtrados pela guarda central.
if (file_exists(SIGE_PATH . 'includes/institutional-product-map.php')) {
    require_once SIGE_PATH . 'includes/institutional-product-map.php';
}

// [v12.18.0] Operational UX & Workflow Hardening - orientação contextual sem escrita.
if (file_exists(SIGE_PATH . 'includes/operational-workflow-hardening.php')) {
    require_once SIGE_PATH . 'includes/operational-workflow-hardening.php';
}

// [v12.19.0] Dashboard Executivo & Inteligencia Operacional por Perfil - leitura por perfil sem escrita.
// [v12.19.1] Dashboard Copy & Português Final Hotfix - corrige microcopy visível sem alterar lógica.
if (file_exists(SIGE_PATH . 'includes/profile-dashboard-intelligence.php')) {
    require_once SIGE_PATH . 'includes/profile-dashboard-intelligence.php';
}

// [v12.8.3] Feature Registry Sync - alinha permissões/módulos com features locais e Hub antigo
if (file_exists(SIGE_PATH . 'includes/feature-registry-sync.php')) {
    require_once SIGE_PATH . 'includes/feature-registry-sync.php';
}

// [CDN-SRI] Scripts CDN centralizados com SRI
if (file_exists(SIGE_PATH . 'includes/cdn-scripts.php')) {
    require_once SIGE_PATH . 'includes/cdn-scripts.php';
}
// Fallback se cdn-scripts.php não existir (evita fatal error nos views)
if (!function_exists('sige_cdn_script')) {
    function sige_cdn_script(string $key): string {
        $base = (defined('SIGE_URL') ? SIGE_URL : plugins_url('/', __FILE__)) . 'assets/vendor/';
        $urls = ['chartjs'=>$base.'chartjs/chart.umd.js','exceljs'=>$base.'exceljs/exceljs.min.js','filesaver'=>$base.'filesaver/FileSaver.min.js','xlsx'=>$base.'xlsx/xlsx.full.min.js','qrious'=>$base.'qrious/qrious.min.js','sortable'=>$base.'sortable/Sortable.min.js','html5qrcode'=>$base.'html5qrcode/html5-qrcode.min.js'];
        return isset($urls[$key]) ? '<script src="'.esc_url($urls[$key]).'"></script>' : '';
    }
}

// Registador central de assets locais (self-host) - base do front-end seguro/CSP.
if (file_exists(SIGE_PATH . 'includes/assets-registry.php')) {
    require_once SIGE_PATH . 'includes/assets-registry.php';
}

if (file_exists(SIGE_PATH . 'includes/portaria-camera-safe-page.php')) {
    require_once SIGE_PATH . 'includes/portaria-camera-safe-page.php';
}

// 1.05 SoftGenial Core Foundation (licenciamento, diagnóstico, logs e fila)
if (file_exists(SIGE_PATH . 'includes/core/core-loader.php')) {
    require_once SIGE_PATH . 'includes/core/core-loader.php';
}

// 1.1 Multi-Tenancy (isolamento de dados por escola)
if (file_exists(SIGE_PATH . 'includes/multitenancy.php')) {
    require_once SIGE_PATH . 'includes/multitenancy.php';
}

// 1.1.1 Fase 1 - guarda central de escopo tenant para IDs em AJAX/admin-post
if (file_exists(SIGE_PATH . 'includes/security-scope-guard.php')) {
    require_once SIGE_PATH . 'includes/security-scope-guard.php';
}

// 1.1.2 Security Kernel Foundation - regras operacionais e runtime.
// Carrega depois da matriz/tenant e antes dos modulos funcionais criticos.
if (file_exists(SIGE_PATH . 'includes/security-kernel-rules.php')) {
    require_once SIGE_PATH . 'includes/security-kernel-rules.php';
}
if (file_exists(SIGE_PATH . 'includes/security-kernel.php')) {
    require_once SIGE_PATH . 'includes/security-kernel.php';
}

// 1.2 Migração Centralizada (fonte única de verdade para schema BD)
require_once SIGE_PATH . 'includes/class-sige-migration.php';

// 1.2.1 DB Migration Engine incremental (12.7.0) - histórico e sync seguro de schema
if (file_exists(SIGE_PATH . 'includes/db-migration-engine.php')) {
    require_once SIGE_PATH . 'includes/db-migration-engine.php';
}

// 1.25 Regime de Mensalidade por Ciclo (Tempo Inteiro / Meio Dia) - compatibilidade Casa Colorida
if (file_exists(SIGE_PATH . 'includes/regime-mensalidade-core.php')) {
    require_once SIGE_PATH . 'includes/regime-mensalidade-core.php';
}

// 2. Database Handler (CRUD, AJAX, QR access)
require_once SIGE_PATH . 'includes/db-handler.php';

// 2.1.2 [v12.18.0] Operational UX & Workflow Hardening
//     - faixa contextual de fluxo seguro via asset leve; sem escrita e sem regra sensivel.
// 2.1.1 [v12.17.1] Alunos Mobile Header Hotfix
// 2.1 [v12.17.0] Alunos Performance & Modularization Contract
//     - helpers partilhados para SELECT leve, payload minimo e contratos de listagem.
if (file_exists(SIGE_PATH . 'includes/alunos-performance-contract.php')) {
    require_once SIGE_PATH . 'includes/alunos-performance-contract.php';
}

// 2.2 [v12.9.8+] AJAX handlers para paginacao server-side de alunos
//     - sige_get_aluno_full     (modal de edicao)
//     - sige_get_alunos_export  (Excel + cartoes em lote)
require_once SIGE_PATH . 'includes/aluno-fetch-ajax.php';


// 2.2 [v12.11.9.54] Mobile App Redesign Alunos PRO: topbar, hero, KPIs, pesquisa, chips, cards e bottom nav no padrão mobile inegociável.
// 2.3 [v12.11.9.55] Smoke visual/funcional Mobile App Alunos PRO: refinamento de aderência ao screenshot, pesquisa por turma e safe-area mobile.
// 2.4 [v12.11.9.57] Hero Fine-Tune 10/10 Mobile PRO: refino final do hero e cards mobile do módulo alunos.
// 2.5 [v12.11.9.58] Mobile App Visual System Global PRO: globaliza topbar, bottom nav e base visual app-grade mobile para todo o sistema.
// 2.2 [v12.11.9.53] Hotfix UX Mobile Cards de Alunos: acções fechadas por defeito, botões compactos com texto e sem área branca gigante.
// 2.2 [v12.11.9.52] UX responsivo PRO para Alunos e Matrículas: camada final
// mobile/tablet com filtros compactos, acções com texto, fluxo guiado no modal,
// acordeões na Ficha 360º e reforço de acessibilidade, sem alterar regras de negócio.
// 2.2 [v12.11.9.50] Gestão avançada de encarregados - helpers de privacidade,
// consentimento e apoio a comunicações sem alterar regras financeiras/académicas.
if (file_exists(SIGE_PATH . 'includes/encarregados-advanced.php')) {
    require_once SIGE_PATH . 'includes/encarregados-advanced.php';
}

// 3. Finance Core (regras financeiras, pagamentos)
require_once SIGE_PATH . 'includes/finance-core.php';

// 3.0.0.1 Livro-razao financeiro (Fase 6): append-only, encadeado por HMAC.
// Carregado antes do fin-action-service para instrumentar as operacoes criticas.
if (file_exists(SIGE_PATH . 'includes/finance-ledger.php')) {
    require_once SIGE_PATH . 'includes/finance-ledger.php';
}

// 3.0.1 Mapa de Cobranca (Lista de Devedores) em PDF - depende do finance-core
// para reutilizar a saldo canonica sige_fin_saldo_sql(). Query handler
// ?sige_dev_print=lista, protegido pelo Security Kernel (enforce) e auto-protegido.
require_once SIGE_PATH . 'includes/finance-devedores-pdf.php';

// 3.0 Camada financeira avançada importada da Casa Colorida - carregamento protegido.
// Restaura o fluxo completo de Centros de Custo sem substituir o finance-core validado da Malisa.
foreach ([
    'fin-fsm.php',
    'fin-classe-helper.php',
    'fin-item-dispatcher.php',
    'fin-familia-service.php',
    'fin-fecho-turno.php',
    'fin-action-service.php',
    'finance-aprovacoes.php',
    'despesa-state-machine.php',
    'centros-helpers.php',
    'fin-kpi-engine.php',
    'finance-data-efectiva.php',
    'notificacoes-encarregados.php',
] as $__sige_fin_file) {
    $__sige_fin_path = SIGE_PATH . 'includes/' . $__sige_fin_file;
    if (file_exists($__sige_fin_path)) {
        require_once $__sige_fin_path;
    }
}

// 3b. Pesquisa Global (P3): caixa de pesquisa unica na barra de topo.
require_once SIGE_PATH . 'includes/sige-pesquisa-global.php';

// 4. Security & Roles (permissões, redirecionamentos)
require_once SIGE_PATH . 'includes/security-roles.php';

// 5. Documents Engine (recibos, facturas, extractos)
require_once SIGE_PATH . 'includes/documents-engine.php';


// [v12.11.9.81] Histórico Financeiro do Aluno PRO - documento 360º imprimível/baixável com CSV.
if (file_exists(SIGE_PATH . 'includes/financeiro-historico-aluno-pro.php')) {
    require_once SIGE_PATH . 'includes/financeiro-historico-aluno-pro.php';
}

// 5.1 Fase 1 - download seguro de documentos do aluno/portal
if (file_exists(SIGE_PATH . 'includes/secure-document-download.php')) {
    require_once SIGE_PATH . 'includes/secure-document-download.php';
}

// 6. WhatsApp Engine (API, templates, queue)
require_once SIGE_PATH . 'includes/whatsapp-engine.php';

// 6.0.0 [v12.9.53] WhatsApp Templates Conversacional v2 - Secretaria a falar
// Carregado imediatamente a seguir ao engine para que o renderer
// sige_wpp_render_finance_template possa delegar a sige_wpp_tpl_v2_render
// quando o template persistido na escola estiver vazio.
if (file_exists(SIGE_PATH . 'includes/whatsapp-templates-conversacional.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-templates-conversacional.php';
}

// 6.0.1 WhatsApp Recovery Mode / Guardian Pro (v12.9.19)
if (file_exists(SIGE_PATH . 'includes/whatsapp-recovery-mode.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-recovery-mode.php';
}


// 6.0.2 Notification Policy (v12.9.57+) - DEVE carregar ANTES de
// whatsapp-human-advanced.php para que as nossas overrides de funções
// (com mesmo nome via `if (!function_exists())`) ganhem precedência sobre
// as definições canónicas. [v12.9.62] adiciona spread-load reschedule.
if (file_exists(SIGE_PATH . 'includes/notification-policy.php')) {
    require_once SIGE_PATH . 'includes/notification-policy.php';
}

// 6.0.3 WhatsApp Throughput Seguro Adaptativo (v12.9.36)
if (file_exists(SIGE_PATH . 'includes/whatsapp-human-advanced.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-human-advanced.php';
}

// 6.0.4 [v12.10.136] WhatsApp Guardrails PRO - segurança conservadora anti-ban.
// 6.0.5 [v12.10.137] Botões Financeiros PRO - compatibilidade de clique, isenção de dívidas e fallback de submissão.
// Carrega depois das políticas antigas para reforçar limites, sem alterar finanças/académico.
if (file_exists(SIGE_PATH . 'includes/whatsapp-guardrails-pro.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-guardrails-pro.php';
}

// 6.0.7 [v12.11.4] Permission Matrix Enforcement PRO - remove bloqueios cosméticos por perfil e garante DEC/ACTA reais via matriz.
// 6.0.8 [v12.11.5] Teacher Dashboard Permission PRO - Painel Principal passa a ser permissão explícita e não vem activo por defeito para Professores.
// 6.0.6 [v12.11.3] Notification Concordance PRO - tom B2C moçambicano, concordância e remoção de sinais de automação.
if (file_exists(SIGE_PATH . 'includes/notification-humanization-pro.php')) {
    require_once SIGE_PATH . 'includes/notification-humanization-pro.php';
}

// 6.1 WhatsApp Diagnostics (v12.9.8.3) - handlers AJAX da página de diagnóstico
if (file_exists(SIGE_PATH . 'includes/whatsapp-diagnostics.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-diagnostics.php';
}

// 6.2 WhatsApp Central de Mensagens (v12.9.56) - visualização integral da fila
// Permite ver agendadas, prontas, enviadas, falhadas e o texto integral.
// Apenas leitura/cancelamento/retry - nunca toca em fórmulas financeiras/académicas.
if (file_exists(SIGE_PATH . 'includes/whatsapp-central.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-central.php';
}

// 6.3 Circulares aos Encarregados - comunicados por turma/escola pela fila existente
if (file_exists(SIGE_PATH . 'includes/circulares.php')) {
    require_once SIGE_PATH . 'includes/circulares.php';
}

// 6.4 Presenças Académicas - derivadas da Portaria (sige_acessos) com excepções humanas
if (file_exists(SIGE_PATH . 'includes/presencas-engine.php')) {
    require_once SIGE_PATH . 'includes/presencas-engine.php';
}

// 6.5 Pagamentos M-Pesa - canal de pagamentos móveis com conciliação automática
// (OFF por defeito; a conciliação regista SEMPRE via sige_fin_registar_pagamento)
foreach (['mobile-tenant-options.php', 'mpesa-config.php', 'mpesa-client.php', 'mpesa-conciliacao.php', 'mpesa-webhook.php', 'mpesa-cobranca.php', 'emola-config.php', 'emola-webhook.php', 'emola-client.php', 'reconciliacao-divergencias.php', 'reconciliacao-viva.php', 'reconciliacao-accoes.php'] as $sige_mp_file) {
    if (file_exists(SIGE_PATH . 'includes/payments/' . $sige_mp_file)) {
        require_once SIGE_PATH . 'includes/payments/' . $sige_mp_file;
    }
}
unset($sige_mp_file);

// 6.6 Dados, privacidade e retencao (Fase 8) - inventario, classificacao, acesso/portabilidade e apagamento de PII
foreach (['privacy/pii-catalog.php', 'privacy/pii-inventario.php', 'privacy/pii-dossier.php', 'privacy/pii-dossier-export.php', 'privacy/pii-apagamento.php', 'privacy/pii-anonimizar-handler.php', 'privacy/pii-retencao.php'] as $sige_priv_file) {
    if (file_exists(SIGE_PATH . 'includes/' . $sige_priv_file)) {
        require_once SIGE_PATH . 'includes/' . $sige_priv_file;
    }
}
unset($sige_priv_file);

// 7. Email Engine (notificações, recibo público)
require_once SIGE_PATH . 'includes/email-engine.php';

// 7.1 Email Queue + Templates Inteligentes (v12.9.43)
if (file_exists(SIGE_PATH . 'includes/email-queue-templates.php')) {
    require_once SIGE_PATH . 'includes/email-queue-templates.php';
}
if (file_exists(SIGE_PATH . 'includes/email-central.php')) {
    require_once SIGE_PATH . 'includes/email-central.php';
}
if (file_exists(SIGE_PATH . 'includes/comunicacoes-core.php')) {
    require_once SIGE_PATH . 'includes/comunicacoes-core.php';
}

// 8. Cron Tasks (tarefas agendadas)
require_once SIGE_PATH . 'includes/cron-tasks.php';

// 8.0.1 Relatório Mensal Automático à Direcção (dia 1; OFF por defeito)
if (file_exists(SIGE_PATH . 'includes/relatorio-mensal-email.php')) {
    require_once SIGE_PATH . 'includes/relatorio-mensal-email.php';
}

// 8.0.2 Saúde Operacional - cartão de métricas e interruptores na Saúde do Sistema
if (file_exists(SIGE_PATH . 'includes/saude-operacional.php')) {
    require_once SIGE_PATH . 'includes/saude-operacional.php';
}

// 8.0.3 UI Kit - tokens e componentes canónicos do programa de UX (Sprint UX-1)
if (file_exists(SIGE_PATH . 'includes/ui-kit.php')) {
    require_once SIGE_PATH . 'includes/ui-kit.php';
}

// 8.0.4 CSP Zero-Inline Guard - remove permissao-inline do shell administrativo SIGE
if (file_exists(SIGE_PATH . 'includes/csp-zero-inline.php')) {
    require_once SIGE_PATH . 'includes/csp-zero-inline.php';
}

// 8.1 Alertas operacionais - cobrança, inconsistências e acompanhamento
foreach ([
    'alertas-core.php',
    'alertas-cron.php',
    'alertas-templates.php',
] as $__sige_alert_file) {
    $__sige_alert_path = SIGE_PATH . 'includes/' . $__sige_alert_file;
    if (file_exists($__sige_alert_path)) {
        require_once $__sige_alert_path;
    }
}
if (file_exists(SIGE_PATH . 'admin/alertas/ajax-handlers.php')) {
    require_once SIGE_PATH . 'admin/alertas/ajax-handlers.php';
}

// 9. AJAX Handlers (operações assíncronas)
require_once SIGE_PATH . 'includes/ajax-handlers.php';

// 10. Portal do Aluno / Encarregado
if (file_exists(SIGE_PATH . 'includes/portal-logic.php')) {
    require_once SIGE_PATH . 'includes/portal-logic.php';
}
if (file_exists(SIGE_PATH . 'includes/portal-handlers.php')) {
    require_once SIGE_PATH . 'includes/portal-handlers.php';
}

// 10.1 Dica do Dia - cultura de gestão escolar no primeiro acesso diário
if (file_exists(SIGE_PATH . 'includes/dica-do-dia.php')) {
    require_once SIGE_PATH . 'includes/dica-do-dia.php';
}

// 11. Contas de Alunos (provisionamento WP users)
if (file_exists(SIGE_PATH . 'includes/aluno-accounts.php')) {
    require_once SIGE_PATH . 'includes/aluno-accounts.php';
}

// 12. Académico (notas, avaliações, pautas)
if (file_exists(SIGE_PATH . 'includes/academic-logic.php')) {
    require_once SIGE_PATH . 'includes/academic-logic.php';
    require_once SIGE_PATH . 'includes/pauta-pdf-handler.php';
    require_once SIGE_PATH . 'includes/pauta-excel-handler.php';

    // [v12.9.67] Migração automática de re-aprovação de notas afectadas pelo
    // bug de versões anteriores (lançamento de campo novo invalidava aprovação
    // de campos anteriores). Idempotente - corre 1× por escola.
    if (file_exists(SIGE_PATH . 'includes/notas-reaprovacao-migracao.php')) {
        require_once SIGE_PATH . 'includes/notas-reaprovacao-migracao.php';
    }

    // [v12.9.9.0] Documentos finais: handlers sempre registados quando o ficheiro existe.
    // A autorização real fica dentro do handler (sige_can + capacidades legadas), sem abrir o backend WP.
    if (file_exists(SIGE_PATH . 'includes/passagem-docs-handler.php')) {
        require_once SIGE_PATH . 'includes/passagem-docs-handler.php';
        add_action('admin_post_sige_declaracao_passagem_pdf', 'sige_declaracao_passagem_pdf_handler');
        add_action('admin_post_sige_boletim_passagem_pdf', 'sige_boletim_passagem_pdf_handler');
    }

    // [Sprint 2 · M1] MAP Oficial (Mapa de Aproveitamento Pedagógico)
    require_once SIGE_PATH . 'includes/map-pdf-handler.php';
    add_action('admin_post_sige_map_pdf', 'sige_map_pdf_handler');
    add_action('admin_post_sige_map_turma_pdf', 'sige_map_turma_pdf_handler');

    // [Sprint 2 · M2] ACTA do Conselho de Notas (ACN/A25)
    require_once SIGE_PATH . 'includes/acta-pdf-handler.php';
    add_action('admin_post_sige_acta_pdf',     'sige_acta_pdf_handler');
    add_action('admin_post_sige_acta_guardar', 'sige_acta_guardar_handler');
    add_action('admin_post_sige_acta_aprovar_nota_votada', 'sige_acta_aprovar_nota_votada_handler');
    if (function_exists('sige_acta_pro_ensure_schema')) add_action('admin_init', 'sige_acta_pro_ensure_schema', 5);
}

// 13. WhatsApp Helper (fix de nonces)
if (file_exists(SIGE_PATH . 'includes/whatsapp-helper.php')) {
    require_once SIGE_PATH . 'includes/whatsapp-helper.php';
}

// 14. Audit Hooks (registo de acções)
if (file_exists(SIGE_PATH . 'includes/audit-hooks.php')) {
    require_once SIGE_PATH . 'includes/audit-hooks.php';
}

// 15. Admin Shell + handlers administrativos
// Carrega apenas no contexto administrativo/admin-post/ajax para reduzir peso no front-end e login público.
if (is_admin()) {
    require_once SIGE_PATH . 'includes/admin-shell.php';
    require_once SIGE_PATH . 'includes/jardim-handlers.php';
}

// ============================================================================
// 17. HUB CLIENT (Fase 1B - camada paralela ao SIGE_License existente)
// ============================================================================
if (file_exists(SIGE_PATH . 'includes/hub/class-sige-hub-client.php')) {
    require_once SIGE_PATH . 'includes/hub/class-sige-hub-client.php';
}
if (file_exists(SIGE_PATH . 'includes/hub/class-sige-hub-heartbeat.php')) {
    require_once SIGE_PATH . 'includes/hub/class-sige-hub-heartbeat.php';
}
if (file_exists(SIGE_PATH . 'includes/hub/class-sige-hub-commands.php')) {
    require_once SIGE_PATH . 'includes/hub/class-sige-hub-commands.php';
}
if (file_exists(SIGE_PATH . 'includes/hub/class-sige-hub-update-checker.php')) {
    require_once SIGE_PATH . 'includes/hub/class-sige-hub-update-checker.php';
}
if (file_exists(SIGE_PATH . 'includes/hub/class-sige-hub-admin.php')) {
    require_once SIGE_PATH . 'includes/hub/class-sige-hub-admin.php';
}
if (file_exists(SIGE_PATH . 'includes/hub/class-sige-hub-billing.php')) {
    require_once SIGE_PATH . 'includes/hub/class-sige-hub-billing.php';
}

add_action('plugins_loaded', function () {
    if (class_exists('SIGE_Hub_Client')) {
        SIGE_Hub_Client::boot();
    }
    if (class_exists('SIGE_Hub_Heartbeat')) {
        SIGE_Hub_Heartbeat::boot();
    }
    if (class_exists('SIGE_Hub_Update_Checker')) {
        SIGE_Hub_Update_Checker::boot();
    }
    if (class_exists('SIGE_Hub_Admin')) {
        SIGE_Hub_Admin::boot();
    }
});

// ============================================================================
// VERIFICAÇÃO DE FICHEIROS CRÍTICOS
// ============================================================================
add_action('admin_notices', function () {
    $file = SIGE_PATH . 'includes/academic-logic.php';
    if (!file_exists($file)) {
        echo '<div class="notice notice-error"><p><strong>SIGE SoftGenial:</strong> Ficheiro em falta: <code>includes/academic-logic.php</code>. O módulo de Notas não vai funcionar.</p></div>';
    }
});

// ============================================================================
// ACTIVAÇÃO DO PLUGIN
// ============================================================================
register_activation_hook(__FILE__, function () {
    sige_criar_roles_acesso();
    SIGE_Migration::activate();
    if (function_exists('sige_permissions_install')) { sige_permissions_install(); }
    if (function_exists('sige_security_baseline_install')) { sige_security_baseline_install(); }
    if (function_exists('sige_curriculum_install')) { sige_curriculum_install(); }
});

// ============================================================================
// DESACTIVAÇÃO DO PLUGIN - limpar crons do Hub Client (Fase 1B)
// ============================================================================
register_deactivation_hook(__FILE__, function () {
    if (class_exists('SIGE_Hub_Client')) {
        SIGE_Hub_Client::deactivate();
    }
    if (class_exists('SIGE_Hub_Heartbeat')) {
        SIGE_Hub_Heartbeat::deactivate();
    }
});

// ============================================================================
// DB SCHEMA (migração centralizada - class-sige-migration.php)
// ============================================================================
add_action('admin_init', ['SIGE_Migration', 'maybe_upgrade']);

// ============================================================================
// PORTAL DO ALUNO (Fallback se portal-logic.php não existir)
// ============================================================================
if (!function_exists('sige_render_portal_aluno')) {
    function sige_render_portal_aluno() {
        $target = function_exists('sige_aluno_portal_url_v117')
            ? sige_aluno_portal_url_v117(get_current_user_id())
            : admin_url('admin.php?page=sige-app&view=aluno_portal');

        if (!is_user_logged_in()) {
            return '<div class="sige-portal-erro"><p>A Página do Aluno foi integrada no SIGE. <a href="' . esc_url(wp_login_url($target)) . '">Inicie sessão para aceder</a>.</p></div>';
        }

        if (!headers_sent()) {
            wp_safe_redirect($target, 302);
            exit;
        }

        return '<div class="sige-portal"><p><a href="' . esc_url($target) . '">Abrir Página do Aluno integrada</a></p></div>';
    }
}
