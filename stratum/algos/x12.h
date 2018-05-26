#ifndef X12_H
#define X12_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void x12_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
