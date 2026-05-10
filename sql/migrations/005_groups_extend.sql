-- Migration 005: Gruppen erweitern + Station-Besuchsprotokoll
-- Datum: 2026-05-10

-- Neue Felder für groups
ALTER TABLE `groups`
    ADD COLUMN registration_date DATE          AFTER competition_id,
    ADD COLUMN active            TINYINT(1)    NOT NULL DEFAULT 1  AFTER qr_token,
    ADD COLUMN kbm_area          VARCHAR(100)  AFTER active,
    ADD COLUMN last_station_id   INT           AFTER kbm_area,
    ADD CONSTRAINT fk_groups_last_station
        FOREIGN KEY (last_station_id) REFERENCES stations (id) ON DELETE SET NULL;

-- Anmeldeverlauf: welche Gruppe war wann an welcher Station
CREATE TABLE IF NOT EXISTS group_station_log (
    id           INT      NOT NULL AUTO_INCREMENT,
    group_id     INT      NOT NULL,
    station_id   INT      NOT NULL,
    checked_in   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    checked_out  DATETIME,
    PRIMARY KEY (id),
    CONSTRAINT fk_log_group   FOREIGN KEY (group_id)   REFERENCES `groups`  (id) ON DELETE CASCADE,
    CONSTRAINT fk_log_station FOREIGN KEY (station_id) REFERENCES stations  (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
