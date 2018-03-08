#ifndef DEEP_H
#define DEEP_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void deep_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif

