
-- =========================================================
-- EventNest Database Schema
-- =========================================================

CREATE DATABASE IF NOT EXISTS eventnest
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE eventnest;

-- =========================================================
-- USERS
-- =========================================================

CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    industry VARCHAR(100) NOT NULL,
    user_status ENUM('school', 'uni', 'professional') NOT NULL,
    account_status ENUM('active', 'suspended', 'deleted') NOT NULL DEFAULT 'active',
    suspension_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_users_email (email),
    INDEX idx_users_status (user_status),
    INDEX idx_users_account_status (account_status)
) ENGINE=InnoDB;


-- =========================================================
-- HASHTAGS
-- =========================================================

CREATE TABLE IF NOT EXISTS hashtags (
    hashtag_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    hashtag_name VARCHAR(100) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- =========================================================
-- USER HASHTAGS
-- Connects users to their selected/recommended hashtags
-- =========================================================

CREATE TABLE IF NOT EXISTS user_hashtags (
    user_id INT UNSIGNED NOT NULL,
    hashtag_id INT UNSIGNED NOT NULL,

    PRIMARY KEY (user_id, hashtag_id),

    CONSTRAINT fk_user_hashtags_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_user_hashtags_hashtag
        FOREIGN KEY (hashtag_id)
        REFERENCES hashtags(hashtag_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- EVENTS
-- =========================================================

CREATE TABLE IF NOT EXISTS events (
    event_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    creator_id INT UNSIGNED NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    event_date DATETIME NOT NULL,
    location VARCHAR(255) NOT NULL,
    capacity INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_events_creator
        FOREIGN KEY (creator_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_events_creator (creator_id),
    INDEX idx_events_date (event_date)
) ENGINE=InnoDB;


-- =========================================================
-- EVENT ATTENDEES
-- =========================================================

CREATE TABLE IF NOT EXISTS event_attendees (
    event_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    registered_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (event_id, user_id),

    CONSTRAINT fk_event_attendees_event
        FOREIGN KEY (event_id)
        REFERENCES events(event_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_event_attendees_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE
) ENGINE=InnoDB;


-- =========================================================
-- POSTS
-- =========================================================

CREATE TABLE IF NOT EXISTS posts (
    post_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    content TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    CONSTRAINT fk_posts_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_posts_user (user_id),
    INDEX idx_posts_created (created_at)
) ENGINE=InnoDB;


-- =========================================================
-- REACTIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS reactions (
    reaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reaction_type ENUM('like', 'love', 'interested', 'support') NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uq_user_post_reaction (post_id, user_id),

    CONSTRAINT fk_reactions_post
        FOREIGN KEY (post_id)
        REFERENCES posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_reactions_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_reactions_user (user_id),
    INDEX idx_reactions_post (post_id)
) ENGINE=InnoDB;


-- =========================================================
-- DEFERRED REACTIONS
-- Used when a reaction cannot immediately be processed.
-- =========================================================

CREATE TABLE IF NOT EXISTS deferred_reactions (
    deferred_reaction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    reaction_type ENUM('like', 'love', 'interested', 'support') NOT NULL,
    status ENUM('pending', 'processed', 'cancelled') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,

    CONSTRAINT fk_deferred_reactions_post
        FOREIGN KEY (post_id)
        REFERENCES posts(post_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    CONSTRAINT fk_deferred_reactions_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_deferred_status (status),
    INDEX idx_deferred_user (user_id)
) ENGINE=InnoDB;


-- =========================================================
-- USER SUSPENSIONS
-- =========================================================

CREATE TABLE IF NOT EXISTS user_suspensions (
    suspension_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    reason VARCHAR(255) NOT NULL,
    suspended_until DATETIME NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

    CONSTRAINT fk_user_suspensions_user
        FOREIGN KEY (user_id)
        REFERENCES users(user_id)
        ON DELETE CASCADE
        ON UPDATE CASCADE,

    INDEX idx_suspension_user (user_id),
    INDEX idx_suspension_until (suspended_until)
) ENGINE=InnoDB;