-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- don't forget to restart memcached service to refresh the db structure

ALTER TABLE `coins` ADD `watch` TINYINT(1) NOT NULL DEFAULT '0' AFTER `installed`;
ALTER TABLE `coins` ADD `multialgos` TINYINT(1) NOT NULL DEFAULT '0' AFTER `auxpow`;
ALTER TABLE `coins` ADD `stake` DOUBLE NULL AFTER `balance`;

