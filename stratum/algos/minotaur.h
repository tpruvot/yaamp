// Minotaur hash

#ifndef MINOTAUR_H
#define MINOTAUR_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void minotaur_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
