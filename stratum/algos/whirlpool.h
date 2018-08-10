#ifndef WHIRLPOOL_H
#define WHIRLPOOL_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void whirlpool_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
