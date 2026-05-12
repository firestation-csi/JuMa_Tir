ALTER TABLE group_station_log
    ADD COLUMN laufweg_id INT NULL AFTER station_id,
    ADD CONSTRAINT fk_log_laufweg FOREIGN KEY (laufweg_id) REFERENCES laufwege (id) ON DELETE SET NULL,
    ADD INDEX idx_log_laufweg (laufweg_id);
