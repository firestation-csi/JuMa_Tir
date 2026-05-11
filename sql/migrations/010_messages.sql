-- Migration 010: Nachrichten-Tabelle (Zentrale ↔ Schiedsrichter)
-- Datum: 2026-05-11
-- sender 'zentrale' = Admin hat gesendet, 'judge' = Schiedsrichter hat gesendet

CREATE TABLE IF NOT EXISTS messages (
    id         INT          NOT NULL AUTO_INCREMENT,
    station_id INT          NOT NULL,
    judge_id   INT          NULL,                              -- NULL = von Zentrale gesendet
    sender     ENUM('zentrale','judge') NOT NULL DEFAULT 'zentrale',
    body       TEXT         NOT NULL,
    read_at    DATETIME     NULL,
    created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    CONSTRAINT fk_messages_station FOREIGN KEY (station_id)
        REFERENCES stations (id) ON DELETE CASCADE,
    CONSTRAINT fk_messages_judge FOREIGN KEY (judge_id)
        REFERENCES judges (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
