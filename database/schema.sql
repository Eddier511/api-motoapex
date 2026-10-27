-- MotoApex schema v1. Import into an EMPTY selected database.
-- MySQL >=8.0.16 (also compatible with MariaDB >=10.6). No CREATE DATABASE, DEFINER, triggers or events.
-- UTC timestamps; API converts to America/Costa_Rica.
SET NAMES utf8mb4;
SET time_zone = '+00:00';

CREATE TABLE roles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(40) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL, description TEXT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE permissions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(100) NOT NULL UNIQUE, description VARCHAR(255),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL, PRIMARY KEY(role_id,permission_id), FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE, FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE users (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL, email VARCHAR(191) NOT NULL UNIQUE, password_hash VARCHAR(255) NOT NULL, role_id BIGINT UNSIGNED NOT NULL, status ENUM('active','inactive') NOT NULL DEFAULT 'active', avatar_url TEXT, phone VARCHAR(40), last_access_at DATETIME, email_verified_at DATETIME, failed_login_attempts INT UNSIGNED NOT NULL DEFAULT 0, locked_until DATETIME, password_changed_at DATETIME, must_change_password BOOLEAN NOT NULL DEFAULT 0, deleted_at DATETIME, FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE RESTRICT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_sessions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, ip_address VARCHAR(45), user_agent TEXT, expires_at DATETIME NOT NULL, last_seen_at DATETIME, revoked_at DATETIME, INDEX(expires_at), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_reset_tokens (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, token_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, expires_at DATETIME NOT NULL, used_at DATETIME, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_mfa (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL UNIQUE, encrypted_secret TEXT NOT NULL, confirmed_at DATETIME, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE mfa_recovery_codes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, code_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL UNIQUE, used_at DATETIME, FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email VARCHAR(191), user_id BIGINT UNSIGNED, ip_address VARCHAR(45), succeeded BOOLEAN NOT NULL DEFAULT 0, INDEX(email,created_at), INDEX(ip_address,created_at), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media_assets (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  storage_key VARCHAR(255) UNIQUE, url TEXT NOT NULL, original_name VARCHAR(255), mime_type VARCHAR(100) NOT NULL, byte_size BIGINT UNSIGNED, width INT UNSIGNED, height INT UNSIGNED, alt_text VARCHAR(500), uploaded_by BIGINT UNSIGNED, deleted_at DATETIME, FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE brands (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, logo_media_id BIGINT UNSIGNED, primary_color CHAR(7) NOT NULL DEFAULT '#F97316', secondary_color CHAR(7) NOT NULL DEFAULT '#18181B', description TEXT, status ENUM('active','inactive') NOT NULL DEFAULT 'active', sort_order INT UNSIGNED NOT NULL DEFAULT 0, meta_title VARCHAR(255), meta_description VARCHAR(500), deleted_at DATETIME, FOREIGN KEY (logo_media_id) REFERENCES media_assets(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE categories (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(120) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, description TEXT, brand_id BIGINT UNSIGNED, parent_id BIGINT UNSIGNED, image_media_id BIGINT UNSIGNED, sort_order INT UNSIGNED NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active', deleted_at DATETIME, FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE RESTRICT, FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE RESTRICT, FOREIGN KEY (image_media_id) REFERENCES media_assets(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorcycles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  brand_id BIGINT UNSIGNED NOT NULL, category_id BIGINT UNSIGNED NOT NULL,
  model VARCHAR(150) NOT NULL, version VARCHAR(120) NOT NULL DEFAULT '', model_year SMALLINT UNSIGNED NOT NULL,
  slug VARCHAR(191) NOT NULL UNIQUE, sku VARCHAR(100) UNIQUE, displacement_cc DECIMAL(8,2) UNSIGNED,
  price DECIMAL(15,2) UNSIGNED, promo_price DECIMAL(15,2) UNSIGNED, currency ENUM('CRC','USD') NOT NULL DEFAULT 'CRC',
  availability_status ENUM('available','coming_soon','reserved','sold_out') NOT NULL DEFAULT 'coming_soon',
  publication_status ENUM('draft','published','hidden','archived') NOT NULL DEFAULT 'draft',
  featured BOOLEAN NOT NULL DEFAULT 0, is_new BOOLEAN NOT NULL DEFAULT 0, show_price BOOLEAN NOT NULL DEFAULT 1, allow_quote BOOLEAN NOT NULL DEFAULT 1,
  condition_type ENUM('new','used') NOT NULL DEFAULT 'new', mileage_km INT UNSIGNED, previous_owners SMALLINT UNSIGNED, condition_description TEXT,
  short_description TEXT, description LONGTEXT, meta_title VARCHAR(255), meta_description VARCHAR(500), keywords TEXT, canonical_url TEXT,
  published_at DATETIME, scheduled_publish_at DATETIME, created_by BIGINT UNSIGNED, updated_by BIGINT UNSIGNED, deleted_at DATETIME,
  CHECK(model_year BETWEEN 1900 AND 2200), CHECK(promo_price IS NULL OR (price IS NOT NULL AND promo_price <= price)),
  INDEX(publication_status,deleted_at,featured), INDEX(brand_id,category_id), INDEX(condition_type,availability_status),
  FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE RESTRICT, FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE RESTRICT, FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorcycle_specifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  motorcycle_id BIGINT UNSIGNED NOT NULL UNIQUE, engine VARCHAR(255), cylinders VARCHAR(100), power VARCHAR(100), torque VARCHAR(100), transmission VARCHAR(150), cooling VARCHAR(150), weight VARCHAR(100), seat_height VARCHAR(100), tank_capacity VARCHAR(100), front_suspension TEXT, rear_suspension TEXT, front_brake TEXT, rear_brake TEXT, front_tire VARCHAR(150), rear_tire VARCHAR(150), abs BOOLEAN NOT NULL DEFAULT 0, traction_control BOOLEAN NOT NULL DEFAULT 0, riding_modes TEXT, consumption VARCHAR(150), warranty TEXT, FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorcycle_custom_specs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  motorcycle_id BIGINT UNSIGNED NOT NULL, name VARCHAR(150) NOT NULL, value TEXT NOT NULL, unit VARCHAR(50), group_name VARCHAR(100), sort_order INT UNSIGNED NOT NULL DEFAULT 0, INDEX(motorcycle_id,sort_order), FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorcycle_colors (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  motorcycle_id BIGINT UNSIGNED NOT NULL, name VARCHAR(120) NOT NULL, hex CHAR(7) NOT NULL, sku VARCHAR(100) UNIQUE, status ENUM('active','inactive') NOT NULL DEFAULT 'active', available BOOLEAN NOT NULL DEFAULT 1, sort_order INT UNSIGNED NOT NULL DEFAULT 0, UNIQUE(id,motorcycle_id), INDEX(motorcycle_id,sort_order), FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorcycle_color_images (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  color_id BIGINT UNSIGNED NOT NULL, media_id BIGINT UNSIGNED NOT NULL, label VARCHAR(150), alt_text VARCHAR(500), sort_order INT UNSIGNED NOT NULL DEFAULT 0, is_primary BOOLEAN NOT NULL DEFAULT 0, primary_color_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN is_primary = 1 THEN color_id ELSE NULL END) STORED, UNIQUE(primary_color_id), UNIQUE(color_id,media_id), INDEX(color_id,sort_order), FOREIGN KEY (color_id) REFERENCES motorcycle_colors(id) ON DELETE RESTRICT, FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE RESTRICT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorcycle_media (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  motorcycle_id BIGINT UNSIGNED NOT NULL, media_id BIGINT UNSIGNED NOT NULL, purpose ENUM('hero','card','mobile','gallery','video','document') NOT NULL DEFAULT 'gallery', label VARCHAR(150), alt_text VARCHAR(500), sort_order INT UNSIGNED NOT NULL DEFAULT 0, featured_slot VARCHAR(30) GENERATED ALWAYS AS (CASE WHEN purpose IN ('hero','card','mobile') THEN purpose ELSE NULL END) STORED, UNIQUE(motorcycle_id,featured_slot), INDEX(motorcycle_id,purpose,sort_order), FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE CASCADE, FOREIGN KEY (media_id) REFERENCES media_assets(id) ON DELETE RESTRICT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE motorcycle_videos (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  motorcycle_id BIGINT UNSIGNED NOT NULL, provider ENUM('youtube','external') NOT NULL DEFAULT 'external', url TEXT NOT NULL, title VARCHAR(255), sort_order INT UNSIGNED NOT NULL DEFAULT 0, FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_locations (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL, code VARCHAR(50) NOT NULL UNIQUE, address TEXT, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_stock (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  motorcycle_id BIGINT UNSIGNED NOT NULL, color_id BIGINT UNSIGNED, location_id BIGINT UNSIGNED NOT NULL, quantity INT UNSIGNED NOT NULL DEFAULT 0, reserved_quantity INT UNSIGNED NOT NULL DEFAULT 0, low_stock_threshold INT UNSIGNED NOT NULL DEFAULT 0, color_key BIGINT UNSIGNED GENERATED ALWAYS AS (COALESCE(color_id,0)) STORED, UNIQUE(motorcycle_id,color_key,location_id), CHECK(reserved_quantity <= quantity), FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE RESTRICT, FOREIGN KEY(color_id,motorcycle_id) REFERENCES motorcycle_colors(id,motorcycle_id) ON DELETE RESTRICT, FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE RESTRICT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_movements (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  stock_id BIGINT UNSIGNED NOT NULL, movement_type ENUM('opening','receipt','sale','return','adjustment','transfer_in','transfer_out','reserve','release') NOT NULL, quantity_delta INT NOT NULL DEFAULT 0, reserved_delta INT NOT NULL DEFAULT 0, quantity_after INT UNSIGNED NOT NULL, reserved_after INT UNSIGNED NOT NULL, reference VARCHAR(150), reason TEXT NOT NULL, actor_id BIGINT UNSIGNED, CHECK(reserved_after <= quantity_after), INDEX(stock_id,created_at), FOREIGN KEY (stock_id) REFERENCES inventory_stock(id) ON DELETE RESTRICT, FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inventory_units (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  stock_id BIGINT UNSIGNED NOT NULL, vin VARCHAR(50) UNIQUE, engine_number VARCHAR(100), status ENUM('available','reserved','sold','in_transit','maintenance') NOT NULL DEFAULT 'available', acquisition_cost DECIMAL(15,2) UNSIGNED, currency ENUM('CRC','USD') NOT NULL DEFAULT 'CRC', received_at DATETIME, sold_at DATETIME, notes TEXT, FOREIGN KEY (stock_id) REFERENCES inventory_stock(id) ON DELETE RESTRICT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE promotions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, description TEXT, image_media_id BIGINT UNSIGNED, brand_id BIGINT UNSIGNED, starts_at DATETIME NOT NULL, ends_at DATETIME NOT NULL, status ENUM('active','inactive','expired') NOT NULL DEFAULT 'inactive', featured BOOLEAN NOT NULL DEFAULT 0, show_on_home BOOLEAN NOT NULL DEFAULT 0, sort_order INT UNSIGNED NOT NULL DEFAULT 0, created_by BIGINT UNSIGNED, deleted_at DATETIME, CHECK(ends_at >= starts_at), INDEX(status,starts_at,ends_at), FOREIGN KEY (image_media_id) REFERENCES media_assets(id) ON DELETE SET NULL, FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE RESTRICT, FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE promotion_motorcycles (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  promotion_id BIGINT UNSIGNED NOT NULL, motorcycle_id BIGINT UNSIGNED NOT NULL, original_price DECIMAL(15,2) UNSIGNED NOT NULL, promo_price DECIMAL(15,2) UNSIGNED NOT NULL, currency ENUM('CRC','USD') NOT NULL DEFAULT 'CRC', UNIQUE(promotion_id,motorcycle_id), CHECK(promo_price <= original_price), FOREIGN KEY (promotion_id) REFERENCES promotions(id) ON DELETE CASCADE, FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE RESTRICT,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE leads (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name VARCHAR(150) NOT NULL, phone VARCHAR(40), email VARCHAR(191), brand_id BIGINT UNSIGNED, motorcycle_id BIGINT UNSIGNED, brand_snapshot VARCHAR(120), motorcycle_snapshot VARCHAR(255), type ENUM('quote','availability','test_ride','contact','whatsapp') NOT NULL, status ENUM('new','contacted','follow_up','closed','discarded') NOT NULL DEFAULT 'new', assigned_to BIGINT UNSIGNED, message TEXT, source VARCHAR(100), source_url TEXT, utm_source VARCHAR(150), utm_medium VARCHAR(150), utm_campaign VARCHAR(150), consent_at DATETIME, next_follow_up_at DATETIME, closed_at DATETIME, deleted_at DATETIME, INDEX(status,created_at), INDEX(assigned_to,next_follow_up_at), INDEX(motorcycle_id,created_at), FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL, FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE SET NULL, FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_notes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL, author_id BIGINT UNSIGNED, note TEXT NOT NULL, FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE, FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE lead_activities (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL, actor_id BIGINT UNSIGNED, activity_type ENUM('status','assignment','call','email','whatsapp','meeting','note','other') NOT NULL, from_value TEXT, to_value TEXT, detail TEXT, INDEX(lead_id,created_at), FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE, FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE test_ride_appointments (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL, motorcycle_id BIGINT UNSIGNED, assigned_to BIGINT UNSIGNED, location_id BIGINT UNSIGNED, starts_at DATETIME NOT NULL, ends_at DATETIME, status ENUM('requested','confirmed','completed','cancelled','no_show') NOT NULL DEFAULT 'requested', notes TEXT, CHECK(ends_at IS NULL OR ends_at >= starts_at), FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE CASCADE, FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE SET NULL, FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL, FOREIGN KEY (location_id) REFERENCES inventory_locations(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quotes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  lead_id BIGINT UNSIGNED NOT NULL, quote_number VARCHAR(100) NOT NULL UNIQUE, currency ENUM('CRC','USD') NOT NULL DEFAULT 'CRC', status ENUM('draft','sent','accepted','rejected','expired') NOT NULL DEFAULT 'draft', valid_until DATETIME, notes TEXT, created_by BIGINT UNSIGNED, FOREIGN KEY (lead_id) REFERENCES leads(id) ON DELETE RESTRICT, FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE quote_items (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quote_id BIGINT UNSIGNED NOT NULL, motorcycle_id BIGINT UNSIGNED, description VARCHAR(500) NOT NULL, quantity INT UNSIGNED NOT NULL DEFAULT 1, unit_price DECIMAL(15,2) UNSIGNED NOT NULL, discount_amount DECIMAL(15,2) UNSIGNED NOT NULL DEFAULT 0, tax_rate DECIMAL(5,2) UNSIGNED NOT NULL DEFAULT 0, CHECK(quantity > 0), CHECK(discount_amount <= quantity * unit_price), CHECK(tax_rate <= 100), FOREIGN KEY (quote_id) REFERENCES quotes(id) ON DELETE CASCADE, FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE web_pages (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(255) NOT NULL, slug VARCHAR(191) NOT NULL UNIQUE, body LONGTEXT, status ENUM('draft','published','hidden','archived') NOT NULL DEFAULT 'draft', meta_title VARCHAR(255), meta_description VARCHAR(500), published_at DATETIME, updated_by BIGINT UNSIGNED, FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE web_banners (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  page_id BIGINT UNSIGNED, placement VARCHAR(100) NOT NULL DEFAULT 'home_hero', title VARCHAR(255), subtitle TEXT, desktop_media_id BIGINT UNSIGNED, mobile_media_id BIGINT UNSIGNED, link_url TEXT, button_label VARCHAR(100), brand_id BIGINT UNSIGNED, status ENUM('active','inactive') NOT NULL DEFAULT 'inactive', starts_at DATETIME, ends_at DATETIME, sort_order INT UNSIGNED NOT NULL DEFAULT 0, CHECK(ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at), FOREIGN KEY (page_id) REFERENCES web_pages(id) ON DELETE CASCADE, FOREIGN KEY (desktop_media_id) REFERENCES media_assets(id) ON DELETE SET NULL, FOREIGN KEY (mobile_media_id) REFERENCES media_assets(id) ON DELETE SET NULL, FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE site_contact (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  site_key VARCHAR(50) NOT NULL UNIQUE, site_name VARCHAR(150) NOT NULL, whatsapp VARCHAR(40), phone VARCHAR(40), email VARCHAR(191), address TEXT, latitude DECIMAL(10,7), longitude DECIMAL(10,7), opening_hours TEXT, logo_media_id BIGINT UNSIGNED, favicon_media_id BIGINT UNSIGNED, FOREIGN KEY (logo_media_id) REFERENCES media_assets(id) ON DELETE SET NULL, FOREIGN KEY (favicon_media_id) REFERENCES media_assets(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE social_links (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  platform VARCHAR(60) NOT NULL, label VARCHAR(150), url TEXT NOT NULL, sort_order INT UNSIGNED NOT NULL DEFAULT 0, status ENUM('active','inactive') NOT NULL DEFAULT 'active',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  setting_key VARCHAR(191) NOT NULL UNIQUE, setting_value LONGTEXT, value_type ENUM('string','number','boolean','json') NOT NULL DEFAULT 'string', is_public BOOLEAN NOT NULL DEFAULT 0, updated_by BIGINT UNSIGNED, FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE content_revisions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  entity_type VARCHAR(60) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL, revision_number INT UNSIGNED NOT NULL, payload LONGTEXT NOT NULL, author_id BIGINT UNSIGNED, UNIQUE(entity_type,entity_id,revision_number), FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE audit_logs (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  actor_id BIGINT UNSIGNED, action VARCHAR(100) NOT NULL, entity_type VARCHAR(60) NOT NULL, entity_id BIGINT UNSIGNED, before_data LONGTEXT, after_data LONGTEXT, ip_address VARCHAR(45), request_id VARCHAR(100), INDEX(entity_type,entity_id,created_at), INDEX(actor_id,created_at), FOREIGN KEY (actor_id) REFERENCES users(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE analytics_events (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  event_type ENUM('page_view','motorcycle_view','quote_click','whatsapp_click','test_ride_click','contact_submit') NOT NULL, motorcycle_id BIGINT UNSIGNED, brand_id BIGINT UNSIGNED, page_path VARCHAR(500), visitor_hash CHAR(64), source VARCHAR(150), INDEX(event_type,created_at), INDEX(motorcycle_id,created_at), FOREIGN KEY (motorcycle_id) REFERENCES motorcycles(id) ON DELETE SET NULL, FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE notifications (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id BIGINT UNSIGNED NOT NULL, title VARCHAR(255) NOT NULL, message TEXT, entity_type VARCHAR(60), entity_id BIGINT UNSIGNED, read_at DATETIME, INDEX(user_id,read_at,created_at), FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO roles(code,name) VALUES ('admin','Administrador'),('sales','Ventas'),('marketing','Marketing'),('editor','Editor');

INSERT INTO permissions(code) VALUES ('users.manage'),('roles.manage'),('motorcycles.read'),('motorcycles.write'),('motorcycles.publish'),('brands.manage'),('categories.manage'),('media.manage'),('inventory.manage'),('promotions.manage'),('leads.manage'),('quotes.manage'),('content.manage'),('settings.manage'),('analytics.read'),('audit.read');

INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='admin';

INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='sales' AND p.code IN ('motorcycles.read','inventory.manage','leads.manage','quotes.manage','analytics.read');

INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='marketing' AND p.code IN ('motorcycles.read','promotions.manage','media.manage','content.manage','analytics.read');

INSERT INTO role_permissions(role_id,permission_id) SELECT r.id,p.id FROM roles r CROSS JOIN permissions p WHERE r.code='editor' AND p.code IN ('motorcycles.read','motorcycles.write','brands.manage','categories.manage','media.manage','content.manage');

INSERT INTO inventory_locations(name,code) VALUES ('Principal','MAIN');
INSERT INTO site_contact(site_key,site_name) VALUES ('main','MotoApex Costa Rica');
INSERT INTO settings(setting_key,setting_value,is_public) VALUES ('site_url','https://motoapexcr.com',1),('admin_url','https://admin.motoapexcr.com',0),('api_url','https://api.motoapexcr.com',0),('timezone','America/Costa_Rica',1),('default_currency','CRC',1);

CREATE VIEW v_motorcycle_inventory AS
SELECT m.id AS motorcycle_id, COALESCE(SUM(s.quantity),0) AS quantity,
 COALESCE(SUM(s.reserved_quantity),0) AS reserved_quantity,
 COALESCE(SUM(s.quantity-s.reserved_quantity),0) AS available_quantity
FROM motorcycles m LEFT JOIN inventory_stock s ON s.motorcycle_id=m.id GROUP BY m.id;

CREATE VIEW v_active_promotions AS
SELECT p.* FROM promotions p WHERE p.status='active' AND p.deleted_at IS NULL
AND UTC_TIMESTAMP() BETWEEN p.starts_at AND p.ends_at;

CREATE TABLE schema_migrations (version VARCHAR(100) NOT NULL PRIMARY KEY, applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
INSERT INTO schema_migrations(version) VALUES ('001_initial_schema');
