-- Vollständiges Datenbankschema JuMa Tirol Wettbewerbs-Auswertungstool
-- Zeichensatz: utf8mb4, Kollation: utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- Wettbewerbe
CREATE TABLE IF NOT EXISTS competitions (
    id         INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    date       DATE         NOT NULL,
    status     ENUM('active','finished','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stationen
CREATE TABLE IF NOT EXISTS stations (
    id             INT          NOT NULL AUTO_INCREMENT,
    competition_id INT          NOT NULL,
    name           VARCHAR(255) NOT NULL,
    task           TEXT,
    max_score      SMALLINT     NOT NULL DEFAULT 10,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_stations_competition FOREIGN KEY (competition_id)
        REFERENCES competitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Schiedsrichter
CREATE TABLE IF NOT EXISTS judges (
    id         INT          NOT NULL AUTO_INCREMENT,
    station_id INT          NOT NULL,
    name       VARCHAR(255) NOT NULL,
    qr_token   CHAR(32)     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_judges_token (qr_token),
    CONSTRAINT fk_judges_station FOREIGN KEY (station_id)
        REFERENCES stations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Teilnehmergruppen
CREATE TABLE IF NOT EXISTS `groups` (
    id             INT          NOT NULL AUTO_INCREMENT,
    competition_id INT          NOT NULL,
    name           VARCHAR(255) NOT NULL,
    qr_token       CHAR(32)     NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_groups_token (qr_token),
    CONSTRAINT fk_groups_competition FOREIGN KEY (competition_id)
        REFERENCES competitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Bewertungen
CREATE TABLE IF NOT EXISTS scores (
    id         INT          NOT NULL AUTO_INCREMENT,
    judge_id   INT          NOT NULL,
    group_id   INT          NOT NULL,
    station_id INT          NOT NULL,
    value      DECIMAL(6,2) NOT NULL,
    notes      TEXT,
    synced_at  DATETIME,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_score (judge_id, group_id, station_id),
    CONSTRAINT fk_scores_judge   FOREIGN KEY (judge_id)   REFERENCES judges   (id) ON DELETE RESTRICT,
    CONSTRAINT fk_scores_group   FOREIGN KEY (group_id)   REFERENCES `groups` (id) ON DELETE RESTRICT,
    CONSTRAINT fk_scores_station FOREIGN KEY (station_id) REFERENCES stations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Offline-Queue (clientseitig temporär, hier nur zur Vollständigkeit)
CREATE TABLE IF NOT EXISTS offline_queue (
    id         INT          NOT NULL AUTO_INCREMENT,
    judge_id   INT          NOT NULL,
    group_id   INT          NOT NULL,
    station_id INT          NOT NULL,
    value      DECIMAL(6,2) NOT NULL,
    notes      TEXT,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    synced_at  DATETIME,
    PRIMARY KEY (id),
    CONSTRAINT fk_oq_judge   FOREIGN KEY (judge_id)   REFERENCES judges   (id) ON DELETE CASCADE,
    CONSTRAINT fk_oq_group   FOREIGN KEY (group_id)   REFERENCES `groups` (id) ON DELETE CASCADE,
    CONSTRAINT fk_oq_station FOREIGN KEY (station_id) REFERENCES stations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
