#ifndef XEVAN_H
#define XEVAN_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void xevan_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
