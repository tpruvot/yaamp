
#include <gmp.h>
#include <stdbool.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <float.h>
#include <math.h>

#include "magimath.h"

#include "../sha3/sph_sha2.h"
#include "../sha3/sph_keccak.h"
#include "../sha3/sph_haval.h"
#include "../sha3/sph_tiger.h"
#include "../sha3/sph_whirlpool.h"
#include "../sha3/sph_ripemd.h"

static void mpz_set_uint256(mpz_t r, uint8_t *u)
{
	mpz_import(r, 32 / sizeof(unsigned long), -1, sizeof(unsigned long), -1, 0, u);
}

static void mpz_set_uint512(mpz_t r, uint8_t *u)
{
	mpz_import(r, 64 / sizeof(unsigned long), -1, sizeof(unsigned long), -1, 0, u);
}

static void set_one_if_zero(uint8_t *hash512)
{
	int i;
	for (i=0; i < 32; i++) {
		if (hash512[i] != 0) {
			return;
		}
	}
	hash512[0] = 1;
}

#define BITS_PER_DIGIT 3.32192809488736234787
#define EPS (DBL_EPSILON)

#define NM7M 5
#define SW_DIVS 5
#define M7_MIDSTATE_LEN 76
int m7m_hash(const char* input, char* output, uint32_t len)
{
	uint32_t data[32] __attribute__((aligned(128)));
	uint8_t bhash[7][64] __attribute__((aligned(32)));
	uint32_t hash[8] __attribute__((aligned(32)));
	uint32_t *data_p64 = data + (M7_MIDSTATE_LEN / sizeof(data[0]));
	uint8_t *bdata = 0;
	int i, j, rc = 0;
	int bytes, nnNonce2;

	mpz_t bns[8];
	mpz_t product;
	mpz_init(product);

	for(i=0; i < 8; i++){
		mpz_init(bns[i]);
	}

	memcpy(data, input, len /*80*/);

	sph_sha256_context       ctx_final_sha256;

	sph_sha256_context       ctx_sha256;
	sph_sha512_context       ctx_sha512;
	sph_keccak512_context    ctx_keccak;
	sph_whirlpool_context    ctx_whirlpool;
	sph_haval256_5_context   ctx_haval;
	sph_tiger_context        ctx_tiger;
	sph_ripemd160_context    ctx_ripemd;

	sph_sha256_init(&ctx_sha256);
	sph_sha256 (&ctx_sha256, data, M7_MIDSTATE_LEN);

	sph_sha512_init(&ctx_sha512);
	sph_sha512 (&ctx_sha512, data, M7_MIDSTATE_LEN);

	sph_keccak512_init(&ctx_keccak);
	sph_keccak512 (&ctx_keccak, data, M7_MIDSTATE_LEN);

	sph_whirlpool_init(&ctx_whirlpool);
	sph_whirlpool (&ctx_whirlpool, data, M7_MIDSTATE_LEN);

	sph_haval256_5_init(&ctx_haval);
	sph_haval256_5 (&ctx_haval, data, M7_MIDSTATE_LEN);

	sph_tiger_init(&ctx_tiger);
	sph_tiger (&ctx_tiger, data, M7_MIDSTATE_LEN);

	sph_ripemd160_init(&ctx_ripemd);
	sph_ripemd160 (&ctx_ripemd, data, M7_MIDSTATE_LEN);


	nnNonce2 = (int)(data[19]/2);
	memset(bhash, 0, 7 * 64);

	sph_sha256 (&ctx_sha256, data_p64, 80 - M7_MIDSTATE_LEN);
	sph_sha256_close(&ctx_sha256, (void*)(bhash[0]));

	sph_sha512 (&ctx_sha512, data_p64, 80 - M7_MIDSTATE_LEN);
	sph_sha512_close(&ctx_sha512, (void*)(bhash[1]));

	sph_keccak512 (&ctx_keccak, data_p64, 80 - M7_MIDSTATE_LEN);
	sph_keccak512_close(&ctx_keccak, (void*)(bhash[2]));

	sph_whirlpool (&ctx_whirlpool, data_p64, 80 - M7_MIDSTATE_LEN);
	sph_whirlpool_close(&ctx_whirlpool, (void*)(bhash[3]));

	sph_haval256_5 (&ctx_haval, data_p64, 80 - M7_MIDSTATE_LEN);
	sph_haval256_5_close(&ctx_haval, (void*)(bhash[4]));

	sph_tiger (&ctx_tiger, data_p64, 80 - M7_MIDSTATE_LEN);
	sph_tiger_close(&ctx_tiger, (void*)(bhash[5]));

	sph_ripemd160 (&ctx_ripemd, data_p64, 80 - M7_MIDSTATE_LEN);
	sph_ripemd160_close(&ctx_ripemd, (void*)(bhash[6]));

	for(i=0; i < 7; i++) {
		set_one_if_zero(bhash[i]);
		mpz_set_uint512(bns[i], bhash[i]);
	}

	mpz_set_ui(bns[7],0);

	for(i=0; i < 7; i++){
		mpz_add(bns[7], bns[7], bns[i]);
	}

	mpz_set_ui(product, 1);

	for(i=0; i < 8; i++){
		mpz_mul(product, product, bns[i]);
	}

	mpz_pow_ui(product, product, 2);

	bytes = mpz_sizeinbase(product, 256);
	bdata = (uint8_t*) realloc(bdata, bytes);
	mpz_export((void *)bdata, NULL, -1, 1, 0, 0, product);

	sph_sha256_init(&ctx_final_sha256);
	sph_sha256 (&ctx_final_sha256, bdata, bytes);
	sph_sha256_close(&ctx_final_sha256, (void*)(hash));

	int digits=(int)((sqrt((double)(nnNonce2))*(1.+EPS))/9000+75);
	int iterations=20;
	mpf_set_default_prec((long int)(digits*BITS_PER_DIGIT+16));

	mpz_t magipi;
	mpz_t magisw;
	mpf_t magifpi;
	mpf_t mpa1, mpb1, mpt1, mpp1;
	mpf_t mpa2, mpb2, mpt2, mpp2;
	mpf_t mpsft;

	mpz_init(magipi);
	mpz_init(magisw);
	mpf_init(magifpi);
	mpf_init(mpsft);
	mpf_init(mpa1);
	mpf_init(mpb1);
	mpf_init(mpt1);
	mpf_init(mpp1);

	mpf_init(mpa2);
	mpf_init(mpb2);
	mpf_init(mpt2);
	mpf_init(mpp2);

	uint32_t usw_ = sw_(nnNonce2, SW_DIVS);
	if (usw_ < 1) usw_ = 1;
	mpz_set_ui(magisw, usw_);
	uint32_t mpzscale = mpz_size(magisw);

	for(i=0; i < NM7M; i++)
	{
		if (mpzscale > 1000) mpzscale = 1000;
		else if (mpzscale < 1) mpzscale = 1;

		mpf_set_ui(mpa1, 1);
		mpf_set_ui(mpb1, 2);
		mpf_set_d(mpt1, 0.25*mpzscale);
		mpf_set_ui(mpp1, 1);
		mpf_sqrt(mpb1, mpb1);
		mpf_ui_div(mpb1, 1, mpb1);
		mpf_set_ui(mpsft, 10);

		for(j=0; j <= iterations; j++)
		{
			mpf_add(mpa2, mpa1, mpb1);
			mpf_div_ui(mpa2, mpa2, 2);
			mpf_mul(mpb2, mpa1, mpb1);
			mpf_abs(mpb2, mpb2);
			mpf_sqrt(mpb2, mpb2);
			mpf_sub(mpt2, mpa1, mpa2);
			mpf_abs(mpt2, mpt2);
			mpf_sqrt(mpt2, mpt2);
			mpf_mul(mpt2, mpt2, mpp1);
			mpf_sub(mpt2, mpt1, mpt2);
			mpf_mul_ui(mpp2, mpp1, 2);
			mpf_swap(mpa1, mpa2);
			mpf_swap(mpb1, mpb2);
			mpf_swap(mpt1, mpt2);
			mpf_swap(mpp1, mpp2);
		}

		mpf_add(magifpi, mpa1, mpb1);
		mpf_pow_ui(magifpi, magifpi, 2);
		mpf_div_ui(magifpi, magifpi, 4);
		mpf_abs(mpt1, mpt1);
		mpf_div(magifpi, magifpi, mpt1);

		mpf_pow_ui(mpsft, mpsft, digits/2);
		mpf_mul(magifpi, magifpi, mpsft);

		mpz_set_f(magipi, magifpi);

		mpz_add(product,product,magipi);
		mpz_add(product,product,magisw);

		mpz_set_uint256(bns[0], (void*)(hash));
		mpz_add(bns[7], bns[7], bns[0]);

		mpz_mul(product, product, bns[7]);
		mpz_cdiv_q(product, product, bns[0]);
		if (mpz_sgn(product) <= 0) mpz_set_ui(product,1);

		bytes = mpz_sizeinbase(product, 256);
		mpzscale = bytes;
		bdata = (uint8_t *)realloc(bdata, bytes);
		mpz_export(bdata, NULL, -1, 1, 0, 0, product);

		sph_sha256_init(&ctx_final_sha256);
		sph_sha256 (&ctx_final_sha256, bdata, bytes);
		sph_sha256_close(&ctx_final_sha256, (void*)(hash));
	}

	mpz_clear(magipi);
	mpz_clear(magisw);
	mpf_clear(magifpi);
	mpf_clear(mpsft);
	mpf_clear(mpa1);
	mpf_clear(mpb1);
	mpf_clear(mpt1);
	mpf_clear(mpp1);

	mpf_clear(mpa2);
	mpf_clear(mpb2);
	mpf_clear(mpt2);
	mpf_clear(mpp2);

	for(i=0; i < 8; i++) {
		mpz_clear(bns[i]);
	}

	mpz_clear(product);
	free(bdata);

	memcpy(output, (void*) hash, 32);
}

