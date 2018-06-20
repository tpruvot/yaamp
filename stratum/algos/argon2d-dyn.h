#ifndef ARGON2D_H
#define ARGON2D_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void argon2d_dyn_hash(const char* input, char* output, unsigned int len);

#ifdef __cplusplus
}
#endif

#endif