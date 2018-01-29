#include "stratum.h"

#ifdef HAVE_CURL
#include <curl/curl.h>

#ifndef WIN32
#include <errno.h>
#include <sys/socket.h>
#include <netinet/in.h>
#include <netinet/tcp.h>
#else
#include <Windows.h>
#include <winsock2.h>
#include <mstcpip.h>
#endif

bool opt_timeout = CURL_RPC_TIMEOUT; // 30sec
bool opt_debug = false;
bool opt_protocol = false;
bool opt_proxy = false;
long opt_proxy_type = 0; //CURLPROXY_SOCKS5;

static __thread char curl_last_err[1024] = { 0 };
const int last_err_len = 1023;

#define USER_AGENT "stratum/yiimp"
#define JSON_INDENT(x) 0
#define json_object_get(j,k) json_get_object(j,k)

struct data_buffer {
	void		*buf;
	size_t		len;
};

struct upload_buffer {
	const void	*buf;
	size_t		len;
	size_t		pos;
};

// may be used to trap header values
struct header_info {
	char* value;
};

static void databuf_free(struct data_buffer *db)
{
	if (!db)
		return;

	free(db->buf);

	memset(db, 0, sizeof(*db));
}

static size_t all_data_cb(const void *ptr, size_t size, size_t nmemb, void *user_data)
{
	struct data_buffer *db = (struct data_buffer *)user_data;
	size_t len = size * nmemb;
	size_t oldlen, newlen;
	void *newmem;
	static const unsigned char zero = 0;

	oldlen = db->len;
	newlen = oldlen + len;

	newmem = realloc(db->buf, newlen + 1);
	if (!newmem)
		return 0;

	db->buf = newmem;
	db->len = newlen;
	memcpy((char*)db->buf + oldlen, ptr, len);
	memcpy((char*)db->buf + newlen, &zero, 1);	/* null terminate */

	return len;
}

static size_t upload_data_cb(void *ptr, size_t size, size_t nmemb, void *user_data)
{
	struct upload_buffer *ub = (struct upload_buffer *)user_data;
	unsigned int len = (unsigned int)(size * nmemb);

	if (len > ub->len - ub->pos)
		len = (unsigned int)(ub->len - ub->pos);

	if (len) {
		memcpy(ptr, (char*)ub->buf + ub->pos, len);
		ub->pos += len;
	}

	return len;
}

#if LIBCURL_VERSION_NUM >= 0x071200
static int seek_data_cb(void *user_data, curl_off_t offset, int origin)
{
	struct upload_buffer *ub = (struct upload_buffer *)user_data;

	switch (origin) {
	case SEEK_SET:
		ub->pos = (size_t)offset;
		break;
	case SEEK_CUR:
		ub->pos += (size_t)offset;
		break;
	case SEEK_END:
		ub->pos = ub->len + (size_t)offset;
		break;
	default:
		return 1; /* CURL_SEEKFUNC_FAIL */
	}

	return 0; /* CURL_SEEKFUNC_OK */
}
#endif

static size_t resp_hdr_cb(void *ptr, size_t size, size_t nmemb, void *user_data)
{
	struct header_info *hi = (struct header_info *)user_data;
	size_t remlen, slen, ptrlen = size * nmemb;
	char *rem, *val = NULL, *key = NULL;
	void *tmp;

	val = (char*)calloc(1, ptrlen);
	key = (char*)calloc(1, ptrlen);
	if (!key || !val)
		goto out;

	tmp = memchr(ptr, ':', ptrlen);
	if (!tmp || (tmp == ptr))	/* skip empty keys / blanks */
		goto out;
	slen = (size_t)((char*)tmp - (char*)ptr);
	if ((slen + 1) == ptrlen)	/* skip key w/ no value */
		goto out;
	memcpy(key, ptr, slen);		/* store & nul term key */
	key[slen] = 0;

	rem = (char*)ptr + slen + 1;		/* trim value's leading whitespace */
	remlen = ptrlen - slen - 1;
	while ((remlen > 0) && (isspace(*rem))) {
		remlen--;
		rem++;
	}

	memcpy(val, rem, remlen);	/* store value, trim trailing ws */
	val[remlen] = 0;
	while ((*val) && (isspace(val[strlen(val) - 1]))) {
		val[strlen(val) - 1] = 0;
	}

out:
	free(key);
	free(val);
	return ptrlen;
}

