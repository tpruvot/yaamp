-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- filled by the stratum instance, to allow to handle/watch multiple instances

ALTER TABLE `stratums` ADD `started` int(11) UNSIGNED NULL AFTER `time`;

ALTER TABLE `stratums` ADD `workers` int(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `algo`;

ALTER TABLE `stratums` ADD `port` int(6) UNSIGNED NULL AFTER `workers`;

ALTER TABLE `stratums` ADD `symbol` varchar(16) NULL AFTER `port`;

ALTER TABLE `stratums` ADD `url` varchar(128) NULL AFTER `symbol`;

ALTER TABLE `stratums` ADD `fds` int(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `url`;

