
#include "stratum.h"

bool client_suggest_difficulty(YAAMP_CLIENT *client, json_value *json_params)
{
	if(json_params->u.array.length>0)
	{
		double diff = client_normalize_difficulty(json_params->u.array.values[0]->u.dbl);
		uint64_t user_target = diff_to_target(diff);

		if(user_target >= YAAMP_MINDIFF && user_target <= YAAMP_MAXDIFF)
			client->difficulty_actual = diff;
	}

	client_send_result(client, "true");
	return true;
}

bool client_suggest_target(YAAMP_CLIENT *client, json_value *json_params)
{
	client_send_result(client, "true");
	return true;
}

bool client_subscribe(YAAMP_CLIENT *client, json_value *json_params)
{
	//if(client_find_my_ip(client->sock->ip)) return false;
	get_next_extraonce1(client->extranonce1_default);

	client->extranonce2size_default = YAAMP_EXTRANONCE2_SIZE;
	client->difficulty_actual = g_stratum_difficulty;

	strcpy(client->extranonce1, client->extranonce1_default);
	client->extranonce2size = client->extranonce2size_default;

	// decred uses an extradata field in block header, 2 first uint32 are set by the miner
	if (g_current_algo->name && !strcmp(g_current_algo->name,"decred")) {
		memset(client->extranonce1, '0', sizeof(client->extranonce1));
		memcpy(&client->extranonce1[16], client->extranonce1_default, YAAMP_EXTRANONCE2_SIZE*2);
		client->extranonce1[24] = '\0';
		client->extranonce2size = client->extranonce2size_default = 12;
	}

	get_random_key(client->notify_id);

	if(json_params->u.array.length>0)
	{
		if (json_params->u.array.values[0]->u.string.ptr)
			strncpy(client->version, json_params->u.array.values[0]->u.string.ptr, 1023);

		if (strstr(client->version, "NiceHash"))
      client->difficulty_actual = g_stratum_nicehash_difficulty;

		if(strstr(client->version, "proxy") || strstr(client->version, "/3."))
      client->reconnectable = false;

		if(strstr(client->version, "ccminer")) client->stats = true;
		if(strstr(client->version, "cpuminer-multi")) client->stats = true;
		if(strstr(client->version, "cpuminer-opt")) client->stats = true;
	}

	if(json_params->u.array.length>1)
	{
		char notify_id[1024] = { 0 };
		if (json_params->u.array.values[1]->u.string.ptr)
			strncpy(notify_id, json_params->u.array.values[1]->u.string.ptr, 1023);

		YAAMP_CLIENT *client1 = client_find_notify_id(notify_id, true);
		if(client1)
		{
			strncpy(client->notify_id, notify_id, 1023);

			client->jobid_locked = client1->jobid_locked;
//			client->jobid_next = client1->jobid_next;
			client->difficulty_actual = client1->difficulty_actual;

			client->extranonce2size_default = client1->extranonce2size_default;
			strcpy(client->extranonce1_default, client1->extranonce1_default);

			client->extranonce2size = client1->extranonce2size_reconnect;
			strcpy(client->extranonce1, client1->extranonce1_reconnect);

			client->speed = client1->speed;
			client->extranonce1_id = client1->extranonce1_id;

			client->userid = client1->userid;
			client->workerid = client1->workerid;

			memcpy(client->job_history, client1->job_history, sizeof(client->job_history));
			client1->lock_count = 0;

			if (g_debuglog_client) {
				debuglog("reconnecting client locked to %x\n", client->jobid_next);
			}
		}

		else
		{
			YAAMP_CLIENT *client1 = client_find_notify_id(notify_id, false);
			if(client1)
			{
				strncpy(client->notify_id, notify_id, 1023);

				client->difficulty_actual = client1->difficulty_actual;
				client->speed = client1->speed;

				memcpy(client->job_history, client1->job_history, sizeof(client->job_history));
				client1->lock_count = 0;

				if (g_debuglog_client) {
					debuglog("reconnecting2 client\n");
				}
			}
		}
	}

	strcpy(client->extranonce1_last, client->extranonce1);
	client->extranonce2size_last = client->extranonce2size;

	if (g_debuglog_client) {
		debuglog("new client with nonce %s\n", client->extranonce1);
	}

	client_send_result(client, "[[[\"mining.set_difficulty\",\"%.3g\"],[\"mining.notify\",\"%s\"]],\"%s\",%d]",
		client->difficulty_actual, client->notify_id, client->extranonce1, client->extranonce2size);

	return true;
}

