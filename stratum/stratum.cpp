
#include "stratum.h"
#include <signal.h>
#include <sys/resource.h>

CommonList g_list_coind;
CommonList g_list_client;
CommonList g_list_job;
CommonList g_list_remote;
CommonList g_list_renter;
CommonList g_list_share;
CommonList g_list_worker;
CommonList g_list_block;
CommonList g_list_submit;
CommonList g_list_source;

int g_tcp_port;

char g_tcp_server[1024];
char g_tcp_password[1024];

char g_sql_host[1024];
char g_sql_database[1024];
char g_sql_username[1024];
char g_sql_password[1024];

char g_stratum_algo[1024];
double g_stratum_difficulty;

int g_stratum_max_ttf;
bool g_stratum_reconnect;
bool g_stratum_renting;
bool g_stratum_curl;
bool g_autoexchange = true;

time_t g_last_broadcasted = 0;
YAAMP_DB *g_db = NULL;

pthread_mutex_t g_db_mutex;
pthread_mutex_t g_nonce1_mutex;
pthread_mutex_t g_job_create_mutex;

struct ifaddrs *g_ifaddr;

void *stratum_thread(void *p);
void *monitor_thread(void *p);

////////////////////////////////////////////////////////////////////////////////////////

static void scrypt_hash(const char* input, char* output, uint32_t len)
{
	scrypt_1024_1_1_256((unsigned char *)input, (unsigned char *)output);
}

static void scryptn_hash(const char* input, char* output, uint32_t len)
{
	time_t time_table[][2] =
	{
		{2048, 1389306217},
		{4096, 1456415081},
		{8192, 1506746729},
		{16384, 1557078377},
		{32768, 1657741673},
		{65536, 1859068265},
		{131072, 2060394857},
		{262144, 1722307603},
		{524288, 1769642992},
		{0, 0},
	};

	for(int i=0; time_table[i][0]; i++)
		if(time(NULL) < time_table[i+1][1])
		{
			scrypt_N_R_1_256(input, output, time_table[i][0], 1, len);
			return;
		}
}

static void neoscrypt_hash(const char* input, char* output, uint32_t len)
{
	neoscrypt((unsigned char *)input, (unsigned char *)output, 0x80000620);
}

YAAMP_ALGO g_algos[] =
{
	{"sha256", sha256_double_hash, 1, 0, 0},
	{"scrypt", scrypt_hash, 0x10000, 0, 0},
	{"scryptn", scryptn_hash, 0x10000, 0, 0},
	{"neoscrypt", neoscrypt_hash, 0x10000, 0, 0},

	{"c11", c11_hash, 1, 0, 0},
	{"x11", x11_hash, 1, 0, 0},
	{"x13", x13_hash, 1, 0, 0},
	{"x14", x14_hash, 1, 0, 0},
	{"x15", x15_hash, 1, 0, 0},

	{"lyra2", lyra2re_hash, 0x80, 0, 0},
	{"lyra2v2", lyra2v2_hash, 0x100, 0, 0},

	{"blake", blake_hash, 1, 0 },
	{"blakecoin", blakecoin_hash, 0x100, 0, sha256_hash_hex },
	{"blake2s", blake2s_hash, 1, 0 },
	{"vanilla", blakecoin_hash, 1, 0 },
	{"decred", decred_hash, 1, 0 },

	{"fresh", fresh_hash, 0x100, 0, 0},
	{"quark", quark_hash, 1, 0, 0},
	{"nist5", nist5_hash, 1, 0, 0},
	{"qubit", qubit_hash, 1, 0, 0},
	{"groestl", groestl_hash, 0x100, 0, sha256_hash_hex }, /* groestlcoin */
	{"dmd-gr", groestl_hash, 0x100, 0, 0}, /* diamond (double groestl) */
	{"myr-gr", groestlmyriad_hash, 1, 0, 0}, /* groestl + sha 64 */
	{"skein", skein_hash, 1, 0, 0},
	{"keccak", keccak256_hash, 0x80, 0, sha256_hash_hex },

	{"bmw", bmw_hash, 1, 0, 0},
	{"luffa", luffa_hash, 1, 0, 0},
	{"penta", penta_hash, 1, 0, 0},
	{"skein2", skein2_hash, 1, 0, 0},
	{"yescrypt", yescrypt_hash, 0x10000, 0, 0},
	{"zr5", zr5_hash, 1, 0, 0},

	{"hive", hive_hash, 0x10000, 0, 0},
	{"m7m", m7m_hash, 0x10000, 0, 0},
	{"velvet", velvet_hash, 0x10000, 0, 0},
	{"argon2", argon2_hash, 0x10000, 0, sha256_hash_hex },

	{"sib", sib_hash, 1, 0, 0},

	{"whirlcoin", whirlpool_hash, 1, 0, sha256_hash_hex }, /* old sha merkleroot */
	{"whirlpool", whirlpool_hash, 1, 0 }, /* sha256d merkleroot */
	{"whirlpoolx", whirlpoolx_hash, 1, 0, 0},

	{"", NULL, 0, 0},
};

YAAMP_ALGO *g_current_algo = NULL;

YAAMP_ALGO *stratum_find_algo(const char *name)
{
	for(int i=0; g_algos[i].name[0]; i++)
		if(!strcmp(name, g_algos[i].name))
			return &g_algos[i];

	return NULL;
}

////////////////////////////////////////////////////////////////////////////////////////

#include <dirent.h>

