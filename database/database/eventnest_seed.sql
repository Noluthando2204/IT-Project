
USE eventnest;

-- ============================================================
-- 1. CATEGORIES
-- ============================================================
INSERT INTO CATEGORIES (CategoryName, Description)
SELECT 'Technology', 'Technology, computing and digital innovation events.'
WHERE NOT EXISTS (SELECT 1 FROM CATEGORIES WHERE CategoryName = 'Technology');

INSERT INTO CATEGORIES (CategoryName, Description)
SELECT 'Business', 'Business, entrepreneurship and professional development events.'
WHERE NOT EXISTS (SELECT 1 FROM CATEGORIES WHERE CategoryName = 'Business');

INSERT INTO CATEGORIES (CategoryName, Description)
SELECT 'Design', 'Design, creativity and visual communication events.'
WHERE NOT EXISTS (SELECT 1 FROM CATEGORIES WHERE CategoryName = 'Design');

INSERT INTO CATEGORIES (CategoryName, Description)
SELECT 'Logistics & Supply Chain', 'Logistics, transport, procurement and supply-chain events.'
WHERE NOT EXISTS (SELECT 1 FROM CATEGORIES WHERE CategoryName = 'Logistics & Supply Chain');

INSERT INTO CATEGORIES (CategoryName, Description)
SELECT 'Career Development', 'Career guidance, employability and workplace-readiness events.'
WHERE NOT EXISTS (SELECT 1 FROM CATEGORIES WHERE CategoryName = 'Career Development');

INSERT INTO CATEGORIES (CategoryName, Description)
SELECT 'Arts & Culture', 'Arts, culture, heritage and creative community events.'
WHERE NOT EXISTS (SELECT 1 FROM CATEGORIES WHERE CategoryName = 'Arts & Culture');

-- ============================================================
-- 2. USERS
-- PasswordHash values below were generated with PHP password_hash().
-- No plaintext passwords are stored in this seed file.
-- ============================================================
INSERT INTO USERS
    (FirstName, LastName, Email, PasswordHash, Phone, CareerField,
     UserStatus, Role, VerificationStatus, RecommendationOptIn,
     VerificationFeePaidAt, VerificationFeePaidUntil, VerificationFeePaymentStatus, VerificationFeeGatewayReference)
SELECT
    'Amani', 'Mokoena', 'amani.attendee01@example.com',
    '$2y$12$i8iVVdKEi9q13I5mzRmmCe5DdGTSn/ONFbm.xiQjUSQApg.5EPk7a',
    '0100000001', 'Technology', 'active', 'attendee', 'verified', TRUE,
    NULL, NULL, 'unpaid', NULL
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE Email = 'amani.attendee01@example.com');

INSERT INTO USERS
    (FirstName, LastName, Email, PasswordHash, Phone, CareerField,
     UserStatus, Role, VerificationStatus, RecommendationOptIn,
     VerificationFeePaidAt, VerificationFeePaidUntil, VerificationFeePaymentStatus, VerificationFeeGatewayReference)
SELECT
    'Lerato', 'Dlamini', 'lerato.attendee02@example.com',
    '$2y$12$sr5UqgeUBT3JP/3Jan6e4.Nbrc3BWTxtNwwC0CY9RMwjKbr6Px3mS',
    '0100000002', 'Business', 'active', 'attendee', 'unverified', TRUE,
    NULL, NULL, 'unpaid', NULL
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE Email = 'lerato.attendee02@example.com');

INSERT INTO USERS
    (FirstName, LastName, Email, PasswordHash, Phone, CareerField,
     UserStatus, Role, VerificationStatus, RecommendationOptIn,
     VerificationFeePaidAt, VerificationFeePaidUntil, VerificationFeePaymentStatus, VerificationFeeGatewayReference)
SELECT
    'Neo', 'Pillay', 'neo.attendee03@example.com',
    '$2y$12$P4qH.DytBEXgVyhAmf1vUONSdP0GUKRxJM42JISpLhKg9AL9h.M4S',
    'Design', 'suspended', 'attendee', 'verified', FALSE,
    NULL, NULL, 'unpaid', NULL
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE Email = 'neo.attendee03@example.com');

INSERT INTO USERS
    (FirstName, LastName, Email, PasswordHash, Phone, CareerField,
     UserStatus, Role, VerificationStatus, RecommendationOptIn,
     VerificationFeePaidAt, VerificationFeePaidUntil, VerificationFeePaymentStatus, VerificationFeeGatewayReference)
