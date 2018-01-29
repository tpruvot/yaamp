// PHC submission:  POMELO v2
// Designed by:     Hongjun Wu (Email: wuhongjun@gmail.com)
// This code was written by Hongjun Wu on Jan 31, 2015.

// This codes gives the C implementation of POMELO on 64-bit platform (little-endian)

// m_cost is an integer, 0 <= m_cost <= 25; the memory size is 2**(13+m_cost) bytes
// t_cost is an integer, 0 <= t_cost <= 25; the number of steps is roughly:  2**(8+m_cost+t_cost)
// For the machine today, it is recommended that: 5 <= t_cost + m_cost <= 25;
// one may use the parameters: m_cost = 15; t_cost = 0; (256 MegaByte memory)

#include <stdlib.h>
#include <string.h>
#include <stdio.h>
#include "pomelo.h"

#define F0(i)  {               \
	i0 = ((i) - 0*4)  & mask1; \
	i1 = ((i) - 2*4)  & mask1; \
	i2 = ((i) - 3*4)  & mask1; \
	i3 = ((i) - 7*4)  & mask1; \
	i4 = ((i) - 13*4) & mask1; \
	S[i0+1] = ((S[i1+0] ^ S[i2+0]) + S[i3+0]) ^ S[i4+0];         \
	S[i0+2] = ((S[i1+1] ^ S[i2+1]) + S[i3+1]) ^ S[i4+1];         \
	S[i0+3] = ((S[i1+2] ^ S[i2+2]) + S[i3+2]) ^ S[i4+2];         \
	S[i0+0] = ((S[i1+3] ^ S[i2+3]) + S[i3+3]) ^ S[i4+3];         \
	S[i0+0] = (S[i0+0] << 17) | (S[i0+0] >> 47);  \
	S[i0+1] = (S[i0+1] << 17) | (S[i0+1] >> 47);  \
	S[i0+2] = (S[i0+2] << 17) | (S[i0+2] >> 47);  \
	S[i0+3] = (S[i0+3] << 17) | (S[i0+3] >> 47);  \
}

#define F(i)  {                \
	i0 = ((i) - 0*4)  & mask1; \
	i1 = ((i) - 2*4)  & mask1; \
	i2 = ((i) - 3*4)  & mask1; \
	i3 = ((i) - 7*4)  & mask1; \
	i4 = ((i) - 13*4) & mask1; \
	S[i0+0] += ((S[i1+0] ^ S[i2+0]) + S[i3+0]) ^ S[i4+0];         \
	S[i0+1] += ((S[i1+1] ^ S[i2+1]) + S[i3+1]) ^ S[i4+1];         \
	S[i0+2] += ((S[i1+2] ^ S[i2+2]) + S[i3+2]) ^ S[i4+2];         \
	S[i0+3] += ((S[i1+3] ^ S[i2+3]) + S[i3+3]) ^ S[i4+3];         \
	temp = S[i0+3];         \
	S[i0+3] = S[i0+2];      \
	S[i0+2] = S[i0+1];      \
	S[i0+1] = S[i0+0];      \
	S[i0+0] = temp;         \
	S[i0+0] = (S[i0+0] << 17) | (S[i0+0] >> 47);  \
	S[i0+1] = (S[i0+1] << 17) | (S[i0+1] >> 47);  \
	S[i0+2] = (S[i0+2] << 17) | (S[i0+2] >> 47);  \
	S[i0+3] = (S[i0+3] << 17) | (S[i0+3] >> 47);  \
}

#define G(i,random_number)  {                                                       \
	index_global = ((random_number >> 16) & mask) << 2;                             \
	for (j = 0; j < 128; j = j+4)                                                   \
	{                                                                               \
		F(i+j);                                                                     \
		index_global   = (index_global + 4) & mask1;                                      \
		index_local    = (((i + j) >> 2) - 0x1000 + (random_number & 0x1fff)) & mask;     \
		index_local    = index_local << 2;                                                \
		S[i0+0]       += (S[index_local+0] << 1);                                   \
		S[i0+1]       += (S[index_local+1] << 1);                                   \
		S[i0+2]       += (S[index_local+2] << 1);                                   \
		S[i0+3]       += (S[index_local+3] << 1);                                   \
		S[index_local+0] += (S[i0+0] << 2); \
		S[index_local+1] += (S[i0+1] << 2); \
		S[index_local+2] += (S[i0+2] << 2); \
		S[index_local+3] += (S[i0+3] << 2); \
		S[i0+0]       += (S[index_global+0] << 1);                                   \
		S[i0+1]       += (S[index_global+1] << 1);                                   \
		S[i0+2]       += (S[index_global+2] << 1);                                   \
		S[i0+3]       += (S[index_global+3] << 1);                                   \
		S[index_global+0] += (S[i0+0] << 3); \
		S[index_global+1] += (S[i0+1] << 3); \
		S[index_global+2] += (S[i0+2] << 3); \
		S[index_global+3] += (S[i0+3] << 3); \
		random_number += (random_number << 2);                                      \
		random_number  = (random_number << 19) ^ (random_number >> 45)  ^ 3141592653589793238ULL;   \
	}                                                                               \
}

