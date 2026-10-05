-- Apply once after schema.sql. Does not alter or delete existing records.
CREATE TABLE rate_limits (
  bucket CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
  hits INT UNSIGNED NOT NULL DEFAULT 1,
  expires_at DATETIME NOT NULL,
  KEY rate_expiry (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Presentation fields required by the existing public website.
ALTER TABLE brands
  ADD tagline VARCHAR(500) NOT NULL DEFAULT '',
  ADD slogan VARCHAR(500) NOT NULL DEFAULT '',
  ADD accent_light CHAR(7) NOT NULL DEFAULT '#000000',
  ADD hero_image_url TEXT NULL,
  ADD tile_image_url TEXT NULL;
ALTER TABLE motorcycles ADD tagline VARCHAR(500) NOT NULL DEFAULT '', ADD horsepower DECIMAL(8,2) UNSIGNED NULL;
INSERT INTO schema_migrations(version) VALUES ('002_api_support');
