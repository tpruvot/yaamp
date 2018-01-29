-- Recent additions to add after db init (.gz)

ALTER TABLE `coins` ADD `hasmasternodes` TINYINT(1) NOT NULL DEFAULT '0' AFTER `hassubmitblock`;

UPDATE coins SET hasmasternodes=1 WHERE symbol IN ('DASH','BOD','CHC','MDT');

ALTER TABLE `coins` ADD `serveruser` varchar(45) NULL AFTER `rpcpasswd`;
