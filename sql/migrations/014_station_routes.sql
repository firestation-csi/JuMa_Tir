CREATE TABLE IF NOT EXISTS station_routes (
    id              INT            NOT NULL AUTO_INCREMENT,
    competition_id  INT            NOT NULL,
    from_station_id INT            NOT NULL,
    to_station_id   INT            NOT NULL,
    distance_m      SMALLINT UNSIGNED DEFAULT NULL,
    est_time_min    TINYINT UNSIGNED  DEFAULT NULL,
    sort_order      TINYINT UNSIGNED  NOT NULL DEFAULT 0,
    notes           VARCHAR(255)   DEFAULT NULL,
    created_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at      DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_route (competition_id, from_station_id, to_station_id),
    CONSTRAINT fk_route_comp  FOREIGN KEY (competition_id)  REFERENCES competitions (id) ON DELETE CASCADE,
    CONSTRAINT fk_route_from  FOREIGN KEY (from_station_id) REFERENCES stations     (id) ON DELETE CASCADE,
    CONSTRAINT fk_route_to    FOREIGN KEY (to_station_id)   REFERENCES stations     (id) ON DELETE CASCADE,
    INDEX idx_route_comp (competition_id),
    INDEX idx_route_sort (competition_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
