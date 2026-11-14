-- Lets the office control the order branches appear in on the public
-- site (the Classes page, the home page, etc.) instead of always being
-- fixed to creation order. Same "sort_order" pattern already used for
-- site_ads. Backfilled from the current id order, so nothing visibly
-- reorders the moment this runs.
ALTER TABLE institutes ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER status;

SET @rownum := 0;
UPDATE institutes SET sort_order = (@rownum := @rownum + 1) * 10 ORDER BY id ASC;
