#ifndef X11EVO_H
#define X11EVO_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

void x11evo_hash(const char* input, char* output, uint32_t len);

#ifdef __cplusplus
}
#endif

#endif
