#include <memory.h>

#include "sha3/sph_blake.h"
#include "sha3/sph_groestl.h"
#include "sha3/sph_skein.h"
#include "sha3/sph_keccak.h"
#include "sha3/sph_cubehash.h"

#include "Lyra2.h"

void allium_hash(const char* input, char* output, uint32_t len)
{
    uint32_t hashA[8], hashB[8];

    sph_blake256_context     ctx_blake;
    sph_keccak256_context    ctx_keccak;
    sph_cubehash512_context  ctx_cubehash;
    sph_skein256_context     ctx_skein;
    sph_groestl256_context   ctx_groestl;

    sph_blake256_init(&ctx_blake);
    sph_blake256(&ctx_blake, input, 80);
    sph_blake256_close(&ctx_blake, hashA);

    sph_keccak256_init(&ctx_keccak);
    sph_keccak256(&ctx_keccak, hashA, 32);
    sph_keccak256_close(&ctx_keccak, hashB);

    LYRA2(hashA, 32, hashB, 32, hashB, 32, 1, 8, 8);

    sph_cubehash256_init(&ctx_cubehash);
    sph_cubehash256(&ctx_cubehash, hashA, 32);
    sph_cubehash256_close(&ctx_cubehash, hashB);

    LYRA2(hashA, 32, hashB, 32, hashB, 32, 1, 8, 8);

    sph_skein256_init(&ctx_skein);
    sph_skein256(&ctx_skein, hashA, 32);
    sph_skein256_close(&ctx_skein, hashB);

    sph_groestl256_init(&ctx_groestl);
    sph_groestl256(&ctx_groestl, hashB, 32);
    sph_groestl256_close(&ctx_groestl, hashA);

    memcpy(output, hashA, 32);
}
