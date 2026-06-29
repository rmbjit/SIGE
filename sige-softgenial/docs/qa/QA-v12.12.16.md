# QA - v12.12.16 (Correccao: tabela do ledger e ecra resiliente)

Execucao real:

- php -l a todos os ficheiros alterados: sem erros (migracao, finance-ledger, gate, smoke).
- Smoke do ledger: 14/14 (inclui resiliencia: sem tabela, append=false, verify vazio, lista vazia, sem erro; mais a cadeia HMAC e a deteccao de adulteracao/remocao/chave).
- Gate do ledger: verde, reforcado (exige SCHEMA_VERSION subida e guard de existencia nos 4 pontos).
- Corredor completo: 56/56 verde.
- Superficie de accoes: 196 (inalterada). md5 das regras de calculo intactos.
- Zero travessoes. Raiz canonica.

## Criar a tabela manualmente (alternativa de emergencia)
Se for preciso criar a tabela sem actualizar nem reactivar, correr em SQL (substituir
o prefixo real das tabelas):

CREATE TABLE PREFIXO_sige_fin_ledger (
  id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  escola_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 1,
  seq BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
  event_type VARCHAR(60) NOT NULL,
  entidade VARCHAR(60) NOT NULL DEFAULT '',
  entidade_id BIGINT(20) NOT NULL DEFAULT 0,
  montante DECIMAL(15,2) DEFAULT NULL,
  actor_user_id BIGINT(20) NOT NULL DEFAULT 0,
  actor_nome VARCHAR(120) NOT NULL DEFAULT '',
  ocorrido_em DATETIME NOT NULL,
  payload LONGTEXT DEFAULT NULL,
  prev_hash CHAR(64) NOT NULL DEFAULT '',
  hash CHAR(64) NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uniq_escola_seq (escola_id, seq),
  KEY idx_event_type (event_type),
  KEY idx_ocorrido_em (ocorrido_em),
  KEY idx_escola_id (escola_id)
) DEFAULT CHARSET=utf8mb4;
