-- Migration 009: time als eigener Task-Typ + max_count für Zähler-Aufgaben
-- Datum: 2026-05-11
-- time-Typ: Zeitwertung — FP = floor((Istzeit - Sollzeit) / Zeiteinheit) * zeitstrafe_fp
-- max_count: Obergrenze des Steppers (Nummer-Tasks), z.B. 4 Teilnehmer oder 72 Antworten

ALTER TABLE station_tasks
    MODIFY COLUMN type ENUM('count','boolean','time') NOT NULL DEFAULT 'boolean',
    ADD COLUMN max_count SMALLINT UNSIGNED NULL AFTER points;