///////////////////////////////////////////////////////////////////////////////////////////
bool client_validate_user_address(YAAMP_CLIENT *client)
{
	int client_workers = 0;
	if (client->userid == 0) {
		client_workers = client_workers_byaddress(client->username);
	} else {
		client_workers = client_workers_count(client);
	}

	// if already logged in this instance, reuse data from other workers (in memory)
	if (client_workers > 1) {
		if (client_workers > 100 && (client_workers%100 == 0)) {
			clientlog(client, "using %d workers", client_workers);
		}
		if (client_auth_by_workers(client)) {
			// client->coinid filled
			return true;
		}
	}

	if (!client->coinid) {
		for(CLI li = g_list_coind.first; li; li = li->next) {
			YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
			// debuglog("user %s testing on coin %s ...\n", client->username, coind->symbol);
			if(!coind_can_mine(coind)) continue;
			if(strlen(g_current_algo->name) && strcmp(g_current_algo->name, coind->algo)) continue;
			if(coind_validate_user_address(coind, client->username)) {
				debuglog("new user %s for coin %s\n", client->username, coind->symbol);
				client->coinid = coind->id;
				// update the db now to prevent addresses conflicts
				CommonLock(&g_db_mutex);
				db_init_user_coinid(g_db, client);
				CommonUnlock(&g_db_mutex);
				return true;
			}
		}
	}

	if (!client->coinid) {
		return false;
	}

	YAAMP_COIND *coind = (YAAMP_COIND *)object_find(&g_list_coind, client->coinid);
	if (!coind) {
		clientlog(client, "unable to find the wallet for coinid %d...", client->coinid);
		return false;
	} else {
		if(g_current_algo && strlen(g_current_algo->name) && strcmp(g_current_algo->name, coind->algo)) {
			clientlog(client, "%s address is on the wrong coin %s, reset to auto...", client->username, coind->symbol);
			client->coinid = 0;
			CommonLock(&g_db_mutex);
			db_init_user_coinid(g_db, client);
			CommonUnlock(&g_db_mutex);
			return false;
		}
	}

	bool isvalid = coind_validate_user_address(coind, client->username);
	if (isvalid) {
		client->coinid = coind->id;
	} else {
		clientlog(client, "unable to verify %s address for user coinid %d...", coind->symbol, client->coinid);
	}
	return isvalid;
}

///////////////////////////////////////////////////////////////////////////////////////////

bool client_authorize(YAAMP_CLIENT *client, json_value *json_params)
{

	if(g_list_client.Find(client)) {
		clientlog(client, "Already logged");
		client_send_error(client, 21, "Already logged");
		return false;
	}

	if(json_params->u.array.length>1 && json_params->u.array.values[1]->u.string.ptr)
		strncpy(client->password, json_params->u.array.values[1]->u.string.ptr, 1023);

	if (g_list_client.count >= g_stratum_max_cons) {
		client_send_error(client, 21, "Server full");
		return false;
	}

	if(json_params->u.array.length>0 && json_params->u.array.values[0]->u.string.ptr)
	{
		strncpy(client->username, json_params->u.array.values[0]->u.string.ptr, 1023);

		db_check_user_input(client->username);
		int len = strlen(client->username);
		if (!len)
			return false;

		char *sep = strpbrk(client->username, ".,;:");
		if (sep) {
			*sep = '\0';
			strncpy(client->worker, sep+1, 1023-len);
			if (strlen(client->username) > MAX_ADDRESS_LEN) return false;
		} else if (len > MAX_ADDRESS_LEN) {
			return false;
		}
	}

	bool reset = client_initialize_multialgo(client);
	if(reset) return false;

	client_initialize_difficulty(client);

	if (g_debuglog_client) {
		debuglog("new client %s, %s, %s\n", client->username, client->password, client->version);
	}

	if(!client->userid || !client->workerid)
	{
		CommonLock(&g_db_mutex);
		db_add_user(g_db, client);

		if(client->userid == -1)
		{
			CommonUnlock(&g_db_mutex);
			client_block_ip(client, "account locked");
			clientlog(client, "account locked");

			return false;
		}

		db_add_worker(g_db, client);
		CommonUnlock(&g_db_mutex);
	}

	client_send_result(client, "true");
	client_send_difficulty(client, client->difficulty_actual);

	if(client->jobid_locked)
		job_send_jobid(client, client->jobid_locked);
	else
		job_send_last(client);

	g_list_client.AddTail(client);
	return true;
}

///////////////////////////////////////////////////////////////////////////////////////////

