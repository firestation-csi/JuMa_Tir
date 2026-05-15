-- Migration 021: Zielgruppen-Filter für group_announcements
-- NULL = Broadcast an alle Gruppen, JSON-Array = nur diese Gruppen-IDs
ALTER TABLE group_announcements
    ADD COLUMN target_group_ids TEXT NULL DEFAULT NULL AFTER body;
