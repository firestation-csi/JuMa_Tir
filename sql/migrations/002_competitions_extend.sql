-- Migration 002: Wettbewerb um Ort, Hash und Koordinaten erweitern

ALTER TABLE competitions
    ADD COLUMN location VARCHAR(255)   AFTER name,
    ADD COLUMN hash      CHAR(32)       AFTER status,
    ADD COLUMN lat       DECIMAL(10,7)  AFTER hash,
    ADD COLUMN lng       DECIMAL(10,7)  AFTER lat,
    ADD UNIQUE KEY uq_competitions_hash (hash);
