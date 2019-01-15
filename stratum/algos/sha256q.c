
#include <stdlib.h>
#include <stdint.h>
#include <string.h>
#include <stdio.h>

#include "sha256.h"

#include <stdlib.h>

void sha256q_hash(const char* input, char* output, uint32_t len)
{
	unsigned char hash[64];

	SHA256_CTX ctx_sha256;
	SHA256_Init(&ctx_sha256);
	SHA256_Update(&ctx_sha256, input, len);
	SHA256_Final(hash, &ctx_sha256);

	SHA256_Init(&ctx_sha256);
	SHA256_Update(&ctx_sha256, hash, 32);
	SHA256_Final(hash, &ctx_sha256);

	SHA256_Init(&ctx_sha256);
	SHA256_Update(&ctx_sha256, hash, 32);
	SHA256_Final(hash, &ctx_sha256);

	SHA256_Init(&ctx_sha256);
	SHA256_Update(&ctx_sha256, hash, 32);
	SHA256_Final((unsigned char*)output, &ctx_sha256);
}

