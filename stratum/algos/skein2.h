#ifndef SKEIN2_H
#define SKEIN2_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void skein2_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
