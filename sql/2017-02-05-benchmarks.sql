-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- don't forget to restart memcached service to refresh the db structure

ALTER TABLE `benchmarks` ADD `realfreq` INT(8) UNSIGNED NULL AFTER `freq`;
ALTER TABLE `benchmarks` ADD `realmemf` INT(8) UNSIGNED NULL AFTER `memf`;
ALTER TABLE `benchmarks` ADD `plimit` INT(5) UNSIGNED NULL AFTER `power`;
ALTER TABLE `benchmarks` DROP COLUMN `mem`;

