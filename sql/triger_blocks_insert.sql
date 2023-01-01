/* Table Blocks Triger after inserted.*/

UPDATE coins set coins.block_height=NEW.height , coins.difficulty = NEW.difficulty where coins.id=NEW.coin_id
