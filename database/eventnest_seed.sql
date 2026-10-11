<<<<<<< HEAD

-- ============================================================
-- EventNest Seed Data
-- Compatible with the final eventnest_schema.sql
-- ============================================================

USE eventnest;

SET FOREIGN_KEY_CHECKS = 0;

-- ------------------------------------------------------------
-- CATEGORIES
-- ------------------------------------------------------------

=======
USE eventnest;

-- ============================================================
-- 1. CATEGORIES
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO CATEGORIES
    (CategoryID, CategoryName, Description)
VALUES
    (1, 'Technology', 'Technology, software and digital career events.'),
<<<<<<< HEAD
    (2, 'Finance', 'Finance, accounting and financial-services career events.'),
=======
    (2, 'Dermatology', 'Dermatology, skin health, skincare treatments and dermatology career events.'),
>>>>>>> origin/main
    (3, 'Design', 'Creative, UX, graphic and design career events.'),
    (4, 'Security', 'Cybersecurity and information-security career events.'),
    (5, 'Networking', 'Professional networking and career-connection events.'),
    (6, 'Leadership', 'Leadership and professional-development events.'),
    (7, 'Cloud Computing', 'Cloud infrastructure, cloud engineering and related careers.'),
    (8, 'Career Development', 'Graduate, employability and career-development events.');

<<<<<<< HEAD
-- ------------------------------------------------------------
-- USERS
-- PasswordHash values are bcrypt test hashes only.
-- ------------------------------------------------------------

