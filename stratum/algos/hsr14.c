#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "../sha3/sph_blake.h"
#include "../sha3/sph_bmw.h"
#include "../sha3/sph_groestl.h"
#include "../sha3/sph_jh.h"
#include "../sha3/sph_keccak.h"
#include "../sha3/sph_skein.h"
#include "../sha3/sph_luffa.h"
#include "../sha3/sph_cubehash.h"
#include "../sha3/sph_shavite.h"
#include "../sha3/sph_simd.h"
#include "../sha3/sph_echo.h"
#include "../sha3/sph_hamsi.h"
#include "../sha3/sph_fugue.h"

#include "sm3.h"

#include "common.h"

void hsr_hash(const char* input, char* output, uint32_t len)
{
    sph_blake512_context    ctx_blake;
    sph_bmw512_context      ctx_bmw;
    sph_groestl512_context  ctx_groestl;
    sph_skein512_context    ctx_skein;
    sph_jh512_context       ctx_jh;
    sph_keccak512_context   ctx_keccak;
    sph_luffa512_context    ctx_luffa1;
    sph_cubehash512_context ctx_cubehash1;
    sph_shavite512_context  ctx_shavite1;
    sph_simd512_context     ctx_simd1;
    sph_echo512_context     ctx_echo1;
    sm3_ctx_t               ctx_sm3;
    sph_hamsi512_context    ctx_hamsi1;
    sph_fugue512_context    ctx_fugue1;

    uint8_t _ALIGN(128) hash[64];

    sph_blake512_init(&ctx_blake);
    sph_blake512(&ctx_blake, input, len);
    sph_blake512_close(&ctx_blake, hash);

    sph_bmw512_init(&ctx_bmw);
    sph_bmw512(&ctx_bmw, hash, 64);
    sph_bmw512_close(&ctx_bmw, hash);

    sph_groestl512_init(&ctx_groestl);
    sph_groestl512(&ctx_groestl, hash, 64);
    sph_groestl512_close(&ctx_groestl, hash);

    sph_skein512_init(&ctx_skein);
    sph_skein512(&ctx_skein, hash, 64);
    sph_skein512_close (&ctx_skein, hash);

    sph_jh512_init(&ctx_jh);
    sph_jh512(&ctx_jh, hash, 64);
    sph_jh512_close(&ctx_jh, hash);

    sph_keccak512_init(&ctx_keccak);
    sph_keccak512(&ctx_keccak, hash, 64);
    sph_keccak512_close(&ctx_keccak, hash);

    sph_luffa512_init(&ctx_luffa1);
    sph_luffa512(&ctx_luffa1, hash, 64);
    sph_luffa512_close(&ctx_luffa1, hash);

    sph_cubehash512_init(&ctx_cubehash1);
    sph_cubehash512(&ctx_cubehash1, hash, 64);
    sph_cubehash512_close(&ctx_cubehash1, hash);

    sph_shavite512_init(&ctx_shavite1);
    sph_shavite512(&ctx_shavite1, hash, 64);
    sph_shavite512_close(&ctx_shavite1, hash);

    sph_simd512_init(&ctx_simd1);
    sph_simd512(&ctx_simd1, hash, 64);
    sph_simd512_close(&ctx_simd1, hash);

    sph_echo512_init (&ctx_echo1);
    sph_echo512(&ctx_echo1, hash, 64);
    sph_echo512_close(&ctx_echo1, hash);

    sm3_init(&ctx_sm3);
    sm3_update(&ctx_sm3, hash, 64);
    memset(hash, 0, sizeof hash);
    sm3_close(&ctx_sm3, hash);

    sph_hamsi512_init(&ctx_hamsi1);
    sph_hamsi512(&ctx_hamsi1, hash, 64);
    sph_hamsi512_close(&ctx_hamsi1, hash);

    sph_fugue512_init(&ctx_fugue1);
    sph_fugue512(&ctx_fugue1, hash, 64);
    sph_fugue512_close(&ctx_fugue1, hash);

    memcpy(output, hash, 32);
}
