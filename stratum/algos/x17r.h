#ifndef X17R_H
#define X17R_H

#ifdef __cplusplus
extern "C"{
#endif

#include <stdint.h>

#include <stddef.h>

void x17r_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
