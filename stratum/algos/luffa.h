#ifndef LUFFA_H
#define LUFFA_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void luffa_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
