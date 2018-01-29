-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

ALTER TABLE `blocks` ADD `segwit` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `txhash`;

ALTER TABLE `coins` ADD `usesegwit` tinyint(1) UNSIGNED NOT NULL DEFAULT '0' AFTER `usememorypool`;