bool client_update_block(YAAMP_CLIENT *client, json_value *json_params)
{
	// password, id, block hash
	if(json_params->u.array.length < 3 || !json_params->u.array.values[0]->u.string.ptr)
	{
		clientlog(client, "update block, bad params");
		return false;
	}

	if(strcmp(g_tcp_password, json_params->u.array.values[0]->u.string.ptr))
	{
		clientlog(client, "update block, bad password");
		return false;
	}

	int coinid = json_params->u.array.values[1]->u.integer;
	if(!coinid) return false;
	YAAMP_COIND *coind = (YAAMP_COIND *)object_find(&g_list_coind, coinid, true);
	if(!coind) return false;

	const char* hash = json_params->u.array.values[2]->u.string.ptr;

	if (g_debuglog_client) {
		debuglog("notify: new %s block %s\n", coind->symbol, hash);
	}

	snprintf(coind->lastnotifyhash, 161, "%s", hash);

	coind->newblock = true;
	coind->notreportingcounter = 0;

	if (!strcmp("DCR", coind->rpcencoding)) {
		usleep(300*YAAMP_MS);
	}

	block_confirm(coind->id, hash);

	coind_create_job(coind);
	object_unlock(coind);

	if(coind->isaux) for(CLI li = g_list_coind.first; li; li = li->next)
	{
		YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
		if(!coind_can_mine(coind)) continue;
		if(coind->pos) continue;

		coind_create_job(coind);
	}

	job_signal();
	return true;
}

///////////////////////////////////////////////////////////////////////////////////////////

bool client_ask_stats(YAAMP_CLIENT *client)
{
	int id;
	if (!client->stats) return false;
	id = client_ask(client, "client.get_stats", "[]");
	return true;
}

static bool client_store_stats(YAAMP_CLIENT *client, json_value *result)
{
	if (json_typeof(result) != json_object)
		return false;

	json_value *val = json_get_val(result, "type");
	if (val && json_is_string(val)) {
		debuglog("received stats of type %s\n", json_string_value(val));
		//if (!strcmp("gpu", json_string_value(val))) {
			CommonLock(&g_db_mutex);
			db_store_stats(g_db, client, result);
			CommonUnlock(&g_db_mutex);
		//}
		return true;
	}

	return false;
}

///////////////////////////////////////////////////////////////////////////////////////////

int client_workers_count(YAAMP_CLIENT *client)
{
	int count = 0;
	if (!client || client->userid <= 0)
		return count;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *cli = (YAAMP_CLIENT *)li->data;
		if (cli->deleted) continue;
		if (cli->userid == client->userid) count++;
	}
	g_list_client.Leave();

	return count;
}

int client_workers_byaddress(const char *username)
{
	int count = 0;
	if (!username || !strlen(username))
		return count;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *cli = (YAAMP_CLIENT *)li->data;
		if (cli->deleted) continue;
		if (strcmp(cli->username, username) == 0) count++;
	}
	g_list_client.Leave();

	return count;
}

bool client_auth_by_workers(YAAMP_CLIENT *client)
{
	if (!client || client->userid < 0)
		return false;

	g_list_client.Enter();
	for(CLI li = g_list_client.first; li; li = li->next)
	{
		YAAMP_CLIENT *cli = (YAAMP_CLIENT *)li->data;
		if (cli->deleted) continue;
		if (client->userid) {
			if(cli->userid == client->userid) {
				client->coinid = cli->coinid;
				break;
			}
		} else if (strcmp(cli->username, client->username) == 0) {
			client->coinid = cli->coinid;
			client->userid = cli->userid;
			break;
		}
	}
	g_list_client.Leave();

	return (client->coinid > 0 && client->userid > 0);
}

///////////////////////////////////////////////////////////////////////////////////////////

//YAAMP_SOURCE *source_init(YAAMP_CLIENT *client)
//{
//	YAAMP_SOURCE *source = NULL;
//	g_list_source.Enter();
//
//	for(CLI li = g_list_source.first; li; li = li->next)
//	{
//		YAAMP_SOURCE *source1 = (YAAMP_SOURCE *)li->data;
//		if(!strcmp(source1->ip, client->sock->ip))
//		{
//			source = source1;
//			break;
//		}
//	}
//
//	if(!source)
//	{
//		source = new YAAMP_SOURCE;
//		memset(source, 0, sizeof(YAAMP_SOURCE));
//
//		strncpy(source->ip, client->sock->ip, 64);
//		source->speed = 1;
//
//		g_list_source.AddTail(source);
//	}
//
//	source->count++;
//
//	g_list_source.Leave();
//	return source;
//}
//
//void source_close(YAAMP_SOURCE *source)
//{
//	g_list_source.Enter();
//	source->count--;
//
//	if(source->count <= 0)
//	{
//		g_list_source.Delete(source);
//		delete source;
//	}
//
//	g_list_source.Leave();
//}
//
//void source_prune()
//{
////	debuglog("source_prune() %d\n", g_list_source.count);
//	g_list_source.Enter();
//	for(CLI li = g_list_source.first; li; li = li->next)
//	{
//		YAAMP_SOURCE *source = (YAAMP_SOURCE *)li->data;
//		source->speed *= 0.8;
//
//		double idx = source->speed/source->count;
//		if(idx < 0.0005)
//		{
//			stratumlog("disconnect all ip %s, %s, count %d, %f, %f\n", source->ip, g_current_algo->name, source->count, source->speed, idx);
//			for(CLI li = g_list_client.first; li; li = li->next)
//			{
//				YAAMP_CLIENT *client = (YAAMP_CLIENT *)li->data;
//				if(client->deleted) continue;
//				if(!client->workerid) continue;
//
//				if(!strcmp(source->ip, client->sock->ip))
//					shutdown(client->sock->sock, SHUT_RDWR);
//			}
//		}
//
//		else if(source->count > 500)
//			stratumlog("over 500 ip %s, %s, %d, %f, %f\n", source->ip, g_current_algo->name, source->count, source->speed, idx);
//	}
//
//	g_list_source.Leave();
//}

