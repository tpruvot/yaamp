
#define MAX_AUXS	32

class YAAMP_REMOTE;
class YAAMP_COIND;
class YAAMP_COIND_AUX;

struct YAAMP_JOB_VALUES
{
	char coinbase[4*1024];
	char merkleroot_be[1024];

	char header[1024];
	char header_be[1024];
	unsigned char header_bin[1024];

	char hash_hex[1024];
	char hash_be[1024];
	unsigned char hash_bin[1024];
};

struct YAAMP_JOB_TEMPLATE
{
	int created;
	char flags[64];

	char prevhash_hex[512];
	char prevhash_be[512];

	char extradata_hex[512];
	char extradata_be[512];

	// todo: can use extra field
	char claim_hex[128];
	char claim_be[128];

	int txcount;
	char txmerkles[YAAMP_SMALLBUFSIZE];

	vector<string> txsteps;
	vector<string> txdata;

	char version[32];
	char nbits[32];
	char ntime[32];

	int height;
	int target;

	json_int_t value;

	char coinb1[4*1024];
	char coinb2[4*1024];
	char coinforsubmitb1[4*1024];
	char coinforsubmitb2[4*1024];
	bool isbitcash;

	char header[256];

	bool has_segwit_txs;

	bool has_filtered_txs;
	int filtered_txs_fee;

	int auxs_size;
	YAAMP_COIND_AUX *auxs[MAX_AUXS];
	
	bool needpriceinfo;
	char priceinfo[1024];	
};

#define YAAMP_JOB_MAXSUBIDS		200

class YAAMP_JOB: public YAAMP_OBJECT
{
public:
	bool block_found;
	char name[1024];

	int count;
	double speed;

	double maxspeed;
	double profit;

	YAAMP_COIND *coind;			// either one of them
	YAAMP_REMOTE *remote;
	YAAMP_JOB_TEMPLATE *templ;

	bool remote_subids[YAAMP_JOB_MAXSUBIDS];
};

inline void job_delete(YAAMP_OBJECT *object)
{
	YAAMP_JOB *job = (YAAMP_JOB *)object;
	if (!job) return;
	if (job->templ && job->templ->txcount) {
		job->templ->txsteps.clear();
		job->templ->txdata.clear();
	}
	if (job->templ) delete job->templ;
	delete job;
}

/////////////////////////////////////////////////////////////////////////////////////////////

int job_get_jobid();

void job_sort();
void job_relock_clients(int jobid_old, int jobid_new);
void job_unlock_clients(YAAMP_JOB *job=NULL);
void job_assign_locked_clients(YAAMP_JOB *job);

bool job_can_mine(YAAMP_JOB *job);
void job_reset_clients(YAAMP_JOB *job=NULL);
bool job_has_free_client();

//YAAMP_JOB_TEMPLATE *job_create_template(YAAMP_COIND *coind);
//void job_create_last(YAAMP_COIND *coind, bool force=false);

/////////////////////////

void job_send_jobid(YAAMP_CLIENT *client, int jobid);
void job_send_last(YAAMP_CLIENT *client);
void job_broadcast(YAAMP_JOB *job);

/////////////////////////

void *job_thread(void *p);
void job_signal();
void job_update();
void job_init();


void coinbase_create(YAAMP_COIND *coind, YAAMP_JOB_TEMPLATE *templ, json_value *json_result);

vector<string> coind_aux_hashlist(YAAMP_COIND_AUX **auxs, int size);
vector<string> coind_aux_merkle_branch(YAAMP_COIND_AUX **auxs, int size, int index);
void coind_aux_build_auxs(YAAMP_JOB_TEMPLATE *templ);






