-- Migration 003: Stationen um active, hash, lat, lng erweitern
-- Datum: 2026-05-10

ALTER TABLE stations
    ADD COLUMN active TINYINT(1)  NOT NULL DEFAULT 1 AFTER has_time,
    ADD COLUMN hash   CHAR(32)    UNIQUE           AFTER active,
    ADD COLUMN lat    DECIMAL(10,7)                AFTER hash,
    ADD COLUMN lng    DECIMAL(10,7)                AFTER lat;
