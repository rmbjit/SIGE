# SPEC-HUB-SAUDE - Painel de saúde por tenant no SigeHub

**Estado:** lado cliente IMPLEMENTADO (v12.11.9.90); falta o ecrã no Hub.
**Valor:** reduzir o tempo de suporte do Rogério: ver de um relance, sem
entrar em cada wp-admin, que escola precisa de atenção.

## O que o plugin JÁ envia (heartbeat enriquecido nesta versão)
Campos novos no payload, todos defensivos (null quando a tabela falta):
```
wpp_queue_pendentes        fila WhatsApp por enviar
wpp_queue_falhadas_24h     falhas de envio nas últimas 24h
email_queue_pendentes      fila de email por enviar
cron_wpp_proximo           próxima execução do processador WhatsApp (UTC)
cron_diario_proximo        próxima execução do evento diário (UTC)
login_locks_24h            bloqueios do escudo de login nas últimas 24h
```
Mais os já existentes: plugin_version, wp_version, php_version,
mysql_version, alunos_count, pagamentos_30d, errors_24h, timezone.

## Ecrã a construir no SigeHub (codebase do Hub, parent site)

### Vista única: tabela de tenants com semáforo
Uma linha por escola; coluna Estado calculada por regras:
- VERMELHO: heartbeat em atraso > 24h | cron_wpp_proximo no passado há > 2h
  | wpp_queue_falhadas_24h > 20 | errors_24h > 50;
- AMARELO: plugin_version desactualizada face à última release | php_version
  < 8.1 | wpp_queue_pendentes > 200 | login_locks_24h > 10;
- VERDE: tudo o resto.
Colunas: escola, versão, PHP, fila WPP (pend/falhas), próximo cron, alunos,
último heartbeat. Clique abre o JSON completo do último payload.

### Alertas (fase 2 do ecrã)
Email diário ao admin do Hub com as escolas em vermelho. Reutiliza o
heartbeat guardado; zero chamadas novas aos tenants.

## Critério de pronto
Painel lista as escolas reais com semáforo correcto; cortar o cron numa
escola de teste fá-la passar a vermelho no ciclo seguinte.
