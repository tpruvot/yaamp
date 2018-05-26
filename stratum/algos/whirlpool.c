#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "../sha3/sph_whirlpool.h"

/* untested ! */

void whirlpool_hash(const char* input, char* output, uint32_t len)
{
	unsigned char hash[64] = { 0 };
	int i;

	sph_whirlpool1_context ctx_whirlpool;

	sph_whirlpool1_init(&ctx_whirlpool);
	sph_whirlpool1 (&ctx_whirlpool, input, len);
	sph_whirlpool1_close(&ctx_whirlpool, (void*) hash);

	sph_whirlpool1_init(&ctx_whirlpool);
	sph_whirlpool1 (&ctx_whirlpool, (const void*) hash, 64);
	sph_whirlpool1_close(&ctx_whirlpool, (void*) hash);

	sph_whirlpool1_init(&ctx_whirlpool);
	sph_whirlpool1 (&ctx_whirlpool, (const void*) hash, 64);
	sph_whirlpool1_close(&ctx_whirlpool, (void*) hash);

	sph_whirlpool1_init(&ctx_whirlpool);
	sph_whirlpool1 (&ctx_whirlpool, (const void*) hash, 64);
	sph_whirlpool1_close(&ctx_whirlpool, (void*) hash);

	memcpy(output, hash, 32);
}
