-- Apply once after 003, on the existing database. No example content is published.
ALTER TABLE web_pages ADD content_format ENUM('text','blocks') NOT NULL DEFAULT 'text', ADD sort_order INT UNSIGNED NOT NULL DEFAULT 0, ADD deleted_at DATETIME NULL;
ALTER TABLE web_banners ADD alt_text VARCHAR(500) NOT NULL DEFAULT '', ADD accent_color CHAR(7) NOT NULL DEFAULT '#000000', ADD secondary_button_label VARCHAR(100) NULL, ADD secondary_link_url VARCHAR(2048) NULL, ADD deleted_at DATETIME NULL;
ALTER TABLE social_links ADD deleted_at DATETIME NULL;
INSERT INTO schema_migrations (version) VALUES ('004_web_content');
