
#include "stratum.h"

bool remote_can_mine(YAAMP_REMOTE *remote)
{
	if(!remote) return false;
	if(remote->deleted) return false;
	if(!remote_connected(remote)) return false;
	if(!remote->job) return false;
	if(remote->status != YAAMP_REMOTE_READY) return false;
	if(remote->renter && remote->renter->balance <= 0) return false;

	return true;
}

void remote_sort()
{
	for(CLI li = g_list_remote.first; li && li->next; li = li->next)
	{
		YAAMP_REMOTE *remote1 = (YAAMP_REMOTE *)li->data;
		YAAMP_REMOTE *remote2 = (YAAMP_REMOTE *)li->next->data;

		if(remote2->price > remote1->price)
		{
			g_list_remote.Swap(li, li->next);
			remote_sort();

			return;
		}
	}
}

bool remote_connected(YAAMP_REMOTE *remote)
{
	if(!remote->sock) return false;
	return socket_connected(remote->sock);
}

void remote_close(YAAMP_REMOTE *remote)
{
	if (g_debuglog_remote) {
		debuglog("remote_close JOB%d\n", remote->id);
	}

	remote->difficulty_actual = 0;

	if(remote->status != YAAMP_REMOTE_TERMINATE)
		remote->status = YAAMP_REMOTE_CLOSED;

	object_delete(remote->job);
	remote->job = NULL;

	socket_close(remote->sock);
	remote->sock = NULL;
}

bool remote_connect(YAAMP_REMOTE *remote)
{
//	if(!strcmp(remote->host, "yaamp.com")) return false;
//	if(!strcmp(remote->host, "localhost")) return false;
//	if(client_find_my_ip(remote->host)) return false;

	if(remote_connected(remote))
		remote_close(remote);

	if (g_debuglog_remote) {
		debuglog("connecting to %s:%d JOB%d\n", remote->host, remote->port, remote->id);
	}

	int sock = socket(AF_INET, SOCK_STREAM, 0);
	if(sock <= 0) return false;

	struct hostent *ent = gethostbyname(remote->host);
	if(!ent) return false;

	struct sockaddr_in serv;

	serv.sin_family = AF_INET;
	serv.sin_port = htons(remote->port);

	bcopy((char *)ent->h_addr, (char *)&serv.sin_addr.s_addr, ent->h_length);

	int res = connect(sock, (struct sockaddr*)&serv, sizeof(serv));
	if(res < 0)
	{
		if (g_debuglog_remote) {
			debuglog("cant connect to %s:%d JOB%d\n", remote->host, remote->port, remote->id);
		}
		return false;
	}

//	int flags = fcntl(sock, F_GETFL, 0);
//	fcntl(sock, F_SETFL, flags|O_NONBLOCK);

	remote->status = YAAMP_REMOTE_SUBSCRIBE;
	remote->sock = socket_initialize(sock);
//	remote->updated = time(NULL);

    debuglog("connected to %s:%d JOB%d\n", remote->host, remote->port, remote->id);
    return true;
}

////////////////////////////////////////////////////////////////////////////

