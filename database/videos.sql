-- YouTube videos table for the portfolio.
-- Safe to run more than once: it only creates the table if it does not exist
-- and never touches your existing tables or data.

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