int main(int argc, char **argv)
{
	if(argc < 2)
	{
		printf("usage: %s <algo>\n", argv[0]);
		return 1;
	}

	srand(time(NULL));
	getifaddrs(&g_ifaddr);

	initlog(argv[1]);

#ifdef NO_EXCHANGE
	// todo: init with a db setting or a yiimp shell command
	g_autoexchange = false;
#endif

	char configfile[1024];
	sprintf(configfile, "%s.conf", argv[1]);

	dictionary *ini = iniparser_load(configfile);
	if(!ini)
	{
		debuglog("cant load config file %s\n", configfile);
		return 1;
	}

	g_tcp_port = iniparser_getint(ini, "TCP:port", 3333);
	strcpy(g_tcp_server, iniparser_getstring(ini, "TCP:server", NULL));
	strcpy(g_tcp_password, iniparser_getstring(ini, "TCP:password", NULL));

	strcpy(g_sql_host, iniparser_getstring(ini, "SQL:host", NULL));
	strcpy(g_sql_database, iniparser_getstring(ini, "SQL:database", NULL));
	strcpy(g_sql_username, iniparser_getstring(ini, "SQL:username", NULL));
	strcpy(g_sql_password, iniparser_getstring(ini, "SQL:password", NULL));

	strcpy(g_stratum_algo, iniparser_getstring(ini, "STRATUM:algo", NULL));
	g_stratum_difficulty = iniparser_getdouble(ini, "STRATUM:difficulty", 16);
	g_stratum_max_ttf = iniparser_getint(ini, "STRATUM:max_ttf", 0x70000000);
	g_stratum_reconnect = iniparser_getint(ini, "STRATUM:reconnect", true);
	g_stratum_renting = iniparser_getint(ini, "STRATUM:renting", true);

	g_stratum_curl = iniparser_getint(ini, "RPC:curl", false);

	iniparser_freedict(ini);

	g_current_algo = stratum_find_algo(g_stratum_algo);

	if(!g_current_algo) yaamp_error("invalid algo");
	if(!g_current_algo->hash_function) yaamp_error("no hash function");

	stratumlog("* starting stratumd for %s on %s:%d\n", g_current_algo->name, g_tcp_server, g_tcp_port);

	g_db = db_connect();
	if(!g_db) yaamp_error("Cant connect database");

//	db_query(g_db, "update mining set stratumids='loading'");

	yaamp_create_mutex(&g_db_mutex);
	yaamp_create_mutex(&g_nonce1_mutex);
	yaamp_create_mutex(&g_job_create_mutex);

	YAAMP_DB *db = db_connect();
	if(!db) yaamp_error("Cant connect database");

	db_register_stratum(db);
	db_update_algos(db);
	db_update_coinds(db);

	sleep(2);
	job_init();

//	job_signal();

	////////////////////////////////////////////////

	pthread_t thread1;
	pthread_create(&thread1, NULL, monitor_thread, NULL);

	pthread_t thread2;
	pthread_create(&thread2, NULL, stratum_thread, NULL);

	while(1)
	{
		sleep(20);

		db_register_stratum(db);
		db_update_workers(db);
		db_update_algos(db);
		db_update_coinds(db);

		if(g_stratum_renting)
		{
			db_update_renters(db);
			db_update_remotes(db);
		}

		share_write(db);
		share_prune(db);

		block_prune(db);
		submit_prune(db);

		sleep(1);
		job_signal();

		////////////////////////////////////

//		source_prune();

		object_prune(&g_list_coind, coind_delete);
		object_prune(&g_list_remote, remote_delete);
		object_prune(&g_list_job, job_delete);
		object_prune(&g_list_client, client_delete);
		object_prune(&g_list_block, block_delete);
		object_prune(&g_list_worker, worker_delete);
		object_prune(&g_list_share, share_delete);
		object_prune(&g_list_submit, submit_delete);
	}

	db_close(db);
	return 0;
}

///////////////////////////////////////////////////////////////////////////////

void *monitor_thread(void *p)
{
	while(1)
	{
		sleep(120);

		if(g_last_broadcasted + YAAMP_MAXJOBDELAY < time(NULL))
		{
			stratumlog("%s dead lock, exiting...\n", g_current_algo->name);
			exit(1);
		}
	}
}

///////////////////////////////////////////////////////////////////////////////

void *stratum_thread(void *p)
{
	int listen_sock = socket(AF_INET, SOCK_STREAM, 0);
	if(listen_sock <= 0) yaamp_error("socket");

	int optval = 1;
	setsockopt(listen_sock, SOL_SOCKET, SO_REUSEADDR, &optval, sizeof optval);

	struct sockaddr_in serv;

	serv.sin_family = AF_INET;
	serv.sin_addr.s_addr = htonl(INADDR_ANY);
	serv.sin_port = htons(g_tcp_port);

	int res = bind(listen_sock, (struct sockaddr*)&serv, sizeof(serv));
	if(res < 0) yaamp_error("bind");

	res = listen(listen_sock, 4096);
	if(res < 0) yaamp_error("listen");

	/////////////////////////////////////////////////////////////////////////

	while(1)
	{
		int sock = accept(listen_sock, NULL, NULL);
		if(sock <= 0)
		{
			stratumlog("%s accept error %d %d\n", g_current_algo->name, res, errno);
			continue;
		}

		pthread_t thread;

		int res = pthread_create(&thread, NULL, client_thread, (void *)(long)sock);
		if(res != 0)
		{
			close(sock);
			stratumlog("%s pthread_create error %d %d\n", g_current_algo->name, res, errno);
		}

		pthread_detach(thread);
	}
}

