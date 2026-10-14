-- Pródigo: modelo inicial. Importar en una base vacía MySQL 8.0.16+.
-- No contiene datos privados ni credenciales. utf8mb4 preserva textos completos.
SET NAMES utf8mb4;

CREATE TABLE categories (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 name VARCHAR(100) NOT NULL,
 slug VARCHAR(120) NOT NULL UNIQUE,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 position INT NOT NULL DEFAULT 0
) ENGINE=InnoDB;

CREATE TABLE pieces (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(40) NOT NULL UNIQUE,
 slug VARCHAR(180) NOT NULL UNIQUE,
 title VARCHAR(255) NOT NULL,
 unit_type ENUM('object','set') NOT NULL DEFAULT 'object',
 component_count INT UNSIGNED NULL,
 publication_status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 availability ENUM('available','reserved','sold','not_for_sale') NOT NULL DEFAULT 'not_for_sale',
 description TEXT NULL,
 dating_label VARCHAR(255) NULL,
 year_from SMALLINT NULL,
 year_to SMALLINT NULL,
 dating_certainty ENUM('documented','estimated','unknown') NOT NULL DEFAULT 'unknown',
 dimensions_label VARCHAR(255) NULL,
 condition_description TEXT NULL,
 provenance_public TEXT NULL,
 price_mode ENUM('undecided','visible','on_request','none') NOT NULL DEFAULT 'undecided',
 price_amount DECIMAL(14,2) NULL,
 price_currency CHAR(3) NULL,
 published_at DATETIME NULL,
 deleted_at DATETIME NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
 CHECK (component_count IS NULL OR component_count > 0),
 CHECK (year_from IS NULL OR year_to IS NULL OR year_from <= year_to),
 CHECK ((price_mode = 'visible' AND price_amount IS NOT NULL AND price_amount >= 0 AND price_currency IS NOT NULL)
     OR (price_mode <> 'visible' AND price_amount IS NULL AND price_currency IS NULL)),
 INDEX idx_public_catalog (publication_status, deleted_at, availability),
 INDEX idx_dating (year_from, year_to)
) ENGINE=InnoDB;

