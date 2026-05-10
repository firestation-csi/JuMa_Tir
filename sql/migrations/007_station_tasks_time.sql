-- Migration 007: Zeitwertung zu station_tasks hinzufügen
-- Datum: 2026-05-10
-- Formel App: FP = floor((ist - sollzeit_sek) / zeiteinheit_sek) * zeitstrafe_fp
--             gedeckelt auf floor((hoechstzeit_sek - sollzeit_sek) / zeiteinheit_sek) * zeitstrafe_fp

ALTER TABLE station_tasks
    ADD COLUMN sollzeit_sek    SMALLINT UNSIGNED NULL AFTER points,
    ADD COLUMN hoechstzeit_sek SMALLINT UNSIGNED NULL AFTER sollzeit_sek,
    ADD COLUMN zeitstrafe_fp   TINYINT  UNSIGNED NULL AFTER hoechstzeit_sek,
    ADD COLUMN zeiteinheit_sek TINYINT  UNSIGNED NULL AFTER zeitstrafe_fp;
