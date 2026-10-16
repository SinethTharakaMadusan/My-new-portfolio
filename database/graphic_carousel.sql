-- Graphic Design Carousel
-- Import this file into your database once: phpMyAdmin → SQL tab → paste & run.

CREATE TABLE IF NOT EXISTS graphic_carousel (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(150)  NOT NULL,
    category   VARCHAR(80)   DEFAULT '',
    image      VARCHAR(255)  NOT NULL,
    link_url   VARCHAR(255)  DEFAULT '',
    sort_order INT           DEFAULT 0,
    is_visible TINYINT(1)    DEFAULT 1,
    created_at TIMESTAMP     DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB CHARSET=utf8mb4;
