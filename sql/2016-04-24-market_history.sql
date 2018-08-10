-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql


CREATE TABLE `market_history` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `time` int(11) NOT NULL,
  `idcoin` int(11) NOT NULL,
  `price` double NULL,
  `price2` double NULL,
  `balance` double NULL,
  `idmarket` int(11) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- KEYS

ALTER TABLE `market_history`
  ADD KEY `idcoin` (`idcoin`),
  ADD KEY `idmarket` (`idmarket`),
  ADD INDEX `time` (`time` DESC);

ALTER TABLE market_history ADD CONSTRAINT fk_mh_market FOREIGN KEY (`idmarket`)
  REFERENCES markets (`id`) ON DELETE CASCADE;

ALTER TABLE market_history ADD CONSTRAINT fk_mh_coin FOREIGN KEY (`idcoin`)
  REFERENCES coins (`id`) ON DELETE CASCADE;
