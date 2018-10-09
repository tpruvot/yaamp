#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include <sha3/sph_blake.h>
#include <sha3/sph_bmw.h>
#include <sha3/sph_keccak.h>

#define _ALIGN(x) __attribute__ ((aligned(x)))

extern uint64_t lbk3_height;

void lbk3_hash(const char* input, char* output, uint32_t len)
{
	sph_bmw256_context       ctx_bmw;
	sph_blake256_context     ctx_blake;
	sph_keccak256_context    ctx_keccak;

	uint8_t _ALIGN(128) hash[96];
	memset(&hash[32], 0, 64);

	sph_bmw256_init(&ctx_bmw);
	sph_bmw256 (&ctx_bmw, input, 80);
	sph_bmw256_close(&ctx_bmw, &hash[0]);

	sph_blake256_init(&ctx_blake);
	sph_blake256 (&ctx_blake, &hash[0], 64);
	sph_blake256_close(&ctx_blake, &hash[32]);

	sph_keccak256_init(&ctx_keccak);
	sph_keccak256 (&ctx_keccak, &hash[32], 64);
	sph_keccak256_close(&ctx_keccak, &hash[64]);

	memcpy(output, &hash[64], 32);
}
