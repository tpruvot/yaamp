
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

static void p2sh_pack_tx(YAAMP_COIND *coind, char *data, json_int_t amount, const char *payee)
{
	char evalue[32];
	char coinb2_part[256];
	char coinb2_len[4];
	sprintf(coinb2_part, "a9%02x%s87", (unsigned int)(strlen(payee) >> 1) & 0xFF, payee);
	sprintf(coinb2_len, "%02x", (unsigned int)(strlen(coinb2_part) >> 1) & 0xFF);
	encode_tx_value(evalue, amount);
	strcat(data, evalue);
	strcat(data, coinb2_len);
	strcat(data, coinb2_part);
}

static void job_pack_tx(YAAMP_COIND *coind, char *data, json_int_t amount, char *key)
{
	int ol = strlen(data);
	char evalue[32];

	if(coind->p2sh_address && !key) {
		p2sh_pack_tx(coind, data, amount, coind->script_pubkey);
		return;
	}

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
	templ->isbitcash = false;
	if(strcmp(coind->symbol, "BITC") == 0) {
		char *params = (char *)malloc(4096);
		if (params) {
			unsigned char price_bin[1024];
			unsigned char pricehash_bin[1024];
			char pricehash_hex[1024];
			char pricehash_be[1024];
			
			if (templ->needpriceinfo && strlen(templ->priceinfo) > 0 && strlen(templ->priceinfo) < 1000) {
				binlify(price_bin, templ->priceinfo);
			
				int price_len = strlen(templ->priceinfo)/2;
				sha256_double_hash((char *)price_bin, (char *)pricehash_bin, price_len);

				hexlify(pricehash_hex, pricehash_bin, 32);
				string_be(pricehash_hex, pricehash_be);
			
				sprintf(params, "[\"%s\", %i, \"%s\"]", coind->wallet, templ->height, pricehash_be);
			} else {
				sprintf(params, "[\"%s\", %i]", coind->wallet, templ->height);
			}
			//std::cout << "Params:" << params << std::endl;
			json_value *json = rpc_call(&coind->rpc, "createcoinbaseforaddress", params);

			free(params);
			if (json) {
				json_value *json_result = json_get_object(json, "result");
				if (json_result) {
					sprintf(templ->coinb1, "%s", json_get_string(json_result, "coinbaseforhashpart1"));			
					templ->coinb1[strlen(templ->coinb1) - 16] = '\0';
					sprintf(templ->coinb2, "%s", json_get_string(json_result, "coinbaseforhashpart2"));			
					
					sprintf(templ->coinforsubmitb1, "%s", json_get_string(json_result, "coinbasepart1"));
					templ->coinforsubmitb1[strlen(templ->coinforsubmitb1) - 16] = '\0';
					sprintf(templ->coinforsubmitb2, "%s", json_get_string(json_result, "coinbasepart2"));
					templ->isbitcash = true;
				}
			}
		}
		return;
	}
	
	char eheight[32], etime[32];
	char entime[32] = { 0 };
	char commitment[128] = { 0 };

	ser_number(templ->height, eheight);
	ser_number(time(NULL), etime);
	if(coind->pos) ser_string_be(templ->ntime, entime, 1);

	char eversion1[32] = "01000000";
	if(coind->txmessage)
		strcpy(eversion1, "02000000");

	const char *coinbase_payload = json_get_string(json_result, "coinbase_payload");
	if (coinbase_payload && strlen(coinbase_payload) > 0) {
		strcpy(eversion1, "03000500");
	}

	char script1[4*1024];
	sprintf(script1, "%s%s%s08", eheight, templ->flags, etime);

	char script2[32] = "746865706f6f6c2e6c6966655c30"; // "thepool.life\0" in hex ascii

	if(!coind->pos && !coind->isaux && templ->auxs_size)
		coinbase_aux(templ, script2);

	int script_len = strlen(script1)/2 + strlen(script2)/2 + 8;
	sprintf(templ->coinb1, "%s%s01"
		"0000000000000000000000000000000000000000000000000000000000000000"
		"ffffffff%02x%s", eversion1, entime, script_len, script1);

	sprintf(templ->coinb2, "%s00000000", script2);

	// segwit commitment, if needed
	if (templ->has_segwit_txs)
		sprintf(commitment, "0000000000000000%02x%s", (int) (strlen(coind->commitment)/2), coind->commitment);

	json_int_t available = templ->value;

	// sample coins using mandatory dev/foundation fees
	if(strcmp(coind->symbol, "EGC") == 0) {
		if (coind->charity_percent <= 0)
			coind->charity_percent = 2;
		if (strlen(coind->charity_address) == 0)
			sprintf(coind->charity_address, "EdFwYw4Mo2Zq6CFM2yNJgXvE2DTJxgdBRX");
	}

        if(strcmp(coind->symbol, "SIN") == 0) {

               // these just get reused
               int npayees = 1;
               char payees[2];
               char sinpayee[256] = {0};
               char sinscript[1024] = {0};
               char devpayee[256] = {0};
               char devscript[1024] = {0};
               const char *devpayaddr = json_get_string(json_result, "payee");
               json_int_t devfee_amount = json_get_int(json_result, "payee_amount");

               // prepare devpay script
               snprintf(devpayee, 255, "%s", devpayaddr);
               base58_decode(devpayee, devscript);
               npayees++;
               available -= devfee_amount;

               // masternode packs
               const char* mnpayaddrs[7] = {0};
               json_value* masternodes = json_get_array(json_result, "masternode");
               json_int_t mnamounts[7] = {0};
               for(int i = 0; i < masternodes->u.array.length; i++) {
                       mnpayaddrs[i] = json_get_string(masternodes->u.array.values[i], "payee");
                       mnamounts[i] = json_get_int(masternodes->u.array.values[i], "amount");
                       available -= mnamounts[i];
                       npayees++;
               }

               sprintf(payees, "%02x", npayees);
               strcat(templ->coinb2, payees);
               job_pack_tx(coind, templ->coinb2, available, NULL);
               job_pack_tx(coind, templ->coinb2, devfee_amount, devscript);
               for(int i = 0; i < masternodes->u.array.length; i++) {
                       snprintf(sinpayee, 255, "%s", mnpayaddrs[i]);
                       base58_decode(sinpayee, sinscript);
                       job_pack_tx(coind, templ->coinb2, mnamounts[i], sinscript);
               }

               // sequence
               strcat(templ->coinb2, "00000000");
               coind->reward = (double)available/100000000;
               return;
        }

	else if(strcmp(coind->symbol, "DYN") == 0)
	{
		char script_dests[2048] = { 0 };
		char script_payee[128] = { 0 };
		char payees[3];
		int npayees = (templ->has_segwit_txs) ? 2 : 1;
		bool dynode_enabled;
		dynode_enabled = json_get_bool(json_result, "dynode_payments_enforced");
		bool superblocks_enabled = json_get_bool(json_result, "superblocks_enabled");
		json_value* superblock = json_get_array(json_result, "superblock");
		json_value* dynode;
		dynode = json_get_object(json_result, "dynode");
		if(!dynode && json_get_bool(json_result, "dynode_payments")) {
			coind->oldmasternodes = true;
			debuglog("%s is using old dynodes rpc keys\n", coind->symbol);
			return;
		}

		if(superblocks_enabled && superblock) {
			for(int i = 0; i < superblock->u.array.length; i++) {
				const char *payee = json_get_string(superblock->u.array.values[i], "payee");
				json_int_t amount = json_get_int(superblock->u.array.values[i], "amount");
				if (payee && amount) {
					npayees++;
					available -= amount;
					base58_decode(payee, script_payee);
					job_pack_tx(coind, script_dests, amount, script_payee);
					//debuglog("%s superblock found %s %u\n", coind->symbol, payee, amount);
				}
			}
		}
		if (dynode_enabled && dynode) {
			bool started;
			started = json_get_bool(json_result, "dynode_payments_started");
			const char *payee = json_get_string(dynode, "payee");
			json_int_t amount = json_get_int(dynode, "amount");
			if (!payee)
				debuglog("coinbase_create failed to get Dynode payee\n");

			if (!amount)
				debuglog("coinbase_create failed to get Dynode amount\n");

			if (!started)
				debuglog("coinbase_create failed to get Dynode started\n");

			if (payee && amount && started) {
				npayees++;
				available -= amount;
				base58_decode(payee, script_payee);
				job_pack_tx(coind, script_dests, amount, script_payee);
				//debuglog("%s dynode found %s %u\n", coind->symbol, payee, amount);
			}
		}
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		if (templ->has_segwit_txs) strcat(templ->coinb2, commitment);
		strcat(templ->coinb2, script_dests);
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("%s %d dests %s\n", coind->symbol, npayees, script_dests);
		return;
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
	else if(strcmp(coind->symbol, "STAK") == 0) {
		char script_payee[512] = { 0 };
		char payees[4];
		int npayees = (templ->has_segwit_txs) ? 2 : 1;
		bool masternode_payments = json_get_bool(json_result, "masternode_payments");
		bool masternodes_enabled = json_get_bool(json_result, "enforce_masternode_payments");

		if (masternodes_enabled && masternode_payments) {
			const char *payee = json_get_string(json_result, "payee");
			json_int_t amount = json_get_int(json_result, "payee_amount");
			if (payee && amount)
				++npayees;
		}

		//treasury 5% @ 10 STAK per block
		json_int_t charity_amount = 50000000;
		//testnet
		//sprintf(coind->charity_address, "93ASJtDuVYVdKXemH9BrtSMscznvsp9stD");
		switch (templ->height % 4) {
			case 0: sprintf(coind->charity_address, "3K3bPrW5h7DYEMp2RcXawTCXajcm4ZU9Zh");
			break;
			case 1: sprintf(coind->charity_address, "33Ssxmn3ehVMgyxgegXhpLGSBpubPjLZQ6");
			break;
			case 2: sprintf(coind->charity_address, "3HFPNAjesiBY5sSVUmuBFnMEGut69R49ca");
			break;
			case 3: sprintf(coind->charity_address, "37jLjjfUXQU4bdqVzvpUXyzAqPQSmxyByi");
			break;
		}
		++npayees;
		available -= charity_amount;
		base58_decode(coind->charity_address, script_payee);
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		if (templ->has_segwit_txs) strcat(templ->coinb2, commitment);
		char echarity_amount[32];
		encode_tx_value(echarity_amount, charity_amount);
		strcat(templ->coinb2, echarity_amount);
		char coinb2_part[1024] = { 0 };
		char coinb2_len[3] = { 0 };
		sprintf(coinb2_part, "a9%02x%s87", (unsigned int)(strlen(script_payee) >> 1) & 0xFF, script_payee);
		sprintf(coinb2_len, "%02x", (unsigned int)(strlen(coinb2_part) >> 1) & 0xFF);
		strcat(templ->coinb2, coinb2_len);
		strcat(templ->coinb2, coinb2_part);
		if (masternodes_enabled && masternode_payments) {
			//duplicated: revisit ++todo
			const char *payee = json_get_string(json_result, "payee");
			json_int_t amount = json_get_int(json_result, "payee_amount");
			if (payee && amount) {
				available -= amount;
				base58_decode(payee, script_payee);
				job_pack_tx(coind, templ->coinb2, amount, script_payee);
			}
		}
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime

		coind->reward = (double)available / 100000000 * coind->reward_mul;
		return;
	}
	else if(strcmp(coind->symbol, "TUX") == 0)  {
		char script_payee[1024];
		char charity_payee[256] = { 0 };
		const char *payee = json_get_string(json_result, "donation_payee");
		if(payee != NULL){
			sprintf(coind->charity_address, "%s", payee);
		} else {
			sprintf(coind->charity_address, "%s", "");
		}

		if(strlen(coind->charity_address) > 0){
			char script_payee[1024];
			char charity_payee[256] = { 0 };
			sprintf(charity_payee, "%s", coind->charity_address);
			if (strlen(charity_payee) == 0)
				stratumlog("ERROR %s has no charity_address set!\n", coind->name);

			base58_decode(charity_payee, script_payee);

			json_int_t charity_amount = json_get_int(json_result, "donation_amount");
			coind->charity_amount = charity_amount;

			if (templ->has_segwit_txs) {
				strcat(templ->coinb2, "03"); // 3 outputs (nulldata + fees + miner)
				strcat(templ->coinb2, commitment);
			} else {
				strcat(templ->coinb2, "02");
			}
			job_pack_tx(coind, templ->coinb2, available, NULL);

			char echarity_amount[32];
			encode_tx_value(echarity_amount, charity_amount);
			strcat(templ->coinb2, echarity_amount);
			char coinb2_part[1024] = { 0 };
			char coinb2_len[3] = { 0 };
			sprintf(coinb2_part, "a9%02x%s87", (unsigned int)(strlen(script_payee) >> 1) & 0xFF, script_payee);
			sprintf(coinb2_len, "%02x", (unsigned int)(strlen(coinb2_part) >> 1) & 0xFF);
			strcat(templ->coinb2, coinb2_len);
			strcat(templ->coinb2, coinb2_part);
			debuglog("pack tx %s\n", coinb2_part);
			strcat(templ->coinb2, "00000000"); // locktime

			coind->reward = (double)available/100000000*coind->reward_mul;
			//debuglog("INFO %s block available %f, charity %f miner %f\n", coind->symbol,
			//	(double) available/1e8, (double) charity_amount/1e8, coind->reward);
			return;
		}
	}

	bool founder_enabled = json_get_bool(json_result, "founder_payments_started");
	json_value* founder = json_get_object(json_result, "founder");

	if (!coind->hasmasternodes && founder_enabled && founder) {
		char founder_payee[256] = { 0 };
		char founder_script[1024] = { 0};
		const char *payee = json_get_string(founder, "payee");
		bool founder_use_p2sh = (strcmp(coind->symbol, "PGN") == 0);
		json_int_t amount = json_get_int(founder, "amount");
		if(payee && amount) {
			if (payee) snprintf(founder_payee, 255, "%s", payee);
			if (strlen(founder_payee) == 0)
				stratumlog("ERROR %s has no charity_address set!\n", coind->name);
			base58_decode(founder_payee, founder_script);
			available -= amount;

			if (templ->has_segwit_txs) {
				strcat(templ->coinb2, "03"); // 3 outputs (nulldata + fees + miner)
				strcat(templ->coinb2, commitment);
			} else {
				strcat(templ->coinb2, "02");
			}
			job_pack_tx(coind, templ->coinb2, available, NULL);
			if(founder_use_p2sh) {
				p2sh_pack_tx(coind, templ->coinb2, amount, founder_script);
			} else {
				job_pack_tx(coind, templ->coinb2, amount, founder_script);
			}
			strcat(templ->coinb2, "00000000"); // locktime

			coind->reward = (double)available/100000000*coind->reward_mul;
			debuglog("%s founder address %s, amount %lld\n", coind->symbol,founder_payee, amount);
			debuglog("%s founder script %s\n", coind->symbol,founder_script);
			debuglog("%s scripts %s\n", coind->symbol, templ->coinb2);

			return;
		}
	}

	// 2 txs are required on these coins, one for foundation (dev fees)
	if(coind->charity_percent && !coind->hasmasternodes)
	{
		char script_payee[1024];
		char charity_payee[256] = { 0 };
		const char *payee = json_get_string(json_result, "payee");
		if (payee) snprintf(charity_payee, 255, "%s", payee);
		else sprintf(charity_payee, "%s", coind->charity_address);
		if (strlen(charity_payee) == 0)
			stratumlog("ERROR %s has no charity_address set!\n", coind->name);

		base58_decode(charity_payee, script_payee);

		json_int_t charity_amount = json_get_int(json_result, "payee_amount");
		if (charity_amount <= 0)
			charity_amount = (available * coind->charity_percent) / 100;

		available -= charity_amount;
		coind->charity_amount = charity_amount;

		if (templ->has_segwit_txs) {
			strcat(templ->coinb2, "03"); // 3 outputs (nulldata + fees + miner)
			strcat(templ->coinb2, commitment);
		} else {
			strcat(templ->coinb2, "02");
		}
		job_pack_tx(coind, templ->coinb2, available, NULL);
		job_pack_tx(coind, templ->coinb2, charity_amount, script_payee);
		strcat(templ->coinb2, "00000000"); // locktime

		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("INFO %s block available %f, charity %f miner %f\n", coind->symbol,
		//	(double) available/1e8, (double) charity_amount/1e8, coind->reward);
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

	// most recent masternodes rpc (DASH, SIB, MUE, DSR, GBX...)
	if(coind->hasmasternodes && !coind->oldmasternodes)
	{
		char script_dests[2048] = { 0 };
		char script_payee[128] = { 0 };
		char payees[4]; // addresses count
		int npayees = (templ->has_segwit_txs) ? 2 : 1;
		bool masternode_enabled = json_get_bool(json_result, "masternode_payments_enforced");
		bool superblocks_enabled = json_get_bool(json_result, "superblocks_enabled");
		json_value* superblock = json_get_array(json_result, "superblock");
		json_value* masternode = json_get_object(json_result, "masternode");
		if(!masternode && json_get_bool(json_result, "masternode_payments")) {
			coind->oldmasternodes = true;
			debuglog("%s is using old masternodes rpc keys\n", coind->symbol);
			return;
		}
		if(coind->charity_percent) {
            		char charity_payee[256] = { 0 };
            		const char *payee = json_get_string(json_result, "payee");
            		if (payee) snprintf(charity_payee, 255, "%s", payee);
            		else sprintf(charity_payee, "%s", coind->charity_address);
            		if (strlen(charity_payee) == 0)
                		stratumlog("ERROR %s has no charity_address set!\n", coind->name);
            		json_int_t charity_amount = (available * coind->charity_percent) / 100;
            		npayees++;
            		available -= charity_amount;
            		coind->charity_amount = charity_amount;
            		base58_decode(charity_payee, script_payee);
           		job_pack_tx(coind, script_dests, charity_amount, script_payee);
        	}
		// smart contracts balance refund, same format as DASH superblocks
		json_value* screfund = json_get_array(json_result, "screfund");
		if(screfund && screfund->u.array.length) {
			superblocks_enabled = true;
			superblock = screfund;
		}
		if(superblocks_enabled && superblock) {
			for(int i = 0; i < superblock->u.array.length; i++) {
				const char *payee = json_get_string(superblock->u.array.values[i], "payee");
				json_int_t amount = json_get_int(superblock->u.array.values[i], "amount");
				if (payee && amount) {
					npayees++;
					available -= amount;
					base58_decode(payee, script_payee);
					bool superblock_use_p2sh = (strcmp(coind->symbol, "MAC") == 0);
					if(superblock_use_p2sh)
						p2sh_pack_tx(coind, script_dests, amount, script_payee);
					else
						job_pack_tx(coind, script_dests, amount, script_payee);
					//debuglog("%s superblock %s %u\n", coind->symbol, payee, amount);
				}
			}
		}
		if (masternode_enabled && masternode) {
			bool started = json_get_bool(json_result, "masternode_payments_started");
			if (json_is_array(masternode)) {
				for(int i = 0; i < masternode->u.array.length; i++) {
					const char *payee = json_get_string(masternode->u.array.values[i], "payee");
					json_int_t amount = json_get_int(masternode->u.array.values[i], "amount");
					if (payee && amount && started) {
						npayees++;
						available -= amount;
						const char *script = json_get_string(masternode->u.array.values[i], "script");
						if (script) {
							p2sh_pack_tx(coind, script_dests, amount, script);
						} else {
							base58_decode(payee, script_payee);
							job_pack_tx(coind, script_dests, amount, script_payee);
						}
						//debuglog("%s masternode %s %u\n", coind->symbol, payee, amount);
					}
				}
			} else {
				const char *payee = json_get_string(masternode, "payee");
				json_int_t amount = json_get_int(masternode, "amount");
				if (payee && amount && started) {
					npayees++;
					available -= amount;
					base58_decode(payee, script_payee);
					bool masternode_use_p2sh = (strcmp(coind->symbol, "MAC") == 0);
					if(masternode_use_p2sh)
						p2sh_pack_tx(coind, script_dests, amount, script_payee);
					else
						job_pack_tx(coind, script_dests, amount, script_payee);
				}
			}
		}
		sprintf(payees, "%02x", npayees);
		strcat(templ->coinb2, payees);
		if (templ->has_segwit_txs) strcat(templ->coinb2, commitment);
		strcat(templ->coinb2, script_dests);
		job_pack_tx(coind, templ->coinb2, available, NULL);
		strcat(templ->coinb2, "00000000"); // locktime
		coind->reward = (double)available/100000000*coind->reward_mul;
		//debuglog("%s total %u available %u\n", coind->symbol, templ->value, available);
		//debuglog("%s %d dests %s\n", coind->symbol, npayees, script_dests);

		// FIXME: it assumes that coinbase_payload length < 0xFD.
		// Otherwise the full compactSize implementation is necessary.
		if (coinbase_payload && strlen(coinbase_payload) > 0) {
			unsigned int len = (strlen(coinbase_payload) >> 1) & 0xFF;
			if (len < 0xFD) {
				char cb_payload_len[4];
				sprintf(cb_payload_len, "%02x", len);
				strcat(templ->coinb2, cb_payload_len);
				strcat(templ->coinb2, coinbase_payload);
			} else {
				strcat(templ->coinb2, "00");
			}
		}
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


	else if(coind->hasmasternodes && coind->oldmasternodes) /* OLD DASH style */
	{
		char charity_payee[256] = { 0 };
		const char *payee = json_get_string(json_result, "payee");
		if (payee) snprintf(charity_payee, 255, "%s", payee);

		json_int_t charity_amount = json_get_int(json_result, "payee_amount");
		bool charity_payments = json_get_bool(json_result, "masternode_payments");
		bool charity_enforce = json_get_bool(json_result, "enforce_masternode_payments");

		if(strcmp(coind->symbol, "CRW") == 0)
		{
			char script_dests[2048] = { 0 };
			char script_payee[128] = { 0 };
			char payees[4];
			int npayees = 1;
			bool masternodes_enabled = json_get_bool(json_result, "enforce_masternode_payments");
			bool systemnodes_enabled = json_get_bool(json_result, "enforce_systemnode_payments");
			bool systemnodes = json_get_bool(json_result, "systemnodes");
			bool masternodes = json_get_bool(json_result, "masternodes");
			if(systemnodes_enabled && systemnodes) {
				const char *payeeSN = json_get_string(json_result, "payeeSN");
				json_int_t payeeSN_amount = json_get_int(json_result, "payeeSN_amount");
				if (payeeSN && payeeSN_amount) {
					npayees++;
					available -= payeeSN_amount;
					base58_decode(payeeSN, script_payee);
					job_pack_tx(coind, script_dests, payeeSN_amount, script_payee);
					//debuglog("%s systemnode %s %u\n", coind->symbol, payeeSN, payeeSN_amount);
				}
			}
			if (masternodes_enabled && masternodes) {
				const char *payee = json_get_string(json_result, "payee");
				json_int_t amount = json_get_int(json_result, "amount");
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

		if(charity_payments && charity_enforce)
		{
			char script_payee[256] = { 0 };
			base58_decode(charity_payee, script_payee);

			if (templ->has_segwit_txs) {
				strcat(templ->coinb2, "03"); // 3 outputs (nulldata + node + miner)
				strcat(templ->coinb2, commitment);
			} else {
				strcat(templ->coinb2, "02"); // 2 outputs
			}

			job_pack_tx(coind, templ->coinb2, charity_amount, script_payee);
			available -= charity_amount;

		} else {
			strcat(templ->coinb2, "01");
		}
	}

	else if (templ->has_segwit_txs) {
		strcat(templ->coinb2, "02");
		strcat(templ->coinb2, commitment);
	} else {
		strcat(templ->coinb2, "01");
	}

	job_pack_tx(coind, templ->coinb2, available, NULL);

	//if(coind->txmessage)
	//	strcat(templ->coinb2, "00");
	if(strcmp(coind->symbol, "DEM") == 0){if(coind->txmessage){strcat(templ->coinb2, "00");}} //fixes eMark.
    	if(strcmp(coind->symbol, "FLO") == 0){if(coind->txmessage){strcat(templ->coinb2, "00");}} //fixes FlorinCoin

	strcat(templ->coinb2, "00000000"); // locktime

	coind->reward = (double)available/100000000*coind->reward_mul;
//	debuglog("coinbase %f\n", coind->reward);

//	debuglog("coinbase %s: version %s, nbits %s, time %s\n", coind->symbol, templ->version, templ->nbits, templ->ntime);
//	debuglog("coinb1 %s\n", templ->coinb1);
//	debuglog("coinb2 %s\n", templ->coinb2);
}
