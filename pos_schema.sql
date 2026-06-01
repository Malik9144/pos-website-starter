
-- MySQL schema
CREATE TABLE branches (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL
);
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('kasir','spv','admin','superadmin','spv_warehouse') NOT NULL DEFAULT 'kasir',
  branch_id INT DEFAULT 1,
  totp_secret VARCHAR(64) DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (branch_id) REFERENCES branches(id)
);
CREATE TABLE role_permissions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  role VARCHAR(50) NOT NULL,
  permission VARCHAR(100) NOT NULL
);
INSERT INTO role_permissions(role,permission) VALUES
 ('kasir','pos.use'),('kasir','order.create'),('kasir','order.cancel.request'),
 ('spv','order.cancel.approve'),('spv','inventory.view'),
 ('spv_warehouse','inventory.view.all'),
 ('admin','*'),('superadmin','*');
CREATE TABLE products (
  id INT AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(60) UNIQUE,
  name VARCHAR(160) NOT NULL,
  price INT NOT NULL,
  image VARCHAR(200) DEFAULT NULL,
  active TINYINT(1) DEFAULT 1
);
CREATE TABLE inventory (
  id INT AUTO_INCREMENT PRIMARY KEY,
  product_id INT NOT NULL,
  branch_id INT NOT NULL,
  stock INT NOT NULL DEFAULT 0,
  UNIQUE KEY (product_id,branch_id),
  FOREIGN KEY (product_id) REFERENCES products(id),
  FOREIGN KEY (branch_id) REFERENCES branches(id)
);
CREATE TABLE orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  branch_id INT NOT NULL,
  total INT NOT NULL,
  payment_method VARCHAR(40) NOT NULL,
  status ENUM('paid','refunded','void') DEFAULT 'paid',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  product_id INT NOT NULL,
  qty INT NOT NULL,
  price INT NOT NULL,
  discount INT NOT NULL DEFAULT 0,
  FOREIGN KEY (order_id) REFERENCES orders(id),
  FOREIGN KEY (product_id) REFERENCES products(id)
);
CREATE TABLE cancel_requests (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  requester_id INT NOT NULL,
  reason VARCHAR(255),
  status ENUM('pending','approved','rejected') DEFAULT 'pending',
  approver_id INT DEFAULT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE audits (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  branch_id INT,
  action VARCHAR(100),
  details JSON,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
-- Targets per cashier/store (for reporting)
CREATE TABLE targets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT,
  user_id INT,
  target_month DATE,
  target_amount INT
);

-- Reservations (dine-in with table)
CREATE TABLE reservations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  branch_id INT NOT NULL,
  customer_name VARCHAR(160) NOT NULL,
  phone VARCHAR(40) DEFAULT NULL,
  reservation_date DATE NOT NULL,
  reservation_time TIME NOT NULL,
  party_size INT NOT NULL DEFAULT 1,
  table_no VARCHAR(40) DEFAULT NULL,
  notes VARCHAR(255) DEFAULT NULL,
  status ENUM('booked','seated','completed','cancelled') NOT NULL DEFAULT 'booked',
  created_by INT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP NULL DEFAULT NULL,
  FOREIGN KEY (branch_id) REFERENCES branches(id),
  FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Seed branches and admin
INSERT INTO branches(name) VALUES ('Pusat'),('Cabang A'),('Cabang B'),('Cabang C'),('Cabang D');
INSERT INTO users(name,email,password_hash,role,branch_id) VALUES
 ('Super Admin','admin@example.com', '$2y$10$A1cQ6k2J3s6g8I2o8Xk1pO9sC2mH8mK2Y6k9u4e3fG5d7s8u9vWcG', 'superadmin', 1);
-- password is: admin123 (bcrypt placeholder; change after import)