SELECT
    'Zola', 'Naidoo', 'zola.attendee04@example.com',
    '$2y$12$DYdsz8Ok2Z7UUweUmZvhmOVb7NPlOzt2B66wRZiG3bef6.rXweKYy',
    '0100000004', 'Career Development', 'deleted', 'attendee', 'unverified', FALSE,
    NULL, NULL, 'unpaid', NULL
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE Email = 'zola.attendee04@example.com');

INSERT INTO USERS
    (FirstName, LastName, Email, PasswordHash, Phone, CareerField,
     UserStatus, Role, VerificationStatus, RecommendationOptIn,
     VerificationFeePaidAt, VerificationFeePaidUntil, VerificationFeePaymentStatus, VerificationFeeGatewayReference)
SELECT
    'Kea', 'Maseko', 'kea.organiser01@example.com',
    '$2y$12$xMlONO8p9fibL4AoUR2QWe1R2zAKmSPijk.EJTy26Y6LMc2.EAv2q',
    '0100000010', 'Technology', 'active', 'organiser', 'verified', TRUE,
    '2026-08-15 10:00:00', '2027-08-15 10:00:00', 'paid', 'PF-VERIFY-DEV-0001'
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE Email = 'kea.organiser01@example.com');

INSERT INTO USERS
    (FirstName, LastName, Email, PasswordHash, Phone, CareerField,
     UserStatus, Role, VerificationStatus, RecommendationOptIn,
     VerificationFeePaidAt, VerificationFeePaidUntil, VerificationFeePaymentStatus, VerificationFeeGatewayReference)
SELECT
    'Mpho', 'Radebe', 'mpho.organiser02@example.com',
    '$2y$12$XnqvlRGyfO1B.0Ofd341gOqHObsjUsUVCaqHSNP5QqitAI1mjuQo2',
    '0100000011', 'Business', 'active', 'organiser', 'pending', FALSE,
    NULL, NULL, 'pending', 'PF-VERIFY-DEV-0002'
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE Email = 'mpho.organiser02@example.com');

INSERT INTO USERS
    (FirstName, LastName, Email, PasswordHash, Phone, CareerField,
     UserStatus, Role, VerificationStatus, RecommendationOptIn,
     VerificationFeePaidAt, VerificationFeePaidUntil, VerificationFeePaymentStatus, VerificationFeeGatewayReference)
SELECT
    'Talia', 'Mthembu', 'talia.admin01@example.com',
    '$2y$12$j7ynq48.rWL5MMPYWLSC..r6A2yFco3O8FP0.nsM18NrimbWxG4vy',
    '0100000012', 'Career Development', 'active', 'admin', 'verified', FALSE,
    NULL, NULL, 'unpaid', NULL
WHERE NOT EXISTS (SELECT 1 FROM USERS WHERE Email = 'talia.admin01@example.com');

-- ============================================================
-- 3. EVENTS
-- ============================================================
INSERT INTO EVENTS
    (OrganiserID, CategoryID, EventName, Description, Venue, EventDate,
     AgeRestriction, Status, Format, Hashtags, CareerGateType,
     AllowedCareerField, AllowedEmailDomain, IsFeatured, Province)
SELECT
    u.UserID, c.CategoryID,
    'FutureTech Connect 2026',
    'A fictional technology networking event for students and young professionals.',
    'Innovation Hall', '2026-11-14 10:00:00', '16+', 'published', 'hybrid',
    '#technology #networking', 'none', NULL, NULL, TRUE, 'Gauteng'
FROM USERS u
JOIN CATEGORIES c ON c.CategoryName = 'Technology'
WHERE u.Email = 'kea.organiser01@example.com'
  AND NOT EXISTS (SELECT 1 FROM EVENTS WHERE EventName = 'FutureTech Connect 2026');

INSERT INTO EVENTS
    (OrganiserID, CategoryID, EventName, Description, Venue, EventDate,
     AgeRestriction, Status, Format, Hashtags, CareerGateType,
     AllowedCareerField, AllowedEmailDomain, IsFeatured, Province)
SELECT
    u.UserID, c.CategoryID,
    'Young Business Network Meetup',
    'A fictional business networking event with career and entrepreneurship discussions.',
    'Civic Conference Centre', '2026-11-21 14:00:00', '16+', 'published', 'physical',
    '#business #careers', 'field',
    (SELECT CategoryID FROM CATEGORIES WHERE CategoryName = 'Business'),
    NULL, FALSE, 'Gauteng'
