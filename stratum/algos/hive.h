#ifndef HIVE_H
#define HIVE_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void hive_hash(const char* input, char* output, uint32_t size);

#ifdef __cplusplus
}
#endif

#endif
