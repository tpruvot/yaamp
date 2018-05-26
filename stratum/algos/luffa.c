#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "luffa.h"

#include "../sha3/sph_luffa.h"

void luffa_hash(const char* input, char* output, uint32_t len)
{
	unsigned char hash[64];
	sph_luffa512_context ctx_luffa;

	sph_luffa512_init(&ctx_luffa);
	sph_luffa512 (&ctx_luffa, input, 80);
	sph_luffa512_close(&ctx_luffa, (void*) hash);

	memcpy(output, hash, 32);
}
