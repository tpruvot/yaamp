#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include <sha3/sph_blake.h>
#include <sha3/sph_groestl.h>
#include <sha3/sph_jh.h>
#include <sha3/sph_keccak.h>
#include <sha3/sph_skein.h>

#include "jha.h"
#include "common.h"

void jha_hash(const char* input, char* output, uint32_t len)
{
	sph_blake512_context     ctx_blake;
	sph_groestl512_context   ctx_groestl;
	sph_jh512_context        ctx_jh;
	sph_keccak512_context    ctx_keccak;
	sph_skein512_context     ctx_skein;

	uint32_t _ALIGN(64) hash[16];

	// JHA v8: SHA3 512, on 80 bytes (not 88)
	sph_keccak512_init(&ctx_keccak);
	sph_keccak512(&ctx_keccak, input, 80);
	sph_keccak512_close(&ctx_keccak, (&hash));

	// Heavy & Light Pair Loop
	for (int round = 0; round < 3; round++)
	{
		if (hash[0] & 0x01) {
			sph_groestl512_init(&ctx_groestl);
			sph_groestl512(&ctx_groestl, (&hash), 64);
			sph_groestl512_close(&ctx_groestl, (&hash));
		} else {
			sph_skein512_init(&ctx_skein);
			sph_skein512(&ctx_skein, (&hash), 64);
			sph_skein512_close(&ctx_skein, (&hash));
		}

		if (hash[0] & 0x01) {
			sph_blake512_init(&ctx_blake);
			sph_blake512(&ctx_blake, (&hash), 64);
			sph_blake512_close(&ctx_blake, (&hash));
		} else {
			sph_jh512_init(&ctx_jh);
			sph_jh512(&ctx_jh, (&hash), 64);
			sph_jh512_close(&ctx_jh, (&hash));
		}
	}

	// Return 256 bits (32x8)
	memcpy(output, hash, 32);
}
