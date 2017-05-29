-- Recent additions to add after db init (.gz)

ALTER TABLE `coins` ADD `hasthrones` TINYINT(1) NOT NULL DEFAULT '0' AFTER `hasmasternodes`;

UPDATE coins SET hasthrones=1 WHERE symbol IN ('CRW');

ALTER TABLE `coins` ADD `serveruser` varchar(45) NULL AFTER `rpcpasswd`;
