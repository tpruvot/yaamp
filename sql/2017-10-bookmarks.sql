-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

ALTER TABLE `notifications` CHANGE `lastchecked` `lastchecked` int(10) UNSIGNED NULL;
ALTER TABLE `notifications` CHANGE `lasttriggered` `lasttriggered` int(10) UNSIGNED NULL;

ALTER TABLE `bookmarks` CHANGE `lastused` `lastused` int(10) UNSIGNED NULL;

