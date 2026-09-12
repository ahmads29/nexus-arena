USE gaming;

INSERT INTO roles (id, name) VALUES (1, 'Admin'), (2, 'Manager'), (3, 'Cashier'), (4, 'Staff');

INSERT INTO permissions (name, label) VALUES
('dashboard.view','View dashboard'), ('pos.use','Use POS'), ('sessions.manage','Manage sessions'),
('stations.manage','Manage stations'), ('playstation.manage','Manage PlayStation'), ('products.manage','Manage products'),
('inventory.manage','Manage inventory'), ('customers.manage','Manage customers'), ('debts.manage','Manage debts'),
('reports.view','View reports'), ('employees.manage','Manage employees'), ('settings.manage','Manage settings'),
('sales.view','View sales'), ('expenses.manage','Manage expenses');

INSERT INTO role_permissions (role_id, permission_id)
SELECT 1, id FROM permissions;
INSERT INTO role_permissions (role_id, permission_id)
SELECT 2, id FROM permissions WHERE name IN ('dashboard.view','pos.use','sessions.manage','stations.manage','playstation.manage','products.manage','inventory.manage','customers.manage','debts.manage','reports.view','sales.view','expenses.manage');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 3, id FROM permissions WHERE name IN ('dashboard.view','pos.use','sessions.manage','customers.manage','sales.view');
INSERT INTO role_permissions (role_id, permission_id)
SELECT 4, id FROM permissions WHERE name IN ('dashboard.view','sessions.manage','customers.manage');

INSERT INTO users (name, username, email, password_hash, role_id) VALUES
('System Admin', 'admin', 'admin@example.com', '$2y$12$YKFCpy4lvTqOeW7At81q0OVN1VJWNAHCA4Qm7FyuUGZZL4jGwlPMO', 1);

INSERT INTO station_types (id, name, tier, default_rate) VALUES
(1, 'Standard PC', 'Standard', 3.00), (2, 'Pro PC', 'Pro', 4.50), (3, 'VIP PC', 'VIP', 6.00);
INSERT INTO station_zones (id, name) VALUES (1, 'Zone A'), (2, 'Zone B'), (3, 'VIP'), (4, 'Console Den');

INSERT INTO stations (name, station_type_id, station_zone_id, tier, status, hourly_rate, specifications) VALUES
('PC 01', 2, 1, 'Pro', 'PLAYING', 4.50, 'RTX 4080 // 240Hz'),
('PC 02', 1, 1, 'Standard', 'AVAILABLE', 3.00, 'RTX 3060 // 165Hz'),
('PC 03', 1, 1, 'Standard', 'AVAILABLE', 3.00, 'RTX 3060 // 165Hz'),
('PC 04', 3, 3, 'VIP', 'RESERVED', 6.00, 'RTX 4090 // 360Hz'),
('PC 05', 2, 2, 'Pro', 'MAINTENANCE', 4.50, 'RTX 4070 Ti // 240Hz'),
('PC 06', 1, 2, 'Standard', 'AVAILABLE', 3.00, 'RTX 3060 // 165Hz'),
('PC 07', 1, 2, 'Standard', 'AVAILABLE', 3.00, 'RTX 3060 // 165Hz'),
('PC 08', 2, 1, 'Pro', 'AVAILABLE', 4.50, 'RTX 4080 // 240Hz'),
('PC 09', 1, 1, 'Standard', 'PLAYING', 3.00, 'RTX 3060 // 165Hz'),
('PC 10', 3, 3, 'VIP', 'AVAILABLE', 6.00, 'RTX 4090 // 360Hz'),
('PC 11', 1, 2, 'Standard', 'AVAILABLE', 3.00, 'RTX 3060 // 165Hz'),
('PC 12', 2, 2, 'Pro', 'RESERVED', 4.50, 'RTX 4070 Ti // 240Hz');

INSERT INTO playstation_stations (name, console_type, station_zone_id, status, hourly_rate, notes) VALUES
('PS4 #01', 'PS4', 4, 'AVAILABLE', 3.00, 'DualShock ready'),
('PS4 #02', 'PS4', 4, 'PLAYING', 3.00, 'EA FC'),
('PS5 #01', 'PS5', 4, 'AVAILABLE', 5.00, 'OLED 120Hz'),
('PS5 #02', 'PS5', 4, 'RESERVED', 5.00, 'Reserved for 19:30'),
('PS5 #03', 'PS5', 4, 'PLAYING', 5.00, 'EA FC');

