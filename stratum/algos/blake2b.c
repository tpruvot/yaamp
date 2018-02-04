/**
 * Blake2-b Implementation
 * tpruvot@github 2015-2016
 */

#include <string.h>
#include <stdint.h>

#include <ar2/blake2b.h>

void blake2s_hash(const char* input, char* output, uint64_t len)
{
	uint8_t hash[BLAKE2B_OUTBYTES];
	blake2b_state blake2_ctx;

	blake2b_init(&blake2_ctx, BLAKE2B_OUTBYTES);
	blake2b_update(&blake2_ctx, input, len);
	blake2b_final(&blake2_ctx, hash, BLAKE2B_OUTBYTES);

	memcpy(output, hash, 64);
}
