#ifndef DEDALHASH_H
#define DEDALHASH_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void dedal_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif // DEDALHASH_H