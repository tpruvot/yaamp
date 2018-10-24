#ifndef EXOSIS_H
#define EXOSIS_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void exosis_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
