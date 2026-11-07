-- Applicare SOLO dopo backup su DB Aruba; il merge non modifica il DB.
CREATE TABLE IF NOT EXISTS gf_oauth_codes (
  code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  id_utente INT NOT NULL,
  id_famiglia INT NOT NULL,
  client_id VARCHAR(255) NOT NULL,
  redirect_uri VARCHAR(500) NOT NULL,
  resource VARCHAR(500) NOT NULL,
  scope VARCHAR(100) NOT NULL,
  code_challenge VARCHAR(128) NOT NULL,
  expires_at DATETIME NOT NULL,
  used_at DATETIME DEFAULT NULL,
  KEY idx_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS gf_oauth_access_tokens (
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  id_utente INT NOT NULL,
  id_famiglia INT NOT NULL,
  client_id VARCHAR(255) NOT NULL,
  resource VARCHAR(500) NOT NULL,
  scope VARCHAR(100) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  KEY idx_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS gf_oauth_refresh_tokens (
  token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL PRIMARY KEY,
  id_utente INT NOT NULL,
  id_famiglia INT NOT NULL,
  client_id VARCHAR(255) NOT NULL,
  resource VARCHAR(500) NOT NULL,
  scope VARCHAR(100) NOT NULL,
  expires_at DATETIME NOT NULL,
  revoked_at DATETIME DEFAULT NULL,
  KEY idx_exp (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

