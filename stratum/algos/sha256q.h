#ifndef SHA256Q_H
#define SHA256Q_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void sha256q_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
