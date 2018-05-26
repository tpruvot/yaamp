
#include "stratum.h"
#include <mysql/mysqld_error.h>
#include <signal.h>

void db_reconnect(YAAMP_DB *db)
{
	if (g_exiting) {
		db_close(db);
		return;
	}

	mysql_init(&db->mysql);
	for(int i=0; i<6; i++)
	{
		MYSQL *p = mysql_real_connect(&db->mysql, g_sql_host, g_sql_username, g_sql_password, g_sql_database, g_sql_port, 0, 0);
		if(p) break;

		stratumlog("%d, %s\n", i, mysql_error(&db->mysql));
		sleep(10);

		mysql_close(&db->mysql);
		mysql_init(&db->mysql);
	}
}

YAAMP_DB *db_connect()
{
	YAAMP_DB *db = new YAAMP_DB;
	db_reconnect(db);

	return db;
}

void db_close(YAAMP_DB *db)
{
	if (db) {
		mysql_close(&db->mysql);
		delete db;
	}
	db = NULL;
}

char *db_clean_string(YAAMP_DB *db, char *string)
{
	char *c = string;
	size_t i, len = strlen(string) & 0x1FF;
	for (i = 0; i < len; i++) {
		bool isdigit = (c[i] >= '0' && c[i] <= '9');
		bool isalpha = (c[i] >= 'a' && c[i] <= 'z') || (c[i] >= 'A' && c[i] <= 'Z');
		bool issepch = (c[i] == '=' || c[i] == ',' || c[i] == ';' || c[i] == '.');
		bool isextra = (c[i] == '/' || c[i] == '-' || c[i] == '_');
		if (!isdigit && !isalpha && !issepch && !isextra) { c[i] = '\0'; break; }
	}
	return string;
}

// allow more chars without the most hurting ones (bench device names)
static void clean_html(char* string)
{
	char *c = string;
	size_t i, len = strlen(string) & 0x1FF;
	for (i = 0; i < len; i++) {
		if (c[i] == '<' || c[i] == '>' || c[i] == '%' || c[i] == '\\' || c[i] == '"' || c[i] == '\'') {
			c[i] = '\0'; break;
		}
	}
	if (strstr(string, "script")) strcpy(string, "");
}

void db_query(YAAMP_DB *db, const char *format, ...)
{
	va_list arglist;
	va_start(arglist, format);
	if(!db) return;

	char *buffer = (char *)malloc(YAAMP_SMALLBUFSIZE+strlen(format));
	if(!buffer) return;

	int len = vsprintf(buffer, format, arglist);
	va_end(arglist);

	while(!g_exiting)
	{
		int res = mysql_query(&db->mysql, buffer);
		if(!res) break;
		res = mysql_errno(&db->mysql);

		stratumlog("SQL ERROR: %d, %s\n", res, mysql_error(&db->mysql));
		if(res == ER_DUP_ENTRY) break; // rarely seen on new user creation
		if(res != CR_SERVER_GONE_ERROR && res != CR_SERVER_LOST) exit(1);

		usleep(100*YAAMP_MS);
		db_reconnect(db);
	}

	free(buffer);
}

///////////////////////////////////////////////////////////////////////

void db_register_stratum(YAAMP_DB *db)
{
	int pid = getpid();
	int t = time(NULL);
	if(!db) return;

	db_query(db, "INSERT INTO stratums (pid, time, started, algo, url, port) VALUES (%d,%d,%d,'%s','%s',%d) "
		" ON DUPLICATE KEY UPDATE time=%d, algo='%s', url='%s', port=%d",
		pid, t, t, g_stratum_algo, g_tcp_server, g_tcp_port,
		t, g_stratum_algo, g_tcp_server, g_tcp_port
	);
}

