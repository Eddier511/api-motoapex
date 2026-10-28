-- Run ONCE after 006, after backup and checking schema_migrations.
-- MySQL >= 8.0.16: existing CHECK(ends_at >= starts_at) accepts UNKNOWN
-- when either operand is NULL, and still rejects reversed non-NULL dates.
-- Keep that CHECK (its generated name can vary), indexes and all existing data.
ALTER TABLE promotions
  MODIFY COLUMN starts_at DATETIME NULL DEFAULT NULL,
  MODIFY COLUMN ends_at DATETIME NULL DEFAULT NULL;

INSERT INTO schema_migrations(version) VALUES ('007_optional_promotion_dates');
