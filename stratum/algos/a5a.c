#include <float.h>
#include <math.h>
#include <gmp.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>
#include "a5amath.h"

#include "../sha3/sph_sha2.h"
#include "../sha3/sph_keccak.h"
#include "../sha3/sph_whirlpool.h"
#include "../sha3/sph_ripemd.h"

static void mpz_set_uint256(mpz_t r, uint8_t *u)
{
    mpz_import(r, 32 / sizeof(unsigned long), -1, sizeof(unsigned long), -1, 0, u);
}

static void mpz_get_uint256(mpz_t r, uint8_t *u)
{
    u=0;
    mpz_export(u, 0, -1, sizeof(unsigned long), -1, 0, r);
}

static void mpz_set_uint512(mpz_t r, uint8_t *u)
{
    mpz_import(r, 64 / sizeof(unsigned long), -1, sizeof(unsigned long), -1, 0, u);
}

static void set_one_if_zero(uint8_t *hash512) {
  int i;
    for (i = 0; i < 32; i++) {
        if (hash512[i] != 0) {
            return;
        }
    }
    hash512[0] = 1;
}

#define BITS_PER_DIGIT 3.32192809488736234787
//#define EPS (std::numeric_limits<double>::epsilon())
#define EPS (DBL_EPSILON)

#define Na5a 5
#define SW_DIVS 5
//#define SW_MAX 1000