void db_update_algos(YAAMP_DB *db)
{
	int pid = getpid();
	int fds = opened_files();
	if(!db) return;

	if(g_current_algo->overflow)
	{
		debuglog("setting overflow\n");
		g_current_algo->overflow = false;

		db_query(db, "UPDATE algos SET overflow=true WHERE name='%s'", g_stratum_algo);
	}

	char symbol[16] = "NULL\0";
	if(g_list_coind.count == 1) {
		if (g_list_coind.first) {
			CLI li = g_list_coind.first;
			YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
			sprintf(symbol,"'%s'", coind->symbol);
		}
	}

	db_query(db, "UPDATE stratums SET workers=%d, fds=%d, symbol=%s WHERE pid=%d",
		g_list_client.count, fds, symbol, pid);

	///////////////////////////////////////////////////////////////////////////////////////////

	db_query(db, "select name, profit, rent, factor from algos");

	MYSQL_RES *result = mysql_store_result(&db->mysql);
	if(!result) return;

	MYSQL_ROW row;
	while((row = mysql_fetch_row(result)) != NULL)
	{
		YAAMP_ALGO *algo = stratum_find_algo(row[0]);
		if(!algo) continue;

		if(row[1]) algo->profit = atof(row[1]);
		if(row[2]) algo->rent = atof(row[2]);
		if(row[3]) algo->factor = atof(row[3]);
	}

	mysql_free_result(result);

	////////////////////

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *client = (YAAMP_CLIENT *)li->data;
		if(client->deleted) continue;

		client_reset_multialgo(client, false);
	}

	g_list_client.Leave();
}

////////////////////////////////////////////////////////////////////////////////

