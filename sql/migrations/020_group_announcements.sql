-- Migration 020: Gruppenansagen (Nachrichten von Admin an alle Gruppen)
CREATE TABLE group_announcements (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    competition_id INT          NOT NULL,
    body           TEXT         NOT NULL,
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (competition_id) REFERENCES competitions(id) ON DELETE CASCADE,
    INDEX idx_comp_created (competition_id, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
