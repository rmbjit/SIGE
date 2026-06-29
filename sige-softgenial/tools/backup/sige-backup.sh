#!/usr/bin/env bash
# ============================================================================
# SIGE SoftGenial - Backup nocturno verificado por escola (CloudPanel/Hostinger)
#
# O que faz, por cada base de dados listada:
#   1. mysqldump consistente (single-transaction) comprimido;
#   2. VERIFICA o ficheiro: gzip íntegro + presença das tabelas sentinela
#      (sige_alunos, sige_lancamentos, sige_pagamentos) + tamanho mínimo;
#   3. aplica retenção (14 diários por defeito) e remove só após novo backup OK;
#   4. escreve relatório em $LOG e devolve exit!=0 se QUALQUER escola falhar
#      (para o cron enviar email de alerta via MAILTO).
#
# Instalação (uma vez, como root ou utilizador do site):
#   mkdir -p /home/sige-backups && chmod 700 /home/sige-backups
#   cp tools/backup/sige-backup.sh /home/sige-backups/ && chmod 700 /home/sige-backups/sige-backup.sh
#   Editar a lista BANCOS abaixo e criar /home/sige-backups/.my.cnf (ver runbook).
#   Cron (02:30, com alerta por email em falha):
#     MAILTO=teu-email@dominio
#     30 2 * * * /home/sige-backups/sige-backup.sh >> /home/sige-backups/cron.out 2>&1
# ============================================================================
set -u

# ── CONFIGURAR AQUI ─────────────────────────────────────────────────────────
# Uma linha por escola: "nome_da_base etiqueta"
BANCOS=(
  "db_cicasacolorida casacolorida"
  "db_lmuhalaze lmuhalaze"
  "db_malisa malisa"
  "db_demo demo"
  "db_softgenial_hub hub"
)
DEST="/home/sige-backups/diarios"
RETENCAO_DIAS=14
MYCNF="/home/sige-backups/.my.cnf"     # credenciais (ver runbook; nunca em claro aqui)
TAM_MINIMO_BYTES=20000                  # backup de escola real abaixo disto = suspeito
SENTINELAS=("sige_alunos" "sige_lancamentos" "sige_pagamentos")
LOG="/home/sige-backups/backup.log"
# ────────────────────────────────────────────────────────────────────────────

HOJE=$(date +%F)
AGORA=$(date '+%F %T')
mkdir -p "$DEST"
FALHAS=0

log() { echo "[$AGORA] $1" | tee -a "$LOG"; }

for entrada in "${BANCOS[@]}"; do
  DB=$(echo "$entrada" | awk '{print $1}')
  TAG=$(echo "$entrada" | awk '{print $2}')
  FICH="$DEST/${TAG}-${HOJE}.sql.gz"

  # 1) Dump consistente
  if ! mysqldump --defaults-extra-file="$MYCNF" \
        --single-transaction --quick --routines --triggers \
        --no-tablespaces "$DB" 2>>"$LOG" | gzip > "$FICH"; then
    log "FALHA dump ${TAG} (${DB})"; FALHAS=$((FALHAS+1)); rm -f "$FICH"; continue
  fi

  # 2) Verificações de integridade
  if ! gzip -t "$FICH" 2>>"$LOG"; then
    log "FALHA gzip corrompido ${TAG}"; FALHAS=$((FALHAS+1)); continue
  fi
  TAM=$(stat -c%s "$FICH")
  if [ "$TAM" -lt "$TAM_MINIMO_BYTES" ]; then
    log "FALHA tamanho suspeito ${TAG} (${TAM} bytes)"; FALHAS=$((FALHAS+1)); continue
  fi
  SENTINELAS_OK=1
  for T in "${SENTINELAS[@]}"; do
    if ! zgrep -q "CREATE TABLE.*${T}" "$FICH"; then
      log "FALHA sentinela ausente ${TAG}: ${T}"; SENTINELAS_OK=0
    fi
  done
  if [ "$SENTINELAS_OK" -ne 1 ]; then FALHAS=$((FALHAS+1)); continue; fi

  log "OK ${TAG} (${DB}) -> $(basename "$FICH") ${TAM} bytes"

  # 3) Retenção: remover só os antigos desta etiqueta (após backup OK de hoje)
  find "$DEST" -name "${TAG}-*.sql.gz" -mtime +"$RETENCAO_DIAS" -print -delete >> "$LOG" 2>&1
done

if [ "$FALHAS" -gt 0 ]; then
  log "RESULTADO: ${FALHAS} escola(s) FALHARAM - intervir hoje."
  exit 1
fi
log "RESULTADO: todas as escolas com backup verificado."
exit 0