void db_update_coinds(YAAMP_DB *db)
{
	if(!db) return;

	for(CLI li = g_list_coind.first; li; li = li->next)
	{
		YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
		if(coind->deleted) continue;
		if(coind->auto_ready) continue;

		debuglog("disabling %s\n", coind->symbol);
		db_query(db, "update coins set auto_ready=%d where id=%d", coind->auto_ready, coind->id);
	}

	////////////////////////////////////////////////////////////////////////////////////////

	db_query(db, "SELECT id, name, rpchost, rpcport, rpcuser, rpcpasswd, rpcencoding, master_wallet, reward, price, "
		"hassubmitblock, txmessage, enable, auto_ready, algo, pool_ttf, charity_address, charity_amount, charity_percent, "
		"reward_mul, symbol, auxpow, actual_ttf, network_ttf, usememorypool, hasmasternodes, algo, symbol2, "
		"rpccurl, rpcssl, rpccert, account, multialgos, max_miners, max_shares, usesegwit "
		"FROM coins WHERE enable AND auto_ready AND algo='%s' ORDER BY index_avg", g_stratum_algo);

	MYSQL_RES *result = mysql_store_result(&db->mysql);
	if(!result) yaamp_error("Cant query database");

	MYSQL_ROW row;
	g_list_coind.Enter();

	while((row = mysql_fetch_row(result)) != NULL)
	{
		YAAMP_COIND *coind = (YAAMP_COIND *)object_find(&g_list_coind, atoi(row[0]));
		if(!coind)
		{
			coind = new YAAMP_COIND;
			memset(coind, 0, sizeof(YAAMP_COIND));

			coind->newcoind = true;
			coind->newblock = true;
			coind->id = atoi(row[0]);
			coind->aux.coind = coind;
		}
		else
			coind->newcoind = false;

		strcpy(coind->name, row[1]);
		strcpy(coind->symbol, row[20]);
		// optional coin filters
		if(coind->newcoind) {
			bool ignore = false;
			if (strlen(g_stratum_coin_include) && !strstr(g_stratum_coin_include, coind->symbol)) ignore = true;
			if (strlen(g_stratum_coin_exclude) && strstr(g_stratum_coin_exclude, coind->symbol)) ignore = true;
			if (ignore) {
				object_delete(coind);
				continue;
			}
		}

		if(row[7]) strcpy(coind->wallet, row[7]);
		if(row[6]) strcpy(coind->rpcencoding, row[6]);
		if(row[6]) coind->pos = strcasecmp(row[6], "POS")? false: true;
		if(row[10]) coind->hassubmitblock = atoi(row[10]);

		coind->rpc.ssl = 0;
		// deprecated method to set ssl and cert (before db specific fields)
		if(row[2]) {
			char buffer[1024];
			char cert[1024];
			strcpy(buffer, row[2]);
			// sample ssl host : "https://mycert@127.0.0.1"
			if (strstr(buffer, "https://") != NULL) {
				strcpy(buffer, row[2] + 8);
				if (strstr(buffer, "@") != NULL) {
					int p = (strstr(buffer, "@") - buffer);
					strcpy(cert, buffer); cert[p] = '\0';
					strcpy(buffer, row[2] + 8 + p + 1);
				} else {
					strcpy(cert, "yiimp");
				}
				coind->rpc.ssl = 1;
				sprintf(coind->rpc.cert, "/usr/share/ca-certificates/%s.crt", cert);
			}
			strcpy(coind->rpc.cert, "");
			strcpy(coind->rpc.host, buffer);
		}

		if(row[3]) coind->rpc.port = atoi(row[3]);

		if(row[4] && row[5])
		{
			char buffer[1024];
			sprintf(buffer, "%s:%s", row[4], row[5]);

			base64_encode(coind->rpc.credential, buffer);
			coind->rpc.coind = coind;
		}

		if(row[8]) coind->reward = atof(row[8]);
		if(row[9]) coind->price = atof(row[9]);
		if(row[11]) coind->txmessage = atoi(row[11]);
		if(row[12]) coind->enable = atoi(row[12]);
		if(row[13]) coind->auto_ready = atoi(row[13]);
		if(row[15]) coind->pool_ttf = atoi(row[15]);

		if(row[16]) strcpy(coind->charity_address, row[16]);
		if(row[17]) coind->charity_amount = atof(row[17]);
		if(row[18]) coind->charity_percent = atof(row[18]);
		if(row[19]) coind->reward_mul = atof(row[19]);

		if(row[21]) coind->isaux = atoi(row[21]);

		if(row[22] && row[23]) coind->actual_ttf = min(atoi(row[22]), atoi(row[23]));
		else if(row[22]) coind->actual_ttf = atoi(row[22]);
		coind->actual_ttf = min(coind->actual_ttf, 120);
		coind->actual_ttf = max(coind->actual_ttf, 20);

		if(row[24]) coind->usememorypool = atoi(row[24]);
		if(row[25]) coind->hasmasternodes = atoi(row[25]);

		if(row[26]) strcpy(coind->algo, row[26]);
		if(row[27]) strcpy(coind->symbol2, row[27]); // if pool + aux, prevent double submit

		if(row[28]) coind->rpc.curl = atoi(row[28]) != 0;
		if(row[29]) coind->rpc.ssl = atoi(row[29]) != 0;
		if(row[30]) strcpy(coind->rpc.cert, row[30]);

		if(row[31]) strcpy(coind->account, row[31]);
		if(row[32]) coind->multialgos = atoi(row[32]);
		if(row[33] && atoi(row[33]) > 0) g_stratum_max_cons = atoi(row[33]);
		if(row[34] && atol(row[34]) > 0) g_max_shares = atol(row[34]);
		if(row[35]) coind->usesegwit = atoi(row[35]) > 0;

		if(coind->usesegwit) g_stratum_segwit = true;

		// force the right rpcencoding for DCR
		if(!strcmp(coind->symbol, "DCR") && strcmp(coind->rpcencoding, "DCR"))
			strcpy(coind->rpcencoding, "DCR");

		// old dash masternodes coins..
		if(coind->hasmasternodes) {
			if (strcmp(coind->symbol, "ALQO") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "BSD") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "BWK") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "CHC") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "CRW") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "DNR") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "FLAX") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "ITZ") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "J") == 0 || strcmp(coind->symbol2, "J") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "MAG") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "PBS") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "URALS") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "VSX") == 0) coind->oldmasternodes = true;
			if (strcmp(coind->symbol, "XLR") == 0) coind->oldmasternodes = true;
		}

		////////////////////////////////////////////////////////////////////////////////////////////////////

		//coind->touch = true;
		if(coind->newcoind)
		{
			debuglog("connecting to coind %s\n", coind->symbol);

			bool b = rpc_connect(&coind->rpc);
			if (!b) {
				debuglog("%s: connect failure\n", coind->symbol);
				object_delete(coind);
				continue;
			}
			coind_init(coind);

			g_list_coind.AddTail(coind);
			usleep(100*YAAMP_MS);
		}
		coind->touch = true;
		coind_create_job(coind);
	}

	mysql_free_result(result);

	for(CLI li = g_list_coind.first; li; li = li->next)
	{
		YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
		if(coind->deleted) continue;

		if(!coind->touch)
		{
			coind_terminate(coind);
			continue;
		}

		coind->touch = false;
	}

	coind_sort();
	g_list_coind.Leave();
}

