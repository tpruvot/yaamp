#ifndef HSR14_H
#define HSR14_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void hsr_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