void *remote_thread(void *p)
{
	YAAMP_REMOTE *remote = (YAAMP_REMOTE *)p;

	const char message_subscribe[] = "{\"id\":1,\"method\":\"mining.subscribe\",\"params\":[\"stratum-proxy/0.0.2\"]}\n";
	const char message_extranonce[] = "{\"id\":3,\"method\":\"mining.extranonce.subscribe\",\"params\":[]}\n";

	remote_connect(remote);
	while(remote->status != YAAMP_REMOTE_TERMINATE)
	{
		if(!remote_connected(remote))
		{
			debuglog("disconnected from %s:%d JOB%d\n", remote->host, remote->port, remote->id);
			sleep(300);

			if(remote->status == YAAMP_REMOTE_TERMINATE) break;
			remote_connect(remote);

			continue;
		}

		if(remote->status == YAAMP_REMOTE_TERMINATE)
			break;

		else if(remote->status == YAAMP_REMOTE_RESET)
		{
			remote_close(remote);
			job_signal();

			remote_connect(remote);
			continue;
		}

		else if(remote->status == YAAMP_REMOTE_SUBSCRIBE)
			socket_send(remote->sock, message_subscribe);

		else if(remote->status == YAAMP_REMOTE_AUTHORIZE)
		{
			char message_authorize[2*1024];
			sprintf(message_authorize, "{\"id\":2,\"method\":\"mining.authorize\",\"params\":[\"%s\",\"%s\"]}\n",
				remote->username, remote->password);

			socket_send(remote->sock, message_authorize);
		}

		else if(remote->status == YAAMP_REMOTE_EXTRANONCE)
		{
			socket_send(remote->sock, message_extranonce);
			remote->status = YAAMP_REMOTE_READY;
		}

		////////////////////////////////////////////////////////////////

		json_value *json = socket_nextjson(remote->sock);
		if(!json)
		{
			sleep(1);
			remote_close(remote);

			job_signal();
			continue;
		}

		if(remote->status == YAAMP_REMOTE_TERMINATE)
		{
			json_value_free(json);
			break;
		}

		int id = json_get_int(json, "id");
		const char *method = json_get_string(json, "method");

		json_value *json_params = json_get_array(json, "params");
		json_value *json_result = json_get_array(json, "result");

		if(id == 1)
		{
			remote->status = YAAMP_REMOTE_AUTHORIZE;

			strncpy(remote->nonce1_next, json_result->u.array.values[1]->u.string.ptr, 16);
			remote->nonce2size_next = json_result->u.array.values[2]->u.integer;

			if(remote->nonce2size_next < 2)
			{
				debuglog("error nonce2 too small %d\n", remote->nonce2size_next);
				remote_close(remote);
			}
		}

		else if(id == 2)
		{
			if(remote->status == YAAMP_REMOTE_AUTHORIZE)
				remote->status = YAAMP_REMOTE_EXTRANONCE;
		}

		else if(id == 4)
		{
			if(json_result && !json_result->u.boolean)
			{
				if(remote->submit_last) remote->submit_last->valid = false;

//				json_value *json_error = json_get_array(json, "error");
//				if(json_error && json_error->type == json_array && json_error->u.array.length > 1)
//				{
//					debuglog("remote submit error JOB%d %d %s ***\n", remote->id,
//						(int)json_error->u.array.values[0]->u.integer, json_error->u.array.values[1]->u.string.ptr);
//				}
			}
		}

		else if(method)
		{
//			debuglog(" * remote method %s\n", method);
			if(!strcmp(method, "mining.set_difficulty"))
			{
				if(json_params->u.array.values[0]->type == json_double)
					remote->difficulty_next = json_params->u.array.values[0]->u.dbl;

				else if(json_params->u.array.values[0]->type == json_integer)
					remote->difficulty_next = json_params->u.array.values[0]->u.integer;

				else if(json_params->u.array.values[0]->type == json_string)
					remote->difficulty_next = atof(json_params->u.array.values[0]->u.string.ptr);

			//	debuglog("remote difficulty %f\n", remote->difficulty_next);
			}

			else if(!strcmp(method, "mining.set_extranonce"))
			{
				strncpy(remote->nonce1_next, json_params->u.array.values[0]->u.string.ptr, 16);
				remote->nonce2size_next = json_params->u.array.values[1]->u.integer;

				if(remote->nonce2size_next < 2)
				{
					debuglog("error nonce2 too small %d\n", remote->nonce2size_next);
					remote_close(remote);
					job_signal();
				}
			}

			else if(!strcmp(method, "mining.notify"))
			{
				strncpy(remote->jobid, json_params->u.array.values[0]->u.string.ptr, 16);
				string_lower(remote->jobid);

				if(	strcmp(remote->nonce1, remote->nonce1_next) ||
					remote->nonce2size != remote->nonce2size_next ||
					remote->difficulty_actual != remote->difficulty_next)
				{
					strncpy(remote->nonce1, remote->nonce1_next, 16);
					string_lower(remote->nonce1);

					remote->nonce2size = remote->nonce2size_next;
					remote->difficulty_actual = remote->difficulty_next;

					remote_create_job(remote, json_params);
					if(!remote->job) break;

					job_signal();
				}

				else
				{
					remote_create_job(remote, json_params);
					if(!remote->job) break;

					job_assign_locked_clients(remote->job);
					job_broadcast(remote->job);
				}
			}

			else if(!strcmp(method, "client.reconnect"))
			{
				remote_close(remote);
				job_signal();

				remote_connect(remote);
			}
		}

		json_value_free(json);
	}

	debuglog("terminate JOB%d %s:%d\n", remote->id, remote->host, remote->port);
	object_delete(remote);

	job_signal();
	pthread_exit(NULL);
}