///////////////////////////////////////////////////////////////////////////////////////////////

void db_update_remotes(YAAMP_DB *db)
{
	if(!db) return;

	db_query(db, "select id, speed/1000000, host, port, username, password, time, price, renterid from jobs where active and ready and algo='%s' order by time", g_stratum_algo);

	MYSQL_RES *result = mysql_store_result(&db->mysql);
	if(!result) yaamp_error("Cant query database");

	MYSQL_ROW row;

	g_list_remote.Enter();
	while((row = mysql_fetch_row(result)) != NULL)
	{
		if(!row[0] || !row[1] || !row[2] || !row[3] || !row[4] || !row[5] || !row[6] || !row[7]) continue;
		bool newremote = false;

		YAAMP_REMOTE *remote = (YAAMP_REMOTE *)object_find(&g_list_remote, atoi(row[0]));
		if(!remote)
		{
			remote = new YAAMP_REMOTE;
			memset(remote, 0, sizeof(YAAMP_REMOTE));

			remote->id = atoi(row[0]);
			newremote = true;
		}

//		else if(remote->reset_balance)
//			continue;

		else if(row[6] && atoi(row[6]) > remote->updated)
			remote->status = YAAMP_REMOTE_RESET;

		remote->speed = atof(row[1]);
		strcpy(remote->host, row[2]);
		remote->port = atoi(row[3]);
		strcpy(remote->username, row[4]);
		strcpy(remote->password, row[5]);
		remote->updated = atoi(row[6]);
		remote->price = atof(row[7]);
		remote->touch = true;
		remote->submit_last = NULL;

		int renterid = row[8]? atoi(row[8]): 0;
		if(renterid && !remote->renter)
			remote->renter = (YAAMP_RENTER *)object_find(&g_list_renter, renterid);

		if(newremote)
		{
			if(remote->renter && remote->renter->balance <= 0.00001000)
			{
				debuglog("dont load that job %d\n", remote->id);
				delete remote;
				continue;
			}

			pthread_t thread;

			pthread_create(&thread, NULL, remote_thread, remote);
			pthread_detach(thread);

			g_list_remote.AddTail(remote);
			usleep(100*YAAMP_MS);
		}

		if(remote->renter)
		{
			if(!strcmp(g_current_algo->name, "sha256"))
				remote->speed = min(remote->speed, max(remote->renter->balance/g_current_algo->rent*100000000, 1));
			else
				remote->speed = min(remote->speed, max(remote->renter->balance/g_current_algo->rent*100000, 1));
		}
	}

	mysql_free_result(result);

	///////////////////////////////////////////////////////////////////////////////////////////

	for(CLI li = g_list_remote.first; li; li = li->next)
	{
		YAAMP_REMOTE *remote = (YAAMP_REMOTE *)li->data;
//		if(remote->reset_balance && remote->renter)
//		{
//			db_query(db, "update renters set balance=0 where id=%d", remote->renter->id);
//			db_query(db, "update jobs set ready=false, active=false where renterid=%d", remote->renter->id);
//
//			remote->reset_balance = false;
//		}

		if(remote->deleted) continue;

		if(remote->kill)
		{
			debuglog("******* kill that sucka %s\n", remote->host);

			pthread_cancel(remote->thread);
			object_delete(remote);

			continue;
		}

		if(remote->sock && remote->sock->last_read && remote->sock->last_read+120<time(NULL))
		{
			debuglog("****** timeout %s\n", remote->host);

			remote->status = YAAMP_REMOTE_TERMINATE;
			remote->kill = true;

			remote_close(remote);
			continue;
		}

		if(!remote->touch)
		{
			remote->status = YAAMP_REMOTE_TERMINATE;
			continue;
		}

		remote->touch = false;

		if(remote->difficulty_written != remote->difficulty_actual)
		{
			remote->difficulty_written = remote->difficulty_actual;
			db_query(db, "update jobs set difficulty=%f where id=%d", remote->difficulty_actual, remote->id);
		}
	}

//	remote_sort();
	g_list_remote.Leave();
}

