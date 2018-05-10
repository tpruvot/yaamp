-- Changes since first yaamp release (already in sql.gz)

ALTER TABLE `accounts` ADD  `login`   varchar(45) DEFAULT NULL AFTER `coinsymbol`;
ALTER TABLE `accounts` ADD `hostaddr` varchar(39) DEFAULT NULL AFTER `login`;

