
#include "stratum.h"

uint64_t lyra2z_height = 0;

//#define MERKLE_DEBUGLOG
//#define DONTSUBMIT

void build_submit_values(YAAMP_JOB_VALUES *submitvalues, YAAMP_JOB_TEMPLATE *templ,
	const char *nonce1, const char *nonce2, const char *ntime, const char *nonce)
{
	sprintf(submitvalues->coinbase, "%s%s%s%s", templ->coinb1, nonce1, nonce2, templ->coinb2);
	int coinbase_len = strlen(submitvalues->coinbase);

	unsigned char coinbase_bin[1024];
	memset(coinbase_bin, 0, 1024);
	binlify(coinbase_bin, submitvalues->coinbase);

	char doublehash[128];
	memset(doublehash, 0, 128);

	// some (old) wallet/algos need a simple SHA256 (blakecoin, whirlcoin, groestlcoin...)
	YAAMP_HASH_FUNCTION merkle_hash = sha256_double_hash_hex;
	if (g_current_algo->merkle_func)
		merkle_hash = g_current_algo->merkle_func;
	merkle_hash((char *)coinbase_bin, doublehash, coinbase_len/2);

	string merkleroot = merkle_with_first(templ->txsteps, doublehash);
	ser_string_be(merkleroot.c_str(), submitvalues->merkleroot_be, 8);

	if(templ->isbitcash) {
		sprintf(submitvalues->coinbase, "%s%s%s%s", templ->coinforsubmitb1, nonce1, nonce2, templ->coinforsubmitb2);
	}

#ifdef MERKLE_DEBUGLOG
	printf("merkle root %s\n", merkleroot.c_str());
#endif
	if (!strcmp(g_stratum_algo, "lbry")) {
		sprintf(submitvalues->header, "%s%s%s%s%s%s%s", templ->version, templ->prevhash_be, submitvalues->merkleroot_be,
			templ->claim_be, ntime, templ->nbits, nonce);
		ser_string_be(submitvalues->header, submitvalues->header_be, 112/4);
	} else if (strlen(templ->extradata_be) == 128) { // LUX SC
		sprintf(submitvalues->header, "%s%s%s%s%s%s%s", templ->version, templ->prevhash_be, submitvalues->merkleroot_be,
			ntime, templ->nbits, nonce, templ->extradata_be);
		ser_string_be(submitvalues->header, submitvalues->header_be, 36); // 80+64 / sizeof(u32)
	} else if (templ->needpriceinfo)
	{
		sprintf(submitvalues->header, "%s%s%s%s%s%s", templ->version, templ->prevhash_be, submitvalues->merkleroot_be,
			ntime, templ->nbits, nonce);
		ser_string_be(submitvalues->header, submitvalues->header_be, 20);
		sprintf(submitvalues->header_be, "%s%s", submitvalues->header_be, templ->priceinfo);
	} else
	{
		sprintf(submitvalues->header, "%s%s%s%s%s%s", templ->version, templ->prevhash_be, submitvalues->merkleroot_be,
			ntime, templ->nbits, nonce);
		ser_string_be(submitvalues->header, submitvalues->header_be, 20);
	}

	binlify(submitvalues->header_bin, submitvalues->header_be);

//	printf("%s\n", submitvalues->header_be);
	int header_len = strlen(submitvalues->header)/2;
	g_current_algo->hash_function((char *)submitvalues->header_bin, (char *)submitvalues->hash_bin, header_len);

	hexlify(submitvalues->hash_hex, submitvalues->hash_bin, 32);
	string_be(submitvalues->hash_hex, submitvalues->hash_be);
}

/////////////////////////////////////////////

static void create_decred_header(YAAMP_JOB_TEMPLATE *templ, YAAMP_JOB_VALUES *out,
	const char *ntime, const char *nonce, const char *nonce2, const char *vote, bool usegetwork)
{
	struct __attribute__((__packed__)) {
		uint32_t version;
		char prevblock[32];
		char merkleroot[32];
		char stakeroot[32];
		uint16_t votebits;
		char finalstate[6];
		uint16_t voters;
		uint8_t freshstake;
		uint8_t revoc;
		uint32_t poolsize;
		uint32_t nbits;
		uint64_t sbits;
		uint32_t height;
		uint32_t size;
		uint32_t ntime;
		uint32_t nonce;
		unsigned char extra[32];
		uint32_t stakever;
		uint32_t hashtag[3];
	} header;

	memcpy(&header, templ->header, sizeof(header));

	memset(header.extra, 0, 32);
	sscanf(nonce, "%08x", &header.nonce);

	if (strcmp(vote, "")) {
		uint16_t votebits = 0;
		sscanf(vote, "%04hx", &votebits);
		header.votebits = (header.votebits & 1) | (votebits & 0xfffe);
	}

	binlify(header.extra, nonce2);

	hexlify(out->header, (const unsigned char*) &header, 180);
	memcpy(out->header_bin, &header, sizeof(header));
}

