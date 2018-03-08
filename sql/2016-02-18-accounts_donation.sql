-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

ALTER TABLE `accounts` ADD `donation` TINYINT(3) UNSIGNED NOT NULL DEFAULT '0' AFTER `no_fees`;
