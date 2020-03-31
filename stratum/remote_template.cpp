
#include "stratum.h"

void remote_submit(YAAMP_CLIENT *client, YAAMP_JOB *job, YAAMP_JOB_VALUES *submitvalues, char *extranonce2, char *ntime, char *nonce)
{
	YAAMP_REMOTE *remote = job->remote;
	if(!remote) {
		debuglog("job has no remote!\n");
		return;
	}
	if(remote->deleted) return;
	if(remote->status != YAAMP_REMOTE_READY) return;
	if(!remote_connected(remote)) return;

	uint64_t hash_int = get_hash_difficulty(submitvalues->hash_bin);
	uint64_t remote_target = diff_to_target(remote->difficulty_actual);

//	debuglog("%016llx actual\n", hash_int);
//	debuglog("%016llx target diff multiplier=%u\n", remote_target, g_current_algo->diff_multiplier);

	if(hash_int > remote_target) return;
	remote->speed_avg += remote->difficulty_actual / g_current_algo->diff_multiplier * 42;

	if(remote->nonce2size == 2)
		socket_send(remote->sock, "{\"method\":\"mining.submit\",\"params\":[\"%s\",\"%s\",\"%s\",\"%s\",\"%s\"],\"id\":4}\n",
			remote->username, remote->jobid, extranonce2, ntime, nonce);

	else
		socket_send(remote->sock, "{\"method\":\"mining.submit\",\"params\":[\"%s\",\"%s\",\"%02x%s\",\"%s\",\"%s\"],\"id\":4}\n",
			remote->username, remote->jobid, client->extranonce1_id, extranonce2, ntime, nonce);

	remote->submit_last = submit_add(remote->id, remote->difficulty_actual);

//	if(remote->renter)
//	{
//		double increment = g_current_algo->rent * remote->difficulty_actual / 20116.56761169;
//		remote->renter->balance -= increment;
//
//		if(remote->renter->balance-increment <= 0.00001000)
//		{
//			debuglog("balance %.8f %.8f\n", remote->renter->balance, increment);
//			debuglog("no more fund, stop remote %d\n", remote->id);
//
//			remote->renter->balance = 0;
//			remote->reset_balance = true;
//			remote->status = YAAMP_REMOTE_TERMINATE;
//
//			job_signal();
//		}
//	}
}

/////////////////////////////////////////////////////////////////////////////////////////////

void remote_create_job(YAAMP_REMOTE *remote, json_value *json_params)
{
	int jobid_old = remote->job? remote->job->id: 0;
	object_delete(remote->job);

	if(json_params->u.array.length<8) return;

	YAAMP_JOB_TEMPLATE *templ = new YAAMP_JOB_TEMPLATE;
	memset(templ, 0, sizeof(YAAMP_JOB_TEMPLATE));

	strncpy(templ->prevhash_be, json_params->u.array.values[1]->u.string.ptr, sizeof(templ->prevhash_be)-1);
	strncpy(templ->coinb1, json_params->u.array.values[2]->u.string.ptr, 1023);
	strncpy(templ->coinb2, json_params->u.array.values[3]->u.string.ptr, 1023);

	strncpy(templ->version, json_params->u.array.values[5]->u.string.ptr, 16);
	strncpy(templ->nbits, json_params->u.array.values[6]->u.string.ptr, 16);
	strncpy(templ->ntime, json_params->u.array.values[7]->u.string.ptr, 16);

	json_value *json_merkles = json_params->u.array.values[4];

	templ->txmerkles[0] = 0;
	templ->txcount = json_merkles->u.array.length+1;

	for(int i=0; i<json_merkles->u.array.length; i++)
	{
		const char *merkle = json_merkles->u.array.values[i]->u.string.ptr;
		if(i>0) strcat(templ->txmerkles, ",");

		templ->txsteps.push_back(merkle);
		sprintf(templ->txmerkles + strlen(templ->txmerkles), "\"%s\"", merkle);
	}

	templ->height = getblocheight(templ->coinb1);

	remote->job = new YAAMP_JOB;
	memset(remote->job, 0, sizeof(YAAMP_JOB));

	sprintf(remote->job->name, "JOB%d", remote->id);
	if(remote->nonce2size == 2) strcat(remote->job->name, "*");

	remote->job->id = job_get_jobid();
	remote->job->coind = NULL;
	remote->job->remote = remote;
	remote->job->templ = templ;

	if(remote->renter)
		remote->job->profit = g_current_algo->rent;
	else
		remote->job->profit = remote->price;

	remote->job->maxspeed = remote->speed;

	g_list_job.AddTail(remote->job);
	job_relock_clients(jobid_old, remote->job->id);
}






//	bool found = false;
//	for(CLI li = g_list_coind.first; li && li->next; li = li->next)
//	{
//		YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
//		if(coind->deleted) continue;
////		debuglog("coin height %d %d\n", coind->height, templ->height);
//		if(coind->height - 1 < templ->height && coind->height + 3 > templ->height)
//		{
//			found = true;
//			break;
//		}
//	}
//
//	if(!found)
//	{
//		uint64_t coin_target = decode_compact(templ->nbits);
//		double coin_diff = target_to_diff(coin_target);
//
//		stratumlog("unknown coin %s %d diff %f\n", g_stratum_algo, templ->height, coin_diff);
//	}