FROM USERS u
JOIN CATEGORIES c ON c.CategoryName = 'Business'
WHERE u.Email = 'kea.organiser01@example.com'
  AND NOT EXISTS (SELECT 1 FROM EVENTS WHERE EventName = 'Young Business Network Meetup');

INSERT INTO EVENTS
    (OrganiserID, CategoryID, EventName, Description, Venue, EventDate,
     AgeRestriction, Status, Format, Hashtags, CareerGateType,
     AllowedCareerField, AllowedEmailDomain, IsFeatured, Province)
SELECT
    u.UserID, c.CategoryID,
    'Creative Design Lab',
    'A fictional design workshop for developing creative project ideas.',
    'Studio 4', '2026-12-05 09:00:00', '15+', 'draft', 'physical',
    '#design #creative', 'email_domain', NULL, 'design.example', FALSE, 'Gauteng'
FROM USERS u
JOIN CATEGORIES c ON c.CategoryName = 'Design'
WHERE u.Email = 'mpho.organiser02@example.com'
  AND NOT EXISTS (SELECT 1 FROM EVENTS WHERE EventName = 'Creative Design Lab');

INSERT INTO EVENTS
    (OrganiserID, CategoryID, EventName, Description, Venue, EventDate,
     AgeRestriction, Status, Format, Hashtags, CareerGateType,
     AllowedCareerField, AllowedEmailDomain, IsFeatured, Province)
SELECT
    u.UserID, c.CategoryID,
    'Career Skills Expo 2026',
    'A fictional career development expo with talks and employability activities.',
    'Career Centre', '2026-09-12 10:00:00', '15+', 'completed', 'hybrid',
    '#careers #skills', 'field',
    (SELECT CategoryID FROM CATEGORIES WHERE CategoryName = 'Career Development'),
    NULL, FALSE, 'Gauteng'
FROM USERS u
JOIN CATEGORIES c ON c.CategoryName = 'Career Development'
WHERE u.Email = 'kea.organiser01@example.com'
  AND NOT EXISTS (SELECT 1 FROM EVENTS WHERE EventName = 'Career Skills Expo 2026');

INSERT INTO EVENTS
    (OrganiserID, CategoryID, EventName, Description, Venue, EventDate,
     AgeRestriction, Status, Format, Hashtags, CareerGateType,
     AllowedCareerField, AllowedEmailDomain, IsFeatured, Province)
SELECT
    u.UserID, c.CategoryID,
    'Heritage Creative Evening',
    'A fictional arts and culture event demonstrating a cancelled-event scenario.',
    'Community Arts Hall', '2026-09-20 18:00:00', '16+', 'canceled', 'online',
    '#arts #culture', 'none', NULL, NULL, FALSE, 'Gauteng'
FROM USERS u
JOIN CATEGORIES c ON c.CategoryName = 'Arts & Culture'
WHERE u.Email = 'kea.organiser01@example.com'
  AND NOT EXISTS (SELECT 1 FROM EVENTS WHERE EventName = 'Heritage Creative Evening');

-- ============================================================
-- 4. TICKETS
-- ============================================================
INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'General', 120.00, 150, 200, 'General admission ticket.'
FROM EVENTS e
WHERE e.EventName = 'FutureTech Connect 2026'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'General'
  );

INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'VIP', 250.00, 20, 25, 'VIP admission ticket.'
FROM EVENTS e
WHERE e.EventName = 'FutureTech Connect 2026'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'VIP'
  );

INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'Student', 0.00, 10, 30, 'Free student admission ticket.'
FROM EVENTS e
WHERE e.EventName = 'FutureTech Connect 2026'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'Student'
  );

INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'Early Bird', 80.00, 5, 20, 'Limited early-bird admission ticket.'
FROM EVENTS e
WHERE e.EventName = 'Young Business Network Meetup'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'Early Bird'
  );

INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'General', 100.00, 100, 100, 'General admission ticket.'
FROM EVENTS e
WHERE e.EventName = 'Young Business Network Meetup'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'General'
  );

INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'General', 90.00, 50, 50, 'General workshop admission.'
FROM EVENTS e
WHERE e.EventName = 'Creative Design Lab'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'General'
  );

INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'General', 60.00, 0, 100, 'General admission for the completed expo.'
FROM EVENTS e
WHERE e.EventName = 'Career Skills Expo 2026'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'General'
  );