#if LIBCURL_VERSION_NUM >= 0x070f06
static int sockopt_keepalive_cb(void *userdata, curl_socket_t fd,
	curlsocktype purpose)
{
	int keepalive = 1;
	int tcp_keepcnt = 3;
	int tcp_keepidle = 50;
	int tcp_keepintvl = 50;
#ifdef WIN32
	DWORD outputBytes;
#endif

#ifndef WIN32
	if (setsockopt(fd, SOL_SOCKET, SO_KEEPALIVE, &keepalive, sizeof(keepalive)))
		return 1;
#ifdef __linux
	if (setsockopt(fd, SOL_TCP, TCP_KEEPCNT, &tcp_keepcnt, sizeof(tcp_keepcnt)))
		return 1;
	if (setsockopt(fd, SOL_TCP, TCP_KEEPIDLE, &tcp_keepidle, sizeof(tcp_keepidle)))
		return 1;
	if (setsockopt(fd, SOL_TCP, TCP_KEEPINTVL, &tcp_keepintvl, sizeof(tcp_keepintvl)))
		return 1;
#endif /* __linux */
#ifdef __APPLE_CC__
	if (setsockopt(fd, IPPROTO_TCP, TCP_KEEPALIVE, &tcp_keepintvl, sizeof(tcp_keepintvl)))
		return 1;
#endif /* __APPLE_CC__ */
#else /* WIN32 */
	struct tcp_keepalive vals;
	vals.onoff = 1;
	vals.keepalivetime = tcp_keepidle * 1000;
	vals.keepaliveinterval = tcp_keepintvl * 1000;
	if (unlikely(WSAIoctl(fd, SIO_KEEPALIVE_VALS, &vals, sizeof(vals),
		NULL, 0, &outputBytes, NULL, NULL)))
		return 1;
#endif /* WIN32 */

	return 0;
}
#endif


