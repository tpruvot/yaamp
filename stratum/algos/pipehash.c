/*
 * pipehash cryptographic hash function
 *
 * Copyright (c) 2018, uou pipe developer 
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 *    list of conditions and the following disclaimer.
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 *    this list of conditions and the following disclaimer in the documentation
 *    and/or other materials provided with the distribution.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS" AND
 * ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT OWNER OR CONTRIBUTORS BE LIABLE FOR
 * ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR SERVICES;
 * LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER CAUSED AND
 * ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY, OR TORT
 * (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE OF THIS
 * SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 *
 * The views and conclusions contained in the software and documentation are those
 * of the authors and should not be interpreted as representing official policies,
 * either expressed or implied, of the FreeBSD Project.
 */
#include "sha256.h"
#include <assert.h>
#include <string.h>
//#include "sph_blake.h"
//#include "sph_groestl.h"
#include <sha3/sph_keccak.h>
#include <sha3/sph_hefty1.h>
#include "pipehash.h"
#include <sha3/sph_blake.h>
#include <sha3/sph_groestl.h>


void shift_left(unsigned char *data, unsigned int shift){
    unsigned char a[32];
    memcpy(a,data,32);

    memset(data,0,32);

    int k = shift / 8;
    shift = shift % 8;
    for (int i = 0; i < 32; i++)
    {
        if (i+k+1 < 32 && shift != 0)
            data[i+k+1] |= (a[i] >> (8-shift));
        if (i+k < 32)
            data[i+k] |= (a[i] << shift);
    }
}

void shift_right(unsigned char *data, unsigned int shift){
    unsigned char a[32];
    memcpy(a,data,32);

    memset(data,0,32);

    int k = shift / 8;//How bytes to shift
    shift = shift % 8; //How bits to shift in current byte
    for (int i = 0; i < 32; i++)
    {
        if (i-k-1 >= 0 && shift != 0)
            data[i-k-1] |= (a[i] << (8-shift));
        if (i-k >= 0)
            data[i-k] |= (a[i] >> shift);
    }
}

void or_op(unsigned char *data,int val){
    data[0] |= (int)val;
}

int and_result_not_equ_zero(unsigned char *in1,unsigned char *in2){
    for (int i = 0; i < 32; i++){
        if(in1[i]&in2[i])
            return 1;
    }
    return 0;
}


/* Combine stop 64-bits from each hash into a single hash */
void combine_hashes(unsigned char*  hash1, unsigned char* hash2, unsigned char* hash3, unsigned char* hash4,unsigned char* output)
{
    unsigned char mask[32];
    memset(mask,0,32);mask[31]=0x80;

    unsigned char hash[4][32];
    memcpy(hash[0],hash1,32);
    memcpy(hash[1],hash2,32);
    memcpy(hash[2],hash3,32);
    memcpy(hash[3],hash4,32);


    /* Transpose first 64 bits of each hash into final */
    unsigned char final[32]={0};
    for (unsigned int i = 0; i < 64; i++) {
        for (unsigned int j = 0; j < 4; j++) {
            shift_left(final,1);
            if (and_result_not_equ_zero(hash[j] , mask))
                or_op(final,1);
        }
        shift_right(mask,1);
    }

    memcpy(output,final,32);
}

/* Combines top 64-bits from each hash into a single hash */
static void cpu_combine_hashes(uint32_t *out, const uint32_t *hash1, const uint32_t *hash2, const uint32_t *hash3, const uint32_t *hash4)
{
    const uint32_t *hash[4] = { hash1, hash2, hash3, hash4 };
    int bits;
    unsigned int i;
    uint32_t mask;
    unsigned int k;

    /* Transpose first 64 bits of each hash into out */
    memset(out, 0, 32);
    bits = 0;
    for (i = 7; i >= 6; i--) {
        for (mask = 0x80000000; mask; mask >>= 1) {
            for (k = 0; k < 4; k++) {
                out[(255 - bits) / 32] <<= 1;
                if ((hash[k][i] & mask) != 0)
                    out[(255 - bits) / 32] |= 1;
                bits++;
            }
        }
    }
}

void pipe_hash(const char *input,char *output,unsigned int len)
{
    unsigned char hash1[32];
    HEFTY1(input, len , hash1);

    /* HEFTY1 is new, so take an extra security measure to eliminate
     * the possiblity of collisions:
     *
     *     Hash(x) = SHA256(x + HEFTY1(x))
     *
     * N.B. '+' is concatenation.
     */
    unsigned char hash2[32];
    SHA256_CTX ctx;
    SHA256_Init(&ctx);
    SHA256_Update(&ctx,input,len);
    SHA256_Update(&ctx, hash1, 32);
    SHA256_Final(hash2, &ctx);

    /* Additional security: Do not rely on a single cryptographic hash
     * function.  Instead, combine the outputs of 4 of the most secure
     * cryptographic hash functions-- SHA256, KECCAK512, GROESTL512
     * and BLAKE512.
     */

    unsigned char hash3[64];
    sph_keccak512_context keccakCtx;
    sph_keccak512_init(&keccakCtx);
    sph_keccak512(&keccakCtx,input,len);
    sph_keccak512(&keccakCtx, hash1, 32);
    sph_keccak512_close(&keccakCtx, (void *)hash3);

    unsigned char hash4[64];
    sph_groestl512_context groestlCtx;
    sph_groestl512_init(&groestlCtx);
    sph_groestl512(&groestlCtx,input,len);
    sph_groestl512(&groestlCtx, hash1, 32);
    sph_groestl512_close(&groestlCtx, (void *)hash4);

    unsigned char hash5[64];
    sph_blake512_context blakeCtx;
    sph_blake512_init(&blakeCtx);
    sph_blake512(&blakeCtx,input,len);
    sph_blake512(&blakeCtx, hash1, 32);
    sph_blake512_close(&blakeCtx, (void *)hash5);

    cpu_combine_hashes(output,hash2, hash3, hash4, hash5);
}