#!/usr/bin/env bash
# ============================================================================
# SIGE SoftGenial - Teste semanal de RESTAURO real
#
# Backup que nunca foi restaurado é uma esperança, não um backup.
# Este script pega no dump mais recente de UMA escola (rotativa por semana),
# restaura-o numa base temporária, conta linhas das tabelas críticas e
# compara com mínimos de sanidade. No fim, apaga a base temporária.
#
# Cron sugerido (domingo 03:30):
#   30 3 * * 0 /home/sige-backups/sige-restore-test.sh >> /home/sige-backups/cron.out 2>&1
# ============================================================================
set -u

DEST="/home/sige-backups/diarios"
MYCNF="/home/sige-backups/.my.cnf"
ETIQUETAS=("casacolorida" "lmuhalaze" "malisa" "demo")
LOG="/home/sige-backups/restore-test.log"

log() { echo "[$(date '+%F %T')] $1" | tee -a "$LOG"; }

# Rotação: semana do ano escolhe a escola (todas testadas ao longo do mês)
SEM=$(date +%V)
IDX=$(( SEM % ${#ETIQUETAS[@]} ))
TAG="${ETIQUETAS[$IDX]}"

FICH=$(ls -1t "$DEST/${TAG}-"*.sql.gz 2>/dev/null | head -1)
if [ -z "${FICH:-}" ]; then log "FALHA: nenhum backup encontrado para ${TAG}"; exit 1; fi

DBTMP="sige_restore_test"
log "A testar restauro de ${TAG} a partir de $(basename "$FICH")"

mysql --defaults-extra-file="$MYCNF" -e "DROP DATABASE IF EXISTS ${DBTMP}; CREATE DATABASE ${DBTMP} CHARACTER SET utf8mb4;" || { log "FALHA: criar BD temporária"; exit 1; }

if ! gunzip -c "$FICH" | mysql --defaults-extra-file="$MYCNF" "$DBTMP" 2>>"$LOG"; then
  log "FALHA: restauro de ${TAG} abortou"; exit 1
fi

# Sanidade: tabelas críticas existem e têm conteúdo plausível
ERROS=0
for PAR in "sige_alunos:1" "sige_lancamentos:1" "sige_pagamentos:0"; do
  T="${PAR%%:*}"; MIN="${PAR##*:}"
  N=$(mysql --defaults-extra-file="$MYCNF" -N -e "SELECT COUNT(*) FROM ${DBTMP}.\`wp_${T}\`" 2>/dev/null || echo "ERR")
  if [ "$N" = "ERR" ]; then
    # tenta sem prefixo wp_ (algumas instalações usam prefixo próprio)
    N=$(mysql --defaults-extra-file="$MYCNF" -N -e "SHOW TABLES FROM ${DBTMP} LIKE '%${T}'" | head -1)
    if [ -z "$N" ]; then log "FALHA: tabela ${T} ausente no restauro"; ERROS=$((ERROS+1)); continue; fi
    N=$(mysql --defaults-extra-file="$MYCNF" -N -e "SELECT COUNT(*) FROM ${DBTMP}.\`${N}\`")
  fi
  if [ "$N" -lt "$MIN" ]; then log "AVISO: ${T} com ${N} linhas (mínimo ${MIN})"; fi
  log "OK ${T}: ${N} linhas"
done

mysql --defaults-extra-file="$MYCNF" -e "DROP DATABASE IF EXISTS ${DBTMP};"

if [ "$ERROS" -gt 0 ]; then log "RESULTADO: restauro de ${TAG} com ${ERROS} erro(s)."; exit 1; fi
log "RESULTADO: restauro de ${TAG} verificado com sucesso."
exit 0