=======
-- ============================================================
-- 2. USERS
-- PasswordHash values are bcrypt test hashes only.
-- No plaintext password is stored in this file.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO USERS
(
    UserID,
    FirstName,
    LastName,
    Email,
    PasswordHash,
    Phone,
    CareerField,
    UserStatus,
    Role,
    VerificationStatus,
    Verification_docs_path,
    RecommendationOptIn,
    Blocks_count,
    Suspension_count,
    Suspension_start_date,
    Is_email_verified,
    CreatedAt,
    LastLoginAt,
    VerificationFeePaidAt,
    VerificationFeePaidUntil,
    VerificationFeePaymentStatus,
    VerificationFeeGatewayReference
)
VALUES
<<<<<<< HEAD
=======
-- Verified organiser with valid 12-month R99 verification-fee coverage.
>>>>>>> origin/main
(
    1,
    'Kea',
    'Mokoena',
    'kea.organiser01@example.com',
<<<<<<< HEAD
    '$2y$12$P4qH.DytBEXgVyhAmf1vUONSdP0GUKRxJM42JISpLhKg9AL9h.M4S',
=======
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
>>>>>>> origin/main
    '0100000001',
    'Technology',
    'active',
    'organiser',
    'verified',
<<<<<<< HEAD
    'uploads/verification/kea-organiser01.pdf',
    FALSE,
    1,
=======
    NULL,
    FALSE,
    0,
>>>>>>> origin/main
    0,
    NULL,
    TRUE,
    '2026-09-01 09:00:00',
    '2026-10-03 10:00:00',
    '2026-09-02 10:00:00',
<<<<<<< HEAD
    '2026-12-02 23:59:59',
    'paid',
    'EN-VERIFY-KEAO01'
),
=======
    '2027-09-02 10:00:00',
    'paid',
    'EN-VERIFY-KEAO01'
),
-- Pending organiser: registered but not yet allowed to create/publish events.
>>>>>>> origin/main
(
    2,
    'Mpho',
    'Dlamini',
    'mpho.organiser02@example.com',
<<<<<<< HEAD
    '$2y$12$P4qH.DytBEXgVyhAmf1vUONSdP0GUKRxJM42JISpLhKg9AL9h.M4S',
=======
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
>>>>>>> origin/main
    '0100000002',
    'Design',
    'active',
    'organiser',
    'pending',
<<<<<<< HEAD
    'uploads/verification/mpho-organiser02.pdf',
=======
    NULL,
>>>>>>> origin/main
    FALSE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-05 09:00:00',
    '2026-10-02 14:00:00',
    NULL,
    NULL,
    'pending',
    'EN-VERIFY-MPHO02'
),
<<<<<<< HEAD
(
    3,
    'Tino',
    'Maseko',
    'tino.attendee01@example.com',
    '$2y$12$P4qH.DytBEXgVyhAmf1vUONSdP0GUKRxJM42JISpLhKg9AL9h.M4S',
=======
-- Active opted-in Technology attendee.
(
    3,
    'Lethabo',
    'Maseko',
    'lethabo.attendee01@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
>>>>>>> origin/main
    '0100000003',
    'Technology',
    'active',
    'attendee',
    'verified',
    NULL,
    TRUE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-10 08:00:00',
    '2026-10-03 12:00:00',
    NULL,
    NULL,
    'unpaid',
    NULL
),
<<<<<<< HEAD
(
    4,
    'Neo',
    'Pillay',
    'neo.attendee03@example.com',
    '$2y$12$P4qH.DytBEXgVyhAmf1vUONSdP0GUKRxJM42JISpLhKg9AL9h.M4S',
    '0100000004',
    'Design',
    'suspended',
=======
-- Design attendee with one active organiser block. One block is not a suspension.
(
    4,
    'Priya',
    'Pillay',
    'priya.attendee03@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000004',
    'Design',
    'active',
>>>>>>> origin/main
    'attendee',
    'verified',
    NULL,
    FALSE,
<<<<<<< HEAD
    0,
    1,
    '2026-10-01 09:00:00',
    TRUE,
    '2026-09-12 08:00:00',
    '2026-09-30 11:00:00',
=======
    1,
    0,
    NULL,
    TRUE,
    '2026-09-12 08:00:00',
    '2026-10-03 11:00:00',
>>>>>>> origin/main
    NULL,
    NULL,
    'unpaid',
    NULL
),
<<<<<<< HEAD
(
    5,
    'Aisha',
    'Naidoo',
    'aisha.attendee02@example.com',
    '$2y$12$P4qH.DytBEXgVyhAmf1vUONSdP0GUKRxJM42JISpLhKg9AL9h.M4S',
    '0100000005',
    'Cloud Computing',
=======
-- Active opted-in Dermatology attendee.
(
    5,
    'Ayanda',
    'Zuma',
    'ayanda.attendee02@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000005',
    'Dermatology',
>>>>>>> origin/main
    'active',
    'attendee',
    'verified',
    NULL,
    TRUE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-15 08:00:00',
    '2026-10-03 15:00:00',
    NULL,
    NULL,
    'unpaid',
    NULL
),
<<<<<<< HEAD
(
    6,
    'Sam',
    'Jacobs',
    'sam.admin01@example.com',
    '$2y$12$P4qH.DytBEXgVyhAmf1vUONSdP0GUKRxJM42JISpLhKg9AL9h.M4S',
=======
-- Administrator test account.
(
    6,
    'Zion',
    'Jacobs',
    'zion.admin01@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
>>>>>>> origin/main
    '0100000006',
    NULL,
    'active',
    'admin',
    'verified',
    NULL,
    FALSE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-01 08:00:00',
    '2026-10-03 16:00:00',
    NULL,
    NULL,
    'unpaid',
    NULL
<<<<<<< HEAD
);

-- ------------------------------------------------------------
-- EVENTS
-- ------------------------------------------------------------

=======
),
-- Active Cloud Computing attendee used for field-gated/free-booking tests.
(
    7,
    'Nandi',
    'Khumalo',
    'nandi.cloud@students.example.edu',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000007',
    'Cloud Computing',
    'active',
    'attendee',
    'verified',
    NULL,
    TRUE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-16 08:30:00',
    '2026-10-03 15:30:00',
    NULL,
    NULL,
    'unpaid',
    NULL
),
-- Second active Design attendee for completed-event/review tests.
(
    8,
    'Maya',
    'Daniels',
    'maya.design@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000008',
    'Design',
    'active',
    'attendee',
    'verified',
    NULL,
    FALSE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-13 08:30:00',
    '2026-10-03 13:00:00',
    NULL,
    NULL,
    'unpaid',
    NULL
),
-- Deleted account state fixture; no new active transactions depend on it.
(
    9,
    'Zola',
    'Naidoo',
    'zola.deleted@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000009',
    'Career Development',
    'deleted',
    'attendee',
    'unverified',
    NULL,
    FALSE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-11 08:30:00',
    '2026-09-18 13:00:00',
    NULL,
    NULL,
    'unpaid',
    NULL
);

-- ============================================================
-- 3. EVENTS
-- Public/draft examples all belong to the verified organiser.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO EVENTS
(
    EventID,
    OrganiserID,
    CategoryID,
    EventName,
    Description,
    Venue,
    EventDate,
    AgeRestriction,
    Status,
    Format,
    Hashtags,
    CareerGoalType,
    AllowedCareerField,
    AllowedEmailDomain,
    IsFeatured,
    Province
)
VALUES
<<<<<<< HEAD
=======
-- Published + featured + physical + no career gate.
>>>>>>> origin/main
(
    1,
    1,
    8,
    'Graduate Career Summit',
    'A career-development event connecting graduates with employers and professional speakers.',
    'Sandton Convention Centre',
<<<<<<< HEAD
    '2026-10-10 10:00:00',
=======
    '2026-10-20 10:00:00',
>>>>>>> origin/main
    '18+',
    'published',
    'physical',
    '#GraduateJobs #CareerDevelopment #Networking',
    'none',
    NULL,
    NULL,
    TRUE,
    'Gauteng'
),
<<<<<<< HEAD
=======
-- Published + non-featured + online + field gate.
>>>>>>> origin/main
(
    2,
    1,
    7,
    'Cloud Skills Workshop',
    'A practical professional-development workshop covering cloud careers and entry-level skills.',
<<<<<<< HEAD
    NULL,
=======
    'Online',
>>>>>>> origin/main
    '2026-10-18 10:00:00',
    '18+',
    'published',
    'online',
    '#CloudCareers #CloudSkills #Technology',
    'field',
    7,
    NULL,
    FALSE,
    'Gauteng'
),
<<<<<<< HEAD
=======
-- Draft + physical + field gate.
>>>>>>> origin/main
(
    3,
    1,
    3,
    'Creative Design Lab',
    'A design-focused career workshop for attendees interested in creative and digital design.',
    'Braamfontein Creative Hub',
    '2026-10-24 11:00:00',
    '18+',
    'draft',
    'physical',
    '#DesignCareers #CreativeDesign',
    'field',
    3,
    NULL,
    FALSE,
    'Gauteng'
),
<<<<<<< HEAD
=======
-- Completed event used for completed-booking/review/released-escrow tests.
>>>>>>> origin/main
(
    4,
    1,
    3,
    'Heritage Creative Evening',
    'A completed professional networking event focused on creative careers and portfolio development.',
    'Cape Town Creative Centre',
    '2026-09-20 18:00:00',
    '18+',
    'completed',
    'physical',
    '#CreativeCareers #Networking #Design',
    'field',
    3,
    NULL,
    FALSE,
    'Western Cape'
),
<<<<<<< HEAD
=======
-- Canceled + hybrid + email-domain gate.
>>>>>>> origin/main
(
    5,
    1,
    1,
    'FutureTech Connect 2026',
<<<<<<< HEAD
    'A technology event that was cancelled after the organiser changed the delivery schedule.',
    'Durban Innovation Hub',
=======
    'A technology event that was canceled after the organiser changed the delivery schedule.',
    'Durban Innovation Hub + livestream',
>>>>>>> origin/main
    '2026-10-28 09:00:00',
    '18+',
    'canceled',
    'hybrid',
    '#FutureTech #Technology',
    'email_domain',
    NULL,
    'students.example.edu',
    FALSE,
    'KwaZulu-Natal'
);

<<<<<<< HEAD
-- ------------------------------------------------------------
-- USER EVENT RECOMMENDATIONS
-- AI recommendations are seeded only for opted-in attendees.
-- ExpiresAt is 30 minutes after GeneratedAt.
-- ------------------------------------------------------------

=======
-- ============================================================
-- 4. USER EVENT RECOMMENDATIONS
-- Only active, opted-in attendees are cached.
-- Only published, upcoming, eligible events are referenced.
-- Cache lifetime is exactly 30 minutes.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO USER_EVENT_RECOMMENDATIONS
(
    RecommendationID,
    UserID,
    EventID,
    SimilarityScore,
    ModelName,
    GeneratedAt,
    ExpiresAt
)
VALUES
<<<<<<< HEAD
(
    1,
    3,
    2,
    0.9200,
    'EventNest-Recommendation-Test',
    '2026-10-04 10:00:00',
    '2026-10-04 10:30:00'
),
(
    2,
    5,
    2,
    0.9500,
    'EventNest-Recommendation-Test',
    '2026-10-04 10:05:00',
    '2026-10-04 10:35:00'
),
(
    3,
    3,
    1,
    0.8800,
    'EventNest-Recommendation-Test',
    '2026-10-04 10:00:00',
    '2026-10-04 10:30:00'
);

-- ------------------------------------------------------------
-- TICKETS
-- QuantityAvailable is the remaining quantity after the
-- seeded booking states below have been applied.
-- ------------------------------------------------------------

=======
-- Lethabo can view Event 1 because it has no career/email gate.
(
    1,
    3,
    1,
    0.8800,
    'text-embedding-3-small',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 30 MINUTE)
),
-- Ayanda can also view Event 1 because it has no career/email gate.
(
    2,
    5,
    1,
    0.8000,
    'text-embedding-3-small',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 30 MINUTE)
),
-- Nandi matches the Cloud Computing field gate on Event 2.
(
    3,
    7,
    2,
    0.9500,
    'text-embedding-3-small',
    NOW(),
    DATE_ADD(NOW(), INTERVAL 30 MINUTE)
);

-- ============================================================
-- 5. TICKETS
-- QuantityAvailable reflects the seeded booking outcomes below:
-- pending and paid bookings reserve/consume capacity;
-- failed/refunded/canceled bookings are treated as restored.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO TICKETS
(
    TicketID,
    EventID,
    TicketType,
    Price,
    QuantityAvailable,
    Capacity,
    Description
)
VALUES
(
    1,
    1,
    'General',
    150.00,
    98,
    100,
    'Standard access to the Graduate Career Summit.'
),
(
    2,
    1,
    'VIP',
    250.00,
    100,
    100,
    'VIP access and premium networking area.'
),
(
    3,
    2,
    'General',
    0.00,
    49,
    50,
    'Free online general-access ticket.'
),
(
    4,
    2,
    'Student',
    0.00,
    50,
    50,
    'Free student ticket for eligible attendees.'
),
(
    5,
    3,
    'General',
    100.00,
<<<<<<< HEAD
    29,
=======
    30,
>>>>>>> origin/main
    30,
    'General admission to the Creative Design Lab.'
),
(
    6,
    3,
    'Student',
    50.00,
    50,
    50,
    'Discounted student ticket.'
),
(
    7,
    4,
    'General',
    80.00,
    38,
    40,
    'General admission to the completed creative event.'
),
(
    8,
    4,
    'VIP',
    150.00,
    20,
    20,
    'VIP admission to the completed creative event.'
),
(
    9,
    5,
    'Early Bird',
    100.00,
    30,
    30,
<<<<<<< HEAD
    'Early Bird ticket for the cancelled event.'
);

-- ------------------------------------------------------------
-- BOOKINGS
-- ------------------------------------------------------------

=======
    'Early Bird ticket for the canceled event.'
);

