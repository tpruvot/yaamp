-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- Store coin id used on payment, memoid could be used later for xrp

ALTER TABLE `payouts`
  ADD `idcoin` int(11) NULL AFTER `account_id`,
  ADD `memoid` varchar(128) NULL AFTER `tx`;

ALTER TABLE `payouts` DROP COLUMN `account_ids`;

ALTER TABLE `payouts`
  ADD KEY `payouts_coin` (`idcoin`);

ALTER TABLE `payouts` ADD CONSTRAINT fk_payouts_coin FOREIGN KEY (`idcoin`)
  REFERENCES coins (`id`) ON DELETE CASCADE;

ALTER TABLE `payouts` ADD CONSTRAINT fk_payouts_account FOREIGN KEY (`account_id`)
  REFERENCES accounts (`id`) ON DELETE CASCADE;

