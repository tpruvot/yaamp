#ifndef POLYTIMOS_H
#define POLYTIMOS_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void polytimos_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
