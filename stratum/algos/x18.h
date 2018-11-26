#ifndef X18_H
#define X18_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void x18_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
