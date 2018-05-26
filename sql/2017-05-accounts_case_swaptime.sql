-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

-- Adds binary type to be case sensitive
ALTER TABLE accounts CHANGE `username` `username` varchar(128) binary NOT NULL;

-- Remember last coin id swap
ALTER TABLE accounts ADD `swap_time` INT(10) UNSIGNED NULL AFTER `coinsymbol`;

