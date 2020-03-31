// Minotaur hash

#include "minotaur.h"

#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "sha3/sph_blake.h"
#include "sha3/sph_bmw.h"
#include "sha3/sph_groestl.h"
#include "sha3/sph_jh.h"
#include "sha3/sph_keccak.h"
#include "sha3/sph_skein.h"
#include "sha3/sph_luffa.h"
#include "sha3/sph_cubehash.h"
#include "sha3/sph_shavite.h"
#include "sha3/sph_simd.h"
#include "sha3/sph_echo.h"
#include "sha3/sph_hamsi.h"
#include "sha3/sph_fugue.h"
#include "sha3/sph_shabal.h"
#include "sha3/sph_whirlpool.h"
#include "sha3/sph_sha2.h"

#ifndef _MSC_VER
#define _ALIGN(x) __attribute__ ((aligned(x)))
#else
#define _ALIGN(x) __declspec(align(x))
#endif

// Config
#define MINOTAUR_ALGO_COUNT	16

typedef struct TortureNode TortureNode;
typedef struct TortureGarden TortureGarden;

// Graph of hash algos plus SPH contexts
struct TortureGarden {
    sph_blake512_context context_blake;
    sph_bmw512_context context_bmw;
    sph_cubehash512_context context_cubehash;
    sph_echo512_context context_echo;
    sph_fugue512_context context_fugue;
    sph_groestl512_context context_groestl;
    sph_hamsi512_context context_hamsi;
    sph_jh512_context context_jh;
    sph_keccak512_context context_keccak;
    sph_luffa512_context context_luffa;
    sph_shabal512_context context_shabal;
    sph_shavite512_context context_shavite;
    sph_simd512_context context_simd;
    sph_skein512_context context_skein;
    sph_whirlpool_context context_whirlpool;
    sph_sha512_context context_sha2;

    struct TortureNode {
        unsigned int algo;
        TortureNode *childLeft;
        TortureNode *childRight;
    } nodes[22];
};

// Get a 64-byte hash for given 64-byte input, using given TortureGarden contexts and given algo index
void get_hash(void *output, const void *input, TortureGarden *garden, unsigned int algo)
{
	unsigned char _ALIGN(64) hash[64];

    switch (algo) {
        case 0:
            sph_blake512_init(&garden->context_blake);
            sph_blake512(&garden->context_blake, input, 64);
            sph_blake512_close(&garden->context_blake, hash);
            break;
        case 1:
            sph_bmw512_init(&garden->context_bmw);
            sph_bmw512(&garden->context_bmw, input, 64);
            sph_bmw512_close(&garden->context_bmw, hash);
            break;
        case 2:
            sph_cubehash512_init(&garden->context_cubehash);
            sph_cubehash512(&garden->context_cubehash, input, 64);
            sph_cubehash512_close(&garden->context_cubehash, hash);
            break;
        case 3:
            sph_echo512_init(&garden->context_echo);
            sph_echo512(&garden->context_echo, input, 64);
            sph_echo512_close(&garden->context_echo, hash);
            break;
        case 4:
            sph_fugue512_init(&garden->context_fugue);
            sph_fugue512(&garden->context_fugue, input, 64);
            sph_fugue512_close(&garden->context_fugue, hash);
            break;
        case 5:
            sph_groestl512_init(&garden->context_groestl);
            sph_groestl512(&garden->context_groestl, input, 64);
            sph_groestl512_close(&garden->context_groestl, hash);
            break;
        case 6:
            sph_hamsi512_init(&garden->context_hamsi);
            sph_hamsi512(&garden->context_hamsi, input, 64);
            sph_hamsi512_close(&garden->context_hamsi, hash);
            break;
        case 7:
            sph_sha512_init(&garden->context_sha2);
            sph_sha512(&garden->context_sha2, input, 64);
            sph_sha512_close(&garden->context_sha2, hash);
            break;
        case 8:
            sph_jh512_init(&garden->context_jh);
            sph_jh512(&garden->context_jh, input, 64);
            sph_jh512_close(&garden->context_jh, hash);
            break;
        case 9:
            sph_keccak512_init(&garden->context_keccak);
            sph_keccak512(&garden->context_keccak, input, 64);
            sph_keccak512_close(&garden->context_keccak, hash);
            break;
        case 10:
            sph_luffa512_init(&garden->context_luffa);
            sph_luffa512(&garden->context_luffa, input, 64);
            sph_luffa512_close(&garden->context_luffa, hash);
            break;
        case 11:
            sph_shabal512_init(&garden->context_shabal);
            sph_shabal512(&garden->context_shabal, input, 64);
            sph_shabal512_close(&garden->context_shabal, hash);
            break;
        case 12:
            sph_shavite512_init(&garden->context_shavite);
            sph_shavite512(&garden->context_shavite, input, 64);
            sph_shavite512_close(&garden->context_shavite, hash);
            break;
        case 13:
            sph_simd512_init(&garden->context_simd);
            sph_simd512(&garden->context_simd, input, 64);
            sph_simd512_close(&garden->context_simd, hash);
            break;
        case 14:
            sph_skein512_init(&garden->context_skein);
            sph_skein512(&garden->context_skein, input, 64);
            sph_skein512_close(&garden->context_skein, hash);
            break;
        case 15:
            sph_whirlpool_init(&garden->context_whirlpool);
            sph_whirlpool(&garden->context_whirlpool, input, 64);
            sph_whirlpool_close(&garden->context_whirlpool, hash);
            break;
    }

    // Output the hash
    memcpy(output, hash, 64);
}