void db_update_renters(YAAMP_DB *db)
{
	if(!db) return;

	db_query(db, "select id, balance, updated from renters");

	MYSQL_RES *result = mysql_store_result(&db->mysql);
	if(!result) yaamp_error("Cant query database");

	MYSQL_ROW row;
	g_list_renter.Enter();

	while((row = mysql_fetch_row(result)) != NULL)
	{
		if(!row[0] || !row[1]) continue;

		YAAMP_RENTER *renter = (YAAMP_RENTER *)object_find(&g_list_renter, atoi(row[0]));
		if(!renter)
		{
			renter = new YAAMP_RENTER;
			memset(renter, 0, sizeof(YAAMP_RENTER));

			renter->id = atoi(row[0]);
			g_list_renter.AddTail(renter);
		}

		if(row[1]) renter->balance = atof(row[1]);
		if(row[2]) renter->updated = atoi(row[2]);
	}

	mysql_free_result(result);
	g_list_renter.Leave();
}

///////////////////////////////////////////////////////////////////////

static void _json_str_safe(YAAMP_DB *db, json_value *json, const char *key, size_t maxlen, char* out)
{
	json_value *val = json_get_val(json, key);
	out[0] = '\0';
	if (db && val && json_is_string(val)) {
		char str[128] = { 0 };
		char escaped[256] = { 0 };
		snprintf(str, sizeof(str)-1, "%s", json_string_value(val));
		str[maxlen-1] = '\0'; // truncate to dest len
		clean_html(str);
		mysql_real_escape_string(&db->mysql, escaped, str, strlen(str));
		snprintf(out, maxlen, "%s", escaped);
		out[maxlen-1] = '\0';
	}
}
#define json_str_safe(stats, k, out) _json_str_safe(db, stats, k, sizeof(out), out)

static int json_int_safe(json_value *json, const char *key)
{
	json_value *val = json_get_val(json, key);
	return val ? (int) json_integer_value(val) : 0;
}

static double json_double_safe(json_value *json, const char *key)
{
	json_value *val = json_get_val(json, key);
	return val ? json_double_value(val) : 0.;
}

void db_store_stats(YAAMP_DB *db, YAAMP_CLIENT *client, json_value *stats)
{
	int t = time(NULL);
	json_value *algo, *val;
	char sdev[80], stype[8], svid[12], sarch[8];
	char salgo[32], sclient[48], sdriver[32], sos[8];
	double khashes, intensity, throughput;
	int power, freq, memf, realfreq, realmemf, plimit;

	if (!db) return;

	json_str_safe(stats, "algo", salgo);
	if (strcasecmp(g_current_algo->name, salgo) && client->submit_bad) {
	//	debuglog("stats: wrong algo used %s != %s", salgo, g_current_algo->name);
		return;
	}

	json_str_safe(stats, "device", sdev);
	json_str_safe(stats, "type", stype);
	json_str_safe(stats, "vendorid", svid);
	json_str_safe(stats, "arch", sarch); // or cpu best feature
	json_str_safe(stats, "client", sclient);
	json_str_safe(stats, "os", sos);
	json_str_safe(stats, "driver", sdriver); // or cpu compiler

	power = json_int_safe(stats, "power");
	freq  = json_int_safe(stats, "freq");
	memf  = json_int_safe(stats, "memf");
	realfreq = json_int_safe(stats, "curr_freq");
	realmemf = json_int_safe(stats, "curr_memf");
	plimit = json_int_safe(stats, "plimit");
	intensity  = json_double_safe(stats, "intensity");
	khashes    = json_double_safe(stats, "khashes");
	throughput = json_double_safe(stats, "throughput");
	if (throughput < 0.) throughput = 0.;
	if (khashes < 0. || intensity < 0.) return;

	db_query(db, "INSERT INTO benchmarks("
		"time, algo, type, device, arch, vendorid, os, driver,"
		"client, khps, freq, memf, realfreq, realmemf, power, plimit, "
		"intensity, throughput, userid )"
		"VALUES (%d,'%s','%s','%s','%s','%s','%s','%s',"
		"'%s',%f,%d,%d,%d,%d,%d,%d, %.2f,%.0f,%d)",
		t, g_current_algo->name, stype, sdev, sarch, svid, sos, sdriver,
		sclient, khashes, freq, memf, realfreq, realmemf, power, plimit,
		intensity, throughput, client->userid);
}
