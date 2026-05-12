CREATE TABLE group_locations (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    group_id    INT            NOT NULL,
    lat         DECIMAL(10,7)  NOT NULL,
    lng         DECIMAL(10,7)  NOT NULL,
    accuracy    FLOAT          NULL,
    recorded_at TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_id) REFERENCES `groups`(id) ON DELETE CASCADE,
    INDEX idx_group_recorded (group_id, recorded_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
