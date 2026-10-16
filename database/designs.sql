-- Graphic Design Showcase. Safe to run on an existing site: creates only what is missing, touches no existing data.
CREATE TABLE IF NOT EXISTS designs (
    id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title        VARCHAR(150) NOT NULL,
    category     VARCHAR(60)  NOT NULL DEFAULT 'Other',
    description  VARCHAR(500) NOT NULL DEFAULT '',
    image        VARCHAR(255) NOT NULL,
    thumb        VARCHAR(255) NOT NULL DEFAULT '',
    img_width    INT UNSIGNED NOT NULL DEFAULT 0,
    img_height   INT UNSIGNED NOT NULL DEFAULT 0,
    external_url VARCHAR(255) NOT NULL DEFAULT '',
    is_published TINYINT(1)   NOT NULL DEFAULT 1,
    created_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_designs_public (is_published, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- "See More Designs" destination (empty = button hidden until you set it in Admin > Graphic Design)
INSERT IGNORE INTO settings (k,v) VALUES ('designs_see_more_url','');
