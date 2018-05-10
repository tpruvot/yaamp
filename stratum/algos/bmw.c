#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>

#include "bmw.h"

#include "../sha3/sph_bmw.h"

void bmw_hash(const char* input, char* output, uint32_t len)
{
    uint32_t hash[32];
    sph_bmw256_context ctx_bmw;

    sph_bmw256_init(&ctx_bmw);
    sph_bmw256 (&ctx_bmw, input, 80);
    sph_bmw256_close(&ctx_bmw, hash);

    memcpy(output, hash, 32);
}

