/*
 * Copyright 2012 Luke Dashjr
 *
 * This program is free software; you can redistribute it and/or modify it
 * under the terms of the standard MIT license.  See COPYING for more details.
 */
#include <arpa/inet.h>

#include <stdbool.h>
#include <stdio.h>
#include <stdint.h>
#include <string.h>

static const int8_t b58digits[] = {
	-1,-1,-1,-1,-1,-1,-1,-1, -1,-1,-1,-1,-1,-1,-1,-1,
	-1,-1,-1,-1,-1,-1,-1,-1, -1,-1,-1,-1,-1,-1,-1,-1,
	-1,-1,-1,-1,-1,-1,-1,-1, -1,-1,-1,-1,-1,-1,-1,-1,
	-1, 0, 1, 2, 3, 4, 5, 6,  7, 8,-1,-1,-1,-1,-1,-1,
	-1, 9,10,11,12,13,14,15, 16,-1,17,18,19,20,21,-1,
	22,23,24,25,26,27,28,29, 30,31,32,-1,-1,-1,-1,-1,
	-1,33,34,35,36,37,38,39, 40,41,42,43,-1,44,45,46,
	47,48,49,50,51,52,53,54, 55,56,57,-1,-1,-1,-1,-1,
};

static bool _blkmk_b58tobin(void *bin, size_t binsz, const char *b58, size_t b58sz)
{
	const unsigned char *b58u = (const unsigned char*)b58;
	unsigned char *binu = (unsigned char *)bin;
	size_t outisz = (binsz + 3) / 4;
	uint32_t outi[outisz];
	uint64_t t;
	uint32_t c;
	size_t i, j;
	uint8_t bytesleft = binsz % 4;
	uint32_t zeromask = ~((1 << ((bytesleft) * 8)) - 1);

	if (!b58sz)
		b58sz = strlen(b58);

	memset(outi, 0, outisz * sizeof(*outi));

	for (i = 0; i < b58sz; ++i)
	{
		if (b58u[i] & 0x80)
			// High-bit set on invalid digit
			return false;
		if (b58digits[b58u[i]] == -1)
			// Invalid base58 digit
			return false;
		c = b58digits[b58u[i]];
		for (j = outisz; j--; )
		{
			t = ((uint64_t)outi[j]) * 58 + c;
			c = (t & 0x3f00000000) >> 32;
			outi[j] = t & 0xffffffff;
		}
		if (c)
			// Output number too big (carry to the next int32)
			return false;
		if (outi[0] & zeromask)
			// Output number too big (last int32 filled too far)
			return false;
	}

	j = 0;
	switch (bytesleft) {
		case 3:
			*(binu++) = (outi[0] &   0xff0000) >> 16;
		case 2:
			*(binu++) = (outi[0] &     0xff00) >>  8;
		case 1:
			*(binu++) = (outi[0] &       0xff);
			++j;
		default:
			break;
	}

	for (; j < outisz; ++j)
	{
		*((uint32_t*)binu) = htonl(outi[j]);
		binu += sizeof(uint32_t);
	}
	return true;
}

bool base58_decode(const char *input, char *output)
{
	unsigned char output_bin[32] = { 0 };
	bool b = _blkmk_b58tobin(output_bin, 26, input, 0);
	output[0] = '\0';

	if(!b) return false;

	for(int i=2; i < 22; i++)
		sprintf(output+strlen(output), "%02x", output_bin[i]);

	return true;
}

bool is_base58(char *input)
{
	// All alphanumeric characters except "0", "O", "I" and "l"
	size_t i=0, len = strlen(input);
	char *c = input;
	while (i < len) {
		bool isdigit = (c[i] >= '1' && c[i] <= '9');
		bool isalpha = (c[i] >= 'a' && c[i] <= 'z') || (c[i] >= 'A' && c[i] <= 'Z');
		if (!isdigit && !isalpha) return false;
		if (c[i] == 'I' || c[i] == 'O' || c[i] == 'l') return false;
		i++;
	}
	return true;
}

