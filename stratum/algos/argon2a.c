#include <stdio.h>
#include <stdlib.h>
#include <stdint.h>
#include <string.h>

#include "sysendian.h"

#include "argon2a.h"
#include "ar2/argon2.h"
#include "ar2/core.h"
#include "ar2/ar2-scrypt-jane.h"

#define _ALIGN(x) __attribute__ ((aligned(x)))

#define T_COSTS 2
#define M_COSTS 16
#define MASK 8
#define ZERO 0

inline void argon_call(void *out, void *in, void *salt, int type)
{
	argon2_context context = { 0 };

	context.out = (uint8_t *)out;
	context.pwd = (uint8_t *)in;
	context.salt = (uint8_t *)salt;

	argon2_ctx(&context, type);
}

void argon2a_hash(const char* input, char* output, uint32_t len)
{
	uint32_t _ALIGN(32) hashA[8], hashB[8];

	my_scrypt((unsigned char *)input, len,
		(unsigned char *)input, len,
		(unsigned char *)hashA);

	argon_call(hashB, hashA, hashA, (hashA[0] & MASK) == ZERO);

	my_scrypt((const unsigned char *)hashB, 32,
		(const unsigned char *)hashB, 32,
		(unsigned char *)output);
}