///////////////////////////////////////////////////////////////////////////////////////////

void *client_thread(void *p)
{
	YAAMP_CLIENT *client = new YAAMP_CLIENT;
	if(!client) {
		stratumlog("client_thread OOM");
		pthread_exit(NULL);
		return NULL;
	}
	memset(client, 0, sizeof(YAAMP_CLIENT));

	client->reconnectable = true;
	client->speed = 1;
	client->created = time(NULL);
	client->last_best = time(NULL);

	client->sock = socket_initialize((int)(long)p);
//	client->source = source_init(client);

	client->shares_per_minute = YAAMP_SHAREPERSEC;
	client->last_submit_time = current_timestamp();

//	usleep(g_list_client.count * 5000);

	while(!g_exiting)
	{
		if(client->submit_bad > 1024)
		{
			clientlog(client, "bad submits");
			break;
		}

		json_value *json = socket_nextjson(client->sock, client);
		if(!json)
		{
//			clientlog(client, "bad json");
			break;
		}

		client->id_int = json_get_int(json, "id");
		client->id_str = json_get_string(json, "id");
		if (client->id_str && strlen(client->id_str) > 32) {
			clientlog(client, "bad id");
			break;
		}

		const char *method = json_get_string(json, "method");

		if (!method && client->stats && client->id_int == client->reqid)
		{
			json_value *result = json_get_object(json, "result");
			if (result) client_store_stats(client, result);
			json_value_free(json);
			continue;
		}

		if(!method)
		{
			json_value_free(json);
			clientlog(client, "bad json, no method");
			break;
		}

		json_value *json_params = json_get_array(json, "params");
		if(!json_params)
		{
			json_value_free(json);
			clientlog(client, "bad json, no params");
			break;
		}

		if (g_debuglog_client) {
			debuglog("client %s %d %s\n", method, client->id_int, client->id_str? client->id_str: "null");
		}

		bool b = false;
		if(!strcmp(method, "mining.subscribe"))
			b = client_subscribe(client, json_params);

		else if(!strcmp(method, "mining.authorize"))
			b = client_authorize(client, json_params);

		else if(!strcmp(method, "mining.ping"))
			b = client_send_result(client, "\"pong\"");

		else if(!strcmp(method, "mining.submit"))
			b = client_submit(client, json_params);

		else if(!strcmp(method, "mining.suggest_difficulty"))
			b = client_suggest_difficulty(client, json_params);

		else if(!strcmp(method, "mining.suggest_target"))
			b = client_suggest_target(client, json_params);

		else if(!strcmp(method, "mining.get_transactions"))
			b = client_send_result(client, "[]");

		else if(!strcmp(method, "mining.multi_version"))
			b = client_send_result(client, "false"); // ASICBOOST

		else if(!strcmp(method, "mining.extranonce.subscribe"))
		{
			client->extranonce_subscribe = true;
			b = client_send_result(client, "true");
		}

		else if(!strcmp(method, "mining.update_block"))
			client_update_block(client, json_params);

		else if(!strcmp(method, "getwork"))
		{
			clientlog(client, "using getwork"); // client using http:// url
		}
		else
		{
			b = client_send_error(client, 20, "Not supported");
			client->submit_bad++;

			stratumlog("unknown method %s %s\n", method, client->sock->ip);
		}

		json_value_free(json);
		if(!b) break;
	}

//	source_close(client->source);

	if (g_debuglog_client) {
		debuglog("client terminate\n");
	}
	if(!client) {
		pthread_exit(NULL);
	}

	else if(client->sock->total_read == 0)
		clientlog(client, "no data");

	if(client->sock->sock >= 0)
		shutdown(client->sock->sock, SHUT_RDWR);

	if(g_list_client.Find(client))
	{
		if(client->workerid && !client->reconnecting)
		{
			CommonLock(&g_db_mutex);
			db_clear_worker(g_db, client);
			CommonUnlock(&g_db_mutex);
		}
		object_delete(client);
	} else {
		// only clients sockets in g_list_client are purged (if marked deleted)
		socket_close(client->sock);
		delete client;
	}

	pthread_exit(NULL);
}
