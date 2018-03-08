#ifndef DROPLP_H
#define DROPLP_H

#ifdef __cplusplus
extern "C" {
#endif

#include <stdint.h>

#define ARRAYLEN(array)     (sizeof(array)/sizeof((array)[0]))
#define WIDTH	(BITS/32)
static const unsigned int VERSION_MASK  = 0x00007FFF;
static const unsigned int POK_BOOL_MASK = 0x00008000;
static const unsigned int POK_DATA_MASK = 0xFFFF0000;

void drop_hash(const char* input, char* output, uint32_t len );
void drop_hash_512( uint8_t* input, uint8_t* output, uint32_t len );
uint32_t getleastsig32( uint8_t* buffer, unsigned int nIndex );

#ifdef __cplusplus
}
#endif

#endif
