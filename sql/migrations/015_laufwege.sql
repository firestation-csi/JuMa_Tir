CREATE TABLE IF NOT EXISTS laufwege (
    id             INT           NOT NULL AUTO_INCREMENT,
    competition_id INT           NOT NULL,
    name           VARCHAR(100)  NOT NULL,
    color          VARCHAR(20)   NOT NULL DEFAULT '#C0392B',
    sort_order     TINYINT UNSIGNED NOT NULL DEFAULT 0,
    notes          VARCHAR(255)  DEFAULT NULL,
    created_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_laufweg_comp FOREIGN KEY (competition_id) REFERENCES competitions (id) ON DELETE CASCADE,
    INDEX idx_laufweg_comp (competition_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE station_routes
    ADD COLUMN laufweg_id INT NULL AFTER competition_id,
    ADD CONSTRAINT fk_route_laufweg FOREIGN KEY (laufweg_id) REFERENCES laufwege (id) ON DELETE SET NULL;
