-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

CREATE TABLE `bookmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `idcoin` int(11) NOT NULL,
  `label` varchar(32) NULL,
  `address` varchar(128) NOT NULL,
  `lastused` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- KEYS

ALTER TABLE `bookmarks`
  ADD KEY `bookmarks_coin` (`idcoin`);

ALTER TABLE `bookmarks` ADD CONSTRAINT fk_bookmarks_coin FOREIGN KEY (`idcoin`)
  REFERENCES coins (`id`) ON DELETE CASCADE;
