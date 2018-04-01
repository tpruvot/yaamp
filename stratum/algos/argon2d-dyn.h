#ifndef ARGON2D_DYN_H
#define ARGON2D_DYN_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void argon2d_dyn_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif

