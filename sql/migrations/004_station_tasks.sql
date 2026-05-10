-- Migration 004: Aufgaben pro Station
-- Datum: 2026-05-10
-- Typ 'count'   = Zähler je Teilnehmer (Plus/Minus in der App)
-- Typ 'boolean' = Ja/Nein für die Gruppe

CREATE TABLE IF NOT EXISTS station_tasks (
    id         INT          NOT NULL AUTO_INCREMENT,
    station_id INT          NOT NULL,
    label      VARCHAR(255) NOT NULL,
    type       ENUM('count','boolean') NOT NULL DEFAULT 'boolean',
    points     SMALLINT     NOT NULL DEFAULT 1,
    sort_order TINYINT      NOT NULL DEFAULT 0,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_station_tasks_station FOREIGN KEY (station_id)
        REFERENCES stations (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
