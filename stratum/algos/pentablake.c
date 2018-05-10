
#include "pentablake.h"

#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>

#include "../sha3/sph_blake.h"

#include <stdlib.h>

void penta_hash(const char* input, char* output, uint32_t len)
{
	unsigned char hash[128];
	// same as uint32_t hashA[16], hashB[16];

	#define hashB hash+64

	sph_blake512_context     ctx_blake;

	sph_blake512_init(&ctx_blake);
	sph_blake512(&ctx_blake, input, 80);
	sph_blake512_close(&ctx_blake, hash);

	sph_blake512(&ctx_blake, hash, 64);
	sph_blake512_close(&ctx_blake, hashB);

	sph_blake512(&ctx_blake, hashB, 64);
	sph_blake512_close(&ctx_blake, hash);

	sph_blake512(&ctx_blake, hash, 64);
	sph_blake512_close(&ctx_blake, hashB);

	sph_blake512(&ctx_blake, hashB, 64);
	sph_blake512_close(&ctx_blake, hash);

	memcpy(output, hash, 32);
}