INSERT INTO TICKETS (EventID, TicketType, Price, QuantityAvailable, Capacity, Description)
SELECT e.EventID, 'General', 50.00, 30, 30, 'General admission for the cancelled event.'
FROM EVENTS e
WHERE e.EventName = 'Heritage Creative Evening'
  AND NOT EXISTS (
      SELECT 1 FROM TICKETS t
      WHERE t.EventID = e.EventID AND t.TicketType = 'General'
  );

-- ============================================================
-- 5. BOOKINGS
-- ============================================================
INSERT INTO BOOKINGS
    (EventID, UserID, TicketID, BookingDate, Quantity, TotalAmount, Status, BookingReference)
SELECT e.EventID, u.UserID, t.TicketID, '2026-10-01 09:15:00', 2,
       t.Price * 2, 'paid', 'EN-FT-0001'
FROM EVENTS e
JOIN TICKETS t ON t.EventID = e.EventID AND t.TicketType = 'General'
JOIN USERS u ON u.Email = 'amani.attendee01@example.com'
WHERE e.EventName = 'FutureTech Connect 2026'
  AND NOT EXISTS (SELECT 1 FROM BOOKINGS WHERE BookingReference = 'EN-FT-0001');

INSERT INTO BOOKINGS
    (EventID, UserID, TicketID, BookingDate, Quantity, TotalAmount, Status, BookingReference)
SELECT e.EventID, u.UserID, t.TicketID, '2026-10-01 10:30:00', 1,
       t.Price, 'free', 'EN-FT-0002'
FROM EVENTS e
JOIN TICKETS t ON t.EventID = e.EventID AND t.TicketType = 'Student'
JOIN USERS u ON u.Email = 'lerato.attendee02@example.com'
WHERE e.EventName = 'FutureTech Connect 2026'
  AND NOT EXISTS (SELECT 1 FROM BOOKINGS WHERE BookingReference = 'EN-FT-0002');

INSERT INTO BOOKINGS
    (EventID, UserID, TicketID, BookingDate, Quantity, TotalAmount, Status, BookingReference)
SELECT e.EventID, u.UserID, t.TicketID, '2026-10-02 11:00:00', 1,
       t.Price, 'pending', 'EN-BN-0001'
FROM EVENTS e
JOIN TICKETS t ON t.EventID = e.EventID AND t.TicketType = 'Early Bird'
JOIN USERS u ON u.Email = 'lerato.attendee02@example.com'
WHERE e.EventName = 'Young Business Network Meetup'
  AND NOT EXISTS (SELECT 1 FROM BOOKINGS WHERE BookingReference = 'EN-BN-0001');

INSERT INTO BOOKINGS
    (EventID, UserID, TicketID, BookingDate, Quantity, TotalAmount, Status, BookingReference)
SELECT e.EventID, u.UserID, t.TicketID, '2026-09-01 14:00:00', 2,
       t.Price * 2, 'confirmed', 'EN-CS-0001'
FROM EVENTS e
JOIN TICKETS t ON t.EventID = e.EventID AND t.TicketType = 'General'
JOIN USERS u ON u.Email = 'amani.attendee01@example.com'
WHERE e.EventName = 'Career Skills Expo 2026'
  AND NOT EXISTS (SELECT 1 FROM BOOKINGS WHERE BookingReference = 'EN-CS-0001');

INSERT INTO BOOKINGS
    (EventID, UserID, TicketID, BookingDate, Quantity, TotalAmount, Status, BookingReference)
SELECT e.EventID, u.UserID, t.TicketID, '2026-09-02 15:30:00', 1,
       t.Price, 'refunded', 'EN-CS-0002'
FROM EVENTS e
JOIN TICKETS t ON t.EventID = e.EventID AND t.TicketType = 'General'
JOIN USERS u ON u.Email = 'lerato.attendee02@example.com'
WHERE e.EventName = 'Career Skills Expo 2026'
  AND NOT EXISTS (SELECT 1 FROM BOOKINGS WHERE BookingReference = 'EN-CS-0002');

INSERT INTO BOOKINGS
    (EventID, UserID, TicketID, BookingDate, Quantity, TotalAmount, Status, BookingReference)
SELECT e.EventID, u.UserID, t.TicketID, '2026-09-03 16:00:00', 1,
       t.Price, 'canceled', 'EN-HE-0001'
