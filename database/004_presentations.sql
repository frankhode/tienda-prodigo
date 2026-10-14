-- Extensión no destructiva: una presentación opcional por pieza.
-- Puede ejecutarse desde «Habilitar plantillas» en el panel.
CREATE TABLE IF NOT EXISTS piece_presentations (
 piece_id BIGINT UNSIGNED PRIMARY KEY,
 template VARCHAR(20) NOT NULL DEFAULT 'panels',
 title_font VARCHAR(20) NOT NULL DEFAULT 'classic',
 body_font VARCHAR(20) NOT NULL DEFAULT 'sans',
 title_size VARCHAR(10) NOT NULL DEFAULT 'medium',
 body_size VARCHAR(10) NOT NULL DEFAULT 'medium',
 title_style VARCHAR(10) NOT NULL DEFAULT 'normal',
 text_position VARCHAR(10) NOT NULL DEFAULT 'left',
 tone VARCHAR(10) NOT NULL DEFAULT 'dark',
 veil TINYINT UNSIGNED NOT NULL DEFAULT 60,
 focal_x TINYINT UNSIGNED NOT NULL DEFAULT 50,
 focal_y TINYINT UNSIGNED NOT NULL DEFAULT 50,
 `lead` VARCHAR(600) NULL,
 FOREIGN KEY (piece_id) REFERENCES pieces(id),
 CHECK (template IN ('panels','wallpaper')),
 CHECK (veil IN (20,40,60,80)),
 CHECK (focal_x BETWEEN 0 AND 100),
 CHECK (focal_y BETWEEN 0 AND 100)
) ENGINE=InnoDB;
