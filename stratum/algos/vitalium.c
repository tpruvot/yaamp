#include "vitalium.h"
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include <sha3/sph_skein.h>
#include <sha3/sph_cubehash.h>
#include <sha3/sph_fugue.h>
#include <sha3/sph_echo.h>
#include <sha3/sph_shavite.h>
#include <sha3/sph_luffa.h>
#include "gost.h"

#include "common.h"

void vitalium_hash(const char* input, char* output, uint32_t len)
{
	sph_skein512_context        ctx_skein;
	sph_cubehash512_context     ctx_cubehash;
	sph_fugue512_context        ctx_fugue;
	sph_gost512_context         ctx_gost;
	sph_echo512_context         ctx_echo;
	sph_shavite512_context      ctx_shavite;
	sph_luffa512_context        ctx_luffa;

	//these uint512 in the c++ source of the client are backed by an array of uint32
	uint32_t hashA[16], hashB[16];

	sph_skein512_init(&ctx_skein);
	sph_skein512 (&ctx_skein, input, len);
	sph_skein512_close (&ctx_skein, hashA);
	
	sph_cubehash512_init(&ctx_cubehash);
	sph_cubehash512 (&ctx_cubehash, hashA, 64);
	sph_cubehash512_close(&ctx_cubehash, hashB);

	sph_fugue512_init(&ctx_fugue);
	sph_fugue512 (&ctx_fugue, hashB, 64);
	sph_fugue512_close(&ctx_fugue, hashA);

	sph_gost512_init(&ctx_gost);
	sph_gost512 (&ctx_gost, hashA, 64);
	sph_gost512_close (&ctx_gost, hashB);

	sph_echo512_init(&ctx_echo);
	sph_echo512 (&ctx_echo, hashB, 64);
	sph_echo512_close(&ctx_echo, hashA);

	sph_shavite512_init(&ctx_shavite);
	sph_shavite512 (&ctx_shavite, hashA, 64);
	sph_shavite512_close(&ctx_shavite, hashB);

	sph_luffa512_init (&ctx_luffa);
	sph_luffa512 (&ctx_luffa, hashB, 64);
	sph_luffa512_close (&ctx_luffa, hashA);
	
	sph_gost512_init(&ctx_gost);
	sph_gost512 (&ctx_gost, hashA, 64);
	sph_gost512_close (&ctx_gost, hashB);
	
	sph_cubehash512_init(&ctx_cubehash);
	sph_cubehash512 (&ctx_cubehash, hashB, 64);
	sph_cubehash512_close(&ctx_cubehash, hashA);
	
	sph_fugue512_init(&ctx_fugue);
	sph_fugue512 (&ctx_fugue, hashA, 64);
	sph_fugue512_close(&ctx_fugue, hashB);
	
	sph_gost512_init(&ctx_gost);
	sph_gost512 (&ctx_gost, hashB, 64);
	sph_gost512_close (&ctx_gost, hashA);
	
	sph_echo512_init(&ctx_echo);
	sph_echo512 (&ctx_echo, hashA, 64);
	sph_echo512_close(&ctx_echo, hashB);
	
	sph_shavite512_init(&ctx_shavite);
	sph_shavite512 (&ctx_shavite, hashB, 64);
	sph_shavite512_close(&ctx_shavite, hashA);
	
	sph_luffa512_init (&ctx_luffa);
	sph_luffa512 (&ctx_luffa, hashA, 64);
	sph_luffa512_close (&ctx_luffa, hashB);

	memcpy(output, hashB, 32);
}
