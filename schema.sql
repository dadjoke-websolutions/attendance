-- Anwesenheitsliste – Schema
-- Import via phpMyAdmin oder: mysql -u USER -p DBNAME < schema.sql

CREATE TABLE IF NOT EXISTS participants (
  id         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  first_name VARCHAR(80)  NOT NULL,
  last_name  VARCHAR(80)  NOT NULL DEFAULT '',
  gender     ENUM('f','m','x') NOT NULL DEFAULT 'x',
  birth_year SMALLINT UNSIGNED NULL,
  mobile     VARCHAR(40)  NOT NULL DEFAULT '',
  email      VARCHAR(190) NOT NULL DEFAULT '',
  notes      TEXT         NULL,
  active     TINYINT(1)   NOT NULL DEFAULT 1,
  whatsapp   TINYINT(1)   NOT NULL DEFAULT 0,
  created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_name (first_name, last_name),
  KEY idx_active (active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS trainings (
  id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  training_date DATE         NOT NULL,
  label         VARCHAR(80)  NOT NULL DEFAULT '',
  PRIMARY KEY (id),
  UNIQUE KEY uq_training (training_date, label)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS attendance (
  training_id    INT UNSIGNED NOT NULL,
  participant_id INT UNSIGNED NOT NULL,
  status         ENUM('present','absent') NOT NULL,
  updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (training_id, participant_id),
  KEY idx_participant (participant_id),
  CONSTRAINT fk_att_training    FOREIGN KEY (training_id)    REFERENCES trainings (id)    ON DELETE CASCADE,
  CONSTRAINT fk_att_participant FOREIGN KEY (participant_id) REFERENCES participants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
