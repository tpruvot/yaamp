/**
 * x17r algo implementation
 *
 * modifyed by wubei@fusionsilicon.com 2018
 */

#include <stdio.h>
#include <stdint.h>

#include <stdlib.h>
#include <string.h>
#include <memory.h>

#include "sha3/sph_blake.h"
#include "sha3/sph_bmw.h"
#include "sha3/sph_groestl.h"
#include "sha3/sph_jh.h"
#include "sha3/sph_keccak.h"
#include "sha3/sph_skein.h"
#include "sha3/sph_luffa.h"
#include "sha3/sph_cubehash.h"
#include "sha3/sph_shavite.h"
#include "sha3/sph_simd.h"
#include "sha3/sph_echo.h"
#include "sha3/sph_hamsi.h"
#include "sha3/sph_fugue.h"
#include "sha3/sph_shabal.h"
#include "sha3/sph_whirlpool.h"
#include "sha3/sph_sha2.h"
#include "sha3/sph_haval.h"

#include "common.h"

#ifndef _MSC_VER
#define _ALIGN(x) __attribute__ ((aligned(x)))
#else
#define _ALIGN(x) __declspec(align(x))
#endif

enum Algo {
	BLAKE = 0,
	BMW,
	GROESTL,
	JH,
	KECCAK,
	SKEIN,
	LUFFA,
	CUBEHASH,
	SHAVITE,
	SIMD,
	ECHO,
	HAMSI,
	FUGUE,
	SHABAL,
	WHIRLPOOL,
	SHA512,
	HAVAL,//
	HASH_FUNC_COUNT
};

static void getAlgoString(const uint8_t* prevblock, char *output)
{
	char *sptr = output;
	for (int j = 0; j < HASH_FUNC_COUNT; j++) {
		//uint8_t b = (16 - j) >> 1; // 16 first ascii hex chars (lsb in uint256)
		//printf ("the prevblock is %d\n",prevblock[j]);
		//uint8_t algoDigit = (j & 1) ? (prevblock[b] & 0xF) : prevblock[b] >> 4;//
		uint8_t algoDigit = prevblock[j] % HASH_FUNC_COUNT;

		//printf ("the algoDigit is %d\n",algoDigit);
		if (algoDigit >= 10)
			sprintf(sptr, "%c", 'A' + (algoDigit - 10));
		else
			sprintf(sptr, "%u", (uint32_t) algoDigit);
		sptr++;
	}
	*sptr = '\0';
}


