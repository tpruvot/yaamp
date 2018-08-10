-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- don't forget to restart memcached service to refresh the db structure

ALTER TABLE `coins` ADD `rpcssl` TINYINT(1) NOT NULL DEFAULT '0' AFTER `rpcport`;
ALTER TABLE `coins` ADD `rpccurl` TINYINT(1) NOT NULL DEFAULT '0' AFTER `rpcport`;
ALTER TABLE `coins` ADD `rpccert` VARCHAR(255) NULL AFTER `rpcssl`;
ALTER TABLE `coins` ADD `account` VARCHAR(64) NOT NULL DEFAULT '' AFTER `rpcencoding`;
ALTER TABLE `coins` ADD `payout_min` DOUBLE NULL AFTER `txfee`;
ALTER TABLE `coins` ADD `payout_max` DOUBLE NULL AFTER `payout_min`;
ALTER TABLE `coins` ADD `link_site` VARCHAR(1024) NULL AFTER `installed`;

ALTER TABLE `coins` ADD INDEX `created` (`created` DESC);

