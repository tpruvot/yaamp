
#include "skein2.h"

#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>

#include "../sha3/sph_skein.h"
#include "sha256.h"

#include <stdlib.h>

void skein2_hash(const char* input, char* output, uint32_t len)
{
    char temp[64];

    sph_skein512_context ctx_skien;
    sph_skein512_init(&ctx_skien);
    sph_skein512(&ctx_skien, input, len);
    sph_skein512_close(&ctx_skien, &temp);

    sph_skein512_init(&ctx_skien);
    sph_skein512(&ctx_skien, &temp, 64);
    sph_skein512_close(&ctx_skien, &output[0]);
}

