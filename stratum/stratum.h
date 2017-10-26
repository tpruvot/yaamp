
#include <sys/types.h>
#include <sys/socket.h>
#include <sys/time.h>
#include <sys/resource.h>
#include <netinet/in.h>
#include <arpa/inet.h>
#include <netdb.h>
#include <errno.h>
#include <math.h>

#include <stdio.h>
#include <stdlib.h>
#include <stdarg.h>
#include <string.h>
#include <unistd.h>
#include <fcntl.h>
#include <pthread.h>
#include <mysql.h>
#include <errmsg.h>
#include <ifaddrs.h>

#include <iostream>
#include <vector>

using namespace std;

#include "iniparser/src/iniparser.h"

#include "json.h"
#include "util.h"

#define YAAMP_RESTARTDELAY		(24*60*60)
#define YAAMP_MAXJOBDELAY		(2*60)
#define CURL_RPC_TIMEOUT		(30)

#define YAAMP_MS				1000
#define YAAMP_SEC				1000000

#define YAAMP_MAXALGOS			32

typedef void (*YAAMP_HASH_FUNCTION)(const char *, char *, uint32_t);

#define YAAMP_SHAREPERSEC		10

#define YAAMP_MINDIFF			0x0000000080000000
#define YAAMP_MAXDIFF			0x4000000000000000

#define YAAMP_SMALLBUFSIZE		(32*1024)

#define YAAMP_NONCE_SIZE		4
#define YAAMP_EXTRANONCE2_SIZE	4

#define YAAMP_HASHLEN_STR		65
#define YAAMP_HASHLEN_BIN		32

extern CommonList g_list_coind;
extern CommonList g_list_client;
extern CommonList g_list_job;
extern CommonList g_list_remote;
extern CommonList g_list_renter;
extern CommonList g_list_share;
extern CommonList g_list_worker;
extern CommonList g_list_block;
extern CommonList g_list_submit;
extern CommonList g_list_source;

extern int g_tcp_port;

extern char g_tcp_server[1024];
extern char g_tcp_password[1024];

extern char g_sql_host[1024];
extern char g_sql_database[1024];
extern char g_sql_username[1024];
extern char g_sql_password[1024];

extern char g_stratum_algo[1024];
extern double g_stratum_difficulty;

extern int g_stratum_max_cons;
extern int g_stratum_max_ttf;
extern bool g_stratum_reconnect;
extern bool g_stratum_renting;

extern uint64_t g_max_shares;
extern uint64_t g_shares_counter;

extern bool g_allow_rolltime;
extern time_t g_last_broadcasted;

extern struct ifaddrs *g_ifaddr;

extern pthread_mutex_t g_db_mutex;
extern pthread_mutex_t g_nonce1_mutex;
extern pthread_mutex_t g_job_create_mutex;

extern volatile bool g_exiting;

#include "db.h"
#include "object.h"
#include "socket.h"
#include "client.h"
#include "rpc.h"
#include "job.h"
#include "coind.h"
#include "remote.h"
#include "share.h"

extern YAAMP_DB *g_db;
extern YAAMP_ALGO g_algos[];
extern YAAMP_ALGO *g_current_algo;

extern bool g_autoexchange;

/////////////////////////////////////////////////////////////////////////////////////////

YAAMP_ALGO *stratum_find_algo(const char *name);

extern "C"
{
void sha256_hash(const char *input, char *output, unsigned int len);
void sha256_double_hash(const char *input, char *output, unsigned int len);

void scrypt_1024_1_1_256(const unsigned char *input, unsigned char *output);
void scrypt_N_R_1_256(const char* input, char* output, uint32_t N, uint32_t R, uint32_t len);
}

void sha256_hash_hex(const char *input, char *output, unsigned int len);
void sha256_double_hash_hex(const char *input, char *output, unsigned int len);

#include "algos/c11.h"
#include "algos/x11.h"
#include "algos/x11evo.h"
#include "algos/x13.h"
#include "algos/x14.h"
#include "algos/x15.h"
#include "algos/x17.h"
#include "algos/xevan.h"
#include "algos/hmq17.h"
#include "algos/nist5.h"
#include "algos/fresh.h"
#include "algos/hsr14.h"
#include "algos/quark.h"
#include "algos/neoscrypt.h"
#include "algos/lyra2re.h"
#include "algos/lyra2v2.h"
#include "algos/lyra2z.h"
#include "algos/blake.h"
#include "algos/blakecoin.h"
#include "algos/blake2.h"
#include "algos/qubit.h"
#include "algos/groestl.h"
#include "algos/jha.h"
#include "algos/skein.h"
#include "algos/keccak.h"
#include "algos/sha256t.h"
#include "algos/skunk.h"
#include "algos/timetravel.h"
#include "algos/bitcore.h"

#include "algos/bastion.h"
#include "algos/bmw.h"
#include "algos/deep.h"
#include "algos/lbry.h"
#include "algos/luffa.h"
#include "algos/pentablake.h"
#include "algos/whirlpool.h"
#include "algos/whirlpoolx.h"
#include "algos/skein2.h"
#include "algos/yescrypt.h"
#include "algos/zr5.h"
#include "algos/hive.h"
#include "algos/sib.h"
#include "algos/m7m.h"
#include "algos/phi.h"
#include "algos/tribus.h"
#include "algos/veltor.h"
#include "algos/velvet.h"
#include "algos/argon2a.h"

