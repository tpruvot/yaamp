#include <stdint.h>
#include <stdio.h>
#include <memory.h>

#include "dedal.h"
#include "sph_blake.h"
#include "sph_bmw.h"
#include "sph_groestl.h"
#include "sph_jh.h"
#include "sph_keccak.h"
#include "sph_skein.h"
#include "sph_luffa.h"
#include "sph_cubehash.h"
#include "sph_shavite.h"
#include "sph_simd.h"
#include "sph_echo.h"
#include "sph_hamsi.h"
#include "sph_fugue.h"
#include "sph_shabal.h"
#include "sph_whirlpool.h"
#include "sph_sha2.h"

const uint8_t Kspeed[16] = {
	200,	// BLAKE
	236,	// BMW
	252,	// SKEIN
	224,	// KECCAK
	240,	// SHA512
	230,	// SHABAL
	79,		// WHIRLPOOL
	78,		// LUFFA
	89,		// CUBEHASH
	62,		// SHAVITE
	59,		// FUGUE
	119,	// JH
	62,		// HAMSI
	52,		// ECHO
	22,		// SIMD
	47		// GROESTL
};

static void get_hash_order(const uint32_t* prevblock, uint8_t* output, uint8_t* hashrounds)
{
	uint8_t* ord = output;
	uint8_t hr = 0;
	uint8_t* data = (uint8_t*)prevblock;
	uint16_t tspeed = 0;
	
	for (uint8_t i = 0; i < 6; i++) {
		ord[i] = data[i] % 16;
		ord[i + 6] = data[i+1] >> 4;
		tspeed += Kspeed[ord[i]] + Kspeed[ord[i + 6]];
	}
	hr = tspeed + 920 >> 7;
	
	uint8_t c = hr - 12;
	for (uint8_t i = 0; i < c ; i++) {
		if (i < 15) {
			uint8_t j = i >> 1;
			ord[i + 12] = (i & 1) ? data[j] % 6 : data[j] % 5;
		} else {
			ord[i + 12] = data[i - 15] % 4;
		}
	}
	*hashrounds = hr;
}

void dedal_hash(const char* input, char* output, uint32_t len) 
{
	
    unsigned char hash[128];

		sph_blake512_context ctx_blake;
		sph_bmw512_context ctx_bmw;
		sph_groestl512_context ctx_groestl;
		sph_jh512_context ctx_jh;
		sph_keccak512_context ctx_keccak;
		sph_skein512_context ctx_skein;
		sph_luffa512_context ctx_luffa;
		sph_cubehash512_context ctx_cubehash;
		sph_shavite512_context ctx_shavite;
		sph_simd512_context ctx_simd;
		sph_echo512_context ctx_echo;
		sph_hamsi512_context ctx_hamsi;
		sph_fugue512_context ctx_fugue;
		sph_shabal512_context ctx_shabal;
		sph_whirlpool_context ctx_whirlpool;
		sph_sha512_context ctx_sha512;

    const void *in = input;
    int size = len;
    uint32_t *in32 = (uint32_t*) input;
	uint8_t hashorder[32] = {};
	uint8_t hashrounds = 0;

	get_hash_order(&in32[1], hashorder, &hashrounds);

    for (int i = 0; i < hashrounds; i++) 
	{
        switch (hashorder[i]) 
		{
			case 0:
				sph_blake512_init(&ctx_blake);
				sph_blake512(&ctx_blake, in, size);
				sph_blake512_close(&ctx_blake, hash);
				break;
			case 1:
				sph_bmw512_init(&ctx_bmw);
				sph_bmw512(&ctx_bmw, in, size);
				sph_bmw512_close(&ctx_bmw, hash);
				break;
			case 2:
				sph_skein512_init(&ctx_skein);
				sph_skein512(&ctx_skein, in, size);
				sph_skein512_close(&ctx_skein, hash);
				break;
			case 3:
				sph_keccak512_init(&ctx_keccak);
				sph_keccak512(&ctx_keccak, in, size);
				sph_keccak512_close(&ctx_keccak, hash);
				break;
			case 4:
				sph_sha512_init(&ctx_sha512);
				sph_sha512(&ctx_sha512, in, size);
				sph_sha512_close(&ctx_sha512, hash);
				break;
			case 5:
				sph_shabal512_init(&ctx_shabal);
				sph_shabal512(&ctx_shabal, in, size);
				sph_shabal512_close(&ctx_shabal, hash);
				break;
			case 6:
				sph_whirlpool_init(&ctx_whirlpool);
				sph_whirlpool(&ctx_whirlpool, in, size);
				sph_whirlpool_close(&ctx_whirlpool, hash);
				break;
			case 7:
				sph_luffa512_init(&ctx_luffa);
				sph_luffa512(&ctx_luffa, in, size);
				sph_luffa512_close(&ctx_luffa, hash);
				break;
			case 8:
				sph_cubehash512_init(&ctx_cubehash);
				sph_cubehash512(&ctx_cubehash, in, size);
				sph_cubehash512_close(&ctx_cubehash, hash);
				break;
			case 9:
				sph_shavite512_init(&ctx_shavite);
				sph_shavite512(&ctx_shavite, in, size);
				sph_shavite512_close(&ctx_shavite, hash);
				break;
			case 10:
				sph_fugue512_init(&ctx_fugue);
				sph_fugue512(&ctx_fugue, in, size);
				sph_fugue512_close(&ctx_fugue, hash);
				break;
			case 11:
				sph_jh512_init(&ctx_jh);
				sph_jh512(&ctx_jh, in, size);
				sph_jh512_close(&ctx_jh, hash);
				break;
			case 12:
				sph_hamsi512_init(&ctx_hamsi);
				sph_hamsi512(&ctx_hamsi, in, size);
				sph_hamsi512_close(&ctx_hamsi, hash);
				break;
			case 13:
				sph_echo512_init(&ctx_echo);
				sph_echo512(&ctx_echo, in, size);
				sph_echo512_close(&ctx_echo, hash);
				break;
			case 14:
				sph_simd512_init(&ctx_simd);
				sph_simd512(&ctx_simd, in, size);
				sph_simd512_close(&ctx_simd, hash);
				break;
			case 15:
				sph_groestl512_init(&ctx_groestl);
				sph_groestl512(&ctx_groestl, in, size);
				sph_groestl512_close(&ctx_groestl, hash);
				break;	
        }
        in = (void*)hash;
        size = 64;
    }
    memcpy(output, hash, 32);
}