#define H(i, random_number)  {                                                      \
	index_global = ((random_number >> 16) & mask) << 2;                             \
	for (j = 0; j < 128; j = j+4)                                                   \
	{                                                                               \
		F(i+j);                                                                     \
		index_global   = (index_global + 4) & mask1;                                      \
		index_local    = (((i + j) >> 2) - 0x1000 + (random_number & 0x1fff)) & mask;     \
		index_local    = index_local << 2;                                                \
		S[i0+0]       += (S[index_local+0] << 1);                                   \
		S[i0+1]       += (S[index_local+1] << 1);                                   \
		S[i0+2]       += (S[index_local+2] << 1);                                   \
		S[i0+3]       += (S[index_local+3] << 1);                                   \
		S[index_local+0] += (S[i0+0] << 2); \
		S[index_local+1] += (S[i0+1] << 2); \
		S[index_local+2] += (S[i0+2] << 2); \
		S[index_local+3] += (S[i0+3] << 2); \
		S[i0+0]       += (S[index_global+0] << 1);                                   \
		S[i0+1]       += (S[index_global+1] << 1);                                   \
		S[i0+2]       += (S[index_global+2] << 1);                                   \
		S[i0+3]       += (S[index_global+3] << 1);                                   \
		S[index_global+0] += (S[i0+0] << 3); \
		S[index_global+1] += (S[i0+1] << 3); \
		S[index_global+2] += (S[i0+2] << 3); \
		S[index_global+3] += (S[i0+3] << 3); \
		random_number  = S[i3];              \
	}                                        \
}

int POMELO(void *out, size_t outlen, const void *in, size_t inlen, const void *salt, size_t saltlen, unsigned int t_cost, unsigned int m_cost);

int POMELO(void *out, size_t outlen, const void *in, size_t inlen, const void *salt, size_t saltlen, unsigned int t_cost, unsigned int m_cost)
{
	unsigned long long i,j,k,temp;
	unsigned long long i0,i1,i2,i3,i4;
	unsigned long long *S;
	unsigned long long random_number, index_global, index_local;
	unsigned long long state_size, mask, mask1, mask2;

	//check the size of password, salt and output. Password is at most 256 bytes; the salt is at most 32 bytes.
	if (inlen > 256 || saltlen > 64 || outlen > 256 || inlen < 0 || saltlen < 0 || outlen < 0) return 1;

	//Step 1: Initialize the state S
	state_size = 1ULL << (13+m_cost);    // state size is 2**(13+m_cost) bytes
	S = (unsigned long long *)malloc(state_size);
	mask  = (1ULL << (8+m_cost))  - 1;   // mask is used for modulation: modulo size_size/32;
	mask1 = (1ULL << (10+m_cost)) - 1;   // mask is used for modulation: modulo size_size/8;

	//Step 2: Load the password, salt, input/output sizes into the state S
	for (i = 0; i < inlen; i++)   ((unsigned char*)S)[i] = ((unsigned char*)in)[i];         // load password into S
	for (i = 0; i < saltlen; i++) ((unsigned char*)S)[inlen+i] = ((unsigned char*)salt)[i]; // load salt into S
	for (i = inlen+saltlen; i < 384; i++) ((unsigned char*)S)[i] = 0;
	((unsigned char*)S)[384] = inlen & 0xff;         // load password length (in bytes) into S;
	((unsigned char*)S)[385] = (inlen >> 8) & 0xff;  // load password length (in bytes) into S;
	((unsigned char*)S)[386] = saltlen;              // load salt length (in bytes) into S;
	((unsigned char*)S)[387] = outlen & 0xff;        // load output length (in bytes into S)
	((unsigned char*)S)[388] = (outlen >> 8) & 0xff; // load output length (in bytes into S)
	((unsigned char*)S)[389] = 0;
	((unsigned char*)S)[390] = 0;
	((unsigned char*)S)[391] = 0;

	((unsigned char*)S)[392] = 1;
	((unsigned char*)S)[393] = 1;
	for (i = 394; i < 416; i++) ((unsigned char*)S)[i] = ((unsigned char*)S)[i-1] + ((unsigned char*)S)[i-2];

	//Step 3: Expand the data into the whole state
	for (i = 13*4; i < (1ULL << (10+m_cost)); i=i+4)  F0(i);

	//Step 4: Update the state using function G
	random_number = 123456789ULL;
	for (i = 0; i < (1ULL << (9+m_cost+t_cost)); i=i+128) G(i,random_number);

	//Step 5: Update the state using function H
	for (i = 1ULL << (9+m_cost+t_cost);  i < (1ULL << (10+m_cost+t_cost)); i=i+128)  H(i,random_number);

	//Step 6: Update the state using function F
	for (i = 0; i < (1ULL << (10+m_cost)); i=i+4)  F(i);

	//Step 7: Generate the output
	memcpy(out, ((unsigned char*)S)+state_size-outlen, outlen);
	memset(S, 0, state_size);  // clear the memory
	free(S);          // free the memory

	return 0;
}

