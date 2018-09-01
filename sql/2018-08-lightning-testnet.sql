CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `userid` int(11) NOT NULL,
  `bolt11` varchar(1000) NOT NULL,
  `amount` int(11) NOT NULL,
  `paid` int(11) NOT NULL,
  `shop` text,
  `description` text NOT NULL,
  `status` varchar(80) DEFAULT NULL,
  `exectime` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `workers` ADD `work` DOUBLE NOT NULL DEFAULT '0.0' AFTER `algo`;
