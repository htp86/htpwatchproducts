-- HTPWatchProducts - Table des produits surveillés
-- Dolibarr 23.0.2 - NAS Synology DS418
-- Version: 20260521 Build: 1400

CREATE TABLE IF NOT EXISTS llx_htpwatchproducts_prod (
  rowid INTEGER AUTO_INCREMENT PRIMARY KEY,
  label VARCHAR(255) NOT NULL,
  url VARCHAR(500) NOT NULL,
  supplier VARCHAR(50) NOT NULL,
  last_price DECIMAL(10,2) DEFAULT NULL,
  last_check DATETIME DEFAULT NULL,
  price_history TEXT DEFAULT NULL,
  date_creation DATETIME NOT NULL,
  tms TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  fk_user_creat INTEGER DEFAULT NULL,
  fk_user_modif INTEGER DEFAULT NULL,
  status INTEGER DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE INDEX idx_htpwatchproducts_supplier ON llx_htpwatchproducts_prod(supplier);
CREATE INDEX idx_htpwatchproducts_status ON llx_htpwatchproducts_prod(status);
CREATE INDEX idx_htpwatchproducts_label ON llx_htpwatchproducts_prod(label);
CREATE INDEX idx_htpwatchproducts_date_creation ON llx_htpwatchproducts_prod(date_creation);
