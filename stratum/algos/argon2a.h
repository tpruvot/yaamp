#ifndef ARGON2A_H
#define ARGON2A_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void argon2a_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
