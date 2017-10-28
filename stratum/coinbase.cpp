
// http://www.righto.com/2014/02/bitcoin-mining-hard-way-algorithms.html

// https://en.bitcoin.it/wiki/Merged_mining_specification#Merged_mining_coinbase

#include "stratum.h"

#define TX_VALUE(v, s)	((unsigned int)(v>>s)&0xff)

static void encode_tx_value(char *encoded, json_int_t value)
{
	sprintf(encoded, "%02x%02x%02x%02x%02x%02x%02x%02x",
		TX_VALUE(value, 0), TX_VALUE(value, 8), TX_VALUE(value, 16), TX_VALUE(value, 24),
		TX_VALUE(value, 32), TX_VALUE(value, 40), TX_VALUE(value, 48), TX_VALUE(value, 56));
}

static void job_pack_tx(YAAMP_COIND *coind, char *data, json_int_t amount, char *key)
{
	int ol = strlen(data);
	char evalue[32];
	encode_tx_value(evalue, amount);

	sprintf(data+strlen(data), "%s", evalue);

	if(coind->pos && !key)
		sprintf(data+strlen(data), "2321%sac", coind->pubkey);

	else
		sprintf(data+strlen(data), "1976a914%s88ac", key? key: coind->script_pubkey);

//	debuglog("pack tx %s\n", data+ol);
//	debuglog("pack tx %lld\n", amount);
}

void coinbase_aux(YAAMP_JOB_TEMPLATE *templ, char *aux_script)
{
	vector<string> hashlist = coind_aux_hashlist(templ->auxs, templ->auxs_size);
	while(hashlist.size() > 1)
	{
		vector<string> l;
		for(int i = 0; i < hashlist.size()/2; i++)
		{
			string s = hashlist[i*2] + hashlist[i*2+1];

			char bin[YAAMP_HASHLEN_BIN*2];
			char out[YAAMP_HASHLEN_STR];

			binlify((unsigned char *)bin, s.c_str());
			sha256_double_hash_hex(bin, out, YAAMP_HASHLEN_BIN*2);

			l.push_back(out);
		}

		hashlist = l;
	}

	char merkle_hash[4*1024];
	memset(merkle_hash, 0, 4*1024);
	string_be(hashlist[0].c_str(), merkle_hash);

	sprintf(aux_script+strlen(aux_script), "fabe6d6d%s%02x00000000000000", merkle_hash, templ->auxs_size);
//	debuglog("aux_script is %s\n", aux_script);
}

