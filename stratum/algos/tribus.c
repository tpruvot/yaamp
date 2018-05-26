#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include <sha3/sph_jh.h>
#include <sha3/sph_keccak.h>
#include <sha3/sph_echo.h>

#include "common.h"

void tribus_hash(const char* input, char* output, uint32_t len)
{
	uint8_t _ALIGN(64) hash[64];

	sph_jh512_context ctx_jh;
	sph_keccak512_context ctx_keccak;
	sph_echo512_context ctx_echo;

	sph_jh512_init(&ctx_jh);
	sph_jh512(&ctx_jh, input, 80);
	sph_jh512_close(&ctx_jh, (void*) hash);

	sph_keccak512_init(&ctx_keccak);
	sph_keccak512(&ctx_keccak, (const void*) hash, 64);
	sph_keccak512_close(&ctx_keccak, (void*) hash);

	sph_echo512_init(&ctx_echo);
	sph_echo512(&ctx_echo, (const void*) hash, 64);
	sph_echo512_close(&ctx_echo, (void*) hash);

	memcpy(output, hash, 32);
}

