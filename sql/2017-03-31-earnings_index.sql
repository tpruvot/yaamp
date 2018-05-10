-- Recent additions to add after db init (.gz)
-- mysql yaamp -p < file.sql

ALTER TABLE `earnings` ADD UNIQUE INDEX `ndx_user_block`(`userid`, `blockid`);

