#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "../sha3/sph_whirlpool.h"

void whirlpoolx_hash(const char* input, char* output, uint32_t len)
{
	unsigned char hash[64] = { 0 };
	unsigned char hash_xored[32] = { 0 };
	int i;

	sph_whirlpool_context ctx_whirlpool;

	sph_whirlpool_init(&ctx_whirlpool);
	sph_whirlpool(&ctx_whirlpool, input, len); /* 80 */
	sph_whirlpool_close(&ctx_whirlpool, hash);

	for (i = 0; i < 32; i++) {
		hash_xored[i] = hash[i] ^ hash[i + 16];
	}

	memcpy(output, hash_xored, 32);
}
