-- Run once on an existing EventNest database.
CREATE TABLE IF NOT EXISTS TICKET_WAITLIST (
    WaitlistID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    UserID INT UNSIGNED NOT NULL,
    TicketID INT UNSIGNED NOT NULL,
    Quantity INT UNSIGNED NOT NULL DEFAULT 1,
    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    NotifiedAt DATETIME NULL,

    PRIMARY KEY (WaitlistID),
    UNIQUE KEY uq_waitlist_user_ticket (UserID, TicketID),
    KEY idx_waitlist_ticket (TicketID, NotifiedAt, CreatedAt),

    CONSTRAINT fk_waitlist_user
        FOREIGN KEY (UserID) REFERENCES USERS(UserID)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_waitlist_ticket
        FOREIGN KEY (TicketID) REFERENCES TICKETS(TicketID)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT chk_waitlist_quantity CHECK (Quantity > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
