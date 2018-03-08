
#include <stdint.h>
#include <stdlib.h>
#include <string.h>
#include <stdio.h>

#include "../sha3/sph_types.h"
#include "../sha3/sph_keccak.h"
#include "../sha3/sph_shabal.h"
#include "../sha3/sph_blake.h"

#include "pomelo.h"

void hive_hash(const char *input, char *output, uint32_t len)
{
	uint32_t hash[8], hashB[8];
	sph_shabal256_context     ctx_shabal;
	sph_blake256_context      ctx_blake;
	sph_keccak256_context     ctx_keccak;


	sph_shabal256_init(&ctx_shabal);
	sph_shabal256 (&ctx_shabal, input, 80);
	sph_shabal256_close (&ctx_shabal, hash);

	POMELO(hashB, 32, hash, 32, hash, 32, 2, 10);

	sph_blake256_init(&ctx_blake);
	sph_blake256 (&ctx_blake, hashB, 32);
	sph_blake256_close(&ctx_blake, hash);

	sph_keccak256_init(&ctx_keccak);
	sph_keccak256 (&ctx_keccak, hash, 32);
	sph_keccak256_close(&ctx_keccak, output);
}

