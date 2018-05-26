
#include "stratum.h"

vector<string> merkle_steps(vector<string> input)
{
	vector<string> L = input;
	vector<string> steps;
	vector<string> PreL;
	PreL.push_back("");

	int Ll = L.size();
	while(Ll > 1)
	{
		steps.push_back(L[1]);

		if(Ll % 2)
			L.push_back(L[L.size() - 1]);

		vector<string> Ld;
		for(int i = 1; i < L.size()/2; i++)
		{
			string s = L[i*2] + L[i*2+1];

			char bin[YAAMP_HASHLEN_BIN*2];
			char out[YAAMP_HASHLEN_STR];

			binlify((unsigned char *)bin, s.c_str());
			sha256_double_hash_hex(bin, out, YAAMP_HASHLEN_BIN*2);

			Ld.push_back(out);
		}

		L = PreL;
		L.insert(L.end(), Ld.begin(), Ld.end());

		Ll = L.size();
	}

	return steps;
}

string merkle_with_first(vector<string> steps, string f)
{
	vector<string>::const_iterator i;
	for(i = steps.begin(); i != steps.end(); ++i)
	{
		string s = f + *i;

		char bin[YAAMP_HASHLEN_BIN*2];
		char out[YAAMP_HASHLEN_STR];

		binlify((unsigned char *)bin, s.c_str());
		sha256_double_hash_hex(bin, out, YAAMP_HASHLEN_BIN*2);

		f = out;
	}

	return f;
}

//def withFirst(self, f):
//        steps = self._steps
//        for s in steps:
//            f = doublesha(f + s)
//        return f

int test_merkle()
{
	vector<string> hash;
	hash.push_back("");
	hash.push_back("999d2c8bb6bda0bf784d9ebeb631d711dbbbfe1bc006ea13d6ad0d6a2649a971");
	hash.push_back("3f92594d5a3d7b4df29d7dd7c46a0dac39a96e751ba0fc9bab5435ea5e22a19d");
	hash.push_back("a5633f03855f541d8e60a6340fc491d49709dc821f3acb571956a856637adcb6");
	hash.push_back("28d97c850eaf917a4c76c02474b05b70a197eaefb468d21c22ed110afe8ec9e0");

	vector<string> res = merkle_steps(hash);
	string mr = merkle_with_first(res, "d43b669fb42cfa84695b844c0402d410213faa4f3e66cb7248f688ff19d5e5f7");

	printf("mr: %s\n", mr.c_str());		// 82293f182d5db07d08acf334a5a907012bbb9990851557ac0ec028116081bd5a

}





