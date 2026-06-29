# RUNBOOK - Backups verificados (CloudPanel/Hostinger VPS)

Tempo de instalação: 15 minutos. Fazer uma vez; depois é só vigiar o email.

## 1. Preparar a pasta e as credenciais
```bash
mkdir -p /home/sige-backups/diarios && chmod 700 /home/sige-backups
cp tools/backup/sige-backup.sh tools/backup/sige-restore-test.sh /home/sige-backups/
chmod 700 /home/sige-backups/*.sh
```

Criar `/home/sige-backups/.my.cnf` com um utilizador MySQL SÓ DE LEITURA para
os dumps (mais DROP/CREATE apenas na base de teste de restauro):
```ini
[client]
user=sige_backup
password=GERAR-PASSWORD-FORTE
host=localhost
```
```bash
chmod 600 /home/sige-backups/.my.cnf
```
No MySQL (uma vez):
```sql
CREATE USER 'sige_backup'@'localhost' IDENTIFIED BY 'GERAR-PASSWORD-FORTE';
GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, PROCESS ON *.* TO 'sige_backup'@'localhost';
GRANT ALL PRIVILEGES ON sige_restore_test.* TO 'sige_backup'@'localhost';
FLUSH PRIVILEGES;
```

## 2. Configurar as escolas
Editar a lista `BANCOS` em `sige-backup.sh` com os nomes REAIS das bases
(ver no CloudPanel > Databases). Confirmar os nomes das tabelas sentinela
caso o prefixo não seja `wp_` (o script procura por sufixo, tolera prefixos).

## 3. Agendar
```bash
crontab -e
```
```
MAILTO=teu-email@dominio
30 2 * * * /home/sige-backups/sige-backup.sh >> /home/sige-backups/cron.out 2>&1
30 3 * * 0 /home/sige-backups/sige-restore-test.sh >> /home/sige-backups/cron.out 2>&1
```
O `MAILTO` garante alerta por email sempre que um script termina com erro.

## 4. Cópia para FORA do servidor (obrigatória)
Um backup no mesmo disco do servidor não sobrevive ao pior dia. Opção mais
simples e gratuita até 10 GB: rclone para um remoto (Drive/Backblaze/S3):
```bash
apt install rclone && rclone config   # criar remoto chamado "offsite"
```
Acrescentar ao fim do cron diário (03:10):
```
10 3 * * * rclone copy /home/sige-backups/diarios offsite:sige-backups --max-age 48h >> /home/sige-backups/cron.out 2>&1
```

## 5. Primeiro ciclo (validação manual)
```bash
/home/sige-backups/sige-backup.sh && echo BACKUP-OK
/home/sige-backups/sige-restore-test.sh && echo RESTAURO-OK
tail -30 /home/sige-backups/backup.log
```
Os dois "OK" no ecrã + listagem dos .sql.gz em `diarios/` = item 1.1 fechado.

## 6. Em caso de desastre (restauro real)
```bash
gunzip -c /home/sige-backups/diarios/ESCOLA-DATA.sql.gz | mysql --defaults-extra-file=/home/sige-backups/.my.cnf NOME_DA_BASE
```
Nota: para restauro real o utilizador precisa de privilégios de escrita nessa
base; usar nesse momento o utilizador admin do CloudPanel, não o sige_backup.
