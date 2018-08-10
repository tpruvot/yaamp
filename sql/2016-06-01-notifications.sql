-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `idcoin` int(11) NOT NULL,
  `enabled` int(1) NOT NULL DEFAULT '0',
  `description` varchar(128) NULL,
  `conditiontype` varchar(32) NULL,
  `conditionvalue` double NULL,
  `notifytype` varchar(32) NULL,
  `notifycmd` varchar(512) NULL,
  `lastchecked` int(10) UNSIGNED NOT NULL,
  `lasttriggered` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- KEYS

ALTER TABLE `notifications`
  ADD KEY `notif_coin` (`idcoin`),
  ADD INDEX `notif_checked` (`lastchecked`);

ALTER TABLE `notifications` ADD CONSTRAINT fk_notif_coin FOREIGN KEY (`idcoin`)
  REFERENCES coins (`id`) ON DELETE CASCADE;
