#ifndef VELVET_H
#define VELVET_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void velvet_hash(const char* input, char* output, uint32_t size);

#ifdef __cplusplus
}
#endif

#endif
