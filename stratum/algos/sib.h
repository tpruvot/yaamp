#ifndef SIB_H
#define SIB_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void sib_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
