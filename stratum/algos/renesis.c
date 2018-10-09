// Copyright (c) 2009-2010 Satoshi Nakamoto
// Copyright (c) 2009-2012 The Bitcoin developers
// Distributed under the MIT/X11 software license, see the accompanying
// file COPYING or http://www.opensource.org/licenses/mit-license.php.
// Copyright (c) 2018, hav0k Renesis Developers & Renesis Group
// This is the pre-fork hash for Renesis.
// http://renesis.io

#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "sha3/sph_skein.h"
#include "sha3/sph_keccak.h"
#include "sha3/sph_simd.h"
#include "sha3/sph_shavite.h"
#include "sha3/sph_jh.h"
#include "sha3/sph_cubehash.h"
#include "sha3/sph_fugue.h"
#include "sha3/sph_gost.h"


void renesis_hash(const char* input, char* output, uint32_t len)
{
	uint32_t hash[64];

	sph_skein512_context     ctx_skein;
	sph_keccak512_context    ctx_keccak;
	sph_simd512_context      ctx_simd;
	sph_shavite512_context   ctx_shavite;
	sph_jh512_context        ctx_jh;
	sph_cubehash512_context  ctx_cubehash;
	sph_fugue512_context	 ctx_fugue;
	sph_gost512_context	 ctx_gost;

	sph_skein512_init(&ctx_skein);
	sph_skein512 (&ctx_skein, input, 80);
	sph_skein512_close (&ctx_skein, hash);

	sph_keccak512_init(&ctx_keccak);
	sph_keccak512 (&ctx_keccak, hash, 64);
	sph_keccak512_close(&ctx_keccak, hash);

	sph_simd512_init (&ctx_simd);
	sph_simd512 (&ctx_simd, hash, 64);
	sph_simd512_close(&ctx_simd, hash);

	sph_shavite512_init (&ctx_shavite);
	sph_shavite512 (&ctx_shavite, hash, 64);
	sph_shavite512_close(&ctx_shavite, hash);

	sph_jh512_init(&ctx_jh);
	sph_jh512 (&ctx_jh, hash, 64);
	sph_jh512_close(&ctx_jh, hash);

	sph_cubehash512_init(&ctx_cubehash);
	sph_cubehash512 (&ctx_cubehash, hash, 64);
	sph_cubehash512_close(&ctx_cubehash, hash);

	sph_fugue512_init (&ctx_fugue);
	sph_fugue512 (&ctx_fugue, hash, 64);
	sph_fugue512_close (&ctx_fugue, hash);

	sph_gost512_init (&ctx_gost);
	sph_gost512 (&ctx_gost, hash, 64);
	sph_gost512_close(&ctx_gost, hash);

	memcpy(output, hash, 32);
}
