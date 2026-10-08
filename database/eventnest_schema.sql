CREATE DATABASE IF NOT EXISTS eventnest
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE eventnest;

SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS BLOCKS;
DROP TABLE IF EXISTS REVIEWS;
DROP TABLE IF EXISTS Post_Categories;
DROP TABLE IF EXISTS REACTIONS;
DROP TABLE IF EXISTS POSTS;
DROP TABLE IF EXISTS PAYMENTS;
DROP TABLE IF EXISTS BOOKINGS;
DROP TABLE IF EXISTS TICKETS;
DROP TABLE IF EXISTS USER_EVENT_RECOMMENDATIONS;
DROP TABLE IF EXISTS EVENTS;
DROP TABLE IF EXISTS USERS;
DROP TABLE IF EXISTS CATEGORIES;

SET FOREIGN_KEY_CHECKS = 1;


-- ============================================================
-- CATEGORIES
-- ============================================================

CREATE TABLE CATEGORIES (
    CategoryID INT UNSIGNED NOT NULL AUTO_INCREMENT,
    CategoryName VARCHAR(100) NOT NULL,
    Description TEXT NULL,

    PRIMARY KEY (CategoryID),
    UNIQUE KEY uq_categories_name (CategoryName)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- USERS
-- ============================================================

CREATE TABLE USERS (
    UserID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    FirstName VARCHAR(50) NULL,
    LastName VARCHAR(50) NULL,
    Email VARCHAR(100) NOT NULL,
    PasswordHash VARCHAR(255) NULL,
    Phone VARCHAR(15) NULL,
    CareerField VARCHAR(100) NULL,

    UserStatus ENUM('active','suspended','deleted')
        NOT NULL DEFAULT 'active',

    Role ENUM('attendee','organiser','admin')
        NOT NULL DEFAULT 'attendee',

    -- Includes rejected because the approved admin verification flow
    -- explicitly supports approve/reject decisions.
    VerificationStatus ENUM('unverified','pending','verified','rejected')
        NOT NULL DEFAULT 'unverified',

    -- Approved extension for the protected organiser verification upload.
    Verification_docs_path VARCHAR(255) NULL,

    RecommendationOptIn BOOLEAN NOT NULL DEFAULT FALSE,

    -- Approved moderation/suspension extensions.
    Blocks_count INT NOT NULL DEFAULT 0,
    Suspension_count INT NOT NULL DEFAULT 0,
    Suspension_start_date DATETIME NULL,

    Is_email_verified BOOLEAN NOT NULL DEFAULT FALSE,

    CreatedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    LastLoginAt DATETIME NULL,

    -- Approved organiser verification-fee extension.
    VerificationFeePaidAt DATETIME NULL,
    VerificationFeePaidUntil DATETIME NULL,

    VerificationFeePaymentStatus
        ENUM('unpaid','pending','paid','failed')
        NOT NULL DEFAULT 'unpaid',

    VerificationFeeGatewayReference VARCHAR(100) NULL,

    PRIMARY KEY (UserID),

    UNIQUE KEY uq_users_email (Email),

    KEY idx_users_email (Email),
    KEY idx_users_status (UserStatus),
    KEY idx_users_role (Role),
    KEY idx_users_verification_status (VerificationStatus),
    KEY idx_users_verification_fee_until (VerificationFeePaidUntil),

    CONSTRAINT chk_users_blocks_count
        CHECK (Blocks_count >= 0),

    CONSTRAINT chk_users_suspension_count
        CHECK (Suspension_count >= 0)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- EVENTS
-- ============================================================

CREATE TABLE EVENTS (
    EventID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    OrganiserID INT UNSIGNED NOT NULL,
    CategoryID INT UNSIGNED NOT NULL,

    EventName VARCHAR(100) NOT NULL,
    Description TEXT NULL,
    Venue VARCHAR(150) NULL,
    EventDate DATETIME NOT NULL,
    AgeRestriction VARCHAR(10) NULL,

    Status ENUM('draft','published','completed','canceled')
        NOT NULL DEFAULT 'draft',

    Format ENUM('online','physical','hybrid')
        NOT NULL,

    Hashtags VARCHAR(255) NULL,

    -- Final team/ERD field name.
    CareerGoalType ENUM('none','field','email_domain')
        NOT NULL DEFAULT 'none',

    AllowedCareerField INT UNSIGNED NULL,
    AllowedEmailDomain VARCHAR(100) NULL,

    IsFeatured BOOLEAN NOT NULL DEFAULT FALSE,
    Province VARCHAR(100) NULL,

    Created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UpdatedAt DATETIME NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (EventID),

    KEY idx_events_organiser (OrganiserID),
    KEY idx_events_category (CategoryID),
    KEY idx_events_allowed_career_field (AllowedCareerField),
    KEY idx_events_status (Status),
    KEY idx_events_format (Format),
    KEY idx_events_event_date (EventDate),
    KEY idx_events_province (Province),
    KEY idx_events_featured (IsFeatured),

    CONSTRAINT fk_events_organiser
        FOREIGN KEY (OrganiserID)
        REFERENCES USERS(UserID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_events_category
        FOREIGN KEY (CategoryID)
        REFERENCES CATEGORIES(CategoryID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_events_allowed_career_field
        FOREIGN KEY (AllowedCareerField)
        REFERENCES CATEGORIES(CategoryID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_events_gate_fields
        CHECK (
            (
                CareerGoalType = 'none'
                AND AllowedCareerField IS NULL
                AND AllowedEmailDomain IS NULL
            )
            OR
            (
                CareerGoalType = 'field'
                AND AllowedCareerField IS NOT NULL
                AND AllowedEmailDomain IS NULL
            )
            OR
            (
                CareerGoalType = 'email_domain'
                AND AllowedCareerField IS NULL
                AND AllowedEmailDomain IS NOT NULL
            )
        )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- USER EVENT RECOMMENDATIONS
-- ============================================================

CREATE TABLE USER_EVENT_RECOMMENDATIONS (
    RecommendationID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    UserID INT UNSIGNED NOT NULL,
    EventID INT UNSIGNED NOT NULL,

    SimilarityScore DECIMAL(5,4) NULL,
    ModelName VARCHAR(100) NULL,

    GeneratedAt DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ExpiresAt DATETIME NULL,

    PRIMARY KEY (RecommendationID),

    UNIQUE KEY uq_user_event_recommendation (UserID, EventID),

    KEY idx_recommendations_user (UserID),
    KEY idx_recommendations_event (EventID),
    KEY idx_recommendations_expires (ExpiresAt),

    CONSTRAINT fk_recommendations_user
        FOREIGN KEY (UserID)
        REFERENCES USERS(UserID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_recommendations_event
        FOREIGN KEY (EventID)
        REFERENCES EVENTS(EventID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- TICKETS
-- ============================================================

CREATE TABLE TICKETS (
    TicketID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    EventID INT UNSIGNED NOT NULL,

    TicketType VARCHAR(50) NOT NULL,
    Price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    QuantityAvailable INT NOT NULL,
    Capacity INT NOT NULL,
    Description VARCHAR(255) NULL,

    PRIMARY KEY (TicketID),

    -- One row per ticket tier for a given event.
    UNIQUE KEY uq_tickets_event_type (EventID, TicketType),

    KEY idx_tickets_event (EventID),
    KEY idx_tickets_type (TicketType),

    CONSTRAINT fk_tickets_event
        FOREIGN KEY (EventID)
        REFERENCES EVENTS(EventID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_tickets_price
        CHECK (Price >= 0),

    CONSTRAINT chk_tickets_capacity
        CHECK (Capacity > 0),

    CONSTRAINT chk_tickets_quantity
        CHECK (
            QuantityAvailable >= 0
            AND QuantityAvailable <= Capacity
        ),

    CONSTRAINT chk_tickets_type
        CHECK (
            TicketType IN (
                'General',
                'VIP',
                'Early Bird',
                'Student'
            )
        )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BOOKINGS
-- ============================================================

CREATE TABLE BOOKINGS (
    BookingID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    EventID INT UNSIGNED NOT NULL,
    UserID INT UNSIGNED NOT NULL,
    TicketID INT UNSIGNED NOT NULL,

    BookingDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    Quantity INT NOT NULL,
    TotalAmount DECIMAL(10,2) NOT NULL DEFAULT 0.00,

    Status ENUM(
        'pending',
        'paid',
        'failed',
        'refunded',
        'canceled'
    ) NOT NULL DEFAULT 'pending',

    BookingReference VARCHAR(50) NOT NULL,

    PRIMARY KEY (BookingID),

    UNIQUE KEY uq_bookings_reference (BookingReference),

    KEY idx_bookings_reference (BookingReference),
    KEY idx_bookings_event (EventID),
    KEY idx_bookings_user (UserID),
    KEY idx_bookings_ticket (TicketID),
    KEY idx_bookings_status (Status),
    KEY idx_bookings_date (BookingDate),

    CONSTRAINT fk_bookings_event
        FOREIGN KEY (EventID)
        REFERENCES EVENTS(EventID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_bookings_user
        FOREIGN KEY (UserID)
        REFERENCES USERS(UserID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_bookings_ticket
        FOREIGN KEY (TicketID)
        REFERENCES TICKETS(TicketID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_bookings_quantity
        CHECK (Quantity > 0),

    CONSTRAINT chk_bookings_total
        CHECK (TotalAmount >= 0)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- PAYMENTS
-- ============================================================

CREATE TABLE PAYMENTS (
    PaymentID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    BookingID INT UNSIGNED NOT NULL,

    PaymentDate DATETIME NULL,
    Amount DECIMAL(10,2) NOT NULL,
    PaymentMethod VARCHAR(50) NULL,

    PaymentStatus ENUM(
        'success',
        'failed',
        'refunded'
    ) NOT NULL,

    TransactionID VARCHAR(100) NULL,

    -- Approved simulated escrow extension.
    Escrow_status ENUM(
        'held',
        'released',
        'refunded'
    ) NULL,

    PRIMARY KEY (PaymentID),

    -- BookingID is intentionally NOT UNIQUE:
    -- Retry Payment may create multiple payment-attempt records
    -- while reusing the same BookingID.
    KEY idx_payments_booking (BookingID),

    KEY idx_payments_status (PaymentStatus),
    KEY idx_payments_date (PaymentDate),
    KEY idx_payments_transaction (TransactionID),

    UNIQUE KEY uq_payments_transaction (TransactionID),

    CONSTRAINT fk_payments_booking
        FOREIGN KEY (BookingID)
        REFERENCES BOOKINGS(BookingID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_payments_amount
        CHECK (Amount >= 0)

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- POSTS
-- ============================================================

CREATE TABLE POSTS (
    PostID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    EventID INT UNSIGNED NOT NULL,
    OrganiserID INT UNSIGNED NOT NULL,

    Content TEXT NULL,

    Media_type ENUM('image','video') NULL,
    Media VARCHAR(255) NULL,

    Hashtags VARCHAR(255) NULL,
    PostDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (PostID),

    KEY idx_posts_event (EventID),
    KEY idx_posts_organiser (OrganiserID),
    KEY idx_posts_date (PostDate),

    CONSTRAINT fk_posts_event
        FOREIGN KEY (EventID)
        REFERENCES EVENTS(EventID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_posts_organiser
        FOREIGN KEY (OrganiserID)
        REFERENCES USERS(UserID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_posts_media_type
        CHECK (
            Media IS NULL
            OR Media_type IS NOT NULL
        )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- REACTIONS
-- ============================================================

CREATE TABLE REACTIONS (
    ReactionID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    PostID INT UNSIGNED NOT NULL,
    UserID INT UNSIGNED NOT NULL,

    ReactionType ENUM(
        'thumbs_up',
        'clap',
        'lightbulb'
    ) NOT NULL,

    ReactionDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (ReactionID),

    UNIQUE KEY uq_reactions_user_post (UserID, PostID),

    KEY idx_reactions_post (PostID),
    KEY idx_reactions_user (UserID),
    KEY idx_reactions_type (ReactionType),

    CONSTRAINT fk_reactions_post
        FOREIGN KEY (PostID)
        REFERENCES POSTS(PostID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_reactions_user
        FOREIGN KEY (UserID)
        REFERENCES USERS(UserID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- POST CATEGORIES
-- ============================================================

CREATE TABLE Post_Categories (
    post_category_id INT UNSIGNED NOT NULL AUTO_INCREMENT,

    post_id INT UNSIGNED NOT NULL,
    category_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (post_category_id),

    UNIQUE KEY uq_post_category (post_id, category_id),

    KEY idx_post_categories_post (post_id),
    KEY idx_post_categories_category (category_id),

    CONSTRAINT fk_post_categories_post
        FOREIGN KEY (post_id)
        REFERENCES POSTS(PostID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_post_categories_category
        FOREIGN KEY (category_id)
        REFERENCES CATEGORIES(CategoryID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- REVIEWS
-- ============================================================

CREATE TABLE REVIEWS (
    ReviewID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    BookingID INT UNSIGNED NOT NULL,
    UserID INT UNSIGNED NOT NULL,
    EventID INT UNSIGNED NOT NULL,

    Rating INT NOT NULL,

    Comment TEXT NULL,
    Tags VARCHAR(255) NULL,

    WantsContact BOOLEAN NOT NULL DEFAULT FALSE,
    ContactEmail VARCHAR(100) NULL,

    ReviewDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (ReviewID),

    UNIQUE KEY uq_reviews_booking (BookingID),

    KEY idx_reviews_booking (BookingID),
    KEY idx_reviews_user (UserID),
    KEY idx_reviews_event (EventID),
    KEY idx_reviews_date (ReviewDate),

    CONSTRAINT fk_reviews_booking
        FOREIGN KEY (BookingID)
        REFERENCES BOOKINGS(BookingID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_reviews_user
        FOREIGN KEY (UserID)
        REFERENCES USERS(UserID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT fk_reviews_event
        FOREIGN KEY (EventID)
        REFERENCES EVENTS(EventID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE,

    CONSTRAINT chk_reviews_rating
        CHECK (Rating BETWEEN 1 AND 5),

    CONSTRAINT chk_reviews_contact
        CHECK (
            WantsContact = FALSE
            OR ContactEmail IS NOT NULL
        )

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- BLOCKS
-- ============================================================

CREATE TABLE BLOCKS (
    BlockID INT UNSIGNED NOT NULL AUTO_INCREMENT,

    OrganiserID INT UNSIGNED NOT NULL,
    BlockedEmail VARCHAR(100) NOT NULL,
    Reason VARCHAR(255) NULL,
    BlockDate DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (BlockID),

    UNIQUE KEY uq_blocks_organiser_email
        (OrganiserID, BlockedEmail),

    KEY idx_blocks_organiser (OrganiserID),
    KEY idx_blocks_email (BlockedEmail),
    KEY idx_blocks_date (BlockDate),

    CONSTRAINT fk_blocks_organiser
        FOREIGN KEY (OrganiserID)
        REFERENCES USERS(UserID)
        ON DELETE RESTRICT
        ON UPDATE CASCADE

) ENGINE=InnoDB
DEFAULT CHARSET=utf8mb4
COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;
