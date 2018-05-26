#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "../sha3/sph_blake.h"

void blakecoin_hash(const char* input, char* output, uint32_t len)
{
	sph_blake256_context ctx_blake;

	sph_blake256_set_rounds(8);

	sph_blake256_init(&ctx_blake);
	sph_blake256(&ctx_blake, input, len);
	sph_blake256_close(&ctx_blake, output);
}

