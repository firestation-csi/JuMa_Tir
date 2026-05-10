-- JuMa Tirol Wettbewerbs-Auswertungstool — Datenbankschema
-- Bewertungsmodell: Fehlerpunkte-System (Kriterien + Strafen)
-- Zeichensatz: utf8mb4_unicode_ci

SET NAMES utf8mb4;
SET foreign_key_checks = 0;

-- ----------------------------------------------------------------
-- Wettbewerbe
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS competitions (
    id         INT          NOT NULL AUTO_INCREMENT,
    name       VARCHAR(255) NOT NULL,
    date       DATE         NOT NULL,
    status     ENUM('active','finished','archived') NOT NULL DEFAULT 'active',
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Stationen
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS stations (
    id             INT          NOT NULL AUTO_INCREMENT,
    competition_id INT          NOT NULL,
    code           VARCHAR(10)  NOT NULL,            -- z.B. "A06"
    name           VARCHAR(255) NOT NULL,
    task           TEXT,
    location       VARCHAR(255),
    has_time       TINYINT(1)   NOT NULL DEFAULT 0,  -- Stoppuhr aktiv?
    version        VARCHAR(20)  NOT NULL DEFAULT '2026.1',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_stations_competition FOREIGN KEY (competition_id)
        REFERENCES competitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Wertungskriterien (z.B. Knoten) einer Station
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS station_criteria (
    id         INT          NOT NULL AUTO_INCREMENT,
    station_id INT          NOT NULL,
    code       VARCHAR(20)  NOT NULL,               -- interner Code, z.B. "mast"
    label      VARCHAR(255) NOT NULL,               -- Anzeigename, z.B. "Mastwurf"
    hint       VARCHAR(255),                        -- Hinweis für Schiedsrichter
    weight     SMALLINT     NOT NULL DEFAULT 5,     -- Fehlerpunkte bei Fehler
    sort_order TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_criteria_station FOREIGN KEY (station_id)
        REFERENCES stations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Strafpunkte-Kategorien einer Station
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS station_penalties (
    id         INT          NOT NULL AUTO_INCREMENT,
    station_id INT          NOT NULL,
    code       VARCHAR(20)  NOT NULL,               -- interner Code, z.B. "sicht"
    label      VARCHAR(255) NOT NULL,               -- z.B. "Knoten nicht sichtbar"
    weight     SMALLINT     NOT NULL DEFAULT 5,     -- Fehlerpunkte je Vorfall
    max_count  TINYINT      NOT NULL DEFAULT 10,    -- maximale Anzahl Vorfälle
    sort_order TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_penalties_station FOREIGN KEY (station_id)
        REFERENCES stations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Schiedsrichter
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS judges (
    id         INT          NOT NULL AUTO_INCREMENT,
    station_id INT          NOT NULL,
    name       VARCHAR(255) NOT NULL,
    initials   VARCHAR(5),
    role       VARCHAR(100) NOT NULL DEFAULT 'Schiedsrichter A',
    qr_token   CHAR(32)     NOT NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_judges_token (qr_token),
    CONSTRAINT fk_judges_station FOREIGN KEY (station_id)
        REFERENCES stations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Teilnehmergruppen
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `groups` (
    id             INT          NOT NULL AUTO_INCREMENT,
    competition_id INT          NOT NULL,
    num            SMALLINT     NOT NULL,            -- Startnummer
    name           VARCHAR(255) NOT NULL,
    kreis          VARCHAR(255),                     -- Kreisfeuerwehrverband
    altersgruppe   VARCHAR(50),                      -- z.B. "AG I", "AG II"
    startnr        VARCHAR(20),                      -- z.B. "B-14"
    qr_token       CHAR(32)     NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_groups_token (qr_token),
    CONSTRAINT fk_groups_competition FOREIGN KEY (competition_id)
        REFERENCES competitions (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Gruppenmitglieder
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS group_members (
    id         INT          NOT NULL AUTO_INCREMENT,
    group_id   INT          NOT NULL,
    vorname    VARCHAR(100) NOT NULL,
    name       VARCHAR(100) NOT NULL,
    alter_jahre TINYINT UNSIGNED,
    funktion   VARCHAR(100),                         -- z.B. "Gruppenführer", "Maschinist"
    sort_order TINYINT      NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    CONSTRAINT fk_members_group FOREIGN KEY (group_id)
        REFERENCES `groups` (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Bewertungen (Kopf-Datensatz pro Gruppe/Station)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS scores (
    id           INT          NOT NULL AUTO_INCREMENT,
    judge_id     INT          NOT NULL,
    group_id     INT          NOT NULL,
    station_id   INT          NOT NULL,
    time_ms      INT          UNSIGNED,              -- Stoppuhr-Zeit in Millisekunden
    impression   ENUM('sehr_gut','gut','befriedigend') NOT NULL DEFAULT 'gut',
    total_fp     SMALLINT     NOT NULL DEFAULT 0,    -- Fehlerpunkte gesamt (denormalisiert)
    notes        TEXT,
    synced_at    DATETIME,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_score (judge_id, group_id, station_id),
    CONSTRAINT fk_scores_judge   FOREIGN KEY (judge_id)   REFERENCES judges   (id) ON DELETE RESTRICT,
    CONSTRAINT fk_scores_group   FOREIGN KEY (group_id)   REFERENCES `groups` (id) ON DELETE RESTRICT,
    CONSTRAINT fk_scores_station FOREIGN KEY (station_id) REFERENCES stations (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Kriterien-Ergebnisse pro Bewertung
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS score_criteria (
    id          INT  NOT NULL AUTO_INCREMENT,
    score_id    INT  NOT NULL,
    criterion_id INT NOT NULL,
    result      ENUM('ok','fail') NOT NULL DEFAULT 'ok',
    PRIMARY KEY (id),
    UNIQUE KEY uq_score_criterion (score_id, criterion_id),
    CONSTRAINT fk_sc_score     FOREIGN KEY (score_id)     REFERENCES scores           (id) ON DELETE CASCADE,
    CONSTRAINT fk_sc_criterion FOREIGN KEY (criterion_id) REFERENCES station_criteria (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Strafen-Zähler pro Bewertung
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS score_penalties (
    id          INT     NOT NULL AUTO_INCREMENT,
    score_id    INT     NOT NULL,
    penalty_id  INT     NOT NULL,
    count       TINYINT NOT NULL DEFAULT 0,
    PRIMARY KEY (id),
    UNIQUE KEY uq_score_penalty (score_id, penalty_id),
    CONSTRAINT fk_sp_score   FOREIGN KEY (score_id)  REFERENCES scores           (id) ON DELETE CASCADE,
    CONSTRAINT fk_sp_penalty FOREIGN KEY (penalty_id) REFERENCES station_penalties (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ----------------------------------------------------------------
-- Offline-Queue
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS offline_queue (
    id         INT          NOT NULL AUTO_INCREMENT,
    judge_id   INT          NOT NULL,
    payload    JSON         NOT NULL,               -- komplettes Bewertungsobjekt als JSON
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    synced_at  DATETIME,
    PRIMARY KEY (id),
    CONSTRAINT fk_oq_judge FOREIGN KEY (judge_id) REFERENCES judges (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET foreign_key_checks = 1;
