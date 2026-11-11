-- 025: a map pin per branch, and the class's own links.
--
-- The contact page draws a map for every branch. With nothing stored it
-- searches for the branch name and town, which is as precise as the
-- address we actually have; map_url lets the office paste the exact
-- Google Maps link for the building and get a real pin instead.
-- Safe to re-run.

ALTER TABLE institutes
    ADD COLUMN IF NOT EXISTS map_url VARCHAR(500) NULL AFTER location;
