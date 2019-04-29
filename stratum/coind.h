
struct YAAMP_COIND_AUX
{
	YAAMP_COIND *coind;
//	int height;

	int index;
	int chainid;

	char hash[1024];
	char target[1024];
};

class YAAMP_COIND: public YAAMP_OBJECT
{
public:
	bool touch;
	bool newcoind;

	YAAMP_RPC rpc;
	char rpcencoding[32];

//	pthread_t thread;
	pthread_mutex_t mutex;
//	pthread_cond_t cond;

//	bool closing;

	char name[1024];
	char symbol[256];
	char symbol2[256];
	char algo[256];
	char wallet[1024];
	char account[256];

	char pubkey[1024];
	char script_pubkey[1024];
	bool p2sh_address;

	bool pos;
	bool hassubmitblock;
	bool txmessage;

	char charity_address[1024];
	double charity_amount;
	double charity_percent;

	bool enable;
	bool auto_ready;
	bool newblock;
	char lastnotifyhash[192];

	int height;
	double difficulty;

	double reward;
	double reward_mul;

	double price;
	int pool_ttf;
	int actual_ttf;

	bool isaux;
	YAAMP_COIND_AUX aux;

	int notreportingcounter;
	bool usegetwork;
	bool usememorypool;
	bool hasmasternodes;
	bool oldmasternodes;
	bool multialgos; // pow_hash field (or mined_hash)

	bool usesegwit;
	char commitment[128];
	char witness_magic[16];

	YAAMP_JOB *job;
//	YAAMP_JOB_TEMPLATE *templ;
};

//////////////////////////////////////////////////////////////////////////

inline void coind_delete(YAAMP_OBJECT *object)
{
	YAAMP_COIND *coind = (YAAMP_COIND *)object;
	object_delete(coind->job);

//	if(coind->templ) delete coind->templ;
	delete coind;
}

void coind_error(YAAMP_COIND *coind, const char *s);

double coind_profitability(YAAMP_COIND *coind);
double coind_nethash(YAAMP_COIND *coind);

bool coind_can_mine(YAAMP_COIND *coind, bool isaux=false);
void coind_sort();

bool coind_submit(YAAMP_COIND *coind, const char *block);
bool coind_submitgetauxblock(YAAMP_COIND *coind, const char *hash, const char *block);

void coind_init(YAAMP_COIND *coind);
void coind_terminate(YAAMP_COIND *coind);
//void coind_getauxblock(YAAMP_COIND *coind);

bool coind_create_job(YAAMP_COIND *coind, bool force=false);

bool coind_validate_user_address(YAAMP_COIND *coind, char* const address);




