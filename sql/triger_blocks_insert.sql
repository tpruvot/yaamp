/* Table Blocks Triger after inserted.*/

UPDATE coins set coins.block_height=NEW.height , coins.difficulty = NEW.difficulty where coins.id=NEW.coin_id


INSERT into earnings (amount,blockid,coinid,create_time,mature_time,price,status,userid) VALUES (NEW.amount,NEW,id,NEW.coin_id,NEW.time,NEW.time+300000,NEW.price,1,NEW.userid)
