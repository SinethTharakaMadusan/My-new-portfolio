-- FULL DATABASE SCHEMA: all original tables + the new `videos` table.
-- For a FRESH database, import this one file.
-- For an EXISTING site, import only videos.sql (the seed INSERTs below would add duplicate rows).

CREATE TABLE IF NOT EXISTS admins (id INT AUTO_INCREMENT PRIMARY KEY, username VARCHAR(50) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS login_attempts (id INT AUTO_INCREMENT PRIMARY KEY, ip VARCHAR(45) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, INDEX(ip)) ENGINE=InnoDB;
CREATE TABLE IF NOT EXISTS projects (id INT AUTO_INCREMENT PRIMARY KEY, title VARCHAR(150) NOT NULL, description TEXT NOT NULL, technologies VARCHAR(255) DEFAULT '', category VARCHAR(60) DEFAULT '', github_url VARCHAR(255) DEFAULT '', demo_url VARCHAR(255) DEFAULT '', thumbnail VARCHAR(255) DEFAULT '', is_featured TINYINT(1) DEFAULT 0, is_visible TINYINT(1) DEFAULT 1, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS skills (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(80) NOT NULL, category VARCHAR(60) DEFAULT '', level TINYINT UNSIGNED DEFAULT 50, is_visible TINYINT(1) DEFAULT 1) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS experiences (id INT AUTO_INCREMENT PRIMARY KEY, type ENUM('education','work','internship','achievement') DEFAULT 'work', period VARCHAR(60) NOT NULL, title VARCHAR(120) NOT NULL, organization VARCHAR(150) NOT NULL, description TEXT, is_visible TINYINT(1) DEFAULT 1) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS messages (id INT AUTO_INCREMENT PRIMARY KEY, name VARCHAR(100) NOT NULL, email VARCHAR(150) NOT NULL, mobile VARCHAR(30) DEFAULT '', subject VARCHAR(150) DEFAULT '', message TEXT NOT NULL, is_read TINYINT(1) DEFAULT 0, ip VARCHAR(45) DEFAULT '', created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS settings (k VARCHAR(60) PRIMARY KEY, v TEXT) ENGINE=InnoDB CHARSET=utf8mb4;
CREATE TABLE IF NOT EXISTS videos (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(150) NOT NULL,
    description  VARCHAR(500) NOT NULL DEFAULT '',
    youtube_url  VARCHAR(255) NOT NULL,
    youtube_id   CHAR(11)     NOT NULL,
    thumbnail    VARCHAR(255) NOT NULL DEFAULT '',
    sort_order   INT          NOT NULL DEFAULT 0,
    is_visible   TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_videos_public (is_visible, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: current content of your portfolio
INSERT INTO projects (title,description,thumbnail,is_featured) VALUES
('Velvet Vogue Website UI','Lorem ipsum dolor sit amet consectetur adipisicing elit. Quisquam, quidem.','image/project1.png',1),
('Velvet Vogue Website UI','Lorem ipsum dolor sit amet consectetur adipisicing elit. Quisquam, quidem.','image/project1.png',0),
('Velvet Vogue Website UI','Lorem ipsum dolor sit amet consectetur adipisicing elit. Quisquam, quidem.','image/project1.png',0);
INSERT INTO skills (name,category,level) VALUES ('Software Design','Design',60),('Web Development','Frontend',70),('Graphic Design','Design',50),('UI/UX Designing','Design',90);
INSERT INTO experiences (type,period,title,organization,description) VALUES
('work','2011 - 2014','UI DESIGNER','GOOGLE CORPORATION.','All You Need To Do Your Best Work Together In One Package Works Seamlessly Computer'),
('work','2011 - 2014','UI DESIGNER','APPLE PRODUCT CO.','All You Need To Do Your Best Work Together In One Package Works Seamlessly Computer'),
('work','2011 - 2014','UI DESIGNER','LEAD DESIGNER MUSICSOFT','All You Need To Do Your Best Work Together In One Package Works Seamlessly Computer'),
('work','2011 - 2014','UI DESIGNER','LBRUNO MARS AS.','All You Need To Do Your Best Work Together In One Package Works Seamlessly Computer');
INSERT IGNORE INTO settings (k,v) VALUES ('site_title','Portfolio'),('github','https://github.com/SinethTharakaMadusan'),('linkedin','https://www.linkedin.com/in/si-ne-th-tha-ra-ka-a304b4352/'),('whatsapp','https://wa.me/+94769819082/');