static json_value *curl_json_rpc(YAAMP_RPC *rpc, const char *url, const char *rpc_req, int *curl_err)
{
	char len_hdr[64] = { 0 }, auth_hdr[512] = { 0 };
	char curl_err_str[CURL_ERROR_SIZE] = { 0 };
	struct data_buffer all_data = { 0 };
	struct upload_buffer upload_data;
	struct curl_slist *headers = NULL;
	struct header_info hi = { 0 };
	char *httpdata;
	CURL *curl = rpc->CURL;
	json_value *val;
	int rc;

	long timeout = opt_timeout;
	bool keepalive = false;

	/* it is assumed that 'curl' is freshly [re]initialized at this pt */

	if (opt_protocol)
		curl_easy_setopt(curl, CURLOPT_VERBOSE, 1);
	curl_easy_setopt(curl, CURLOPT_URL, url);

	if (rpc->ssl) {
		curl_easy_setopt(curl, CURLOPT_SSLVERSION, 1); // TLSv1
		if (strlen(rpc->cert))
			curl_easy_setopt(curl, CURLOPT_CAINFO, rpc->cert);
	}

	curl_easy_setopt(curl, CURLOPT_ENCODING, "");
	curl_easy_setopt(curl, CURLOPT_FAILONERROR, 0);
	curl_easy_setopt(curl, CURLOPT_NOSIGNAL, 1);
	curl_easy_setopt(curl, CURLOPT_TCP_NODELAY, 1);
	curl_easy_setopt(curl, CURLOPT_WRITEFUNCTION, all_data_cb);
	curl_easy_setopt(curl, CURLOPT_WRITEDATA, &all_data);
	curl_easy_setopt(curl, CURLOPT_READFUNCTION, upload_data_cb);
	curl_easy_setopt(curl, CURLOPT_READDATA, &upload_data);
#if LIBCURL_VERSION_NUM >= 0x071200
	curl_easy_setopt(curl, CURLOPT_SEEKFUNCTION, &seek_data_cb);
	curl_easy_setopt(curl, CURLOPT_SEEKDATA, &upload_data);
#endif
	curl_easy_setopt(curl, CURLOPT_ERRORBUFFER, curl_err_str);
	curl_easy_setopt(curl, CURLOPT_FOLLOWLOCATION, 1);
	curl_easy_setopt(curl, CURLOPT_CONNECTTIMEOUT, 5);
	curl_easy_setopt(curl, CURLOPT_TIMEOUT, timeout);
	curl_easy_setopt(curl, CURLOPT_HEADERFUNCTION, resp_hdr_cb);
	curl_easy_setopt(curl, CURLOPT_HEADERDATA, &hi);
	if (opt_proxy) {
		curl_easy_setopt(curl, CURLOPT_PROXY, opt_proxy);
		curl_easy_setopt(curl, CURLOPT_PROXYTYPE, opt_proxy_type);
	}

	// Encoded login/pass
	snprintf(auth_hdr, sizeof(auth_hdr), "Authorization: Basic %s", rpc->credential);

#if LIBCURL_VERSION_NUM >= 0x070f06
	if (keepalive)
		curl_easy_setopt(curl, CURLOPT_SOCKOPTFUNCTION, sockopt_keepalive_cb);
#endif
	curl_easy_setopt(curl, CURLOPT_POST, 1);

	if (opt_protocol)
		debuglog("JSON protocol request:\n%s", rpc_req);

	upload_data.buf = rpc_req;
	upload_data.len = strlen(rpc_req);
	upload_data.pos = 0;
	sprintf(len_hdr, "Content-Length: %lu", (unsigned long) upload_data.len);

	headers = curl_slist_append(headers, "Content-Type: application/json");
	headers = curl_slist_append(headers, len_hdr);
	headers = curl_slist_append(headers, auth_hdr);
	headers = curl_slist_append(headers, "User-Agent: " USER_AGENT);
	headers = curl_slist_append(headers, "Accept:"); /* disable Accept hdr*/
	headers = curl_slist_append(headers, "Expect:"); /* disable Expect hdr*/

	curl_easy_setopt(curl, CURLOPT_HTTPHEADER, headers);

	rc = curl_easy_perform(curl);
	if (curl_err != NULL)
		*curl_err = rc;
	if (rc) {
		if (rc != CURLE_OPERATION_TIMEDOUT) {
			snprintf(curl_last_err, last_err_len, "HTTP request failed: %s", curl_err_str);
			goto err_out;
		}
	}

	if (!all_data.buf || !all_data.len) {
		strcpy(curl_last_err, "rpc warning: no data received");
		goto err_out;
	}

	httpdata = (char*) all_data.buf;

	if (*httpdata != '{' && *httpdata != '[') {
		long errcode = 0;
		CURLcode c = curl_easy_getinfo(curl, CURLINFO_RESPONSE_CODE, &errcode);
		if (c == CURLE_OK && errcode == 401) {
			debuglog("ERR: You are not authorized, check your login and password.\n");
			goto err_out;
		}
	}

	val = json_parse(httpdata, strlen(httpdata));
	if (!val) {
		snprintf(curl_last_err, last_err_len, "JSON decode failed!");
		debuglog("ERR: JSON decode failed!\n");
		if (opt_protocol)
			debuglog("%s\n", httpdata);
		goto err_out;
	}

	if (opt_protocol) {
		debuglog("JSON protocol response:\n%s\n", httpdata);
	}

	databuf_free(&all_data);
	curl_slist_free_all(headers);
	curl_easy_reset(curl);
	return val;

err_out:
	databuf_free(&all_data);
	curl_slist_free_all(headers);
	curl_easy_reset(curl);
	return NULL;
}


