-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

ALTER TABLE `notifications` CHANGE `lastchecked` `lastchecked` int(10) UNSIGNED NOT NULL DEFAULT '0';
ALTER TABLE `notifications` CHANGE `lasttriggered` `lasttriggered` int(10) UNSIGNED NOT NULL DEFAULT '0';

