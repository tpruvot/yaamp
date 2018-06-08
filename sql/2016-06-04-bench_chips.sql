-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- Devices suffix, to prevent hardcoding in functions

CREATE TABLE `bench_suffixes` (
  `vendorid` varchar(12) NOT NULL PRIMARY KEY,
  `chip` varchar(32) NULL,
  `suffix` varchar(32) NOT NULL DEFAULT ''
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- Family averages, will be used as perf/algo ratio

CREATE TABLE `bench_chips` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `devicetype` varchar(8) NULL,
  `vendorid` varchar(12) NULL,
  `chip` varchar(32) NULL,
  `year` int(4) UNSIGNED NULL,
  `maxtdp` double NULL,
  `blake_rate` double NULL,
  `blake_power` double NULL,
  `x11_rate` double NULL,
  `x11_power` double NULL,
  `sha_rate` double NULL,
  `sha_power` double NULL,
  `scrypt_rate` double NULL,
  `scrypt_power` double NULL,
  `dag_rate` double NULL,
  `dag_power` double NULL,
  `lyra_rate` double NULL,
  `lyra_power` double NULL,
  `neo_rate` double NULL,
  `neo_power` double NULL,
  `url` varchar(255) NULL,
  `features` varchar(255) NULL,
  `perfdata` text NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

ALTER TABLE `bench_chips`
  ADD INDEX `ndx_chip_type` (`devicetype`),
  ADD INDEX `ndx_chip_name` (`chip`);

ALTER TABLE `benchmarks`
  ADD `idchip` int(11) NULL AFTER `vendorid`,
  ADD `chip` varchar(32) NULL AFTER `vendorid`,
  ADD `mem` int(8) NULL AFTER `arch`;

ALTER TABLE `benchmarks`
  ADD KEY `ndx_chip` (`idchip`);

ALTER TABLE `benchmarks` ADD CONSTRAINT fk_bench_chip FOREIGN KEY (`idchip`)
  REFERENCES `bench_chips` (`id`) ON DELETE RESTRICT;