CREATE TABLE piece_categories (
 piece_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (piece_id, category_id),
 FOREIGN KEY (piece_id) REFERENCES pieces(id),
 FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Una definición por categoría. Los valores siempre pertenecen a una categoría de la pieza.
CREATE TABLE category_fields (
 category_id BIGINT UNSIGNED NOT NULL,
 field_key VARCHAR(80) NOT NULL,
 label VARCHAR(120) NOT NULL,
 value_type ENUM('text','number','boolean','date') NOT NULL DEFAULT 'text',
 position INT NOT NULL DEFAULT 0,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 PRIMARY KEY (category_id, field_key),
 FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE piece_field_values (
 piece_id BIGINT UNSIGNED NOT NULL,
 category_id BIGINT UNSIGNED NOT NULL,
 field_key VARCHAR(80) NOT NULL,
 value_text TEXT NOT NULL,
 PRIMARY KEY (piece_id, category_id, field_key),
 FOREIGN KEY (piece_id, category_id) REFERENCES piece_categories(piece_id, category_id),
 FOREIGN KEY (category_id, field_key) REFERENCES category_fields(category_id, field_key)
) ENGINE=InnoDB;

CREATE TABLE piece_private (
 piece_id BIGINT UNSIGNED PRIMARY KEY,
 physical_location VARCHAR(255) NULL,
 acquired_on DATE NULL,
 acquisition_cost DECIMAL(14,2) NULL,
 cost_currency CHAR(3) NULL,
 acquisition_source TEXT NULL,
 internal_notes TEXT NULL,
 FOREIGN KEY (piece_id) REFERENCES pieces(id),
 CHECK (acquisition_cost IS NULL OR acquisition_cost >= 0),
 CHECK ((acquisition_cost IS NULL AND cost_currency IS NULL) OR (acquisition_cost IS NOT NULL AND cost_currency IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE images (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 piece_id BIGINT UNSIGNED NOT NULL,
 role ENUM('presentation','descriptive','detail') NOT NULL DEFAULT 'descriptive',
 position INT NOT NULL DEFAULT 0,
 alt_text VARCHAR(500) NOT NULL,
 caption TEXT NULL,
 focal_x DECIMAL(5,4) NOT NULL DEFAULT 0.5,
 focal_y DECIMAL(5,4) NOT NULL DEFAULT 0.5,
 FOREIGN KEY (piece_id) REFERENCES pieces(id),
 UNIQUE KEY uq_image_piece (piece_id, id),
 CHECK (focal_x BETWEEN 0 AND 1),
 CHECK (focal_y BETWEEN 0 AND 1)
) ENGINE=InnoDB;

-- Una imagen lógica tiene miniatura, vista web y zoom; los originales quedan aparte.
CREATE TABLE image_variants (
 image_id BIGINT UNSIGNED NOT NULL,
 variant ENUM('thumbnail','web','zoom') NOT NULL,
 storage_backend VARCHAR(40) NOT NULL DEFAULT 'local',
 storage_key VARCHAR(500) NOT NULL,
 mime_type VARCHAR(80) NOT NULL,
 width INT UNSIGNED NOT NULL,
 height INT UNSIGNED NOT NULL,
 byte_size BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (image_id, variant),
 FOREIGN KEY (image_id) REFERENCES images(id),
 CHECK (width > 0 AND height > 0)
) ENGINE=InnoDB;

CREATE TABLE piece_cover (
 piece_id BIGINT UNSIGNED PRIMARY KEY,
 image_id BIGINT UNSIGNED NOT NULL,
 FOREIGN KEY (piece_id, image_id) REFERENCES images(piece_id, id)
) ENGINE=InnoDB;

CREATE TABLE terms (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 facet ENUM('period','place','maker','material','style','theme','use','visual') NOT NULL,
 label VARCHAR(180) NOT NULL,
 UNIQUE KEY uq_term (facet, label)
) ENGINE=InnoDB;

CREATE TABLE piece_terms (
 piece_id BIGINT UNSIGNED NOT NULL,
 term_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (piece_id, term_id),
 FOREIGN KEY (piece_id) REFERENCES pieces(id),
 FOREIGN KEY (term_id) REFERENCES terms(id)
) ENGINE=InnoDB;

-- Vínculos editoriales dirigidos: para ambos sentidos crear dos filas con sus propios textos.
CREATE TABLE piece_relations (
 source_id BIGINT UNSIGNED NOT NULL,
 target_id BIGINT UNSIGNED NOT NULL,
 relation_type ENUM('historical','visual','evocative','personal') NOT NULL,
 explanation TEXT NOT NULL,
 position INT NOT NULL DEFAULT 0,
 PRIMARY KEY (source_id, target_id, relation_type),
 FOREIGN KEY (source_id) REFERENCES pieces(id),
 FOREIGN KEY (target_id) REFERENCES pieces(id),
 CHECK (source_id <> target_id),
 CHECK (CHAR_LENGTH(TRIM(explanation)) > 0),
 INDEX idx_relation_target (target_id)
) ENGINE=InnoDB;

CREATE TABLE collections (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 slug VARCHAR(180) NOT NULL UNIQUE,
 title VARCHAR(255) NOT NULL,
 kind ENUM('selection','route','exhibition') NOT NULL DEFAULT 'selection',
 introduction TEXT NULL,
 publication_status ENUM('draft','published','archived') NOT NULL DEFAULT 'draft',
 deleted_at DATETIME NULL
) ENGINE=InnoDB;

CREATE TABLE collection_pieces (
 collection_id BIGINT UNSIGNED NOT NULL,
 piece_id BIGINT UNSIGNED NOT NULL,
 position INT NOT NULL DEFAULT 0,
 editorial_text TEXT NULL,
 PRIMARY KEY (collection_id, piece_id),
 FOREIGN KEY (collection_id) REFERENCES collections(id),
 FOREIGN KEY (piece_id) REFERENCES pieces(id)
) ENGINE=InnoDB;

-- Seguimiento privado, incluso cuando la consulta llegó por WhatsApp o correo.
CREATE TABLE inquiries (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 contact_name VARCHAR(255) NULL,
 contact_channel VARCHAR(80) NULL,
 contact_reference VARCHAR(255) NULL,
 status ENUM('new','in_progress','closed') NOT NULL DEFAULT 'new',
 internal_notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE inquiry_pieces (
 inquiry_id BIGINT UNSIGNED NOT NULL,
 piece_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY (inquiry_id, piece_id),
 FOREIGN KEY (inquiry_id) REFERENCES inquiries(id),
 FOREIGN KEY (piece_id) REFERENCES pieces(id)
) ENGINE=InnoDB;

-- Sólo una operación activa por pieza; bloquear la fila de pieces al reservar/vender.
CREATE TABLE transactions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 piece_id BIGINT UNSIGNED NOT NULL,
 inquiry_id BIGINT UNSIGNED NULL,
 status ENUM('reserved','completed','cancelled') NOT NULL,
 active_piece_id BIGINT UNSIGNED GENERATED ALWAYS AS
   (CASE WHEN status IN ('reserved','completed') THEN piece_id ELSE NULL END) STORED,
 amount DECIMAL(14,2) NULL,
 currency CHAR(3) NULL,
 reserved_until DATETIME NULL,
 completed_at DATETIME NULL,
 internal_notes TEXT NULL,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 UNIQUE KEY uq_active_transaction (active_piece_id),
 FOREIGN KEY (piece_id) REFERENCES pieces(id),
 FOREIGN KEY (inquiry_id) REFERENCES inquiries(id),
 CHECK (amount IS NULL OR amount >= 0),
 CHECK ((amount IS NULL AND currency IS NULL) OR (amount IS NOT NULL AND currency IS NOT NULL))
) ENGINE=InnoDB;

CREATE TABLE admins (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 email VARCHAR(255) NOT NULL UNIQUE,
 password_hash VARCHAR(255) NOT NULL,
 active BOOLEAN NOT NULL DEFAULT TRUE,
 created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Proyección explícita: jamás incluir tablas privadas en la API pública.
CREATE VIEW public_pieces AS
 SELECT id, code, slug, title, unit_type, component_count, availability,
 description, dating_label, year_from, year_to, dating_certainty,
 dimensions_label, condition_description, provenance_public,
 price_mode, price_amount, price_currency, published_at
 FROM pieces
 WHERE publication_status = 'published' AND deleted_at IS NULL;
