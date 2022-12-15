/* Table Blocks Triger after inserted.*/

UPDATE coins set coins.block_height=NEW.height where coins.id=NEW.coin_id
