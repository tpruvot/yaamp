#ifndef M7M_H
#define M7M_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void m7m_hash(const char* input, char* output, uint32_t size);

#ifdef __cplusplus
}
#endif

#endif
