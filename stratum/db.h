
class YAAMP_CLIENT;

struct YAAMP_DB
{
	MYSQL mysql;

};

YAAMP_DB *db_connect();

char *db_clean_string(YAAMP_DB *db, char *string);

void db_close(YAAMP_DB *p);
void db_query(YAAMP_DB *db, const char *format, ...);

void db_register_stratum(YAAMP_DB *db);
void db_update_algos(YAAMP_DB *db);
void db_update_coinds(YAAMP_DB *db);
void db_update_remotes(YAAMP_DB *db);

//int db_find_user(YAAMP_DB *db, YAAMP_CLIENT *client);
void db_add_user(YAAMP_DB *db, YAAMP_CLIENT *client);

void db_add_worker(YAAMP_DB *db, YAAMP_CLIENT *client);
void db_clear_worker(YAAMP_DB *db, YAAMP_CLIENT *client);
void db_update_worker(YAAMP_DB *db, YAAMP_CLIENT *client);
void db_update_workers(YAAMP_DB *db);

void db_init_user_coinid(YAAMP_DB *db, YAAMP_CLIENT *client);

void db_store_stats(YAAMP_DB *db, YAAMP_CLIENT *client, json_value *stats);

void db_update_renters(YAAMP_DB *db);

