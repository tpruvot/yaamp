/**
 * Blake2-S Implementation
 * tpruvot@github 2015-2016
 */

#include <string.h>
#include <stdint.h>

#include <sha3/blake2s.h>

void blake2s_hash(const char* input, char* output, uint32_t len)
{
	uint8_t hash[BLAKE2S_OUTBYTES];
	blake2s_state blake2_ctx;

	blake2s_init(&blake2_ctx, BLAKE2S_OUTBYTES);
	blake2s_update(&blake2_ctx, input, len);
	blake2s_final(&blake2_ctx, hash, BLAKE2S_OUTBYTES);

	memcpy(output, hash, 32);
}

