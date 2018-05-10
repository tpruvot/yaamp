#ifndef BASTION_H
#define BASTION_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void bastion_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
