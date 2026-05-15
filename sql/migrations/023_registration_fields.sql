-- Migration 023: Selbstanmeldung für Gruppen
ALTER TABLE `groups`
    ADD COLUMN geschlecht       VARCHAR(20)  NULL DEFAULT NULL AFTER name,
    ADD COLUMN self_registered  TINYINT(1)   NOT NULL DEFAULT 0 AFTER active;

ALTER TABLE group_members
    ADD COLUMN geschlecht   VARCHAR(10) NULL DEFAULT NULL AFTER funktion,
    ADD COLUMN geburtsdatum DATE        NULL DEFAULT NULL AFTER geschlecht;
