#ifndef LYRA2ZZ_H
#define LYRA2ZZ_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void lyra2zz_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
