-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- NOT NULL
ALTER TABLE `shares` CHANGE COLUMN `difficulty` `difficulty` DOUBLE NOT NULL DEFAULT '0';

ALTER TABLE `shares` ADD `share_diff` DOUBLE NOT NULL DEFAULT '0' AFTER `difficulty`;


