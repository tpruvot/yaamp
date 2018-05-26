-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- don't forget to restart memcached service to refresh the db structure

ALTER TABLE `coins` ADD `powend_height` INT(11) NULL AFTER `target_height`;
ALTER TABLE `coins` ADD `mature_blocks` INT(11) NULL AFTER `reward_mul`;
ALTER TABLE `coins` ADD `block_time` INT(11) NULL AFTER `payout_max`;
ALTER TABLE `coins` ADD `available` DOUBLE NULL AFTER `balance`;
ALTER TABLE `coins` ADD `cleared` DOUBLE NULL AFTER `balance`;
ALTER TABLE `coins` ADD `immature` DOUBLE NULL AFTER `balance`;
ALTER TABLE `coins` ADD `max_miners` INT(11) NULL AFTER `visible`;
ALTER TABLE `coins` ADD `max_shares` INT(11) NULL AFTER `max_miners`;
