#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>

#include "drop.h"

#ifdef __cplusplus
extern "C" {
#endif

#include "../sha3/sph_blake.h"
#include "../sha3/sph_groestl.h"
#include "../sha3/sph_skein.h"
#include "../sha3/sph_jh.h"
#include "../sha3/sph_keccak.h"
#include "../sha3/sph_luffa.h"
#include "../sha3/sph_cubehash.h"
#include "../sha3/sph_shavite.h"
#include "../sha3/sph_simd.h"
#include "../sha3/sph_echo.h"
#include "../sha3/sph_fugue.h"

//#define TEST_VERBOSELY

static inline void shiftr_lp(const uint32_t *input, uint32_t *output, unsigned int shift)
{
	if (!shift) {
		memcpy(output, input, 64);
		return;
	}
	memset(output, 0, 64);
	int i;
	for (i = 0; i < 15; ++i) {
		output[i + 1] |= (input[i] >> (32 - shift));
		output[i] |= (input[i] << shift);
	}
	output[15] |= (input[15] << shift);
	return;
}


void drop_hash_512( uint8_t* input, uint8_t* output, uint32_t len )
{
	uint8_t hash[2][64];

	sph_jh512_context ctx_jh;
	sph_keccak512_context ctx_keccak;
	sph_blake512_context ctx_blake;
	sph_groestl512_context ctx_groestl;
	sph_skein512_context ctx_skein;
	sph_luffa512_context ctx_luffa;
	sph_echo512_context ctx_echo;
	sph_shavite512_context ctx_shavite;
	sph_fugue512_context ctx_fugue;
	sph_simd512_context ctx_simd;
	sph_cubehash512_context ctx_cubehash;

	unsigned int startPosition; // order in which to apply the hashing algos
	unsigned int i = 0; 
	unsigned int j = 0;

	uint8_t *phashA = (uint8_t *)(&hash[0]);
	uint8_t *phashB = (uint8_t *)(&hash[1]);

	// initialize the buffers for hash results
	for(i=0; i<2; i++) { for(j=0; j<64; j++) { hash[i][j] = 0; } }


	sph_jh512_init(&ctx_jh);
	sph_jh512 (&ctx_jh, input, len);
	sph_jh512_close(&ctx_jh, phashA);

	uint32_t gls32 = getleastsig32(phashA, 0);
	startPosition = gls32 % 31;

	for (i = startPosition; i < 31; i--) {
		int start = i % 10;
		for ( j = start; j < 10; j++) {
			shiftr_lp((uint32_t *)phashA, (uint32_t *)phashB, (i & 3));
			switch (j) {
				case 0:
					sph_keccak512_init(&ctx_keccak);
					sph_keccak512(&ctx_keccak, phashB, 64);
					sph_keccak512_close(&ctx_keccak, phashA);
					break;
				case 1:
					sph_blake512_init(&ctx_blake);
					sph_blake512(&ctx_blake, phashB, 64);
					sph_blake512_close(&ctx_blake, phashA);
					break;
				case 2:
					sph_groestl512_init(&ctx_groestl);
					sph_groestl512(&ctx_groestl, phashB, 64);
					sph_groestl512_close(&ctx_groestl, phashA);
					break;
				case 3:
					sph_skein512_init(&ctx_skein);
					sph_skein512(&ctx_skein, phashB, 64);
					sph_skein512_close(&ctx_skein, phashA);
					break;
				case 4:
					sph_luffa512_init(&ctx_luffa);
					sph_luffa512(&ctx_luffa, phashB, 64);
					sph_luffa512_close(&ctx_luffa, phashA);
					break;
				case 5:
					sph_echo512_init(&ctx_echo);
					sph_echo512(&ctx_echo, phashB, 64);
					sph_echo512_close(&ctx_echo, phashA);
					break;
				case 6:
					sph_shavite512_init(&ctx_shavite);
					sph_shavite512(&ctx_shavite, phashB, 64);
					sph_shavite512_close(&ctx_shavite, phashA);
					break;
				case 7:
					sph_fugue512_init(&ctx_fugue);
					sph_fugue512(&ctx_fugue, phashB, 64);
					sph_fugue512_close(&ctx_fugue, phashA);
					break;
				case 8:
					sph_simd512_init(&ctx_simd);
					sph_simd512(&ctx_simd, phashB, 64);
					sph_simd512_close(&ctx_simd, phashA);
					break;
				case 9:
					sph_cubehash512_init(&ctx_cubehash);
					sph_cubehash512(&ctx_cubehash, phashB, 64);
					sph_cubehash512_close(&ctx_cubehash, phashA);
					break;
				default:
					break;
			}
		}
		for ( j = 0; j < start; j++) {
			shiftr_lp((uint32_t *)phashA, (uint32_t *)phashB, (i & 3));
			switch (j) {
				case 0:
					sph_keccak512_init(&ctx_keccak);
					sph_keccak512(&ctx_keccak, phashB, 64);
					sph_keccak512_close(&ctx_keccak, phashA);
					break;
				case 1:
					sph_blake512_init(&ctx_blake);
					sph_blake512(&ctx_blake, phashB, 64);
					sph_blake512_close(&ctx_blake, phashA);
					break;
				case 2:
					sph_groestl512_init(&ctx_groestl);
					sph_groestl512(&ctx_groestl, phashB, 64);
					sph_groestl512_close(&ctx_groestl, phashA);
					break;
				case 3:
					sph_skein512_init(&ctx_skein);
					sph_skein512(&ctx_skein, phashB, 64);
					sph_skein512_close(&ctx_skein, phashA);
					break;
				case 4:
					sph_luffa512_init(&ctx_luffa);
					sph_luffa512(&ctx_luffa, phashB, 64);
					sph_luffa512_close(&ctx_luffa, phashA);
					break;
				case 5:
					sph_echo512_init(&ctx_echo);
					sph_echo512(&ctx_echo, phashB, 64);
					sph_echo512_close(&ctx_echo, phashA);
					break;
				case 6:
					sph_shavite512_init(&ctx_shavite);
					sph_shavite512(&ctx_shavite, phashB, 64);
					sph_shavite512_close(&ctx_shavite, phashA);
					break;
				case 7:
					sph_fugue512_init(&ctx_fugue);
					sph_fugue512(&ctx_fugue, phashB, 64);
					sph_fugue512_close(&ctx_fugue, phashA);
					break;
				case 8:
					sph_simd512_init(&ctx_simd);
					sph_simd512(&ctx_simd, phashB, 64);
					sph_simd512_close(&ctx_simd, phashA);
					break;
				case 9:
					sph_cubehash512_init(&ctx_cubehash);
					sph_cubehash512(&ctx_cubehash, phashB, 64);
					sph_cubehash512_close(&ctx_cubehash, phashA);
					break;
				default:
					break;
			}
		}
		i += 10;
	}
	for ( i = 0; i < startPosition; i--) {
		int start = i % 10;
		for ( j = start; j < 10; j++) {
			shiftr_lp((uint32_t *)phashA, (uint32_t *)phashB, (i & 3));
			switch (j) {
				case 0:
					sph_keccak512_init(&ctx_keccak);
					sph_keccak512(&ctx_keccak, phashB, 64);
					sph_keccak512_close(&ctx_keccak, phashA);
					break;
				case 1:
					sph_blake512_init(&ctx_blake);
					sph_blake512(&ctx_blake, phashB, 64);
					sph_blake512_close(&ctx_blake, phashA);
					break;
				case 2:
					sph_groestl512_init(&ctx_groestl);
					sph_groestl512(&ctx_groestl, phashB, 64);
					sph_groestl512_close(&ctx_groestl, phashA);
					break;
				case 3:
					sph_skein512_init(&ctx_skein);
					sph_skein512(&ctx_skein, phashB, 64);
					sph_skein512_close(&ctx_skein, phashA);
					break;
				case 4:
					sph_luffa512_init(&ctx_luffa);
					sph_luffa512(&ctx_luffa, phashB, 64);
					sph_luffa512_close(&ctx_luffa, phashA);
					break;
				case 5:
					sph_echo512_init(&ctx_echo);
					sph_echo512(&ctx_echo, phashB, 64);
					sph_echo512_close(&ctx_echo, phashA);
					break;
				case 6:
					sph_shavite512_init(&ctx_shavite);
					sph_shavite512(&ctx_shavite, phashB, 64);
					sph_shavite512_close(&ctx_shavite, phashA);
					break;
				case 7:
					sph_fugue512_init(&ctx_fugue);
					sph_fugue512(&ctx_fugue, phashB, 64);
					sph_fugue512_close(&ctx_fugue, phashA);
					break;
				case 8:
					sph_simd512_init(&ctx_simd);
					sph_simd512(&ctx_simd, phashB, 64);
					sph_simd512_close(&ctx_simd, phashA);
					break;
				case 9:
					sph_cubehash512_init(&ctx_cubehash);
					sph_cubehash512(&ctx_cubehash, phashB, 64);
					sph_cubehash512_close(&ctx_cubehash, phashA);
					break;
				default:
					break;
			}
		}
		for ( j = 0; j < start; j++) {
			shiftr_lp((uint32_t *)phashA, (uint32_t *)phashB, (i & 3));
			switch (j) {
				case 0:
					sph_keccak512_init(&ctx_keccak);
					sph_keccak512(&ctx_keccak, phashB, 64);
					sph_keccak512_close(&ctx_keccak, phashA);
					break;
				case 1:
					sph_blake512_init(&ctx_blake);
					sph_blake512(&ctx_blake, phashB, 64);
					sph_blake512_close(&ctx_blake, phashA);
					break;
				case 2:
					sph_groestl512_init(&ctx_groestl);
					sph_groestl512(&ctx_groestl, phashB, 64);
					sph_groestl512_close(&ctx_groestl, phashA);
					break;
				case 3:
					sph_skein512_init(&ctx_skein);
					sph_skein512(&ctx_skein, phashB, 64);
					sph_skein512_close(&ctx_skein, phashA);
					break;
				case 4:
					sph_luffa512_init(&ctx_luffa);
					sph_luffa512(&ctx_luffa, phashB, 64);
					sph_luffa512_close(&ctx_luffa, phashA);
					break;
				case 5:
					sph_echo512_init(&ctx_echo);
					sph_echo512(&ctx_echo, phashB, 64);
					sph_echo512_close(&ctx_echo, phashA);
					break;
				case 6:
					sph_shavite512_init(&ctx_shavite);
					sph_shavite512(&ctx_shavite, phashB, 64);
					sph_shavite512_close(&ctx_shavite, phashA);
					break;
				case 7:
					sph_fugue512_init(&ctx_fugue);
					sph_fugue512(&ctx_fugue, phashB, 64);
					sph_fugue512_close(&ctx_fugue, phashA);
					break;
				case 8:
					sph_simd512_init(&ctx_simd);
					sph_simd512(&ctx_simd, phashB, 64);
					sph_simd512_close(&ctx_simd, phashA);
					break;
				case 9:
					sph_cubehash512_init(&ctx_cubehash);
					sph_cubehash512(&ctx_cubehash, phashB, 64);
					sph_cubehash512_close(&ctx_cubehash, phashA);
					break;
				default:
					break;
			}
		}
		i += 10;
	}

	phashB = (uint8_t *)output;
	for (i = 0; i < 64; i++) {
		phashB[i] = phashA[i];
	}
	return;
}


void drop_hash(const char* input, char* output, uint32_t len)
{
	uint8_t *input512;	// writeable copy of input
	uint8_t output512[64];	// output of both zr5 hashes
	uint32_t version;	// writeable copy of version
	uint32_t nPoK = 0;	// integer copy of PoK state
	static const unsigned int POK_BOOL_MASK = 0x00008000;
	static const unsigned int POK_DATA_MASK = 0xFFFF0000;
#ifdef TEST_VERBOSELY
	char buffer[512] = { 0 };
	char *buf = buffer;
	uint32_t i = 0;
#endif

	// copy the input buffer at input to a modifiable location at input512,
	input512 = (uint8_t*)malloc(len);		// allocate space for the copy
	memcpy((uint8_t*)input512, (uint8_t*)input, len);

#ifdef TEST_VERBOSELY
	fprintf(stderr, "drop_hash input: ");
	for (i=0; i<len; i++) sprintf(stderr, "%02x", input512[i]);
	fprintf(stderr, "\n");
#endif

	// store the version bytes
	memcpy((uint8_t *)&version, (uint8_t *)input, 4);

	// apply the first hash, yielding 512bits = 64 bytes
	drop_hash_512(input512, output512, len);

	// Now begins Proof of Knowledge
	//
	// Pull the data from the result for the Proof of Knowledge
	// (this is the 3rd and 4th of the first four bytes of the result)
	memcpy(&nPoK, (uint8_t *)output512, 4);	// yields big or little endian uint
	// keep only the two least significant bytes
#if BYTE_ORDER == LITTLE_ENDIAN
	nPoK &= 0xFFFF0000;		// bytes 3&4 of big endian are 1&2 of little endian
#else
	nPoK &= 0x0000FFFF;		// bytes 1&2 of big endian are 3&4 of little endian
#endif
	//
	// PoK part 2:
	// update the version variable with the masks and PoK value
	// according to the Proof of Knowledge setting
	version &= (~POK_BOOL_MASK);
	version |= (POK_DATA_MASK & nPoK);
#ifdef TEST_VERBOSELY
	fprintf(stderr, "new version field: %x\n", version);
#endif

	// and now write it back out to our copy of the input buffer
	memcpy((uint8_t *)input512, (uint8_t *)&version, 4);

	// apply a second ZR5 hash of the modified input, 512 bits in and out,
	// to the input modified with PoK. Length is still the original length
	drop_hash_512(input512, output512, len);

	// copy the left-most 256 bits (32 bytes) of the last hash into the output buffer
	memcpy((uint8_t *)output, (uint8_t *)output512, sizeof(output512)/2);

#ifdef TEST_VERBOSELY
	buf += sprintf(buf, "drop hash: ");
	for (i=0; i<32; i++) { buf += sprintf(buf, "%02x", output512[i]); }
	fprintf(stderr, "%s\n", buffer); buf = buffer;
#endif

	free(input512);
	return;
}


// WARNING: This routine might work for little endian numbers!
uint32_t getleastsig32( uint8_t* buffer, unsigned int nIndex)
{
	uint32_t *	ptr = NULL;
	uint32_t	result;

	ptr = (uint32_t *)buffer;
	result = ptr[nIndex % sizeof(uint32_t)];

	return(result);
}

#ifdef __cplusplus
}
#endif
