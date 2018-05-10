#ifndef BLAKECOIN_H
#define BLAKECOIN_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void blakecoin_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
