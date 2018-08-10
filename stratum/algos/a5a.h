#ifndef SCRYPT_H
#define SCRYPT_H
#include <stdint.h>
#ifdef __cplusplus
extern "C" {
#endif

void a5a_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif