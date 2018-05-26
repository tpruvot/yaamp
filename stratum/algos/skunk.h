#ifndef SKUNK_H
#define SKUNK_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void skunk_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
