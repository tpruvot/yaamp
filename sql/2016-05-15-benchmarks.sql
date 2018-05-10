-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

CREATE TABLE `benchmarks` (
  `id` int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `algo` varchar(16) NOT NULL,
  `type` varchar(8) NOT NULL,
  `khps` double NULL,
  `device` varchar(80) NULL,
  `vendorid` varchar(12) NULL,
  `arch` varchar(8) NULL,
  `power` int(5) UNSIGNED NULL,
  `freq` int(8) UNSIGNED NULL,
  `memf` int(8) UNSIGNED NULL,
  `client` varchar(48) NULL,
  `os` varchar(8) NULL,
  `driver` varchar(32) NULL,
  `intensity` double NULL,
  `throughput` int(11) UNSIGNED NULL,
  `userid` int(11) NULL,
  `time` int(10) UNSIGNED NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

-- KEYS & Indexes

ALTER TABLE `benchmarks`
  ADD KEY `bench_userid` (`userid`),
  ADD INDEX `ndx_type` (`type`),
  ADD INDEX `ndx_algo` (`algo`),
  ADD INDEX `ndx_time` (`time` DESC);

