-- Incremental migration: run ONCE after 001_initial_schema and 002_api_support.
-- Adds presentation only. No existing promotions/relations/media are deleted or seeded.
ALTER TABLE promotions
  ADD button_label VARCHAR(100) NOT NULL DEFAULT 'Ver oferta',
  ADD button_href VARCHAR(2048) NOT NULL DEFAULT '/';
INSERT INTO schema_migrations(version) VALUES ('003_promotions');
