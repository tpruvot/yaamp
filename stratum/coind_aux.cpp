
#include "stratum.h"

////////////////////////////////////////////////////////////////////////////////////////////////////

void coind_aux_build_auxs(YAAMP_JOB_TEMPLATE *templ)
{
	int len = 0;
	for(CLI li = g_list_coind.first; li; li = li->next)
	{
		YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
		if(!coind_can_mine(coind, true)) continue;

//		coind_getauxblock(coind);
		len++;
	}

	templ->auxs_size = 0;
	memset(templ->auxs, 0, sizeof(templ->auxs));

	if(!len) return;

	for(int i=0; i<MAX_AUXS; i++)
	{
		templ->auxs_size = pow(2, i);
		if(templ->auxs_size<len) continue;

		bool done = true;
		for(CLI li = g_list_coind.first; li; li = li->next)
		{
			YAAMP_COIND *coind = (YAAMP_COIND *)li->data;
			if(!coind_can_mine(coind, true)) continue;

			int pos = (int)(int64_t)((1103515245 * coind->aux.chainid + 1103515245 * (int64_t)12345 + 12345) % templ->auxs_size);
			if(templ->auxs[pos])
			{
				templ->auxs_size = 0;
				memset(templ->auxs, 0, sizeof(templ->auxs));

				done = false;
				break;
			}

			coind->aux.index = pos;
			templ->auxs[pos] = &coind->aux;
		}

		if(done) break;
	}
}

vector<string> coind_aux_hashlist(YAAMP_COIND_AUX **auxs, int size)
{
	vector<string> hashlist;
	for(int i=0; i<size; i++)
	{
		if(auxs[i])
		{
			char hash_be[1024];
			memset(hash_be, 0, 1024);

			if(auxs[i]->hash != NULL)
			{
				string_be(auxs[i]->hash, hash_be);
				hashlist.push_back(hash_be);
			}
		}
		else
			hashlist.push_back("0000000000000000000000000000000000000000000000000000000000000000");
	}

	return hashlist;
}

vector<string> coind_aux_merkle_branch(YAAMP_COIND_AUX **auxs, int size, int index)
{
	vector<string> hashlist = coind_aux_hashlist(auxs, size);
	vector<string> lresult;

	while(hashlist.size() > 1)
	{
		if(index%2)
			lresult.push_back(hashlist[index-1]);
		else
			lresult.push_back(hashlist[index+1]);

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
		index = index/2;
	}

	return lresult;
}




