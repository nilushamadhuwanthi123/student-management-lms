-- Seminars can be advertised on the public home page.
--
-- show_on_website: the poster ticks "Show on the website home page".
--   Only published, upcoming seminars with this set appear there, and
--   only their public details (title, date, time, venue, branch, poster,
--   description) -- never the online meeting link.
--
-- Purely additive: existing seminars default to 0 (not shown), which is
-- exactly today's behaviour.

ALTER TABLE seminars
    ADD COLUMN IF NOT EXISTS show_on_website TINYINT(1) NOT NULL DEFAULT 0 AFTER registration_required;

ALTER TABLE seminars
    ADD INDEX IF NOT EXISTS idx_seminar_website (show_on_website, status, event_date);
