#ifndef RENESIS_H
#define RENESIS_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void renesis_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