static void build_submit_values_decred(YAAMP_JOB_VALUES *submitvalues, YAAMP_JOB_TEMPLATE *templ,
	const char *nonce1, const char *nonce2, const char *ntime, const char *nonce, const char *vote, bool usegetwork)
{
	if (!usegetwork) {
		// not used yet
		char doublehash[128] = { 0 };

		sprintf(submitvalues->coinbase, "%s%s%s%s", templ->coinb1, nonce1, nonce2, templ->coinb2);
		int coinbase_len = strlen(submitvalues->coinbase);

		unsigned char coinbase_bin[1024];
		memset(coinbase_bin, 0, 1024);
		binlify(coinbase_bin, submitvalues->coinbase);

		YAAMP_HASH_FUNCTION merkle_hash = sha256_double_hash_hex;
		if (g_current_algo->merkle_func)
			merkle_hash = g_current_algo->merkle_func;
		merkle_hash((char *)coinbase_bin, doublehash, coinbase_len/2);

		string merkleroot = merkle_with_first(templ->txsteps, doublehash);
		ser_string_be(merkleroot.c_str(), submitvalues->merkleroot_be, 8);

#ifdef MERKLE_DEBUGLOG
		printf("merkle root %s\n", merkleroot.c_str());
#endif
	}
	create_decred_header(templ, submitvalues, ntime, nonce, nonce2, vote, usegetwork);

	int header_len = strlen(submitvalues->header)/2;
	g_current_algo->hash_function((char *)submitvalues->header_bin, (char *)submitvalues->hash_bin, header_len);

	hexlify(submitvalues->hash_hex, submitvalues->hash_bin, 32);
	string_be(submitvalues->hash_hex, submitvalues->hash_be);
}

/////////////////////////////////////////////////////////////////////////////////