INSERT INTO customers (name, phone, email, balance) VALUES
('Walk-In Customer', NULL, NULL, 0), ('Ahmad S.', '+96170000001', 'ahmad@example.com', 0);

INSERT INTO categories (id, name) VALUES (1, 'Drinks'), (2, 'Snacks'), (3, 'Food'), (4, 'Accessories'), (5, 'Other');
INSERT INTO suppliers (id, name, phone) VALUES (1, 'Default Supplier', '+96170000000');
INSERT INTO products (sku, barcode, name, category_id, supplier_id, cost_price, selling_price, current_stock, minimum_stock, status) VALUES
('DRK-PEPSI', '100001', 'Pepsi', 1, 1, 0.55, 1.00, 42, 10, 'ACTIVE'),
('DRK-RBZERO', '100002', 'Red Bull Zero', 1, 1, 1.80, 3.50, 16, 8, 'ACTIVE'),
('SNK-CHIPS', '200001', 'Chips', 2, 1, 0.45, 1.00, 30, 10, 'ACTIVE'),
('SNK-CHEETOS', '200002', 'Cheetos Flamin Hot', 2, 1, 0.75, 1.50, 5, 8, 'ACTIVE'),
('FOD-BURGER', '300001', 'Gamer Burger', 3, 1, 4.00, 8.00, 12, 3, 'ACTIVE'),
('ACC-USBC', '400001', 'USB-C Cable 2M', 4, 1, 4.25, 9.99, 7, 2, 'ACTIVE');

INSERT INTO stock_movements (product_id, movement_type, quantity_change, previous_stock, new_stock, reason, created_by)
SELECT id, 'INITIAL_STOCK', current_stock, 0, current_stock, 'Seed stock', 1 FROM products;

INSERT INTO games (name, sort_order) VALUES
('Valorant', 1), ('Counter-Strike 2', 2), ('Fortnite', 3), ('EA FC', 4), ('Call of Duty', 5), ('GTA V', 6), ('League of Legends', 7), ('Apex Legends', 8);

INSERT INTO pricing_items (title, category, price, unit, sort_order) VALUES
('Standard PC', 'PC', 3.00, 'hour', 1),
('Pro PC', 'PC', 4.50, 'hour', 2),
('VIP PC', 'PC', 6.00, 'hour', 3),
('PS4', 'PLAYSTATION', 3.00, 'hour', 4),
('PS5', 'PLAYSTATION', 5.00, 'hour', 5);

INSERT INTO settings (setting_key, setting_value) VALUES
('business_name', 'NEXUS ARENA'),
('business_description', 'High performance PCs, PlayStation gaming, fast internet, premium peripherals, and competitive lounge energy.'),
('phone', '+1 (800) 555-GAME'),
('email', 'command@nexusarena.gg'),
('address', '4200 Esports Parkway, Suite 100'),
('opening_hours', 'Open daily 10:00 - 02:00'),
('map_embed_url', ''),
('location_cta_label', 'Open Location'),
('currency', 'LBP '),
('currency_code', 'LBP'),
('usd_lbp_rate', '89500'),
('tax_rate', '0'),
('enable_invoice_printing', '1'),
('standard_pc_rate', '3.00'),
('pro_pc_rate', '4.50'),
('vip_pc_rate', '6.00'),
('ps4_rate', '3.00'),
('ps5_rate', '5.00'),
('ps4_controller_rate', '0.50'),
('ps5_controller_rate', '1.00'),
('google_maps_url', '#');

INSERT INTO gaming_sessions (session_code, station_id, customer_id, started_by, current_game, start_time, hourly_rate, status)
VALUES ('S-PC01-SEED', 1, 2, 1, 'Valorant', DATE_SUB(NOW(), INTERVAL 84 MINUTE), 4.50, 'ACTIVE'),
('S-PC09-SEED', 9, NULL, 1, 'Counter-Strike 2', DATE_SUB(NOW(), INTERVAL 31 MINUTE), 3.00, 'ACTIVE'),
('S-PS402-SEED', NULL, NULL, 1, 'EA FC', DATE_SUB(NOW(), INTERVAL 46 MINUTE), 3.00, 'ACTIVE'),
('S-PS503-SEED', NULL, NULL, 1, 'EA FC', DATE_SUB(NOW(), INTERVAL 18 MINUTE), 5.00, 'ACTIVE');

UPDATE gaming_sessions SET playstation_station_id = 2 WHERE session_code = 'S-PS402-SEED';
UPDATE gaming_sessions SET playstation_station_id = 5 WHERE session_code = 'S-PS503-SEED';
