<?php
if (!defined('ABSPATH') && !defined('SIGE_SECURITY_KERNEL_TEST_MODE')) exit;

if (!function_exists('sige_security_kernel_rules')) {
    function sige_security_kernel_rules(): array {
        return array (
  0 => 
  array (
    'id' => 'admin_post:sige_abrir_ano',
    'type' => 'admin_post',
    'name' => 'sige_abrir_ano',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 303,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  1 => 
  array (
    'id' => 'admin_post:sige_acta_aprovar_nota_votada',
    'type' => 'admin_post',
    'name' => 'sige_acta_aprovar_nota_votada',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 336,
      ),
      1 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 442,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  2 => 
  array (
    'id' => 'admin_post:sige_acta_guardar',
    'type' => 'admin_post',
    'name' => 'sige_acta_guardar',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 345,
      ),
      1 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 441,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  3 => 
  array (
    'id' => 'admin_post:sige_acta_pdf',
    'type' => 'admin_post',
    'name' => 'sige_acta_pdf',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 440,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  4 => 
  array (
    'id' => 'admin_post:sige_aluno_portal_change_password',
    'type' => 'admin_post',
    'name' => 'sige_aluno_portal_change_password',
    'module' => 'portal',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/portal-handlers.php',
        'line' => 200,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  5 => 
  array (
    'id' => 'admin_post:sige_aprovar_pauta',
    'type' => 'admin_post',
    'name' => 'sige_aprovar_pauta',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 129,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  6 => 
  array (
    'id' => 'admin_post:sige_boletim_passagem_pdf',
    'type' => 'admin_post',
    'name' => 'sige_boletim_passagem_pdf',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 430,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  7 => 
  array (
    'id' => 'admin_post:sige_circular_enviar',
    'type' => 'admin_post',
    'name' => 'sige_circular_enviar',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/circulares.php',
        'line' => 123,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  8 => 
  array (
    'id' => 'admin_post:sige_core_force_license_check',
    'type' => 'admin_post',
    'name' => 'sige_core_force_license_check',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_core_force_license_check',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/core/class-sige-core.php',
        'line' => 15,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  9 => 
  array (
    'id' => 'admin_post:sige_core_save_license_settings',
    'type' => 'admin_post',
    'name' => 'sige_core_save_license_settings',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_core_save_license_settings',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/core/class-sige-core.php',
        'line' => 14,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  10 => 
  array (
    'id' => 'admin_post:sige_curriculum_select_profile',
    'type' => 'admin_post',
    'name' => 'sige_curriculum_select_profile',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/curriculum-engine.php',
        'line' => 399,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  11 => 
  array (
    'id' => 'admin_post:sige_curriculum_sync_legacy',
    'type' => 'admin_post',
    'name' => 'sige_curriculum_sync_legacy',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/curriculum-engine.php',
        'line' => 383,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  12 => 
  array (
    'id' => 'admin_post:sige_declaracao_passagem_pdf',
    'type' => 'admin_post',
    'name' => 'sige_declaracao_passagem_pdf',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 429,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  13 => 
  array (
    'id' => 'admin_post:sige_download_modelo_importacao_alunos_xlsx',
    'type' => 'admin_post',
    'name' => 'sige_download_modelo_importacao_alunos_xlsx',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-fetch-ajax.php',
        'line' => 1365,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  14 => 
  array (
    'id' => 'admin_post:sige_emola_guardar_config',
    'type' => 'admin_post',
    'name' => 'sige_emola_guardar_config',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.mobile_payments_gerir',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretaria_geral',
    ),
    'permission_mode' => 'kernel_and_legacy_compatible',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_emola_config',
      'source' => 'request',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_emola_guardar_config',
      'max' => 10,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/emola-config.php',
        'line' => 83,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Enforcement piloto v12.12.5: nonce/permissao/tenant/rate/audit no kernel e escrita em option scoped por escola.',
    'permission_note' => 'Credenciais de pagamentos moveis; matriz SIGE e fallback legado compativel.',
    'delegated_to' => '',
    'tenant_storage' => 'tenant_scoped_wp_option',
  ),
  15 => 
  array (
    'id' => 'admin_post:sige_encerrar_ano',
    'type' => 'admin_post',
    'name' => 'sige_encerrar_ano',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 298,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  16 => 
  array (
    'id' => 'admin_post:sige_hub_force_heartbeat',
    'type' => 'admin_post',
    'name' => 'sige_hub_force_heartbeat',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/hub/class-sige-hub-admin.php',
        'line' => 28,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  17 => 
  array (
    'id' => 'admin_post:sige_hub_force_refresh',
    'type' => 'admin_post',
    'name' => 'sige_hub_force_refresh',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/hub/class-sige-hub-admin.php',
        'line' => 27,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  18 => 
  array (
    'id' => 'admin_post:sige_jardim_avaliacao_salvar',
    'type' => 'admin_post',
    'name' => 'sige_jardim_avaliacao_salvar',
    'module' => 'jardim',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 873,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  19 => 
  array (
    'id' => 'admin_post:sige_jardim_criterios_salvar',
    'type' => 'admin_post',
    'name' => 'sige_jardim_criterios_salvar',
    'module' => 'jardim',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 283,
      ),
      1 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 822,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  20 => 
  array (
    'id' => 'admin_post:sige_jardim_diario_salvar',
    'type' => 'admin_post',
    'name' => 'sige_jardim_diario_salvar',
    'module' => 'jardim',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 261,
      ),
      1 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 769,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  21 => 
  array (
    'id' => 'admin_post:sige_jardim_presencas_salvar',
    'type' => 'admin_post',
    'name' => 'sige_jardim_presencas_salvar',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 741,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  22 => 
  array (
    'id' => 'admin_post:sige_jardim_relatorio_enviar_individual',
    'type' => 'admin_post',
    'name' => 'sige_jardim_relatorio_enviar_individual',
    'module' => 'jardim',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 976,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  23 => 
  array (
    'id' => 'admin_post:sige_jardim_relatorio_enviar_mensal',
    'type' => 'admin_post',
    'name' => 'sige_jardim_relatorio_enviar_mensal',
    'module' => 'jardim',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 1007,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  24 => 
  array (
    'id' => 'admin_post:sige_jardim_saude_salvar',
    'type' => 'admin_post',
    'name' => 'sige_jardim_saude_salvar',
    'module' => 'jardim',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 272,
      ),
      1 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 796,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  25 => 
  array (
    'id' => 'admin_post:sige_limpar_dados_teste',
    'type' => 'admin_post',
    'name' => 'sige_limpar_dados_teste',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 325,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  26 => 
  array (
    'id' => 'admin_post:sige_map_pdf',
    'type' => 'admin_post',
    'name' => 'sige_map_pdf',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 435,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  27 => 
  array (
    'id' => 'admin_post:sige_map_turma_pdf',
    'type' => 'admin_post',
    'name' => 'sige_map_turma_pdf',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 436,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  28 => 
  array (
    'id' => 'admin_post:sige_mpesa_guardar_config',
    'type' => 'admin_post',
    'name' => 'sige_mpesa_guardar_config',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.mobile_payments_gerir',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretaria_geral',
    ),
    'permission_mode' => 'kernel_and_legacy_compatible',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mpesa_config',
      'source' => 'request',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_mpesa_guardar_config',
      'max' => 10,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/mpesa-config.php',
        'line' => 95,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Enforcement piloto v12.12.5: nonce/permissao/tenant/rate/audit no kernel e escrita em option scoped por escola.',
    'permission_note' => 'Credenciais de pagamentos moveis; matriz SIGE e fallback legado compativel.',
    'delegated_to' => '',
    'tenant_storage' => 'tenant_scoped_wp_option',
  ),
  29 => 
  array (
    'id' => 'admin_post:sige_pauta_excel',
    'type' => 'admin_post',
    'name' => 'sige_pauta_excel',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/pauta-excel-handler.php',
        'line' => 15,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  30 => 
  array (
    'id' => 'admin_post:sige_pauta_pdf',
    'type' => 'admin_post',
    'name' => 'sige_pauta_pdf',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/pauta-pdf-handler.php',
        'line' => 15,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  31 => 
  array (
    'id' => 'admin_post:sige_process_queue_now',
    'type' => 'admin_post',
    'name' => 'sige_process_queue_now',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 554,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  32 => 
  array (
    'id' => 'admin_post:sige_saude_toggles',
    'type' => 'admin_post',
    'name' => 'sige_saude_toggles',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/saude-operacional.php',
        'line' => 136,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  33 => 
  array (
    'id' => 'admin_post:sige_secure_document_download',
    'type' => 'admin_post',
    'name' => 'sige_secure_document_download',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 352,
      ),
      1 => 
      array (
        'file' => 'includes/secure-document-download.php',
        'line' => 269,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  34 => 
  array (
    'id' => 'admin_post:sige_secure_staff_document_download',
    'type' => 'admin_post',
    'name' => 'sige_secure_staff_document_download',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/secure-document-download.php',
        'line' => 267,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  35 => 
  array (
    'id' => 'admin_post:sige_wpp_health_set',
    'type' => 'admin_post',
    'name' => 'sige_wpp_health_set',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/notification-policy.php',
        'line' => 1344,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  36 => 
  array (
    'id' => 'admin_post_nopriv:sige_process_queue_now',
    'type' => 'admin_post_nopriv',
    'name' => 'sige_process_queue_now',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => true,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'hmac_token_or_signature_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 555,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  37 => 
  array (
    'id' => 'cron_hook:admin_enqueue_scripts',
    'type' => 'cron_hook',
    'name' => 'admin_enqueue_scripts',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/admin-shell.php',
        'line' => 937,
      ),
      1 => 
      array (
        'file' => 'includes/ui-kit.php',
        'line' => 16,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  38 => 
  array (
    'id' => 'cron_hook:login_enqueue_scripts',
    'type' => 'cron_hook',
    'name' => 'login_enqueue_scripts',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/login-page.php',
        'line' => 10,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  39 => 
  array (
    'id' => 'cron_hook:sige_alertas_cron_daily',
    'type' => 'cron_hook',
    'name' => 'sige_alertas_cron_daily',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/alertas-cron.php',
        'line' => 100,
      ),
      1 => 
      array (
        'file' => 'includes/alertas-cron.php',
        'line' => 77,
      ),
      2 => 
      array (
        'file' => 'includes/alertas-cron.php',
        'line' => 90,
      ),
      3 => 
      array (
        'file' => 'includes/alertas-cron.php',
        'line' => 213,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  40 => 
  array (
    'id' => 'cron_hook:sige_conciliacao_semanal',
    'type' => 'cron_hook',
    'name' => 'sige_conciliacao_semanal',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 770,
      ),
      1 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 44,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  41 => 
  array (
    'id' => 'cron_hook:sige_evento_diario',
    'type' => 'cron_hook',
    'name' => 'sige_evento_diario',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/whatsapp_central-view.php',
        'line' => 206,
      ),
      1 => 
      array (
        'file' => 'admin/whatsapp_diag-view.php',
        'line' => 87,
      ),
      2 => 
      array (
        'file' => 'includes/alertas-cron.php',
        'line' => 159,
      ),
      3 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 560,
      ),
      4 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 601,
      ),
      5 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 41,
      ),
      6 => 
      array (
        'file' => 'includes/payments/mpesa-conciliacao.php',
        'line' => 241,
      ),
      7 => 
      array (
        'file' => 'includes/relatorio-mensal-email.php',
        'line' => 83,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  42 => 
  array (
    'id' => 'cron_hook:sige_hub_client_refresh',
    'type' => 'cron_hook',
    'name' => 'sige_hub_client_refresh',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/hub/class-sige-hub-client.php',
        'line' => 28,
      ),
      1 => 
      array (
        'file' => 'includes/hub/class-sige-hub-client.php',
        'line' => 30,
      ),
      2 => 
      array (
        'file' => 'includes/hub/class-sige-hub-client.php',
        'line' => 40,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  43 => 
  array (
    'id' => 'cron_hook:sige_hub_heartbeat_send',
    'type' => 'cron_hook',
    'name' => 'sige_hub_heartbeat_send',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/hub/class-sige-hub-heartbeat.php',
        'line' => 23,
      ),
      1 => 
      array (
        'file' => 'includes/hub/class-sige-hub-heartbeat.php',
        'line' => 27,
      ),
      2 => 
      array (
        'file' => 'includes/hub/class-sige-hub-heartbeat.php',
        'line' => 46,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  44 => 
  array (
    'id' => 'cron_hook:sige_processar_email_queue',
    'type' => 'cron_hook',
    'name' => 'sige_processar_email_queue',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-queue-templates.php',
        'line' => 378,
      ),
      1 => 
      array (
        'file' => 'includes/email-queue-templates.php',
        'line' => 374,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  45 => 
  array (
    'id' => 'cron_hook:sige_processar_whatsapp_queue',
    'type' => 'cron_hook',
    'name' => 'sige_processar_whatsapp_queue',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/whatsapp_central-view.php',
        'line' => 205,
      ),
      1 => 
      array (
        'file' => 'admin/whatsapp_diag-view.php',
        'line' => 86,
      ),
      2 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 492,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  46 => 
  array (
    'id' => 'query_handler:sige_billing_bypass',
    'type' => 'query_handler',
    'name' => 'sige_billing_bypass',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/hub/class-sige-hub-billing.php',
        'line' => 201,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'runtime_hooks' => 
    array (
      0 => 'admin_init',
      1 => 'parse_request',
      2 => 'template_redirect',
    ),
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_multi_hook',
  ),
  47 => 
  array (
    'id' => 'query_handler:sige_desp_print',
    'type' => 'query_handler',
    'name' => 'sige_desp_print',
    'module' => 'financeiro',
    'risk' => 'high',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.despesas_ver',
      1 => 'financeiro.despesas_gerir',
      2 => 'financeiro.ver',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretario',
      2 => 'sige_financeiro',
    ),
    'permission_mode' => 'kernel_and_legacy_compatible',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_desp_print',
      'source' => 'get',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_query_handler_sige_desp_print',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/finance-core.php',
        'line' => 3491,
      ),
      1 => 
      array (
        'file' => 'includes/finance-core.php',
        'line' => 3495,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Enforcement piloto v12.12.6: comprovativo/relatorio de despesas protegido por nonce, permissao, tenant, rate e auditoria; dispatch antecipado multi-hook.',
    'permission_note' => 'Documento financeiro de despesas; conserva permissoes da v12.12.1.',
    'delegated_to' => '',
    'runtime_hooks' => 
    array (
      0 => 'admin_init',
      1 => 'parse_request',
      2 => 'template_redirect',
    ),
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_multi_hook',
  ),
  48 => 
  array (
    'id' => 'query_handler:sige_portaria_camera',
    'type' => 'query_handler',
    'name' => 'sige_portaria_camera',
    'module' => 'portaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/portaria-camera-safe-page.php',
        'line' => 13,
      ),
      1 => 
      array (
        'file' => 'includes/security-baseline-pro.php',
        'line' => 333,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Observe v12.12.6: portaria camera passa pelo kernel em prioridade antecipada antes do render standalone.',
    'delegated_to' => '',
    'runtime_hooks' => 
    array (
      0 => 'admin_init',
      1 => 'parse_request',
      2 => 'template_redirect',
    ),
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_multi_hook',
  ),
  49 => 
  array (
    'id' => 'query_handler:sige_print',
    'type' => 'query_handler',
    'name' => 'sige_print',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/documents-engine.php',
        'line' => 16,
      ),
      1 => 
      array (
        'file' => 'includes/documents-engine.php',
        'line' => 21,
      ),
      2 => 
      array (
        'file' => 'includes/financeiro-historico-aluno-pro.php',
        'line' => 960,
      ),
      3 => 
      array (
        'file' => 'includes/security-roles.php',
        'line' => 190,
      ),
      4 => 
      array (
        'file' => 'includes/security-scope-guard.php',
        'line' => 182,
      ),
      5 => 
      array (
        'file' => 'includes/security-scope-guard.php',
        'line' => 197,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Observe v12.12.6: documentos/prints passam pelo kernel em admin_init, parse_request e template_redirect antes dos handlers funcionais.',
    'delegated_to' => '',
    'runtime_hooks' => 
    array (
      0 => 'admin_init',
      1 => 'parse_request',
      2 => 'template_redirect',
    ),
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_multi_hook',
  ),
  50 => 
  array (
    'id' => 'query_handler:sige_recibo',
    'type' => 'query_handler',
    'name' => 'sige_recibo',
    'module' => 'financeiro',
    'risk' => 'high',
    'public' => true,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'hmac_token_or_signature_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-engine.php',
        'line' => 661,
      ),
      1 => 
      array (
        'file' => 'includes/email-engine.php',
        'line' => 667,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'runtime_hooks' => 
    array (
      0 => 'admin_init',
      1 => 'parse_request',
      2 => 'template_redirect',
    ),
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_multi_hook',
  ),
  51 => 
  array (
    'id' => 'rest_route:sige/v1:/emola/callback',
    'type' => 'rest_route',
    'name' => 'sige/v1',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => true,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'public_token',
    'intent' => 
    array (
      'type' => 'token',
      'field' => 'token',
      'header' => 'x-sige-token',
      'source' => 'rest_param_or_header',
      'validator' => 'mobile_payment_webhook',
      'provider' => 'emola',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_rest_emola_callback',
      'max' => 120,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/emola-webhook.php',
        'line' => 53,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => '',
    'route' => '/emola/callback',
    'runtime_match' => 'namespace_route',
    'authorization_mode' => 'public_token',
  ),
  52 => 
  array (
    'id' => 'rest_route:sige/v1:/hub/instant-refresh',
    'type' => 'rest_route',
    'name' => 'sige/v1',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => true,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'hmac_token_or_signature_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/hub/class-sige-hub-client.php',
        'line' => 47,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'route' => '/hub/instant-refresh',
    'runtime_match' => 'namespace_route',
  ),
  53 => 
  array (
    'id' => 'rest_route:sige/v1:/mpesa/callback',
    'type' => 'rest_route',
    'name' => 'sige/v1',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => true,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'public_token',
    'intent' => 
    array (
      'type' => 'token',
      'field' => 'token',
      'header' => 'x-sige-token',
      'source' => 'rest_param_or_header',
      'validator' => 'mobile_payment_webhook',
      'provider' => 'mpesa',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_rest_mpesa_callback',
      'max' => 120,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/mpesa-webhook.php',
        'line' => 18,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => '',
    'route' => '/mpesa/callback',
    'runtime_match' => 'namespace_route',
    'authorization_mode' => 'public_token',
  ),
  54 => 
  array (
    'id' => 'rest_route:sige/v1:/process-queue',
    'type' => 'rest_route',
    'name' => 'sige/v1',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => true,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'hmac_token_or_signature_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/cron-tasks.php',
        'line' => 499,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'route' => '/process-queue',
    'runtime_match' => 'namespace_route',
  ),
  55 => 
  array (
    'id' => 'rest_route:sige/v1:/whatsapp-webhook',
    'type' => 'rest_route',
    'name' => 'sige/v1',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => true,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'hmac_token_or_signature_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-guardian.php',
        'line' => 191,
      ),
      1 => 
      array (
        'file' => 'includes/whatsapp-recovery-mode.php',
        'line' => 787,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'route' => '/whatsapp-webhook',
    'runtime_match' => 'namespace_route',
  ),
  56 => 
  array (
    'id' => 'shortcode:sige_portal',
    'type' => 'shortcode',
    'name' => 'sige_portal',
    'module' => 'portal',
    'risk' => 'medium',
    'public' => true,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'hmac_token_or_signature_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/portal-logic.php',
        'line' => 2297,
      ),
      1 => 
      array (
        'file' => 'includes/portal-logic.php',
        'line' => 2301,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  57 => 
  array (
    'id' => 'view_action:financeiro-despesas:anular_despesa',
    'type' => 'view_action',
    'name' => 'anular_despesa',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.despesas_gerir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => 'sige_nonce_anular',
      'action' => 'sige_anular_despesa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_despesas_anular_despesa',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-despesas-view.php',
        'line' => 136,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-despesas',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'acao',
      'source' => 'post',
      'value' => 'anular_despesa',
    ),
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'despesa_id',
        'table' => 'sige_fin_despesas',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => false,
      ),
    ),
  ),
  58 => 
  array (
    'id' => 'view_action:financeiro-despesas:aprovar_despesa',
    'type' => 'view_action',
    'name' => 'aprovar_despesa',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.despesas_gerir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => 'sige_nonce_aprovar',
      'action' => 'sige_aprovar_despesa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_despesas_aprovar_despesa',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-despesas-view.php',
        'line' => 97,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-despesas',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'acao',
      'source' => 'post',
      'value' => 'aprovar_despesa',
    ),
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'despesa_id',
        'table' => 'sige_fin_despesas',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => false,
      ),
    ),
  ),
  59 => 
  array (
    'id' => 'view_action:financeiro-despesas:nova_despesa',
    'type' => 'view_action',
    'name' => 'nova_despesa',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.despesas_gerir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => 'sige_despesa_nonce',
      'action' => 'sige_nova_despesa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_despesas_nova_despesa',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-despesas-view.php',
        'line' => 43,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-despesas',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'acao',
      'source' => 'post',
      'value' => 'nova_despesa',
    ),
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'despesa_id',
        'table' => 'sige_fin_despesas',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => false,
      ),
    ),
  ),
  60 => 
  array (
    'id' => 'view_action:financeiro-extratos:sige_anular_recibo',
    'type' => 'view_action',
    'name' => 'sige_anular_recibo',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.estornar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_anular_recibo',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_extratos_sige_anular_recibo',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-extratos.php',
        'line' => 725,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-extratos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_anular_recibo',
      'source' => 'post',
    ),
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'recibo_id_anular',
        'table' => 'sige_fin_pagamentos',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => true,
      ),
    ),
  ),
  61 => 
  array (
    'id' => 'view_action:financeiro-extratos:sige_fechar_caixa',
    'type' => 'view_action',
    'name' => 'sige_fechar_caixa',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.caixa_fechar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_fechar_caixa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_extratos_sige_fechar_caixa',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-extratos.php',
        'line' => 607,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-extratos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fechar_caixa',
      'source' => 'post',
    ),
  ),
  62 => 
  array (
    'id' => 'view_action:financeiro-extratos:sige_reabrir_caixa',
    'type' => 'view_action',
    'name' => 'sige_reabrir_caixa',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.caixa_reabrir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_reabrir_caixa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_extratos_sige_reabrir_caixa',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-extratos.php',
        'line' => 494,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-extratos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_reabrir_caixa',
      'source' => 'post',
    ),
  ),
  63 => 
  array (
    'id' => 'view_action:financeiro-lancamentos:cancelar_lancamento',
    'type' => 'view_action',
    'name' => 'cancelar_lancamento',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.lancamentos_gerir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => 'sige_cancelar_nonce',
      'action' => 'sige_cancelar_lancamento',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_lancamentos_cancelar_lancamento',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-lancamentos-view.php',
        'line' => 226,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-lancamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'acao',
      'source' => 'post',
      'value' => 'cancelar_lancamento',
    ),
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'lancamento_id',
        'table' => 'sige_fin_lancamentos',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => true,
      ),
    ),
  ),
  64 => 
  array (
    'id' => 'view_action:financeiro-lancamentos:isentar_lancamento',
    'type' => 'view_action',
    'name' => 'isentar_lancamento',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.isentar_multas',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => 'sige_isentar_nonce',
      'action' => 'sige_isentar_lancamento',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_lancamentos_isentar_lancamento',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-lancamentos-view.php',
        'line' => 249,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-lancamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'acao',
      'source' => 'post',
      'value' => 'isentar_lancamento',
    ),
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'lancamento_id',
        'table' => 'sige_fin_lancamentos',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => true,
      ),
    ),
  ),
  65 => 
  array (
    'id' => 'view_action:financeiro-lancamentos:reativar_lancamento',
    'type' => 'view_action',
    'name' => 'reativar_lancamento',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.lancamentos_gerir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => 'sige_reativar_nonce',
      'action' => 'sige_reativar_lancamento',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_lancamentos_reativar_lancamento',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-lancamentos-view.php',
        'line' => 272,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-lancamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'acao',
      'source' => 'post',
      'value' => 'reativar_lancamento',
    ),
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'lancamento_id',
        'table' => 'sige_fin_lancamentos',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => true,
      ),
    ),
  ),
  66 => 
  array (
    'id' => 'view_action:financeiro-pagamentos:sige_fin_bloquear_mes_submit',
    'type' => 'view_action',
    'name' => 'sige_fin_bloquear_mes_submit',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.bloquear_mes',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce_bloquear',
      'action' => 'sige_bloquear_mes',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_pagamentos_sige_fin_bloquear_mes_submit',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-pagamentos.php',
        'line' => 1100,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-pagamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fin_bloquear_mes_submit',
      'source' => 'post',
    ),
  ),
  67 => 
  array (
    'id' => 'view_action:financeiro-pagamentos:sige_fin_cancelar_submit',
    'type' => 'view_action',
    'name' => 'sige_fin_cancelar_submit',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.lancamentos_gerir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce_cancel',
      'action' => 'sige_cancelar_lancamento',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_pagamentos_sige_fin_cancelar_submit',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-pagamentos.php',
        'line' => 1016,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-pagamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fin_cancelar_submit',
      'source' => 'post',
    ),
  ),
  68 => 
  array (
    'id' => 'view_action:financeiro-pagamentos:sige_fin_desbloquear_mes_submit',
    'type' => 'view_action',
    'name' => 'sige_fin_desbloquear_mes_submit',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.desbloquear_mes',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce_desbloquear',
      'action' => 'sige_desbloquear_mes',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_pagamentos_sige_fin_desbloquear_mes_submit',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-pagamentos.php',
        'line' => 1140,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-pagamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fin_desbloquear_mes_submit',
      'source' => 'post',
    ),
  ),
  69 => 
  array (
    'id' => 'view_action:financeiro-pagamentos:sige_fin_isentar_lancamento_submit',
    'type' => 'view_action',
    'name' => 'sige_fin_isentar_lancamento_submit',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.isentar_multas',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce_isentar',
      'action' => 'sige_isentar_lancamento',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_pagamentos_sige_fin_isentar_lancamento_submit',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-pagamentos.php',
        'line' => 1045,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-pagamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fin_isentar_lancamento_submit',
      'source' => 'post',
    ),
  ),
  70 => 
  array (
    'id' => 'view_action:financeiro-pagamentos:sige_fin_pagar_familia_submit',
    'type' => 'view_action',
    'name' => 'sige_fin_pagar_familia_submit',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.pagar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce_familia',
      'action' => 'sige_fin_pagar_familia',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_pagamentos_sige_fin_pagar_familia_submit',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-pagamentos.php',
        'line' => 1200,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-pagamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fin_pagar_familia_submit',
      'source' => 'post',
    ),
  ),
  71 => 
  array (
    'id' => 'view_action:financeiro-pagamentos:sige_fin_pagar_submit',
    'type' => 'view_action',
    'name' => 'sige_fin_pagar_submit',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.pagar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_fin_pagar',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_pagamentos_sige_fin_pagar_submit',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/financeiro-pagamentos.php',
        'line' => 340,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-pagamentos',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fin_pagar_submit',
      'source' => 'post',
    ),
  ),
  72 => 
  array (
    'id' => 'view_action:sige_permissoes:assign_user_role',
    'type' => 'view_action',
    'name' => 'assign_user_role',
    'module' => 'usuarios',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'usuarios.gerir_permissoes',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_sige_perm_nonce',
      'action' => 'sige_permissions_ui_action',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_sige_permissoes_assign_user_role',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/system/permissions-ui.php',
        'line' => 233,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'sige_permissoes',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_perm_action',
      'source' => 'post',
      'value' => 'assign_user_role',
    ),
  ),
  73 => 
  array (
    'id' => 'view_action:sige_permissoes:save_role_permissions',
    'type' => 'view_action',
    'name' => 'save_role_permissions',
    'module' => 'usuarios',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'usuarios.gerir_permissoes',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_sige_perm_nonce',
      'action' => 'sige_permissions_ui_action',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_sige_permissoes_save_role_permissions',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/system/permissions-ui.php',
        'line' => 183,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'sige_permissoes',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_perm_action',
      'source' => 'post',
      'value' => 'save_role_permissions',
    ),
  ),
  74 => 
  array (
    'id' => 'view_action:sige_permissoes:unassign_user_role',
    'type' => 'view_action',
    'name' => 'unassign_user_role',
    'module' => 'usuarios',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'usuarios.gerir_permissoes',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_sige_perm_nonce',
      'action' => 'sige_permissions_ui_action',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_sige_permissoes_unassign_user_role',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/system/permissions-ui.php',
        'line' => 278,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.7: mutação directa de view colocada sob enforcement do Security Kernel.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'sige_permissoes',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_perm_action',
      'source' => 'post',
      'value' => 'unassign_user_role',
    ),
  ),
  75 => 
  array (
    'id' => 'wp_ajax:sige_alocar_aluno',
    'type' => 'wp_ajax',
    'name' => 'sige_alocar_aluno',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 509,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  76 => 
  array (
    'id' => 'wp_ajax:sige_alterar_senha_portal',
    'type' => 'wp_ajax',
    'name' => 'sige_alterar_senha_portal',
    'module' => 'portal',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/portal-handlers.php',
        'line' => 30,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  77 => 
  array (
    'id' => 'wp_ajax:sige_anular_lote_importacao_alunos',
    'type' => 'wp_ajax',
    'name' => 'sige_anular_lote_importacao_alunos',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-fetch-ajax.php',
        'line' => 2375,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  78 => 
  array (
    'id' => 'wp_ajax:sige_atualizar_professor',
    'type' => 'wp_ajax',
    'name' => 'sige_atualizar_professor',
    'module' => 'rh',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 493,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  79 => 
  array (
    'id' => 'wp_ajax:sige_carregar_horario_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_carregar_horario_turma',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 469,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  80 => 
  array (
    'id' => 'wp_ajax:sige_centro_eliminar',
    'type' => 'wp_ajax',
    'name' => 'sige_centro_eliminar',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/centros-helpers.php',
        'line' => 459,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  81 => 
  array (
    'id' => 'wp_ajax:sige_centro_reatribuir_servico',
    'type' => 'wp_ajax',
    'name' => 'sige_centro_reatribuir_servico',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/centros-helpers.php',
        'line' => 517,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  82 => 
  array (
    'id' => 'wp_ajax:sige_centro_salvar',
    'type' => 'wp_ajax',
    'name' => 'sige_centro_salvar',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/centros-helpers.php',
        'line' => 387,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  83 => 
  array (
    'id' => 'wp_ajax:sige_check_caixa_data',
    'type' => 'wp_ajax',
    'name' => 'sige_check_caixa_data',
    'module' => 'financeiro',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/finance-data-efectiva.php',
        'line' => 151,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  84 => 
  array (
    'id' => 'wp_ajax:sige_circular_preview',
    'type' => 'wp_ajax',
    'name' => 'sige_circular_preview',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/circulares.php',
        'line' => 101,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  85 => 
  array (
    'id' => 'wp_ajax:sige_clonar_matriz',
    'type' => 'wp_ajax',
    'name' => 'sige_clonar_matriz',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 202,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1805,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  86 => 
  array (
    'id' => 'wp_ajax:sige_comm_action',
    'type' => 'wp_ajax',
    'name' => 'sige_comm_action',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/comunicacoes-core.php',
        'line' => 300,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  87 => 
  array (
    'id' => 'wp_ajax:sige_comm_buscar',
    'type' => 'wp_ajax',
    'name' => 'sige_comm_buscar',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/comunicacoes-core.php',
        'line' => 522,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  88 => 
  array (
    'id' => 'wp_ajax:sige_comm_get_full',
    'type' => 'wp_ajax',
    'name' => 'sige_comm_get_full',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/comunicacoes-core.php',
        'line' => 332,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  89 => 
  array (
    'id' => 'wp_ajax:sige_comm_historico',
    'type' => 'wp_ajax',
    'name' => 'sige_comm_historico',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/comunicacoes-core.php',
        'line' => 532,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  90 => 
  array (
    'id' => 'wp_ajax:sige_criar_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_criar_turma',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 505,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  91 => 
  array (
    'id' => 'wp_ajax:sige_criar_usuario_staff',
    'type' => 'wp_ajax',
    'name' => 'sige_criar_usuario_staff',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 412,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1353,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  92 => 
  array (
    'id' => 'wp_ajax:sige_dica_do_dia_dismiss',
    'type' => 'wp_ajax',
    'name' => 'sige_dica_do_dia_dismiss',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/dica-do-dia.php',
        'line' => 816,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  93 => 
  array (
    'id' => 'wp_ajax:sige_editar_usuario_staff',
    'type' => 'wp_ajax',
    'name' => 'sige_editar_usuario_staff',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 413,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1508,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  94 => 
  array (
    'id' => 'wp_ajax:sige_emc_cancel',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_cancel',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 117,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  95 => 
  array (
    'id' => 'wp_ajax:sige_emc_delete',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_delete',
    'module' => 'comunicacao',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_emc_delete',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 199,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  96 => 
  array (
    'id' => 'wp_ajax:sige_emc_get_full',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_get_full',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 228,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  97 => 
  array (
    'id' => 'wp_ajax:sige_emc_process_now',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_process_now',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 259,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  98 => 
  array (
    'id' => 'wp_ajax:sige_emc_reset_template',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_reset_template',
    'module' => 'comunicacao',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_emc_reset_template',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 304,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  99 => 
  array (
    'id' => 'wp_ajax:sige_emc_retry',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_retry',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 157,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  100 => 
  array (
    'id' => 'wp_ajax:sige_emc_save_templates',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_save_templates',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 278,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  101 => 
  array (
    'id' => 'wp_ajax:sige_emc_smtp_test',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_smtp_test',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 382,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  102 => 
  array (
    'id' => 'wp_ajax:sige_emc_test_template',
    'type' => 'wp_ajax',
    'name' => 'sige_emc_test_template',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-central.php',
        'line' => 334,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  103 => 
  array (
    'id' => 'wp_ajax:sige_enviar_credenciais',
    'type' => 'wp_ajax',
    'name' => 'sige_enviar_credenciais',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-accounts.php',
        'line' => 334,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  104 => 
  array (
    'id' => 'wp_ajax:sige_excluir_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_excluir_turma',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 507,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  105 => 
  array (
    'id' => 'wp_ajax:sige_exportar_folha_staff',
    'type' => 'wp_ajax',
    'name' => 'sige_exportar_folha_staff',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 1030,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  106 => 
  array (
    'id' => 'wp_ajax:sige_get_aluno_360',
    'type' => 'wp_ajax',
    'name' => 'sige_get_aluno_360',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-fetch-ajax.php',
        'line' => 404,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  107 => 
  array (
    'id' => 'wp_ajax:sige_get_aluno_full',
    'type' => 'wp_ajax',
    'name' => 'sige_get_aluno_full',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-fetch-ajax.php',
        'line' => 216,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  108 => 
  array (
    'id' => 'wp_ajax:sige_get_alunos_export',
    'type' => 'wp_ajax',
    'name' => 'sige_get_alunos_export',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-fetch-ajax.php',
        'line' => 284,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  109 => 
  array (
    'id' => 'wp_ajax:sige_get_disciplinas_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_get_disciplinas_turma',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/academic-logic.php',
        'line' => 551,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  110 => 
  array (
    'id' => 'wp_ajax:sige_get_matriz',
    'type' => 'wp_ajax',
    'name' => 'sige_get_matriz',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1783,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  111 => 
  array (
    'id' => 'wp_ajax:sige_get_professor_detalhes',
    'type' => 'wp_ajax',
    'name' => 'sige_get_professor_detalhes',
    'module' => 'rh',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 497,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  112 => 
  array (
    'id' => 'wp_ajax:sige_get_staff_secure',
    'type' => 'wp_ajax',
    'name' => 'sige_get_staff_secure',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 962,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  113 => 
  array (
    'id' => 'wp_ajax:sige_importar_alunos_csv',
    'type' => 'wp_ajax',
    'name' => 'sige_importar_alunos_csv',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-fetch-ajax.php',
        'line' => 1363,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  114 => 
  array (
    'id' => 'wp_ajax:sige_isentar_mes',
    'type' => 'wp_ajax',
    'name' => 'sige_isentar_mes',
    'module' => 'financeiro',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/finance-core.php',
        'line' => 3325,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  115 => 
  array (
    'id' => 'wp_ajax:sige_ler_logs',
    'type' => 'wp_ajax',
    'name' => 'sige_ler_logs',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 483,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  116 => 
  array (
    'id' => 'wp_ajax:sige_listar_alunos_notas',
    'type' => 'wp_ajax',
    'name' => 'sige_listar_alunos_notas',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/academic-logic.php',
        'line' => 210,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  117 => 
  array (
    'id' => 'wp_ajax:sige_listar_alunos_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_listar_alunos_turma',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 672,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  118 => 
  array (
    'id' => 'wp_ajax:sige_listar_criterios',
    'type' => 'wp_ajax',
    'name' => 'sige_listar_criterios',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1710,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  119 => 
  array (
    'id' => 'wp_ajax:sige_listar_docentes_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_listar_docentes_turma',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 2004,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  120 => 
  array (
    'id' => 'wp_ajax:sige_listar_lotes_importacao_alunos',
    'type' => 'wp_ajax',
    'name' => 'sige_listar_lotes_importacao_alunos',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-fetch-ajax.php',
        'line' => 2486,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  121 => 
  array (
    'id' => 'wp_ajax:sige_mpesa_abertos',
    'type' => 'wp_ajax',
    'name' => 'sige_mpesa_abertos',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.mobile_payments_gerir',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretaria_geral',
    ),
    'permission_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mpesa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_sige_mpesa_abertos',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/mpesa-conciliacao.php',
        'line' => 320,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => '',
    'authorization_mode' => 'permissions',
  ),
  122 => 
  array (
    'id' => 'wp_ajax:sige_mpesa_cobrar',
    'type' => 'wp_ajax',
    'name' => 'sige_mpesa_cobrar',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.mobile_payments_gerir',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretaria_geral',
    ),
    'permission_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mpesa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_sige_mpesa_cobrar',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/mpesa-cobranca.php',
        'line' => 38,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => '',
    'authorization_mode' => 'permissions',
  ),
  123 => 
  array (
    'id' => 'wp_ajax:sige_mpesa_conciliar_manual',
    'type' => 'wp_ajax',
    'name' => 'sige_mpesa_conciliar_manual',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.mobile_payments_gerir',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretaria_geral',
    ),
    'permission_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mpesa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_sige_mpesa_conciliar_manual',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/mpesa-conciliacao.php',
        'line' => 253,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => '',
    'authorization_mode' => 'permissions',
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'tx_id',
        'table' => 'sige_mpesa_transacoes',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => true,
      ),
    ),
  ),
  124 => 
  array (
    'id' => 'wp_ajax:sige_mpesa_rejeitar',
    'type' => 'wp_ajax',
    'name' => 'sige_mpesa_rejeitar',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.mobile_payments_gerir',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretaria_geral',
    ),
    'permission_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mpesa',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_sige_mpesa_rejeitar',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/payments/mpesa-conciliacao.php',
        'line' => 296,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => '',
    'authorization_mode' => 'permissions',
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'tx_id',
        'table' => 'sige_mpesa_transacoes',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => true,
      ),
    ),
  ),
  125 => 
  array (
    'id' => 'wp_ajax:sige_notas_reaprovacao_run',
    'type' => 'wp_ajax',
    'name' => 'sige_notas_reaprovacao_run',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/notas-reaprovacao-migracao.php',
        'line' => 161,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  126 => 
  array (
    'id' => 'wp_ajax:sige_ping',
    'type' => 'wp_ajax',
    'name' => 'sige_ping',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/academic-logic.php',
        'line' => 202,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  127 => 
  array (
    'id' => 'wp_ajax:sige_planos_lancs_aluno',
    'type' => 'wp_ajax',
    'name' => 'sige_planos_lancs_aluno',
    'module' => 'financeiro',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/finance-core.php',
        'line' => 3266,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  128 => 
  array (
    'id' => 'wp_ajax:sige_portal_pagamentos',
    'type' => 'wp_ajax',
    'name' => 'sige_portal_pagamentos',
    'module' => 'financeiro',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/portal-logic.php',
        'line' => 2092,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  129 => 
  array (
    'id' => 'wp_ajax:sige_presencas_grid',
    'type' => 'wp_ajax',
    'name' => 'sige_presencas_grid',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/presencas-engine.php',
        'line' => 269,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  130 => 
  array (
    'id' => 'wp_ajax:sige_presencas_marcar',
    'type' => 'wp_ajax',
    'name' => 'sige_presencas_marcar',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/presencas-engine.php',
        'line' => 281,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  131 => 
  array (
    'id' => 'wp_ajax:sige_processar_email_queue_now',
    'type' => 'wp_ajax',
    'name' => 'sige_processar_email_queue_now',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-queue-templates.php',
        'line' => 429,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  132 => 
  array (
    'id' => 'wp_ajax:sige_processar_matricula',
    'type' => 'wp_ajax',
    'name' => 'sige_processar_matricula',
    'module' => 'secretaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 102,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 503,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  133 => 
  array (
    'id' => 'wp_ajax:sige_provisionar_individual',
    'type' => 'wp_ajax',
    'name' => 'sige_provisionar_individual',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-accounts.php',
        'line' => 302,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  134 => 
  array (
    'id' => 'wp_ajax:sige_provisionar_lote',
    'type' => 'wp_ajax',
    'name' => 'sige_provisionar_lote',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-accounts.php',
        'line' => 353,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  135 => 
  array (
    'id' => 'wp_ajax:sige_registar_acesso',
    'type' => 'wp_ajax',
    'name' => 'sige_registar_acesso',
    'module' => 'portaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 312,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  136 => 
  array (
    'id' => 'wp_ajax:sige_remover_alocacao',
    'type' => 'wp_ajax',
    'name' => 'sige_remover_alocacao',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_remover_alocacao',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 511,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  137 => 
  array (
    'id' => 'wp_ajax:sige_remover_aluno',
    'type' => 'wp_ajax',
    'name' => 'sige_remover_aluno',
    'module' => 'secretaria',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'alunos.apagar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_sige_nonce',
      'action' => 'sige_alunos_action',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_remover_aluno',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 524,
      ),
      1 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 96,
      ),
      2 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 2684,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: remover aluno passa a arquivar; hard delete legado retirado do hook.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'permissions',
    'object_guards' => 
    array (
      0 => 
      array (
        'source' => 'post',
        'field' => 'id',
        'table' => 'sige_alunos',
        'primary_key' => 'id',
        'tenant_column' => 'escola_id',
        'required' => true,
      ),
    ),
  ),
  138 => 
  array (
    'id' => 'wp_ajax:sige_remover_criterio',
    'type' => 'wp_ajax',
    'name' => 'sige_remover_criterio',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_remover_criterio',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1695,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  139 => 
  array (
    'id' => 'wp_ajax:sige_remover_matriz',
    'type' => 'wp_ajax',
    'name' => 'sige_remover_matriz',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_remover_matriz',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 197,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1768,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  140 => 
  array (
    'id' => 'wp_ajax:sige_remover_professor',
    'type' => 'wp_ajax',
    'name' => 'sige_remover_professor',
    'module' => 'rh',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_remover_professor',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 495,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  141 => 
  array (
    'id' => 'wp_ajax:sige_remover_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_remover_turma',
    'module' => 'academico',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_remover_turma',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 155,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1979,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  142 => 
  array (
    'id' => 'wp_ajax:sige_remover_usuario_staff',
    'type' => 'wp_ajax',
    'name' => 'sige_remover_usuario_staff',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_remover_usuario_staff',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 1105,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1467,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  143 => 
  array (
    'id' => 'wp_ajax:sige_repor_senha_aluno',
    'type' => 'wp_ajax',
    'name' => 'sige_repor_senha_aluno',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-accounts.php',
        'line' => 278,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  144 => 
  array (
    'id' => 'wp_ajax:sige_resetar_senha',
    'type' => 'wp_ajax',
    'name' => 'sige_resetar_senha',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_resetar_senha',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 1202,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1626,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  145 => 
  array (
    'id' => 'wp_ajax:sige_restaurar_backup',
    'type' => 'wp_ajax',
    'name' => 'sige_restaurar_backup',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 485,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  146 => 
  array (
    'id' => 'wp_ajax:sige_salvar_aluno',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_aluno',
    'module' => 'secretaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/aluno-accounts.php',
        'line' => 266,
      ),
      1 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 86,
      ),
      2 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 2170,
      ),
      3 => 
      array (
        'file' => 'includes/regime-mensalidade-core.php',
        'line' => 33,
      ),
      4 => 
      array (
        'file' => 'includes/regime-mensalidade-core.php',
        'line' => 96,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  147 => 
  array (
    'id' => 'wp_ajax:sige_salvar_config_avancado',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_config_avancado',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_salvar_config_avancado',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 229,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 481,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  148 => 
  array (
    'id' => 'wp_ajax:sige_salvar_criterio',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_criterio',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1666,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  149 => 
  array (
    'id' => 'wp_ajax:sige_salvar_docente_disciplina',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_docente_disciplina',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 161,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 2115,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  150 => 
  array (
    'id' => 'wp_ajax:sige_salvar_email_templates',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_email_templates',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-queue-templates.php',
        'line' => 389,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  151 => 
  array (
    'id' => 'wp_ajax:sige_salvar_funcionario',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_funcionario',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 710,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  152 => 
  array (
    'id' => 'wp_ajax:sige_salvar_horario_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_horario_turma',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 419,
      ),
      1 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 171,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  153 => 
  array (
    'id' => 'wp_ajax:sige_salvar_notas_bulk',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_notas_bulk',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/academic-logic.php',
        'line' => 381,
      ),
      1 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 115,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  154 => 
  array (
    'id' => 'wp_ajax:sige_salvar_professor',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_professor',
    'module' => 'rh',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 491,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  155 => 
  array (
    'id' => 'wp_ajax:sige_salvar_smtp_config',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_smtp_config',
    'module' => 'comunicacao',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_salvar_smtp_config',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-engine.php',
        'line' => 274,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  156 => 
  array (
    'id' => 'wp_ajax:sige_salvar_templates_whatsapp_financeiro',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_templates_whatsapp_financeiro',
    'module' => 'financeiro',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-engine.php',
        'line' => 169,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  157 => 
  array (
    'id' => 'wp_ajax:sige_salvar_turma',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_turma',
    'module' => 'academico',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 143,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1898,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  158 => 
  array (
    'id' => 'wp_ajax:sige_salvar_whatsapp_isolado',
    'type' => 'wp_ajax',
    'name' => 'sige_salvar_whatsapp_isolado',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 253,
      ),
      1 => 
      array (
        'file' => 'includes/whatsapp-engine.php',
        'line' => 124,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  159 => 
  array (
    'id' => 'wp_ajax:sige_settings_enter_technical_mode',
    'type' => 'wp_ajax',
    'name' => 'sige_settings_enter_technical_mode',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/settings/class-sige-settings-technical-mode.php',
        'line' => 18,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  160 => 
  array (
    'id' => 'wp_ajax:sige_settings_exit_technical_mode',
    'type' => 'wp_ajax',
    'name' => 'sige_settings_exit_technical_mode',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/settings/class-sige-settings-technical-mode.php',
        'line' => 19,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  161 => 
  array (
    'id' => 'wp_ajax:sige_settings_save',
    'type' => 'wp_ajax',
    'name' => 'sige_settings_save',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'settings.policy.can_edit',
      1 => 'configuracoes.editar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => 'nonce',
      'action' => 'sige_settings_save_v1',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_settings_save',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/settings/class-sige-settings-controller.php',
        'line' => 31,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Enforcement piloto v12.12.6: login, nonce, tenant, rate e auditoria no kernel; autorizacao fina por chave continua em SIGE_Settings_Policy::can_edit().',
    'permission_note' => 'Settings center grava dados tenant-scoped; sem escola resolvida deve falhar fechado.',
    'delegated_policy' => 'SIGE_Settings_Policy::can_edit',
    'delegated_to' => 'SIGE_Settings_Policy::can_edit',
    'authorization_mode' => 'delegated',
    'tenant_scope' => 'required_for_sige_config_writes',
  ),
  162 => 
  array (
    'id' => 'wp_ajax:sige_testar_email_template_queue',
    'type' => 'wp_ajax',
    'name' => 'sige_testar_email_template_queue',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-queue-templates.php',
        'line' => 418,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  163 => 
  array (
    'id' => 'wp_ajax:sige_testar_sms_config',
    'type' => 'wp_ajax',
    'name' => 'sige_testar_sms_config',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_testar_sms_config',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 487,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  164 => 
  array (
    'id' => 'wp_ajax:sige_testar_smtp_config',
    'type' => 'wp_ajax',
    'name' => 'sige_testar_smtp_config',
    'module' => 'comunicacao',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_testar_smtp_config',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-engine.php',
        'line' => 398,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  165 => 
  array (
    'id' => 'wp_ajax:sige_testar_wpp_config',
    'type' => 'wp_ajax',
    'name' => 'sige_testar_wpp_config',
    'module' => 'comunicacao',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_testar_wpp_config',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-engine.php',
        'line' => 247,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  166 => 
  array (
    'id' => 'wp_ajax:sige_toggle_status_staff',
    'type' => 'wp_ajax',
    'name' => 'sige_toggle_status_staff',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/ajax-handlers.php',
        'line' => 585,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  167 => 
  array (
    'id' => 'wp_ajax:sige_update_ordem_matriz',
    'type' => 'wp_ajax',
    'name' => 'sige_update_ordem_matriz',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1848,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  168 => 
  array (
    'id' => 'wp_ajax:sige_update_ordem_matriz_lote',
    'type' => 'wp_ajax',
    'name' => 'sige_update_ordem_matriz_lote',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1867,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  169 => 
  array (
    'id' => 'wp_ajax:sige_validar_acesso',
    'type' => 'wp_ajax',
    'name' => 'sige_validar_acesso',
    'module' => 'portaria',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 2822,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  170 => 
  array (
    'id' => 'wp_ajax:sige_vincular_carga',
    'type' => 'wp_ajax',
    'name' => 'sige_vincular_carga',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 499,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  171 => 
  array (
    'id' => 'wp_ajax:sige_vincular_matriz',
    'type' => 'wp_ajax',
    'name' => 'sige_vincular_matriz',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/audit-hooks.php',
        'line' => 188,
      ),
      1 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 1727,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  172 => 
  array (
    'id' => 'wp_ajax:sige_wpp_activar_recovery',
    'type' => 'wp_ajax',
    'name' => 'sige_wpp_activar_recovery',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-recovery-mode.php',
        'line' => 863,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  173 => 
  array (
    'id' => 'wp_ajax:sige_wpp_diag_force_cron',
    'type' => 'wp_ajax',
    'name' => 'sige_wpp_diag_force_cron',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-diagnostics.php',
        'line' => 111,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  174 => 
  array (
    'id' => 'wp_ajax:sige_wpp_diag_send_test',
    'type' => 'wp_ajax',
    'name' => 'sige_wpp_diag_send_test',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-diagnostics.php',
        'line' => 198,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  175 => 
  array (
    'id' => 'wp_ajax:sige_wpp_diag_status',
    'type' => 'wp_ajax',
    'name' => 'sige_wpp_diag_status',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-diagnostics.php',
        'line' => 33,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  176 => 
  array (
    'id' => 'wp_ajax:sige_wpp_teste_directo',
    'type' => 'wp_ajax',
    'name' => 'sige_wpp_teste_directo',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-engine.php',
        'line' => 215,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  177 => 
  array (
    'id' => 'wp_ajax:sige_wppc_backfill_pending',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_backfill_pending',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 573,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  178 => 
  array (
    'id' => 'wp_ajax:sige_wppc_bulk',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_bulk',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 727,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  179 => 
  array (
    'id' => 'wp_ajax:sige_wppc_cancel',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_cancel',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 155,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  180 => 
  array (
    'id' => 'wp_ajax:sige_wppc_clear_pending',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_clear_pending',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 460,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  181 => 
  array (
    'id' => 'wp_ajax:sige_wppc_delete',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_delete',
    'module' => 'comunicacao',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'delegated',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'delegated',
    'intent' => 
    array (
      'type' => 'delegated',
      'source' => 'domain_handler',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_wp_ajax_sige_wppc_delete',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 253,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'v12.12.7: acção crítica retirada de observe; controlo fino delegado ao handler canónico até lockdown de domínio específico.',
    'delegated_to' => 'existing_handler_policy',
    'authorization_mode' => 'delegated',
  ),
  182 => 
  array (
    'id' => 'wp_ajax:sige_wppc_force_cron',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_force_cron',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 520,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  183 => 
  array (
    'id' => 'wp_ajax:sige_wppc_get_full',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_get_full',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 96,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  184 => 
  array (
    'id' => 'wp_ajax:sige_wppc_pending_receipts_list',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_pending_receipts_list',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 294,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  185 => 
  array (
    'id' => 'wp_ajax:sige_wppc_pull_forward',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_pull_forward',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 491,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  186 => 
  array (
    'id' => 'wp_ajax:sige_wppc_retry',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_retry',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 199,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  187 => 
  array (
    'id' => 'wp_ajax:sige_wppc_send_link_now',
    'type' => 'wp_ajax',
    'name' => 'sige_wppc_send_link_now',
    'module' => 'comunicacao',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/whatsapp-central.php',
        'line' => 416,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  188 => 
  array (
    'id' => 'wp_hook:admin_init',
    'type' => 'wp_hook',
    'name' => 'admin_init',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/admin-shell.php',
        'line' => 1524,
      ),
      1 => 
      array (
        'file' => 'includes/centros-helpers.php',
        'line' => 87,
      ),
      2 => 
      array (
        'file' => 'includes/core/class-sige-core.php',
        'line' => 13,
      ),
      3 => 
      array (
        'file' => 'includes/core/class-sige-core.php',
        'line' => 17,
      ),
      4 => 
      array (
        'file' => 'includes/curriculum-engine.php',
        'line' => 372,
      ),
      5 => 
      array (
        'file' => 'includes/db-handler.php',
        'line' => 175,
      ),
      6 => 
      array (
        'file' => 'includes/db-migration-engine.php',
        'line' => 177,
      ),
      7 => 
      array (
        'file' => 'includes/feature-registry-sync.php',
        'line' => 149,
      ),
      8 => 
      array (
        'file' => 'includes/finance-data-efectiva.php',
        'line' => 64,
      ),
      9 => 
      array (
        'file' => 'includes/financeiro-historico-aluno-pro.php',
        'line' => 1001,
      ),
      10 => 
      array (
        'file' => 'includes/hub/class-sige-hub-billing.php',
        'line' => 27,
      ),
      11 => 
      array (
        'file' => 'includes/hub/class-sige-hub-client.php',
        'line' => 35,
      ),
      12 => 
      array (
        'file' => 'includes/hub/class-sige-hub-client.php',
        'line' => 36,
      ),
      13 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 731,
      ),
      14 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 734,
      ),
      15 => 
      array (
        'file' => 'includes/jardim-handlers.php',
        'line' => 1033,
      ),
      16 => 
      array (
        'file' => 'includes/multitenancy.php',
        'line' => 393,
      ),
      17 => 
      array (
        'file' => 'includes/notas-reaprovacao-migracao.php',
        'line' => 139,
      ),
      18 => 
      array (
        'file' => 'includes/notification-humanization-pro.php',
        'line' => 397,
      ),
      19 => 
      array (
        'file' => 'includes/permissions-layer.php',
        'line' => 623,
      ),
      20 => 
      array (
        'file' => 'includes/permissions-layer.php',
        'line' => 912,
      ),
      21 => 
      array (
        'file' => 'includes/regime-mensalidade-core.php',
        'line' => 32,
      ),
      22 => 
      array (
        'file' => 'includes/regime-mensalidade-core.php',
        'line' => 95,
      ),
      23 => 
      array (
        'file' => 'includes/security-baseline-pro.php',
        'line' => 419,
      ),
      24 => 
      array (
        'file' => 'includes/security-baseline-pro.php',
        'line' => 423,
      ),
      25 => 
      array (
        'file' => 'includes/security-baseline-pro.php',
        'line' => 547,
      ),
      26 => 
      array (
        'file' => 'includes/security-kernel.php',
        'line' => 515,
      ),
      27 => 
      array (
        'file' => 'includes/security-kernel.php',
        'line' => 516,
      ),
      28 => 
      array (
        'file' => 'includes/security-roles.php',
        'line' => 244,
      ),
      29 => 
      array (
        'file' => 'includes/security-scope-guard.php',
        'line' => 295,
      ),
      30 => 
      array (
        'file' => 'includes/versioning.php',
        'line' => 92,
      ),
      31 => 
      array (
        'file' => 'includes/whatsapp-templates-conversacional.php',
        'line' => 1118,
      ),
      32 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 443,
      ),
      33 => 
      array (
        'file' => 'sige-softgenial.php',
        'line' => 536,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_wp_hook',
  ),
  189 => 
  array (
    'id' => 'wp_hook:parse_request',
    'type' => 'wp_hook',
    'name' => 'parse_request',
    'module' => 'sistema',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/security-baseline-pro.php',
        'line' => 132,
      ),
      1 => 
      array (
        'file' => 'includes/security-kernel.php',
        'line' => 517,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_wp_hook',
  ),
  190 => 
  array (
    'id' => 'wp_hook:send_headers',
    'type' => 'wp_hook',
    'name' => 'send_headers',
    'module' => 'portaria',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/portaria-camera-safe-page.php',
        'line' => 35,
      ),
      1 => 
      array (
        'file' => 'includes/security-baseline-pro.php',
        'line' => 418,
      ),
      2 => 
      array (
        'file' => 'includes/security-baseline-pro.php',
        'line' => 422,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_wp_hook',
  ),
  191 => 
  array (
    'id' => 'wp_hook:template_redirect',
    'type' => 'wp_hook',
    'name' => 'template_redirect',
    'module' => 'comunicacao',
    'risk' => 'medium',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/email-engine.php',
        'line' => 658,
      ),
      1 => 
      array (
        'file' => 'includes/finance-core.php',
        'line' => 3489,
      ),
      2 => 
      array (
        'file' => 'includes/portaria-camera-safe-page.php',
        'line' => 323,
      ),
      3 => 
      array (
        'file' => 'includes/security-kernel.php',
        'line' => 518,
      ),
      4 => 
      array (
        'file' => 'includes/security-roles.php',
        'line' => 105,
      ),
      5 => 
      array (
        'file' => 'includes/security-roles.php',
        'line' => 182,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Registado no Security Kernel em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_wp_hook',
  ),
  192 => 
  array (
    'id' => 'query_handler:sige_dev_print',
    'type' => 'query_handler',
    'name' => 'sige_dev_print',
    'module' => 'financeiro',
    'risk' => 'high',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.cobrancas_ver',
      1 => 'financeiro.cobrancas_gerir',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_secretario',
      2 => 'sige_financeiro',
    ),
    'permission_mode' => 'kernel_and_legacy_compatible',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_dev_print',
      'source' => 'get',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_query_handler_sige_dev_print',
      'max' => 30,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/finance-devedores-pdf.php',
        'line' => 187,
      ),
      1 => 
      array (
        'file' => 'includes/finance-devedores-pdf.php',
        'line' => 194,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Mapa de Cobranca (lista de devedores) em PDF: query handler protegido por nonce, permissao, tenant, rate limit e auditoria; dispatch antecipado multi-hook. Leitura pura, nunca escreve em tabelas financeiras.',
    'permission_note' => 'Documento financeiro de cobranca; usa exactamente as permissoes da pagina Central de Cobrancas (financeiro.cobrancas_ver ou financeiro.cobrancas_gerir).',
    'delegated_to' => '',
    'runtime_hooks' => 
    array (
      0 => 'admin_init',
      1 => 'parse_request',
      2 => 'template_redirect',
    ),
    'runtime_priority' => -1000,
    'runtime_dispatch' => 'early_multi_hook',
  ),
  193 => 
  array (
    'id' => 'admin_post:sige_mfa_confirm',
    'type' => 'admin_post',
    'name' => 'sige_mfa_confirm',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
      0 => 'sistema.estado_ver',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_admin_ti',
    ),
    'permission_mode' => 'kernel_and_legacy_compatible',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mfa_confirm',
      'source' => 'request',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_mfa_confirm',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/security-mfa-stepup.php',
        'line' => 179,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'MFA de operacao (step-up) v12.12.10: confirmacao de identidade por OTP antes de operacoes criticas. Endpoint protegido por login e nonce; observado pelo kernel.',
    'permission_note' => 'Disponivel a utilizador autenticado com desafio MFA pendente (perfis criticos sige_director, sige_admin_ti).',
    'delegated_to' => '',
  ),
  array (
    'id' => 'admin_post:sige_mfa_totp_enroll',
    'type' => 'admin_post',
    'name' => 'sige_mfa_totp_enroll',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
      0 => 'sistema.estado_ver',
    ),
    'legacy_caps' => 
    array (
      0 => 'sige_director',
      1 => 'sige_admin_ti',
    ),
    'permission_mode' => 'kernel_and_legacy_compatible',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mfa_totp_enroll',
      'source' => 'request',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_mfa_totp_enroll',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/security-mfa-totp.php',
        'line' => 548,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'MFA de operacao (TOTP) v12.12.11: inscricao, confirmacao e desactivacao da aplicacao autenticadora do proprio utilizador. Endpoint protegido por login e nonce; cada utilizador afecta apenas a sua conta (get_current_user_id). Observado pelo kernel.',
    'permission_note' => 'Disponivel a utilizador autenticado para gerir o seu proprio segundo factor (perfis criticos sige_director, sige_admin_ti); cada utilizador so altera a sua propria conta.',
    'delegated_to' => '',
  ),
  array (
    'id' => 'admin_post:sige_mfa_settings_save',
    'type' => 'admin_post',
    'name' => 'sige_mfa_settings_save',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => false,
    'permissions' => 
    array (
      0 => 'sistema.estado_ver',
    ),
    'legacy_caps' => 
    array (
      0 => 'administrator',
    ),
    'permission_mode' => 'kernel_and_legacy_compatible',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_mfa_settings',
      'source' => 'request',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_mfa_settings_save',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/security-mfa-settings.php',
        'line' => 207,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Painel de controlo de seguranca (MFA) v12.12.13: liga/desliga step-up, reposicao automatica e modo estrito, e escolhe os perfis abrangidos. Acesso restrito ao administrador WordPress real (sige_is_real_wp_admin_user); o Admin IT e os outros perfis nativos do SIGE sao recusados no menu, no render e na gravacao. Cada alteracao e auditada, com enfase em desligar o step-up. Observado pelo kernel.',
    'permission_note' => 'Restrito ao ADMIN Super (perfil administrator WordPress ou super admin de multisite). Nunca disponivel a sige_admin_ti nem a outros perfis nativos do SIGE, mesmo com manage_options herdado (retirado pelo filtro de hardening).',
    'delegated_to' => '',
  ),
  196 => 
  array (
    'id' => 'view_action:financeiro-aprovacoes:sige_fin_aprovacao_decidir',
    'type' => 'view_action',
    'name' => 'sige_fin_aprovacao_decidir',
    'module' => 'financeiro',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'financeiro.estornar',
      1 => 'financeiro.caixa_reabrir',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_fin_aprovacao_decidir',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_view_action_financeiro_aprovacoes_sige_fin_aprovacao_decidir',
      'max' => 20,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'admin/finance/aprovacoes-view.php',
        'line' => 33,
      ),
    ),
    'source_manifest_status' => 'critical_actions_lockdown',
    'notes' => 'v12.12.21 Fase 7 incr 2: endpoint de decisao da regra de quatro-olhos (aprovar/rejeitar estornos e reaberturas). Acesso a quem detem financeiro.estornar OU financeiro.caixa_reabrir; o codigo aplica a permissao especifica por tipo e proibe a auto-aprovacao.',
    'delegated_to' => '',
    'page' => 'sige-app',
    'view' => 'financeiro-aprovacoes',
    'method' => 'POST',
    'status' => 'critical_actions_lockdown',
    'discriminator' => 
    array (
      'field' => 'sige_fin_aprovacao_decidir',
      'source' => 'post',
    ),
  ),
  array (
    'id' => 'admin_post:sige_privacidade_exportar',
    'type' => 'admin_post',
    'name' => 'sige_privacidade_exportar',
    'module' => 'sistema',
    'risk' => 'high',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'privacidade.acesso_exportar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_privacidade_exportar',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_privacidade_exportar',
      'max' => 10,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/privacy/pii-dossier-export.php',
        'line' => 16,
      ),
    ),
    'source_manifest_status' => 'fase8_acesso_portabilidade',
    'notes' => 'v12.12.24 Fase 8 incr 2: exportacao do dossie de dados pessoais do aluno (direito de acesso e portabilidade). So leitura da base; transmite JSON estruturado. Acesso a quem detem privacidade.acesso_exportar; isolamento por escola; cada exportacao auditada. Kernel aplica permissao, nonce e rate limit.',
    'delegated_to' => '',
  ),
  array (
    'id' => 'admin_post:sige_privacidade_apagar',
    'type' => 'admin_post',
    'name' => 'sige_privacidade_apagar',
    'module' => 'sistema',
    'risk' => 'critical',
    'public' => false,
    'mode' => 'enforce',
    'tenant_required' => true,
    'permissions' => 
    array (
      0 => 'privacidade.apagamento_executar',
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'permissions',
    'authorization_mode' => 'permissions',
    'intent' => 
    array (
      'type' => 'nonce',
      'field' => '_wpnonce',
      'action' => 'sige_privacidade_apagar',
      'source' => 'post',
    ),
    'rate_limit' => 
    array (
      'key' => 'sk_admin_post_sige_privacidade_apagar',
      'max' => 5,
      'window' => 300,
    ),
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/privacy/pii-anonimizar-handler.php',
        'line' => 19,
      ),
    ),
    'source_manifest_status' => 'fase8_apagamento_anonimizacao',
    'notes' => 'v12.12.25 Fase 8 incr 3: apagamento por anonimizacao do aluno (direito ao apagamento). Operacao destrutiva e irreversivel. Redige PII catalogada, preserva numero de processo, aluno_id e valores financeiros/academicos. Acesso a quem detem privacidade.apagamento_executar; confirmacao em dois passos por numero de processo no servidor; isolamento por escola; auditoria antes e depois. Kernel aplica permissao, nonce e rate limit.',
    'delegated_to' => '',
  ),
  array (
    'id' => 'wp_ajax:sige_presencas_marcar_lote',
    'type' => 'wp_ajax',
    'name' => 'sige_presencas_marcar_lote',
    'module' => 'academico',
    'risk' => 'high',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => true,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/presencas-engine.php',
        'line' => 446,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Correccao de presencas em lote (caminho rapido da secretaria, v12.15.17). Mesma guarda do handler de celula: permissao, nonce, escola_id, validacao por item e tecto de 1000. Registado em modo observe para lockdown progressivo na Fase 2.',
    'delegated_to' => '',
  ),
  array (
    'id' => 'wp_ajax:sige_pesquisa_global',
    'type' => 'wp_ajax',
    'name' => 'sige_pesquisa_global',
    'module' => 'pesquisa',
    'risk' => 'low',
    'public' => false,
    'mode' => 'observe',
    'tenant_required' => true,
    'permissions' => 
    array (
    ),
    'legacy_caps' => 
    array (
    ),
    'permission_mode' => 'observe',
    'intent' => 
    array (
      'type' => 'observed',
      'source' => 'manifest',
      'expected' => 'nonce_expected',
    ),
    'rate_limit' => NULL,
    'audit' => false,
    'audit_on_observe' => false,
    'registrations' => 
    array (
      0 => 
      array (
        'file' => 'includes/sige-pesquisa-global.php',
        'line' => 29,
      ),
    ),
    'source_manifest_status' => 'baseline_pending_phase_1_enforcement',
    'notes' => 'Pesquisa global so leitura (P3, v12.15.21). Caixa unica na barra de topo. Tres grupos escopados por permissao (alunos.ver, academico.turmas_ver, financeiro.extractos_ver/pagar), escola_id em todas as queries, nonce global. Registado em modo observe.',
    'delegated_to' => '',
  ),
);
    }
}
