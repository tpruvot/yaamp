#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "../sha3/sph_sha2.h"
#include "../sha3/sph_ripemd.h"

//#define DEBUG

#ifdef DEBUG
static void hexlify(char *hex, const unsigned char *bin, int len)
{
	hex[0] = 0;
	for(int i=0; i < len; i++)
		sprintf(hex+strlen(hex), "%02x", bin[i]);
}
#endif

void lbry_hash(const char* input, char* output, uint32_t len)
{
	uint32_t hashA[16];
	uint32_t hashB[8];
	uint32_t hashC[8];

	sph_sha256_context ctx_sha256;
	sph_sha512_context ctx_sha512;
	sph_ripemd160_context ctx_ripemd;

	sph_sha256_init(&ctx_sha256);
	sph_sha512_init(&ctx_sha512);
	sph_ripemd160_init(&ctx_ripemd);

	sph_sha256(&ctx_sha256, input, 112);
	sph_sha256_close(&ctx_sha256, hashA);
	sph_sha256(&ctx_sha256, hashA, 32);
	sph_sha256_close(&ctx_sha256, hashA);

	sph_sha512(&ctx_sha512, hashA, 32);
	sph_sha512_close(&ctx_sha512, hashA);

	sph_ripemd160(&ctx_ripemd, hashA, 32);  // sha512 low
	sph_ripemd160_close(&ctx_ripemd, hashB);

	sph_ripemd160(&ctx_ripemd, &hashA[8], 32); // sha512 high
	sph_ripemd160_close(&ctx_ripemd, hashC);

	sph_sha256(&ctx_sha256, hashB, 20);
	sph_sha256(&ctx_sha256, hashC, 20);
	sph_sha256_close(&ctx_sha256, hashA);

	sph_sha256(&ctx_sha256, hashA, 32);
	sph_sha256_close(&ctx_sha256, hashA);

	memcpy(output, hashA, 32);

#ifdef DEBUG
	char hex[512] = { 0 };
	hexlify(hex, input, len);
	fprintf(stderr, "input  %s (%d)\n", hex, len);

	hexlify(hex, output, 32);
	fprintf(stderr, "output %s\n", hex);
#endif
}
