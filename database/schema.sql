CREATE DATABASE IF NOT EXISTS gaming CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE gaming;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS audit_logs, settings, reservations, debt_payments, debts, payments, sale_items, sales, session_products, gaming_sessions, stock_movements, purchase_items, purchases, products, categories, suppliers, playstation_stations, stations, station_zones, station_types, customers, role_permissions, permissions, users, roles, pricing_items, games, expenses;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE roles (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(50) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE permissions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  label VARCHAR(120) NOT NULL
) ENGINE=InnoDB;

CREATE TABLE role_permissions (
  role_id INT UNSIGNED NOT NULL,
  permission_id INT UNSIGNED NOT NULL,
  PRIMARY KEY (role_id, permission_id),
  CONSTRAINT fk_rp_role FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
  CONSTRAINT fk_rp_permission FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  username VARCHAR(80) NOT NULL UNIQUE,
  email VARCHAR(160) NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  role_id INT UNSIGNED NOT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id)
) ENGINE=InnoDB;

CREATE TABLE customers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(160) NULL,
  notes TEXT NULL,
  balance DECIMAL(12,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_customers_phone (phone)
) ENGINE=InnoDB;

CREATE TABLE station_types (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE,
  tier VARCHAR(60) NOT NULL DEFAULT 'Standard',
  default_rate DECIMAL(10,2) NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE station_zones (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE stations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(40) NOT NULL UNIQUE,
  station_type_id INT UNSIGNED NOT NULL,
  station_zone_id INT UNSIGNED NULL,
  tier VARCHAR(60) NOT NULL DEFAULT 'Standard',
  status ENUM('AVAILABLE','PLAYING','RESERVED','MAINTENANCE','OFFLINE') NOT NULL DEFAULT 'AVAILABLE',
  hourly_rate DECIMAL(10,2) NOT NULL,
  specifications TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_stations_type FOREIGN KEY (station_type_id) REFERENCES station_types(id),
  CONSTRAINT fk_stations_zone FOREIGN KEY (station_zone_id) REFERENCES station_zones(id) ON DELETE SET NULL,
  INDEX idx_stations_status (status),
  INDEX idx_stations_tier (tier)
) ENGINE=InnoDB;

CREATE TABLE playstation_stations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(60) NOT NULL UNIQUE,
  console_type ENUM('PS4','PS5') NOT NULL,
  station_zone_id INT UNSIGNED NULL,
  status ENUM('AVAILABLE','PLAYING','RESERVED','MAINTENANCE','OFFLINE') NOT NULL DEFAULT 'AVAILABLE',
  hourly_rate DECIMAL(10,2) NOT NULL,
  notes TEXT NULL,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_ps_zone FOREIGN KEY (station_zone_id) REFERENCES station_zones(id) ON DELETE SET NULL,
  INDEX idx_ps_status (status),
  INDEX idx_ps_type (console_type)
) ENGINE=InnoDB;

CREATE TABLE suppliers (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(140) NOT NULL,
  phone VARCHAR(50) NULL,
  email VARCHAR(160) NULL,
  address TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(100) NOT NULL UNIQUE,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE'
) ENGINE=InnoDB;

CREATE TABLE products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(80) NULL UNIQUE,
  barcode VARCHAR(120) NULL UNIQUE,
  name VARCHAR(160) NOT NULL,
  category_id INT UNSIGNED NULL,
  supplier_id INT UNSIGNED NULL,
  cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  selling_price DECIMAL(10,2) NOT NULL DEFAULT 0,
  current_stock INT NOT NULL DEFAULT 0,
  minimum_stock INT NOT NULL DEFAULT 0,
  image VARCHAR(255) NULL,
  description TEXT NULL,
  status ENUM('ACTIVE','INACTIVE') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
  CONSTRAINT fk_products_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  INDEX idx_products_name (name),
  INDEX idx_products_stock (current_stock, minimum_stock)
) ENGINE=InnoDB;

