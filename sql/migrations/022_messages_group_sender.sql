-- Migration 022: messages-Tabelle um Gruppenabsender erweitern
ALTER TABLE messages
    MODIFY COLUMN sender ENUM('zentrale','judge','group') NOT NULL DEFAULT 'zentrale',
    ADD COLUMN group_id   INT          NULL DEFAULT NULL AFTER judge_id,
    ADD COLUMN group_name VARCHAR(100) NULL DEFAULT NULL AFTER group_id;
