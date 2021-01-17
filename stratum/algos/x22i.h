#ifndef X22I_H
#define X22I_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void x22i_hash(const char* input, char* output, uint32_t len);
void x22i_hash_hex(const char *input, char *output, unsigned int len);

#ifdef __cplusplus
}
#endif

#endif
