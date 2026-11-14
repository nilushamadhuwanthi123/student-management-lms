-- Lets each branch's photo/video banner on the public Classes page be
-- sized from the CMS instead of one fixed height for every branch --
-- useful since a banner that looks fine with a photo can look empty or
-- oversized with nothing in it, or vice versa.
ALTER TABLE institutes ADD COLUMN banner_size ENUM('small','medium','large') NOT NULL DEFAULT 'medium' AFTER sort_order;
