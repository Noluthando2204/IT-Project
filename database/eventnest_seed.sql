USE eventnest;

INSERT IGNORE INTO CATEGORIES
    (CategoryID, CategoryName, Description)
VALUES
    (1, 'Technology', 'Technology, software and digital career events.'),
    (2, 'Dermatology', 'Dermatology, skin health, skincare treatments and dermatology career events.'),
    (3, 'Design', 'Creative, UX, graphic and design career events.'),
    (4, 'Security', 'Cybersecurity and information-security career events.'),
    (5, 'Networking', 'Professional networking and career-connection events.'),
    (6, 'Leadership', 'Leadership and professional-development events.'),
    (7, 'Cloud Computing', 'Cloud infrastructure, cloud engineering and related careers.'),
    (8, 'Career Development', 'Graduate, employability and career-development events.');

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
(
    1,
    'Kea',
    'Mokoena',
    'kea.organiser01@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000001',
    'Technology',
    'active',
    'organiser',
    'verified',
    NULL,
    FALSE,
    0,
    0,
    NULL,
    TRUE,
    '2026-09-01 09:00:00',
    '2026-10-03 10:00:00',
    '2026-09-02 10:00:00',
    '2027-09-02 10:00:00',
    'paid',
    'EN-VERIFY-KEAO01'
),
(
    2,
    'Mpho',
    'Dlamini',
    'mpho.organiser02@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000002',
    'Design',
    'active',
    'organiser',
    'pending',
    NULL,
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
(
    3,
    'Lethabo',
    'Maseko',
    'lethabo.attendee01@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
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
(
    4,
    'Priya',
    'Pillay',
    'priya.attendee03@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000004',
    'Design',
    'active',
    'attendee',
    'verified',
    NULL,
    FALSE,
    1,
    0,
    NULL,
    TRUE,
    '2026-09-12 08:00:00',
    '2026-10-03 11:00:00',
    NULL,
    NULL,
    'unpaid',
    NULL
),
(
    5,
    'Ayanda',
    'Zuma',
    'ayanda.attendee02@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
    '0100000005',
    'Dermatology',
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
(
    6,
    'Zion',
    'Jacobs',
    'zion.admin01@example.com',
    '$2y$12$10fgnP414A4Oyvyd.VKvgO3n4EYaK5Zhx95E/FMl.2I89tX.ye.Qe',
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
),
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
(
    1,
    1,
    8,
    'Graduate Career Summit',
    'A career-development event connecting graduates with employers and professional speakers.',
    'Sandton Convention Centre',
    '2026-10-20 10:00:00',
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
(
    2,
    1,
    7,
    'Cloud Skills Workshop',
    'A practical professional-development workshop covering cloud careers and entry-level skills.',
    'Online',
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
(
    5,
    1,
    1,
    'FutureTech Connect 2026',
    'A technology event that was canceled after the organiser changed the delivery schedule.',
    'Durban Innovation Hub + livestream',
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
    (1, 3, 1, 0.8800, 'text-embedding-3-small', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE)),
    (2, 5, 1, 0.8000, 'text-embedding-3-small', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE)),
    (3, 7, 2, 0.9500, 'text-embedding-3-small', NOW(), DATE_ADD(NOW(), INTERVAL 30 MINUTE));

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
    (1, 1, 'General', 150.00, 98, 100, 'Standard access to the Graduate Career Summit.'),
    (2, 1, 'VIP', 250.00, 100, 100, 'VIP access and premium networking area.'),
    (3, 2, 'General', 0.00, 49, 50, 'Free online general-access ticket.'),
    (4, 2, 'Student', 0.00, 50, 50, 'Free student ticket for eligible attendees.'),
    (5, 3, 'General', 100.00, 30, 30, 'General admission to the Creative Design Lab.'),
    (6, 3, 'Student', 50.00, 50, 50, 'Discounted student ticket.'),
    (7, 4, 'General', 80.00, 38, 40, 'General admission to the completed creative event.'),
    (8, 4, 'VIP', 150.00, 20, 20, 'VIP admission to the completed creative event.'),
    (9, 5, 'Early Bird', 100.00, 30, 30, 'Early Bird ticket for the canceled event.');

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
(
    1,
    1,
    3,
    1,
    DATE_SUB(NOW(), INTERVAL 1 MINUTE),
    1,
    150.00,
    'pending',
    'EN-BN-0001'
),
(2, 1, 5, 1, '2026-10-03 09:00:00', 1, 150.00, 'paid', 'EN-BN-0002'),
(3, 1, 4, 2, '2026-09-29 09:00:00', 1, 250.00, 'failed', 'EN-BN-0003'),
(4, 4, 8, 7, '2026-09-15 12:00:00', 1, 80.00, 'refunded', 'EN-CS-0002'),
(5, 5, 7, 9, '2026-10-01 12:00:00', 1, 100.00, 'canceled', 'EN-BN-0005'),
(6, 4, 4, 7, '2026-09-14 10:00:00', 1, 80.00, 'paid', 'EN-CS-0003'),
(7, 4, 8, 7, '2026-09-14 11:00:00', 1, 80.00, 'paid', 'EN-CS-0004'),
(8, 2, 7, 3, '2026-10-04 09:00:00', 1, 0.00, 'paid', 'EN-FREE-0001');

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
    (1, 2, '2026-10-03 09:02:00', 150.00, 'PayFast Sandbox', 'success', 'EN-TXN-0002', 'held'),
    (2, 3, '2026-09-29 09:02:00', 250.00, 'PayFast Sandbox', 'failed', 'EN-TXN-0003', NULL),
    (3, 4, '2026-09-15 12:02:00', 80.00, 'PayFast Sandbox', 'refunded', 'EN-TXN-0004', 'refunded'),
    (4, 6, '2026-09-14 10:02:00', 80.00, 'PayFast Sandbox', 'success', 'EN-TXN-0006', 'released'),
    (5, 7, '2026-09-14 11:02:00', 80.00, 'PayFast Sandbox', 'success', 'EN-TXN-0007', 'released');

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
(
    2,
    2,
    1,
    'Cloud Skills Workshop preview image for attendees interested in cloud careers.',
    'image',
    'uploaded-content/eventnest-cloud-skills-workshop.jpg',
    '#CloudCareers #CloudSkills',
    '2026-10-02 10:00:00'
),
(
    3,
    1,
    1,
    'Graduate Career Summit venue walkthrough video.',
    'video',
    'uploaded-content/eventnest-graduate-career-summit.mp4',
    '#GraduateJobs #EventVenue',
    '2026-10-03 11:00:00'
);

INSERT IGNORE INTO REACTIONS
(
    ReactionID,
    PostID,
    UserID,
    ReactionType,
    ReactionDate
)
VALUES
    (1, 1, 3, 'thumbs_up', '2026-10-02 09:30:00'),
    (2, 1, 5, 'clap', '2026-10-02 09:35:00'),
    (3, 2, 7, 'lightbulb', '2026-10-03 10:30:00'),
    (4, 3, 8, 'clap', '2026-10-03 12:00:00');

INSERT IGNORE INTO Post_Categories
(
    post_category_id,
    post_id,
    category_id
)
VALUES
    (1, 1, 8),
    (2, 2, 7),
    (3, 3, 8);

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
(
    1,
    6,
    4,
    4,
    5,
    NULL,
    '#Helpful #Professional',
    FALSE,
    NULL,
    '2026-09-21 10:00:00'
),
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
    'priya.attendee03@example.com',
    'Repeatedly inappropriate event interactions in a fictional moderation test scenario.',
    '2026-10-01 14:00:00'
);

