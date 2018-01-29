-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

ALTER TABLE `markets` ADD `disabled` TINYINT(1) NOT NULL DEFAULT '0' AFTER `coinid`;
ALTER TABLE `markets` ADD `priority` TINYINT(1) NOT NULL DEFAULT '0' AFTER `marketid`;
ALTER TABLE `markets` ADD `ontrade` DOUBLE NOT NULL DEFAULT '0' AFTER `balance`;
ALTER TABLE `markets` ADD `balancetime` INT(11) NULL AFTER `lasttraded`;
ALTER TABLE `markets` ADD `pricetime` INT(11) NULL AFTER `price2`;