void a5a_hash(const char* input, char* output, uint32_t len)
{
    unsigned int nnNonce;
    uint32_t pdata[32];
    memcpy(pdata, input, 80);
//    memcpy(&nnNonce, input+76, 4);

    int i, j, bytes, nnNonce2;
    nnNonce2 = (int)(pdata[19]/2);
    size_t sz = 80;
    uint8_t bhash[5][64];
    uint32_t hash[6];
    memset(bhash, 0, 5 * 64);

    sph_sha256_context       ctx_final_sha256;

    sph_sha256_context       ctx_sha256;
    sph_sha512_context       ctx_sha512;
    sph_keccak512_context    ctx_keccak;
    sph_whirlpool_context    ctx_whirlpool;
    sph_ripemd160_context    ctx_ripemd;

    sph_sha256_init(&ctx_sha256);
    // ZSHA256;
    sph_sha256 (&ctx_sha256, input, sz);
    sph_sha256_close(&ctx_sha256, (void*)(bhash[0]));

    sph_sha512_init(&ctx_sha512);
    // ZSHA512;
    sph_sha512 (&ctx_sha512, input, sz);
    sph_sha512_close(&ctx_sha512, (void*)(bhash[1]));

    sph_keccak512_init(&ctx_keccak);
    // ZKECCAK;
    sph_keccak512 (&ctx_keccak, input, sz);
    sph_keccak512_close(&ctx_keccak, (void*)(bhash[2]));

    sph_whirlpool_init(&ctx_whirlpool);
    // ZWHIRLPOOL;
    sph_whirlpool (&ctx_whirlpool, input, sz);
    sph_whirlpool_close(&ctx_whirlpool, (void*)(bhash[3]));

    sph_ripemd160_init(&ctx_ripemd);
    // ZRIPEMD;
    sph_ripemd160 (&ctx_ripemd, input, sz);
    sph_ripemd160_close(&ctx_ripemd, (void*)(bhash[4]));

//    printf("%s\n", hash[4].GetHex().c_str());

    mpz_t bns[6];
    for(i=0; i < 6; i++){
        mpz_init(bns[i]);
    }
    //Take care of zeros and load gmp
    for(i=0; i < 5; i++){
	set_one_if_zero(bhash[i]);
	mpz_set_uint512(bns[i],bhash[i]);
    }

    mpz_set_ui(bns[5],0);
    for(i=0; i < 5; i++)
	mpz_add(bns[5], bns[5], bns[i]);

    mpz_t product;
    mpz_init(product);
    mpz_set_ui(product,1);
//    mpz_pow_ui(bns[5], bns[5], 2);
    for(i=0; i < 6; i++){
	mpz_mul(product,product,bns[i]);
    }
    mpz_pow_ui(product, product, 2);

    bytes = mpz_sizeinbase(product, 256);
//    printf("a5a data space: %iB\n", bytes);
    char *data = (char*)malloc(bytes);
    mpz_export(data, NULL, -1, 1, 0, 0, product);

    sph_sha256_init(&ctx_final_sha256);
    // ZSHA256;
    sph_sha256 (&ctx_final_sha256, data, bytes);
    sph_sha256_close(&ctx_final_sha256, (void*)(hash));
    free(data);

    int digits=(int)((sqrt((double)(nnNonce2))*(1.+EPS))/9000+75);
//    int iterations=(int)((sqrt((double)(nnNonce2))+EPS)/500+350); // <= 500
//    int digits=100;
    int iterations=20; // <= 500
    mpf_set_default_prec((long int)(digits*BITS_PER_DIGIT+16));

    mpz_t a5api;
    mpz_t a5asw;
    mpf_t a5afpi;
    mpf_t mpa1, mpb1, mpt1, mpp1;
    mpf_t mpa2, mpb2, mpt2, mpp2;
    mpf_t mpsft;

    mpz_init(a5api);
    mpz_init(a5asw);
    mpf_init(a5afpi);
    mpf_init(mpsft);
    mpf_init(mpa1);
    mpf_init(mpb1);
    mpf_init(mpt1);
    mpf_init(mpp1);

    mpf_init(mpa2);
    mpf_init(mpb2);
    mpf_init(mpt2);
    mpf_init(mpp2);

    uint32_t usw_;
    usw_ = sw_(nnNonce2, SW_DIVS);
    if (usw_ < 1) usw_ = 1;
//    if(fDebuga5a) printf("usw_: %d\n", usw_);
    mpz_set_ui(a5asw, usw_);
    uint32_t mpzscale=mpz_size(a5asw);
for(i=0; i < Na5a; i++)
{
    if (mpzscale > 1000) {
      mpzscale = 1000;
    }
    else if (mpzscale < 1) {
      mpzscale = 1;
    }
//    if(fDebuga5a) printf("mpzscale: %d\n", mpzscale);

    mpf_set_ui(mpa1, 1);
    mpf_set_ui(mpb1, 2);
    mpf_set_d(mpt1, 0.25*mpzscale);
    mpf_set_ui(mpp1, 1);
    mpf_sqrt(mpb1, mpb1);
    mpf_ui_div(mpb1, 1, mpb1);
    mpf_set_ui(mpsft, 10);

    for(j=0; j <= iterations; j++)
    {
	mpf_add(mpa2, mpa1,  mpb1);
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
    mpf_add(a5afpi, mpa1, mpb1);
    mpf_pow_ui(a5afpi, a5afpi, 2);
    mpf_div_ui(a5afpi, a5afpi, 4);
    mpf_abs(mpt1, mpt1);
    mpf_div(a5afpi, a5afpi, mpt1);

//    mpf_out_str(stdout, 10, digits+2, a5afpi);

    mpf_pow_ui(mpsft, mpsft, digits/2);
    mpf_mul(a5afpi, a5afpi, mpsft);

    mpz_set_f(a5api, a5afpi);

//mpz_set_ui(a5api,1);

    mpz_add(product,product,a5api);
    mpz_add(product,product,a5asw);

    mpz_set_uint256(bns[0], (void*)(hash));
    mpz_add(bns[5], bns[5], bns[0]);

    mpz_mul(product,product,bns[5]);
    mpz_cdiv_q (product, product, bns[0]);
    if (mpz_sgn(product) <= 0) mpz_set_ui(product,1);

    bytes = mpz_sizeinbase(product, 256);
    mpzscale=bytes;
//    printf("a5a data space: %iB\n", bytes);
    char *bdata = (char*)malloc(bytes);
    mpz_export(bdata, NULL, -1, 1, 0, 0, product);

    sph_sha256_init(&ctx_final_sha256);
    // ZSHA256;
    sph_sha256 (&ctx_final_sha256, bdata, bytes);
    sph_sha256_close(&ctx_final_sha256, (void*)(hash));
    free(bdata);
}
    //Free the memory
    for(i=0; i < 6; i++){
	mpz_clear(bns[i]);
    }
//    mpz_clear(dSpectralWeight);
    mpz_clear(product);

    mpz_clear(a5api);
    mpz_clear(a5asw);
    mpf_clear(a5afpi);
    mpf_clear(mpsft);
    mpf_clear(mpa1);
    mpf_clear(mpb1);
    mpf_clear(mpt1);
    mpf_clear(mpp1);

    mpf_clear(mpa2);
    mpf_clear(mpb2);
    mpf_clear(mpt2);
    mpf_clear(mpp2);

    memcpy(output, hash, 32);
}