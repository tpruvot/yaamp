-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- filled by the stratum instance, to allow to handle/watch multiple instances

ALTER TABLE `coins` ADD `hasgetinfo` tinyint(1) UNSIGNED NOT NULL DEFAULT '1' AFTER `account`;

UPDATE coins SET hassubmitblock=0 WHERE hassubmitblock IS NULL;
UPDATE coins SET hassubmitblock=1 WHERE hassubmitblock > 0;
ALTER TABLE `coins` CHANGE `hassubmitblock` `hassubmitblock` tinyint(1) UNSIGNED NOT NULL DEFAULT '1';

ALTER TABLE `coins` ADD `no_explorer` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `visible`;

