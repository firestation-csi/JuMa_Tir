-- Migration 006: group_members umstrukturieren
-- alter_jahre entfernen, geburtsdatum + geschlecht hinzufügen
-- Datum: 2026-05-10

ALTER TABLE group_members
    DROP COLUMN alter_jahre,
    ADD COLUMN geburtsdatum DATE                   AFTER name,
    ADD COLUMN geschlecht   ENUM('m','w','d') NULL AFTER geburtsdatum;
