-- Migration 011: Anzahl Zeit-Felder pro Aufgabe
-- Datum: 2026-05-11
-- zeit_felder = 1  → ein Stoppuhr-Wert für die Gruppe (bisheriges Verhalten)
-- zeit_felder > 1  → je ein Stoppuhr-Wert pro Teilnehmer (z.B. 4 beim Zielwurf)

ALTER TABLE station_tasks
    ADD COLUMN zeit_felder TINYINT UNSIGNED NOT NULL DEFAULT 1 AFTER zeiteinheit_sek;
