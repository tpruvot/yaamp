-- Recent additions to add after db init (.gz)

ALTER TABLE `payouts` ADD `errmsg` text NULL AFTER `tx`;
