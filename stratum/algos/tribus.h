#ifndef TRIBUS_H
#define TRIBUS_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void tribus_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
