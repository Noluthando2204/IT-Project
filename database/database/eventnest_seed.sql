
-- =========================================================
-- EventNest Seed Data
-- =========================================================

USE eventnest;

-- =========================================================
-- USERS
-- =========================================================

INSERT INTO users
    (full_name, email, password_hash, industry, user_status)
VALUES
(
    'Test Student',
    'student@eventnest.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2wzJ8jY9b9fYJ7mK',
    'Technology',
    'school'
),
(
    'University User',
    'university@eventnest.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2wzJ8jY9b9fYJ7mK',
    'Business',
    'uni'
),
(
    'Professional User',
    'professional@eventnest.test',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC2wzJ8jY9b9fYJ7mK',
    'Information Technology',
    'professional'
);


-- =========================================================
-- HASHTAGS
-- =========================================================

INSERT INTO hashtags (hashtag_name)
VALUES
('technology'),
('business'),
('career'),
('education'),
('networking'),
('programming'),
('entrepreneurship'),
('student');


-- =========================================================
-- USER HASHTAGS
-- =========================================================

INSERT INTO user_hashtags (user_id, hashtag_id)
SELECT 1, hashtag_id
FROM hashtags
WHERE hashtag_name IN
('technology', 'education', 'programming');

INSERT INTO user_hashtags (user_id, hashtag_id)
SELECT 2, hashtag_id
FROM hashtags
WHERE hashtag_name IN
('business', 'career', 'networking');

INSERT INTO user_hashtags (user_id, hashtag_id)
SELECT 3, hashtag_id
FROM hashtags
WHERE hashtag_name IN
('technology', 'career', 'entrepreneurship');


-- =========================================================
-- EVENTS
-- =========================================================

INSERT INTO events
    (creator_id, title, description, event_date, location, capacity)
VALUES
(
    1,
    'Student Technology Meetup',
    'A networking event for students interested in technology and programming.',
    '2026-10-15 10:00:00',
    'Johannesburg',
    100
),
(
    2,
    'Career Networking Day',
    'Connect with students, graduates and professionals from different industries.',
    '2026-10-20 09:00:00',
    'Pretoria',
    200
),
(
    3,
    'Entrepreneurship Workshop',
    'A workshop covering entrepreneurship, business development and networking.',
    '2026-11-05 13:00:00',
    'Johannesburg',
    75
);


-- =========================================================
-- EVENT ATTENDEES
-- =========================================================

INSERT INTO event_attendees
    (event_id, user_id)
VALUES
(1, 1),
(1, 2),
(2, 1),
(2, 2),
(2, 3),
(3, 3);


-- =========================================================
-- POSTS
-- =========================================================

INSERT INTO posts
    (user_id, content)
VALUES
(
    1,
    'Looking forward to meeting other students interested in technology!'
),
(
    2,
    'The career networking event is a great opportunity to connect with professionals.'
),
(
    3,
    'Excited to share some entrepreneurship ideas at the upcoming workshop.'
);


-- =========================================================
-- REACTIONS
-- =========================================================

INSERT INTO reactions
    (post_id, user_id, reaction_type)
VALUES
(1, 2, 'like'),
(1, 3, 'interested'),
(2, 1, 'support'),
(3, 2, 'love');


-- =========================================================
-- DEFERRED REACTIONS
-- =========================================================

INSERT INTO deferred_reactions
    (post_id, user_id, reaction_type, status)
VALUES
(1, 3, 'like', 'pending');