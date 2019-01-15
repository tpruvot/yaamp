/**
 * Blake2-B Implementation
 * tpruvot@github 2016-2018
 */

#include <string.h>
#include <stdint.h>

#include <sha3/blake2b.h>
#include <sha3/sph_types.h>

void blake2b_hash(const char* input, char* output, uint32_t len)
{
	uint32_t ALIGN(64) hash[8];
	blake2b_ctx ctx;

	blake2b_init(&ctx, 32, NULL, 0);
	blake2b_update(&ctx, input, len);
	blake2b_final(&ctx, hash);

	memcpy(output, hash, 32);
}

