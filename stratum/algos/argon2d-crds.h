#ifndef ARGON2D_CRDS_H
#define ARGON2D_CRDS_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void argon2d_crds_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif

