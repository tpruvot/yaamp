#ifndef PENTABLAKE_H
#define PENTABLAKE_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void penta_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
