-- Migration 008: task_results JSON-Spalte zu scores hinzufügen
-- Ersetzt die separate Speicherung in score_criteria / score_penalties
-- Datum: 2026-05-10

ALTER TABLE scores
    ADD COLUMN task_results JSON NULL AFTER notes;