void coinbase_create(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, json_value *json_result)
{
	char eheight[32], etime[32];
	char entime[32] = { 0 };

	ser_number(templ->height, eheight);
	ser_number(time(NULL), etime);
	if(coind->pos) ser_string_be(templ->ntime, entime, 1);

	char eversion1[32] = "01000000";
	if(coind->txmessage)
		strcpy(eversion1, "02000000");

	char script1[4*1024];
	sprintf(script1, "%s%s%s08", eheight, templ->flags, etime);

	char script2[32] = "7969696d7000"; // "yiimp\0" in hex ascii

	if(!coind->pos && !coind->isaux && templ->auxs_size)
		coinbase_aux(templ, script2);

	int script_len = strlen(script1)/2 + strlen(script2)/2 + 8;
	sprintf(templ->coinb1, "%s%s01"
		"0000000000000000000000000000000000000000000000000000000000000000"
		"ffffffff%02x%s", eversion1, entime, script_len, script1);

	sprintf(templ->coinb2, "%s00000000", script2);

	json_int_t available = templ->value;

	// sample coins using mandatory dev/foundation fees
	if(strcmp(coind->symbol, "EGC") == 0) {
		if (coind->charity_percent <= 0)
			coind->charity_percent = 2;
		if (strlen(coind->charity_address) == 0)
			sprintf(coind->charity_address, "EdFwYw4Mo2Zq6CFM2yNJgXvE2DTJxgdBRX");
	}
	else if(strcmp(coind->symbol, "LTCR") == 0) {
		if (coind->charity_percent <= 0)
			coind->charity_percent = 10;
		if (strlen(coind->charity_address) == 0)
			sprintf(coind->charity_address, "BCDrF1hWdKTmrjXXVFTezPjKBmGigmaXg5");
	}
	else if(strcmp(coind->symbol, "XZC") == 0) {
		char script_payee[1024];
		if (coind->charity_percent <= 0)
			coind->charity_percent = 25; // wrong coinbase 40 instead of 40 + 10 = 50

		json_int_t charity_amount = (available * coind->charity_percent) / 100;

		if (strlen(coind->charity_address) == 0)
			sprintf(coind->charity_address, "aHu897ivzmeFuLNB6956X6gyGeVNHUBRgD");

		strcat(templ->coinb2, "06");
		job_pack_tx(coind, templ->coinb2, available, NULL);
		base58_decode("aCAgTPgtYcA4EysU4UKC86EQd5cTtHtCcr", script_payee);
		job_pack_tx(coind, templ->coinb2, charity_amount/5, script_payee);
		base58_decode(coind->charity_address, script_payee); // may change
		job_pack_tx(coind, templ->coinb2, charity_amount/5, script_payee);
		base58_decode("aQ18FBVFtnueucZKeVg4srhmzbpAeb1KoN", script_payee);
		job_pack_tx(coind, templ->coinb2, charity_amount/5, script_payee);
		base58_decode("a1HwTdCmQV3NspP2QqCGpehoFpi8NY4Zg3", script_payee);
		job_pack_tx(coind, templ->coinb2, charity_amount/5, script_payee);
		base58_decode("a1kCCGddf5pMXSipLVD9hBG2MGGVNaJ15U", script_payee);
		job_pack_tx(coind, templ->coinb2, charity_amount/5, script_payee);
		strcat(templ->coinb2, "00000000"); // locktime

		coind->reward = (double)available/100000000*coind->reward_mul;
		return;
	}
	else if(strcmp("DCR", coind->rpcencoding) == 0) {
		coind->reward_mul = 6;  // coinbase value is wrong, reward_mul should be 6
		coind->charity_percent = 0;
		coind->charity_amount = available;
		available *= coind->reward_mul;
		if (strlen(coind->charity_address) == 0 && !strcmp(coind->symbol, "DCR"))
			sprintf(coind->charity_address, "Dcur2mcGjmENx4DhNqDctW5wJCVyT3Qeqkx");
	}

	// 2 txs are required on these coins, one for foundation (dev fees)
	if(coind->charity_percent)
	{
		char script_payee[1024];
		char charity_payee[256] = { 0 };
		const char *payee = json_get_string(json_result, "payee");
		if (payee) snprintf(charity_payee, 255, "%s", payee);
		else sprintf(charity_payee, "%s", coind->charity_address);
		if (strlen(charity_payee) == 0)
			stratumlog("ERROR %s has no charity_address set!\n", coind->name);

		base58_decode(charity_payee, script_payee);

		json_int_t charity_amount = (available * coind->charity_percent) / 100;
		available -= charity_amount;
		coind->charity_amount = charity_amount;

		strcat(templ->coinb2, "02");
		job_pack_tx(coind, templ->coinb2, available, NULL);
		job_pack_tx(coind, templ->coinb2, charity_amount, script_payee);
		strcat(templ->coinb2, "00000000"); // locktime

		coind->reward = (double)available/100000000*coind->reward_mul;
		return;
	}

	else if(coind->charity_amount && !strcmp("DCR", coind->rpcencoding))
	{
		stratumlog("ERROR %s should not use coinbase (getwork only)!\n", coind->symbol);
		coind->reward = (double)available/100000000;
		return;
	}

	if(strcmp(coind->symbol, "XVC") == 0)
	{
		char charity_payee[256];
		json_value* incentive = json_get_object(json_result, "incentive");
		if (incentive) {
			const char* payee = json_get_string(incentive, "address");
			if (payee) snprintf(charity_payee, 255, "%s", payee);
			else sprintf(charity_payee, "%s", coind->charity_address);

			bool enforced = json_get_bool(incentive, "enforced");
			json_int_t charity_amount = json_get_int(incentive, "amount");
			if (enforced && charity_amount && strlen(charity_payee)) {
				char script_payee[1024];
				base58_decode(charity_payee, script_payee);

				strcat(templ->coinb2, "02");
				job_pack_tx(coind, templ->coinb2, available, NULL);
				job_pack_tx(coind, templ->coinb2, charity_amount, script_payee);
				strcat(templ->coinb2, "00000000"); // locktime

				coind->charity_amount = charity_amount;
				coind->reward = (double)available/100000000*coind->reward_mul;
				//debuglog("XVC coinbase %ld (+%ld incentive to %s)\n",
				//	(long) available, (long) charity_amount, charity_payee);
				return;
			}
		}
	}

	if(strcmp(coind->symbol, "SIB") == 0 ||
		strcmp(coind->symbol, "MUE") == 0 || // MUEcore-x11
		strcmp(coind->symbol, "VIVO") == 0 || // VIVO coin
	   	strcmp(coind->symbol, "INN") == 0 || // Innova coin
	   	strcmp(coind->symbol, "DSR") == 0 || // Desire coin
		strcmp(coind->symbol, "DASH") == 0 || strcmp(coind->symbol, "DASH-TESTNET") == 0) // Dash 12.1
	{
		char script_dests[2048] = { 0 };
		char script_payee[128] = { 0 };
		char payees[4]; // addresses count
		int npayees = 1;
		bool masternode_enabled = json_get_bool(json_result, "masternode_payments_enforced");
		bool superblocks_enabled = json_get_bool(json_result, "superblocks_enabled");
		json_value* superblock = json_get_array(json_result, "superblock");
		json_value* masternode = json_get_object(json_result, "masternode");
		if(superblocks_enabled && superblock) {
			for(int i = 0; i < superblock->u.array.length; i++) {
				const char *payee = json_get_string(superblock->u.array.values[i], "payee");
				json_int_t amount = json_get_int(superblock->u.array.values[i], "amount");
				if (payee && amount) {
					npayees++;
					available -= amount;
					base58_decode(payee, script_payee);
					job_pack_tx(coind, script_dests, amount, script_payee);
					//debuglog("%s superblock %s %u\n", coind->symbol, payee, amount);
				}
			}
		}
		if (masternode_enabled && masternode) {
			const char *payee = json_get_string(masternode, "payee");
			json_int_t amount = json_get_int(masternode, "amount");
			if (payee && amount) {
				npayees++;
				available -= amount;
				base58_decode(payee, script_payee);
				job_pack_tx(coind, script_dests, amount, script_payee);
			}
		}
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		strcat(templ->coinb2, script_dests);
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("%s %d dests %s\n", coind->symbol, npayees, script_dests);
		return;
	}
	
	else if(strcmp(coind->symbol, "ARC") == 0)
	{
		char script_dests[2048] = { 0 };
		char script_payee[128] = { 0 };
		char payees[4];
		int npayees = 1;
		bool masternode_enabled = json_get_bool(json_result, "goldminenode_payments_enforced");
		bool superblocks_enabled = json_get_bool(json_result, "superblocks_enabled");
		json_value* superblock = json_get_array(json_result, "superblock");
		json_value* masternode = json_get_object(json_result, "goldminenode");
		if(superblocks_enabled && superblock) {
			for(int i = 0; i < superblock->u.array.length; i++) {
				const char *payee = json_get_string(superblock->u.array.values[i], "payee");
				json_int_t amount = json_get_int(superblock->u.array.values[i], "amount");
				if (payee && amount) {
					npayees++;
					available -= amount;
					base58_decode(payee, script_payee);
					job_pack_tx(coind, script_dests, amount, script_payee);
					//debuglog("%s superblock %s %u\n", coind->symbol, payee, amount);
				}
			}
		}
		if (masternode_enabled && masternode) {
			const char *payee = json_get_string(masternode, "payee");
			json_int_t amount = json_get_int(masternode, "amount");
			if (payee && amount) {
				npayees++;
				available -= amount;
				base58_decode(payee, script_payee);
				job_pack_tx(coind, script_dests, amount, script_payee);
			}
		}
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		strcat(templ->coinb2, script_dests);
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("%s %d dests %s\n", coind->symbol, npayees, script_dests);
		return;
	}
	
	else if(strcmp(coind->symbol, "ENT") == 0)
	{
		char script_dests[2048] = { 0 };
		char script_payee[128] = { 0 };
		char payees[4];
		int npayees = 1;
		bool masternode_enabled = json_get_bool(json_result, "eternitynode_payments_enforced");
		bool superblocks_enabled = json_get_bool(json_result, "superblocks_enabled");
		json_value* superblock = json_get_array(json_result, "superblock");
		json_value* masternode = json_get_object(json_result, "eternitynode");
		if(superblocks_enabled && superblock) {
			for(int i = 0; i < superblock->u.array.length; i++) {
				const char *payee = json_get_string(superblock->u.array.values[i], "payee");
				json_int_t amount = json_get_int(superblock->u.array.values[i], "amount");
				if (payee && amount) {
					npayees++;
					available -= amount;
					base58_decode(payee, script_payee);
					job_pack_tx(coind, script_dests, amount, script_payee);
					//debuglog("%s superblock %s %u\n", coind->symbol, payee, amount);
				}
			}
		}
		if (masternode_enabled && masternode) {
			const char *payee = json_get_string(masternode, "payee");
			json_int_t amount = json_get_int(masternode, "amount");
			if (payee && amount) {
				npayees++;
				available -= amount;
				base58_decode(payee, script_payee);
				job_pack_tx(coind, script_dests, amount, script_payee);
			}
		}
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		strcat(templ->coinb2, script_dests);
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("%s %d dests %s\n", coind->symbol, npayees, script_dests);
		return;
	}


	else if(coind->hasmasternodes) /* OLD DASH style */
	{
		char charity_payee[256] = { 0 };
		const char *payee = json_get_string(json_result, "payee");
		if (payee) snprintf(charity_payee, 255, "%s", payee);

		json_int_t charity_amount = json_get_int(json_result, "payee_amount");
		bool charity_payments = json_get_bool(json_result, "masternode_payments");
		bool charity_enforce = json_get_bool(json_result, "enforce_masternode_payments");
		if(strcmp(coind->symbol, "CRW") == 0)
		{
			charity_payments = json_get_bool(json_result, "throne_payments");
			charity_enforce = json_get_bool(json_result, "enforce_throne_payments");
		}
		if(charity_payments && charity_enforce)
		{
			available -= charity_amount;

			char script_payee[1024];
			base58_decode(charity_payee, script_payee);

			strcat(templ->coinb2, "02");
			job_pack_tx(coind, templ->coinb2, charity_amount, script_payee);
		}
		else
			strcat(templ->coinb2, "01");
	}

	else
		strcat(templ->coinb2, "01");

	job_pack_tx(coind, templ->coinb2, available, NULL);
	strcat(templ->coinb2, "00000000"); // locktime

	//if(coind->txmessage)
	//	strcat(templ->coinb2, "00");

	coind->reward = (double)available/100000000*coind->reward_mul;
//	debuglog("coinbase %f\n", coind->reward);

//	debuglog("coinbase %s: version %s, nbits %s, time %s\n", coind->symbol, templ->version, templ->nbits, templ->ntime);
//	debuglog("coinb1 %s\n", templ->coinb1);
//	debuglog("coinb2 %s\n", templ->coinb2);
}



