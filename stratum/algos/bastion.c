#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include <sha3/sph_hefty1.h>
#include <sha3/sph_echo.h>
#include <sha3/sph_fugue.h>
#include <sha3/sph_whirlpool.h>
#include <sha3/sph_skein.h>
#include <sha3/sph_echo.h>
#include <sha3/sph_luffa.h>
#include <sha3/sph_hamsi.h>
#include <sha3/sph_shabal.h>

#define _ALIGN(x) __attribute__ ((aligned(x)))

void bastion_hash(const char* input, char* output, uint32_t len)
{
	unsigned char _ALIGN(128) hash[64] = { 0 };

	sph_echo512_context ctx_echo;
	sph_luffa512_context ctx_luffa;
	sph_fugue512_context ctx_fugue;
	sph_whirlpool_context ctx_whirlpool;
	sph_shabal512_context ctx_shabal;
	sph_skein512_context ctx_skein;
	sph_hamsi512_context ctx_hamsi;

	HEFTY1(input, len, hash);

	sph_luffa512_init(&ctx_luffa);
	sph_luffa512(&ctx_luffa, hash, 64);
	sph_luffa512_close(&ctx_luffa, hash);

	if (hash[0] & 0x8)
	{
		sph_fugue512_init(&ctx_fugue);
		sph_fugue512(&ctx_fugue, hash, 64);
		sph_fugue512_close(&ctx_fugue, hash);
	} else {
		sph_skein512_init(&ctx_skein);
		sph_skein512(&ctx_skein, hash, 64);
		sph_skein512_close(&ctx_skein, hash);
	}

	sph_whirlpool_init(&ctx_whirlpool);
	sph_whirlpool(&ctx_whirlpool, hash, 64);
	sph_whirlpool_close(&ctx_whirlpool, hash);

	sph_fugue512_init(&ctx_fugue);
	sph_fugue512(&ctx_fugue, hash, 64);
	sph_fugue512_close(&ctx_fugue, hash);

	if (hash[0] & 0x8)
	{
		sph_echo512_init(&ctx_echo);
		sph_echo512(&ctx_echo, hash, 64);
		sph_echo512_close(&ctx_echo, hash);
	} else {
		sph_luffa512_init(&ctx_luffa);
		sph_luffa512(&ctx_luffa, hash, 64);
		sph_luffa512_close(&ctx_luffa, hash);
	}

	sph_shabal512_init(&ctx_shabal);
	sph_shabal512(&ctx_shabal, hash, 64);
	sph_shabal512_close(&ctx_shabal, hash);

	sph_skein512_init(&ctx_skein);
	sph_skein512(&ctx_skein, hash, 64);
	sph_skein512_close(&ctx_skein, hash);

	if (hash[0] & 0x8)
	{
		sph_shabal512_init(&ctx_shabal);
		sph_shabal512(&ctx_shabal, hash, 64);
		sph_shabal512_close(&ctx_shabal, hash);
	} else {
		sph_whirlpool_init(&ctx_whirlpool);
		sph_whirlpool(&ctx_whirlpool, hash, 64);
		sph_whirlpool_close(&ctx_whirlpool, hash);
	}

	sph_shabal512_init(&ctx_shabal);
	sph_shabal512(&ctx_shabal, hash, 64);
	sph_shabal512_close(&ctx_shabal, hash);

	if (hash[0] & 0x8)
	{
		sph_hamsi512_init(&ctx_hamsi);
		sph_hamsi512(&ctx_hamsi, hash, 64);
		sph_hamsi512_close(&ctx_hamsi, hash);
	} else {
		sph_luffa512_init(&ctx_luffa);
		sph_luffa512(&ctx_luffa, hash, 64);
		sph_luffa512_close(&ctx_luffa, hash);
	}

	memcpy(output, hash, 32);
}