CREATE TABLE purchases (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  supplier_id INT UNSIGNED NULL,
  invoice_number VARCHAR(80) NULL,
  purchase_date DATE NOT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('DRAFT','COMPLETED','CANCELLED') NOT NULL DEFAULT 'DRAFT',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_purchases_supplier FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE SET NULL,
  CONSTRAINT fk_purchases_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE purchase_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  purchase_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  cost_price DECIMAL(10,2) NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_pi_purchase FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  CONSTRAINT fk_pi_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE stock_movements (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  product_id INT UNSIGNED NOT NULL,
  movement_type ENUM('SALE','PURCHASE','RETURN','DAMAGE','ADJUSTMENT','INITIAL_STOCK') NOT NULL,
  quantity_change INT NOT NULL,
  previous_stock INT NOT NULL,
  new_stock INT NOT NULL,
  reason VARCHAR(255) NULL,
  reference_type VARCHAR(60) NULL,
  reference_id INT UNSIGNED NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sm_product FOREIGN KEY (product_id) REFERENCES products(id),
  CONSTRAINT fk_sm_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_sm_product_date (product_id, created_at),
  INDEX idx_sm_type (movement_type)
) ENGINE=InnoDB;

CREATE TABLE gaming_sessions (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  session_code VARCHAR(40) NOT NULL UNIQUE,
  station_id INT UNSIGNED NULL,
  playstation_station_id INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  started_by INT UNSIGNED NULL,
  ended_by INT UNSIGNED NULL,
  current_game VARCHAR(120) NULL,
  start_time DATETIME NOT NULL,
  base_hourly_rate DECIMAL(10,2) NULL,
  controller_count INT NOT NULL DEFAULT 0,
  controller_rate DECIMAL(10,2) NOT NULL DEFAULT 0,
  end_time DATETIME NULL,
  paused_seconds INT NOT NULL DEFAULT 0,
  last_paused_at DATETIME NULL,
  hourly_rate DECIMAL(10,2) NOT NULL,
  gaming_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  product_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  status ENUM('ACTIVE','PAUSED','COMPLETED','CANCELLED') NOT NULL DEFAULT 'ACTIVE',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_gs_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL,
  CONSTRAINT fk_gs_ps FOREIGN KEY (playstation_station_id) REFERENCES playstation_stations(id) ON DELETE SET NULL,
  CONSTRAINT fk_gs_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_gs_started_by FOREIGN KEY (started_by) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_gs_ended_by FOREIGN KEY (ended_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_gs_status (status),
  INDEX idx_gs_start (start_time)
) ENGINE=InnoDB;

CREATE TABLE session_products (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  gaming_session_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sp_session FOREIGN KEY (gaming_session_id) REFERENCES gaming_sessions(id) ON DELETE CASCADE,
  CONSTRAINT fk_sp_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE sales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  invoice_number VARCHAR(40) NOT NULL UNIQUE,
  customer_id INT UNSIGNED NULL,
  cashier_id INT UNSIGNED NULL,
  gaming_session_id INT UNSIGNED NULL,
  gaming_charge DECIMAL(12,2) NOT NULL DEFAULT 0,
  product_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
  discount DECIMAL(12,2) NOT NULL DEFAULT 0,
  tax DECIMAL(12,2) NOT NULL DEFAULT 0,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  payment_method ENUM('CASH','CARD','OTHER','DEBT') NOT NULL DEFAULT 'CASH',
  status ENUM('PAID','PARTIAL','DEBT','VOID') NOT NULL DEFAULT 'PAID',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sales_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_cashier FOREIGN KEY (cashier_id) REFERENCES users(id) ON DELETE SET NULL,
  CONSTRAINT fk_sales_session FOREIGN KEY (gaming_session_id) REFERENCES gaming_sessions(id) ON DELETE SET NULL,
  INDEX idx_sales_date (created_at)
) ENGINE=InnoDB;

CREATE TABLE sale_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NOT NULL,
  product_id INT UNSIGNED NOT NULL,
  product_name VARCHAR(160) NOT NULL,
  quantity INT NOT NULL,
  unit_price DECIMAL(10,2) NOT NULL,
  total DECIMAL(12,2) NOT NULL,
  CONSTRAINT fk_si_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  CONSTRAINT fk_si_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

CREATE TABLE payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sale_id INT UNSIGNED NULL,
  customer_id INT UNSIGNED NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('CASH','CARD','OTHER') NOT NULL DEFAULT 'CASH',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_pay_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_customer FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE SET NULL,
  CONSTRAINT fk_pay_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE debts (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  sale_id INT UNSIGNED NULL,
  original_amount DECIMAL(12,2) NOT NULL,
  paid_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
  remaining_amount DECIMAL(12,2) NOT NULL,
  status ENUM('OPEN','PARTIAL','PAID') NOT NULL DEFAULT 'OPEN',
  notes TEXT NULL,
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_debts_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_debts_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE SET NULL,
  CONSTRAINT fk_debts_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_debts_status (status)
) ENGINE=InnoDB;

CREATE TABLE debt_payments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  debt_id INT UNSIGNED NOT NULL,
  amount DECIMAL(12,2) NOT NULL,
  method ENUM('CASH','CARD','OTHER') NOT NULL DEFAULT 'CASH',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_dp_debt FOREIGN KEY (debt_id) REFERENCES debts(id),
  CONSTRAINT fk_dp_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE reservations (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  customer_id INT UNSIGNED NOT NULL,
  station_id INT UNSIGNED NULL,
  playstation_station_id INT UNSIGNED NULL,
  station_kind ENUM('PC','PLAYSTATION') NOT NULL,
  reservation_date DATE NOT NULL,
  start_time TIME NOT NULL,
  duration_minutes INT NOT NULL,
  status ENUM('UPCOMING','ACTIVE','COMPLETED','CANCELLED','NO_SHOW') NOT NULL DEFAULT 'UPCOMING',
  created_by INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_res_customer FOREIGN KEY (customer_id) REFERENCES customers(id),
  CONSTRAINT fk_res_station FOREIGN KEY (station_id) REFERENCES stations(id) ON DELETE SET NULL,
  CONSTRAINT fk_res_ps FOREIGN KEY (playstation_station_id) REFERENCES playstation_stations(id) ON DELETE SET NULL,
  CONSTRAINT fk_res_user FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_res_lookup (station_kind, reservation_date, start_time, status)
) ENGINE=InnoDB;

CREATE TABLE games (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL UNIQUE,
  image_path VARCHAR(255) NULL,
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB;

CREATE TABLE pricing_items (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  category ENUM('PC','PLAYSTATION','OTHER') NOT NULL DEFAULT 'PC',
  price DECIMAL(10,2) NOT NULL DEFAULT 0,
  unit VARCHAR(40) NOT NULL DEFAULT 'hour',
  sort_order INT NOT NULL DEFAULT 0,
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pricing_active (active, sort_order),
  INDEX idx_pricing_category (category)
) ENGINE=InnoDB;

CREATE TABLE expenses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  category ENUM('Rent','Internet','Electricity','Maintenance','Salaries','Supplies','Other') NOT NULL DEFAULT 'Other',
  amount DECIMAL(12,2) NOT NULL,
  expense_date DATE NOT NULL,
  description TEXT NULL,
  user_id INT UNSIGNED NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_exp_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_exp_date (expense_date)
) ENGINE=InnoDB;

CREATE TABLE settings (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(100) NOT NULL UNIQUE,
  setting_value TEXT NULL,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id INT UNSIGNED NULL,
  action VARCHAR(80) NOT NULL,
  entity_type VARCHAR(80) NOT NULL,
  entity_id INT UNSIGNED NULL,
  meta JSON NULL,
  ip_address VARCHAR(64) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  INDEX idx_audit_date (created_at),
  INDEX idx_audit_entity (entity_type, entity_id)
) ENGINE=InnoDB;
