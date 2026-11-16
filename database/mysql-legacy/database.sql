-- UDTC-Portal : ศูนย์กลางให้บริการระบบงานต่างๆ
-- นำเข้าผ่าน phpMyAdmin (แท็บ Import) หรือใช้ install.php แทนก็ได้
-- บัญชีเริ่มต้น: admin / admin1234

CREATE DATABASE IF NOT EXISTS `udpt_portal` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `udpt_portal`;

CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    fullname VARCHAR(150) NOT NULL DEFAULT '',
    role ENUM('admin','staff') NOT NULL DEFAULT 'staff',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type ENUM('website','system','document') NOT NULL DEFAULT 'website',
    name VARCHAR(255) NOT NULL,
    url VARCHAR(2048) NULL,
    is_visible TINYINT(1) NOT NULL DEFAULT 1,
    file_original VARCHAR(255) NULL,
    file_stored VARCHAR(100) NULL,
    file_mime VARCHAR(150) NULL,
    file_size BIGINT UNSIGNED NULL,
    file_visible TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED NULL,
    updated_by INT UNSIGNED NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_visible (is_visible)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS favorites (
    user_id INT UNSIGNED NOT NULL,
    link_id INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, link_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_link FOREIGN KEY (link_id) REFERENCES links(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO users (username, password_hash, fullname, role)
VALUES ('admin', '$2y$10$.NG/hMHNwjRWXtGirH76A.90rHdW/HYFjusr4w7nacpPlpBVSP7zO', 'ผู้ดูแลระบบ', 'admin');

INSERT INTO links (type, name, url) VALUES
('website', 'Google', 'https://www.google.com'),
('website', 'กรมบัญชีกลาง', 'https://www.cgd.go.th'),
('system', 'Gmail', 'https://mail.google.com');
