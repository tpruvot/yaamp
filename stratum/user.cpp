
#include "stratum.h"

// sql injection security, unwanted chars
void db_check_user_input(char* input)
{
	char *p = NULL;
	if (input && input[0]) {
		p = strpbrk(input, " \"'\\");
		if(p) *p = '\0';
	}
}

bool db_check_symbol(YAAMP_DB *db, char *symbol)
{
	db_query(db, "SELECT symbol,symbol2 FROM coins WHERE symbol='%s' OR symbol2='%s'", symbol, symbol);

	MYSQL_RES *result = mysql_store_result(&db->mysql);
	if(!result) return false;

	MYSQL_ROW row = mysql_fetch_row(result);
	if(!row) return false;

	if(row[0]) {
		strncpy(symbol, row[0], sizeof(symbol));
		return true;
	}

	return false;
}

void db_add_user(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	db_clean_string(db, client->username);
	db_clean_string(db, client->password);
	db_clean_string(db, client->version);
	db_clean_string(db, client->notify_id);
	db_clean_string(db, client->worker);

	char symbol[16] = { 0 };
	char *p = strstr(client->password, "c=");
	if(!p) p = strstr(client->password, "s=");
	if(p) strncpy(symbol, p+2, 15);
	p = strchr(symbol, ',');
	if(p) *p = '\0';

	bool guest = false;
	int gift = -1;
#ifdef ALLOW_CUSTOM_DONATIONS
	// donation percent
	p = strstr(client->password, "g=");
	if(p) gift = atoi(p+2);
	if(gift > 100) gift = 100;
#endif

	db_check_user_input(client->username);
	if(strlen(client->username) < MIN_ADDRESS_LEN) {
		// allow benchmark / test / donate usernames
		if (!strcmp(client->username, "benchmark") || !strcmp(client->username, "donate") || !strcmp(client->username, "test")) {
			guest = true;
			if (g_list_coind.first) {
				CLI li = g_list_coind.first;
				YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
				if (!strlen(client->worker)) strcpy(client->worker, client->username);
				strcpy(client->username, coind->wallet);
				if (!strcmp(client->username, "benchmark")) strcat(client->password, ",stats");
				if (!strcmp(client->username, "donate")) gift = 100;
			}
		}
		if (!guest) {
			debuglog("Invalid user address '%s'\n", client->username);
			return;
		}
	}

	if(!db_check_symbol(db, symbol)) {
		debuglog("Invalid coin symbol '%s' from '%s'\n", symbol, client->username);
		return;
	}

	// debuglog("user %s %s gives %d %\n", client->username, symbol, gift);
	db_query(db, "SELECT id, is_locked, logtraffic, coinid, donation FROM accounts WHERE username='%s'", client->username);

	MYSQL_RES *result = mysql_store_result(&db->mysql);
	if(!result) return;

	MYSQL_ROW row = mysql_fetch_row(result);
	if(row)
	{
		if(row[1] && atoi(row[1])) client->userid = -1;
		else client->userid = atoi(row[0]);

		client->logtraffic = row[2] && atoi(row[2]);
		client->coinid = row[3] ? atoi(row[3]) : 0;
		if (gift == -1) gift = row[4] ? atoi(row[4]) : 0; // keep current
	}

	mysql_free_result(result);

	db_check_user_input(symbol);

	if (gift < 0) gift = 0;
	client->donation = gift;

	if(client->userid == -1)
		return;

	else if(client->userid == 0 && strlen(client->username) >= MIN_ADDRESS_LEN)
	{
		db_query(db, "INSERT INTO accounts (username, coinsymbol, balance, donation) values ('%s', '%s', 0, %d)",
			client->username, symbol, gift);
		client->userid = (int)mysql_insert_id(&db->mysql);
	}

	else
		db_query(db, "UPDATE accounts SET coinsymbol='%s', donation=%d WHERE id=%d AND balance = 0"
			" AND (SELECT COUNT(id) FROM payouts WHERE account_id=%d AND tx IS NULL) = 0" // failed balance
			" AND (SELECT pending FROM balanceuser WHERE userid=%d ORDER by time DESC LIMIT 1) = 0" // pending balance
			, symbol, gift, client->userid, client->userid, client->userid);
}

//////////////////////////////////////////////////////////////////////////////////////

void db_clear_worker(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	if(!client->workerid)
		return;

	db_query(db, "DELETE FROM workers WHERE id=%d", client->workerid);
	client->workerid = 0;
}

void db_add_worker(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	db_clear_worker(db, client);
	int now = time(NULL);

	/* maybe not required here (already made), but... */
	db_check_user_input(client->username);
	db_check_user_input(client->version);
	db_check_user_input(client->password);
	db_check_user_input(client->worker);

	db_query(db, "INSERT INTO workers (userid, ip, name, difficulty, version, password, worker, algo, time, pid) "\
		"VALUES (%d, '%s', '%s', %f, '%s', '%s', '%s', '%s', %d, %d)",
		client->userid, client->sock->ip, client->username, client->difficulty_actual,
		client->version, client->password, client->worker, g_stratum_algo, now, getpid());

	client->workerid = (int)mysql_insert_id(&db->mysql);
}

void db_update_workers(YAAMP_DB *db)
{
	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *client = (YAAMP_CLIENT *)li->data;
		if(client->deleted) continue;
		if(!client->workerid) continue;

		if(client->speed < 0.00001)
		{
			clientlog(client, "speed %f", client->speed);
			shutdown(client->sock->sock, SHUT_RDWR);

			continue;
		}

		client->speed *= 0.8;
		if(client->difficulty_written == client->difficulty_actual) continue;

		db_query(db, "UPDATE workers SET difficulty=%f, subscribe=%d WHERE id=%d",
			client->difficulty_actual, client->extranonce_subscribe, client->workerid);
		client->difficulty_written = client->difficulty_actual;
	}

	client_sort();
	g_list_client.Leave();
}

void db_init_user_coinid(YAAMP_DB *db, YAAMP_CLIENT *client)
{
	if (!client->userid)
		return;

	if (!client->coinid)
		db_query(db, "UPDATE accounts SET coinid=NULL WHERE id=%d", client->userid);
	else
		db_query(db, "UPDATE accounts SET coinid=%d WHERE id=%d AND IFNULL(coinid,0) = 0",
			client->coinid, client->userid);
}

