-- Honest / Hamadema Marketplace — database schema
-- Import this via phpMyAdmin (InfinityFree control panel → MySQL Databases → phpMyAdmin)

CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  phone VARCHAR(30),
  password_hash VARCHAR(255) NOT NULL,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(80) NOT NULL,
  slug VARCHAR(80) NOT NULL UNIQUE,
  icon VARCHAR(10)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ads (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  title VARCHAR(150) NOT NULL,
  description TEXT,
  price DECIMAL(12,2) NULL,
  negotiable TINYINT(1) DEFAULT 0,
  `condition` ENUM('new','used') DEFAULT 'used',
  location VARCHAR(100),
  images TEXT, -- JSON array of filenames, e.g. ["123.jpg","456.jpg"]
  status ENUM('active','sold') DEFAULT 'active',
  views INT DEFAULT 0,
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  FOREIGN KEY (category_id) REFERENCES categories(id),
  INDEX idx_category (category_id),
  INDEX idx_status (status),
  INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO categories (name, slug, icon) VALUES
  ('Vehicles', 'vehicles', '🚗'),
  ('Property', 'property', '🏠'),
  ('Mobile Phones & Tablets', 'mobile', '📱'),
  ('Electronics & Appliances', 'electronics', '🔌'),
  ('Home & Garden', 'home-garden', '🛋️'),
  ('Fashion & Beauty', 'fashion', '👗'),
  ('Jobs', 'jobs', '💼'),
  ('Services', 'services', '🛠️'),
  ('Animals & Pets', 'pets', '🐾'),
  ('Hobby, Sport & Kids', 'hobby-sport-kids', '⚽')
ON DUPLICATE KEY UPDATE name = VALUES(name);
