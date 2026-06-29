# PAINEL DE MELHORIAS - SIGE SoftGenial

Documento vivo. A percentagem mede o caminho até "feito e provado em produção":
código pronto e testado no pacote conta a maior parte; o que depende de acções
no servidor/live (instalar cron, criar repositório, activar opção, validar com
dados reais) fica explícito em "Falta".

Última actualização: v12.11.9.93 (Sprint 4 parcial).

## Bloco 1 - Proteger o que factura

| # | Item | % | Estado |
|---|------|---|--------|
| 1.1 | Backups verificados por escola | 70% | Toolkit completo em tools/backup/ (script de dump por BD, teste de restauro automático, retenção, runbook). Falta: instalar o cron na VPS e correr o primeiro ciclo real. |
| 1.2a | Rate limit no login | 95% | includes/security-login-shield.php activo por defeito: 5 falhas/10 min por utilizador+IP, 20/h por IP, registo em auditoria, constante de emergência. Falta: observar 1 semana em live. |
| 1.2b | 2FA por email (Director/Admin TI) | 75% | Implementado no mesmo módulo, OFF por defeito (option sige_2fa_email=on para ligar). Admin WP real isento por segurança, salvo opção explícita. Falta: teste live com SMTP Zoho e decisão de roll-out. |
| 1.3 | Git + CI de release | 85% | .gitignore, workflow GitHub Actions (lint integral + gates + ZIP como artefacto) e guia docs/dev/GIT-CI.md prontos. Falta: criar o repositório privado e fazer o primeiro push. |

## Bloco 2 - Baixar o custo de cada release

| # | Item | % | Estado |
|---|------|---|--------|
| 2.1 | Regra do escuteiro nos monólitos | 35% | Primeira extracção REAL concluída e verificada: Presenças vive em assets/views/presencas.css/js com config via window.SIGE_PRESENCAS_CFG; padrão provado e protegido por invariantes. As restantes views seguem ao ritmo dos toques. |
| 2.2 | Consolidação do style.css | 40% | Ferramenta css-consolidar.php construída e candidato gerado (281 KB para 242 KB, 72 grupos fundidos, cascata preservada), provado como NÃO carregado. Falta: screenshots de referência e a troca validada visualmente (procedimento com rollback de 10s no relatório). |
| 2.3 | Piso PHP 8.1 | 50% | tools/check-php-compat.php pronto; heartbeat já reporta php_version de cada tenant ao Hub. Falta: confirmar 8.1+ em todos os tenants e aplicar o patch preparado (remove fin-fsm-php74). |
| 2.4 | Status canónico na escrita | 65% | Helper sige_status_canonico() aplicado nos pontos de gravação de aluno/matrícula; tools/migrar-status-canonico.php (dry-run por defeito, auditado) pronto. Falta: correr a migração em live com backup. |
| 2.5 | Pacote de regressão vivo | 100% | tools/smoke-regression-pack.php: asserts funcionais portados dos gates 71/27/80 + invariantes financeiros, sem literais de versão. Passa integralmente. |

## Bloco 3 - Crescer o produto

| # | Item | % | Estado |
|---|------|---|--------|
| 3.1 | M-Pesa / e-Mola com conciliação | 72% | M-Pesa completo + e-Mola webhook-first no MESMO funil canónico (provider-aware, provado por gate), ecrã unificado "Pagamentos Móveis" e parser monetário blindado (bug europeu corrigido). Falta: ciclo sandbox Vodacom, produção supervisionada e afinar o cliente e-Mola à doc oficial da Movitel. |
| 3.2 | Presenças académicas via Portaria | 88% | Módulo completo + Mapa Mensal de Assiduidade em formato oficial (A4 paisagem, cabeçalho da escola, M/F, totais por dia, assinaturas), 23 testes verdes. Falta: validação com dados reais e o ajuste fino ao modelo MINEDH em papel (acesso nº 2). |
| 3.3 | Circulares WhatsApp | 85% | Módulo completo: nova página Circulares (turma/escola), pré-visualização com contagem real, deduplicação por família, envio pela fila existente com guardrails intactos. Falta: envio real de teste em live. |
| 3.4 | Relatório mensal automático à Direcção | 90% | Cron + email prontos e agora com interruptor e campo de destinatários na Saúde do Sistema. Falta: validar 1 ciclo real no dia 1. |

## Bloco 4 - Decisões estratégicas

| # | Item | % | Estado |
|---|------|---|--------|
| 4.1 | Unificar caminhos de actualização | 70% | ADR-002 decidido (Hub primário, clássico só sem Hub) e gate implementado no update-checker clássico. Falta: validar um ciclo de update real via Hub. |
| 4.2 | Política i18n | 100% | ADR-001: PT-MZ é a língua do produto; sem retrofit de i18n; critério objectivo de reavaliação documentado. |
| 4.3 | Painel de saúde por tenant no Hub | 90% | FEITO sobre o codebase real: SigeHub v3.4.0 com painel "Saúde operacional por escola" na Frota (semáforo conforme a SPEC, motivos por escola, payload em um clique, leitura sem N+1) + alerta diário por email das escolas em vermelho (OFF por defeito, tab Canais) + 14 testes do semáforo. Falta: deploy no site-mãe e observar 1 ciclo real de heartbeats. |

## Progresso global do programa

Média ponderada por esforço: **~79%** do programa completo.
Sprint 4 entregue em duas partes: v12.11.9.93 do plugin (e-Mola + mapa
oficial) e SigeHub v3.4.0 (painel multi-tenant de saúde + alerta diário).
Restantes acessos pendentes: modelo MINEDH em papel, sandbox Vodacom,
doc e-Mola, screenshots CSS, cron de backups, push Git, dry-runs.
