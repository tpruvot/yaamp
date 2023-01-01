/* Table Blocks Triger after inserted.*/

UPDATE coins set coins.block_height=NEW.height , coins.difficulty = NEW.difficulty where coins.id=NEW.coin_id



/* add cronjob https://pool.xxx/site/checkblocks?id=coind_id to cronjob everyminute */
