-- Run once on an existing EventNest database to enable category and event images.
ALTER TABLE CATEGORIES ADD COLUMN ImagePath VARCHAR(255) NULL AFTER Description;
ALTER TABLE EVENTS ADD COLUMN ImagePath VARCHAR(255) NULL AFTER Province;