FROM EVENTS e
JOIN TICKETS t ON t.EventID = e.EventID AND t.TicketType = 'General'
JOIN USERS u ON u.Email = 'amani.attendee01@example.com'
WHERE e.EventName = 'Heritage Creative Evening'
  AND NOT EXISTS (SELECT 1 FROM BOOKINGS WHERE BookingReference = 'EN-HE-0001');

-- ============================================================
-- 6. PAYMENTS
-- ============================================================
INSERT INTO PAYMENTS
    (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
SELECT b.BookingID, '2026-10-01 09:20:00', b.TotalAmount, 'PayFast',
       'success', 'PF-DEV-FT-0001', 'held'
FROM BOOKINGS b
WHERE b.BookingReference = 'EN-FT-0001'
  AND NOT EXISTS (SELECT 1 FROM PAYMENTS WHERE TransactionID = 'PF-DEV-FT-0001');

INSERT INTO PAYMENTS
    (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
SELECT b.BookingID, '2026-09-01 14:05:00', b.TotalAmount, 'PayFast',
       'success', 'PF-DEV-CS-0001', 'released'
FROM BOOKINGS b
WHERE b.BookingReference = 'EN-CS-0001'
  AND NOT EXISTS (SELECT 1 FROM PAYMENTS WHERE TransactionID = 'PF-DEV-CS-0001');

INSERT INTO PAYMENTS
    (BookingID, PaymentDate, Amount, PaymentMethod, PaymentStatus, TransactionID, Escrow_status)
SELECT b.BookingID, '2026-09-02 15:35:00', b.TotalAmount, 'PayFast',
       'refunded', 'PF-DEV-CS-0002', 'refunded'
FROM BOOKINGS b
WHERE b.BookingReference = 'EN-CS-0002'
  AND NOT EXISTS (SELECT 1 FROM PAYMENTS WHERE TransactionID = 'PF-DEV-CS-0002');

-- ============================================================
-- 7. POSTS
-- ============================================================
INSERT INTO POSTS
    (EventID, OrganiserID, Content, Media_type, Media, Hashtags, PostDate)
SELECT e.EventID, u.UserID,
       'Registration is open for our fictional FutureTech Connect event.',
       NULL, NULL, '#technology #eventnest', '2026-10-01 08:00:00'
FROM EVENTS e
JOIN USERS u ON u.Email = 'kea.organiser01@example.com'
WHERE e.EventName = 'FutureTech Connect 2026'
  AND NOT EXISTS (
      SELECT 1 FROM POSTS p
      WHERE p.EventID = e.EventID
        AND p.Content = 'Registration is open for our fictional FutureTech Connect event.'
  );

INSERT INTO POSTS
    (EventID, OrganiserID, Content, Media_type, Media, Hashtags, PostDate)
SELECT e.EventID, u.UserID,
       'A fictional event image announcement for FutureTech Connect.',
       'image', NULL, '#technology #eventnest',
       '2026-10-02 09:00:00'
FROM EVENTS e
JOIN USERS u ON u.Email = 'kea.organiser01@example.com'
WHERE e.EventName = 'FutureTech Connect 2026'
  AND NOT EXISTS (
      SELECT 1 FROM POSTS p
      WHERE p.EventID = e.EventID
        AND p.Content = 'A fictional event image announcement for FutureTech Connect.'
  );

INSERT INTO POSTS
    (EventID, OrganiserID, Content, Media_type, Media, Hashtags, PostDate)
SELECT e.EventID, u.UserID,
       'A fictional short video announcement for the Young Business Network Meetup.',
       'video', NULL, '#business #eventnest',
       '2026-10-02 12:00:00'
FROM EVENTS e
JOIN USERS u ON u.Email = 'kea.organiser01@example.com'
WHERE e.EventName = 'Young Business Network Meetup'
  AND NOT EXISTS (
      SELECT 1 FROM POSTS p
      WHERE p.EventID = e.EventID
        AND p.Content = 'A fictional short video announcement for the Young Business Network Meetup.'
  );

-- ============================================================
-- 8. REACTIONS
-- ============================================================
INSERT INTO REACTIONS (PostID, UserID, ReactionType, ReactionDate)
SELECT p.PostID, u.UserID, 'thumbs_up', '2026-10-02 13:00:00'
FROM POSTS p
JOIN USERS u ON u.Email = 'amani.attendee01@example.com'
WHERE p.Content = 'Registration is open for our fictional FutureTech Connect event.'
  AND NOT EXISTS (
      SELECT 1 FROM REACTIONS r
      WHERE r.PostID = p.PostID AND r.UserID = u.UserID
  );

INSERT INTO REACTIONS (PostID, UserID, ReactionType, ReactionDate)
SELECT p.PostID, u.UserID, 'clap', '2026-10-02 13:05:00'
FROM POSTS p
JOIN USERS u ON u.Email = 'lerato.attendee02@example.com'
WHERE p.Content = 'A fictional event image announcement for FutureTech Connect.'
  AND NOT EXISTS (
      SELECT 1 FROM REACTIONS r
      WHERE r.PostID = p.PostID AND r.UserID = u.UserID
  );

INSERT INTO REACTIONS (PostID, UserID, ReactionType, ReactionDate)
SELECT p.PostID, u.UserID, 'lightbulb', '2026-10-02 13:10:00'
FROM POSTS p
JOIN USERS u ON u.Email = 'neo.attendee03@example.com'
WHERE p.Content = 'A fictional short video announcement for the Young Business Network Meetup.'
  AND NOT EXISTS (
      SELECT 1 FROM REACTIONS r
      WHERE r.PostID = p.PostID AND r.UserID = u.UserID
  );

-- ============================================================
-- 9. POST_CATEGORIES
-- ============================================================
INSERT INTO Post_Categories (post_id, category_id)
SELECT p.PostID, c.CategoryID
FROM POSTS p
JOIN CATEGORIES c ON c.CategoryName = 'Technology'
WHERE p.Content = 'Registration is open for our fictional FutureTech Connect event.'
  AND NOT EXISTS (
      SELECT 1 FROM Post_Categories pc
      WHERE pc.post_id = p.PostID AND pc.category_id = c.CategoryID
  );

INSERT INTO Post_Categories (post_id, category_id)
SELECT p.PostID, c.CategoryID
FROM POSTS p
JOIN CATEGORIES c ON c.CategoryName = 'Technology'
WHERE p.Content = 'A fictional event image announcement for FutureTech Connect.'
  AND NOT EXISTS (
      SELECT 1 FROM Post_Categories pc
      WHERE pc.post_id = p.PostID AND pc.category_id = c.CategoryID
  );

INSERT INTO Post_Categories (post_id, category_id)
SELECT p.PostID, c.CategoryID
FROM POSTS p
JOIN CATEGORIES c ON c.CategoryName = 'Business'
WHERE p.Content = 'A fictional short video announcement for the Young Business Network Meetup.'
  AND NOT EXISTS (
      SELECT 1 FROM Post_Categories pc
      WHERE pc.post_id = p.PostID AND pc.category_id = c.CategoryID
  );

-- ============================================================
-- 10. REVIEWS
-- ============================================================
INSERT INTO REVIEWS
    (BookingID, UserID, EventID, Rating, Comment, Tags, WantsContact, ContactEmail, ReviewDate)
SELECT b.BookingID, b.UserID, b.EventID, 5, NULL,
       'well-organised, useful', FALSE, NULL, '2026-09-14 10:00:00'
FROM BOOKINGS b
WHERE b.BookingReference = 'EN-CS-0001'
  AND NOT EXISTS (SELECT 1 FROM REVIEWS r WHERE r.BookingID = b.BookingID);

INSERT INTO REVIEWS
    (BookingID, UserID, EventID, Rating, Comment, Tags, WantsContact, ContactEmail, ReviewDate)
SELECT b.BookingID, b.UserID, b.EventID, 3,
       'The fictional event was useful, but the programme could have been clearer.',
       NULL, TRUE, u.Email, '2026-09-15 11:00:00'
FROM BOOKINGS b
JOIN USERS u ON u.UserID = b.UserID
WHERE b.BookingReference = 'EN-CS-0002'
  AND NOT EXISTS (SELECT 1 FROM REVIEWS r WHERE r.BookingID = b.BookingID);

-- ============================================================
-- 11. BLOCKS
-- ============================================================
INSERT INTO BLOCKS (OrganiserID, BlockedEmail, Reason, BlockDate)
SELECT u.UserID, 'blocked.dev01@example.com',
       'Fictional development block used to test organiser blocking.',
       '2026-09-25 09:00:00'
FROM USERS u
WHERE u.Email = 'kea.organiser01@example.com'
  AND NOT EXISTS (
      SELECT 1 FROM BLOCKS b
      WHERE b.OrganiserID = u.UserID
        AND b.BlockedEmail = 'blocked.dev01@example.com'
  );

-- End of fictional EventNest seed data.
