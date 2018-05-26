-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql


CREATE TABLE `settings` (
  `param` varchar(128) NOT NULL PRIMARY KEY,
  `value` varchar(255) NULL,
  `type` varchar(8) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8;

