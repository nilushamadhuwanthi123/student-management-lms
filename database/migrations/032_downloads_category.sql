-- Lets downloads be grouped into named folders (e.g. "Grade 10 Papers",
-- "Grade 11 Notes") on the public Downloads page, instead of one long
-- flat list. NULL/blank stays ungrouped, shown under "Other files".
ALTER TABLE site_downloads ADD COLUMN category VARCHAR(100) NULL AFTER title;