// Recursively traverse a given torture garden starting with a given hash and given node within the garden. The hash is overwritten with the final hash.
void traverse_garden(TortureGarden *garden, void *hash, TortureNode *node)
{
    unsigned char _ALIGN(64) partialHash[64];
    get_hash(partialHash, hash, garden, node->algo);

    if (partialHash[63] % 2 == 0) {                                     // Last byte of output hash is even
        if (node->childLeft != NULL)
            traverse_garden(garden, partialHash, node->childLeft);
    } else {                                                            // Last byte of output hash is odd
        if (node->childRight != NULL)
            traverse_garden(garden, partialHash, node->childRight);
    }

    memcpy(hash, partialHash, 64);
}

// Associate child nodes with a parent node
inline void link_nodes(TortureNode *parent, TortureNode *childLeft, TortureNode *childRight)
{
    parent->childLeft = childLeft;
    parent->childRight = childRight;
}

// Produce a 32-byte hash from 80-byte input data
void minotaur_hash(const char* input, char* output, uint32_t len)
{
    // Create torture garden nodes. Note that both sides of 19 and 20 lead to 21, and 21 has no children (to make traversal complete).
    // Every path through the garden stops at 7 nodes.
    TortureGarden garden;
    link_nodes(&garden.nodes[0], &garden.nodes[1], &garden.nodes[2]);
    link_nodes(&garden.nodes[1], &garden.nodes[3], &garden.nodes[4]);
    link_nodes(&garden.nodes[2], &garden.nodes[5], &garden.nodes[6]);
    link_nodes(&garden.nodes[3], &garden.nodes[7], &garden.nodes[8]);
    link_nodes(&garden.nodes[4], &garden.nodes[9], &garden.nodes[10]);
    link_nodes(&garden.nodes[5], &garden.nodes[11], &garden.nodes[12]);
    link_nodes(&garden.nodes[6], &garden.nodes[13], &garden.nodes[14]);
    link_nodes(&garden.nodes[7], &garden.nodes[15], &garden.nodes[16]);
    link_nodes(&garden.nodes[8], &garden.nodes[15], &garden.nodes[16]);
    link_nodes(&garden.nodes[9], &garden.nodes[15], &garden.nodes[16]);
    link_nodes(&garden.nodes[10], &garden.nodes[15], &garden.nodes[16]);
    link_nodes(&garden.nodes[11], &garden.nodes[17], &garden.nodes[18]);
    link_nodes(&garden.nodes[12], &garden.nodes[17], &garden.nodes[18]);
    link_nodes(&garden.nodes[13], &garden.nodes[17], &garden.nodes[18]);
    link_nodes(&garden.nodes[14], &garden.nodes[17], &garden.nodes[18]);
    link_nodes(&garden.nodes[15], &garden.nodes[19], &garden.nodes[20]);
    link_nodes(&garden.nodes[16], &garden.nodes[19], &garden.nodes[20]);
    link_nodes(&garden.nodes[17], &garden.nodes[19], &garden.nodes[20]);
    link_nodes(&garden.nodes[18], &garden.nodes[19], &garden.nodes[20]);
    link_nodes(&garden.nodes[19], &garden.nodes[21], &garden.nodes[21]);
    link_nodes(&garden.nodes[20], &garden.nodes[21], &garden.nodes[21]);
    garden.nodes[21].childLeft = NULL;
    garden.nodes[21].childRight = NULL;

    // Find initial sha512 hash
    unsigned char _ALIGN(64) hash[64];
	sph_sha512_init(&garden.context_sha2);
	sph_sha512(&garden.context_sha2, input, 80);
	sph_sha512_close(&garden.context_sha2, hash);

    // Assign algos to torture garden nodes based on initial hash
    for (int i = 0; i < 22; i++)
        garden.nodes[i].algo = hash[i] % MINOTAUR_ALGO_COUNT;

    // Send the initial hash through the torture garden
    traverse_garden(&garden, hash, &garden.nodes[0]);

	// Truncate the result
    memcpy(output, hash, len);
}
