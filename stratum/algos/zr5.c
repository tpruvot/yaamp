#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>

#include "../sha3/sph_blake.h"
#include "../sha3/sph_groestl.h"
#include "../sha3/sph_jh.h"
#include "../sha3/sph_keccak.h"
#include "../sha3/sph_skein.h"

//#define TEST_VERBOSELY

#define ZR_BLAKE   0
#define ZR_GROESTL 1
#define ZR_JH      2
#define ZR_SKEIN   3

#define POK_BOOL_MASK 0x00008000
#define POK_DATA_MASK 0xFFFF0000

#define ARRAY_SIZE(arr) (sizeof(arr) / sizeof((arr)[0]))

static const int permut[][4] = {
	{0, 1, 2, 3},
	{0, 1, 3, 2},
	{0, 2, 1, 3},
	{0, 2, 3, 1},
	{0, 3, 1, 2},
	{0, 3, 2, 1},
	{1, 0, 2, 3},
	{1, 0, 3, 2},
	{1, 2, 0, 3},
	{1, 2, 3, 0},
	{1, 3, 0, 2},
	{1, 3, 2, 0},
	{2, 0, 1, 3},
	{2, 0, 3, 1},
	{2, 1, 0, 3},
	{2, 1, 3, 0},
	{2, 3, 0, 1},
	{2, 3, 1, 0},
	{3, 0, 1, 2},
	{3, 0, 2, 1},
	{3, 1, 0, 2},
	{3, 1, 2, 0},
	{3, 2, 0, 1},
	{3, 2, 1, 0}
};

static void zr5_hash_512(const char* input, char* output, uint32_t len)
{
	sph_keccak512_context ctx_keccak;
	sph_blake512_context ctx_blake;
	sph_groestl512_context ctx_groestl;
	sph_jh512_context ctx_jh;
	sph_skein512_context ctx_skein;

	uint32_t hash[5][16];
	char *ph = (char *)hash[0];

	sph_keccak512_init(&ctx_keccak);
	sph_keccak512(&ctx_keccak, (const void*) input, len);
	sph_keccak512_close(&ctx_keccak, (void*) &hash[0][0]);

	unsigned int norder = hash[0][0] % ARRAY_SIZE(permut); /* % 24 */
	int i;

#ifdef TEST_VERBOSELY
	for(i=0; i<len; i++) printf("%02x", (unsigned char)input[i]); printf("\n");
	for(i=0; i<32; i++) printf("%02x", (unsigned char)ph[i]); printf("\n");
#endif
	for(i = 0; i < 4; i++)
	{
		void* phash = (void*) &(hash[i][0]);
		void* pdest = (void*) &(hash[i+1][0]);

		//printf("permut %d\n", permut[norder][i]);
		switch (permut[norder][i]) {
		case ZR_BLAKE:
			sph_blake512_init(&ctx_blake);
			sph_blake512(&ctx_blake, (const void*) phash, 64);
			sph_blake512_close(&ctx_blake, pdest);
			break;
		case ZR_GROESTL:
			sph_groestl512_init(&ctx_groestl);
			sph_groestl512(&ctx_groestl, (const void*) phash, 64);
			sph_groestl512_close(&ctx_groestl, pdest);
			break;
		case ZR_JH:
			sph_jh512_init(&ctx_jh);
			sph_jh512(&ctx_jh, (const void*) phash, 64);
			sph_jh512_close(&ctx_jh, pdest);
			break;
		case ZR_SKEIN:
			sph_skein512_init(&ctx_skein);
			sph_skein512(&ctx_skein, (const void*) phash, 64);
			sph_skein512_close(&ctx_skein, pdest);
			break;
		default:
			break;
		}
	}
	memcpy(output, &hash[4], 32);
}


void zr5_hash(const char* input, char* output, uint32_t len)
{
        uint8_t *input512;      // writeable copy of input
        uint8_t output512[64];  // output of both zr5 hashes
        uint32_t version;       // writeable copy of version
        uint32_t nPoK = 0;      // integer copy of PoK state
#ifdef TEST_VERBOSELY
        char buffer[512] = { 0 };
        char *buf = buffer;
        uint32_t i = 0;
#endif

        // copy the input buffer at input to a modifiable location at input512,
        input512 = (uint8_t*)malloc(len);               // allocate space for the copy
        memcpy((uint8_t*)input512, (uint8_t*)input, len);

#ifdef TEST_VERBOSELY
        fprintf(stderr, "zr5 input: ");
        for (i=0; i<len; i++) sprintf(stderr, "%02x", input512[i]);
        fprintf(stderr, "\n");
#endif

        // store the version bytes
        memcpy((uint8_t *)&version, (uint8_t *)input, 4);

        // apply the first hash, yielding 512bits = 64 bytes
        zr5_hash_512(input512, output512, len);

        // Now begins Proof of Knowledge
        //
        // Pull the data from the result for the Proof of Knowledge
        // (this is the 3rd and 4th of the first four bytes of the result)
        memcpy(&nPoK, (uint8_t *)output512, 4); // yields big or little endian uint
        // keep only the two least significant bytes
#if BYTE_ORDER == LITTLE_ENDIAN
        nPoK &= 0xFFFF0000;             // bytes 3&4 of big endian are 1&2 of little endian
#else
        nPoK &= 0x0000FFFF;             // bytes 1&2 of big endian are 3&4 of little endian
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
        zr5_hash_512(input512, output512, len);

        // copy the left-most 256 bits (32 bytes) of the last hash into the output buffer
        memcpy((uint8_t *)output, (uint8_t *)output512, sizeof(output512)/2);

#ifdef TEST_VERBOSELY
        buf += sprintf(buf, "zr5 hash: ");
        for (i=0; i<32; i++) { buf += sprintf(buf, "%02x", output512[i]); }
        fprintf(stderr, "%s\n", buffer); buf = buffer;
#endif

        free(input512);
        return;
}
