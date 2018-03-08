-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

ALTER TABLE `accounts` CHANGE COLUMN `last_login` `last_earning` INT(10) NULL;

ALTER TABLE `accounts` ADD INDEX `earning` (`last_earning` DESC);