//-------------------------------------------------------------------------------------------------

bool rpc_curl_connected(YAAMP_RPC *rpc)
{
	if (!rpc->CURL) return false;
#if 0 // LIBCURL_VERSION_NUM >= 0x072d00 /* 7.45 */
	curl_socket_t sock;
	struct sockaddr_storage peer;
	socklen_t peer_len = sizeof(peer);
	CURLcode c = curl_easy_getinfo(rpc->CURL, CURLINFO_ACTIVESOCKET, &sock);
	if (c == CURLE_OK) {
		if (getpeername(sock, (struct sockaddr*)&peer, &peer_len) != -1) {
			int port = 0;
			if (peer.ss_family == AF_INET) {
				struct sockaddr_in *s = (struct sockaddr_in*) &peer;
				port = (int) ntohs(s->sin_port);
			} else {
				struct sockaddr_in6 *s = (struct sockaddr_in6*) &peer;
				port = (int) ntohs(s->sin6_port);
			}
			//debuglog("%s port %d\n", __func__, port);
			return (port > 0);
		}
	}
#endif
	return true;
}

void rpc_curl_close(YAAMP_RPC *rpc)
{
	if(!rpc->CURL) return;
//	debuglog("%s %d\n", __func__, (int) rpc->sock);

	curl_easy_cleanup(rpc->CURL);
	rpc->CURL = NULL;
}

bool rpc_curl_connect(YAAMP_RPC *rpc)
{
	//rpc_curl_close(rpc);

	if (!rpc->CURL) {
//		debuglog("%s %d\n", __func__, (int) rpc->sock);
		rpc->CURL = curl_easy_init();
	}

	return true;
}

void rpc_curl_get_lasterr(char* buffer, int buflen)
{
	snprintf(buffer, buflen, "%s", curl_last_err);
}

/////////////////////////////////////////////////////////////////////////////////

static json_value *rpc_curl_do_call(YAAMP_RPC *rpc, char const *data)
{
	CommonLock(&rpc->mutex);

	char url[1024];
	int curl_err = 0;
	sprintf(url, "http%s://%s:%d", rpc->ssl?"s":"", rpc->host, rpc->port);
	strcpy(curl_last_err, "");

	json_value *res = curl_json_rpc(rpc, url, data, &curl_err);

	CommonUnlock(&rpc->mutex);

	return res;
}

json_value *rpc_curl_call(YAAMP_RPC *rpc, char const *method, char const *params)
{
//	debuglog("%s: %s:%d %s\n", __func__, rpc->host, rpc->port, method);

	int s1 = current_timestamp();
	if (!rpc->CURL) {
		rpc_curl_connect(rpc);
	}

	if(!rpc_curl_connected(rpc)) return NULL;

	int paramlen = params? strlen(params): 0;

	char *message = (char *)malloc(paramlen+1024);
	if(!message) return NULL;

	if(params)
		sprintf(message, "{\"method\":\"%s\",\"params\":%s,\"id\":\"%d\"}", method, params, ++rpc->id);
	else
		sprintf(message, "{\"method\":\"%s\",\"id\":\"%d\"}", method, ++rpc->id);

	json_value *json = rpc_curl_do_call(rpc, message);
	free(message);
	//rpc_curl_close(rpc);

	if(!json) return NULL;

	int s2 = current_timestamp();
	if(s2-s1 > 2000)
		debuglog("%s: delay %s:%d %s in %d ms\n", __func__, rpc->host, rpc->port, method, s2-s1);

	if(json->type != json_object)
	{
		json_value_free(json);
		return NULL;
	}

	return json;
}

#endif /* HAVE_CURL */
