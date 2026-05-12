-- Gruppen-Hilfeanfragen im Message-Board ermöglichen
ALTER TABLE messages
    ADD COLUMN group_id   INT          NULL AFTER judge_id,
    ADD COLUMN group_name VARCHAR(150) NULL AFTER group_id;
