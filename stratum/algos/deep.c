#include <string.h>
#include <stdlib.h>

#include <sha3/sph_luffa.h>
#include <sha3/sph_cubehash.h>
#include <sha3/sph_echo.h>

void deep_hash(const char* input, char* output, uint32_t len)
{
    sph_luffa512_context    ctx_luffa;
    sph_cubehash512_context ctx_cubehash;
    sph_echo512_context     ctx_echo;

    char hash1[64], hash2[64];

    sph_luffa512_init(&ctx_luffa);
    sph_luffa512(&ctx_luffa, (const void*) input, len);
    sph_luffa512_close(&ctx_luffa, (void*) &hash1);

    sph_cubehash512_init(&ctx_cubehash);
    sph_cubehash512(&ctx_cubehash, (const void*) &hash1, 64);
    sph_cubehash512_close(&ctx_cubehash, (void*) &hash2);

    sph_echo512_init(&ctx_echo);
    sph_echo512(&ctx_echo, (const void*) &hash2, 64);
    sph_echo512_close(&ctx_echo, (void*) &hash1);

    memcpy(output, &hash1, 32);
}
