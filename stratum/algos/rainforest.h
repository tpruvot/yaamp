#ifndef RAINFOREST_H
#define RAINFOREST_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void rf256_hash(void *out, const void *in, size_t len);
static inline void rainforest_hash(const char* input, char* output, uint32_t len) {
  rf256_hash(output, input, len);
}

#ifdef __cplusplus
}
#endif

#endif