static void client_do_submit(YAAMP_CLIENT *client, YAAMP_JOB *job, YAAMP_JOB_VALUES *submitvalues,
	char *extranonce2, char *ntime, char *nonce, char *vote)
{
	YAAMP_COIND *coind = job->coind;
	YAAMP_JOB_TEMPLATE *templ = job->templ;

	if(job->block_found) return;
	if(job->deleted) return;

	uint64_t hash_int = get_hash_difficulty(submitvalues->hash_bin);
	uint64_t coin_target = decode_compact(templ->nbits);
	if (templ->nbits && !coin_target) coin_target = 0xFFFF000000000000ULL;

	int block_size = YAAMP_SMALLBUFSIZE;
	vector<string>::const_iterator i;

	for(i = templ->txdata.begin(); i != templ->txdata.end(); ++i)
		block_size += strlen((*i).c_str());

	char *block_hex = (char *)malloc(block_size);
	if(!block_hex) return;

	// do aux first
	for(int i=0; i<templ->auxs_size; i++)
	{
		if(!templ->auxs[i]) continue;
		YAAMP_COIND *coind_aux = templ->auxs[i]->coind;

		if(!coind_aux || !strcmp(coind->symbol, coind_aux->symbol2))
			continue;

		unsigned char target_aux[1024];
		binlify(target_aux, coind_aux->aux.target);

		uint64_t coin_target_aux = get_hash_difficulty(target_aux);
		if(hash_int <= coin_target_aux)
		{
			memset(block_hex, 0, block_size);

			strcat(block_hex, submitvalues->coinbase);		// parent coinbase
			strcat(block_hex, submitvalues->hash_be);		// parent hash

			////////////////////////////////////////////////// parent merkle steps

			sprintf(block_hex+strlen(block_hex), "%02x", (unsigned char)templ->txsteps.size());

			vector<string>::const_iterator i;
			for(i = templ->txsteps.begin(); i != templ->txsteps.end(); ++i)
				sprintf(block_hex + strlen(block_hex), "%s", (*i).c_str());

			strcat(block_hex, "00000000");

			////////////////////////////////////////////////// auxs merkle steps

			vector<string> lresult = coind_aux_merkle_branch(templ->auxs, templ->auxs_size, coind_aux->aux.index);
			sprintf(block_hex+strlen(block_hex), "%02x", (unsigned char)lresult.size());

			for(i = lresult.begin(); i != lresult.end(); ++i)
				sprintf(block_hex+strlen(block_hex), "%s", (*i).c_str());

			sprintf(block_hex+strlen(block_hex), "%02x000000", (unsigned char)coind_aux->aux.index);

			////////////////////////////////////////////////// parent header

			strcat(block_hex, submitvalues->header_be);

			bool b = coind_submitgetauxblock(coind_aux, coind_aux->aux.hash, block_hex);
			if(b)
			{
				debuglog("*** ACCEPTED %s %d (+1)\n", coind_aux->name, coind_aux->height);

				block_add(client->userid, client->workerid, coind_aux->id, coind_aux->height, target_to_diff(coin_target_aux),
					target_to_diff(hash_int), coind_aux->aux.hash, "", 0);
			}

			else
				debuglog("%s %d REJECTED\n", coind_aux->name, coind_aux->height);
		}
	}

	if(hash_int <= coin_target)
	{
		char count_hex[8] = { 0 };
		if (templ->txcount <= 252)
			sprintf(count_hex, "%02x", templ->txcount & 0xFF);
		else
			sprintf(count_hex, "fd%02x%02x", templ->txcount & 0xFF, templ->txcount >> 8);

		memset(block_hex, 0, block_size);
		sprintf(block_hex, "%s%s%s", submitvalues->header_be, count_hex, submitvalues->coinbase);

		if (g_current_algo->name && !strcmp("jha", g_current_algo->name)) {
			// block header of 88 bytes
			sprintf(block_hex, "%s8400000008000000%s%s", submitvalues->header_be, count_hex, submitvalues->coinbase);
		}

		vector<string>::const_iterator i;
		for(i = templ->txdata.begin(); i != templ->txdata.end(); ++i)
			sprintf(block_hex+strlen(block_hex), "%s", (*i).c_str());

		// POS coins need a zero byte appended to block, the daemon replaces it with the signature
		if(coind->pos)
			strcat(block_hex, "00");

		if(!strcmp("DCR", coind->rpcencoding)) {
			// submit the regenerated block header
			char hex[384];
			hexlify(hex, submitvalues->header_bin, 180);
			if (coind->usegetwork)
				snprintf(block_hex, block_size, "%s8000000100000000000005a0", hex);
			else
				snprintf(block_hex, block_size, "%s", hex);
		}

		bool b = coind_submit(coind, block_hex);
		if(b)
		{
			debuglog("*** ACCEPTED %s %d (diff %g) by %s (id: %d)\n", coind->name, templ->height,
				target_to_diff(hash_int), client->sock->ip, client->userid);

			job->block_found = true;

			char doublehash2[128];
			memset(doublehash2, 0, 128);

			YAAMP_HASH_FUNCTION merkle_hash = sha256_double_hash_hex;
			//if (g_current_algo->merkle_func)
			//	merkle_hash = g_current_algo->merkle_func;

			merkle_hash((char *)submitvalues->header_bin, doublehash2, strlen(submitvalues->header_be)/2);

      // isnt perfect, but it works
      if(strcmp(coind->symbol, "SIN") == 0)
        x22i_hash_hex((char *)submitvalues->header_bin, doublehash2, strlen(submitvalues->header_be)/2);

			char hash1[1024];
			memset(hash1, 0, 1024);

			string_be(doublehash2, hash1);

			if(coind->usegetwork && !strcmp("DCR", coind->rpcencoding)) {
				// no merkle stuff
				strcpy(hash1, submitvalues->hash_hex);
			}

			block_add(client->userid, client->workerid, coind->id, templ->height,
				target_to_diff(coin_target), target_to_diff(hash_int),
				hash1, submitvalues->hash_be, templ->has_segwit_txs);

			if(!strcmp("DCR", coind->rpcencoding)) {
				// delay between dcrd and dcrwallet
				sleep(1);
			}

			if(!strcmp(coind->lastnotifyhash,submitvalues->hash_be)) {
				block_confirm(coind->id, submitvalues->hash_be);
			}

			if (g_debuglog_hash) {
				debuglog("--------------------------------------------------------------\n");
				debuglog("hash1 %s\n", hash1);
				debuglog("hash2 %s\n", submitvalues->hash_be);
			}
		}

		else {
			debuglog("*** REJECTED :( %s block %d %d txs\n", coind->name, templ->height, templ->txcount);
			rejectlog("REJECTED %s block %d\n", coind->symbol, templ->height);
			if (g_debuglog_hash) {
				//debuglog("block %s\n", block_hex);
				debuglog("--------------------------------------------------------------\n");
			}
		}
	}

	free(block_hex);
}

