
#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <stdio.h>

#include "../sha3/sph_types.h"
#include "../sha3/sph_keccak.h"

void keccak256_hash(const char *input, char *output, uint32_t len)
{
	uint32_t hash[16];

	sph_keccak256_context ctx_keccak;

	sph_keccak256_init(&ctx_keccak);
	sph_keccak256(&ctx_keccak, input, len /* 80 */);
	sph_keccak256_close(&ctx_keccak, hash);

	memcpy(output, hash, 32);
}

//void keccak512_hash(const char *input, char *output, uint32_t len)
//{
//	uint32_t hash[16];
//
//	sph_keccak512_context ctx_keccak;
//
//	sph_keccak512_init(&ctx_keccak);
//	sph_keccak512(&ctx_keccak, input, len);
//	sph_keccak512_close(&ctx_keccak, hash);
//
//	memcpy(output, hash, 32);
//}
