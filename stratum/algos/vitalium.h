#ifndef VITALITY_H
#define VITALITY_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void vitalium_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
