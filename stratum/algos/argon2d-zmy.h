#ifndef ARGON2D_ZMY_H
#define ARGON2D_ZMY_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void argon2d_zmy_hash(const char* input, char* output, unsigned int len);

#ifdef __cplusplus
}
#endif

#endif