//uint32_t s_ntime = UINT32_MAX;
void x17r_hash(const char* input, char* output, uint32_t len)
{
	uint32_t s_ntime = UINT32_MAX;
	char hashOrder[HASH_FUNC_COUNT + 1] = { 0 };

	char* bytes_swap = NULL;
	bytes_swap = (char *)malloc(len);
	if (len >= 180) {
      memcpy(bytes_swap, (char *)input, 140);
	   memcpy(bytes_swap+140, (char *)input+144, len-144);
	   memcpy(bytes_swap+len-4, (char *)input+140, 4);
   } else {
	   memcpy(bytes_swap, (char *)input, len);
	}

	uint32_t _ALIGN(128) hash[64/4];

	sph_blake512_context     ctx_blake;
	sph_bmw512_context       ctx_bmw;
	sph_groestl512_context   ctx_groestl;
	sph_skein512_context     ctx_skein;
	sph_jh512_context        ctx_jh;
	sph_keccak512_context    ctx_keccak;
	sph_luffa512_context     ctx_luffa1;
	sph_cubehash512_context  ctx_cubehash1;
	sph_shavite512_context   ctx_shavite1;
	sph_simd512_context      ctx_simd1;
	sph_echo512_context      ctx_echo1;
	sph_hamsi512_context     ctx_hamsi1;
	sph_fugue512_context     ctx_fugue1;
	sph_shabal512_context    ctx_shabal1;
	sph_whirlpool_context    ctx_whirlpool1;
	sph_sha512_context       ctx_sha512;
	sph_haval256_5_context   ctx_haval;//
  
   memset((char*)&hash, 0, 64);
	void *in = (void*) bytes_swap;
	int size = len;

	if (s_ntime == UINT32_MAX) {
		const uint8_t* in8 = (uint8_t*) bytes_swap;
		getAlgoString(&in8[4], hashOrder);
	}

	for (int i = 0; i < 17; i++)//
	{
		const char elem = hashOrder[i];
		const uint8_t algo = elem >= 'A' ? elem - 'A' + 10 : elem - '0';
		//printf ("the algo is %d\n",algo);
		switch (algo) {
		case BLAKE:
			sph_blake512_init(&ctx_blake);
			sph_blake512(&ctx_blake, in, size);
			sph_blake512_close(&ctx_blake, hash);
			break;
		case BMW:
			sph_bmw512_init(&ctx_bmw);
			sph_bmw512(&ctx_bmw, in, size);
			sph_bmw512_close(&ctx_bmw, hash);
			break;
		case GROESTL:
			sph_groestl512_init(&ctx_groestl);
			sph_groestl512(&ctx_groestl, in, size);
			sph_groestl512_close(&ctx_groestl, hash);
			break;
		case SKEIN:
			sph_skein512_init(&ctx_skein);
			sph_skein512(&ctx_skein, in, size);
			sph_skein512_close(&ctx_skein, hash);
			break;
		case JH:
			sph_jh512_init(&ctx_jh);
			sph_jh512(&ctx_jh, in, size);
			sph_jh512_close(&ctx_jh, hash);
			break;
		case KECCAK:
			sph_keccak512_init(&ctx_keccak);
			sph_keccak512(&ctx_keccak, in, size);
			sph_keccak512_close(&ctx_keccak, hash);
			break;
		case LUFFA:
			sph_luffa512_init(&ctx_luffa1);
			sph_luffa512(&ctx_luffa1, in, size);
			sph_luffa512_close(&ctx_luffa1, hash);
			break;
		case CUBEHASH:
			sph_cubehash512_init(&ctx_cubehash1);
			sph_cubehash512(&ctx_cubehash1, in, size);
			sph_cubehash512_close(&ctx_cubehash1, hash);
			break;
		case SHAVITE:
			sph_shavite512_init(&ctx_shavite1);
			sph_shavite512(&ctx_shavite1, in, size);
			sph_shavite512_close(&ctx_shavite1, hash);
			break;
		case SIMD:
			sph_simd512_init(&ctx_simd1);
			sph_simd512(&ctx_simd1, in, size);
			sph_simd512_close(&ctx_simd1, hash);
			break;
		case ECHO:
			sph_echo512_init(&ctx_echo1);
			sph_echo512(&ctx_echo1, in, size);
			sph_echo512_close(&ctx_echo1, hash);
			break;
		case HAMSI:
			sph_hamsi512_init(&ctx_hamsi1);
			sph_hamsi512(&ctx_hamsi1, in, size);
			sph_hamsi512_close(&ctx_hamsi1, hash);
			break;
		case FUGUE:
			sph_fugue512_init(&ctx_fugue1);
			sph_fugue512(&ctx_fugue1, in, size);
			sph_fugue512_close(&ctx_fugue1, hash);
			break;
		case SHABAL:
			sph_shabal512_init(&ctx_shabal1);
			sph_shabal512(&ctx_shabal1, in, size);
			sph_shabal512_close(&ctx_shabal1, hash);
			break;
		case WHIRLPOOL:
			sph_whirlpool_init(&ctx_whirlpool1);
			sph_whirlpool(&ctx_whirlpool1, in, size);
			sph_whirlpool_close(&ctx_whirlpool1, hash);
			break;
		case SHA512:
			sph_sha512_init(&ctx_sha512);
			sph_sha512(&ctx_sha512,(const void*) in, size);
			sph_sha512_close(&ctx_sha512,(void*) hash);
			break;
      case HAVAL:
         sph_haval256_5_init(&ctx_haval);
         sph_haval256_5(&ctx_haval, (const void*)in, size);
         sph_haval256_5_close(&ctx_haval, hash);
         memset(hash+8,0x00000000,32);
         break;
		}
		in = (void*) hash;
		size = 64;
	}
	memcpy(output, hash, 32);
	free(bytes_swap);
}