-- ============================================================
-- 6. BOOKINGS
-- Stored schema statuses are pending, paid, failed, refunded, canceled.
-- Free bookings bypass PayFast but still use the approved stored booking status.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO BOOKINGS
(
    BookingID,
    EventID,
    UserID,
    TicketID,
    BookingDate,
    Quantity,
    TotalAmount,
    Status,
    BookingReference
)
VALUES
<<<<<<< HEAD
=======
-- Current pending paid booking; age is below the four-minute timeout.
>>>>>>> origin/main
(
    1,
    1,
    3,
    1,
<<<<<<< HEAD
    NOW(),
=======
    DATE_SUB(NOW(), INTERVAL 1 MINUTE),
>>>>>>> origin/main
    1,
    150.00,
    'pending',
    'EN-BN-0001'
),
<<<<<<< HEAD
=======
-- Successful paid future booking.
>>>>>>> origin/main
(
    2,
    1,
    5,
    1,
    '2026-10-03 09:00:00',
    1,
    150.00,
    'paid',
    'EN-BN-0002'
),
<<<<<<< HEAD
=======
-- Failed payment test. Priya was active at this booking date.
>>>>>>> origin/main
(
    3,
    1,
    4,
    2,
    '2026-09-29 09:00:00',
    1,
    250.00,
    'failed',
    'EN-BN-0003'
),
<<<<<<< HEAD
(
    4,
    4,
    3,
=======
-- Refunded completed-event booking; attendee satisfies the Design field gate.
(
    4,
    4,
    8,
>>>>>>> origin/main
    7,
    '2026-09-15 12:00:00',
    1,
    80.00,
    'refunded',
    'EN-CS-0002'
),
<<<<<<< HEAD
(
    5,
    5,
    5,
=======
-- Canceled booking for an attendee whose email satisfies the event email-domain gate.
(
    5,
    5,
    7,
>>>>>>> origin/main
    9,
    '2026-10-01 12:00:00',
    1,
    100.00,
    'canceled',
    'EN-BN-0005'
),
<<<<<<< HEAD
(
    6,
    4,
    5,
=======
-- Paid completed-event booking eligible for a private review.
(
    6,
    4,
    4,
>>>>>>> origin/main
    7,
    '2026-09-14 10:00:00',
    1,
    80.00,
    'paid',
    'EN-CS-0003'
),
<<<<<<< HEAD
(
    7,
    4,
    3,
=======
-- Second paid completed-event booking eligible for a private review.
(
    7,
    4,
    8,
>>>>>>> origin/main
    7,
    '2026-09-14 11:00:00',
    1,
    80.00,
    'paid',
    'EN-CS-0004'
<<<<<<< HEAD
);

-- ------------------------------------------------------------
-- PAYMENTS
-- Successful paid booking for a future event: escrow held.
-- Successful paid booking for a completed event: escrow released.
-- Refunded booking: escrow refunded.
-- Failed/cancelled scenarios are also represented.
-- ------------------------------------------------------------

=======
),
-- Free booking: valid and confirmed in application terms without a PAYMENTS row.
(
    8,
    2,
    7,
    3,
    '2026-10-04 09:00:00',
    1,
    0.00,
    'paid',
    'EN-FREE-0001'
);

-- ============================================================
-- 7. PAYMENTS
-- Paid future event -> held escrow.
-- Completed paid events -> released escrow.
-- Refunded booking -> refunded payment/escrow.
-- Free booking intentionally has no payment record.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO PAYMENTS
(
    PaymentID,
    BookingID,
    PaymentDate,
    Amount,
    PaymentMethod,
    PaymentStatus,
    TransactionID,
    Escrow_status
)
VALUES
(
    1,
    2,
    '2026-10-03 09:02:00',
    150.00,
    'PayFast Sandbox',
    'success',
    'EN-TXN-0002',
    'held'
),
(
    2,
    3,
    '2026-09-29 09:02:00',
    250.00,
    'PayFast Sandbox',
    'failed',
    'EN-TXN-0003',
    NULL
),
(
    3,
    4,
    '2026-09-15 12:02:00',
    80.00,
    'PayFast Sandbox',
    'refunded',
    'EN-TXN-0004',
    'refunded'
),
(
    4,
    6,
    '2026-09-14 10:02:00',
    80.00,
    'PayFast Sandbox',
    'success',
    'EN-TXN-0006',
    'released'
),
(
    5,
    7,
    '2026-09-14 11:02:00',
    80.00,
    'PayFast Sandbox',
    'success',
    'EN-TXN-0007',
    'released'
);

<<<<<<< HEAD
-- ------------------------------------------------------------
-- POSTS
-- Media paths are harmless project-relative test references.
-- Ensure the referenced demo files exist before testing display.
-- ------------------------------------------------------------

=======
-- ============================================================
-- 8. POSTS
-- The image/video files below are demo assets and must
-- exist under uploaded-content/ when media-display testing is performed.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO POSTS
(
    PostID,
    EventID,
    OrganiserID,
    Content,
    Media_type,
    Media,
    Hashtags,
    PostDate
)
VALUES
<<<<<<< HEAD
=======
-- Text-only Event update.
>>>>>>> origin/main
(
    1,
    1,
    1,
    'Applications and registration information for the Graduate Career Summit are now available.',
    NULL,
    NULL,
    '#GraduateJobs #CareerDevelopment',
    '2026-10-01 09:00:00'
),
<<<<<<< HEAD
=======
-- Image post.
>>>>>>> origin/main
(
    2,
    2,
    1,
    'Cloud Skills Workshop preview image for attendees interested in cloud careers.',
    'image',
<<<<<<< HEAD
    'uploads/posts/eventnest-cloud-skills-workshop.jpg',
    '#CloudCareers #CloudSkills',
    '2026-10-02 10:00:00'
),
=======
    'uploaded-content/eventnest-cloud-skills-workshop.jpg',
    '#CloudCareers #CloudSkills',
    '2026-10-02 10:00:00'
),
-- Video post.
>>>>>>> origin/main
(
    3,
    3,
    1,
    'Creative Design Lab short promotional video.',
    'video',
<<<<<<< HEAD
    'uploads/posts/eventnest-creative-design-lab.mp4',
=======
    'uploaded-content/eventnest-creative-design-lab.mp4',
>>>>>>> origin/main
    '#DesignCareers #CreativeDesign',
    '2026-10-03 11:00:00'
);

<<<<<<< HEAD
-- ------------------------------------------------------------
-- REACTIONS
-- ------------------------------------------------------------

=======
-- ============================================================
-- 9. REACTIONS
-- One current reaction per UserID/PostID pair.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO REACTIONS
(
    ReactionID,
    PostID,
    UserID,
    ReactionType,
    ReactionDate
)
VALUES
(
    1,
    1,
    3,
    'thumbs_up',
    '2026-10-02 09:30:00'
),
(
    2,
    1,
    5,
    'clap',
    '2026-10-02 09:35:00'
),
(
    3,
    2,
<<<<<<< HEAD
    3,
=======
    7,
>>>>>>> origin/main
    'lightbulb',
    '2026-10-03 10:30:00'
),
(
    4,
    3,
<<<<<<< HEAD
    5,
=======
    8,
>>>>>>> origin/main
    'clap',
    '2026-10-03 12:00:00'
);

<<<<<<< HEAD
-- ------------------------------------------------------------
-- POST CATEGORIES
-- ------------------------------------------------------------

=======
-- ============================================================
-- 10. POST CATEGORIES
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO Post_Categories
(
    post_category_id,
    post_id,
    category_id
)
VALUES
(
    1,
    1,
    8
),
(
    2,
    2,
    7
),
(
    3,
    3,
    3
);

<<<<<<< HEAD
-- ------------------------------------------------------------
-- REVIEWS
-- Reviews are attached only to completed-event paid bookings.
-- ------------------------------------------------------------

=======
-- ============================================================
-- 11. REVIEWS
-- Rating 4-5 uses Tags; rating below 4 uses Comment.
-- Reviews are private and linked to paid bookings for a completed event.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO REVIEWS
(
    ReviewID,
    BookingID,
    UserID,
    EventID,
    Rating,
    Comment,
    Tags,
    WantsContact,
    ContactEmail,
    ReviewDate
)
VALUES
<<<<<<< HEAD
(
    1,
    6,
    5,
    4,
    5,
    'Excellent event with useful portfolio and career-development guidance.',
=======
-- Positive review: compliment tags only.
(
    1,
    6,
    4,
    4,
    5,
    NULL,
>>>>>>> origin/main
    '#Helpful #Professional',
    FALSE,
    NULL,
    '2026-09-21 10:00:00'
),
<<<<<<< HEAD
(
    2,
    7,
    3,
    4,
    3,
    'Useful networking opportunities, although the schedule could be clearer.',
    '#Networking #NeedsImprovement',
    TRUE,
    'tino.attendee01@example.com',
    '2026-09-21 11:00:00'
);

-- ------------------------------------------------------------
-- BLOCKS
-- ------------------------------------------------------------

=======
-- Below-4 review: complaint text only, with requested follow-up.
(
    2,
    7,
    8,
    4,
    3,
    'Useful networking opportunities, although the schedule could be clearer.',
    NULL,
    TRUE,
    'maya.design@example.com',
    '2026-09-21 11:00:00'
);

-- ============================================================
-- 12. BLOCKS
-- One organiser/email pair only. This shows blocking without
-- pretending that a single organiser can trigger a suspension.
-- Priya has Blocks_count = 1 and remains active.
-- ============================================================
>>>>>>> origin/main
INSERT IGNORE INTO BLOCKS
(
    BlockID,
    OrganiserID,
    BlockedEmail,
    Reason,
    BlockDate
)
VALUES
(
    1,
    1,
<<<<<<< HEAD
    'neo.attendee03@example.com',
    'Repeatedly inappropriate event interactions in a test moderation scenario.',
    '2026-10-01 14:00:00'
);

SET FOREIGN_KEY_CHECKS = 1;
=======
    'priya.attendee03@example.com',
    'Repeatedly inappropriate event interactions in a fictional moderation test scenario.',
    '2026-10-01 14:00:00'
);
>>>>>>> origin/main