bool dump_submit_debug(const char *title, YAAMP_CLIENT *client, YAAMP_JOB *job, char *extranonce2, char *ntime, char *nonce)
{
	debuglog("ERROR %s, %s subs %d, job %x, %s, id %x, %d, %s, %s %s\n",
		title, client->sock->ip, client->extranonce_subscribe, job? job->id: 0, client->extranonce1,
		client->extranonce1_id, client->extranonce2size, extranonce2, ntime, nonce);
}

void client_submit_error(YAAMP_CLIENT *client, YAAMP_JOB *job, int id, const char *message, char *extranonce2, char *ntime, char *nonce)
{
//	if(job->templ->created+2 > time(NULL))
	if(job && job->deleted)
		client_send_result(client, "true");

	else
	{
		client_send_error(client, id, message);
		share_add(client, job, false, extranonce2, ntime, nonce, 0, id);

		client->submit_bad++;
		if (g_debuglog_hash) {
			dump_submit_debug(message, client, job, extranonce2, ntime, nonce);
		}
	}

	object_unlock(job);
}

static bool valid_string_params(json_value *json_params)
{
	for(int p=0; p < json_params->u.array.length; p++) {
		if (!json_is_string(json_params->u.array.values[p]))
			return false;
	}
	return true;
}

bool client_submit(YAAMP_CLIENT *client, json_value *json_params)
{
	// submit(worker_name, jobid, extranonce2, ntime, nonce):
	if(json_params->u.array.length<5 || !valid_string_params(json_params)) {
		debuglog("%s - %s bad message\n", client->username, client->sock->ip);
		client->submit_bad++;
		return false;
	}

	char extranonce2[32] = { 0 };
	char extra[160] = { 0 };
	char nonce[80] = { 0 };
	char ntime[32] = { 0 };
	char vote[8] = { 0 };

	if (strlen(json_params->u.array.values[1]->u.string.ptr) > 32) {
		clientlog(client, "bad json, wrong jobid len");
		client->submit_bad++;
		return false;
	}
	int jobid = htoi(json_params->u.array.values[1]->u.string.ptr);

	strncpy(extranonce2, json_params->u.array.values[2]->u.string.ptr, 31);
	strncpy(ntime, json_params->u.array.values[3]->u.string.ptr, 31);
	strncpy(nonce, json_params->u.array.values[4]->u.string.ptr, 31);

	string_lower(extranonce2);
	string_lower(ntime);
	string_lower(nonce);

	if (json_params->u.array.length == 6) {
		if (strstr(g_stratum_algo, "phi")) {
			// lux optional field, smart contral root hashes (not mandatory on shares submit)
			strncpy(extra, json_params->u.array.values[5]->u.string.ptr, 128);
			string_lower(extra);
		} else {
			// heavycoin vote
			strncpy(vote, json_params->u.array.values[5]->u.string.ptr, 7);
			string_lower(vote);
		}
	}

	if (g_debuglog_hash) {
		debuglog("submit %s (uid %d) %d, %s, t=%s, n=%s, extra=%s\n", client->sock->ip, client->userid,
			jobid, extranonce2, ntime, nonce, extra);
	}

	YAAMP_JOB *job = (YAAMP_JOB *)object_find(&g_list_job, jobid, true);
	if(!job)
	{
		client_submit_error(client, NULL, 21, "Invalid job id", extranonce2, ntime, nonce);
		return true;
	}

	if(job->deleted)
	{
		client_send_result(client, "true");
		object_unlock(job);

		return true;
	}

	bool is_decred = job->coind && !strcmp("DCR", job->coind->rpcencoding);

	YAAMP_JOB_TEMPLATE *templ = job->templ;

	if(strlen(nonce) != YAAMP_NONCE_SIZE*2 || !ishexa(nonce, YAAMP_NONCE_SIZE*2)) {
		client_submit_error(client, job, 20, "Invalid nonce size", extranonce2, ntime, nonce);
		return true;
	}

	if(strcmp(ntime, templ->ntime))
	{
		if (!ishexa(ntime, 8)) {
			client_submit_error(client, job, 23, "Invalid ntime", extranonce2, ntime, nonce);
			return true;
		}
		// dont allow algos permutations change over time (can lead to different speeds)
		if (!g_allow_rolltime) {
			client_submit_error(client, job, 23, "Invalid ntime (rolling not allowed)", extranonce2, ntime, nonce);
			return true;
		}
	}

	YAAMP_SHARE *share = share_find(job->id, extranonce2, ntime, nonce, client->extranonce1);
	if(share)
	{
		client_submit_error(client, job, 22, "Duplicate share", extranonce2, ntime, nonce);
		return true;
	}

	if(strlen(extranonce2) != client->extranonce2size*2)
	{
		client_submit_error(client, job, 24, "Invalid extranonce2 size", extranonce2, ntime, nonce);
		return true;
	}

	// check if the submitted extranonce is valid
	if(is_decred && client->extranonce2size > 4) {
		char extra1_id[16], extra2_id[16];
		int cmpoft = client->extranonce2size*2 - 8;
		strcpy(extra1_id, &client->extranonce1[cmpoft]);
		strcpy(extra2_id, &extranonce2[cmpoft]);
		int extradiff = (int) strcmp(extra2_id, extra1_id);
		int extranull = (int) !strcmp(extra2_id, "00000000");
		if (extranull && client->extranonce2size > 8)
			extranull = (int) !strcmp(&extranonce2[8], "00000000" "00000000");
		if (extranull) {
			debuglog("extranonce %s is empty!, should be %s - %s\n", extranonce2, extra1_id, client->sock->ip);
			client_submit_error(client, job, 27, "Invalid extranonce2 suffix", extranonce2, ntime, nonce);
			return true;
		}
		if (extradiff) {
			// some ccminer pre-release doesn't fill correctly the extranonce
			client_submit_error(client, job, 27, "Invalid extranonce2 suffix", extranonce2, ntime, nonce);
			socket_send(client->sock, "{\"id\":null,\"method\":\"mining.set_extranonce\",\"params\":[\"%s\",%d]}\n",
				client->extranonce1, client->extranonce2size);
			return true;
		}
	}
	else if(!ishexa(extranonce2, client->extranonce2size*2)) {
		client_submit_error(client, job, 27, "Invalid nonce2", extranonce2, ntime, nonce);
		return true;
	}

	///////////////////////////////////////////////////////////////////////////////////////////

	YAAMP_JOB_VALUES submitvalues;
	memset(&submitvalues, 0, sizeof(submitvalues));

	if(is_decred)
		build_submit_values_decred(&submitvalues, templ, client->extranonce1, extranonce2, ntime, nonce, vote, true);
	else
		build_submit_values(&submitvalues, templ, client->extranonce1, extranonce2, ntime, nonce);

	if (templ->height && !strcmp(g_current_algo->name,"lyra2z")) {
		lyra2z_height = templ->height;
	}

        uint64_t hash_int = * (uint64_t *) &submitvalues.hash_bin[24];
        uint64_t user_target = share_to_target(client->difficulty_actual) * g_current_algo->diff_multiplier;
        uint64_t coin_target = decode_compact(templ->nbits) / 0x10000;

if (g_debuglog_hash) {
        debuglog("hash %016lx \n", hash_int);
        debuglog("shar %016lx \n", user_target);
        debuglog("coin %016lx \n", coin_target);
}

	if(hash_int > user_target)
	{
		client_submit_error(client, job, 26, "Low difficulty share", extranonce2, ntime, nonce);
		return true;
	}

	if(job->coind)
		client_do_submit(client, job, &submitvalues, extranonce2, ntime, nonce, vote);
	else
		remote_submit(client, job, &submitvalues, extranonce2, ntime, nonce);

	client_send_result(client, "true");
	client_record_difficulty(client);
	client->submit_bad = 0;
	client->shares++;
	if (client->shares <= 200 && (client->shares % 50) == 0) {
		// 4 records are enough per miner
		if (!client_ask_stats(client)) client->stats = false;
	}

	double share_diff = diff_to_target(hash_int);
//	if (g_current_algo->diff_multiplier != 0) {
//		share_diff = share_diff / g_current_algo->diff_multiplier;
//	}

	if (g_debuglog_hash) {
		// only log a few...
		if (share_diff > (client->difficulty_actual * 16))
			debuglog("submit %s (uid %d) %d, %s, %s, %s, %.3f/%.3f\n", client->sock->ip, client->userid,
				jobid, extranonce2, ntime, nonce, share_diff, client->difficulty_actual);
	}

	share_add(client, job, true, extranonce2, ntime, nonce, share_diff, 0);
	object_unlock(job);

	return true;
}
