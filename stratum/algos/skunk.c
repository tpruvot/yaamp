#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include <sha3/sph_skein.h>
#include <sha3/sph_cubehash.h>
#include <sha3/sph_fugue.h>
#include "gost.h"

#include "common.h"

void skunk_hash(const char *input, char* output, uint32_t len)
{
	uint32_t _ALIGN(64) hash[16];

	sph_skein512_context ctx_skein;
	sph_cubehash512_context ctx_cube;
	sph_fugue512_context ctx_fugue;
	sph_gost512_context ctx_gost;

	sph_skein512_init(&ctx_skein);
	sph_skein512(&ctx_skein, input, 80);
	sph_skein512_close(&ctx_skein, (void*) hash);

	sph_cubehash512_init(&ctx_cube);
	sph_cubehash512(&ctx_cube, hash, 64);
	sph_cubehash512_close(&ctx_cube, hash);

	sph_fugue512_init (&ctx_fugue);
	sph_fugue512(&ctx_fugue, hash, 64);
	sph_fugue512_close(&ctx_fugue, hash);

	sph_gost512_init(&ctx_gost);
	sph_gost512(&ctx_gost, (const void*) hash, 64);
	sph_gost512_close(&ctx_gost, (void*) hash);

	memcpy(output, hash, 32);
}

